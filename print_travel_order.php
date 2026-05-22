<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Travel Order</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Outfit', Arial, sans-serif; 
            padding: 40px;
            background-color: #f8fafc;
            color: #1e293b;
        }
        .print-container { 
            background: #ffffff;
            border: 1px solid #e2e8f0; 
            padding: 40px; 
            width: 100%; 
            max-width: 800px;
            margin: auto; 
            border-radius: 12px;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #3b82f6;
        }
        .header h2 {
            margin: 0;
            color: #0f172a;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .header p {
            margin: 5px 0 0;
            color: #64748b;
            font-size: 0.9rem;
        }
        .ref-badge {
            display: inline-block;
            margin-top: 10px;
            background: linear-gradient(135deg, #eff6ff, #dbeafe);
            border: 1.5px solid #93c5fd;
            border-radius: 20px;
            padding: 5px 18px;
            font-size: 0.95rem;
            font-weight: 700;
            color: #1d4ed8;
            letter-spacing: 1px;
        }
        .details-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        .detail-item {
            background: #f1f5f9;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        .detail-item strong {
            display: block;
            color: #475569;
            font-size: 0.85rem;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .detail-item span {
            color: #0f172a;
            font-size: 1.1rem;
            font-weight: 600;
        }
        .purpose-section {
            background: #f1f5f9;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            margin-bottom: 30px;
        }
        .purpose-section strong {
            display: block;
            color: #475569;
            font-size: 0.85rem;
            text-transform: uppercase;
            margin-bottom: 10px;
        }
        .purpose-section p {
            margin: 0;
            color: #0f172a;
            line-height: 1.6;
        }
        .approval-section {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        .signature-box {
            text-align: center;
            width: 45%;
        }
        .signature-line {
            border-bottom: 1px solid #0f172a;
            height: 40px;
            margin-bottom: 10px;
        }
        .signature-box span {
            display: block;
            font-size: 0.85rem;
            color: #64748b;
        }
        .signature-box strong {
            color: #0f172a;
            font-weight: 600;
        }
        @media print {
            body { 
                background: white; 
                padding: 0;
            }
            .print-container { 
                box-shadow: none; 
                border: none;
                padding: 0;
                max-width: 100%;
            }
            .detail-item, .purpose-section {
                background: white !important;
                border: 1px solid #ccc !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .header {
                border-bottom: 2px solid #000 !important;
            }
            .ref-badge {
                background: white !important;
                border: 1.5px solid #000 !important;
                color: #000 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
<?php
$conn = new mysqli("localhost", "root", "", "hr");

$data = [];
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("SELECT * FROM travel_orders WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
}

$conn->close();

if (!$data) {
    echo "<p style='text-align: center; margin-top: 50px;'>Record not found.</p>";
    exit;
}

// Build Reference ID: prefer travel_order_no, fallback to TO-00001
$refID = !empty($data['travel_order_no'])
    ? $data['travel_order_no']
    : 'TO-' . str_pad($data['id'], 5, '0', STR_PAD_LEFT);
?>

<div class="print-container">
    <div class="header">
        <h2>Official Travel Order</h2>
        <p>Reference ID</p>
        <span class="ref-badge">&#35; <?= htmlspecialchars($refID) ?></span>
    </div>

    <div class="details-grid">
        <div class="detail-item">
            <strong>Employee Name</strong>
            <span><?= htmlspecialchars($data['employeeName'] ?? 'N/A') ?></span>
        </div>
        <div class="detail-item">
            <strong>Employee ID</strong>
            <span><?= htmlspecialchars($data['EmpID'] ?? 'N/A') ?></span>
        </div>
        <div class="detail-item">
            <strong>Branch</strong>
            <span><?= htmlspecialchars($data['BrCode'] ?? 'N/A') ?></span>
        </div>
		<div class="detail-item">
            <strong>Branch / Department</strong>
            <span><?= htmlspecialchars($data['department'] ?? 'N/A') ?></span>
        </div>
        <div class="detail-item">
            <strong>From</strong>
            <span><?= htmlspecialchars($data['travelFrom'] ?? 'N/A') ?></span>
        </div>
		<div class="detail-item">
            <strong>Destination</strong>
            <span><?= htmlspecialchars($data['destination'] ?? 'N/A') ?></span>
        </div>
        <div class="detail-item">
            <strong>Travel Period</strong>
            <span><?= htmlspecialchars($data['travelDateFrom'] ?? 'N/A') ?> to <?= htmlspecialchars($data['travelDateTo'] ?? 'N/A') ?></span>
        </div>
        <div class="detail-item">
            <strong>Mode of Transport</strong>
            <span><?= htmlspecialchars($data['modeOfTransport'] ?? 'N/A') ?></span>
        </div>
    </div>

    <div class="purpose-section">
        <strong>Purpose of Travel</strong>
        <p><?= nl2br(htmlspecialchars($data['purpose'] ?? 'N/A')) ?></p>
    </div>

    <div class="approval-section">
        <div class="signature-box">
            <div class="signature-line"></div>
            <strong>Employee Signature</strong>
            <span><?= htmlspecialchars($data['employeeName'] ?? 'Employee') ?></span>
        </div>
        <div class="signature-box">
            <div class="signature-line">
                <?php if (($data['approval_ceo_status'] ?? '') === 'Approved'): ?>
                    <span style="color: #10b981; font-weight: bold; line-height: 40px; font-size: 1.2rem;">APPROVED</span>
                <?php endif; ?>
            </div>
            <strong>Approving Authority</strong>
            <span><?= htmlspecialchars($data['ceo_approver_name'] ?? 'CEO / Authorized Officer') ?></span>
        </div>
    </div>
</div>

</body>
</html>