<?php
require 'db.php';
if ($conn->query("ALTER TABLE employees ADD COLUMN level ENUM('PH', 'NSM', 'HR', 'CEO') DEFAULT NULL")) {
    echo "Success";
} else {
    echo $conn->error;
}
?>
