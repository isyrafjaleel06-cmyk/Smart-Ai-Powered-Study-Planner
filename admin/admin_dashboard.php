<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

require_once '../config.php';

$admin_username = $_SESSION['admin_username'];

// ── Stats ──────────────────────────────────────────────────────────────────

// Total students
$total_students = $conn->query("SELECT COUNT(*) as total FROM student")->fetch_assoc()['total'];

// Total subjects across all students
$total_subjects = $conn->query("SELECT COUNT(*) as total FROM personal_study_plan WHERE subject IS NOT NULL")->fetch_assoc()['total'];

// Total AI sessions generated this week
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end   = date('Y-m-d', strtotime('sunday this week'));
$ai_stmt = $conn->prepare("SELECT COUNT(*) as total FROM ai_personal_study_timetable WHERE study_date BETWEEN ? AND ?");
$ai_stmt->bind_param("ss", $week_start, $week_end);
$ai_stmt->execute();
$total_ai_sessions = $ai_stmt->get_result()->fetch_assoc()['total'];
$ai_stmt->close();

// Total completed sessions
$total_completed = $conn->query("SELECT COUNT(*) as total FROM progress WHERE status = 'Done'")->fetch_assoc()['total'];

// Total class timetable entries
$total_classes = $conn->query("SELECT COUNT(*) as total FROM class_timetable")->fetch_assoc()['total'];

// New students this month
$month_start = date('Y-m-01');
// We count all students for now since no created_at in student table
// If you have created_at, use: WHERE created_at >= '$month_start'

