<?php
require_once 'auth.php';
requireLogin();
require_once 'db.php';

// ─────────────────────────────────────────────
// Helper: Build one detail row for the email card
// ─────────────────────────────────────────────
function emailRow(string $label, string $value, string $stripeBg = ''): string {
    $bg = $stripeBg ? "background:{$stripeBg}0d;" : 'background:#ffffff;';
    return "
    <tr>
      <td style=\"{$bg}padding:10px 16px;border-bottom:1px solid #e2e8f0;font-size:12px;
                  color:#6b7280;font-weight:600;text-transform:uppercase;
                  letter-spacing:0.5px;width:38%;vertical-align:top;\">
        {$label}
      </td>
      <td style=\"{$bg}padding:10px 16px;border-bottom:1px solid #e2e8f0;font-size:14px;
                  color:#1f2937;font-weight:500;vertical-align:top;\">
        " . htmlspecialchars($value) . "
      </td>
    </tr>";
}

// ─────────────────────────────────────────────
// Helper: Send HTML email using PHP mail()
// Now accepts the full $order array for extra fields
// ─────────────────────────────────────────────
function sendApprovalEmail(
    string $toEmail,
    string $toName,
    string $subject,
    string $approvalStage,
    array  $order,
    string $approverName,
    string $remarks,
    string $nextStage
): void {

    // ── Colour palette per stage ──
    $stageColours = [
        'Province' => ['bg' => '#1a56db', 'badge_bg' => '#dbeafe', 'badge_text' => '#1e40af'],
        'NSM'      => ['bg' => '#0891b2', 'badge_bg' => '#cffafe', 'badge_text' => '#0e7490'],
        'HR'       => ['bg' => '#7c3aed', 'badge_bg' => '#ede9fe', 'badge_text' => '#5b21b6'],
        'CEO'      => ['bg' => '#059669', 'badge_bg' => '#d1fae5', 'badge_text' => '#065f46'],
    ];
    $c = $stageColours[$approvalStage] ?? $stageColours['Province'];

    // ── Pull all needed fields from $order ──
    $employeeName  = $order['employeeName']    ?? '';
    $travelOrderNo = $order['travel_order_no'] ?? '';
    $destination   = $order['destination']     ?? '';
    $travelDateFrom= $order['travelDateFrom']  ?? '';
    $travelDateTo  = $order['travelDateTo']    ?? '';
    $purpose       = $order['purpose']         ?? '';
    $branchCode    = $order['BrCode']      ?? '';   // Branch / dept code column
	$branchname    = $order['department']      ?? '';   // Branch / dept code column
	
	$modeoftravel    = $order['modeOfTransport']      ?? '';   // Branch / dept code column
	$kilometer    = $order['kilometer']      ?? '';   // Branch / dept code column
    
	
	$empCode       = $order['EmpID']           ?? '';
    $noOfDays      = $order['noOfDays']        ?? '';
    $place         = $order['travelFrom']     ?? '';   // place = destination; adjust if different column
    $estimatedCost = !empty($order['estimatedCost'])
                        ? 'Rs. ' . number_format((float)$order['estimatedCost'], 2)
                        : 'N/A';

    $travelPeriod  = $travelDateFrom . '  →  ' . $travelDateTo;

    $remarksHtml = trim($remarks) !== ''
        ? "<p style=\"margin:6px 0 0;font-size:13px;color:{$c['badge_text']};font-style:italic;\">
               &ldquo;" . htmlspecialchars($remarks) . "&rdquo;
           </p>"
        : '';

    // ── Build detail rows ──
    $detailRows  = emailRow('TADA No',         $travelOrderNo,  $c['bg']);
    $detailRows .= emailRow('Employee Name',   $employeeName);
    $detailRows .= emailRow('Employee Code',   $empCode,        $c['bg']);
    $detailRows .= emailRow('Branch Code',     $branchCode);
	$detailRows .= emailRow('Branch Name',     $branchname);
    $detailRows .= emailRow('Place',           $place,          $c['bg']);
    $detailRows .= emailRow('Destination',     $destination);
	
	$detailRows .= emailRow('Travel Mode',$modeoftravel, $c['bg']);
	$detailRows .= emailRow('kilometer',     $kilometer);
	
    $detailRows .= emailRow('Travel Date From',$travelDateFrom, $c['bg']);
    $detailRows .= emailRow('Travel Date To',  $travelDateTo);
    $detailRows .= emailRow('No. of Days',     $noOfDays,       $c['bg']);
    $detailRows .= emailRow('Purpose',         $purpose);
    $detailRows .= emailRow('Advance Amount',  $estimatedCost,  $c['bg']);

    // ── HTML body ──
    $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>{$subject}</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:'Segoe UI',Helvetica,Arial,sans-serif;">

  <table width="100%" cellpadding="0" cellspacing="0" border="0"
         style="background:#f1f5f9;padding:36px 0;">
    <tr>
      <td align="center">

        <!-- ░░ Outer card ░░ -->
        <table width="600" cellpadding="0" cellspacing="0" border="0"
               style="max-width:600px;width:100%;background:#ffffff;
                      border-radius:12px;overflow:hidden;
                      box-shadow:0 4px 24px rgba(0,0,0,0.09);">

          <!-- ▌Header ▌ -->
          <tr>
            <td style="background:{$c['bg']};padding:38px 40px;text-align:center;">
              <p style="margin:0 0 6px;font-size:11px;color:rgba(255,255,255,0.7);
                         letter-spacing:2px;text-transform:uppercase;">
                Nepal Life Insurance Co. Ltd.
              </p>
              <h1 style="margin:0 0 8px;font-size:26px;font-weight:700;
                          color:#ffffff;line-height:1.25;">
                Travel Order Approved
              </h1>
              <p style="margin:0;font-size:14px;color:rgba(255,255,255,0.88);
                         background:rgba(255,255,255,0.15);
                         display:inline-block;padding:4px 16px;border-radius:20px;">
                {$approvalStage} Level &nbsp;✔&nbsp; Completed
              </p>
            </td>
          </tr>

          <!-- ▌Body ▌ -->
          <tr>
            <td style="padding:36px 40px;">

              <!-- Greeting -->
              <p style="margin:0 0 8px;font-size:16px;color:#1f2937;font-weight:600;">
                Dear {$toName},
              </p>
              <p style="margin:0 0 28px;font-size:15px;color:#4b5563;line-height:1.65;">
                A travel order has been <strong>approved at the {$approvalStage} level</strong>
                and forwarded to you for the next action.
              </p>

              <!-- ░░ Travel Order Details card ░░ -->
              <table width="100%" cellpadding="0" cellspacing="0" border="0"
                     style="border:1px solid #e2e8f0;border-radius:10px;
                            overflow:hidden;margin-bottom:24px;">
                <tr>
                  <td colspan="2"
                      style="padding:14px 16px 10px;background:#f8fafc;
                             border-bottom:1px solid #e2e8f0;">
                    <p style="margin:0;font-size:11px;font-weight:700;color:#6b7280;
                               letter-spacing:1.2px;text-transform:uppercase;">
                      Travel Order Details
                    </p>
                  </td>
                </tr>
                {$detailRows}
              </table>

              <!-- ░░ Approved-by badge ░░ -->
              <table width="100%" cellpadding="0" cellspacing="0" border="0"
                     style="background:{$c['badge_bg']};
                            border-left:4px solid {$c['bg']};
                            border-radius:0 8px 8px 0;
                            margin-bottom:24px;">
                <tr>
                  <td style="padding:16px 20px;">
                    <p style="margin:0 0 4px;font-size:11px;font-weight:700;
                               color:{$c['badge_text']};letter-spacing:1.1px;
                               text-transform:uppercase;">
                      Approved by &mdash; {$approvalStage}
                    </p>
                    <p style="margin:0;font-size:15px;color:{$c['badge_text']};
                               font-weight:600;">
                      {$approverName}
                    </p>
                    {$remarksHtml}
                  </td>
                </tr>
              </table>

              <!-- ░░ Action-required banner ░░ -->
              <table width="100%" cellpadding="0" cellspacing="0" border="0"
                     style="background:#fffbeb;border:1px solid #fcd34d;
                            border-radius:8px;margin-bottom:28px;">
                <tr>
                  <td style="padding:14px 18px;">
                    <p style="margin:0;font-size:14px;color:#92400e;line-height:1.5;">
                      ⏳ &nbsp;<strong>Action Required:</strong>
                      Please review and complete the
                      <strong>{$nextStage} Approval</strong>
                      at your earliest convenience.
                    </p>
                  </td>
                </tr>
              </table>

              <p style="margin:0;font-size:13px;color:#9ca3af;line-height:1.7;">
                Please log in to the
                <strong style="color:#374151;">Nepal Life Travel Management System</strong>
                to take action on this request.
              </p>

            </td>
          </tr>

          <!-- ▌Footer ▌ -->
          <tr>
            <td style="background:#f8fafc;border-top:1px solid #e2e8f0;
                        padding:22px 40px;text-align:center;">
              <p style="margin:0 0 4px;font-size:12px;color:#9ca3af;">
                This is an automated notification from the Nepal Life Travel System.
              </p>
              <p style="margin:0;font-size:11px;color:#cbd5e1;">
                Please do not reply directly to this email.
              </p>
            </td>
          </tr>

        </table>
        <!-- /Outer card -->

      </td>
    </tr>
  </table>

</body>
</html>
HTML;

    // ── Plain-text fallback ──
    $plainText = "Dear {$toName},\n\n"
        . "A travel order for {$employeeName} (TADA No: {$travelOrderNo}) has been approved\n"
        . "at the {$approvalStage} level by {$approverName} and requires your {$nextStage} approval.\n\n"
        . "Employee Code  : {$empCode}\n"
        . "Branch Name    : {$branch_name}\n"
		. "branchCode    : {$BrCode}\n"
		. "Mode Of Transport    : {$modeOfTransport}\n"
        . "Place          : {$travelFrom}\n"
        . "Destination    : {$destination}\n"
        . "Travel Date From: {$travelDateFrom}\n"
        . "Travel Date To  : {$travelDateTo}\n"
        . "No. of Days    : {$noOfDays}\n"

        . "Kilometer Covered    : {$kilometer}\n"
		
        . "Purpose        : {$purpose}\n"
        . "Advance Amount : {$estimatedCost}\n"
        . (trim($remarks) !== '' ? "Remarks        : {$remarks}\n" : '')
        . "\nPlease log in to the Nepal Life Travel Management System to take action.\n\n"
        . "This is an automated message. Please do not reply.";

    // ── MIME boundary ──
    $boundary = '----=_Part_' . md5(uniqid((string)mt_rand(), true));

    $headers  = "From: Nepal Life Travel System <noreply@nepallife.com.np>\r\n";
    $headers .= "Reply-To: noreply@nepallife.com.np\r\n";
    $headers .= "To: =?UTF-8?B?" . base64_encode($toName) . "?= <{$toEmail}>\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

    $body  = "--{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode($plainText)) . "\r\n";

    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode($htmlBody)) . "\r\n";

    $body .= "--{$boundary}--";

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    $sent = mail($toEmail, $encodedSubject, $body, $headers);
    if (!$sent) {
        error_log("Travel System – mail() failed. To: {$toEmail} | Subject: {$subject}");
    }
}

