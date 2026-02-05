<?php
// Export reservations to Excel (.xlsx if PhpSpreadsheet is available, otherwise .xls HTML fallback)
include 'db.php';

// Build same filters as admin.php (name, date, status)
$where = [];
if(!empty($_GET['name'])){ $namef = mysqli_real_escape_string($conn, $_GET['name']); $where[] = "c.name LIKE '%$namef%'"; }
if(!empty($_GET['date'])){ $datef = mysqli_real_escape_string($conn, $_GET['date']); $where[] = "r.reservation_date='$datef'"; }
if(!empty($_GET['status'])){ $statusf = mysqli_real_escape_string($conn, $_GET['status']); $where[] = "r.status='$statusf'"; }
$whereSql = '';
if(!empty($where)) $whereSql = 'WHERE ' . implode(' AND ', $where);

$q = "SELECT r.reservation_id, r.reservation_date, r.reservation_time, r.num_people, r.table_numbers, r.status, c.name, c.phone, c.email FROM Reservation r JOIN Customer c ON r.customer_id=c.customer_id $whereSql ORDER BY r.reservation_date DESC, r.reservation_time DESC";
$res = mysqli_query($conn, $q);

// Try to use PhpSpreadsheet if installed
if(file_exists(__DIR__.'/vendor/autoload.php')){
    require __DIR__.'/vendor/autoload.php';
    try{
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $headers = ['ID','Name','Phone','Email','Date','Time','People','Tables','Status'];
        $col = 1;
        foreach($headers as $h){ $sheet->setCellValueByColumnAndRow($col++, 1, $h); }
        $rowNum = 2;
        while($row = mysqli_fetch_assoc($res)){
            $sheet->setCellValueByColumnAndRow(1, $rowNum, $row['reservation_id']);
            $sheet->setCellValueByColumnAndRow(2, $rowNum, $row['name']);
            $sheet->setCellValueByColumnAndRow(3, $rowNum, $row['phone']);
            $sheet->setCellValueByColumnAndRow(4, $rowNum, $row['email']);
            $sheet->setCellValueByColumnAndRow(5, $rowNum, $row['reservation_date']);
            $sheet->setCellValueByColumnAndRow(6, $rowNum, $row['reservation_time']);
            $sheet->setCellValueByColumnAndRow(7, $rowNum, $row['num_people']);
            $sheet->setCellValueByColumnAndRow(8, $rowNum, $row['table_numbers']);
            $sheet->setCellValueByColumnAndRow(9, $rowNum, $row['status']);
            $rowNum++;
        }
        $filename = 'reservations_' . date('Ymd_His') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
        exit;
    } catch(Exception $e){
        // fall through to HTML fallback
    }
}

// Fallback: simple Excel-compatible HTML table (.xls)
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="reservations_' . date('Ymd_His') . '.xls"');
echo "<table border=1><tr><th>ID</th><th>Name</th><th>Phone</th><th>Email</th><th>Date</th><th>Time</th><th>People</th><th>Tables</th><th>Status</th></tr>\n";
while($row = mysqli_fetch_assoc($res)){
    echo '<tr>';
    echo '<td>' . htmlspecialchars($row['reservation_id']) . '</td>';
    echo '<td>' . htmlspecialchars($row['name']) . '</td>';
    echo '<td>' . htmlspecialchars($row['phone']) . '</td>';
    echo '<td>' . htmlspecialchars($row['email']) . '</td>';
    echo '<td>' . htmlspecialchars($row['reservation_date']) . '</td>';
    echo '<td>' . htmlspecialchars($row['reservation_time']) . '</td>';
    echo '<td>' . htmlspecialchars($row['num_people']) . '</td>';
    echo '<td>' . htmlspecialchars($row['table_numbers']) . '</td>';
    echo '<td>' . htmlspecialchars($row['status']) . '</td>';
    echo '</tr>\n';
}
echo '</table>';
exit;
