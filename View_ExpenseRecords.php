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
            vehicle = ?, distance = ?, remarks = ?,
            fare = ?, airport = ?, road_tax = ?, hotel = ?, other_exp = ?,
            daily_rate = ?, days = ?, advance = ?,
            approved_fare = ?, approved_daily = ?, approved_hotel = ?,
            adjustment = ?, final_amount = ?,
            expense_table_data = ?
            WHERE id = ?");

        $stmt->execute([
            $_POST['name']               ?? '',
            $_POST['position']           ?? '',
            $_POST['office']             ?? '',
            $_POST['purpose']            ?? '',
            $_POST['from_date']          ?? null,
            $_POST['to_date']            ?? null,
            $_POST['vehicle']            ?? '',
            $_POST['distance']           ?? 0,
            $_POST['remarks']            ?? '',
            $_POST['fare']               ?? 0,
            $_POST['airport']            ?? 0,
            $_POST['road_tax']           ?? 0,
            $_POST['hotel']              ?? 0,
            $_POST['other_exp']          ?? 0,
            $_POST['daily_rate']         ?? 0,
            $_POST['days']               ?? 0,
            $_POST['advance']            ?? 0,
            $_POST['approved_fare']      ?? 0,
            $_POST['approved_daily']     ?? 0,
            $_POST['approved_hotel']     ?? 0,
            $_POST['adjustment']         ?? 0,
            $_POST['final_amount']       ?? 0,
            $_POST['expense_table_json'] ?? '[]',
            $id
        ]);

        header("Location: " . $_SERVER['PHP_SELF'] . "?updated=1");
        exit;

    } catch (PDOException $e) {
        $error = "Update failed: " . $e->getMessage();
    }
}

// ==================== PAGINATION & SEARCH ====================
$records_per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
$page             = isset($_GET['page'])     ? max(1, (int)$_GET['page']) : 1;
$offset           = ($page - 1) * $records_per_page;
$search           = isset($_GET['search'])   ? trim($_GET['search']) : '';

if (!empty($search)) {
    $like = '%' . $search . '%';

    $total_stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM travel_expenses WHERE name LIKE ? OR purpose LIKE ? OR office LIKE ?"
    );
    $total_stmt->execute([$like, $like, $like]);
    $total_records = $total_stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT * FROM travel_expenses
         WHERE name LIKE ? OR purpose LIKE ? OR office LIKE ?
         ORDER BY id DESC LIMIT ? OFFSET ?"
    );
    $stmt->bindValue(1, $like,             PDO::PARAM_STR);
    $stmt->bindValue(2, $like,             PDO::PARAM_STR);
    $stmt->bindValue(3, $like,             PDO::PARAM_STR);
    $stmt->bindValue(4, $records_per_page, PDO::PARAM_INT);
    $stmt->bindValue(5, $offset,           PDO::PARAM_INT);
    $stmt->execute();
} else {
    $total_stmt = $pdo->prepare("SELECT COUNT(*) FROM travel_expenses");
    $total_stmt->execute();
    $total_records = $total_stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT * FROM travel_expenses ORDER BY id DESC LIMIT ? OFFSET ?"
    );
    $stmt->bindValue(1, $records_per_page, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset,           PDO::PARAM_INT);
    $stmt->execute();
}

