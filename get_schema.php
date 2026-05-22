<?php
require 'db.php';
$res = $conn->query('SELECT designation FROM employees LIMIT 5');
while($row=$res->fetch_assoc()) echo $row['designation']."\n";
?>
