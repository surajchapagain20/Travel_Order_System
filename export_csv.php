<?php
// Database setup
$host = 'localhost';
$dbname = 'hr';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Fetch all records
$stmt = $pdo->prepare("SELECT * FROM travel_expenses ORDER BY id DESC");
$stmt->execute();
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=travel_expenses_' . date('Y-m-d') . '.csv');

// Output CSV
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel compatibility
fwrite($output, "\xEF\xBB\xBF");

// Column headers
fputcsv($output, [
    'ID', 'Name', 'Position', 'Office', 'Purpose', 
    'From Date', 'To Date', 'Vehicle', 'Distance (km)', 
    'Fare', 'Airport Tax', 'Road Tax', 'Daily Rate', 
    'Days', 'Hotel', 'Other Expenses', 'Advance', 
    'Signature Date', 'Created At'
]);

// Add data rows
foreach ($records as $row) {
    fputcsv($output, [
        $row['id'],
        $row['name'],
        $row['position'],
        $row['office'],
        $row['purpose'],
        $row['from_date'],
        $row['to_date'],
        $row['vehicle'],
        $row['distance'],
        $row['fare'],
        $row['airport'],
        $row['road_tax'],
        $row['daily_rate'],
        $row['days'],
        $row['hotel'],
        $row['other_exp'],
        $row['advance'],
        $row['signature_date'],
        $row['created_at']
    ]);
}

fclose($output);
exit;
?>