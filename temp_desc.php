<?php
require 'db.php';
$res = $conn->query("DESCRIBE employees");
while($r = $res->fetch_assoc()) {
    echo $r['Field'] . " - " . $r['Type'] . "\n";
}
?>
