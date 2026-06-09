<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}

$student_id = $_SESSION['student_id'];
$username   = $_SESSION['username'];
$success    = '';
$error      = '';

// ── Mark session as Done (insert/update progress) ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'mark_done') {
        $timetable_id = (int)$_POST['timetable_id'];
        $plan_id      = (int)$_POST['plan_id'];
        $subject      = trim($_POST['subject']);
        $remark       = trim($_POST['remark'] ?? '');

        // Check if progress record exists
        $check = $conn->prepare("SELECT progress_id FROM progress WHERE timetable_id = ? AND student_id = ?");
        $check->bind_param("ii", $timetable_id, $student_id);
        $check->execute();
        $exists = $check->get_result()->fetch_assoc();
        $check->close();

        if ($exists) {
            $upd = $conn->prepare("UPDATE progress SET status = 'Done', remark = ? WHERE timetable_id = ? AND student_id = ?");
            $upd->bind_param("sii", $remark, $timetable_id, $student_id);
            $upd->execute();
            $upd->close();
        } else {
            $ins = $conn->prepare("INSERT INTO progress (timetable_id, plan_id, student_id, subject, status, remark) VALUES (?, ?, ?, ?, 'Done', ?)");
            $ins->bind_param("iiiss", $timetable_id, $plan_id, $student_id, $subject, $remark);
            $ins->execute();
            $ins->close();
        }

        // Also mark is_completed in ai_personal_study_timetable
        $markQ = $conn->prepare("UPDATE ai_personal_study_timetable SET is_completed = 1 WHERE timetable_id = ? AND student_id = ?");
        $markQ->bind_param("ii", $timetable_id, $student_id);
        $markQ->execute();
        $markQ->close();

        $success = 'Session marked as done!';

    } elseif ($action === 'update_remark') {
        $progress_id = (int)$_POST['progress_id'];
        $remark      = trim($_POST['remark'] ?? '');
        $upd = $conn->prepare("UPDATE progress SET remark = ? WHERE progress_id = ? AND student_id = ?");
        $upd->bind_param("sii", $remark, $progress_id, $student_id);
        $upd->execute();
        $upd->close();
        $success = 'Remark updated!';

    } elseif ($action === 'update_confidence') {
        $plan_id          = (int)$_POST['plan_id'];
        $confidence_level = (int)$_POST['confidence_level'];
        $upd = $conn->prepare("UPDATE personal_study_plan SET confidence_level = ? WHERE plan_id = ? AND student_id = ?");
        $upd->bind_param("iii", $confidence_level, $plan_id, $student_id);
        $upd->execute();
        $upd->close();
        $success = 'Confidence level updated! Regenerate your timetable to reflect the change.';
    }
}

// ── Fetch this week's timetable sessions with progress data ────────────────
$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekEnd   = date('Y-m-d', strtotime('sunday this week'));