$records     = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total_pages = (int)ceil($total_records / $records_per_page);
?>
<!DOCTYPE html>
<html lang="ne">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Travel Expense Records - Nepal Life Insurance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', Arial, sans-serif; background: #f4f4f4; }
        .container { max-width: 1250px; margin: 20px auto; background: white; padding: 25px; border-radius: 10px; box-shadow: 0 0 15px rgba(0,0,0,0.1); }
        h1 { color: #003087; }
        thead th { background: #003087; color: white; }
        .modal-body { max-height: 85vh; overflow-y: auto; }
        .modal-body table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .modal-body td, .modal-body th { border: 1px solid #ccc; padding: 8px; }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>

<?php if (isset($_GET['updated'])): ?>
<div class="alert alert-success alert-dismissible fade show mx-3 mt-2">
    ✅ Record updated successfully!
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($error)): ?>
<div class="alert alert-danger mx-3 mt-2">❌ <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="container">
    <h1>📋 Travel Expense Records</h1>

    <?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-success">✅ Record deleted successfully!</div>
    <?php endif; ?>

    <div class="mb-3">
        <a href="travel_expense.php" class="btn btn-primary">+ New Expense Record</a>
        <a href="export_csv.php" class="btn btn-info text-white">Export All to CSV</a>
    </div>

    <!-- Search -->
    <form method="GET" class="mb-3">
        <div class="input-group" style="max-width:420px;">
            <input type="text" name="search" class="form-control"
                   value="<?= htmlspecialchars($search) ?>"
                   placeholder="Search by name, purpose or office...">
            <button class="btn btn-outline-primary" type="submit">Search</button>
            <?php if (!empty($search)): ?>
            <a href="View_ExpenseRecords.php" class="btn btn-outline-secondary">Clear</a>
            <?php endif; ?>
        </div>
    </form>

    <div class="table-responsive">

    <table class="table table-bordered table-hover align-middle">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Position</th>
                <th>Office</th>
                <th>Purpose</th>
                <th>Dates</th>
                <th>Total Fare</th>
                <th>Total Hotel</th>
                <th>Net Amount</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($records)): ?>
            <tr><td colspan="11" class="text-center">No records found.</td></tr>
        <?php else: ?>
            <?php foreach ($records as $row):
                $total_fare  = $row['fare'] + $row['airport'] + $row['road_tax'];
                $total_hotel = $row['hotel'] + $row['other_exp'];
                $net         = $total_fare + ($row['daily_rate'] * $row['days']) + $total_hotel - $row['advance'];

                // Build ALL data attributes here so JS can read every field
                $da = [
                    'id'            => $row['id'],
                    'name'          => htmlspecialchars($row['name'],     ENT_QUOTES),
                    'position'      => htmlspecialchars($row['position'], ENT_QUOTES),
                    'office'        => htmlspecialchars($row['office'],   ENT_QUOTES),
                    'purpose'       => htmlspecialchars($row['purpose'],  ENT_QUOTES),
                    'from'          => $row['from_date'],
                    'to'            => $row['to_date'],
                    'fare'          => $row['fare'],
                    'airport'       => $row['airport'],
                    'roadtax'       => $row['road_tax'],
                    'hotel'         => $row['hotel'],
                    'other'         => $row['other_exp'],
                    'daily'         => $row['daily_rate'],
                    'days'          => $row['days'],
                    'advance'       => $row['advance'],
                    'vehicle'       => htmlspecialchars($row['vehicle']  ?? '', ENT_QUOTES),
                    'distance'      => $row['distance'] ?? 0,
                    'remarks'       => htmlspecialchars($row['remarks']  ?? '', ENT_QUOTES),
                    // ---- fields that were MISSING before ----
                    'approvedfare'  => $row['approved_fare'],
                    'approveddaily' => $row['approved_daily'],
                    'approvedhotel' => $row['approved_hotel'],
                    'adjustment'    => $row['adjustment'],
                    'finalamount'   => $row['final_amount'],
                    'tabledata'     => htmlspecialchars($row['expense_table_data'] ?? '[]', ENT_QUOTES),
                ];
                $dataAttrs = '';
                foreach ($da as $k => $v) {
                    $dataAttrs .= ' data-' . $k . '="' . $v . '"';
                }
            ?>
            <tr>
                <td><?= $row['id'] ?></td>
                <td><?= htmlspecialchars($row['name']) ?></td>
                <td><?= htmlspecialchars($row['position']) ?></td>
                <td><?= htmlspecialchars($row['office']) ?></td>
                <td><?= htmlspecialchars($row['purpose']) ?></td>
                <td><?= htmlspecialchars($row['from_date']) ?> – <?= htmlspecialchars($row['to_date']) ?></td>
                <td>रू. <?= number_format($total_fare,  2) ?></td>
                <td>रू. <?= number_format($total_hotel, 2) ?></td>
                <td><strong>रू. <?= number_format($net, 2) ?></strong></td>
                <td><?= date('Y-m-d', strtotime($row['created_at'])) ?></td>
                <td>
                    <button class="btn btn-success btn-sm edit-btn" <?= $dataAttrs ?>>
                        <i class="bi bi-pencil"></i> Edit
                    </button>
                    <a href="delete_expense.php?id=<?= $row['id'] ?>"
                       class="btn btn-danger btn-sm"
                       onclick="return confirm('Delete this record?')">
                        <i class="bi bi-trash"></i> Delete
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="d-flex align-items-center gap-2 mt-3">
        <?php if ($page > 1): ?>
        <a href="?page=<?= $page-1 ?>&per_page=<?= $records_per_page ?>&search=<?= urlencode($search) ?>"
           class="btn btn-outline-primary">← Previous</a>
        <?php endif; ?>
        <span>Page <?= $page ?> of <?= $total_pages ?> (<?= $total_records ?> records)</span>
        <?php if ($page < $total_pages): ?>
        <a href="?page=<?= $page+1 ?>&per_page=<?= $records_per_page ?>&search=<?= urlencode($search) ?>"
           class="btn btn-outline-primary">Next →</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div><!-- /container -->

<!-- ==================== EDIT MODAL ==================== -->
<div class="modal fade" id="editModal" tabindex="-1"> 
  
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">✏️ Edit Travel Expense Record</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <form method="POST" id="expenseForm">
                <input type="hidden" name="id" id="edit_id">

                <div class="modal-body">

<div class="modal-body" id="printableArea">

                    <!-- Basic Info -->
                    <table class="table table-borderless">
                        <tr>
                            <td style="width:120px"><strong>नाम :</strong></td>
                            <td><input type="text" name="name" id="edit_name" class="form-control" required></td>
                            <td style="width:140px"><strong>भ्रमण मिति :</strong></td>
                            <td>
                                <input type="date" name="from_date" id="edit_from" class="form-control d-inline w-auto" required>
                                देखि
                                <input type="date" name="to_date" id="edit_to" class="form-control d-inline w-auto" required>
                                सम्म
                            </td>
                        </tr>
                        <tr>
                            <td><strong>पद :</strong></td>
                            <td><input type="text" name="position" id="edit_position" class="form-control" required></td>
                            <td><strong>उद्देश्य :</strong></td>
                            <td><input type="text" name="purpose" id="edit_purpose" class="form-control" required></td>
                        </tr>
                        <tr>
                            <td><strong>कार्यालय :</strong></td>
                            <td colspan="3"><input type="text" name="office" id="edit_office" class="form-control" required></td>
                        </tr>
                    </table>

                    <!-- Section 1: Travel Detail -->
                    <h6 class="fw-bold mt-2">१. भ्रमण विवरण</h6>
                    <table>
                        <thead>
                            <tr>
                                <th>भ्रमण साधन</th>
                                <th>दूरी (कि.मि.)</th>
                                <th>भाडा/इन्धन (रू.)</th>
                                <th>कैफियत</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><input type="text"   name="vehicle"   id="edit_vehicle"   class="form-control"></td>
                                <td><input type="number" name="distance"  id="edit_distance"  class="form-control" step="0.01" value="0" oninput="calculateTotals()"></td>
                                <td><input type="number" name="fare"      id="edit_fare"      class="form-control" step="0.01" value="0" oninput="calculateTotals()"></td>
                                <td><input type="text"   name="remarks"   id="edit_remarks"   class="form-control"></td>
                            </tr>
                            <tr>
                                <td colspan="3">२. एयरपोर्ट ट्याक्स तथा ट्याक्सी खर्च</td>
                                <td><input type="number" name="airport"   id="edit_airport"   class="form-control" step="0.01" value="0" oninput="calculateTotals()"></td>
                            </tr>
                            <tr>
                                <td colspan="3">३. सडक कर</td>
                                <td><input type="number" name="road_tax"  id="edit_roadtax"   class="form-control" step="0.01" value="0" oninput="calculateTotals()"></td>
                            </tr>
                            <tr class="table-info">
                                <td colspan="3"><strong>(क) जम्मा भाडा/इन्धन खर्च</strong></td>
                                <td id="total_fare"><strong>0.00</strong></td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Section 2: Daily + Hotel -->
                    <table class="table table-bordered mt-2">
                        <tr>
                            <td>
                                १. दैनिक भत्ता रू.
                                <input type="number" name="daily_rate" id="edit_daily" class="form-control d-inline" style="width:100px" step="0.01" value="0" oninput="calculateTotals()">
                                × दिन
                                <input type="number" name="days"       id="edit_days"  class="form-control d-inline" style="width:70px" value="0" oninput="calculateTotals()">
                            </td>
                            <td class="table-info"><strong>(ख) जम्मा दैनिक भत्ता</strong></td>
                            <td id="total_daily"><strong>0.00</strong></td>
                        </tr>
                        <tr>
                            <td colspan="2">२. होटेल खर्च (खाना तथा बास)</td>
                            <td><input type="number" name="hotel"      id="edit_hotel"  class="form-control" step="0.01" value="0" oninput="calculateTotals()"></td>
                        </tr>
                        <tr>
                            <td colspan="2">३. अन्य खर्च</td>
                            <td><input type="number" name="other_exp"  id="edit_other"  class="form-control" step="0.01" value="0" oninput="calculateTotals()"></td>
                        </tr>
                        <tr class="table-info">
                            <td colspan="2"><strong>(ग) जम्मा होटेल खर्च</strong></td>
                            <td id="total_hotel"><strong>0.00</strong></td>
                        </tr>
                    </table>

                    <!-- Advance & Net -->
                    <table class="table table-bordered">
                        <tr>
                            <td>(घ) पेशकी / समायोजन</td>
                            <td><input type="number" name="advance" id="edit_advance" class="form-control" step="0.01" value="0" oninput="calculateTotals()"></td>
                        </tr>
                        <tr class="table-success fw-bold">
                            <td>(ङ) भुक्तानी पाउनु पर्ने [क+ख+ग−घ]</td>
                            <td id="net_amount">0.00</td>
                        </tr>
                    </table>

                    <div class="mb-3">
                        मिति: <input type="date" name="signature_date" id="edit_signature_date" class="form-control d-inline w-auto">
                    </div>

                    <!-- Office Purpose Table -->
                    <h6 class="fw-bold mt-3">अफिस प्रयोजनको लागि मात्रः</h6>
                    <table id="expenseTable" class="table table-bordered">
                        <thead>
                            <tr>
                                <th width="60%">विवरण</th>
                                <th>रकम (रू.)</th>
                                <th width="120px">कार्य</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>१) जम्मा भाडा/इन्धन खर्च स्वीकृत (कोड #4642201)</td>
                                <td><input type="number" name="approved_fare"  id="approved_fare"  class="form-control" step="0.01" value="0"></td>
                                <td>—</td>
                            </tr>
                            <tr>
                                <td>२) जम्मा दैनिक भत्ता (कोड #4642301)</td>
                                <td><input type="number" name="approved_daily" id="approved_daily" class="form-control" step="0.01" value="0"></td>
                                <td>—</td>
                            </tr>
                            <tr>
                                <td>३) जम्मा होटेल खर्च स्वीकृत (कोड #4642401)</td>
                                <td><input type="number" name="approved_hotel" id="approved_hotel" class="form-control" step="0.01" value="0"></td>
                                <td>—</td>
                            </tr>
                            <tr>
                                <td>४) समायोजन रकम</td>
                                <td><input type="number" name="adjustment"     id="adjustment"     class="form-control" step="0.01" value="0"></td>
                                <td>—</td>
                            </tr>
                            <tr>
                                <td>५) भुक्तानी पाउनु पर्ने वा (तिर्नुपर्ने रकम)</td>
                                <td><input type="number" name="final_amount"   id="final_amount"   class="form-control" step="0.01" value="0"></td>
                                <td>—</td>
                            </tr>
                            <!-- Dynamic extra rows injected here by JS -->
                        </tbody>
                    </table>

                    <div class="mt-2">
                        <button type="button" id="addRow" class="btn btn-success btn-sm">+ नयाँ ROW थप्नुहोस्</button>
                    </div>

                    <input type="hidden" name="expense_table_json" id="expense_table_json">
                </div><!-- /modal-body -->
			</div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">💾 Update Record</button>
					
					button type="button" class="btn btn-info" onclick="printExpenseForm()">
                        🖨️ Print
                    </button>
