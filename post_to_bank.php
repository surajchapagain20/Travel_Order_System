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

// Handle AJAX Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_bank_post') {
    $id = intval($_POST['id']);
    $data = $_POST['data'] ?? '[]';
    
    // Ensure data is valid JSON
    json_decode($data);
    if (json_last_error() === JSON_ERROR_NONE) {
        $stmt = $pdo->prepare("UPDATE travel_expenses SET bank_post_data = ? WHERE id = ?");
        if ($stmt->execute([$data, $id])) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Database update failed']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid JSON data']);
    }
    exit;
}

// Fetch approved expenses with employee details
$stmt = $pdo->prepare("
    SELECT t.*, e.BrCode, e.level, p.PostName as employeeDesignation
    FROM travel_expenses t
    LEFT JOIN employees e ON t.emp_id = e.EmpID
    LEFT JOIN posts p ON e.designation = p.PostId
    WHERE t.status = 'Approved'
    ORDER BY t.id DESC
");
$stmt->execute();
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=post_to_bank_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Emp Code', 'Account Code', 'Name', 'Designation', 'Branch', 'Office', 'Purpose', 'Amount']);
    
    foreach ($records as $row) {
        $total_fare  = $row['fare'] + $row['airport'] + $row['road_tax'];
        $total_hotel = $row['hotel'] + $row['other_exp'];
        $net         = $total_fare + ($row['daily_rate'] * $row['days']) + $total_hotel - $row['advance'];
        
        $bank_post_data = json_decode($row['bank_post_data'], true);
        if (empty($bank_post_data)) {
            $bank_post_data = [['accode' => '', 'amount' => $net]];
        }
        
        foreach ($bank_post_data as $bpost) {
            fputcsv($output, [
                $row['id'],
                $row['emp_id'],
                $bpost['accode'],
                $row['name'],
                $row['employeeDesignation'] ?: $row['position'],
                $row['BrCode'],
                $row['office'],
                $row['purpose'],
                number_format((float)$bpost['amount'], 2, '.', '')
            ]);
        }
    }
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="ne">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post to Bank - HR Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', Arial, sans-serif; background: #f4f4f4; }
        .container-main { max-width: 1400px; margin: 10px 5px; background: white; padding: 10px; border-radius: 5px; box-shadow: 0 0 5px rgba(0,0,0,.1); }
        h1 { color: #003087; }
        .table thead th { background-color: #003087 !important; color: #ffffff !important; }
        .inner-table { margin-bottom: 0; background: transparent; }
        .inner-table td { padding: 4px; border: none; }
        
        @media print {
            body * { visibility: hidden; }
            .container-main, .container-main * { visibility: visible; }
            .container-main { position: absolute; left: 0; top: 0; width: 100%; box-shadow: none; margin: 0; padding: 0; }
            .no-print { display: none !important; }
            table { border-collapse: collapse; width: 100%; }
            th, td { border: 1px solid #000 !important; padding: 8px; font-size: 14px; }
            th { background-color: #f2f2f2 !important; color: #000 !important; }
            .table-responsive { overflow: visible; }
            .print-only { display: block !important; }
        }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="container-main">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="bi bi-bank"></i> Post to Bank</h1>
        <div class="no-print">
            <button onclick="window.print()" class="btn btn-secondary me-2"><i class="bi bi-printer"></i> Print</button>
            <a href="?export=csv" class="btn btn-success"><i class="bi bi-file-earmark-excel"></i> Export CSV</a>
        </div>
    </div>

    <!-- Alert for save status -->
    <div id="statusAlert" class="alert d-none" role="alert"></div>

    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Emp Code</th>
                    <th>Name</th>
                    <th>Designation</th>
                    <th>Branch</th>
                    <th>Purpose</th>
                    <th>Net Amt (रू.)</th>
                    <th style="min-width: 300px;">Post Details (Acc Code & Amount)</th>
                    <th class="no-print text-center">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($records)): ?>
                <tr><td colspan="9" class="text-center">No approved records found.</td></tr>
            <?php else: ?>
                <?php 
                $total_sum = 0;
                foreach ($records as $row):
                    $total_fare  = $row['fare'] + $row['airport'] + $row['road_tax'];
                    $total_hotel = $row['hotel'] + $row['other_exp'];
                    $net         = $total_fare + ($row['daily_rate'] * $row['days']) + $total_hotel - $row['advance'];
                    $total_sum += $net;
                    
                    $bank_post_data = json_decode($row['bank_post_data'], true);
                    if (empty($bank_post_data)) {
                        $bank_post_data = [['accode' => '', 'amount' => $net]];
                    }
                ?>
                <tr data-record-id="<?= $row['id'] ?>" data-net-amt="<?= $net ?>">
                    <td><?= $row['id'] ?></td>
                    <td><?= htmlspecialchars($row['emp_id']) ?></td>
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td><?= htmlspecialchars($row['employeeDesignation'] ?: $row['position']) ?></td>
                    <td><?= htmlspecialchars($row['BrCode'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['purpose']) ?></td>
                    <td class="fw-bold"><?= number_format($net, 2) ?></td>
                    <td>
                        <!-- Interactive Editor -->
                        <div class="post-details-editor no-print">
                            <table class="inner-table w-100">
                                <tbody>
                                    <?php 
                                    $post_total = 0;
                                    foreach ($bank_post_data as $idx => $bpost): 
                                        $post_total += (float)$bpost['amount'];
                                    ?>
                                    <tr class="bpost-row">
                                        <td><input type="text" class="form-control form-control-sm acct-code" placeholder="Account Code" value="<?= htmlspecialchars($bpost['accode']) ?>"></td>
                                        <td><input type="number" step="0.01" class="form-control form-control-sm acct-amount" placeholder="Amount" value="<?= htmlspecialchars($bpost['amount']) ?>" oninput="updatePostTotal(this)"></td>
                                        <td><button type="button" class="btn btn-sm btn-outline-danger remove-row" onclick="const table = this.closest('table'); this.closest('tr').remove(); updatePostTotal(table);"><i class="bi bi-x"></i></button></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td class="text-end fw-bold">Total:</td>
                                        <td><input type="text" class="form-control form-control-sm total-post-amount fw-bold" readonly value="<?= number_format($post_total, 2, '.', '') ?>"></td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                            <button type="button" class="btn btn-sm btn-link text-decoration-none mt-1 add-row-btn" onclick="addBankPostRow(this, <?= $net ?>)"><i class="bi bi-plus-circle"></i> Add Row</button>
                        </div>
                        
                        <!-- Print View (Hidden on screen) -->
                        <div class="d-none d-print-block">
                            <ul class="list-unstyled mb-0">
                                <?php foreach ($bank_post_data as $bpost): ?>
                                    <li><strong><?= htmlspecialchars($bpost['accode'] ?: 'N/A') ?></strong>: <?= number_format((float)$bpost['amount'], 2) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </td>
                    <td class="no-print text-center">
                        <button type="button" class="btn btn-primary btn-sm save-btn" onclick="saveBankPost(this, <?= $row['id'] ?>)">Save</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <tr>
                    <td colspan="6" class="text-end fw-bold">Total Net Amount</td>
                    <td class="fw-bold text-success"><?= number_format($total_sum, 2) ?></td>
                    <td colspan="2"></td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Status Modal -->
<div class="modal fade" id="statusModal" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="statusModalLabel">Notification</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="statusModalBody">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Revise / Close</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function updatePostTotal(element) {
    const table = element.closest('.inner-table');
    if (!table) return;
    let total = 0;
    table.querySelectorAll('.acct-amount').forEach(input => {
        total += parseFloat(input.value) || 0;
    });
    const totalInput = table.querySelector('.total-post-amount');
    if (totalInput) {
        totalInput.value = total.toFixed(2);
    }
}

function addBankPostRow(btn, maxAmount) {
    const tbody = btn.previousElementSibling.querySelector('tbody');
    const tr = document.createElement('tr');
    tr.className = 'bpost-row';
    tr.innerHTML = `
        <td><input type="text" class="form-control form-control-sm acct-code" placeholder="Account Code" value=""></td>
        <td><input type="number" step="0.01" class="form-control form-control-sm acct-amount" placeholder="Amount" value="" oninput="updatePostTotal(this)"></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger remove-row" onclick="const table = this.closest('table'); this.closest('tr').remove(); updatePostTotal(table);"><i class="bi bi-x"></i></button></td>
    `;
    tbody.appendChild(tr);
}

function showStatus(message, isSuccess) {
    const modalEl = document.getElementById('statusModal');
    const modalBody = document.getElementById('statusModalBody');
    const modalTitle = document.getElementById('statusModalLabel');
    
    modalTitle.textContent = isSuccess ? 'Success' : 'Error';
    modalTitle.className = 'modal-title ' + (isSuccess ? 'text-success' : 'text-danger');
    modalBody.textContent = message;
    
    // Create or get the modal instance and show it
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
}

function saveBankPost(btn, id) {
    const row = btn.closest('tr');
    const empCode = row.cells[1].textContent.trim();
    const empName = row.cells[2].textContent.trim();
    const netAmt = parseFloat(row.getAttribute('data-net-amt')) || 0;
    const detailRows = row.querySelectorAll('.bpost-row');
    const data = [];
    let totalPostAmt = 0;
    
    detailRows.forEach(tr => {
        const code = tr.querySelector('.acct-code').value.trim();
        const amount = parseFloat(tr.querySelector('.acct-amount').value) || 0;
        if (code !== '' || amount > 0) {
            data.push({ accode: code, amount: amount });
            totalPostAmt += amount;
        }
    });
    
    if (Math.abs(totalPostAmt - netAmt) > 0.01) {
        showStatus('Error for ' + empName + ' (' + empCode + '): Total Post Amount (' + totalPostAmt.toFixed(2) + ') must be equal to Net Amount (' + netAmt.toFixed(2) + '). Please revise it.', false);
        return;
    }
    
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    btn.disabled = true;

    fetch('post_to_bank.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'update_bank_post',
            id: id,
            data: JSON.stringify(data)
        })
    })
    .then(res => res.json())
    .then(res => {
        btn.innerHTML = 'Save';
        btn.disabled = false;
        
        if (res.success) {
            // Update print view dynamically
            const printView = row.querySelector('.d-print-block ul');
            printView.innerHTML = '';
            data.forEach(item => {
                printView.innerHTML += `<li><strong>${item.accode || 'N/A'}</strong>: ${item.amount.toFixed(2)}</li>`;
            });
            showStatus('Record for ' + empName + ' (' + empCode + ') saved successfully.', true);
        } else {
            showStatus('Error saving record for ' + empName + ' (' + empCode + '): ' + (res.error || 'Unknown error'), false);
        }
    })
    .catch(err => {
        btn.innerHTML = 'Save';
        btn.disabled = false;
        showStatus('Network error while saving record for ' + empName + ' (' + empCode + ').', false);
    });
}
</script>
</body>
</html>
