<?php
require_once 'auth.php';
require_once 'db.php';

if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        $stmt = $conn->prepare("SELECT id, username, password, full_name, role FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Invalid password.";
            }
        } else {
            $error = "User not found.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | HR Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-hsl: 222, 47%, 11%;
            --accent-hsl: 210, 100%, 50%;
            --bg-gradient: linear-gradient(135deg, hsl(222, 47%, 11%), hsl(222, 47%, 20%));
            --glass-bg: rgba(255, 255, 255, 0.05);
            --glass-border: rgba(255, 255, 255, 0.1);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Outfit', sans-serif;
        }

        body {
            background: var(--bg-gradient);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            overflow: hidden;
        }

        .login-container {
            width: 100%;
            max-width: 400px;
            padding: 2rem;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.8s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo i {
            font-size: 3rem;
            color: hsl(var(--accent-hsl));
            filter: drop-shadow(0 0 10px hsla(var(--accent-hsl), 0.5));
        }

        .logo h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-top: 1rem;
            letter-spacing: 1px;
        }

        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-group i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.5);
            transition: color 0.3s;
        }

        .form-control {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            color: white;
            font-size: 1rem;
            transition: all 0.3s;
            outline: none;
        }

        .form-control:focus {
            background: rgba(255, 255, 255, 0.1);
            border-color: hsl(var(--accent-hsl));
            box-shadow: 0 0 0 4px hsla(var(--accent-hsl), 0.2);
        }

        .form-control:focus + i {
            color: hsl(var(--accent-hsl));
        }

        .btn-login {
            width: 100%;
            padding: 1rem;
            background: hsl(var(--accent-hsl));
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 1rem;
            box-shadow: 0 10px 15px -3px hsla(var(--accent-hsl), 0.4);
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 25px -5px hsla(var(--accent-hsl), 0.5);
            background: hsl(210, 100%, 60%);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .error-message {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #ef4444;
            padding: 0.75rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .footer {
            margin-top: 2rem;
            text-align: center;
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.4);
        }

        /* Decorative circles */
        .circle {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            z-index: -1;
        }

        .circle-1 {
            width: 300px;
            height: 300px;
            background: hsla(var(--accent-hsl), 0.2);
            top: -100px;
            right: -100px;
        }

        .circle-2 {
            width: 400px;
            height: 400px;
            background: hsla(280, 100%, 50%, 0.1);
            bottom: -150px;
            left: -150px;
        }
    </style>
</head>
<body>
    <div class="circle circle-1"></div>
    <div class="circle circle-2"></div>

    <div class="login-container">
        <div class="logo">
            <i class="bi bi-shield-lock-fill"></i>
            <h1>HR PORTAL</h1>
        </div>

        <?php if ($error): ?>
            <div class="error-message">
                <i class="bi bi-exclamation-circle-fill"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <input type="text" name="username" class="form-control" placeholder="Username / Email" required>
                <i class="bi bi-person-fill"></i>
            </div>

            <div class="form-group">
                <input type="password" name="password" class="form-control" placeholder="Password" required>
                <i class="bi bi-key-fill"></i>
            </div>

            <button type="submit" class="btn-login">Sign In</button>
        </form>

        <div class="footer">
            &copy; <?= date('Y') ?> Nepal Life HR System
        </div>
    </div>
</body>
</html>
