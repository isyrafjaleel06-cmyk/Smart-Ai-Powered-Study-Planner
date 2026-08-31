<?php
session_start();

// If already logged in as admin, redirect to dashboard
if (isset($_SESSION['admin_id'])) {
    header("Location: admin_dashboard.php");
    exit();
}

require_once '../config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Username and password are required!';
    } else {
        $stmt = $conn->prepare("SELECT admin_id, password, email FROM admin WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();
            if (password_verify($password, $row['password'])) {
                $_SESSION['admin_id']       = $row['admin_id'];
                $_SESSION['admin_username'] = $username;
                $_SESSION['admin_email']    = $row['email'];
                header("Location: admin_dashboard.php");
                exit();
            } else {
                $error = 'Invalid username or password!';
            }
        } else {
            $error = 'Invalid username or password!';
        }
        $stmt->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Smart AI-Powered Study Planner</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            display: flex; justify-content: center; align-items: center;
            padding: 20px; position: relative; overflow-x: hidden;
        }

        body::before {
            content: ''; position: fixed; top: 0; left: 0;
            width: 100%; height: 100%;
            background: radial-gradient(circle, rgba(255,255,255,0.03) 1px, transparent 1px);
            background-size: 40px 40px;
            animation: moveBackground 25s linear infinite;
            z-index: 0; pointer-events: none;
        }
        @keyframes moveBackground {
            0%   { transform: translate(0,0); }
            100% { transform: translate(40px,40px); }
        }

        /* Floating orbs */
        body::after {
            content: ''; position: fixed;
            width: 400px; height: 400px; border-radius: 50%;
            background: radial-gradient(circle, rgba(99,102,241,0.15) 0%, transparent 70%);
            top: -100px; right: -100px;
            animation: float 8s ease-in-out infinite;
            z-index: 0; pointer-events: none;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50%       { transform: translateY(30px); }
        }

        /* ── Container ── */
        .container {
            position: relative; z-index: 10;
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 24px; padding: 50px 45px;
            width: 100%; max-width: 440px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.4),
                        0 0 0 1px rgba(255,255,255,0.05) inset;
            animation: slideUp 0.6s ease-out;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── Icon ── */
        .icon-wrap {
            width: 72px; height: 72px; border-radius: 20px;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 24px;
            box-shadow: 0 12px 30px rgba(99,102,241,0.5);
            transition: transform 0.3s ease;
        }
        .icon-wrap:hover { transform: scale(1.05) rotate(-3deg); }
        .icon-wrap i { color: white; font-size: 30px; }

        /* ── Badge ── */
        .admin-badge {
            display: inline-block; padding: 4px 14px;
            background: rgba(99,102,241,0.2);
            border: 1px solid rgba(99,102,241,0.4);
            border-radius: 20px; font-size: 11px; font-weight: 700;
            color: #a5b4fc; text-transform: uppercase; letter-spacing: 1.5px;
            margin-bottom: 12px;
        }

        /* ── Title ── */
        .title {
            text-align: center; font-size: 28px; font-weight: 800;
            color: white; margin-bottom: 6px; letter-spacing: -0.5px;
        }
        .subtitle { text-align: center; font-size: 13px; color: rgba(255,255,255,0.45); margin-bottom: 32px; }

        /* ── Alert ── */
        .alert {
            padding: 13px 16px; border-radius: 10px; margin-bottom: 22px;
            font-size: 13px; display: flex; align-items: center; gap: 10px;
            background: rgba(239,68,68,0.15); color: #fca5a5;
            border: 1px solid rgba(239,68,68,0.3);
            animation: slideDown 0.3s ease;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── Form ── */
        .form-group { margin-bottom: 20px; }

        label {
            display: block; font-size: 11px; font-weight: 700;
            color: rgba(255,255,255,0.5); text-transform: uppercase;
            letter-spacing: 0.8px; margin-bottom: 8px;
        }

        .input-wrap { position: relative; display: flex; align-items: center; }

        input {
            width: 100%; padding: 13px 16px; font-size: 14px;
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 10px; color: white;
            transition: all 0.3s ease; font-family: inherit;
        }
        input[type="password"] { padding-right: 46px; }
        input::placeholder { color: rgba(255,255,255,0.25); }
        input:focus {
            outline: none;
            border-color: #6366f1;
            background: rgba(99,102,241,0.1);
            box-shadow: 0 0 0 3px rgba(99,102,241,0.2);
        }

        /* ── Eye toggle ── */
        .eye-btn {
            position: absolute; right: 13px;
            background: none; border: none; cursor: pointer;
            color: rgba(255,255,255,0.3); font-size: 14px;
            transition: color 0.2s ease; padding: 4px;
            display: flex; align-items: center;
        }
        .eye-btn:hover { color: #a5b4fc; }

        /* ── Login Button ── */
        .login-btn {
            width: 100%; padding: 14px; font-size: 15px; font-weight: 700;
            color: white; border: none; border-radius: 12px; cursor: pointer;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            box-shadow: 0 8px 24px rgba(99,102,241,0.4);
            transition: all 0.3s ease; margin-top: 8px;
            display: flex; align-items: center; justify-content: center; gap: 9px;
            letter-spacing: 0.3px;
        }
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 32px rgba(99,102,241,0.55);
        }
        .login-btn:active { transform: translateY(0); }

        /* ── Divider ── */
        .divider {
            display: flex; align-items: center; gap: 12px; margin: 28px 0 20px;
        }
        .divider::before, .divider::after {
            content: ''; flex: 1; height: 1px;
            background: rgba(255,255,255,0.08);
        }
        .divider span { font-size: 11px; color: rgba(255,255,255,0.25); }

        /* ── Back link ── */
        .back-link {
            text-align: center; font-size: 13px; color: rgba(255,255,255,0.35);
        }
        .back-link a {
            color: #a5b4fc; text-decoration: none; font-weight: 600;
            transition: color 0.2s ease;
        }
        .back-link a:hover { color: #c4b5fd; text-decoration: underline; }

        /* ── Security notice ── */
        .security-note {
            margin-top: 28px; padding: 14px 16px;
            background: rgba(99,102,241,0.08);
            border: 1px solid rgba(99,102,241,0.2);
            border-radius: 10px;
            display: flex; align-items: flex-start; gap: 10px;
        }
        .security-note i { color: #818cf8; font-size: 14px; margin-top: 1px; flex-shrink: 0; }
        .security-note p { font-size: 12px; color: rgba(255,255,255,0.35); line-height: 1.5; }

        @media (max-width: 480px) {
            .container { padding: 35px 25px; }
            .title { font-size: 24px; }
        }
    </style>
</head>
<body>
    <div class="container">

        <!-- Icon -->
        <div style="text-align:center;">
            <div class="icon-wrap">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
            <div class="admin-badge">Admin Portal</div>
        </div>

        <h1 class="title">Admin Sign In</h1>
        <p class="subtitle">Smart AI-Powered Study Planner</p>

        <?php if ($error): ?>
            <div class="alert">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="admin_login.php">

            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username"
                       placeholder="Enter admin username"
                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                       required autocomplete="username">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrap">
                    <input type="password" id="password" name="password"
                           placeholder="Enter admin password"
                           required autocomplete="current-password">
                    <button type="button" class="eye-btn" id="togglePwd" title="Show/Hide Password">
                        <i class="fa fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="login-btn">
                <i class="fa-solid fa-right-to-bracket"></i>
                Sign In to Admin Panel
            </button>
        </form>

        <div class="divider"><span>or</span></div>

        <div class="back-link">
            Not an admin? <a href="../login.php">Go to Student Login</a>
        </div>

        <div class="security-note">
            <i class="fa-solid fa-lock"></i>
            <p>This area is restricted to authorised administrators only. Unauthorised access attempts are monitored.</p>
        </div>

    </div>

    <script>
        const togglePwd = document.getElementById('togglePwd');
        const pwdInput  = document.getElementById('password');

        togglePwd.addEventListener('click', function(e) {
            e.preventDefault();
            const type = pwdInput.getAttribute('type') === 'password' ? 'text' : 'password';
            pwdInput.setAttribute('type', type);
            this.querySelector('i').className = type === 'password' ? 'fa fa-eye' : 'fa fa-eye-slash';
        });
    </script>
</body>
</html>