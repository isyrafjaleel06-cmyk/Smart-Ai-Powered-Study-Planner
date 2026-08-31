<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

require_once '../config.php';

$admin_username = $_SESSION['admin_username'];

// ── Filters ────────────────────────────────────────────────────────────────
$filter_student = trim($_GET['student'] ?? '');
$filter_week    = $_GET['week'] ?? date('Y-m-d', strtotime('monday this week'));
$filter_status  = $_GET['status'] ?? 'all';

// Calculate week start/end
$week_start = date('Y-m-d', strtotime('monday this week', strtotime($filter_week)));
$week_end   = date('Y-m-d', strtotime('sunday this week', strtotime($filter_week)));

// ── Stats ──────────────────────────────────────────────────────────────────
$total_this_week = $conn->prepare("SELECT COUNT(*) as c FROM ai_personal_study_timetable WHERE study_date BETWEEN ? AND ?");
$total_this_week->bind_param("ss", $week_start, $week_end);
$total_this_week->execute();
$total_sessions = $total_this_week->get_result()->fetch_assoc()['c'];
$total_this_week->close();

$done_this_week = $conn->prepare("SELECT COUNT(*) as c FROM ai_personal_study_timetable WHERE study_date BETWEEN ? AND ? AND is_completed = 1");
$done_this_week->bind_param("ss", $week_start, $week_end);
$done_this_week->execute();
$done_sessions = $done_this_week->get_result()->fetch_assoc()['c'];
$done_this_week->close();

$students_with_plan = $conn->prepare("SELECT COUNT(DISTINCT student_id) as c FROM ai_personal_study_timetable WHERE study_date BETWEEN ? AND ?");
$students_with_plan->bind_param("ss", $week_start, $week_end);
$students_with_plan->execute();
$active_students = $students_with_plan->get_result()->fetch_assoc()['c'];
$students_with_plan->close();

$pending_sessions = $total_sessions - $done_sessions;
$completion_pct   = $total_sessions > 0 ? round($done_sessions / $total_sessions * 100) : 0;

// ── Fetch all students for dropdown ───────────────────────────────────────
$student_list = $conn->query("SELECT student_id, username FROM student ORDER BY username")->fetch_all(MYSQLI_ASSOC);

// ── Fetch timetable data ───────────────────────────────────────────────────
$where  = ["t.study_date BETWEEN '$week_start' AND '$week_end'"];
$params = [];
$types  = "";

if ($filter_student) {
    $where[]  = "s.username LIKE ?";
    $params[] = "%$filter_student%";
    $types   .= "s";
}
if ($filter_status === 'done') {
    $where[] = "t.is_completed = 1";
} elseif ($filter_status === 'pending') {
    $where[] = "t.is_completed = 0";
}

$where_sql = implode(" AND ", $where);

$sql = "
    SELECT t.timetable_id, t.student_id, t.subject, t.study_date,
           t.start_time, t.end_time, t.is_completed,
           s.username, s.email,
           ps.confidence_level
    FROM ai_personal_study_timetable t
    JOIN student s ON s.student_id = t.student_id
    LEFT JOIN personal_study_plan ps ON ps.student_id = t.student_id AND ps.subject = t.subject
    WHERE $where_sql
    ORDER BY t.study_date ASC, t.start_time ASC, s.username ASC
";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result    = $stmt->get_result();
$timetables = [];
while ($r = $result->fetch_assoc()) $timetables[] = $r;
$stmt->close();

// Group by day
$by_day = [];
$days   = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
foreach ($days as $d) $by_day[$d] = [];
foreach ($timetables as $t) {
    $day_name = date('l', strtotime($t['study_date']));
    $by_day[$day_name][] = $t;
}

$conn->close();

