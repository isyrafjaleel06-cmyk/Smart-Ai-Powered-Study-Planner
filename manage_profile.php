<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}

$student_id = $_SESSION['student_id'];
$username = $_SESSION['username'];
$success = '';
$error = '';

// Get current student info
$student_query = $conn->prepare("SELECT username, email FROM Student WHERE student_id = ?");
$student_query->bind_param("i", $student_id);
$student_query->execute();
$student_result = $student_query->get_result();
$student_data = $student_result->fetch_assoc();
$current_email = $student_data['email'];
$student_query->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_username = trim($_POST['current_username']);
    $current_password = trim($_POST['current_password']);
    $current_email_input = trim($_POST['current_email']);
    $new_username = trim($_POST['new_username']);
    $new_password = trim($_POST['new_password']);
    $new_email = trim($_POST['new_email']);

    // Verify current credentials
    $verify_query = $conn->prepare("SELECT password FROM Student WHERE student_id = ? AND username = ?");
    $verify_query->bind_param("is", $student_id, $current_username);
    $verify_query->execute();
    $verify_result = $verify_query->get_result();

    if ($verify_result->num_rows === 0) {
        $error = 'Current username is incorrect!';
    } else {
        $verify_row = $verify_result->fetch_assoc();
        if (!password_verify($current_password, $verify_row['password'])) {
            $error = 'Current password is incorrect!';
        } else if ($current_email_input !== $current_email) {
            $error = 'Current email is incorrect!';
        } else {
            // Validate new data
            if (!empty($new_username)) {
                if (strlen($new_username) < 3) {
                    $error = 'New username must be at least 3 characters long!';
                } else {
                    // Check if new username already exists
                    $check_username = $conn->prepare("SELECT student_id FROM Student WHERE username = ? AND student_id != ?");
                    $check_username->bind_param("si", $new_username, $student_id);
                    $check_username->execute();
                    $check_result = $check_username->get_result();
                    if ($check_result->num_rows > 0) {
                        $error = 'New username is already taken!';
                    }
                    $check_username->close();
                }
            }

            if (!empty($new_email)) {
                if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                    $error = 'New email format is invalid!';
                } else {
                    // Check if new email already exists
                    $check_email = $conn->prepare("SELECT student_id FROM Student WHERE email = ? AND student_id != ?");
                    $check_email->bind_param("si", $new_email, $student_id);
                    $check_email->execute();
                    $check_result = $check_email->get_result();
                    if ($check_result->num_rows > 0) {
                        $error = 'New email is already registered!';
                    }
                    $check_email->close();
                }
            }

            // If no errors, update profile
            if (empty($error)) {
                $update_username = !empty($new_username) ? $new_username : $current_username;
                $update_email = !empty($new_email) ? $new_email : $current_email;

                if (!empty($new_password)) {
                    if (strlen($new_password) < 6) {
                        $error = 'New password must be at least 6 characters long!';
                    } else {
                        $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
                        $update_query = $conn->prepare("UPDATE Student SET username = ?, email = ?, password = ? WHERE student_id = ?");
                        $update_query->bind_param("sssi", $update_username, $update_email, $hashed_password, $student_id);
                        if ($update_query->execute()) {
                            $_SESSION['username'] = $update_username;
                            $success = 'Profile updated successfully!';
                            $current_email = $update_email;
                            $username = $update_username;
                        } else {
                            $error = 'Failed to update profile!';
                        }
                        $update_query->close();
                    }
                } else {
                    $update_query = $conn->prepare("UPDATE Student SET username = ?, email = ? WHERE student_id = ?");
                    $update_query->bind_param("ssi", $update_username, $update_email, $student_id);
                    if ($update_query->execute()) {
                        $_SESSION['username'] = $update_username;
                        $success = 'Profile updated successfully!';
                        $current_email = $update_email;
                        $username = $update_username;
                    } else {
                        $error = 'Failed to update profile!';
                    }
                    $update_query->close();
                }
            }
        }
    }
    $verify_query->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Profile - Smart AI-Powered Study Planner</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: moveBackground 20s linear infinite;
            z-index: 0;
            pointer-events: none;
        }

        @keyframes moveBackground {
            0% { transform: translate(0, 0); }
            100% { transform: translate(50px, 50px); }
        }

        /* Header */
        .header {
            position: relative;
            z-index: 20;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 8px 32px rgba(102, 126, 234, 0.15);
        }

        .logo {
            font-size: 18px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .user-section {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            padding: 8px 15px;
            border-radius: 10px;
        }

        .user-profile:hover {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.15) 0%, rgba(118, 75, 162, 0.15) 100%);
            transform: translateY(-2px);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
            font-weight: 700;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            transition: all 0.3s ease;
        }

        .user-profile:hover .user-avatar {
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
        }

        .user-name {
            font-size: 14px;
            font-weight: 600;
            color: #667eea;
        }

        .logout-btn {
            background: white;
            color: #333;
            border: 1px solid #e0e0e0;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .logout-btn:hover {
            background: #f5f5f5;
            border-color: #667eea;
            color: #667eea;
        }

        /* Main Container */
        .main-container {
            display: flex;
            position: relative;
            z-index: 10;
            min-height: calc(100vh - 70px);
            padding: 30px;
            gap: 30px;
        }

        /* Sidebar */
        .sidebar {
            width: 200px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 25px 0;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1),
                        0 0 40px rgba(102, 126, 234, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            height: fit-content;
            animation: slideInLeft 0.6s ease-out;
        }

        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .sidebar-item {
            display: block;
            padding: 12px 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
            color: #666;
            text-decoration: none;
            font-weight: 500;
            position: relative;
            overflow: hidden;
        }

        .sidebar-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            opacity: 0.1;
            transition: left 0.3s ease;
            z-index: -1;
        }

        .sidebar-item:hover::before {
            left: 0;
        }

        .sidebar-item:hover {
            color: #667eea;
            transform: translateX(5px);
        }

        .sidebar-item.active {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.15) 0%, rgba(118, 75, 162, 0.15) 100%);
            color: #667eea;
            font-weight: 600;
            border-left: 4px solid #667eea;
            padding-left: 16px;
        }

        .sidebar-submenu {
            max-height: 200px;
        }

        .sidebar-submenu .sidebar-item {
            font-size: 13px;
            margin-left: 15px;
            padding-left: 20px;
            color: #667eea;
            font-weight: 500;
        }

        /* Content Area */
        .content {
            flex: 1;
            animation: slideInRight 0.6s ease-out;
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .profile-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1),
                        0 0 40px rgba(102, 126, 234, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .profile-header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 30px;
            border-bottom: 2px solid #f0f0f0;
        }

        .profile-title {
            font-size: 28px;
            font-weight: 700;
            color: #333;
            margin-bottom: 20px;
        }

        .profile-subtitle {
            font-size: 14px;
            color: #999;
            font-weight: 500;
        }

        .profile-pic-container {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 20px 0;
        }

        .profile-pic {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }

        .alert {
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-size: 14px;
            animation: slideDown 0.4s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-error {
            background: linear-gradient(135deg, rgba(244, 67, 54, 0.1) 0%, rgba(229, 57, 53, 0.1) 100%);
            color: #d32f2f;
            border-left: 4px solid #d32f2f;
        }

        .alert-success {
            background: linear-gradient(135deg, rgba(76, 175, 80, 0.1) 0%, rgba(56, 142, 60, 0.1) 100%);
            color: #388e3c;
            border-left: 4px solid #388e3c;
        }

        /* Form Sections */
        .form-section {
            margin-bottom: 40px;
        }

        .form-section:last-child {
            margin-bottom: 0;
        }

        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title::before {
            content: '📋';
            font-size: 18px;
        }

        .form-section:nth-child(2) .section-title::before {
            content: '🔐';
        }

        .form-group {
            margin-bottom: 20px;
            animation: fadeIn 0.6s ease-out forwards;
            opacity: 0;
        }

        .form-section:nth-child(1) .form-group:nth-child(1) { animation-delay: 0.1s; }
        .form-section:nth-child(1) .form-group:nth-child(2) { animation-delay: 0.2s; }
        .form-section:nth-child(1) .form-group:nth-child(3) { animation-delay: 0.3s; }
        .form-section:nth-child(2) .form-group:nth-child(1) { animation-delay: 0.4s; }
        .form-section:nth-child(2) .form-group:nth-child(2) { animation-delay: 0.5s; }
        .form-section:nth-child(2) .form-group:nth-child(3) { animation-delay: 0.6s; }

        @keyframes fadeIn {
            to {
                opacity: 1;
            }
        }

        label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 8px;
            color: #333;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .label-optional {
            font-size: 11px;
            color: #999;
            text-transform: none;
            font-weight: 400;
            margin-left: 5px;
        }

        .password-field-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        input {
            width: 100%;
            padding: 12px 15px;
            font-size: 14px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            background-color: #f8f9fa;
            color: #333;
            transition: all 0.3s ease;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        input[type="password"] {
            padding-right: 45px;
        }

        input::placeholder {
            color: #bbb;
        }

        input:focus {
            outline: none;
            border-color: #667eea;
            background-color: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            transform: translateY(-2px);
        }

        input:disabled {
            background-color: #f0f0f0;
            color: #999;
            cursor: not-allowed;
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 18px;
            color: #667eea;
            transition: all 0.3s ease;
            padding: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .password-toggle:hover {
            color: #764ba2;
            transform: scale(1.2);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-row.full {
            grid-template-columns: 1fr;
        }

        /* Info Box */
        .info-box {
            background: linear-gradient(135deg, rgba(33, 150, 243, 0.1) 0%, rgba(3, 155, 229, 0.1) 100%);
            border: 1px solid rgba(33, 150, 243, 0.3);
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 25px;
            font-size: 13px;
            color: #1976d2;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-box-icon {
            font-size: 18px;
            flex-shrink: 0;
        }

        /* Buttons */
        .button-group {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 40px;
            padding-top: 30px;
            border-top: 2px solid #f0f0f0;
            animation: slideUp 0.6s ease-out 0.7s forwards;
            opacity: 0;
        }

        .btn {
            padding: 12px 30px;
            font-size: 14px;
            font-weight: 600;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.5);
        }

        .btn-primary:active {
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
        }

        .btn-secondary:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .main-container {
                flex-direction: column;
                padding: 20px;
            }

            .sidebar {
                width: 100%;
                padding: 15px;
                display: flex;
                flex-wrap: wrap;
                height: auto;
            }

            .sidebar-item {
                flex: 1;
                min-width: 120px;
                text-align: center;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .profile-card {
                padding: 25px;
            }

            .profile-title {
                font-size: 22px;
            }

            .button-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }

            .main-container {
                padding: 15px;
            }

            .profile-pic {
                width: 80px;
                height: 80px;
                font-size: 40px;
            }
        }

        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="logo">📚 Smart AI-Powered Study Planner</div>
        <div class="user-section">
            <div class="user-profile">
                <div class="user-avatar"><?php echo strtoupper(substr($username, 0, 1)); ?></div>
                <span class="user-name"><?php echo htmlspecialchars($username); ?></span>
            </div>
            <a href="logout.php" class="logout-btn">🚪 Log Out</a>
        </div>
    </div>

    <!-- Main Container -->
    <div class="main-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <a href="dashboard.php" class="sidebar-item">
                📊 Dashboard
            </a>
            <a href="#" class="sidebar-item" id="studyMenu">
                📖 Study
            </a>
            <div class="sidebar-submenu">
                <a href="class_timetable.php" class="sidebar-item">
                    📅 Class Timetable
                </a>
                <a href="personal_study_plan.php" class="sidebar-item">
                    📝 Personal Plan
                </a>
            </div>
            <a href="timetable.php" class="sidebar-item">
                ⏰ Timetable
            </a>
            <a href="progress.php" class="sidebar-item">
                📈 Progress
            </a>
            <a href="manage_profile.php" class="sidebar-item active">
                ⚙️ Manage Profile
            </a>
        </div>

        <!-- Content Area -->
        <div class="content">
            <div class="profile-card">
                <div class="profile-header">
                    <h1 class="profile-title">Manage Profile</h1>
                    <p class="profile-subtitle">Update your account information</p>
                    <div class="profile-pic-container">
                        <div class="profile-pic">
                            <?php echo strtoupper(substr($username, 0, 1)); ?>
                        </div>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error">
                        ❌ <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        ✅ <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="manage_profile.php" novalidate>
                    <!-- Current Information Section -->
                    <div class="form-section">
                        <div class="section-title">Verify Your Current Information</div>
                        
                        <div class="info-box">
                            <span class="info-box-icon">ℹ️</span>
                            <span>Please verify your current details before making any changes</span>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="current_username">Current Username</label>
                                <input 
                                    type="text" 
                                    id="current_username" 
                                    name="current_username" 
                                    placeholder="Enter your current username" 
                                    required
                                >
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="current_password">Current Password</label>
                                <div class="password-field-wrapper">
                                    <input 
                                        type="password" 
                                        id="current_password" 
                                        name="current_password" 
                                        placeholder="Enter your current password" 
                                        required
                                    >
                                    <button type="button" class="password-toggle" id="toggleCurrentPassword" title="Show/Hide Password">
                                        👁️
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="current_email">Current Email</label>
                                <input 
                                    type="email" 
                                    id="current_email" 
                                    name="current_email" 
                                    placeholder="Enter your current email" 
                                    value="<?php echo htmlspecialchars($current_email); ?>"
                                    required
                                >
                            </div>
                        </div>
                    </div>

                    <!-- New Information Section -->
                    <div class="form-section">
                        <div class="section-title">Update Your Information</div>

                        <div class="info-box">
                            <span class="info-box-icon">💡</span>
                            <span>Leave fields blank to keep your current information</span>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="new_username">
                                    New Username
                                    <span class="label-optional">(Optional)</span>
                                </label>
                                <input 
                                    type="text" 
                                    id="new_username" 
                                    name="new_username" 
                                    placeholder="Leave empty to keep current username"
                                >
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="new_password">
                                    New Password
                                    <span class="label-optional">(Optional)</span>
                                </label>
                                <div class="password-field-wrapper">
                                    <input 
                                        type="password" 
                                        id="new_password" 
                                        name="new_password" 
                                        placeholder="Leave empty to keep current password"
                                    >
                                    <button type="button" class="password-toggle" id="toggleNewPassword" title="Show/Hide Password">
                                        👁️
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="new_email">
                                    New Email
                                    <span class="label-optional">(Optional)</span>
                                </label>
                                <input 
                                    type="email" 
                                    id="new_email" 
                                    name="new_email" 
                                    placeholder="Leave empty to keep current email"
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Buttons -->
                    <div class="button-group">
                        <button type="submit" class="btn btn-primary">💾 Confirm Changes</button>
                        <a href="dashboard.php" class="btn btn-secondary">❌ Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Password visibility toggle for current password
        const toggleCurrentPassword = document.getElementById('toggleCurrentPassword');
        const currentPasswordInput = document.getElementById('current_password');

        toggleCurrentPassword.addEventListener('click', function(e) {
            e.preventDefault();
            const type = currentPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            currentPasswordInput.setAttribute('type', type);
            this.textContent = type === 'password' ? '👁️' : '🙈';
        });

        // Password visibility toggle for new password
        const toggleNewPassword = document.getElementById('toggleNewPassword');
        const newPasswordInput = document.getElementById('new_password');

        toggleNewPassword.addEventListener('click', function(e) {
            e.preventDefault();
            const type = newPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            newPasswordInput.setAttribute('type', type);
            this.textContent = type === 'password' ? '👁️' : '🙈';
        });

        // Study Menu Toggle
        const studyMenu = document.getElementById('studyMenu');
        const studySubmenu = document.querySelector('.sidebar-submenu');

        studyMenu.addEventListener('click', function(e) {
            e.preventDefault();
            studySubmenu.classList.toggle('open');
            this.classList.toggle('expand-open');
        });

        // Form validation
        const form = document.querySelector('form');
        form.addEventListener('submit', function(e) {
            const currentUsername = document.getElementById('current_username').value.trim();
            const currentPassword = document.getElementById('current_password').value.trim();
            const currentEmail = document.getElementById('current_email').value.trim();

            if (!currentUsername || !currentPassword || !currentEmail) {
                e.preventDefault();
                alert('Please fill in all current information fields!');
                return;
            }

            const newUsername = document.getElementById('new_username').value.trim();
            const newPassword = document.getElementById('new_password').value.trim();
            const newEmail = document.getElementById('new_email').value.trim();

            if (newUsername && newUsername.length < 3) {
                e.preventDefault();
                alert('New username must be at least 3 characters long!');
                return;
            }

            if (newPassword && newPassword.length < 6) {
                e.preventDefault();
                alert('New password must be at least 6 characters long!');
                return;
            }

            if (newEmail && !isValidEmail(newEmail)) {
                e.preventDefault();
                alert('Please enter a valid email!');
                return;
            }
        });

        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        // Sidebar item styling
        document.querySelectorAll('.sidebar-item').forEach(item => {
            item.addEventListener('click', function(e) {
                if (this.id !== 'studyMenu') {
                    document.querySelectorAll('.sidebar-item').forEach(i => i.classList.remove('active'));
                    this.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>