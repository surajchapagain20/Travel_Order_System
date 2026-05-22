<?php
require 'db.php';
$r = $conn->query("SELECT * FROM users LIMIT 3");
while($row=$r->fetch_assoc()) print_r($row);
$r = $conn->query("SELECT * FROM employees LIMIT 3");
while($row=$r->fetch_assoc()) print_r($row);
?>
