<?php
session_start();
include 'db.php';
include 'qr_helper.php';

// Check if user is trying to view a specific QR code
$res_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($res_id <= 0) {
    http_response_code(404);
    die('Invalid reservation ID');
}

// If user is logged in, verify they own this reservation
if (!empty($_SESSION['customer_id'])) {
    $customer_id = (int)$_SESSION['customer_id'];
    $stmt = mysqli_prepare($conn, 'SELECT customer_id, created_at FROM Reservation WHERE reservation_id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $res_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    
    if (!$row || (int)$row['customer_id'] !== $customer_id) {
        http_response_code(403);
        die('You do not have permission to view this QR code');
    }
    
    // Check if QR code is still valid (within 24 hours)
    $created_time = strtotime($row['created_at']);
    $current_time = time();
    $hours_passed = ($current_time - $created_time) / 3600;
    
    if ($hours_passed > 24) {
        http_response_code(403);
        die('QR code access expired. QR codes are available for 24 hours only. Please make a new reservation if needed.<br><a href="index.php">Back to home</a>');
    }
} else {
    // For guests: allow access but check if reservation is recent (within 24 hours)
    // This allows guests to view their QR code on the same day they booked
    $stmt = mysqli_prepare($conn, 'SELECT reservation_id, created_at FROM Reservation WHERE reservation_id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $res_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    
    if (!$row) {
        http_response_code(404);
        die('Reservation not found');
    }
    
    // Check if reservation is within 24 hours
    $created_time = strtotime($row['created_at']);
    $current_time = time();
    $hours_passed = ($current_time - $created_time) / 3600;
    
    if ($hours_passed > 24) {
        http_response_code(403);
        die('QR code access expired. QR codes are available for 24 hours only. Please make a new reservation if needed.<br><a href="index.php">Back to home</a>');
    }
}

// Get reservation details
$stmt = mysqli_prepare($conn, 'SELECT * FROM Reservation WHERE reservation_id = ?');
mysqli_stmt_bind_param($stmt, 'i', $res_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$reservation = mysqli_fetch_assoc($res);

if (!$reservation) {
    http_response_code(404);
    die('Reservation not found');
}

// Get all QR codes and find the one for this reservation
$all_qrcodes = get_all_qr_codes();
$qr_info = null;

foreach ($all_qrcodes as $qr) {
    if ($qr['reservation_id'] === $res_id) {
        $qr_info = $qr;
        break;
    }
}

function h($s){ return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reservation QR Code</title>
<link rel="stylesheet" href="assets/style.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
    .qr-container {
        max-width: 600px;
        margin: 60px auto;
        padding: 0 20px;
    }
    .qr-card {
        background: linear-gradient(180deg, #fff, #fffbf8);
        border-radius: 12px;
        padding: 40px;
        text-align: center;
        box-shadow: 0 10px 30px rgba(16,24,40,0.12);
    }
    .qr-image-wrapper {
        margin: 30px 0;
        padding: 20px;
        background: #fff;
        border-radius: 10px;
        border: 2px solid var(--primary-1);
        display: inline-block;
    }
    .qr-image {
        max-width: 300px;
        width: 100%;
        height: auto;
        display: block;
    }
    .qr-alt-text {
        background: #f0f9ff;
        padding: 15px;
        border-radius: 8px;
        margin: 20px 0;
        font-size: 13px;
        color: var(--muted);
        line-height: 1.6;
        word-break: break-all;
        font-family: monospace;
    }
    .reservation-info {
        background: linear-gradient(135deg, #e0f2fe, #f0f9ff);
        padding: 20px;
        border-radius: 8px;
        margin: 20px 0;
        text-align: left;
    }
    .info-row {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid rgba(6,182,212,0.1);
    }
    .info-row:last-child {
        border-bottom: none;
    }
    .info-label {
        font-weight: 600;
        color: #0f172a;
    }
    .info-value {
        color: var(--muted);
    }
    .button-group {
        display: flex;
        gap: 10px;
        justify-content: center;
        margin-top: 30px;
        flex-wrap: wrap;
    }
    .btn {
        display: inline-block;
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 14px;
        cursor: pointer;
        border: none;
        text-decoration: none;
        transition: all .2s ease;
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
        background: #fff;
        color: var(--primary-1);
        border: 1px solid var(--primary-1);
    }
    .btn-secondary:hover {
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
            <?php if(!empty($_SESSION['user_id'])): ?>
                <a href="my_reservations.php">My Reservations</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<div class="qr-container">
    <div class="qr-card">
        <h2>Reservation QR Code</h2>
        <p style="color: var(--muted); margin-bottom: 20px">
            Show this to the staff when you arrive
        </p>
        
        <div class="reservation-info">
            <div class="info-row">
                <span class="info-label">Reservation ID:</span>
                <span class="info-value">#<?php echo h($reservation['reservation_id']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Date:</span>
                <span class="info-value"><?php echo date('M j, Y', strtotime($reservation['reservation_date'])); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Time:</span>
                <span class="info-value"><?php echo date('g:i A', strtotime($reservation['reservation_time'] . ':00')); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Tables:</span>
                <span class="info-value"><?php echo h($reservation['table_numbers']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Guests:</span>
                <span class="info-value"><?php echo h($reservation['num_people']); ?> people</span>
            </div>
        </div>
        
        <?php if ($qr_info && $qr_info['file']): ?>
            <div class="qr-image-wrapper">
                <?php 
                $file_ext = strtolower(pathinfo($qr_info['file'], PATHINFO_EXTENSION));
                if ($file_ext === 'png' || $file_ext === 'jpg' || $file_ext === 'jpeg' || $file_ext === 'gif'): 
                ?>
                    <img src="<?php echo get_qr_code_url($qr_info['file']); ?>" alt="Reservation QR Code" class="qr-image">
                <?php elseif ($file_ext === 'txt'): ?>
                    <!-- Text-based QR code representation -->
                    <pre style="background: #f0f9ff; padding: 15px; border-radius: 8px; overflow-x: auto; font-size: 11px; line-height: 1.2; color: var(--muted)">
<?php 
$qr_file_path = __DIR__ . '/qrcodes/' . $qr_info['file'];
if (file_exists($qr_file_path)) {
    echo h(file_get_contents($qr_file_path));
}
?>
                    </pre>
                <?php endif; ?>
            </div>
            <p style="color: #10b981; font-weight: 600; margin: 15px 0 0 0">
                ✓ QR code generated and saved
            </p>
        <?php else: ?>
            <div style="background: #fef3c7; color: #92400e; padding: 20px; border-radius: 8px; margin: 20px 0">
                <p style="margin: 0; font-size: 14px">
                    ℹ️ QR code is being generated. Please wait or refresh the page.
                </p>
            </div>
        <?php endif; ?>
        
        <div class="button-group">
            <button class="btn btn-primary" onclick="downloadQRCode()">
                ⬇️ Save QR Code
            </button>
            <?php if(!empty($_SESSION['user_id'])): ?>
                <a href="my_reservations.php" class="btn btn-secondary">
                    ← Back to Reservations
                </a>
            <?php else: ?>
                <a href="index.php" class="btn btn-secondary">
                    ← Back to Home
                </a>
            <?php endif; ?>
        </div>
        
        <p style="color: var(--muted); font-size: 12px; margin-top: 30px; margin-bottom: 0">
            ⏰ This QR code is available for 24 hours. Make sure to save it before it expires.
        </p>
    </div>
</div>

<script>
function downloadQRCode() {
    const qrImage = document.querySelector('#qrDisplay img, #qrDisplay canvas');
    
    if (!qrImage) {
        alert('QR code image not found');
        return;
    }
    
    // Create a canvas if we need to convert to PNG
    let canvas = qrImage.tagName === 'CANVAS' ? qrImage : null;
    
    if (!canvas) {
        // If it's an image, create a canvas to convert it
        canvas = document.createElement('canvas');
        canvas.width = qrImage.width;
        canvas.height = qrImage.height;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(qrImage, 0, 0);
    }
    
    // Download as PNG
    const link = document.createElement('a');
    link.href = canvas.toDataURL('image/png');
    link.download = 'reservation-qr-code-<?php echo $res_id; ?>.png';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

</body>
</html>
