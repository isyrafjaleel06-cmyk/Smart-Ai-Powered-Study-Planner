<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}

$student_id = $_SESSION['student_id'];
$username   = $_SESSION['username'];
$success    = '';
$error      = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $new_username    = trim($_POST['username'] ?? '');
        $email           = trim($_POST['email'] ?? '');
        $wake_up_time    = $_POST['wake_up_time'] ?? '07:00';
        $sleep_time      = $_POST['sleep_time'] ?? '23:00';
        $max_study_hours = (int)($_POST['max_study_hours'] ?? 4);

        $preferred_times     = isset($_POST['preferred_time']) ? (array)$_POST['preferred_time'] : [];
        $preferred_time_json = !empty($preferred_times) ? json_encode($preferred_times) : json_encode(['Morning']);

        if (strlen($new_username) < 3) {
            $error = 'Username must be at least 3 characters!';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format!';
        } else {
            $chk = $conn->prepare("SELECT student_id FROM student WHERE username = ? AND student_id != ?");
            $chk->bind_param("si", $new_username, $student_id);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $error = 'Username already taken!';
            } else {
                $upd = $conn->prepare("UPDATE student SET username = ?, email = ?, wake_up_time = ?, sleep_time = ?, preferred_time = ?, max_study_hours = ? WHERE student_id = ?");
                $upd->bind_param("sssssii", $new_username, $email, $wake_up_time, $sleep_time, $preferred_time_json, $max_study_hours, $student_id);
                if ($upd->execute()) {
                    $_SESSION['username'] = $new_username;
                    $username = $new_username;
                    $success  = 'Profile updated successfully!';
                } else {
                    $error = 'Failed to update profile: ' . $conn->error;
                }
                $upd->close();
            }
            $chk->close();
        }

    } elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password     = $_POST['new_password'] ?? '';

        $gp = $conn->prepare("SELECT password FROM student WHERE student_id = ?");
        $gp->bind_param("i", $student_id);
        $gp->execute();
        $pwd_data = $gp->get_result()->fetch_assoc();
        $gp->close();

        if (!password_verify($current_password, $pwd_data['password'])) {
            $error = 'Current password is incorrect!';
        } elseif (strlen($new_password) < 6) {
            $error = 'New password must be at least 6 characters!';
        } else {
            $hashed = password_hash($new_password, PASSWORD_BCRYPT);
            $upd    = $conn->prepare("UPDATE student SET password = ? WHERE student_id = ?");
            $upd->bind_param("si", $hashed, $student_id);
            $success = $upd->execute() ? 'Password changed successfully!' : 'Failed to change password!';
            $upd->close();
        }
    }
}

// Fetch current data
$sq = $conn->prepare("SELECT email, wake_up_time, sleep_time, preferred_time, max_study_hours FROM student WHERE student_id = ?");
$sq->bind_param("i", $student_id);
$sq->execute();
$student_data = $sq->get_result()->fetch_assoc();
$sq->close();
$conn->close();

$email           = $student_data['email']           ?? '';
$wake_up_time    = $student_data['wake_up_time']    ?? '07:00';
$sleep_time      = $student_data['sleep_time']      ?? '23:00';
$max_study_hours = $student_data['max_study_hours'] ?? 4;

