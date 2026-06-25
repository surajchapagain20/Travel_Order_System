<?php
require_once 'auth.php';
requireLogin();

$file = $_GET['file'] ?? '';

if (empty($file)) {
    http_response_code(400);
    die("No file specified.");
}

$realFilePath = realpath($file);

// Security Check
if (!$realFilePath || !file_exists($realFilePath) || !str_starts_with($realFilePath, realpath('uploads/'))) {
    http_response_code(403);
    die("Access denied or file not found.");
}

$mimeType = mime_content_type($realFilePath);

header("Content-Type: " . $mimeType);
header("Content-Disposition: inline; filename=\"" . basename($realFilePath) . "\"");
header("Cache-Control: no-cache, must-revalidate");
header("Expires: 0");

readfile($realFilePath);
exit;
?>