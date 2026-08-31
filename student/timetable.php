<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}

$student_id = $_SESSION['student_id'];
$username = $_SESSION['username'];
$success = '';
$error = '';

// ─────────────────────────────────────────────
// HELPER FUNCTIONS
// ─────────────────────────────────────────────

function timeToMinutes($time) {
    list($h, $m) = explode(':', $time);
    return (int)$h * 60 + (int)$m;
}

function minutesToTime($minutes) {
    $h = floor($minutes / 60) % 24;
    $m = $minutes % 60;
    return str_pad($h, 2, '0', STR_PAD_LEFT) . ':' . str_pad($m, 2, '0', STR_PAD_LEFT);
}

function getSessionDuration($confidence) {
    if ($confidence <= 30) return 120;       // Critical: 2 hours
    if ($confidence <= 70) return 60;        // Intermediate: 1 hour
    return 30;                               // Mastered: 30 min
}

function getConfidenceLabel($confidence) {
    if ($confidence <= 30) return ['Critical', '#ef4444'];
    if ($confidence <= 70) return ['Intermediate', '#f59e0b'];
    return ['Mastered', '#10b981'];
}

// ─────────────────────────────────────────────
// GENERATE AI STUDY PLAN
// ─────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {

    // 1. Fetch student profile
    $profile = $conn->prepare("SELECT wake_up_time, sleep_time, preferred_time, max_study_hours FROM student WHERE student_id = ?");
    $profile->bind_param("i", $student_id);
    $profile->execute();
    $profileData = $profile->get_result()->fetch_assoc();
    $profile->close();

    if (!$profileData || !$profileData['wake_up_time']) {
        $error = 'Please complete your profile (wake-up time, sleep time, preferred study time) before generating a plan.';
    } else {
        $wakeMinutes  = timeToMinutes($profileData['wake_up_time']);
        $sleepMinutes = timeToMinutes($profileData['sleep_time']);
        if ($sleepMinutes <= $wakeMinutes) $sleepMinutes += 24 * 60; // overnight
        $maxStudyMinutes = (int)$profileData['max_study_hours'] * 60;
        $preferredTimes  = json_decode($profileData['preferred_time'], true) ?? ['Morning'];

        // 2. Fetch subjects ordered by confidence ASC (Low Confidence First)
        $subjectsQ = $conn->prepare("SELECT plan_id, subject, confidence_level FROM personal_study_plan WHERE student_id = ? AND subject IS NOT NULL ORDER BY confidence_level ASC");
        $subjectsQ->bind_param("i", $student_id);
        $subjectsQ->execute();
        $subjectsResult = $subjectsQ->get_result();
        $subjects = [];
        while ($row = $subjectsResult->fetch_assoc()) $subjects[] = $row;
        $subjectsQ->close();

        if (empty($subjects)) {
            $error = 'Please add subjects in Personal Study Plan before generating a timetable.';
        } else {
            // 3. Fetch class timetable (fixed blocks) for the week
            $classQ = $conn->prepare("SELECT day, start_time, end_time FROM class_timetable WHERE student_id = ?");
            $classQ->bind_param("i", $student_id);
            $classQ->execute();
            $classResult = $classQ->get_result();
            $fixedBlocks = [];
            while ($row = $classResult->fetch_assoc()) {
                $fixedBlocks[$row['day']][] = [
                    'start' => timeToMinutes($row['start_time']),
                    'end'   => timeToMinutes($row['end_time']),
                ];
            }
            $classQ->close();

            // 4. Fetch incomplete sessions from yesterday (Carry-Forward Rule)
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $carryQ = $conn->prepare("SELECT plan_id, subject FROM ai_personal_study_timetable WHERE student_id = ? AND study_date = ? AND is_completed = 0");
            $carryQ->bind_param("is", $student_id, $yesterday);
            $carryQ->execute();
            $carryResult = $carryQ->get_result();
            $carryForward = [];
            while ($row = $carryResult->fetch_assoc()) {
                $carryForward[$row['plan_id']] = true; // mark plan_ids to prioritize
            }
            $carryQ->close();

            // Bump carry-forward subjects to top priority
            if (!empty($carryForward)) {
                usort($subjects, function($a, $b) use ($carryForward) {
                    $aCarry = isset($carryForward[$a['plan_id']]) ? 0 : 1;
                    $bCarry = isset($carryForward[$b['plan_id']]) ? 0 : 1;
                    if ($aCarry !== $bCarry) return $aCarry - $bCarry;
                    return $a['confidence_level'] - $b['confidence_level'];
                });
            }

            // 5. Delete existing plan for this week
            $deleteQ = $conn->prepare("DELETE FROM ai_personal_study_timetable WHERE student_id = ? AND study_date BETWEEN ? AND ?");
            $weekStart = date('Y-m-d', strtotime('monday this week'));
            $weekEnd   = date('Y-m-d', strtotime('sunday this week'));
            $deleteQ->bind_param("iss", $student_id, $weekStart, $weekEnd);
            $deleteQ->execute();
            $deleteQ->close();

            // 6. Generate schedule for each day
            $days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
            $generatedSessions = [];
            $subjectDayCount = []; // track consecutive sessions per subject per day

            foreach ($days as $dayIndex => $day) {
                $studyDate = date('Y-m-d', strtotime("$weekStart +{$dayIndex} days"));
                $dailyStudied = 0;
                $currentMinute = $wakeMinutes;
                $subjectDayCount[$day] = [];

                // Build free slots for this day
                // Sort fixed blocks
                $todayFixed = $fixedBlocks[$day] ?? [];
                usort($todayFixed, fn($a, $b) => $a['start'] - $b['start']);

                // Determine preferred start time
                $morningStart = timeToMinutes('06:00');
                $afternoonStart = timeToMinutes('12:00');
                $nightStart = timeToMinutes('18:00');

                if (in_array('Morning', $preferredTimes)) {
                    $currentMinute = max($wakeMinutes, $morningStart);
                } elseif (in_array('Afternoon', $preferredTimes)) {
                    $currentMinute = max($wakeMinutes, $afternoonStart);
                } elseif (in_array('Night', $preferredTimes)) {
                    $currentMinute = max($wakeMinutes, $nightStart);
                }

                $subjectIndex = 0;
                $subjectCount = count($subjects);
                $sessionsToday = [];

                // Try to schedule each subject
                $attempts = 0;
                while ($dailyStudied < $maxStudyMinutes && $currentMinute < $sleepMinutes && $attempts < 100) {
                    $attempts++;
                    $subject = $subjects[$subjectIndex % $subjectCount];
                    $subjectIndex++;

                    $duration = getSessionDuration($subject['confidence_level']);

                    // Check if we'd exceed daily limit
                    if ($dailyStudied + $duration > $maxStudyMinutes) {
                        // Try shorter duration
                        $remaining = $maxStudyMinutes - $dailyStudied;
                        if ($remaining < 30) break;
                        $duration = $remaining;
                    }

                    $sessionStart = $currentMinute;
                    $sessionEnd   = $sessionStart + $duration;

                    // Sleep Rule
                    if ($sessionEnd > $sleepMinutes) {
                        $duration = $sleepMinutes - $sessionStart;
                        if ($duration < 30) break;
                        $sessionEnd = $sleepMinutes;
                    }

                    // Fixed Block Rule — check for conflicts
                    $conflict = false;
                    foreach ($todayFixed as $block) {
                        if ($sessionStart < $block['end'] && $sessionEnd > $block['start']) {
                            // Skip past this block + buffer
                            $currentMinute = $block['end'] + 15;
                            $conflict = true;
                            break;
                        }
                    }
                    if ($conflict) continue;

                    // Subject Spacing Rule — max 2 consecutive same subject
                    $subjectDayCount[$day][$subject['plan_id']] = ($subjectDayCount[$day][$subject['plan_id']] ?? 0) + 1;
                    if ($subjectDayCount[$day][$subject['plan_id']] > 2) {
                        // Skip this subject, try next
                        $subjectDayCount[$day][$subject['plan_id']]--;
                        $subjectIndex++;
                        continue;
                    }

                    // Schedule this session
                    $sessionsToday[] = [
                        'plan_id'    => $subject['plan_id'],
                        'subject'    => $subject['subject'],
                        'start_time' => minutesToTime($sessionStart),
                        'end_time'   => minutesToTime($sessionEnd),
                        'study_date' => $studyDate,
                    ];

                    $dailyStudied  += $duration;
                    $currentMinute  = $sessionEnd + 15; // Buffer Rule: 15 min break
                }

                // Insert into DB
                foreach ($sessionsToday as $session) {
                    $insertQ = $conn->prepare("INSERT INTO ai_personal_study_timetable (student_id, plan_id, subject, start_time, end_time, study_date, is_completed, created_at) VALUES (?, ?, ?, ?, ?, ?, 0, NOW())");
                    $insertQ->bind_param("iissss", $student_id, $session['plan_id'], $session['subject'], $session['start_time'], $session['end_time'], $session['study_date']);
                    $insertQ->execute();
                    $insertQ->close();
                    $generatedSessions[] = $session;
                }
            }

            if (empty($generatedSessions)) {
                $error = 'Could not generate a study plan. Please check your profile settings and class timetable.';
            } else {
                $success = 'AI Study Plan generated successfully for this week!';
            }
        }
    }
}

