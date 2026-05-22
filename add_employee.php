<?php
require_once 'auth.php';
requireLogin();
require_once 'db.php';

// Allow Admin or HR to access this page
if (($_SESSION['role'] ?? '') !== 'Admin' && ($_SESSION['role'] ?? '') !== 'HR') {
    header("Location: dashboard.php");
    exit();
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'save') {
        $id = $_POST['id'] ?? '';
        $emp_id = trim($_POST['emp_id']);
        $employee_name = trim($_POST['employee_name']);
        $employee_email = trim($_POST['employee_email']);
        $br_code = trim($_POST['br_code']);
        $designation = trim($_POST['designation'] ?? '');

        if (empty($emp_id) || empty($employee_name)) {
            $error = "Employee ID and Name are required.";
        } else {
            if ($id) {
                $stmt = $conn->prepare("UPDATE employees SET EmpID=?, employeeName=?, employeeEmail=?, BrCode=?, designation=? WHERE ID=?");
                $stmt->bind_param("sssssi", $emp_id, $employee_name, $employee_email, $br_code, $designation, $id);
                if ($stmt->execute()) {
                    $message = "Employee updated successfully.";
                } else {
                    $error = "Error updating employee: " . $conn->error;
                }
            } else {
                $stmt = $conn->prepare("INSERT INTO employees (EmpID, employeeName, employeeEmail, BrCode, designation) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $emp_id, $employee_name, $employee_email, $br_code, $designation);
                if ($stmt->execute()) {
                    $message = "Employee added successfully.";
                } else {
                    $error = "Error adding employee: " . $conn->error;
                }
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $id = $_POST['id'];
        $stmt = $conn->prepare("DELETE FROM employees WHERE ID=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $message = "Employee deleted successfully.";
        } else {
            $error = "Error deleting employee: " . $conn->error;
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'import') {
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES['csv_file']['tmp_name'];
            if (($handle = fopen($tmpName, 'r')) !== FALSE) {
                // Skip header row
                $header = fgetcsv($handle, 1000, ",");
                $successCount = 0;
                $errorCount = 0;
                
                $stmt = $conn->prepare("INSERT INTO employees (EmpID, employeeName, employeeEmail, BrCode, designation) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE employeeName=VALUES(employeeName), employeeEmail=VALUES(employeeEmail), BrCode=VALUES(BrCode), designation=VALUES(designation)");
                
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    if (count($data) >= 2) {
                        $emp_id = trim($data[0]);
                        $name = trim($data[1]);
                        $email = count($data) >= 3 ? trim($data[2]) : '';
                        $br_code = count($data) >= 4 ? trim($data[3]) : '';
                        $designation = count($data) >= 5 ? trim($data[4]) : '';
                        
                        if (!empty($emp_id) && !empty($name)) {
                            $stmt->bind_param("sssss", $emp_id, $name, $email, $br_code, $designation);
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
                $message = "Import completed. $successCount added/updated. $errorCount skipped/errors.";
            } else {
                $error = "Failed to open the uploaded file.";
            }
        } else {
            $error = "Please upload a valid CSV file.";
        }
    }
}

// Fetch all employees
$sql = "SELECT e.ID, e.EmpID, e.employeeName, e.employeeEmail, e.BrCode, e.designation, p.PostName FROM employees e LEFT JOIN posts p ON e.designation = p.PostId ORDER BY e.ID DESC LIMIT 500";
$result = $conn->query($sql);

// Fetch all posts for designation dropdown
$postsResult = $conn->query("SELECT PostId, PostName FROM posts ORDER BY PostName ASC");
$posts = [];
if ($postsResult) {
    while ($row = $postsResult->fetch_assoc()) {
        $posts[] = $row;
    }
}
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
        body {
            font-family: 'Outfit', sans-serif;
            background-color: #f1f5f9;
        }
        .table-container {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
        }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold m-0">Manage Employees</h2>
        <div>
            <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#importModal">
                <i class="bi bi-file-earmark-arrow-up me-1"></i> Import CSV
            </button>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#employeeModal" onclick="openNewEmployeeModal()">
                <i class="bi bi-person-plus me-1"></i> Add Employee
            </button>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="table-container">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>Emp ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Designation</th>
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
                        <td><?= htmlspecialchars($row['BrCode'] ?? '') ?></td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-primary me-2" onclick="editEmployee(<?= htmlspecialchars(json_encode($row)) ?>)">
                                <i class="bi bi-pencil"></i> Edit
                            </button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this employee?');">
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
                    <tr><td colspan="5" class="text-center text-muted">No employees found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Employee Modal (Add/Edit) -->
<div class="modal fade" id="employeeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="employeeId">
            
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="employeeModalTitle">Add Employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Employee ID</label>
                        <input type="text" name="emp_id" id="empId" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="employee_name" id="employeeName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="employee_email" id="employeeEmail" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Designation</label>
                        <select name="designation" id="designation" class="form-select">
                            <option value="">Select Designation</option>
                            <?php foreach ($posts as $post): ?>
                                <option value="<?= htmlspecialchars($post['PostId']) ?>"><?= htmlspecialchars($post['PostName']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Branch Code</label>
                        <input type="text" name="br_code" id="brCode" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="import">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Import Employees (CSV)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <strong>CSV Format:</strong><br>
                        Row 1 must be a header (e.g., EmpID, Name, Email, BrCode, Designation).<br>
                        Column 1: EmpID (Required)<br>
                        Column 2: Name (Required)<br>
                        Column 3: Email (Optional)<br>
                        Column 4: Branch Code (Optional)<br>
                        Column 5: Designation (Optional)
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Select CSV File</label>
                        <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                    </div>
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
        document.getElementById('brCode').value = '';
    }

    function editEmployee(emp) {
        document.getElementById('employeeModalTitle').innerText = 'Edit Employee';
        document.getElementById('employeeId').value = emp.ID;
        document.getElementById('empId').value = emp.EmpID;
        document.getElementById('employeeName').value = emp.employeeName;
        document.getElementById('employeeEmail').value = emp.employeeEmail;
        document.getElementById('designation').value = emp.designation || '';
        document.getElementById('brCode').value = emp.BrCode;
        employeeModal.show();
    }
</script>

</body>
</html>
