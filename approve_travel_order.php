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
// ─────────────────────────────────────────────
function sendApprovalEmail(
    string $toEmail,
    string $toName,
    string $subject,
    string $approvalStage,
    array $order,
    string $approverName,
    string $remarks,
    string $nextStage
): void {
    $stageColours = [
        'PH' => ['bg' => '#1a56db', 'badge_bg' => '#dbeafe', 'badge_text' => '#1e40af'],
        'BM' => ['bg' => '#0284c7', 'badge_bg' => '#e0f2fe', 'badge_text' => '#0369a1'],
        'NSM' => ['bg' => '#0891b2', 'badge_bg' => '#cffafe', 'badge_text' => '#0e7490'],
        'CD' => ['bg' => '#d97706', 'badge_bg' => '#fef3c7', 'badge_text' => '#92400e'],
        'DH' => ['bg' => '#9333ea', 'badge_bg' => '#f3e8ff', 'badge_text' => '#6b21a8'],
        'HR' => ['bg' => '#7c3aed', 'badge_bg' => '#ede9fe', 'badge_text' => '#5b21b6'],
        'CEO' => ['bg' => '#059669', 'badge_bg' => '#d1fae5', 'badge_text' => '#065f46'],
        'DCEO' => ['bg' => '#0f766e', 'badge_bg' => '#ccfbf1', 'badge_text' => '#134e4a'],
    ];
    $c = $stageColours[$approvalStage] ?? $stageColours['HR'];

    $employeeName = $order['employeeName'] ?? '';
    $travelOrderNo = $order['travel_order_no'] ?? '';
    $destination = $order['destination'] ?? '';
    $travelDateFrom = $order['travelDateFrom'] ?? '';
    $travelDateTo = $order['travelDateTo'] ?? '';
    $purpose = $order['purpose'] ?? '';
    $branchCode = $order['BrCode'] ?? '';
    $branchName = $order['department'] ?? '';
    $modeOfTravel = $order['modeOfTransport'] ?? '';
    $kilometer = $order['kilometer'] ?? '';
    $empCode = $order['EmpID'] ?? '';
    $noOfDays = $order['noOfDays'] ?? '';
    $place = $order['travelFrom'] ?? '';
    $estimatedCost = !empty($order['estimatedCost'])
                        ? 'Rs. ' . number_format((float)$order['estimatedCost'], 2)
                        : 'N/A';

    $remarksHtml = trim($remarks) !== ''
        ? "<p style=\"margin:6px 0 0;font-size:13px;color:{$c['badge_text']};font-style:italic;\">
               &ldquo;" . htmlspecialchars($remarks) . "&rdquo;
           </p>"
        : '';

    $detailRows = emailRow('TADA No', $travelOrderNo, $c['bg']);
    $detailRows .= emailRow('Employee Name', $employeeName);
    $detailRows .= emailRow('Employee Code', $empCode, $c['bg']);
    $detailRows .= emailRow('Branch Code', $branchCode);
    $detailRows .= emailRow('Branch Name', $branchName, $c['bg']);
    $detailRows .= emailRow('Place', $place);
    $detailRows .= emailRow('Destination', $destination, $c['bg']);
    $detailRows .= emailRow('Travel Mode', $modeOfTravel);
    $detailRows .= emailRow('Kilometer', $kilometer, $c['bg']);
    $detailRows .= emailRow('Travel Date From', $travelDateFrom);
    $detailRows .= emailRow('Travel Date To', $travelDateTo, $c['bg']);
    $detailRows .= emailRow('No. of Days', $noOfDays);
    $detailRows .= emailRow('Purpose', $purpose, $c['bg']);
    $detailRows .= emailRow('Advance Amount', $estimatedCost);

    $stageLabel = $nextStage !== 'None – Fully Approved ✔'
        ? "<p style=\"margin:0;font-size:14px;color:#92400e;line-height:1.5;\">
               ⏳ &nbsp;<strong>Action Required:</strong> Please review and complete the
               <strong>{$nextStage} Approval</strong>.
           </p>"
        : "<p style=\"margin:0;font-size:14px;color:#065f46;line-height:1.5;\">
               ✅ &nbsp;This travel order has been <strong>fully approved</strong>.<br>
               No further action is required.
           </p>";

    $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>{$subject}</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:'Segoe UI',Helvetica,Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f1f5f9;padding:36px 0;">
    <tr>
      <td align="center">
        <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.09);">
          <!-- Header -->
          <tr>
            <td style="background:{$c['bg']};padding:38px 40px;text-align:center;">
              <p style="margin:0 0 6px;font-size:11px;color:rgba(255,255,255,0.7);letter-spacing:2px;text-transform:uppercase;">Nepal Life Insurance Co. Ltd.</p>
              <h1 style="margin:0 0 8px;font-size:26px;font-weight:700;color:#ffffff;line-height:1.25;">Travel Order Approved</h1>
              <p style="margin:0;font-size:14px;color:rgba(255,255,255,0.88);background:rgba(255,255,255,0.15);display:inline-block;padding:4px 16px;border-radius:20px;">
                {$approvalStage} Level &nbsp;✔&nbsp; Completed
              </p>
            </td>
          </tr>
          <!-- Body -->
          <tr>
            <td style="padding:36px 40px;">
              <p style="margin:0 0 8px;font-size:16px;color:#1f2937;font-weight:600;">Dear {$toName},</p>
              <p style="margin:0 0 28px;font-size:15px;color:#4b5563;line-height:1.65;">
                A travel order has been <strong>approved at the {$approvalStage} level</strong>.
              </p>
              <!-- Details -->
              <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;margin-bottom:24px;">
                <tr>
                  <td colspan="2" style="padding:14px 16px 10px;background:#f8fafc;border-bottom:1px solid #e2e8f0;">
                    <p style="margin:0;font-size:11px;font-weight:700;color:#6b7280;letter-spacing:1.2px;text-transform:uppercase;">Travel Order Details</p>
                  </td>
                </tr>
                {$detailRows}
              </table>
              <!-- Approved-by badge -->
              <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:{$c['badge_bg']};border-left:4px solid {$c['bg']};border-radius:0 8px 8px 0;margin-bottom:24px;">
                <tr>
                  <td style="padding:16px 20px;">
                    <p style="margin:0 0 4px;font-size:11px;font-weight:700;color:{$c['badge_text']};letter-spacing:1.1px;text-transform:uppercase;">Approved by — {$approvalStage}</p>
                    <p style="margin:0;font-size:15px;color:{$c['badge_text']};font-weight:600;">{$approverName}</p>
                    {$remarksHtml}
                  </td>
                </tr>
              </table>
              <!-- Action banner -->
              <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#fffbeb;border:1px solid #fcd34d;border-radius:8px;margin-bottom:28px;">
                <tr>
                  <td style="padding:14px 18px;">{$stageLabel}</td>
                </tr>
              </table>
              <p style="margin:0;font-size:13px;color:#9ca3af;line-height:1.7;">
                Please log in to the <strong style="color:#374151;">Nepal Life Travel Management System</strong> to view this request.
              </p>
            </td>
          </tr>
          <!-- Footer -->
          <tr>
            <td style="background:#f8fafc;border-top:1px solid #e2e8f0;padding:22px 40px;text-align:center;">
              <p style="margin:0 0 4px;font-size:12px;color:#9ca3af;">This is an automated notification from the Nepal Life Travel System.</p>
              <p style="margin:0;font-size:11px;color:#cbd5e1;">Please do not reply directly to this email.</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;

    $plainText = "Dear {$toName},\n\n"
        . "A travel order for {$employeeName} (TADA No: {$travelOrderNo}) has been approved at the {$approvalStage} level by {$approverName}.\n\n"
        . "Next Action: {$nextStage}\n\n"
        . "Please log in to the Nepal Life Travel Management System.\n\n"
        . "This is an automated message.";

    $boundary = '----=_Part_' . md5(uniqid((string)mt_rand(), true));
    $headers = "From: Nepal Life Travel System <noreply@nepallife.com.np>\r\n";
    $headers .= "Reply-To: noreply@nepallife.com.np\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";

    $body = "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
          . chunk_split(base64_encode($plainText)) . "\r\n"
          . "--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
          . chunk_split(base64_encode($htmlBody)) . "\r\n--{$boundary}--";

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $sent = mail($toEmail, $encodedSubject, $body, $headers);

    if (!$sent) {
        error_log("Travel System – mail() failed. To: {$toEmail} | Subject: {$subject}");
    }
}

