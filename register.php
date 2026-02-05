<?php
session_start();
include 'db.php';

header('Content-Type: text/html; charset=utf-8');

function h($s){ return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    if($name === '' || $email === '' || $password === ''){
        $error = 'Please provide name, email and password.';
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)){
        $error = 'Invalid email address.';
    } else {
        // Ensure Users table exists
        $create = "CREATE TABLE IF NOT EXISTS Users (
            user_id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        mysqli_query($conn, $create);

        // Check existing
        $st = mysqli_prepare($conn, 'SELECT user_id FROM Users WHERE email=?');
        mysqli_stmt_bind_param($st, 's', $email);
        mysqli_stmt_execute($st);
        $res = mysqli_stmt_get_result($st);
        if(mysqli_fetch_assoc($res)){
            $error = 'Email already registered.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $st2 = mysqli_prepare($conn, 'INSERT INTO Users (name,email,password_hash) VALUES (?,?,?)');
            mysqli_stmt_bind_param($st2, 'sss', $name, $email, $hash);
            mysqli_stmt_execute($st2);
            $user_id = mysqli_insert_id($conn);

            // Also create a Customer record for reservations
            $st3 = mysqli_prepare($conn, 'INSERT INTO Customer (name,email,phone) VALUES (?,?,?)');
            mysqli_stmt_bind_param($st3, 'sss', $name, $email, $phone);
            mysqli_stmt_execute($st3);
            $customer_id = mysqli_insert_id($conn);

            // Set session
            $_SESSION['user_id'] = $user_id;
            $_SESSION['customer_id'] = $customer_id;
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;
            $_SESSION['phone'] = $phone;

            header('Location: index.php');
            exit;
        }
    }
}

?>
<!doctype html>
<html><head><meta charset="utf-8"><title>Register</title></head><body>
<h2>Register</h2>
<?php if(!empty($error)): ?><p style="color:red"><?php echo h($error); ?></p><?php endif; ?>
<form method="post">
    <input name="name" placeholder="Name" required value="<?php echo h($_POST['name'] ?? ''); ?>"><br>
    <input name="email" type="email" placeholder="Email" required value="<?php echo h($_POST['email'] ?? ''); ?>"><br>
    <input name="phone" placeholder="Phone" value="<?php echo h($_POST['phone'] ?? ''); ?>"><br>
    <input name="password" type="password" placeholder="Password" required><br>
    <button type="submit">Register</button>
</form>
<p>Already have an account? <a href="login.php">Login</a></p>
</body></html>
