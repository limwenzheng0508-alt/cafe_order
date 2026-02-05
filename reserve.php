<?php
header('Content-Type: application/json; charset=utf-8');
include 'db.php';
session_start();

// Configuration
$people_per_table = 6;
$total_tables = 50;

function bad($msg){
    echo json_encode(['success'=>false,'message'=>$msg]);
    exit;
}

if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    http_response_code(405);
    bad('Invalid request method');
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$date = trim($_POST['date'] ?? '');
$time = trim($_POST['time'] ?? '');
$num_people = (int)($_POST['num_people'] ?? 0);
$menuitems = $_POST['menuitem'] ?? [];
$csrf_token = $_POST['csrf_token'] ?? '';
// country for phone validation
$country = $_POST['country'] ?? 'MY';

// CSRF check
if(empty($csrf_token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)){
    http_response_code(400);
    bad('Invalid CSRF token');
}

// Basic validation
if($name === '' || $phone === '' || $date === '' || $time === '' || $num_people <= 0){
    http_response_code(400);
    bad('Please provide name, phone, date, time and number of people.');
}

// Validate date and time formats (YYYY-MM-DD and HH:MM)
$d = DateTime::createFromFormat('Y-m-d', $date);
$t = DateTime::createFromFormat('H:i', $time);
if(!$d || $d->format('Y-m-d') !== $date) bad('Invalid date format');
if(!$t || $t->format('H:i') !== $time) bad('Invalid time format');

// booking window: not in past and not more than 365 days ahead
$today = new DateTime('today');
$max = (new DateTime('today'))->modify('+365 days');
if($d < $today || $d > $max){
    http_response_code(400);
    bad('Reservation date must be between today and one year from today.');
}

// simple email and phone validation
if($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)){
    http_response_code(400);
    bad('Invalid email address');
}
// normalize phone
$phone_raw = $phone;
$phone = preg_replace('/[^0-9+]/', '', $phone);
function valid_phone_by_country($country, $phone){
    $digits = preg_replace('/[^0-9+]/','',$phone);
    if($country === 'MY'){
        return preg_match('/^\+?6?0?[0-9]{9,10}$/', $digits) || preg_match('/^[0-9]{9,10}$/', $digits);
    }
    if($country === 'US'){
        return preg_match('/^\+?1?[2-9][0-9]{9}$/', $digits) || preg_match('/^[2-9][0-9]{9}$/', $digits);
    }
    if($country === 'GB'){
        return preg_match('/^\+?44?[0-9]{9,10}$/', $digits) || preg_match('/^[0-9]{9,10}$/', $digits);
    }
    return (strlen(preg_replace('/[^0-9]/','',$digits)) >= 7);
}
if(!valid_phone_by_country($country, $phone)){
    http_response_code(400);
    bad('Invalid phone number for selected country');
}

$tables_needed = (int)ceil($num_people / $people_per_table);

