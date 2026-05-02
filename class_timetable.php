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
$edit_mode = false;
$edit_subject = null;

// Handle Add/Update Subject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'add_subject') {
        $subject = trim($_POST['subject']);
        $lecture_start = $_POST['lecture_start'] ?? null;
        $lecture_end = $_POST['lecture_end'] ?? null;
        $lecture_day = $_POST['lecture_day'] ?? null;
        $tutorial_start = $_POST['tutorial_start'] ?? null;
        $tutorial_end = $_POST['tutorial_end'] ?? null;
        $tutorial_day = $_POST['tutorial_day'] ?? null;

        if (empty($subject)) {
            $error = 'Subject name is required!';
        } else {
            // Insert lecture class
            if (!empty($lecture_start) && !empty($lecture_end) && !empty($lecture_day)) {
                $insert_lecture = $conn->prepare("INSERT INTO Class_Timetable (student_id, subject, start_time, end_time, day) VALUES (?, ?, ?, ?, ?)");
                $insert_lecture->bind_param("issss", $student_id, $subject, $lecture_start, $lecture_end, $lecture_day);
                $insert_lecture->execute();
                $insert_lecture->close();
            }

            // Insert tutorial class
            if (!empty($tutorial_start) && !empty($tutorial_end) && !empty($tutorial_day)) {
                $insert_tutorial = $conn->prepare("INSERT INTO Class_Timetable (student_id, subject, start_time, end_time, day) VALUES (?, ?, ?, ?, ?)");
                $insert_tutorial->bind_param("issss", $student_id, $subject, $tutorial_start, $tutorial_end, $tutorial_day);
                $insert_tutorial->execute();
                $insert_tutorial->close();
            }

            $success = 'Subject added successfully!';
        }
    } elseif ($action === 'delete_subject') {
        $class_id = $_POST['class_id'];
        $delete = $conn->prepare("DELETE FROM Class_Timetable WHERE class_id = ? AND student_id = ?");
        $delete->bind_param("ii", $class_id, $student_id);
        if ($delete->execute()) {
            $success = 'Class deleted successfully!';
        } else {
            $error = 'Failed to delete class!';
        }
        $delete->close();
    } elseif ($action === 'update_subject') {
        $class_id = $_POST['class_id'];
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $day = $_POST['day'];

        if (empty($start_time) || empty($end_time) || empty($day)) {
            $error = 'All fields are required!';
        } else {
            // Validate time
            if ($start_time >= $end_time) {
                $error = 'End time must be after start time!';
            } else {
                $update = $conn->prepare("UPDATE Class_Timetable SET start_time = ?, end_time = ?, day = ? WHERE class_id = ? AND student_id = ?");
                $update->bind_param("sssii", $start_time, $end_time, $day, $class_id, $student_id);
                if ($update->execute()) {
                    $success = 'Class updated successfully!';
                } else {
                    $error = 'Failed to update class!';
                }
                $update->close();
            }
        }
    }
}

// Get all classes for this student
$classes_query = $conn->prepare("SELECT class_id, subject, start_time, end_time, day FROM Class_Timetable WHERE student_id = ? ORDER BY day, start_time");
$classes_query->bind_param("i", $student_id);
$classes_query->execute();
$classes_result = $classes_query->get_result();
$classes_query->close();

// Group classes by subject
$subjects_classes = array();
while ($class = $classes_result->fetch_assoc()) {
    if (!isset($subjects_classes[$class['subject']])) {
        $subjects_classes[$class['subject']] = array();
    }
    $subjects_classes[$class['subject']][] = $class;
}

