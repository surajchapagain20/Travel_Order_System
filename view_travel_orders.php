<?php
require_once 'auth.php';
requireLogin();
require_once 'db.php';

// ==================== HELPER FUNCTIONS ====================

function getApprovalChain($level, $brCode, $requestType = 'Normal') {
    $level       = strtoupper(trim((string)$level));
    $requestType = strtolower(trim((string)$requestType));

    if ($brCode === '100' && $requestType === 'claim') {
        if ($level === 'PH')  return ['NSM', 'HR', 'CEO_OR_DCEO'];
        if ($level === 'NSM') return ['HR', 'CEO_OR_DCEO'];
        return ['CD', 'HR', 'CEO_OR_DCEO'];
    }
    if ($brCode === '100') {
        if ($level === 'PH')  return ['NSM', 'HR', 'CEO_OR_DCEO'];
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

function stageLabel($stage) {
    $stage  = strtoupper(trim((string)$stage));
    $labels = [
        'DH'          => 'Department Head',
        'BM'          => 'Branch Manager',
        'PH'          => 'Province Head',
        'CD'          => 'Claim Head',
        'NSM'         => 'NSM',
        'HR'          => 'Human Resource',
        'CEO_OR_DCEO' => 'CEO / DCEO',
        'CEO'         => 'CEO',
        'DCEO'        => 'DCEO',
    ];
    return $labels[$stage] ?? $stage;
}

function canApproveStage($approverLevel, $currentStage) {
    $approverLevel = strtoupper(trim((string)$approverLevel));
    $currentStage  = strtoupper(trim((string)$currentStage));
    if ($currentStage === 'CEO_OR_DCEO') {
        return in_array($approverLevel, ['CEO', 'DCEO', 'CEO_OR_DCEO'], true);
    }
    return $approverLevel === $currentStage;
}

// ==================== FETCH LOGGED-IN APPROVER ====================

$approver_name_val  = $_SESSION['full_name'] ?? 'Approver';
$approver_email_val = $_SESSION['username']  ?? '';
$approver_level_val = strtoupper(trim((string)($_SESSION['level'] ?? '')));

if (!empty($_SESSION['full_name'])) {
    $stmt = $conn->prepare("SELECT employeeEmail, level FROM employees WHERE employeeName = ? LIMIT 1");
    $stmt->bind_param("s", $_SESSION['full_name']);
    $stmt->execute();
    if ($row = $stmt->get_result()->fetch_assoc()) {
        if (!empty($row['employeeEmail'])) $approver_email_val = $row['employeeEmail'];
        if (!empty($row['level']))         $approver_level_val = strtoupper(trim($row['level']));
    }
}

if (empty($approver_level_val) && !empty($approver_email_val)) {
    $stmt = $conn->prepare("SELECT level, employeeName, employeeEmail FROM employees WHERE employeeEmail = ? LIMIT 1");
    $stmt->bind_param("s", $approver_email_val);
    $stmt->execute();
    if ($row = $stmt->get_result()->fetch_assoc()) {
        if (!empty($row['level']))         $approver_level_val = strtoupper(trim($row['level']));
        if (!empty($row['employeeName']))  $approver_name_val  = $row['employeeName'];
        if (!empty($row['employeeEmail'])) $approver_email_val = $row['employeeEmail'];
    }
}

// ==================== ROLE-BASED STAGE FILTER ====================

$stageFilter = '';
switch ($approver_level_val) {
    case 'PH':
        $stageFilter = "AND t.current_approval_stage = 'PH'";
        break;
    case 'HR':
        $stageFilter = '';
        break;
    case 'NSM':
        $stageFilter = "AND t.current_approval_stage = 'NSM'";
        break;
    case 'DH':
        $stageFilter = "AND t.current_approval_stage = 'DH'";
        break;
    case 'BM':
        $stageFilter = "AND t.current_approval_stage = 'BM'";
        break;
    case 'CD':
        $stageFilter = "AND t.current_approval_stage = 'CD'";
        break;
    case 'CEO':
    case 'DCEO':
    case 'CEO_OR_DCEO':
        $stageFilter = "AND t.current_approval_stage IN ('CEO_OR_DCEO','CEO','DCEO')";
        break;
    default:
        if (!empty($approver_level_val)) {
            $safeLevel   = $conn->real_escape_string($approver_level_val);
            $stageFilter = "AND t.current_approval_stage = '$safeLevel'";
        }
        break;
}

$manuallySelectedRoles = ['BM', 'PH', 'NSM', 'DH', 'CD'];
$assignedFilter = '';
if (!empty($approver_email_val) && in_array($approver_level_val, $manuallySelectedRoles, true)) {
    $safeEmail      = $conn->real_escape_string($approver_email_val);
    $assignedFilter = "AND t.assigned_approver_email = '$safeEmail'";
}

// ==================== EXPORT TO CSV ====================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $search = $_GET['search'] ?? '';
    $where  = "WHERE 1=1 $stageFilter $assignedFilter";
    if (!empty($search)) {
        $s      = $conn->real_escape_string($search);
        $where .= " AND (t.employeeName LIKE '%$s%' OR t.department LIKE '%$s%' OR t.EmpID LIKE '%$s%')";
    }
    $sql    = "SELECT t.*, e.level AS emp_level FROM travel_orders t
               LEFT JOIN employees e ON t.EmpID = e.EmpID
               $where ORDER BY t.id DESC";
    $result = $conn->query($sql);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="travel_orders_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['#','TADA No','BrCode','Branch/Dept','EmpID','Employee','Travel From','Destination',
                   'Start Date','End Date','No of Days','Purpose','Request Type','Status','Current Stage']);
    $sn = 1;
    while ($row = $result->fetch_assoc()) {
        $requestType  = $row['request_type'] ?? 'Normal';
        $empLevel     = strtoupper(trim((string)($row['emp_level'] ?? '')));
        $chain        = getApprovalChain($empLevel, $row['BrCode'] ?? '', $requestType);
        $currentStage = strtoupper(trim((string)($row['current_approval_stage'] ?? ($chain[0] ?? 'PH'))));
        $status       = ($row['approval_ceo_status'] === 'Approved') ? 'FULLY APPROVED' : 'PENDING';
        fputcsv($out, [
            $sn++,
            $row['travel_order_no'],
            $row['BrCode'],
            $row['department'],
            $row['EmpID'],
            $row['employeeName'],
            $row['travelFrom'],
            $row['destination'],
            $row['travelDateFrom'],
            $row['travelDateTo'],
            $row['noOfDays'],
            $row['purpose'],
            $requestType,
            $status,
            stageLabel($currentStage),
        ]);
    }
    fclose($out);
    exit;
}

// ==================== FETCH TRAVEL ORDERS (PAGINATED) ====================

$search      = $_GET['search'] ?? '';
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$perPage     = max(5, min(100, (int)($_GET['per_page'] ?? 10)));

$where = "WHERE 1=1 $stageFilter $assignedFilter";
if (!empty($search)) {
    $s      = $conn->real_escape_string($search);
    $where .= " AND (t.employeeName LIKE '%$s%' OR t.department LIKE '%$s%' OR t.EmpID LIKE '%$s%')";
}

$countSql     = "SELECT COUNT(*) AS cnt FROM travel_orders t LEFT JOIN employees e ON t.EmpID = e.EmpID $where";
$countResult  = $conn->query($countSql);
$totalRecords = $countResult ? (int)$countResult->fetch_assoc()['cnt'] : 0;
$totalPages   = $totalRecords > 0 ? (int)ceil($totalRecords / $perPage) : 1;
$currentPage  = min($currentPage, $totalPages);
$offset       = ($currentPage - 1) * $perPage;

