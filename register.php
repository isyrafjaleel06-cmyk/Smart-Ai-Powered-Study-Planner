<?php
session_start();
require_once 'config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'All fields are required!';
    } elseif (strlen($username) < 3) {
        $error = 'Username must be at least 3 characters long!';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format!';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long!';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match!';
    } else {
        $check_username = $conn->prepare("SELECT student_id FROM Student WHERE username = ?");
        $check_username->bind_param("s", $username);
        $check_username->execute();
        $result = $check_username->get_result();

        if ($result->num_rows > 0) {
            $error = 'Username already exists!';
        } else {
            $check_email = $conn->prepare("SELECT student_id FROM Student WHERE email = ?");
            $check_email->bind_param("s", $email);
            $check_email->execute();
            $result = $check_email->get_result();

            if ($result->num_rows > 0) {
                $error = 'Email already registered!';
            } else {
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $insert = $conn->prepare("INSERT INTO Student (username, email, password) VALUES (?, ?, ?)");
                $insert->bind_param("sss", $username, $email, $hashed_password);

                if ($insert->execute()) {
                    $success = 'Registration successful! Redirecting to login...';
                    $_SESSION['register_success'] = true;
                    header("refresh:2;url=login.php");
                } else {
                    $error = 'Registration failed. Please try again!';
                }
                $insert->close();
            }
            $check_email->close();
        }
        $check_username->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Smart AI-Powered Study Planner</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            width: 100%;
            height: 100%;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: moveBackground 20s linear infinite;
            z-index: 0;
            pointer-events: none;
        }

        @keyframes moveBackground {
            0%   { transform: translate(0, 0); }
            100% { transform: translate(50px, 50px); }
        }

        .wrapper {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 450px;
        }

        .container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 50px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3),
                        0 0 40px rgba(102, 126, 234, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: slideUp 0.6s ease-out;
            max-height: calc(100vh - 40px);
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: #667eea #f0f0f0;
        }

        .container::-webkit-scrollbar { width: 8px; }
        .container::-webkit-scrollbar-track { background: #f0f0f0; border-radius: 10px; }
        .container::-webkit-scrollbar-thumb { background: #667eea; border-radius: 10px; }
        .container::-webkit-scrollbar-thumb:hover { background: #764ba2; }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .icon-container {
            text-align: center;
            margin-bottom: 30px;
        }

        .icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            font-size: 32px;
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }

        .title {
            text-align: center;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
            color: #333;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .subtitle {
            text-align: center;
            font-size: 13px;
            color: #999;
            margin-bottom: 25px;
            letter-spacing: 0.5px;
        }

        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            animation: slideUp 0.4s ease-out;
        }

        .alert-error   { background-color: #fee; color: #c33; border-left: 4px solid #c33; }
        .alert-success { background-color: #efe; color: #3c3; border-left: 4px solid #3c3; }

        .form-group {
            margin-bottom: 20px;
            animation: fadeIn 0.6s ease-out forwards;
            opacity: 0;
        }

        .form-group:nth-child(1) { animation-delay: 0.1s; }
        .form-group:nth-child(2) { animation-delay: 0.2s; }
        .form-group:nth-child(3) { animation-delay: 0.3s; }
        .form-group:nth-child(4) { animation-delay: 0.4s; }

        @keyframes fadeIn { to { opacity: 1; } }

        label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .password-field-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        input {
            width: 100%;
            padding: 14px 16px;
            font-size: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            background-color: #f8f9fa;
            color: #333;
            transition: all 0.3s ease;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        input[type="password"],
        input[type="text"].password-input {
            padding-right: 48px;
        }

        input::placeholder { color: #bbb; }

        input:focus {
            outline: none;
            border-color: #667eea;
            background-color: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            transform: translateY(-2px);
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            background: none;
            border: none;
            cursor: pointer;
            color: #667eea;
            transition: all 0.3s ease;
            padding: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
        }

        .password-toggle:hover {
            color: #764ba2;
            transform: scale(1.2);
        }

        .password-requirements {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
            padding: 8px;
            background-color: #f5f5f5;
            border-radius: 6px;
            display: none;
        }

        .password-requirements.show {
            display: block;
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .requirement { margin: 4px 0; }
        .requirement.met   { color: #3c3; }
        .requirement.unmet { color: #c33; }

        .signup-button {
            width: 100%;
            padding: 14px;
            font-size: 16px;
            font-weight: 600;
            color: white;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            cursor: pointer;
            margin-top: 25px;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
            animation: slideUp 0.6s ease-out 0.5s forwards;
            opacity: 0;
        }

        .signup-button:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.5);
        }

        .signup-button:active:not(:disabled) { transform: translateY(-1px); }
        .signup-button:disabled { opacity: 0.6; cursor: not-allowed; }

        .login-link {
            margin-top: 20px;
            text-align: center;
            font-size: 14px;
            animation: slideUp 0.6s ease-out 0.6s forwards;
            opacity: 0;
        }

        .login-link span { color: #999; }

        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .login-link a:hover { color: #764ba2; text-decoration: underline; }

        .divider {
            display: flex;
            align-items: center;
            margin: 25px 0;
            opacity: 0;
            animation: slideUp 0.6s ease-out 0.45s forwards;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e0e0e0;
        }

        .divider-text {
            padding: 0 12px;
            color: #999;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        @media (max-width: 480px) {
            .container { padding: 30px; max-height: calc(100vh - 20px); }
            .title { font-size: 24px; }
            input { padding: 12px 14px; }
            body { padding: 10px; }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="container">
            <div class="icon-container">
                <div class="icon">📚</div>
            </div>

            <h1 class="title">Sign Up</h1>
            <p class="subtitle">Join our Smart AI-Powered Study Planner</p>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <strong>Success:</strong> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <form id="registerForm" method="POST" action="register.php" novalidate>

                <div class="form-group">
                    <label for="username">Username</label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        placeholder="Enter your username"
                        value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="Enter your email"
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-field-wrapper">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Create a strong password"
                            required
                        >
                        <button type="button" class="password-toggle" id="togglePassword" title="Show/Hide Password">
                            <i class="fa fa-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="password-requirements" id="passwordRequirements">
                        <div class="requirement unmet" id="req-length">✓ At least 6 characters</div>
                        <div class="requirement unmet" id="req-uppercase">✓ At least 1 uppercase letter</div>
                        <div class="requirement unmet" id="req-number">✓ At least 1 number</div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <div class="password-field-wrapper">
                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            placeholder="Confirm your password"
                            required
                        >
                        <button type="button" class="password-toggle" id="toggleConfirmPassword" title="Show/Hide Password">
                            <i class="fa fa-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="signup-button" id="submitBtn">Sign Up Now</button>
            </form>

            <div class="divider">
                <span class="divider-text">Or</span>
            </div>

            <div class="login-link">
                <span>Already have an account? </span>
                <a href="login.php">Sign In Here</a>
            </div>
        </div>
    </div>

    <script>
        // Password visibility toggle
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput  = document.getElementById('password');

        togglePassword.addEventListener('click', function(e) {
            e.preventDefault();
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.querySelector('i').className = type === 'password' ? 'fa fa-eye' : 'fa fa-eye-slash';
        });

        // Confirm password visibility toggle
        const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
        const confirmPasswordInput  = document.getElementById('confirm_password');

        toggleConfirmPassword.addEventListener('click', function(e) {
            e.preventDefault();
            const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            confirmPasswordInput.setAttribute('type', type);
            this.querySelector('i').className = type === 'password' ? 'fa fa-eye' : 'fa fa-eye-slash';
        });

        // Real-time validation
        const form            = document.getElementById('registerForm');
        const username        = document.getElementById('username');
        const email           = document.getElementById('email');
        const password        = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');
        const passwordReqs    = document.getElementById('passwordRequirements');
        const submitBtn       = document.getElementById('submitBtn');

        username.addEventListener('blur', function() {
            this.style.borderColor = this.value.length < 3 ? '#ff6b6b' : '#e0e0e0';
        });

        email.addEventListener('blur', function() {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            this.style.borderColor = !emailRegex.test(this.value) ? '#ff6b6b' : '#e0e0e0';
        });

        password.addEventListener('focus', function() {
            passwordReqs.classList.add('show');
        });

        password.addEventListener('input', function() {
            const length    = this.value.length >= 6;
            const uppercase = /[A-Z]/.test(this.value);
            const number    = /[0-9]/.test(this.value);

            updateRequirement('req-length',    length);
            updateRequirement('req-uppercase', uppercase);
            updateRequirement('req-number',    number);

            this.style.borderColor = (length && uppercase && number) ? '#e0e0e0' : '#ff6b6b';
        });

        password.addEventListener('blur', function() {
            if (this.value.length === 0) passwordReqs.classList.remove('show');
        });

        confirmPassword.addEventListener('input', function() {
            if (this.value !== password.value) {
                this.style.borderColor = '#ff6b6b';
            } else if (this.value.length > 0) {
                this.style.borderColor = '#e0e0e0';
            }
        });

        function updateRequirement(id, met) {
            const el = document.getElementById(id);
            el.classList.toggle('met',   met);
            el.classList.toggle('unmet', !met);
        }

        form.addEventListener('submit', function(e) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

            if (username.value.length < 3) {
                e.preventDefault(); alert('Username must be at least 3 characters long!'); username.focus();
            } else if (!emailRegex.test(email.value)) {
                e.preventDefault(); alert('Please enter a valid email!'); email.focus();
            } else if (password.value.length < 6) {
                e.preventDefault(); alert('Password must be at least 6 characters long!'); password.focus();
            } else if (password.value !== confirmPassword.value) {
                e.preventDefault(); alert('Passwords do not match!'); confirmPassword.focus();
            } else {
                submitBtn.disabled    = true;
                submitBtn.textContent = 'Registering...';
            }
        });
    </script>
</body>
</html>