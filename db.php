<?php
$server = "localhost";
$user = "root";
$pass = "alwin0508";
$dbname = "cafe_db";

$conn = mysqli_connect($server, $user, $pass, $dbname);
if (!$conn) {
    error_log("DB connection failed: " . mysqli_connect_error());
    // Minimal message to client if ever exposed; more details are in server logs
    die("Database connection error");
}

// Use exceptions for mysqli errors and set proper charset
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
mysqli_set_charset($conn, 'utf8mb4');
?>
