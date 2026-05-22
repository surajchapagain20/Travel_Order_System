<?php
require_once 'auth.php';
requireLogin();
require_once 'db.php';

// Fetch all travel orders with approval statuses
$sql = "SELECT t.*, e.level FROM travel_orders t LEFT JOIN employees e ON t.EmpID = e.EmpID ORDER BY t.id DESC";
$result = $conn->query($sql);
if (!$result) {
    die("Query failed: " . $conn->error);
}

$search = "";
$where = "WHERE 1=1";
if (!empty($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
    $where .= " AND (t.employeeName LIKE '%$search%' OR t.department LIKE '%$search%' OR t.EmpID LIKE '%$search%')";
}

if (!empty($_GET['filter'])) {
    $filter = $_GET['filter'];
    if ($filter === 'approved') {
        $where .= " AND t.approval_ceo_status = 'Approved'";
    } elseif ($filter === 'pending') {
        $where .= " AND t.approval_ceo_status != 'Approved'";
    }
}

$sql = "SELECT t.*, e.level FROM travel_orders t LEFT JOIN employees e ON t.EmpID = e.EmpID $where ORDER BY t.id DESC";
$result = $conn->query($sql);
if (!$result) {
    die("Query failed: " . $conn->error);
}

// Fetch logged-in user's email from employees table
$approver_email_val = $_SESSION['username'] ?? '';
if (isset($_SESSION['full_name'])) {
    $stmt_emp = $conn->prepare("SELECT employeeEmail FROM employees WHERE employeeName = ? LIMIT 1");
    $stmt_emp->bind_param("s", $_SESSION['full_name']);
    $stmt_emp->execute();
    $res_emp = $stmt_emp->get_result();
    if ($emp_row = $res_emp->fetch_assoc()) {
        if (!empty($emp_row['employeeEmail'])) {
            $approver_email_val = $emp_row['employeeEmail'];
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Travel Orders</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>


<?php include 'navbar.php'; ?>
<div class="container-fluid px-4">
    <div class="table-responsive">
        <div class="col-md-12">

<div class="mb-3">
    <form method="GET" class="d-flex">
        <input type="text" name="search" class="form-control me-2" placeholder="Search by Employee, Branch or EmpID" value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn btn-primary">Search</button>
        <?php if (!empty($search)): ?>
            <a href="?" class="btn btn-secondary ms-2">Clear</a>
        <?php endif; ?>
    </form>
</div>


    <?php if ($result && $result->num_rows > 0): ?>
        <table class="table table-bordered table-hover table-striped w-100">
            <thead class="table-primary text-center">
                <tr>
                    <th>#</th>
                    <th>TADA NO</th>
					<th>EmpID</th>
                    <th>Employee Name</th>
                    <th>Branch Name</th>
                    <th>From_To</th>
                    <th>No of Days</th>
                    <th>Destination</th>
                    <th>Purpose</th>
                    <th>Transport</th>
                    <th>Document</th>
                    <th>Province Approval</th>
                    <th>NSM Approval</th>
                    <th>HR Approval</th>
                    <th>CEO Approval</th>
                    <th>Last Updated</th>
                    <th>Print</th>
                    <th>Expense</th>
                </tr>
            </thead>
            <tbody>
                <?php $sn = 1; while ($row = $result->fetch_assoc()): ?>
                    <tr data-id="<?= $row['id'] ?>" ondblclick="openExpenseModal(this.dataset.id)">
                        <td class="text-center"><?= $sn++ ?></td>
						<td><?= htmlspecialchars($row['travel_order_no']) ?></td>
						<td><?= htmlspecialchars($row['EmpID']) ?></td>
                        <td><?= htmlspecialchars($row['employeeName']) ?></td>
                        <td><?= htmlspecialchars($row['department']) ?></td>
                        <td style="width: auto; white-space: nowrap;">
							<?= htmlspecialchars($row['travelDateFrom']) ?> to <?= htmlspecialchars($row['travelDateTo']) ?>
						</td>
                        <td><?= htmlspecialchars($row['noOfDays']) ?></td>
                        
                        <td><?= htmlspecialchars($row['destination']) ?></td>
                        <td><?= htmlspecialchars($row['purpose']) ?></td>
                        <td><?= htmlspecialchars($row['modeOfTransport']) ?></td>
                        

                        <!-- Document -->
                        <td class="text-center">
							<?php if (!empty($row['document_path'])): ?>
								<button type="button"
										class="btn btn-sm btn-primary view-doc-btn"
										data-doc="<?= htmlspecialchars($row['document_path']) ?>"
										data-bs-toggle="modal"
										data-bs-target="#documentModal">
									View
								</button>
							<?php else: ?>
								<span class="text-muted">No file</span>
							<?php endif; ?>
						</td>

                        <!-- Province Approval -->
                        <td class="text-center">
                            <?php if ($row['approval_province_status'] === 'Approved'): ?>
                                <?php if (in_array($row['level'], ['PH', 'NSM', 'HR', 'CEO'])): ?>
                                    <?= htmlspecialchars($row['province_approver_name']) ?>
                                <?php else: ?>
                                    <strong>Recommended and Forwarded to NSM</strong><br>
                                    <small class="text-muted">
                                        <?= htmlspecialchars($row['province_approver_name']) ?><br>
                                        <?= htmlspecialchars($row['province_approver_email']) ?><br>
                                        <em><?= htmlspecialchars($row['approval_province_remarks']) ?></em>
                                    </small>
                                <?php endif; ?>
                            <?php else: ?>
                                <?= htmlspecialchars($row['approval_province_status']) ?><br>
                                <?php if (in_array($_SESSION['role'] ?? '', ['HR', 'Admin'])): ?>
                                    <button class="btn btn-sm btn-success"
                                            data-bs-toggle="modal"
                                            data-bs-target="#provinceModal<?= $row['id'] ?>">
                                        Recommended
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>

                        <!-- NSM Approval -->
                        <td class="text-center">
                            <?php if ($row['approval_nsm_status'] === 'Approved'): ?>
                                <?php if (in_array($row['level'], ['PH', 'NSM', 'HR', 'CEO'])): ?>
                                    <?= htmlspecialchars($row['nsm_approver_name']) ?>
                                <?php else: ?>
                                    <strong>Recommended and Forwarded to HR</strong><br>
                                    <small class="text-muted">
                                        <?= htmlspecialchars($row['nsm_approver_name']) ?><br>
                                        <?= htmlspecialchars($row['nsm_approver_email']) ?><br>
                                        <em><?= htmlspecialchars($row['approval_nsm_remarks']) ?></em>
                                    </small>
                                <?php endif; ?>
                            <?php else: ?>
                                <?= htmlspecialchars($row['approval_nsm_status']) ?><br>
                                <?php if (in_array($_SESSION['role'] ?? '', ['HR', 'Admin']) && $row['approval_province_status'] === 'Approved'): ?>
                                    <button class="btn btn-sm btn-success"
                                            data-bs-toggle="modal"
                                            data-bs-target="#nsmModal<?= $row['id'] ?>">
                                        Recommended
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
						
						<!-- HR Approval -->
                       <!-- HR Approval -->
<td class="text-center">
    <?php if ($row['approval_hr_status'] === 'Approved'): ?>
        <?php if (in_array($row['level'], ['PH', 'NSM', 'HR', 'CEO'])): ?>
            <?= htmlspecialchars($row['hr_approver_name']) ?>
        <?php else: ?>
            <strong>Recommended and Forwarded to CEO</strong><br>
            <small class="text-muted">
                <?= htmlspecialchars($row['hr_approver_name']) ?><br>
                <?= htmlspecialchars($row['hr_approver_email']) ?><br>
                <em><?= htmlspecialchars($row['approval_hr_remarks']) ?></em>
            </small>
        <?php endif; ?>
    <?php else: ?>
        <?= htmlspecialchars($row['approval_hr_status']) ?><br>
        <?php if (in_array($_SESSION['role'] ?? '', ['HR', 'Admin']) && $row['approval_nsm_status'] === 'Approved'): ?>
            <button class="btn btn-sm btn-success"
                    data-bs-toggle="modal"
                    data-bs-target="#hrModal<?= $row['id'] ?>">
                Approved
            </button>
        <?php endif; ?>
    <?php endif; ?>
</td>


                       <!-- CEO Approval -->
<!-- CEO Approval -->
<td class="text-center">
    <?php if ($row['approval_ceo_status'] === 'Approved'): ?>
        <?php if (in_array($row['level'], ['PH', 'NSM', 'HR', 'CEO'])): ?>
            <?= htmlspecialchars($row['ceo_approver_name']) ?>
        <?php else: ?>
            <strong>Approved</strong><br>
            <small class="text-muted">
                <?= htmlspecialchars($row['ceo_approver_name']) ?><br>
                <?= htmlspecialchars($row['ceo_approver_email']) ?><br>
                <em><?= htmlspecialchars($row['approval_ceo_remarks']) ?></em>
            </small>
        <?php endif; ?>
    <?php else: ?>
        <?= htmlspecialchars($row['approval_ceo_status']) ?><br>
        <?php if (in_array($_SESSION['role'] ?? '', ['HR', 'Admin']) && $row['approval_hr_status'] === 'Approved'): ?>
            <button class="btn btn-sm btn-success"
                    data-bs-toggle="modal"
                    data-bs-target="#ceoModal<?= $row['id'] ?>">
                Approve
            </button>
        <?php endif; ?>
    <?php endif; ?>
</td>
<!-- Last Updated -->
<td class="text-center">
    <small class="text-muted">
        By: <?= htmlspecialchars($row['last_updated_by'] ?? 'N/A') ?><br>
        At: <?= !empty($row['last_updated_at']) ? date('Y-m-d H:i', strtotime($row['last_updated_at'])) : 'N/A' ?>
    </small>
</td>

<td>
    <!-- <a href="print_travel_order.php?id=<?= $row['id']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
        Print
    </a> -->
	<button type="button" class="btn btn-primary btn-sm mb-1 w-100" data-bs-toggle="modal" data-bs-target="#printModal" data-id="<?= $row['id']; ?>">
    Print
</button>
<?php if (in_array($_SESSION['role'] ?? '', ['HR', 'Admin']) && $row['approval_ceo_status'] !== 'Approved'): ?>
    <a href="edit_travel_order.php?id=<?= $row['id'] ?>" class="btn btn-warning btn-sm w-100 mt-1">Edit</a>
<?php endif; ?>
<!-- PRINT MODAL -->
<div class="modal fade" id="printModal" tabindex="-1" aria-labelledby="printModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Print Travel Order</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="printContent">
        <!-- Travel Order content will be loaded here -->
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-success" onclick="printModalContent()">Print</button>
      </div>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var printModal = document.getElementById('printModal');
    printModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        var id = button.getAttribute('data-id');

        // Load travel order details via AJAX
        fetch('print_travel_order.php?id=' + id)
            .then(response => response.text())
            .then(data => {
                document.getElementById('printContent').innerHTML = data;
            });
    });
});

