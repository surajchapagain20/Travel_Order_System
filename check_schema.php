<?php
require_once 'db.php';
$conn->query("ALTER TABLE users MODIFY COLUMN role ENUM('HR', 'Employee', 'Admin') NOT NULL DEFAULT 'Employee'");
echo "Role column updated to include Admin.";
?>
