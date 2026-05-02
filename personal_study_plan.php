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

// Handle Add/Update Subject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'add_subject') {
        $subject = trim($_POST['subject']);
        $available_study_hours = $_POST['available_study_hours'] ?? null;
        $study_day = $_POST['study_day'] ?? null;

        if (empty($subject)) {
            $error = 'Subject name is required!';
        } elseif (empty($available_study_hours) || empty($study_day)) {
            $error = 'Please fill in all fields!';
        } else {
            if (!is_numeric($available_study_hours) || $available_study_hours <= 0) {
                $error = 'Study hours must be a positive number!';
            } else {
                $insert = $conn->prepare("INSERT INTO Personal_Study_Plan (student_id, subject, available_study_hours, day) VALUES (?, ?, ?, ?)");
                $insert->bind_param("isds", $student_id, $subject, $available_study_hours, $study_day);
                
                if ($insert->execute()) {
                    $success = 'Subject added successfully!';
                } else {
                    $error = 'Failed to add subject!';
                }
                $insert->close();
            }
        }
    } elseif ($action === 'delete_subject') {
        $plan_id = $_POST['plan_id'];
        $delete = $conn->prepare("DELETE FROM Personal_Study_Plan WHERE plan_id = ? AND student_id = ?");
        $delete->bind_param("ii", $plan_id, $student_id);
        if ($delete->execute()) {
            $success = 'Subject deleted successfully!';
        } else {
            $error = 'Failed to delete subject!';
        }
        $delete->close();
    } elseif ($action === 'update_subject') {
        $plan_id = $_POST['plan_id'];
        $subject = trim($_POST['subject']);
        $available_study_hours = $_POST['available_study_hours'];
        $study_day = $_POST['study_day'];

        if (empty($subject) || empty($available_study_hours) || empty($study_day)) {
            $error = 'All fields are required!';
        } else {
            if (!is_numeric($available_study_hours) || $available_study_hours <= 0) {
                $error = 'Study hours must be a positive number!';
            } else {
                // Update WITH day column
                $update = $conn->prepare("UPDATE Personal_Study_Plan SET subject = ?, available_study_hours = ?, day = ? WHERE plan_id = ? AND student_id = ?");
                $update->bind_param("sdsii", $subject, $available_study_hours, $study_day, $plan_id, $student_id);
                if ($update->execute()) {
                    $success = 'Subject updated successfully!';
                } else {
                    $error = 'Failed to update subject!';
                }
                $update->close();
            }
        }
    }
}

// Get all study plans for this student - WITH day column
$plans_query = $conn->prepare("SELECT plan_id, subject, available_study_hours, day FROM Personal_Study_Plan WHERE student_id = ? ORDER BY day");
$plans_query->bind_param("i", $student_id);
$plans_query->execute();
$plans_result = $plans_query->get_result();
$plans_query->close();

