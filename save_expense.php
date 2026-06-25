<?php
header('Content-Type: application/json');
require_once 'auth.php';
requireLogin();

$host = 'localhost';
$dbname = 'hr';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Connection failed"]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$claim_id = isset($data['claim_id']) ? (int)$data['claim_id'] : 0;
$expense_data = $data['expense_data'] ?? [];

if ($claim_id <= 0) {
    echo json_encode(["success" => false, "message" => "Invalid Claim ID"]);
    exit;
}

// Delete existing expense details for this claim
$stmt = $pdo->prepare("DELETE FROM expense_details WHERE claim_id = ?");
$stmt->execute([$claim_id]);

// Insert new rows
if (!empty($expense_data)) {
    $stmt = $pdo->prepare("INSERT INTO expense_details (claim_id, description, amount) VALUES (?, ?, ?)");
    
    foreach ($expense_data as $row) {
        $description = trim($row['description'] ?? '');
        $amount = isset($row['amount']) ? (float)$row['amount'] : 0.00;
        
        if (!empty($description)) {
            $stmt->execute([$claim_id, $description, $amount]);
        }
    }
}

echo json_encode([
    "success" => true, 
    "message" => "Office section saved successfully"
]);
?>