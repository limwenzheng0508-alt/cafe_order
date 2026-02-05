<?php
session_start();
include 'config.php';
include 'db.php';

function h($s){ return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

if(!empty($_SESSION['is_admin']) && $_SESSION['is_admin'] === true){
    header('Location: admin.php'); exit;
}

$error = '';
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';
    if($user === $admin_user && $pass === $admin_pass){
        $_SESSION['is_admin'] = true;
        // CSRF token for admin actions
        $_SESSION['admin_csrf'] = bin2hex(random_bytes(24));
        header('Location: admin.php'); exit;
    } else {
        $error = 'Invalid credentials';
    }
}

?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Admin Login</title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        .auth-page{min-height:80vh; display:flex; align-items:center; justify-content:center}
        .auth-card{width:100%; max-width:420px; padding:22px; border-radius:12px; box-shadow:0 14px 40px rgba(16,24,40,0.12); background:linear-gradient(180deg,#fff,#fffaf6)}
        .auth-card h3{margin:0 0 8px 0}
        .field{margin-bottom:12px}
        .field input{width:100%; padding:10px 12px; border-radius:8px; border:1px solid #e6e9ef}
        .error{color:var(--danger); margin-bottom:12px}
        .help{font-size:13px; color:var(--muted)}
    </style>
</head>
<body>

<header class="site-header">
    <div class="container">
        <div class="logo">
            <svg viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
                <defs><linearGradient id="ag" x1="0" x2="1"><stop offset="0" stop-color="#06b6d4"/><stop offset="1" stop-color="#3b82f6"/></linearGradient></defs>
                <circle cx="32" cy="24" r="12" fill="url(#ag)" />
                <rect x="12" y="36" width="40" height="14" rx="6" fill="#fff" opacity="0.95" />
            </svg>
            <span>Café Reservations</span>
        </div>
        <nav class="site-nav"><a href="index.php">Home</a></nav>
    </div>
</header>

<main class="auth-page">
    <div class="auth-card">
        <h3>Admin Login</h3>
        <?php if($error): ?><div class="error"><?php echo h($error); ?></div><?php endif; ?>
        <form method="post">
            <div class="field"><input name="username" placeholder="Username" required></div>
            <div class="field"><input name="password" type="password" placeholder="Password" required></div>
            <div style="display:flex; gap:8px; align-items:center; justify-content:flex-end">
                <button class="btn secondary" type="button" onclick="location.href='index.php'">Cancel</button>
                <button class="btn primary" type="submit">Sign In</button>
            </div>
        </form>
        <div class="help" style="margin-top:12px">Use the admin credentials from <code>config.php</code>. Change them after first login.</div>
    </div>
</main>

</body>
</html>
