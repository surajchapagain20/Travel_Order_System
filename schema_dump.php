<?php
require_once 'db.php';
$tables = [];
$res = $conn->query("SHOW TABLES");
while($row = $res->fetch_array()) {
    $table = $row[0];
    $tables[$table] = [];
    $res2 = $conn->query("DESCRIBE $table");
    while($row2 = $res2->fetch_assoc()) {
        $tables[$table][] = $row2;
    }
}
echo json_encode($tables, JSON_PRETTY_PRINT);
?>
