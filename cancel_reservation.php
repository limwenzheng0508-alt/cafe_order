<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
include 'db.php';

function bad($msg){
    echo json_encode(['success'=>false,'message'=>$msg]);
    exit;
}

// Only logged-in users can cancel
if(empty($_SESSION['customer_id'])){
    http_response_code(403);
    bad('You must be logged in to cancel a reservation');
}

if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    http_response_code(405);
    bad('Invalid request method');
}

$customer_id = (int)$_SESSION['customer_id'];
$reservation_id = (int)($_POST['reservation_id'] ?? 0);

if($reservation_id <= 0){
    http_response_code(400);
    bad('Invalid reservation ID');
}

try {
    // Verify the reservation belongs to this customer and is not already cancelled
    $stmt = mysqli_prepare($conn, 'SELECT status, reservation_date FROM Reservation WHERE reservation_id = ? AND customer_id = ?');
    mysqli_stmt_bind_param($stmt, 'ii', $reservation_id, $customer_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $reservation = mysqli_fetch_assoc($result);
    
    if(!$reservation){
        http_response_code(403);
        bad('Reservation not found or you do not have permission to cancel it');
    }
    
    if($reservation['status'] === 'cancelled'){
        http_response_code(400);
        bad('This reservation is already cancelled');
    }
    
    // Don't allow cancellation of past reservations
    $res_date = new DateTime($reservation['reservation_date']);
    $today = new DateTime('today');
    
    if($res_date < $today){
        http_response_code(400);
        bad('Cannot cancel a past reservation');
    }
    
    // Cancel the reservation
    $update_stmt = mysqli_prepare($conn, 'UPDATE Reservation SET status = ? WHERE reservation_id = ?');
    $status = 'cancelled';
    mysqli_stmt_bind_param($update_stmt, 'si', $status, $reservation_id);
    
    if(mysqli_stmt_execute($update_stmt)){
        echo json_encode([
            'success' => true,
            'message' => 'Reservation cancelled successfully'
        ]);
    } else {
        http_response_code(500);
        bad('Failed to cancel reservation: ' . mysqli_error($conn));
    }
    
} catch(Exception $e){
    http_response_code(500);
    bad('Database error: ' . $e->getMessage());
}
?>
