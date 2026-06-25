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

// Build dynamic WHERE clause
$whereClauses = [];
$params = [];

$filterClauses = [];
if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
    $filterClauses[] = "(te.from_date >= :start_date AND te.to_date <= :end_date)";
    $params[':start_date'] = $_GET['start_date'];
    $params[':end_date'] = $_GET['end_date'];
}
if (!empty($_GET['employee'])) {
    $filterClauses[] = "(te.name LIKE :employee OR te.employeeName LIKE :employee)";
    $params[':employee'] = "%" . $_GET['employee'] . "%";
}

if (!empty($filterClauses)) {
    $whereClauses[] = "(" . implode(' OR ', $filterClauses) . ")";
}

if (!empty($_GET['brcode'])) {
    $whereClauses[] = "te.BrCode = :brcode";
    $params[':brcode'] = $_GET['brcode'];
}

$whereSQL = $whereClauses ? "WHERE " . implode(' AND ', $whereClauses) : '';

// Updated Query - Added last_updated_at from travel_orders
$sql = "
    SELECT te.*,
           to.travelFrom,
           to.Destination,
           to.BrCode AS order_BrCode,
           to.last_updated_at
    FROM travel_expenses te
    LEFT JOIN travel_orders `to` ON te.emp_id = `to`.EmpID
    $whereSQL
    ORDER BY te.id DESC
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Query failed: " . $e->getMessage());
}

// CSV Headers
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=travel_expenses_' . date('Y-m-d_H-i-s') . '.csv');

$output = fopen('php://output', 'w');
fwrite($output, "\xEF\xBB\xBF"); // UTF-8 BOM

// Column Headers (Added Last Updated At)
fputcsv($output, [
    'ID', 'EMPID','Order_BrCode', 'Travel From', 'Destination', 'Name',
    'Position', 'Office', 'Purpose', 'From Date', 'To Date', 'Vehicle',
    'Distance (km)', 'Fare', 'Airport Tax', 'Road Tax', 'Daily Rate', 'Days',
    'Hotel', 'Other Expenses', 'Advance', 'Signature Date', 'Created At',
    'Approved Date', 'Last Updated At', 'Net Payment'
]);

// Data Rows
foreach ($records as $row) {
    fputcsv($output, [
        $row['id'] ?? '',
        $row['emp_id'] ?? '',
        //$row['BrCode'] ?? '',           // From travel_expenses
        $row['order_BrCode'] ?? '',     // From travel_orders
        $row['travelFrom'] ?? '',
        $row['Destination'] ?? '',
        $row['name'] ?? '',
        $row['position'] ?? '',
        $row['office'] ?? '',
        $row['purpose'] ?? '',
        $row['from_date'] ?? '',
        $row['to_date'] ?? '',
        $row['vehicle'] ?? '',
        $row['distance'] ?? '',
        $row['fare'] ?? '',
        $row['airport'] ?? '',
        $row['road_tax'] ?? '',
        $row['daily_rate'] ?? '',
        $row['days'] ?? '',
        $row['hotel'] ?? '',
        $row['other_exp'] ?? '',
        $row['advance'] ?? '',
        $row['signature_date'] ?? '',
        $row['created_at'] ?? '',
        $row['last_updated_at'] ?? '',
        //$row['last_updated_at'] ?? '',   // ← From travel_orders
        $row['net_payment'] ?? ''
    ]);
}

fclose($output);
exit;
?>