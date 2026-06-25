<?php
require_once 'auth.php';
requireLogin();

$host     = 'localhost';
$dbname   = 'hr';
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
            daily_rate = ?, days = ?, advance = ?, signature_date = ?,
            approved_fare = ?, approved_daily = ?, approved_hotel = ?,
            adjustment = ?, final_amount = ?,
            expense_table_data = ?
            WHERE id = ?");

        $stmt->execute([
            $_POST['name']               ?? '',
            $_POST['position']           ?? '',
            $_POST['office']             ?? '',
            $_POST['purpose']            ?? '',
            $_POST['from_date']          ?: null,
            $_POST['to_date']            ?: null,
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
            $_POST['signature_date']     ?: null,
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
        /* ── Screen styles ─────────────────────────────────────────────── */
        body          { font-family: 'Outfit', Arial, sans-serif; background: #f4f4f4; }
        .container    { max-width: 1350px; margin: 5px 10px; background: white;
                        padding: 5px; border-radius: 10px; box-shadow: 0 0 15px rgba(0,0,0,.1); }
        h1            { color: #003087; }

        /* ── FIX: Force left-align on all table cells ── */
        thead th      { background: #003087; color: white; text-align: left; }
        tbody td      { text-align: left; vertical-align: middle; }

        .modal-body   { max-height: 80vh; overflow-y: auto; }

        /* ── PRINT styles ──────────────────────────────────────────────── */
        @media print {
            /* Hide everything by default */
            body > *                          { display: none !important; }

            /* Show only the print container */
            #print-container                  { display: block !important; }

            #print-container {
                position: fixed;
                inset: 0;
                background: white;
                padding: 18mm 15mm;
                font-family: Arial, sans-serif;
                font-size: 11pt;
                color: #000;
            }

            /* Logo / header */
            #print-container .print-logo      { display: block; text-align: center; margin-bottom: 6px; }
            #print-container .print-logo img  { height: 60px; }
            #print-container .print-title     { text-align: center; font-size: 15pt;
                                                font-weight: bold; color: #003087;
                                                margin-bottom: 14px; }

            /* Tables */
            #print-container table            { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
            #print-container th,
            #print-container td               { border: 1px solid #000; padding: 5px 8px;
                                                vertical-align: middle; }
            #print-container .no-border td,
            #print-container .no-border th   { border: none; padding: 3px 6px; }
            #print-container .total-row td    { font-weight: bold; background: #f0f0f0; }

            /* Signature row */
            #print-container .sig-row         { margin-top: 30px; display: flex;
                                                justify-content: space-between; }
            #print-container .sig-box         { text-align: center; width: 30%; }
            #print-container .sig-line        { border-top: 1px solid #000;
                                                margin-top: 30px; padding-top: 4px; }

            /* Office-use section */
            #print-container .section-title   { font-weight: bold; margin: 14px 0 4px; font-size: 10pt; }

            @page { size: A4 portrait; margin: 0; }
        }

        /* Hide print container on screen */
        #print-container { display: none; }
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
        <a href="export_csv.php"     class="btn btn-info text-white">Export All to CSV</a>
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
    <!-- FIX: Added text-start class to force left-alignment on all cells -->
    <table class="table table-bordered table-hover align-middle text-start">
        <thead>
            <tr>
                <th>ID</th>
                <th>Travel Order No</th>
                <th>Emp Code</th>
                <th>Name</th>
                <th>Position</th>
                <th>Office</th>
                <th>Purpose</th>
                <th>Dates</th>
                <th>Total Fare</th>
                <th>Total Hotel</th>
                <th>Net Amount</th>
                <th>Created</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($records)): ?>
            <tr><td colspan="14" class="text-center">No records found.</td></tr>
        <?php else: ?>
            <?php foreach ($records as $row):
                $total_fare  = $row['fare'] + $row['airport'] + $row['road_tax'];
                $total_hotel = $row['hotel'] + $row['other_exp'];
                $net         = $total_fare + ($row['daily_rate'] * $row['days']) + $total_hotel - $row['advance'];

                $da = [
                    'id'             => $row['id'],
                    'emp_id'         => htmlspecialchars($row['emp_id'],              ENT_QUOTES),
                    'name'           => htmlspecialchars($row['name'],                ENT_QUOTES),
                    'position'       => htmlspecialchars($row['position'],            ENT_QUOTES),
                    'office'         => htmlspecialchars($row['office'],              ENT_QUOTES),
                    'purpose'        => htmlspecialchars($row['purpose'],             ENT_QUOTES),
                    'from'           => $row['from_date'],
                    'to'             => $row['to_date'],
                    'fare'           => $row['fare'],
                    'airport'        => $row['airport'],
                    'roadtax'        => $row['road_tax'],
                    'hotel'          => $row['hotel'],
                    'other'          => $row['other_exp'],
                    'daily'          => $row['daily_rate'],
                    'days'           => $row['days'],
                    'advance'        => $row['advance'],
                    'vehicle'        => htmlspecialchars($row['vehicle']       ?? '', ENT_QUOTES),
                    'distance'       => $row['distance']       ?? 0,
                    'remarks'        => htmlspecialchars($row['remarks']       ?? '', ENT_QUOTES),
                    'approvedfare'   => $row['approved_fare']  ?? 0,
                    'approveddaily'  => $row['approved_daily'] ?? 0,
                    'approvedhotel'  => $row['approved_hotel'] ?? 0,
                    'adjustment'     => $row['adjustment']     ?? 0,
                    'finalamount'    => $row['final_amount']   ?? 0,
                    'tabledata'      => htmlspecialchars($row['expense_table_data'] ?? '[]', ENT_QUOTES),
                    'document'       => htmlspecialchars($row['document_path'] ?? '', ENT_QUOTES),
                    'signaturedate'  => $row['signature_date'] ?? '',
                    'officeremarks'  => htmlspecialchars($row['office_remarks'] ?? '', ENT_QUOTES),
                    'status'         => htmlspecialchars($row['status'] ?? 'Pending', ENT_QUOTES),
                ];

                $dataAttrs = '';
                foreach ($da as $k => $v) {
                    $dataAttrs .= ' data-' . $k . '="' . $v . '"';
                }
            ?>
            <tr>
                <td><?= $row['id'] ?></td>
                <td><?= htmlspecialchars($row['travel_order_no']) ?></td>
                <td><?= htmlspecialchars($row['emp_id']) ?></td>
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
                    <span class="badge <?= ($row['status'] ?? 'Pending') === 'Approved' ? 'bg-success' : 'bg-warning text-dark' ?>">
                        <?= htmlspecialchars($row['status'] ?? 'Pending') ?>
                    </span>
                </td>
                <td class="text-nowrap">
                    <button class="btn btn-primary btn-sm view-btn" <?= $dataAttrs ?>>
                        <i class="bi bi-eye"></i> View
                    </button>
                    <?php if (($row['status'] ?? 'Pending') !== 'Approved'): ?>
                    <button class="btn btn-success btn-sm edit-btn" <?= $dataAttrs ?>>
                        <i class="bi bi-pencil"></i> Edit
                    </button>
                    <?php endif; ?>
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


