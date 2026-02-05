<?php
include 'config.php';
include 'db.php';

session_start();
// Session-based admin auth
if(empty($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true){
    header('Location: admin_login.php');
    exit;
}

$people_per_table = 6;
$total_tables = 50;

// Handle POST actions: cancel or reassign (require admin CSRF)
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && !empty($_POST['reservation_id'])){
    $token = $_POST['admin_csrf'] ?? '';
    if(empty($_SESSION['admin_csrf']) || !hash_equals($_SESSION['admin_csrf'], $token)){
        header('Location: admin.php?msg=' . urlencode('Invalid CSRF token'));
        exit;
    }
    $rid = (int)$_POST['reservation_id'];
    if($_POST['action'] === 'cancel'){
        $st = mysqli_prepare($conn, "UPDATE Reservation SET status='cancelled' WHERE reservation_id=?");
        mysqli_stmt_bind_param($st, 'i', $rid);
        mysqli_stmt_execute($st);
        header('Location: admin.php');
        exit;
    }
    if($_POST['action'] === 'reassign' && isset($_POST['table_numbers'])){
        $new = trim($_POST['table_numbers']);
        // parse table numbers
        $parts = array_filter(array_map('trim', explode(',', $new)));
        $newTables = [];
        foreach($parts as $p){
            if(!ctype_digit($p)) continue;
            $n = (int)$p;
            if($n < 1 || $n > $total_tables) continue;
            $newTables[$n] = true;
        }
        if(empty($newTables)){
            header('Location: admin.php');
            exit;
        }

        // get reservation slot
        $s = mysqli_prepare($conn, 'SELECT reservation_date,reservation_time FROM Reservation WHERE reservation_id=? LIMIT 1');
        mysqli_stmt_bind_param($s, 'i', $rid);
        mysqli_stmt_execute($s);
        $rres = mysqli_stmt_get_result($s);
        $rrow = mysqli_fetch_assoc($rres);
        if(!$rrow){ header('Location: admin.php'); exit; }
        $rdate = $rrow['reservation_date'];
        $rtime = $rrow['reservation_time'];

        // collect occupied tables for this slot, excluding current reservation
        $occupied = [];
        $q = mysqli_prepare($conn, "SELECT table_numbers FROM Reservation WHERE reservation_date=? AND reservation_time=? AND status='confirmed' AND reservation_id<>? ");
        mysqli_stmt_bind_param($q, 'ssi', $rdate, $rtime, $rid);
        mysqli_stmt_execute($q);
        $resq = mysqli_stmt_get_result($q);
        while($rr = mysqli_fetch_assoc($resq)){
            if(empty($rr['table_numbers'])) continue;
            $p = array_filter(array_map('trim', explode(',', $rr['table_numbers'])));
            foreach($p as $t) $occupied[(int)$t] = true;
        }

        // check conflicts
        $conflict = false;
        foreach(array_keys($newTables) as $t){
            if(!empty($occupied[$t])) { $conflict = true; break; }
        }
        if($conflict){
            // simple feedback via query string
            header('Location: admin.php?msg=' . urlencode('Selected tables conflict with other reservations'));
            exit;
        }

        $table_numbers_str = implode(',', array_keys($newTables));
        $tables_needed = count($newTables);
        $up = mysqli_prepare($conn, 'UPDATE Reservation SET table_numbers=?, tables_needed=? WHERE reservation_id=?');
        mysqli_stmt_bind_param($up, 'sii', $table_numbers_str, $tables_needed, $rid);
        mysqli_stmt_execute($up);
        header('Location: admin.php');
        exit;
    }
}

// Filters from GET
$filters = [];
$where = [];
$params = [];
if(!empty($_GET['name'])){ $namef = mysqli_real_escape_string($conn, $_GET['name']); $where[] = "c.name LIKE '%$namef%'"; }
if(!empty($_GET['date'])){ $datef = mysqli_real_escape_string($conn, $_GET['date']); $where[] = "r.reservation_date='$datef'"; }
if(!empty($_GET['status'])){ $statusf = mysqli_real_escape_string($conn, $_GET['status']); $where[] = "r.status='$statusf'"; }

$whereSql = '';
if(!empty($where)) $whereSql = 'WHERE ' . implode(' AND ', $where);

$q = "SELECT r.reservation_id, r.reservation_date, r.reservation_time, r.num_people, r.table_numbers, r.status, c.name, c.phone, c.email FROM Reservation r JOIN Customer c ON r.customer_id=c.customer_id $whereSql ORDER BY r.reservation_date DESC, r.reservation_time DESC LIMIT 500";
$res = mysqli_query($conn, $q);

