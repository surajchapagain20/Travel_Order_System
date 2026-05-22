<?php
// PDO database connection for use in smtp.php and other scripts.
// Adjust credentials if they differ from db.php.
$host = defined('DB_HOST') ? DB_HOST : 'localhost';
$dbname = defined('DB_NAME') ? DB_NAME : 'hr';
$user = defined('DB_USER') ? DB_USER : 'root';
$pass = defined('DB_PASS') ? DB_PASS : '';

$dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";

try {
    $dbh = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    // In a production setting you might log this error instead of displaying.
    die('Database connection failed: ' . $e->getMessage());
}
?>
