<?php
session_start();
include 'db.php';

// Simple QR code verification page for staff
// In a real app, you'd use a QR code scanning library
// For now, this allows manual input of reservation ID

$verified = false;
$reservation = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $res_id = isset($_POST['reservation_id']) ? (int)$_POST['reservation_id'] : 0;
    
    if ($res_id <= 0) {
        $error = 'Invalid reservation ID';
    } else {
        // Fetch reservation
        $stmt = mysqli_prepare($conn, 'SELECT r.*, c.name, c.phone FROM Reservation r JOIN Customer c ON r.customer_id = c.customer_id WHERE r.reservation_id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $res_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $reservation = mysqli_fetch_assoc($result);
        
        if ($reservation) {
            if ($reservation['status'] === 'confirmed') {
                $verified = true;
            } else {
                $error = 'Reservation status: ' . strtoupper($reservation['status']);
            }
        } else {
            $error = 'Reservation not found';
        }
    }
}

function h($s){ return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>QR Code Verification</title>
<link rel="stylesheet" href="assets/style.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
    .page-container {
        max-width: 700px;
        margin: 60px auto;
        padding: 0 20px;
    }
    .card {
        background: linear-gradient(180deg, #fff, #fffbf8);
        border-radius: 12px;
        padding: 30px;
        box-shadow: 0 10px 30px rgba(16,24,40,0.12);
    }
    .form-group {
        margin-bottom: 20px;
    }
    .form-group label {
        display: block;
        font-weight: 600;
        margin-bottom: 8px;
        color: #0f172a;
    }
    .form-group input {
        width: 100%;
        padding: 12px 14px;
        border: 1px solid rgba(16,24,40,0.06);
        border-radius: 8px;
        font-size: 14px;
        background: linear-gradient(180deg, #fff, #fffaf6);
    }
    .form-group input:focus {
        outline: none;
        border-color: var(--primary-1);
        box-shadow: 0 0 0 3px rgba(6,182,212,0.1);
    }
    .button-group {
        display: flex;
        gap: 10px;
    }
    .btn {
        flex: 1;
        padding: 12px 16px;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all .2s ease;
        font-size: 14px;
    }
    .btn-primary {
        background: linear-gradient(90deg, var(--primary-1), var(--primary-2));
        color: #fff;
    }
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(6,182,212,0.3);
    }
    .btn-secondary {
        background: #f1f5f9;
        color: #0f172a;
        border: 1px solid rgba(16,24,40,0.06);
    }
    .btn-secondary:hover {
        background: #e2e8f0;
    }
    .alert {
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-size: 14px;
    }
    .alert-error {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }
    .alert-success {
        background: #dcfce7;
        color: #166534;
        border: 1px solid #86efac;
    }
    .reservation-details {
        background: linear-gradient(135deg, #e0f2fe, #f0f9ff);
        padding: 20px;
        border-radius: 8px;
        margin-top: 20px;
    }
    .detail-row {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid rgba(6,182,212,0.1);
    }
    .detail-row:last-child {
        border-bottom: none;
    }
    .detail-label {
        font-weight: 600;
        color: #0f172a;
    }
    .detail-value {
        color: var(--muted);
        text-align: right;
    }
    .badge {
        display: inline-block;
        padding: 8px 12px;
        background: linear-gradient(90deg, #10b981, #34d399);
        color: #fff;
        border-radius: 6px;
        font-weight: 600;
        font-size: 13px;
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
            <a href="admin_login.php">Admin</a>
        </nav>
    </div>
</header>

<div class="page-container">
    <div class="card">
        <h2>Verify Reservation</h2>
        <p style="color: var(--muted); margin-bottom: 20px">
            Scan or enter the reservation ID to verify the customer check-in
        </p>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                ⚠️ <?php echo h($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="res_id">Reservation ID</label>
                <input 
                    type="number" 
                    id="res_id" 
                    name="reservation_id" 
                    placeholder="e.g., 12345" 
                    required 
                    autofocus
                    <?php if ($verified && $reservation): ?>
                    value="<?php echo (int)$reservation['reservation_id']; ?>"
                    <?php endif; ?>
                >
            </div>
            
            <div class="button-group">
                <button type="submit" class="btn btn-primary">Check ID</button>
                <button type="reset" class="btn btn-secondary">Clear</button>
            </div>
        </form>
        
        <?php if ($verified && $reservation): ?>
            <div class="alert alert-success">
                ✓ Valid Reservation
            </div>
            
            <div class="reservation-details">
                <div class="detail-row">
                    <span class="detail-label">Reservation ID</span>
                    <span class="detail-value">#<?php echo (int)$reservation['reservation_id']; ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Customer Name</span>
                    <span class="detail-value"><?php echo h($reservation['name']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Phone</span>
                    <span class="detail-value"><?php echo h($reservation['phone']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Date & Time</span>
                    <span class="detail-value">
                        <?php echo date('M j, Y', strtotime($reservation['reservation_date'])); ?> 
                        @ <?php echo date('g:i A', strtotime($reservation['reservation_time'] . ':00')); ?>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Number of Guests</span>
                    <span class="detail-value"><?php echo (int)$reservation['num_people']; ?> people</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Tables Assigned</span>
                    <span class="detail-value"><?php echo h($reservation['table_numbers']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Status</span>
                    <span class="detail-value">
                        <span class="badge"><?php echo strtoupper($reservation['status']); ?></span>
                    </span>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
