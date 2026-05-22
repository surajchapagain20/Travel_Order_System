<?php
require_once 'auth.php';
requireLogin();

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

// ==================== HANDLE UPDATE ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    try {
        $id = (int)$_POST['id'];

        $stmt = $pdo->prepare("UPDATE travel_expenses SET
            name = ?, position = ?, office = ?, purpose = ?,
            from_date = ?, to_date = ?,
            fare = ?, airport = ?, road_tax = ?, hotel = ?, other_exp = ?,
            daily_rate = ?, days = ?, advance = ?,
            approved_fare = ?, approved_daily = ?, approved_hotel = ?,
            adjustment = ?, final_amount = ?,
            expense_table_data = ?,
            updated_at = NOW()
            WHERE id = ?");

        $stmt->execute([
            $_POST['name'] ?? '',
            $_POST['position'] ?? '',
            $_POST['office'] ?? '',
            $_POST['purpose'] ?? '',
            $_POST['from_date'] ?? null,
            $_POST['to_date'] ?? null,
            $_POST['fare'] ?? 0,
            $_POST['airport'] ?? 0,
            $_POST['road_tax'] ?? 0,
            $_POST['hotel'] ?? 0,
            $_POST['other_exp'] ?? 0,
            $_POST['daily_rate'] ?? 0,
            $_POST['days'] ?? 0,
            $_POST['advance'] ?? 0,
            $_POST['approved_fare'] ?? 0,
            $_POST['approved_daily'] ?? 0,
            $_POST['approved_hotel'] ?? 0,
            $_POST['adjustment'] ?? 0,
            $_POST['final_amount'] ?? 0,
            $_POST['expense_table_json'] ?? '[]',
            $id
        ]);

        header("Location: View_ExpenseRecords.php?success=1");
        exit;

    } catch (PDOException $e) {
        $error = "Update failed: " . $e->getMessage();
    }
}

// ==================== PAGINATION & SEARCH ====================
$records_per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $records_per_page;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where = '';
$params = [];
if (!empty($search)) {
    $where = " WHERE name LIKE :search OR purpose LIKE :search OR office LIKE :search";
    $params[':search'] = "%$search%";
}

$total_stmt = $pdo->prepare("SELECT COUNT(*) FROM travel_expenses" . $where);
$total_stmt->execute($params);
$total_records = $total_stmt->fetchColumn();
$total_pages = ceil($total_records / $records_per_page);

$sql = "SELECT * FROM travel_expenses" . $where . " ORDER BY id DESC LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);