function confColor($c) {
    if ($c === null) return ['#94a3b8', 'rgba(100,116,139,0.12)'];
    if ($c <= 30)    return ['#ef4444', 'rgba(239,68,68,0.12)'];
    if ($c <= 70)    return ['#f59e0b', 'rgba(245,158,11,0.12)'];
    return                 ['#10b981', 'rgba(16,185,129,0.12)'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Timetables - Admin Panel</title>
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
            color:rgba(255,255,255,0.5); font-size:14px; font-weight:500;
            margin-bottom:3px;
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

        /* ── Stats ── */
        .stats-grid {
            display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
            gap:16px; margin-bottom:26px;
        }
        .stat-card {
            background:linear-gradient(135deg,rgba(30,27,75,0.8),rgba(26,26,46,0.8));
            border:1px solid rgba(255,255,255,0.07);
            border-radius:14px; padding:20px 22px;
            animation:fadeInUp 0.5s ease forwards; opacity:0;
        }
        .stat-card:nth-child(1){animation-delay:0.05s}
        .stat-card:nth-child(2){animation-delay:0.10s}
        .stat-card:nth-child(3){animation-delay:0.15s}
        .stat-card:nth-child(4){animation-delay:0.20s}
        @keyframes fadeInUp { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }
        .stat-icon {
            width:40px; height:40px; border-radius:10px;
            display:flex; align-items:center; justify-content:center;
            font-size:16px; margin-bottom:14px;
        }
        .stat-value { font-size:30px; font-weight:800; color:white; margin-bottom:3px; }
        .stat-label { font-size:11px; color:rgba(255,255,255,0.35); font-weight:600; text-transform:uppercase; letter-spacing:0.6px; }

        /* ── Progress Bar ── */
        .progress-wrap {
            background:linear-gradient(135deg,rgba(30,27,75,0.8),rgba(26,26,46,0.8));
            border:1px solid rgba(255,255,255,0.07); border-radius:14px;
            padding:20px 24px; margin-bottom:26px;
            animation:fadeInUp 0.5s ease 0.25s forwards; opacity:0;
        }
        .progress-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; }
        .progress-header span { font-size:13px; color:rgba(255,255,255,0.5); font-weight:500; }
        .progress-header strong { font-size:14px; color:white; }
        .progress-track {
            width:100%; height:10px; background:rgba(255,255,255,0.06);
            border-radius:5px; overflow:hidden;
        }
        .progress-fill {
            height:100%; border-radius:5px;
            background:linear-gradient(90deg,#6366f1,#8b5cf6);
            transition:width 1s ease;
        }

        /* ── Filter Bar ── */
        .filter-bar {
            background:linear-gradient(135deg,rgba(25, 25, 58, 0.77),rgba(36, 36, 85, 0.71));
            border:1px solid rgba(255,255,255,0.07); border-radius:14px;
            padding:18px 22px; margin-bottom:22px;
            display:flex; gap:14px; flex-wrap:wrap; align-items:flex-end;
            animation:fadeInUp 0.5s ease 0.3s forwards; opacity:0;
        }
        .filter-group { display:flex; flex-direction:column; gap:6px; }
        .filter-group label {
            font-size:10px; font-weight:700; color:rgba(255,255,255,0.3);
            text-transform:uppercase; letter-spacing:0.8px;
        }
        .filter-group input,
        .filter-group select {
            background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.1);
            border-radius:8px; padding:8px 12px; color:white; font-size:13px;
            font-family:inherit; outline:none; min-width:160px;
            transition:border-color 0.2s;
        }
        .filter-group input:focus,
        .filter-group select:focus { border-color:#6366f1; }
        .filter-group select option { background:#1e1b4b; }
        .btn-filter {
            padding:9px 20px; border-radius:8px; font-size:13px; font-weight:600;
            background:linear-gradient(135deg,#6366f1,#8b5cf6);
            color:white; border:none; cursor:pointer;
            box-shadow:0 4px 14px rgba(99,102,241,0.35);
            transition:all 0.2s; display:flex; align-items:center; gap:7px;
        }
        .btn-filter:hover { transform:translateY(-1px); box-shadow:0 6px 20px rgba(99,102,241,0.5); }
        .btn-reset {
            padding:9px 16px; border-radius:8px; font-size:13px; font-weight:600;
            background:rgba(255,255,255,0.06); color:rgba(255,255,255,0.5);
            border:1px solid rgba(255,255,255,0.1); cursor:pointer; text-decoration:none;
            display:flex; align-items:center; gap:7px; transition:all 0.2s;
        }
        .btn-reset:hover { background:rgba(255,255,255,0.1); color:white; }

        /* ── Week Navigation ── */
        .week-nav {
            display:flex; align-items:center; gap:12px; margin-bottom:22px;
        }
        .week-btn {
            padding:8px 16px; border-radius:8px; font-size:12px; font-weight:600;
            background:rgba(9, 9, 10, 0.68); border:1px solid rgba(99,102,241,0.2);
            color:#a5b4fc; cursor:pointer; text-decoration:none;
            display:flex; align-items:center; gap:6px; transition:all 0.2s;
        }
        .week-btn:hover { background:rgba(99,102,241,0.25); }
        .week-label {
            flex:1; text-align:center; font-size:14px; font-weight:700; color:white;
            background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08);
            border-radius:10px; padding:10px 20px;
        }

        /* ── Day Tabs ── */
        .day-tabs {
            display:flex; gap:6px; margin-bottom:20px; flex-wrap:wrap;
        }
        .day-tab {
            padding:8px 16px; border-radius:8px; font-size:12px; font-weight:600;
            background:rgba(17, 17, 17, 0.7); border:1px solid rgba(255, 255, 255, 0.59);
            color:rgba(255, 255, 255, 0.94); cursor:pointer; transition:all 0.2s;
            display:flex; align-items:center; gap:6px;
        }
        .day-tab:hover { background:rgba(99,102,241,0.15); color:#a5b4fc; }
        .day-tab.active {
            background:linear-gradient(135deg,rgba(99,102,241,0.3),rgba(139,92,246,0.2));
            border-color:rgba(99,102,241,0.4); color:#a5b4fc;
        }
        .day-tab .count {
            background:rgba(99,102,241,0.2); color:#a5b4fc;
            padding:1px 7px; border-radius:10px; font-size:10px;
        }
        .day-tab.active .count { background:rgba(99,102,241,0.4); }

        /* ── Day Panel ── */
        .day-panel { display:none; }
        .day-panel.active { display:block; animation:fadeInUp 0.3s ease; }

        /* ── Session Cards ── */
        .sessions-grid {
            display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr));
            gap:14px;
        }
        .session-card {
            background:linear-gradient(135deg,rgba(8, 8, 8, 0.67),rgba(26, 26, 46, 0.74));
            border:1px solid rgba(255,255,255,0.07); border-radius:14px;
            padding:18px; transition:all 0.2s;
            border-left:4px solid transparent;
        }
        .session-card:hover { transform:translateY(-2px); border-color:rgba(99,102,241,0.4); box-shadow:0 8px 24px rgba(0,0,0,0.3); }
        .session-card.completed { border-left-color:#10b981; opacity:0.8; }
        .session-card.pending   { border-left-color:#f59e0b; }
        .session-card.critical  { border-left-color:#ef4444; }

        .sc-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px; }
        .sc-subject { font-size:14px; font-weight:700; color:white; line-height:1.3; }
        .sc-status {
            padding:3px 10px; border-radius:20px; font-size:10px; font-weight:700;
            white-space:nowrap; flex-shrink:0; margin-left:8px;
        }
        .sc-status.done    { background:rgba(16,185,129,0.15); color:#6ee7b7; }
        .sc-status.pending { background:rgba(245,158,11,0.15); color:#fcd34d; }

        .sc-student {
            display:flex; align-items:center; gap:8px; margin-bottom:10px;
        }
        .sc-avatar {
            width:26px; height:26px; border-radius:7px; flex-shrink:0;
            background:linear-gradient(135deg,#6366f1,#8b5cf6);
            display:flex; align-items:center; justify-content:center;
            font-size:10px; font-weight:700; color:white;
        }
        .sc-username { font-size:12px; color:#a5b4fc; font-weight:600; }

        .sc-meta { display:flex; gap:12px; flex-wrap:wrap; }
        .sc-meta-item {
            display:flex; align-items:center; gap:5px;
            font-size:11px; color:rgba(255,255,255,0.35);
        }
        .sc-meta-item i { font-size:10px; }

        .sc-conf {
            display:inline-block; padding:2px 9px; border-radius:20px;
            font-size:10px; font-weight:700; margin-top:10px;
        }

        /* ── Empty ── */
        .empty-day {
            text-align:center; padding:50px 20px;
            color:rgba(255,255,255,0.2);
        }
        .empty-day i { font-size:36px; display:block; margin-bottom:12px; opacity:0.3; }
        .empty-day p { font-size:14px; }

        /* ── Table View ── */
        .view-toggle {
            display:flex; gap:6px; margin-left:auto;
        }
        .view-btn {
            padding:7px 14px; border-radius:8px; font-size:12px; font-weight:600;
            background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1);
            color:rgba(255,255,255,0.4); cursor:pointer; transition:all 0.2s;
        }
        .view-btn.active { background:rgba(99,102,241,0.2); border-color:rgba(99,102,241,0.3); color:#a5b4fc; }

        .table-view { display:none; }
        .table-view.active { display:block; }
        .card-view  { display:block; }
        .card-view.hidden  { display:none; }

        table { width:100%; border-collapse:collapse; }
        thead tr { border-bottom:1px solid rgba(255,255,255,0.08); }
        th {
            padding:11px 14px; text-align:left;
            font-size:11px; font-weight:700; color:rgba(255,255,255,0.3);
            text-transform:uppercase; letter-spacing:0.7px; white-space:nowrap;
        }
        tbody tr { border-bottom:1px solid rgba(255,255,255,0.04); transition:background 0.2s; }
        tbody tr:last-child { border-bottom:none; }
        tbody tr:hover { background:rgba(99,102,241,0.05); }
        td { padding:13px 14px; font-size:13px; color:rgba(255,255,255,0.7); vertical-align:middle; }

        .badge { padding:3px 10px; border-radius:20px; font-size:11px; font-weight:600; }
        .badge-green  { background:rgba(16,185,129,0.12); color:#6ee7b7; }
        .badge-yellow { background:rgba(245,158,11,0.12); color:#fcd34d; }
        .badge-red    { background:rgba(239,68,68,0.12);  color:#fca5a5; }

        /* ── Scrollbar ── */
        ::-webkit-scrollbar { width:6px; }
        ::-webkit-scrollbar-track { background:rgba(255,255,255,0.02); }
        ::-webkit-scrollbar-thumb { background:rgba(99,102,241,0.3); border-radius:3px; }

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
        <a href="admin_students.php"  class="nav-item"><i class="fa-solid fa-users"></i> All Students</a>
        <a href="admin_timetables.php" class="nav-item active"><i class="fa-solid fa-calendar-week"></i> AI Timetables</a>
        <a href="admin_progress.php"  class="nav-item"><i class="fa-solid fa-chart-line"></i> Progress Reports</a>
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
            <h1>AI Study Timetables</h1>
            <p>View all generated AI study plans across all students</p>
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
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(16,185,129,0.12);">
                    <i class="fa-solid fa-circle-check" style="color:#34d399;"></i>
                </div>
                <div class="stat-value"><?php echo $done_sessions; ?></div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(245,158,11,0.12);">
                    <i class="fa-solid fa-clock" style="color:#fbbf24;"></i>
                </div>
                <div class="stat-value"><?php echo $pending_sessions; ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(139,92,246,0.15);">
                    <i class="fa-solid fa-users" style="color:#a78bfa;"></i>
                </div>
                <div class="stat-value"><?php echo $active_students; ?></div>
                <div class="stat-label">Active Students</div>
            </div>
        </div>

        <!-- Progress Bar -->
        <div class="progress-wrap">
            <div class="progress-header">
                <span>Week Completion Rate</span>
                <strong><?php echo $done_sessions; ?> / <?php echo $total_sessions; ?> sessions — <?php echo $completion_pct; ?>%</strong>
            </div>
            <div class="progress-track">
                <div class="progress-fill" style="width:<?php echo $completion_pct; ?>%;"></div>
            </div>
        </div>

        <!-- Filter Bar -->
        <form method="GET" action="admin_timetables.php">
            <div class="filter-bar">
                <div class="filter-group">
                    <label>Search Student</label>
                    <input type="text" name="student" placeholder="Username..."
                           value="<?php echo htmlspecialchars($filter_student); ?>">
                </div>
                <div class="filter-group">
                    <label>Week Starting</label>
                    <input type="date" name="week" value="<?php echo $filter_week; ?>">
                </div>
                <div class="filter-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="all"     <?php echo $filter_status === 'all'     ? 'selected' : ''; ?>>All Sessions</option>
                        <option value="done"    <?php echo $filter_status === 'done'    ? 'selected' : ''; ?>>Completed Only</option>
                        <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending Only</option>
                    </select>
                </div>
                <button type="submit" class="btn-filter">
                     Apply Filter
                </button>
                <a href="admin_timetables.php" class="btn-reset">
                    <i class="fa-solid fa-xmark"></i> Reset
                </a>

                <div class="view-toggle" style="margin-left:auto;">
                    <button type="button" class="view-btn active" id="btnCard" onclick="switchView('card')">
                         Cards
                    </button>
                    <button type="button" class="view-btn" id="btnTable" onclick="switchView('table')">
                         Table
                    </button>
                </div>
            </div>
        </form>

        <!-- Week Navigation -->
        <?php
        $prev_week = date('Y-m-d', strtotime($week_start . ' -7 days'));
        $next_week = date('Y-m-d', strtotime($week_start . ' +7 days'));
        $curr_week = date('Y-m-d', strtotime('monday this week'));
        ?>
        <div class="week-nav">
            <a href="?week=<?php echo $prev_week; ?>&student=<?php echo urlencode($filter_student); ?>&status=<?php echo $filter_status; ?>" class="week-btn">
                <i class="fa-solid fa-chevron-left"></i> Prev
            </a>
            <div class="week-label">
                <?php echo date('d M', strtotime($week_start)); ?> – <?php echo date('d M Y', strtotime($week_end)); ?>
                <?php if ($week_start === $curr_week): ?>
                    <span style="background:rgba(11, 11, 12, 0.77); color:#a5b4fc; padding:2px 10px; border-radius:20px; font-size:11px; margin-left:8px;">This Week</span>
                <?php endif; ?>
            </div>
            <a href="?week=<?php echo $next_week; ?>&student=<?php echo urlencode($filter_student); ?>&status=<?php echo $filter_status; ?>" class="week-btn">
                Next <i class="fa-solid fa-chevron-right"></i>
            </a>
        </div>

        <!-- ── Card View ── -->
        <div id="cardView" class="card-view">
            <!-- Day Tabs -->
            <div class="day-tabs">
                <?php foreach ($days as $i => $day):
                    $count = count($by_day[$day]);
                    $date  = date('d/m', strtotime($week_start . ' +' . $i . ' days'));
                ?>
                <div class="day-tab <?php echo $i === 0 ? 'active' : ''; ?>"
                     onclick="switchDay('<?php echo $day; ?>')">
                    <span><?php echo substr($day, 0, 3); ?> <span style="color:rgba(255,255,255,0.2); font-size:10px;"><?php echo $date; ?></span></span>
                    <span class="count"><?php echo $count; ?></span>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Day Panels -->
            <?php foreach ($days as $i => $day): ?>
            <div class="day-panel <?php echo $i === 0 ? 'active' : ''; ?>" id="panel_<?php echo $day; ?>">
                <?php if (empty($by_day[$day])): ?>
                    <div class="empty-day">
                        <i class="fa-regular fa-calendar-xmark"></i>
                        <p>No AI study sessions on <?php echo $day; ?></p>
                    </div>
                <?php else: ?>
                    <div class="sessions-grid">
                        <?php foreach ($by_day[$day] as $t):
                            [$confColor, $confBg] = confColor($t['confidence_level']);
                            $cardClass = $t['is_completed'] ? 'completed' : ($t['confidence_level'] !== null && $t['confidence_level'] <= 30 ? 'critical' : 'pending');
                        ?>
                        <div class="session-card <?php echo $cardClass; ?>">
                            <div class="sc-header">
                                <div class="sc-subject"><?php echo htmlspecialchars($t['subject']); ?></div>
                                <span class="sc-status <?php echo $t['is_completed'] ? 'done' : 'pending'; ?>">
                                    <?php echo $t['is_completed'] ? '✓ Done' : 'Pending'; ?>
                                </span>
                            </div>
                            <div class="sc-student">
                                <div class="sc-avatar"><?php echo strtoupper(substr($t['username'], 0, 1)); ?></div>
                                <div class="sc-username"><?php echo htmlspecialchars($t['username']); ?></div>
                            </div>
                            <div class="sc-meta">
                                <div class="sc-meta-item">
                                    <i class="fa-solid fa-clock"></i>
                                    <?php echo date('h:i A', strtotime($t['start_time'])); ?> – <?php echo date('h:i A', strtotime($t['end_time'])); ?>
                                </div>
                                <div class="sc-meta-item">
                                    <i class="fa-solid fa-hourglass-half"></i>
                                    <?php echo round((strtotime($t['end_time']) - strtotime($t['start_time'])) / 60); ?> min
                                </div>
                            </div>
                            <?php if ($t['confidence_level'] !== null): ?>
                            <div class="sc-conf" style="background:<?php echo $confBg; ?>; color:<?php echo $confColor; ?>;">
                                <?php
                                if ($t['confidence_level'] <= 30)      echo 'Critical';
                                elseif ($t['confidence_level'] <= 70)  echo 'Intermediate';
                                else                                    echo 'Mastered';
                                echo ' (' . $t['confidence_level'] . '%)';
                                ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ── Table View ── -->
        <div id="tableView" class="table-view"
             style="background:linear-gradient(135deg,rgba(30,27,75,0.6),rgba(26,26,46,0.6));
                    border:1px solid rgba(255,255,255,0.07); border-radius:18px; padding:24px; overflow-x:auto;">
            <?php if (empty($timetables)): ?>
                <div style="text-align:center; padding:50px; color:rgba(255,255,255,0.2);">
                    <i class="fa-solid fa-calendar-xmark" style="font-size:32px; display:block; margin-bottom:12px; opacity:0.3;"></i>
                    No sessions found for this week.
                </div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Subject</th>
                        <th>Day</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Duration</th>
                        <th>Confidence</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($timetables as $t):
                        [$confColor, $confBg] = confColor($t['confidence_level']);
                        $duration = round((strtotime($t['end_time']) - strtotime($t['start_time'])) / 60);
                    ?>
                    <tr>
                        <td>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <div style="width:28px; height:28px; border-radius:7px; background:linear-gradient(135deg,#6366f1,#8b5cf6); display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; color:white; flex-shrink:0;">
                                    <?php echo strtoupper(substr($t['username'], 0, 1)); ?>
                                </div>
                                <span style="font-weight:600; color:white;"><?php echo htmlspecialchars($t['username']); ?></span>
                            </div>
                        </td>
                        <td style="color:white; font-weight:500;"><?php echo htmlspecialchars($t['subject']); ?></td>
                        <td style="color:#818cf8;"><?php echo date('l', strtotime($t['study_date'])); ?></td>
                        <td><?php echo date('d M Y', strtotime($t['study_date'])); ?></td>
                        <td><?php echo date('h:i A', strtotime($t['start_time'])); ?> – <?php echo date('h:i A', strtotime($t['end_time'])); ?></td>
                        <td><span class="badge" style="background:rgba(99,102,241,0.12); color:#a5b4fc;"><?php echo $duration; ?> min</span></td>
                        <td>
                            <?php if ($t['confidence_level'] !== null): ?>
                                <span class="badge" style="background:<?php echo $confBg; ?>; color:<?php echo $confColor; ?>;">
                                    <?php echo $t['confidence_level']; ?>%
                                </span>
                            <?php else: ?>
                                <span style="color:rgba(255,255,255,0.2); font-size:12px;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($t['is_completed']): ?>
                                <span class="badge badge-green">✓ Done</span>
                            <?php else: ?>
                                <span class="badge badge-yellow">⏳ Pending</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
    // Day tabs
    function switchDay(day) {
        document.querySelectorAll('.day-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.day-panel').forEach(p => p.classList.remove('active'));
        event.currentTarget.classList.add('active');
        document.getElementById('panel_' + day).classList.add('active');
    }

    // Card / Table view toggle
    function switchView(type) {
        const card  = document.getElementById('cardView');
        const table = document.getElementById('tableView');
        const btnC  = document.getElementById('btnCard');
        const btnT  = document.getElementById('btnTable');
        if (type === 'card') {
            card.classList.remove('hidden'); table.classList.remove('active');
            btnC.classList.add('active'); btnT.classList.remove('active');
        } else {
            card.classList.add('hidden'); table.classList.add('active');
            btnT.classList.add('active'); btnC.classList.remove('active');
        }
    }
</script>
</body>
</html>