function printModalContent() {
    var printWindow = window.open('', '', 'width=900,height=600');
    printWindow.document.write('<html><head><title>Print</title>');
    printWindow.document.write('<style>body { font-family: Arial; padding: 20px; }</style>');
    printWindow.document.write('</head><body >');
    printWindow.document.write(document.getElementById('printContent').innerHTML);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.focus();
    printWindow.print();
    printWindow.close();
}
</script>

</td>
                            <!-- Expense button -->
                            <td class="text-center">
                                <a href="travel_expense.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-success">Expense</a>
                            </td>
                    </tr>

                    <!-- Province Approval Modal -->
                    <div class="modal fade" id="provinceModal<?= $row['id'] ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <form action="approve_province.php" method="POST">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Province Approval for <?= htmlspecialchars($row['employeeName']) ?></h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="hidden" name="travel_order_id" value="<?= $row['id'] ?>">
                                        <div class="mb-3">
                                            <label class="form-label">Your Name</label>
                                            <input type="text" name="approver_name" class="form-control" value="<?= htmlspecialchars($row['selected_ph_name'] ?: ($_SESSION['full_name'] ?? '')) ?>" readonly required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Your Email</label>
                                            <input type="email" name="approver_email" class="form-control" value="<?= htmlspecialchars($row['selected_ph_email'] ?: $approver_email_val) ?>" readonly required>
                                        </div>
										<div class="mb-3">
                                            <label class="form-label">Remarks</label>
                                            <textarea name="remarks" class="form-control" required></textarea>
                                        </div>
									</div>
                                    <div class="modal-footer">
                                        <button type="submit" class="btn btn-primary">Approve and Forward</button>
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- NSM Approval Modal -->
                    <div class="modal fade" id="nsmModal<?= $row['id'] ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <form action="approve_province.php" method="POST">
                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">NSM Approval for <?= htmlspecialchars($row['employeeName']) ?></h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label">Approver Name</label>
                                            <input type="text" name="nsm_approver_name" class="form-control" value="Umapati Pokharel" readonly required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Approver Email</label>
                                            <input type="email" name="nsm_approver_email" class="form-control" value="Umapati.pokharel@nepallife.com.np" readonly required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Remarks</label>
                                            <textarea name="approval_nsm_remarks" class="form-control" rows="3"></textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="submit" class="btn btn-primary">Submit Approval</button>
                                        <button type="button" class="btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
					
                    <!-- HR Approval Modal -->
