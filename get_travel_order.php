<?php
require_once 'auth.php';
requireLogin();
require_once 'db.php';

header('Content-Type: application/json');

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid ID']);
    exit;
}

$stmt = $conn->prepare("SELECT * FROM travel_orders WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $order = $result->fetch_assoc();
    echo json_encode($order);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Travel order not found']);
}
