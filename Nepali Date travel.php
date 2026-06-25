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
    //if ($level === 'ST') return ['BM', 'PH', 'NSM', 'HR', 'CEO_OR_DCEO'];
	if ($level === 'ST') return ['PH', 'NSM', 'HR', 'CEO_OR_DCEO'];
    if ($level === 'OI') return ['PH', 'NSM', 'HR', 'CEO_OR_DCEO'];
	if ($level === 'PH') return ['NSM', 'HR', 'CEO_OR_DCEO'];
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
          WHERE level = ?
            AND employeeEmail IS NOT NULL
            AND employeeEmail != ''"
    );
    $stmt->bind_param("s", $level);
    $stmt->execute();
    $result     = $stmt->get_result();
    $recipients = [];
    while ($row = $result->fetch_assoc()) {
        $recipients[] = $row;
    }
    return $recipients;
}

function stageLabel($stage) {
    $labels = [
        'DH'          => 'Department Head',
        'BM'          => 'Branch Manager',
        'PH'          => 'Province Head',
        'CD'          => 'Claim Head',
        'NSM'         => 'NSM',
        'HR'          => 'Human Resource',
        'DCEO' 		  => 'DCEO',
		'CEO' 		  => 'CEO',
		'CEO_OR_DCEO' => 'CEO / DCEO',
    ];
    return $labels[$stage] ?? $stage;
}