<!-- ══════════════════════════════════════════════════════════════════
     EDIT MODAL
═══════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">

            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">✏️ Edit Travel Expense – ID: <span id="edit_id_label"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <form method="POST" id="expenseForm">
                <input type="hidden" name="id" id="edit_id">

                <div class="modal-body">

                    <!-- Basic Info -->
                    <table class="table table-borderless">
                        <tr>
                            <td style="width:110px"><strong>नाम :</strong></td>
                            <td><input type="text" name="name" id="edit_name" class="form-control" readonly></td>
                            <td style="width:130px"><strong>भ्रमण मिति :</strong></td>
                            <td>
                                <input type="date" name="from_date" id="edit_from" class="form-control d-inline w-auto" readonly>
                                देखि
                                <input type="date" name="to_date"   id="edit_to"   class="form-control d-inline w-auto" readonly>
                                सम्म
                            </td>
                        </tr>
                        <tr>
                            <td><strong>पद :</strong></td>
                            <td><input type="text" name="position" id="edit_position" class="form-control" readonly></td>
                            <td><strong>उद्देश्य :</strong></td>
                            <td><input type="text" name="purpose"  id="edit_purpose"  class="form-control" readonly></td>
                        </tr>
                        <tr>
                            <td><strong>कार्यालय :</strong></td>
                            <td colspan="3"><input type="text" name="office" id="edit_office" class="form-control" readonly></td>
                        </tr>
                    </table>

                    <!-- Section 1: Travel Detail -->
                    <h6 class="fw-bold mt-2">१. भ्रमण विवरण</h6>
                    <table class="table table-bordered">
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
                                <td><input type="text"   name="vehicle"  id="edit_vehicle"  class="form-control" readonly></td>
                                <td><input type="number" name="distance" id="edit_distance" class="form-control" step="0.01" value="0" oninput="calculateTotals()"></td>
                                <td><input type="number" name="fare"     id="edit_fare"     class="form-control" step="0.01" value="0" oninput="calculateTotals()"></td>
                                <td><input type="text"   name="remarks"  id="edit_remarks"  class="form-control"></td>
                            </tr>
                            <tr>
                                <td colspan="3">२. एयरपोर्ट ट्याक्स तथा ट्याक्सी खर्च</td>
                                <td><input type="number" name="airport"  id="edit_airport"  class="form-control" step="0.01" value="0" oninput="calculateTotals()"></td>
                            </tr>
                            <tr>
                                <td colspan="3">३. सडक कर</td>
                                <td><input type="number" name="road_tax" id="edit_roadtax"  class="form-control" step="0.01" value="0" oninput="calculateTotals()"></td>
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
                            <td><input type="number" name="hotel"     id="edit_hotel"  class="form-control" step="0.01" value="0" oninput="calculateTotals()"></td>
                        </tr>
                        <tr>
                            <td colspan="2">३. अन्य खर्च</td>
                            <td><input type="number" name="other_exp" id="edit_other"  class="form-control" step="0.01" value="0" oninput="calculateTotals()"></td>
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
                        मिति: <input type="date" name="signature_date" id="edit_signature_date"
                                     class="form-control d-inline w-auto">
                    </div>

                    <!-- Office-use table -->
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

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-info"      id="editPrintBtn">
                        <i class="bi bi-printer"></i> Print
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-floppy"></i> Update Record
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>


