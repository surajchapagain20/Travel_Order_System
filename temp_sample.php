<?php
require 'db.php';

$res = $conn->query("SELECT * FROM travel_orders LIMIT 1");
if ($r = $res->fetch_assoc()) {
    echo "Travel Orders:\n";
    print_r($r);
}

$res = $conn->query("SELECT * FROM travel_expenses LIMIT 1");
if ($r = $res->fetch_assoc()) {
    echo "Travel Expenses:\n";
    print_r($r);
}
?>
