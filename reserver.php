<?php
include 'db.php';

// Max people per table
$people_per_table = 6;
$total_tables = 50;

if($_SERVER['REQUEST_METHOD'] === "POST"){
    $name = $_POST['name'];
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'];
    $date = $_POST['date'];
    $time = $_POST['time'];
    $num_people = (int)$_POST['num_people'];
    $menuitems = $_POST['menuitem'] ?? []; // array of menuitem_ids

    // Calculate tables needed
    $tables_needed = ceil($num_people / $people_per_table);

    // Check tables availability
    $sql_check = "SELECT SUM(tables_needed) as booked_tables 
                  FROM Reservation 
                  WHERE reservation_date='$date' AND reservation_time='$time' AND status='confirmed'";
    $res_check = mysqli_query($conn, $sql_check);
    $row = mysqli_fetch_assoc($res_check);
    $tables_booked = $row['booked_tables'] ?? 0;

    $tables_remaining = $total_tables - $tables_booked;

    if($tables_needed > $tables_remaining){
        echo json_encode(['success'=>false, 'message'=>'Not enough tables available at this time.']);
        exit;
    }

    // Assign table numbers (first available)
    $assigned_tables = [];
    $occupied = [];
    $sql_existing = "SELECT table_numbers FROM Reservation 
                     WHERE reservation_date='$date' AND reservation_time='$time' AND status='confirmed'";
    $res_existing = mysqli_query($conn, $sql_existing);
    while($row_table = mysqli_fetch_assoc($res_existing)){
        $occupied = array_merge($occupied, explode(',', $row_table['table_numbers']));
    }

    for($i=1; $i<=$total_tables; $i++){
        if(!in_array($i, $occupied)){
            $assigned_tables[] = $i;
            if(count($assigned_tables) == $tables_needed) break;
        }
    }
    $table_numbers_str = implode(',', $assigned_tables);

    // Insert customer
    $stmt = mysqli_prepare($conn, "INSERT INTO Customer (name,email,phone) VALUES (?,?,?)");
    mysqli_stmt_bind_param($stmt, "sss", $name, $email, $phone);
    mysqli_stmt_execute($stmt);
    $customer_id = mysqli_insert_id($conn);

    // Insert reservation
    $stmt2 = mysqli_prepare($conn, "INSERT INTO Reservation (customer_id,reservation_date,reservation_time,num_people,tables_needed,table_numbers,status) VALUES (?,?,?,?,?,?, 'confirmed')");
    mysqli_stmt_bind_param($stmt2, "isiiis", $customer_id, $date, $time, $num_people, $tables_needed, $table_numbers_str);
    mysqli_stmt_execute($stmt2);
    $reservation_id = mysqli_insert_id($conn);

    // Insert menu items if any
    foreach($menuitems as $menuitem_id){
        $stmt3 = mysqli_prepare($conn, "INSERT INTO ReservationMenuItem (reservation_id,menuitem_id,quantity) VALUES (?,?,1)");
        mysqli_stmt_bind_param($stmt3, "ii", $reservation_id, $menuitem_id);
        mysqli_stmt_execute($stmt3);
    }

    echo json_encode(['success'=>true,'reservation_id'=>$reservation_id,'table_number'=>$table_numbers_str]);
}
?>
