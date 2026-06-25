<?php
require_once 'db.php';

$employeeName = trim($_GET['employeeName'] ?? '');
$brCode       = trim($_GET['brCode']       ?? '');

if (empty($employeeName) || empty($brCode)) {
    echo 'TADA.—.—.0000';
    exit;
}

// Extract first name — first word, alphanumeric only, uppercase
$firstName = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', explode(' ', $employeeName)[0]));
if (empty($firstName)) $firstName = 'EMP';

$prefix = 'TADA.' . $brCode . '.' . $firstName . '.';

$stmt = $conn->prepare(
    "SELECT travel_order_no FROM travel_orders
      WHERE travel_order_no LIKE ?
      ORDER BY id DESC LIMIT 1"
);
$like = $prefix . '%';
$stmt->bind_param("s", $like);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->bind_result($lastNo);
    $stmt->fetch();
    $parts  = explode('.', $lastNo);
    $newNum = (int) end($parts) + 1;
} else {
    $newNum = 1;
}
$stmt->close();

echo $prefix . str_pad($newNum, 4, '0', STR_PAD_LEFT);
// Example output: TADA.100.RAJESH.0003