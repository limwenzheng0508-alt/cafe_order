<?php
session_start();
include 'db.php';
include 'qr_helper.php';

// Optional: Require admin authentication
// Uncomment next 3 lines if you want to restrict this to admins only
// if(empty($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true){
//     header('Location: admin_login.php');
//     exit;
// }

$all_qrcodes = get_all_qr_codes();
$total_qrcodes = count($all_qrcodes);

function h($s){ return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>QR Codes Archive</title>
<link rel="stylesheet" href="assets/style.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
    .page-container {
        max-width: 1200px;
        margin: 40px auto;
        padding: 0 20px;
    }
    .qr-list {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 16px;
        margin-top: 20px;
    }
    .qr-card {
        background: linear-gradient(180deg, #fff, #fffbf8);
        border-radius: 12px;
        padding: 16px;
        border: 1px solid rgba(6,182,212,0.1);
        transition: all .2s ease;
    }
    .qr-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(6,182,212,0.12);
        border-color: rgba(6,182,212,0.2);
    }
    .qr-card-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 12px;
    }
    .qr-card-id {
        font-weight: 700;
        color: var(--primary-1);
        font-size: 14px;
    }
    .qr-card-date {
        font-size: 12px;
        color: var(--muted);
    }
    .qr-info {
        font-size: 13px;
        color: var(--muted);
        margin-bottom: 8px;
    }
    .qr-info strong {
        color: #0f172a;
    }
    .qr-action {
        display: flex;
        gap: 8px;
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid rgba(6,182,212,0.1);
    }
    .qr-button {
        flex: 1;
        padding: 8px 12px;
        border: none;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
        text-decoration: none;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
        cursor: pointer;
        transition: all .2s ease;
    }
    .qr-button-view {
        background: linear-gradient(90deg, var(--primary-1), var(--primary-2));
        color: #fff;
    }
    .qr-button-view:hover {
        transform: scale(1.05);
        box-shadow: 0 4px 12px rgba(6,182,212,0.3);
    }
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: var(--muted);
    }
    .stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 12px;
        margin-bottom: 30px;
    }
    .stat-card {
        background: linear-gradient(135deg, #e0f2fe, #f0f9ff);
        padding: 16px;
        border-radius: 8px;
        text-align: center;
        border: 1px solid rgba(6,182,212,0.2);
    }
    .stat-value {
        font-size: 24px;
        font-weight: 700;
        color: var(--primary-1);
    }
    .stat-label {
        font-size: 12px;
        color: var(--muted);
        margin-top: 4px;
        text-transform: uppercase;
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
            <?php if(!empty($_SESSION['is_admin'])): ?>
                <a href="admin.php">Admin Panel</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<div class="page-container">
    <div class="card">
        <div class="meta">
            <h2>QR Codes Archive</h2>
            <p style="color: var(--muted); font-size: 13px">All generated reservation QR codes</p>
        </div>
        
        <?php if ($total_qrcodes > 0): ?>
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $total_qrcodes; ?></div>
                    <div class="stat-label">QR Codes</div>
                </div>
            </div>
            
            <div class="qr-list">
                <?php foreach ($all_qrcodes as $qr): ?>
                    <?php 
                    // Try to get reservation details
                    $res_id = $qr['reservation_id'];
                    $stmt = mysqli_prepare($conn, 'SELECT r.reservation_date, r.reservation_time, r.num_people, r.status, c.name FROM Reservation r JOIN Customer c ON r.customer_id = c.customer_id WHERE r.reservation_id = ?');
                    mysqli_stmt_bind_param($stmt, 'i', $res_id);
                    mysqli_stmt_execute($stmt);
                    $res = mysqli_stmt_get_result($stmt);
                    $reservation = mysqli_fetch_assoc($res);
                    ?>
                    <div class="qr-card">
                        <div class="qr-card-header">
                            <span class="qr-card-id">#<?php echo $res_id; ?></span>
                            <span class="qr-card-date">
                                <?php echo date('M d, Y', $qr['created_at']); ?>
                            </span>
                        </div>
                        
                        <?php if ($reservation): ?>
                            <div class="qr-info">
                                <strong><?php echo h($reservation['name']); ?></strong>
                            </div>
                            <div class="qr-info">
                                <strong>Date:</strong> <?php echo date('M j, Y', strtotime($reservation['reservation_date'])); ?>
                            </div>
                            <div class="qr-info">
                                <strong>Time:</strong> <?php echo date('g:i A', strtotime($reservation['reservation_time'] . ':00')); ?>
                            </div>
                            <div class="qr-info">
                                <strong>Guests:</strong> <?php echo (int)$reservation['num_people']; ?> people
                            </div>
                            <div class="qr-info">
                                <strong>Status:</strong> 
                                <span style="padding: 2px 6px; background: <?php echo $reservation['status'] === 'confirmed' ? '#dcfce7' : '#fee2e2'; ?>; color: <?php echo $reservation['status'] === 'confirmed' ? '#166534' : '#991b1b'; ?>; border-radius: 3px; font-size: 11px">
                                    <?php echo strtoupper($reservation['status']); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="qr-action">
                            <a href="view_qr.php?id=<?php echo $res_id; ?>" class="qr-button qr-button-view" target="_blank">
                                📱 View
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <h3>No QR codes yet</h3>
                <p>QR codes will appear here as reservations are made.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
