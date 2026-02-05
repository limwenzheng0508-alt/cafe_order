<?php
session_start();
unset($_SESSION['is_admin']);
unset($_SESSION['admin_csrf']);
session_regenerate_id(true);
header('Location: admin_login.php');
exit;
