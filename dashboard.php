<?php
require_once 'auth.php';
requireLogin();
require_once 'db.php';

$role = $_SESSION['role'] ?? 'Admin';
$fullName = $_SESSION['full_name'] ?? 'Sarah Chen';

// Basic analytics
$totalEmployeesResult = $conn->query("SELECT COUNT(*) as count FROM employees");
$totalEmployees = $totalEmployeesResult ? $totalEmployeesResult->fetch_assoc()['count'] : 0;

$levelsQuery = $conn->query("SELECT level, COUNT(*) as count FROM employees GROUP BY level");
$levelCounts = [
    'PH' => 0, 'DH' => 0, 'CD' => 0, 'CEO' => 0, 'DCEO' => 0, 'ST' => 0, 'OI' => 0, 'NSM' => 0
];
if ($levelsQuery) {
    while ($row = $levelsQuery->fetch_assoc()) {
        $lvl = strtoupper(trim($row['level'] ?? ''));
        if (isset($levelCounts[$lvl])) {
            $levelCounts[$lvl] = $row['count'];
        }
    }
}

$lineLabels = array_keys($levelCounts);
$lineData = array_values($levelCounts);

// Department pie chart
$deptQuery = $conn->query("SELECT department, COUNT(*) as count FROM travel_orders WHERE department != '' GROUP BY department LIMIT 5");
$deptLabels = [];
$deptData = [];
$deptColors = ['#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#eab308'];
if ($deptQuery) {
    while ($row = $deptQuery->fetch_assoc()) {
        $deptLabels[] = $row['department'] ? $row['department'] : 'Other';
        $deptData[] = (int)$row['count'];
    }
}
if (empty($deptData)) {
    $deptLabels = ['Eng', 'Sales', 'Mkt', 'HR', 'Ops'];
    $deptData = [40, 25, 15, 10, 10];
}

// Fetch Travel Orders
$travelOrdersQuery = $conn->query("SELECT * FROM travel_orders ORDER BY id DESC LIMIT 4");
$travelOrders = [];
if ($travelOrdersQuery) {
    while ($row = $travelOrdersQuery->fetch_assoc()) {
        $travelOrders[] = $row;
    }
}

