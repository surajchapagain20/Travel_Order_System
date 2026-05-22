<?php
require_once 'auth.php';
requireLogin();
require_once 'db.php';

$role = $_SESSION['role'] ?? 'Employee';

// Basic analytics
$totalEmployees = $conn->query("SELECT COUNT(*) as count FROM employees")->fetch_assoc()['count'];
$totalOrders = $conn->query("SELECT COUNT(*) as count FROM travel_orders")->fetch_assoc()['count'];
$approvedOrders = $conn->query("SELECT COUNT(*) as count FROM travel_orders WHERE approval_ceo_status = 'Approved'")->fetch_assoc()['count'];
$pendingOrders = $totalOrders - $approvedOrders;

// Orders by Department
$deptQuery = $conn->query("SELECT department, COUNT(*) as count FROM travel_orders GROUP BY department");
$deptData = [];
$deptLabels = [];
while ($row = $deptQuery->fetch_assoc()) {
    $deptLabels[] = $row['department'];
    $deptData[] = $row['count'];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | HR Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #0f172a;
            --secondary: #1e293b;
            --accent: #3b82f6;
            --text-light: #f8fafc;
        }
        body {
            font-family: 'Outfit', sans-serif;
            background-color: #f1f5f9;
        }
        .dashboard-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 3rem 0;
            margin-bottom: 2rem;
            border-bottom-left-radius: 20px;
            border-bottom-right-radius: 20px;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            transition: transform 0.3s;
            border: 1px solid #e2e8f0;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-icon {
            font-size: 2.5rem;
            color: var(--accent);
            opacity: 0.8;
        }
        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
        }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="dashboard-header text-center">
    <h1 class="fw-bold">Welcome back, <?= htmlspecialchars($_SESSION['full_name']) ?>!</h1>
    <p class="lead opacity-75">Role: <span class="badge bg-primary"><?= htmlspecialchars($role) ?></span></p>
</div>

<div class="container mb-5">
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <a href="add_employee.php" class="text-decoration-none text-dark">
                <div class="stat-card d-flex align-items-center justify-content-between">
                    <div>
                        <h5 class="text-muted fw-normal mb-1">Total Employees</h5>
                        <h2 class="fw-bold mb-0 text-info"><?= $totalEmployees ?></h2>
                    </div>
                    <i class="bi bi-people stat-icon text-info"></i>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="view_travel_orders.php" class="text-decoration-none text-dark">
                <div class="stat-card d-flex align-items-center justify-content-between">
                    <div>
                        <h5 class="text-muted fw-normal mb-1">Total Travel Orders</h5>
                        <h2 class="fw-bold mb-0"><?= $totalOrders ?></h2>
                    </div>
                    <i class="bi bi-airplane-engines stat-icon"></i>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="view_travel_orders.php?filter=approved" class="text-decoration-none text-dark">
                <div class="stat-card d-flex align-items-center justify-content-between">
                    <div>
                        <h5 class="text-muted fw-normal mb-1">Approved Orders</h5>
                        <h2 class="fw-bold mb-0 text-success"><?= $approvedOrders ?></h2>
                    </div>
                    <i class="bi bi-check-circle stat-icon text-success"></i>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="view_travel_orders.php?filter=pending" class="text-decoration-none text-dark">
                <div class="stat-card d-flex align-items-center justify-content-between">
                    <div>
                        <h5 class="text-muted fw-normal mb-1">Pending Approval</h5>
                        <h2 class="fw-bold mb-0 text-warning"><?= $pendingOrders ?></h2>
                    </div>
                    <i class="bi bi-hourglass-split stat-icon text-warning"></i>
                </div>
            </a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="chart-container h-100">
                <h4 class="fw-bold mb-4">Orders by Department</h4>
                <canvas id="deptChart"></canvas>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="chart-container h-100">
                <h4 class="fw-bold mb-4">Approval Status</h4>
                <canvas id="statusChart"></canvas>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const deptCtx = document.getElementById('deptChart').getContext('2d');
    new Chart(deptCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($deptLabels) ?>,
            datasets: [{
                label: 'Travel Orders',
                data: <?= json_encode($deptData) ?>,
                backgroundColor: '#3b82f6',
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false }
            }
        }
    });

    const statusCtx = document.getElementById('statusChart').getContext('2d');
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: ['Approved', 'Pending'],
            datasets: [{
                data: [<?= $approvedOrders ?>, <?= $pendingOrders ?>],
                backgroundColor: ['#10b981', '#f59e0b'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            cutout: '70%',
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });
</script>

</body>
</html>
