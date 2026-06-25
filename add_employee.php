<?php
require_once 'auth.php';
requireLogin();
require_once 'db.php';

// Allow Admin or HR only
if (($_SESSION['role'] ?? '') !== 'Admin' && ($_SESSION['role'] ?? '') !== 'HR') {
    header("Location: dashboard.php");
    exit();
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = intval($_POST['id'] ?? 0);
        $emp_id = trim($_POST['emp_id'] ?? '');
        $employee_name = trim($_POST['employee_name'] ?? '');
        $employee_email = trim($_POST['employee_email'] ?? '');
        $br_code = trim($_POST['br_code'] ?? '');
        $designation = trim($_POST['designation'] ?? '');
        $level = trim($_POST['level'] ?? '');

        if (empty($emp_id) || empty($employee_name)) {
            $error = "Employee ID and Name are required.";
        } else {
            // Check for duplicate EmpID
            $checkStmt = $conn->prepare("SELECT ID FROM employees WHERE EmpID = ? AND ID != ?");
            $checkStmt->bind_param("si", $emp_id, $id);
            $checkStmt->execute();
            $checkStmt->store_result();

            if ($checkStmt->num_rows > 0) {
                $error = "Employee ID '$emp_id' already exists. Please use a unique Employee ID.";
            } else {
                if ($id > 0) {
                    // Update
                    $stmt = $conn->prepare("UPDATE employees SET EmpID=?, employeeName=?, employeeEmail=?, BrCode=?, designation=?, level=? WHERE ID=?");
                    $stmt->bind_param("ssssssi", $emp_id, $employee_name, $employee_email, $br_code, $designation, $level, $id);
                    if ($stmt->execute()) {
                        $message = "Employee updated successfully.";
                    } else {
                        $error = "Error updating employee: " . $conn->error;
                    }
                } else {
                    // Insert
                    $stmt = $conn->prepare("INSERT INTO employees (EmpID, employeeName, employeeEmail, BrCode, designation, level) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssssss", $emp_id, $employee_name, $employee_email, $br_code, $designation, $level);
                    if ($stmt->execute()) {
                        $message = "Employee added successfully.";
                    } else {
                        $error = "Error adding employee: " . $conn->error;
                    }
                }
            }
            $checkStmt->close();
        }
    } 
    elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM employees WHERE ID=?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $message = "Employee deleted successfully.";
            } else {
                $error = "Error deleting employee: " . $conn->error;
            }
        }
    } 
    elseif ($action === 'import') {
        // Import logic (with level support)
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES['csv_file']['tmp_name'];
            if (($handle = fopen($tmpName, 'r')) !== FALSE) {
                $header = fgetcsv($handle, 1000, ",");
                $successCount = 0;
                $errorCount = 0;

                $stmt = $conn->prepare("INSERT INTO employees (EmpID, employeeName, employeeEmail, BrCode, designation, level)
                                      VALUES (?, ?, ?, ?, ?, ?)
                                      ON DUPLICATE KEY UPDATE
                                      employeeName=VALUES(employeeName),
                                      employeeEmail=VALUES(employeeEmail),
                                      BrCode=VALUES(BrCode),
                                      designation=VALUES(designation),
                                      level=VALUES(level)");

                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    if (count($data) >= 2) {
                        $emp_id = trim($data[0] ?? '');
                        $name = trim($data[1] ?? '');
                        $email = trim($data[2] ?? '');
                        $br_code = trim($data[3] ?? '');
                        $designation = trim($data[4] ?? '');
                        $level = trim($data[5] ?? '');

                        if (!empty($emp_id) && !empty($name)) {
                            $stmt->bind_param("ssssss", $emp_id, $name, $email, $br_code, $designation, $level);
                            if ($stmt->execute()) {
                                $successCount++;
                            } else {
                                $errorCount++;
                            }
                        } else {
                            $errorCount++;
                        }
                    }
                }
                fclose($handle);
                $message = "Import completed. $successCount records added/updated. $errorCount skipped/errors.";
            } else {
                $error = "Failed to open the uploaded file.";
            }
        } else {
            $error = "Please upload a valid CSV file.";
        }
    }
}

