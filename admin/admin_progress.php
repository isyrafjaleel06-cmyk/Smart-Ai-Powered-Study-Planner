<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

require_once '../config.php';

$admin_username = $_SESSION['admin_username'];

// ── Week filter ────────────────────────────────────────────────────────────
$filter_week = $_GET['week'] ?? date('Y-m-d', strtotime('monday this week'));
$week_start  = date('Y-m-d', strtotime('monday this week', strtotime($filter_week)));
$week_end    = date('Y-m-d', strtotime('sunday this week', strtotime($filter_week)));
$prev_week   = date('Y-m-d', strtotime($week_start . ' -7 days'));
$next_week   = date('Y-m-d', strtotime($week_start . ' +7 days'));
$curr_week   = date('Y-m-d', strtotime('monday this week'));

// ── Overall Stats ──────────────────────────────────────────────────────────

// Total sessions this week
$q = $conn->prepare("SELECT COUNT(*) as c FROM ai_personal_study_timetable WHERE study_date BETWEEN ? AND ?");
$q->bind_param("ss", $week_start, $week_end);
$q->execute();
$total_sessions = $q->get_result()->fetch_assoc()['c'];
$q->close();

// Total completed (from progress table)
$q = $conn->prepare("SELECT COUNT(*) as c FROM progress p JOIN ai_personal_study_timetable t ON t.timetable_id = p.timetable_id WHERE t.study_date BETWEEN ? AND ? AND p.status = 'Done'");
$q->bind_param("ss", $week_start, $week_end);
$q->execute();
$total_done = $q->get_result()->fetch_assoc()['c'];
$q->close();

$total_pending  = $total_sessions - $total_done;
$overall_pct    = $total_sessions > 0 ? round($total_done / $total_sessions * 100) : 0;

// Students with at least one done session
$q = $conn->prepare("SELECT COUNT(DISTINCT p.student_id) as c FROM progress p JOIN ai_personal_study_timetable t ON t.timetable_id = p.timetable_id WHERE t.study_date BETWEEN ? AND ? AND p.status = 'Done'");
$q->bind_param("ss", $week_start, $week_end);
$q->execute();
$active_students = $q->get_result()->fetch_assoc()['c'];
$q->close();

// Students with ZERO done sessions this week (at-risk)
$q = $conn->prepare("SELECT COUNT(DISTINCT student_id) as c FROM ai_personal_study_timetable WHERE study_date BETWEEN ? AND ?");
$q->bind_param("ss", $week_start, $week_end);
$q->execute();
$students_with_plan = $q->get_result()->fetch_assoc()['c'];
$q->close();
$at_risk = $students_with_plan - $active_students;