// ── Recent Students (latest 8) ─────────────────────────────────────────────
$students_query = $conn->query("
    SELECT s.student_id, s.username, s.email,
           s.wake_up_time, s.sleep_time, s.max_study_hours,
           s.preferred_time,
           COUNT(DISTINCT psp.plan_id) as subject_count,
           COUNT(DISTINCT ct.class_id) as class_count
    FROM student s
    LEFT JOIN personal_study_plan psp ON psp.student_id = s.student_id AND psp.subject IS NOT NULL
    LEFT JOIN class_timetable ct ON ct.student_id = s.student_id
    GROUP BY s.student_id
    ORDER BY s.student_id DESC
    LIMIT 8
");
$recent_students = [];
while ($row = $students_query->fetch_assoc()) $recent_students[] = $row;

// ── Confidence distribution ────────────────────────────────────────────────
$critical     = $conn->query("SELECT COUNT(*) as c FROM personal_study_plan WHERE confidence_level <= 30 AND subject IS NOT NULL")->fetch_assoc()['c'];
$intermediate = $conn->query("SELECT COUNT(*) as c FROM personal_study_plan WHERE confidence_level > 30 AND confidence_level <= 70 AND subject IS NOT NULL")->fetch_assoc()['c'];
$mastered     = $conn->query("SELECT COUNT(*) as c FROM personal_study_plan WHERE confidence_level > 70 AND subject IS NOT NULL")->fetch_assoc()['c'];

// ── Top subjects by count ──────────────────────────────────────────────────
$top_subjects_query = $conn->query("
    SELECT subject, COUNT(*) as count
    FROM personal_study_plan
    WHERE subject IS NOT NULL
    GROUP BY subject
    ORDER BY count DESC
    LIMIT 5
");
$top_subjects = [];
while ($row = $top_subjects_query->fetch_assoc()) $top_subjects[] = $row;

// ── Delete student ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_student'])) {
    $del_id = (int)$_POST['student_id'];
    // Delete related records first
    foreach (['ai_personal_study_timetable','personal_study_plan','class_timetable','progress'] as $tbl) {
        $d = $conn->prepare("DELETE FROM $tbl WHERE student_id = ?");
        $d->bind_param("i", $del_id);
        $d->execute();
        $d->close();
    }
    $d2 = $conn->prepare("DELETE FROM student WHERE student_id = ?");
    $d2->bind_param("i", $del_id);
    $d2->execute();
    $d2->close();
    header("Location: admin_dashboard.php?deleted=1");
    exit();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Smart AI-Powered Study Planner</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background:rgb(159, 180, 230);
            min-height: 100vh; color: #e2e8f0;
            display: flex; overflow-x: hidden;
        }

        /* ── Sidebar ── */
        .sidebar {
            width: 260px; min-height: 100vh;
            background: linear-gradient(180deg, #1e1b4b 0%, #1a1a2e 100%);
            border-right: 1px solid rgba(99,102,241,0.15);
            display: flex; flex-direction: column;
            position: fixed; top: 0; left: 0; z-index: 50;
            box-shadow: 4px 0 20px rgba(0,0,0,0.3);
        }

        .sidebar-logo {
            padding: 28px 24px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        .sidebar-logo .logo-icon {
            width: 44px; height: 44px; border-radius: 12px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; margin-bottom: 10px;
            box-shadow: 0 4px 15px rgba(99,102,241,0.4);
        }
        .sidebar-logo h2 {
            font-size: 14px; font-weight: 700; color: white; line-height: 1.3;
        }
        .sidebar-logo p { font-size: 11px; color: rgba(255,255,255,0.3); margin-top: 2px; }

        .sidebar-nav { flex: 1; padding: 20px 12px; }
        .nav-section-title {
            font-size: 10px; font-weight: 700; color: rgba(255,255,255,0.25);
            text-transform: uppercase; letter-spacing: 1.2px;
            padding: 0 12px; margin-bottom: 8px; margin-top: 20px;
        }
        .nav-section-title:first-child { margin-top: 0; }

        .nav-item {
            display: flex; align-items: center; gap: 12px;
            padding: 11px 14px; border-radius: 10px; cursor: pointer;
            transition: all 0.2s ease; text-decoration: none;
            color: rgba(255,255,255,0.5); font-size: 14px; font-weight: 500;
            margin-bottom: 3px;
        }
        .nav-item i { width: 18px; text-align: center; font-size: 15px; }
        .nav-item:hover { background: rgba(99,102,241,0.15); color: #a5b4fc; }
        .nav-item.active {
            background: linear-gradient(135deg, rgba(99,102,241,0.3), rgba(139,92,246,0.2));
            color: #a5b4fc; border: 1px solid rgba(99,102,241,0.3);
        }

        .sidebar-footer {
            padding: 16px 20px;
            border-top: 1px solid rgba(255,255,255,0.06);
        }
        .admin-info {
            display: flex; align-items: center; gap: 10px; margin-bottom: 14px;
        }
        .admin-avatar {
            width: 36px; height: 36px; border-radius: 10px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            display: flex; align-items: center; justify-content: center;
            font-size: 14px; font-weight: 700; color: white;
            flex-shrink: 0;
        }
        .admin-info-text p { font-size: 13px; font-weight: 600; color: white; }
        .admin-info-text span { font-size: 11px; color: rgba(255,255,255,0.35); }

        .logout-btn {
            display: flex; align-items: center; gap: 8px;
            padding: 9px 14px; border-radius: 8px; width: 100%;
            background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2);
            color: #fca5a5; font-size: 13px; font-weight: 600;
            cursor: pointer; text-decoration: none; transition: all 0.2s ease;
        }
        .logout-btn:hover { background: rgba(239,68,68,0.2); border-color: rgba(239,68,68,0.4); }

        /* ── Main Content ── */
        .main {
            margin-left: 260px; flex: 1;
            min-height: 100vh; display: flex; flex-direction: column;
        }

        /* ── Top Bar ── */
        .topbar {
            padding: 20px 30px;
            background: rgba(15,23,42,0.8); backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255,255,255,0.05);
            display: flex; justify-content: space-between; align-items: center;
            position: sticky; top: 0; z-index: 40;
        }
        .topbar-title h1 { font-size: 22px; font-weight: 700; color: white; }
        .topbar-title p  { font-size: 13px; color: rgba(255,255,255,0.35); margin-top: 2px; }

        .topbar-right { display: flex; align-items: center; gap: 14px; }
        .date-badge {
            padding: 6px 14px; border-radius: 8px;
            background: rgba(99,102,241,0.1); border: 1px solid rgba(99,102,241,0.2);
            font-size: 12px; color: #a5b4fc; font-weight: 600;
        }

        /* ── Page Body ── */
        .page-body { padding: 28px 30px; flex: 1; }

        /* ── Alert ── */
        .alert-success {
            padding: 14px 18px; border-radius: 12px; margin-bottom: 24px;
            background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.25);
            color: #6ee7b7; font-size: 14px; font-weight: 500;
            display: flex; align-items: center; gap: 10px;
            animation: slideDown 0.4s ease;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── Stats Grid ── */
        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 18px; margin-bottom: 28px;
        }

        .stat-card {
            background: linear-gradient(135deg, rgba(30,27,75,0.8), rgba(26,26,46,0.8));
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 16px; padding: 22px 24px;
            transition: all 0.3s ease; cursor: default;
            animation: fadeInUp 0.5s ease forwards; opacity: 0;
        }
        .stat-card:nth-child(1) { animation-delay: 0.05s; }
        .stat-card:nth-child(2) { animation-delay: 0.10s; }
        .stat-card:nth-child(3) { animation-delay: 0.15s; }
        .stat-card:nth-child(4) { animation-delay: 0.20s; }
        .stat-card:nth-child(5) { animation-delay: 0.25s; }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .stat-card:hover { transform: translateY(-3px); border-color: rgba(99,102,241,0.3); box-shadow: 0 12px 30px rgba(0,0,0,0.3); }

        .stat-icon {
            width: 44px; height: 44px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; margin-bottom: 16px;
        }
        .stat-value { font-size: 36px; font-weight: 800; color: white; margin-bottom: 4px; line-height: 1; }
        .stat-label { font-size: 12px; color: rgba(255,255,255,0.4); font-weight: 600; text-transform: uppercase; letter-spacing: 0.6px; }
        .stat-sub   { font-size: 11px; color: rgba(255,255,255,0.25); margin-top: 6px; }

        /* ── Two Column Grid ── */
        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px; }

        /* ── Card ── */
        .card {
            background: linear-gradient(135deg, rgba(16, 15, 27, 0.77), rgba(21, 21, 32, 0.75));
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 18px; padding: 24px;
            animation: fadeInUp 0.5s ease 0.3s forwards; opacity: 0;
        }
        .card-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 20px; padding-bottom: 16px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        .card-title {
            font-size: 15px; font-weight: 700; color: white;
            display: flex; align-items: center; gap: 10px;
        }
        .card-title i { color: #818cf8; }

        /* ── Confidence Bars ── */
        .conf-item { margin-bottom: 16px; }
        .conf-label-row {
            display: flex; justify-content: space-between;
            font-size: 13px; margin-bottom: 7px;
        }
        .conf-label-row span:first-child { color: rgba(255,255,255,0.7); font-weight: 500; }
        .conf-label-row span:last-child  { color: rgba(255,255,255,0.35); font-size: 12px; }
        .conf-track {
            width: 100%; height: 8px; background: rgba(255,255,255,0.06);
            border-radius: 4px; overflow: hidden;
        }
        .conf-fill { height: 100%; border-radius: 4px; transition: width 1s ease; }

        /* ── Top Subjects ── */
        .subject-row {
            display: flex; align-items: center; gap: 12px;
            padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .subject-row:last-child { border-bottom: none; }
        .subject-rank {
            width: 26px; height: 26px; border-radius: 8px;
            background: rgba(99,102,241,0.15); border: 1px solid rgba(99,102,241,0.2);
            display: flex; align-items: center; justify-content: center;
            font-size: 11px; font-weight: 700; color: #818cf8; flex-shrink: 0;
        }
        .subject-name-text { flex: 1; font-size: 13px; color: rgba(255,255,255,0.8); font-weight: 500; }
        .subject-count-badge {
            padding: 3px 10px; border-radius: 20px;
            background: rgba(99,102,241,0.15); font-size: 11px;
            color: #a5b4fc; font-weight: 600;
        }

        /* ── Students Table ── */
        .table-card {
            background: linear-gradient(135deg, rgba(16, 16, 26, 0.74), rgba(22, 22, 34, 0.75));
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 18px; padding: 24px;
            animation: fadeInUp 0.5s ease 0.4s forwards; opacity: 0;
        }

        .search-bar {
            display: flex; align-items: center; gap: 10px;
            background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
            border-radius: 10px; padding: 9px 14px;
        }
        .search-bar i { color: rgba(255,255,255,0.25); font-size: 13px; }
        .search-bar input {
            background: none; border: none; outline: none;
            color: white; font-size: 13px; font-family: inherit; width: 200px;
        }
        .search-bar input::placeholder { color: rgba(255,255,255,0.2); }

        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        thead tr { border-bottom: 1px solid rgba(255,255,255,0.08); }
        th {
            padding: 10px 14px; text-align: left;
            font-size: 11px; font-weight: 700; color: rgba(255,255,255,0.3);
            text-transform: uppercase; letter-spacing: 0.7px;
        }
        tbody tr {
            border-bottom: 1px solid rgba(255,255,255,0.04);
            transition: background 0.2s ease;
        }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: rgba(99,102,241,0.05); }
        td { padding: 13px 14px; font-size: 13px; color: rgba(255,255,255,0.7); vertical-align: middle; }

        .student-name-cell { display: flex; align-items: center; gap: 10px; }
        .student-avatar-sm {
            width: 32px; height: 32px; border-radius: 8px; flex-shrink: 0;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            display: flex; align-items: center; justify-content: center;
            font-size: 12px; font-weight: 700; color: white;
        }
        .student-name-text { font-weight: 600; color: white; font-size: 13px; }
        .student-email     { font-size: 11px; color: rgba(255,255,255,0.3); }

        .badge {
            padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600;
        }
        .badge-purple { background: rgba(99,102,241,0.15); color: #a5b4fc; }
        .badge-green  { background: rgba(16,185,129,0.12); color: #6ee7b7; }
        .badge-yellow { background: rgba(245,158,11,0.12); color: #fcd34d; }
        .badge-gray   { background: rgba(100,116,139,0.15); color: #94a3b8; }

        .btn-view {
            padding: 6px 14px; border-radius: 7px; font-size: 12px; font-weight: 600;
            background: rgba(99,102,241,0.15); color: #a5b4fc;
            border: 1px solid rgba(99,102,241,0.25); cursor: pointer;
            text-decoration: none; display: inline-flex; align-items: center; gap: 5px;
            transition: all 0.2s ease;
        }
        .btn-view:hover { background: rgba(99,102,241,0.3); }

        .btn-del {
            padding: 6px 12px; border-radius: 7px; font-size: 12px; font-weight: 600;
            background: rgba(239,68,68,0.1); color: #fca5a5;
            border: 1px solid rgba(239,68,68,0.2); cursor: pointer;
            display: inline-flex; align-items: center; gap: 5px;
            transition: all 0.2s ease;
        }
        .btn-del:hover { background: rgba(239,68,68,0.25); }

        /* ── Preferred Time Tag ── */
        .pref-tag {
            display: inline-block; padding: 2px 8px; border-radius: 5px;
            font-size: 10px; font-weight: 600; margin: 1px;
        }
        .pref-morning  { background: rgba(251,191,36,0.15); color: #fcd34d; }
        .pref-afternoon{ background: rgba(16,185,129,0.12); color: #6ee7b7; }
        .pref-night    { background: rgba(99,102,241,0.15); color: #a5b4fc; }

        /* ── Empty ── */
        .empty-row td { text-align: center; padding: 40px; color: rgba(255,255,255,0.2); font-size: 13px; }

        /* ── Scrollbar ── */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: rgba(255,255,255,0.02); }
        ::-webkit-scrollbar-thumb { background: rgba(99,102,241,0.3); border-radius: 3px; }

        /* ── Responsive ── */
        @media (max-width: 1200px) { .two-col { grid-template-columns: 1fr; } }
        @media (max-width: 1024px) {
            .sidebar { width: 220px; }
            .main { margin-left: 220px; }
        }
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main { margin-left: 0; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .page-body { padding: 20px; }
        }
    </style>
</head>
<body>

<!-- ── Sidebar ── -->
<div class="sidebar">
    <div class="sidebar-logo">
        <div class="logo-icon"><i class="fa-solid fa-shield-halved" style="color:white;"></i></div>
        <h2>Admin Panel</h2>
        <p>Study Planner Management</p>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section-title">Main</div>
        <a href="admin_dashboard.php" class="nav-item active">
            <i class="fa-solid fa-gauge-high"></i> Dashboard
        </a>

        <div class="nav-section-title">Management</div>
        <a href="admin_students.php" class="nav-item">
            <i class="fa-solid fa-users"></i> All Students
        </a>
        <a href="admin_timetables.php" class="nav-item">
            <i class="fa-solid fa-calendar-week"></i> AI Timetables
        </a>
        <a href="admin_progress.php" class="nav-item">
            <i class="fa-solid fa-chart-line"></i> Progress Reports
        </a>

        <div class="nav-section-title">System</div>
        <a href="../student/login.php" class="nav-item" target="_blank">
            <i class="fa-solid fa-arrow-up-right-from-square"></i> Student Portal
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="admin-info">
            <div class="admin-avatar"><?php echo strtoupper(substr($admin_username, 0, 1)); ?></div>
            <div class="admin-info-text">
                <p><?php echo htmlspecialchars($admin_username); ?></p>
                <span>Administrator</span>
            </div>
        </div>
        <a href="admin_logout.php" class="logout-btn">
            <i class="fa-solid fa-right-from-bracket"></i> Sign Out
        </a>
    </div>
</div>

<!-- ── Main ── -->
<div class="main">

    <!-- Top Bar -->
    <div class="topbar">
        <div class="topbar-title">
            <h1>Dashboard Overview</h1>
            <p>Welcome back, <?php echo htmlspecialchars($admin_username); ?> — here's what's happening today.</p>
        </div>
        <div class="topbar-right">
            <div class="date-badge">
                <?php echo date('D, d M Y'); ?>
            </div>
        </div>
    </div>

    <!-- Page Body -->
    <div class="page-body">

        <?php if (isset($_GET['deleted'])): ?>
            <div class="alert-success">
                <i class="fa-solid fa-circle-check"></i>
                Student account and all related data have been deleted successfully.
            </div>
        <?php endif; ?>

        <!-- ── Stats Grid ── -->
        <div class="stats-grid">

            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(99,102,241,0.15);">
                    <i class="fa-solid fa-users" style="color:#818cf8;"></i>
                </div>
                <div class="stat-value"><?php echo $total_students; ?></div>
                <div class="stat-label">Total Students</div>
                <div class="stat-sub">Registered accounts</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(16,185,129,0.12);">
                    <i class="fa-solid fa-book-open" style="color:#34d399;"></i>
                </div>
                <div class="stat-value"><?php echo $total_subjects; ?></div>
                <div class="stat-label">Total Subjects</div>
                <div class="stat-sub">Across all students</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(245,158,11,0.12);">
                    <i class="fa-solid fa-calendar-days" style="color:#fbbf24;"></i>
                </div>
                <div class="stat-value"><?php echo $total_classes; ?></div>
                <div class="stat-label">Class Entries</div>
                <div class="stat-sub">Class timetable records</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(139,92,246,0.15);">
                    <i class="fa-solid fa-wand-magic-sparkles" style="color:#a78bfa;"></i>
                </div>
                <div class="stat-value"><?php echo $total_ai_sessions; ?></div>
                <div class="stat-label">AI Sessions</div>
                <div class="stat-sub">Generated this week</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(20,184,166,0.12);">
                    <i class="fa-solid fa-circle-check" style="color:#2dd4bf;"></i>
                </div>
                <div class="stat-value"><?php echo $total_completed; ?></div>
                <div class="stat-label">Completed Sessions</div>
                <div class="stat-sub">Marked done by students</div>
            </div>

        </div>

        <!-- ── Two Column ── -->
        <div class="two-col">

            <!-- Confidence Distribution -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fa-solid fa-chart-pie"></i>
                        Confidence Distribution
                    </div>
                </div>
                <?php
                $conf_total = $critical + $intermediate + $mastered;
                $conf_total = $conf_total > 0 ? $conf_total : 1;
                ?>
                <div class="conf-item">
                    <div class="conf-label-row">
                        <span>🔴 Critical (0–30%)</span>
                        <span><?php echo $critical; ?> subjects (<?php echo round($critical/$conf_total*100); ?>%)</span>
                    </div>
                    <div class="conf-track">
                        <div class="conf-fill" style="width:<?php echo round($critical/$conf_total*100); ?>%; background:#ef4444;"></div>
                    </div>
                </div>
                <div class="conf-item">
                    <div class="conf-label-row">
                        <span>🟡 Intermediate (31–70%)</span>
                        <span><?php echo $intermediate; ?> subjects (<?php echo round($intermediate/$conf_total*100); ?>%)</span>
                    </div>
                    <div class="conf-track">
                        <div class="conf-fill" style="width:<?php echo round($intermediate/$conf_total*100); ?>%; background:#f59e0b;"></div>
                    </div>
                </div>
                <div class="conf-item">
                    <div class="conf-label-row">
                        <span>🟢 Mastered (71–100%)</span>
                        <span><?php echo $mastered; ?> subjects (<?php echo round($mastered/$conf_total*100); ?>%)</span>
                    </div>
                    <div class="conf-track">
                        <div class="conf-fill" style="width:<?php echo round($mastered/$conf_total*100); ?>%; background:#10b981;"></div>
                    </div>
                </div>
            </div>

            <!-- Top Subjects -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fa-solid fa-ranking-star"></i>
                        Most Added Subjects
                    </div>
                </div>
                <?php if (empty($top_subjects)): ?>
                    <p style="color:rgba(255,255,255,0.2); font-size:13px; text-align:center; padding:20px;">No subjects yet</p>
                <?php else: ?>
                    <?php foreach ($top_subjects as $i => $sub): ?>
                    <div class="subject-row">
                        <div class="subject-rank"><?php echo $i + 1; ?></div>
                        <div class="subject-name-text"><?php echo htmlspecialchars($sub['subject']); ?></div>
                        <div class="subject-count-badge"><?php echo $sub['count']; ?> students</div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>

        <!-- ── Recent Students Table ── -->
        <div class="table-card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fa-solid fa-users"></i>
                    Recent Students
                    <span class="badge badge-purple" style="font-size:11px;"><?php echo $total_students; ?> total</span>
                </div>
                <div style="display:flex; gap:10px; align-items:center;">
                    <div class="search-bar">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" id="searchInput" placeholder="Search students...">
                    </div>
                    <a href="admin_students.php" class="btn-view">
                        <i class="fa-solid fa-arrow-right"></i> View All
                    </a>
                </div>
            </div>

            <table id="studentsTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student</th>
                        <th>Wake Up Time & Sleep Time</th>
                        <th>Preferred Study Time</th>
                        <th>Subjects</th>
                        <th>Classes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_students)): ?>
                        <tr class="empty-row">
                            <td colspan="7">No students registered yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recent_students as $i => $s):
                            $prefs = json_decode($s['preferred_time'] ?? '[]', true) ?? [];
                            $wake  = $s['wake_up_time'] ? date('h:i A', strtotime($s['wake_up_time'])) : '—';
                            $sleep = $s['sleep_time']   ? date('h:i A', strtotime($s['sleep_time']))   : '—';
                        ?>
                        <tr>
                            <td style="color:rgba(255,255,255,0.25); font-size:12px;"><?php echo $s['student_id']; ?></td>
                            <td>
                                <div class="student-name-cell">
                                    <div class="student-avatar-sm"><?php echo strtoupper(substr($s['username'], 0, 1)); ?></div>
                                    <div>
                                        <div class="student-name-text"><?php echo htmlspecialchars($s['username']); ?></div>
                                        <div class="student-email"><?php echo htmlspecialchars($s['email'] ?? '—'); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td style="font-size:12px;">
                                <div style="color:rgba(255,255,255,0.5);">
                                    <i class="fa-solid fa-sun" style="color:#fbbf24; margin-right:4px;"></i><?php echo $wake; ?>
                                </div>
                                <div style="color:rgba(255,255,255,0.35); margin-top:3px;">
                                    <i class="fa-solid fa-moon" style="color:#818cf8; margin-right:4px;"></i><?php echo $sleep; ?>
                                </div>
                            </td>
                            <td>
                                <?php if (empty($prefs)): ?>
                                    <span style="color:rgba(255,255,255,0.2); font-size:12px;">—</span>
                                <?php else: ?>
                                    <?php foreach ($prefs as $p):
                                        $cls = strtolower($p) === 'morning' ? 'pref-morning' : (strtolower($p) === 'afternoon' ? 'pref-afternoon' : 'pref-night');
                                    ?>
                                        <span class="pref-tag <?php echo $cls; ?>"><?php echo $p; ?></span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-green"><?php echo $s['subject_count']; ?> subjects</span>
                            </td>
                            <td>
                                <span class="badge badge-yellow"><?php echo $s['class_count']; ?> classes</span>
                            </td>
                            <td>
                                <div style="display:flex; gap:6px;">
                                    <a href="admin_students.php?view=<?php echo $s['student_id']; ?>" class="btn-view">
                                        <i class="fa-solid fa-eye"></i> View
                                    </a>
                                    <form method="POST" action="admin_dashboard.php" style="display:inline;"
                                          onsubmit="return confirm('Delete <?php echo htmlspecialchars($s['username']); ?>? This will remove all their data permanently.')">
                                        <input type="hidden" name="delete_student" value="1">
                                        <input type="hidden" name="student_id" value="<?php echo $s['student_id']; ?>">
                                        <button type="submit" class="btn-del">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div><!-- /page-body -->
</div><!-- /main -->

<script>
    // Live search
    document.getElementById('searchInput').addEventListener('input', function() {
        const q = this.value.toLowerCase();
        document.querySelectorAll('#studentsTable tbody tr:not(.empty-row)').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    });
</script>
</body>
</html>