// ==================== EXPORT TO CSV ====================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $exportSearch = trim($_GET['search'] ?? '');
    $exportWhere  = "WHERE 1=1";
    $exportParams = [];
    $exportTypes  = '';

    if (!empty($exportSearch)) {
        $exportWhere .= " AND (e.employeeName LIKE ? OR e.EmpID LIKE ?)";
        $exportLike    = "%$exportSearch%";
        $exportParams[] = $exportLike;
        $exportParams[] = $exportLike;
        $exportTypes   .= 'ss';
    }

    $exportSql  = "SELECT e.EmpID, e.employeeName, e.employeeEmail, e.BrCode,
                          e.designation, e.level, p.PostName
                   FROM employees e
                   LEFT JOIN posts p ON e.designation = p.PostId
                   $exportWhere
                   ORDER BY e.employeeName ASC";
    $exportStmt = $conn->prepare($exportSql);
    if (!empty($exportParams)) $exportStmt->bind_param($exportTypes, ...$exportParams);
    $exportStmt->execute();
    $exportResult = $exportStmt->get_result();

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="employees_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['EmpID', 'Name', 'Email', 'BrCode', 'Designation', 'Level']);
    while ($row = $exportResult->fetch_assoc()) {
        fputcsv($out, [
            $row['EmpID'],
            $row['employeeName'],
            $row['employeeEmail'],
            $row['BrCode'],
            $row['PostName'] ?? $row['designation'],
            $row['level'],
        ]);
    }
    fclose($out);
    exit;
}

// Fetch employees with level
$sql = "SELECT e.ID, e.EmpID, e.employeeName, e.employeeEmail, e.BrCode, 
               e.designation, e.level, p.PostName 
        FROM employees e 
        LEFT JOIN posts p ON e.designation = p.PostId 
        ORDER BY e.ID DESC LIMIT 500";
$result = $conn->query($sql);



// ==================== SEARCH & PAGINATION ====================
$search      = trim($_GET['search'] ?? '');
$perPage     = 20;
$currentPage = max(1, intval($_GET['page'] ?? 1));
$offset      = ($currentPage - 1) * $perPage;

$whereClause = "WHERE 1=1";
$params      = [];
$types       = '';

if (!empty($search)) {
    $whereClause .= " AND (e.employeeName LIKE ? OR e.EmpID LIKE ?)";
    $like         = "%$search%";
    $params[]     = $like;
    $params[]     = $like;
    $types       .= 'ss';
}

// Total count
$countSql  = "SELECT COUNT(*) FROM employees e $whereClause";
$countStmt = $conn->prepare($countSql);
if (!empty($params)) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$countStmt->bind_result($totalRecords);
$countStmt->fetch();
$countStmt->close();
$totalPages = ceil($totalRecords / $perPage);

// Fetch page
$dataSql  = "SELECT e.ID, e.EmpID, e.employeeName, e.employeeEmail, e.BrCode,
                    e.designation, e.level, p.PostName
             FROM employees e
             LEFT JOIN posts p ON e.designation = p.PostId
             $whereClause
             ORDER BY e.employeeName ASC
             LIMIT ? OFFSET ?";
$dataStmt = $conn->prepare($dataSql);
$types2   = $types . 'ii';
$params2  = array_merge($params, [$perPage, $offset]);
$dataStmt->bind_param($types2, ...$params2);
$dataStmt->execute();
$result = $dataStmt->get_result();