// Helper Functions (unchanged)
function jsonResponse($success, $message, $extra = []) {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

function getRecipientsByLevel(mysqli $conn, string $level): array {
    $stmt = $conn->prepare("SELECT employeeName, employeeEmail FROM employees WHERE level = ? AND employeeEmail IS NOT NULL AND employeeEmail != ''");
    $stmt->bind_param("s", $level);
    $stmt->execute();
    $result = $stmt->get_result();
    $recipients = [];
    while ($row = $result->fetch_assoc()) {
        $recipients[] = $row;
    }
    return $recipients;
}

function notifyLevel(mysqli $conn, string $level, string $subject, string $approvalStage, array $order, string $approverName, string $remarks, string $nextStage): void {
    $recipients = getRecipientsByLevel($conn, $level);
    foreach ($recipients as $recipient) {
        sendApprovalEmail($recipient['employeeEmail'], $recipient['employeeName'], $subject, $approvalStage, $order, $approverName, $remarks, $nextStage);
    }
}

function getApprovalChain(array $order): array {
    $level = strtoupper(trim($order['emp_level'] ?? $order['role'] ?? ''));
    $brCode = trim($order['BrCode'] ?? '');

    // Request Type can be saved with different column names depending on the page/form.
    // Normal approval was working, but Claim was being missed when the value was not in `order_type`.
    $requestType = strtolower(trim((string)(
        $order['request_type']
        ?? $order['RequestType']
        ?? $order['Request Type']
        ?? $order['order_type']
        ?? ''
    )));

    if ($brCode === '100' && $requestType === 'claim') {
        if ($level === 'PH') return ['NSM', 'HR', 'CEO_OR_DCEO'];
        if ($level === 'NSM') return ['HR', 'CEO_OR_DCEO'];
        return ['CD', 'HR', 'CEO_OR_DCEO'];
    }
    if ($brCode === '100') {
        if ($level === 'PH') return ['NSM', 'HR', 'CEO_OR_DCEO'];
        if ($level === 'NSM') return ['HR', 'CEO_OR_DCEO'];
        return ['DH', 'HR', 'CEO_OR_DCEO'];
    }

    switch ($level) {
        case 'ST':          return ['BM', 'PH', 'NSM', 'HR', 'CEO_OR_DCEO'];
        case 'BM':          return ['PH', 'NSM', 'HR', 'CEO_OR_DCEO'];
        case 'PH':          return ['NSM', 'HR', 'CEO_OR_DCEO'];
        case 'NSM':         return ['HR', 'CEO_OR_DCEO'];
        case 'DH':          return ['HR', 'CEO_OR_DCEO'];
        case 'CD':          return ['HR', 'CEO_OR_DCEO'];
        case 'HR':          return ['CEO_OR_DCEO'];
        case 'CEO':
        case 'DCEO':
        case 'CEO_OR_DCEO': return ['CEO_OR_DCEO'];
        default:            return ['PH', 'NSM', 'HR', 'CEO_OR_DCEO'];
    }
}

function stageToLevel(string $stage): string {
    $map = ['BM'=>'BM','PH'=>'PH','NSM'=>'NSM','CD'=>'CD','DH'=>'DH','HR'=>'HR','CEO_OR_DCEO'=>'CEO'];
    return $map[$stage] ?? $stage;
}

function stageLabel(string $stage): string {
    $labels = ['BM'=>'Branch Manager','PH'=>'Province Head','NSM'=>'NSM','CD'=>'Claim Head','DH'=>'Department Head','HR'=>'Human Resource','CEO_OR_DCEO'=>'CEO / DCEO'];
    return $labels[$stage] ?? $stage;
}

function stageDbPrefix(string $stage): string {
    $prefixes = ['BM'=>'bm','PH'=>'ph','NSM'=>'nsm','CD'=>'cd','DH'=>'dh','HR'=>'hr','CEO_OR_DCEO'=>'ceo'];
    return $prefixes[$stage] ?? strtolower($stage);
}

// ══════════════════════════════════════════════
// MAIN LOGIC
// ══════════════════════════════════════════════
header('Content-Type: application/json');

$id = (int) ($_POST['id'] ?? 0);
$stage = $_POST['approval_stage'] ?? '';
$remarks = trim($_POST['remarks'] ?? '');
$approverName = trim($_POST['approver_name'] ?? ($_SESSION['full_name'] ?? 'Approver'));
$approverEmail = trim($_POST['approver_email'] ?? ($_SESSION['username'] ?? ''));

if (!$id || !$stage) {
    jsonResponse(false, 'Invalid approval request.');
}

$stmt = $conn->prepare("SELECT t.*, e.level AS emp_level FROM travel_orders t LEFT JOIN employees e ON t.EmpID = e.EmpID WHERE t.id = ? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    jsonResponse(false, 'Travel order not found.');
}

$chain = getApprovalChain($order);
if (!in_array($stage, $chain, true)) {
    $debugChain = implode(' -> ', $chain);
    $debugLevel = $order['emp_level'] ?? $order['role'] ?? 'UNKNOWN';
    $debugBr = $order['BrCode'] ?? 'UNKNOWN';
    jsonResponse(false, "Invalid approval stage: '$stage'. Chain: [$debugChain]. Level: '$debugLevel'. BrCode: '$debugBr'.");
}

$dbPrefix = stageDbPrefix($stage);
$updatedBy = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'System';
$now = date('Y-m-d H:i:s');

$index = array_search($stage, $chain, true);
$nextStageKey = $chain[$index + 1] ?? null;
$nextStageValue = $nextStageKey ?? 'COMPLETED';

// Find the first available approver at the next stage so view page can filter correctly
$nextApproverEmail = '';
if ($nextStageKey !== null) {
    $nextLevel = stageToLevel($nextStageKey);
    $nextRecipients = getRecipientsByLevel($conn, $nextLevel);
    $nextApproverEmail = $nextRecipients[0]['employeeEmail'] ?? '';
}

$sql = "UPDATE travel_orders SET 
            approval_{$dbPrefix}_status = 'Approved',
            {$dbPrefix}_approver_name = ?,
            {$dbPrefix}_approver_email = ?,
            approval_{$dbPrefix}_remarks = ?,
            current_approval_stage = ?,
            assigned_approver_email = ?,
            last_updated_by = ?,
            last_updated_at = ?
        WHERE id = ?";

$upd = $conn->prepare($sql);
$upd->bind_param("sssssssi", $approverName, $approverEmail, $remarks, $nextStageValue, $nextApproverEmail, $updatedBy, $now, $id);

if (!$upd->execute()) {
    jsonResponse(false, 'Database update failed: ' . $upd->error);
}

// ─────────────────────────────────────────────
// EMAIL NOTIFICATION - Now works for final approval too
// ─────────────────────────────────────────────
$approvalStageLabel = stageLabel($stage);
$nextStageLabel = $nextStageKey ? stageLabel($nextStageKey) : 'None – Fully Approved ✔';
$subject = $nextStageKey 
    ? "Action Required: " . stageLabel($nextStageKey) . " Approval – Travel Order " . ($order['travel_order_no'] ?? '')
    : "Travel Order Fully Approved – " . ($order['travel_order_no'] ?? '');

if ($nextStageKey !== null) {
    // Notify next level
    $nextLevel = stageToLevel($nextStageKey);
    notifyLevel($conn, $nextLevel, $subject, $approvalStageLabel, $order, $approverName, $remarks, $nextStageLabel);
} else {
    // Final Approval - Notify Requester + HR
    if (!empty($order['employeeEmail'])) {
        sendApprovalEmail(
            $order['employeeEmail'],
            $order['employeeName'] ?? 'Employee',
            $subject,
            $approvalStageLabel,
            $order,
            $approverName,
            $remarks,
            $nextStageLabel
        );
    }
    notifyLevel($conn, 'HR', $subject, $approvalStageLabel, $order, $approverName, $remarks, $nextStageLabel);
}

jsonResponse(true, 'Travel order approved successfully.');
?>