<?php
header('Content-Type: application/json');
require_once 'auth.php';
requireLogin();

$host = 'localhost'; $dbname = 'hr'; $username = 'root'; $password = '';

$pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);

$claim_id = $_GET['claim_id'] ?? 0;

$stmt = $pdo->prepare("SELECT description, amount FROM expense_details WHERE claim_id = ? ORDER BY id");
$stmt->execute([$claim_id]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>