// ─────────────────────────────────────────────
// Helper: fetch ALL employees by level
// ─────────────────────────────────────────────
function getRecipientsByLevel(mysqli $conn, string $level): array {
    $stmt = $conn->prepare(
        "SELECT employeeName, employeeEmail FROM employees
          WHERE level = ?
            AND employeeEmail IS NOT NULL
            AND employeeEmail != ''"
    );
    $stmt->bind_param("s", $level);
    $stmt->execute();
    $result = $stmt->get_result();
    $recipients = [];
    while ($row = $result->fetch_assoc()) {
        $recipients[] = $row;
    }
    return $recipients;
}

// ─────────────────────────────────────────────
// Helper: send individual email to every
// employee at the given level
// ─────────────────────────────────────────────
function notifyLevel(
    mysqli $conn,
    string $level,
    string $subject,
    string $approvalStage,
    array  $order,
    string $approverName,
    string $remarks,
    string $nextStage
): void {
    $recipients = getRecipientsByLevel($conn, $level);
    foreach ($recipients as $recipient) {
        sendApprovalEmail(
            $recipient['employeeEmail'],
            $recipient['employeeName'],
            $subject,
            $approvalStage,
            $order,
            $approverName,
            $remarks,
            $nextStage
        );
    }
}

// ─────────────────────────────────────────────
// Fetch the travel order
// ─────────────────────────────────────────────
$id = (int) ($_POST['id'] ?? $_POST['travel_order_id'] ?? 0);
if (!$id) {
    die("Invalid travel order ID.");
}