$sql    = "SELECT t.*, e.level AS emp_level FROM travel_orders t
           LEFT JOIN employees e ON t.EmpID = e.EmpID
           $where ORDER BY t.id DESC
           LIMIT $perPage OFFSET $offset";
$result = $conn->query($sql);

// ==================== STAGE SUMMARY STATS ====================
$stageStats = [];
$stageDbMap = [
    'BM'          => 'bm',
    'DH'          => 'dh',
    'NSM'         => 'nsm',
    'HR'          => 'hr',
    'CEO_OR_DCEO' => 'ceo',
];
$stagesToCount = ['BM', 'DH', 'NSM', 'HR', 'CEO_OR_DCEO'];
foreach ($stagesToCount as $st) {
    $stageClause = ($st === 'CEO_OR_DCEO')
        ? "current_approval_stage IN ('CEO_OR_DCEO','CEO','DCEO')"
        : "current_approval_stage = '$st'";
    $dbCol = $stageDbMap[$st];

    $rPending = $conn->query(
        "SELECT COUNT(*) AS cnt FROM travel_orders
         WHERE $stageClause
           AND (approval_{$dbCol}_status IS NULL OR approval_{$dbCol}_status != 'Approved')"
    );
    $rApproved = $conn->query(
        "SELECT COUNT(*) AS cnt FROM travel_orders
         WHERE approval_{$dbCol}_status = 'Approved'"
    );

    $stageStats[$st] = [
        'pending'  => $rPending  ? (int)$rPending->fetch_assoc()['cnt']  : 0,
        'approved' => $rApproved ? (int)$rApproved->fetch_assoc()['cnt'] : 0,
    ];
}

