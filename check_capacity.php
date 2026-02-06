<?php
header('Content-Type: application/json; charset=utf-8');
include 'db.php';

// Configuration
$people_per_table = 6;
$total_tables = 50;
$max_capacity = $people_per_table * $total_tables; // 300 people

if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    http_response_code(405);
    echo json_encode(['success'=>false,'message'=>'Invalid request method']);
    exit;
}

$date = trim($_POST['date'] ?? '');
$time = trim($_POST['time'] ?? '');
$num_people = (int)($_POST['num_people'] ?? 0);

// Validate inputs
if(empty($date) || empty($time) || $num_people <= 0){
    echo json_encode(['success'=>false,'message'=>'Missing required fields']);
    exit;
}

// Validate date and time formats
$d = DateTime::createFromFormat('Y-m-d', $date);
$t = DateTime::createFromFormat('H:i', $time);
if(!$d || $d->format('Y-m-d') !== $date){
    echo json_encode(['success'=>false,'message'=>'Invalid date format']);
    exit;
}
if(!$t || $t->format('H:i') !== $time){
    echo json_encode(['success'=>false,'message'=>'Invalid time format']);
    exit;
}

try {
    // Check total people already booked at this time slot
    $sql_check = "SELECT COALESCE(SUM(num_people),0) AS total_people FROM Reservation WHERE reservation_date=? AND reservation_time=? AND status='confirmed'";
    $st = mysqli_prepare($conn, $sql_check);
    mysqli_stmt_bind_param($st, 'ss', $date, $time);
    mysqli_stmt_execute($st);
    $res = mysqli_stmt_get_result($st);
    $row = mysqli_fetch_assoc($res);
    $booked_people = (int)($row['total_people'] ?? 0);
    
    $available_capacity = $max_capacity - $booked_people;
    $tables_booked = (int)ceil($booked_people / $people_per_table);
    $tables_remaining = $total_tables - $tables_booked;
    $tables_needed = (int)ceil($num_people / $people_per_table);

    // Check if restaurant is at capacity
    if($booked_people >= $max_capacity){
        echo json_encode([
            'success'=>false,
            'full'=>true,
            'message'=>'餐厅已满，请15分钟后重试',
            'booked_people'=>$booked_people,
            'max_capacity'=>$max_capacity,
            'available_capacity'=>0
        ]);
        exit;
    }

    // Check if this request can be accommodated
    if($num_people > $available_capacity){
        echo json_encode([
            'success'=>false,
            'message'=>"此时段仅剩 {$available_capacity} 个座位，无法容纳 {$num_people} 人",
            'booked_people'=>$booked_people,
            'max_capacity'=>$max_capacity,
            'available_capacity'=>$available_capacity
        ]);
        exit;
    }

    // Check table availability
    if($tables_needed > $tables_remaining){
        echo json_encode([
            'success'=>false,
            'message'=>"此时段仅剩 {$tables_remaining} 张桌子，无法容纳 {$num_people} 人",
            'booked_people'=>$booked_people,
            'max_capacity'=>$max_capacity,
            'available_capacity'=>$available_capacity,
            'tables_remaining'=>$tables_remaining,
            'tables_needed'=>$tables_needed
        ]);
        exit;
    }

    // Available!
    echo json_encode([
        'success'=>true,
        'message'=>'座位充足，可以预订',
        'booked_people'=>$booked_people,
        'max_capacity'=>$max_capacity,
        'available_capacity'=>$available_capacity,
        'tables_remaining'=>$tables_remaining,
        'tables_needed'=>$tables_needed
    ]);

} catch(Exception $e){
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Database error: '.$e->getMessage()]);
}
?>
