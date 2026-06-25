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
    array  $order,
    string $approverName,
    string $remarks,
    string $nextStage
): void {

    $stageColours = [
        'PH'   => ['bg' => '#1a56db', 'badge_bg' => '#dbeafe', 'badge_text' => '#1e40af'],
        'BM'   => ['bg' => '#0284c7', 'badge_bg' => '#e0f2fe', 'badge_text' => '#0369a1'],
        'NSM'  => ['bg' => '#0891b2', 'badge_bg' => '#cffafe', 'badge_text' => '#0e7490'],
        'CD'   => ['bg' => '#d97706', 'badge_bg' => '#fef3c7', 'badge_text' => '#92400e'],
        'DH'   => ['bg' => '#9333ea', 'badge_bg' => '#f3e8ff', 'badge_text' => '#6b21a8'],
        'HR'   => ['bg' => '#7c3aed', 'badge_bg' => '#ede9fe', 'badge_text' => '#5b21b6'],
        'CEO'  => ['bg' => '#059669', 'badge_bg' => '#d1fae5', 'badge_text' => '#065f46'],
        'DCEO' => ['bg' => '#0f766e', 'badge_bg' => '#ccfbf1', 'badge_text' => '#134e4a'],
    ];
    $c = $stageColours[$approvalStage] ?? $stageColours['HR'];

    $employeeName   = $order['employeeName']    ?? '';
    $travelOrderNo  = $order['travel_order_no'] ?? '';
    $destination    = $order['destination']     ?? '';
    $travelDateFrom = $order['travelDateFrom']  ?? '';
    $travelDateTo   = $order['travelDateTo']    ?? '';
    $purpose        = $order['purpose']         ?? '';
    $branchCode     = $order['BrCode']          ?? '';
    $branchName     = $order['department']      ?? '';
    $modeOfTravel   = $order['modeOfTransport'] ?? '';
    $kilometer      = $order['kilometer']       ?? '';
    $empCode        = $order['EmpID']           ?? '';
    $noOfDays       = $order['noOfDays']        ?? '';
    $place          = $order['travelFrom']      ?? '';
    $estimatedCost  = !empty($order['estimatedCost'])
                        ? 'Rs. ' . number_format((float)$order['estimatedCost'], 2)
                        : 'N/A';
    $travelPeriod   = $travelDateFrom . '  →  ' . $travelDateTo;

    $remarksHtml = trim($remarks) !== ''
        ? "<p style=\"margin:6px 0 0;font-size:13px;color:{$c['badge_text']};font-style:italic;\">
               &ldquo;" . htmlspecialchars($remarks) . "&rdquo;
           </p>"
        : '';

    $detailRows  = emailRow('TADA No',          $travelOrderNo,  $c['bg']);
    $detailRows .= emailRow('Employee Name',    $employeeName);
    $detailRows .= emailRow('Employee Code',    $empCode,        $c['bg']);
    $detailRows .= emailRow('Branch Code',      $branchCode);
    $detailRows .= emailRow('Branch Name',      $branchName,     $c['bg']);
    $detailRows .= emailRow('Place',            $place);
    $detailRows .= emailRow('Destination',      $destination,    $c['bg']);
    $detailRows .= emailRow('Travel Mode',      $modeOfTravel);
    $detailRows .= emailRow('Kilometer',        $kilometer,      $c['bg']);
    $detailRows .= emailRow('Travel Date From', $travelDateFrom);
    $detailRows .= emailRow('Travel Date To',   $travelDateTo,   $c['bg']);
    $detailRows .= emailRow('No. of Days',      $noOfDays);
    $detailRows .= emailRow('Purpose',          $purpose,        $c['bg']);
    $detailRows .= emailRow('Advance Amount',   $estimatedCost);

    $stageLabel = $nextStage !== 'None – Fully Approved ✔'
        ? "<p style=\"margin:0;font-size:14px;color:#92400e;line-height:1.5;\">
               ⏳ &nbsp;<strong>Action Required:</strong>
               Please review and complete the
               <strong>{$nextStage} Approval</strong>
               at your earliest convenience.
           </p>"
        : "<p style=\"margin:0;font-size:14px;color:#065f46;line-height:1.5;\">
               ✅ &nbsp;This travel order has been <strong>fully approved</strong>.
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
  <table width="100%" cellpadding="0" cellspacing="0" border="0"
         style="background:#f1f5f9;padding:36px 0;">
    <tr>
      <td align="center">
        <table width="600" cellpadding="0" cellspacing="0" border="0"
               style="max-width:600px;width:100%;background:#ffffff;
                      border-radius:12px;overflow:hidden;
                      box-shadow:0 4px 24px rgba(0,0,0,0.09);">
          <!-- Header -->
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
          <!-- Body -->
          <tr>
            <td style="padding:36px 40px;">
              <p style="margin:0 0 8px;font-size:16px;color:#1f2937;font-weight:600;">
                Dear {$toName},
              </p>
              <p style="margin:0 0 28px;font-size:15px;color:#4b5563;line-height:1.65;">
                A travel order has been <strong>approved at the {$approvalStage} level</strong>
                and forwarded to you for the next action.
              </p>
              <!-- Details -->
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
              <!-- Approved-by badge -->
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
              <!-- Action banner -->
              <table width="100%" cellpadding="0" cellspacing="0" border="0"
                     style="background:#fffbeb;border:1px solid #fcd34d;
                            border-radius:8px;margin-bottom:28px;">
                <tr>
                  <td style="padding:14px 18px;">
                    {$stageLabel}
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
          <!-- Footer -->
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
      </td>
    </tr>
  </table>
</body>
</html>
HTML;

    $plainText = "Dear {$toName},\n\n"
        . "A travel order for {$employeeName} (TADA No: {$travelOrderNo}) has been approved\n"
        . "at the {$approvalStage} level by {$approverName}.\n\n"
        . "Employee Code      : {$empCode}\n"
        . "Branch Code        : {$branchCode}\n"
        . "Branch Name        : {$branchName}\n"
        . "Mode of Transport  : {$modeOfTravel}\n"
        . "Kilometer Covered  : {$kilometer}\n"
        . "Place              : {$place}\n"
        . "Destination        : {$destination}\n"
        . "Travel Date From   : {$travelDateFrom}\n"
        . "Travel Date To     : {$travelDateTo}\n"
        . "No. of Days        : {$noOfDays}\n"
        . "Purpose            : {$purpose}\n"
        . "Advance Amount     : {$estimatedCost}\n"
        . (trim($remarks) !== '' ? "Remarks            : {$remarks}\n" : '')
        . "\nNext Action        : {$nextStage}\n"
        . "\nPlease log in to the Nepal Life Travel Management System to take action.\n\n"
        . "This is an automated message. Please do not reply.";

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
// Helper: Fetch recipients by level/level
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
// Helper: Notify all employees at a given level
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
// Helper: Determine the approval chain for
// a given travel order based on level & BrCode.
//
// Returns an ordered array of stage keys, e.g.:
//   ['BM', 'PH', 'NSM', 'HR', 'CEO_OR_DCEO']
//
// Chains:
//   OI  (Operation Incharge) → PH → NSM → HR → CEO/DCEO
//   ST  (Staff)              → BM → PH  → NSM → HR → CEO/DCEO
//   CD  (Claim)              → CD → HR  → CEO/DCEO
//   BrCode = '100'           → DH → HR  → CEO/DCEO
// ─────────────────────────────────────────────
function getApprovalChain(array $order): array
{
    $level    = strtoupper(trim($order['level']    ?? ''));
    $brCode  = trim($order['BrCode'] ?? '');
    $isClaim = strtolower(trim($order['order_type'] ?? '')) === 'claim';

    // BrCode 100 takes precedence over level
    if ($brCode === '100') {
        return ['DH', 'HR', 'CEO_OR_DCEO'];
    }

    if ($isClaim || $level === 'CD') {
        return ['CD', 'HR', 'CEO_OR_DCEO'];
    }

    if ($level === 'ST') {
        return ['BM', 'PH', 'NSM', 'HR', 'CEO_OR_DCEO'];
    }

    // OI (Operation Incharge) – also the default
    return ['PH', 'NSM', 'HR', 'CEO_OR_DCEO'];
}

// ─────────────────────────────────────────────
// Helper: Map a stage key to the employees
// table `level` value used in getRecipientsByLevel
// ─────────────────────────────────────────────
function stageToLevel(string $stage): string
{
    $map = [
        'BM'          => 'BM',
        'PH'          => 'PH',
        'NSM'         => 'NSM',
        'CD'          => 'CD',
        'DH'          => 'DH',
        'HR'          => 'HR',
        'CEO_OR_DCEO' => 'CEO',   // adjust if CEO and DCEO are separate levels
    ];
    return $map[$stage] ?? $stage;
}

// ─────────────────────────────────────────────
// Helper: Friendly display label for a stage key
// ─────────────────────────────────────────────
function stageLabel(string $stage): string
{
    $labels = [
        'BM'          => 'Branch Manager',
        'PH'          => 'Province Head',
        'NSM'         => 'NSM',
        'CD'          => 'Claim Head',
        'DH'          => 'Department Head',
        'HR'          => 'Human Resource',
        'CEO_OR_DCEO' => 'CEO / DCEO',
    ];
    return $labels[$stage] ?? $stage;
}

// ─────────────────────────────────────────────
// Helper: DB column prefix for each stage key
// e.g. 'PH' → 'approval_ph_status', 'ph_approver_name' …
// ─────────────────────────────────────────────
function stageDbPrefix(string $stage): string
{
    $prefixes = [
        'BM'          => 'bm',
        'PH'          => 'ph',
        'NSM'         => 'nsm',
        'CD'          => 'cd',
        'DH'          => 'dh',
        'HR'          => 'hr',
        'CEO_OR_DCEO' => 'ceo',
    ];
    return $prefixes[$stage] ?? strtolower($stage);
}

// ─────────────────────────────────────────────
// Helper: POST field name used by each stage
// e.g. 'PH' → 'ph_approver_name'
// ─────────────────────────────────────────────
function stagePostKey(string $stage): string
{
    return stageDbPrefix($stage) . '_approver_name';
}

// ══════════════════════════════════════════════
// MAIN – Fetch travel order
// ══════════════════════════════════════════════
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

// Determine the correct approval chain for this order
$chain = getApprovalChain($order);

// ──────────────────────────────────────────────
// Loop through the chain and find which stage
// is being submitted right now (by checking POST)
// ──────────────────────────────────────────────
$handledStage = null;

foreach ($chain as $index => $stage) {

    $postKey = stagePostKey($stage);         // e.g. 'ph_approver_name'

    if (!isset($_POST[$postKey])) {
        continue;   // not this stage
    }

    // ── Grab POST data for this stage ──
    $dbPrefix      = stageDbPrefix($stage);  // e.g. 'ph'
    $approverName  = trim($_POST[$postKey]);
    $approverEmail = trim($_POST[$dbPrefix . '_approver_email'] ?? '');
    $remarks       = trim($_POST['approval_' . $dbPrefix . '_remarks'] ?? '');

    // ── Update the travel_orders table ──
    $sql = "
        UPDATE travel_orders
           SET approval_{$dbPrefix}_status  = 'Approved',
               {$dbPrefix}_approver_name    = ?,
               {$dbPrefix}_approver_email   = ?,
               approval_{$dbPrefix}_remarks = ?,
               last_updated_by              = ?,
               last_updated_at              = ?
         WHERE id = ?
    ";
    $upd = $conn->prepare($sql);
    $upd->bind_param("sssssi",
        $approverName,
        $approverEmail,
        $remarks,
        $updatedBy,
        $now,
        $id
    );
    $upd->execute();

    // ── Determine the next stage in the chain ──
    $nextStageKey   = $chain[$index + 1] ?? null;
    $nextStageLabel = $nextStageKey
                        ? stageLabel($nextStageKey)
                        : 'None – Fully Approved ✔';

    // ── Notify the next stage recipients ──
    if ($nextStageKey !== null) {
        $nextLevel = stageToLevel($nextStageKey);
        notifyLevel(
            $conn,
            $nextLevel,
            "Action Required: {$nextStageLabel} Approval – Travel Order {$order['travel_order_no']}",
            stageLabel($stage),
            $order,
            $approverName,
            $remarks,
            $nextStageLabel
        );
    }

    $handledStage = $dbPrefix;
    break;
}

// ── Redirect ──
if ($handledStage !== null) {
    header("Location: view_travel_orders.php?success={$handledStage}");
    exit;
}

// Fallback – no matching POST key found
header("Location: view_travel_orders.php?error=invalid");
exit;