// ─────────────────────────────────────────────
// FETCH CURRENT WEEK'S TIMETABLE FOR DISPLAY
// ─────────────────────────────────────────────

$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekEnd   = date('Y-m-d', strtotime('sunday this week'));

$fetchQ = $conn->prepare("SELECT timetable_id, plan_id, subject, start_time, end_time, study_date, is_completed FROM ai_personal_study_timetable WHERE student_id = ? AND study_date BETWEEN ? AND ? ORDER BY study_date, start_time");
$fetchQ->bind_param("iss", $student_id, $weekStart, $weekEnd);
$fetchQ->execute();
$fetchResult = $fetchQ->get_result();
$timetableData = [];
while ($row = $fetchResult->fetch_assoc()) {
    $dayName = date('l', strtotime($row['study_date']));
    $timetableData[$dayName][] = $row;
}
$fetchQ->close();

// Fetch subjects for confidence colors
$subjectsColorQ = $conn->prepare("SELECT plan_id, confidence_level FROM personal_study_plan WHERE student_id = ?");
$subjectsColorQ->bind_param("i", $student_id);
$subjectsColorQ->execute();
$subjectsColorResult = $subjectsColorQ->get_result();
$subjectConfidence = [];
while ($row = $subjectsColorResult->fetch_assoc()) {
    $subjectConfidence[$row['plan_id']] = $row['confidence_level'];
}
$subjectsColorQ->close();