try {
    // Start transaction to avoid race conditions
    mysqli_begin_transaction($conn, MYSQLI_TRANS_START_READ_WRITE);

    // Re-check availability with FOR UPDATE to lock matching rows
    $sql_check = "SELECT COALESCE(SUM(tables_needed),0) AS booked_tables FROM Reservation WHERE reservation_date=? AND reservation_time=? AND status='confirmed' FOR UPDATE";
    $st = mysqli_prepare($conn, $sql_check);
    mysqli_stmt_bind_param($st, 'ss', $date, $time);
    mysqli_stmt_execute($st);
    $res = mysqli_stmt_get_result($st);
    $row = mysqli_fetch_assoc($res);
    $tables_booked = (int)($row['booked_tables'] ?? 0);

    $tables_remaining = $total_tables - $tables_booked;
    if($tables_needed > $tables_remaining){
        mysqli_rollback($conn);
        bad('Not enough tables available at this time.');
    }

    // Gather occupied table numbers for that slot and lock rows
    $occupied = [];
    $sql_existing = "SELECT table_numbers FROM Reservation WHERE reservation_date=? AND reservation_time=? AND status='confirmed' FOR UPDATE";
    $st2 = mysqli_prepare($conn, $sql_existing);
    mysqli_stmt_bind_param($st2, 'ss', $date, $time);
    mysqli_stmt_execute($st2);
    $res_existing = mysqli_stmt_get_result($st2);
    while($r = mysqli_fetch_assoc($res_existing)){
        if(!empty($r['table_numbers'])){
            $parts = array_filter(array_map('trim', explode(',', $r['table_numbers'])));
            foreach($parts as $p) $occupied[(int)$p] = true;
        }
    }

    // Assign first available tables
    $assigned = [];
    for($i=1;$i<=$total_tables && count($assigned) < $tables_needed;$i++){
        if(empty($occupied[$i])){
            $assigned[] = $i;
        }
    }
    if(count($assigned) < $tables_needed){
        mysqli_rollback($conn);
        bad('Unable to assign tables (concurrent booking). Please try again.');
    }
    $table_numbers_str = implode(',', $assigned);

    // Insert customer (simple approach)
    // Use logged-in customer's id if available, otherwise insert a new Customer
    if(!empty($_SESSION['customer_id'])){
        $customer_id = (int)$_SESSION['customer_id'];
        // optionally update customer info
        $upd = mysqli_prepare($conn, "UPDATE Customer SET name=?, email=?, phone=? WHERE customer_id=?");
        mysqli_stmt_bind_param($upd, 'sssi', $name, $email, $phone, $customer_id);
        mysqli_stmt_execute($upd);
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO Customer (name,email,phone) VALUES (?,?,?)");
        mysqli_stmt_bind_param($stmt, "sss", $name, $email, $phone);
        mysqli_stmt_execute($stmt);
        $customer_id = mysqli_insert_id($conn);
        $_SESSION['customer_id'] = $customer_id;
    }

    // Insert reservation
    $stmt2 = mysqli_prepare($conn, "INSERT INTO Reservation (customer_id,reservation_date,reservation_time,num_people,tables_needed,table_numbers,status) VALUES (?,?,?,?,?,?,'confirmed')");
    $types = 'issiis'; // i:customer_id, s:date, s:time, i:num_people, i:tables_needed, s:table_numbers
    mysqli_stmt_bind_param($stmt2, $types, $customer_id, $date, $time, $num_people, $tables_needed, $table_numbers_str);
    mysqli_stmt_execute($stmt2);
    $reservation_id = mysqli_insert_id($conn);

    // Insert menu items if provided -- validate ids exist
    if(!empty($menuitems) && is_array($menuitems)){
        $stmt_check = mysqli_prepare($conn, "SELECT COUNT(*) AS cnt FROM MenuItem WHERE menuitem_id = ?");
        $stmt3 = mysqli_prepare($conn, "INSERT INTO ReservationMenuItem (reservation_id,menuitem_id,quantity) VALUES (?,?,?)");
        foreach($menuitems as $mi){
            $mid = (int)$mi;
            if($mid <= 0) continue;
            mysqli_stmt_bind_param($stmt_check, 'i', $mid);
            mysqli_stmt_execute($stmt_check);
            $resc = mysqli_stmt_get_result($stmt_check);
            $rc = mysqli_fetch_assoc($resc);
            if(((int)$rc['cnt']) === 0) continue; // skip invalid id
            $qty = 1;
            mysqli_stmt_bind_param($stmt3, 'iii', $reservation_id, $mid, $qty);
            mysqli_stmt_execute($stmt3);
        }
    }

    mysqli_commit($conn);
    echo json_encode(['success'=>true,'reservation_id'=>$reservation_id,'table_number'=>$table_numbers_str]);

} catch(Exception $e){
    mysqli_rollback($conn);
    error_log('Reservation error: '.$e->getMessage());
    http_response_code(500);
    bad('Server error while processing reservation.');
}

?>