<!-- ══════════════════════════════════════════════════════════════════
     VIEW MODAL
═══════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">

            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Travel Expense Details – ID: <span id="view_id"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>EmpID:</strong>         <span id="emp_id"></span><br>
                        <strong>Name:</strong>           <span id="view_name"></span><br>
                        <strong>Position:</strong>       <span id="view_position"></span><br>
                        <strong>Office:</strong>         <span id="view_office"></span><br>
                        <strong>Purpose:</strong>        <span id="view_purpose"></span><br>
                        <strong>Dates:</strong>          <span id="view_dates"></span><br>
                        <strong>Signature Date:</strong> <span id="view_signature"></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Vehicle:</strong>  <span id="view_vehicle"></span><br>
                        <strong>Distance:</strong> <span id="view_distance"></span> km<br>
                        <strong>Remarks:</strong>  <span id="view_remarks"></span>
                    </div>
                </div>

                <hr>
                <h6 class="fw-bold">Expense Breakdown</h6>
                <table class="table table-bordered">
                    <tr><th>Fare / Fuel</th>              <td id="view_fare"></td></tr>
                    <tr><th>Airport + Road Tax</th>       <td id="view_airport_road"></td></tr>
                    <tr><th class="table-info">Total Transport (क)</th><td id="view_total_fare"  class="table-info fw-bold"></td></tr>
                    <tr><th>Daily Allowance (ख)</th>      <td id="view_daily"></td></tr>
                    <tr><th>Hotel + Other (ग)</th>        <td id="view_hotel"></td></tr>
                    <tr><th>Advance Taken (घ)</th>        <td id="view_advance"></td></tr>
                    <tr class="table-success fw-bold">
                        <th>Net Amount (ङ)</th><td id="view_net"></td>
                    </tr>
                </table>

                <hr>
                <h6 class="fw-bold">Uploaded Document</h6>
                <div id="document_preview" class="text-center border p-3"
                     style="min-height:300px; background:#f8f9fa;"></div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-info"      id="viewPrintBtn">
                    <i class="bi bi-printer"></i> Print
                </button>
            </div>

        </div>
    </div>
</div>


<!-- ══════════════════════════════════════════════════════════════════
     HIDDEN PRINT CONTAINER  (only visible during window.print())
     Populated by JS before printing.
═══════════════════════════════════════════════════════════════════════ -->
<div id="print-container">
    <!-- Injected by buildPrintContent() -->
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>

// ── Auto-redirect after update ─────────────────────────────────────────────
<?php if (isset($_GET['updated'])): ?>
window.addEventListener('load', function () {
    setTimeout(function () { window.location.href = 'View_ExpenseRecords.php'; }, 2000);
});
<?php endif; ?>