// ── Per-Student Completion ─────────────────────────────────────────────────
$q = $conn->prepare("
    SELECT s.student_id, s.username, s.email,
           COUNT(DISTINCT t.timetable_id) as total_sessions,
           COUNT(DISTINCT p.progress_id)  as done_sessions
    FROM student s
    JOIN ai_personal_study_timetable t ON t.student_id = s.student_id
        AND t.study_date BETWEEN ? AND ?
    LEFT JOIN progress p ON p.timetable_id = t.timetable_id AND p.status = 'Done'
    GROUP BY s.student_id
    ORDER BY done_sessions DESC, total_sessions DESC
");
$q->bind_param("ss", $week_start, $week_end);
$q->execute();
$student_stats = $q->get_result()->fetch_all(MYSQLI_ASSOC);
$q->close();

// ── Subject Completion Rate ────────────────────────────────────────────────
$q = $conn->prepare("
    SELECT t.subject,
           COUNT(DISTINCT t.timetable_id)  as total,
           COUNT(DISTINCT p.progress_id)   as done,
           AVG(ps.confidence_level)        as avg_conf
    FROM ai_personal_study_timetable t
    LEFT JOIN progress p ON p.timetable_id = t.timetable_id AND p.status = 'Done'
    LEFT JOIN personal_study_plan ps ON ps.subject = t.subject AND ps.student_id = t.student_id
    WHERE t.study_date BETWEEN ? AND ?
    GROUP BY t.subject
    ORDER BY done ASC, total DESC
    LIMIT 10
");
$q->bind_param("ss", $week_start, $week_end);
$q->execute();
$subject_stats = $q->get_result()->fetch_all(MYSQLI_ASSOC);
$q->close();

// ── Recent Remarks ─────────────────────────────────────────────────────────
$q = $conn->prepare("
    SELECT p.remark, p.subject, p.status,
           s.username, t.study_date, t.start_time, t.end_time
    FROM progress p
    JOIN student s ON s.student_id = p.student_id
    JOIN ai_personal_study_timetable t ON t.timetable_id = p.timetable_id
    WHERE p.remark IS NOT NULL AND p.remark != ''
      AND t.study_date BETWEEN ? AND ?
    ORDER BY p.progress_id DESC
    LIMIT 12
");
$q->bind_param("ss", $week_start, $week_end);
$q->execute();
$remarks = $q->get_result()->fetch_all(MYSQLI_ASSOC);
$q->close();

// ── At-Risk Students (0 done sessions this week) ───────────────────────────
$q = $conn->prepare("
    SELECT s.student_id, s.username, s.email,
           COUNT(DISTINCT t.timetable_id) as total_sessions
    FROM student s
    JOIN ai_personal_study_timetable t ON t.student_id = s.student_id
        AND t.study_date BETWEEN ? AND ?
    LEFT JOIN progress p ON p.timetable_id = t.timetable_id AND p.status = 'Done'
    GROUP BY s.student_id
    HAVING COUNT(DISTINCT p.progress_id) = 0
    ORDER BY total_sessions DESC
");
$q->bind_param("ss", $week_start, $week_end);
$q->execute();
$at_risk_students = $q->get_result()->fetch_all(MYSQLI_ASSOC);
$q->close();

// ── Day-by-day completion this week ───────────────────────────────────────
$daily_stats = [];
for ($i = 0; $i < 7; $i++) {
    $day_date = date('Y-m-d', strtotime($week_start . " +$i days"));
    $day_name = date('D', strtotime($day_date));

    $q = $conn->prepare("SELECT COUNT(*) as c FROM ai_personal_study_timetable WHERE study_date = ?");
    $q->bind_param("s", $day_date);
    $q->execute();
    $day_total = $q->get_result()->fetch_assoc()['c'];
    $q->close();

    $q = $conn->prepare("SELECT COUNT(*) as c FROM progress p JOIN ai_personal_study_timetable t ON t.timetable_id = p.timetable_id WHERE t.study_date = ? AND p.status = 'Done'");
    $q->bind_param("s", $day_date);
    $q->execute();
    $day_done = $q->get_result()->fetch_assoc()['c'];
    $q->close();

    $daily_stats[] = [
        'day'   => $day_name,
        'date'  => $day_date,
        'total' => $day_total,
        'done'  => $day_done,
        'pct'   => $day_total > 0 ? round($day_done / $day_total * 100) : 0,
    ];
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Progress Reports - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background:rgb(159, 180, 230);
            min-height: 100vh; color: #e2e8f0;
            display: flex; overflow-x: hidden;
        }

        /* ── Sidebar ── */
        .sidebar {
            width:260px; min-height:100vh;
            background:linear-gradient(180deg,#1e1b4b 0%,#1a1a2e 100%);
            border-right:1px solid rgba(99,102,241,0.15);
            display:flex; flex-direction:column;
            position:fixed; top:0; left:0; z-index:50;
            box-shadow:4px 0 20px rgba(0,0,0,0.3);
        }
        .sidebar-logo { padding:28px 24px 20px; border-bottom:1px solid rgba(255,255,255,0.06); }
        .sidebar-logo .logo-icon {
            width:44px; height:44px; border-radius:12px;
            background:linear-gradient(135deg,#6366f1,#8b5cf6);
            display:flex; align-items:center; justify-content:center;
            font-size:20px; margin-bottom:10px;
            box-shadow:0 4px 15px rgba(99,102,241,0.4);
        }
        .sidebar-logo h2 { font-size:14px; font-weight:700; color:white; }
        .sidebar-logo p  { font-size:11px; color:rgba(255,255,255,0.3); margin-top:2px; }
        .sidebar-nav { flex:1; padding:20px 12px; }
        .nav-section-title {
            font-size:10px; font-weight:700; color:rgba(255,255,255,0.25);
            text-transform:uppercase; letter-spacing:1.2px;
            padding:0 12px; margin-bottom:8px; margin-top:20px;
        }
        .nav-section-title:first-child { margin-top:0; }
        .nav-item {
            display:flex; align-items:center; gap:12px;
            padding:11px 14px; border-radius:10px; cursor:pointer;
            transition:all 0.2s; text-decoration:none;
            color:rgba(255,255,255,0.5); font-size:14px; font-weight:500; margin-bottom:3px;
        }
        .nav-item i { width:18px; text-align:center; font-size:15px; }
        .nav-item:hover { background:rgba(99,102,241,0.15); color:#a5b4fc; }
        .nav-item.active {
            background:linear-gradient(135deg,rgba(99,102,241,0.3),rgba(139,92,246,0.2));
            color:#a5b4fc; border:1px solid rgba(99,102,241,0.3);
        }
        .sidebar-footer { padding:16px 20px; border-top:1px solid rgba(255,255,255,0.06); }
        .admin-info { display:flex; align-items:center; gap:10px; margin-bottom:14px; }
        .admin-avatar {
            width:36px; height:36px; border-radius:10px;
            background:linear-gradient(135deg,#6366f1,#8b5cf6);
            display:flex; align-items:center; justify-content:center;
            font-size:14px; font-weight:700; color:white; flex-shrink:0;
        }
        .admin-info-text p    { font-size:13px; font-weight:600; color:white; }
        .admin-info-text span { font-size:11px; color:rgba(255,255,255,0.35); }
        .logout-btn {
            display:flex; align-items:center; gap:8px;
            padding:9px 14px; border-radius:8px; width:100%;
            background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.2);
            color:#fca5a5; font-size:13px; font-weight:600;
            cursor:pointer; text-decoration:none; transition:all 0.2s;
        }
        .logout-btn:hover { background:rgba(239,68,68,0.2); }

        /* ── Main ── */
        .main { margin-left:260px; flex:1; min-height:100vh; display:flex; flex-direction:column; }
        .topbar {
            padding:20px 30px;
            background:rgba(15,23,42,0.8); backdrop-filter:blur(10px);
            border-bottom:1px solid rgba(255,255,255,0.05);
            display:flex; justify-content:space-between; align-items:center;
            position:sticky; top:0; z-index:40;
        }
        .topbar-title h1 { font-size:22px; font-weight:700; color:white; }
        .topbar-title p  { font-size:13px; color:rgba(255,255,255,0.35); margin-top:2px; }
        .page-body { padding:28px 30px; flex:1; }

        /* ── Animation ── */
        @keyframes fadeInUp { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }

        /* ── Stats Grid ── */
        .stats-grid {
            display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
            gap:16px; margin-bottom:24px;
        }
        .stat-card {
            background:linear-gradient(135deg,rgba(30,27,75,0.8),rgba(26,26,46,0.8));
            border:1px solid rgba(255,255,255,0.07); border-radius:14px;
            padding:20px 22px; transition:all 0.2s;
            animation:fadeInUp 0.5s ease forwards; opacity:0;
        }
        .stat-card:nth-child(1){animation-delay:0.05s}
        .stat-card:nth-child(2){animation-delay:0.10s}
        .stat-card:nth-child(3){animation-delay:0.15s}
        .stat-card:nth-child(4){animation-delay:0.20s}
        .stat-card:hover { transform:translateY(-2px); border-color:rgba(99,102,241,0.2); }
        .stat-icon {
            width:40px; height:40px; border-radius:10px;
            display:flex; align-items:center; justify-content:center;
            font-size:16px; margin-bottom:14px;
        }
        .stat-value { font-size:32px; font-weight:800; color:white; margin-bottom:3px; line-height:1; }
        .stat-label { font-size:11px; color:rgba(255,255,255,0.35); font-weight:600; text-transform:uppercase; letter-spacing:0.6px; }
        .stat-sub   { font-size:11px; color:rgba(255,255,255,0.2); margin-top:5px; }

        /* ── Week Navigation ── */
        .week-nav {
            display:flex; align-items:center; gap:12px; margin-bottom:24px;
        }
        .week-btn {
            padding:8px 16px; border-radius:8px; font-size:12px; font-weight:600;
            background:rgba(8, 8, 8, 0.71); border:1px solid rgba(99,102,241,0.2);
            color:#a5b4fc; cursor:pointer; text-decoration:none;
            display:flex; align-items:center; gap:6px; transition:all 0.2s;
        }
        .week-btn:hover { background:rgba(12, 12, 12, 0.78); }
        .week-label {
            flex:1; text-align:center; font-size:14px; font-weight:700; color:white;
            background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08);
            border-radius:10px; padding:10px 20px;
        }

        /* ── Grid Layouts ── */
        .two-col { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:22px; }
        .three-col { display:grid; grid-template-columns:2fr 1fr; gap:20px; margin-bottom:22px; }

        /* ── Card ── */
        .card {
            background:linear-gradient(135deg,rgba(11, 11, 12, 0.73),rgba(13, 13, 15, 0.75));
            border:1px solid rgba(255,255,255,0.07); border-radius:18px; padding:24px;
            animation:fadeInUp 0.5s ease 0.3s forwards; opacity:0;
        }
        .card-title {
            font-size:15px; font-weight:700; color:white; margin-bottom:18px;
            padding-bottom:14px; border-bottom:1px solid rgba(255,255,255,0.06);
            display:flex; align-items:center; gap:10px;
        }
        .card-title i { color:#818cf8; }

        /* ── Overall Progress Bar ── */
        .big-progress {
            text-align:center; padding:10px 0 20px;
        }
        .big-pct {
            font-size:56px; font-weight:800;
            background:linear-gradient(135deg,#6366f1,#8b5cf6);
            -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;
            line-height:1; margin-bottom:6px;
        }
        .big-label { font-size:13px; color:rgba(255,255,255,0.35); margin-bottom:20px; }
        .progress-track {
            width:100%; height:12px; background:rgba(255,255,255,0.06);
            border-radius:6px; overflow:hidden; margin-bottom:10px;
        }
        .progress-fill {
            height:100%; border-radius:6px;
            background:linear-gradient(90deg,#6366f1,#8b5cf6);
            transition:width 1.2s ease;
        }
        .progress-sub { font-size:12px; color:rgba(255,255,255,0.25); }

        /* ── Daily Bar Chart ── */
        .daily-chart { display:flex; align-items:flex-end; gap:8px; height:120px; margin-top:10px; }
        .day-bar-wrap { flex:1; display:flex; flex-direction:column; align-items:center; gap:5px; height:100%; justify-content:flex-end; }
        .day-bar-track { width:100%; background:rgba(255,255,255,0.05); border-radius:4px; flex:1; display:flex; align-items:flex-end; }
        .day-bar-fill {
            width:100%; border-radius:4px;
            background:linear-gradient(180deg,#6366f1,#8b5cf6);
            transition:height 1s ease; min-height:2px;
        }
        .day-bar-fill.zero { background:rgba(255,255,255,0.05); }
        .day-label { font-size:10px; color:rgba(255,255,255,0.3); font-weight:600; text-align:center; }
        .day-pct   { font-size:9px;  color:rgba(255,255,255,0.2); text-align:center; }

        /* ── Student Table ── */
        table { width:100%; border-collapse:collapse; }
        thead tr { border-bottom:1px solid rgba(255,255,255,0.08); }
        th {
            padding:10px 14px; text-align:left;
            font-size:11px; font-weight:700; color:rgba(255,255,255,0.3);
            text-transform:uppercase; letter-spacing:0.7px;
        }
        tbody tr { border-bottom:1px solid rgba(255,255,255,0.04); transition:background 0.2s; }
        tbody tr:last-child { border-bottom:none; }
        tbody tr:hover { background:rgba(99,102,241,0.05); }
        td { padding:12px 14px; font-size:13px; color:rgba(255,255,255,0.7); vertical-align:middle; }

        .s-cell { display:flex; align-items:center; gap:10px; }
        .s-avatar {
            width:32px; height:32px; border-radius:8px; flex-shrink:0;
            background:linear-gradient(135deg,#6366f1,#8b5cf6);
            display:flex; align-items:center; justify-content:center;
            font-size:12px; font-weight:700; color:white;
        }
        .s-name  { font-weight:600; color:white; font-size:13px; }
        .s-email { font-size:11px; color:rgba(255,255,255,0.3); margin-top:1px; }

        /* Inline progress bar */
        .mini-bar-wrap { display:flex; align-items:center; gap:8px; }
        .mini-bar-track { flex:1; height:6px; background:rgba(255,255,255,0.06); border-radius:3px; overflow:hidden; min-width:80px; }
        .mini-bar-fill  { height:100%; border-radius:3px; background:linear-gradient(90deg,#6366f1,#8b5cf6); }
        .mini-pct { font-size:12px; font-weight:700; color:#a5b4fc; width:36px; text-align:right; }

        .badge { padding:3px 10px; border-radius:20px; font-size:11px; font-weight:600; }
        .badge-green  { background:rgba(16,185,129,0.12); color:#6ee7b7; }
        .badge-yellow { background:rgba(245,158,11,0.12); color:#fcd34d; }
        .badge-red    { background:rgba(239,68,68,0.12);  color:#fca5a5; }
        .badge-gray   { background:rgba(100,116,139,0.12); color:#94a3b8; }

        /* ── Subject Rows ── */
        .subject-row {
            display:flex; align-items:center; gap:12px; padding:10px 0;
            border-bottom:1px solid rgba(255,255,255,0.05);
        }
        .subject-row:last-child { border-bottom:none; }
        .subject-name { flex:1; font-size:13px; color:white; font-weight:500; }
        .subject-numbers { font-size:11px; color:rgba(255,255,255,0.3); white-space:nowrap; }

        /* ── Remark Cards ── */
        .remarks-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:12px; }
        .remark-card {
            background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.07);
            border-radius:12px; padding:16px;
        }
        .remark-header { display:flex; align-items:center; gap:8px; margin-bottom:10px; }
        .remark-avatar {
            width:28px; height:28px; border-radius:7px; flex-shrink:0;
            background:linear-gradient(135deg,#6366f1,#8b5cf6);
            display:flex; align-items:center; justify-content:center;
            font-size:10px; font-weight:700; color:white;
        }
        .remark-meta { flex:1; }
        .remark-username { font-size:12px; font-weight:600; color:#a5b4fc; }
        .remark-subject  { font-size:11px; color:rgba(255,255,255,0.3); }
        .remark-text {
            font-size:13px; color:rgba(255,255,255,0.7); line-height:1.5;
            font-style:italic;
        }
        .remark-date { font-size:10px; color:rgba(255,255,255,0.2); margin-top:8px; }

        /* ── At-Risk ── */
        .at-risk-row {
            display:flex; align-items:center; gap:10px; padding:10px 0;
            border-bottom:1px solid rgba(255,255,255,0.05);
        }
        .at-risk-row:last-child { border-bottom:none; }
        .at-risk-name { flex:1; font-size:13px; font-weight:600; color:white; }
        .at-risk-email { font-size:11px; color:rgba(255,255,255,0.3); }
        .at-risk-badge {
            padding:3px 10px; border-radius:20px; font-size:10px; font-weight:700;
            background:rgba(239,68,68,0.12); color:#fca5a5;
            white-space:nowrap;
        }

        /* ── Empty ── */
        .empty-msg {
            text-align:center; padding:30px; color:rgba(255,255,255,0.2); font-size:13px;
        }
        .empty-msg i { font-size:28px; display:block; margin-bottom:10px; opacity:0.3; }

        /* ── Scrollbar ── */
        ::-webkit-scrollbar { width:6px; }
        ::-webkit-scrollbar-track { background:rgba(255,255,255,0.02); }
        ::-webkit-scrollbar-thumb { background:rgba(99,102,241,0.3); border-radius:3px; }

        @media(max-width:1200px) { .two-col,.three-col { grid-template-columns:1fr; } }
        @media(max-width:768px) {
            .sidebar { display:none; }
            .main { margin-left:0; }
            .page-body { padding:20px; }
            .stats-grid { grid-template-columns:repeat(2,1fr); }
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-logo">
        <div class="logo-icon"><i class="fa-solid fa-shield-halved" style="color:white;"></i></div>
        <h2>Admin Panel</h2>
        <p>Study Planner Management</p>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section-title">Main</div>
        <a href="admin_dashboard.php" class="nav-item"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
        <div class="nav-section-title">Management</div>
        <a href="admin_students.php"   class="nav-item"><i class="fa-solid fa-users"></i> All Students</a>
        <a href="admin_timetables.php" class="nav-item"><i class="fa-solid fa-calendar-week"></i> AI Timetables</a>
        <a href="admin_progress.php"   class="nav-item active"><i class="fa-solid fa-chart-line"></i> Progress Reports</a>
        <div class="nav-section-title">System</div>
        <a href="../student/login.php" class="nav-item" target="_blank"><i class="fa-solid fa-arrow-up-right-from-square"></i> Student Portal</a>
    </nav>
    <div class="sidebar-footer">
        <div class="admin-info">
            <div class="admin-avatar"><?php echo strtoupper(substr($admin_username, 0, 1)); ?></div>
            <div class="admin-info-text">
                <p><?php echo htmlspecialchars($admin_username); ?></p>
                <span>Administrator</span>
            </div>
        </div>
        <a href="admin_logout.php" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i> Sign Out</a>
    </div>
</div>

<!-- Main -->
<div class="main">
    <div class="topbar">
        <div class="topbar-title">
            <h1>Progress Reports</h1>
            <p>Weekly completion analysis across all students</p>
        </div>
    </div>

    <div class="page-body">

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(99,102,241,0.15);">
                    <i class="fa-solid fa-calendar-check" style="color:#818cf8;"></i>
                </div>
                <div class="stat-value"><?php echo $total_sessions; ?></div>
                <div class="stat-label">Total Sessions</div>
                <div class="stat-sub">This week</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(16,185,129,0.12);">
                    <i class="fa-solid fa-circle-check" style="color:#34d399;"></i>
                </div>
                <div class="stat-value"><?php echo $total_done; ?></div>
                <div class="stat-label">Completed</div>
                <div class="stat-sub"><?php echo $overall_pct; ?>% completion rate</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(245,158,11,0.12);">
                    <i class="fa-solid fa-hourglass-half" style="color:#fbbf24;"></i>
                </div>
                <div class="stat-value"><?php echo $total_pending; ?></div>
                <div class="stat-label">Pending</div>
                <div class="stat-sub">Not yet completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(239,68,68,0.12);">
                    <i class="fa-solid fa-triangle-exclamation" style="color:#f87171;"></i>
                </div>
                <div class="stat-value"><?php echo $at_risk; ?></div>
                <div class="stat-label">At-Risk Students</div>
                <div class="stat-sub">0 sessions done this week</div>
            </div>
        </div>

        <!-- Week Navigation -->
        <div class="week-nav">
            <a href="?week=<?php echo $prev_week; ?>" class="week-btn">
                <i class="fa-solid fa-chevron-left"></i> Prev Week
            </a>
            <div class="week-label">
                <?php echo date('d M', strtotime($week_start)); ?> – <?php echo date('d M Y', strtotime($week_end)); ?>
                <?php if ($week_start === $curr_week): ?>
                    <span style="background:rgba(10, 10, 10, 0.79); color:#a5b4fc; padding:2px 10px; border-radius:20px; font-size:11px; margin-left:8px;">This Week</span>
                <?php endif; ?>
            </div>
            <a href="?week=<?php echo $next_week; ?>" class="week-btn">
                Next Week <i class="fa-solid fa-chevron-right"></i>
            </a>
        </div>

        <!-- Row 1: Overall Progress + Daily Chart -->
        <div class="two-col">

            <!-- Overall Completion -->
            <div class="card">
                <div class="card-title"><i class="fa-solid fa-bullseye"></i> Overall Completion Rate</div>
                <div class="big-progress">
                    <div class="big-pct"><?php echo $overall_pct; ?>%</div>
                    <div class="big-label">of all sessions completed this week</div>
                    <div class="progress-track">
                        <div class="progress-fill" style="width:<?php echo $overall_pct; ?>%;"></div>
                    </div>
                    <div class="progress-sub"><?php echo $total_done; ?> done out of <?php echo $total_sessions; ?> total sessions</div>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-top:16px;">
                    <div style="background:rgba(16,185,129,0.08); border:1px solid rgba(16,185,129,0.15); border-radius:10px; padding:14px; text-align:center;">
                        <div style="font-size:22px; font-weight:800; color:#34d399;"><?php echo $active_students; ?></div>
                        <div style="font-size:11px; color:rgba(255,255,255,0.3); margin-top:3px;">Active Students</div>
                    </div>
                    <div style="background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.15); border-radius:10px; padding:14px; text-align:center;">
                        <div style="font-size:22px; font-weight:800; color:#f87171;"><?php echo $at_risk; ?></div>
                        <div style="font-size:11px; color:rgba(255,255,255,0.3); margin-top:3px;">At-Risk Students</div>
                    </div>
                </div>
            </div>

            <!-- Daily Chart -->
            <div class="card">
                <div class="card-title"><i class="fa-solid fa-chart-bar"></i> Daily Completion This Week</div>
                <?php $max_total = max(array_column($daily_stats, 'total') ?: [1]); ?>
                <div class="daily-chart">
                    <?php foreach ($daily_stats as $ds):
                        $bar_pct = $max_total > 0 ? ($ds['total'] / $max_total * 100) : 0;
                        $fill_pct = $ds['total'] > 0 ? ($ds['done'] / $ds['total'] * 100) : 0;
                        $is_today = $ds['date'] === date('Y-m-d');
                    ?>
                    <div class="day-bar-wrap">
                        <div style="font-size:9px; color:rgba(255,255,255,0.25); text-align:center;">
                            <?php echo $ds['done']; ?>/<?php echo $ds['total']; ?>
                        </div>
                        <div class="day-bar-track" style="position:relative;">
                            <!-- total bar -->
                            <div style="position:absolute; bottom:0; width:100%; height:<?php echo $bar_pct; ?>%; background:rgba(99,102,241,0.12); border-radius:4px;"></div>
                            <!-- done bar -->
                            <div style="position:absolute; bottom:0; width:100%; height:<?php echo ($bar_pct * $fill_pct / 100); ?>%; background:linear-gradient(180deg,#6366f1,#8b5cf6); border-radius:4px; min-height:<?php echo $ds['done'] > 0 ? '3' : '0'; ?>px;"></div>
                        </div>
                        <div class="day-label" style="color:<?php echo $is_today ? '#a5b4fc' : 'rgba(255,255,255,0.3)'; ?>; font-weight:<?php echo $is_today ? '700' : '600'; ?>;">
                            <?php echo $ds['day']; ?>
                        </div>
                        <div class="day-pct"><?php echo $ds['pct']; ?>%</div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div style="display:flex; gap:16px; margin-top:16px; font-size:11px; color:rgba(255,255,255,0.3);">
                    <div style="display:flex; align-items:center; gap:5px;">
                        <div style="width:10px; height:10px; background:rgba(99,102,241,0.2); border-radius:2px;"></div> Total Sessions
                    </div>
                    <div style="display:flex; align-items:center; gap:5px;">
                        <div style="width:10px; height:10px; background:#6366f1; border-radius:2px;"></div> Completed
                    </div>
                </div>
            </div>
        </div>

        <!-- Row 2: Student Leaderboard + Subject Breakdown -->
        <div class="two-col">

            <!-- Student Completion Table -->
            <div class="card">
                <div class="card-title">
                    <i class="fa-solid fa-trophy"></i> Student Completion Leaderboard
                </div>
                <?php if (empty($student_stats)): ?>
                    <div class="empty-msg">
                        <i class="fa-solid fa-users-slash"></i>
                        No student data for this week.
                    </div>
                <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Student</th>
                            <th>Progress</th>
                            <th>Done</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($student_stats as $i => $st):
                            $pct = $st['total_sessions'] > 0 ? round($st['done_sessions'] / $st['total_sessions'] * 100) : 0;
                            if ($pct === 100)     $statusClass = 'badge-green';
                            elseif ($pct >= 50)  $statusClass = 'badge-yellow';
                            elseif ($pct > 0)    $statusClass = 'badge-red';
                            else                 $statusClass = 'badge-gray';
                        ?>
                        <tr>
                            <td style="color:rgba(255,255,255,0.2); font-size:12px; font-weight:700;">
                                <?php if ($i === 0): ?>🥇
                                <?php elseif ($i === 1): ?>🥈
                                <?php elseif ($i === 2): ?>🥉
                                <?php else: echo $i + 1; endif; ?>
                            </td>
                            <td>
                                <div class="s-cell">
                                    <div class="s-avatar"><?php echo strtoupper(substr($st['username'], 0, 1)); ?></div>
                                    <div>
                                        <div class="s-name"><?php echo htmlspecialchars($st['username']); ?></div>
                                        <div class="s-email"><?php echo htmlspecialchars($st['email'] ?? ''); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="mini-bar-wrap">
                                    <div class="mini-bar-track">
                                        <div class="mini-bar-fill" style="width:<?php echo $pct; ?>%;"></div>
                                    </div>
                                    <div class="mini-pct"><?php echo $pct; ?>%</div>
                                </div>
                            </td>
                            <td style="text-align:center; font-weight:600; color:white;">
                                <?php echo $st['done_sessions']; ?>/<?php echo $st['total_sessions']; ?>
                            </td>
                            <td>
                                <span class="badge <?php echo $statusClass; ?>">
                                    <?php
                                    if ($pct === 100)     echo '✓ Perfect';
                                    elseif ($pct >= 50)  echo '↑ Good';
                                    elseif ($pct > 0)    echo '↓ Low';
                                    else                 echo '✕ None';
                                    ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

            <!-- Subject Completion -->
            <div class="card">
                <div class="card-title"><i class="fa-solid fa-book-open"></i> Subject Completion Rate</div>
                <?php if (empty($subject_stats)): ?>
                    <div class="empty-msg">
                        <i class="fa-solid fa-book-skull"></i>
                        No subject data for this week.
                    </div>
                <?php else: ?>
                    <?php foreach ($subject_stats as $sub):
                        $sub_pct = $sub['total'] > 0 ? round($sub['done'] / $sub['total'] * 100) : 0;
                        if ($sub_pct >= 70)      $bar_color = '#10b981';
                        elseif ($sub_pct >= 40)  $bar_color = '#f59e0b';
                        else                     $bar_color = '#ef4444';
                        $avg_conf = $sub['avg_conf'] ? round($sub['avg_conf']) : null;
                    ?>
                    <div class="subject-row">
                        <div style="flex:1;">
                            <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                                <span class="subject-name"><?php echo htmlspecialchars($sub['subject']); ?></span>
                                <span class="subject-numbers">
                                    <?php echo $sub['done']; ?>/<?php echo $sub['total']; ?>
                                    <?php if ($avg_conf): ?>
                                        <span style="color:rgba(255,255,255,0.2); margin-left:4px;">· <?php echo $avg_conf; ?>% conf</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <div class="mini-bar-track" style="flex:1;">
                                    <div class="mini-bar-fill" style="width:<?php echo $sub_pct; ?>%; background:<?php echo $bar_color; ?>;"></div>
                                </div>
                                <span style="font-size:12px; font-weight:700; color:<?php echo $bar_color; ?>; width:34px; text-align:right;"><?php echo $sub_pct; ?>%</span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Row 3: At-Risk Students + Recent Remarks -->
        <div class="two-col">

            <!-- At-Risk Students -->
            <div class="card">
                <div class="card-title">
                    <i class="fa-solid fa-triangle-exclamation" style="color:#f87171;"></i>
                    At-Risk Students
                    <span style="background:rgba(239,68,68,0.12); color:#fca5a5; padding:2px 8px; border-radius:20px; font-size:11px; margin-left:4px;">
                        <?php echo count($at_risk_students); ?> students
                    </span>
                </div>
                <?php if (empty($at_risk_students)): ?>
                    <div class="empty-msg">
                        <i class="fa-solid fa-party-horn" style="color:#34d399;"></i>
                        <p style="color:#6ee7b7;">All students completed at least one session! 🎉</p>
                    </div>
                <?php else: ?>
                    <p style="font-size:12px; color:rgba(255,255,255,0.25); margin-bottom:14px;">
                        These students have a generated plan this week but haven't completed any sessions yet.
                    </p>
                    <?php foreach ($at_risk_students as $ar): ?>
                    <div class="at-risk-row">
                        <div class="s-avatar" style="width:32px; height:32px; border-radius:8px; font-size:12px; flex-shrink:0;">
                            <?php echo strtoupper(substr($ar['username'], 0, 1)); ?>
                        </div>
                        <div style="flex:1;">
                            <div class="at-risk-name"><?php echo htmlspecialchars($ar['username']); ?></div>
                            <div class="at-risk-email"><?php echo htmlspecialchars($ar['email'] ?? ''); ?></div>
                        </div>
                        <span class="at-risk-badge"><?php echo $ar['total_sessions']; ?> sessions pending</span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Recent Remarks -->
            <div class="card">
                <div class="card-title"><i class="fa-solid fa-comments"></i> Recent Student Remarks</div>
                <?php if (empty($remarks)): ?>
                    <div class="empty-msg">
                        <i class="fa-regular fa-comment-dots"></i>
                        No remarks submitted this week.
                    </div>
                <?php else: ?>
                    <div style="display:flex; flex-direction:column; gap:10px; max-height:340px; overflow-y:auto; padding-right:4px;">
                        <?php foreach ($remarks as $r): ?>
                        <div class="remark-card">
                            <div class="remark-header">
                                <div class="remark-avatar"><?php echo strtoupper(substr($r['username'], 0, 1)); ?></div>
                                <div class="remark-meta">
                                    <div class="remark-username"><?php echo htmlspecialchars($r['username']); ?></div>
                                    <div class="remark-subject"><?php echo htmlspecialchars($r['subject']); ?></div>
                                </div>
                                <span class="badge badge-green" style="font-size:10px;">Done</span>
                            </div>
                            <div class="remark-text">"<?php echo htmlspecialchars($r['remark']); ?>"</div>
                            <div class="remark-date">
                                <i class="fa-solid fa-clock" style="margin-right:3px;"></i>
                                <?php echo date('D, d M', strtotime($r['study_date'])); ?>
                                · <?php echo date('h:i A', strtotime($r['start_time'])); ?>–<?php echo date('h:i A', strtotime($r['end_time'])); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>

    </div>
</div>

</body>
</html>