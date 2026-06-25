<?php
require 'db.php';
$res = $conn->query("DESCRIBE travel_orders");
echo "travel_orders columns:\n";
while($r = $res->fetch_assoc()) echo $r['Field'] . "\n";

$res2 = $conn->query("DESCRIBE travel_expenses");
echo "\ntravel_expenses columns:\n";
while($r = $res2->fetch_assoc()) echo $r['Field'] . "\n";
?>
