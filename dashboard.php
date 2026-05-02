<?php
session_start();
require_once 'config.php';

// Check if user is logged in
$is_logged_in = isset($_SESSION['student_id']);
$student_id = $is_logged_in ? $_SESSION['student_id'] : null;
$username = $is_logged_in ? $_SESSION['username'] : 'Guest';

$total_subjects = 0;
$weekly_hours = 0;
$today_plan_result = null;

// Only fetch data if logged in
if ($is_logged_in) {
    // Get student info
    $student_query = $conn->prepare("SELECT email FROM Student WHERE student_id = ?");
    $student_query->bind_param("i", $student_id);
    $student_query->execute();
    $student_result = $student_query->get_result();
    $student_data = $student_result->fetch_assoc();
    $student_query->close();

    // Get total subjects
    $total_subjects_query = $conn->prepare("SELECT COUNT(DISTINCT subject) as total FROM Class_Timetable WHERE student_id = ?");
    $total_subjects_query->bind_param("i", $student_id);
    $total_subjects_query->execute();
    $total_subjects_result = $total_subjects_query->get_result();
    $total_subjects_data = $total_subjects_result->fetch_assoc();
    $total_subjects = $total_subjects_data['total'] ?? 0;
    $total_subjects_query->close();

    // Get weekly study hours
    $weekly_hours_query = $conn->prepare("SELECT SUM(available_study_hours) as total_hours FROM Personal_Study_Plan WHERE student_id = ?");
    $weekly_hours_query->bind_param("i", $student_id);
    $weekly_hours_query->execute();
    $weekly_hours_result = $weekly_hours_query->get_result();
    $weekly_hours_data = $weekly_hours_result->fetch_assoc();
    $weekly_hours = $weekly_hours_data['total_hours'] ?? 0;
    $weekly_hours_query->close();

    // Get today's study plan
    $today = date('l');
    $today_plan_query = $conn->prepare("
        SELECT DISTINCT subject, study_time 
        FROM AI_Personal_Study_Timetable 
        WHERE student_id = ? AND day = ?
        LIMIT 5
    ");
    $today_plan_query->bind_param("is", $student_id, $today);
    $today_plan_query->execute();
    $today_plan_result = $today_plan_query->get_result();
    $today_plan_query->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Smart AI-Powered Study Planner</title>
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

        /* Animated background */
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
            font-size: 20px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
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
            text-decoration: none;
        }

        .user-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .auth-buttons {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .login-btn, .logout-btn {
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .login-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
        }

        .logout-btn {
            background: white;
            color: #333;
            border: 1px solid #e0e0e0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .logout-btn:hover {
            background: #f5f5f5;
            border-color: #667eea;
            color: #667eea;
            transform: translateY(-2px);
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
            width: 250px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
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

        .sidebar-section {
            margin-bottom: 30px;
        }

        .sidebar-section:last-child {
            margin-bottom: 0;
        }

        .sidebar-title {
            font-size: 11px;
            font-weight: 700;
            color: #667eea;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            margin-bottom: 15px;
            display: block;
        }

        .sidebar-item {
            display: block;
            padding: 12px 15px;
            margin-bottom: 8px;
            border-radius: 10px;
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
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.2) 0%, rgba(118, 75, 162, 0.2) 100%);
            color: #667eea;
            font-weight: 600;
            border-left: 4px solid #667eea;
            padding-left: 11px;
        }

        .sidebar-item.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            color: #999;
        }

        .sidebar-item.disabled:hover {
            color: #999;
            transform: none;
        }

        .sidebar-submenu {
            margin-left: 0;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }

        .sidebar-submenu.open {
            max-height: 200px;
        }

        .sidebar-submenu .sidebar-item {
            font-size: 13px;
            margin-left: 15px;
            padding-left: 12px;
        }

        .arrow {
            display: inline-block;
            margin-left: 8px;
            transition: transform 0.3s ease;
        }

        .sidebar-item.expand-open .arrow {
            transform: rotate(90deg);
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

        .welcome-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
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

        .welcome-text {
            font-size: 18px;
            color: #666;
            font-weight: 500;
        }

        .welcome-name {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 700;
        }

        .guest-notice {
            background: linear-gradient(135deg, rgba(255, 193, 7, 0.1) 0%, rgba(255, 152, 0, 0.1) 100%);
            border: 1px solid rgba(255, 193, 7, 0.3);
            border-radius: 12px;
            padding: 15px;
            margin-top: 15px;
            font-size: 13px;
            color: #f57c00;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1),
                        0 0 40px rgba(102, 126, 234, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
            animation: slideUp 0.6s ease-out;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, rgba(102, 126, 234, 0.1) 0%, transparent 70%);
            transition: all 0.3s ease;
        }

        .stat-card:hover::before {
            top: -25%;
            right: -25%;
        }

        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }

        .stat-card.locked {
            opacity: 0.7;
            cursor: not-allowed;
            position: relative;
        }

        .stat-card.locked::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 5;
        }

        .lock-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 10;
            text-align: center;
            width: 100%;
        }

        .lock-icon {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .lock-text {
            font-size: 12px;
            color: #667eea;
            font-weight: 600;
        }

        .stat-content {
            position: relative;
            z-index: 2;
            text-align: center;
        }

        .stat-icon {
            font-size: 40px;
            margin-bottom: 15px;
            display: inline-block;
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }

        .stat-label {
            font-size: 13px;
            color: #999;
            font-weight: 700;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        .stat-value {
            font-size: 56px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-value.placeholder {
            font-size: 24px;
            color: #999;
        }

        /* Today's Study Plan */
        .plan-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1),
                        0 0 40px rgba(102, 126, 234, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: slideUp 0.6s ease-out 0.3s forwards;
            opacity: 0;
            position: relative;
        }

        .plan-card.locked {
            opacity: 1;
        }

        .plan-card.locked::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.7);
            border-radius: 20px;
            z-index: 5;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .plan-header {
            font-size: 18px;
            font-weight: 700;
            color: #333;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
            position: relative;
            z-index: 1;
        }

        .plan-header::before {
            content: '📅';
            font-size: 24px;
        }

        .plan-list {
            list-style: none;
            position: relative;
            z-index: 1;
        }

        .plan-item {
            padding: 18px 0;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
            animation: slideInItem 0.6s ease-out forwards;
            opacity: 0;
        }

        .plan-item:nth-child(1) { animation-delay: 0.4s; }
        .plan-item:nth-child(2) { animation-delay: 0.5s; }
        .plan-item:nth-child(3) { animation-delay: 0.6s; }
        .plan-item:nth-child(4) { animation-delay: 0.7s; }
        .plan-item:nth-child(5) { animation-delay: 0.8s; }

        @keyframes slideInItem {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .plan-item:last-child {
            border-bottom: none;
        }

        .plan-item:hover {
            background-color: #f9f9f9;
            padding-left: 10px;
            border-radius: 8px;
        }

        .plan-item-subject {
            font-size: 15px;
            font-weight: 600;
            color: #333;
        }

        .plan-item-time {
            font-size: 13px;
            color: white;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 6px 16px;
            border-radius: 20px;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .plan-empty {
            text-align: center;
            padding: 40px;
            color: #999;
            font-size: 15px;
        }

        .plan-locked-message {
            text-align: center;
            padding: 40px;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 10;
            width: 80%;
        }

        .lock-icon-large {
            font-size: 48px;
            margin-bottom: 15px;
        }

        .lock-message-title {
            font-size: 16px;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 10px;
        }

        .lock-message-text {
            font-size: 14px;
            color: #666;
            margin-bottom: 15px;
        }

        .login-link {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .login-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .main-container {
                flex-direction: column;
                padding: 20px;
                gap: 20px;
            }

            .sidebar {
                width: 100%;
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                height: auto;
            }

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .auth-buttons {
                width: 100%;
                justify-content: center;
            }

            .sidebar {
                grid-template-columns: repeat(2, 1fr);
                padding: 20px;
            }

            .sidebar-section {
                margin-bottom: 20px;
            }

            .sidebar-submenu {
                max-height: none;
                margin-top: 10px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .stat-value {
                font-size: 42px;
            }

            .welcome-card {
                padding: 20px;
            }

            .plan-card {
                padding: 20px;
            }

            .main-container {
                padding: 15px;
            }

            .guest-notice {
                font-size: 12px;
            }

            .user-profile {
                padding: 6px 10px;
            }

            .user-name {
                font-size: 12px;
            }
        }

        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="logo">📚 Smart AI-Powered Study Planner</div>
        <div class="user-info">
            <?php if ($is_logged_in): ?>
                <a href="manage_profile.php" class="user-profile" title="Manage Profile">
                    <div class="user-avatar"><?php echo strtoupper(substr($username, 0, 1)); ?></div>
                    <span class="user-name"><?php echo htmlspecialchars($username); ?></span>
                </a>
                <span class="user-badge">Logged In</span>
                <a href="logout.php" class="logout-btn">🚪 Logout</a>
            <?php else: ?>
                <span class="user-name">👤 Guest User</span>
                <span class="user-badge">Guest</span>
                <div class="auth-buttons">
                    <a href="login.php" class="login-btn">🔐 Login</a>
                    <a href="register.php" class="login-btn">✍️ Sign Up</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Main Container -->
    <div class="main-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-section">
                <span class="sidebar-title">Main</span>
                <a href="dashboard.php" class="sidebar-item active">
                    📊 Dashboard
                </a>
            </div>

            <div class="sidebar-section">
                <span class="sidebar-title">Study</span>
                <a href="#" class="sidebar-item <?php echo !$is_logged_in ? 'disabled' : ''; ?>" id="studyMenu" <?php echo !$is_logged_in ? 'onclick="return false;"' : ''; ?>>
                    📖 Study <span class="arrow">›</span>
                </a>
                <div class="sidebar-submenu" id="studySubmenu">
                    <a href="<?php echo $is_logged_in ? 'class_timetable.php' : 'javascript:void(0)'; ?>" class="sidebar-item <?php echo !$is_logged_in ? 'disabled' : ''; ?>" <?php echo !$is_logged_in ? 'onclick="showLoginPrompt(event)"' : ''; ?>>
                        📅 Class Timetable
                    </a>
                    <a href="<?php echo $is_logged_in ? 'personal_study_plan.php' : 'javascript:void(0)'; ?>" class="sidebar-item <?php echo !$is_logged_in ? 'disabled' : ''; ?>" <?php echo !$is_logged_in ? 'onclick="showLoginPrompt(event)"' : ''; ?>>
                        📝 Personal Plan
                    </a>
                </div>
            </div>

            <div class="sidebar-section">
                <span class="sidebar-title">Tracking</span>
                <a href="<?php echo $is_logged_in ? 'timetable.php' : 'javascript:void(0)'; ?>" class="sidebar-item <?php echo !$is_logged_in ? 'disabled' : ''; ?>" <?php echo !$is_logged_in ? 'onclick="showLoginPrompt(event)"' : ''; ?>>
                    ⏰ Timetable
                </a>
                <a href="<?php echo $is_logged_in ? 'progress.php' : 'javascript:void(0)'; ?>" class="sidebar-item <?php echo !$is_logged_in ? 'disabled' : ''; ?>" <?php echo !$is_logged_in ? 'onclick="showLoginPrompt(event)"' : ''; ?>>
                    📈 Progress
                </a>
            </div>

            <?php if ($is_logged_in): ?>
                <div class="sidebar-section">
                    <span class="sidebar-title">Settings</span>
                    <a href="manage_profile.php" class="sidebar-item">
                        ⚙️ Manage Profile
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Content Area -->
        <div class="content">
            <!-- Welcome Card -->
            <div class="welcome-card">
                <p class="welcome-text">
                    <?php if ($is_logged_in): ?>
                        Welcome back, <span class="welcome-name"><?php echo htmlspecialchars($username); ?></span>! 👋
                    <?php else: ?>
                        Welcome to <span class="welcome-name">Smart AI-Powered Study Planner</span>! 👋
                    <?php endif; ?>
                </p>
                <?php if (!$is_logged_in): ?>
                    <div class="guest-notice">
                        <span>ℹ️</span>
                        <span>You are browsing as a guest. <a href="login.php" style="color: #f57c00; font-weight: 700; text-decoration: underline;">Login</a> to access all features and view your personalized data.</span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card <?php echo !$is_logged_in ? 'locked' : ''; ?>">
                    <?php if (!$is_logged_in): ?>
                        <div class="lock-overlay" onclick="showLoginPrompt(event)">
                            <div class="lock-icon">🔒</div>
                            <div class="lock-text">Login to view</div>
                        </div>
                    <?php endif; ?>
                    <div class="stat-content">
                        <div class="stat-icon">📚</div>
                        <div class="stat-label">Total Subjects</div>
                        <div class="stat-value <?php echo !$is_logged_in ? 'placeholder' : ''; ?>">
                            <?php echo $is_logged_in ? $total_subjects : '-'; ?>
                        </div>
                    </div>
                </div>

                <div class="stat-card <?php echo !$is_logged_in ? 'locked' : ''; ?>">
                    <?php if (!$is_logged_in): ?>
                        <div class="lock-overlay" onclick="showLoginPrompt(event)">
                            <div class="lock-icon">🔒</div>
                            <div class="lock-text">Login to view</div>
                        </div>
                    <?php endif; ?>
                    <div class="stat-content">
                        <div class="stat-icon">⏱️</div>
                        <div class="stat-label">Weekly Study Hours</div>
                        <div class="stat-value <?php echo !$is_logged_in ? 'placeholder' : ''; ?>">
                            <?php echo $is_logged_in ? number_format($weekly_hours, 1) : '-'; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Today's Study Plan -->
            <div class="plan-card <?php echo !$is_logged_in ? 'locked' : ''; ?>">
                <?php if (!$is_logged_in): ?>
                    <div class="plan-locked-message">
                        <div class="lock-icon-large">🔒</div>
                        <div class="lock-message-title">Login Required</div>
                        <div class="lock-message-text">Login to see your personalized study plan for today</div>
                        <a href="login.php" class="login-link">🔐 Login Now</a>
                    </div>
                <?php endif; ?>
                <div class="plan-header">Today's Study Plan</div>
                <ul class="plan-list">
                    <?php
                    if ($is_logged_in && $today_plan_result) {
                        $has_plan = false;
                        while ($plan = $today_plan_result->fetch_assoc()) {
                            $has_plan = true;
                            echo '<li class="plan-item">';
                            echo '<span class="plan-item-subject">' . htmlspecialchars($plan['subject']) . '</span>';
                            echo '<span class="plan-item-time">' . htmlspecialchars($plan['study_time']) . ' hrs</span>';
                            echo '</li>';
                        }
                        if (!$has_plan) {
                            echo '<div class="plan-empty">✨ No study plan for today. Start planning your studies! 🎯</div>';
                        }
                    }
                    ?>
                </ul>
            </div>
        </div>
    </div>

    <!-- Login Modal -->
    <div id="loginModal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.7); animation: fadeIn 0.3s ease-out;">
        <div style="background-color: white; margin: 5% auto; padding: 30px; border-radius: 20px; width: 90%; max-width: 400px; text-align: center; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3); animation: slideUp 0.4s ease-out;">
            <div style="font-size: 48px; margin-bottom: 20px;">🔐</div>
            <h2 style="font-size: 24px; font-weight: 700; color: #333; margin-bottom: 10px;">Login Required</h2>
            <p style="font-size: 14px; color: #666; margin-bottom: 25px; line-height: 1.6;">
                You need to log in to access this feature and view your personalized study data.
            </p>
            <div style="display: flex; gap: 10px; justify-content: center;">
                <a href="login.php" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 25px; border-radius: 8px; text-decoration: none; font-weight: 600; transition: all 0.3s ease; display: inline-block;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(102, 126, 234, 0.5)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 15px rgba(102, 126, 234, 0.3)'">
                    Login
                </a>
                <a href="register.php" style="background: #f0f0f0; color: #333; padding: 12px 25px; border-radius: 8px; text-decoration: none; font-weight: 600; transition: all 0.3s ease; display: inline-block;" onmouseover="this.style.backgroundColor='#e0e0e0'" onmouseout="this.style.backgroundColor='#f0f0f0'">
                    Sign Up
                </a>
                <button onclick="closeLoginModal()" style="background: white; color: #333; padding: 12px 25px; border-radius: 8px; border: 1px solid #e0e0e0; font-weight: 600; cursor: pointer; transition: all 0.3s ease;" onmouseover="this.style.backgroundColor='#f5f5f5'" onmouseout="this.style.backgroundColor='white'">
                    Close
                </button>
            </div>
        </div>
    </div>

    <style>
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
    </style>

    <script>
        // Study Menu Toggle
        const studyMenu = document.getElementById('studyMenu');
        const studySubmenu = document.getElementById('studySubmenu');

        if (studyMenu && !studyMenu.classList.contains('disabled')) {
            studyMenu.addEventListener('click', function(e) {
                e.preventDefault();
                studySubmenu.classList.toggle('open');
                this.classList.toggle('expand-open');
            });
        }

        // Show Login Prompt
        function showLoginPrompt(event) {
            if (event) {
                event.preventDefault();
            }
            document.getElementById('loginModal').style.display = 'block';
        }

        // Close Login Modal
        function closeLoginModal() {
            document.getElementById('loginModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('loginModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }

        // Sidebar item active state
        const sidebarItems = document.querySelectorAll('.sidebar-item:not(.disabled)');
        sidebarItems.forEach(item => {
            item.addEventListener('click', function(e) {
                if (this.id !== 'studyMenu') {
                    sidebarItems.forEach(i => i.classList.remove('active'));
                    this.classList.add('active');
                }
            });
        });

        // Add hover effect to plan items
        document.querySelectorAll('.plan-item').forEach(item => {
            item.addEventListener('mouseenter', function() {
                this.style.transform = 'translateX(5px)';
            });
            item.addEventListener('mouseleave', function() {
                this.style.transform = 'translateX(0)';
            });
        });
    </script>
</body>
</html>