$preferred_times = [];
if (!empty($student_data['preferred_time'])) {
    $decoded = json_decode($student_data['preferred_time'], true);
    $preferred_times = is_array($decoded) ? $decoded : [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Profile - Smart AI-Powered Study Planner</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh; color: #333;
            position: relative; overflow-x: hidden;
        }

        body::before {
            content: ''; position: fixed; top: -50%; left: -50%;
            width: 200%; height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: moveBackground 20s linear infinite;
            z-index: 0; pointer-events: none;
        }
        @keyframes moveBackground {
            0%   { transform: translate(0,0); }
            100% { transform: translate(50px,50px); }
        }

        /* ── Header ── */
        .header {
            position: relative; z-index: 20;
            background: rgba(255,255,255,0.95); backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255,255,255,0.2);
            padding: 20px 30px;
            display: flex; justify-content: space-between; align-items: center;
            box-shadow: 0 8px 32px rgba(102,126,234,0.15);
        }
        .logo {
            font-size: 18px; font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        }
        .user-info { display: flex; align-items: center; gap: 12px; }
        .user-profile {
            display: flex; align-items: center; gap: 12px;
            cursor: pointer; transition: all 0.3s ease;
            padding: 8px 15px; border-radius: 10px; text-decoration: none;
        }
        .user-profile:hover { background: rgba(102,126,234,0.1); }
        .user-avatar {
            width: 40px; height: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%; display: flex; align-items: center;
            justify-content: center; color: white; font-size: 18px; font-weight: 700;
            box-shadow: 0 4px 15px rgba(102,126,234,0.3);
        }
        .user-name { font-size: 14px; font-weight: 600; color: #667eea; }
        .logout-btn {
            padding: 9px 18px; border-radius: 8px; cursor: pointer;
            font-size: 13px; font-weight: 600; transition: all 0.3s ease;
            text-decoration: none; display: inline-block;
            background: white; color: #333;
            border: 1px solid #e0e0e0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .logout-btn:hover { background: #f5f5f5; border-color: #667eea; color: #667eea; transform: translateY(-2px); }

        /* ── Layout ── */
        .main-container {
            display: flex; position: relative; z-index: 10;
            min-height: calc(100vh - 70px); padding: 30px; gap: 30px;
        }

        /* ── Sidebar ── */
        .sidebar {
            width: 200px; background: rgba(255,255,255,0.95); backdrop-filter: blur(10px);
            border-radius: 20px; padding: 25px 0;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 0 40px rgba(102,126,234,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            height: fit-content; animation: slideInLeft 0.6s ease-out;
        }
        @keyframes slideInLeft {
            from { opacity: 0; transform: translateX(-30px); }
            to   { opacity: 1; transform: translateX(0); }
        }
        .sidebar-item {
            display: block; padding: 12px 20px; cursor: pointer;
            transition: all 0.3s ease; font-size: 14px; color: #666;
            text-decoration: none; font-weight: 500; position: relative; overflow: hidden;
        }
        .sidebar-item::before {
            content: ''; position: absolute; top: 0; left: -100%;
            width: 100%; height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            opacity: 0.1; transition: left 0.3s ease; z-index: -1;
        }
        .sidebar-item:hover::before { left: 0; }
        .sidebar-item:hover { color: #667eea; transform: translateX(5px); }
        .sidebar-item.active {
            background: rgba(102,126,234,0.15); color: #667eea; font-weight: 600;
            border-left: 4px solid #667eea; padding-left: 16px;
        }
        .sidebar-submenu { max-height: 200px; }
        .sidebar-submenu .sidebar-item {
            font-size: 13px; margin-left: 15px; padding-left: 20px;
            color: #667eea; font-weight: 500;
        }

        /* ── Content ── */
        .content {
            flex: 1; animation: slideInRight 0.6s ease-out;
            max-height: calc(100vh - 100px); overflow-y: auto;
        }
        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(30px); }
            to   { opacity: 1; transform: translateX(0); }
        }

        /* ── Page Header ── */
        .page-header {
            background: rgba(255,255,255,0.95); backdrop-filter: blur(10px);
            border-radius: 20px; padding: 25px 30px; margin-bottom: 25px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 0 40px rgba(102,126,234,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; gap: 15px;
            animation: slideUp 0.6s ease-out;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .page-title   { font-size: 26px; font-weight: 700; color: #333; margin-bottom: 5px; }
        .page-subtitle { font-size: 14px; color: #999; font-weight: 500; }

        /* ── Continue Button ── */
        .btn-continue {
            padding: 12px 22px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; border: none; border-radius: 10px;
            cursor: pointer; font-size: 14px; font-weight: 600;
            text-decoration: none; display: inline-flex;
            align-items: center; gap: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 8px 20px rgba(102,126,234,0.35);
            white-space: nowrap;
        }
        .btn-continue:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 30px rgba(102,126,234,0.5);
        }

        /* ── Alerts ── */
        .alert {
            padding: 15px; border-radius: 12px; margin-bottom: 20px;
            font-size: 14px; animation: slideDown 0.4s ease-out;
            display: flex; align-items: center; gap: 10px;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .alert-error   { background: rgba(244,67,54,0.1);  color: #d32f2f; border-left: 4px solid #d32f2f; }
        .alert-success { background: rgba(76,175,80,0.1);  color: #388e3c; border-left: 4px solid #388e3c; }

        /* ── Card ── */
        .card {
            background: rgba(255,255,255,0.95); backdrop-filter: blur(10px);
            border-radius: 20px; padding: 28px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 0 40px rgba(102,126,234,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            margin-bottom: 20px;
            animation: slideUp 0.6s ease-out forwards; opacity: 0;
        }
        .card:nth-child(1) { animation-delay: 0.10s; }
        .card:nth-child(2) { animation-delay: 0.18s; }
        .card:nth-child(3) { animation-delay: 0.26s; }
        .card:nth-child(4) { animation-delay: 0.34s; }

        .card-title {
            font-size: 16px; font-weight: 700; color: #333;
            margin-bottom: 22px; padding-bottom: 14px;
            border-bottom: 2px solid #f0f0f0;
            display: flex; align-items: center; gap: 8px;
        }

        /* ── Profile Grid ── */
        .profile-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 18px;
        }
        .form-group { margin-bottom: 0; }

        label {
            display: block; font-size: 11px; font-weight: 700; color: #888;
            text-transform: uppercase; letter-spacing: 0.6px; margin-bottom: 7px;
        }

        /* ── Inputs ── */
        .input-wrap { position: relative; display: flex; align-items: center; }

        input[type="email"],
        input[type="password"],
        input[type="time"],
        input[type="text"],
        input[type="number"] {
            width: 100%; padding: 11px 14px;
            font-size: 14px; font-family: inherit;
            border: 2px solid #e8e8e8; border-radius: 10px;
            background: #f8f9fa; color: #333;
            transition: border-color 0.25s ease, box-shadow 0.25s ease, background 0.25s ease;
        }
        input[type="password"] { padding-right: 44px; }
        input:focus {
            outline: none; border-color: #667eea;
            background: white; box-shadow: 0 0 0 3px rgba(102,126,234,0.12);
        }
        input:disabled { background: #f0f0f0; color: #aaa; cursor: not-allowed; border-color: #e0e0e0; }

        .eye-btn {
            position: absolute; right: 12px;
            background: none; border: none; cursor: pointer;
            color: #aaa; font-size: 14px;
            transition: color 0.2s ease; padding: 4px;
            display: flex; align-items: center;
        }
        .eye-btn:hover { color: #667eea; }

        /* ── Time Grid ── */
        .time-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }

        /* ── Checkboxes ── */
        .checkbox-group { display: flex; gap: 20px; margin-top: 6px; flex-wrap: wrap; }
        .checkbox-option { display: flex; align-items: center; gap: 9px; cursor: pointer; }
        .checkbox-option input[type="checkbox"] { display: none; }
        .checkbox-box {
            width: 20px; height: 20px; border-radius: 6px;
            border: 2px solid #ddd; display: flex; align-items: center;
            justify-content: center; background: white; transition: all 0.2s ease;
        }
        .checkbox-option input[type="checkbox"]:checked + .checkbox-box {
            border-color: #667eea; background: #667eea;
        }
        .checkbox-option input[type="checkbox"]:checked + .checkbox-box::after {
            content: '✓'; color: white; font-weight: 700; font-size: 12px;
        }
        .checkbox-label { font-size: 14px; font-weight: 500; color: #555; }

        /* ── Slider ── */
        .slider-wrap { padding: 10px 0; }
        .slider-track {
            position: relative; height: 8px;
            background: #e8e8e8; border-radius: 10px; margin-bottom: 18px;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.08);
        }
        .slider-fill {
            position: absolute; left: 0; top: 0; height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            border-radius: 10px; pointer-events: none;
            transition: width 0.05s linear;
        }
        .slider {
            position: absolute; top: 50%; left: 0;
            transform: translateY(-50%);
            width: 100%; height: 100%;
            -webkit-appearance: none; appearance: none;
            background: transparent; outline: none; cursor: pointer; margin: 0;
        }
        .slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 24px; height: 24px; border-radius: 50%;
            background: white; border: 3px solid #667eea;
            box-shadow: 0 2px 12px rgba(102,126,234,0.4);
            cursor: grab;
            transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
        }
        .slider::-webkit-slider-thumb:hover {
            transform: scale(1.2);
            box-shadow: 0 4px 20px rgba(102,126,234,0.55);
            border-color: #764ba2;
        }
        .slider:active::-webkit-slider-thumb {
            cursor: grabbing; transform: scale(1.1);
            box-shadow: 0 6px 24px rgba(102,126,234,0.6);
        }
        .slider::-moz-range-thumb {
            width: 24px; height: 24px; border-radius: 50%;
            background: white; border: 3px solid #667eea;
            box-shadow: 0 2px 12px rgba(102,126,234,0.4);
            cursor: grab;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .slider::-moz-range-thumb:hover {
            transform: scale(1.2);
            box-shadow: 0 4px 20px rgba(102,126,234,0.55);
        }
        .slider-labels { display: flex; justify-content: space-between; align-items: center; }
        .slider-min-max { font-size: 11px; color: #bbb; font-weight: 600; }
        .slider-current {
            font-size: 26px; font-weight: 800;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
            transition: all 0.2s ease; min-width: 80px; text-align: center;
        }

        /* ── Save Button ── */
        .btn-save {
            width: 100%; padding: 13px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; border: none; border-radius: 10px;
            cursor: pointer; font-size: 14px; font-weight: 700;
            transition: all 0.3s ease; letter-spacing: 0.4px;
            box-shadow: 0 8px 20px rgba(102,126,234,0.35);
            margin-top: 22px;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 30px rgba(102,126,234,0.5);
        }

        /* ── Info Note ── */
        .info-note {
            margin-top: 20px; padding: 15px;
            background: rgba(102,126,234,0.06);
            border-left: 4px solid #667eea; border-radius: 8px;
            font-size: 13px; color: #667eea; font-weight: 500;
        }

        /* ── Scrollbar ── */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: rgba(255,255,255,0.1); }
        ::-webkit-scrollbar-thumb { background: linear-gradient(135deg,#667eea,#764ba2); border-radius: 4px; }

        /* ── Responsive ── */
        @media (max-width: 1024px) {
            .main-container { flex-direction: column; }
            .sidebar { width: 100%; display: flex; flex-wrap: wrap; height: auto; padding: 15px; }
            .sidebar-item { flex: 1; min-width: 100px; text-align: center; }
        }
        @media (max-width: 640px) {
            .profile-grid { grid-template-columns: 1fr; }
            .time-grid    { grid-template-columns: 1fr; }
            .page-header  { flex-direction: column; align-items: flex-start; }
            .btn-continue { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>

<!-- Header -->
<div class="header">
    <div class="logo">Smart AI-Powered Study Planner</div>
    <div class="user-info">
        <a href="manage_profile.php" class="user-profile">
            <div class="user-avatar"><?php echo strtoupper(substr($username, 0, 1)); ?></div>
            <span class="user-name"><?php echo htmlspecialchars($username); ?></span>
        </a>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>
</div>

<div class="main-container">

    <!-- Sidebar -->
    <div class="sidebar">
        <a href="dashboard.php"            class="sidebar-item">Dashboard</a>
        <a href="#"                         class="sidebar-item" id="studyMenu">Study</a>
        <div class="sidebar-submenu">
            <a href="class_timetable.php"    class="sidebar-item">Class Timetable</a>
            <a href="personal_study_plan.php" class="sidebar-item">Personal Plan</a>
        </div>
        <a href="timetable.php"            class="sidebar-item">AI Timetable</a>
        <a href="progress.php"             class="sidebar-item">Progress</a>
        <a href="manage_profile.php"       class="sidebar-item active">Manage Profile</a>
    </div>

    <!-- Content -->
    <div class="content">

        <!-- Page Header with Continue Button -->
        <div class="page-header">
            <div>
                <h1 class="page-title">Manage Profile</h1>
                <p class="page-subtitle">Update your personal information and study preferences</p>
            </div>
            <a href="class_timetable.php" class="btn-continue">
                Class Timetable
            </a>
        </div>

        <?php if ($error):   ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-circle-check"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <!-- Profile Form -->
        <form method="POST" action="manage_profile.php">
            <input type="hidden" name="action" value="update_profile">

            <div class="card">
                <div class="card-title">
                    Profile Information
                </div>
                <div class="profile-grid">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username"
                               value="<?php echo htmlspecialchars($username); ?>" required minlength="3">
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email"
                               value="<?php echo htmlspecialchars($email); ?>" required>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-title">
                    Wake Up & Sleep Time
                </div>
                <div class="time-grid">
                    <div class="form-group">
                        <label for="wake_up_time">Wake Up Time</label>
                        <input type="time" id="wake_up_time" name="wake_up_time"
                               value="<?php echo htmlspecialchars($wake_up_time); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="sleep_time">Sleep Time</label>
                        <input type="time" id="sleep_time" name="sleep_time"
                               value="<?php echo htmlspecialchars($sleep_time); ?>" required>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-title">
                    Preferred Study Time
                </div>
                <div class="checkbox-group">
                    <?php foreach (['Morning','Afternoon','Night'] as $period): ?>
                    <label class="checkbox-option">
                        <input type="checkbox" name="preferred_time[]" value="<?php echo $period; ?>"
                               <?php echo in_array($period, $preferred_times) ? 'checked' : ''; ?>>
                        <div class="checkbox-box"></div>
                        <span class="checkbox-label"><?php echo $period; ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-title">
                    Max Study Hours Per Day
                </div>
                <div class="slider-wrap">
                    <div class="slider-track">
                        <div class="slider-fill" id="sliderFill"></div>
                        <input type="range" class="slider" id="studyHoursSlider"
                               name="max_study_hours" min="1" max="12"
                               value="<?php echo $max_study_hours; ?>">
                    </div>
                    <div class="slider-labels">
                        <span class="slider-min-max">1 hr</span>
                        <span class="slider-current" id="hoursDisplay"><?php echo $max_study_hours; ?> hrs</span>
                        <span class="slider-min-max">12 hrs</span>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-save">
                
                Save Profile Settings
            </button>
        </form>

        <!-- Change Password Form -->
        <form method="POST" action="manage_profile.php" style="margin-top: 20px;">
            <input type="hidden" name="action" value="change_password">
            <div class="card">
                <div class="card-title">
                    Change Password
                </div>
                <div class="profile-grid">
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <div class="input-wrap">
                            <input type="password" id="current_password" name="current_password"
                                   required placeholder="Enter current password">
                            <button type="button" class="eye-btn" onclick="togglePwd('current_password', this)">
                                <i class="fa fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <div class="input-wrap">
                            <input type="password" id="new_password" name="new_password"
                                   required placeholder="Min 6 characters">
                            <button type="button" class="eye-btn" onclick="togglePwd('new_password', this)">
                                <i class="fa fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn-save">
                    Change Password
                </button>
            </div>
        </form>

        <div class="info-note">
            <i class="fa-solid fa-lightbulb"></i>
            <strong> Tip:</strong> Your wake-up time, sleep time, and preferred study time help the AI create a personalised study schedule just for you!
        </div>

    </div>
</div>

<script>
    // ── Slider ──────────────────────────────────────────
    const slider  = document.getElementById('studyHoursSlider');
    const fill    = document.getElementById('sliderFill');
    const display = document.getElementById('hoursDisplay');

    function updateSlider() {
        const min = Number(slider.min) || 1;
        const max = Number(slider.max) || 12;
        const val = Number(slider.value);
        const pct = ((val - min) / (max - min)) * 100;
        fill.style.width    = pct + '%';
        display.textContent = val + ' hrs';
        display.style.transform = 'scale(1.08)';
        setTimeout(() => display.style.transform = 'scale(1)', 120);
    }

    slider.addEventListener('input', updateSlider);
    updateSlider();

    // ── Password toggle ──────────────────────────────────
    function togglePwd(inputId, btn) {
        const input = document.getElementById(inputId);
        const icon  = btn.querySelector('i');
        const isHidden = input.type === 'password';
        input.type     = isHidden ? 'text' : 'password';
        icon.className = isHidden ? 'fa fa-eye-slash' : 'fa fa-eye';
    }

    // ── Sidebar study menu toggle ────────────────────────
    const studyMenu = document.getElementById('studyMenu');
    if (studyMenu) {
        studyMenu.addEventListener('click', function(e) {
            e.preventDefault();
            this.nextElementSibling.classList.toggle('open');
        });
    }
</script>
</body>
</html>