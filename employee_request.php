<?php
require_once 'auth.php';
require_once 'db.php';

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
    $estimatedCost = filter_input(INPUT_POST, 'estimatedCost', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $approvingAuthority = 'Province';

    if (
        empty($empID) || empty($employeeName) || empty($employeeEmail) ||
        empty($designation) || empty($department) || empty($travelFrom) ||
        empty($travelDateFrom) || empty($travelDateTo) || empty($destination) ||
        empty($purpose) || empty($modeOfTransport)
    ) {
        $errorMessage = "All required fields must be filled.";
    } elseif (strtotime($travelDateTo) < strtotime($travelDateFrom)) {
        $errorMessage = "Travel end date must be after start date.";
    } else {
        // File upload
        $document = '';
        if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
            $targetDir = "uploads/";
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
            $fileExtension = strtolower(pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION));
            $safeFilename = uniqid("doc_", true) . '.' . $fileExtension;
            $document = $targetDir . $safeFilename;
            $allowedTypes = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
            $maxFileSize = 5 * 1024 * 1024;

            if (!in_array($fileExtension, $allowedTypes)) {
                $errorMessage = "Invalid file type. Allowed types: " . implode(', ', $allowedTypes);
            } elseif ($_FILES['document']['size'] > $maxFileSize) {
                $errorMessage = "File too large. Maximum allowed size is 5MB.";
            } elseif (!move_uploaded_file($_FILES['document']['tmp_name'], $document)) {
                $errorMessage = "Failed to upload document.";
            }
        }

        if (empty($errorMessage)) {
            $stmt = $conn->prepare("INSERT INTO travel_orders (
                EmpID, employeeName, employeeEmail, designation, department,
                travelFrom, travelDateFrom, travelDateTo, destination, purpose,
                modeOfTransport, estimatedCost, approvingAuthority, document,
                approval_province_status, approval_nsm_status, approval_hr_status, approval_ceo_status,
                last_updated_by, last_updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', 'Pending', 'Pending', 'Pending', ?, NOW())");

            if ($stmt) {
                // If not logged in, use 'Employee' as the user
                $currentUser = isset($_SESSION['username']) ? getCurrentUser() : 'Employee';
                $stmt->bind_param(
                    "sssssssssssssss",
                    $empID, $employeeName, $employeeEmail, $designation,
                    $department, $travelFrom, $travelDateFrom, $travelDateTo,
                    $destination, $purpose, $modeOfTransport, $estimatedCost,
                    $approvingAuthority, $document, $currentUser
                );

                if ($stmt->execute()) {
                    $newOrderId = $conn->insert_id;
                    $successMessage = "Travel order submitted successfully.";
                } else {
                    $errorMessage = "Database error: " . $stmt->error;
                    if (!empty($document) && file_exists($document)) {
                        unlink($document);
                    }
                }
                $stmt->close();
            } else {
                $errorMessage = "Statement preparation failed: " . $conn->error;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Travel Order Request Form | HR Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background-color: #f1f5f9;
            color: #1e293b;
        }
        .form-container {
            background: white;
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05), 0 8px 10px -6px rgba(0,0,0,0.01);
            border: 1px solid #e2e8f0;
            margin-top: 2rem;
            margin-bottom: 3rem;
            position: relative;
            overflow: hidden;
        }
        .form-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: linear-gradient(90deg, #3b82f6, #8b5cf6);
        }
        .form-title {
            color: #0f172a;
            font-weight: 700;
            margin-bottom: 2rem;
            position: relative;
            padding-bottom: 1rem;
        }
        .form-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 60px;
            height: 4px;
            background: #3b82f6;
            border-radius: 2px;
        }
        .form-label {
            font-weight: 600;
            color: #475569;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
        }
        .form-label i {
            color: #3b82f6;
            margin-right: 8px;
            font-size: 1.1rem;
        }
        .form-control, .form-select {
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
            background-color: #f8fafc;
        }
        .form-control:focus, .form-select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
            background-color: white;
        }
        .form-control[readonly] {
            background-color: #e2e8f0;
            color: #64748b;
            cursor: not-allowed;
        }
        .section-divider {
            height: 1px;
            background: #e2e8f0;
            margin: 2rem 0;
            position: relative;
        }
        .section-divider span {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 0 15px;
            color: #94a3b8;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }
        .btn-submit {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            border: none;
            border-radius: 10px;
            padding: 1rem 2rem;
            font-weight: 600;
            font-size: 1.1rem;
            letter-spacing: 0.5px;
            box-shadow: 0 10px 15px -3px rgba(59, 130, 246, 0.4);
            transition: all 0.3s ease;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 25px -5px rgba(59, 130, 246, 0.5);
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
        }
        
        /* Select2 Custom Styling */
        .select2-container--default .select2-selection--single {
            height: 48px;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            background-color: #f8fafc;
            display: flex;
            align-items: center;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: normal;
            padding-left: 1rem;
            color: #1e293b;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 46px;
            right: 10px;
        }
        .select2-dropdown {
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-9">
            <div class="form-container">
                <h2 class="form-title">
                    <i class="bi bi-airplane me-2 text-primary"></i>My Travel Request
                </h2>

                <?php if (!empty($successMessage)): ?>
                    <div class="alert alert-success d-flex align-items-center justify-content-between rounded-3 mb-4" role="alert">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-check-circle-fill fs-4 me-3"></i>
                            <div><?= $successMessage; ?></div>
                        </div>
                        <?php if (isset($newOrderId)): ?>
                            <a href="print_travel_order.php?id=<?= $newOrderId ?>" target="_blank" class="btn btn-sm btn-success">
                                <i class="bi bi-printer me-1"></i> Print
                            </a>
                        <?php endif; ?>
                    </div>
                <?php elseif (!empty($errorMessage)): ?>
                    <div class="alert alert-danger d-flex align-items-center rounded-3 mb-4" role="alert">
                        <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
                        <div><?= $errorMessage; ?></div>
                    </div>
                <?php endif; ?>

                <?php
                // Fetch employee data with branch name and post name
                $empResult = $conn->query("SELECT e.empID, e.employeeName, e.employeeEmail, p.PostName as designation, b.branch_name FROM employees e LEFT JOIN branches b ON e.BrCode = b.BrCode LEFT JOIN posts p ON e.designation = p.PostId");
                $employeeData = [];
                while ($row = $empResult->fetch_assoc()) {
                    $employeeData[$row['empID']] = $row;
                }
                
                // Fetch branch names
                $branchResult = $conn->query("SELECT branch_name FROM branches");
                $branchData = [];
                while ($row = $branchResult->fetch_assoc()) {
                    $branchData[] = $row['branch_name'];
                }
                ?>

                <form method="POST" enctype="multipart/form-data">
                    <div class="row g-4 mb-2">
                        <div class="col-md-4">
                            <label class="form-label"><i class="bi bi-person-badge"></i> Emp ID</label>
                            <select name="empID" class="form-select select2-emp" onchange="fillEmployeeDetails(this.value)" required>
                                <option value="">Select Employee ID</option>
                                <?php foreach ($employeeData as $empID => $data): ?>
                                    <option value="<?= htmlspecialchars($empID) ?>"><?= htmlspecialchars($empID) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label"><i class="bi bi-person"></i> Employee Name</label>
                            <input type="text" name="employeeName" class="form-control" placeholder="Auto-filled" readonly required>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label"><i class="bi bi-envelope"></i> Email</label>
                            <input type="email" name="employeeEmail" class="form-control" placeholder="Auto-filled" readonly required>
                        </div>
                    </div>

                    <div class="row g-4 mb-2">
                        <div class="col-md-6">
                            <label class="form-label"><i class="bi bi-award"></i> Designation</label>
                            <input type="text" name="designation" class="form-control" placeholder="Auto-filled" readonly required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label"><i class="bi bi-building"></i> Branch / Department</label>
                            <select name="department" class="form-select select2" required>
                                <option value="">Select Branch</option>
                                <?php foreach ($branchData as $branchName): ?>
                                    <option value="<?= htmlspecialchars($branchName) ?>"><?= htmlspecialchars($branchName) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="section-divider">
                        <span>Travel Details</span>
                    </div>

                    <div class="row g-4 mb-2">
                        <div class="col-md-4">
                            <label class="form-label"><i class="bi bi-geo-alt"></i> Travel From</label>
                            <input type="text" name="travelFrom" class="form-control" placeholder="Origin city/location" required>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label"><i class="bi bi-geo-fill"></i> Destination</label>
                            <input type="text" name="destination" class="form-control" placeholder="Destination city/location" required>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label"><i class="bi bi-car-front"></i> Transport Mode</label>
                            <select name="modeOfTransport" class="form-select" required>
                                <option value="">Select Transport</option>
                                <option value="By Air">By Air</option>
                                <option value="Private Vehicle">Private Vehicle</option>
                                <option value="By Public">By Public</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-4 mb-2">
                        <div class="col-md-4">
                            <label class="form-label"><i class="bi bi-calendar-event"></i> Start Date</label>
                            <input type="date" name="travelDateFrom" class="form-control" required>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label"><i class="bi bi-calendar-check"></i> End Date</label>
                            <input type="date" name="travelDateTo" class="form-control" required>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label"><i class="bi bi-cash"></i> Estimated Cost</label>
                            <div class="input-group">
                                <span class="input-group-text border-end-0 bg-light">NPR</span>
                                <input type="number" name="estimatedCost" step="0.01" class="form-control border-start-0" placeholder="0.00" required>
                            </div>
                        </div>
                    </div>

                    <div class="row g-4 mb-4">
                        <div class="col-12">
                            <label class="form-label"><i class="bi bi-card-text"></i> Purpose of Travel</label>
                            <textarea name="purpose" class="form-control" rows="3" placeholder="Provide detailed reason for the travel request" required></textarea>
                        </div>
                    </div>

                    <div class="section-divider">
                        <span>Attachments</span>
                    </div>

                    <div class="row g-4 mb-4">
                        <div class="col-12">
                            <label class="form-label"><i class="bi bi-paperclip"></i> Supporting Document (Optional)</label>
                            <input type="file" name="document" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                            <div class="form-text mt-2"><i class="bi bi-info-circle me-1"></i>Allowed formats: PDF, DOCX, JPG, PNG (Max 5MB)</div>
                        </div>
                    </div>
                    
                    <input type="hidden" name="approvingAuthority" value="Province">

                    <div class="text-center mt-5">
                        <button type="submit" class="btn btn-primary btn-submit text-white w-100">
                            <i class="bi bi-send-fill me-2"></i> Submit Travel Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    const employeeData = <?= json_encode($employeeData) ?>;

    function fillEmployeeDetails(empID) {
        if (employeeData[empID]) {
            document.querySelector('input[name="employeeName"]').value = employeeData[empID].employeeName || '';
            document.querySelector('input[name="employeeEmail"]').value = employeeData[empID].employeeEmail || '';
            document.querySelector('input[name="designation"]').value = employeeData[empID].designation || '';
            
            if (employeeData[empID].branch_name) {
                $('select[name="department"]').val(employeeData[empID].branch_name).trigger('change');
            } else {
                $('select[name="department"]').val('').trigger('change');
            }
        } else {
            document.querySelector('input[name="employeeName"]').value = '';
            document.querySelector('input[name="employeeEmail"]').value = '';
            document.querySelector('input[name="designation"]').value = '';
            $('select[name="department"]').val('').trigger('change');
        }
    }

    $(document).ready(function() {
        $('.select2').select2({
            placeholder: "Select Branch",
            allowClear: true,
            width: '100%'
        });
        
        $('.select2-emp').select2({
            placeholder: "Select Employee ID",
            allowClear: true,
            width: '100%'
        }).on('select2:select', function (e) {
            fillEmployeeDetails(e.params.data.id);
        }).on('select2:unselect', function (e) {
            fillEmployeeDetails('');
        });
    });
</script>
</body>
</html>

