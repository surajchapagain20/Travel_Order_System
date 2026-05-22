<?php
require_once 'auth.php';
requireLogin();
require_once 'db.php';

// Fetch employees with branch name
$sql = "SELECT e.EmpID, e.employeeName, b.branch_name FROM employees e LEFT JOIN branches b ON e.BrCode = b.BrCode ORDER BY e.EmpID";
$res = $conn->query($sql);
if (!$res) {
    die('Query failed: ' . $conn->error);
}
?>
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <title>Employee List | Travel Expense</title>
    <link href='https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap' rel='stylesheet'>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css' rel='stylesheet'>
    <style>
        body {font-family: 'Outfit', sans-serif;background:#f1f5f9;}
        .card {border:none;box-shadow:0 4px 12px rgba(0,0,0,0.05);border-radius:12px;}
        .table-hover tbody tr:hover {background-color:#e0f2fe;cursor:pointer;}
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class='container py-5'>
    <h2 class='mb-4'><i class='bi bi-people-fill me-2'></i>Employee List</h2>
    <?php if ($res->num_rows > 0): ?>
        <div class='card'>
            <div class='card-body'>
                <table class='table table-bordered table-hover'>
                    <thead class='table-primary'>
                        <tr>
                            <th>EmpID</th>
                            <th>Employee Name</th>
                            <th>Branch Name</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $res->fetch_assoc()): ?>
                            <tr onclick="openExpenseForm('<?php echo $row['EmpID']; ?>','<?php echo htmlspecialchars($row['employeeName']); ?>','<?php echo htmlspecialchars($row['branch_name']); ?>')">
                                <td><?php echo $row['EmpID']; ?></td>
                                <td><?php echo htmlspecialchars($row['employeeName']); ?></td>
                                <td><?php echo htmlspecialchars($row['branch_name']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <div class='alert alert-info'>No employees found.</div>
    <?php endif; ?>
</div>
<script>
function openExpenseForm(empID, name, branch) {
    const url = `travel_expense.php?empID=${encodeURIComponent(empID)}&employeeName=${encodeURIComponent(name)}&branchName=${encodeURIComponent(branch)}`;
    window.location.href = url;
}
</script>
<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>
</body>
</html>
