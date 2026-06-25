<?php
// Database setup
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

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM travel_expenses WHERE id = ?");
        $stmt->execute([$id]);
        $success = true;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
} else {
    $error = "No record ID provided.";
}

// Redirect back to view page
header("Location: View_ExpenseRecords.php" . (isset($success) ? "?deleted=1" : "?error=1"));
exit;
?>