<?php
// Simple authentication check used across admin pages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
function check_login() {
    if (!isset($_SESSION['uname']) || empty($_SESSION['uname'])) {
        // Not logged in, redirect to login page
        header('Location: login.php');
        exit();
    }
    // Additional role checks can be added here if needed
}
?>