// Fetch posts for Designation dropdown
$postsResult = $conn->query("SELECT PostId, PostName FROM posts ORDER BY PostName ASC");
$posts = $postsResult ? $postsResult->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Employees | HR Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #f1f5f9; }
        .page-header {
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
            color: white;
            padding: 2rem 1.5rem;
            border-radius: 15px;
            margin-bottom: 1.5rem;
        }
        .page-header h2 { font-weight: 700; font-size: 1.8rem; margin: 0; }
        .page-header p  { margin: 0; opacity: 0.85; font-size: 0.95rem; }
        .table-container {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
        }
		.search-bar .form-control { border-radius: 8px 0 0 8px; }
        .search-bar .btn-search   { border-radius: 0 8px 8px 0; }
        .badge-level {
            font-size: 0.75rem;
            padding: 4px 10px;
            border-radius: 6px;
            font-weight: 600;
            display: inline-block;
        }
        .pagination .page-link { border-radius: 6px !important; margin: 0 2px; }
        .total-badge {
            background: #dbeafe;
            color: #1e40af;
            border-radius: 8px;
            padding: 4px 12px;
            font-size: 0.875rem;
            font-weight: 600;
        }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container-fluid px-4 py-4">

    <!-- Page Header -->
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h2><i class="bi bi-people-fill me-2"></i>Employee Records</h2>
            <p>Manage all employee information, levels, and designations</p>
        </div>
            <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>"
               class="btn btn-outline-light me-2">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export CSV
            </a>
            <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#importModal">
                <i class="bi bi-file-earmark-arrow-up me-1"></i> Import CSV
            </button>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#employeeModal" onclick="openNewEmployeeModal()">
                <i class="bi bi-person-plus me-1"></i> Add Employee
            </button>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($message) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="table-container">
	
	<!-- Search Bar + Count -->
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <form method="GET" class="d-flex search-bar align-items-center" style="max-width:450px; width:100%;">
                <input type="text" name="search" class="form-control"
                       placeholder="Search by Name or Employee Code..."
                       value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn btn-primary btn-search px-3">
                    <i class="bi bi-search"></i>
                </button>
                <?php if (!empty($search)): ?>
                    <a href="?" class="btn btn-outline-secondary ms-2" style="border-radius:8px;">
                        <i class="bi bi-x-lg"></i>
                    </a>
                <?php endif; ?>
            </form>
            <span class="total-badge">
                <?= number_format($totalRecords) ?> Employee<?= $totalRecords != 1 ? 's' : '' ?>
                <?= !empty($search) ? ' for "<strong>' . htmlspecialchars($search) . '</strong>"' : '' ?>
            </span>
        </div>
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>Emp ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Designation</th>
                    <th>Level</th>
                    <th>Branch Code</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['EmpID']) ?></td>
                        <td><?= htmlspecialchars($row['employeeName']) ?></td>
                        <td><?= htmlspecialchars($row['employeeEmail']) ?></td>
                        <td><?= htmlspecialchars($row['PostName'] ?? $row['designation']) ?></td>
                        <td><?= htmlspecialchars($row['level'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['BrCode'] ?? '') ?></td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-primary me-2" 
                                    onclick="editEmployee(<?= htmlspecialchars(json_encode($row)) ?>)">
                                <i class="bi bi-pencil"></i> Edit
                            </button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this employee?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $row['ID'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
                <?php if ($result->num_rows === 0): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No employees found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="employeeModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" id="employeeForm">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="employeeId">
            
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="employeeModalTitle">Add Employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Employee ID <span class="text-danger">*</span></label>
                        <input type="text" name="emp_id" id="empId" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" name="employee_name" id="employeeName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="employee_email" id="employeeEmail" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Designation</label>
                        <select name="designation" id="designation" class="form-select">
                            <option value="">-- Select Designation --</option>
                            <?php foreach ($posts as $post): ?>
                                <option value="<?= htmlspecialchars($post['PostId']) ?>">
                                    <?= htmlspecialchars($post['PostName']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Level</label>
                        <select name="level" id="level" class="form-select">
                            <option value="">-- Select Level --</option>
                            <option value="ST">Staff</option>
                            <option value="BM">Branch Manager</option>
							<option value="OI">Operation Incharge</option>
                            <option value="DH">Department Head</option>
							<option value="CD">Claim Head</option>
                            <option value="HR">Human Resource</option>
                            <option value="PH">Province Head</option>
                            <option value="NSM">National Sales Head</option>
                            <option value="DCEO">Deputy CEO</option>
							<option value="CEO">CEO</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Branch Code</label>
                        <input type="text" name="br_code" id="brCode" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Employee</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="import">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Import Employees from CSV</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <strong>CSV Format:</strong><br>
                        EmpID, Name, Email, BrCode, Designation, Level
                    </div>
                    <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Import</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const employeeModal = new bootstrap.Modal(document.getElementById('employeeModal'));

    function openNewEmployeeModal() {
        document.getElementById('employeeModalTitle').innerText = 'Add Employee';
        document.getElementById('employeeId').value = '';
        document.getElementById('empId').value = '';
        document.getElementById('employeeName').value = '';
        document.getElementById('employeeEmail').value = '';
        document.getElementById('designation').value = '';
        document.getElementById('level').value = '';
        document.getElementById('brCode').value = '';
        employeeModal.show();
    }

    function editEmployee(emp) {
        document.getElementById('employeeModalTitle').innerText = 'Edit Employee';
        
        document.getElementById('employeeId').value = emp.ID || '';
        document.getElementById('empId').value = emp.EmpID || '';
        document.getElementById('employeeName').value = emp.employeeName || '';
        document.getElementById('employeeEmail').value = emp.employeeEmail || '';
        document.getElementById('designation').value = emp.designation || '';
        document.getElementById('level').value = emp.level || '';
        document.getElementById('brCode').value = emp.BrCode || '';

        employeeModal.show();
    }
</script>

</body>
</html>