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

// Fetch all reservations for this customer
$reservations = [];
$sql = "SELECT r.reservation_id, r.reservation_date, r.reservation_time, r.num_people, r.table_numbers, r.status, r.created_at 
        FROM Reservation r 
        WHERE r.customer_id = ? 
        ORDER BY r.reservation_date DESC, r.reservation_time DESC";
$st = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($st, 'i', $customer_id);
mysqli_stmt_execute($st);
$res = mysqli_stmt_get_result($st);

while($row = mysqli_fetch_assoc($res)){
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
    $reservations[] = $row;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Reservations</title>
<link rel="stylesheet" href="assets/style.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
    .reservations-container {
        max-width: 1000px;
        margin: 40px auto;
        padding: 0 20px;
    }
    .reservation-card {
        background: linear-gradient(180deg, #fff, #fffbf8);
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
        border-left: 6px solid var(--primary-1);
        box-shadow: 0 4px 12px rgba(16,24,40,0.08);
    }
    .reservation-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 16px;
        gap: 16px;
    }
    .reservation-dates {
        flex: 1;
    }
    .reservation-dates h3 {
        margin: 0 0 6px 0;
        color: #1e293b;
        font-size: 16px;
    }
    .reservation-info {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 12px;
        margin-bottom: 14px;
    }
    .info-item {
        font-size: 13px;
        color: var(--muted);
    }
    .info-label {
        font-weight: 600;
        color: #0f172a;
        margin-bottom: 2px;
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
        background: linear-gradient(90deg, #10b981, #34d399);
        color: #fff;
    }
    .status-cancelled {
        background: #fee2e2;
        color: #991b1b;
    }
    .status-pending {
        background: #fef3c7;
        color: #92400e;
    }
    .table-numbers {
        background: linear-gradient(90deg, var(--primary-1), var(--primary-2));
        color: #fff;
        padding: 8px 12px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 14px;
    }
    .menu-items {
        margin-top: 14px;
        padding-top: 14px;
        border-top: 1px solid rgba(6,182,212,0.1);
    }
    .menu-items h4 {
        margin: 0 0 8px 0;
        font-size: 13px;
        color: var(--muted);
        text-transform: uppercase;
    }
    .menu-list {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    .menu-tag {
        background: #f0f9ff;
        color: var(--primary-1);
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 12px;
        border: 1px solid rgba(6,182,212,0.2);
    }
    .no-reservations {
        text-align: center;
        padding: 40px 20px;
        color: var(--muted);
    }
    .no-reservations h3 {
        margin-top: 0;
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
        transform: translateX(-2px);
    }
    .action-buttons {
        margin-top: 14px;
        padding-top: 14px;
        border-top: 1px solid rgba(6,182,212,0.1);
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    .action-button {
        display: inline-block;
        padding: 8px 12px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
        border: none;
        transition: all .2s ease;
    }
    .action-button.qr {
        background: linear-gradient(90deg, var(--primary-1), var(--primary-2));
        color: #fff;
    }
    .action-button.qr:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(6,182,212,0.3);
    }
    .action-button.cancel-btn {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fca5a5;
    }
    .action-button.cancel-btn:hover {
        background: #fecaca;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(239,68,68,0.2);
    }</style>
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
            <a href="history.php">History</a>
            <a href="logout.php">Logout</a>
        </nav>
    </div>
</header>

<div class="reservations-container">
    <a href="index.php" class="back-button">← Back to Home</a>
    
    <div class="card">
        <div class="meta">
            <h2>My Reservations</h2>
            <a href="history.php" class="back-button" style="background: linear-gradient(90deg, var(--primary-1), var(--primary-2)); color: #fff">📊 View Full History</a>
        </div>
        <div style="background: #e0f2fe; color: #0c4a6e; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 12px; border: 1px solid #06b6d4">
            ⏰ <strong>Note:</strong> QR codes are available for 24 hours after reservation. Please print or save them before they expire.
        </div>
            <small><?php echo h($user_name); ?></small>
        </div>
        
        <?php if(count($reservations) === 0): ?>
            <div class="no-reservations">
                <h3>No reservations yet</h3>
                <p>You haven't made any reservations. <a href="index.php">Make a reservation now!</a></p>
            </div>
        <?php else: ?>
            <?php foreach($reservations as $res): ?>
                <div class="reservation-card">
                    <div class="reservation-header">
                        <div class="reservation-dates">
                            <h3><?php echo date('D, M j, Y', strtotime($res['reservation_date'])); ?> at <?php echo date('g:i A', strtotime($res['reservation_time'] . ':00')); ?></h3>
                        </div>
                        <span class="status-badge status-<?php echo $res['status']; ?>">
                            <?php echo ucfirst($res['status']); ?>
                        </span>
                    </div>
                    
                    <div class="reservation-info">
                        <div class="info-item">
                            <div class="info-label">Guests</div>
                            <div><?php echo h($res['num_people']); ?> people</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Table Numbers</div>
                            <div class="table-numbers">
                                <?php 
                                $tables = explode(',', $res['table_numbers']);
                                echo implode(', ', array_map('trim', $tables));
                                ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Reservation ID</div>
                            <div style="font-family: monospace; font-weight: 600;">#<?php echo (int)$res['reservation_id']; ?></div>
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
                                            × <?php echo (int)$item['quantity']; ?>
                                        <?php endif; ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="action-buttons">
                        <a href="view_qr.php?id=<?php echo (int)$res['reservation_id']; ?>" class="action-button qr">
                            📱 View QR Code
                        </a>
                        <?php 
                        $res_date = new DateTime($res['reservation_date']);
                        $today = new DateTime('today');
                        if($res_date >= $today && $res['status'] === 'confirmed'): 
                        ?>
                            <button type="button" class="action-button cancel-btn" onclick="cancelReservation(<?php echo (int)$res['reservation_id']; ?>)">
                                ✕ Cancel
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
async function cancelReservation(reservationId) {
    if (!confirm('Are you sure you want to cancel this reservation? This action cannot be undone.')) {
        return;
    }
    
    try {
        const response = await fetch('cancel_reservation.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `reservation_id=${encodeURIComponent(reservationId)}`
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert(data.message + '\nThe page will refresh.');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        alert('Failed to cancel reservation: ' + error.message);
    }
}
</script>

</body>
</html>
