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

// Handle Add Class
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'add_class') {
        $subject = trim($_POST['subject']);
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $day = $_POST['day'];

        if (empty($subject) || empty($start_time) || empty($end_time) || empty($day)) {
            $error = 'All fields are required!';
        } elseif ($start_time >= $end_time) {
            $error = 'End time must be after start time!';
        } else {
            $insert = $conn->prepare("INSERT INTO Class_Timetable (student_id, subject, start_time, end_time, day) VALUES (?, ?, ?, ?, ?)");
            $insert->bind_param("issss", $student_id, $subject, $start_time, $end_time, $day);
            
            if ($insert->execute()) {
                $success = 'Class added successfully!';
            } else {
                $error = 'Failed to add class!';
            }
            $insert->close();
        }
    } elseif ($action === 'delete_class') {
        $class_id = $_POST['class_id'];
        $delete = $conn->prepare("DELETE FROM Class_Timetable WHERE class_id = ? AND student_id = ?");
        $delete->bind_param("ii", $class_id, $student_id);
        if ($delete->execute()) {
            $success = 'Class deleted successfully!';
        } else {
            $error = 'Failed to delete class!';
        }
        $delete->close();
    }
}

// Get all classes for this student
$classes_query = $conn->prepare("SELECT class_id, subject, start_time, end_time, day FROM Class_Timetable WHERE student_id = ? ORDER BY day, start_time, subject");
$classes_query->bind_param("i", $student_id);
$classes_query->execute();
$classes_result = $classes_query->get_result();
$classes_query->close();

$classes = array();
$colors = ['#90EE90', '#87CEEB', '#FFB6C1', '#FFD700', '#FFA500', '#DDA0DD'];
$colorIndex = 0;
$maxEndTime = '18:00';
$hasEveningClasses = false;

while ($class = $classes_result->fetch_assoc()) {
    $class['color'] = $colors[$colorIndex % count($colors)];
    $classes[] = $class;
    $colorIndex++;
    
    // Check if class ends after 6:00 PM
    if ($class['end_time'] > '18:00') {
        $maxEndTime = $class['end_time'];
        $hasEveningClasses = true;
    }
}

$conn->close();

// Function to get classes for a specific time slot
function getClassesForSlot($classes, $day, $time) {
    $slotClasses = array();
    foreach ($classes as $class) {
        if ($class['day'] === $day) {
            $startTime = strtotime($class['start_time']);
            $endTime = strtotime($class['end_time']);
            $currentTime = strtotime($time);

            if ($currentTime >= $startTime && $currentTime < $endTime) {
                $slotClasses[] = $class;
            }
        }
    }
    return $slotClasses;
}

// Generate time slots based on max end time
function generateTimeSlots($maxEndTime) {
    $times = array();
    $startHour = 8;
    $endHour = intval(substr($maxEndTime, 0, 2));
    
    // If there's no class after 6 PM, go until 18:00
    if ($endHour <= 18) {
        $endHour = 18;
    } else {
        // If there are evening classes, extend to 24:00 (midnight)
        $endHour = 24;
    }
    
    for ($i = $startHour; $i < $endHour; $i++) {
        $times[] = str_pad($i, 2, '0', STR_PAD_LEFT) . ':00';
    }
    
    return $times;
}

