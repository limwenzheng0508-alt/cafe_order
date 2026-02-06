<?php
session_start();
include 'db.php';

// Check if user is logged in
if(empty($_SESSION['customer_id'])){
    header('Location: login.php');
    exit;
}

$customer_id = (int)$_SESSION['customer_id'];
$user_name = $_SESSION['user_name'] ?? '';

function h($s){ return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// Get filter parameter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all'; // all, upcoming, completed, cancelled

// Fetch customer info
$stmt = mysqli_prepare($conn, 'SELECT * FROM Customer WHERE customer_id = ?');
mysqli_stmt_bind_param($stmt, 'i', $customer_id);
mysqli_stmt_execute($stmt);
$cust_res = mysqli_stmt_get_result($stmt);
$customer = mysqli_fetch_assoc($cust_res);

// Fetch all reservations
$sql = "SELECT r.reservation_id, r.reservation_date, r.reservation_time, r.num_people, r.table_numbers, r.status, r.created_at 
        FROM Reservation r 
        WHERE r.customer_id = ? 
        ORDER BY r.reservation_date DESC, r.reservation_time DESC";
$st = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($st, 'i', $customer_id);
mysqli_stmt_execute($st);
$res = mysqli_stmt_get_result($st);

$all_reservations = [];
$upcoming_count = 0;
$completed_count = 0;
$cancelled_count = 0;
$total_reservations = 0;

while($row = mysqli_fetch_assoc($res)){
    $total_reservations++;
    
    // Categorize by status and date
    if ($row['status'] === 'cancelled') {
        $cancelled_count++;
    } else {
        $res_date = new DateTime($row['reservation_date']);
        $today = new DateTime('today');
        
        if ($res_date >= $today && $row['status'] === 'confirmed') {
            $upcoming_count++;
            $row['category'] = 'upcoming';
        } else {
            $completed_count++;
            $row['category'] = $row['status'] === 'cancelled' ? 'cancelled' : 'past';
        }
    }
    
    // Get menu items for this reservation
    $menu_items = [];
    $sql_menu = "SELECT m.menuitem_id, m.name, m.price, rm.quantity 
                 FROM ReservationMenuItem rm 
                 JOIN MenuItem m ON rm.menuitem_id = m.menuitem_id 
                 WHERE rm.reservation_id = ?";
    $st_menu = mysqli_prepare($conn, $sql_menu);
    mysqli_stmt_bind_param($st_menu, 'i', $row['reservation_id']);
    mysqli_stmt_execute($st_menu);
    $res_menu = mysqli_stmt_get_result($st_menu);
    
    while($menu_row = mysqli_fetch_assoc($res_menu)){
        $menu_items[] = $menu_row;
    }
    
    $row['menu_items'] = $menu_items;
    $all_reservations[] = $row;
}

// Filter reservations
$reservations = [];
foreach ($all_reservations as $res) {
    if ($filter === 'all') {
        $reservations[] = $res;
    } elseif ($filter === 'upcoming' && $res['category'] === 'upcoming') {
        $reservations[] = $res;
    } elseif ($filter === 'past' && ($res['category'] === 'past' || $res['status'] === 'confirmed')) {
        $res_date = new DateTime($res['reservation_date']);
        $today = new DateTime('today');
        if ($res_date < $today) {
            $reservations[] = $res;
        }
    } elseif ($filter === 'cancelled' && $res['status'] === 'cancelled') {
        $reservations[] = $res;
    }
}

// Calculate total spent
$spent_sql = "SELECT COALESCE(SUM(m.price * rm.quantity), 0) AS total_spent
              FROM ReservationMenuItem rm
              JOIN MenuItem m ON rm.menuitem_id = m.menuitem_id
              JOIN Reservation r ON rm.reservation_id = r.reservation_id
              WHERE r.customer_id = ? AND r.status = 'confirmed'";
$spent_stmt = mysqli_prepare($conn, $spent_sql);
mysqli_stmt_bind_param($spent_stmt, 'i', $customer_id);
mysqli_stmt_execute($spent_stmt);
$spent_result = mysqli_stmt_get_result($spent_stmt);
$spent_row = mysqli_fetch_assoc($spent_result);
$total_spent = floatval($spent_row['total_spent']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reservation History</title>
<link rel="stylesheet" href="assets/style.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
    .history-container {
        max-width: 1100px;
        margin: 40px auto;
        padding: 0 20px;
    }
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 14px;
        margin-bottom: 30px;
    }
    .stat-card {
        background: linear-gradient(135deg, #e0f2fe, #f0f9ff);
        border: 1px solid rgba(6,182,212,0.2);
        border-radius: 10px;
        padding: 18px;
        text-align: center;
    }
    .stat-value {
        font-size: 28px;
        font-weight: 700;
        color: var(--primary-1);
        margin-bottom: 4px;
    }
    .stat-label {
        font-size: 12px;
        color: var(--muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .filter-tabs {
        display: flex;
        gap: 8px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }
    .filter-btn {
        padding: 10px 16px;
        border: 1px solid rgba(16,24,40,0.1);
        border-radius: 8px;
        background: #fff;
        color: var(--muted);
        font-weight: 600;
        font-size: 13px;
        cursor: pointer;
        transition: all .2s ease;
        text-decoration: none;
        display: inline-block;
    }
    .filter-btn:hover {
        border-color: var(--primary-1);
        color: var(--primary-1);
    }
    .filter-btn.active {
        background: linear-gradient(90deg, var(--primary-1), var(--primary-2));
        color: #fff;
        border-color: var(--primary-1);
    }
    .reservation-card {
        background: linear-gradient(180deg, #fff, #fffbf8);
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 14px;
        border-left: 6px solid var(--primary-1);
        box-shadow: 0 4px 12px rgba(16,24,40,0.08);
        transition: all .2s ease;
    }
    .reservation-card:hover {
        box-shadow: 0 6px 16px rgba(16,24,40,0.12);
        transform: translateY(-1px);
    }
    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 12px;
        gap: 12px;
    }
    .card-date {
        font-size: 16px;
        font-weight: 600;
        color: #1e293b;
    }
    .status-badge {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
    }
    .status-confirmed {
        background: linear-gradient(90deg, #dcfce7, #bbf7d0);
        color: #166534;
    }
    .status-cancelled {
        background: #fee2e2;
        color: #991b1b;
    }
    .status-pending {
        background: #fef3c7;
        color: #92400e;
    }
    .card-info {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 14px;
        margin-bottom: 14px;
    }
    .info-item {
        font-size: 13px;
    }
    .info-label {
        font-weight: 600;
        color: #0f172a;
        margin-bottom: 2px;
    }
    .info-value {
        color: var(--muted);
    }
    .menu-items {
        margin-top: 14px;
        padding-top: 14px;
        border-top: 1px solid rgba(6,182,212,0.1);
    }
    .menu-items h4 {
        margin: 0 0 8px 0;
        font-size: 12px;
        color: var(--muted);
        text-transform: uppercase;
    }
    .menu-list {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }
    .menu-tag {
        background: #f0f9ff;
        color: var(--primary-1);
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 11px;
        border: 1px solid rgba(6,182,212,0.2);
    }
    .qr-link {
        display: inline-block;
        padding: 8px 12px;
        background: linear-gradient(90deg, var(--primary-1), var(--primary-2));
        color: #fff;
        border-radius: 6px;
        text-decoration: none;
        font-size: 12px;
        font-weight: 600;
        margin-top: 8px;
        transition: all .2s ease;
    }
    .qr-link:hover {
        transform: scale(1.05);
    }
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: var(--muted);
    }
    .back-button {
        display: inline-block;
        margin-bottom: 20px;
        padding: 10px 14px;
        background: #fff;
        border: 1px solid rgba(16,24,40,0.06);
        border-radius: 8px;
        color: var(--primary-1);
        text-decoration: none;
        font-size: 13px;
        cursor: pointer;
        transition: all .2s ease;
    }
    .back-button:hover {
        background: var(--primary-1);
        color: #fff;
    }
</style>
</head>
<body>

<!-- Header -->
<header class="site-header">
    <div class="container">
        <div class="logo">
            <svg viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
                <defs><linearGradient id="g" x1="0" x2="1"><stop offset="0" stop-color="#06b6d4"/><stop offset="1" stop-color="#3b82f6"/></linearGradient></defs>
                <circle cx="32" cy="24" r="12" fill="url(#g)" />
                <rect x="12" y="36" width="40" height="14" rx="6" fill="#fefefe" opacity="0.95" />
            </svg>
            <span>Café Reservations</span>
        </div>
        <nav class="site-nav">
            <a href="index.php">Home</a>
            <a href="my_reservations.php">My Reservations</a>
            <a href="logout.php">Logout</a>
        </nav>
    </div>
</header>

<div class="history-container">
    <a href="index.php" class="back-button">← Back to Home</a>
    
    <div class="card">
        <div class="meta">
            <h2>Reservation History</h2>
            <small><?php echo h($user_name); ?></small>
        </div>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_reservations; ?></div>
                <div class="stat-label">Total Reservations</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $upcoming_count; ?></div>
                <div class="stat-label">Upcoming</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $completed_count; ?></div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">RM<?php echo number_format($total_spent, 2); ?></div>
                <div class="stat-label">Total Spent</div>
            </div>
        </div>
        
        <!-- QR Code Note -->
        <div style="background: #e0f2fe; color: #0c4a6e; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 12px; border: 1px solid #06b6d4">
            ⏰ <strong>Note:</strong> QR codes are available for 24 hours after reservation. Please print or save them before they expire.
        </div>
        
        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <a href="?filter=all" class="filter-btn <?php echo ($filter === 'all') ? 'active' : ''; ?>">
                All (<?php echo $total_reservations; ?>)
            </a>
            <a href="?filter=upcoming" class="filter-btn <?php echo ($filter === 'upcoming') ? 'active' : ''; ?>">
                Upcoming (<?php echo $upcoming_count; ?>)
            </a>
            <a href="?filter=past" class="filter-btn <?php echo ($filter === 'past') ? 'active' : ''; ?>">
                Past (<?php echo $completed_count; ?>)
            </a>
            <a href="?filter=cancelled" class="filter-btn <?php echo ($filter === 'cancelled') ? 'active' : ''; ?>">
                Cancelled (<?php echo $cancelled_count; ?>)
            </a>
        </div>
        
        <!-- Reservations List -->
        <?php if(count($reservations) === 0): ?>
            <div class="empty-state">
                <h3>No reservations found</h3>
                <p>
                    <?php if ($filter === 'all'): ?>
                        You haven't made any reservations yet.
                    <?php else: ?>
                        No <?php echo $filter; ?> reservations.
                    <?php endif; ?>
                </p>
                <a href="index.php" class="back-button" style="margin-top: 20px">Make a reservation</a>
            </div>
        <?php else: ?>
            <?php foreach($reservations as $res): ?>
                <div class="reservation-card">
                    <div class="card-header">
                        <div>
                            <div class="card-date">
                                <?php echo date('D, M j, Y', strtotime($res['reservation_date'])); ?> 
                                @ <?php echo date('g:i A', strtotime($res['reservation_time'] . ':00')); ?>
                            </div>
                            <div style="font-size: 12px; color: var(--muted); margin-top: 2px">
                                Booked <?php echo date('M j, Y g:i A', strtotime($res['created_at'])); ?>
                            </div>
                        </div>
                        <span class="status-badge status-<?php echo $res['status']; ?>">
                            <?php echo strtoupper($res['status']); ?>
                        </span>
                    </div>
                    
                    <div class="card-info">
                        <div class="info-item">
                            <div class="info-label">Reservation ID</div>
                            <div class="info-value" style="font-family: monospace; font-weight: 600">#<?php echo (int)$res['reservation_id']; ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Number of Guests</div>
                            <div class="info-value"><?php echo h($res['num_people']); ?> people</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Tables</div>
                            <div class="info-value">
                                <span style="background: linear-gradient(90deg, var(--primary-1), var(--primary-2)); color: #fff; padding: 4px 8px; border-radius: 4px; display: inline-block; font-weight: 600; font-size: 11px">
                                    <?php 
                                    $tables = explode(',', $res['table_numbers']);
                                    echo implode(', ', array_map('trim', $tables));
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <?php if(count($res['menu_items']) > 0): ?>
                        <div class="menu-items">
                            <h4>Pre-ordered Items</h4>
                            <div class="menu-list">
                                <?php foreach($res['menu_items'] as $item): ?>
                                    <span class="menu-tag">
                                        <?php echo h($item['name']); ?>
                                        <strong>(RM<?php echo h($item['price']); ?>)</strong>
                                        <?php if((int)$item['quantity'] > 1): ?>
                                            ×<?php echo (int)$item['quantity']; ?>
                                        <?php endif; ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if($res['status'] === 'confirmed'): ?>
                        <a href="view_qr.php?id=<?php echo (int)$res['reservation_id']; ?>" class="qr-link">
                            📱 View QR Code
                        </a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