$query = $conn->prepare("
    SELECT 
        t.timetable_id,
        t.plan_id,
        t.subject,
        t.start_time,
        t.end_time,
        t.study_date,
        t.is_completed,
        p.progress_id,
        p.status,
        p.remark,
        ps.confidence_level
    FROM ai_personal_study_timetable t
    LEFT JOIN progress p ON p.timetable_id = t.timetable_id AND p.student_id = t.student_id
    LEFT JOIN personal_study_plan ps ON ps.plan_id = t.plan_id
    WHERE t.student_id = ? AND t.study_date BETWEEN ? AND ?
    ORDER BY t.study_date ASC, t.start_time ASC
");
$query->bind_param("iss", $student_id, $weekStart, $weekEnd);
$query->execute();
$result   = $query->get_result();
$sessions = [];
while ($row = $result->fetch_assoc()) $sessions[] = $row;
$query->close();

// Stats
$total     = count($sessions);
$done      = count(array_filter($sessions, fn($s) => $s['status'] === 'Done'));
$pending   = $total - $done;
$pct       = $total > 0 ? round($done / $total * 100) : 0;

$conn->close();

function getConfBadge($c) {
    if ($c === null) return ['–', '#9ca3af', 'Unknown'];
    if ($c <= 30)   return ['Critical',     '#ef4444', 'rgba(239,68,68,0.1)'];
    if ($c <= 70)   return ['Intermediate', '#f59e0b', 'rgba(245,158,11,0.1)'];
    return                 ['Mastered',     '#10b981', 'rgba(16,185,129,0.1)'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Study Progress - Smart AI-Powered Study Planner</title>
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
        .page-subtitle { font-size: 13px; color: #999; font-weight: 500; }

        .header-actions { display: flex; gap: 12px; flex-wrap: wrap; }

        .btn-back {
            padding: 10px 20px;
            background: white; color: #667eea;
            border: 2px solid #667eea; border-radius: 10px;
            cursor: pointer; font-size: 13px; font-weight: 600;
            transition: all 0.3s ease; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-back:hover { background: #667eea; color: white; transform: translateY(-2px); }

        .btn-profile {
            padding: 10px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; border: none; border-radius: 10px;
            cursor: pointer; font-size: 13px; font-weight: 600;
            transition: all 0.3s ease; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;
            box-shadow: 0 4px 15px rgba(102,126,234,0.3);
        }
        .btn-profile:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(102,126,234,0.5); }

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

        /* ── Stats Row ── */
        .stats-row {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px; margin-bottom: 25px;
        }
        .stat-mini {
            background: rgba(255,255,255,0.95); border-radius: 16px; padding: 18px 20px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.08); text-align: center;
            animation: slideUp 0.6s ease-out forwards; opacity: 0;
        }
        .stat-mini:nth-child(1) { animation-delay: 0.05s; }
        .stat-mini:nth-child(2) { animation-delay: 0.10s; }
        .stat-mini:nth-child(3) { animation-delay: 0.15s; }
        .stat-mini:nth-child(4) { animation-delay: 0.20s; }
        .stat-mini-value {
            font-size: 30px; font-weight: 800;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        }
        .stat-mini-label { font-size: 11px; color: #999; font-weight: 600; text-transform: uppercase; letter-spacing: 0.6px; margin-top: 5px; }

        /* ── Progress Bar ── */
        .progress-bar-wrap {
            background: rgba(255,255,255,0.95); border-radius: 16px; padding: 20px 25px;
            margin-bottom: 25px; box-shadow: 0 8px 24px rgba(0,0,0,0.08);
            animation: slideUp 0.6s ease-out 0.2s forwards; opacity: 0;
        }
        .progress-bar-label {
            display: flex; justify-content: space-between;
            font-size: 13px; font-weight: 600; color: #555; margin-bottom: 10px;
        }
        .progress-bar-track {
            width: 100%; height: 12px; background: #f0f0f0; border-radius: 6px; overflow: hidden;
        }
        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            border-radius: 6px; transition: width 1s ease;
        }

        /* ── Table Card ── */
        .table-card {
            background: rgba(255,255,255,0.95); backdrop-filter: blur(10px);
            border-radius: 20px; padding: 25px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 0 40px rgba(102,126,234,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            animation: slideUp 0.6s ease-out 0.25s forwards; opacity: 0;
            overflow-x: auto;
        }
        .card-title {
            font-size: 16px; font-weight: 700; color: #333;
            margin-bottom: 20px; padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
            display: flex; align-items: center; gap: 10px;
        }

        /* ── Progress Table ── */
        .progress-table { width: 100%; border-collapse: collapse; min-width: 700px; }
        .progress-table thead tr {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .progress-table th {
            padding: 14px 16px; text-align: left;
            font-size: 12px; font-weight: 700; color: white;
            text-transform: uppercase; letter-spacing: 0.6px;
        }
        .progress-table th:first-child { border-radius: 10px 0 0 0; }
        .progress-table th:last-child  { border-radius: 0 10px 0 0; }

        .progress-table tbody tr {
            border-bottom: 1px solid #f5f5f5;
            transition: background 0.2s ease;
            animation: fadeInRow 0.5s ease-out forwards; opacity: 0;
        }
        .progress-table tbody tr:hover { background: rgba(102,126,234,0.03); }
        .progress-table tbody tr:last-child { border-bottom: none; }

        @keyframes fadeInRow {
            from { opacity: 0; transform: translateY(10px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .progress-table td { padding: 14px 16px; font-size: 14px; color: #444; vertical-align: middle; }

        .subject-cell { font-weight: 700; color: #333; }
        .date-cell    { font-size: 12px; color: #888; }
        .time-cell    { font-size: 12px; color: #667eea; font-weight: 600; }

        /* confidence badge */
        .conf-badge {
            display: inline-block; padding: 4px 10px; border-radius: 20px;
            font-size: 11px; font-weight: 700; cursor: pointer;
            transition: all 0.2s ease;
        }
        .conf-badge:hover { transform: scale(1.05); }

        /* status badge */
        .status-done    { display: inline-flex; align-items: center; gap: 5px; background: rgba(16,185,129,0.12); color: #059669; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 700; }
        .status-pending { display: inline-flex; align-items: center; gap: 5px; background: rgba(245,158,11,0.12); color: #d97706; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 700; }

        /* remark input */
        .remark-form { display: flex; gap: 8px; align-items: center; }
        .remark-input {
            flex: 1; padding: 8px 12px; font-size: 13px;
            border: 2px solid #e0e0e0; border-radius: 8px;
            background: #f8f9fa; transition: all 0.3s ease;
            font-family: inherit;
        }
        .remark-input:focus { outline: none; border-color: #667eea; background: white; box-shadow: 0 0 0 3px rgba(102,126,234,0.1); }

        /* action buttons */
        .btn-done {
            padding: 8px 16px; background: linear-gradient(135deg, #10b981, #059669);
            color: white; border: none; border-radius: 8px; cursor: pointer;
            font-size: 12px; font-weight: 700; transition: all 0.3s ease;
            white-space: nowrap;
        }
        .btn-done:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(16,185,129,0.4); }

        .btn-save-remark {
            padding: 8px 14px; background: #667eea; color: white;
            border: none; border-radius: 8px; cursor: pointer;
            font-size: 12px; font-weight: 600; transition: all 0.3s ease;
            white-space: nowrap;
        }
        .btn-save-remark:hover { background: #764ba2; transform: translateY(-1px); }

        /* ── Empty State ── */
        .empty-state { text-align: center; padding: 60px 20px; }
        .empty-state-icon  { font-size: 60px; margin-bottom: 20px; opacity: 0.4; }
        .empty-state-title { font-size: 18px; font-weight: 700; color: #999; margin-bottom: 10px; }
        .empty-state-text  { font-size: 14px; color: #bbb; }

        /* ── Confidence Modal ── */
        .modal-overlay {
            display: none; position: fixed; z-index: 1000;
            left: 0; top: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.6); animation: fadeIn 0.3s ease;
        }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        .modal-box {
            background: white; margin: 8% auto; padding: 35px;
            border-radius: 20px; width: 90%; max-width: 420px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.3);
            animation: modalSlide 0.35s ease;
        }
        @keyframes modalSlide {
            from { opacity: 0; transform: translateY(-30px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .modal-title {
            font-size: 20px; font-weight: 700; color: #333;
            margin-bottom: 5px; display: flex; justify-content: space-between; align-items: center;
        }
        .modal-sub { font-size: 13px; color: #999; margin-bottom: 25px; }
        .close-btn { background: none; border: none; font-size: 22px; color: #bbb; cursor: pointer; }
        .close-btn:hover { color: #333; }

        .conf-slider {
            width: 100%; height: 6px; border-radius: 3px; outline: none;
            -webkit-appearance: none; appearance: none; cursor: pointer;
            background: linear-gradient(90deg, #667eea 0%, #667eea var(--val, 50%), #e0e0e0 var(--val, 50%), #e0e0e0 100%);
        }
        .conf-slider::-webkit-slider-thumb {
            -webkit-appearance: none; width: 22px; height: 22px; border-radius: 50%;
            background: #667eea; cursor: pointer; box-shadow: 0 2px 8px rgba(102,126,234,0.4);
        }
        .conf-display {
            text-align: center; font-size: 36px; font-weight: 800; margin: 15px 0 5px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        }
        .conf-label { text-align: center; font-size: 13px; color: #999; margin-bottom: 20px; }

        .btn-modal-save {
            width: 100%; padding: 13px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; border: none; border-radius: 10px; cursor: pointer;
            font-size: 14px; font-weight: 700; transition: all 0.3s ease;
        }
        .btn-modal-save:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(102,126,234,0.4); }
        .btn-modal-cancel {
            width: 100%; padding: 11px; background: #f5f5f5; color: #666;
            border: 1px solid #e0e0e0; border-radius: 10px; cursor: pointer;
            font-size: 14px; font-weight: 600; margin-top: 10px; transition: all 0.2s ease;
        }
        .btn-modal-cancel:hover { background: #e0e0e0; }

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
            .header-actions { width: 100%; }
            .btn-back, .btn-profile { flex: 1; justify-content: center; }
        }
    </style>
</head>
<body>

<!-- Header -->
<div class="header">
    <div class="logo">📚 Smart AI-Powered Study Planner</div>
    <a href="manage_profile.php" class="user-profile">
        <div class="user-avatar"><?php echo strtoupper(substr($username, 0, 1)); ?></div>
        <span class="user-name"><?php echo htmlspecialchars($username); ?></span>
    </a>
</div>

<!-- Main -->
<div class="main-container">

    <!-- Sidebar -->
    <div class="sidebar">
        <a href="dashboard.php"          class="sidebar-item">📊 Dashboard</a>
        <a href="#"                       class="sidebar-item" id="studyMenu">📖 Study</a>
        <div class="sidebar-submenu">
            <a href="class_timetable.php"    class="sidebar-item">📅 Class Timetable</a>
            <a href="personal_study_plan.php" class="sidebar-item">📝 Personal Plan</a>
        </div>
        <a href="timetable.php"          class="sidebar-item">⏰ Timetable</a>
        <a href="progress.php"           class="sidebar-item active">📈 Progress</a>
        <a href="manage_profile.php"     class="sidebar-item">⚙️ Manage Profile</a>
    </div>

    <!-- Content -->
    <div class="content">

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1 class="page-title">📈 Study Progress</h1>
                <p class="page-subtitle">
                    Week of <?php echo date('d M', strtotime('monday this week')); ?> –
                    <?php echo date('d M Y', strtotime('sunday this week')); ?>
                </p>
            </div>
            <div class="header-actions">
                <a href="timetable.php" class="btn-back">← Back to Timetable</a>
                <a href="manage_profile.php" class="btn-profile">⚙️ Manage Profile</a>
            </div>
        </div>

        <?php if ($error):   ?><div class="alert alert-error">❌ <?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success">✅ <?php echo htmlspecialchars($success); ?></div><?php endif; ?>

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-mini">
                <div class="stat-mini-value"><?php echo $total; ?></div>
                <div class="stat-mini-label">Total Sessions</div>
            </div>
            <div class="stat-mini">
                <div class="stat-mini-value"><?php echo $done; ?></div>
                <div class="stat-mini-label">Completed</div>
            </div>
            <div class="stat-mini">
                <div class="stat-mini-value"><?php echo $pending; ?></div>
                <div class="stat-mini-label">Pending</div>
            </div>
            <div class="stat-mini">
                <div class="stat-mini-value"><?php echo $pct; ?>%</div>
                <div class="stat-mini-label">Completion Rate</div>
            </div>
        </div>

        <!-- Progress Bar -->
        <div class="progress-bar-wrap">
            <div class="progress-bar-label">
                <span>Weekly Completion</span>
                <span><?php echo $done; ?> / <?php echo $total; ?> sessions done</span>
            </div>
            <div class="progress-bar-track">
                <div class="progress-bar-fill" style="width: <?php echo $pct; ?>%"></div>
            </div>
        </div>

        <!-- Table -->
        <div class="table-card">
            <div class="card-title">📋 Session Details</div>

            <?php if (empty($sessions)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <div class="empty-state-title">No Sessions This Week</div>
                    <div class="empty-state-text">Go to the <a href="timetable.php" style="color:#667eea;font-weight:700;">Timetable</a> page and generate your AI Study Plan first.</div>
                </div>
            <?php else: ?>
                <table class="progress-table">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Date & Time</th>
                            <th>Confidence</th>
                            <th>Status</th>
                            <th>Remark</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sessions as $i => $s):
                            [$confLabel, $confColor, $confBg] = getConfBadge($s['confidence_level']);
                            $isDone = $s['status'] === 'Done';
                            $startFmt = date('h:i A', strtotime($s['start_time']));
                            $endFmt   = date('h:i A', strtotime($s['end_time']));
                            $dayName  = date('D, d M', strtotime($s['study_date']));
                        ?>
                        <tr style="animation-delay: <?php echo $i * 0.05; ?>s">
                            <td>
                                <div class="subject-cell"><?php echo htmlspecialchars($s['subject']); ?></div>
                            </td>
                            <td>
                                <div class="date-cell"><?php echo $dayName; ?></div>
                                <div class="time-cell"><?php echo $startFmt; ?> – <?php echo $endFmt; ?></div>
                            </td>
                            <td>
                                <span class="conf-badge"
                                      style="background:<?php echo $confBg; ?>; color:<?php echo $confColor; ?>; border: 1px solid <?php echo $confColor; ?>;"
                                      onclick="openConfModal(<?php echo (int)$s['plan_id']; ?>, '<?php echo htmlspecialchars($s['subject']); ?>', <?php echo (int)($s['confidence_level'] ?? 50); ?>)"
                                      title="Click to edit confidence level">
                                     <?php echo $confLabel; ?> (<?php echo $s['confidence_level'] ?? '–'; ?>%)
                                </span>
                            </td>
                            <td>
                                <?php if ($isDone): ?>
                                    <span class="status-done">Done</span>
                                <?php else: ?>
                                    <span class="status-pending">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td style="min-width:200px;">
                                <?php if ($isDone && $s['progress_id']): ?>
                                    <!-- Update remark form -->
                                    <form method="POST" action="progress.php" class="remark-form">
                                        <input type="hidden" name="action" value="update_remark">
                                        <input type="hidden" name="progress_id" value="<?php echo $s['progress_id']; ?>">
                                        <input type="text" name="remark" class="remark-input"
                                               value="<?php echo htmlspecialchars($s['remark'] ?? ''); ?>"
                                               placeholder="Add a note…">
                                        <button type="submit" class="btn-save-remark">💾</button>
                                    </form>
                                <?php elseif (!$isDone): ?>
                                    <!-- Remark shown in done form below -->
                                    <span style="font-size:12px; color:#bbb;">Mark done to add remark</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!$isDone): ?>
                                    <form method="POST" action="progress.php" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                                        <input type="hidden" name="action" value="mark_done">
                                        <input type="hidden" name="timetable_id" value="<?php echo $s['timetable_id']; ?>">
                                        <input type="hidden" name="plan_id" value="<?php echo $s['plan_id']; ?>">
                                        <input type="hidden" name="subject" value="<?php echo htmlspecialchars($s['subject']); ?>">
                                        <input type="text" name="remark" class="remark-input"
                                               placeholder="Optional remark…" style="min-width:130px;">
                                        <button type="submit" class="btn-done">✓ Done</button>
                                    </form>
                                <?php else: ?>
                                    <span style="font-size:12px; color:#10b981; font-weight:600;">✔ Completed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    </div><!-- /content -->
</div><!-- /main -->

<!-- Confidence Level Modal -->
<div class="modal-overlay" id="confModal">
    <div class="modal-box">
        <div class="modal-title">
            <span>✏️ Edit Confidence Level</span>
            <button class="close-btn" onclick="closeConfModal()">✕</button>
        </div>
        <div class="modal-sub" id="confModalSub">Subject name</div>

        <form method="POST" action="progress.php" id="confForm">
            <input type="hidden" name="action" value="update_confidence">
            <input type="hidden" name="plan_id" id="confPlanId">

            <div class="conf-display" id="confDisplay">50%</div>
            <div class="conf-label" id="confLabelText">Intermediate</div>

            <input type="range" class="conf-slider" id="confSlider"
                   name="confidence_level" min="0" max="100" value="50"
                   oninput="updateConfSlider(this.value)">

            <div style="display:flex; justify-content:space-between; font-size:11px; color:#bbb; margin-top:6px; margin-bottom:20px;">
                <span>0% Critical</span><span>50% Intermediate</span><span>100% Mastered</span>
            </div>

            <button type="submit" class="btn-modal-save">💾 Save Confidence Level</button>
            <button type="button" class="btn-modal-cancel" onclick="closeConfModal()">Cancel</button>
        </form>
    </div>
</div>

<script>
    // Sidebar toggle
    const studyMenu = document.getElementById('studyMenu');
    if (studyMenu) {
        studyMenu.addEventListener('click', function(e) {
            e.preventDefault();
            this.nextElementSibling.classList.toggle('open');
        });
    }

    // Confidence Modal
    function openConfModal(planId, subject, confidence) {
        document.getElementById('confPlanId').value  = planId;
        document.getElementById('confModalSub').textContent = subject;
        document.getElementById('confSlider').value  = confidence;
        updateConfSlider(confidence);
        document.getElementById('confModal').style.display = 'block';
    }

    function closeConfModal() {
        document.getElementById('confModal').style.display = 'none';
    }

    function updateConfSlider(val) {
        val = parseInt(val);
        document.getElementById('confDisplay').textContent = val + '%';
        document.getElementById('confSlider').style.setProperty('--val', val + '%');

        let label, color;
        if (val <= 30)      { label = 'Critical — needs more study time';     color = '#ef4444'; }
        else if (val <= 70) { label = 'Intermediate — making good progress';  color = '#f59e0b'; }
        else                { label = 'Mastered — short review sessions only'; color = '#10b981'; }

        const disp = document.getElementById('confDisplay');
        const lbl  = document.getElementById('confLabelText');
        disp.style.backgroundImage = `linear-gradient(135deg, ${color}, ${color}cc)`;
        lbl.textContent = label;
        lbl.style.color = color;
    }

    window.onclick = function(e) {
        if (e.target === document.getElementById('confModal')) closeConfModal();
    };
</script>
</body>
</html>