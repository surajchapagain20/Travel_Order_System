<?php
require_once 'db.php';
$result = $conn->query("SHOW COLUMNS FROM travel_expenses LIKE 'bank_post_data'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE travel_expenses ADD COLUMN bank_post_data JSON DEFAULT NULL");
    echo "Column added.";
} else {
    echo "Column already exists.";
}
?>