$conn->close();

$days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
$today = date('l');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Timetable - Smart AI-Powered Study Planner</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

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
            top: -50%; left: -50%;
            width: 200%; height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: moveBackground 20s linear infinite;
            z-index: 0; pointer-events: none;
        }

        @keyframes moveBackground {
            0% { transform: translate(0,0); }
            100% { transform: translate(50px,50px); }
        }

        /* ── Header ── */
        .header {
            position: relative; z-index: 20;
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255,255,255,0.2);
            padding: 20px 30px;
            display: flex; justify-content: space-between; align-items: center;
            box-shadow: 0 8px 32px rgba(102,126,234,0.15);
        }

        .logo {
            font-size: 18px; font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            background-clip: text;
        }

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
            justify-content: center; color: white;
            font-size: 18px; font-weight: 700;
            box-shadow: 0 4px 15px rgba(102,126,234,0.3);
        }

        .user-name { font-size: 14px; font-weight: 600; color: #667eea; }

        /* ── Layout ── */
        .main-container {
            display: flex; position: relative; z-index: 10;
            min-height: calc(100vh - 70px);
            padding: 30px; gap: 30px;
        }

        /* ── Sidebar ── */
        .sidebar {
            width: 200px;
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px; padding: 25px 0;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 0 40px rgba(102,126,234,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            height: fit-content;
            animation: slideInLeft 0.6s ease-out;
        }

        @keyframes slideInLeft {
            from { opacity: 0; transform: translateX(-30px); }
            to   { opacity: 1; transform: translateX(0); }
        }

        .sidebar-item {
            display: block; padding: 12px 20px;
            cursor: pointer; transition: all 0.3s ease;
            font-size: 14px; color: #666; text-decoration: none;
            font-weight: 500; position: relative; overflow: hidden;
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
            background: rgba(102,126,234,0.15);
            color: #667eea; font-weight: 600;
            border-left: 4px solid #667eea; padding-left: 16px;
        }

        .sidebar-submenu { max-height: 200px; }
        .sidebar-submenu .sidebar-item {
            font-size: 13px; margin-left: 15px;
            padding-left: 20px; color: #667eea; font-weight: 500;
        }

        /* ── Content ── */
        .content {
            flex: 1;
            animation: slideInRight 0.6s ease-out;
            max-height: calc(100vh - 100px);
            overflow-y: auto;
        }

        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(30px); }
            to   { opacity: 1; transform: translateX(0); }
        }

        /* ── Page Header ── */
        .page-header {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px; padding: 30px; margin-bottom: 25px;
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

        .page-title { font-size: 26px; font-weight: 700; color: #333; margin-bottom: 6px; }
        .page-subtitle { font-size: 13px; color: #999; font-weight: 500; }

        .btn-generate {
            padding: 14px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; border: none; border-radius: 12px;
            cursor: pointer; font-size: 14px; font-weight: 700;
            transition: all 0.3s ease; letter-spacing: 0.5px;
            box-shadow: 0 10px 25px rgba(102,126,234,0.4);
            white-space: nowrap;
        }
        .btn-generate:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(102,126,234,0.6);
        }

        /* ── Alerts ── */
        .alert {
            padding: 15px; border-radius: 12px; margin-bottom: 20px;
            font-size: 14px; animation: slideDown 0.4s ease-out;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .alert-error   { background: rgba(239,68,68,0.1);  color: #dc2626; border-left: 4px solid #dc2626; }
        .alert-success { background: rgba(16,185,129,0.1); color: #059669; border-left: 4px solid #059669; }

        /* ── Legend ── */
        .legend {
            background: rgba(255,255,255,0.95);
            border-radius: 16px; padding: 16px 24px; margin-bottom: 20px;
            display: flex; gap: 20px; flex-wrap: wrap; align-items: center;
            box-shadow: 0 8px 32px rgba(0,0,0,0.08);
        }
        .legend-title { font-size: 12px; font-weight: 700; color: #999; text-transform: uppercase; letter-spacing: 0.8px; }
        .legend-item { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #555; font-weight: 500; }
        .legend-dot { width: 14px; height: 14px; border-radius: 4px; flex-shrink: 0; }

        /* ── Timetable Card ── */
        .timetable-card {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px; padding: 25px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 0 40px rgba(102,126,234,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            animation: slideUp 0.6s ease-out 0.15s forwards; opacity: 0;
            overflow-x: auto;
        }

        /* ── Week Table ── */
        .week-table {
            width: 100%; border-collapse: collapse;
            min-width: 700px;
        }

        .week-table th {
            padding: 14px 10px; text-align: center;
            font-size: 15px; font-weight: 700; color: white;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .week-table th:first-child { border-radius: 12px 0 0 0; }
        .week-table th:last-child  { border-radius: 0 12px 0 0; }

        .week-table th.today-header {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
            position: relative;
        }
        .week-table th.today-header::after {
            content: '●'; position: absolute;
            bottom: 4px; left: 50%; transform: translateX(-50%);
            font-size: 8px; color: rgba(255,255,255,0.8);
        }

        .week-table td {
            vertical-align: top; padding: 10px 8px;
            border-bottom: 1px solid #f0f0f0;
            border-right: 1px solid #f0f0f0;
            min-width: 110px; min-height: 80px;
        }

        .week-table td:last-child { border-right: none; }
        .week-table tr:last-child td { border-bottom: none; }

        .day-cell { min-height: 80px; }

        .day-cell.today-col { background: rgba(102,126,234,0.03); }

        .empty-day {
            display: flex; align-items: center; justify-content: center;
            min-height: 80px; color: #ccc; font-size: 22px;
        }

        /* ── Session Block ── */
        .session-block {
            border-radius: 10px; padding: 10px;
            margin-bottom: 8px; position: relative;
            transition: all 0.3s ease; cursor: default;
            border-left: 4px solid transparent;
        }
        .session-block:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.15); }

        .session-block.critical     { background: rgba(239,68,68,0.12);  border-color: #ef4444; }
        .session-block.intermediate { background: rgba(245,158,11,0.12); border-color: #f59e0b; }
        .session-block.mastered     { background: rgba(16,185,129,0.12); border-color: #10b981; }
        .session-block.completed    { background: rgba(107,114,128,0.1); border-color: #9ca3af; opacity: 0.7; }

        .session-subject {
            font-size: 14px; font-weight: 700; color: #333;
            margin-bottom: 4px; line-height: 1.3;
        }
        .session-block.completed .session-subject { text-decoration: line-through; color: #999; }

        .session-time {
            font-size: 12px; color: #777; font-weight: 500; margin-bottom: 6px;
        }

        .session-badge {
            display: inline-block; padding: 2px 8px;
            border-radius: 20px; font-size: 9px; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.5px; color: white; margin-bottom: 6px;
        }
        .badge-critical     { background: #ef4444; }
        .badge-intermediate { background: #f59e0b; }
        .badge-mastered     { background: #10b981; }
        .badge-completed    { background: #9ca3af; }

        /* ── Empty State ── */
        .empty-state {
            text-align: center; padding: 60px 20px; color: #bbb;
        }
        .empty-state-icon { font-size: 64px; margin-bottom: 20px; opacity: 0.5; }
        .empty-state-title { font-size: 20px; font-weight: 700; color: #999; margin-bottom: 10px; }
        .empty-state-text  { font-size: 14px; color: #bbb; }

        /* ── Stats Row ── */
        .stats-row {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 15px; margin-bottom: 20px;
        }

        .stat-mini {
            background: rgba(255,255,255,0.95);
            border-radius: 14px; padding: 16px 20px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
            text-align: center;
            animation: slideUp 0.6s ease-out forwards; opacity: 0;
        }
        .stat-mini:nth-child(1) { animation-delay: 0.05s; }
        .stat-mini:nth-child(2) { animation-delay: 0.10s; }
        .stat-mini:nth-child(3) { animation-delay: 0.15s; }
        .stat-mini:nth-child(4) { animation-delay: 0.20s; }

        .stat-mini-value { font-size: 28px; font-weight: 800; background: linear-gradient(135deg, #667eea, #764ba2); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .stat-mini-label { font-size: 11px; color: #999; font-weight: 600; text-transform: uppercase; letter-spacing: 0.6px; margin-top: 4px; }

        /* ── Scrollbar ── */
        ::-webkit-scrollbar { width: 8px; height: 6px; }
        ::-webkit-scrollbar-track { background: rgba(255,255,255,0.1); }
        ::-webkit-scrollbar-thumb { background: linear-gradient(135deg, #667eea, #764ba2); border-radius: 4px; }

        /* ── Responsive ── */
        @media (max-width: 1024px) {
            .main-container { flex-direction: column; padding: 20px; }
            .sidebar { width: 100%; display: flex; flex-wrap: wrap; height: auto; padding: 15px; }
            .sidebar-item { flex: 1; min-width: 100px; text-align: center; }
        }

        @media (max-width: 768px) {
            .page-header { flex-direction: column; align-items: flex-start; }
            .btn-generate { width: 100%; text-align: center; }
        }
    </style>
</head>
<body>

<!-- Header -->
<div class="header">
    <div class="logo">Smart AI-Powered Study Planner</div>
    <a href="manage_profile.php" class="user-profile">
        <div class="user-avatar"><?php echo strtoupper(substr($username, 0, 1)); ?></div>
        <span class="user-name"><?php echo htmlspecialchars($username); ?></span>
    </a>
</div>

<!-- Main -->
<div class="main-container">

    <!-- Sidebar -->
    <div class="sidebar">
        <a href="dashboard.php"        class="sidebar-item">Dashboard</a>
        <a href="#"                    class="sidebar-item" id="studyMenu">Study</a>
        <div class="sidebar-submenu">
            <a href="class_timetable.php"   class="sidebar-item">Class Timetable</a>
            <a href="personal_study_plan.php" class="sidebar-item">Personal Plan</a>
        </div>
        <a href="timetable.php"        class="sidebar-item active">AI Timetable</a>
        <a href="progress.php"         class="sidebar-item">Progress</a>
        <a href="manage_profile.php"   class="sidebar-item">Manage Profile</a>
    </div>

    <!-- Content -->
    <div class="content">

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1 class="page-title">AI Study Plan Timetable</h1>
                <p class="page-subtitle">
                    Week of <?php echo date('d M', strtotime('monday this week')); ?> –
                    <?php echo date('d M Y', strtotime('sunday this week')); ?>
                </p>
            </div>
            <form method="POST" action="timetable.php">
                <button type="submit" name="generate" class="btn-generate">
                    Generate AI Study Plan
                </button>
            </form>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php
        // Calculate stats
        $totalSessions = 0; $completedSessions = 0; $totalMinutes = 0;
        foreach ($timetableData as $daySessions) {
            foreach ($daySessions as $s) {
                $totalSessions++;
                if ($s['is_completed']) $completedSessions++;
                $totalMinutes += (timeToMinutes($s['end_time']) - timeToMinutes($s['start_time']));
            }
        }
        $totalHours = round($totalMinutes / 60, 1);
        $completionPct = $totalSessions > 0 ? round($completedSessions / $totalSessions * 100) : 0;
        ?>

        <?php if (!empty($timetableData)): ?>
        <!-- Stats Row -->
        <div class="stats-row">
            <div class="stat-mini">
                <div class="stat-mini-value"><?php echo $totalSessions; ?></div>
                <div class="stat-mini-label">Total Sessions</div>
            </div>
            <div class="stat-mini">
                <div class="stat-mini-value"><?php echo $totalHours; ?>h</div>
                <div class="stat-mini-label">Study Hours</div>
            </div>
            <div class="stat-mini">
                <div class="stat-mini-value"><?php echo $completedSessions; ?></div>
                <div class="stat-mini-label">Completed Sessions</div>
            </div>
            <div class="stat-mini">
                <div class="stat-mini-value"><?php echo $completionPct; ?>%</div>
                <div class="stat-mini-label">Progress</div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Legend -->
        <div class="legend">
            <span class="legend-title">Notes:</span>
            <div class="legend-item"><div class="legend-dot" style="background:#ef4444;"></div> Critical (0–30%)</div>
            <div class="legend-item"><div class="legend-dot" style="background:#f59e0b;"></div> Intermediate (31–70%)</div>
            <div class="legend-item"><div class="legend-dot" style="background:#10b981;"></div> Mastered (71–100%)</div>
            <div class="legend-item"><div class="legend-dot" style="background:#9ca3af;"></div> Completed</div>
        </div>

        <!-- Timetable -->
        <div class="timetable-card">
            <?php if (empty($timetableData)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon"></div>
                    <div class="empty-state-title">No Study Plan Yet</div>
                    <div class="empty-state-text">
                        Click <strong>"Generate AI Study Plan"</strong> above to create your personalised weekly schedule.<br><br>
                        Make sure you have set up your <strong>Profile</strong>, added <strong>Class Timetable</strong> and at least one <strong>Subject</strong> in Personal Plan first.
                    </div>
                </div>
            <?php else: ?>
                <table class="week-table">
                    <thead>
                        <tr>
                            <?php foreach ($days as $day): ?>
                                <th class="<?php echo $day === $today ? 'today-header' : ''; ?>">
                                    <?php echo substr($day, 0, 3); ?>
                                    <br>
                                    <span style="font-size:12px; font-weight:500; opacity:0.85;">
                                        <?php echo date('d/m', strtotime("$weekStart +" . (array_search($day, $days)) . " days")); ?>
                                    </span>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <?php foreach ($days as $day): ?>
                                <td class="day-cell <?php echo $day === $today ? 'today-col' : ''; ?>">
                                    <?php if (!empty($timetableData[$day])): ?>
                                        <?php foreach ($timetableData[$day] as $session): ?>
                                            <?php
                                            $conf = $subjectConfidence[$session['plan_id']] ?? 50;
                                            if ($session['is_completed']) {
                                                $blockClass = 'completed'; $badgeClass = 'badge-completed'; $badgeLabel = 'Done ✓';
                                            } elseif ($conf <= 30) {
                                                $blockClass = 'critical'; $badgeClass = 'badge-critical'; $badgeLabel = 'Critical';
                                            } elseif ($conf <= 70) {
                                                $blockClass = 'intermediate'; $badgeClass = 'badge-intermediate'; $badgeLabel = 'Medium';
                                            } else {
                                                $blockClass = 'mastered'; $badgeClass = 'badge-mastered'; $badgeLabel = 'Mastered';
                                            }
                                            $startFmt = date('h:i A', strtotime($session['start_time']));
                                            $endFmt   = date('h:i A', strtotime($session['end_time']));
                                            $dur = round((timeToMinutes($session['end_time']) - timeToMinutes($session['start_time'])));
                                            ?>
                                            <div class="session-block <?php echo $blockClass; ?>">
                                                <div class="session-badge <?php echo $badgeClass; ?>"><?php echo $badgeLabel; ?></div>
                                                <div class="session-subject"><?php echo htmlspecialchars($session['subject']); ?></div>
                                                <div class="session-time">
                                                     <?php echo $startFmt; ?> – <?php echo $endFmt; ?><br>
                                                     <?php echo $dur; ?> min
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="empty-day">–</div>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    </div><!-- /content -->
</div><!-- /main-container -->

<script>
    // Sidebar study menu toggle
    const studyMenu = document.getElementById('studyMenu');
    if (studyMenu) {
        studyMenu.addEventListener('click', function(e) {
            e.preventDefault();
            const submenu = this.nextElementSibling;
            submenu.classList.toggle('open');
        });
    }
</script>
</body>
</html>