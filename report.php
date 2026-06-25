<?php
require_once 'auth.php';
requireLogin();

$host     = 'localhost';
$dbname   = 'hr';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// ── Filters ──────────────────────────────────────────────────────────────
$startDate = $_GET['start_date'] ?? '';
$endDate   = $_GET['end_date']   ?? '';
$employee  = trim($_GET['employee'] ?? '');
$brcode    = trim($_GET['brcode']   ?? '');

$whereClauses = [];
$params       = [];

if ($startDate) { $whereClauses[] = "travelDateFrom >= :start_date"; $params[':start_date'] = $startDate; }
if ($endDate)   { $whereClauses[] = "travelDateTo   <= :end_date";   $params[':end_date']   = $endDate;   }
if ($employee)  { $whereClauses[] = "employeeName LIKE :employee";   $params[':employee']   = "%$employee%"; }
if ($brcode)    { $whereClauses[] = "BrCode = :brcode";              $params[':brcode']     = $brcode;    }

$expWhere  = [];
$expParams = [];
if ($startDate) { $expWhere[] = "from_date >= :start_date"; $expParams[':start_date'] = $startDate; }
if ($endDate)   { $expWhere[] = "to_date   <= :end_date";   $expParams[':end_date']   = $endDate;   }
if ($employee)  { $expWhere[] = "name LIKE :employee";      $expParams[':employee']   = "%$employee%"; }

$orderWhere  = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';
$expWhereSQL = $expWhere     ? 'WHERE ' . implode(' AND ', $expWhere)     : '';

// ── Employee dropdown list ─────────────────────────────────────────────────
$empListStmt = $pdo->query("
    SELECT DISTINCT employeeName AS emp_name FROM travel_orders WHERE employeeName IS NOT NULL AND employeeName != ''
    UNION
    SELECT DISTINCT name AS emp_name FROM travel_expenses WHERE name IS NOT NULL AND name != ''
    ORDER BY emp_name ASC
");
$employeeList = $empListStmt->fetchAll(PDO::FETCH_COLUMN);

// ── Summary ───────────────────────────────────────────────────────────────
$summaryStmt = $pdo->prepare("SELECT COUNT(*) AS order_count, COALESCE(SUM(estimatedCost),0) AS total_estimated FROM travel_orders $orderWhere");
$summaryStmt->execute($params);
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);

$expenseStmt = $pdo->prepare("SELECT COUNT(*) AS expense_count, COALESCE(SUM(fare+airport+road_tax+hotel+other_exp+(daily_rate*days)-advance),0) AS total_expense FROM travel_expenses $expWhereSQL");
$expenseStmt->execute($expParams);
$expenseSummary = $expenseStmt->fetch(PDO::FETCH_ASSOC);

