<?php
require_once 'db.php';

$sql = "SELECT id, travel_order_no, EmpID, employeeName, BrCode, current_approval_stage FROM travel_orders WHERE BrCode='100' AND current_approval_stage NOT IN ('DH', 'HR', 'CEO_OR_DCEO') AND employeeName NOT IN (SELECT employeeName FROM employees WHERE level IN ('PH', 'NSM'))";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        echo "Found bad data: Order No: " . $row['travel_order_no'] . " | Emp: " . $row['employeeName'] . " | Stage: " . $row['current_approval_stage'] . "\n";
        
        // Fix it to 'DH'
        $updateSql = "UPDATE travel_orders SET current_approval_stage='DH' WHERE id=" . $row['id'];
        if ($conn->query($updateSql)) {
            echo "-> Fixed to DH\n";
        } else {
            echo "-> Failed to fix: " . $conn->error . "\n";
        }
    }
} else {
    echo "No bad data found.\n";
}
?>