<!-- ==================== PRINT STYLES ==================== -->
<style>
    @media print {
        body * {
            visibility: hidden;
        }
        #printableArea, #printableArea * {
            visibility: visible;
        }
        #printableArea {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            padding: 20px;
        }
        .modal, .modal-header, .modal-footer, .btn, .table-bordered .btn {
            display: none !important;
        }
        input.form-control {
            border: none !important;
            border-bottom: 1px solid #000 !important;
            background: transparent !important;
            box-shadow: none !important;
            padding: 4px 0;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 15px;
        }
        th, td {
            border: 1px solid #000 !important;
            padding: 8px;
            vertical-align: middle;
        }
        .table-info, .table-success {
            background-color: #f8f9fa !important;
            -webkit-print-color-adjust: exact;
        }
        h6 {
            margin-top: 25px !important;
        }
        strong {
            font-weight: bold;
        }
    }
</style>

<script>
function printExpenseForm() {
    if (typeof calculateTotals === 'function') {
        calculateTotals();
    }
    window.print();
}

// Keyboard shortcut (Ctrl + P)
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
        e.preventDefault();
        printExpenseForm();
    }
});
</script>


                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>

// Auto-redirect after successful update
<?php if (isset($_GET['updated'])): ?>
window.addEventListener('load', function () {
    setTimeout(function () { window.location.href = 'View_ExpenseRecords.php'; }, 2000);
});
<?php endif; ?>

