<?php
require_once 'auth.php';
requireLogin();
require_once 'db.php';

if (!in_array($_SESSION['role'] ?? '', ['HR', 'Admin'])) {
    header("Location: dashboard.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: view_travel_orders.php");
    exit();
}

$id = intval($_GET['id']);
$successMessage = $errorMessage = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $empID = trim(filter_input(INPUT_POST, 'empID', FILTER_SANITIZE_STRING));
    $employeeName = trim(filter_input(INPUT_POST, 'employeeName', FILTER_SANITIZE_STRING));
    $employeeEmail = trim(filter_input(INPUT_POST, 'employeeEmail', FILTER_SANITIZE_EMAIL));
    $designation = trim(filter_input(INPUT_POST, 'designation', FILTER_SANITIZE_STRING));
    $department = trim(filter_input(INPUT_POST, 'department', FILTER_SANITIZE_STRING));
    $travelFrom = trim(filter_input(INPUT_POST, 'travelFrom', FILTER_SANITIZE_STRING));
    $travelDateFrom = $_POST['travelDateFrom'] ?? '';
    $travelDateTo = $_POST['travelDateTo'] ?? '';
    $destination = trim(filter_input(INPUT_POST, 'destination', FILTER_SANITIZE_STRING));
    $purpose = trim(filter_input(INPUT_POST, 'purpose', FILTER_SANITIZE_STRING));
    $modeOfTransport = trim(filter_input(INPUT_POST, 'modeOfTransport', FILTER_SANITIZE_STRING));
    $noOfDays = filter_input(INPUT_POST, 'noOfDays', FILTER_SANITIZE_NUMBER_INT);
    $estimatedCost = filter_input(INPUT_POST, 'estimatedCost', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    
    if (
        empty($empID) || empty($employeeName) || empty($employeeEmail) ||
        empty($designation) || empty($department) || empty($travelFrom) ||
        empty($travelDateFrom) || empty($travelDateTo) || empty($destination) ||
        empty($purpose) || empty($modeOfTransport) || empty($noOfDays)
    ) {
        $errorMessage = "All required fields must be filled.";
    } elseif (strtotime($travelDateTo) < strtotime($travelDateFrom)) {
        $errorMessage = "Travel end date must be after start date.";
    } else {
        $stmt = $conn->prepare("UPDATE travel_orders SET 
            EmpID=?, employeeName=?, employeeEmail=?, designation=?, department=?,
            travelFrom=?, travelDateFrom=?, travelDateTo=?, noOfDays=?, destination=?, purpose=?,
            modeOfTransport=?, estimatedCost=?, last_updated_by=?, last_updated_at=NOW() 
            WHERE id=?");

        if ($stmt) {
            $currentUser = getCurrentUser();
            $stmt->bind_param(
                "ssssssssisssssi",
                $empID, $employeeName, $employeeEmail, $designation,
                $department, $travelFrom, $travelDateFrom, $travelDateTo, $noOfDays,
                $destination, $purpose, $modeOfTransport, $estimatedCost,
                $currentUser, $id
            );

            if ($stmt->execute()) {
                $successMessage = "Travel order updated successfully.";
            } else {
                $errorMessage = "Database error: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $errorMessage = "Statement preparation failed: " . $conn->error;
        }
    }
}

// Fetch existing data
$stmt = $conn->prepare("SELECT * FROM travel_orders WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) {
    die("Record not found.");
}

if ($data['approval_ceo_status'] === 'Approved') {
    die("<div class='container mt-5'><div class='alert alert-danger text-center'>This travel order has been fully approved by the CEO and can no longer be edited.</div><div class='text-center'><a href='view_travel_orders.php' class='btn btn-primary'>Go Back</a></div></div>");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Travel Order | HR Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #f1f5f9; color: #1e293b; }
        .form-container { background: white; border-radius: 20px; padding: 2.5rem; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); margin-top: 2rem; margin-bottom: 3rem; }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-9">
            <div class="form-container">
                <h2 class="mb-4">
                    <i class="bi bi-pencil-square me-2 text-warning"></i>Edit Travel Request (ID: <?= $id ?>)
                </h2>

                <?php if (!empty($successMessage)): ?>
                    <div class="alert alert-success d-flex align-items-center rounded-3 mb-4"><i class="bi bi-check-circle-fill fs-4 me-3"></i><div><?= $successMessage; ?></div></div>
                <?php elseif (!empty($errorMessage)): ?>
                    <div class="alert alert-danger d-flex align-items-center rounded-3 mb-4"><i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i><div><?= $errorMessage; ?></div></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="row g-4 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Emp ID</label>
                            <input type="text" name="empID" class="form-control" value="<?= htmlspecialchars($data['EmpID']) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Employee Name</label>
                            <input type="text" name="employeeName" class="form-control" value="<?= htmlspecialchars($data['employeeName']) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Email</label>
                            <input type="email" name="employeeEmail" class="form-control" value="<?= htmlspecialchars($data['employeeEmail']) ?>" required>
                        </div>
                    </div>

                    <div class="row g-4 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Designation</label>
                            <input type="text" name="designation" class="form-control" value="<?= htmlspecialchars($data['designation']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Branch / Department</label>
                            <input type="text" name="department" class="form-control" value="<?= htmlspecialchars($data['department']) ?>" required>
                        </div>
                    </div>

                    <div class="row g-4 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Travel From</label>
                            <input type="text" name="travelFrom" class="form-control" value="<?= htmlspecialchars($data['travelFrom']) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Destination</label>
                            <input type="text" name="destination" class="form-control" value="<?= htmlspecialchars($data['destination']) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Transport Mode</label>
                            <select name="modeOfTransport" class="form-select" required>
                                <option value="By Air" <?= $data['modeOfTransport'] == 'By Air' ? 'selected' : '' ?>>By Air</option>
                                <option value="Private Vehicle" <?= $data['modeOfTransport'] == 'Private Vehicle' ? 'selected' : '' ?>>Private Vehicle</option>
                                <option value="By Public" <?= $data['modeOfTransport'] == 'By Public' ? 'selected' : '' ?>>By Public</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-4 mb-3">
                        <div class="col-md-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="travelDateFrom" id="travelDateFrom" class="form-control" value="<?= htmlspecialchars($data['travelDateFrom']) ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">End Date</label>
                            <input type="date" name="travelDateTo" id="travelDateTo" class="form-control" value="<?= htmlspecialchars($data['travelDateTo']) ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">No of Days</label>
                            <input type="number" name="noOfDays" id="noOfDays" class="form-control" value="<?= htmlspecialchars($data['noOfDays'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Estimated Cost</label>
                            <input type="number" name="estimatedCost" step="0.01" class="form-control" value="<?= htmlspecialchars($data['estimatedCost']) ?>" required>
                        </div>
                    </div>

                    <div class="row g-4 mb-4">
                        <div class="col-12">
                            <label class="form-label">Purpose of Travel</label>
                            <textarea name="purpose" class="form-control" rows="3" required><?= htmlspecialchars($data['purpose']) ?></textarea>
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <a href="view_travel_orders.php" class="btn btn-secondary me-2">Cancel</a>
                        <button type="submit" class="btn btn-warning"><i class="bi bi-save me-2"></i> Update Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    $(document).ready(function() {
        function calculateDays() {
            const start = $('#travelDateFrom').val();
            const end = $('#travelDateTo').val();
            if (start && end) {
                const startDate = new Date(start);
                const endDate = new Date(end);
                if (endDate >= startDate) {
                    const diffTime = Math.abs(endDate - startDate);
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1; // Inclusive of start and end
                    $('#noOfDays').val(diffDays);
                } else {
                    $('#noOfDays').val('');
                }
            }
        }

        $('#travelDateFrom, #travelDateTo').on('change', calculateDays);
        if (!$('#noOfDays').val()) calculateDays();
    });
</script>
</body>
</html>
