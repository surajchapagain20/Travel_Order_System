<?php
require_once 'auth.php';
requireLogin();
require_once 'db.php';

// Admin or HR access
$allowedRoles = ['Admin', 'HR'];
if (!in_array($_SESSION['role'] ?? '', $allowedRoles)) {
    header("Location: dashboard.php");
    exit();
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'save') {
        $id = $_POST['id'] ?? '';
        $username = trim($_POST['username']);
        $full_name = trim($_POST['full_name']);
        $role = $_POST['role'];
        $password = $_POST['password'];

        if (empty($username) || empty($full_name) || empty($role)) {
            $error = "Please fill in all required fields.";
        } else {
            if ($id) {
                // Update existing user
                if (!empty($password)) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET username=?, full_name=?, role=?, password=? WHERE id=?");
                    $stmt->bind_param("ssssi", $username, $full_name, $role, $hashed_password, $id);
                } else {
                    $stmt = $conn->prepare("UPDATE users SET username=?, full_name=?, role=? WHERE id=?");
                    $stmt->bind_param("sssi", $username, $full_name, $role, $id);
                }
                
                if ($stmt->execute()) {
                    $message = "User updated successfully.";
                } else {
                    $error = "Error updating user: " . $conn->error;
                }
            } else {
                // Create new user
                if (empty($password)) {
                    $error = "Password is required for new users.";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("INSERT INTO users (username, full_name, role, password) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssss", $username, $full_name, $role, $hashed_password);
                    
                    if ($stmt->execute()) {
                        $message = "User created successfully.";
                    } else {
                        $error = "Error creating user: " . $conn->error;
                    }
                }
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $id = $_POST['id'];
        $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $message = "User deleted successfully.";
        } else {
            $error = "Error deleting user: " . $conn->error;
        }
    }
}

// Fetch all users
$sql = "SELECT id, username, full_name, role, created_at FROM users ORDER BY id DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users | HR Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background-color: #f1f5f9;
        }
        .table-container {
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

<div class="container mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold m-0">Manage Users</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal" onclick="openNewUserModal()">
            <i class="bi bi-person-plus me-1"></i> Add New User
        </button>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="table-container">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Full Name</th>
                    <th>Role</th>
                    <th>Created At</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['id'] ?></td>
                        <td><?= htmlspecialchars($row['username']) ?></td>
                        <td><?= htmlspecialchars($row['full_name']) ?></td>
                        <td>
                            <?php if ($row['role'] === 'HR'): ?>
                                <span class="badge bg-primary">HR</span>
                            <?php elseif ($row['role'] === 'Admin'): ?>
                                <span class="badge bg-success">Admin</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Employee</span>
                            <?php endif; ?>

                        </td>
                        <td><?= date('Y-m-d H:i', strtotime($row['created_at'])) ?></td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-primary me-2" onclick="editUser(<?= htmlspecialchars(json_encode($row)) ?>)">
                                <i class="bi bi-pencil"></i> Edit
                            </button>
                            <?php if ($row['id'] != $_SESSION['user_id']): // Prevent self-deletion ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- User Modal (Add/Edit) -->
<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" id="userForm">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="userId">
            
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="userModalTitle">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Username / Email</label>
                        <input type="text" name="username" id="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" id="fullName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select name="role" id="userRole" class="form-select" required>
                            <option value="Employee">Employee</option>
                            <option value="HR">HR</option>
                            <option value="Admin">Admin</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" id="userPassword" class="form-control">
                        <small class="text-muted" id="passwordHelp">Leave blank to keep existing password.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save User</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const userModal = new bootstrap.Modal(document.getElementById('userModal'));

    function openNewUserModal() {
        document.getElementById('userModalTitle').innerText = 'Add New User';
        document.getElementById('userId').value = '';
        document.getElementById('username').value = '';
        document.getElementById('fullName').value = '';
        document.getElementById('userRole').value = 'Employee';
        document.getElementById('userPassword').required = true;
        document.getElementById('passwordHelp').style.display = 'none';
    }

    function editUser(user) {
        document.getElementById('userModalTitle').innerText = 'Edit User';
        document.getElementById('userId').value = user.id;
        document.getElementById('username').value = user.username;
        document.getElementById('fullName').value = user.full_name;
        document.getElementById('userRole').value = user.role;
        document.getElementById('userPassword').value = '';
        document.getElementById('userPassword').required = false;
        document.getElementById('passwordHelp').style.display = 'block';
        userModal.show();
    }
</script>

</body>
</html>