<div class="modal fade" id="hrModal<?= $row['id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="approve_province.php" method="POST">
            <input type="hidden" name="id" value="<?= $row['id'] ?>">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">HR Approval for <?= htmlspecialchars($row['employeeName']) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Approver Name</label>
                        <input type="text" name="hr_approver_name" class="form-control" value="<?= htmlspecialchars($_SESSION['full_name'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Approver Email</label>
                        <input type="email" name="hr_approver_email" class="form-control" value="<?= htmlspecialchars($approver_email_val) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Remarks</label>
                        <textarea name="approval_hr_remarks" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Submit Approval</button>
                    <button type="button" class="btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </form>
    </div>
</div>

                    <!-- CEO Approval Modal -->
<!-- CEO Approval Modal -->
<div class="modal fade" id="ceoModal<?= $row['id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="approve_province.php" method="POST">
            <input type="hidden" name="id" value="<?= $row['id'] ?>">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">CEO Approval for <?= htmlspecialchars($row['employeeName']) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Approver Name</label>
                        <input type="text" name="ceo_approver_name" class="form-control" value="Pravin Raman Parajuli" readonly required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Approver Email</label>
                        <input type="email" name="ceo_approver_email" class="form-control" value="Pravin.parajuli@nepallife.com.np" readonly required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Remarks</label>
                        <textarea name="approval_ceo_remarks" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Submit Approval</button>
                    <button type="button" class="btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </form>
    </div>