// ---- Calculate totals ----
function calculateTotals() {
    var fare       = parseFloat(document.getElementById('edit_fare').value)    || 0;
    var airport    = parseFloat(document.getElementById('edit_airport').value) || 0;
    var road_tax   = parseFloat(document.getElementById('edit_roadtax').value) || 0;
    var daily_rate = parseFloat(document.getElementById('edit_daily').value)   || 0;
    var days       = parseFloat(document.getElementById('edit_days').value)    || 0;
    var hotel      = parseFloat(document.getElementById('edit_hotel').value)   || 0;
    var other      = parseFloat(document.getElementById('edit_other').value)   || 0;
    var advance    = parseFloat(document.getElementById('edit_advance').value) || 0;

    var tf  = fare + airport + road_tax;
    var td  = daily_rate * days;
    var th  = hotel + other;
    var net = tf + td + th - advance;

    document.getElementById('total_fare').innerHTML  = '<strong>' + tf.toFixed(2)  + '</strong>';
    document.getElementById('total_daily').innerHTML = '<strong>' + td.toFixed(2)  + '</strong>';
    document.getElementById('total_hotel').innerHTML = '<strong>' + th.toFixed(2)  + '</strong>';
    document.getElementById('net_amount').textContent = net.toFixed(2);
}

// ---- Load ALL DB fields into modal when Edit is clicked ----
document.querySelectorAll('.edit-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var d = this.dataset;

        // Basic fields
        document.getElementById('edit_id').value        = d.id;
        document.getElementById('edit_name').value      = d.name;
        document.getElementById('edit_position').value  = d.position;
        document.getElementById('edit_office').value    = d.office;
        document.getElementById('edit_purpose').value   = d.purpose;
        document.getElementById('edit_from').value      = d.from;
        document.getElementById('edit_to').value        = d.to;

        // Travel detail fields (vehicle / distance / remarks)
        document.getElementById('edit_vehicle').value   = d.vehicle;
        document.getElementById('edit_distance').value  = d.distance;
        document.getElementById('edit_remarks').value   = d.remarks;

        // Travel cost fields
        document.getElementById('edit_fare').value      = d.fare;
        document.getElementById('edit_airport').value   = d.airport;
        document.getElementById('edit_roadtax').value   = d.roadtax;
        document.getElementById('edit_hotel').value     = d.hotel;
        document.getElementById('edit_other').value     = d.other;
        document.getElementById('edit_daily').value     = d.daily;
        document.getElementById('edit_days').value      = d.days;
        document.getElementById('edit_advance').value   = d.advance;

        // Office-purpose fields (these were missing before — root cause of blank values)
        document.getElementById('approved_fare').value  = d.approvedfare;
        document.getElementById('approved_daily').value = d.approveddaily;
        document.getElementById('approved_hotel').value = d.approvedhotel;
        document.getElementById('adjustment').value     = d.adjustment;
        document.getElementById('final_amount').value   = d.finalamount;

        // Rebuild any saved dynamic extra rows from stored JSON
        var tbody = document.querySelector('#expenseTable tbody');
        // Remove previously appended dynamic rows (leave the 5 fixed rows)
        var allRows = tbody.querySelectorAll('tr.dynamic-row');
        allRows.forEach(function (r) { r.remove(); });

        try {
            var extra = JSON.parse(d.tabledata || '[]');
            extra.forEach(function (item) {
                var tr = document.createElement('tr');
                tr.className = 'dynamic-row';
                tr.innerHTML =
                    '<td><input type="text" class="form-control detail" value="' + (item.detail || '') + '"></td>' +
                    '<td><input type="number" class="form-control amount" step="0.01" value="' + (item.amount || 0) + '"></td>' +
                    '<td><button type="button" class="btn btn-danger btn-sm deleteRow">हटाउनुहोस्</button></td>';
                tbody.appendChild(tr);
            });
        } catch (e) { /* invalid JSON — skip */ }

        calculateTotals();

        var modal = new bootstrap.Modal(document.getElementById('editModal'));
        modal.show();
    });
});

