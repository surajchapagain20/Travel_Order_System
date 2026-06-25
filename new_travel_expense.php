<?php
require_once 'auth.php';
requireLogin();
require_once 'db.php';

$successMessage = $errorMessage = '';
// ── Database connection ────────────────────────────────────────────────────
try {
    $pdo = new PDO("mysql:host=localhost;dbname=hr;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ── AJAX: Search employees ─────────────────────────────────────────────────
if (isset($_GET['search_emp'])) {
    header('Content-Type: application/json');
    $q = trim($_GET['q'] ?? '');
    if (strlen($q) < 1) { echo json_encode([]); exit; }
    $stmt = $pdo->prepare("
        SELECT e.EmpID, e.employeeName, p.PostName AS designation,
               b.branch_name AS office, e.BrCode
        FROM employees e
        LEFT JOIN posts    p ON p.PostId = e.designation
        LEFT JOIN branches b ON b.BrCode = e.BrCode
        WHERE e.EmpID LIKE :q OR e.employeeName LIKE :q2
        ORDER BY e.employeeName LIMIT 10
    ");
    $stmt->execute([':q' => "$q%", ':q2' => "%$q%"]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// ── AJAX: Check existing expense records ──────────────────────────────────
if (isset($_GET['check_existing_expenses'])) {
    header('Content-Type: application/json');
    $empID = trim($_GET['emp_id'] ?? '');
    if ($empID === '') { echo json_encode(['success' => false, 'records' => []]); exit; }
    try {
        $stmt = $pdo->prepare("
            SELECT te.id, te.emp_id, te.name, te.office, te.purpose,
                   te.from_date, te.to_date, te.vehicle,
                   te.distance, te.fare, te.airport, te.road_tax,
                   te.daily_rate, te.days, te.hotel,
                   te.other_exp, te.advance, te.signature_date,
                   te.remarks, te.document_path,
                   COALESCE(te.status, 'pending') AS status,
                   te.travel_order_no,
                   to2.travel_order_no AS linked_order_no
            FROM travel_expenses te
            LEFT JOIN travel_orders to2
                   ON to2.travel_order_no = te.travel_order_no
                  AND to2.EmpID           = te.emp_id
            WHERE te.emp_id = ?
            ORDER BY te.signature_date DESC
            LIMIT 20
        ");
        $stmt->execute([$empID]);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'records' => $records]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'records' => [], 'error' => $e->getMessage()]);
    }
    exit;
}

// ── AJAX: Fetch travel orders by EmpID ────────────────────────────────────
if (isset($_GET['fetch_travel_order'])) {
    header('Content-Type: application/json');
    $empID = trim($_GET['emp_id'] ?? '');
    if ($empID === '') { echo json_encode(['success' => false, 'message' => 'Empty EmpID']); exit; }

    // Fetch ALL travel orders for this employee (newest first)
    $stmt = $pdo->prepare("
        SELECT t.id AS order_id, t.travel_order_no, t.employeeName, t.designation,
               t.department AS office, t.purpose, t.travelDateFrom AS from_date,
               t.travelDateTo AS to_date, t.modeOfTransport AS vehicle,
               t.kilometer AS distance, t.entryDate AS signature_date,
               t.EmpID, b.branch_name AS branch_office, p.PostName AS post_name
        FROM travel_orders t
        LEFT JOIN employees e ON e.EmpID = t.EmpID
        LEFT JOIN branches  b ON b.BrCode = t.BrCode
        LEFT JOIN posts     p ON p.PostId = e.designation
        WHERE t.EmpID = ?
        ORDER BY t.entryDate DESC
        LIMIT 50
    ");
    $stmt->execute([$empID]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($rows) {
        // Resolve fallback fields for every row
        foreach ($rows as &$row) {
            if (empty($row['office']))      $row['office']      = $row['branch_office'];
            if (empty($row['designation'])) $row['designation'] = $row['post_name'];
        }
        unset($row);
        echo json_encode(['success' => true, 'orders' => $rows, 'data' => $rows[0]]);
    } else {
        // No travel orders — still load employee basic info
        $stmt2 = $pdo->prepare("
            SELECT e.EmpID, e.employeeName, p.PostName AS designation, b.branch_name AS office
            FROM employees e
            LEFT JOIN posts    p ON p.PostId = e.designation
            LEFT JOIN branches b ON b.BrCode = e.BrCode
            WHERE e.EmpID = ? LIMIT 1
        ");
        $stmt2->execute([$empID]);
        $emp = $stmt2->fetch(PDO::FETCH_ASSOC);
        if ($emp) {
            echo json_encode(['success' => true, 'orders' => [], 'data' => [
                'EmpID'           => $emp['EmpID'],
                'employeeName'    => $emp['employeeName'],
                'designation'     => $emp['designation'],
                'office'          => $emp['office'],
                'order_id'        => null,
                'travel_order_no' => null,
                'purpose'         => '',
                'from_date'       => '',
                'to_date'         => '',
                'vehicle'         => '',
                'distance'        => '',
                'signature_date'  => date('Y-m-d'),
            ], 'note' => 'No travel order found. Employee info loaded.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Employee ID not found.']);
        }
    }
    exit;
}

// ── Upload directory ───────────────────────────────────────────────────────
$uploadDir = 'uploads/travel_bills/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

// ── Load record for editing ────────────────────────────────────────────────
$editData = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM travel_expenses WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $editData = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ── Handle form submission ─────────────────────────────────────────────────
$message  = '';
$formData = $editData ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // ── Validate document upload (server-side guard) ───────────────
        $hasFile = isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK;
        if (!$hasFile) {
            throw new Exception("कागजात / बिल अपलोड गर्नुपर्छ। (Document upload is required.)");
        }

        $documentPath = null;
        $file    = $_FILES['document'];
        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (in_array($fileExt, ['pdf','jpg','jpeg','png'])) {
            $newFileName = 'travel_bill_' . time() . '_' . uniqid() . '.' . $fileExt;
            $destination = $uploadDir . $newFileName;
            if (!move_uploaded_file($file['tmp_name'], $destination))
                throw new Exception("Failed to move uploaded file.");
            $documentPath = $destination;
        } else {
            throw new Exception("Invalid file type. Allowed: PDF, JPG, PNG");
        }

        $postEmpID = trim($_POST['emp_id'] ?? '');
        if ($postEmpID === '') {
            throw new Exception("Employee ID missing. Please reload the employee and resubmit.");
        }

        $stmt = $pdo->prepare("
            INSERT INTO travel_expenses
                (emp_id, name, position, office, purpose, from_date, to_date, vehicle,
                 distance, fare, airport, road_tax, daily_rate, days, hotel, other_exp,
                 advance, signature_date, remarks, document_path, travel_order_no)
            VALUES
                (:emp_id, :name, :position, :office, :purpose, :from_date, :to_date,
                 :vehicle, :distance, :fare, :airport, :road_tax, :daily_rate, :days,
                 :hotel, :other_exp, :advance, :signature_date, :remarks, :document_path,
                 :travel_order_no)
        ");

        $stmt->execute([
            ':emp_id'           => $postEmpID,
            ':name'             => trim($_POST['name']           ?? ''),
            ':position'         => trim($_POST['position']       ?? ''),
            ':office'           => trim($_POST['office']         ?? ''),
            ':purpose'          => trim($_POST['purpose']        ?? ''),
            ':from_date'        => $_POST['from_date']           ?: null,
            ':to_date'          => $_POST['to_date']             ?: null,
            ':vehicle'          => trim($_POST['vehicle']        ?? ''),
            ':distance'         => floatval($_POST['distance']   ?? 0),
            ':fare'             => floatval($_POST['fare']       ?? 0),
            ':airport'          => floatval($_POST['airport']    ?? 0),
            ':road_tax'         => floatval($_POST['road_tax']   ?? 0),
            ':daily_rate'       => floatval($_POST['daily_rate'] ?? 0),
            ':days'             => intval($_POST['days']         ?? 0),
            ':hotel'            => floatval($_POST['hotel']      ?? 0),
            ':other_exp'        => floatval($_POST['other_exp']  ?? 0),
            ':advance'          => floatval($_POST['advance']    ?? 0),
            ':signature_date'   => $_POST['signature_date']      ?: null,
            ':remarks'          => trim($_POST['remarks']        ?? ''),
            ':document_path'    => $documentPath,
            ':travel_order_no'  => trim($_POST['travel_order_no'] ?? '') ?: null,
        ]);

        $lastId  = $pdo->lastInsertId();
        $message = "✅ Expense record saved! ID: $lastId (EmpID: $postEmpID) — Document uploaded.";
        $formData = isset($_POST['save_and_new']) ? [] : $_POST;

    } catch (Exception $e) {
        $message = "❌ Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="ne">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>भत्ता तथा खर्च विवरण - Nepal Life Insurance</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
	    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f4f4f4; }
        .form-container { max-width: 1000px; margin: 0 auto; background: #fff; padding: 20px; border: 2px solid #000; }
        h1 { text-align: center; color: #003087; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; }
        input[type="text"], input[type="number"], input[type="date"] { width: 100%; padding: 5px; border: 1px solid #ccc; box-sizing: border-box; }
        input[type="file"] { width: 100%; padding: 5px; }
        input[readonly]    { background: #eef3fb; color: #333; }
        .totals            { font-weight: bold; }
        .signature         { margin-top: 20px; }
        .message           { padding: 10px; margin: 10px 0; border-radius: 5px; }
        .success           { background: #d4edda; color: #155724; }
        .error             { background: #f8d7da; color: #721c24; }

        /* ── Lookup bar ── */
        #emp-lookup-bar { background: #eef3fb; border: 2px solid #003087; border-radius: 8px; padding: 16px 20px; margin-bottom: 18px; }
        #emp-lookup-bar label { font-weight: 600; color: #003087; }
        .ac-wrap { position: relative; }
        #emp_code_input { width: 100%; padding: 8px 12px; font-size: 15px; border: 1px solid #003087; border-radius: 5px; box-sizing: border-box; }
        #ac-dropdown { position: absolute; top: 100%; left: 0; right: 0; z-index: 999; background: #fff; border: 1px solid #003087; border-top: none; border-radius: 0 0 5px 5px; max-height: 260px; overflow-y: auto; display: none; }
        .ac-item { padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #eee; font-size: 14px; }
        .ac-item:hover, .ac-item.active { background: #003087; color: #fff; }
        #btn_load_emp { padding: 8px 22px; background: #003087; color: #fff; border: none; border-radius: 5px; font-size: 15px; cursor: pointer; margin-top: 8px; }
        #btn_load_emp:hover { background: #00205f; }
        #emp_status { font-size: 14px; margin-top: 6px; display: block; min-height: 20px; }
        #emp_status.ok  { color: #155724; font-weight: 600; }
        #emp_status.err { color: #721c24; font-weight: 600; }
        .travel-order-badge { display: inline-block; background: #003087; color: #fff; font-size: 11px; padding: 2px 8px; border-radius: 10px; margin-left: 8px; }

        /* ── Form overlay ── */
        #mainFormWrap { position: relative; }
        #form-overlay { position: absolute; inset: 0; background: rgba(255,255,255,0.82); z-index: 10; display: flex; align-items: flex-start; justify-content: center; padding-top: 60px; }
        #form-overlay p { background: #fff3cd; color: #856404; border: 1px solid #ffc107; border-radius: 6px; padding: 16px 28px; font-size: 16px; font-weight: 600; text-align: center; }

        /* ── Info note ── */
        #info-note { background: #cff4fc; border: 1px solid #0dcaf0; border-radius: 5px; padding: 8px 14px; font-size: 13px; color: #055160; margin-bottom: 10px; display: none; }

        /* ── Travel Order Selector ── */
        #travel-order-selector {
            background: #f0f7ff;
            border: 2px solid #93c5fd;
            border-radius: 8px;
            padding: 14px 18px;
            margin-bottom: 14px;
            display: none;
        }
        #travel-order-selector label {
            font-weight: 700;
            color: #1d4ed8;
            font-size: 14px;
            margin-bottom: 6px;
            display: block;
        }
        #travel-order-selector select {
            width: 100%;
            padding: 8px 12px;
            border: 1.5px solid #93c5fd;
            border-radius: 6px;
            font-size: 14px;
            color: #1e3a5f;
            background: #fff;
            cursor: pointer;
        }
        #travel-order-selector select:focus { outline: none; border-color: #3b82f6; }
        .tada-badge {
            display: inline-block;
            background: #1d4ed8;
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            padding: 2px 9px;
            border-radius: 10px;
            letter-spacing: 0.5px;
            margin-left: 6px;
        }

        /* ── File upload highlight when missing ── */
        .file-required { border: 2px solid #dc3545 !important; border-radius: 4px; background: #fff5f5; }
        #file-label-row td { transition: background 0.3s; }

        /* ══════════════════════════════════════════
           DOCUMENT WARNING POPUP MODAL
        ══════════════════════════════════════════ */
        #docWarnModal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 99999;
            background: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
        }
        #docWarnModal.show { display: flex; }
        .doc-warn-box {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 10px 50px rgba(0,0,0,0.25);
            max-width: 420px;
            width: 92vw;
            overflow: hidden;
            border: 2px solid #dc3545;
            animation: popIn 0.22s ease;
        }
        @keyframes popIn {
            from { transform: scale(0.85); opacity: 0; }
            to   { transform: scale(1);    opacity: 1; }
        }
        .doc-warn-header {
            background: #dc3545;
            color: #fff;
            padding: 16px 22px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .doc-warn-header .warn-icon {
            font-size: 26px;
            line-height: 1;
        }
        .doc-warn-header h3 {
            margin: 0;
            font-size: 17px;
            font-weight: 700;
        }
        .doc-warn-body {
            padding: 22px 24px 10px;
            text-align: center;
        }
        .doc-warn-body .big-icon {
            font-size: 52px;
            display: block;
            margin-bottom: 12px;
        }
        .doc-warn-body p {
            font-size: 15px;
            color: #333;
            margin: 0 0 6px;
            font-weight: 600;
        }
        .doc-warn-body small {
            font-size: 13px;
            color: #666;
            display: block;
            margin-bottom: 4px;
        }
        .doc-warn-formats {
            display: inline-flex;
            gap: 8px;
            margin: 10px 0 4px;
        }
        .fmt-badge {
            background: #f0f4fa;
            color: #003087;
            border: 1px solid #b8d0ee;
            border-radius: 5px;
            padding: 3px 10px;
            font-size: 12px;
            font-weight: 700;
        }
        .doc-warn-footer {
            padding: 14px 24px 20px;
            text-align: center;
        }
        .btn-doc-ok {
            padding: 10px 36px;
            background: #dc3545;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
        }
        .btn-doc-ok:hover { background: #b02a37; }

        /* ══════════════════════════════════════════
           EXISTING RECORDS MODAL
        ══════════════════════════════════════════ */
        #existingModal { display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,0.55); align-items: center; justify-content: center; }
        #existingModal.show { display: flex; }
        .modal-box { background: #fff; border-radius: 10px; box-shadow: 0 8px 40px rgba(0,0,40,0.22); max-width: 860px; width: 96vw; max-height: 88vh; display: flex; flex-direction: column; overflow: hidden; border: 2px solid #003087; }
        .modal-header { background: #003087; color: #fff; padding: 16px 22px; display: flex; align-items: center; justify-content: space-between; flex-shrink: 0; }
        .modal-header h2 { margin: 0; font-size: 17px; font-weight: 700; }
        .emp-badge { background: rgba(255,255,255,0.18); border-radius: 20px; padding: 4px 14px; font-size: 13px; margin-left: 10px; }
        .modal-close { background: none; border: none; color: #fff; font-size: 24px; cursor: pointer; line-height: 1; padding: 0 4px; }
        .modal-close:hover { color: #ffc107; }
        .modal-subheader { background: #fff8e1; border-bottom: 1px solid #ffe082; padding: 10px 22px; font-size: 13px; color: #7a5800; flex-shrink: 0; }
        .modal-body { overflow-y: auto; padding: 0; flex: 1; }
        .records-table { width: 100%; border-collapse: collapse; }
        .records-table thead th { background: #f0f4fa; color: #003087; font-size: 12px; font-weight: 700; padding: 10px 12px; border-bottom: 2px solid #003087; text-align: left; position: sticky; top: 0; z-index: 1; }
        .records-table tbody tr { cursor: pointer; transition: background 0.15s; border-bottom: 1px solid #e8edf5; }
        .records-table tbody tr:hover { background: #e8f0fe; }
        .records-table tbody tr.selected { background: #d0e4ff; outline: 2px solid #003087; }
        .records-table td { padding: 10px 12px; font-size: 13px; vertical-align: top; border: none; }
        .record-name { font-weight: 700; color: #003087; font-size: 14px; }
        .record-dates { color: #555; font-size: 12px; margin-top: 2px; }
        .record-id-badge { display: inline-block; background: #e8f0fe; color: #003087; border-radius: 4px; padding: 2px 7px; font-size: 11px; font-weight: 700; margin-bottom: 3px; }
        .amount-cell { font-weight: 700; color: #1a5c1a; font-size: 13px; }
        .modal-footer { padding: 14px 22px; border-top: 1px solid #dde3ee; display: flex; gap: 10px; align-items: center; justify-content: flex-end; flex-shrink: 0; background: #f8faff; }
        .btn-select-record { padding: 9px 26px; background: #003087; color: #fff; border: none; border-radius: 5px; font-size: 15px; cursor: pointer; font-weight: 600; }
        .btn-select-record:disabled { background: #aaa; cursor: not-allowed; }
        .btn-select-record:not(:disabled):hover { background: #00205f; }
        .btn-fresh { padding: 9px 22px; background: #fff; color: #555; border: 1px solid #bbb; border-radius: 5px; font-size: 14px; cursor: pointer; }
        .btn-fresh:hover { background: #f5f5f5; }
        .select-hint { font-size: 12px; color: #888; flex: 1; }
        .row-radio { width: 18px; height: 18px; border-radius: 50%; border: 2px solid #bbb; display: inline-block; background: #fff; vertical-align: middle; }
        tr.selected .row-radio { background: #003087; border-color: #003087; }

        /* ── Status badges ── */
        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.4px;
            text-transform: uppercase;
        }
        .status-approved {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #6ee7b7;
        }
        .status-pending {
            background: #fef9c3;
            color: #854d0e;
            border: 1px solid #fde047;
        }

        /* ── Approved rows — not selectable ── */
        .records-table tbody tr.row-approved {
            cursor: not-allowed;
            background: #f9f9f9;
            opacity: 0.72;
        }
        .records-table tbody tr.row-approved:hover {
            background: #f9f9f9;
        }
        .records-table tbody tr.row-approved .row-radio {
            display: none;
        }
        .approved-lock {
            font-size: 14px;
            color: #999;
        }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>


<div class="form-container">
				 <a href="travel.php" class="btn btn-info btn-lg">
					Apply Travel Order
				 </a>
				<br>

    <center><img src="images/logo.png" alt="Nepal Life Logo" height="80" width="550"></center><br>
    <h1>भत्ता तथा खर्च विवरण</h1>

    <?php if ($message): ?>
    <div id="autoMessage" class="message <?= strpos($message,'✅') !== false ? 'success' : 'error' ?>">
        <?= htmlspecialchars($message) ?>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const m = document.getElementById('autoMessage');
        if (m) setTimeout(() => {
            m.style.transition = 'all 0.6s ease';
            m.style.opacity    = '0';
            m.style.transform  = 'translateY(-10px)';
            setTimeout(() => m.remove(), 600);
        }, 3000);
    });
    </script>
    <?php endif; ?>

    <!-- ══ LOOKUP BAR ══ -->
    <div id="emp-lookup-bar">
        <label for="emp_code_input">कर्मचारी कोड खोज्नुहोस् / Search Employee (EmpID or Name):</label>
        <div class="ac-wrap mt-1">
            <input type="text" id="emp_code_input"
                   placeholder="e.g. E00126 or Suraj Chapagain"
                   autocomplete="off">
            <div id="ac-dropdown"></div>
        </div>
        <button type="button" id="btn_load_emp">Load ➜</button>
        <span id="emp_status"></span>
    </div>

    <div id="info-note">
        ℹ️ यस कर्मचारीको कुनै Travel Order फेला परेन। कर्मचारी जानकारी मात्र लोड भएको छ।
    </div>

    <!-- ══ TRAVEL ORDER SELECTOR ══ -->
    <div id="travel-order-selector">
        <label for="travel-order-select">
            🗂️ Travel Order छान्नुहोस् (Select Travel Order):
        </label>
        <select id="travel-order-select" onchange="onTravelOrderSelect(this.value)">
            <option value="">— Travel Order छान्नुहोस् —</option>
        </select>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════
         DOCUMENT WARNING POPUP
    ═══════════════════════════════════════════════════════════════════════ -->
    <div id="docWarnModal" role="alertdialog" aria-modal="true" aria-labelledby="docWarnTitle">
        <div class="doc-warn-box">
            <div class="doc-warn-header">
                <span class="warn-icon">⚠️</span>
                <h3 id="docWarnTitle">कागजात अपलोड गर्नुहोस्!</h3>
            </div>
            <div class="doc-warn-body">
                <span class="big-icon">📄</span>
                <p>भ्रमण सम्बन्धी कागजात / बिल अपलोड अनिवार्य छ।</p>
                <small>Document upload is required before submitting.</small>
                <small>कृपया तलका फर्म्याटमध्ये एउटा फाइल छान्नुहोस्:</small>
                <div class="doc-warn-formats">
                    <span class="fmt-badge">PDF</span>
                    <span class="fmt-badge">JPG</span>
                    <span class="fmt-badge">JPEG</span>
                    <span class="fmt-badge">PNG</span>
                </div>
                <small style="color:#888;font-size:12px;">अधिकतम साइज: ५ MB</small>
            </div>
            <div class="doc-warn-footer">
                <button class="btn-doc-ok" id="btn-doc-ok">ठीक छ, अपलोड गर्छु</button>
            </div>
        </div>
    </div>

    <!-- ══ EXISTING RECORDS MODAL ══ -->
    <div id="existingModal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <div class="modal-box">
            <div class="modal-header">
                <div style="display:flex;align-items:center;">
                    <h2 id="modalTitle">⚠️ पहिलेका खर्च रेकर्डहरू भेटियो</h2>
                    <span class="emp-badge" id="modal-emp-name">–</span>
                </div>
                <button class="modal-close" id="modal-close-btn" aria-label="Close">&times;</button>
            </div>
            <div class="modal-subheader">
                यस कर्मचारीको <strong id="modal-count">0</strong> वटा पहिलेका खर्च रेकर्ड फेला परे।
                तलबाट एउटा छान्नुहोस् अथवा <em>Start Fresh</em> थिचेर नयाँ विवरण भर्नुहोस्।
            </div>
            <div class="modal-body">
                <table class="records-table">
                    <thead>
                        <tr>
                            <th style="width:32px;"></th>
                            <th>नाम / ID</th>
                            <th>Travel Order No</th>
                            <th>भ्रमण मिति</th>
                            <th>उद्देश्य</th>
                            <th>जम्मा रकम (रू.)</th>
                            <th>स्थिति</th>
                        </tr>
                    </thead>
                    <tbody id="modal-records-body"></tbody>
                </table>
            </div>
            <div class="modal-footer">
                <span class="select-hint" id="modal-select-hint">माथिबाट एउटा रेकर्ड छान्नुहोस्।</span>
                <button class="btn-fresh"         id="btn-modal-fresh">✦ Start Fresh (नयाँ)</button>
                <button class="btn-select-record" id="btn-modal-select" disabled>✔ यो रेकर्ड प्रयोग गर्नुहोस्</button>
            </div>
        </div>
    </div>

    <!-- ══ MAIN FORM ══ -->
    <div id="mainFormWrap">
        <div id="form-overlay">
            <p>⬆️ माथि कर्मचारी कोड वा नाम खोजी Load गर्नुहोस् ।<br>
               Search and load an Employee above to proceed.</p>
        </div>

        <form method="POST" enctype="multipart/form-data" id="expenseForm">
            <input type="hidden" name="emp_id"          id="h_emp_id">
            <input type="hidden" name="travel_order_id" id="h_order_id">
            <input type="hidden" name="travel_order_no" id="h_travel_order_no">

            <table>
                <tr>
                    <td width="18%">नाम :</td>
                    <td colspan="3">
                        <input type="text" name="name" id="f_name" required
                               placeholder="पूरा नाम लेख्नुहोस्"
                               value="<?= htmlspecialchars($formData['name'] ?? '') ?>">
                    </td>
                </tr>
                <tr>
                    <td>Travel Order No :</td>
                    <td colspan="3">
                        <input type="text" id="f_travel_order_no" readonly
                               placeholder="— कुनै Travel Order छैन —"
                               value="<?= htmlspecialchars($formData['travel_order_no'] ?? '') ?>"
                               style="background:#eef3fb;color:#003087;font-weight:600;">
                    </td>
                </tr>
                <tr>
                    <td>भ्रमण मिति :</td>
                    <td colspan="3">
                        देखि&nbsp;
                        <input type="date" name="from_date" id="f_from_date" required style="width:auto"
                               value="<?= htmlspecialchars($formData['from_date'] ?? '') ?>">
                        &nbsp;सम्म&nbsp;
                        <input type="date" name="to_date" id="f_to_date" required style="width:auto"
                               value="<?= htmlspecialchars($formData['to_date'] ?? '') ?>">
                    </td>
                </tr>
                <tr>
                    <td>पद :</td>
                    <td>
                        <input type="text" name="position" id="f_position" required
                               placeholder="पद लेख्नुहोस्"
                               value="<?= htmlspecialchars($formData['position'] ?? '') ?>">
                    </td>
                    <td width="20%">भ्रमण उद्देश्य :</td>
                    <td>
                        <input type="text" name="purpose" id="f_purpose" required
                               placeholder="भ्रमणको उद्देश्य लेख्नुहोस्"
                               value="<?= htmlspecialchars($formData['purpose'] ?? '') ?>">
                    </td>
                </tr>
                <tr>
                    <td>कार्यालय :</td>
                    <td colspan="3">
                        <input type="text" name="office" id="f_office" required
                               placeholder="कार्यालयको नाम लेख्नुहोस्"
                               value="<?= htmlspecialchars($formData['office'] ?? '') ?>">
                    </td>
                </tr>
            </table>

            <!-- ══ DOCUMENT UPLOAD — highlighted red if missing ══ -->
            <h3>४. कागजात / बिल अपलोड <span style="color:#dc3545;font-size:14px;">*अनिवार्य</span></h3>
            <table>
                <tr id="file-label-row">
                    <td>भ्रमण सम्बन्धी कागजात / बिल अपलोड गर्नुहोस्:</td>
                    <td>
                        <input type="file" name="document" id="fileInput"
                               accept=".pdf,.jpg,.jpeg,.png">
                        <div id="file-status" style="font-size:12px;margin-top:4px;color:#888;">
                            कुनै फाइल छानिएको छैन।
                        </div>
                    </td>
                </tr>
                <tr>
                    <td colspan="2" style="font-size:.85em;color:#555;">
                        (PDF, JPG, PNG) — अधिकतम ५ MB
                    </td>
                </tr>
            </table>

            <h3>१. भ्रमण विवरण (देखी – सम्म)</h3>
            <table>
                <thead>
                    <tr>
                        <th>भ्रमण साधन</th>
                        <th>जम्मा दूरी कि.मि.</th>
                        <th>भाडा/इन्धन (रू.)</th>
                        <th>कैफियत</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><input type="text"   name="vehicle"  id="f_vehicle"  placeholder="भ्रमण साधन"
                                   value="<?= htmlspecialchars($formData['vehicle']  ?? '') ?>"></td>
                        <td><input type="number" name="distance" id="f_distance" step="0.01"
                                   oninput="calculateTotals()" placeholder="दुरी"
                                   value="<?= htmlspecialchars($formData['distance'] ?? '') ?>"></td>
                        <td><input type="number" name="fare"     step="0.01"
                                   oninput="calculateTotals()" placeholder="रकम"
                                   value="<?= htmlspecialchars($formData['fare']     ?? '') ?>"></td>
                        <td><input type="text"   name="remarks"  placeholder="कैफियत"
                                   value="<?= htmlspecialchars($formData['remarks']  ?? '') ?>"></td>
                    </tr>
                    <tr>
                        <td colspan="3">२. एयरपोर्ट ट्याक्स तथा ट्याक्सी खर्च</td>
                        <td><input type="number" name="airport"  step="0.01"
                                   oninput="calculateTotals()" placeholder="रकम"
                                   value="<?= htmlspecialchars($formData['airport']  ?? '') ?>"></td>
                    </tr>
                    <tr>
                        <td colspan="3">३. अन्य खर्च : सडक कर</td>
                        <td><input type="number" name="road_tax" step="0.01"
                                   oninput="calculateTotals()" placeholder="रकम"
                                   value="<?= htmlspecialchars($formData['road_tax'] ?? '') ?>"></td>
                    </tr>
                    <tr class="totals">
                        <td colspan="3">(क) जम्मा भाडा/इन्धन खर्च</td>
                        <td id="total_fare">0.00</td>
                    </tr>
                </tbody>
            </table>

            <table>
                <tr>
                    <td>१. दैनिक भ्रमण भत्ता रू.<br>
                        <input type="number" name="daily_rate" step="0.01"
                               placeholder="प्रति दिन रकम" oninput="calculateTotals()"
                               value="<?= htmlspecialchars($formData['daily_rate'] ?? '') ?>">
                    </td>
                    <td><br>
                        <input type="number" name="days" placeholder="दिन संख्या"
                               oninput="calculateTotals()"
                               value="<?= htmlspecialchars($formData['days'] ?? '') ?>">
                    </td>
                    <td class="totals">(ख) जम्मा दैनिक भत्ता</td>
                    <td id="total_daily">0.00</td>
                </tr>
                <tr>
                    <td colspan="2">२. होटेल खर्च (खाना तथा बास)</td>
                    <td><input type="number" name="hotel" step="0.01"
                               oninput="calculateTotals()" placeholder="रकम"
                               value="<?= htmlspecialchars($formData['hotel']     ?? '') ?>"></td>
                    <td></td>
                </tr>
                <tr>
                    <td colspan="2">३. अन्य खर्च :</td>
                    <td><input type="number" name="other_exp" step="0.01"
                               oninput="calculateTotals()" placeholder="रकम"
                               value="<?= htmlspecialchars($formData['other_exp'] ?? '') ?>"></td>
                    <td></td>
                </tr>
                <tr class="totals">
                    <td colspan="2">(ग) जम्मा होटेल खर्च</td>
                    <td id="total_hotel">0.00</td>
                    <td></td>
                </tr>
            </table>

            <table>
                <tr>
                    <td>(घ) भ्रमण प्रयोजनको लागि लिएको पेशकी / समायोजन</td>
                    <td><input type="number" name="advance" step="0.01"
                               oninput="calculateTotals()" placeholder="पेशकी रकम"
                               value="<?= htmlspecialchars($formData['advance']   ?? '') ?>"></td>
                </tr>
                <tr class="totals">
                    <td>(ङ) भुक्तानी पाउनु पर्ने / (तिर्नुपर्ने रकम) [क+ख+ग−घ]</td>
                    <td id="net_amount">0.00</td>
                </tr>
            </table>

            <div class="signature">
                मिति:
                <input type="date" name="signature_date" id="f_signature_date" style="width:auto"
                       value="<?= htmlspecialchars($formData['signature_date'] ?? date('Y-m-d')) ?>"
                       readonly>
            </div>

            <div style="text-align:center;margin-top:30px;">
                <button type="button" id="btnSubmit"
                        style="padding:12px 30px;font-size:16px;background:#003087;color:#fff;border:none;cursor:pointer;border-radius:4px;">
                    Submit
                </button>
                <button type="button"
                        onclick="window.location.href='View_ExpenseRecords.php';"
                        style="padding:12px 30px;font-size:16px;margin-left:10px;border-radius:4px;border:1px solid #ccc;cursor:pointer;">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// ══════════════════════════════════════════════════════════════
//  DOM REFS
// ══════════════════════════════════════════════════════════════
const overlay      = document.getElementById('form-overlay');
const statusEl     = document.getElementById('emp_status');
const infoNote     = document.getElementById('info-note');
const empInput     = document.getElementById('emp_code_input');
const dropdown     = document.getElementById('ac-dropdown');
const btnLoad      = document.getElementById('btn_load_emp');
const modal        = document.getElementById('existingModal');
const modalBody    = document.getElementById('modal-records-body');
const btnSelect    = document.getElementById('btn-modal-select');
const btnFresh     = document.getElementById('btn-modal-fresh');
const hEmpID       = document.getElementById('h_emp_id');
const hOrderID     = document.getElementById('h_order_id');
const fileInput    = document.getElementById('fileInput');
const fileStatus   = document.getElementById('file-status');
const docWarnModal = document.getElementById('docWarnModal');
const btnDocOk     = document.getElementById('btn-doc-ok');
const btnSubmit    = document.getElementById('btnSubmit');
const expenseForm  = document.getElementById('expenseForm');

// ══════════════════════════════════════════════════════════════
//  STATE
// ══════════════════════════════════════════════════════════════
let selectedEmpID       = '';
let acItems             = [];
let acIndex             = -1;
let currentEmpData      = null;
let allTravelOrders     = [];   // all orders for the loaded employee
let existingRecords     = [];
let selectedRecordIndex = -1;

// ══════════════════════════════════════════════════════════════
//  LOCK / UNLOCK FORM
// ══════════════════════════════════════════════════════════════
function lockForm()   { overlay.style.display = 'flex'; }
function unlockForm() { overlay.style.display = 'none'; }
lockForm();

// Pre-fill travel_order_no if redirected from travel.php
(function () {
    const params = new URLSearchParams(window.location.search);
    const ton    = params.get('travel_order_no');
    if (ton) {
        const hField = document.getElementById('h_travel_order_no');
        const vField = document.getElementById('f_travel_order_no');
        if (hField) hField.value = ton;
        if (vField) {
            vField.value = ton;
            vField.style.borderColor = '#2563eb';
            vField.style.background  = '#dbeafe';
        }
    }
})();

// ══════════════════════════════════════════════════════════════
//  FILE INPUT — live status + clear red border on selection
// ══════════════════════════════════════════════════════════════
fileInput.addEventListener('change', () => {
    if (fileInput.files.length > 0) {
        const f    = fileInput.files[0];
        const size = (f.size / 1024).toFixed(1);
        fileStatus.textContent = `✔ ${f.name} (${size} KB)`;
        fileStatus.style.color = '#155724';
        fileInput.classList.remove('file-required');
        document.getElementById('file-label-row').style.background = '';
    } else {
        fileStatus.textContent = 'कुनै फाइल छानिएको छैन।';
        fileStatus.style.color = '#888';
    }
});

// ══════════════════════════════════════════════════════════════
//  DOCUMENT WARNING POPUP
// ══════════════════════════════════════════════════════════════
function showDocWarn() {
    // Highlight the file input row
    fileInput.classList.add('file-required');
    document.getElementById('file-label-row').style.background = '#fff5f5';
    // Show popup
    docWarnModal.classList.add('show');
}

function closeDocWarn() {
    docWarnModal.classList.remove('show');
    // Scroll to file input and focus it
    fileInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
    setTimeout(() => fileInput.focus(), 400);
}

btnDocOk.addEventListener('click', closeDocWarn);

// Close on backdrop click
docWarnModal.addEventListener('click', e => {
    if (e.target === docWarnModal) closeDocWarn();
});

// Close on Escape key
document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && docWarnModal.classList.contains('show')) closeDocWarn();
});

// ══════════════════════════════════════════════════════════════
//  SUBMIT BUTTON — intercept, validate, then submit
// ══════════════════════════════════════════════════════════════
btnSubmit.addEventListener('click', () => {
    // 1. Check emp_id
    if (!hEmpID.value.trim()) {
        alert('कर्मचारी ID (emp_id) खाली छ। कृपया पुनः Load गर्नुहोस्।');
        return;
    }
    // 2. Check document — show popup if missing
    if (!fileInput.files || fileInput.files.length === 0) {
        showDocWarn();
        return;
    }
    // 3. All good — submit the form
    expenseForm.submit();
});

// ══════════════════════════════════════════════════════════════
//  HELPERS
// ══════════════════════════════════════════════════════════════
function setField(id, value, readOnly = false) {
    const el = document.getElementById(id);
    if (!el) return;
    el.value    = value ?? '';
    el.readOnly = readOnly;
    el.style.background = readOnly ? '#eef3fb' : '';
}
function setInput(name, value) {
    const el = document.querySelector(`input[name="${name}"]`);
    if (el) el.value = value ?? '';
}

// ══════════════════════════════════════════════════════════════
//  AUTOCOMPLETE
// ══════════════════════════════════════════════════════════════
let acTimer;
empInput.addEventListener('input', () => {
    clearTimeout(acTimer);
    selectedEmpID = '';
    const q = empInput.value.trim();
    if (!q) { hideDropdown(); return; }
    acTimer = setTimeout(() => fetchSuggestions(q), 220);
});

async function fetchSuggestions(q) {
    try {
        const res = await fetch(`?search_emp=1&q=${encodeURIComponent(q)}`);
        acItems   = await res.json();
        renderDropdown();
    } catch { hideDropdown(); }
}

function renderDropdown() {
    if (!acItems.length) { hideDropdown(); return; }
    dropdown.innerHTML = '';
    acItems.forEach((item, i) => {
        const div = document.createElement('div');
        div.className = 'ac-item';
        div.innerHTML = `<strong>${item.EmpID}</strong> – ${item.employeeName}
                         <br><small>${item.designation ?? ''} | ${item.office ?? ''}</small>`;
        div.addEventListener('mousedown', () => selectItem(i));
        dropdown.appendChild(div);
    });
    acIndex = -1;
    dropdown.style.display = 'block';
}

function selectItem(i) {
    selectedEmpID  = acItems[i].EmpID;
    empInput.value = `${acItems[i].EmpID} – ${acItems[i].employeeName}`;
    hideDropdown();
}

function hideDropdown() { dropdown.style.display = 'none'; }

empInput.addEventListener('keydown', e => {
    if (dropdown.style.display === 'none') {
        if (e.key === 'Enter') { e.preventDefault(); loadEmployee(); }
        return;
    }
    const items = dropdown.querySelectorAll('.ac-item');
    if (e.key === 'ArrowDown') {
        acIndex = Math.min(acIndex + 1, items.length - 1);
        items.forEach((el, i) => el.classList.toggle('active', i === acIndex));
        e.preventDefault();
    } else if (e.key === 'ArrowUp') {
        acIndex = Math.max(acIndex - 1, -1);
        items.forEach((el, i) => el.classList.toggle('active', i === acIndex));
        e.preventDefault();
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (acIndex >= 0) { selectItem(acIndex); hideDropdown(); }
        else loadEmployee();
    } else if (e.key === 'Escape') { hideDropdown(); }
});

document.addEventListener('click', e => {
    if (!e.target.closest('.ac-wrap')) hideDropdown();
});

// ══════════════════════════════════════════════════════════════
//  LOAD EMPLOYEE
// ══════════════════════════════════════════════════════════════
btnLoad.addEventListener('click', loadEmployee);

async function loadEmployee() {
    let empID = selectedEmpID || empInput.value.trim().split(/[\s–\-]/)[0].trim();
    if (!empID) {
        statusEl.className   = 'err';
        statusEl.textContent = '⚠️ कर्मचारी कोड खाली छ।';
        lockForm(); return;
    }

    statusEl.className     = '';
    statusEl.textContent   = 'Loading…';
    infoNote.style.display = 'none';
    document.getElementById('travel-order-selector').style.display = 'none';

    try {
        const res1  = await fetch(`?fetch_travel_order=1&emp_id=${encodeURIComponent(empID)}`);
        const json1 = await res1.json();

        if (!json1.success) {
            statusEl.className   = 'err';
            statusEl.textContent = `❌ ${json1.message ?? 'फेला परेन।'}`;
            lockForm(); return;
        }

        currentEmpData  = json1.data;
        allTravelOrders = json1.orders ?? [];

        if (!currentEmpData.EmpID) {
            statusEl.className   = 'err';
            statusEl.textContent = '❌ Server returned no EmpID. Cannot proceed.';
            lockForm(); return;
        }

        // Populate the travel order selector if there are multiple orders
        populateTravelOrderSelector(allTravelOrders);

        // Check for existing expense records
        let existingRecs = [];
        try {
            const res2  = await fetch(`?check_existing_expenses=1&emp_id=${encodeURIComponent(currentEmpData.EmpID)}`);
            const json2 = await res2.json();
            if (json2.success && Array.isArray(json2.records))
                existingRecs = json2.records;
        } catch (e2) {
            console.warn('check_existing_expenses (non-fatal):', e2);
        }

        if (existingRecs.length > 0) {
            existingRecords = existingRecs;
            openExistingModal(existingRecs, currentEmpData.employeeName, currentEmpData.EmpID);
        } else {
            populateFormFromTravelOrder(currentEmpData, json1.note ?? null);
        }

    } catch (err) {
        console.error(err);
        statusEl.className   = 'err';
        statusEl.textContent = '❌ Server error. पुनः प्रयास गर्नुहोस्।';
        lockForm();
    }
}

// ══════════════════════════════════════════════════════════════
//  TRAVEL ORDER SELECTOR
// ══════════════════════════════════════════════════════════════
function populateTravelOrderSelector(orders) {
    const wrap   = document.getElementById('travel-order-selector');
    const select = document.getElementById('travel-order-select');
    select.innerHTML = '<option value="">— Travel Order छान्नुहोस् —</option>';

    if (!orders || orders.length === 0) {
        wrap.style.display = 'none';
        return;
    }

    orders.forEach((o, idx) => {
        const opt   = document.createElement('option');
        opt.value   = idx;
        const label = o.travel_order_no
            ? `${o.travel_order_no}  |  ${o.purpose || ''}  |  ${o.from_date || ''} → ${o.to_date || ''}`
            : `#${o.order_id}  |  ${o.purpose || ''}  |  ${o.from_date || ''} → ${o.to_date || ''}`;
        opt.textContent = label;
        select.appendChild(opt);
    });

    // Auto-select the first (most recent) order
    select.value = '0';
    wrap.style.display = 'block';
}

function onTravelOrderSelect(val) {
    if (val === '' || !allTravelOrders[val]) return;
    const order = allTravelOrders[parseInt(val)];
    currentEmpData = { ...currentEmpData, ...order };
    populateFormFromTravelOrder(currentEmpData, null);
}

// ══════════════════════════════════════════════════════════════
//  POPULATE FROM TRAVEL ORDER
// ══════════════════════════════════════════════════════════════
function populateFormFromTravelOrder(d, note) {
    setField('f_name',           d.employeeName,  true);
    setField('f_position',       d.designation,   true);
    setField('f_office',         d.office,        true);
    setField('f_purpose',        d.purpose        || '', false);
    setField('f_from_date',      d.from_date      || '', false);
    setField('f_to_date',        d.to_date        || '', false);
    setField('f_vehicle',        d.vehicle        || '', false);
    setField('f_distance',       d.distance       || '', false);
    setField('f_signature_date', d.from_date      || '<?= date('Y-m-d') ?>', false);

    hEmpID.value   = d.EmpID;
    hOrderID.value = d.order_id ?? '';
    document.getElementById('h_travel_order_no').value  = d.travel_order_no ?? '';
    document.getElementById('f_travel_order_no').value  = d.travel_order_no ?? '';

    const badge = d.travel_order_no
        ? `<span class="travel-order-badge">Order: ${d.travel_order_no}</span>` : '';
    statusEl.className = 'ok';
    statusEl.innerHTML = `✅ ${d.employeeName} (${d.EmpID}) — लोड भयो। ${badge}`;

    if (note) infoNote.style.display = 'block';
    unlockForm();
    calculateTotals();
}

// ══════════════════════════════════════════════════════════════
//  POPULATE FROM EXISTING RECORD
// ══════════════════════════════════════════════════════════════
function populateFormFromRecord(rec) {
    setField('f_name',           currentEmpData.employeeName, true);
    setField('f_position',       currentEmpData.designation,  true);
    setField('f_office',         currentEmpData.office,       true);
    setField('f_purpose',        rec.purpose        || '', false);
    setField('f_from_date',      rec.from_date      || '', false);
    setField('f_to_date',        rec.to_date        || '', false);
    setField('f_vehicle',        rec.vehicle        || '', false);
    setField('f_distance',       rec.distance       || '', false);
    setField('f_signature_date', rec.signature_date || '', false);

    hEmpID.value   = currentEmpData.EmpID;
    hOrderID.value = currentEmpData.order_id ?? '';
    const recOrderNo = rec.travel_order_no || rec.linked_order_no || '';
    document.getElementById('h_travel_order_no').value = recOrderNo;
    document.getElementById('f_travel_order_no').value = recOrderNo;

    ['fare','airport','road_tax','daily_rate','days','hotel','other_exp','advance','remarks']
        .forEach(n => setInput(n, rec[n] ?? ''));

    // Check approval status
    if (rec.status && rec.status.toLowerCase() === 'approved') {
        statusEl.className = 'ok';
        statusEl.innerHTML = `✅ ${currentEmpData.employeeName} (${currentEmpData.EmpID}) — रेकर्ड #${rec.id} स्वीकृत (Approved). सम्पादन गर्न सकिँदैन।`;
        // Disable editing by locking the form
        lockForm();
    } else {
        statusEl.className = 'ok';
        statusEl.innerHTML = `✅ ${currentEmpData.employeeName} (${currentEmpData.EmpID}) — रेकर्ड #${rec.id} लोड भयो।`;
        unlockForm();
    }
    calculateTotals();
}

// ══════════════════════════════════════════════════════════════
//  EXISTING RECORDS MODAL
// ══════════════════════════════════════════════════════════════
function fmtDate(d) {
    if (!d) return '–';
    const p = String(d).split('-');
    return p.length === 3 ? `${p[2]}/${p[1]}/${p[0]}` : d;
}
function calcNet(r) {
    const fare  = (+r.fare||0) + (+r.airport||0) + (+r.road_tax||0);
    const daily = (+r.daily_rate||0) * (+r.days||0);
    const hotel = (+r.hotel||0) + (+r.other_exp||0);
    return (fare + daily + hotel - (+r.advance||0)).toFixed(2);
}

function openExistingModal(records, empName, empID) {
    document.getElementById('modal-emp-name').textContent = `${empName} (${empID})`;
    document.getElementById('modal-count').textContent    = records.length;
    document.getElementById('modal-select-hint').textContent = 'माथिबाट एउटा रेकर्ड छान्नुहोस्।';
    selectedRecordIndex = -1;
    btnSelect.disabled  = true;
    modalBody.innerHTML = '';

    records.forEach((rec, idx) => {
        const isApproved = rec.status && rec.status.toLowerCase() === 'approved';
        const tr = document.createElement('tr');
        if (isApproved) tr.classList.add('row-approved');

        const statusBadge = isApproved
            ? `<span class="status-badge status-approved">✔ Approved</span>`
            : `<span class="status-badge status-pending">⏳ Pending</span>`;

        const radioOrLock = isApproved
            ? `<span class="approved-lock" title="Approved — cannot select">🔒</span>`
            : `<span class="row-radio"></span>`;

        const orderNo = rec.travel_order_no || rec.linked_order_no || '–';
        const orderBadge = (rec.travel_order_no || rec.linked_order_no)
            ? `<span style="display:inline-block;background:#e8f0fe;color:#003087;border-radius:4px;padding:2px 7px;font-size:11px;font-weight:700;">${orderNo}</span>`
            : `<span style="color:#bbb;font-size:12px;">—</span>`;

        tr.innerHTML = `
            <td>${radioOrLock}</td>
            <td>
                <span class="record-id-badge">#${rec.id}</span><br>
                <span class="record-name">${rec.name || '–'}</span>
                <div class="record-dates">${fmtDate(rec.from_date)} – ${fmtDate(rec.to_date)}</div>
            </td>
            <td>${orderBadge}</td>
            <td>
                <div style="font-size:13px;">${fmtDate(rec.from_date)}</div>
                <div style="font-size:11px;color:#888;">देखि</div>
                <div style="font-size:13px;">${fmtDate(rec.to_date)}</div>
                <div style="font-size:11px;color:#888;">सम्म</div>
            </td>
            <td class="record-purpose">${rec.purpose || '–'}</td>
            <td class="amount-cell">रू.&nbsp;${calcNet(rec)}</td>
            <td>${statusBadge}</td>
        `;

        if (!isApproved) {
            tr.addEventListener('click', () => selectModalRow(idx));
        }
        modalBody.appendChild(tr);
    });

    modal.classList.add('show');
}

function selectModalRow(idx) {
    const rec = existingRecords[idx];
    if (rec.status && rec.status.toLowerCase() === 'approved') return; // not selectable
    selectedRecordIndex = idx;
    modalBody.querySelectorAll('tr').forEach((tr, i) => tr.classList.toggle('selected', i === idx));
    btnSelect.disabled = false;
    document.getElementById('modal-select-hint').textContent =
        `#${rec.id} छानिएको — ${fmtDate(rec.from_date)} देखि ${fmtDate(rec.to_date)} सम्म`;
}

function closeModal() {
    modal.classList.remove('show');
    selectedRecordIndex = -1;
}

btnSelect.addEventListener('click', () => {
    if (selectedRecordIndex < 0) return;
    populateFormFromRecord(existingRecords[selectedRecordIndex]);
    closeModal();
});

btnFresh.addEventListener('click', () => {
    closeModal();
    populateFormFromTravelOrder(currentEmpData, null);
    ['fare','airport','road_tax','daily_rate','days','hotel','other_exp','advance','remarks']
        .forEach(n => setInput(n, ''));
    // travel_order_no stays from currentEmpData (already set in populateFormFromTravelOrder)
    calculateTotals();
});

document.getElementById('modal-close-btn').addEventListener('click', () => {
    closeModal(); lockForm();
    statusEl.className   = 'err';
    statusEl.textContent = '⚠️ रेकर्ड छानिएन। पुनः Load गर्नुहोस् वा Start Fresh थिच्नुहोस्।';
});
modal.addEventListener('click', e => {
    if (e.target === modal) {
        closeModal(); lockForm();
        statusEl.className   = 'err';
        statusEl.textContent = '⚠️ रेकर्ड छानिएन। पुनः Load गर्नुहोस्।';
    }
});

// ══════════════════════════════════════════════════════════════
//  TOTALS
// ══════════════════════════════════════════════════════════════
function v(name) {
    return parseFloat(document.querySelector(`input[name="${name}"]`)?.value) || 0;
}
function calculateTotals() {
    const tFare  = v('fare') + v('airport') + v('road_tax');
    const tDaily = v('daily_rate') * v('days');
    const tHotel = v('hotel') + v('other_exp');
    document.getElementById('total_fare').textContent  = tFare.toFixed(2);
    document.getElementById('total_daily').textContent = tDaily.toFixed(2);
    document.getElementById('total_hotel').textContent = tHotel.toFixed(2);
    document.getElementById('net_amount').textContent  = (tFare + tDaily + tHotel - v('advance')).toFixed(2);
}
window.addEventListener('load', calculateTotals);
document.querySelectorAll('input[type="number"]').forEach(i => i.addEventListener('input', calculateTotals));
</script>
</body>
</html>