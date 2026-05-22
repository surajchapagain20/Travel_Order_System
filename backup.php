<?php
include('password.php'); // USER_ID and USER_PASSWORD

session_start();

// Timeout
$timeout_duration = 120;

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
        session_unset();
        session_destroy();
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    $_SESSION['last_activity'] = time();
    $message = $_SESSION['message'] ?? '';
    unset($_SESSION['message']);


    date_default_timezone_set("Asia/Kathmandu");
    $currentDateTime = date("Y-m-d H:i:s");

    $backupDir = __DIR__ . '/backups';
    $mysqlUser = 'root';
    $mysqlPassword = '';
    $mysqlPath = 'D:\\xampp\\mysql\\bin\\mysqldump.exe';
    $message = '';

    if (!file_exists($backupDir)) {
        mkdir($backupDir, 0777, true);
    }

    // Handle Backup
    if (isset($_POST['backup'])) {
        $timestamp = date('Y-m-d_H-i-s');
        $backupFile = "$backupDir/all_db_backup_$timestamp.sql";

        $mysqlUserEscaped = escapeshellarg($mysqlUser);
        $mysqlPasswordPart = $mysqlPassword !== '' ? '-p' . escapeshellarg($mysqlPassword) : '';
        $backupFileEscaped = escapeshellarg($backupFile);
        $mysqlPathEscaped = escapeshellcmd($mysqlPath);

		  $command = "D:\\xampp\\mysql\\bin\\mysqldump.exe -u $mysqlUserEscaped $mysqlPasswordPart --all-databases > $backupFileEscaped";
		//$command = "$mysqlPathEscaped -u $mysqlUserEscaped $mysqlPasswordPart --all-databases --result-file=$backupFileEscaped";
        exec($command, $output, $result);

        if ($result === 0) {
            $_SESSION['message'] = "<div class='alert alert-success'>✅ Backup created successfully at <code>$timestamp</code>.</div>";
        } else {
            $_SESSION['message'] = "<div class='alert alert-danger'>❌ Backup failed. Please check MySQL path or credentials.<br><small>Command: <code>$command</code></small><br><small>Error Code: $result</small></div>";
        }
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    // Handle Delete
    if (isset($_POST['delete_file'])) {
        $filename = basename($_POST['delete_file']);
        $filepath = "$backupDir/$filename";

        if (file_exists($filepath)) {
            unlink($filepath) ?
                $message = "<div class='alert alert-warning'>🗑️ File <strong>$filename</strong> deleted.</div>" :
                $message = "<div class='alert alert-danger'>❌ Failed to delete <strong>$filename</strong>.</div>";
        } else {
            $message = "<div class='alert alert-danger'>❌ File not found for deletion.</div>";
        }
    }

    // Handle Download
    if (isset($_GET['download'])) {
        $filename = basename($_GET['download']);
        $filepath = "$backupDir/$filename";

        if (file_exists($filepath)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/sql');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($filepath));
            readfile($filepath);
            exit;
        } else {
            $message = "<div class='alert alert-danger'>❌ File not found.</div>";
        }
    }

    // Handle View Modal
    if (isset($_GET['view'])) {
        $filename = basename($_GET['view']);
        $filepath = "$backupDir/$filename";
        if (file_exists($filepath)) {
            echo "<pre style='white-space: pre-wrap; word-wrap: break-word; max-height: 70vh; overflow:auto;'>";
            echo htmlspecialchars(file_get_contents($filepath));
            echo "</pre>";
        } else {
            echo "<p class='text-danger'>❌ File not found.</p>";
        }
        exit;
    }

    // List backups
    $backups = [];
    foreach (glob("$backupDir/*.sql") as $file) {
        $backups[] = [
            'name' => basename($file),
            'time' => date("Y-m-d H:i:s", filemtime($file))
        ];
    }
    usort($backups, fn($a, $b) => strcmp($b['time'], $a['time']));

} else {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'], $_POST['password'])) {
        if ($_POST['email'] === USER_ID && $_POST['password'] === USER_PASSWORD) {
            $_SESSION['logged_in'] = true;
            $_SESSION['last_activity'] = time();
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $loginError = "<div class='alert alert-danger'>❌ Invalid Email or Password!</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>MySQL Backup Tool</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script>
        function viewFile(filename) {
            fetch('?view=' + encodeURIComponent(filename))
                .then(res => res.text())
                .then(data => {
                    document.getElementById('modalContent').innerHTML = data;
                    new bootstrap.Modal(document.getElementById('viewModal')).show();
                });
        }
    </script>
</head>
<body class="bg-light">
<div class="container py-5">
    <?php if (!isset($_SESSION['logged_in'])): ?>
        <h2>Login</h2>
        <?= $loginError ?? '' ?>
        <form method="post">
            <div class="mb-3">
                <label>Email:</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <div class="mb-3">
                <label>Password:</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button class="btn btn-primary">Login</button>
        </form>
    <?php else: ?>
        <h2>🗃️ MySQL Full Database Backup</h2>
        <p><strong>📅 Date & Time:</strong> <?= $currentDateTime ?></p>
        <?= $message ?>
        <form method="post">
            <button name="backup" class="btn btn-primary mb-4">🔄 Backup Now</button>
        </form>

        <h5>📂 Backup Files</h5>
        <?php if ($backups): ?>
            <ul class="list-group">
                <?php foreach ($backups as $b): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <?= htmlspecialchars($b['name']) ?>
                        <div>
                            <span class="badge bg-secondary me-2"><?= $b['time'] ?></span>
                            <button class="btn btn-sm btn-info" onclick="viewFile('<?= htmlspecialchars($b['name']) ?>')">👁️ View</button>
                            <a href="?download=<?= urlencode($b['name']) ?>" class="btn btn-sm btn-success">⬇️ Download</a>
                            <form method="post" style="display:inline;" onsubmit="return confirm('Delete this backup?');">
                                <input type="hidden" name="delete_file" value="<?= htmlspecialchars($b['name']) ?>">
                                <button type="submit" class="btn btn-sm btn-danger">🗑️ Delete</button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>No backup files available.</p>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Modal -->
<div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">📄 SQL File Content</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalContent">
                Loading...
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