</div>

					
					

                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="alert alert-warning text-center">No travel orders found.</div>
    <?php endif; ?>
</div>

<!-- Document Modal -->
<div class="modal fade" id="documentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Travel Document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <iframe id="docViewer" src="" frameborder="0" width="100%" height="600px"></iframe>
            </div>
        </div>
    </div>
</div>

<!-- Expense Modal -->
<div class="modal fade" id="expenseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Travel Expense Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="expenseModalContent">
                <!-- Loaded via AJAX -->
                <div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div></div>
            </div>
        </div>
    </div>
</div>

<script>
    function openExpenseModal(id) {
        const modalBody = document.getElementById('expenseModalContent');
        modalBody.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div></div>';
        fetch(`travel_expense.php?id=${id}`)
            .then(res => res.text())
            .then(html => {
                modalBody.innerHTML = html;
            })
            .catch(err => {
                modalBody.innerHTML = '<div class="alert alert-danger">Failed to load expense form.</div>';
            });
        const modal = new bootstrap.Modal(document.getElementById('expenseModal'));
        modal.show();
    }
</script>

<!-- JS Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.querySelectorAll('.view-doc-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const docUrl = this.getAttribute('data-doc');
            document.getElementById('docViewer').src = docUrl;
        });
    });
</script>

<div class="modal fade" id="documentModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Document View</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body text-center" style="height:80vh;">
                <iframe id="docFrame"
                        src=""
                        style="width:100%; height:100%; border:none;">
                </iframe>
            </div>
        </div>
    </div>
</div>
</body>
</html>
