<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
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
        // --- Active Directory Authentication ---
        $ad_auth_success = false;
        
        // Simplified LDAP connection: try standard LDAP with StartTLS, then fallback to LDAPS
        $ldap_host = '10.150.1.34';
        $ldap_domain = "Nepallife.com.np";
        
        $upn = $username;
        if (strpos($upn, '@') === false) {
            $upn = $upn . '@' . $ldap_domain;
        }

        $ldap_conn = @ldap_connect($ldap_host, 389);
        if ($ldap_conn) {
            @ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
            @ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);
            @ldap_set_option($ldap_conn, LDAP_OPT_NETWORK_TIMEOUT, 10);
            // Attempt to upgrade to TLS; ignore failures (will continue insecurely if not possible)
            @ldap_start_tls($ldap_conn);
        } else {
            // Fallback to LDAPS on port 636
            $ldap_conn = @ldap_connect($ldap_host, 636);
            if ($ldap_conn) {
                @ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
                @ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);
                @ldap_set_option($ldap_conn, LDAP_OPT_NETWORK_TIMEOUT, 10);
                // For LDAPS, ignore cert verification if self‑signed
                @ldap_set_option($ldap_conn, LDAP_OPT_X_TLS_REQUIRE_CERT, 0);
            }
        }
        
        if (!$ldap_conn) {
            $error = 'Unable to connect to Active Directory server.';
        }
        
        if ($ldap_conn) {
            if (!empty($password)) {
                // Attempt bind using UPN format
                $ldap_bind = @ldap_bind($ldap_conn, $upn, $password);
                if (!$ldap_bind) {
                    // Fallback to DOMAIN\username format
                    $ldap_bind = @ldap_bind($ldap_conn, 'nepallife\\' . $username, $password);
                }
                if ($ldap_bind) {
                    $ad_auth_success = true;
                }
            }
        } else {
            $error = 'Unable to connect to Active Directory server.';
        }

        if ($ad_auth_success) {
            // AD authentication successful – sync with local DB
            $stmt = $conn->prepare("SELECT id, username, full_name, role FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($user = $result->fetch_assoc()) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
            } else {
                // Fallback if AD user not in local table
                $_SESSION['user_id'] = $username;
                $_SESSION['username'] = $username;
                $_SESSION['full_name'] = $username;
                $_SESSION['role'] = 'User';
            }
            header("Location: dashboard.php");
            exit();
        } else {
    $error = "Invalid Active Directory username or password.";
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
            position: relative;
        }

        .login-container {
            width: 100%;
            max-width: 400px;
            padding: 2.5rem;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.8s ease-out;
            z-index: 10;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .logo {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .logo i {
            font-size: 3.5rem;
            color: hsl(var(--accent-hsl));
            filter: drop-shadow(0 0 10px hsla(var(--accent-hsl), 0.5));
            margin-bottom: 0.5rem;
        }

        .logo h1 {
            font-size: 1.75rem;
            font-weight: 600;
            margin-top: 1rem;
            letter-spacing: 1px;
            color: #ffffff;
        }
        
        .logo p {
            font-size: 0.9rem;
            color: rgba(255,255,255,0.6);
            margin-top: 0.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-group i {
            position: absolute;
            left: 1.25rem;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.5);
            transition: color 0.3s;
            font-size: 1.1rem;
        }

        .form-control {
            width: 100%;
            padding: 1rem 1rem 1rem 3.25rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            color: white;
            font-size: 1rem;
            transition: all 0.3s;
            outline: none;
        }
        
        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.4);
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
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 1rem;
            box-shadow: 0 10px 15px -3px hsla(var(--accent-hsl), 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
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
            padding: 0.85rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            animation: shake 0.5s ease-in-out;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            50% { transform: translateX(5px); }
            75% { transform: translateX(-5px); }
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
            z-index: 1;
        }
        .circle-1 {
            width: 400px; height: 400px;
            background: hsla(210, 100%, 50%, 0.15);
            top: -100px; left: -100px;
        }
        .circle-2 {
            width: 300px; height: 300px;
            background: hsla(280, 100%, 50%, 0.15);
            bottom: -50px; right: -50px;
        }
    </style>
</head>
<body>

    <div class="circle circle-1"></div>
    <div class="circle circle-2"></div>

    <div class="login-container">
        <div class="logo">
            <i class="bi bi-shield-lock-fill"></i>
            <h1>HR Portal Login</h1>
            <p>Sign in using your Nepal Life Active Directory credentials</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="error-message">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <input type="text" name="username" class="form-control" placeholder="AD Username" required autofocus>
                <i class="bi bi-person-fill"></i>
            </div>
            
            <div class="form-group">
                <input type="password" name="password" class="form-control" placeholder="Password" required>
                <i class="bi bi-key-fill"></i>
            </div>

            <button type="submit" class="btn-login">
                Sign In <i class="bi bi-box-arrow-in-right"></i>
            </button>
        </form>

        <div class="footer">
            &copy; <?php echo date("Y"); ?> Nepal Life Insurance. All rights reserved.
        </div>
    </div>

</body>
</html>
