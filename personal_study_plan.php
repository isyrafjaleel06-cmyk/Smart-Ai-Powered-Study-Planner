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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_subject') {
        $subject = trim($_POST['subject'] ?? '');
        $confidence_level = (int)($_POST['confidence_level'] ?? 50);
        
        if (empty($subject)) {
            $error = 'Subject name is required!';
        } else {
            $insert = $conn->prepare("INSERT INTO Personal_Study_Plan (student_id, subject, confidence_level) VALUES (?, ?, ?)");
            $insert->bind_param("isi", $student_id, $subject, $confidence_level);
            if ($insert->execute()) {
                $success = 'Subject added successfully!';
            } else {
                $error = 'Failed to add subject: ' . $conn->error;
            }
            $insert->close();
        }
    } elseif ($action === 'update_subject') {
        $plan_id = (int)($_POST['plan_id'] ?? 0);
        $subject = trim($_POST['subject'] ?? '');
        $confidence_level = (int)($_POST['confidence_level'] ?? 50);
        
        if (empty($subject)) {
            $error = 'Subject name is required!';
        } elseif ($plan_id <= 0) {
            $error = 'Invalid subject ID!';
        } else {
            $update = $conn->prepare("UPDATE Personal_Study_Plan SET subject = ?, confidence_level = ? WHERE plan_id = ? AND student_id = ?");
            $update->bind_param("siii", $subject, $confidence_level, $plan_id, $student_id);
            if ($update->execute()) {
                $success = 'Subject updated successfully!';
            } else {
                $error = 'Failed to update subject: ' . $conn->error;
            }
            $update->close();
        }
    } elseif ($action === 'delete_subject') {
        $plan_id = (int)($_POST['plan_id'] ?? 0);
        if ($plan_id > 0) {
            $delete = $conn->prepare("DELETE FROM Personal_Study_Plan WHERE plan_id = ? AND student_id = ?");
            $delete->bind_param("ii", $plan_id, $student_id);
            if ($delete->execute()) {
                $success = 'Subject deleted successfully!';
            } else {
                $error = 'Failed to delete subject: ' . $conn->error;
            }
            $delete->close();
        }
    }
}

