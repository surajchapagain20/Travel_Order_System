<?php
require_once 'auth.php';
requireLogin();

$role = strtoupper(trim($_SESSION['role'] ?? ''));
if (!in_array($role, ['HR', 'ADMIN'])) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Not Authorized</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <style>
            body {font-family: "Outfit", sans-serif; background:#f1f5f9; color:#1e293b;}
            .not-auth {margin-top:120px; text-align:center;}
        </style>
    </head>
    <body>
        <div class="container not-auth">
            <h1 class="display-4 text-danger">🚫 Not Authorized</h1>
            <p class="lead">You do not have permission to view this page.</p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Database Connection
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

// Fetch Approved Travel Orders
$stmt = $pdo->prepare("
    SELECT * FROM travel_orders 
    WHERE approval_ceo_status = 'Approved' 
    ORDER BY id DESC
");
$stmt->execute();
$approvedOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approved Travel Orders</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- SheetJS for Excel Export -->
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <!-- jsPDF + AutoTable for PDF Export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>

    <style>
        :root {
            --brand-primary: #1a56db;
            --brand-dark: #1e3a8a;
        }
        body {
            font-family: 'Outfit', sans-serif;
            background: #f1f5f9;
            color: #1e293b;
        }
        .page-header {
            background: linear-gradient(135deg, var(--brand-dark) 0%, var(--brand-primary) 60%, #3b82f6 100%);
            color: #fff;
            padding: 32px 36px 28px;
            border-radius: 0 0 20px 20px;
            margin-bottom: 30px;
        }
        .page-header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0;
        }
        .table th {
            background: #f8fafc;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }
        .card {
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
        }
        .btn-export {
            font-size: 0.875rem;
            font-weight: 600;
            border-radius: 8px;
            padding: 8px 16px;
        }

        /* ── Print Styles ───────────────────────────────── */
        @media print {
            @page { size: A4 landscape; margin: 15mm; }

            body {
                background: #fff !important;
                padding-left: 0 !important;
                font-size: 11px;
            }
            .sidebar,
            .page-header,
            .no-print { display: none !important; }

            .card {
                box-shadow: none !important;
                border: none !important;
                border-radius: 0 !important;
            }
            .card-header { border-bottom: 2px solid #1e3a8a !important; }

            .print-header { display: block !important; }

            .table { width: 100% !important; font-size: 10px; }
            .table th {
                background: #1e3a8a !important;
                color: #fff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                font-size: 9px;
                padding: 6px 4px;
            }
            .table td { padding: 5px 4px; }
            .table tbody tr:nth-child(even) td {
                background: #f1f5f9 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .badge { border: 1px solid #ccc; padding: 2px 6px; border-radius: 4px; }
            .container, .container-fluid { padding: 0 !important; max-width: 100% !important; }
            .table-responsive { overflow: visible !important; }
        }

        .print-header {
            display: none;
            text-align: center;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 2px solid #1e3a8a;
        }
        .print-header h2 {
            font-size: 1.3rem;
            font-weight: 700;
            color: #1e3a8a;
            margin-bottom: 4px;
        }
        .print-header p { font-size: 0.82rem; color: #64748b; margin: 0; }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="page-header no-print">
    <div class="container">
        <h1><i class="bi bi-check2-circle me-3"></i>Approved Travel Orders</h1>
        <p class="mb-0 opacity-75">List of all approved travel requests</p>
    </div>
</div>

<div class="container pb-5">
    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0">
                <i class="bi bi-list-check text-success"></i>
                Approved Orders (<?= count($approvedOrders) ?>)
            </h5>

            <!-- Export / Print Buttons -->
            <div class="d-flex gap-2 no-print">
                <button onclick="printTable()" class="btn btn-outline-secondary btn-export">
                    <i class="bi bi-printer me-1"></i> Print
                </button>
                <button onclick="exportExcel()" class="btn btn-outline-success btn-export">
                    <i class="bi bi-file-earmark-excel me-1"></i> Export Excel
                </button>
                <button onclick="exportPDF()" class="btn btn-outline-danger btn-export">
                    <i class="bi bi-file-earmark-pdf me-1"></i> Export PDF
                </button>
            </div>
        </div>

        <div class="table-responsive">

            <!-- Print Header (shown only when printing) -->
            <div class="print-header">
                <h2><i class="bi bi-check2-circle"></i> Approved Travel Orders</h2>
                <p>Generated on: <?= date('F d, Y \a\t h:i A') ?> &nbsp;|&nbsp; Total Records: <?= count($approvedOrders) ?></p>
            </div>

            <?php if (empty($approvedOrders)): ?>
                <div class="p-5 text-center">
                    <i class="bi bi-inbox display-1 text-muted"></i>
                    <h5 class="mt-3 text-muted">No approved travel orders found</h5>
                </div>
            <?php else: ?>
                <table class="table table-hover mb-0" id="approvedOrdersTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Order No</th>
                            <th>Employee</th>
                            <th>Branch</th>
                            <th>Route</th>
                            <th>Travel Period</th>
                            <th>Purpose</th>
                            <th>Est. Cost</th>
                            <th>Approved Date</th>
                            <th>Updated By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($approvedOrders as $index => $order): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><strong><?= htmlspecialchars($order['travel_order_no'] ?? 'N/A') ?></strong></td>
                                <td><?= htmlspecialchars($order['employeeName'] ?? 'N/A') ?></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($order['BrCode'] ?? $order['br_code'] ?? 'N/A') ?></span></td>
                                <td><?= htmlspecialchars($order['travelFrom'] ?? '') ?> → <?= htmlspecialchars($order['destination'] ?? '') ?></td>
                                <td><?= htmlspecialchars($order['travelDateFrom'] ?? '') ?> — <?= htmlspecialchars($order['travelDateTo'] ?? '') ?></td>
                                <td><?= htmlspecialchars($order['purpose'] ?? 'N/A') ?></td>
                                <td><strong>रू. <?= number_format($order['estimatedCost'] ?? 0, 2) ?></strong></td>
                                <td class="text-muted small"><?= htmlspecialchars($order['last_updated_at'] ?? '—') ?></td>
                                <td>
                                    <?php if (!empty($order['last_updated_by'])): ?>
                                        <span class="badge bg-primary bg-opacity-10 text-primary fw-semibold">
                                            <i class="bi bi-person-check me-1"></i><?= htmlspecialchars($order['last_updated_by']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // ─── Print (Landscape via CSS @page) ─────────────────────────────────────
    function printTable() {
        window.print();
    }

    // ─── Export Excel ─────────────────────────────────────────────────────────
    function exportExcel() {
        const table = document.getElementById('approvedOrdersTable');
        if (!table) return alert('No data to export.');

        const rows = [];
        const headers = [...table.querySelectorAll('thead th')].map(th => th.innerText.trim());
        rows.push(headers);

        table.querySelectorAll('tbody tr').forEach(tr => {
            const row = [...tr.querySelectorAll('td')].map(td => td.innerText.trim());
            rows.push(row);
        });

        const ws = XLSX.utils.aoa_to_sheet(rows);

        // Style header row bold
        const range = XLSX.utils.decode_range(ws['!ref']);
        for (let C = range.s.c; C <= range.e.c; C++) {
            const cell = ws[XLSX.utils.encode_cell({ r: 0, c: C })];
            if (cell) cell.s = { font: { bold: true } };
        }

        ws['!cols'] = [
            { wch: 5 }, { wch: 16 }, { wch: 22 }, { wch: 10 },
            { wch: 28 }, { wch: 28 }, { wch: 28 }, { wch: 14 },
            { wch: 20 }, { wch: 20 }
        ];

        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Approved Travel Orders');
        XLSX.writeFile(wb, 'Approved_Travel_Orders_<?= date('Y-m-d') ?>.xlsx');
    }

    // ─── Export PDF ───────────────────────────────────────────────────────────
    function exportPDF() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });

        // Title
        doc.setFontSize(16);
        doc.setTextColor(30, 58, 138);
        doc.text('Approved Travel Orders', 148, 14, { align: 'center' });

        // Subtitle
        doc.setFontSize(9);
        doc.setTextColor(100, 116, 139);
        doc.text('Generated on: <?= date('F d, Y \a\t h:i A') ?>  |  Total Records: <?= count($approvedOrders) ?>', 148, 21, { align: 'center' });

        // Divider line
        doc.setDrawColor(30, 58, 138);
        doc.setLineWidth(0.5);
        doc.line(10, 24, 287, 24);

        const table = document.getElementById('approvedOrdersTable');
        if (!table) return alert('No data to export.');

        const headers = [...table.querySelectorAll('thead th')].map(th => th.innerText.trim());
        const body = [...table.querySelectorAll('tbody tr')].map(tr =>
            [...tr.querySelectorAll('td')].map(td => td.innerText.trim())
        );

        doc.autoTable({
            head: [headers],
            body: body,
            startY: 27,
            styles: {
                fontSize: 8,
                cellPadding: 3,
                font: 'helvetica',
                overflow: 'linebreak',
                valign: 'middle'
            },
            headStyles: {
                fillColor: [30, 58, 138],
                textColor: 255,
                fontStyle: 'bold',
                fontSize: 8,
                halign: 'center'
            },
            alternateRowStyles: {
                fillColor: [241, 245, 249]
            },
            columnStyles: {
                0: { cellWidth: 8,  halign: 'center' },
                1: { cellWidth: 24 },
                2: { cellWidth: 30 },
                3: { cellWidth: 14, halign: 'center' },
                4: { cellWidth: 36 },
                5: { cellWidth: 34 },
                6: { cellWidth: 34 },
                7: { cellWidth: 18, halign: 'right' },
                8: { cellWidth: 24 },
                9: { cellWidth: 22 }
            },
            margin: { top: 27, left: 10, right: 10 },
            didDrawPage: function(data) {
                const pageCount = doc.internal.getNumberOfPages();
                const pageNum   = doc.internal.getCurrentPageInfo().pageNumber;
                doc.setFontSize(8);
                doc.setTextColor(150);
                doc.text(
                    'Page ' + pageNum + ' of ' + pageCount,
                    148, doc.internal.pageSize.height - 5, { align: 'center' }
                );
            }
        });

        doc.save('Approved_Travel_Orders_<?= date('Y-m-d') ?>.pdf');
    }
</script>

</body>
</html>