$times = generateTimeSlots($maxEndTime);
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
            text-decoration: none;
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

        /* Content Wrapper */
        .content-wrapper {
            display: flex;
            gap: 20px;
        }

        /* Calendar Card */
        .calendar-card {
            flex: 2;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1),
                        0 0 40px rgba(102, 126, 234, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: slideUp 0.6s ease-out 0.1s forwards;
            opacity: 0;
            overflow-x: auto;
        }

        .card-title {
            font-size: 20px;
            font-weight: 700;
            color: #333;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-title::before {
            content: '';
            font-size: 24px;
        }

        /* Calendar Grid */
        .calendar-grid {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            overflow-x: auto;
            background: white;
            min-width: 100%;
        }

        .calendar-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }

        .calendar-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .calendar-header th {
            padding: 15px;
            text-align: center;
            border-bottom: 3px solid #667eea;
            font-size: 14px;
        }

        .time-column {
            width: 80px;
            background: #f9f9f9;
            font-weight: 600;
            color: #999;
            font-size: 12px;
        }

        .day-column {
            width: calc(100% / 7);
            position: relative;
            height: 100px;
            border-right: 1px solid #e0e0e0;
            cursor: pointer;
            transition: background 0.3s ease;
            padding: 5px;
            overflow-y: auto;
            max-height: 100px;
        }

        .day-column:hover {
            background: rgba(102, 126, 234, 0.05);
        }

        .day-column:last-child {
            border-right: none;
        }

        .time-row td {
            border-bottom: 1px solid #e0e0e0;
            height: 100px;
            padding: 5px;
            position: relative;
        }

        .time-row:last-child td {
            border-bottom: none;
        }

        .time-cell {
            text-align: center;
            font-size: 12px;
            color: #999;
            background: #f9f9f9;
            font-weight: 500;
        }

        /* Morning Classes (8-12) */
        .time-row.morning .time-cell {
            background: linear-gradient(135deg, rgba(255, 193, 7, 0.05) 0%, rgba(255, 193, 7, 0.05) 100%);
            color: #f57f17;
        }

        /* Afternoon Classes (12-18) */
        .time-row.afternoon .time-cell {
            background: linear-gradient(135deg, rgba(76, 175, 80, 0.05) 0%, rgba(76, 175, 80, 0.05) 100%);
            color: #388e3c;
        }

        /* Evening Classes (18-24) */
        .time-row.evening .time-cell {
            background: linear-gradient(135deg, rgba(63, 81, 181, 0.1) 0%, rgba(63, 81, 181, 0.1) 100%);
            color: #1a237e;
            font-weight: 700;
        }

        .time-row.evening td {
            background: linear-gradient(135deg, rgba(63, 81, 181, 0.05) 0%, rgba(63, 81, 181, 0.05) 100%);
        }

        /* Night Classes (24) */
        .time-row.night .time-cell {
            background: linear-gradient(135deg, rgba(33, 33, 33, 0.1) 0%, rgba(33, 33, 33, 0.1) 100%);
            color: #1a1a1a;
            font-weight: 700;
        }

        .time-row.night td {
            background: linear-gradient(135deg, rgba(33, 33, 33, 0.05) 0%, rgba(33, 33, 33, 0.05) 100%);
        }

        .classes-container {
            display: flex;
            flex-direction: column;
            gap: 3px;
            width: 100%;
            height: 100%;
            position: relative;
        }

        .class-block {
            width: 100%;
            padding: 6px;
            border-radius: 4px;
            color: white;
            font-size: 10px;
            font-weight: 600;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 35px;
            flex-shrink: 0;
        }

        .class-block:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
        }

        .class-block-content {
            width: 100%;
        }

        .class-block-title {
            font-weight: 700;
            margin-bottom: 1px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .class-block-time {
            font-size: 8px;
            opacity: 0.9;
            margin-bottom: 3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .class-block-delete {
            background: rgba(255, 255, 255, 0.4);
            border: none;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 8px;
            font-weight: 700;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            width: 100%;
            max-width: 50px;
        }

        .class-block-delete:hover {
            background: rgba(255, 0, 0, 0.6);
            transform: scale(1.05);
        }

        .add-button {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: rgba(102, 126, 234, 0.2);
            border: 2px solid #667eea;
            color: #667eea;
            cursor: pointer;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            margin: auto;
        }

        .add-button:hover {
            background: rgba(102, 126, 234, 0.3);
            transform: scale(1.1);
        }

        /* Time Period Badges */
        .time-period-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .badge-morning {
            background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%);
            color: white;
        }

        .badge-evening {
            background: linear-gradient(135deg, #3F51B5 0%, #1A237E 100%);
            color: white;
        }

        .badge-night {
            background: linear-gradient(135deg, #424242 0%, #000000 100%);
            color: white;
        }

        /* Calendar Note */
        .calendar-note {
            margin-top: 20px;
            padding: 15px;
            background: linear-gradient(135deg, rgba(33, 150, 243, 0.1) 0%, rgba(3, 155, 229, 0.1) 100%);
            border-left: 4px solid #2196F3;
            border-radius: 8px;
            font-size: 13px;
            color: #1976d2;
        }

        /* Add Class Panel */
        .add-class-card {
            flex: 1;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1),
                        0 0 40px rgba(102, 126, 234, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            height: fit-content;
            animation: slideUp 0.6s ease-out 0.2s forwards;
            opacity: 0;
        }

        .panel-title {
            font-size: 18px;
            font-weight: 700;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .panel-title::before {
            content: '';
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

        @keyframes fadeIn {
            to {
                opacity: 1;
            }
        }

        label {
            display: block;
            font-size: 12px;
            font-weight: 600;
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

        .time-input-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .color-picker {
            display: flex;
            gap: 10px;
            margin-top: 10px;
            flex-wrap: wrap;
        }

        .color-option {
            width: 35px;
            height: 35px;
            border-radius: 6px;
            cursor: pointer;
            border: 3px solid transparent;
            transition: all 0.3s ease;
        }

        .color-option:hover {
            transform: scale(1.1);
        }

        .color-option.selected {
            border-color: #333;
            box-shadow: 0 0 0 2px white, 0 0 0 4px #333;
        }

        .btn-primary {
            width: 100%;
            padding: 12px;
            font-size: 14px;
            font-weight: 600;
            color: white;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            cursor: pointer;
            margin-top: 25px;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
            animation: slideUp 0.6s ease-out 0.7s forwards;
            opacity: 0;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.5);
        }

        .btn-secondary {
            width: 100%;
            padding: 12px;
            font-size: 14px;
            font-weight: 600;
            color: #667eea;
            background: white;
            border: 2px solid #667eea;
            border-radius: 10px;
            cursor: pointer;
            margin-top: 15px;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-decoration: none;
            display: inline-block;
        }

        .btn-secondary:hover {
            background: #667eea;
            color: white;
        }

        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 3px;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .content-wrapper {
                flex-direction: column;
            }
        }

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

            .calendar-table {
                min-width: 800px;
            }

            .day-column {
                height: 80px;
            }

            .time-row td {
                height: 80px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="logo">Smart AI-Powered Study Planner</div>
        <div class="user-section">
            <a href="manage_profile.php" class="user-profile">
                <div class="user-avatar"><?php echo strtoupper(substr($username, 0, 1)); ?></div>
                <span class="user-name"><?php echo htmlspecialchars($username); ?></span>
            </a>
           
        </div>
    </div>

    <!-- Main Container -->
    <div class="main-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <a href="dashboard.php" class="sidebar-item"> Dashboard</a>
            <a href="#" class="sidebar-item" id="studyMenu">Study</a>
            <div class="sidebar-submenu">
                <a href="class_timetable.php" class="sidebar-item active">Class Timetable</a>
                <a href="personal_study_plan.php" class="sidebar-item">Personal Plan</a>
            </div>
            <a href="timetable.php" class="sidebar-item">Timetable</a>
            <a href="progress.php" class="sidebar-item">Progress</a>
            <a href="manage_profile.php" class="sidebar-item">Manage Profile</a>
        </div>

        <!-- Content -->
        <div class="content">
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">
                     Class Timetable
                    <?php 
                        if ($hasEveningClasses && $maxEndTime >= '23:00') {
                            echo '<span class="time-period-badge badge-night">🌙 Late Night (Up to Midnight)</span>';
                        } elseif ($hasEveningClasses) {
                            echo '<span class="time-period-badge badge-evening">🌙 Evening Classes</span>';
                        }
                    ?>
                </h1>
                <p class="page-subtitle">Manage your class schedule - Add multiple subjects in the same time slot</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">❌ <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">✅ <?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <!-- Content Wrapper -->
            <div class="content-wrapper">
                <!-- Calendar Card -->
                <div class="calendar-card">
                    <div class="card-title">Your Weekly Schedule</div>

                    <!-- Calendar Grid -->
                    <div class="calendar-grid">
                        <table class="calendar-table">
                            <thead class="calendar-header">
                                <tr>
                                    <th class="time-column">Time</th>
                                    <th>Monday</th>
                                    <th>Tuesday</th>
                                    <th>Wednesday</th>
                                    <th>Thursday</th>
                                    <th>Friday</th>
                                    <th>Saturday</th>
                                    <th>Sunday</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

                                foreach ($times as $time) {
                                    $hour = intval(substr($time, 0, 2));
                                    
                                    // Determine time period
                                    if ($hour >= 8 && $hour < 12) {
                                        $rowClass = 'time-row morning';
                                    } elseif ($hour >= 12 && $hour < 18) {
                                        $rowClass = 'time-row afternoon';
                                    } elseif ($hour >= 18 && $hour < 24) {
                                        $rowClass = 'time-row evening';
                                    } else {
                                        $rowClass = 'time-row night';
                                    }
                                    
                                    echo '<tr class="' . $rowClass . '">';
                                    
                                    // Format time display - show midnight as 00:00 -> 12:00 AM
                                    if ($time === '24:00') {
                                        echo '<td class="time-cell">🌙 00:00</td>';
                                    } else {
                                        echo '<td class="time-cell">' . $time . '</td>';
                                    }
                                    
                                    foreach ($days as $day) {
                                        $slotClasses = getClassesForSlot($classes, $day, $time);
                                        
                                        echo '<td class="day-column">';
                                        
                                        if (count($slotClasses) > 0) {
                                            echo '<div class="classes-container">';
                                            foreach ($slotClasses as $classInfo) {
                                                echo '<div class="class-block" style="background: ' . $classInfo['color'] . ';">';
                                                echo '<div class="class-block-content">';
                                                echo '<div class="class-block-title">' . htmlspecialchars($classInfo['subject']) . '</div>';
                                                echo '<div class="class-block-time">' . $classInfo['start_time'] . ' - ' . $classInfo['end_time'] . '</div>';
                                                echo '</div>';
                                                echo '<button class="class-block-delete" onclick="event.stopPropagation(); deleteClass(' . $classInfo['class_id'] . ')">Delete</button>';
                                                echo '</div>';
                                            }
                                            echo '</div>';
                                        } else {
                                            echo '<button class="add-button" onclick="event.stopPropagation(); selectSlot(\'' . $day . '\', \'' . $time . '\')">+</button>';
                                        }
                                        
                                        echo '</td>';
                                    }
                                    
                                    echo '</tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Note -->
                    <div class="calendar-note">
                        <strong>Note:</strong> Fixed classes are time blocks that AI will respect and not plot study sessions. You can add multiple subjects in the same time slot! 
                        <?php 
                            if ($hasEveningClasses && $maxEndTime >= '23:00') {
                                echo '<strong>⭐ You have late night classes! Grid extends up to midnight (00:00).</strong>';
                            } elseif ($hasEveningClasses) {
                                echo '<strong>⭐ You have evening classes after 6:00 PM!</strong>';
                            }
                        ?>
                    </div>
                </div>

                <!-- Add Class Panel -->
                <div class="add-class-card">
                    <div class="panel-title"> Add Class</div>

                    <form method="POST" action="class_timetable.php" id="addClassForm">
                        <input type="hidden" name="action" value="add_class">

                        <!-- Class Name -->
                        <div class="form-group">
                            <label for="subject">Class Name</label>
                            <input 
                                type="text" 
                                id="subject" 
                                name="subject" 
                                placeholder="e.g., Physics, Mathematics" 
                                required
                            >
                        </div>

                        <!-- Time Inputs -->
                        <div class="form-group">
                            <label>Time</label>
                            <div class="time-input-group">
                                <div>
                                    <label style="font-size: 11px; text-transform: none; color: #999;">Start Time</label>
                                    <input type="time" id="start_time" name="start_time" required>
                                </div>
                                <div>
                                    <label style="font-size: 11px; text-transform: none; color: #999;">End Time</label>
                                    <input type="time" id="end_time" name="end_time" required>
                                </div>
                            </div>
                        </div>

                        <!-- Day Selection -->
                        <div class="form-group">
                            <label for="day">Select Day</label>
                            <select id="day" name="day" required>
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

                        <!-- Color Picker -->
                        <div class="form-group">
                            <label>Color</label>
                            <div class="color-picker">
                                <div class="color-option selected" style="background: #90EE90;" onclick="selectColor(this, '#90EE90')"></div>
                                <div class="color-option" style="background: #87CEEB;" onclick="selectColor(this, '#87CEEB')"></div>
                                <div class="color-option" style="background: #FFB6C1;" onclick="selectColor(this, '#FFB6C1')"></div>
                                <div class="color-option" style="background: #FFD700;" onclick="selectColor(this, '#FFD700')"></div>
                                <div class="color-option" style="background: #FFA500;" onclick="selectColor(this, '#FFA500')"></div>
                                <div class="color-option" style="background: #DDA0DD;" onclick="selectColor(this, '#DDA0DD')"></div>
                            </div>
                            <input type="hidden" id="color" name="color" value="#90EE90">
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" class="btn-primary"> Add To Timetable</button>
                        <a href="personal_study_plan.php" class="btn-secondary">Continue ➜</a>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Study Menu Toggle
        const studyMenu = document.getElementById('studyMenu');
        studyMenu.addEventListener('click', function(e) {
            e.preventDefault();
            const submenu = this.nextElementSibling;
            submenu.classList.toggle('open');
            this.classList.toggle('expand-open');
        });

        // Select Color
        function selectColor(element, color) {
            document.querySelectorAll('.color-option').forEach(el => {
                el.classList.remove('selected');
            });
            element.classList.add('selected');
            document.getElementById('color').value = color;
        }

        // Select Time Slot
        function selectSlot(day, time) {
            document.getElementById('day').value = day;
            document.getElementById('start_time').value = time === '24:00' ? '00:00' : time;
            
            const hours = time === '24:00' ? 1 : parseInt(time.split(':')[0]) + 1;
            const endHour = Math.min(hours, 24).toString().padStart(2, '0');
            document.getElementById('end_time').value = endHour + ':00';
            
            document.getElementById('subject').focus();
            document.querySelector('.add-class-card').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        // Delete Class
        function deleteClass(classId) {
            if (confirm('Are you sure you want to delete this class?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'class_timetable.php';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete_class';
                form.appendChild(actionInput);
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'class_id';
                idInput.value = classId;
                form.appendChild(idInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Form Validation
        document.getElementById('addClassForm').addEventListener('submit', function(e) {
            const subject = document.getElementById('subject').value.trim();
            const startTime = document.getElementById('start_time').value;
            const endTime = document.getElementById('end_time').value;
            const day = document.getElementById('day').value;

            if (!subject) {
                e.preventDefault();
                alert('Please enter a class name!');
                return;
            }

            if (!startTime || !endTime || !day) {
                e.preventDefault();
                alert('Please fill in all fields!');
                return;
            }

            if (startTime >= endTime) {
                e.preventDefault();
                alert('End time must be after start time!');
                return;
            }
        });
    </script>
</body>
</html>