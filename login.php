<?php
session_start();
include 'db.php';

header('Content-Type: text/html; charset=utf-8');
function h($s){ return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if($email === '' || $password === ''){
        $error = 'Provide email and password.';
    } else {
        $st = mysqli_prepare($conn, 'SELECT user_id, password_hash, name FROM Users WHERE email=?');
        mysqli_stmt_bind_param($st, 's', $email);
        mysqli_stmt_execute($st);
        $res = mysqli_stmt_get_result($st);
        $row = mysqli_fetch_assoc($res);
        if(!$row || !password_verify($password, $row['password_hash'])){
            $error = 'Invalid credentials.';
        } else {
            $user_id = (int)$row['user_id'];
            $name = $row['name'];
            // Find or create Customer
            $st2 = mysqli_prepare($conn, 'SELECT customer_id FROM Customer WHERE email=? LIMIT 1');
            mysqli_stmt_bind_param($st2, 's', $email);
            mysqli_stmt_execute($st2);
            $res2 = mysqli_stmt_get_result($st2);
            $r2 = mysqli_fetch_assoc($res2);
            if($r2){
                $customer_id = (int)$r2['customer_id'];
            } else {
                $st3 = mysqli_prepare($conn, 'INSERT INTO Customer (name,email,phone) VALUES (?,?,?)');
                $emptyPhone = '';
                mysqli_stmt_bind_param($st3, 'sss', $name, $email, $emptyPhone);
                mysqli_stmt_execute($st3);
                $customer_id = mysqli_insert_id($conn);
            }
            // Set session
            $_SESSION['user_id'] = $user_id;
            $_SESSION['customer_id'] = $customer_id;
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;

            header('Location: index.php');
            exit;
        }
    }
}

?>
<!doctype html>
<html><head><meta charset="utf-8"><title>Login</title></head><body>
<h2>Login</h2>
<?php if(!empty($error)): ?><p style="color:red"><?php echo h($error); ?></p><?php endif; ?>
<form method="post">
    <input name="email" type="email" placeholder="Email" required value="<?php echo h($_POST['email'] ?? ''); ?>"><br>
    <input name="password" type="password" placeholder="Password" required><br>
    <button type="submit">Login</button>
    </form>
<p>No account? <a href="register.php">Register</a></p>
</body></html>
