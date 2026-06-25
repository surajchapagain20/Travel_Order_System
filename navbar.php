<?php
require_once 'auth.php';
$role = $_SESSION['role'] ?? '';
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<style>
    body {
        margin: 0;
        padding: 0;
        padding-left: 260px;
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
        box-shadow: 4px 0 20px rgba(0,0,0,0.15);
        z-index: 1000;
        overflow-y: auto;
    }

    /* Header */
    .sidebar-header {
        padding: 18px 20px 16px;
        border-bottom: 1px solid rgba(255,255,255,0.07);
        display: flex;
        align-items: center;
        gap: 12px;
        flex-shrink: 0;
    }

    .sidebar-logo-icon {
        width: 36px;
        height: 36px;
        border-radius: 9px;
        background: #3b82f6;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .sidebar-logo-icon i {
        font-size: 18px;
        color: #fff;
    }

    .sidebar-brand-name {
        font-size: 15px;
        font-weight: 600;
        color: #f8fafc;
        line-height: 1.2;
    }

    .sidebar-brand-sub {
        font-size: 11px;
        color: #64748b;
        font-weight: 400;
    }

    /* User */
    .sidebar-user {
        padding: 14px 20px;
        border-bottom: 1px solid rgba(255,255,255,0.07);
        display: flex;
        align-items: center;
        gap: 11px;
        flex-shrink: 0;
    }

    .sidebar-avatar {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: rgba(59,130,246,0.15);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .sidebar-avatar i {
        font-size: 16px;
        color: #60a5fa;
    }

    .sidebar-user-name {
        font-size: 13px;
        font-weight: 500;
        color: #e2e8f0;
        line-height: 1.3;
    }

    .sidebar-user-role {
        font-size: 11px;
        color: #64748b;
    }

    /* Navigation */
    .sidebar-nav {
        flex: 1;
        padding: 10px 0 8px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
    }

    .sidebar-menu {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .sidebar-menu-title {
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        color: #475569;
        padding: 12px 20px 4px;
        font-weight: 500;
    }

    .sidebar-separator {
        margin: 8px 20px;
        border: none;
        border-top: 1px solid rgba(255,255,255,0.06);
    }

    .sidebar-item {
        margin: 0;
    }

    .sidebar-link {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 9px 20px;
        color: #94a3b8;
        text-decoration: none;
        font-size: 13.5px;
        border-left: 3px solid transparent;
        transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
    }

    .sidebar-link i {
        font-size: 17px;
        color: #475569;
        flex-shrink: 0;
        width: 20px;
        text-align: center;
        transition: color 0.15s ease;
    }

    .sidebar-link:hover {
        background: rgba(255,255,255,0.04);
        color: #e2e8f0;
    }

    .sidebar-link:hover i {
        color: #60a5fa;
    }

    .sidebar-link.active {
        background: rgba(59,130,246,0.12);
        color: #ffffff;
        border-left-color: #3b82f6;
    }

    .sidebar-link.active i {
        color: #3b82f6;
    }

    /* Footer */
    .sidebar-footer {
        padding: 12px 14px;
        border-top: 1px solid rgba(255,255,255,0.07);
        flex-shrink: 0;
    }

    .logout-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        width: 100%;
        padding: 9px 12px;
        background: rgba(239,68,68,0.08);
        color: #f87171;
        border: 1px solid rgba(239,68,68,0.18);
        border-radius: 8px;
        text-decoration: none;
        font-weight: 500;
        font-size: 13px;
        transition: background 0.2s, color 0.2s;
    }

    .logout-btn i {
        font-size: 16px;
    }

    .logout-btn:hover {
        background: rgba(239,68,68,0.18);
        color: #fca5a5;
    }

    .container-fluid,
    .container {
        padding-top: 20px;
    }
</style>

<div class="sidebar">

    <!-- Brand Header -->
    <div class="sidebar-header">
        <div class="sidebar-logo-icon">
            <i class="bi bi-shield-lock-fill"></i>
        </div>
        <div>
            <div class="sidebar-brand-name">HR Portal</div>
            <div class="sidebar-brand-sub">Travel Management</div>
        </div>
    </div>

    <!-- Logged-in User -->
    <div class="sidebar-user">
        <div class="sidebar-avatar">
            <i class="bi bi-person-fill"></i>
        </div>
        <div>
            <div class="sidebar-user-name"><?= htmlspecialchars($_SESSION['full_name'] ?? 'User') ?></div>
            <div class="sidebar-user-role"><?= htmlspecialchars($role) ?></div>
        </div>
    </div>

    <nav class="sidebar-nav" aria-label="Sidebar navigation">
        <ul class="sidebar-menu">

            <!-- ── Main Menu ── -->
            <li class="sidebar-menu-title">Main Menu</li>

            <li class="sidebar-item">
                <a class="sidebar-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>
            <li class="sidebar-item">
                <a class="sidebar-link <?= $currentPage === 'index.php' ? 'active' : '' ?>" href="index.php">
                    <i class="bi bi-plus-circle"></i> New Request
                </a>
            </li>
			<li class="sidebar-item">
                <a class="sidebar-link <?= $currentPage === 'apply_expense.php' ? 'active' : '' ?>" href="apply_expense.php">
                    <i class="bi bi-cash-stack"></i> Apply Expense
                </a>
            </li>

            <!-- ── Travel Details (Admin & HR only) ── -->
            <?php if ($role === 'Admin' || $role === 'HR'): ?>
            <hr class="sidebar-separator">
            <li class="sidebar-menu-title">Travel Details</li>

            <li class="sidebar-item">
                <a class="sidebar-link <?= $currentPage === 'view_travel_orders.php' ? 'active' : '' ?>" href="view_travel_orders.php">
                    <i class="bi bi-list-task"></i> View Travel Records
                </a>
            </li>
            <li class="sidebar-item">
                <a class="sidebar-link <?= $currentPage === 'travel_expense.php' ? 'active' : '' ?>" href="travel_expense.php">
                    <i class="bi bi-cash-stack"></i> Travel Expense
                </a>
            </li>
            <li class="sidebar-item">
                <a class="sidebar-link <?= $currentPage === 'View_ExpenseRecords.php' ? 'active' : '' ?>" href="View_ExpenseRecords.php">
                    <i class="bi bi-receipt"></i> View Expense Records
                </a>
            </li>
            <li class="sidebar-item">
                <a class="sidebar-link <?= $currentPage === 'report.php' ? 'active' : '' ?>" href="report.php">
                    <i class="bi bi-graph-up"></i> Report
                </a>
            </li>
            <li class="sidebar-item">
                <a class="sidebar-link <?= $currentPage === 'post_to_bank.php' ? 'active' : '' ?>" href="post_to_bank.php">
                    <i class="bi bi-bank"></i> Post to Bank
                </a>
            </li>
            <li class="sidebar-item">
                <a class="sidebar-link <?= $currentPage === 'approved_list.php' ? 'active' : '' ?>" href="approved_list.php">
                    <i class="bi bi-check2-square"></i> Approved List
                </a>
            </li>
            <?php endif; ?>

            <!-- Employee: View Travel Records only -->
            <?php if ($role === 'Employee'): ?>
            <hr class="sidebar-separator">
            <li class="sidebar-menu-title">Travel Details</li>
            <li class="sidebar-item">
                <a class="sidebar-link <?= $currentPage === 'view_travel_orders.php' ? 'active' : '' ?>" href="view_travel_orders.php">
                    <i class="bi bi-list-task"></i> View Travel Records
                </a>
            </li>
            <?php endif; ?>

            <!-- ── Settings (Admin & HR only) ── -->
            <?php if ($role === 'Admin' || $role === 'HR'): ?>
            <hr class="sidebar-separator">
            <li class="sidebar-menu-title">Settings</li>

            <li class="sidebar-item">
                <a class="sidebar-link <?= $currentPage === 'add_employee.php' ? 'active' : '' ?>" href="add_employee.php">
                    <i class="bi bi-person-lines-fill"></i> Manage Employees
                </a>
            </li>
            <li class="sidebar-item">
                <a class="sidebar-link <?= $currentPage === 'hr_backup.php' ? 'active' : '' ?>" href="hr_backup.php">
                    <i class="bi bi-hdd-network"></i> Backup
                </a>
            </li>
            <?php endif; ?>

            <!-- Manage Users: Admin only -->
            <?php if ($role === 'Admin'): ?>
            <li class="sidebar-item">
                <a class="sidebar-link <?= $currentPage === 'users.php' ? 'active' : '' ?>" href="users.php">
                    <i class="bi bi-people"></i> Manage Users
                </a>
            </li>
            <?php endif; ?>

        </ul>
    </nav>

    <div class="sidebar-footer">
        <a href="logout.php" class="logout-btn">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </div>
</div>