// Fetch Expenses
$expensesQuery = $conn->query("SELECT * FROM travel_expenses ORDER BY id DESC LIMIT 4");
$expenses = [];
if ($expensesQuery) {
    while ($row = $expensesQuery->fetch_assoc()) {
        $expenses[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | HR Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
	
    <style>
        :root {
            --bg-main: #f4f7f9;
            --bg-card: #ffffff;
            --text-primary: #111827;
            --text-secondary: #6b7280;
            --border-color: #e5e7eb;
            --success-bg: #d1fae5;
            --success-text: #059669;
            --pending-bg: #fef3c7;
            --pending-text: #d97706;
            --danger-bg: #fee2e2;
            --danger-text: #dc2626;
            --font-family: 'Inter', sans-serif;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: var(--font-family);
            background-color: var(--bg-main);
            color: var(--text-primary);
        }

        /* Navbar */
        .top-nav {
            background-color: var(--bg-card);
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
        }

        .nav-left .welcome {
            font-size: 20px;
            font-weight: 700;
        }

        .nav-left .role {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .search-container {
            position: relative;
        }

        .search-container input {
            padding: 8px 12px 8px 35px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 14px;
            background-color: #f9fafb;
            width: 250px;
            outline: none;
        }

        .search-container i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
        }

        .nav-icons {
            display: flex;
            gap: 15px;
            align-items: center;
            color: var(--text-secondary);
            font-size: 18px;
        }
        
        /* Profile Dropdown */
        .profile-dropdown-container {
            position: relative;
            display: inline-flex;
            align-items: center;
            cursor: pointer;
        }

        .profile-dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            background-color: var(--bg-card);
            min-width: 160px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
            z-index: 10;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            overflow: hidden;
            margin-top: 10px;
        }

        .profile-dropdown-content a {
            color: var(--text-primary);
            padding: 12px 16px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            transition: background-color 0.2s;
        }

        .profile-dropdown-content a:hover {
            background-color: #f9fafb;
            color: var(--accent);
        }

        .profile-dropdown-container:hover .profile-dropdown-content {
            display: block;
        }
        
        .profile-img {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
        }

        /* Main Content */
        .main-content {
            padding: 30px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .page-title {
            font-size: 28px;
            font-weight: 700;
        }

        .date-display {
            font-size: 16px;
            font-weight: 500;
        }

        /* Cards */
        .card {
            background-color: var(--bg-card);
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            border: 1px solid var(--border-color);
            margin-bottom: 24px;
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
        }

        /* Top Stats Section */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-item h3 {
            font-size: 14px;
            color: var(--text-secondary);
            font-weight: 500;
            margin-bottom: 5px;
        }

        .stat-item .stat-value {
            font-size: 32px;
            font-weight: 700;
        }
        
        .stat-item .stat-sub {
            font-size: 18px;
            color: var(--text-secondary);
            font-weight: 500;
        }

        /* Charts Section */
        .charts-row {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 40px;
        }

        .chart-box h4 {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--text-primary);
        }

        .pie-chart-container {
            display: flex;
            align-items: center;
            gap: 30px;
        }
        
        .pie-canvas-wrapper {
            width: 150px;
            height: 150px;
        }

        .pie-legend {
            display: flex;
            flex-direction: column;
            gap: 8px;
            font-size: 13px;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 2px;
        }
        
        .line-canvas-wrapper {
            width: 100%;
            height: 200px;
        }

        /* Tables Section */
        .tables-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 13px;
        }

        td {
            padding: 16px 0;
            border-bottom: 1px solid #f3f4f6;
            font-size: 14px;
            vertical-align: middle;
        }

        tr:last-child td {
            border-bottom: none;
        }

        .user-cell {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            object-fit: cover;
        }

        /* Badges */
        .badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }

        .badge-success { background-color: var(--success-bg); color: var(--success-text); }
        .badge-pending { background-color: var(--pending-bg); color: var(--pending-text); }
        .badge-danger { background-color: var(--danger-bg); color: var(--danger-text); }

        /* Buttons */
        .btn-group {
            display: flex;
            gap: 8px;
        }

        .btn {
            padding: 6px 12px;
            border: 1px solid var(--border-color);
            background: white;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            color: var(--text-primary);
            transition: all 0.2s;
        }

        .btn:hover {
            background: #f9fafb;
        }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>

    <!-- Top Navigation -->
    <nav class="top-nav">
        <div class="nav-left">
            <div class="welcome">Welcome, <?= htmlspecialchars($fullName) ?>!</div>
            <div class="role"><?= htmlspecialchars($role) ?></div>
        </div>
        <div class="nav-right">
            <div class="search-container">
                <i class="fa-solid fa-search"></i>
                <input type="text" placeholder="Search">
            </div>
            <div class="nav-icons">
                <i class="fa-regular fa-bell"></i>
                <div class="profile-dropdown-container">
                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($fullName) ?>&background=random" alt="Profile" class="profile-img">
                    <i class="fa-solid fa-chevron-down" style="font-size: 12px; margin-left: 8px;"></i>
                    <div class="profile-dropdown-content">
                        <a href="#"><i class="fa-solid fa-pen-to-square"></i> Revise</a>
                        <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Dashboard</h1>
            <div class="date-display"><?= date('M d, Y') ?></div>
        </div>

        <!-- First Row: Employee Statistics -->
        <div class="card">
            <h2 class="card-title">Employee Statistics</h2>
            
            <div class="stats-grid" style="grid-template-columns: repeat(5, 1fr);">
                <div class="stat-item">
                    <h3>Total</h3>
                    <div class="stat-value"><?= htmlspecialchars($totalEmployees) ?></div>
                </div>
                <div class="stat-item">
                    <h3>PH</h3>
                    <div class="stat-value"><?= $levelCounts['PH'] ?></div>
                </div>
                <div class="stat-item">
                    <h3>DH</h3>
                    <div class="stat-value"><?= $levelCounts['DH'] ?></div>
                </div>
                <div class="stat-item">
                    <h3>CD</h3>
                    <div class="stat-value"><?= $levelCounts['CD'] ?></div>
                </div>
                <div class="stat-item">
                    <h3>CEO</h3>
                    <div class="stat-value"><?= $levelCounts['CEO'] ?></div>
                </div>
                <div class="stat-item">
                    <h3>DCEO</h3>
                    <div class="stat-value"><?= $levelCounts['DCEO'] ?></div>
                </div>
                <div class="stat-item">
                    <h3>ST</h3>
                    <div class="stat-value"><?= $levelCounts['ST'] ?></div>
                </div>
                <div class="stat-item">
                    <h3>OI</h3>
                    <div class="stat-value"><?= $levelCounts['OI'] ?></div>
                </div>
                <div class="stat-item">
                    <h3>NSM</h3>
                    <div class="stat-value"><?= $levelCounts['NSM'] ?></div>
                </div>
            </div>

            <div class="charts-row">
                <div class="chart-box">
                    <h4>Department</h4>
                    <div class="pie-chart-container">
                        <div class="pie-canvas-wrapper">
                            <canvas id="deptPieChart"></canvas>
                        </div>
                        <div class="pie-legend">
                            <?php foreach ($deptLabels as $index => $label): ?>
                            <div class="legend-item">
                                <div class="legend-color" style="background:<?= $deptColors[$index % count($deptColors)] ?>;"></div> 
                                <?= htmlspecialchars($label) ?>: <?= htmlspecialchars($deptData[$index]) ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <div class="chart-box" style="position:relative;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <h4>Employee Statistics</h4>
                        <span style="font-size:13px; color:#6b7280;">By Level</span>
                    </div>
                    <div class="line-canvas-wrapper">
                        <canvas id="trendLineChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script>
        // Department Pie Chart
        const deptCtx = document.getElementById('deptPieChart').getContext('2d');
        new Chart(deptCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($deptLabels) ?>,
                datasets: [{
                    data: <?= json_encode($deptData) ?>,
                    backgroundColor: <?= json_encode(array_slice($deptColors, 0, count($deptLabels))) ?>,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '50%',
                plugins: {
                    legend: { display: false },
                    tooltip: { enabled: true }
                }
            }
        });

        // Employee Statistics Line Chart
        const trendCtx = document.getElementById('trendLineChart').getContext('2d');
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($lineLabels) ?>,
                datasets: [
                    {
                        label: 'Employee Count',
                        data: <?= json_encode($lineData) ?>,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.3,
                        borderWidth: 2,
                        pointRadius: 4,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 },
                        grid: { color: '#f3f4f6' },
                        border: { display: false }
                    },
                    x: {
                        grid: { display: false },
                        border: { display: false }
                    }
                }
            }
        });
    </script>
</body>
</html>