if (!empty($search)) {
    $stmt->bindValue(1, $params[':search'], PDO::PARAM_STR);
    $stmt->bindValue(2, $records_per_page, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
} else {
    $stmt->bindValue(1, $records_per_page, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
}
$stmt->execute();
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ne">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Travel Expense Records</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', Arial, sans-serif; background: #f4f4f4; }
        .container { max-width: 1250px; margin: 20px auto; background: white; padding: 25px; border-radius: 10px; box-shadow: 0 0 15px rgba(0,0,0,0.1); }
        th { background: #003087; color: white; }
        .modal-body { max-height: 88vh; overflow-y: auto; }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container">
    <h1>📋 Travel Expense Records</h1>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">✅ Record updated successfully!</div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="mb-3">
        <a href="travel_expense.php" class="btn btn-primary">+ New Expense Record</a>
    </div>

    <form method="GET" class="mb-3">
        <div class="input-group" style="max-width: 400px;">
            <input type="text" name="search" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="Search...">
            <button class="btn btn-primary" type="submit">Search</button>
            <?php if ($search): ?><a href="View_ExpenseRecords.php" class="btn btn-secondary">Clear</a><?php endif; ?>
        </div>
    </form>

    <table class="table table-bordered table-hover">
        <thead>
            <tr>
                <th>ID</th><th>Name</th><th>Position</th><th>Office</th><th>Purpose</th>
                <th>Dates</th><th>Total Fare</th><th>Total Hotel</th><th>Net Amount</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($records)): ?>
                <tr><td colspan="10" class="text-center">No records found.</td></tr>
            <?php else: ?>
                <?php foreach ($records as $row):
                    $total_fare = $row['fare'] + $row['airport'] + $row['road_tax'];
                    $total_hotel = $row['hotel'] + $row['other_exp'];
                    $net = $total_fare + ($row['daily_rate'] * $row['days']) + $total_hotel - $row['advance'];
                ?>
                <tr>
                    <td><?= $row['id'] ?></td>
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td><?= htmlspecialchars($row['position']) ?></td>
                    <td><?= htmlspecialchars($row['office']) ?></td>
                    <td><?= htmlspecialchars($row['purpose']) ?></td>
                    <td><?= $row['from_date'] ?> - <?= $row['to_date'] ?></td>
                    <td>रू. <?= number_format($total_fare, 2) ?></td>
                    <td>रू. <?= number_format($total_hotel, 2) ?></td>
                    <td><strong>रू. <?= number_format($net, 2) ?></strong></td>
                    <td>
                        <button class="btn btn-success btn-sm edit-btn" 
                                onclick="editRecord(<?= htmlspecialchars(json_encode($row)) ?>)">
                            <i class="bi bi-pencil"></i> Edit
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- ==================== EDIT MODAL ==================== -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Travel Expense Record</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            
            <form method="POST" id="expenseForm">
                <input type="hidden" name="id" id="edit_id">

                <div class="modal-body">
                    <!-- Basic Info -->
                    <table class="table table-borderless">
                        <tr>
                            <td>नाम :</td>
                            <td><input type="text" name="name" id="edit_name" class="form-control" required></td>
                            <td>भ्रमण मिति :</td>
                            <td><input type="date" name="from_date" id="edit_from" required> देखि <input type="date" name="to_date" id="edit_to" required> सम्म</td>
                        </tr>
                        <tr>
                            <td>पद :</td>
                            <td><input type="text" name="position" id="edit_position" class="form-control" required></td>
                            <td>भ्रमण उद्देश्य :</td>
                            <td><input type="text" name="purpose" id="edit_purpose" class="form-control" required></td>
                        </tr>
                        <tr>
                            <td>कार्यालय :</td>
                            <td colspan="3"><input type="text" name="office" id="edit_office" class="form-control" required></td>
                        </tr>
                    </table>

                    <h3>१. भ्रमण विवरण (देखी -सम्म)</h3>
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>भ्रमण साधन</th>
                                <th>जम्मा दूरी कि.मि.</th>
                                <th>भाडा/इन्धन (रू.)</th>
                                <th>कैफियत</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><input type="text" name="vehicle" id="edit_vehicle" class="form-control"></td>
                                <td><input type="number" name="distance" id="edit_distance" step="0.01" class="form-control" oninput="calculateTotals()"></td>
                                <td><input type="number" name="fare" id="edit_fare" step="0.01" class="form-control" oninput="calculateTotals()" value="0"></td>
                                <td><input type="text" name="remarks" id="edit_remarks" class="form-control"></td>
                            </tr>
                            <tr>
                                <td colspan="3">२. एयरपोर्ट ट्याक्स तथा ट्याक्सी खर्च</td>
                                <td><input type="number" name="airport" id="edit_airport" step="0.01" class="form-control" oninput="calculateTotals()" value="0"></td>
                            </tr>
                            <tr>
                                <td colspan="3">३. अन्य खर्च : सडक कर</td>
                                <td><input type="number" name="road_tax" id="edit_roadtax" step="0.01" class="form-control" oninput="calculateTotals()" value="0"></td>
                            </tr>
                            <tr class="table-info">
                                <td colspan="3">(क) जम्मा भाडा/इन्धन खर्च</td>
                                <td id="total_fare">0.00</td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Other tables (daily, hotel, advance) - same as before -->
                    <table class="table table-bordered">
                        <tr>
                            <td>१. दैनिक भ्रमण भत्ता रू. <input type="number" name="daily_rate" id="edit_daily" step="0.01" class="form-control d-inline w-25" value="0" oninput="calculateTotals()"> प्रति दिन</td>
                            <td><input type="number" name="days" id="edit_days" class="form-control w-25" value="0" oninput="calculateTotals()"></td>
                            <td class="table-info">(ख) जम्मा दैनिक भत्ता</td>
                            <td id="total_daily">0.00</td>
                        </tr>
                        <tr>
                            <td colspan="2">२. होटेल खर्च</td>
                            <td><input type="number" name="hotel" id="edit_hotel" step="0.01" class="form-control" oninput="calculateTotals()" value="0"></td>
                            <td></td>
                        </tr>
                        <tr>
                            <td colspan="2">३. अन्य खर्च :</td>
                            <td><input type="number" name="other_exp" id="edit_other" step="0.01" class="form-control" oninput="calculateTotals()" value="0"></td>
                            <td></td>
                        </tr>
                        <tr class="table-info">
                            <td colspan="2">(ग) जम्मा होटेल खर्च</td>
                            <td id="total_hotel">0.00</td>
                            <td></td>
                        </tr>
                    </table>

                    <table class="table table-bordered">
                        <tr>
                            <td>(घ) भ्रमण प्रयोजनको लागि लिएको पेशकी / समायोजन</td>
                            <td><input type="number" name="advance" id="edit_advance" step="0.01" class="form-control" oninput="calculateTotals()" value="0"></td>
                        </tr>
                        <tr class="table-success">
                            <td>(ङ) भुक्तानी पाउनु पर्ने/ (तिर्नुपर्ने रकम)</td>
                            <td id="net_amount">0.00</td>
                        </tr>
                    </table>

                    <!-- Office Table -->
                    <h3>अफिस प्रयोजनको लागि मात्रः</h3>
                    <table id="expenseTable" class="table table-bordered">
                        <thead>
                            <tr>
                                <th width="60%">विवरण</th>
                                <th>रकम (रू.)</th>
                                <th width="100px">कार्य</th>
                            </tr>
                        </thead>
                        <tbody id="expenseTableBody"></tbody>
                    </table>

                    <div class="mt-3">
                        <button type="button" id="addRow" class="btn btn-success">+ नयाँ ROW थप्नुहोस्</button>
                    </div>

                    <input type="hidden" name="expense_table_json" id="expense_table_json">
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// ==================== EDIT RECORD (Now with vehicle, distance, remarks) ====================
function editRecord(record) {
    // Basic fields
    document.getElementById('edit_id').value = record.id || '';
    document.getElementById('edit_name').value = record.name || '';
    document.getElementById('edit_position').value = record.position || '';
    document.getElementById('edit_office').value = record.office || '';
    document.getElementById('edit_purpose').value = record.purpose || '';
    document.getElementById('edit_from').value = record.from_date || '';
    document.getElementById('edit_to').value = record.to_date || '';

    // Travel Details Fields (Fixed)
    document.getElementById('edit_vehicle').value = record.vehicle || '';
    document.getElementById('edit_distance').value = record.distance || 0;
    document.getElementById('edit_fare').value = record.fare || 0;
    document.getElementById('edit_remarks').value = record.remarks || '';

    // Other fields
    document.getElementById('edit_airport').value = record.airport || 0;
    document.getElementById('edit_roadtax').value = record.road_tax || 0;
    document.getElementById('edit_hotel').value = record.hotel || 0;
    document.getElementById('edit_other').value = record.other_exp || 0;
    document.getElementById('edit_daily').value = record.daily_rate || 0;
    document.getElementById('edit_days').value = record.days || 0;
    document.getElementById('edit_advance').value = record.advance || 0;

    // Clear & Rebuild Approval Table
    document.getElementById('expenseTableBody').innerHTML = '';

    const fixedRows = [
        {label: "१) जम्मा भाडा/इन्धन खर्च स्वीकृत (कोड #4642201)", name: "approved_fare", value: record.approved_fare || 0},
        {label: "२) जम्मा दैनिक भत्ता (कोड #4642301)", name: "approved_daily", value: record.approved_daily || 0},
        {label: "३) जम्मा दैनिक होटेल खर्च स्वीकृत (कोड #4642401)", name: "approved_hotel", value: record.approved_hotel || 0},
        {label: "४) समायोजन रकम", name: "adjustment", value: record.adjustment || 0},
        {label: "५) भुक्तानी पाउनु पर्ने वा (तिर्नुपर्ने रकम)", name: "final_amount", value: record.final_amount || 0}
    ];

    fixedRows.forEach(r => addRowToTable(r.label, r.name, r.value, true));

    // Load extra rows
    if (record.expense_table_data) {
        try {
            const extras = JSON.parse(record.expense_table_data);
            extras.forEach(r => addRowToTable(r.description || '', 'extra[]', r.amount || 0, false));
        } catch(e) {}
    }

    calculateTotals();
    new bootstrap.Modal(document.getElementById('editModal')).show();
}

function addRowToTable(label, name, value, isFixed) {
    const tbody = document.getElementById('expenseTableBody');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td>${label}</td>
        <td><input type="number" name="${name}" class="form-control amount" step="0.01" value="${parseFloat(value)||0}" oninput="calculateTotals()"></td>
        <td>
            ${isFixed ? 
                '<button type="button" class="btn btn-danger btn-sm" disabled>Fixed</button>' : 
                '<button type="button" class="btn btn-danger btn-sm deleteRow" onclick="this.closest(\'tr\').remove()">हटाउनुहोस्</button>'
            }
        </td>`;
    tbody.appendChild(tr);
}

document.getElementById('addRow').addEventListener('click', () => {
    addRowToTable('नयाँ विवरण', 'extra[]', 0, false);
});

document.getElementById('expenseForm').addEventListener('submit', function() {
    const rows = [];
    document.querySelectorAll('#expenseTableBody tr').forEach(tr => {
        const desc = tr.cells[0].textContent.trim();
        const input = tr.querySelector('input');
        if (desc && input) rows.push({description: desc, amount: parseFloat(input.value)||0});
    });
    document.getElementById('expense_table_json').value = JSON.stringify(rows);
});

function calculateTotals() {
    const fare = parseFloat(document.getElementById('edit_fare').value) || 0;
    const airport = parseFloat(document.getElementById('edit_airport').value) || 0;
    const road_tax = parseFloat(document.getElementById('edit_roadtax').value) || 0;
    const daily_rate = parseFloat(document.getElementById('edit_daily').value) || 0;
    const days = parseFloat(document.getElementById('edit_days').value) || 0;
    const hotel = parseFloat(document.getElementById('edit_hotel').value) || 0;
    const other = parseFloat(document.getElementById('edit_other').value) || 0;
    const advance = parseFloat(document.getElementById('edit_advance').value) || 0;

    document.getElementById('total_fare').textContent = (fare + airport + road_tax).toFixed(2);
    document.getElementById('total_daily').textContent = (daily_rate * days).toFixed(2);
    document.getElementById('total_hotel').textContent = (hotel + other).toFixed(2);
    document.getElementById('net_amount').textContent = (fare + airport + road_tax + daily_rate*days + hotel + other - advance).toFixed(2);
}
</script>
</body>
</html>