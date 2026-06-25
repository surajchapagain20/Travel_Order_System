<?php
require_once 'auth.php';
requireLogin();
require_once 'db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$id           = intval($_POST['id']           ?? 0);
$travelFrom   = trim($_POST['travelDateFrom'] ?? '');
$travelTo     = trim($_POST['travelDateTo']   ?? '');
$noOfDays     = intval($_POST['noOfDays']     ?? 0);

// ── Validation ──
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid travel order ID.']);
    exit;
}

if (empty($travelFrom) || empty($travelTo)) {
    echo json_encode(['success' => false, 'message' => 'Start date and end date are required.']);
    exit;
}

$from = DateTime::createFromFormat('Y-m-d', $travelFrom);
$to   = DateTime::createFromFormat('Y-m-d', $travelTo);

if (!$from || !$to) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format. Use YYYY-MM-DD.']);
    exit;
}

if ($to < $from) {
    echo json_encode(['success' => false, 'message' => 'End date must be on or after start date.']);
    exit;
}

if ($noOfDays <= 0) {
    // Auto-calculate if not provided or invalid
    $noOfDays = $from->diff($to)->days + 1;
}

// ── Update ──
$stmt = $conn->prepare("UPDATE travel_orders SET travelDateFrom = ?, travelDateTo = ?, noOfDays = ? WHERE id = ?");
$stmt->bind_param("ssii", $travelFrom, $travelTo, $noOfDays, $id);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Travel dates updated successfully.',
        'data'    => [
            'id'            => $id,
            'travelDateFrom' => $travelFrom,
            'travelDateTo'   => $travelTo,
            'noOfDays'       => $noOfDays,
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
}

$stmt->close();
$conn->close();