// Check if edit mode
if (isset($_GET['edit'])) {
    $edit_subject = $_GET['edit'];
    $edit_mode = true;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Timetable - Smart AI-Powered Study Planner</title>
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

        .subject-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .subject-name {
            font-size: 18px;
            font-weight: 700;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .subject-icon {
            font-size: 24px;
        }

        .subject-actions {
            display: flex;
            gap: 10px;
        }

        .action-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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

        .btn-delete {
            background: #ffebee;
            color: #d32f2f;
            border: 1px solid #d32f2f;
        }

        .btn-delete:hover {
            background: #d32f2f;
            color: white;
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

        /* Class Entry */
        .class-entry {
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 12px;
            border: 1px solid #e0e0e0;
            transition: all 0.3s ease;
        }

        .class-entry.edit-mode {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
            border: 2px solid #667eea;
        }

        .class-label {
            font-size: 12px;
            font-weight: 700;
            color: #667eea;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
            display: block;
        }

        .class-times {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 12px;
        }

        .time-input {
            display: flex;
            flex-direction: column;
        }

        .time-input label {
            font-size: 12px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .time-input input,
        .time-input select {
            padding: 8px 12px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 13px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            transition: all 0.3s ease;
            background-color: white;
            color: #333;
        }

        .time-input input:disabled,
        .time-input select:disabled {
            background-color: #f0f0f0;
            color: #999;
            cursor: not-allowed;
        }

        .time-input input:focus,
        .time-input select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .class-entry-actions {
            display: flex;
            gap: 8px;
            margin-top: 12px;
        }

        .class-action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 11px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-transform: uppercase;
        }

        .btn-save-class {
            background: #e8f5e9;
            color: #388e3c;
            border: 1px solid #388e3c;
            flex: 1;
        }

        .btn-save-class:hover {
            background: #388e3c;
            color: white;
        }

        .btn-cancel-edit {
            background: #f5f5f5;
            color: #666;
            border: 1px solid #e0e0e0;
            flex: 1;
        }

        .btn-cancel-edit:hover {
            background: #e0e0e0;
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

        .form-group {
            margin-bottom: 20px;
            animation: fadeIn 0.6s ease-out forwards;
            opacity: 0;
        }

        .form-group:nth-child(1) { animation-delay: 0.3s; }
        .form-group:nth-child(2) { animation-delay: 0.4s; }
        .form-group:nth-child(3) { animation-delay: 0.5s; }
        .form-group:nth-child(4) { animation-delay: 0.6s; }
        .form-group:nth-child(5) { animation-delay: 0.7s; }

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

        input[type="text"],
        input[type="time"],
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

        input[type="text"]::placeholder {
            color: #bbb;
        }

        input[type="text"]:focus,
        input[type="time"]:focus,
        select:focus {
            outline: none;
            border-color: #667eea;
            background-color: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            transform: translateY(-2px);
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }

        /* Button Group */
        .button-group {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 25px;
            animation: slideUp 0.6s ease-out 0.8s forwards;
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

        .btn-secondary {
            background: white;
            color: #667eea;
            padding: 12px 30px;
            border: 2px solid #667eea;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-secondary:hover {
            background: #667eea;
            color: white;
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

            .class-times {
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

            .subject-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .subject-actions {
                width: 100%;
            }

            .action-btn {
                flex: 1;
                text-align: center;
            }

            .button-group {
                flex-direction: column;
            }

            .btn-primary,
            .btn-secondary {
                width: 100%;
            }

            .class-entry-actions {
                flex-direction: column;
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
                <a href="class_timetable.php" class="sidebar-item active">
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
            <a href="manage_profile.php" class="sidebar-item">
                ⚙️ Manage Profile
            </a>
        </div>

        <!-- Content Area -->
        <div class="content">
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">📅 Class Timetable</h1>
                <p class="page-subtitle">Manage your class schedule including lectures and tutorials</p>
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

            <!-- Display Existing Classes -->
            <?php if (!empty($subjects_classes)): ?>
                <?php 
                $delay = 0;
                foreach ($subjects_classes as $subject => $classes): 
                ?>
                    <div class="subject-card" style="animation-delay: <?php echo $delay * 0.1; ?>s;">
                        <div class="subject-header">
                            <div class="subject-name">
                                <span class="subject-icon">📚</span>
                                <?php echo htmlspecialchars($subject); ?>
                            </div>
                            <div class="subject-actions">
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete all classes for this subject?');">
                                    <input type="hidden" name="action" value="delete_subject">
                                    <input type="hidden" name="class_id" value="<?php echo $classes[0]['class_id']; ?>">
                                    <button type="submit" class="action-btn btn-delete">
                                        🗑️ Delete
                                    </button>
                                </form>
                            </div>
                        </div>

                        <?php foreach ($classes as $index => $class): ?>
                            <div class="class-entry" id="class-<?php echo $class['class_id']; ?>">
                                <span class="class-label">
                                    <?php echo $index == 0 ? '🎓 Lecture Class' : '👥 Tutorial Class'; ?>
                                </span>
                                <div class="class-times">
                                    <div class="time-input">
                                        <label>Start Time</label>
                                        <input 
                                            type="time" 
                                            class="start-time-<?php echo $class['class_id']; ?>" 
                                            value="<?php echo htmlspecialchars($class['start_time']); ?>" 
                                            disabled
                                        >
                                    </div>
                                    <div class="time-input">
                                        <label>End Time</label>
                                        <input 
                                            type="time" 
                                            class="end-time-<?php echo $class['class_id']; ?>" 
                                            value="<?php echo htmlspecialchars($class['end_time']); ?>" 
                                            disabled
                                        >
                                    </div>
                                    <div class="time-input">
                                        <label>Day</label>
                                        <select class="day-<?php echo $class['class_id']; ?>" disabled>
                                            <option value="Monday" <?php echo $class['day'] == 'Monday' ? 'selected' : ''; ?>>Monday</option>
                                            <option value="Tuesday" <?php echo $class['day'] == 'Tuesday' ? 'selected' : ''; ?>>Tuesday</option>
                                            <option value="Wednesday" <?php echo $class['day'] == 'Wednesday' ? 'selected' : ''; ?>>Wednesday</option>
                                            <option value="Thursday" <?php echo $class['day'] == 'Thursday' ? 'selected' : ''; ?>>Thursday</option>
                                            <option value="Friday" <?php echo $class['day'] == 'Friday' ? 'selected' : ''; ?>>Friday</option>
                                            <option value="Saturday" <?php echo $class['day'] == 'Saturday' ? 'selected' : ''; ?>>Saturday</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="class-entry-actions" id="actions-<?php echo $class['class_id']; ?>" style="display: none;">
                                    <form method="POST" action="class_timetable.php" style="display: flex; gap: 8px; width: 100%;">
                                        <input type="hidden" name="action" value="update_subject">
                                        <input type="hidden" name="class_id" value="<?php echo $class['class_id']; ?>">
                                        <input 
                                            type="hidden" 
                                            name="start_time" 
                                            id="start-time-input-<?php echo $class['class_id']; ?>"
                                            value="<?php echo htmlspecialchars($class['start_time']); ?>"
                                        >
                                        <input 
                                            type="hidden" 
                                            name="end_time" 
                                            id="end-time-input-<?php echo $class['class_id']; ?>"
                                            value="<?php echo htmlspecialchars($class['end_time']); ?>"
                                        >
                                        <input 
                                            type="hidden" 
                                            name="day" 
                                            id="day-input-<?php echo $class['class_id']; ?>"
                                            value="<?php echo htmlspecialchars($class['day']); ?>"
                                        >
                                        <button type="submit" class="class-action-btn btn-save-class">💾 Save</button>
                                        <button type="button" class="class-action-btn btn-cancel-edit" onclick="cancelEdit(<?php echo $class['class_id']; ?>)">❌ Cancel</button>
                                    </form>
                                </div>
                                <div class="class-entry-actions" id="edit-btn-<?php echo $class['class_id']; ?>">
                                    <button class="action-btn btn-edit" onclick="editClass(<?php echo $class['class_id']; ?>)" style="width: 100%;">✏️ Edit</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php $delay++; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">📚</div>
                    <div class="empty-title">No Classes Added Yet</div>
                    <div class="empty-text">Start by adding your first subject with class schedule information</div>
                </div>
            <?php endif; ?>

            <!-- Add Subject Form -->
            <div class="add-subject-card">
                <div class="form-section-title">Add New Subject</div>

                <form method="POST" action="class_timetable.php" novalidate>
                    <input type="hidden" name="action" value="add_subject">

                    <!-- Subject Name -->
                    <div class="form-group">
                        <label for="subject">Subject Name</label>
                        <input 
                            type="text" 
                            id="subject" 
                            name="subject" 
                            placeholder="e.g., Mathematics, Physics, English" 
                            required
                        >
                    </div>

                    <!-- Lecture Class Section -->
                    <div class="form-group">
                        <label style="font-size: 14px; text-transform: none;">🎓 Lecture Class</label>
                    </div>
                    <div class="form-row">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="lecture_start">Start Time</label>
                            <input type="time" id="lecture_start" name="lecture_start">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="lecture_end">End Time</label>
                            <input type="time" id="lecture_end" name="lecture_end">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="lecture_day">Day</label>
                            <select id="lecture_day" name="lecture_day">
                                <option value="">Select Day</option>
                                <option value="Monday">Monday</option>
                                <option value="Tuesday">Tuesday</option>
                                <option value="Wednesday">Wednesday</option>
                                <option value="Thursday">Thursday</option>
                                <option value="Friday">Friday</option>
                                <option value="Saturday">Saturday</option>
                            </select>
                        </div>
                    </div>

                    <!-- Tutorial Class Section -->
                    <div class="form-group">
                        <label style="font-size: 14px; text-transform: none;">👥 Tutorial Class</label>
                    </div>
                    <div class="form-row">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="tutorial_start">Start Time</label>
                            <input type="time" id="tutorial_start" name="tutorial_start">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="tutorial_end">End Time</label>
                            <input type="time" id="tutorial_end" name="tutorial_end">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="tutorial_day">Day</label>
                            <select id="tutorial_day" name="tutorial_day">
                                <option value="">Select Day</option>
                                <option value="Monday">Monday</option>
                                <option value="Tuesday">Tuesday</option>
                                <option value="Wednesday">Wednesday</option>
                                <option value="Thursday">Thursday</option>
                                <option value="Friday">Friday</option>
                                <option value="Saturday">Saturday</option>
                            </select>
                        </div>
                    </div>

                    <div class="button-group">
                        <button type="submit" class="btn-primary">➕ Add Subject</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function editClass(classId) {
            // Enable inputs
            const startTimeInput = document.querySelector('.start-time-' + classId);
            const endTimeInput = document.querySelector('.end-time-' + classId);
            const daySelect = document.querySelector('.day-' + classId);
            
            startTimeInput.disabled = false;
            endTimeInput.disabled = false;
            daySelect.disabled = false;
            
            // Update hidden inputs
            document.getElementById('start-time-input-' + classId).value = startTimeInput.value;
            document.getElementById('end-time-input-' + classId).value = endTimeInput.value;
            document.getElementById('day-input-' + classId).value = daySelect.value;
            
            // Add event listeners to update hidden inputs
            startTimeInput.addEventListener('change', function() {
                document.getElementById('start-time-input-' + classId).value = this.value;
            });
            endTimeInput.addEventListener('change', function() {
                document.getElementById('end-time-input-' + classId).value = this.value;
            });
            daySelect.addEventListener('change', function() {
                document.getElementById('day-input-' + classId).value = this.value;
            });
            
            // Hide edit button and show save/cancel buttons
            document.getElementById('edit-btn-' + classId).style.display = 'none';
            document.getElementById('actions-' + classId).style.display = 'flex';
            
            // Highlight the entry
            document.getElementById('class-' + classId).classList.add('edit-mode');
            
            // Focus on first input
            startTimeInput.focus();
        }

        function cancelEdit(classId) {
            const startTimeInput = document.querySelector('.start-time-' + classId);
            const endTimeInput = document.querySelector('.end-time-' + classId);
            const daySelect = document.querySelector('.day-' + classId);
            
            startTimeInput.disabled = true;
            endTimeInput.disabled = true;
            daySelect.disabled = true;
            
            document.getElementById('edit-btn-' + classId).style.display = 'flex';
            document.getElementById('actions-' + classId).style.display = 'none';
            document.getElementById('class-' + classId).classList.remove('edit-mode');
        }
    </script>
</body>
</html>