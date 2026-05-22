<?php
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
    die("Connection failed: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    
    $id = (int)$_POST['id'];
    
    $data = [
        'name'        => trim($_POST['name'] ?? ''),
        'position'    => trim($_POST['position'] ?? ''),
        'office'      => trim($_POST['office'] ?? ''),
        'purpose'     => trim($_POST['purpose'] ?? ''),
        'from_date'   => $_POST['from_date'] ?? null,
        'to_date'     => $_POST['to_date'] ?? null,
        'vehicle'     => trim($_POST['vehicle'] ?? ''),
        'distance'    => (float)($_POST['distance'] ?? 0),
        'fare'        => (float)($_POST['fare'] ?? 0),
        'airport'     => (float)($_POST['airport'] ?? 0),
        'road_tax'    => (float)($_POST['road_tax'] ?? 0),
        'remarks'     => trim($_POST['remarks'] ?? ''),
        'daily_rate'  => (float)($_POST['daily_rate'] ?? 0),
        'days'        => (int)($_POST['days'] ?? 0),
        'hotel'       => (float)($_POST['hotel'] ?? 0),
        'other_exp'   => (float)($_POST['other_exp'] ?? 0),
        'advance'     => (float)($_POST['advance'] ?? 0),
        'signature_date' => $_POST['signature_date'] ?? null
    ];

    $sql = "UPDATE travel_expenses SET 
                name = :name,
                position = :position,
                office = :office,
                purpose = :purpose,
                from_date = :from_date,
                to_date = :to_date,
                vehicle = :vehicle,
                distance = :distance,
                fare = :fare,
                airport = :airport,
                road_tax = :road_tax,
                remarks = :remarks,
                daily_rate = :daily_rate,
                days = :days,
                hotel = :hotel,
                other_exp = :other_exp,
                advance = :advance,
                signature_date = :signature_date,
                updated_at = NOW()
            WHERE id = :id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($data, ['id' => $id]));

    if ($stmt->rowCount() > 0) {
        header("Location: View_ExpenseRecords.php?updated=1");
    } else {
        header("Location: View_ExpenseRecords.php?error=1");
    }
    exit;
} else {
    header("Location: View_ExpenseRecords.php");
    exit;
}
?>