// ---- Add dynamic row ----
document.getElementById('addRow').addEventListener('click', function () {
    var tbody = document.querySelector('#expenseTable tbody');
    var tr    = document.createElement('tr');
    tr.className = 'dynamic-row';
    tr.innerHTML =
        '<td><input type="text" class="form-control detail" placeholder="विवरण लेख्नुहोस्"></td>' +
        '<td><input type="number" class="form-control amount" step="0.01" value="0"></td>' +
        '<td><button type="button" class="btn btn-danger btn-sm deleteRow">हटाउनुहोस्</button></td>';
    tbody.appendChild(tr);
});

// ---- Delete row ----
document.addEventListener('click', function (e) {
    if (e.target.classList.contains('deleteRow')) {
        if (confirm('यो row हटाउन चाहनुहुन्छ?')) {
            e.target.closest('tr').remove();
        }
    }
});

// ---- Collect dynamic rows into JSON before submit ----
document.getElementById('expenseForm').addEventListener('submit', function () {
    var rows = [];
    document.querySelectorAll('#expenseTable tbody tr.dynamic-row').forEach(function (tr) {
        var det = tr.querySelector('.detail');
        var amt = tr.querySelector('.amount');
        if (det && amt) {
            rows.push({ detail: det.value.trim(), amount: parseFloat(amt.value) || 0 });
        }
    });
    document.getElementById('expense_table_json').value = JSON.stringify(rows);
});

</script>
</body>
</html>