$roleLabelMap = [
    'PH'          => 'Province Head',
    'HR'          => 'Human Resource',
    'NSM'         => 'National Sales Manager',
    'DH'          => 'Department Head',
    'BM'          => 'Branch Manager',
    'CD'          => 'Claim Head',
    'CEO'         => 'CEO',
    'DCEO'        => 'Deputy CEO',
    'CEO_OR_DCEO' => 'CEO / DCEO',
    'ST'          => 'Staff',
];
$roleDisplayLabel = $roleLabelMap[$approver_level_val] ?? $approver_level_val;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Travel Orders — <?= htmlspecialchars($roleDisplayLabel) ?> Approval</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --brand-primary: #1a56db;
            --brand-dark:    #1e3a8a;
            --brand-light:   #eff6ff;
            --sidebar-w:     0px;
        }
        body { font-family: 'Outfit', sans-serif; background: #f1f5f9; color: #1e293b; }

        /* ── Page Header ── */
        .page-header {
            background: linear-gradient(135deg, var(--brand-dark) 0%, var(--brand-primary) 60%, #3b82f6 100%);
            color: #fff;
            padding: 28px 36px 22px;
            border-radius: 0 0 18px 18px;
            margin-bottom: 28px;
            box-shadow: 0 4px 24px rgba(30,58,138,.25);
            position: relative;
            overflow: hidden;
        }
        .page-header::before {
            content: '';
            position: absolute; inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
        .page-header .header-icon {
            width: 56px; height: 56px; border-radius: 14px;
            background: rgba(255,255,255,.18);
            display: flex; align-items: center; justify-content: center;
            font-size: 26px; flex-shrink: 0;
        }
        .page-header h1 { font-size: 1.55rem; font-weight: 700; margin: 0; letter-spacing: -.3px; }
        .page-header .sub { font-size: .85rem; opacity: .82; margin-top: 3px; }
        .role-badge {
            display: inline-flex; align-items: center; gap: 6px;
            background: rgba(255,255,255,.22); border: 1px solid rgba(255,255,255,.35);
            color: #fff; border-radius: 50px; padding: 5px 14px;
            font-size: .8rem; font-weight: 600; letter-spacing: .3px; backdrop-filter: blur(6px);
        }
        .stat-pill {
            background: rgba(255,255,255,.15); border: 1px solid rgba(255,255,255,.25);
            border-radius: 10px; padding: 10px 20px; text-align: center;
        }
        .stat-pill .num  { font-size: 1.6rem; font-weight: 700; line-height: 1; }
        .stat-pill .lbl  { font-size: .72rem; opacity: .8; margin-top: 2px; }

        /* ── Toolbar ── */
        .toolbar-card {
            background: #fff;
            border-radius: 14px;
            padding: 16px 20px;
            box-shadow: 0 1px 6px rgba(0,0,0,.07);
            margin-bottom: 20px;
        }

        /* ── Bulk Action Bar ── */
        .bulk-action-bar {
            display: none;
            background: linear-gradient(135deg, #1e3a8a, #1a56db);
            color: #fff;
            border-radius: 12px;
            padding: 12px 20px;
            margin-bottom: 14px;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
            box-shadow: 0 4px 16px rgba(26,86,219,.3);
            animation: slideIn .2s ease;
        }
        .bulk-action-bar.active { display: flex; }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-8px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .bulk-count-badge {
            background: rgba(255,255,255,.25);
            border: 1px solid rgba(255,255,255,.4);
            border-radius: 50px;
            padding: 4px 14px;
            font-size: .82rem;
            font-weight: 700;
        }
        .bulk-action-bar .btn-bulk-approve {
            background: #16a34a; color: #fff; border: none;
            padding: 7px 18px; border-radius: 8px; font-weight: 600;
            font-size: .85rem; display: flex; align-items: center; gap: 6px;
            transition: background .15s;
        }
        .bulk-action-bar .btn-bulk-approve:hover { background: #15803d; }
        .bulk-action-bar .btn-bulk-clear {
            background: rgba(255,255,255,.15); color: #fff;
            border: 1px solid rgba(255,255,255,.35);
            padding: 7px 14px; border-radius: 8px; font-size: .82rem;
            transition: background .15s;
        }
        .bulk-action-bar .btn-bulk-clear:hover { background: rgba(255,255,255,.25); }

        /* ── Checkbox styling ── */
        .row-checkbox, #selectAllCheckbox {
            width: 16px; height: 16px; cursor: pointer;
            accent-color: var(--brand-primary);
        }
        tr.row-selected { background: #eff6ff !important; }
        tr.row-selected td { border-color: #bfdbfe !important; }
        th.check-col, td.check-col { width: 42px; text-align: center; }

        /* ── Table ── */
        .table-card {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 1px 8px rgba(0,0,0,.07);
            overflow: hidden;
        }
        .table { margin: 0; font-size: .84rem; }
        .table thead th {
            background: var(--brand-dark); color: #fff;
            font-weight: 600; letter-spacing: .3px;
            border: none; padding: 13px 10px; white-space: nowrap;
        }
        .table tbody tr:hover { background: var(--brand-light); }
        .table tbody td { padding: 10px; vertical-align: middle; border-color: #e2e8f0; }

        /* ── Badges ── */
        .status-badge {
            display: inline-block; padding: 4px 10px; border-radius: 20px;
            font-size: .72rem; font-weight: 700; letter-spacing: .4px; white-space: nowrap;
        }
        .status-approved { background: #d1fae5; color: #065f46; }
        .status-pending  { background: #fef3c7; color: #92400e; }
        .stage-chip {
            display: inline-block; background: #dbeafe; color: #1e40af;
            border-radius: 20px; padding: 3px 10px; font-size: .72rem; font-weight: 700;
        }
        .tada-no { font-weight: 700; color: var(--brand-primary); }

        /* ── Buttons ── */
        .btn-approve  { background: #16a34a; color: #fff; border: none; }
        .btn-approve:hover { background: #15803d; color: #fff; }
        .btn-export { background: #16a34a; color: #fff; border: none; }
        .btn-export:hover { background: #15803d; color: #fff; }
        .action-wrap { display: flex; flex-direction: column; gap: 4px; }

        /* ── Empty state ── */
        .empty-state { text-align: center; padding: 60px 20px; color: #94a3b8; }
        .empty-state i { font-size: 3rem; margin-bottom: 12px; display: block; }

        /* Pagination */
        .pagination-wrapper {
            margin-top: 20px;
            position: relative;
            z-index: 50;
            width: 100%;
            display: block;
            clear: both;
            background: #fff;
            overflow: visible;
        }
        .pag-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 12px 0;
            flex-wrap: wrap;
            position: relative;
            z-index: 51;
            overflow: visible;
        }
        .pag-info { font-size: .85rem; color: #6b7280; flex-shrink: 0; white-space: nowrap; }
        .pag-controls {
            display: flex; align-items: center; gap: 6px; flex-wrap: nowrap;
            flex-shrink: 0; position: relative; z-index: 52;
        }
        .pag-link {
            padding: 6px 12px; font-size: .85rem; font-weight: 500;
            color: var(--brand-primary, #2563eb); text-decoration: none;
            background: transparent; border: none; cursor: pointer; transition: all .15s;
        }
        .pag-link:hover:not(.disabled) { color: var(--brand-dark, #1e40af); font-weight: 600; }
        .pag-link.disabled { color: #cbd5e1; cursor: default; pointer-events: none; }
        .pag-numbers { display: flex; align-items: center; gap: 4px; flex-wrap: nowrap; }
        .pag-num {
            display: inline-flex; align-items: center; justify-content: center;
            width: 32px; height: 32px; border-radius: 6px; font-size: .8rem; font-weight: 500;
            background: #f1f5f9; color: #374151; text-decoration: none; transition: all .15s; cursor: pointer;
        }
        .pag-num:not(.pag-num-active):hover { background: var(--brand-light, #dbeafe); color: var(--brand-primary, #2563eb); font-weight: 600; }
        .pag-num-active { background: var(--brand-primary, #2563eb); color: #fff; font-weight: 700; }
        .pag-ellipsis { padding: 0 4px; font-size: .8rem; color: #9ca3af; flex-shrink: 0; }

        /* ── Stage Summary Cards ── */
        .stage-card {
            background: #fff;
            border-radius: 14px;
            padding: 16px 18px 14px;
            box-shadow: 0 1px 8px rgba(0,0,0,.07);
            border-top: 3px solid var(--card-color);
            height: 100%;
        }
        .stage-card-head { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; }
        .stage-card-icon {
            width: 32px; height: 32px; border-radius: 8px;
            background: var(--card-light); color: var(--card-color);
            display: flex; align-items: center; justify-content: center;
            font-size: 15px; flex-shrink: 0;
        }
        .stage-card-label { font-size: .78rem; font-weight: 700; color: #374151; text-transform: uppercase; letter-spacing: .4px; }
        .stage-card-counts { display: flex; align-items: center; gap: 0; margin-bottom: 10px; }
        .scc-item { flex: 1; text-align: center; }
        .scc-divider { width: 1px; height: 32px; background: #e2e8f0; }
        .scc-num { display: block; font-size: 1.45rem; font-weight: 700; color: #1e293b; line-height: 1; }
        .scc-lbl { display: block; font-size: .68rem; color: #6b7280; margin-top: 2px; text-transform: uppercase; letter-spacing: .3px; }
        .scc-pending  .scc-num { color: #d97706; }
        .scc-approved .scc-num { color: #16a34a; }
        .stage-card-bar { height: 4px; background: #f1f5f9; border-radius: 4px; overflow: hidden; margin-bottom: 6px; }
        .stage-card-bar-fill { height: 100%; background: var(--card-color); border-radius: 4px; transition: width .4s ease; }
        .stage-card-total { font-size: .7rem; color: #9ca3af; text-align: right; }

        /* ── Bulk Modal ── */
        .bulk-summary-list {
            max-height: 220px; overflow-y: auto; border: 1px solid #e2e8f0;
            border-radius: 8px; background: #f8fafc;
        }
        .bulk-summary-item {
            display: flex; align-items: center; gap: 10px;
            padding: 8px 12px; border-bottom: 1px solid #e2e8f0; font-size: .82rem;
        }
        .bulk-summary-item:last-child { border-bottom: none; }
        .bulk-summary-item .tada { font-weight: 700; color: var(--brand-primary); min-width: 110px; }
        .bulk-progress-wrap { margin-top: 14px; display: none; }
        .bulk-progress-wrap .progress { height: 8px; border-radius: 4px; }

        @media (max-width: 768px) {
            .page-header { padding: 20px; border-radius: 0; }
            .stat-pill { padding: 8px 12px; }
            .stat-pill .num { font-size: 1.2rem; }
        }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>

<!-- ==================== PAGE HEADER ==================== -->
<div class="page-header">
    <div class="d-flex align-items-start gap-3 flex-wrap">
        <div class="header-icon"><i class="bi bi-airplane-fill"></i></div>
        <div class="flex-grow-1">
            <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                <h1>Travel Order Approvals</h1>
                <span class="role-badge">
                    <i class="bi bi-shield-check-fill"></i>
                    <?= htmlspecialchars($roleDisplayLabel) ?>
                </span>
            </div>
            <div class="sub">
                <i class="bi bi-person-fill me-1"></i><?= htmlspecialchars($approver_name_val) ?>
                &nbsp;·&nbsp;
                <i class="bi bi-envelope-fill me-1"></i><?= htmlspecialchars($approver_email_val) ?>
                &nbsp;·&nbsp;
                <?= date('l, d F Y') ?>
            </div>
        </div>
        <div class="stat-pill">
            <div class="num"><?= $totalRecords ?></div>
            <div class="lbl">Pending Review</div>
        </div>
    </div>
</div>

<div class="container-fluid px-3 px-md-4">

    <!-- ==================== STAGE SUMMARY CARDS ==================== -->
    <?php
    $cardDefs = [
        'BM'         => ['label'=>'Branch Manager', 'icon'=>'bi-building',          'color'=>'#0284c7','light'=>'#e0f2fe','text'=>'#0369a1'],
        'DH'         => ['label'=>'Dept. Head',     'icon'=>'bi-person-workspace',   'color'=>'#9333ea','light'=>'#f3e8ff','text'=>'#6b21a8'],
        'NSM'        => ['label'=>'NSM',             'icon'=>'bi-graph-up-arrow',     'color'=>'#0891b2','light'=>'#cffafe','text'=>'#0e7490'],
        'HR'         => ['label'=>'Human Resource',  'icon'=>'bi-people-fill',        'color'=>'#7c3aed','light'=>'#ede9fe','text'=>'#5b21b6'],
        'CEO_OR_DCEO'=> ['label'=>'CEO / DCEO',      'icon'=>'bi-star-fill',          'color'=>'#059669','light'=>'#d1fae5','text'=>'#065f46'],
    ];
    ?>
    <div class="row g-3 mb-4">
        <?php foreach ($cardDefs as $stKey => $cd):
            $pending  = $stageStats[$stKey]['pending']  ?? 0;
            $approved = $stageStats[$stKey]['approved'] ?? 0;
            $total    = $pending + $approved;
            $pct      = $total > 0 ? round($approved / $total * 100) : 0;
        ?>
        <div class="col-6 col-md-4 col-xl">
            <div class="stage-card" style="--card-color:<?= $cd['color'] ?>;--card-light:<?= $cd['light'] ?>;--card-text:<?= $cd['text'] ?>;">
                <div class="stage-card-head">
                    <span class="stage-card-icon"><i class="bi <?= $cd['icon'] ?>"></i></span>
                    <span class="stage-card-label"><?= $cd['label'] ?></span>
                </div>
                <div class="stage-card-counts">
                    <div class="scc-item scc-pending">
                        <span class="scc-num"><?= $pending ?></span>
                        <span class="scc-lbl">Pending</span>
                    </div>
                    <div class="scc-divider"></div>
                    <div class="scc-item scc-approved">
                        <span class="scc-num"><?= $approved ?></span>
                        <span class="scc-lbl">Approved</span>
                    </div>
                </div>
                <div class="stage-card-bar">
                    <div class="stage-card-bar-fill" style="width:<?= $pct ?>%"></div>
                </div>
                <div class="stage-card-total"><?= $total ?> total &nbsp;·&nbsp; <?= $pct ?>% approved</div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ==================== TOOLBAR ==================== -->
    <div class="toolbar-card d-flex flex-wrap gap-2 align-items-center">
        <form method="GET" class="d-flex gap-2 flex-grow-1" style="max-width:480px;">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0 text-muted">
                    <i class="bi bi-search"></i>
                </span>
                <input type="text" name="search" class="form-control border-start-0 ps-0"
                       placeholder="Search by Name, Department or Emp ID"
                       value="<?= htmlspecialchars($search) ?>">
            </div>
            <button type="submit" class="btn btn-primary px-3">Search</button>
            <?php if (!empty($search)): ?>
                <a href="?" class="btn btn-outline-secondary px-3">
                    <i class="bi bi-x-lg"></i>
                </a>
            <?php endif; ?>
        </form>

        <div class="ms-auto d-flex gap-2 align-items-center">
            <!-- Per-page selector -->
            <form method="GET" class="d-flex align-items-center gap-1">
                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                <label class="text-muted small mb-0 text-nowrap">Rows:</label>
                <select name="per_page" class="form-select form-select-sm" style="width:70px;"
                        onchange="this.form.submit()">
                    <?php foreach ([10, 25, 50, 100] as $opt): ?>
                        <option value="<?= $opt ?>" <?= $perPage == $opt ? 'selected' : '' ?>><?= $opt ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <!-- Export CSV -->
            <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>"
               class="btn btn-export btn-sm px-3">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i>Export CSV
            </a>
        </div>
    </div>

    <!-- ==================== BULK ACTION BAR ==================== -->
    <div class="bulk-action-bar" id="bulkActionBar">
        <i class="bi bi-check2-all fs-5"></i>
        <span class="bulk-count-badge" id="bulkCountBadge">0 selected</span>
        <span class="small opacity-75">travel orders selected for bulk approval</span>
        <div class="ms-auto d-flex gap-2">
            <button type="button" class="btn-bulk-approve" id="bulkApproveBtn"
                    data-bs-toggle="modal" data-bs-target="#bulkApprovalModal">
                <i class="bi bi-check2-circle"></i> Bulk Approve
            </button>
            <button type="button" class="btn-bulk-clear" id="bulkClearBtn">
                <i class="bi bi-x-circle me-1"></i>Clear
            </button>
        </div>
    </div>

    <!-- ==================== TABLE ==================== -->
    <?php if ($totalRecords > 0): ?>
        <div class="table-card mb-4">
            <div class="table-responsive">
                <table class="table table-hover" id="travelTable">
                    <thead>
                        <tr>
                            <th class="check-col">
                                <input type="checkbox" id="selectAllCheckbox" title="Select all approvable rows">
                            </th>
                            <th style="width:40px">#</th>
                            <th>TADA No</th>
                            <th>BrCode</th>
                            <th>Branch / Dept</th>
                            <th>Emp ID</th>
                            <th>Employee</th>
                            <th>Travel From</th>
                            <th>Destination</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th style="width:60px">Days</th>
                            <th>Purpose</th>
                            <th>Type</th>
                            <th>Document</th>
                            <th>Status</th>
                            <th>Current Stage</th>
                            <th style="width:130px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $sn = $offset + 1; while ($row = $result->fetch_assoc()):
                            $requestType  = $row['request_type'] ?? 'Normal';
                            $empLevel     = strtoupper(trim((string)($row['emp_level'] ?? '')));
                            $chain        = getApprovalChain($empLevel, $row['BrCode'] ?? '', $requestType);
                            $currentStage = strtoupper(trim((string)($row['current_approval_stage'] ?? ($chain[0] ?? 'PH'))));
                            $canApprove   = canApproveStage($approver_level_val, $currentStage);
                            $isFullyApproved = ($row['approval_ceo_status'] === 'Approved');
                        ?>
                        <tr data-id="<?= $row['id'] ?>"
                            data-stage="<?= htmlspecialchars($currentStage) ?>"
                            data-request-type="<?= htmlspecialchars($requestType) ?>"
                            data-tada="<?= htmlspecialchars($row['travel_order_no']) ?>"
                            data-name="<?= htmlspecialchars($row['employeeName']) ?>"
                            data-can-approve="<?= ($canApprove && !$isFullyApproved) ? '1' : '0' ?>">
                            <td class="check-col">
                                <?php if ($canApprove && !$isFullyApproved): ?>
                                    <input type="checkbox" class="row-checkbox"
                                           value="<?= $row['id'] ?>"
                                           data-stage="<?= htmlspecialchars($currentStage) ?>"
                                           data-request-type="<?= htmlspecialchars($requestType) ?>"
                                           data-tada="<?= htmlspecialchars($row['travel_order_no']) ?>"
                                           data-name="<?= htmlspecialchars($row['employeeName']) ?>">
                                <?php else: ?>
                                    <span class="text-muted" title="<?= $isFullyApproved ? 'Already approved' : 'Not your stage' ?>">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted small"><?= $sn++ ?></td>
                            <td><span class="tada-no"><?= htmlspecialchars($row['travel_order_no']) ?></span></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($row['BrCode']) ?></span></td>
                            <td><?= htmlspecialchars($row['department']) ?></td>
                            <td class="text-muted small"><?= htmlspecialchars($row['EmpID']) ?></td>
                            <td><strong><?= htmlspecialchars($row['employeeName']) ?></strong></td>
                            <td><?= htmlspecialchars($row['travelFrom']) ?></td>
                            <td><?= htmlspecialchars($row['destination']) ?></td>
                            <td><?= htmlspecialchars($row['travelDateFrom']) ?></td>
                            <td><?= htmlspecialchars($row['travelDateTo']) ?></td>
                            <td class="text-center"><?= htmlspecialchars($row['noOfDays']) ?></td>
                            <td style="max-width:180px; white-space:normal; font-size:.8rem;">
                                <?= htmlspecialchars($row['purpose']) ?>
                            </td>
                            <td>
                                <span class="badge <?= strtolower($requestType) === 'claim' ? 'bg-danger' : 'bg-primary' ?>">
                                    <?= htmlspecialchars($requestType) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($row['document_path'])): ?>
                                    <button class="btn btn-sm btn-outline-primary view-doc-btn py-0 px-2"
                                            data-doc="<?= htmlspecialchars($row['document_path']) ?>"
                                            data-bs-toggle="modal" data-bs-target="#documentModal">
                                        <i class="bi bi-file-earmark-text me-1"></i>View
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge <?= $isFullyApproved ? 'status-approved' : 'status-pending' ?>">
                                    <?= $isFullyApproved ? '✓ APPROVED' : '⏳ PENDING' ?>
                                </span>
                            </td>
                            <td>
                                <span class="stage-chip"><?= stageLabel($currentStage) ?></span>
                            </td>
                            <td>
                                <div class="action-wrap">
                                    <button type="button" class="btn btn-sm btn-outline-info py-0 details-btn"
                                            data-id="<?= htmlspecialchars($row['id']) ?>">
                                        <i class="bi bi-eye me-1"></i>Details
                                    </button>

                                    <?php if (!$isFullyApproved): ?>
                                        <button class="btn btn-sm py-0 <?= $canApprove ? 'btn-approve' : 'btn-outline-secondary' ?> approve-btn"
                                                data-bs-toggle="modal"
                                                data-bs-target="#approvalModal"
                                                data-id="<?= $row['id'] ?>"
                                                data-stage="<?= htmlspecialchars($currentStage) ?>"
                                                data-request-type="<?= htmlspecialchars($requestType) ?>"
                                                data-can-approve="<?= $canApprove ? '1' : '0' ?>">
                                            <?php if ($canApprove): ?>
                                                <i class="bi bi-check2-circle me-1"></i>Approve
                                            <?php else: ?>
                                                <i class="bi bi-lock me-1"></i>Not Your Stage
                                            <?php endif; ?>
                                        </button>
                                    <?php endif; ?>

                                    <button class="btn btn-sm btn-outline-secondary py-0 edit-dates-btn"
                                            data-id="<?= $row['id'] ?>"
                                            data-from="<?= htmlspecialchars($row['travelDateFrom']) ?>"
                                            data-to="<?= htmlspecialchars($row['travelDateTo']) ?>"
                                            data-days="<?= htmlspecialchars($row['noOfDays']) ?>"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editDatesModal">
                                        <i class="bi bi-pencil me-1"></i>Edit
                                    </button>

                                    <button class="btn btn-sm btn-outline-warning py-0"
                                            data-bs-toggle="modal"
                                            data-bs-target="#printModal"
                                            data-id="<?= $row['id'] ?>">
                                        <i class="bi bi-printer me-1"></i>Print
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <div class="table-card mb-4">
            <div class="empty-state">
                <i class="bi bi-inbox text-muted"></i>
                <h5 class="text-muted">No Travel Orders Found</h5>
                <p class="text-muted small mb-0">
                    <?= !empty($search) ? 'No records match your search. Try a different keyword.' : 'There are no travel orders pending at your approval stage.' ?>
                </p>
                <?php if (!empty($search)): ?>
                    <a href="?" class="btn btn-outline-secondary btn-sm mt-3">
                        <i class="bi bi-arrow-left me-1"></i>Clear Search
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- ==================== PAGINATION ==================== -->
    <?php
    $totalPages  = max(1, (int) ceil($totalRecords / $perPage));
    $currentPage = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    $currentPage = max(1, min($currentPage, $totalPages));
    $from = $totalRecords > 0 ? $offset + 1 : 0;
    $to   = min($offset + $perPage, $totalRecords);
    $queryParams = $_GET;
    ?>
    <div class="pagination-wrapper mb-4">
        <div class="pag-container">
            <div class="pag-info">
                Showing <?= $from ?> to <?= $to ?> of <?= $totalRecords ?> records
            </div>
            <div class="pag-controls">
                <?php $queryParams['page'] = max(1, $currentPage - 1); ?>
                <a class="pag-link <?= $currentPage <= 1 ? 'disabled' : '' ?>"
                   href="<?= $currentPage <= 1 ? 'javascript:void(0)' : '?' . http_build_query($queryParams) ?>">
                    Previous
                </a>
                <div class="pag-numbers">
                    <?php
                    $start = max(1, $currentPage - 2);
                    $end   = min($totalPages, $currentPage + 2);
                    ?>
                    <?php if ($start > 1): ?>
                        <?php $queryParams['page'] = 1; ?>
                        <a class="pag-num" href="?<?= http_build_query($queryParams) ?>">1</a>
                        <?php if ($start > 2): ?><span class="pag-ellipsis">...</span><?php endif; ?>
                    <?php endif; ?>
                    <?php for ($p = $start; $p <= $end; $p++): ?>
                        <?php $queryParams['page'] = $p; ?>
                        <a class="pag-num <?= $p == $currentPage ? 'pag-num-active' : '' ?>"
                           href="?<?= http_build_query($queryParams) ?>"><?= $p ?></a>
                    <?php endfor; ?>
                    <?php if ($end < $totalPages): ?>
                        <?php if ($end < $totalPages - 1): ?><span class="pag-ellipsis">...</span><?php endif; ?>
                        <?php $queryParams['page'] = $totalPages; ?>
                        <a class="pag-num" href="?<?= http_build_query($queryParams) ?>"><?= $totalPages ?></a>
                    <?php endif; ?>
                </div>
                <?php $queryParams['page'] = min($totalPages, $currentPage + 1); ?>
                <a class="pag-link <?= $currentPage >= $totalPages ? 'disabled' : '' ?>"
                   href="<?= $currentPage >= $totalPages ? 'javascript:void(0)' : '?' . http_build_query($queryParams) ?>">
                    Next
                </a>
            </div>
        </div>
    </div>
</div>

<!-- ==================== APPROVAL MODAL ==================== -->
<div class="modal fade" id="approvalModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header" style="background:var(--brand-dark); color:#fff;">
                <h5 class="modal-title"><i class="bi bi-check2-circle me-2"></i>Approve Travel Order</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="approvalForm" method="POST" action="approve_travel_order.php">
                <div class="modal-body">
                    <input type="hidden" name="id"              id="approvalOrderId">
                    <input type="hidden" name="order_id"        id="approvalOrderIdCompat">
                    <input type="hidden" name="travel_order_id" id="approvalTravelOrderIdCompat">
                    <input type="hidden" name="approval_stage"  id="approvalStage">
                    <input type="hidden" name="stage"           id="approvalStageCompat">
                    <input type="hidden" name="current_stage"   id="approvalCurrentStageCompat">
                    <input type="hidden" name="request_type"    id="approvalRequestType">

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-muted small">APPROVER NAME</label>
                        <input type="text" name="approver_name" class="form-control"
                               value="<?= htmlspecialchars($approver_name_val) ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-muted small">APPROVER EMAIL</label>
                        <input type="email" name="approver_email" class="form-control"
                               value="<?= htmlspecialchars($approver_email_val) ?>" readonly required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-muted small">APPROVER LEVEL</label>
                        <input type="text" name="approver_level" id="approverLevel" class="form-control"
                               value="<?= htmlspecialchars($approver_level_val) ?>" readonly required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-muted small">REMARKS <span class="text-secondary">(Optional)</span></label>
                        <textarea name="remarks" id="remarksField" class="form-control" rows="3"
                                  placeholder="Add your comments here..."></textarea>
                    </div>
                    <div id="nextStageInfo" class="alert alert-info small py-2 mb-0"></div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success px-4" id="submitBtn">
                        <i class="bi bi-check2-circle me-1"></i>Confirm & Approve
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==================== BULK APPROVAL MODAL ==================== -->
<div class="modal fade" id="bulkApprovalModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header" style="background: linear-gradient(135deg,#1e3a8a,#1a56db); color:#fff;">
                <h5 class="modal-title">
                    <i class="bi bi-check2-all me-2"></i>Bulk Approve Travel Orders
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Approver info row -->
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small">APPROVER</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($approver_name_val) ?>" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small">LEVEL</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($roleDisplayLabel) ?>" readonly>
                    </div>
                </div>

                <!-- Summary list -->
                <label class="form-label fw-semibold text-muted small">SELECTED TRAVEL ORDERS</label>
                <div class="bulk-summary-list mb-3" id="bulkSummaryList">
                    <!-- populated by JS -->
                </div>

                <!-- Shared remarks -->
                <div class="mb-3">
                    <label class="form-label fw-semibold text-muted small">
                        REMARKS <span class="text-secondary">(Optional — applied to all)</span>
                    </label>
                    <textarea id="bulkRemarksField" class="form-control" rows="3"
                              placeholder="Add shared comments for all selected orders..."></textarea>
                </div>

                <!-- Progress bar (shown during processing) -->
                <div class="bulk-progress-wrap" id="bulkProgressWrap">
                    <div class="d-flex justify-content-between small text-muted mb-1">
                        <span id="bulkProgressLabel">Processing...</span>
                        <span id="bulkProgressCount">0 / 0</span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-success"
                             id="bulkProgressBar" style="width:0%"></div>
                    </div>
                </div>

                <!-- Result summary (shown after processing) -->
                <div id="bulkResultSummary" class="d-none">
                    <div class="alert alert-success py-2 mb-1" id="bulkResultSuccess"></div>
                    <div class="alert alert-danger py-2 mb-0 d-none" id="bulkResultError"></div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="bulkCancelBtn">Cancel</button>
                <button type="button" class="btn btn-success px-4" id="bulkConfirmBtn">
                    <i class="bi bi-check2-all me-1"></i>Approve All Selected
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== MESSAGE POPUP MODAL ==================== -->
<div class="modal fade" id="messageModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header" id="messageModalHeader">
                <h5 class="modal-title" id="messageModalTitle">Message</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="messageModalBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== DOCUMENT MODAL ==================== -->
<div class="modal fade" id="documentModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-file-earmark-text me-2"></i>Document Viewer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" style="height: 85vh;">
                <iframe id="docFrame" src="" style="width:100%; height:100%; border:none;"></iframe>
            </div>
        </div>
    </div>
</div>

<!-- ==================== DETAILS MODAL ==================== -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-info-circle me-2"></i>Travel Order Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailsContent">
                <div class="text-center py-5"><div class="spinner-border text-primary"></div></div>
            </div>
        </div>
    </div>
</div>

<!-- ==================== PRINT MODAL ==================== -->
<div class="modal fade" id="printModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-printer me-2"></i>Print Travel Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="printContent">
                <div class="text-center py-5"><div class="spinner-border text-primary"></div></div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="printModalContent()">
                    <i class="bi bi-printer me-1"></i>Print
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== EDIT DATES MODAL ==================== -->
<div class="modal fade" id="editDatesModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header" style="background:#f59e0b; color:#fff;">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Travel Dates</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editDatesForm">
                <div class="modal-body">
                    <input type="hidden" name="id" id="editOrderId">
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold text-muted small">START DATE</label>
                            <input type="date" name="travelDateFrom" id="editDateFrom" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold text-muted small">END DATE</label>
                            <input type="date" name="travelDateTo" id="editDateTo" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-muted small">NUMBER OF DAYS</label>
                        <div class="input-group">
                            <input type="number" name="noOfDays" id="editNoOfDays" class="form-control" min="1" required>
                            <span class="input-group-text text-muted">days</span>
                        </div>
                        <div class="form-text">Auto-calculated from dates, or override manually.</div>
                    </div>
                    <div id="editDateInfo" class="alert alert-info small py-2 d-none"></div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn px-4 text-white" id="editSaveBtn"
                            style="background:#f59e0b; border:none;">
                        <i class="bi bi-save me-1"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const loggedInApproverLevel = <?= json_encode($approver_level_val) ?>;
const approverName          = <?= json_encode($approver_name_val) ?>;
const approverEmail         = <?= json_encode($approver_email_val) ?>;

// ==================== STAGE HELPERS ====================
function stageLabel(stage) {
    const map = {
        'DH':'Department Head','BM':'Branch Manager','PH':'Province Head',
        'CD':'Claim Head','NSM':'NSM','HR':'Human Resource',
        'CEO_OR_DCEO':'CEO / DCEO','CEO':'CEO','DCEO':'DCEO'
    };
    return map[String(stage || '').trim().toUpperCase()] || stage;
}

function getApprovalChain(level, brCode, requestType = 'Normal') {
    level       = String(level       || '').trim().toUpperCase();
    requestType = String(requestType || '').trim().toLowerCase();
    if (brCode === '100' && requestType === 'claim') {
        if (level === 'PH')  return ['NSM', 'HR', 'CEO_OR_DCEO'];
        if (level === 'NSM') return ['HR', 'CEO_OR_DCEO'];
        return ['CD', 'HR', 'CEO_OR_DCEO'];
    }
    if (brCode === '100') {
        if (level === 'PH')  return ['NSM', 'HR', 'CEO_OR_DCEO'];
        if (level === 'NSM') return ['HR', 'CEO_OR_DCEO'];
        return ['DH', 'HR', 'CEO_OR_DCEO'];
    }
    switch (level) {
        case 'ST':          return ['BM', 'PH', 'NSM', 'HR', 'CEO_OR_DCEO'];
        case 'BM':          return ['PH', 'NSM', 'HR', 'CEO_OR_DCEO'];
        case 'PH':          return ['NSM', 'HR', 'CEO_OR_DCEO'];
        case 'NSM':         return ['HR', 'CEO_OR_DCEO'];
        case 'DH':          return ['HR', 'CEO_OR_DCEO'];
        case 'CD':          return ['HR', 'CEO_OR_DCEO'];
        case 'HR':          return ['CEO_OR_DCEO'];
        case 'CEO': case 'DCEO': case 'CEO_OR_DCEO': return ['CEO_OR_DCEO'];
        default:            return ['PH', 'NSM', 'HR', 'CEO_OR_DCEO'];
    }
}

function canApproveStage(approverLevel, currentStage) {
    approverLevel = String(approverLevel || '').trim().toUpperCase();
    currentStage  = String(currentStage  || '').trim().toUpperCase();
    if (currentStage === 'CEO_OR_DCEO') {
        return ['CEO', 'DCEO', 'CEO_OR_DCEO'].includes(approverLevel);
    }
    return approverLevel === currentStage;
}

function getNextStage(current, level, brCode, requestType = 'Normal') {
    const chain = getApprovalChain(level, brCode, requestType);
    const index = chain.indexOf(String(current || '').trim().toUpperCase());
    return (index !== -1 && index < chain.length - 1) ? chain[index + 1] : null;
}

// ==================== BULK SELECTION ====================
const bulkActionBar  = document.getElementById('bulkActionBar');
const bulkCountBadge = document.getElementById('bulkCountBadge');

function getSelectedCheckboxes() {
    return [...document.querySelectorAll('.row-checkbox:checked')];
}

function updateBulkBar() {
    const checked = getSelectedCheckboxes();
    if (checked.length > 0) {
        bulkActionBar.classList.add('active');
        bulkCountBadge.textContent = `${checked.length} selected`;
    } else {
        bulkActionBar.classList.remove('active');
    }
    // Sync select-all state
    const allCheckboxes = document.querySelectorAll('.row-checkbox');
    const selectAll     = document.getElementById('selectAllCheckbox');
    if (allCheckboxes.length > 0) {
        selectAll.indeterminate = checked.length > 0 && checked.length < allCheckboxes.length;
        selectAll.checked       = checked.length === allCheckboxes.length;
    }
    // Highlight selected rows
    document.querySelectorAll('tr[data-id]').forEach(tr => {
        const cb = tr.querySelector('.row-checkbox');
        tr.classList.toggle('row-selected', cb ? cb.checked : false);
    });
}

document.querySelectorAll('.row-checkbox').forEach(cb => {
    cb.addEventListener('change', updateBulkBar);
});

document.getElementById('selectAllCheckbox').addEventListener('change', function () {
    document.querySelectorAll('.row-checkbox').forEach(cb => {
        cb.checked = this.checked;
    });
    updateBulkBar();
});

document.getElementById('bulkClearBtn').addEventListener('click', () => {
    document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = false);
    document.getElementById('selectAllCheckbox').checked = false;
    updateBulkBar();
});

// ==================== BULK APPROVAL MODAL OPEN ====================
document.getElementById('bulkApprovalModal').addEventListener('show.bs.modal', () => {
    const checked = getSelectedCheckboxes();
    const list    = document.getElementById('bulkSummaryList');
    list.innerHTML = '';

    if (checked.length === 0) { list.innerHTML = '<div class="p-3 text-muted small">No orders selected.</div>'; return; }

    checked.forEach((cb, i) => {
        const div = document.createElement('div');
        div.className = 'bulk-summary-item';
        div.innerHTML = `
            <span class="text-muted small">${i + 1}.</span>
            <span class="tada">${cb.dataset.tada || cb.value}</span>
            <span class="flex-grow-1 text-truncate">${cb.dataset.name || '—'}</span>
            <span class="stage-chip" style="font-size:.68rem;">${stageLabel(cb.dataset.stage)}</span>`;
        list.appendChild(div);
    });

    // Reset progress/result areas
    document.getElementById('bulkProgressWrap').style.display = 'none';
    document.getElementById('bulkProgressBar').style.width    = '0%';
    document.getElementById('bulkResultSummary').classList.add('d-none');
    document.getElementById('bulkRemarksField').value = '';
    document.getElementById('bulkConfirmBtn').disabled = false;
    document.getElementById('bulkCancelBtn').textContent = 'Cancel';
    document.getElementById('bulkConfirmBtn').innerHTML = '<i class="bi bi-check2-all me-1"></i>Approve All Selected';
});

// ==================== BULK APPROVE SUBMIT ====================
document.getElementById('bulkConfirmBtn').addEventListener('click', async function () {
    const checked = getSelectedCheckboxes();
    if (checked.length === 0) return;

    const remarks  = document.getElementById('bulkRemarksField').value.trim();
    const confirmBtn = this;
    const cancelBtn  = document.getElementById('bulkCancelBtn');

    confirmBtn.disabled = true;
    confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Processing...';
    cancelBtn.disabled   = true;

    const progressWrap  = document.getElementById('bulkProgressWrap');
    const progressBar   = document.getElementById('bulkProgressBar');
    const progressLabel = document.getElementById('bulkProgressLabel');
    const progressCount = document.getElementById('bulkProgressCount');
    progressWrap.style.display = 'block';

    let successCount = 0;
    let failCount    = 0;
    const failMessages = [];
    const total = checked.length;

    for (let i = 0; i < total; i++) {
        const cb    = checked[i];
        const id    = cb.value;
        const stage = cb.dataset.stage;
        const reqType = cb.dataset.requestType || 'Normal';

        progressLabel.textContent = `Approving ${cb.dataset.tada || id}...`;
        progressCount.textContent = `${i} / ${total}`;
        progressBar.style.width   = `${Math.round((i / total) * 100)}%`;

        const formData = new FormData();
        formData.append('id',              id);
        formData.append('order_id',        id);
        formData.append('travel_order_id', id);
        formData.append('approval_stage',  stage);
        formData.append('stage',           stage);
        formData.append('current_stage',   stage);
        formData.append('request_type',    reqType);
        formData.append('approver_name',   approverName);
        formData.append('approver_email',  approverEmail);
        formData.append('approver_level',  loggedInApproverLevel);
        formData.append('remarks',         remarks);

        try {
            const response = await fetch('approve_travel_order.php', { method: 'POST', body: formData });
            const text     = await response.text();
            let data;
            try { data = JSON.parse(text); } catch (e) { throw new Error('Invalid JSON: ' + text.substring(0, 80)); }

            if (data.success) {
                successCount++;
            } else {
                failCount++;
                failMessages.push(`${cb.dataset.tada || id}: ${data.message || 'Failed'}`);
            }
        } catch (err) {
            failCount++;
            failMessages.push(`${cb.dataset.tada || id}: ${err.message}`);
        }
    }

    // Final progress
    progressBar.style.width       = '100%';
    progressBar.classList.remove('progress-bar-animated');
    progressLabel.textContent     = 'Done!';
    progressCount.textContent     = `${total} / ${total}`;

    // Show result summary
    const resultWrap    = document.getElementById('bulkResultSummary');
    const resultSuccess = document.getElementById('bulkResultSuccess');
    const resultError   = document.getElementById('bulkResultError');
    resultWrap.classList.remove('d-none');

    resultSuccess.textContent = `✓ ${successCount} of ${total} travel order${total > 1 ? 's' : ''} approved successfully.`;

    if (failCount > 0) {
        resultError.classList.remove('d-none');
        resultError.innerHTML = `<strong>${failCount} failed:</strong><br>` +
            failMessages.map(m => `• ${m}`).join('<br>');
    } else {
        resultError.classList.add('d-none');
    }

    confirmBtn.innerHTML = '<i class="bi bi-check2-all me-1"></i>Done';
    cancelBtn.disabled   = false;
    cancelBtn.textContent = 'Close';

    if (successCount > 0) {
        setTimeout(() => location.reload(), 1800);
    }
});

// ==================== DOCUMENT VIEWER ====================
document.querySelectorAll('.view-doc-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('docFrame').src = btn.dataset.doc;
    });
});

// ==================== APPROVE BUTTON CLICK ====================
document.querySelectorAll('.approve-btn').forEach(button => {
    button.addEventListener('click', function () {
        const id          = this.getAttribute('data-id');
        const stage       = this.getAttribute('data-stage');
        const requestType = this.getAttribute('data-request-type') || 'Normal';

        document.getElementById('approvalOrderId').value             = id;
        document.getElementById('approvalOrderIdCompat').value       = id;
        document.getElementById('approvalTravelOrderIdCompat').value = id;
        document.getElementById('approvalStage').value               = stage;
        document.getElementById('approvalStageCompat').value         = stage;
        document.getElementById('approvalCurrentStageCompat').value  = stage;
        document.getElementById('approvalRequestType').value         = requestType;
        document.getElementById('remarksField').value                = '';
        document.getElementById('nextStageInfo').innerHTML           = 'Loading next approval stage...';
        document.getElementById('submitBtn').disabled                = false;

        fetch(`get_travel_order.php?id=${id}`)
            .then(r => r.json())
            .then(data => {
                const dbRequestType = String(data.request_type || requestType || 'Normal');
                const empLevel      = String(data.level || '').trim().toUpperCase();
                const chain         = getApprovalChain(empLevel, data.BrCode, dbRequestType);
                const currentStage  = String(data.current_approval_stage || stage).trim().toUpperCase();
                const approverAllowed = canApproveStage(loggedInApproverLevel, currentStage);

                if (!chain.includes(currentStage)) {
                    document.getElementById('submitBtn').disabled = true;
                    document.getElementById('nextStageInfo').innerHTML = `
                        <div class="alert alert-danger mb-0 py-2">
                            Invalid approval stage: <strong>${stageLabel(currentStage)}</strong><br>
                            Expected route: <strong>${chain.map(stageLabel).join(' → ')}</strong>
                        </div>`;
                    showPopup('Invalid Approval Stage',
                        `Current stage <strong>${stageLabel(currentStage)}</strong> is not in the valid route.`, 'danger');
                    return;
                }

                if (!approverAllowed) {
                    document.getElementById('submitBtn').disabled = true;
                    document.getElementById('nextStageInfo').innerHTML = `
                        <div class="alert alert-warning mb-0 py-2">
                            You cannot approve this request.<br>
                            Your level: <strong>${stageLabel(loggedInApproverLevel || '-')}</strong><br>
                            Required level: <strong>${stageLabel(currentStage)}</strong>
                        </div>`;
                    showPopup('Not Authorized for This Stage',
                        `Your level is <strong>${stageLabel(loggedInApproverLevel || '-')}</strong>. This stage requires <strong>${stageLabel(currentStage)}</strong>.`, 'danger');
                    return;
                }

                const nextStage = getNextStage(currentStage, empLevel, data.BrCode, dbRequestType);
                document.getElementById('nextStageInfo').innerHTML = nextStage
                    ? `<i class="bi bi-arrow-right-circle me-1"></i>After approval, will be forwarded to: <strong>${stageLabel(nextStage)}</strong>`
                    : `<i class="bi bi-trophy me-1"></i><strong>This is the final approval stage.</strong>`;
            })
            .catch(() => {
                document.getElementById('submitBtn').disabled = true;
                document.getElementById('nextStageInfo').innerHTML =
                    '<div class="alert alert-danger mb-0 py-2">Could not load order details.</div>';
                showPopup('Approval Error', 'Could not load order details. Please refresh and try again.', 'danger');
            });
    });
});

// ==================== SINGLE FORM SUBMIT ====================
document.getElementById('approvalForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const submitBtn    = document.getElementById('submitBtn');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Processing...';
    submitBtn.disabled  = true;

    const formData = new FormData(this);
    if (!formData.get('id') || !formData.get('approval_stage')) {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled  = false;
        showPopup('Approval Failed', 'Missing order ID or approval stage.', 'danger');
        return;
    }

    fetch('approve_travel_order.php', { method: 'POST', body: formData })
        .then(async response => {
            const text = await response.text();
            try { return JSON.parse(text); }
            catch (e) { throw new Error('Invalid JSON: ' + text); }
        })
        .then(data => {
            if (data.success) {
                showPopup('Approved', 'Travel Order Approved Successfully!', 'success');
                setTimeout(() => location.reload(), 1200);
            } else {
                showPopup('Approval Failed', data.message || 'Approval failed', 'danger');
            }
        })
        .catch(error => {
            console.error(error);
            showPopup('Approval Error', 'Check browser console for details.', 'danger');
        })
        .finally(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled  = false;
        });
});

// ==================== POPUP ====================
function showPopup(title, message, type = 'info') {
    const header  = document.getElementById('messageModalHeader');
    const titleEl = document.getElementById('messageModalTitle');
    const bodyEl  = document.getElementById('messageModalBody');
    header.className = 'modal-header';
    if (type === 'success')     header.classList.add('bg-success', 'text-white');
    else if (type === 'danger') header.classList.add('bg-danger',  'text-white');
    else                        header.classList.add('bg-primary', 'text-white');
    titleEl.innerHTML = title;
    bodyEl.innerHTML  = message;
    new bootstrap.Modal(document.getElementById('messageModal')).show();
}

// ==================== DETAILS MODAL ====================
document.querySelectorAll('.details-btn').forEach(button => {
    button.addEventListener('click', function () {
        const id = this.dataset.id;
        const detailsContent = document.getElementById('detailsContent');
        detailsContent.innerHTML = `<div class="text-center py-5">
            <div class="spinner-border text-primary"></div>
            <div class="mt-2 text-muted">Loading details...</div></div>`;
        new bootstrap.Modal(document.getElementById('detailsModal')).show();
        fetch('view_travel_order_details.php?id=' + encodeURIComponent(id))
            .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.text(); })
            .then(html => {
                detailsContent.innerHTML = html.trim()
                    ? html
                    : '<div class="alert alert-warning">Details page returned empty content.</div>';
            })
            .catch(err => {
                detailsContent.innerHTML =
                    `<div class="alert alert-danger">Failed to load details: ${err.message}</div>`;
            });
    });
});

// ==================== PRINT MODAL ====================
document.querySelectorAll('[data-bs-target="#printModal"]').forEach(button => {
    button.addEventListener('click', function () {
        const id = this.getAttribute('data-id');
        const printContent = document.getElementById('printContent');
        printContent.innerHTML = `<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>`;
        fetch(`print_travel_order.php?id=${encodeURIComponent(id)}`)
            .then(r => r.text())
            .then(html => { printContent.innerHTML = html; })
            .catch(() => {
                printContent.innerHTML = `<div class="alert alert-danger">Could not load print preview.</div>`;
            });
    });
});

function printModalContent() {
    const content = document.getElementById('printContent').innerHTML;
    const w = window.open('', '', 'width=1000,height=800');
    w.document.write(`<html><head><title>Print Travel Order</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        </head><body>${content}</body></html>`);
    w.document.close();
    w.focus();
    w.print();
    w.close();
}

// ==================== EDIT DATES MODAL ====================
document.querySelectorAll('.edit-dates-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        document.getElementById('editOrderId').value  = this.dataset.id;
        document.getElementById('editDateFrom').value = this.dataset.from;
        document.getElementById('editDateTo').value   = this.dataset.to;
        document.getElementById('editNoOfDays').value = this.dataset.days;
        document.getElementById('editDateInfo').classList.add('d-none');
    });
});

function recalcDays() {
    const from = document.getElementById('editDateFrom').value;
    const to   = document.getElementById('editDateTo').value;
    const info = document.getElementById('editDateInfo');
    if (from && to) {
        const diff = Math.round((new Date(to) - new Date(from)) / (1000 * 60 * 60 * 24)) + 1;
        if (diff > 0) {
            document.getElementById('editNoOfDays').value = diff;
            info.className = 'alert alert-info small py-2';
            info.innerHTML = `<i class="bi bi-calendar-check me-1"></i>Auto-calculated: <strong>${diff} day${diff > 1 ? 's' : ''}</strong>`;
        } else {
            document.getElementById('editNoOfDays').value = '';
            info.className = 'alert alert-warning small py-2';
            info.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i>End date must be on or after start date.';
        }
    }
}

document.getElementById('editDateFrom').addEventListener('change', recalcDays);
document.getElementById('editDateTo').addEventListener('change', recalcDays);

document.getElementById('editDatesForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const btn  = document.getElementById('editSaveBtn');
    const orig = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';
    btn.disabled  = true;

    fetch('update_travel_order_dates.php', { method: 'POST', body: new FormData(this) })
        .then(async r => {
            const text = await r.text();
            try { return JSON.parse(text); }
            catch (_) { throw new Error('Invalid response: ' + text); }
        })
        .then(data => {
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('editDatesModal')).hide();
                showPopup('Updated', 'Travel dates updated successfully!', 'success');
                setTimeout(() => location.reload(), 1200);
            } else {
                showPopup('Update Failed', data.message || 'Could not save changes.', 'danger');
            }
        })
        .catch(err => {
            console.error(err);
            showPopup('Error', 'Could not save changes. Check console for details.', 'danger');
        })
        .finally(() => {
            btn.innerHTML = orig;
            btn.disabled  = false;
        });
});
</script>
</body>
</html>