// ── calculateTotals (edit modal) ───────────────────────────────────────────
function calculateTotals() {
    var fare     = parseFloat(document.getElementById('edit_fare').value)    || 0;
    var airport  = parseFloat(document.getElementById('edit_airport').value) || 0;
    var road_tax = parseFloat(document.getElementById('edit_roadtax').value) || 0;
    var daily    = parseFloat(document.getElementById('edit_daily').value)   || 0;
    var days     = parseFloat(document.getElementById('edit_days').value)    || 0;
    var hotel    = parseFloat(document.getElementById('edit_hotel').value)   || 0;
    var other    = parseFloat(document.getElementById('edit_other').value)   || 0;
    var advance  = parseFloat(document.getElementById('edit_advance').value) || 0;

    var tf  = fare + airport + road_tax;
    var td  = daily * days;
    var th  = hotel + other;
    var net = tf + td + th - advance;

    document.getElementById('total_fare').innerHTML  = '<strong>' + tf.toFixed(2)  + '</strong>';
    document.getElementById('total_daily').innerHTML = '<strong>' + td.toFixed(2)  + '</strong>';
    document.getElementById('total_hotel').innerHTML = '<strong>' + th.toFixed(2)  + '</strong>';
    document.getElementById('net_amount').textContent = net.toFixed(2);
}

// ── Shared data store (set when any modal is opened) ──────────────────────
let currentRecord = {};

// ── Open EDIT modal ────────────────────────────────────────────────────────
document.querySelectorAll('.edit-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var d = this.dataset;
        currentRecord = d;    // store for print

        document.getElementById('edit_id_label').textContent = d.id;
        document.getElementById('edit_id').value        = d.id;
        document.getElementById('edit_name').value      = d.name;
        document.getElementById('edit_position').value  = d.position;
        document.getElementById('edit_office').value    = d.office;
        document.getElementById('edit_purpose').value   = d.purpose;
        document.getElementById('edit_from').value      = d.from;
        document.getElementById('edit_to').value        = d.to;
        document.getElementById('edit_vehicle').value   = d.vehicle;
        document.getElementById('edit_distance').value  = d.distance;
        document.getElementById('edit_remarks').value   = d.remarks;
        document.getElementById('edit_fare').value      = d.fare;
        document.getElementById('edit_airport').value   = d.airport;
        document.getElementById('edit_roadtax').value   = d.roadtax;
        document.getElementById('edit_hotel').value     = d.hotel;
        document.getElementById('edit_other').value     = d.other;
        document.getElementById('edit_daily').value     = d.daily;
        document.getElementById('edit_days').value      = d.days;
        document.getElementById('edit_advance').value   = d.advance;
        document.getElementById('approved_fare').value  = d.approvedfare;
        document.getElementById('approved_daily').value = d.approveddaily;
        document.getElementById('approved_hotel').value = d.approvedhotel;
        document.getElementById('adjustment').value     = d.adjustment;
        document.getElementById('final_amount').value   = d.finalamount;
        document.getElementById('edit_signature_date').value = d.signaturedate || '';

        // Rebuild dynamic extra rows
        var tbody = document.querySelector('#expenseTable tbody');
        tbody.querySelectorAll('tr.dynamic-row').forEach(function (r) { r.remove(); });
        try {
            JSON.parse(d.tabledata || '[]').forEach(function (item) {
                appendDynamicRow(tbody, item.detail, item.amount);
            });
        } catch (e) { /* bad JSON — skip */ }

        calculateTotals();
        new bootstrap.Modal(document.getElementById('editModal')).show();
    });
});

