<?php
require_once 'auth.php';
requireLogin();
require_once 'db.php';

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
    die("Invalid ID");
}

$stmt = $conn->prepare("SELECT * FROM travel_orders WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    die("Travel order not found");
}

$order = $result->fetch_assoc();

// Helper function for stage labels
function stageLabel($stage) {
    $labels = [
        'DH'          => 'Department Head',
        'BM'          => 'Branch Manager',
        'PH'          => 'Province Head',
        'CD'          => 'Claim Head',
        'NSM'         => 'NSM',
        'HR'          => 'Human Resource',
        'CEO_OR_DCEO' => 'CEO / DCEO',
    ];
    return $labels[$stage] ?? $stage;
}

function stageDbPrefix($stage) {
    $prefixes = [
        'BM'          => 'bm',
        'DH'          => 'dh',
        'PH'          => 'ph',
        'NSM'         => 'nsm',
        'CD'          => 'cd',
        'HR'          => 'hr',
        'CEO_OR_DCEO' => 'ceo',
    ];
    return $prefixes[$stage] ?? strtolower($stage);
}

?>

<style>
    .detail-row {
        display: grid;
        grid-template-columns: 200px 1fr;
        gap: 15px;
        margin-bottom: 12px;
        padding-bottom: 12px;
        border-bottom: 1px solid #e5e7eb;
    }
    .detail-label {
        font-weight: 600;
        color: #374151;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .detail-value {
        color: #1f2937;
        font-size: 14px;
    }
    .approval-section {
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 16px;
        margin-top: 16px;
    }
    .approval-section-title {
        font-weight: 700;
        color: #1f2937;
        margin-bottom: 12px;
        font-size: 14px;
    }
    .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }
    .status-approved {
        background: #d1fae5;
        color: #065f46;
    }
    .status-pending {
        background: #fef3c7;
        color: #92400e;
    }
</style>

<!-- Travel Order Details -->
<div class="detail-row">
    <div class="detail-label">Travel Order No</div>
    <div class="detail-value"><strong><?= htmlspecialchars($order['travel_order_no']) ?></strong></div>
</div>

<div class="detail-row">
    <div class="detail-label">Employee</div>
    <div class="detail-value">
        <?= htmlspecialchars($order['employeeName']) ?> (<?= htmlspecialchars($order['EmpID']) ?>)
    </div>
</div>

<div class="detail-row">
    <div class="detail-label">Branch / Department</div>
    <div class="detail-value"><?= htmlspecialchars($order['department']) ?></div>
</div>

<div class="detail-row">
    <div class="detail-label">Branch Code</div>
    <div class="detail-value"><?= htmlspecialchars($order['BrCode']) ?></div>
</div>

<div class="detail-row">
    <div class="detail-label">Designation</div>
    <div class="detail-value"><?= htmlspecialchars($order['designation']) ?></div>
</div>

<div class="detail-row">
    <div class="detail-label">Travel Period</div>
    <div class="detail-value">
        <?= htmlspecialchars($order['travelDateFrom']) ?> to <?= htmlspecialchars($order['travelDateTo']) ?>
        (<?= htmlspecialchars($order['noOfDays']) ?> days)
    </div>
</div>

<div class="detail-row">
    <div class="detail-label">Travel Route</div>
    <div class="detail-value">
        From: <?= htmlspecialchars($order['travelFrom']) ?><br>
        To: <?= htmlspecialchars($order['destination']) ?>
    </div>
</div>

<div class="detail-row">
    <div class="detail-label">Distance</div>
    <div class="detail-value"><?= htmlspecialchars($order['kilometer']) ?> km</div>
</div>

<div class="detail-row">
    <div class="detail-label">Mode of Transport</div>
    <div class="detail-value"><?= htmlspecialchars($order['modeOfTransport']) ?></div>
</div>

<div class="detail-row">
    <div class="detail-label">Purpose of Travel</div>
    <div class="detail-value"><?= htmlspecialchars($order['purpose']) ?></div>
</div>

<div class="detail-row">
    <div class="detail-label">Advance Amount</div>
    <div class="detail-value">
        Rs. <?= number_format((float)$order['estimatedCost'], 2) ?>
    </div>
</div>

<!-- Approval Status Section -->
<div class="approval-section">
    <div class="approval-section-title">📋 Approval Status</div>

    <div class="detail-row">
        <div class="detail-label">Current Stage</div>
        <div class="detail-value">
            <strong><?= stageLabel($order['current_approval_stage'] ?? 'PH') ?></strong>
        </div>
    </div>

    <?php
    // Display approval stages based on the current_approval_stage value
    $stages = ['DH', 'BM', 'PH', 'CD', 'NSM', 'HR', 'CEO_OR_DCEO'];
    
    foreach ($stages as $stage) {
        $prefix = stageDbPrefix($stage);
        $statusCol = "approval_{$prefix}_status";
        $nameCol = "{$prefix}_approver_name";
        $emailCol = "{$prefix}_approver_email";
        $remarksCol = "approval_{$prefix}_remarks";
        
        // Skip if no data for this stage
        if (empty($order[$statusCol]) && empty($order[$nameCol])) {
            continue;
        }
        
        $status = $order[$statusCol] ?? 'Pending';
        $statusClass = ($status === 'Approved') ? 'status-approved' : 'status-pending';
    ?>
        <div class="approval-section" style="margin-top: 0;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <strong><?= stageLabel($stage) ?></strong>
                <span class="status-badge <?= $statusClass ?>"><?= htmlspecialchars($status) ?></span>
            </div>
            
            <?php if (!empty($order[$nameCol])): ?>
                <div style="font-size: 13px; color: #6b7280;">
                    <div><strong>Approver:</strong> <?= htmlspecialchars($order[$nameCol]) ?></div>
                    <div><strong>Email:</strong> <?= htmlspecialchars($order[$emailCol]) ?></div>
                    <?php if (!empty($order[$remarksCol])): ?>
                        <div style="margin-top: 8px; padding: 8px; background: #fff; border-left: 3px solid #3b82f6;">
                            <strong>Remarks:</strong><br>
                            <em><?= htmlspecialchars($order[$remarksCol]) ?></em>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div style="font-size: 13px; color: #9ca3af;">
                    Awaiting approval...
                </div>
            <?php endif; ?>
        </div>
    <?php } ?>
</div>

<!-- Metadata -->
<div class="approval-section" style="margin-top: 16px;">
    <div class="approval-section-title">📅 Metadata</div>
    
    <div class="detail-row">
        <div class="detail-label">Created At</div>
        <div class="detail-value">
            <?= !empty($order['created_at']) ? date('Y-m-d H:i:s', strtotime($order['created_at'])) : 'N/A' ?>
        </div>
    </div>

    <div class="detail-row">
        <div class="detail-label">Last Updated</div>
        <div class="detail-value">
            By: <?= htmlspecialchars($order['last_updated_by'] ?? 'System') ?><br>
            At: <?= !empty($order['last_updated_at']) ? date('Y-m-d H:i:s', strtotime($order['last_updated_at'])) : 'N/A' ?>
        </div>
    </div>
</div>