// ── Approved vs Pending ───────────────────────────────────────────────────
$approvedStmt = $pdo->prepare("SELECT
    SUM(CASE WHEN approval_ceo_status='Approved' THEN 1 ELSE 0 END) AS approved,
    SUM(CASE WHEN approval_ceo_status!='Approved' OR approval_ceo_status IS NULL THEN 1 ELSE 0 END) AS pending
    FROM travel_orders $orderWhere");
$approvedStmt->execute($params);
$approvalCounts = $approvedStmt->fetch(PDO::FETCH_ASSOC);

// ── Monthly trend ─────────────────────────────────────────────────────────
$monthlyStmt = $pdo->query("
    SELECT DATE_FORMAT(travelDateFrom,'%b %Y') AS month_label,
           DATE_FORMAT(travelDateFrom,'%Y-%m') AS month_key,
           COUNT(*) AS cnt,
           COALESCE(SUM(estimatedCost),0) AS total
    FROM travel_orders
    WHERE travelDateFrom >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month_key, month_label
    ORDER BY month_key ASC LIMIT 6
");
$monthlyData = $monthlyStmt->fetchAll(PDO::FETCH_ASSOC);

// ── Top 5 employees ───────────────────────────────────────────────────────
$topEmpStmt = $pdo->query("
    SELECT name, COALESCE(SUM(fare+airport+road_tax+hotel+other_exp+(daily_rate*days)-advance),0) AS total
    FROM travel_expenses GROUP BY name ORDER BY total DESC LIMIT 5
");
$topEmployees = $topEmpStmt->fetchAll(PDO::FETCH_ASSOC);

// ── Expense breakdown averages ────────────────────────────────────────────
$breakdownStmt = $pdo->query("
    SELECT
        COALESCE(AVG(fare+airport+road_tax),0) AS avg_transport,
        COALESCE(AVG(daily_rate*days),0)        AS avg_daily,
        COALESCE(AVG(hotel+other_exp),0)        AS avg_hotel
    FROM travel_expenses
");
$breakdown = $breakdownStmt->fetch(PDO::FETCH_ASSOC);

// ── Detailed lists ────────────────────────────────────────────────────────
$orderStmt = $pdo->prepare("SELECT * FROM travel_orders $orderWhere ORDER BY id DESC");
$orderStmt->execute($params);
$orders = $orderStmt->fetchAll(PDO::FETCH_ASSOC);

$expStmt = $pdo->prepare("SELECT * FROM travel_expenses $expWhereSQL ORDER BY id DESC");
$expStmt->execute($expParams);
$expenses = $expStmt->fetchAll(PDO::FETCH_ASSOC);

// ── Branch-wise count ─────────────────────────────────────────────────────
$branchStmt = $pdo->query("SELECT BrCode, COUNT(*) AS cnt FROM travel_orders GROUP BY BrCode ORDER BY cnt DESC LIMIT 8");
$branchData = $branchStmt->fetchAll(PDO::FETCH_ASSOC);

// JSON for charts — all values cast to numbers for safety
$monthLabels  = json_encode(array_column($monthlyData, 'month_label'));
$monthCounts  = json_encode(array_map('intval',   array_column($monthlyData, 'cnt')));
$monthTotals  = json_encode(array_map('floatval', array_column($monthlyData, 'total')));
$topEmpNames  = json_encode(array_column($topEmployees, 'name'));
$topEmpTotals = json_encode(array_map('floatval', array_column($topEmployees, 'total')));
$branchLabels = json_encode(array_column($branchData, 'BrCode'));
$branchCounts = json_encode(array_map('intval',   array_column($branchData, 'cnt')));
$breakdownAvg = json_encode([
    round((float)$breakdown['avg_transport'], 2),
    round((float)$breakdown['avg_daily'],     2),
    round((float)$breakdown['avg_hotel'],     2),
]);

// FIX: encode full data arrays as JSON — records stored in JS, not in HTML data attributes
// This avoids HTML attribute encoding issues with special characters in names/purposes
$ordersJson   = json_encode($orders,   JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
$expensesJson = json_encode($expenses, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Travel & Expense Analytics Report</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<style>
/* ══════════════════════════════════════════════════════════
   VARIABLES & RESET
══════════════════════════════════════════════════════════ */
:root {
    --navy:    #0a1628;
    --blue:    #1a56db;
    --accent:  #00d4aa;
    --gold:    #f59e0b;
    --danger:  #ef4444;
    --card-bg: #ffffff;
    --page-bg: #f0f4fb;
    --border:  #e2e8f0;
    --text:    #1e293b;
    --muted:   #64748b;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'DM Sans', sans-serif; background: var(--page-bg); color: var(--text); min-height: 100vh; }

/* ── Page header ── */
.report-header {
    background: linear-gradient(135deg, var(--navy) 0%, #1e3a8a 55%, #1a56db 100%);
    color: #fff; padding: 10px 40px 26px; position: relative; overflow: hidden;
}
.report-header::after  { content:''; position:absolute; right:-60px; top:-60px; width:320px; height:320px; border-radius:50%; background:rgba(0,212,170,.08); pointer-events:none; }
.report-header::before { content:''; position:absolute; right:80px; bottom:-80px; width:200px; height:200px; border-radius:50%; background:rgba(26,86,219,.15); pointer-events:none; }
.report-header h1 { font-family:'Sora',sans-serif; font-size:1.7rem; font-weight:800; letter-spacing:-.5px; }
.report-header .sub { font-size:.88rem; opacity:.75; margin-top:4px; }

/* ── Main wrap ── */
.page-wrap { max-width:1550px; margin:0 auto; padding:28px 24px 60px; }

/* ── Filter card ── */
.filter-card { background:var(--card-bg); border-radius:14px; padding:20px 24px; box-shadow:0 1px 8px rgba(0,0,0,.07); margin-bottom:26px; }
.filter-card .form-label { font-size:.78rem; font-weight:600; color:var(--muted); letter-spacing:.4px; text-transform:uppercase; }
.filter-card .form-control { font-size:.88rem; border-radius:8px; border-color:var(--border); }
.filter-card .form-control:focus { border-color:var(--blue); box-shadow:0 0 0 3px rgba(26,86,219,.12); }

/* ══════════════════════════════════════════════════════════
   KPI CARDS
══════════════════════════════════════════════════════════ */
.kpi-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:18px; margin-bottom:26px; }
.kpi-card {
    background:var(--card-bg); border-radius:16px; padding:22px 24px 44px;
    box-shadow:0 2px 12px rgba(0,0,0,.07); position:relative; overflow:hidden;
    border-left:4px solid transparent;
    transition:transform .18s, box-shadow .18s;
    cursor:pointer; user-select:none;
}
.kpi-card:hover { transform:translateY(-4px); box-shadow:0 10px 30px rgba(0,0,0,.14); }
.kpi-card:active { transform:translateY(-1px); }
.kpi-card.blue   { border-left-color:var(--blue);   }
.kpi-card.accent { border-left-color:var(--accent);  }
.kpi-card.gold   { border-left-color:var(--gold);   }
.kpi-card.red    { border-left-color:var(--danger);  }

.kpi-card .kpi-icon { width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.2rem; margin-bottom:14px; }
.kpi-card.blue   .kpi-icon { background:#eff6ff; color:var(--blue); }
.kpi-card.accent .kpi-icon { background:#f0fdf9; color:var(--accent); }
.kpi-card.gold   .kpi-icon { background:#fffbeb; color:var(--gold); }
.kpi-card.red    .kpi-icon { background:#fef2f2; color:var(--danger); }

.kpi-card .kpi-val      { font-family:'Sora',sans-serif; font-size:1.55rem; font-weight:800; line-height:1; }
.kpi-card .kpi-main     { font-family:'Sora',sans-serif; font-size:1.4rem; font-weight:800; line-height:1; color:var(--text); word-break:break-word; }
.kpi-card .kpi-lbl      { font-size:.78rem; color:var(--muted); margin-top:6px; font-weight:500; }
.kpi-card .kpi-sub-title{ font-size:.78rem; color:var(--muted); margin-top:6px; font-weight:500; }
.kpi-card .kpi-sub      { font-size:.75rem; color:var(--muted); margin-top:10px; padding-top:10px; border-top:1px solid var(--border); }

/* Slide-up hint bar */
.kpi-hint {
    position:absolute; bottom:0; left:0; right:0;
    color:#fff; font-size:.67rem; font-weight:700; letter-spacing:.5px;
    text-align:center; padding:6px 0; text-transform:uppercase;
    transform:translateY(100%); transition:transform .22s ease;
}
.kpi-card.blue   .kpi-hint { background:linear-gradient(90deg,var(--blue),#1e3a8a); }
.kpi-card.accent .kpi-hint { background:linear-gradient(90deg,#00a889,var(--accent)); }
.kpi-card.gold   .kpi-hint { background:linear-gradient(90deg,#d97706,var(--gold)); }
.kpi-card.red    .kpi-hint { background:linear-gradient(90deg,#dc2626,var(--danger)); }
.kpi-card:hover .kpi-hint  { transform:translateY(0); }

/* ── Section title ── */
.section-title { font-family:'Sora',sans-serif; font-size:1rem; font-weight:700; color:var(--navy); margin-bottom:16px; display:flex; align-items:center; gap:10px; }
.section-title::after { content:''; flex:2; height:2px; background:var(--border); border-radius:2px; }

/* ── Chart cards ── */
.chart-card { background:var(--card-bg); border-radius:16px; padding:22px 24px; box-shadow:0 2px 10px rgba(0,0,0,.07); height:100%; }
.chart-card .chart-title { font-family:'Sora',sans-serif; font-size:.9rem; font-weight:700; color:var(--navy); margin-bottom:16px; }
/* FIX: chart canvas container to prevent aspect-ratio distortion */
.chart-wrap { position:relative; width:100%; }
.chart-wrap-tall { height:260px; }
.chart-wrap-med  { height:220px; }

/* ── Data tables ── */
.data-card { background:var(--card-bg); border-radius:16px; box-shadow:0 2px 10px rgba(0,0,0,.07); overflow:hidden; margin-bottom:28px; }
.data-card-header { padding:16px 22px; background:var(--navy); color:#fff; display:flex; align-items:center; justify-content:space-between; }
.data-card-header .title { font-family:'Sora',sans-serif; font-size:.95rem; font-weight:700; }
.data-card-header .count-badge { background:rgba(255,255,255,.18); border-radius:20px; padding:3px 12px; font-size:.78rem; font-weight:600; }
.report-table { margin:0; font-size:.84rem; }
.report-table thead th { background:#f8fafc; color:var(--muted); font-weight:600; font-size:.75rem; text-transform:uppercase; letter-spacing:.5px; border-bottom:2px solid var(--border); padding:11px 14px; white-space:nowrap; text-align:left; }
.report-table tbody td { padding:10px 14px; vertical-align:middle; border-color:#f1f5f9; text-align:left; }
.report-table tbody tr:hover { background:#f8faff; }

/* ── Status badges ── */
.badge-approved { background:#d1fae5; color:#065f46; padding:4px 10px; border-radius:20px; font-size:.72rem; font-weight:700; white-space:nowrap; display:inline-block; }
.badge-pending  { background:#fef3c7; color:#92400e; padding:4px 10px; border-radius:20px; font-size:.72rem; font-weight:700; white-space:nowrap; display:inline-block; }

/* ── Row view button ── */
.btn-view { background:var(--blue); color:#fff; border:none; border-radius:8px; padding:5px 14px; font-size:.78rem; font-weight:600; cursor:pointer; transition:background .15s; }
.btn-view:hover { background:#1347c8; color:#fff; }

/* ══════════════════════════════════════════════════════════
   MODALS — SHARED
══════════════════════════════════════════════════════════ */
.modal-content { border:none; border-radius:18px; box-shadow:0 20px 60px rgba(0,0,0,.18); overflow:hidden; }
.modal-header  { padding:18px 24px; border:none; color:#fff; }
.modal-header .btn-close { filter:invert(1) opacity(.8); }
.modal-body    { padding:24px; max-height:75vh; overflow-y:auto; }
.modal-footer  { border-top:1px solid var(--border); padding:14px 24px; }

/* Header colour variants */
.hdr-blue   { background:linear-gradient(135deg,var(--blue),#1e3a8a) !important; }
.hdr-accent { background:linear-gradient(135deg,#00a889,var(--accent)) !important; }
.hdr-gold   { background:linear-gradient(135deg,#d97706,var(--gold)) !important; }
.hdr-red    { background:linear-gradient(135deg,#dc2626,var(--danger)) !important; }
.hdr-navy   { background:linear-gradient(135deg,var(--navy),#1e3a8a) !important; }

/* Detail grid (row view popups) */
.detail-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:20px; }
.detail-item label { font-size:.72rem; font-weight:600; color:var(--muted); text-transform:uppercase; letter-spacing:.4px; display:block; margin-bottom:3px; }
.detail-item span  { font-size:.9rem; font-weight:500; color:var(--text); }
.amount-table th { background:#f8fafc; font-size:.78rem; text-align:left; }
.amount-table td { font-size:.85rem; text-align:left; }
.amount-table .total-row td { background:#eff6ff; font-weight:700; color:var(--blue); }

/* ── KPI Popup — stat boxes ── */
.kpi-stat-row { display:grid; grid-template-columns:repeat(auto-fit,minmax(130px,1fr)); gap:10px; margin-bottom:18px; }
.kpi-stat-box { background:#f8fafc; border-radius:10px; padding:12px 14px; border-left:3px solid var(--blue); }
.kpi-stat-box.sa { border-left-color:var(--accent); }
.kpi-stat-box.sg { border-left-color:#22c55e; }
.kpi-stat-box.sr { border-left-color:var(--danger); }
.kpi-stat-box .sv { font-family:'Sora',sans-serif; font-size:1.05rem; font-weight:800; color:var(--navy); word-break:break-word; }
.kpi-stat-box .sl { font-size:.68rem; color:var(--muted); font-weight:600; text-transform:uppercase; letter-spacing:.4px; margin-top:2px; }

/* ── Pagination ── */
.pg-wrap { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-top:14px; padding-top:14px; border-top:1px solid var(--border); }
.pg-info  { font-size:.78rem; color:var(--muted); }
.pg-btns  { display:flex; align-items:center; gap:4px; flex-wrap:wrap; }
.pg-btn   { background:#f1f5f9; border:1px solid var(--border); border-radius:7px; padding:4px 11px; font-size:.78rem; font-weight:600; cursor:pointer; color:var(--text); transition:all .15s; }
.pg-btn:hover          { background:var(--blue); color:#fff; border-color:var(--blue); }
.pg-btn.pg-active      { background:var(--blue); color:#fff; border-color:var(--blue); }
.pg-btn[disabled]      { opacity:.35; cursor:not-allowed; pointer-events:none; }
.pg-btn.pg-dots        { cursor:default; background:transparent; border-color:transparent; }

/* ── KPI popup table ── */
.popup-table { width:100%; border-collapse:collapse; font-size:.82rem; }
.popup-table thead tr th { background:var(--navy); color:#fff; padding:10px 12px; text-align:left; font-size:.71rem; font-weight:700; text-transform:uppercase; letter-spacing:.4px; border:1px solid rgba(255,255,255,.08); white-space:nowrap; }
.popup-table tbody td { padding:9px 12px; border:1px solid var(--border); vertical-align:middle; }
.popup-table tbody tr:nth-child(even) td { background:#f8fafc; }
.popup-table tbody tr:hover td { background:#eff6ff; }

/* ── Empty state ── */
.empty-state { text-align:center; padding:40px 20px; color:var(--muted); }
.empty-state i { font-size:2rem; display:block; margin-bottom:8px; opacity:.4; }

/* ══════════════════════════════════════════════════════════
   PRINT STYLES
══════════════════════════════════════════════════════════ */
@media print {
    body { background:#fff !important; font-size:10.5pt; color:#000 !important; }
    .report-header { background:#0a1628 !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; padding:14px 20px; }
    .filter-card, .no-print, .btn-view, nav, .export-btn, .kpi-hint { display:none !important; }
    .page-wrap { padding:10px; max-width:100%; }
    .kpi-grid { grid-template-columns:repeat(5,1fr); gap:8px; }
    .kpi-card { box-shadow:none !important; border:1px solid #ccc !important; padding:12px 14px 18px !important; page-break-inside:avoid; border-radius:6px; cursor:default; }
    .chart-card, .data-card { box-shadow:none !important; border:1px solid #ccc !important; border-radius:6px; page-break-inside:avoid; }
    .section-title { border-bottom:2px solid #0a1628; padding-bottom:4px; }
    .section-title::after { background:#ccc; }
    .report-table { border-collapse:collapse !important; width:100% !important; }
    .report-table thead th { background:#0a1628 !important; color:#fff !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; border:1px solid #0a1628 !important; padding:6px 9px; font-size:8pt; }
    .report-table tbody td { border:1px solid #bbb !important; padding:5px 9px; font-size:8.5pt; }
    .report-table tbody tr:nth-child(even) td { background:#f5f7fa !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    .data-card-header { background:#0a1628 !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    .badge-approved { background:#d1fae5 !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; border:1px solid #22c55e; }
    .badge-pending  { background:#fef3c7 !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; border:1px solid #f59e0b; }
    @page { margin:1.2cm; size:A4 landscape; }
}

/* ── Responsive ── */
@media (max-width:768px) {
    .report-header { padding:20px; }
    .kpi-grid { grid-template-columns:1fr 1fr; }
    .detail-grid { grid-template-columns:1fr; }
    .kpi-stat-row { grid-template-columns:1fr 1fr; }
}
</style>
</head>
<body>
<?php include 'navbar.php'; ?>

<!-- ══ PAGE HEADER ══ -->
<div class="report-header">
    <div style="position:relative;z-index:1;">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <div>
                <h1><i class="bi bi-bar-chart-line-fill me-2"></i>Travel & Expense Analytics</h1>
                <div class="sub">Comprehensive overview · <?= date('l, d F Y') ?></div>
            </div>
            <div class="ms-auto d-flex gap-2 flex-wrap no-print">
                <button onclick="window.print()" class="btn btn-sm" style="background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.3);">
                    <i class="bi bi-printer me-1"></i>Print
                </button>
            </div>
        </div>
    </div>
</div>

<div class="page-wrap">

<!-- ══ FILTER CARD ══ -->
<div class="filter-card no-print">
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-3 col-6">
            <label class="form-label">Start Date</label>
            <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>">
        </div>
        <div class="col-md-3 col-6">
            <label class="form-label">End Date</label>
            <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>">
        </div>
        <div class="col-md-2 col-6">
            <label class="form-label">Employee</label>
            <input type="text" name="employee" id="employeeInput" class="form-control"
                placeholder="Search or select..." value="<?= htmlspecialchars($employee) ?>"
                autocomplete="off" list="employeeDatalist">
            <datalist id="employeeDatalist">
                <option value="">-- All Employees --</option>
                <?php foreach ($employeeList as $empName): ?>
                    <option value="<?= htmlspecialchars($empName) ?>">
                <?php endforeach; ?>
            </datalist>
        </div>
        <div class="col-md-2 col-6">
            <label class="form-label">Branch Code</label>
            <input type="text" name="brcode" class="form-control" value="<?= htmlspecialchars($brcode) ?>">
        </div>
        <div class="col-md-2 col-12">
            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel me-1"></i>Apply</button>
        </div>
        <?php if ($startDate || $endDate || $employee || $brcode): ?>
        <div class="col-12">
            <a href="report.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-circle me-1"></i>Reset Filters</a>
        </div>
        <?php endif; ?>
    </form>
    <div class="export-btn mt-3">
        <a href="export_csv.php" class="btn btn-success btn-sm"><i class="bi bi-download me-1"></i>Export Expenses CSV</a>
    </div>
</div>

<!-- ══ KPI CARDS ══ -->
<div class="kpi-grid">

    <!-- 1 · Total Travel Orders -->
    <div class="kpi-card blue" data-kpi="total_orders" role="button" tabindex="0" title="Click to view all travel orders">
        <div class="kpi-icon"><i class="bi bi-airplane-fill"></i></div>
        <div class="kpi-val"><?= number_format($summary['order_count']) ?></div>
        <div class="kpi-lbl">Total Travel Orders</div>
        <div class="kpi-sub"><i class="bi bi-cash me-1"></i>Est. रू. <?= number_format($summary['total_estimated'], 2) ?></div>
        <div class="kpi-hint"><i class="bi bi-zoom-in me-1"></i>Click to view details</div>
    </div>

    <!-- 2 · Expense Records -->
    <div class="kpi-card accent" data-kpi="expense_records" role="button" tabindex="0" title="Click to view all expense records">
        <div class="kpi-icon"><i class="bi bi-receipt"></i></div>
        <div class="kpi-val"><?= number_format($expenseSummary['expense_count']) ?></div>
        <div class="kpi-lbl">Expense Records</div>
        <div class="kpi-sub"><i class="bi bi-cash me-1"></i>Paid रू. <?= number_format($expenseSummary['total_expense'], 2) ?></div>
        <div class="kpi-hint"><i class="bi bi-zoom-in me-1"></i>Click to view details</div>
    </div>

    <!-- 3 · Approved Orders -->
    <div class="kpi-card gold" data-kpi="approved_orders" role="button" tabindex="0" title="Click to view approval status">
        <div class="kpi-icon"><i class="bi bi-check2-circle"></i></div>
        <div class="kpi-val"><?= number_format($approvalCounts['approved'] ?? 0) ?></div>
        <div class="kpi-lbl">Approved Orders</div>
        <div class="kpi-sub"><i class="bi bi-hourglass-split me-1"></i><?= number_format($approvalCounts['pending'] ?? 0) ?> still pending</div>
        <div class="kpi-hint"><i class="bi bi-zoom-in me-1"></i>Click to view details</div>
    </div>

    <!-- 4 · Total Estimated Cost -->
    <div class="kpi-card red" data-kpi="estimated_cost" role="button" tabindex="0" title="Click to view cost breakdown">
        <div class="kpi-icon"><i class="bi bi-calculator"></i></div>
        <div class="kpi-main">रू. <?= number_format($summary['total_estimated'] ?? 0, 2) ?></div>
        <div class="kpi-lbl">Total Estimated Cost</div>
        <div class="kpi-sub"><i class="bi bi-list-ol me-1"></i><?= number_format($summary['order_count'] ?? 0) ?> orders total</div>
        <div class="kpi-hint"><i class="bi bi-zoom-in me-1"></i>Click to view details</div>
    </div>

    <!-- 5 · Total Expense -->
    <div class="kpi-card accent" data-kpi="total_expense" role="button" tabindex="0" title="Click to view expense details">
        <div class="kpi-icon"><i class="bi bi-cash-stack"></i></div>
        <div class="kpi-main">रू. <?= number_format($expenseSummary['total_expense'] ?? 0, 2) ?></div>
        <div class="kpi-sub-title">Total Expense Paid</div>
        <div class="kpi-sub">
            <i class="bi bi-calculator me-1"></i>Avg/Order: रू.
            <?= $summary['order_count'] > 0 ? number_format($summary['total_estimated'] / $summary['order_count'], 2) : '0.00' ?>
        </div>
        <div class="kpi-hint"><i class="bi bi-zoom-in me-1"></i>Click to view details</div>
    </div>

</div><!-- /kpi-grid -->

<!-- ══ CHARTS ROW 1 ══ -->
<div class="section-title"><i class="bi bi-bar-chart-fill"></i> Analytics Overview</div>
<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="chart-card">
            <div class="chart-title"><i class="bi bi-calendar3 me-2 text-primary"></i>Monthly Travel Orders &amp; Cost Trend</div>
            <!-- FIX: wrap canvas in a sized container; remove bare height="" attribute -->
            <div class="chart-wrap chart-wrap-tall">
                <canvas id="monthlyChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="chart-card h-100">
            <div class="chart-title"><i class="bi bi-pie-chart me-2 text-success"></i>Approval Status</div>
            <div class="chart-wrap chart-wrap-med">
                <canvas id="approvalChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- ══ CHARTS ROW 2 ══ -->
<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="chart-card">
            <div class="chart-title"><i class="bi bi-people-fill me-2 text-warning"></i>Top 5 Employees by Expense</div>
            <div class="chart-wrap chart-wrap-tall">
                <canvas id="topEmpChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-3">
        <div class="chart-card">
            <div class="chart-title"><i class="bi bi-diagram-3 me-2 text-info"></i>Avg. Expense Breakdown</div>
            <div class="chart-wrap chart-wrap-med">
                <canvas id="breakdownChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-3">
        <div class="chart-card">
            <div class="chart-title"><i class="bi bi-buildings me-2" style="color:var(--accent)"></i>Orders by Branch</div>
            <div class="chart-wrap chart-wrap-med">
                <canvas id="branchChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- ══ TRAVEL ORDERS TABLE ══ -->
<div class="section-title"><i class="bi bi-table"></i> Detailed Records</div>
<div class="data-card mb-4">
    <div class="data-card-header">
        <span class="title"><i class="bi bi-airplane me-2"></i>Travel Orders</span>
        <span class="count-badge"><?= count($orders) ?> records</span>
    </div>
    <div class="table-responsive">
        <table class="table report-table" id="ordersTable">
            <thead>
                <tr>
                    <th>#</th><th>Order No</th><th>Employee</th><th>Branch</th>
                    <th>Route</th><th>Period</th><th>Purpose</th>
                    <th>Est. Cost</th><th>Status</th><th class="no-print">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($orders)): ?>
                <tr><td colspan="10">
                    <div class="empty-state"><i class="bi bi-inbox"></i>No travel orders found</div>
                </td></tr>
            <?php else: foreach ($orders as $idx => $o):
                $isApproved = ($o['approval_ceo_status'] ?? '') === 'Approved';
                $net_cost   = number_format($o['estimatedCost'] ?? 0, 2);
            ?>
                <tr>
                    <td class="text-muted small"><?= (int)$o['id'] ?></td>
                    <td><strong style="color:var(--blue);"><?= htmlspecialchars($o['travel_order_no'] ?? '') ?></strong></td>
                    <td><?= htmlspecialchars($o['employeeName'] ?? '') ?></td>
                    <td><span class="badge bg-secondary"><?= htmlspecialchars($o['BrCode'] ?? $o['br_code'] ?? '') ?></span></td>
                    <td class="small"><?= htmlspecialchars($o['travelFrom'] ?? '') ?> → <?= htmlspecialchars($o['destination'] ?? '') ?></td>
                    <td class="small text-muted"><?= htmlspecialchars($o['travelDateFrom'] ?? '') ?> – <?= htmlspecialchars($o['travelDateTo'] ?? '') ?></td>
                    <td class="small"><?= htmlspecialchars($o['purpose'] ?? '') ?></td>
                    <td><strong>रू. <?= $net_cost ?></strong></td>
                    <td><?= $isApproved ? '<span class="badge-approved">✓ Approved</span>' : '<span class="badge-pending">⏳ Pending</span>' ?></td>
                    <!-- FIX: use data-idx to reference JS array instead of encoding full JSON in attribute -->
                    <td class="no-print">
                        <button class="btn-view order-view-btn" data-idx="<?= $idx ?>">
                            <i class="bi bi-eye me-1"></i>View
                        </button>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ══ EXPENSE RECORDS TABLE ══ -->
<div class="data-card mb-4">
    <div class="data-card-header">
        <span class="title"><i class="bi bi-receipt me-2"></i>Expense Records</span>
        <span class="count-badge"><?= count($expenses) ?> records</span>
    </div>
    <div class="table-responsive">
        <table class="table report-table" id="expensesTable">
            <thead>
                <tr>
                    <th>#</th><th>Employee</th><th>Purpose</th><th>Period</th>
                    <th>Transport</th><th>Daily Allowance</th><th>Hotel</th>
                    <th>Net Amount</th><th>Status</th><th class="no-print">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($expenses)): ?>
                <tr><td colspan="10">
                    <div class="empty-state"><i class="bi bi-inbox"></i>No expense records found</div>
                </td></tr>
            <?php else: foreach ($expenses as $idx => $e):
                $transport = ((float)($e['fare'] ?? 0) + (float)($e['airport'] ?? 0) + (float)($e['road_tax'] ?? 0));
                $daily_amt = ((float)($e['daily_rate'] ?? 0) * (float)($e['days'] ?? 0));
                $hotel_amt = ((float)($e['hotel'] ?? 0) + (float)($e['other_exp'] ?? 0));
                $net       = $transport + $daily_amt + $hotel_amt - (float)($e['advance'] ?? 0);
                $status    = $e['status'] ?? 'Pending';
            ?>
                <tr>
                    <td class="text-muted small"><?= (int)$e['id'] ?></td>
                    <td><strong><?= htmlspecialchars($e['name'] ?? '') ?></strong></td>
                    <td class="small"><?= htmlspecialchars($e['purpose'] ?? '') ?></td>
                    <td class="small text-muted"><?= htmlspecialchars($e['from_date'] ?? '') ?> – <?= htmlspecialchars($e['to_date'] ?? '') ?></td>
                    <td>रू. <?= number_format($transport, 2) ?></td>
                    <td>रू. <?= number_format($daily_amt, 2) ?></td>
                    <td>रू. <?= number_format($hotel_amt, 2) ?></td>
                    <td><strong style="color:var(--blue);">रू. <?= number_format($net, 2) ?></strong></td>
                    <td><?= $status === 'Approved' ? '<span class="badge-approved">✓ Approved</span>' : '<span class="badge-pending">⏳ Pending</span>' ?></td>
                    <!-- FIX: use data-idx to reference JS array -->
                    <td class="no-print">
                        <button class="btn-view expense-view-btn" data-idx="<?= $idx ?>">
                            <i class="bi bi-eye me-1"></i>View
                        </button>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div><!-- /page-wrap -->


<!-- ══════════════════════════════════════════════════════════════════
     KPI DETAIL MODAL  (shared for all 5 cards, with pagination)
═══════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="kpiModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header hdr-navy" id="kpiModalHeader">
                <h5 class="modal-title" id="kpiModalTitle"><i class="bi bi-grid-3x3 me-2"></i>Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="kpiStatRow" class="kpi-stat-row"></div>
                <div class="table-responsive">
                    <table class="popup-table">
                        <thead id="kpiThead"></thead>
                        <tbody id="kpiTbody"></tbody>
                    </table>
                </div>
                <div id="kpiPgWrap"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-outline-primary btn-sm" id="kpiPrintBtn">
                    <i class="bi bi-printer me-1"></i>Print All
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════
     TRAVEL ORDER ROW — VIEW MODAL
═══════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="orderModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header hdr-navy">
                <h5 class="modal-title"><i class="bi bi-airplane-fill me-2"></i>Travel Order Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="orderModalBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="printModalContent('orderModalBody')">
                    <i class="bi bi-printer me-1"></i>Print
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════
     EXPENSE ROW — VIEW MODAL
═══════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="expenseModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header hdr-navy">
                <h5 class="modal-title"><i class="bi bi-receipt me-2"></i>Expense Record Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="expenseModalBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="printModalContent('expenseModalBody')">
                    <i class="bi bi-printer me-1"></i>Print
                </button>
            </div>
        </div>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ══════════════════════════════════════════════════════════
   DATA FROM PHP  (FIX: single source of truth in JS variables,
   not scattered across HTML data attributes)
══════════════════════════════════════════════════════════ */
const ordersData   = <?= $ordersJson ?>;
const expensesData = <?= $expensesJson ?>;

/* ══════════════════════════════════════════════════════════
   HELPERS
══════════════════════════════════════════════════════════ */
const fmtINR = n => parseFloat(n || 0).toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2});
const esc    = s => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');

// FIX: guard approval status check — null-safe, trimmed
const isApproved = val => (val ?? '').toString().trim() === 'Approved';

const apprBadge = ok => ok
    ? '<span class="badge-approved">✓ Approved</span>'
    : '<span class="badge-pending">⏳ Pending</span>';

const sb = (cls, val, lbl) =>
    `<div class="kpi-stat-box ${cls}"><div class="sv">${val}</div><div class="sl">${lbl}</div></div>`;

/* ══════════════════════════════════════════════════════════
   CHART SETUP
   FIX: use maintainAspectRatio:false + sized wrapper divs
   FIX: cast all data values to Number to prevent NaN bars
══════════════════════════════════════════════════════════ */
Chart.defaults.font.family = "'DM Sans', sans-serif";
Chart.defaults.font.size   = 12;
Chart.defaults.color       = '#64748b';

const BLUE    = '#1a56db';
const ACCENT  = '#00d4aa';
const GOLD    = '#f59e0b';
const RED     = '#ef4444';
const PALETTE = [BLUE, ACCENT, GOLD, RED, '#8b5cf6', '#ec4899', '#14b8a6', '#f97316'];

// Chart data from PHP
const mLabels = <?= $monthLabels ?>;
const mCounts = <?= $monthCounts ?>;
const mTotals = <?= $monthTotals ?>;

// 1. Monthly trend — bar + line combo
new Chart(document.getElementById('monthlyChart'), {
    data: {
        labels: mLabels.length ? mLabels : ['No Data'],
        datasets: [
            {
                type: 'bar',
                label: 'Orders',
                data: mCounts.map(Number),
                backgroundColor: 'rgba(26,86,219,.18)',
                borderColor: BLUE,
                borderWidth: 2,
                borderRadius: 6,
                yAxisID: 'y'
            },
            {
                type: 'line',
                label: 'Est. Cost (रू.)',
                data: mTotals.map(Number),
                borderColor: ACCENT,
                backgroundColor: 'rgba(0,212,170,.08)',
                borderWidth: 2.5,
                pointRadius: 5,
                pointBackgroundColor: ACCENT,
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                tension: 0.4,
                fill: true,
                yAxisID: 'y1'
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false, // FIX: controlled by wrapper div height
        interaction: { mode:'index', intersect:false },
        plugins: { legend:{ position:'top', labels:{ boxWidth:12, padding:16 } } },
        scales: {
            y:  { position:'left',  title:{ display:true, text:'Orders' },    grid:{ color:'#f1f5f9' }, ticks:{ precision:0 } },
            y1: { position:'right', title:{ display:true, text:'Cost (रू.)' }, grid:{ drawOnChartArea:false } }
        }
    }
});

// 2. Approval — FIX: doughnut is clearer than polarArea for 2-value comparison
new Chart(document.getElementById('approvalChart'), {
    type: 'doughnut',
    data: {
        labels: ['Approved', 'Pending'],
        datasets: [{
            data: [
                Number(<?= (int)($approvalCounts['approved'] ?? 0) ?>),
                Number(<?= (int)($approvalCounts['pending']  ?? 0) ?>)
            ],
            backgroundColor: ['rgba(34,197,94,.85)', 'rgba(245,158,11,.85)'],
            borderColor: ['#fff','#fff'],
            borderWidth: 3,
            hoverOffset: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '62%',
        plugins: {
            legend: { position:'bottom', labels:{ boxWidth:12, padding:16 } },
            tooltip: {
                callbacks: {
                    label: ctx => {
                        const total = ctx.dataset.data.reduce((a,b)=>a+b,0);
                        const pct   = total ? ((ctx.raw/total)*100).toFixed(1) : 0;
                        return ` ${ctx.label}: ${ctx.raw} (${pct}%)`;
                    }
                }
            }
        }
    }
});

// 3. Top employees — horizontal bar
const empNames  = <?= $topEmpNames ?>;
const empTotals = <?= $topEmpTotals ?>;
new Chart(document.getElementById('topEmpChart'), {
    type: 'bar',
    data: {
        labels: empNames.length ? empNames : ['No Data'],
        datasets: [{
            label: 'Total Expense (रू.)',
            data: empTotals.map(Number),
            backgroundColor: PALETTE.slice(0, Math.max(empNames.length, 1)),
            borderRadius: 6
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend:{ display:false } },
        scales: {
            x: { grid:{ color:'#f1f5f9' }, ticks:{ callback: v => 'रू. '+fmtINR(v) } },
            y: { grid:{ display:false } }
        }
    }
});

// 4. Breakdown — doughnut (FIX: polarArea had scale label clutter; doughnut is cleaner)
const brkData = <?= $breakdownAvg ?>.map(Number);
new Chart(document.getElementById('breakdownChart'), {
    type: 'doughnut',
    data: {
        labels: ['Transport', 'Daily Allow.', 'Hotel & Other'],
        datasets: [{
            data: brkData,
            backgroundColor: ['rgba(26,86,219,.8)', 'rgba(0,212,170,.8)', 'rgba(245,158,11,.8)'],
            borderColor: ['#fff','#fff','#fff'],
            borderWidth: 3,
            hoverOffset: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '55%',
        plugins: {
            legend: { position:'bottom', labels:{ boxWidth:11, padding:12, font:{size:11} } },
            tooltip: {
                callbacks: { label: ctx => ` ${ctx.label}: रू. ${fmtINR(ctx.raw)}` }
            }
        }
    }
});

// 5. Branch bar
const brLabels = <?= $branchLabels ?>;
const brCounts = <?= $branchCounts ?>;
new Chart(document.getElementById('branchChart'), {
    type: 'bar',
    data: {
        labels: brLabels.length ? brLabels : ['No Data'],
        datasets: [{
            label: 'Orders',
            data: brCounts.map(Number),
            backgroundColor: PALETTE.slice(0, Math.max(brLabels.length, 1)),
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend:{ display:false } },
        scales: {
            x: { grid:{ display:false } },
            y: { grid:{ color:'#f1f5f9' }, ticks:{ precision:0 } }
        }
    }
});


/* ══════════════════════════════════════════════════════════
   PAGINATION ENGINE
══════════════════════════════════════════════════════════ */
const PER_PAGE = 15;

// FIX: renamed kpi object properties to avoid collision with outer scope
const kpiState = { rows:[], page:1, total:0, pages:0, title:'', thead:'', stats:'' };

function renderPage(page) {
    // FIX: correct clamp — was using mismatched variable names before
    kpiState.page = Math.max(1, Math.min(page, kpiState.pages));
    const start = (kpiState.page - 1) * PER_PAGE;
    const slice = kpiState.rows.slice(start, start + PER_PAGE);

    document.getElementById('kpiTbody').innerHTML = slice.map(r => `<tr>${r}</tr>`).join('');

    const wrap = document.getElementById('kpiPgWrap');
    if (kpiState.pages <= 1) { wrap.innerHTML = ''; return; }

    // FIX: buttons use class pg-active (not .active which conflicts with Bootstrap)
    // FIX: ellipsis rendered only when gap exists (was showing on adjacent pages)
    let btns = `<button class="pg-btn" onclick="renderPage(${kpiState.page - 1})" ${kpiState.page <= 1 ? 'disabled' : ''}>&lsaquo; Prev</button>`;
    let lastRendered = 0;
    for (let i = 1; i <= kpiState.pages; i++) {
        const near = (i === 1 || i === kpiState.pages || Math.abs(i - kpiState.page) <= 2);
        if (near) {
            if (lastRendered && i - lastRendered > 1) {
                btns += `<button class="pg-btn pg-dots" disabled>…</button>`;
            }
            btns += `<button class="pg-btn${i === kpiState.page ? ' pg-active' : ''}" onclick="renderPage(${i})">${i}</button>`;
            lastRendered = i;
        }
    }
    btns += `<button class="pg-btn" onclick="renderPage(${kpiState.page + 1})" ${kpiState.page >= kpiState.pages ? 'disabled' : ''}>Next &rsaquo;</button>`;

    wrap.innerHTML = `<div class="pg-wrap">
        <span class="pg-info">Showing ${start + 1}–${Math.min(start + PER_PAGE, kpiState.total)} of <strong>${kpiState.total}</strong> records</span>
        <div class="pg-btns">${btns}</div>
    </div>`;
}

function openKpiModal(cfg) {
    document.getElementById('kpiModalHeader').className = 'modal-header ' + (cfg.hdr || 'hdr-navy');
    document.getElementById('kpiModalTitle').innerHTML  = cfg.icon + ' ' + cfg.title;
    document.getElementById('kpiStatRow').innerHTML     = cfg.stats || '';
    document.getElementById('kpiThead').innerHTML       = '<tr>' + cfg.headers.map(h => `<th>${h}</th>`).join('') + '</tr>';

    kpiState.rows  = cfg.rows;
    kpiState.total = cfg.rows.length;
    kpiState.pages = Math.max(1, Math.ceil(cfg.rows.length / PER_PAGE));
    kpiState.title = cfg.title;
    kpiState.stats = cfg.stats || '';
    kpiState.thead = '<tr>' + cfg.headers.map(h => `<th>${h}</th>`).join('') + '</tr>';

    renderPage(1);
    bootstrap.Modal.getOrCreateInstance(document.getElementById('kpiModal')).show();
}

/* ══════════════════════════════════════════════════════════
   KPI CARD CLICK HANDLERS
   FIX: all approval checks use isApproved() helper — no more
   broken  ')==='Approved'  string literals
══════════════════════════════════════════════════════════ */
document.querySelectorAll('.kpi-card[data-kpi]').forEach(card => {
    const trigger = () => {
        const type = card.dataset.kpi;

        /* ── 1. Total Travel Orders ─────────────────── */
        if (type === 'total_orders') {
            const totalEst = ordersData.reduce((s, o) => s + parseFloat(o.estimatedCost || 0), 0);
            const apprCnt  = ordersData.filter(o => isApproved(o.approval_ceo_status)).length;
            openKpiModal({
                title:'All Travel Orders',
                icon:'<i class="bi bi-airplane-fill"></i>',
                hdr:'hdr-blue',
                stats:
                    sb('',   ordersData.length,              'Total Orders')     +
                    sb('sa', 'रू. ' + fmtINR(totalEst),     'Total Estimated')  +
                    sb('sg', apprCnt,                        'Approved')         +
                    sb('sr', ordersData.length - apprCnt,    'Pending'),
                headers: ['#', 'Order No', 'Employee', 'Branch', 'Route', 'Period', 'Purpose', 'Est. Cost', 'Status'],
                rows: ordersData.map(o => {
                    const a = isApproved(o.approval_ceo_status);
                    return `<td>${o.id}</td>
                            <td><strong style="color:#1a56db">${esc(o.travel_order_no)}</strong></td>
                            <td>${esc(o.employeeName)}</td>
                            <td><span class="badge bg-secondary">${esc(o.BrCode ?? o.br_code)}</span></td>
                            <td>${esc(o.travelFrom)} → ${esc(o.destination)}</td>
                            <td style="white-space:nowrap">${esc(o.travelDateFrom)} – ${esc(o.travelDateTo)}</td>
                            <td>${esc(o.purpose)}</td>
                            <td><strong>रू. ${fmtINR(o.estimatedCost)}</strong></td>
                            <td>${apprBadge(a)}</td>`;
                })
            });
        }

        /* ── 2. Expense Records ─────────────────────── */
        else if (type === 'expense_records') {
            let grandTotal = 0;
            expensesData.forEach(e => {
                const tr = parseFloat(e.fare||0) + parseFloat(e.airport||0) + parseFloat(e.road_tax||0);
                const da = parseFloat(e.daily_rate||0) * parseFloat(e.days||0);
                const ho = parseFloat(e.hotel||0) + parseFloat(e.other_exp||0);
                grandTotal += tr + da + ho - parseFloat(e.advance||0);
            });
            const apprCnt = expensesData.filter(e => isApproved(e.status)).length;
            openKpiModal({
                title:'All Expense Records',
                icon:'<i class="bi bi-receipt"></i>',
                hdr:'hdr-accent',
                stats:
                    sb('sa', expensesData.length,                     'Total Records') +
                    sb('',   'रू. ' + fmtINR(grandTotal),            'Total Paid')    +
                    sb('sg', apprCnt,                                  'Approved')      +
                    sb('sr', expensesData.length - apprCnt,            'Pending'),
                headers: ['#', 'Employee', 'Purpose', 'Period', 'Transport', 'Daily', 'Hotel', 'Net Amount', 'Status'],
                rows: expensesData.map(e => {
                    const tr = parseFloat(e.fare||0)+parseFloat(e.airport||0)+parseFloat(e.road_tax||0);
                    const da = parseFloat(e.daily_rate||0)*parseFloat(e.days||0);
                    const ho = parseFloat(e.hotel||0)+parseFloat(e.other_exp||0);
                    const nt = tr + da + ho - parseFloat(e.advance||0);
                    const a  = isApproved(e.status);
                    return `<td>${e.id}</td>
                            <td><strong>${esc(e.name)}</strong></td>
                            <td>${esc(e.purpose)}</td>
                            <td style="white-space:nowrap">${esc(e.from_date)} – ${esc(e.to_date)}</td>
                            <td>रू. ${fmtINR(tr)}</td>
                            <td>रू. ${fmtINR(da)}</td>
                            <td>रू. ${fmtINR(ho)}</td>
                            <td><strong style="color:#1a56db">रू. ${fmtINR(nt)}</strong></td>
                            <td>${apprBadge(a)}</td>`;
                })
            });
        }

        /* ── 3. Approved Orders ─────────────────────── */
        else if (type === 'approved_orders') {
            const appr  = ordersData.filter(o =>  isApproved(o.approval_ceo_status));
            const pend  = ordersData.filter(o => !isApproved(o.approval_ceo_status));
            const apprE = appr.reduce((s, o) => s + parseFloat(o.estimatedCost||0), 0);
            const pendE = pend.reduce((s, o) => s + parseFloat(o.estimatedCost||0), 0);
            openKpiModal({
                title:'Approval Status — All Orders',
                icon:'<i class="bi bi-check2-circle"></i>',
                hdr:'hdr-gold',
                stats:
                    sb('sg', appr.length,                   'Approved')           +
                    sb('sr', pend.length,                   'Pending')            +
                    sb('sa', 'रू. ' + fmtINR(apprE),       'Approved Estimated') +
                    sb('',   'रू. ' + fmtINR(pendE),        'Pending Estimated'),
                headers: ['#', 'Order No', 'Employee', 'Branch', 'Route', 'Period', 'Est. Cost', 'Status'],
                rows: [...appr, ...pend].map(o => {
                    const a = isApproved(o.approval_ceo_status);
                    return `<td>${o.id}</td>
                            <td><strong style="color:#1a56db">${esc(o.travel_order_no)}</strong></td>
                            <td>${esc(o.employeeName)}</td>
                            <td><span class="badge bg-secondary">${esc(o.BrCode ?? o.br_code)}</span></td>
                            <td>${esc(o.travelFrom)} → ${esc(o.destination)}</td>
                            <td style="white-space:nowrap">${esc(o.travelDateFrom)} – ${esc(o.travelDateTo)}</td>
                            <td><strong>रू. ${fmtINR(o.estimatedCost)}</strong></td>
                            <td>${apprBadge(a)}</td>`;
                })
            });
        }

        /* ── 4. Total Estimated Cost ────────────────── */
        else if (type === 'estimated_cost') {
            const totalEst = ordersData.reduce((s, o) => s + parseFloat(o.estimatedCost||0), 0);
            const avgEst   = ordersData.length ? totalEst / ordersData.length : 0;
            const maxEst   = ordersData.reduce((m, o) => Math.max(m, parseFloat(o.estimatedCost||0)), 0);
            const sorted   = [...ordersData].sort((a, b) => parseFloat(b.estimatedCost||0) - parseFloat(a.estimatedCost||0));
            openKpiModal({
                title:'Estimated Cost — All Orders (High → Low)',
                icon:'<i class="bi bi-calculator"></i>',
                hdr:'hdr-red',
                stats:
                    sb('sr', 'रू. ' + fmtINR(totalEst), 'Grand Total')          +
                    sb('',   'रू. ' + fmtINR(avgEst),   'Avg per Order')        +
                    sb('sg', 'रू. ' + fmtINR(maxEst),   'Highest Order')        +
                    sb('sa', ordersData.length,           'Total Orders'),
                headers: ['Rank', '#', 'Order No', 'Employee', 'Branch', 'Route', 'Period', 'Purpose', 'Est. Cost', 'Status'],
                rows: sorted.map((o, i) => {
                    const a = isApproved(o.approval_ceo_status);
                    return `<td><strong>#${i + 1}</strong></td>
                            <td>${o.id}</td>
                            <td><strong style="color:#1a56db">${esc(o.travel_order_no)}</strong></td>
                            <td>${esc(o.employeeName)}</td>
                            <td><span class="badge bg-secondary">${esc(o.BrCode ?? o.br_code)}</span></td>
                            <td>${esc(o.travelFrom)} → ${esc(o.destination)}</td>
                            <td style="white-space:nowrap">${esc(o.travelDateFrom)} – ${esc(o.travelDateTo)}</td>
                            <td>${esc(o.purpose)}</td>
                            <td><strong>रू. ${fmtINR(o.estimatedCost)}</strong></td>
                            <td>${apprBadge(a)}</td>`;
                })
            });
        }

        /* ── 5. Total Expense ───────────────────────── */
        else if (type === 'total_expense') {
            let grandTotal = 0, totTr = 0, totDa = 0, totHo = 0;
            expensesData.forEach(e => {
                const tr = parseFloat(e.fare||0)+parseFloat(e.airport||0)+parseFloat(e.road_tax||0);
                const da = parseFloat(e.daily_rate||0)*parseFloat(e.days||0);
                const ho = parseFloat(e.hotel||0)+parseFloat(e.other_exp||0);
                const ad = parseFloat(e.advance||0);
                totTr += tr; totDa += da; totHo += ho;
                grandTotal += tr + da + ho - ad;
            });
            openKpiModal({
                title:'Total Expense — Full Breakdown',
                icon:'<i class="bi bi-cash-stack"></i>',
                hdr:'hdr-accent',
                stats:
                    sb('sa', 'रू. ' + fmtINR(grandTotal), 'Grand Total Paid')    +
                    sb('',   'रू. ' + fmtINR(totTr),       'Transport Total')    +
                    sb('sg', 'रू. ' + fmtINR(totDa),       'Daily Allowance')    +
                    sb('sr', 'रू. ' + fmtINR(totHo),       'Hotel & Other'),
                headers: ['#', 'Employee', 'Purpose', 'Period', 'Transport', 'Daily', 'Hotel', 'Advance', 'Net Payable', 'Status'],
                rows: expensesData.map(e => {
                    const tr = parseFloat(e.fare||0)+parseFloat(e.airport||0)+parseFloat(e.road_tax||0);
                    const da = parseFloat(e.daily_rate||0)*parseFloat(e.days||0);
                    const ho = parseFloat(e.hotel||0)+parseFloat(e.other_exp||0);
                    const ad = parseFloat(e.advance||0);
                    const nt = tr + da + ho - ad;
                    const a  = isApproved(e.status);
                    return `<td>${e.id}</td>
                            <td><strong>${esc(e.name)}</strong></td>
                            <td>${esc(e.purpose)}</td>
                            <td style="white-space:nowrap">${esc(e.from_date)} – ${esc(e.to_date)}</td>
                            <td>रू. ${fmtINR(tr)}</td>
                            <td>रू. ${fmtINR(da)}</td>
                            <td>रू. ${fmtINR(ho)}</td>
                            <td>रू. ${fmtINR(ad)}</td>
                            <td><strong style="color:#1a56db">रू. ${fmtINR(nt)}</strong></td>
                            <td>${apprBadge(a)}</td>`;
                })
            });
        }
    };

    card.addEventListener('click', trigger);
    card.addEventListener('keydown', e => {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); trigger(); }
    });
});

/* ══════════════════════════════════════════════════════════
   KPI PRINT — all rows, no pagination
══════════════════════════════════════════════════════════ */
document.getElementById('kpiPrintBtn').addEventListener('click', () => {
    const allTr    = kpiState.rows.map(r => `<tr>${r}</tr>`).join('');
    const titleTxt = kpiState.title.replace(/<[^>]*>/g, '');
    const w = window.open('', '_blank');
    w.document.write(`<!DOCTYPE html><html><head><meta charset="utf-8">
        <title>Print – ${titleTxt}</title>
        <style>
        body{font-family:Arial,sans-serif;padding:20px;color:#1e293b;font-size:10pt;}
        h3{font-weight:800;margin-bottom:12px;color:#0a1628;border-bottom:3px solid #0a1628;padding-bottom:8px;}
        .sr{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:16px;}
        /* FIX: stat box styles self-contained — no external CSS dependency */
        .kpi-stat-box{border:1px solid #ccc;border-radius:6px;padding:10px 12px;border-left:4px solid #1a56db;}
        .kpi-stat-box.sa{border-left-color:#00d4aa;}
        .kpi-stat-box.sg{border-left-color:#22c55e;}
        .kpi-stat-box.sr{border-left-color:#ef4444;}
        .sv{font-size:11pt;font-weight:800;color:#0a1628;word-break:break-word;}
        .sl{font-size:7.5pt;color:#64748b;text-transform:uppercase;font-weight:600;margin-top:2px;}
        table{width:100%;border-collapse:collapse;font-size:8pt;}
        thead th{background:#0a1628;color:#fff;padding:7px 9px;text-align:left;
                 border:1px solid #0a1628;-webkit-print-color-adjust:exact;print-color-adjust:exact;}
        tbody td{padding:5px 9px;border:1px solid #bbb;vertical-align:middle;}
        tbody tr:nth-child(even) td{background:#f5f7fa;-webkit-print-color-adjust:exact;print-color-adjust:exact;}
        .badge-approved{background:#d1fae5;color:#065f46;padding:2px 7px;border-radius:10px;font-weight:700;
                        border:1px solid #22c55e;font-size:7.5pt;-webkit-print-color-adjust:exact;print-color-adjust:exact;}
        .badge-pending {background:#fef3c7;color:#92400e;padding:2px 7px;border-radius:10px;font-weight:700;
                        border:1px solid #f59e0b;font-size:7.5pt;-webkit-print-color-adjust:exact;print-color-adjust:exact;}
        .badge{font-size:7.5pt;background:#64748b;color:#fff;padding:1px 6px;border-radius:4px;}
        @page{margin:1.2cm;size:A4 landscape;}
        </style></head><body>
        <h3>${titleTxt}</h3>
        <div class="sr">${kpiState.stats}</div>
        <table><thead>${kpiState.thead}</thead><tbody>${allTr}</tbody></table>
        </body></html>`);
    w.document.close(); w.focus(); w.print(); w.close();
});

/* ══════════════════════════════════════════════════════════
   TRAVEL ORDER ROW — VIEW POPUP
   FIX: reads from ordersData array by index, not HTML attribute JSON
══════════════════════════════════════════════════════════ */
document.querySelectorAll('.order-view-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        const r = ordersData[parseInt(this.dataset.idx, 10)];
        if (!r) return;
        const a = isApproved(r.approval_ceo_status);
        const fmt2 = n => parseFloat(n || 0).toLocaleString('en-IN', {minimumFractionDigits:2});
        document.getElementById('orderModalBody').innerHTML = `
            <div class="detail-grid">
                <div class="detail-item"><label>Travel Order No</label><span style="color:var(--blue);font-weight:700;">${esc(r.travel_order_no)}</span></div>
                <div class="detail-item"><label>Employee</label><span>${esc(r.employeeName)}</span></div>
                <div class="detail-item"><label>Branch Code</label><span>${esc(r.BrCode ?? r.br_code)}</span></div>
                <div class="detail-item"><label>Department</label><span>${esc(r.department)}</span></div>
                <div class="detail-item"><label>Travel From</label><span>${esc(r.travelFrom)}</span></div>
                <div class="detail-item"><label>Destination</label><span>${esc(r.destination)}</span></div>
                <div class="detail-item"><label>Start Date</label><span>${esc(r.travelDateFrom)}</span></div>
                <div class="detail-item"><label>End Date</label><span>${esc(r.travelDateTo)}</span></div>
                <div class="detail-item"><label>No. of Days</label><span>${esc(r.noOfDays)}</span></div>
                <div class="detail-item"><label>Purpose</label><span>${esc(r.purpose)}</span></div>
                <div class="detail-item"><label>Request Type</label><span>${esc(r.request_type ?? 'Normal')}</span></div>
                <div class="detail-item"><label>Status</label><span>${apprBadge(a)}</span></div>
            </div>
            <hr>
            <table class="table table-bordered amount-table">
                <thead><tr><th>Description</th><th>Amount</th></tr></thead>
                <tbody>
                    <tr><td>Estimated Cost</td><td>रू. ${fmt2(r.estimatedCost)}</td></tr>
                    <tr class="total-row"><td><strong>Total</strong></td><td><strong>रू. ${fmt2(r.estimatedCost)}</strong></td></tr>
                </tbody>
            </table>`;
        bootstrap.Modal.getOrCreateInstance(document.getElementById('orderModal')).show();
    });
});

/* ══════════════════════════════════════════════════════════
   EXPENSE ROW — VIEW POPUP
   FIX: reads from expensesData array by index — no attribute JSON
══════════════════════════════════════════════════════════ */
document.querySelectorAll('.expense-view-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        const r   = expensesData[parseInt(this.dataset.idx, 10)];
        if (!r) return;
        const tr  = parseFloat(r.fare||0)+parseFloat(r.airport||0)+parseFloat(r.road_tax||0);
        const da  = parseFloat(r.daily_rate||0)*parseFloat(r.days||0);
        const ho  = parseFloat(r.hotel||0)+parseFloat(r.other_exp||0);
        const adv = parseFloat(r.advance||0);
        const nt  = tr + da + ho - adv;
        const fmt = n => parseFloat(n || 0).toLocaleString('en-IN', {minimumFractionDigits:2});
        const a   = isApproved(r.status);
        document.getElementById('expenseModalBody').innerHTML = `
            <div class="detail-grid">
                <div class="detail-item"><label>Employee Name</label><span style="font-weight:700;">${esc(r.name)}</span></div>
                <div class="detail-item"><label>Position</label><span>${esc(r.position)}</span></div>
                <div class="detail-item"><label>Office</label><span>${esc(r.office)}</span></div>
                <div class="detail-item"><label>Purpose</label><span>${esc(r.purpose)}</span></div>
                <div class="detail-item"><label>Travel Period</label><span>${esc(r.from_date)} – ${esc(r.to_date)}</span></div>
                <div class="detail-item"><label>Vehicle</label><span>${esc(r.vehicle)}</span></div>
                <div class="detail-item"><label>Distance</label><span>${esc(r.distance ?? '0')} km</span></div>
                <div class="detail-item"><label>Status</label><span>${apprBadge(a)}</span></div>
            </div>
            <hr>
            <h6 style="font-family:'Sora',sans-serif;font-weight:700;margin-bottom:12px;">Expense Breakdown</h6>
            <table class="table table-bordered amount-table">
                <thead><tr><th>Description</th><th>Amount (रू.)</th></tr></thead>
                <tbody>
                    <tr><td>Fare / Fuel</td><td>${fmt(r.fare)}</td></tr>
                    <tr><td>Airport + Road Tax</td><td>${fmt(parseFloat(r.airport||0)+parseFloat(r.road_tax||0))}</td></tr>
                    <tr style="background:#eff6ff;"><td><strong>(क) Total Transport</strong></td><td><strong>${fmt(tr)}</strong></td></tr>
                    <tr><td>Daily Allowance (रू.${fmt(r.daily_rate)} × ${esc(r.days)} days)</td><td>${fmt(da)}</td></tr>
                    <tr><td>Hotel + Other</td><td>${fmt(ho)}</td></tr>
                    <tr><td>Advance Taken</td><td>− रू. ${fmt(adv)}</td></tr>
                    <tr class="total-row"><td><strong>Net Payable Amount</strong></td><td><strong>रू. ${fmt(nt)}</strong></td></tr>
                </tbody>
            </table>`;
        bootstrap.Modal.getOrCreateInstance(document.getElementById('expenseModal')).show();
    });
});

/* ══════════════════════════════════════════════════════════
   PRINT ROW DETAIL (order / expense individual modals)
══════════════════════════════════════════════════════════ */
function printModalContent(bodyId) {
    const content = document.getElementById(bodyId).innerHTML;
    const w = window.open('', '_blank');
    w.document.write(`<!DOCTYPE html><html><head><meta charset="utf-8"><title>Print</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
        <style>
        body{font-family:'DM Sans',Arial,sans-serif;padding:24px;color:#1e293b;font-size:11pt;}
        .detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:18px;}
        .detail-item label{font-size:9pt;font-weight:700;color:#64748b;text-transform:uppercase;display:block;margin-bottom:2px;}
        .detail-item span{font-size:11pt;}
        table{width:100%;border-collapse:collapse;margin-top:12px;}
        th{background:#0a1628;color:#fff;padding:8px 12px;text-align:left;font-size:9pt;
           border:1px solid #0a1628;-webkit-print-color-adjust:exact;print-color-adjust:exact;}
        td{padding:7px 12px;border:1px solid #bbb;font-size:10pt;}
        tr:nth-child(even) td{background:#f5f7fa;-webkit-print-color-adjust:exact;print-color-adjust:exact;}
        .total-row td{background:#eff6ff;font-weight:700;color:#1a56db;
                      -webkit-print-color-adjust:exact;print-color-adjust:exact;}
        .badge-approved{background:#d1fae5;color:#065f46;padding:3px 9px;border-radius:20px;font-size:9pt;
                        border:1px solid #22c55e;-webkit-print-color-adjust:exact;print-color-adjust:exact;}
        .badge-pending {background:#fef3c7;color:#92400e;padding:3px 9px;border-radius:20px;font-size:9pt;
                        border:1px solid #f59e0b;-webkit-print-color-adjust:exact;print-color-adjust:exact;}
        @page{margin:1.5cm;}
        </style></head><body>${content}</body></html>`);
    w.document.close(); w.focus(); w.print(); w.close();
}
</script>
</body>
</html>