// ── Open VIEW modal ────────────────────────────────────────────────────────
document.querySelectorAll('.view-btn').forEach((btn) => {
    btn.addEventListener('click', function () {
        const d = { ...this.dataset };
        currentRecord = d; // store for print

        const setText = (id, value, fallback = '') => {
            const el = document.getElementById(id);
            if (el) el.textContent = value || fallback;
        };

        const num = (value) => {
            const parsed = parseFloat(value);
            return Number.isFinite(parsed) ? parsed : 0;
        };

        const money = (value) => `रू. ${num(value).toFixed(2)}`;

        setText('view_id', d.id);
        setText('emp_id', d.emp_id);
        setText('view_name', d.name);
        setText('view_position', d.position);
        setText('view_office', d.office);
        setText('view_purpose', d.purpose);
        setText('view_dates', `${d.from || ''} – ${d.to || ''}`);
        setText('view_vehicle', d.vehicle, 'N/A');
        setText('view_distance', d.distance);
        setText('view_remarks', d.remarks, '—');
        setText('view_signature', d.signaturedate, 'N/A');

        const fare    = num(d.fare);
        const airport = num(d.airport);
        const roadtax = num(d.roadtax);
        const daily   = num(d.daily);
        const days    = num(d.days);
        const hotel   = num(d.hotel);
        const other   = num(d.other);
        const advance = num(d.advance);

        const totalFare  = fare + airport + roadtax;
        const totalDaily = daily * days;
        const totalHotel = hotel + other;
        const net        = totalFare + totalDaily + totalHotel - advance;

        setText('view_fare',         money(fare));
        setText('view_airport_road', money(airport + roadtax));
        setText('view_total_fare',   money(totalFare));
        setText('view_daily',        `रू. ${totalDaily.toFixed(2)} (${daily.toFixed(2)} × ${days} days)`);
        setText('view_hotel',        money(totalHotel));
        setText('view_advance',      money(advance));
        setText('view_net',          money(net));

        const preview = document.getElementById('document_preview');
        if (!preview) return;

        preview.innerHTML = '';

        if (d.document && d.document.trim() !== '') {
            const fileUrl  = d.document.trim();
            const ext      = fileUrl.split('.').pop().toLowerCase();
            const fileName = fileUrl.split('/').pop();

            if (['jpg', 'jpeg', 'png'].includes(ext)) {
                preview.innerHTML = `
                    <img src="${fileUrl}" alt="Travel Bill"
                         style="max-width:100%;max-height:500px;border:1px solid #ddd;">
                `;
            } else if (ext === 'pdf') {
                preview.innerHTML = `
                    <iframe src="${fileUrl}"
                            style="width:100%;height:500px;border:none;"
                            frameborder="0"></iframe>
                `;
            } else {
                preview.innerHTML = `
                    <div class="alert alert-info">
                        <i class="bi bi-file-earmark-text"></i>
                        <strong></strong><br><br>
                        <a href="${fileUrl}" target="_blank" class="btn btn-primary btn-sm">
                            <i class="bi bi-download"></i> Download
                        </a>
                    </div>
                `;
                preview.querySelector('strong').textContent = fileName;
            }
        } else {
            preview.innerHTML = `
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-circle"></i>
                    No document uploaded for this record.
                </div>
            `;
        }

        const modalEl = document.getElementById('viewModal');
        if (modalEl) {
            new bootstrap.Modal(modalEl).show();
        }
    });
});

// ── Add / delete dynamic rows ──────────────────────────────────────────────
function appendDynamicRow(tbody, detail, amount) {
    var tr = document.createElement('tr');
    tr.className = 'dynamic-row';
    tr.innerHTML =
        '<td><input type="text"   class="form-control detail" value="' + (detail || '') + '" placeholder="विवरण लेख्नुहोस्"></td>' +
        '<td><input type="number" class="form-control amount" step="0.01" value="' + (amount || 0) + '"></td>' +
        '<td><button type="button" class="btn btn-danger btn-sm deleteRow">हटाउनुहोस्</button></td>';
    tbody.appendChild(tr);
}

document.getElementById('addRow').addEventListener('click', function () {
    appendDynamicRow(document.querySelector('#expenseTable tbody'), '', 0);
});

document.addEventListener('click', function (e) {
    if (e.target.classList.contains('deleteRow')) {
        if (confirm('यो row हटाउन चाहनुहुन्छ?')) e.target.closest('tr').remove();
    }
});

// ── Collect dynamic rows before submit ────────────────────────────────────
document.getElementById('expenseForm').addEventListener('submit', function () {
    var rows = [];
    document.querySelectorAll('#expenseTable tbody tr.dynamic-row').forEach(function (tr) {
        var det = tr.querySelector('.detail');
        var amt = tr.querySelector('.amount');
        if (det && amt) rows.push({ detail: det.value.trim(), amount: parseFloat(amt.value) || 0 });
    });
    document.getElementById('expense_table_json').value = JSON.stringify(rows);
});

// ══════════════════════════════════════════════════════════════════════════════
// PRINT SYSTEM
// ══════════════════════════════════════════════════════════════════════════════
function fmt(n) { return 'रू. ' + (parseFloat(n) || 0).toFixed(2); }

