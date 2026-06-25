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

// Pagination & Search Logic (unchanged)
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
    $stmt->bindValue(1, "%$search%", PDO::PARAM_STR);
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
    <title>Travel Expense Records - Nepal Life Insurance</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body { font-family: 'Outfit', Arial, sans-serif; background: #f4f4f4; }
        .container { max-width: 1250px; margin: 20px auto; background: white; padding: 25px; border-radius: 10px; box-shadow: 0 0 15px rgba(0,0,0,0.1); }
        h1 { color: #003087; }
        th { background: #003087; color: white; }
        .modal-body { max-height: 85vh; overflow-y: auto; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        td, th { border: 1px solid #ccc; padding: 8px; }
        .totals { font-weight: bold; background: #f8f9fa; }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>

<?php if (isset($_GET['updated'])): ?>
    <div class="alert alert-success alert-dismissible fade show">✅ Record updated successfully!</div>
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">❌ Failed to update record.</div>
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

    <!-- Search Form -->
    <form method="GET" class="mb-3">
        <div class="input-group" style="max-width: 400px;">
            <input type="text" name="search" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name, purpose or office...">
            <button class="btn btn-outline-primary" type="submit">Search</button>
            <?php if (!empty($search)): ?>
                <a href="View_ExpenseRecords.php" class="btn btn-outline-secondary">Clear</a>
            <?php endif; ?>
        </div>
    </form>

    <table class="table table-bordered table-hover">
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
                    <td><?= date('Y-m-d', strtotime($row['created_at'])) ?></td>
                    <td>
                        <button class="btn btn-success btn-sm edit-btn"
                            data-id="<?= $row['id'] ?>"
                            data-name="<?= htmlspecialchars($row['name']) ?>"
                            data-position="<?= htmlspecialchars($row['position']) ?>"
                            data-office="<?= htmlspecialchars($row['office']) ?>"
                            data-purpose="<?= htmlspecialchars($row['purpose']) ?>"
                            data-from="<?= $row['from_date'] ?>"
                            data-to="<?= $row['to_date'] ?>"
                            data-fare="<?= $row['fare'] ?>"
                            data-airport="<?= $row['airport'] ?>"
                            data-roadtax="<?= $row['road_tax'] ?>"
                            data-hotel="<?= $row['hotel'] ?>"
                            data-other="<?= $row['other_exp'] ?>"
                            data-daily="<?= $row['daily_rate'] ?>"
                            data-days="<?= $row['days'] ?>"
                            data-advance="<?= $row['advance'] ?>">
                            <i class="bi bi-pencil"></i> Edit
                        </button>
                        <a href="delete_expense.php?id=<?= $row['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this record?')">
                            <i class="bi bi-trash"></i> Delete
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination text-center">
        <?php if ($page > 1): ?>
            <a href="?page=<?= $page-1 ?>&per_page=<?= $records_per_page ?>&search=<?= urlencode($search) ?>" class="btn btn-outline-primary">Previous</a>
        <?php endif; ?>
        <span class="mx-3">Page <?= $page ?> of <?= $total_pages ?></span>
        <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page+1 ?>&per_page=<?= $records_per_page ?>&search=<?= urlencode($search) ?>" class="btn btn-outline-primary">Next</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ==================== DETAILED EDIT MODAL ==================== -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Travel Expense Record</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            
            <form method="POST" id="expenseForm" action="update_expense.php">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="modal-body">
                    <table>
                        <tr>
                            <td>नाम :</td>
                            <td><input type="text" name="name" id="edit_name" required></td>
                            <td>भ्रमण मिति :</td>
                            <td><input type="date" name="from_date" id="edit_from" required> देखि 
                                <input type="date" name="to_date" id="edit_to" required> सम्म</td>
                        </tr>
                        <tr>
                            <td>पद :</td>
                            <td><input type="text" name="position" id="edit_position" required></td>
                            <td>भ्रमण उद्देश्य :</td>
                            <td><input type="text" name="purpose" id="edit_purpose" required></td>
                        </tr>
                        <tr>
                            <td>कार्यालय :</td>
                            <td colspan="3"><input type="text" name="office" id="edit_office" required></td>
                        </tr>
                    </table>

                    <h3>१. भ्रमण विवरण (देखी -सम्म)</h3>
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
                                <td><input type="text" name="vehicle" id="edit_vehicle"></td>
                                <td><input type="number" name="distance" id="edit_distance" step="0.01" oninput="calculateTotals()"></td>
                                <td><input type="number" name="fare" id="edit_fare" step="0.01" oninput="calculateTotals()" value="0"></td>
                                <td><input type="text" name="remarks" id="edit_remarks"></td>
                            </tr>
                            <tr>
                                <td colspan="3">२. एयरपोर्ट ट्याक्स तथा ट्याक्सी खर्च (हवाई यात्रामा)</td>
                                <td><input type="number" name="airport" id="edit_airport" step="0.01" oninput="calculateTotals()" value="0"></td>
                            </tr>
                            <tr>
                                <td colspan="3">३. अन्य खर्च : सडक कर</td>
                                <td><input type="number" name="road_tax" id="edit_roadtax" step="0.01" oninput="calculateTotals()" value="0"></td>
                            </tr>
                            <tr class="totals">
                                <td colspan="3">(क) जम्मा भाडा/इन्धन खर्च</td>
                                <td id="total_fare">0.00</td>
                            </tr>
                        </tbody>
                    </table>

                    <table>
                        <tr>
                            <td>१. दैनिक भ्रमण भत्ता रू. <input type="number" name="daily_rate" id="edit_daily" step="0.01" value="0" oninput="calculateTotals()"> प्रति दिन)</td>
                            <td><input type="number" name="days" id="edit_days" value="0" oninput="calculateTotals()"></td>
                            <td class="totals">(ख) जम्मा दैनिक भत्ता</td>
                            <td id="total_daily">0.00</td>
                        </tr>
                        <tr>
                            <td colspan="2">२. होटेल खर्च (खाना तथा बास)</td>
                            <td><input type="number" name="hotel" id="edit_hotel" step="0.01" oninput="calculateTotals()" value="0"></td>
                            <td></td>
                        </tr>
                        <tr>
                            <td colspan="2">३. अन्य खर्च :</td>
                            <td><input type="number" name="other_exp" id="edit_other" step="0.01" oninput="calculateTotals()" value="0"></td>
                            <td></td>
                        </tr>
                        <tr class="totals">
                            <td colspan="2">(ग) जम्मा होटेल खर्च</td>
                            <td id="total_hotel">0.00</td>
                            <td></td>
                        </tr>
                    </table>

                    <table>
                        <tr>
                            <td>(घ) भ्रमण प्रयोजनको लागि लिएको पेशकी / समायोजन</td>
                            <td><input type="number" name="advance" id="edit_advance" step="0.01" oninput="calculateTotals()" value="0"></td>
                        </tr>
                        <tr class="totals">
                            <td>(ङ) भुक्तानी पाउनु पर्ने/ (तिर्नुपर्ने रकम) [क+ख+ग-घ]</td>
                            <td id="net_amount">0.00</td>
                        </tr>
                    </table>

                    <div class="signature">
                        मिति: <input type="date" name="signature_date" id="edit_signature_date">
                    </div>

                    <h3>अफिस प्रयोजनको लागि मात्रः</h3>
                    <table>
                        <tr><td>१) जम्मा भाडा/इन्धन खर्च स्वीकृत (कोड #4642201)</td><td>रू. <input type="number" name="approved_fare" id="edit_approved_fare" step="0.01"></td></tr>
                        <tr><td>२) जम्मा दैनिक भत्ता (कोड #4642301)</td><td>रू. <input type="number" name="approved_daily" id="edit_approved_daily" step="0.01"></td></tr>
                        <tr><td>३) जम्मा दैनिक होटेल खर्च स्वीकृत (कोड #4642401)</td><td>रू. <input type="number" name="approved_hotel" id="edit_approved_hotel" step="0.01"></td></tr>
                        <tr><td>४) समायोजन रकम</td><td>रू. <input type="number" name="adjustment" id="edit_adjustment" step="0.01"></td></tr>
                        <tr><td>५) भुक्तानी पाउनु पर्ने वा (तिर्नुपर्ने रकम)</td><td>रू. <input type="number" name="final_amount" id="edit_final_amount" step="0.01"></td></tr>
                    </table>
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
// Calculate Totals Function
function calculateTotals() {
    const fare = parseFloat(document.getElementById('edit_fare').value) || 0;
    const airport = parseFloat(document.getElementById('edit_airport').value) || 0;
    const road_tax = parseFloat(document.getElementById('edit_roadtax').value) || 0;
    const daily_rate = parseFloat(document.getElementById('edit_daily').value) || 0;
    const days = parseFloat(document.getElementById('edit_days').value) || 0;
    const hotel = parseFloat(document.getElementById('edit_hotel').value) || 0;
    const other = parseFloat(document.getElementById('edit_other').value) || 0;
    const advance = parseFloat(document.getElementById('edit_advance').value) || 0;

    const total_fare = fare + airport + road_tax;
    const total_daily = daily_rate * days;
    const total_hotel = hotel + other;
    const net = total_fare + total_daily + total_hotel - advance;

    document.getElementById('total_fare').textContent = total_fare.toFixed(2);
    document.getElementById('total_daily').textContent = total_daily.toFixed(2);
    document.getElementById('total_hotel').textContent = total_hotel.toFixed(2);
    document.getElementById('net_amount').textContent = net.toFixed(2);
}

// Edit Button Handler
document.querySelectorAll('.edit-btn').forEach(button => {
    button.addEventListener('click', function() {
        const modal = new bootstrap.Modal(document.getElementById('editModal'));
        
        document.getElementById('edit_id').value = this.dataset.id;
        document.getElementById('edit_name').value = this.dataset.name;
        document.getElementById('edit_position').value = this.dataset.position;
        document.getElementById('edit_office').value = this.dataset.office;
        document.getElementById('edit_purpose').value = this.dataset.purpose;
        document.getElementById('edit_from').value = this.dataset.from;
        document.getElementById('edit_to').value = this.dataset.to;
        document.getElementById('edit_fare').value = this.dataset.fare;
        document.getElementById('edit_airport').value = this.dataset.airport;
        document.getElementById('edit_roadtax').value = this.dataset.roadtax;
        document.getElementById('edit_hotel').value = this.dataset.hotel;
        document.getElementById('edit_other').value = this.dataset.other;
        document.getElementById('edit_daily').value = this.dataset.daily;
        document.getElementById('edit_days').value = this.dataset.days;
        document.getElementById('edit_advance').value = this.dataset.advance;

        // Trigger calculation after filling values
        calculateTotals();
        modal.show();
    });
});
</script>
</body>
</html>