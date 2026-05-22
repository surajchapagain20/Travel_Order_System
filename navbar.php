<?php
require_once 'auth.php';
$role = $_SESSION['role'] ?? '';
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<style>
    /* Global Sidebar Styling */
    body {
        margin: 0;
        padding: 0;
        padding-left: 260px; /* Push content to accommodate sidebar */
        min-height: 100vh;
        background-color: #f1f5f9;
    }
    
    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        width: 260px;
        height: 100vh;
        background: #0f172a;
        color: #f8fafc;
        display: flex;
        flex-direction: column;
        box-shadow: 4px 0 10px rgba(0,0,0,0.1);
        z-index: 1000;
        transition: all 0.3s ease;
    }

    .sidebar-header {
        padding: 1.5rem;
        border-bottom: 1px solid rgba(255,255,255,0.1);
        text-align: center;
    }

    .sidebar-header h3 {
        margin: 0;
        font-weight: 700;
        letter-spacing: 1px;
        color: #3b82f6;
    }

    .sidebar-user {
        padding: 1.5rem;
        text-align: center;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    
    .sidebar-user .user-avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: #1e293b;
        color: #3b82f6;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        margin: 0 auto 10px;
        border: 2px solid #3b82f6;
    }

    .sidebar-user .user-name {
        font-weight: 600;
        font-size: 1.1rem;
        margin-bottom: 5px;
    }
    
    .sidebar-user .user-role {
        font-size: 0.85rem;
        color: #94a3b8;
        background: rgba(255,255,255,0.1);
        padding: 2px 10px;
        border-radius: 12px;
        display: inline-block;
    }

    .sidebar-nav {
        flex: 1;
        overflow-y: auto;
        padding: 1rem 0;
    }

    .sidebar-menu {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .sidebar-menu-title {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        color: #64748b;
        padding: 10px 1.5rem;
        margin-top: 10px;
    }

    .sidebar-item {
        margin-bottom: 5px;
    }

    .sidebar-link {
        display: flex;
        align-items: center;
        padding: 12px 1.5rem;
        color: #cbd5e1;
        text-decoration: none;
        transition: all 0.2s ease;
        border-left: 4px solid transparent;
    }

    .sidebar-link i {
        font-size: 1.2rem;
        margin-right: 15px;
        color: #64748b;
        transition: all 0.2s ease;
    }

    .sidebar-link:hover {
        background: rgba(255,255,255,0.05);
        color: #fff;
    }

    .sidebar-link:hover i {
        color: #3b82f6;
    }

    .sidebar-link.active {
        background: rgba(59,130,246,0.1);
        color: #fff;
        border-left-color: #3b82f6;
    }

    .sidebar-link.active i {
        color: #3b82f6;
    }

    .sidebar-footer {
        padding: 1.5rem;
        border-top: 1px solid rgba(255,255,255,0.1);
    }
    
    .logout-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        padding: 10px;
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
        border: 1px solid rgba(239, 68, 68, 0.2);
        border-radius: 8px;
        text-decoration: none;
        transition: all 0.2s;
        font-weight: 600;
    }
    
    .logout-btn:hover {
        background: #ef4444;
        color: white;
    }

    .logout-btn i {
        margin-right: 8px;
    }

    /* Container adjustments since navbar is gone */
    .container-fluid, .container {
        padding-top: 20px;
    }
</style>

<div class="sidebar">
    <div class="sidebar-header">
        <h3><i class="bi bi-shield-lock-fill me-2"></i> HR Portal</h3>
    </div>
    
    <div class="sidebar-user">
        <div class="user-avatar">
            <i class="bi bi-person-fill"></i>
        </div>
        <div class="user-name"><?= htmlspecialchars($_SESSION['full_name'] ?? 'User') ?></div>
        <div class="user-role"><?= htmlspecialchars($role) ?></div>
    </div>

    <div class="sidebar-nav">
        <ul class="sidebar-menu">
            <li class="sidebar-menu-title">Main Menu</li>
            
            <li class="sidebar-item">
                <a class="sidebar-link <?= $currentPage == 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>
            
            <?php if ($role === 'Admin' || $role === 'HR'): ?>
            <li class="sidebar-item">
                <a class="sidebar-link <?= $currentPage == 'index.php' ? 'active' : '' ?>" href="index.php">
                    <i class="bi bi-plus-circle"></i> New Request (Admin)
                </a>
            </li>
            <?php else: ?>
            <li class="sidebar-item">
                <a class="sidebar-link <?= $currentPage == 'employee_request.php' ? 'active' : '' ?>" href="employee_request.php">
                    <i class="bi bi-plus-circle"></i> New Request
                </a>
            </li>
            <?php endif; ?>
            
            <li class="sidebar-item">
                <a class="sidebar-link <?= $currentPage == 'view_travel_orders.php' ? 'active' : '' ?>" href="view_travel_orders.php">
                    <i class="bi bi-list-task"></i> View Travel Records
                </a>
            </li>

            <?php if ($role === 'Admin' || $role === 'HR'): ?>
            <li class="sidebar-menu-title">Settings</li>
            <li class="sidebar-item">
                <a class="sidebar-link <?= $currentPage == 'add_employee.php' ? 'active' : '' ?>" href="add_employee.php">
                    <i class="bi bi-person-lines-fill"></i> Manage Employees
                </a>
            </li>
            <li class="sidebar-item">
                <a class="sidebar-link <?= $currentPage == 'travel_expense.php' ? 'active' : '' ?>" href="travel_expense.php">
                    <i class="bi bi-cash-stack"></i> Travel Expense
                </a>
            </li>
			
			<li class="sidebar-item">
                <a class="sidebar-link <?= $currentPage == 'View_ExpenseRecords.php' ? 'active' : '' ?>" href="View_ExpenseRecords.php">
                    <i class="bi bi-cash-stack"></i> View Travel Expense Records
                </a>
            </li>
			
            <li class="sidebar-item">
                <a class="sidebar-link <?= $currentPage == 'hr_backup.php' ? 'active' : '' ?>" href="hr_backup.php">
                    <i class="bi bi-hdd-network"></i> Backup
                </a>
            </li>
            <?php if ($role === 'Admin'): ?>
            <li class="sidebar-item">
                <a class="sidebar-link <?= $currentPage == 'smtp.php' ? 'active' : '' ?>" href="smtp.php">
                    <i class="bi bi-envelope"></i> SMTP Settings
                </a>
            </li>
            <?php endif; ?>
            <?php endif; ?>

            <?php if ($role === 'Admin'): ?>
            <li class="sidebar-item">
                <a class="sidebar-link <?= $currentPage == 'users.php' ? 'active' : '' ?>" href="users.php">
                    <i class="bi bi-people"></i> Manage Users
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </div>
    
    <div class="sidebar-footer">
        <a href="logout.php" class="logout-btn">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </div>
</div>