// CSV export
if(isset($_GET['export']) && $_GET['export'] === 'csv'){
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="reservations.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Name','Phone','Email','Date','Time','People','Tables','Status']);
    $rs = mysqli_query($conn, $q);
    while($row = mysqli_fetch_assoc($rs)){
        fputcsv($out, [$row['reservation_id'],$row['name'],$row['phone'],$row['email'],$row['reservation_date'],$row['reservation_time'],$row['num_people'],$row['table_numbers'],$row['status']]);
    }
    fclose($out);
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>Admin - Reservations</title>
        <link rel="stylesheet" href="assets/style.css">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
        <style>table{border-collapse:collapse;width:100%} th,td{border:1px solid #e6edf3;padding:10px;} th{background:linear-gradient(90deg,#faf8ff,#fff);} form.inline{display:inline}</style>
</head>
<body>

<header class="site-header">
    <div class="container">
        <div class="logo">
            <svg viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
                <defs><linearGradient id="ag2" x1="0" x2="1"><stop offset="0" stop-color="#06b6d4"/><stop offset="1" stop-color="#3b82f6"/></linearGradient></defs>
                <circle cx="32" cy="24" r="12" fill="url(#ag2)" />
                <rect x="12" y="36" width="40" height="14" rx="6" fill="#fff" opacity="0.95" />
            </svg>
            <span>Admin Panel</span>
        </div>
        <nav class="site-nav"><a href="index.php">Home</a><a href="admin_logout.php">Logout</a></nav>
    </div>
</header>

<div class="container">
    <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:12px">
        <div>
            <h2 style="margin:0">Reservations (Admin)</h2>
            <div class="small">Signed in as <strong><?php echo htmlspecialchars($admin_user); ?></strong>. Change credentials in <code>config.php</code>.</div>
        </div>
        <div style="display:flex; gap:8px; align-items:center">
            <a class="btn secondary" href="index.php">View Site</a>
            <a class="btn" href="admin_logout.php">Sign Out</a>
        </div>
    </div>

    <form method="get" style="margin-bottom:12px;">
        <input name="name" placeholder="Search name" value="<?php echo htmlspecialchars($_GET['name'] ?? ''); ?>">
        <input name="date" type="date" value="<?php echo htmlspecialchars($_GET['date'] ?? ''); ?>">
        <select name="status">
            <option value="">All</option>
            <option value="confirmed" <?php if(($_GET['status'] ?? '')==='confirmed') echo 'selected'; ?>>Confirmed</option>
            <option value="cancelled" <?php if(($_GET['status'] ?? '')==='cancelled') echo 'selected'; ?>>Cancelled</option>
        </select>
        <button type="submit">Filter</button>
        <a style="margin-left:8px;" href="admin.php?<?php echo http_build_query(array_merge($_GET, ['export'=>'csv'])); ?>">Export CSV</a>
        <a style="margin-left:8px;" href="export_excel.php?<?php echo http_build_query($_GET); ?>">Export Excel</a>
    </form>

    <?php if(!empty($_GET['msg'])): ?><p style="color:red"><?php echo htmlspecialchars($_GET['msg']); ?></p><?php endif; ?>

    <table>
        <thead>
            <tr><th>ID</th><th>Name</th><th>Phone</th><th>Email</th><th>Date</th><th>Time</th><th>People</th><th>Tables</th><th>Status</th><th>Action</th></tr>
        </thead>
        <tbody>
        <?php while($row = mysqli_fetch_assoc($res)): ?>
            <tr>
                <td><?php echo $row['reservation_id']; ?></td>
                <td><?php echo htmlspecialchars($row['name']); ?></td>
                <td><?php echo htmlspecialchars($row['phone']); ?></td>
                <td><?php echo htmlspecialchars($row['email']); ?></td>
                <td><?php echo htmlspecialchars($row['reservation_date']); ?></td>
                <td><?php echo htmlspecialchars($row['reservation_time']); ?></td>
                <td><?php echo htmlspecialchars($row['num_people']); ?></td>
                <td><?php echo htmlspecialchars($row['table_numbers']); ?></td>
                <td><?php echo htmlspecialchars($row['status']); ?></td>
                <td>
                <?php if($row['status'] === 'confirmed'): ?>
                    <form method="post" class="inline" style="margin:0">
                        <input type="hidden" name="reservation_id" value="<?php echo $row['reservation_id']; ?>">
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="admin_csrf" value="<?php echo htmlspecialchars($_SESSION['admin_csrf'] ?? ''); ?>">
                        <button type="submit" onclick="return confirm('Cancel this reservation?')">Cancel</button>
                    </form>
                    
                    <form method="post" class="inline" style="margin:0; margin-left:6px;">
                        <input type="hidden" name="reservation_id" value="<?php echo $row['reservation_id']; ?>">
                        <input type="hidden" name="action" value="reassign">
                        <input type="hidden" name="admin_csrf" value="<?php echo htmlspecialchars($_SESSION['admin_csrf'] ?? ''); ?>">
                        <input name="table_numbers" placeholder="e.g. 1,2" value="<?php echo htmlspecialchars($row['table_numbers']); ?>" style="width:80px">
                        <button type="submit" onclick="return confirm('Reassign tables to entered numbers?')">Reassign</button>
                    </form>
                <?php else: ?>
                    -
                <?php endif; ?>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</body>
</html>