function buildPrintContent(record, useEditValues) {
    var name     = useEditValues ? document.getElementById('edit_name').value     : record.name;
    var position = useEditValues ? document.getElementById('edit_position').value : record.position;
    var office   = useEditValues ? document.getElementById('edit_office').value   : record.office;
    var purpose  = useEditValues ? document.getElementById('edit_purpose').value  : record.purpose;
    var fromDate = useEditValues ? document.getElementById('edit_from').value     : record.from;
    var toDate   = useEditValues ? document.getElementById('edit_to').value       : record.to;
    var vehicle  = useEditValues ? document.getElementById('edit_vehicle').value  : record.vehicle;
    var distance = useEditValues ? document.getElementById('edit_distance').value : record.distance;
    var remarks  = useEditValues ? document.getElementById('edit_remarks').value  : record.remarks;
    var sigDate  = useEditValues ? document.getElementById('edit_signature_date').value : record.signaturedate;

    var fare     = parseFloat(useEditValues ? document.getElementById('edit_fare').value    : record.fare)    || 0;
    var airport  = parseFloat(useEditValues ? document.getElementById('edit_airport').value : record.airport) || 0;
    var roadTax  = parseFloat(useEditValues ? document.getElementById('edit_roadtax').value : record.roadtax) || 0;
    var daily    = parseFloat(useEditValues ? document.getElementById('edit_daily').value   : record.daily)   || 0;
    var days     = parseFloat(useEditValues ? document.getElementById('edit_days').value    : record.days)    || 0;
    var hotel    = parseFloat(useEditValues ? document.getElementById('edit_hotel').value   : record.hotel)   || 0;
    var other    = parseFloat(useEditValues ? document.getElementById('edit_other').value   : record.other)   || 0;
    var advance  = parseFloat(useEditValues ? document.getElementById('edit_advance').value : record.advance) || 0;

    var apFare   = parseFloat(useEditValues ? document.getElementById('approved_fare').value  : record.approvedfare)  || 0;
    var apDaily  = parseFloat(useEditValues ? document.getElementById('approved_daily').value : record.approveddaily) || 0;
    var apHotel  = parseFloat(useEditValues ? document.getElementById('approved_hotel').value : record.approvedhotel) || 0;
    var adjust   = parseFloat(useEditValues ? document.getElementById('adjustment').value     : record.adjustment)    || 0;
    var finalAmt = parseFloat(useEditValues ? document.getElementById('final_amount').value   : record.finalamount)   || 0;

    var tf  = fare + airport + roadTax;
    var td  = daily * days;
    var th  = hotel + other;
    var net = tf + td + th - advance;

    var extraRows = '';
    if (useEditValues) {
        document.querySelectorAll('#expenseTable tbody tr.dynamic-row').forEach(function (tr) {
            var det = tr.querySelector('.detail');
            var amt = tr.querySelector('.amount');
            if (det && amt) {
                extraRows += '<tr><td>' + (det.value || '') + '</td><td>' + fmt(amt.value) + '</td></tr>';
            }
        });
    } else {
        try {
            JSON.parse(record.tabledata || '[]').forEach(function (item) {
                extraRows += '<tr><td>' + (item.detail || '') + '</td><td>' + fmt(item.amount) + '</td></tr>';
            });
        } catch(e) {}
    }

    return `
        <div class="print-logo">
            <img src="images/logo.png" alt="Nepal Life Insurance">
        </div>
        <div class="print-title">भत्ता तथा खर्च विवरण</div>

        <table class="no-border">
            <tr>
                <td width="15%"><strong>नाम :</strong></td>
                <td width="35%">${name}</td>
                <td width="15%"><strong>मिति देखि :</strong></td>
                <td>${fromDate} देखि ${toDate} सम्म</td>
            </tr>
            <tr>
                <td><strong>पद :</strong></td>
                <td>${position}</td>
                <td><strong>उद्देश्य :</strong></td>
                <td>${purpose}</td>
            </tr>
            <tr>
                <td><strong>कार्यालय :</strong></td>
                <td colspan="3">${office}</td>
            </tr>
        </table>

        <div class="section-title">१. भ्रमण विवरण (देखी – सम्म)</div>
        <table>
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
                    <td>${vehicle}</td>
                    <td>${distance}</td>
                    <td>${fare.toFixed(2)}</td>
                    <td>${remarks}</td>
                </tr>
                <tr>
                    <td colspan="3">२. एयरपोर्ट ट्याक्स तथा ट्याक्सी खर्च (हवाई यात्रामा)</td>
                    <td>${airport.toFixed(2)}</td>
                </tr>
                <tr>
                    <td colspan="3">३. अन्य खर्च : सडक कर</td>
                    <td>${roadTax.toFixed(2)}</td>
                </tr>
                <tr class="total-row">
                    <td colspan="3">(क) जम्मा भाडा/इन्धन खर्च</td>
                    <td>${tf.toFixed(2)}</td>
                </tr>
            </tbody>
        </table>

        <table>
            <tr>
                <td>१. दैनिक भ्रमण भत्ता रू. ${daily.toFixed(2)} × ${days} दिन</td>
                <td width="25%">(ख) जम्मा दैनिक भत्ता</td>
                <td width="20%">${td.toFixed(2)}</td>
            </tr>
            <tr>
                <td colspan="2">२. होटेल खर्च (खाना तथा बास)</td>
                <td>${hotel.toFixed(2)}</td>
            </tr>
            <tr>
                <td colspan="2">३. अन्य खर्च</td>
                <td>${other.toFixed(2)}</td>
            </tr>
            <tr class="total-row">
                <td colspan="2">(ग) जम्मा होटेल खर्च</td>
                <td>${th.toFixed(2)}</td>
            </tr>
        </table>

        <table>
            <tr>
                <td>(घ) भ्रमण प्रयोजनको लागि लिएको पेशकी / समायोजन</td>
                <td width="25%">${advance.toFixed(2)}</td>
            </tr>
            <tr class="total-row">
                <td>(ङ) भुक्तानी पाउनु पर्ने / (तिर्नुपर्ने रकम) [क+ख+ग−घ]</td>
                <td>${net.toFixed(2)}</td>
            </tr>
        </table>

        <div class="section-title">अफिस प्रयोजनको लागि मात्रः</div>
        <table>
            <thead><tr><th width="70%">विवरण</th><th>रकम (रू.)</th></tr></thead>
            <tbody>
                <tr><td>१) जम्मा भाडा/इन्धन खर्च स्वीकृत (कोड #4642201)</td><td>${apFare.toFixed(2)}</td></tr>
                <tr><td>२) जम्मा दैनिक भत्ता (कोड #4642301)</td><td>${apDaily.toFixed(2)}</td></tr>
                <tr><td>३) जम्मा होटेल खर्च स्वीकृत (कोड #4642401)</td><td>${apHotel.toFixed(2)}</td></tr>
                <tr><td>४) समायोजन रकम</td><td>${adjust.toFixed(2)}</td></tr>
                <tr><td>५) भुक्तानी पाउनु पर्ने वा (तिर्नुपर्ने रकम)</td><td>${finalAmt.toFixed(2)}</td></tr>
                ${extraRows}
            </tbody>
        </table>

        <p style="margin-top:10px;font-size:10pt;">मिति: ${sigDate || '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'}</p>

        <div class="sig-row">
            <div class="sig-box">
                <div class="sig-line">दरखास्तकर्ताको दस्तखत</div>
            </div>
            <div class="sig-box">
                <div class="sig-line">शाखा/कार्यालय प्रमुखको दस्तखत</div>
            </div>
            <div class="sig-box">
                <div class="sig-line">लेखा अधिकृतको दस्तखत</div>
            </div>
        </div>
    `;
}

// Print from Edit modal
document.getElementById('editPrintBtn').addEventListener('click', function () {
    calculateTotals();
    document.getElementById('print-container').innerHTML = buildPrintContent(currentRecord, true);
    window.print();
});

// Print from View modal
document.getElementById('viewPrintBtn').addEventListener('click', function () {
    document.getElementById('print-container').innerHTML = buildPrintContent(currentRecord, false);
    window.print();
});

// Keyboard shortcut Ctrl+P / Cmd+P
document.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
        var editOpen = document.getElementById('editModal').classList.contains('show');
        var viewOpen = document.getElementById('viewModal').classList.contains('show');
        if (editOpen || viewOpen) {
            e.preventDefault();
            if (editOpen) {
                calculateTotals();
                document.getElementById('print-container').innerHTML = buildPrintContent(currentRecord, true);
            } else {
                document.getElementById('print-container').innerHTML = buildPrintContent(currentRecord, false);
            }
            window.print();
        }
    }
});

</script>
</body>
</html>