$plans = array();
while ($plan = $plans_result->fetch_assoc()) {
    $plans[] = $plan;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Personal Study Plan - Smart AI-Powered Study Planner</title>
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
        }

        .user-name {
            font-size: 14px;
            font-weight: 600;
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

        .page-header {
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

        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: #333;
            margin-bottom: 10px;
        }

        .page-subtitle {
            font-size: 14px;
            color: #999;
            font-weight: 500;
        }

        .alert {
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
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

        /* Subject Card */
        .subject-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1),
                        0 0 40px rgba(102, 126, 234, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: slideUp 0.6s ease-out forwards;
            opacity: 0;
            transition: all 0.3s ease;
        }

        .subject-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 70px rgba(0, 0, 0, 0.15),
                        0 0 50px rgba(102, 126, 234, 0.2);
        }

        .subject-card.edit-mode {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
            border: 2px solid #667eea;
        }

        .subject-header {
            margin-bottom: 20px;
        }

        .subject-number {
            font-size: 12px;
            font-weight: 700;
            color: #667eea;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
            display: block;
        }

        .subject-name {
            font-size: 18px;
            font-weight: 700;
            color: #333;
            margin-bottom: 15px;
        }

        /* Form Fields */
        .form-group {
            margin-bottom: 15px;
            animation: fadeIn 0.6s ease-out forwards;
            opacity: 0;
        }

        .form-group:nth-child(1) { animation-delay: 0.1s; }
        .form-group:nth-child(2) { animation-delay: 0.2s; }
        .form-group:nth-child(3) { animation-delay: 0.3s; }

        @keyframes fadeIn {
            to {
                opacity: 1;
            }
        }

        label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: #333;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        input,
        select {
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

        input::placeholder {
            color: #bbb;
        }

        input:focus,
        select:focus {
            outline: none;
            border-color: #667eea;
            background-color: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            transform: translateY(-2px);
        }

        input:disabled,
        select:disabled {
            background-color: #f0f0f0;
            color: #999;
            cursor: not-allowed;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        /* Buttons */
        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .action-btn {
            padding: 10px 18px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            flex: 1;
            text-align: center;
        }

        .btn-edit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .btn-edit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(102, 126, 234, 0.5);
        }

        .btn-save {
            background: #e8f5e9;
            color: #388e3c;
            border: 1px solid #388e3c;
        }

        .btn-save:hover {
            background: #388e3c;
            color: white;
        }

        .btn-cancel {
            background: #f5f5f5;
            color: #666;
            border: 1px solid #e0e0e0;
        }

        .btn-cancel:hover {
            background: #e0e0e0;
        }

        .btn-delete {
            background: #ffebee;
            color: #d32f2f;
            border: 1px solid #d32f2f;
        }

        .btn-delete:hover {
            background: #d32f2f;
            color: white;
        }

        /* Add Subject Form */
        .add-subject-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1),
                        0 0 40px rgba(102, 126, 234, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: slideUp 0.6s ease-out 0.2s forwards;
            opacity: 0;
        }

        .form-section-title {
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

        .form-section-title::before {
            content: '📝';
            font-size: 20px;
        }

        .add-form-group {
            margin-bottom: 20px;
            animation: fadeIn 0.6s ease-out forwards;
            opacity: 0;
        }

        .add-form-group:nth-child(1) { animation-delay: 0.3s; }
        .add-form-group:nth-child(2) { animation-delay: 0.4s; }
        .add-form-group:nth-child(3) { animation-delay: 0.5s; }

        .add-form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        /* Button Group */
        .add-button-group {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 25px;
            animation: slideUp 0.6s ease-out 0.6s forwards;
            opacity: 0;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.5);
        }

        .empty-state {
            text-align: center;
            padding: 60px 30px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
        }

        .empty-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        .empty-title {
            font-size: 20px;
            font-weight: 700;
            color: #333;
            margin-bottom: 10px;
        }

        .empty-text {
            font-size: 14px;
            color: #999;
            margin-bottom: 25px;
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

            .add-form-row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 15px;
            }

            .page-header {
                padding: 20px;
            }

            .page-title {
                font-size: 22px;
            }

            .button-group {
                flex-direction: column;
            }

            .action-btn {
                width: 100%;
            }

            .add-button-group {
                flex-direction: column;
            }

            .btn-primary {
                width: 100%;
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
        <a href="manage_profile.php" class="user-profile" title="Manage Profile">
            <div class="user-avatar"><?php echo strtoupper(substr($username, 0, 1)); ?></div>
            <span class="user-name"><?php echo htmlspecialchars($username); ?></span>
        </a>
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
                <a href="personal_study_plan.php" class="sidebar-item active">
                    📝 Personal Plan
                </a>
            </div>
            <a href="timetable.php" class="sidebar-item">
                ⏰ Timetable
            </a>
            <a href="progress.php" class="sidebar-item">
                📈 Progress
            </a>
            <a href="manage_profile.php" class="sidebar-item">
                ⚙️ Manage Profile
            </a>
        </div>

        <!-- Content Area -->
        <div class="content">
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">📝 Personal Study Plan</h1>
                <p class="page-subtitle">Create and manage your personal study schedule</p>
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

            <!-- Display Existing Plans -->
            <?php if (!empty($plans)): ?>
                <?php 
                $delay = 0;
                foreach ($plans as $index => $plan): 
                ?>
                    <div class="subject-card" id="plan-<?php echo $plan['plan_id']; ?>" style="animation-delay: <?php echo $delay * 0.1; ?>s;">
                        <div class="subject-header">
                            <span class="subject-number">Subject <?php echo $index + 1; ?></span>
                            <div class="subject-name" id="subject-display-<?php echo $plan['plan_id']; ?>">
                                <?php echo htmlspecialchars($plan['subject']); ?>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Study Hours</label>
                                <input 
                                    type="number" 
                                    id="hours-<?php echo $plan['plan_id']; ?>" 
                                    value="<?php echo htmlspecialchars($plan['available_study_hours']); ?>" 
                                    step="0.5"
                                    min="0.5"
                                    disabled
                                >
                            </div>
                            <div class="form-group">
                                <label>Day</label>
                                <select id="day-<?php echo $plan['plan_id']; ?>" disabled>
                                    <option value="Monday" <?php echo $plan['day'] == 'Monday' ? 'selected' : ''; ?>>Monday</option>
                                    <option value="Tuesday" <?php echo $plan['day'] == 'Tuesday' ? 'selected' : ''; ?>>Tuesday</option>
                                    <option value="Wednesday" <?php echo $plan['day'] == 'Wednesday' ? 'selected' : ''; ?>>Wednesday</option>
                                    <option value="Thursday" <?php echo $plan['day'] == 'Thursday' ? 'selected' : ''; ?>>Thursday</option>
                                    <option value="Friday" <?php echo $plan['day'] == 'Friday' ? 'selected' : ''; ?>>Friday</option>
                                    <option value="Saturday" <?php echo $plan['day'] == 'Saturday' ? 'selected' : ''; ?>>Saturday</option>
                                    <option value="Sunday" <?php echo $plan['day'] == 'Sunday' ? 'selected' : ''; ?>>Sunday</option>
                                </select>
                            </div>
                        </div>

                        <div class="button-group">
                            <button class="action-btn btn-edit" onclick="editPlan(<?php echo $plan['plan_id']; ?>)">✏️ Edit</button>
                            <button class="action-btn btn-save" id="save-btn-<?php echo $plan['plan_id']; ?>" onclick="savePlan(<?php echo $plan['plan_id']; ?>)" style="display: none;">💾 Save</button>
                            <button class="action-btn btn-cancel" id="cancel-btn-<?php echo $plan['plan_id']; ?>" onclick="cancelEdit(<?php echo $plan['plan_id']; ?>)" style="display: none;">❌ Cancel</button>
                            <form method="POST" style="display: inline; flex: 1;" onsubmit="return confirm('Delete this subject?');">
                                <input type="hidden" name="action" value="delete_subject">
                                <input type="hidden" name="plan_id" value="<?php echo $plan['plan_id']; ?>">
                                <button type="submit" class="action-btn btn-delete">🗑️ Delete</button>
                            </form>
                        </div>
                    </div>
                    <?php $delay++; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">📚</div>
                    <div class="empty-title">No Study Plans Yet</div>
                    <div class="empty-text">Create your first personal study plan to get started</div>
                </div>
            <?php endif; ?>

            <!-- Add Subject Form -->
            <div class="add-subject-card">
                <div class="form-section-title">Add New Study Subject</div>

                <form method="POST" action="personal_study_plan.php" novalidate>
                    <input type="hidden" name="action" value="add_subject">

                    <div class="add-form-group">
                        <label for="subject">Subject Name</label>
                        <input 
                            type="text" 
                            id="subject" 
                            name="subject" 
                            placeholder="Enter subject name (e.g., Mathematics, History)" 
                            required
                        >
                    </div>

                    <div class="add-form-row">
                        <div class="add-form-group">
                            <label for="available_study_hours">Study Hours</label>
                            <input 
                                type="number" 
                                id="available_study_hours" 
                                name="available_study_hours" 
                                placeholder="Enter hours (e.g., 2.5)" 
                                step="0.5"
                                min="0.5"
                                required
                            >
                        </div>
                        <div class="add-form-group">
                            <label for="study_day">Select Day</label>
                            <select id="study_day" name="study_day" required>
                                <option value="">Choose a day</option>
                                <option value="Monday">Monday</option>
                                <option value="Tuesday">Tuesday</option>
                                <option value="Wednesday">Wednesday</option>
                                <option value="Thursday">Thursday</option>
                                <option value="Friday">Friday</option>
                                <option value="Saturday">Saturday</option>
                                <option value="Sunday">Sunday</option>
                            </select>
                        </div>
                    </div>

                    <div class="add-button-group">
                        <button type="submit" class="btn-primary">Add Subject</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function editPlan(planId) {
            // Enable inputs
            const hoursInput = document.getElementById('hours-' + planId);
            const daySelect = document.getElementById('day-' + planId);
            
            hoursInput.disabled = false;
            daySelect.disabled = false;
            
            // Show save/cancel buttons
            document.getElementById('save-btn-' + planId).style.display = 'flex';
            document.getElementById('cancel-btn-' + planId).style.display = 'flex';
            
            // Hide edit button
            const editBtn = event.target.closest('.btn-edit');
            editBtn.style.display = 'none';
            
            // Highlight the card
            document.getElementById('plan-' + planId).classList.add('edit-mode');
            
            // Focus on first input
            hoursInput.focus();
        }

        function cancelEdit(planId) {
            const hoursInput = document.getElementById('hours-' + planId);
            const daySelect = document.getElementById('day-' + planId);
            
            hoursInput.disabled = true;
            daySelect.disabled = true;
            
            document.getElementById('save-btn-' + planId).style.display = 'none';
            document.getElementById('cancel-btn-' + planId).style.display = 'none';
            
            // Find the edit button and show it
            const card = document.getElementById('plan-' + planId);
            const editBtn = card.querySelector('.btn-edit');
            editBtn.style.display = 'flex';
            
            // Remove highlight
            document.getElementById('plan-' + planId).classList.remove('edit-mode');
        }

        function savePlan(planId) {
            const hoursInput = document.getElementById('hours-' + planId);
            const daySelect = document.getElementById('day-' + planId);
            
            const hours = hoursInput.value;
            const day = daySelect.value;
            
            if (!hours || !day) {
                alert('Please fill in all fields!');
                return;
            }
            
            if (hours <= 0) {
                alert('Study hours must be greater than 0!');
                return;
            }
            
            // Create a form and submit it
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'personal_study_plan.php';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'update_subject';
            form.appendChild(actionInput);
            
            const planIdInput = document.createElement('input');
            planIdInput.type = 'hidden';
            planIdInput.name = 'plan_id';
            planIdInput.value = planId;
            form.appendChild(planIdInput);
            
            const subjectInput = document.createElement('input');
            subjectInput.type = 'hidden';
            subjectInput.name = 'subject';
            subjectInput.value = document.getElementById('subject-display-' + planId).textContent;
            form.appendChild(subjectInput);
            
            const hoursInputField = document.createElement('input');
            hoursInputField.type = 'hidden';
            hoursInputField.name = 'available_study_hours';
            hoursInputField.value = hours;
            form.appendChild(hoursInputField);
            
            const dayInputField = document.createElement('input');
            dayInputField.type = 'hidden';
            dayInputField.name = 'study_day';
            dayInputField.value = day;
            form.appendChild(dayInputField);
            
            document.body.appendChild(form);
            form.submit();
        }

        // Study Menu Toggle
        const studyMenu = document.getElementById('studyMenu');
        studyMenu.addEventListener('click', function(e) {
            e.preventDefault();
            const submenu = this.nextElementSibling;
            submenu.classList.toggle('open');
            this.classList.toggle('expand-open');
        });
    </script>
</body>
</html>