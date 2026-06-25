<?php
session_start();
require_once 'db.php';
$successMessage = $errorMessage = '';

function getApprovalChain($level, $brCode, $requestType = 'Normal') {
    $requestType = ($requestType === 'Claim') ? 'Claim' : 'Normal';
    if ($requestType === 'Claim') {
        if ($brCode !== '100') return [];
        return ['CD', 'HR', 'CEO_OR_DCEO'];
    }
    if ($brCode === '100' && $level === 'ST') return ['DH', 'HR', 'CEO_OR_DCEO'];
    if ($level === 'CD') return ['CD', 'HR', 'CEO_OR_DCEO'];
    if ($level === 'DH') return ['HR', 'CEO_OR_DCEO'];
    if ($level === 'ST') return ['PH', 'NSM', 'HR', 'CEO_OR_DCEO'];
    if ($level === 'OI') return ['PH', 'NSM', 'HR', 'CEO_OR_DCEO'];
    if ($level === 'PH') return ['NSM', 'HR', 'CEO_OR_DCEO'];
    if ($level === 'HR') return ['CEO_OR_DCEO'];
    if ($level === 'CEO') return ['HR', 'CEO_OR_DCEO'];
    if ($level === 'DCEO') return ['HR', 'CEO_OR_DCEO'];
    return ['PH', 'NSM', 'HR', 'CEO_OR_DCEO'];
}
function getFirstApprovalLevel($level, $brCode, $requestType = 'Normal') {
    $chain = getApprovalChain($level, $brCode, $requestType);
    return $chain[0] ?? 'PH';
}
function getRecipientsByLevel($conn, $level) {
    $stmt = $conn->prepare(
        "SELECT employeeName, employeeEmail FROM employees
          WHERE level = ? AND employeeEmail IS NOT NULL AND employeeEmail != ''"
    );
    $stmt->bind_param("s", $level);
    $stmt->execute();
    $result = $stmt->get_result();
    $recipients = [];
    while ($row = $result->fetch_assoc()) $recipients[] = $row;
    return $recipients;
}
function stageLabel($stage) {
    $labels = [
        'DH' => 'Department Head', 'BM' => 'Branch Manager',
        'PH' => 'Province Head',   'CD' => 'Claim Head',
        'NSM' => 'NSM',            'HR' => 'Human Resource',
        'DCEO' => 'DCEO',          'CEO' => 'CEO',
        'CEO_OR_DCEO' => 'CEO / DCEO',
    ];
    return $labels[$stage] ?? $stage;
}
function generateTravelOrderNo($conn, $brCode, $employeeName) {
    $firstName = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', explode(' ', trim($employeeName))[0]));
    if (empty($firstName)) $firstName = 'EMP';
    $prefix = 'TADA.' . $brCode . '.' . $firstName . '.';
    $stmt = $conn->prepare(
        "SELECT travel_order_no FROM travel_orders WHERE travel_order_no LIKE ? ORDER BY id DESC LIMIT 1"
    );
    $like = $prefix . '%';
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->bind_result($lastNo);
        $stmt->fetch();
        $parts  = explode('.', $lastNo);
        $newNum = (int) end($parts) + 1;
    } else {
        $newNum = 1;
    }
    $stmt->close();
    return $prefix . str_pad($newNum, 4, '0', STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $empID           = trim($_POST['empID']           ?? '');
    $BrCode          = trim($_POST['BrCode']          ?? '');
    $level           = trim($_POST['level']           ?? '');
    $employeeName    = trim($_POST['employeeName']    ?? '');
    $employeeEmail   = trim($_POST['employeeEmail']   ?? '');
    $designation     = trim($_POST['designation']     ?? '');
    $department      = trim($_POST['department']      ?? '');
    $travelFrom      = trim($_POST['travelFrom']      ?? '');
    $travelDateFrom  = $_POST['travelDateFrom']       ?? '';
    $travelDateTo    = $_POST['travelDateTo']         ?? '';
    $kilometer       = trim($_POST['kilometer']       ?? '');
    $destination     = trim($_POST['destination']     ?? '');
    $purpose         = trim($_POST['purpose']         ?? '');
    $modeOfTransport = trim($_POST['modeOfTransport'] ?? '');
    $noOfDays        = $_POST['noOfDays']             ?? '';
    $estimatedCost   = $_POST['estimatedCost']        ?? '';
    $requestType     = $_POST['requestType']          ?? 'Normal';
    $requestType     = ($requestType === 'Claim') ? 'Claim' : 'Normal';

    if ($requestType === 'Claim' && $BrCode !== '100') {
        $errorMessage = "Claim option is allowed only for Branch Code 100.";
    }

    $approverEmailInput = $_POST['approverEmail'] ?? '';
    $firstApprovalLevel = empty($errorMessage) ? getFirstApprovalLevel($level, $BrCode, $requestType) : '';
    $firstApprovers     = empty($errorMessage) ? getRecipientsByLevel($conn, $firstApprovalLevel) : [];

    if (empty($errorMessage) && empty($firstApprovers)) {
        $errorMessage = "No approver found for level: $firstApprovalLevel";
    }
    if (empty($errorMessage) && !empty($approverEmailInput)) {
        $filteredApprovers = [];
        foreach ($firstApprovers as $appr) {
            if ($appr['employeeEmail'] === $approverEmailInput) {
                $filteredApprovers[] = $appr;
                break;
            }
        }
        if (empty($filteredApprovers)) {
            $errorMessage = "Selected approver is invalid or not in the required level.";
        } else {
            $firstApprovers = $filteredApprovers;
        }
    }

    if (empty($errorMessage)) {
        $travel_order_no = generateTravelOrderNo($conn, $BrCode, $employeeName);
        $documentPath = '';
        if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
            $file         = $_FILES['document'];
            $maxSize      = 5 * 1024 * 1024;
            $allowedTypes = ['application/pdf','image/jpeg','image/png'];
            if ($file['size'] > $maxSize) {
                $errorMessage = "File size exceeds 5MB limit.";
            } elseif (!in_array($file['type'], $allowedTypes)) {
                $errorMessage = "Only PDF, JPG, PNG files allowed.";
            } else {
                $uploadDir = 'uploads/travel_documents/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                $fileExt     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $newFileName = $empID . '_' . date('Ymd_His') . '.' . $fileExt;
                $targetPath  = $uploadDir . $newFileName;
                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    $documentPath = $targetPath;
                } else {
                    $errorMessage = "Failed to upload document.";
                }
            }
        }
        if (empty($empID) || empty($employeeName) || empty($firstApprovalLevel)) {
            $errorMessage = "Required fields missing.";
        }
        if (empty($errorMessage)) {
            $assignedApproverEmail = !empty($firstApprovers[0]['employeeEmail'])
                                     ? $firstApprovers[0]['employeeEmail'] : '';
            $stmt = $conn->prepare("
                INSERT INTO travel_orders
                (travel_order_no, EmpID, BrCode, employeeName, employeeEmail,
                 designation, department, level, travelFrom, kilometer,
                 travelDateFrom, travelDateTo, noOfDays,
                 destination, purpose, modeOfTransport, estimatedCost,
                 request_type, current_approval_stage, assigned_approver_email,
                 document_path, created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ");
            $now = date('Y-m-d H:i:s');
            $stmt->bind_param(
                "ssssssssssssssssssssss",
                $travel_order_no, $empID, $BrCode, $employeeName, $employeeEmail,
                $designation, $department, $level, $travelFrom, $kilometer,
                $travelDateFrom, $travelDateTo, $noOfDays,
                $destination, $purpose, $modeOfTransport, $estimatedCost,
                $requestType, $firstApprovalLevel, $assignedApproverEmail,
                $documentPath, $now
            );
            if ($stmt->execute()) {
                $approvalChain  = getApprovalChain($level, $BrCode, $requestType);
                foreach ($firstApprovers as $approver) {
                    $to            = $approver['employeeEmail'];
                    $recipientName = $approver['employeeName'];
                    $subject       = "Action Required: " . stageLabel($firstApprovalLevel) . " Approval – Travel Order {$travel_order_no}";
                    $emailBody = "<!DOCTYPE html><html><body>
                        <p>Dear {$recipientName},</p>
                        <p>Travel order <strong>{$travel_order_no}</strong> from {$employeeName} requires your approval.</p>
                        <p><a href='https://monitor.nepallife.com.np/hr'>Click here to approve</a></p>
                    </body></html>";
                    $headers  = "MIME-Version: 1.0\r\nContent-type:text/html;charset=UTF-8\r\n";
                    $headers .= "From: Nepal Life Travel System <noreply@nepallife.com.np>\r\n";
                    mail($to, $subject, $emailBody, $headers);
                }
                header("Location: travel.php?submitted=1&travel_order_no=" . urlencode($travel_order_no));
                exit;
            } else {
                $errorMessage = "Database error: " . $stmt->error;
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
        body { font-family: 'Outfit', sans-serif; background-color: #f1f5f9; color: #1e293b; }

        .form-container {
            background: white; border-radius: 20px; padding: 2.5rem;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0; margin-top: 2rem; margin-bottom: 3rem;
            position: relative; overflow: hidden;
        }
        .form-container::before {
            content: ''; position: absolute; top: 0; left: 0;
            width: 100%; height: 6px;
            background: linear-gradient(90deg, #3b82f6, #8b5cf6);
        }
        .form-title { color: #0f172a; font-weight: 700; margin-bottom: 2rem; position: relative; padding-bottom: 1rem; }
        .form-title::after {
            content: ''; position: absolute; bottom: 0; left: 0;
            width: 60px; height: 4px; background: #3b82f6; border-radius: 2px;
        }
        .order-no-badge {
            display: inline-flex; align-items: center; gap: 8px;
            background: linear-gradient(135deg, #eff6ff, #dbeafe);
            border: 1.5px solid #93c5fd; border-radius: 10px;
            padding: 0.6rem 1.2rem; font-size: 1rem; font-weight: 700;
            color: #1d4ed8; letter-spacing: 1px;
        }
        .order-no-badge i { font-size: 1.1rem; color: #3b82f6; }
        .approval-stage-badge {
            display: inline-flex; align-items: center; gap: 8px;
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border: 1.5px solid #fcd34d; border-radius: 10px;
            padding: 0.6rem 1.2rem; font-size: 0.9rem; font-weight: 600;
            color: #92400e; letter-spacing: 0.5px; margin-top: 1rem;
        }
        .form-label { font-weight: 600; color: #475569; margin-bottom: 0.5rem; display: flex; align-items: center; }
        .form-label i { color: #3b82f6; margin-right: 8px; font-size: 1.1rem; }
        .form-control, .form-select {
            border-radius: 10px; border: 1px solid #cbd5e1;
            padding: 0.75rem 1rem; transition: all 0.3s ease; background-color: #f8fafc;
        }
        .form-control:focus, .form-select:focus {
            border-color: #3b82f6; box-shadow: 0 0 0 4px rgba(59,130,246,0.1); background-color: white;
        }
        .form-control[readonly] { background-color: #e2e8f0; color: #64748b; cursor: not-allowed; }
        .section-divider { height: 1px; background: #e2e8f0; margin: 2rem 0; position: relative; }
        .section-divider span {
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
            background: white; padding: 0 15px; color: #94a3b8;
            font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 600;
        }
        .btn-submit {
            background: linear-gradient(135deg, #3b82f6, #2563eb); border: none;
            border-radius: 10px; padding: 1rem 2rem; font-weight: 600;
            font-size: 1.1rem; letter-spacing: 0.5px;
            box-shadow: 0 10px 15px -3px rgba(59,130,246,0.4); transition: all 0.3s ease;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 25px -5px rgba(59,130,246,0.5);
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
        }
        .select2-container--default .select2-selection--single {
            height: 48px; border-radius: 10px; border: 1px solid #cbd5e1;
            background-color: #f8fafc; display: flex; align-items: center;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: normal; padding-left: 1rem; color: #1e293b;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 46px; right: 10px; }
        .select2-dropdown { border: 1px solid #cbd5e1; border-radius: 10px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }

        /* ── Dual BS/AD Calendar Picker ── */
        .nep-picker-wrap { position: relative; }
        .nep-input-group { display: flex; gap: 0; border-radius: 10px; overflow: hidden; border: 1px solid #cbd5e1; background: #f8fafc; }
        .nep-input-ad, .nep-input-bs {
            flex: 1; border: none; background: transparent;
            padding: 0.72rem 0.6rem; font-size: 0.9rem; font-family: 'Outfit',sans-serif;
            color: #1e293b; outline: none; cursor: pointer; min-width: 0;
        }
        .nep-input-ad::placeholder, .nep-input-bs::placeholder { color: #94a3b8; }
        .nep-divider { width: 1px; background: #cbd5e1; margin: 8px 0; }
        .nep-cal-icon { display:flex; align-items:center; padding: 0 12px; color:#3b82f6; font-size:1.1rem; cursor:pointer; }

        /* Popup calendar */
        .nep-popup {
            display: none; position: absolute; top: calc(100% + 6px); left: 0; z-index: 9999;
            background: white; border-radius: 16px; border: 1px solid #e2e8f0;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15); min-width: 320px;
            overflow: hidden; animation: popIn 0.15s ease;
        }
        .nep-popup.open { display: block; }
        @keyframes popIn { from { opacity:0; transform:translateY(-6px); } to { opacity:1; transform:translateY(0); } }

        /* Mode tabs */
        .nep-tabs { display: flex; border-bottom: 1px solid #e2e8f0; }
        .nep-tab {
            flex:1; padding: 10px; text-align:center; font-size:0.82rem; font-weight:700;
            cursor:pointer; color:#64748b; border-bottom: 3px solid transparent;
            transition: all 0.2s; letter-spacing:0.5px;
        }
        .nep-tab.active { color:#3b82f6; border-bottom-color:#3b82f6; background:#f0f7ff; }

        /* Calendar header nav */
        .nep-cal-header {
            display:flex; align-items:center; justify-content:space-between;
            padding: 12px 16px; background:#f8fafc; border-bottom:1px solid #e2e8f0;
        }
        .nep-nav-btn {
            background:none; border:none; cursor:pointer; font-size:1.1rem;
            color:#3b82f6; padding:4px 8px; border-radius:6px; transition:background 0.2s;
        }
        .nep-nav-btn:hover { background:#dbeafe; }
        .nep-month-year { font-weight:700; font-size:0.95rem; color:#1e293b; text-align:center; flex:1; }
        .nep-month-year select {
            border:none; background:transparent; font-weight:700; font-size:0.93rem;
            color:#1e293b; cursor:pointer; outline:none; font-family:'Outfit',sans-serif;
        }

        /* Day grid */
        .nep-grid { padding: 10px 12px 14px; }
        .nep-weekdays { display:grid; grid-template-columns:repeat(7,1fr); margin-bottom:6px; }
        .nep-weekday { text-align:center; font-size:0.72rem; font-weight:700; color:#94a3b8; padding:4px 0; }
        .nep-days { display:grid; grid-template-columns:repeat(7,1fr); gap:2px; }
        .nep-day {
            text-align:center; padding:7px 2px; border-radius:8px; cursor:pointer;
            font-size:0.87rem; color:#1e293b; transition:all 0.15s; position:relative;
        }
        .nep-day:hover { background:#dbeafe; color:#1d4ed8; }
        .nep-day.selected { background:#3b82f6; color:white; font-weight:700; }
        .nep-day.today { font-weight:700; }
        .nep-day.today::after {
            content:''; position:absolute; bottom:3px; left:50%; transform:translateX(-50%);
            width:4px; height:4px; border-radius:50%; background:#3b82f6;
        }
        .nep-day.today.selected::after { background:white; }
        .nep-day.empty { cursor:default; }
        .nep-day.other-month { color:#cbd5e1; }

        /* Sync bar at bottom */
        .nep-sync-bar {
            padding:8px 16px; background:#f0fdf4; border-top:1px solid #dcfce7;
            font-size:0.78rem; color:#16a34a; font-weight:500;
            display:flex; align-items:center; gap:6px; min-height:32px;
        }
        .nep-sync-bar.empty { background:#f8fafc; color:#94a3b8; border-color:#e2e8f0; }
    </style>
</head>
<body>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-9">
            <div class="form-container">

                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
                    <h2 class="form-title mb-0" style="padding-bottom:0;">
                        <i class="bi bi-airplane me-2 text-primary"></i>Travel Order Request
                    </h2>
                    <div class="order-no-badge" id="tadaNoPreview">
                        <i class="bi bi-hash"></i>
                        <span id="tadaNoText">Select an employee</span>
                    </div>
                    <a href="travel.php" class="btn btn-info btn-lg">Apply Next TADA Now</a>
                </div>

                <?php if (isset($_GET['submitted']) && $_GET['submitted'] == '1'):
                    $shownOrderNo = htmlspecialchars($_GET['travel_order_no'] ?? '');
                ?>
                    <div class="alert alert-success rounded-3 mb-4 p-4" role="alert" style="border-left:5px solid #22c55e;">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <i class="bi bi-check-circle-fill text-success fs-2"></i>
                            <div>
                                <h5 class="mb-0 fw-700">Travel Order Submitted Successfully!</h5>
                                <p class="mb-0 text-muted" style="font-size:0.9rem;">Your request has been sent for approval.</p>
                            </div>
                        </div>
                        <?php if ($shownOrderNo): ?>
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <span class="fw-600 text-secondary">TADA No:</span>
                            <span class="order-no-badge" style="font-size:0.95rem;padding:0.4rem 1rem;">
                                <i class="bi bi-hash"></i><?= $shownOrderNo ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        <hr class="my-2">
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <span class="text-muted" style="font-size:0.9rem;">
                                <i class="bi bi-info-circle me-1"></i>
                                Now apply your expense claim against this travel order.
                            </span>
                            <a href="travel.php" class="btn btn-primary btn-sm px-4 fw-600">
                                <i class="bi bi-receipt me-1"></i> Apply Next TADA Now
                            </a>
                        </div>
                    </div>
                <?php elseif (!empty($errorMessage)): ?>
                    <div class="alert alert-danger d-flex align-items-center rounded-3 mb-4" role="alert">
                        <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
                        <div><?= $errorMessage; ?></div>
                    </div>
                <?php endif; ?>

                <?php
                $empResult = $conn->query("
                    SELECT e.EmpID, e.employeeName, e.employeeEmail, e.BrCode, e.level,
                           e.designation AS designationId,
                           COALESCE(p.PostName, e.designation) AS designationName,
                           b.branch_name
                    FROM employees e
                    LEFT JOIN branches b ON e.BrCode  = b.BrCode
                    LEFT JOIN posts    p ON e.designation = p.PostId
                ");
                $employeeData = [];
                while ($row = $empResult->fetch_assoc()) $employeeData[$row['EmpID']] = $row;

                $branchResult = $conn->query("SELECT branch_name FROM branches ORDER BY branch_name ASC");
                $branchData   = [];
                while ($row = $branchResult->fetch_assoc()) $branchData[] = $row['branch_name'];
                ?>

                <form method="POST" enctype="multipart/form-data" id="travelForm">

                    <!-- Employee Info -->
                    <div class="row g-4 mb-2">
                        <div class="col-md-4">
                            <label class="form-label"><i class="bi bi-person-badge"></i> Emp ID</label>
                            <select name="empID" class="form-select select2-emp" required>
                                <option value="">Search by ID or Name...</option>
                                <?php foreach ($employeeData as $empID => $data): ?>
                                    <option value="<?= htmlspecialchars($empID) ?>"
                                            data-name="<?= htmlspecialchars($data['employeeName']) ?>">
                                        <?= htmlspecialchars($empID) ?> – <?= htmlspecialchars($data['employeeName']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><i class="bi bi-person"></i> Employee Name</label>
                            <input type="text" name="employeeName" id="fieldEmployeeName" class="form-control" placeholder="Auto-filled" readonly required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><i class="bi bi-envelope"></i> Email</label>
                            <input type="email" name="employeeEmail" id="fieldEmployeeEmail" class="form-control" placeholder="Auto-filled" readonly required>
                        </div>
                    </div>

                    <div class="row g-4 mb-2">
                        <div class="col-md-3">
                            <label class="form-label"><i class="bi bi-award"></i> Designation</label>
                            <input type="hidden" name="designationId" id="fieldDesignationId">
                            <input type="text" name="designation" id="fieldDesignation" class="form-control" placeholder="Auto-filled" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><i class="bi bi-shield-check"></i> Level/Role</label>
                            <input type="text" name="level" id="fieldLevel" class="form-control" placeholder="Auto-filled" readonly required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><i class="bi bi-code-square"></i> Branch Code</label>
                            <input type="text" name="BrCode" id="fieldBrCode" class="form-control" placeholder="Auto-filled" readonly required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><i class="bi bi-building"></i> Department</label>
                            <select name="department" class="form-select select2" required>
                                <option value="">Select Branch</option>
                                <?php foreach ($branchData as $branchName): ?>
                                    <option value="<?= htmlspecialchars($branchName) ?>"><?= htmlspecialchars($branchName) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-4 mb-2">
                        <div class="col-md-4">
                            <label class="form-label"><i class="bi bi-list-check"></i> Request Type</label>
                            <select name="requestType" id="requestType" class="form-select" required onchange="handleRequestTypeChange()">
                                <option value="Normal">Normal</option>
                                <option value="Claim" id="claimOption" style="display:none;">Claim</option>
                            </select>
                            <div class="form-text mt-2" id="claimHelpText" style="display:none;">
                                Claim is available only for Corporate and routes to CD → HR → CEO/DCEO.
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><i class="bi bi-person-check"></i> Approver Name</label>
                            <select name="approverEmail" id="approverSelect" class="form-select select2" required>
                                <option value="">Select Approver</option>
                            </select>
                            <div class="form-text mt-2">Select the specific person to approve your request.</div>
                        </div>
                    </div>

                    <div id="approvalStageContainer" style="display:none;">
                        <div class="approval-stage-badge" id="approvalStageBadge">
                            <i class="bi bi-arrow-right-circle"></i>
                            <span id="approvalStageText">Will route to: -</span>
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
                                <option value="By Public">By Public</option>
                                <option value="Car">Car</option>
                                <option value="Bike">Bike</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-4 mb-2">
                        <div class="col-md-4">
                            <label class="form-label"><i class="bi bi-speedometer2"></i> Kilometer</label>
                            <input type="number" name="kilometer" class="form-control" placeholder="Kilometer Covered" required>
                        </div>
                    </div>

                    <!-- ══════════════ BS / AD CALENDAR PICKERS ══════════════ -->
                    <div class="row g-4 mb-2">

                        <!-- Start Date -->
                        <div class="col-md-3">
                            <label class="form-label"><i class="bi bi-calendar-event"></i> Start Date</label>
                            <div class="nep-picker-wrap" id="pickerWrapFrom">
                                <div class="nep-input-group" onclick="openPicker('From')">
                                    <input type="text" id="displayFrom" class="nep-input-ad" placeholder="AD date" readonly>
                                    <div class="nep-divider"></div>
                                    <input type="text" id="displayBSFrom" class="nep-input-bs" placeholder="BS date" readonly>
                                    <span class="nep-cal-icon"><i class="bi bi-calendar3"></i></span>
                                </div>
                                <input type="hidden" name="travelDateFrom" id="travelDateFrom">
                                <!-- Popup -->
                                <div class="nep-popup" id="popupFrom">
                                    <div class="nep-tabs">
                                        <div class="nep-tab active" id="tabADFrom" onclick="setPickerMode('From','AD')">AD Calendar</div>
                                        <div class="nep-tab"        id="tabBSFrom" onclick="setPickerMode('From','BS')">BS Calendar (नेपाली)</div>
                                    </div>
                                    <div class="nep-cal-header">
                                        <button type="button" class="nep-nav-btn" onclick="navMonth('From',-1)">&#8249;</button>
                                        <div class="nep-month-year" id="headerFrom">—</div>
                                        <button type="button" class="nep-nav-btn" onclick="navMonth('From',1)">&#8250;</button>
                                    </div>
                                    <div class="nep-grid">
                                        <div class="nep-weekdays" id="weekdaysFrom"></div>
                                        <div class="nep-days" id="daysFrom"></div>
                                    </div>
                                    <div class="nep-sync-bar empty" id="syncBarFrom">
                                        <i class="bi bi-arrow-left-right"></i> <span id="syncTextFrom">Select a date</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- End Date -->
                        <div class="col-md-3">
                            <label class="form-label"><i class="bi bi-calendar-check"></i> End Date</label>
                            <div class="nep-picker-wrap" id="pickerWrapTo">
                                <div class="nep-input-group" onclick="openPicker('To')">
                                    <input type="text" id="displayTo" class="nep-input-ad" placeholder="AD date" readonly>
                                    <div class="nep-divider"></div>
                                    <input type="text" id="displayBSTo" class="nep-input-bs" placeholder="BS date" readonly>
                                    <span class="nep-cal-icon"><i class="bi bi-calendar3"></i></span>
                                </div>
                                <input type="hidden" name="travelDateTo" id="travelDateTo">
                                <!-- Popup -->
                                <div class="nep-popup" id="popupTo">
                                    <div class="nep-tabs">
                                        <div class="nep-tab active" id="tabADTo" onclick="setPickerMode('To','AD')">AD Calendar</div>
                                        <div class="nep-tab"        id="tabBSTo" onclick="setPickerMode('To','BS')">BS Calendar (नेपाली)</div>
                                    </div>
                                    <div class="nep-cal-header">
                                        <button type="button" class="nep-nav-btn" onclick="navMonth('To',-1)">&#8249;</button>
                                        <div class="nep-month-year" id="headerTo">—</div>
                                        <button type="button" class="nep-nav-btn" onclick="navMonth('To',1)">&#8250;</button>
                                    </div>
                                    <div class="nep-grid">
                                        <div class="nep-weekdays" id="weekdaysTo"></div>
                                        <div class="nep-days" id="daysTo"></div>
                                    </div>
                                    <div class="nep-sync-bar empty" id="syncBarTo">
                                        <i class="bi bi-arrow-left-right"></i> <span id="syncTextTo">Select a date</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label"><i class="bi bi-clock-history"></i> No of Days</label>
                            <input type="number" name="noOfDays" id="noOfDays" class="form-control" placeholder="0" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><i class="bi bi-cash"></i> Advance Amount</label>
                            <div class="input-group">
                                <span class="input-group-text border-end-0 bg-light">NPR</span>
                                <input type="number" name="estimatedCost" step="0.01"
                                       class="form-control border-start-0" placeholder="0.00" required>
                            </div>
                        </div>
                    </div>
                    <!-- ══════════════ END DATE PICKERS ══════════════ -->

                    <div class="row g-4 mb-4">
                        <div class="col-12">
                            <label class="form-label"><i class="bi bi-card-text"></i> Purpose of Travel</label>
                            <textarea name="purpose" class="form-control" rows="3"
                                      placeholder="Provide detailed reason for the travel request" required></textarea>
                        </div>
                    </div>

                    <div class="section-divider"><span>Attachments</span></div>

                    <div class="row g-4 mb-4">
                        <div class="col-12">
                            <label class="form-label"><i class="bi bi-paperclip"></i> Supporting Document (Optional)</label>
                            <input type="file" name="document" class="form-control" accept=".pdf,.doc,.jpg,.jpeg,.png">
                            <div class="form-text mt-2">
                                <i class="bi bi-info-circle me-1"></i>Allowed formats: PDF, JPG, PNG (Max 5MB)
                            </div>
                        </div>
                    </div>

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
// ════════════════════════════════════════════════
//  BS ↔ AD CALENDAR ENGINE
// ════════════════════════════════════════════════
// Days in each BS month per year  (2000–2090 BS)
const bsData = {
    2000:[30,32,31,32,31,30,30,30,29,30,29,31],
    2001:[31,31,32,31,31,31,30,29,30,29,30,30],
    2002:[31,31,32,32,31,30,30,29,30,29,30,30],
    2003:[31,32,31,32,31,30,30,30,29,29,30,31],
    2004:[30,32,31,32,31,30,30,30,29,30,29,31],
    2005:[31,31,32,31,31,31,30,29,30,29,30,30],
    2006:[31,31,32,32,31,30,30,29,30,29,30,30],
    2007:[31,32,31,32,31,30,30,30,29,29,30,31],
    2008:[31,31,32,31,31,31,30,29,30,29,30,30],
    2009:[31,31,32,32,31,30,30,29,30,29,30,30],
    2010:[31,32,31,32,31,30,30,30,29,29,30,30],
    2011:[31,31,32,31,31,31,30,29,30,29,30,30],
    2012:[31,31,32,32,31,30,30,29,30,29,30,30],
    2013:[31,32,31,32,31,30,30,30,29,29,30,30],
    2014:[31,31,32,31,31,31,30,29,30,29,30,30],
    2015:[31,32,31,32,31,30,30,29,30,29,30,30],
    2016:[31,32,31,32,31,30,30,30,29,29,30,30],
    2017:[31,31,32,31,31,31,30,29,30,29,30,30],
    2018:[31,31,32,32,31,30,30,29,30,29,30,30],
    2019:[31,32,31,32,31,30,30,30,29,29,30,31],
    2020:[30,32,31,32,31,30,30,30,29,30,29,31],
    2021:[31,31,32,31,31,31,30,29,30,29,30,30],
    2022:[31,31,32,32,31,30,30,29,30,29,30,30],
    2023:[31,32,31,32,31,30,30,30,29,29,30,30],
    2024:[31,31,32,31,31,31,30,29,30,29,30,30],
    2025:[31,31,32,32,31,30,30,29,30,29,30,30],
    2026:[31,32,31,32,31,30,30,30,29,29,30,31],
    2027:[30,32,31,32,31,30,30,30,29,30,29,31],
    2028:[31,31,32,31,31,31,30,29,30,29,30,30],
    2029:[31,31,32,32,31,30,30,29,30,29,30,30],
    2030:[31,32,31,32,31,30,30,30,29,29,30,30],
    2031:[31,31,32,31,31,31,30,29,30,29,30,30],
    2032:[31,31,32,32,31,30,30,29,30,29,30,30],
    2033:[31,32,31,32,31,30,30,30,29,29,30,30],
    2034:[31,31,32,31,31,31,30,29,30,29,30,30],
    2035:[31,31,32,32,31,30,30,29,30,29,30,30],
    2036:[31,32,31,32,31,30,30,30,29,29,30,30],
    2037:[31,31,32,31,31,31,30,29,30,29,30,30],
    2038:[31,31,32,32,31,30,30,29,30,29,30,30],
    2039:[31,32,31,32,31,30,30,30,29,29,30,31],
    2040:[30,32,31,32,31,30,30,30,29,30,29,31],
    2041:[31,31,32,31,31,31,30,29,30,29,30,30],
    2042:[31,31,32,32,31,30,30,29,30,29,30,30],
    2043:[31,32,31,32,31,30,30,30,29,29,30,30],
    2044:[31,31,32,31,31,31,30,29,30,29,30,30],
    2045:[31,31,32,32,31,30,30,29,30,29,30,30],
    2046:[31,32,31,32,31,30,30,30,29,29,30,30],
    2047:[31,31,32,31,31,31,30,29,30,29,30,30],
    2048:[31,31,32,32,31,30,30,29,30,29,30,30],
    2049:[31,32,31,32,31,30,30,30,29,29,30,31],
    2050:[30,32,31,32,31,30,30,30,29,30,29,31],
    2051:[31,31,32,31,31,31,30,29,30,29,30,30],
    2052:[31,31,32,32,31,30,30,29,30,29,30,30],
    2053:[31,32,31,32,31,30,30,30,29,29,30,30],
    2054:[31,31,32,31,31,31,30,29,30,29,30,30],
    2055:[31,31,32,32,31,30,30,29,30,29,30,30],
    2056:[31,32,31,32,31,30,30,30,29,29,30,30],
    2057:[31,31,32,31,31,31,30,29,30,29,30,30],
    2058:[31,31,32,32,31,30,30,29,30,29,30,30],
    2059:[31,32,31,32,31,30,30,30,29,29,30,31],
    2060:[30,32,31,32,31,30,30,30,29,30,29,31],
    2061:[31,31,32,31,31,31,30,29,30,29,30,30],
    2062:[31,31,32,32,31,30,30,29,30,29,30,30],
    2063:[31,32,31,32,31,30,30,30,29,29,30,30],
    2064:[31,31,32,31,31,31,30,29,30,29,30,30],
    2065:[31,31,32,32,31,30,30,29,30,29,30,30],
    2066:[31,32,31,32,31,30,30,30,29,29,30,30],
    2067:[31,31,32,31,31,31,30,29,30,29,30,30],
    2068:[31,31,32,32,31,30,30,29,30,29,30,30],
    2069:[31,32,31,32,31,30,30,30,29,29,30,31],
    2070:[30,32,31,32,31,30,30,30,29,30,29,31],
    2071:[31,31,32,31,31,31,30,29,30,29,30,30],
    2072:[31,31,32,32,31,30,30,29,30,29,30,30],
    2073:[31,32,31,32,31,30,30,30,29,29,30,30],
    2074:[31,31,32,31,31,31,30,29,30,29,30,30],
    2075:[31,31,32,32,31,30,30,29,30,29,30,30],
    2076:[31,32,31,32,31,30,30,30,29,29,30,30],
    2077:[31,31,32,31,31,31,30,29,30,29,30,30],
    2078:[31,31,32,32,31,30,30,29,30,29,30,30],
    2079:[31,32,31,32,31,30,30,30,29,29,30,31],
    2080:[30,32,31,32,31,30,30,30,29,30,29,31],
    2081:[31,31,32,31,31,31,30,29,30,29,30,30],
    2082:[31,31,32,32,31,30,30,29,30,29,30,30],
    2083:[31,32,31,32,31,30,30,30,29,29,30,30],
    2084:[31,31,32,31,31,31,30,29,30,29,30,30],
    2085:[31,31,32,32,31,30,30,29,30,29,30,30],
    2086:[31,32,31,32,31,30,30,30,29,29,30,30],
    2087:[31,31,32,31,31,31,30,29,30,29,30,30],
    2088:[31,31,32,32,31,30,30,29,30,29,30,30],
    2089:[31,32,31,32,31,30,30,30,29,29,30,31],
    2090:[30,32,31,32,31,30,30,30,29,30,29,31]
};

// BS epoch: 2000/1/1 BS = 1943/4/14 AD
const BS_EPOCH_AD = new Date(1943, 3, 14); // month is 0-indexed
const BS_EPOCH_BS = { year:2000, month:1, day:1 };

const bsMonthNames = ['Baisakh','Jestha','Ashadh','Shrawan','Bhadra','Ashwin',
                      'Kartik','Mangsir','Poush','Magh','Falgun','Chaitra'];
const adMonthNames = ['January','February','March','April','May','June',
                      'July','August','September','October','November','December'];
const weekdaysFull = ['Su','Mo','Tu','We','Th','Fr','Sa'];
const weekdaysBSFull = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

// ── Conversion helpers ──
function padZ(n) { return String(n).padStart(2,'0'); }
function toISODate(d) { return `${d.getFullYear()}-${padZ(d.getMonth()+1)}-${padZ(d.getDate())}`; }

function adToBS_convert(adDate) {
    const diff = Math.floor((adDate - BS_EPOCH_AD) / 86400000);
    if (diff < 0) return null;
    let remaining = diff;
    for (let y = 2000; y <= 2090; y++) {
        if (!bsData[y]) break;
        let daysInYear = bsData[y].reduce((a,b)=>a+b,0);
        if (remaining < daysInYear) {
            for (let m = 0; m < 12; m++) {
                if (remaining < bsData[y][m]) return {year:y, month:m+1, day:remaining+1};
                remaining -= bsData[y][m];
            }
        }
        remaining -= daysInYear;
    }
    return null;
}

function bsToAD_convert(bsYear, bsMonth, bsDay) {
    let totalDays = 0;
    for (let y = 2000; y < bsYear; y++) {
        if (!bsData[y]) return null;
        totalDays += bsData[y].reduce((a,b)=>a+b,0);
    }
    for (let m = 0; m < bsMonth - 1; m++) totalDays += bsData[bsYear][m];
    totalDays += bsDay - 1;
    const ad = new Date(BS_EPOCH_AD);
    ad.setDate(ad.getDate() + totalDays);
    return ad;
}

// ── Picker state per field ──
const pickerState = {
    From: { mode:'AD', viewYear:0, viewMonth:0, selectedAD:null },
    To:   { mode:'AD', viewYear:0, viewMonth:0, selectedAD:null }
};

// ── Initialise view to today ──
function initPickerState(suffix) {
    const today = new Date();
    const st = pickerState[suffix];
    if (st.viewYear === 0) {
        st.viewYear  = today.getFullYear();
        st.viewMonth = today.getMonth(); // 0-indexed for AD
    }
}

// ── Open / close ──
function openPicker(suffix) {
    // Close the other
    const other = suffix === 'From' ? 'To' : 'From';
    document.getElementById('popup' + other).classList.remove('open');

    initPickerState(suffix);
    renderCalendar(suffix);
    document.getElementById('popup' + suffix).classList.add('open');
}

function closePicker(suffix) {
    document.getElementById('popup' + suffix).classList.remove('open');
}

// Close on outside click
document.addEventListener('click', function(e) {
    ['From','To'].forEach(s => {
        const wrap = document.getElementById('pickerWrap' + s);
        if (wrap && !wrap.contains(e.target)) closePicker(s);
    });
});

// ── Switch mode (AD / BS) ──
function setPickerMode(suffix, mode) {
    const st = pickerState[suffix];
    st.mode = mode;

    // Update tabs
    document.getElementById('tabAD' + suffix).classList.toggle('active', mode === 'AD');
    document.getElementById('tabBS' + suffix).classList.toggle('active', mode === 'BS');

    // Sync view to currently selected date if any
    if (st.selectedAD) {
        if (mode === 'AD') {
            st.viewYear  = st.selectedAD.getFullYear();
            st.viewMonth = st.selectedAD.getMonth();
        } else {
            const bs = adToBS_convert(st.selectedAD);
            if (bs) { st.viewYear = bs.year; st.viewMonth = bs.month - 1; }
        }
    } else {
        if (mode === 'BS') {
            // Default to current BS month
            const todayBS = adToBS_convert(new Date());
            if (todayBS) { st.viewYear = todayBS.year; st.viewMonth = todayBS.month - 1; }
        }
    }
    renderCalendar(suffix);
}

// ── Navigate months ──
function navMonth(suffix, dir) {
    const st = pickerState[suffix];
    if (st.mode === 'AD') {
        st.viewMonth += dir;
        if (st.viewMonth > 11) { st.viewMonth = 0;  st.viewYear++; }
        if (st.viewMonth < 0)  { st.viewMonth = 11; st.viewYear--; }
    } else {
        st.viewMonth += dir;
        if (st.viewMonth > 11) { st.viewMonth = 0;  st.viewYear++; }
        if (st.viewMonth < 0)  { st.viewMonth = 11; st.viewYear--; }
    }
    renderCalendar(suffix);
}

// ── Render calendar grid ──
function renderCalendar(suffix) {
    const st = pickerState[suffix];
    const headerEl   = document.getElementById('header'    + suffix);
    const weekdayEl  = document.getElementById('weekdays'  + suffix);
    const daysEl     = document.getElementById('days'      + suffix);

    // Weekday headers
    weekdayEl.innerHTML = weekdaysFull.map(d => `<div class="nep-weekday">${d}</div>`).join('');

    if (st.mode === 'AD') {
        // Header
        headerEl.innerHTML = `<strong>${adMonthNames[st.viewMonth]} ${st.viewYear}</strong>`;

        // Build AD grid
        const firstDay = new Date(st.viewYear, st.viewMonth, 1).getDay(); // 0=Sun
        const daysInMonth = new Date(st.viewYear, st.viewMonth + 1, 0).getDate();
        const today = new Date();
        let html = '';
        for (let i = 0; i < firstDay; i++) html += '<div class="nep-day empty"></div>';
        for (let d = 1; d <= daysInMonth; d++) {
            const thisDate = new Date(st.viewYear, st.viewMonth, d);
            const iso = toISODate(thisDate);
            const isToday    = toISODate(today) === iso;
            const isSelected = st.selectedAD && toISODate(st.selectedAD) === iso;
            html += `<div class="nep-day${isToday?' today':''}${isSelected?' selected':''}"
                          onclick="selectDate('${suffix}','${iso}')">${d}</div>`;
        }
        daysEl.innerHTML = html;

    } else {
        // BS mode
        const bsYear  = st.viewYear;
        const bsMonth = st.viewMonth + 1; // 1-indexed
        if (!bsData[bsYear]) { daysEl.innerHTML = '<div style="padding:20px;color:#94a3b8;text-align:center;">Year out of range</div>'; return; }

        headerEl.innerHTML = `<strong>${bsMonthNames[st.viewMonth]} ${bsYear} BS</strong>`;

        // Find weekday of 1st of this BS month
        const firstAD = bsToAD_convert(bsYear, bsMonth, 1);
        const firstDay = firstAD ? firstAD.getDay() : 0;
        const daysInMonth = bsData[bsYear][bsMonth - 1];
        const todayBS = adToBS_convert(new Date());
        let html = '';
        for (let i = 0; i < firstDay; i++) html += '<div class="nep-day empty"></div>';
        for (let d = 1; d <= daysInMonth; d++) {
            const adEquiv = bsToAD_convert(bsYear, bsMonth, d);
            const iso = adEquiv ? toISODate(adEquiv) : '';
            const isToday    = todayBS && todayBS.year===bsYear && todayBS.month===bsMonth && todayBS.day===d;
            const isSelected = st.selectedAD && iso && toISODate(st.selectedAD) === iso;
            html += `<div class="nep-day${isToday?' today':''}${isSelected?' selected':''}"
                          onclick="${iso ? `selectDate('${suffix}','${iso}')` : ''}">${d}</div>`;
        }
        daysEl.innerHTML = html;
    }
}

// ── Select a date ──
function selectDate(suffix, iso) {
    const st = pickerState[suffix];
    const adDate = new Date(iso + 'T00:00:00');
    st.selectedAD = adDate;

    // Update hidden field
    const hiddenId = suffix === 'From' ? 'travelDateFrom' : 'travelDateTo';
    document.getElementById(hiddenId).value = iso;

    // Update display inputs
    const adFormatted = adDate.toLocaleDateString('en-GB', {day:'2-digit', month:'short', year:'numeric'});
    const bs = adToBS_convert(adDate);
    const bsFormatted = bs ? `${bs.year}/${padZ(bs.month)}/${padZ(bs.day)}` : '—';

    document.getElementById('display'   + suffix).value = adFormatted;
    document.getElementById('displayBS' + suffix).value = bsFormatted + (bs ? ` ${bsMonthNames[bs.month-1]}` : '');

    // Sync bar
    const syncBar  = document.getElementById('syncBar'  + suffix);
    const syncText = document.getElementById('syncText' + suffix);
    syncBar.classList.remove('empty');
    if (bs) {
        syncText.innerHTML = `AD: ${adFormatted} &nbsp;↔&nbsp; BS: ${bsFormatted} (${bsMonthNames[bs.month-1]})`;
    }

    // Re-render to show selection highlight
    renderCalendar(suffix);

    // Close popup after short delay
    setTimeout(() => closePicker(suffix), 180);

    calculateDays();
}

// ── Calculate no. of days ──
function calculateDays() {
    const from = document.getElementById('travelDateFrom').value;
    const to   = document.getElementById('travelDateTo').value;
    if (from && to) {
        const start = new Date(from), end = new Date(to);
        if (end >= start) {
            $('#noOfDays').val(Math.ceil(Math.abs(end - start) / 86400000) + 1);
        } else {
            $('#noOfDays').val('');
        }
    }
}

// ════════════════════════════════════════════════
//  APPROVAL CHAIN
// ════════════════════════════════════════════════
const employeeData = <?= json_encode($employeeData) ?>;

function getApprovalChain(level, brCode, requestType = 'Normal') {
    requestType = (requestType === 'Claim') ? 'Claim' : 'Normal';
    if (requestType === 'Claim') { return brCode !== '100' ? [] : ['CD','HR','CEO/DCEO']; }
    if (brCode === '100' && level === 'ST') return ['DH','HR','CEO/DCEO'];
    if (level === 'CD')   return ['CD','HR','CEO/DCEO'];
    if (level === 'DH')   return ['HR','CEO/DCEO'];
    if (level === 'ST')   return ['PH','NSM','HR','CEO/DCEO'];
    if (level === 'OI')   return ['PH','NSM','HR','CEO/DCEO'];
    if (level === 'PH')   return ['NSM','HR','CEO/DCEO'];
    if (level === 'HR')   return ['CEO/DCEO'];
    if (level === 'CEO')  return ['HR','CEO/DCEO'];
    if (level === 'DCEO') return ['HR','CEO/DCEO'];
    return ['PH','NSM','HR','CEO/DCEO'];
}

function handleRequestTypeChange() {
    const brCode = document.getElementById('fieldBrCode').value;
    const level  = document.getElementById('fieldLevel').value;
    const requestTypeSelect = document.getElementById('requestType');
    const claimOption    = document.getElementById('claimOption');
    const claimHelpText  = document.getElementById('claimHelpText');

    if (brCode === '100') {
        claimOption.style.display = ''; claimOption.disabled = false;
        claimHelpText.style.display = 'block';
    } else {
        requestTypeSelect.value = 'Normal';
        claimOption.style.display = 'none'; claimOption.disabled = true;
        claimHelpText.style.display = 'none';
    }

    const chain = getApprovalChain(level, brCode, requestTypeSelect.value);
    const firstLevel = chain[0] || '';
    if (chain.length > 0 && level && brCode) {
        document.getElementById('approvalStageText').innerHTML =
            `Will route to: <strong>${firstLevel}</strong> → ${chain.slice(1).join(' → ')}`;
        document.getElementById('approvalStageContainer').style.display = 'block';
    } else {
        document.getElementById('approvalStageContainer').style.display = 'none';
    }
    populateApprovers(firstLevel);
}

function populateApprovers(approvalLevel) {
    const approverSelect = $('#approverSelect');
    approverSelect.empty().append(new Option('Select Approver','',true,true));
    if (!approvalLevel) return;
    for (const empID in employeeData) {
        const emp = employeeData[empID];
        if (emp.level === approvalLevel && emp.employeeEmail)
            approverSelect.append(new Option(`${emp.employeeName} (${emp.employeeEmail})`, emp.employeeEmail));
    }
    approverSelect.trigger('change');
}

function updateTadaPreview(employeeName, brCode) {
    const tadaNoText = document.getElementById('tadaNoText');
    if (!employeeName || !brCode) { tadaNoText.textContent = 'Select an employee'; return; }
    tadaNoText.textContent = 'Loading...';
    fetch('get_tada_preview.php?employeeName=' + encodeURIComponent(employeeName) +
          '&brCode=' + encodeURIComponent(brCode))
        .then(r => r.text())
        .then(no => { tadaNoText.textContent = no.trim(); })
        .catch(() => { tadaNoText.textContent = 'Preview unavailable'; });
}

function fillEmployeeDetails(empID) {
    if (empID && employeeData[empID]) {
        const emp = employeeData[empID];
        document.getElementById('fieldEmployeeName').value  = emp.employeeName    || '';
        document.getElementById('fieldEmployeeEmail').value = emp.employeeEmail   || '';
        document.getElementById('fieldDesignation').value   = emp.designationName || emp.designationId || '';
        document.getElementById('fieldDesignationId').value = emp.designationId   || '';
        document.getElementById('fieldLevel').value         = emp.level           || '';
        document.getElementById('fieldBrCode').value        = emp.BrCode          || '';
        if (emp.branch_name) $('select[name="department"]').val(emp.branch_name).trigger('change');
        else                 $('select[name="department"]').val('').trigger('change');
        handleRequestTypeChange();
        updateTadaPreview(emp.employeeName || '', emp.BrCode || '');
    } else {
        ['fieldEmployeeName','fieldEmployeeEmail','fieldDesignation','fieldDesignationId','fieldLevel','fieldBrCode']
            .forEach(id => document.getElementById(id).value = '');
        $('select[name="department"]').val('').trigger('change');
        document.getElementById('approvalStageContainer').style.display = 'none';
        document.getElementById('tadaNoText').textContent = 'Select an employee';
    }
}

$(document).ready(function () {
    $('.select2').select2({ placeholder: "Select Option", allowClear: true, width: '100%' });
    $('.select2-emp').select2({ placeholder: "Select Employee ID", allowClear: true, width: '100%' })
        .on('select2:select',   e => fillEmployeeDetails(e.params.data.id))
        .on('select2:unselect', () => fillEmployeeDetails(''));

    $('#travelForm').on('submit', function(e) {
        if (!$('#travelDateFrom').val()) { alert('Please select a Start Date.'); e.preventDefault(); return false; }
        if (!$('#travelDateTo').val())   { alert('Please select an End Date.');  e.preventDefault(); return false; }
    });
});
</script>
</body>
</html>