function generateTravelOrderNo($conn, $brCode, $employeeName) {
    $firstName = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', explode(' ', trim($employeeName))[0]));
    if (empty($firstName)) $firstName = 'EMP';

    $prefix = 'TADA.' . $brCode . '.' . $firstName . '.';

    $stmt = $conn->prepare(
        "SELECT travel_order_no FROM travel_orders
          WHERE travel_order_no LIKE ?
          ORDER BY id DESC LIMIT 1"
    );
    $like = $prefix . '%';
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($lastNo);
        $stmt->fetch();
        $parts   = explode('.', $lastNo);
        $newNum  = (int) end($parts) + 1;
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

    // Filter to the selected approver if provided
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

        // ==================== DOCUMENT UPLOAD ====================
        $documentPath = '';
        if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
            $file         = $_FILES['document'];
            $maxSize      = 5 * 1024 * 1024;
            $allowedTypes = [
                'application/pdf',
                'image/jpeg',
                'image/png',
                //'application/msword',
                //'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ];

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
            // Store the selected approver's email so view page can filter by it
            $assignedApproverEmail = !empty($firstApprovers[0]['employeeEmail'])
                                     ? $firstApprovers[0]['employeeEmail']
                                     : '';

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
                "sssssssssssissssssssss",
                $travel_order_no,
                $empID,
                $BrCode,
                $employeeName,
                $employeeEmail,
                $designation,
                $department,
                $level,
                $travelFrom,
                $kilometer,
                $travelDateFrom,
                $travelDateTo,
                $noOfDays,
                $destination,
                $purpose,
                $modeOfTransport,
                $estimatedCost,
                $requestType,
                $firstApprovalLevel,
                $assignedApproverEmail,
                $documentPath,
                $now
            );

            if ($stmt->execute()) {
                $approvalChain  = getApprovalChain($level, $BrCode, $requestType);
                $nextStageLabel = isset($approvalChain[1]) ? stageLabel($approvalChain[1]) : 'Final Approval';

                foreach ($firstApprovers as $approver) {
                    $to            = $approver['employeeEmail'];
                    $recipientName = $approver['employeeName'];
                    $subject       = "Action Required: " . stageLabel($firstApprovalLevel) . " Approval – Travel Order {$travel_order_no}";

                    $emailBody = "
<!DOCTYPE html>
<html lang=\"en\">
<head>
  <meta charset=\"UTF-8\">
  <meta name=\"viewport\" content=\"width=device-width,initial-scale=1.0\">
  <title>{$subject}</title>
</head>
<body style=\"margin:0;padding:0;background:#f1f5f9;font-family:'Segoe UI',Helvetica,Arial,sans-serif;\">
  <table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\"
         style=\"background:#f1f5f9;padding:36px 0;\">
    <tr>
      <td align=\"center\">
        <table width=\"600\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\"
               style=\"max-width:600px;width:100%;background:#ffffff;
                      border-radius:12px;overflow:hidden;
                      box-shadow:0 4px 24px rgba(0,0,0,0.09);\">

          <!-- Header -->
          <tr>
            <td style=\"background:#3b82f6;padding:38px 40px;text-align:center;\">
              <p style=\"margin:0 0 6px;font-size:11px;color:rgba(255,255,255,0.7);
                         letter-spacing:2px;text-transform:uppercase;\">
                Nepal Life Insurance Co. Ltd.
              </p>
              <h1 style=\"margin:0 0 8px;font-size:26px;font-weight:700;
                          color:#ffffff;line-height:1.25;\">
                Travel Order Approval Required
              </h1>
              <p style=\"margin:0;font-size:14px;color:rgba(255,255,255,0.88);
                         background:rgba(255,255,255,0.15);
                         display:inline-block;padding:4px 16px;border-radius:20px;\">
                " . stageLabel($firstApprovalLevel) . " Level &nbsp;⏳&nbsp; Pending
              </p>
            </td>
          </tr>

          <!-- Body -->
          <tr>
            <td style=\"padding:36px 40px;\">
              <p style=\"margin:0 0 8px;font-size:16px;color:#1f2937;font-weight:600;\">
                Dear {$recipientName},
              </p>
              <p style=\"margin:0 0 28px;font-size:15px;color:#4b5563;line-height:1.65;\">
                A new travel order request requires your approval at the
                <strong>" . stageLabel($firstApprovalLevel) . " level</strong>.
              </p>

              <table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\"
                     style=\"border:1px solid #e2e8f0;border-radius:10px;
                            overflow:hidden;margin-bottom:24px;\">
                <tr>
                  <td colspan=\"2\" style=\"padding:14px 16px 10px;background:#f8fafc;border-bottom:1px solid #e2e8f0;\">
                    <p style=\"margin:0;font-size:11px;font-weight:700;color:#6b7280;letter-spacing:1.2px;text-transform:uppercase;\">Travel Order Details</p>
                  </td>
                </tr>
                <tr>
                  <td style=\"padding:10px 16px;border-bottom:1px solid #e2e8f0;font-size:12px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;width:38%;background:#eff6ff;\">TADA No</td>
                  <td style=\"padding:10px 16px;border-bottom:1px solid #e2e8f0;font-size:14px;color:#1f2937;font-weight:600;background:#eff6ff;\">{$travel_order_no}</td>
                </tr>
                <tr>
                  <td style=\"padding:10px 16px;border-bottom:1px solid #e2e8f0;font-size:12px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;\">Employee Name</td>
                  <td style=\"padding:10px 16px;border-bottom:1px solid #e2e8f0;font-size:14px;color:#1f2937;font-weight:500;\">{$employeeName}</td>
                </tr>
                <tr>
                  <td style=\"padding:10px 16px;border-bottom:1px solid #e2e8f0;font-size:12px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;background:#f8fafc;\">Employee ID</td>
                  <td style=\"padding:10px 16px;border-bottom:1px solid #e2e8f0;font-size:14px;color:#1f2937;font-weight:500;background:#f8fafc;\">{$empID}</td>
                </tr>
                <tr>
                  <td style=\"padding:10px 16px;border-bottom:1px solid #e2e8f0;font-size:12px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;\">Branch Code</td>
                  <td style=\"padding:10px 16px;border-bottom:1px solid #e2e8f0;font-size:14px;color:#1f2937;font-weight:500;\">{$BrCode}</td>
                </tr>
                <tr>
                  <td style=\"padding:10px 16px;border-bottom:1px solid #e2e8f0;font-size:12px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;background:#f8fafc;\">Department</td>
                  <td style=\"padding:10px 16px;border-bottom:1px solid #e2e8f0;font-size:14px;color:#1f2937;font-weight:500;background:#f8fafc;\">{$department}</td>
                </tr>
                <tr>
                  <td style=\"padding:10px 16px;border-bottom:1px solid #e2e8f0;font-size:12px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;\">Travel From</td>
                  <td style=\"padding:10px 16px;border-bottom:1px solid #e2e8f0;font-size:14px;color:#1f2937;font-weight:500;\">{$travelFrom}</td>
                </tr>
                <tr>
                  <td style=\"padding:10px 16px;border-bottom:1px solid #e2e8f0;font-size:12px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;background:#f8fafc;\">Destination</td>
                  <td style=\"padding:10px 16px;border-bottom:1px solid #e2e8f0;font-size:14px;color:#1f2937;font-weight:500;background:#f8fafc;\">{$destination}</td>
                </tr>
                <tr>
                  <td style=\"padding:10px 16px;border-bottom:1px solid #e2e8f0;font-size:12px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;\">Travel Dates</td>
                  <td style=\"padding:10px 16px;border-bottom:1px solid #e2e8f0;font-size:14px;color:#1f2937;font-weight:500;\">{$travelDateFrom} to {$travelDateTo}</td>
                </tr>
                <tr>
                  <td style=\"padding:10px 16px;border-bottom:1px solid #e2e8f0;font-size:12px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;background:#f8fafc;\">Kilometer</td>
                  <td style=\"padding:10px 16px;border-bottom:1px solid #e2e8f0;font-size:14px;color:#1f2937;font-weight:500;background:#f8fafc;\">{$kilometer}</td>
                </tr>
                <tr>
                  <td style=\"padding:10px 16px;border-bottom:1px solid #e2e8f0;font-size:12px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;\">Purpose</td>
                  <td style=\"padding:10px 16px;border-bottom:1px solid #e2e8f0;font-size:14px;color:#1f2937;font-weight:500;\">{$purpose}</td>
                </tr>
                <tr>
                  <td style=\"padding:10px 16px;font-size:12px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;background:#f8fafc;\">Advance Amount</td>
                  <td style=\"padding:10px 16px;font-size:14px;color:#1f2937;font-weight:600;background:#f8fafc;\">Rs. " . number_format((float)$estimatedCost, 2) . "</td>
                </tr>
              </table>

              <table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\"
                     style=\"background:#fef3c7;border:1px solid #fcd34d;border-radius:8px;margin-bottom:28px;\">
                <tr>
                  <td style=\"padding:14px 18px;\">
                    <p style=\"margin:0;font-size:14px;color:#92400e;line-height:1.5;\">
                      ⏳ &nbsp;<strong>Action Required:</strong>
                      Please review and approve this travel request at your earliest convenience.
					  <br><a href='https://monitor.nepallife.com.np/hr'> Click on the link for the travel Approval </a>
                    </p>
                  </td>
                </tr>
              </table>

              <p style=\"margin:0;font-size:13px;color:#9ca3af;line-height:1.7;\">
                
				Please log in to the <strong style=\"color:#374151;\">Nepal Life Travel Management System</strong>
                to review and take action on this request.
              </p>
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style=\"background:#f8fafc;border-top:1px solid #e2e8f0;padding:22px 40px;text-align:center;\">
              <p style=\"margin:0 0 4px;font-size:12px;color:#9ca3af;\">
                This is an automated notification from the Nepal Life Travel System.
              </p>
              <p style=\"margin:0;font-size:11px;color:#cbd5e1;\">
                Please do not reply directly to this email.
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>";

                    $headers  = "MIME-Version: 1.0\r\n";
                    $headers .= "Content-type:text/html;charset=UTF-8\r\n";
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
    <link rel="stylesheet" href="https://unpkg.com/nepali-date-picker@2.0.2/dist/nepaliDatePicker.min.css">
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
            top: 0; left: 0;
            width: 100%; height: 6px;
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
            bottom: 0; left: 0;
            width: 60px; height: 4px;
            background: #3b82f6;
            border-radius: 2px;
        }
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
        .order-no-badge i { font-size: 1.1rem; color: #3b82f6; }
        .approval-stage-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border: 1.5px solid #fcd34d;
            border-radius: 10px;
            padding: 0.6rem 1.2rem;
            font-size: 0.9rem;
            font-weight: 600;
            color: #92400e;
            letter-spacing: 0.5px;
            margin-top: 1rem;
        }
        .form-label {
            font-weight: 600;
            color: #475569;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
        }
        .form-label i { color: #3b82f6; margin-right: 8px; font-size: 1.1rem; }
        .form-control, .form-select {
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
            background-color: #f8fafc;
        }
        .form-control:focus, .form-select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59,130,246,0.1);
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
            top: 50%; left: 50%;
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
            box-shadow: 0 10px 15px -3px rgba(59,130,246,0.4);
            transition: all 0.3s ease;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 25px -5px rgba(59,130,246,0.5);
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
        }
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
        /* ── AD/BS Date Toggle Styles ── */
        .date-system-toggle {
            display: inline-flex;
            align-items: center;
            gap: 0;
            background: #e2e8f0;
            border-radius: 10px;
            padding: 3px;
            position: relative;
        }
        .date-system-toggle .toggle-btn {
            padding: 6px 18px;
            border: none;
            background: transparent;
            font-family: 'Outfit', sans-serif;
            font-weight: 600;
            font-size: 0.85rem;
            color: #64748b;
            cursor: pointer;
            border-radius: 8px;
            transition: all 0.3s ease;
            letter-spacing: 0.5px;
            position: relative;
            z-index: 1;
        }
        .date-system-toggle .toggle-btn.active {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: #fff;
            box-shadow: 0 2px 8px rgba(59,130,246,0.35);
        }
        .date-system-toggle .toggle-btn:not(.active):hover {
            background: rgba(255,255,255,0.6);
            color: #1e293b;
        }
        .date-mode-label {
            font-size: 0.78rem;
            color: #94a3b8;
            font-weight: 500;
            margin-left: 10px;
            letter-spacing: 0.3px;
        }
        .nepali-date-field {
            cursor: pointer;
            background-color: #f8fafc !important;
        }
        .nepali-date-field:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59,130,246,0.1);
            background-color: white !important;
        }
        /* Override nepali date picker z-index to appear above modals */
        .nepali-date-picker { z-index: 9999 !important; }
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
                    <!-- TADA No: placeholder, updated dynamically via JS -->
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
                            
							<!--<a href="travel.php<?//= $shownOrderNo ? '?travel_order_no=' . urlencode($shownOrderNo) : '' ?>" -->
							<a href="travel.php"
                               class="btn btn-primary btn-sm px-4 fw-600">
								<!-- <a href="Apply_expense.php<?//= $shownOrderNo ? '?travel_order_no=' . urlencode($shownOrderNo) : '' ?>"
                               class="btn btn-primary btn-sm px-4 fw-600"> -->
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
                // ── Build employeeData: keep both PostId and PostName ──
					$empResult = $conn->query("
						SELECT e.EmpID, e.employeeName, e.employeeEmail, e.BrCode, e.level,
							   e.designation  AS designationId,
							   COALESCE(p.PostName, e.designation) AS designationName,
							   b.branch_name
						FROM employees e
						LEFT JOIN branches b ON e.BrCode  = b.BrCode
						LEFT JOIN posts    p ON e.designation = p.PostId
					");
					$employeeData = [];
					while ($row = $empResult->fetch_assoc()) {
						$employeeData[$row['EmpID']] = $row;
					}

                $branchResult = $conn->query("SELECT branch_name FROM branches ORDER BY branch_name ASC");
                $branchData   = [];
                while ($row = $branchResult->fetch_assoc()) {
                    $branchData[] = $row['branch_name'];
                }
                ?>

                <form method="POST" enctype="multipart/form-data" id="travelForm">
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
							<!-- Hidden: submits the actual PostId value to DB -->
							<!-- Hidden: retains designation ID if needed -->
							<input type="hidden" name="designationId" id="fieldDesignationId">
							<!-- Visible: shows PostName and submits it -->
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
                                    <option value="<?= htmlspecialchars($branchName) ?>">
                                        <?= htmlspecialchars($branchName) ?>
                                    </option>
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
                            <div class="form-text mt-2">
                                Select the specific person to approve your request.
                            </div>
                        </div>
                    </div>

                    <!-- Approval Stage Display -->
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
                            <label class="form-label"><i class="bi bi-geo-fill"></i> Kilometer</label>
                            <input type="number" name="kilometer" class="form-control" placeholder="Kilometer Covered" required>
                        </div>
                    </div>

                    <!-- ── Date System Toggle (AD / BS) ── -->
                    <div class="row g-4 mb-2">
                        <div class="col-12">
                            <label class="form-label"><i class="bi bi-calendar3"></i> Date System</label>
                            <div class="d-flex align-items-center">
                                <div class="date-system-toggle" id="dateSystemToggle">
                                    <button type="button" class="toggle-btn active" data-mode="AD">AD</button>
                                    <button type="button" class="toggle-btn" data-mode="BS">BS</button>
                                </div>
                                <span class="date-mode-label" id="dateModeLabel">English (Gregorian) dates</span>
                            </div>
                            <input type="hidden" name="dateSystem" id="dateSystem" value="AD">
                        </div>
                    </div>

                    <!-- Hidden inputs that always carry the final AD date values for form submission -->
                    <input type="hidden" name="travelDateFrom" id="hiddenDateFrom">
                    <input type="hidden" name="travelDateTo"   id="hiddenDateTo">

                    <div class="row g-4 mb-2">
                        <!-- AD Date Inputs (visible, no name attr — hidden inputs above carry the values) -->
                        <div class="col-md-3" id="adStartDateCol">
                            <label class="form-label"><i class="bi bi-calendar-event"></i> Start Date (AD)</label>
                            <input type="date" id="travelDateFrom" class="form-control" required>
                        </div>
                        <div class="col-md-3" id="adEndDateCol">
                            <label class="form-label"><i class="bi bi-calendar-check"></i> End Date (AD)</label>
                            <input type="date" id="travelDateTo" class="form-control" required>
                        </div>

                        <!-- BS Date Inputs (hidden by default) -->
                        <div class="col-md-3" id="bsStartDateCol" style="display:none;">
                            <label class="form-label"><i class="bi bi-calendar-event"></i> Start Date (BS)</label>
                            <input type="text" id="bsTravelDateFrom" class="form-control nepali-date-field" placeholder="YYYY-MM-DD" autocomplete="off" readonly>
                        </div>
                        <div class="col-md-3" id="bsEndDateCol" style="display:none;">
                            <label class="form-label"><i class="bi bi-calendar-check"></i> End Date (BS)</label>
                            <input type="text" id="bsTravelDateTo" class="form-control nepali-date-field" placeholder="YYYY-MM-DD" autocomplete="off" readonly>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label"><i class="bi bi-clock-history"></i> No of Days</label>
                            <input type="number" name="noOfDays" id="noOfDays" class="form-control" placeholder="0" readonly>
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
<script src="https://unpkg.com/nepali-date-picker@2.0.2/dist/nepaliDatePicker.min.js"></script>
<script>
// employeeData: keyed by EmpID; designation = PostName (human-readable)
const employeeData = <?= json_encode($employeeData) ?>;

// ============ APPROVAL CHAIN ============
// ============ APPROVAL CHAIN ============
function getApprovalChain(level, brCode, requestType = 'Normal') {
    requestType = (requestType === 'Claim') ? 'Claim' : 'Normal';

    if (requestType === 'Claim') {
        if (brCode !== '100') return [];
        return ['CD', 'HR', 'CEO/DCEO'];
    }

    if (brCode === '100' && level === 'ST') return ['DH', 'HR', 'CEO/DCEO'];
    if (level === 'CD') return ['CD', 'HR', 'CEO/DCEO'];
    if (level === 'DH') return ['HR', 'CEO/DCEO'];
    //if (level === 'ST') return ['BM', 'PH', 'NSM', 'HR', 'CEO/DCEO'];
	if (level === 'ST') return ['PH', 'NSM', 'HR', 'CEO/DCEO'];
    if (level === 'OI') return ['PH', 'NSM', 'HR', 'CEO/DCEO'];
	if (level === 'PH') return ['NSM', 'HR', 'CEO/DCEO'];
	if (level === 'CEO') return ['HR', 'CEO/DCEO'];	
	if (level === 'DCEO') return ['HR', 'CEO/DCEO'];
	

    return ['PH', 'NSM', 'HR', 'CEO/DCEO'];
}

function handleRequestTypeChange() {
    const brCode           = document.getElementById('fieldBrCode').value;
    const level            = document.getElementById('fieldLevel').value;
    const requestTypeSelect = document.getElementById('requestType');
    const claimOption      = document.getElementById('claimOption');
    const claimHelpText    = document.getElementById('claimHelpText');

    if (brCode === '100') {
        claimOption.style.display = '';
        claimOption.disabled      = false;
        claimHelpText.style.display = 'block';
    } else {
        requestTypeSelect.value     = 'Normal';
        claimOption.style.display   = 'none';
        claimOption.disabled        = true;
        claimHelpText.style.display = 'none';
    }

    const chain = getApprovalChain(level, brCode, requestTypeSelect.value);
    const firstLevel = chain[0] || '';

    if (chain.length > 0 && level && brCode) {
        const routeText = `Will route to: <strong>${firstLevel}</strong> → ${chain.slice(1).join(' → ')}`;
        document.getElementById('approvalStageText').innerHTML    = routeText;
        document.getElementById('approvalStageContainer').style.display = 'block';
    } else {
        document.getElementById('approvalStageContainer').style.display = 'none';
    }

    populateApprovers(firstLevel);
}

function populateApprovers(approvalLevel) {
    const approverSelect = $('#approverSelect');
    approverSelect.empty();
    approverSelect.append(new Option('Select Approver', '', true, true));
    
    if (!approvalLevel) return;

    let found = false;
    for (const empID in employeeData) {
        const emp = employeeData[empID];
        // Note: For CEO_OR_DCEO in PHP logic, we might need matching. 
        // Here we just match the exact level.
        if (emp.level === approvalLevel && emp.employeeEmail) {
            const text = `${emp.employeeName} (${emp.employeeEmail})`;
            approverSelect.append(new Option(text, emp.employeeEmail, false, false));
            found = true;
        }
    }
    approverSelect.trigger('change');
}


// ============ TADA PREVIEW via AJAX ============
function updateTadaPreview(employeeName, brCode) {
    const tadaNoText = document.getElementById('tadaNoText');
    if (!employeeName || !brCode) {
        tadaNoText.textContent = 'Select an employee';
        return;
    }
    tadaNoText.textContent = 'Loading...';
    fetch('get_tada_preview.php?employeeName=' + encodeURIComponent(employeeName) +
          '&brCode=' + encodeURIComponent(brCode))
        .then(r => r.text())
        .then(no => { tadaNoText.textContent = no.trim(); })
        .catch(() => { tadaNoText.textContent = 'Preview unavailable'; });
}

// ============ FILL EMPLOYEE DETAILS ============
function fillEmployeeDetails(empID) {
    if (empID && employeeData[empID]) {
        const emp = employeeData[empID];

        document.getElementById('fieldEmployeeName').value  = emp.employeeName    || '';
        document.getElementById('fieldEmployeeEmail').value = emp.employeeEmail   || '';

        // FIX: show PostName in visible field, submit designationId as value
        document.getElementById('fieldDesignation').value   = emp.designationName || emp.designationId || '';
        document.getElementById('fieldDesignationId').value = emp.designationId   || '';

        document.getElementById('fieldLevel').value         = emp.level   || '';
        document.getElementById('fieldBrCode').value        = emp.BrCode  || '';

        if (emp.branch_name) {
            $('select[name="department"]').val(emp.branch_name).trigger('change');
        } else {
            $('select[name="department"]').val('').trigger('change');
        }

        handleRequestTypeChange();
        updateTadaPreview(emp.employeeName || '', emp.BrCode || '');

    } else {
        document.getElementById('fieldEmployeeName').value  = '';
        document.getElementById('fieldEmployeeEmail').value = '';
        document.getElementById('fieldDesignation').value   = '';
        document.getElementById('fieldDesignationId').value = '';
        document.getElementById('fieldLevel').value         = '';
        document.getElementById('fieldBrCode').value        = '';
        $('select[name="department"]').val('').trigger('change');
        document.getElementById('approvalStageContainer').style.display = 'none';
        document.getElementById('tadaNoText').textContent = 'Select an employee';
    }
}

$(document).ready(function () {
    $('.select2').select2({ placeholder: "Select Option", allowClear: true, width: '100%' });

    $('.select2-emp').select2({
        placeholder: "Search by Emp ID or Name...",
        allowClear: true,
        width: '100%',
        matcher: function(params, data) {
            // If no search term, show all
            if (!params.term || params.term.trim() === '') return data;
            var term = params.term.trim().toLowerCase();
            var empId   = (data.id   || '').toLowerCase();
            var empName = ($(data.element).data('name') || '').toLowerCase();
            if (empId.indexOf(term) > -1 || empName.indexOf(term) > -1) {
                return data;
            }
            return null;
        }
    }).on('select2:select', function (e) {
        fillEmployeeDetails(e.params.data.id);
    }).on('select2:unselect', function () {
        fillEmployeeDetails('');
    });

    // ============ CALCULATE DAYS ============
    // Always reads from the hidden AD inputs which are the source of truth
    function calculateDays() {
        const start = $('#hiddenDateFrom').val();
        const end   = $('#hiddenDateTo').val();
        if (start && end) {
            const startDate = new Date(start);
            const endDate   = new Date(end);
            if (endDate >= startDate) {
                const diffDays = Math.ceil(Math.abs(endDate - startDate) / (1000 * 60 * 60 * 24)) + 1;
                $('#noOfDays').val(diffDays);
            } else {
                $('#noOfDays').val('');
            }
        }
    }
    // When AD visible inputs change, sync to hidden inputs
    $('#travelDateFrom, #travelDateTo').on('change', function() {
        $('#hiddenDateFrom').val($('#travelDateFrom').val());
        $('#hiddenDateTo').val($('#travelDateTo').val());
        calculateDays();
    });

    // ============ AD/BS DATE TOGGLE ============
    var currentDateMode = 'AD';

    // Toggle button click
    $('#dateSystemToggle .toggle-btn').on('click', function() {
        var mode = $(this).data('mode');
        if (mode === currentDateMode) return;

        currentDateMode = mode;
        $('#dateSystem').val(mode);

        // Update toggle button styling
        $('#dateSystemToggle .toggle-btn').removeClass('active');
        $(this).addClass('active');

        if (mode === 'BS') {
            // Switch to BS mode
            $('#dateModeLabel').text('Nepali (Bikram Sambat) dates');
            $('#adStartDateCol, #adEndDateCol').hide();
            $('#bsStartDateCol, #bsEndDateCol').show();

            // Remove required from AD fields, they won't be submitted
            $('#travelDateFrom').removeAttr('required');
            $('#travelDateTo').removeAttr('required');

            // If AD fields had values, convert them to BS and prefill
            var adStart = $('#hiddenDateFrom').val();
            var adEnd   = $('#hiddenDateTo').val();
            if (adStart) {
                var bsStart = adToBS(adStart);
                if (bsStart) $('#bsTravelDateFrom').val(bsStart);
            }
            if (adEnd) {
                var bsEnd = adToBS(adEnd);
                if (bsEnd) $('#bsTravelDateTo').val(bsEnd);
            }
        } else {
            // Switch to AD mode
            $('#dateModeLabel').text('English (Gregorian) dates');
            $('#adStartDateCol, #adEndDateCol').show();
            $('#bsStartDateCol, #bsEndDateCol').hide();

            // Restore required on AD fields
            $('#travelDateFrom').attr('required', 'required');
            $('#travelDateTo').attr('required', 'required');

            // If BS fields had values, the hidden AD fields should already be set
        }
    });

    // ============ NEPALI DATE PICKER INIT ============
    $('#bsTravelDateFrom').nepaliDatePicker({
        dateFormat: '%y-%m-%d',
        closeOnDateSelect: true,
        onChange: function() {
            setTimeout(function() {
                var bsVal = $('#bsTravelDateFrom').val();
                if (bsVal) {
                    var adDate = bsToAD(bsVal);
                    if (adDate) {
                        $('#hiddenDateFrom').val(adDate);
                        calculateDays();
                    }
                }
            }, 100);
        }
    });

    $('#bsTravelDateTo').nepaliDatePicker({
        dateFormat: '%y-%m-%d',
        closeOnDateSelect: true,
        onChange: function() {
            setTimeout(function() {
                var bsVal = $('#bsTravelDateTo').val();
                if (bsVal) {
                    var adDate = bsToAD(bsVal);
                    if (adDate) {
                        $('#hiddenDateTo').val(adDate);
                        calculateDays();
                    }
                }
            }, 100);
        }
    });

    // Also handle manual/direct value changes on BS fields
    $('#bsTravelDateFrom, #bsTravelDateTo').on('change', function() {
        var bsVal = $(this).val();
        if (!bsVal) return;
        var adDate = bsToAD(bsVal);
        if (this.id === 'bsTravelDateFrom' && adDate) {
            $('#hiddenDateFrom').val(adDate);
        } else if (this.id === 'bsTravelDateTo' && adDate) {
            $('#hiddenDateTo').val(adDate);
        }
        calculateDays();
    });

    // ============ BS/AD CONVERSION HELPERS ============
    // Uses the calendarFunctions object exposed by nepali-date-picker
    function bsToAD(bsDateStr) {
        // Expected format: YYYY-MM-DD (Nepali digits or English digits)
        try {
            var parts = nepaliToEnglishDigits(bsDateStr).split('-');
            if (parts.length !== 3) return null;
            var bsY = parseInt(parts[0], 10);
            var bsM = parseInt(parts[1], 10);
            var bsD = parseInt(parts[2], 10);
            if (isNaN(bsY) || isNaN(bsM) || isNaN(bsD)) return null;

            var adDate = calendarFunctions.getAdDateByBsDate(bsY, bsM, bsD);
            if (adDate && adDate instanceof Date && !isNaN(adDate)) {
                var y = adDate.getFullYear();
                var m = adDate.getMonth() + 1; // getMonth() is 0-indexed
                var d = adDate.getDate();
                return y + '-' + String(m).padStart(2, '0') + '-' + String(d).padStart(2, '0');
            }
            return null;
        } catch(e) {
            console.error('BS to AD conversion error:', e);
            return null;
        }
    }

    function adToBS(adDateStr) {
        try {
            var parts = adDateStr.split('-');
            if (parts.length !== 3) return null;
            var adY = parseInt(parts[0], 10);
            var adM = parseInt(parts[1], 10);
            var adD = parseInt(parts[2], 10);
            if (isNaN(adY) || isNaN(adM) || isNaN(adD)) return null;

            var bsObj = calendarFunctions.getBsDateByAdDate(adY, adM, adD);
            if (bsObj && bsObj.bsYear) {
                return bsObj.bsYear + '-' + String(bsObj.bsMonth).padStart(2, '0') + '-' + String(bsObj.bsDate).padStart(2, '0');
            }
            return null;
        } catch(e) {
            console.error('AD to BS conversion error:', e);
            return null;
        }
    }

    function nepaliToEnglishDigits(str) {
        var nepaliDigits = ['०','१','२','३','४','५','६','७','८','९'];
        var result = str;
        for (var i = 0; i < 10; i++) {
            result = result.replace(new RegExp(nepaliDigits[i], 'g'), i.toString());
        }
        return result;
    }

    // ============ FORM VALIDATION ============
    // Before submit: if BS mode, make sure hidden AD fields have values
    $('#travelForm').on('submit', function(e) {
        if (currentDateMode === 'BS') {
            var bsFrom = $('#bsTravelDateFrom').val();
            var bsTo   = $('#bsTravelDateTo').val();

            if (!bsFrom || !bsTo) {
                e.preventDefault();
                alert('Please select both Start Date and End Date in BS.');
                return false;
            }

            var adFrom = bsToAD(bsFrom);
            var adTo   = bsToAD(bsTo);

            if (!adFrom || !adTo) {
                e.preventDefault();
                alert('Could not convert BS dates to AD. Please check the dates.');
                return false;
            }

            // Set the hidden inputs that carry the form values
            $('#hiddenDateFrom').val(adFrom);
            $('#hiddenDateTo').val(adTo);
        } else {
            // AD mode: sync visible date inputs to hidden inputs before submit
            $('#hiddenDateFrom').val($('#travelDateFrom').val());
            $('#hiddenDateTo').val($('#travelDateTo').val());
        }
    });
});
</script>
</body>
</html>