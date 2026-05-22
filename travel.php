<?php
session_start();
require_once 'db.php';
$successMessage = $errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $empID = trim($_POST['empID'] ?? '');
	$BrCode = trim($_POST['BrCode'] ?? '');
    $employeeName = trim($_POST['employeeName'] ?? '');
    $employeeEmail = trim($_POST['employeeEmail'] ?? '');
    $designation = trim($_POST['designation'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $travelFrom = trim($_POST['travelFrom'] ?? '');
    $travelDateFrom = $_POST['travelDateFrom'] ?? '';
    $travelDateTo = $_POST['travelDateTo'] ?? '';
	
	$kilometer = trim($_POST['kilometer'] ?? '');
	
	
    $destination = trim($_POST['destination'] ?? '');
    $purpose = trim($_POST['purpose'] ?? '');
    $modeOfTransport = trim($_POST['modeOfTransport'] ?? '');
    $noOfDays = $_POST['noOfDays'] ?? '';
    $estimatedCost = $_POST['estimatedCost'] ?? '';

    // Province Head split
    $ph_data = $_POST['provinceHead'] ?? '';
    $ph_parts = explode('|', $ph_data);
    $selected_ph_name = $ph_parts[0] ?? '';
    $selected_ph_email = $ph_parts[1] ?? '';

    // ==================== AUTO GENERATE travel_order_no ====================
    $travel_order_no = '';
    $lastRow = $conn->query("SELECT travel_order_no FROM travel_orders WHERE travel_order_no LIKE 'TADA:%' ORDER BY id DESC LIMIT 1");
    if ($lastRow && $lastRow->num_rows > 0) {
        $lastNo = $lastRow->fetch_assoc()['travel_order_no'];
        $lastNum = (int) substr($lastNo, 5); // Extract number after "TADA:"
        $newNum = $lastNum + 1;
    } else {
        $newNum = 1;
    }
    $travel_order_no = 'TADA:' . str_pad($newNum, 4, '0', STR_PAD_LEFT);
    // =======================================================================

    // ==================== DOCUMENT UPLOAD ====================
    $documentPath = '';
    if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['document'];
        $maxSize = 5 * 1024 * 1024;

        $allowedTypes = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];

        if ($file['size'] > $maxSize) {
            $errorMessage = "File size exceeds 5MB limit.";
        } elseif (!in_array($file['type'], $allowedTypes)) {
            $errorMessage = "Only PDF, DOC, DOCX, JPG, PNG files allowed.";
        } else {
            $uploadDir = 'uploads/travel_documents/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $newFileName = $empID . '_' . date('Ymd_His') . '.' . $fileExt;
            $targetPath = $uploadDir . $newFileName;

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $documentPath = $targetPath;
            } else {
                $errorMessage = "Failed to upload document.";
            }
        }
    }
    // =========================================================

    if (empty($empID) || empty($employeeName) || empty($selected_ph_email)) {
        $errorMessage = "Required fields missing";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO travel_orders
            (travel_order_no, EmpID, BrCode, employeeName, employeeEmail, designation, department,
            travelFrom, kilometer, travelDateFrom, travelDateTo, noOfDays,
            destination, purpose, modeOfTransport, estimatedCost,
            selected_ph_name, selected_ph_email, document_path)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");

        $stmt->bind_param(
            "sssssssssssisssssss",
            $travel_order_no,
            $empID, $BrCode, $employeeName, $employeeEmail, $designation,
            $department, $travelFrom, $kilometer, $travelDateFrom, $travelDateTo,
            $noOfDays, $destination, $purpose, $modeOfTransport,
            $estimatedCost, $selected_ph_name, $selected_ph_email, $documentPath
        );

        if ($stmt->execute()) {
            // ✅ EMAIL USING mail()
            $to = $selected_ph_email;
            $subject = "New Travel Request - $employeeName [$travel_order_no]";
            $message = "
<html>
<head>
<style>
body {
    font-family: Arial, sans-serif;
    background-color: #f4f7f9;
    margin: 0;
    padding: 20px;
}
.container {
    max-width: 600px;
    margin: auto;
    background: #ffffff;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 3px 10px rgba(0,0,0,0.08);
}
.header {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    color: #ffffff;
    padding: 15px 20px;
    font-size: 18px;
    font-weight: bold;
}
.badge-no {
    display: inline-block;
    background: rgba(255,255,255,0.25);
    border: 1px solid rgba(255,255,255,0.5);
    border-radius: 20px;
    padding: 3px 12px;
    font-size: 13px;
    margin-left: 10px;
    letter-spacing: 1px;
}
.content {
    padding: 20px;
    color: #333;
}
.table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}
.table td {
    padding: 10px;
    border-bottom: 1px solid #e5e7eb;
}
.table td:first-child {
    font-weight: bold;
    width: 40%;
    color: #374151;
    background-color: #f9fafb;
}
.order-no-row td {
    background-color: #eff6ff !important;
    color: #1d4ed8 !important;
    font-weight: bold !important;
    font-size: 15px;
}
.footer {
    padding: 15px 20px;
    font-size: 12px;
    color: #888;
    text-align: center;
}
.button {
    display: inline-block;
    margin-top: 15px;
    padding: 10px 18px;
    background-color: #3b82f6;
    color: #fff !important;
    text-decoration: none;
    border-radius: 6px;
    font-weight: bold;
}
</style>
</head>
<body>
<div class='container'>
    <div class='header'>
        ✈️ Travel Request Notification
        <span class='badge-no'>$travel_order_no</span>
    </div>
    <div class='content'>
        <p>Dear <strong>$selected_ph_name</strong>,</p>
        <p>A new travel request has been submitted. Below are the details:</p>
        <table class='table'>
            <tr class='order-no-row'><td>Travel Order No.</td><td>$travel_order_no</td></tr>
            <tr><td>Employee Name</td><td>$employeeName</td></tr>
            <tr><td>Employee ID</td><td>$empID</td></tr>
            <tr><td>Department</td><td>$department</td></tr>
            <tr><td>Travel From</td><td>$travelFrom</td></tr>
			
			<tr><td>Total Kilometer</td><td>$kilometer</td></tr>
			<tr><td>Branch Code</td><td>$BrCode</td></tr>
						
            <tr><td>Destination</td><td>$destination</td></tr>
            <tr><td>Travel Dates</td><td>$travelDateFrom to $travelDateTo</td></tr>
            <tr><td>Purpose</td><td>$purpose</td></tr>
        </table>
        " . (!empty($documentPath) ? "<p><strong>Document:</strong> Attached</p>" : "") . "
        <p>Please login to the HR portal to review and take action.</p>
        <a href='https://192.168.0.174/hr' class='button'>Review Request</a>
    </div>
    <div class='footer'>
        This is an automated message from HR System.
    </div>
</div>
</body>
</html>
";
            $headers  = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8\r\n";
            $headers .= "From: HR System <noreply@nepallife.com.np>\r\n";
            mail($to, $subject, $message, $headers);

            header("Location: travel.php?submitted=1");
            exit;
        } else {
            $errorMessage = $stmt->error;
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
        /* Travel Order No Badge */
        .order-no-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #eff6ff, #dbeafe);
            border: 1.5px solid #93c5fd;
            border-radius: 10px;
            padding: 0.6rem 1.2rem;
            font-size: 1rem;
            font-weight: 700;
            color: #1d4ed8;
            letter-spacing: 1px;
        }
        .order-no-badge i {
            font-size: 1.1rem;
            color: #3b82f6;
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
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-9">
            <div class="form-container">

                <!-- Title + Auto-generated Order No -->
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
                    <h2 class="form-title mb-0" style="padding-bottom:0;">
                        <i class="bi bi-airplane me-2 text-primary"></i>Travel Order Request
                    </h2>
                    <div class="order-no-badge">
                        <i class="bi bi-hash"></i>
                        <?php
                        // Preview next travel_order_no for display
                        $previewRow = $conn->query("SELECT travel_order_no FROM travel_orders WHERE travel_order_no LIKE 'TADA:%' ORDER BY id DESC LIMIT 1");
                        if ($previewRow && $previewRow->num_rows > 0) {
                            $lastPreview = $previewRow->fetch_assoc()['travel_order_no'];
                            $previewNum = (int) substr($lastPreview, 5) + 1;
                        } else {
                            $previewNum = 1;
                        }
                        echo 'TADA:' . str_pad($previewNum, 4, '0', STR_PAD_LEFT);
                        ?>
                    </div>
                </div>

                <?php if (!empty($successMessage)): ?>
                    <div class="alert alert-success d-flex align-items-center rounded-3 mb-4" role="alert">
                        <i class="bi bi-check-circle-fill fs-4 me-3"></i>
                        <div><?= $successMessage; ?></div>
                    </div>
                <?php elseif (!empty($errorMessage)): ?>
                    <div class="alert alert-danger d-flex align-items-center rounded-3 mb-4" role="alert">
                        <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
                        <div><?= $errorMessage; ?></div>
                    </div>
                <?php endif; ?>

                <?php
                $empResult = $conn->query("SELECT e.empID, e.employeeName, e.employeeEmail, p.PostName as designation, b.branch_name FROM employees e LEFT JOIN branches b ON e.BrCode = b.BrCode LEFT JOIN posts p ON e.designation = p.PostId");
                $employeeData = [];
                while ($row = $empResult->fetch_assoc()) {
                    $employeeData[$row['empID']] = $row;
                }
                $branchResult = $conn->query("SELECT branch_name FROM branches");
                $branchData = [];
                while ($row = $branchResult->fetch_assoc()) {
                    $branchData[] = $row['branch_name'];
                }
                $phResult = $conn->query("SELECT employeeName, employeeEmail FROM employees WHERE level = 'PH'");
                $phData = [];
                while ($row = $phResult->fetch_assoc()) {
                    $phData[] = $row;
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
                        <div class="col-md-4">
                            <label class="form-label"><i class="bi bi-award"></i> Designation</label>
                            <input type="text" name="designation" class="form-control" placeholder="Auto-filled" readonly required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><i class="bi bi-building"></i> Branch / Department</label>
                            <select name="department" class="form-select select2" required>
                                <option value="">Select Branch</option>
                                <?php foreach ($branchData as $branchName): ?>
                                    <option value="<?= htmlspecialchars($branchName) ?>"><?= htmlspecialchars($branchName) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
						
						<div class="col-md-4">
                            <label class="form-label"><i class="bi bi-geo-alt"></i> Branch Code</label>
                            <input type="text" name="BrCode" class="form-control" placeholder="Your Branch Code" required>
                        </div>
						
                        <div class="col-md-4">
                            <label class="form-label"><i class="bi bi-person-check"></i> Province Head</label>
                            <select name="provinceHead" class="form-select select2" required>
                                <option value="">Select Province Head</option>
                                <?php foreach ($phData as $ph): ?>
                                    <option value="<?= htmlspecialchars($ph['employeeName'] . '|' . $ph['employeeEmail']) ?>">
                                        <?= htmlspecialchars($ph['employeeName']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="section-divider"><span>Travel Details</span></div>

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

					<div class="col-md-4">
                            <label class="form-label"><i class="bi bi-geo-fill"></i> Kilometer</label>
                            <input type="text" name="kilometer" class="form-control" placeholder="Kilometer Covered" required>
                        </div><p></p>
					
					
                    <div class="row g-4 mb-2">
                        <div class="col-md-3">
                            <label class="form-label"><i class="bi bi-calendar-event"></i> Start Date</label>
                            <input type="date" name="travelDateFrom" id="travelDateFrom" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><i class="bi bi-calendar-check"></i> End Date</label>
                            <input type="date" name="travelDateTo" id="travelDateTo" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><i class="bi bi-clock-history"></i> No of Days</label>
                            <input type="number" name="noOfDays" id="noOfDays" class="form-control" placeholder="0" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><i class="bi bi-cash"></i> Advance Amount</label>
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

                    <div class="section-divider"><span>Attachments</span></div>

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

    $(document).ready(function () {
        $('.select2').select2({ placeholder: "Select Branch", allowClear: true, width: '100%' });
        $('.select2-emp').select2({
            placeholder: "Select Employee ID",
            allowClear: true,
            width: '100%'
        }).on('select2:select', function (e) {
            fillEmployeeDetails(e.params.data.id);
        }).on('select2:unselect', function () {
            fillEmployeeDetails('');
        });

        function calculateDays() {
            const start = $('#travelDateFrom').val();
            const end = $('#travelDateTo').val();
            if (start && end) {
                const startDate = new Date(start);
                const endDate = new Date(end);
                if (endDate >= startDate) {
                    const diffTime = Math.abs(endDate - startDate);
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
                    $('#noOfDays').val(diffDays);
                } else {
                    $('#noOfDays').val('');
                }
            }
        }
        $('#travelDateFrom, #travelDateTo').on('change', calculateDays);
    });
</script>
</body>
</html>