<?php
require 'db.php';
$conn->query("ALTER TABLE employees ADD COLUMN BrCode varchar(10) AFTER employeeEmail");
echo "Column added.";
?>