// Get subjects for this student
$subjects_query = $conn->prepare("SELECT plan_id, subject, confidence_level FROM Personal_Study_Plan WHERE student_id = ? AND subject IS NOT NULL ORDER BY subject");
$subjects_query->bind_param("i", $student_id);
$subjects_query->execute();
$subjects_result = $subjects_query->get_result();
$subjects = array();
while ($row = $subjects_result->fetch_assoc()) {
    $subjects[] = $row;
}
$subjects_query->close();

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
            text-decoration: none;
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

        /* Content */
        .content {
            flex: 1;
            animation: slideInRight 0.6s ease-out;
            max-height: calc(100vh - 100px);
            overflow-y: auto;
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

        /* Cards */
        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1),
                        0 0 40px rgba(102, 126, 234, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 20px;
            animation: slideUp 0.6s ease-out forwards;
            opacity: 0;
        }

        .card:nth-child(1) { animation-delay: 0.1s; }
        .card:nth-child(2) { animation-delay: 0.2s; }

        .card-title {
            font-size: 16px;
            font-weight: 700;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        /* Form Group */
        .form-group {
            margin-bottom: 20px;
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

        input[type="text"],
        input[type="number"] {
            width: 100%;
            padding: 12px 15px;
            font-size: 14px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            background-color: #f8f9fa;
            transition: all 0.3s ease;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        input[type="text"]:focus,
        input[type="number"]:focus {
            outline: none;
            border-color: #667eea;
            background-color: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        /* Slider Track Custom Logic Color Filling Variable */
        .slider {
            width: 100%;
            height: 6px;
            border-radius: 3px;
            background: linear-gradient(90deg, #667eea 0%, #667eea var(--value, 50%), #e0e0e0 var(--value, 50%), #e0e0e0 100%);
            outline: none;
            -webkit-appearance: none;
            appearance: none;
            cursor: pointer;
            margin-top: 10px;
        }

        .slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #667eea;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
            transition: all 0.3s ease;
        }

        .slider::-webkit-slider-thumb:hover {
            transform: scale(1.1);
        }

        .slider::-moz-range-thumb {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #667eea;
            cursor: pointer;
            border: none;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
            transition: all 0.3s ease;
        }

        .slider::-moz-range-thumb:hover {
            transform: scale(1.1);
        }

        .slider-value {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
            font-size: 13px;
        }

        .slider-label {
            color: #666;
            font-weight: 500;
        }

        .slider-amount {
            color: #667eea;
            font-weight: 700;
            font-size: 16px;
        }

        /* Subject List */
        .subject-list {
            margin-top: 15px;
        }

        .subject-item {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 15px;
            animation: slideUp 0.6s ease-out;
        }

        .subject-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .subject-name {
            font-size: 15px;
            font-weight: 700;
            color: #333;
        }

        .subject-actions {
            display: flex;
            gap: 10px;
        }

        .btn-small {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-edit {
            background: #f5f5f5;
            color: #333;
            border: 1px solid #e0e0e0;
        }

        .btn-edit:hover {
            background: #667eea;
            color: white;
            border-color: #667eea;
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

        .confidence-section {
            margin-top: 12px;
        }

        .confidence-bar {
            width: 100%;
            height: 6px;
            background: #e0e0e0;
            border-radius: 3px;
            overflow: hidden;
            margin-bottom: 8px;
        }

        .confidence-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            transition: width 0.3s ease;
        }

        .confidence-label {
            font-size: 12px;
            color: #999;
            font-weight: 500;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 100;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background: white;
            margin: 10% auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease;
        }

        .modal-title {
            font-size: 20px;
            font-weight: 700;
            color: #333;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 24px;
            color: #999;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .close-btn:hover {
            color: #333;
        }

        .modal-slider {
            width: 100%;
            margin-top: 10px;
        }

        /* Buttons */
        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .btn-primary {
            flex: 1;
            padding: 12px;
            background: white;
            border: 2px solid #e0e0e0;
            color: #333;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-primary:hover {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .btn-save {
            flex: 1;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.5);
        }

        .btn-modal-save {
            flex: 1;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-transform: uppercase;
        }

        .btn-modal-save:hover {
            transform: translateY(-2px);
        }

        .btn-modal-cancel {
            flex: 1;
            padding: 12px;
            background: #f5f5f5;
            color: #666;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-transform: uppercase;
        }

        .btn-modal-cancel:hover {
            background: #e0e0e0;
        }

        /* Info Note */
        .info-note {
            margin-top: 20px;
            padding: 15px;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
            border-left: 4px solid #667eea;
            border-radius: 8px;
            font-size: 13px;
            color: #667eea;
            font-weight: 500;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .main-container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                display: flex;
                flex-wrap: wrap;
                height: auto;
                padding: 15px;
            }

            .sidebar-item {
                flex: 1;
                min-width: 100px;
                text-align: center;
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
    <div class="header">
        <div class="logo">Smart AI-Powered Study Planner</div>
        <a href="manage_profile.php" class="user-profile">
            <div class="user-avatar"><?php echo strtoupper(substr($username, 0, 1)); ?></div>
            <span class="user-name"><?php echo htmlspecialchars($username); ?></span>
        </a>
    </div>

    <div class="main-container">
        <div class="sidebar">
            <a href="dashboard.php" class="sidebar-item">Dashboard</a>
            <a href="#" class="sidebar-item" id="studyMenu"> Study</a>
            <div class="sidebar-submenu">
                <a href="class_timetable.php" class="sidebar-item"> Class Timetable</a>
                <a href="personal_study_plan.php" class="sidebar-item active"> Personal Plan</a>
            </div>
            <a href="timetable.php" class="sidebar-item">Timetable</a>
            <a href="progress.php" class="sidebar-item">Progress</a>
            <a href="manage_profile.php" class="sidebar-item">Manage Profile</a>
        </div>

        <div class="content">
            <div class="page-header">
                <h1 class="page-title">Personal Study Plan</h1>
                <p class="page-subtitle">Manage your subjects and confidence levels</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"> <?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-title"> Subject & Priority</div>
                
                <div class="form-group">
                    <label for="subject">Subject Name</label>
                    <input type="text" id="subject" name="subject" placeholder="Enter subject name">
                </div>

                <div class="form-group">
                    <label for="newConfidence">Confidence Level: <span id="newConfidenceValue">50</span>%</label>
                    <input type="range" class="slider" id="newConfidence" name="confidence_level" min="0" max="100" value="50" oninput="updateNewConfidence()">
                </div>

                <?php if (!empty($subjects)): ?>
                    <div class="subject-list">
                        <?php foreach ($subjects as $subject): ?>
                            <div class="subject-item">
                                <div class="subject-header">
                                    <div class="subject-name"><?php echo htmlspecialchars($subject['subject']); ?></div>
                                    <div class="subject-actions">
                                        <button type="button" class="btn-small btn-edit" onclick="editSubject(<?php echo $subject['plan_id']; ?>, '<?php echo htmlspecialchars($subject['subject'], ENT_QUOTES); ?>', <?php echo $subject['confidence_level']; ?>)">Edit</button>
                                        <form method="POST" action="personal_study_plan.php" style="display: inline;">
                                            <input type="hidden" name="action" value="delete_subject">
                                            <input type="hidden" name="plan_id" value="<?php echo $subject['plan_id']; ?>">
                                            <button type="submit" class="btn-small btn-delete" onclick="return confirm('Delete this subject?')">Delete</button>
                                        </form>
                                    </div>
                                </div>
                                <div class="confidence-section">
                                    <div class="confidence-bar">
                                        <div class="confidence-fill" style="width: <?php echo $subject['confidence_level']; ?>%"></div>
                                    </div>
                                    <div class="confidence-label">Confidence Level: <?php echo $subject['confidence_level']; ?>%</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="button-group">
                    <button type="button" class="btn-primary" onclick="addSubject()"> Add Subject</button>
                </div>
            </div>

            <div class="info-note">
                 <strong>Tip:</strong> Your study preferences (wake-up time, sleep time, preferred study time) can be configured in <strong>Manage Profile</strong> settings!
            </div>
        </div>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-title">
                <span> Edit Subject</span>
                <button class="close-btn" onclick="closeEditModal()">✕</button>
            </div>
            <form id="editForm" method="POST" action="personal_study_plan.php">
                <input type="hidden" name="action" value="update_subject">
                <input type="hidden" name="plan_id" id="editPlanId">

                <div class="form-group">
                    <label for="editSubject">Subject Name</label>
                    <input type="text" id="editSubject" name="subject" required>
                </div>

                <div class="form-group">
                    <label for="editConfidence">Confidence Level: <span id="editConfidenceValue">50</span>%</label>
                    <input type="range" class="modal-slider slider" id="editConfidence" name="confidence_level" min="0" max="100" value="50" oninput="updateEditConfidence()">
                </div>

                <div class="button-group">
                    <button type="submit" class="btn-modal-save"> Save</button>
                    <button type="button" class="btn-modal-cancel" onclick="closeEditModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Update new confidence level fill track line background
        function updateNewConfidence() {
            const slider = document.getElementById('newConfidence');
            document.getElementById('newConfidenceValue').textContent = slider.value;
            slider.style.setProperty('--value', slider.value + '%');
        }

        // Update edit confidence level fill track line background
        function updateEditConfidence() {
            const slider = document.getElementById('editConfidence');
            document.getElementById('editConfidenceValue').textContent = slider.value;
            slider.style.setProperty('--value', slider.value + '%');
        }

        // Add subject
        function addSubject() {
            const subject = document.getElementById('subject').value.trim();
            const confidence = document.getElementById('newConfidence').value;
            
            if (!subject) {
                alert('Please enter a subject name!');
                return;
            }

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'personal_study_plan.php';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'add_subject';
            form.appendChild(actionInput);

            const subjectInput = document.createElement('input');
            subjectInput.type = 'hidden';
            subjectInput.name = 'subject';
            subjectInput.value = subject;
            form.appendChild(subjectInput);

            const confidenceInput = document.createElement('input');
            confidenceInput.type = 'hidden';
            confidenceInput.name = 'confidence_level';
            confidenceInput.value = confidence;
            form.appendChild(confidenceInput);

            document.body.appendChild(form);
            form.submit();
        }

        // Edit subject
        function editSubject(planId, subject, confidence) {
            document.getElementById('editPlanId').value = planId;
            document.getElementById('editSubject').value = subject;
            
            const editSlider = document.getElementById('editConfidence');
            editSlider.value = confidence;
            document.getElementById('editConfidenceValue').textContent = confidence;
            editSlider.style.setProperty('--value', confidence + '%');
            
            document.getElementById('editModal').style.display = 'block';
        }

        // Close edit modal
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }

        // Study menu toggle
        const studyMenu = document.getElementById('studyMenu');
        if (studyMenu) {
            studyMenu.addEventListener('click', function(e) {
                e.preventDefault();
                const submenu = this.nextElementSibling;
                submenu.classList.toggle('open');
            });
        }

        // Color track setup initialized right on document ready state context
        window.addEventListener('DOMContentLoaded', () => {
            updateNewConfidence();
        });
    </script>
</body>
</html>