$stmt = $conn->prepare("SELECT * FROM travel_orders WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
if (!$order) {
    die("Travel order not found.");
}

$updatedBy = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'System';
$now       = date('Y-m-d H:i:s');

// ══════════════════════════════════════════════
// PROVINCE APPROVAL  →  email ALL NSM employees
// ══════════════════════════════════════════════
if (isset($_POST['approver_name'], $_POST['approver_email'])) {

    $approverName  = trim($_POST['approver_name']);
    $approverEmail = trim($_POST['approver_email']);
    $remarks       = trim($_POST['remarks'] ?? '');

    $stmt = $conn->prepare("
        UPDATE travel_orders
           SET approval_province_status  = 'Approved',
               province_approver_name    = ?,
               province_approver_email   = ?,
               approval_province_remarks = ?,
               last_updated_by           = ?,
               last_updated_at           = ?
         WHERE id = ?
    ");
    $stmt->bind_param("sssssi", $approverName, $approverEmail, $remarks, $updatedBy, $now, $id);
    $stmt->execute();

    notifyLevel(
        $conn,
        'NSM',
        "Action Required: NSM Approval – Travel Order {$order['travel_order_no']}",
        'Province',
        $order,
        $approverName,
        $remarks,
        'NSM'
    );

    header("Location: view_travel_orders.php?success=province");
    exit;
}

// ══════════════════════════════════════════════
// NSM APPROVAL  →  email ALL HR employees
// ══════════════════════════════════════════════
if (isset($_POST['nsm_approver_name'])) {

    $approverName  = trim($_POST['nsm_approver_name']);
    $approverEmail = trim($_POST['nsm_approver_email']);
    $remarks       = trim($_POST['approval_nsm_remarks'] ?? '');

    $stmt = $conn->prepare("
        UPDATE travel_orders
           SET approval_nsm_status  = 'Approved',
               nsm_approver_name    = ?,
               nsm_approver_email   = ?,
               approval_nsm_remarks = ?,
               last_updated_by      = ?,
               last_updated_at      = ?
         WHERE id = ?
    ");
    $stmt->bind_param("sssssi", $approverName, $approverEmail, $remarks, $updatedBy, $now, $id);
    $stmt->execute();

    notifyLevel(
        $conn,
        'HR',
        "Action Required: HR Approval – Travel Order {$order['travel_order_no']}",
        'NSM',
        $order,
        $approverName,
        $remarks,
        'HR'
    );

    header("Location: view_travel_orders.php?success=nsm");
    exit;
}

// ══════════════════════════════════════════════
// HR APPROVAL  →  email ALL CEO employees
// ══════════════════════════════════════════════
if (isset($_POST['hr_approver_name'])) {

    $approverName  = trim($_POST['hr_approver_name']);
    $approverEmail = trim($_POST['hr_approver_email']);
    $remarks       = trim($_POST['approval_hr_remarks'] ?? '');

    $stmt = $conn->prepare("
        UPDATE travel_orders
           SET approval_hr_status  = 'Approved',
               hr_approver_name    = ?,
               hr_approver_email   = ?,
               approval_hr_remarks = ?,
               last_updated_by     = ?,
               last_updated_at     = ?
         WHERE id = ?
    ");
    $stmt->bind_param("sssssi", $approverName, $approverEmail, $remarks, $updatedBy, $now, $id);
    $stmt->execute();

    notifyLevel(
        $conn,
        'CEO',
        "Action Required: CEO Approval – Travel Order {$order['travel_order_no']}",
        'HR',
        $order,
        $approverName,
        $remarks,
        'CEO'
    );

    header("Location: view_travel_orders.php?success=hr");
    exit;
}

// ══════════════════════════════════════════════
// CEO APPROVAL  →  (optionally email employee)
// ══════════════════════════════════════════════
if (isset($_POST['ceo_approver_name'])) {

    $approverName  = trim($_POST['ceo_approver_name']);
    $approverEmail = trim($_POST['ceo_approver_email']);
    $remarks       = trim($_POST['approval_ceo_remarks'] ?? '');

    $stmt = $conn->prepare("
        UPDATE travel_orders
           SET approval_ceo_status  = 'Approved',
               ceo_approver_name    = ?,
               ceo_approver_email   = ?,
               approval_ceo_remarks = ?,
               last_updated_by      = ?,
               last_updated_at      = ?
         WHERE id = ?
    ");
    $stmt->bind_param("sssssi", $approverName, $approverEmail, $remarks, $updatedBy, $now, $id);
    $stmt->execute();

    // ── Optional: notify the employee that their order is fully approved ──
    // Uncomment the block below to enable it.
    /*
    $empStmt = $conn->prepare(
        "SELECT employeeName, employeeEmail FROM employees WHERE EmpID = ? LIMIT 1"
    );
    $empStmt->bind_param("s", $order['EmpID']);
    $empStmt->execute();
    $emp = $empStmt->get_result()->fetch_assoc();
    if (!empty($emp['employeeEmail'])) {
        sendApprovalEmail(
            $emp['employeeEmail'],
            $emp['employeeName'],
            "Your Travel Order {$order['travel_order_no']} is Fully Approved",
            'CEO',
            $order,
            $approverName,
            $remarks,
            'None – Fully Approved ✔'
        );
    }
    */

    header("Location: view_travel_orders.php?success=ceo");
    exit;
}

// Fallback
header("Location: view_travel_orders.php?error=invalid");
exit;