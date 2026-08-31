<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

require_once '../config.php';

$admin_username = $_SESSION['admin_username'];
$success = '';
$error   = '';

// ── Delete student ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_student'])) {
    $del_id = (int)$_POST['student_id'];
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
    $success = 'Student account and all related data deleted successfully.';
}

// ── View single student detail ─────────────────────────────────────────────
$view_student  = null;
$view_subjects = [];
$view_classes  = [];
$view_timetable = [];
$view_progress  = [];

if (isset($_GET['view'])) {
    $view_id = (int)$_GET['view'];

    $vs = $conn->prepare("SELECT * FROM student WHERE student_id = ?");
    $vs->bind_param("i", $view_id);
    $vs->execute();
    $view_student = $vs->get_result()->fetch_assoc();
    $vs->close();

    if ($view_student) {
        $sq = $conn->prepare("SELECT plan_id, subject, confidence_level FROM personal_study_plan WHERE student_id = ? AND subject IS NOT NULL ORDER BY confidence_level ASC");
        $sq->bind_param("i", $view_id);
        $sq->execute();
        $sqr = $sq->get_result();
        while ($r = $sqr->fetch_assoc()) $view_subjects[] = $r;
        $sq->close();

        $cq = $conn->prepare("SELECT class_id, subject, day, start_time, end_time FROM class_timetable WHERE student_id = ? ORDER BY day, start_time");
        $cq->bind_param("i", $view_id);
        $cq->execute();
        $cqr = $cq->get_result();
        while ($r = $cqr->fetch_assoc()) $view_classes[] = $r;
        $cq->close();

        $week_start = date('Y-m-d', strtotime('monday this week'));
        $week_end   = date('Y-m-d', strtotime('sunday this week'));
        $tq = $conn->prepare("SELECT timetable_id, subject, study_date, start_time, end_time, is_completed FROM ai_personal_study_timetable WHERE student_id = ? AND study_date BETWEEN ? AND ? ORDER BY study_date, start_time");
        $tq->bind_param("iss", $view_id, $week_start, $week_end);
        $tq->execute();
        $tqr = $tq->get_result();
        while ($r = $tqr->fetch_assoc()) $view_timetable[] = $r;
        $tq->close();

        $pq = $conn->prepare("SELECT p.progress_id, p.subject, p.status, p.remark, t.study_date FROM progress p LEFT JOIN ai_personal_study_timetable t ON t.timetable_id = p.timetable_id WHERE p.student_id = ? ORDER BY p.progress_id DESC LIMIT 10");
        $pq->bind_param("i", $view_id);
        $pq->execute();
        $pqr = $pq->get_result();
        while ($r = $pqr->fetch_assoc()) $view_progress[] = $r;
        $pq->close();
    }
}

// ── Fetch all students ─────────────────────────────────────────────────────
$search = trim($_GET['search'] ?? '');
if ($search) {
    $like  = "%$search%";
    $all_q = $conn->prepare("
        SELECT s.student_id, s.username, s.email, s.wake_up_time, s.sleep_time,
               s.max_study_hours, s.preferred_time,
               COUNT(DISTINCT psp.plan_id)      as subject_count,
               COUNT(DISTINCT ct.class_id)      as class_count,
               COUNT(DISTINCT ait.timetable_id) as session_count,
               COUNT(DISTINCT pr.progress_id)   as done_count
        FROM student s
        LEFT JOIN personal_study_plan psp ON psp.student_id = s.student_id AND psp.subject IS NOT NULL
        LEFT JOIN class_timetable ct ON ct.student_id = s.student_id
        LEFT JOIN ai_personal_study_timetable ait ON ait.student_id = s.student_id
        LEFT JOIN progress pr ON pr.student_id = s.student_id AND pr.status = 'Done'
        WHERE s.username LIKE ? OR s.email LIKE ?
        GROUP BY s.student_id ORDER BY s.student_id DESC
    ");
    $all_q->bind_param("ss", $like, $like);
} else {
    $all_q = $conn->prepare("
        SELECT s.student_id, s.username, s.email, s.wake_up_time, s.sleep_time,
               s.max_study_hours, s.preferred_time,
               COUNT(DISTINCT psp.plan_id)      as subject_count,
               COUNT(DISTINCT ct.class_id)      as class_count,
               COUNT(DISTINCT ait.timetable_id) as session_count,
               COUNT(DISTINCT pr.progress_id)   as done_count
        FROM student s
        LEFT JOIN personal_study_plan psp ON psp.student_id = s.student_id AND psp.subject IS NOT NULL
        LEFT JOIN class_timetable ct ON ct.student_id = s.student_id
        LEFT JOIN ai_personal_study_timetable ait ON ait.student_id = s.student_id
        LEFT JOIN progress pr ON pr.student_id = s.student_id AND pr.status = 'Done'
        GROUP BY s.student_id ORDER BY s.student_id DESC
    ");
    // Removed $all_q->bind_param(); here
}
$all_q->execute();
$all_q->execute();
$all_result   = $all_q->get_result();
$all_students = [];
while ($r = $all_result->fetch_assoc()) $all_students[] = $r;
$all_q->close();

$conn->close();

function confBadge($c) {
    if ($c <= 30) return ['Critical',     '#ef4444', 'rgba(239,68,68,0.12)'];
    if ($c <= 70) return ['Intermediate', '#f59e0b', 'rgba(245,158,11,0.12)'];
    return              ['Mastered',     '#10b981', 'rgba(16,185,129,0.12)'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Students - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background:rgb(159, 180, 230);
            min-height: 100vh; color:rgb(12, 13, 14);
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
            transition:all 0.2s ease; text-decoration:none;
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
            cursor:pointer; text-decoration:none; transition:all 0.2s ease;
        }
        .logout-btn:hover { background:rgba(239,68,68,0.2); }

        /* ── Main ── */
        .main { margin-left:260px; flex:1; min-height:100vh; display:flex; flex-direction:column; }
        .topbar {
            padding:20px 30px;
            background:rgba(15, 23, 42, 0.87); backdrop-filter:blur(10px);
            border-bottom:1px solid rgba(255,255,255,0.05);
            display:flex; justify-content:space-between; align-items:center;
            position:sticky; top:0; z-index:40;
        }
        .topbar-title h1 { font-size:22px; font-weight:700; color:white; }
        .topbar-title p  { font-size:13px; color:rgba(255, 255, 255, 0.8); margin-top:2px; }
        .page-body { padding:28px 30px; flex:1; }

        /* ── Alerts ── */
        .alert {
            padding:14px 18px; border-radius:12px; margin-bottom:24px;
            font-size:14px; display:flex; align-items:center; gap:10px;
            animation:slideDown 0.4s ease;
        }
        @keyframes slideDown { from{opacity:0;transform:translateY(-10px)} to{opacity:1;transform:translateY(0)} }
        .alert-success { background:rgba(16,185,129,0.1); border:1px solid rgba(16,185,129,0.25); color:#6ee7b7; }

        /* ── Toolbar ── */
        .toolbar {
            display:flex; justify-content:space-between; align-items:center;
            margin-bottom:22px; flex-wrap:wrap; gap:14px;
        }
        .search-form {
            display:flex; align-items:center; gap:10px;
            background:rgba(3, 3, 3, 0.06); border:1px solid rgba(8, 8, 8, 0.77);
            border-radius:10px; padding:10px 16px;
        }
        .search-form i { color:rgba(15, 15, 15, 0.74); }
        .search-form input {
            background:none; border:none; outline:none;
            color:white; font-size:14px; font-family:inherit; width:220px;
        }
        .search-form input::placeholder { color:rgba(0, 0, 0, 0.79); }
        .search-form button {
            background:rgba(5, 5, 5, 0.7); border:1px solid rgba(99,102,241,0.3);
            color:#a5b4fc; padding:5px 14px; border-radius:7px;
            font-size:12px; font-weight:600; cursor:pointer;
        }
        .total-badge {
            padding:8px 16px; border-radius:10px;
            background:rgba(3, 3, 3, 0.73); border:1px solid rgba(2, 2, 2, 0.77);
            font-size:13px; color:#a5b4fc; font-weight:600;
        }

        /* ── Table Card ── */
        .table-card {
            background:linear-gradient(135deg,rgba(19, 18, 32, 0.77),rgba(15, 15, 22, 0.75));
            border:1px solid rgba(255,255,255,0.07);
            border-radius:18px; padding:24px;
            animation:fadeInUp 0.5s ease forwards; opacity:0;
            overflow-x:auto;
        }
        @keyframes fadeInUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }

        table { width:100%; border-collapse:collapse; min-width:900px; }
        thead tr { border-bottom:1px solid rgba(255,255,255,0.08); }
        th {
            padding:11px 14px; text-align:left;
            font-size:11px; font-weight:700; color:rgba(255,255,255,0.3);
            text-transform:uppercase; letter-spacing:0.7px; white-space:nowrap;
        }
        tbody tr { border-bottom:1px solid rgba(255,255,255,0.04); transition:background 0.2s; }
        tbody tr:last-child { border-bottom:none; }
        tbody tr:hover { background:rgba(99,102,241,0.05); }
        td { padding:14px; font-size:13px; color:rgba(255,255,255,0.7); vertical-align:middle; }

        .student-cell { display:flex; align-items:center; gap:10px; }
        .s-avatar {
            width:36px; height:36px; border-radius:10px; flex-shrink:0;
            background:linear-gradient(135deg,#6366f1,#8b5cf6);
            display:flex; align-items:center; justify-content:center;
            font-size:13px; font-weight:700; color:white;
        }
        .s-name  { font-weight:600; color:white; font-size:13px; }
        .s-email { font-size:11px; color:rgba(255,255,255,0.3); margin-top:2px; }

        .schedule-cell { font-size:12px; line-height:1.7; }
        .pref-tag {
            display:inline-block; padding:2px 8px; border-radius:5px;
            font-size:10px; font-weight:600; margin:1px;
        }
        .pref-morning   { background:rgba(251,191,36,0.15); color:#fcd34d; }
        .pref-afternoon { background:rgba(16,185,129,0.12); color:#6ee7b7; }
        .pref-night     { background:rgba(99,102,241,0.15); color:#a5b4fc; }

        .badge { padding:3px 10px; border-radius:20px; font-size:11px; font-weight:600; }
        .badge-purple { background:rgba(32, 32, 34, 0.15); color:#a5b4fc; }
        .badge-green  { background:rgba(16,185,129,0.12); color:#6ee7b7; }
        .badge-yellow { background:rgba(245,158,11,0.12); color:#fcd34d; }
        .badge-blue   { background:rgba(59,130,246,0.12); color:#93c5fd; }

        .btn-view {
            padding:6px 13px; border-radius:7px; font-size:12px; font-weight:600;
            background:rgba(99,102,241,0.15); color:#a5b4fc;
            border:1px solid rgba(99,102,241,0.25); cursor:pointer;
            text-decoration:none; display:inline-flex; align-items:center; gap:5px;
            transition:all 0.2s;
        }
        .btn-view:hover { background:rgba(99,102,241,0.3); }
        .btn-del {
            padding:6px 12px; border-radius:7px; font-size:12px; font-weight:600;
            background:rgba(239,68,68,0.1); color:#fca5a5;
            border:1px solid rgba(239,68,68,0.2); cursor:pointer;
            display:inline-flex; align-items:center; gap:5px; transition:all 0.2s;
        }
        .btn-del:hover { background:rgba(239,68,68,0.25); }
        .empty-row td { text-align:center; padding:50px; color:rgba(255,255,255,0.2); }

        /* ── Detail Panel ── */
        .detail-overlay {
            display:none; position:fixed; top:0; left:0;
            width:100%; height:100%; z-index:100;
            background:rgba(0,0,0,0.6); backdrop-filter:blur(4px);
        }
        .detail-panel {
            position:absolute; top:0; right:0;
            width:560px; max-width:95vw; height:100%;
            background:linear-gradient(180deg,#1e1b4b 0%,#0f172a 100%);
            border-left:1px solid rgba(99,102,241,0.2);
            overflow-y:auto; padding:30px;
            box-shadow:-20px 0 60px rgba(0,0,0,0.5);
            animation:slideIn 0.3s ease;
        }
        @keyframes slideIn { from{transform:translateX(100%)} to{transform:translateX(0)} }

        .panel-close {
            display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;
        }
        .panel-close h2 { font-size:18px; font-weight:700; color:white; }
        .close-btn {
            width:34px; height:34px; border-radius:8px; border:none;
            background:rgba(255,255,255,0.07); color:rgba(255,255,255,0.5);
            cursor:pointer; font-size:16px; transition:all 0.2s;
            display:flex; align-items:center; justify-content:center;
        }
        .close-btn:hover { background:rgba(239,68,68,0.2); color:#fca5a5; }

        .profile-banner {
            background:linear-gradient(135deg,rgba(99,102,241,0.2),rgba(139,92,246,0.15));
            border:1px solid rgba(99,102,241,0.2); border-radius:14px;
            padding:20px; margin-bottom:20px;
            display:flex; align-items:center; gap:16px;
        }
        .profile-avatar-lg {
            width:56px; height:56px; border-radius:14px;
            background:linear-gradient(135deg,#6366f1,#8b5cf6);
            display:flex; align-items:center; justify-content:center;
            font-size:22px; font-weight:700; color:white; flex-shrink:0;
            box-shadow:0 6px 20px rgba(99,102,241,0.4);
        }
        .profile-info h3 { font-size:17px; font-weight:700; color:white; }
        .profile-info p  { font-size:12px; color:rgba(255,255,255,0.35); margin-top:3px; }

        .section { margin-bottom:22px; }
        .section-title {
            font-size:11px; font-weight:700; color:rgba(255,255,255,0.3);
            text-transform:uppercase; letter-spacing:1px; margin-bottom:12px;
            display:flex; align-items:center; gap:8px;
        }
        .section-title::after { content:''; flex:1; height:1px; background:rgba(255,255,255,0.06); }

        .info-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
        .info-box {
            background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.06);
            border-radius:10px; padding:12px 14px;
        }
        .info-box-label { font-size:10px; color:rgba(255,255,255,0.3); font-weight:600; text-transform:uppercase; letter-spacing:0.6px; margin-bottom:5px; }
        .info-box-value { font-size:14px; font-weight:600; color:white; }

        .subject-pill {
            display:flex; align-items:center; justify-content:space-between;
            padding:10px 14px; border-radius:10px; margin-bottom:8px;
            border:1px solid rgba(255,255,255,0.06);
        }
        .subject-pill-name { font-size:13px; font-weight:600; color:white; }
        .subject-pill-conf { padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; }

        .class-row {
            display:flex; align-items:center; gap:10px;
            padding:9px 0; border-bottom:1px solid rgba(255,255,255,0.05); font-size:13px;
        }
        .class-row:last-child { border-bottom:none; }
        .class-day { width:60px; font-size:11px; font-weight:700; color:#818cf8; text-transform:uppercase; flex-shrink:0; }
        .class-subject { flex:1; color:white; font-weight:500; }
        .class-time { font-size:11px; color:rgba(255,255,255,0.35); }

        .session-row {
            display:flex; align-items:center; gap:10px;
            padding:9px 0; border-bottom:1px solid rgba(255,255,255,0.05);
        }
        .session-row:last-child { border-bottom:none; }
        .session-date { width:70px; font-size:11px; color:rgba(255,255,255,0.3); flex-shrink:0; }
        .session-subject { flex:1; font-size:13px; color:white; font-weight:500; }
        .session-time { font-size:11px; color:rgba(255,255,255,0.35); }
        .session-status { padding:2px 8px; border-radius:20px; font-size:10px; font-weight:700; }
        .status-done    { background:rgba(16,185,129,0.12); color:#6ee7b7; }
        .status-pending { background:rgba(245,158,11,0.12); color:#fcd34d; }

        .progress-row {
            display:flex; align-items:flex-start; gap:12px;
            padding:10px 0; border-bottom:1px solid rgba(255,255,255,0.05);
        }
        .progress-row:last-child { border-bottom:none; }
        .progress-subject { flex:1; font-size:13px; color:white; font-weight:500; }
        .progress-remark  { font-size:11px; color:rgba(255,255,255,0.3); margin-top:3px; }

        ::-webkit-scrollbar { width:6px; }
        ::-webkit-scrollbar-track { background:rgba(255,255,255,0.02); }
        ::-webkit-scrollbar-thumb { background:rgba(99,102,241,0.3); border-radius:3px; }

        @media(max-width:768px) {
            .sidebar { display:none; }
            .main { margin-left:0; }
            .page-body { padding:20px; }
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
        <a href="admin_students.php" class="nav-item active"><i class="fa-solid fa-users"></i> All Students</a>
        <a href="admin_timetables.php" class="nav-item"><i class="fa-solid fa-calendar-week"></i> AI Timetables</a>
        <a href="admin_progress.php" class="nav-item"><i class="fa-solid fa-chart-line"></i> Progress Reports</a>
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
            <h1>All Students</h1>
            <p>View, search and manage all registered student accounts</p>
        </div>
    </div>

    <div class="page-body">

        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Toolbar -->
        <div class="toolbar">
            <form method="GET" action="admin_students.php" class="search-form">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" name="search" placeholder="Search by name or email..."
                       value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit">Search</button>
                <?php if ($search): ?>
                    <a href="admin_students.php" style="color:#fca5a5; font-size:12px; text-decoration:none; margin-left:4px;">
                        <i class="fa-solid fa-xmark"></i> Clear
                    </a>
                <?php endif; ?>
            </form>
            <div class="total-badge">
                <i class="fa-solid fa-users" style="margin-right:6px;"></i>
                <?php echo count($all_students); ?> student<?php echo count($all_students) !== 1 ? 's' : ''; ?>
                <?php if ($search): ?> found<?php endif; ?>
            </div>
        </div>

        <!-- Table -->
        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Student</th>
                        <th>Wake Up Time & Sleep Time</th>
                        <th>Preferred Study Time</th>
                        <th>Max Hrs</th>
                        <th>Subjects</th>
                        <th>Classes</th>
                        <th>Sessions</th>
                        <th>Done</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($all_students)): ?>
                        <tr class="empty-row">
                            <td colspan="10">
                                <i class="fa-solid fa-users-slash" style="font-size:28px; display:block; margin-bottom:10px; opacity:0.3;"></i>
                                <?php echo $search ? 'No students matched your search.' : 'No students registered yet.'; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($all_students as $s):
                            $prefs = json_decode($s['preferred_time'] ?? '[]', true) ?? [];
                            $wake  = $s['wake_up_time'] ? date('h:i A', strtotime($s['wake_up_time'])) : '—';
                            $sleep = $s['sleep_time']   ? date('h:i A', strtotime($s['sleep_time']))   : '—';
                        ?>
                        <tr>
                            <td style="color:rgba(255,255,255,0.2); font-size:11px;"><?php echo $s['student_id']; ?></td>
                            <td>
                                <div class="student-cell">
                                    <div class="s-avatar"><?php echo strtoupper(substr($s['username'], 0, 1)); ?></div>
                                    <div>
                                        <div class="s-name"><?php echo htmlspecialchars($s['username']); ?></div>
                                        <div class="s-email"><?php echo htmlspecialchars($s['email'] ?? '—'); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="schedule-cell">
                                <div style="color:#fbbf24;"><i class="fa-solid fa-sun" style="margin-right:4px;"></i><?php echo $wake; ?></div>
                                <div style="color:#818cf8;"><i class="fa-solid fa-moon" style="margin-right:4px;"></i><?php echo $sleep; ?></div>
                            </td>
                            <td>
                                <?php if (empty($prefs)): ?>
                                    <span style="color:rgba(255,255,255,0.15); font-size:11px;">—</span>
                                <?php else: ?>
                                    <?php foreach ($prefs as $p):
                                        $cls = strtolower($p) === 'morning' ? 'pref-morning' : (strtolower($p) === 'afternoon' ? 'pref-afternoon' : 'pref-night');
                                    ?>
                                        <span class="pref-tag <?php echo $cls; ?>"><?php echo $p; ?></span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge badge-blue"><?php echo $s['max_study_hours'] ?? '—'; ?> hrs</span></td>
                            <td><span class="badge badge-purple"><?php echo $s['subject_count']; ?></span></td>
                            <td><span class="badge badge-yellow"><?php echo $s['class_count']; ?></span></td>
                            <td><span class="badge badge-blue"><?php echo $s['session_count']; ?></span></td>
                            <td><span class="badge badge-green"><?php echo $s['done_count']; ?></span></td>
                            <td>
                                <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                    <a href="admin_students.php?view=<?php echo $s['student_id']; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>" class="btn-view">
                                        <i class="fa-solid fa-eye"></i> View
                                    </a>
                                    <form method="POST" action="admin_students.php" style="display:inline;"
                                          onsubmit="return confirm('Delete <?php echo htmlspecialchars($s['username']); ?>? This is permanent.')">
                                        <input type="hidden" name="delete_student" value="1">
                                        <input type="hidden" name="student_id" value="<?php echo $s['student_id']; ?>">
                                        <button type="submit" class="btn-del"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Detail Panel -->
<?php if ($view_student): ?>
<div class="detail-overlay" id="detailOverlay" style="display:block;">
    <div class="detail-panel">

        <div class="panel-close">
            <h2>Student Detail</h2>
            <button class="close-btn" onclick="closeDetail()"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <div class="profile-banner">
            <div class="profile-avatar-lg"><?php echo strtoupper(substr($view_student['username'], 0, 1)); ?></div>
            <div class="profile-info">
                <h3><?php echo htmlspecialchars($view_student['username']); ?></h3>
                <p><?php echo htmlspecialchars($view_student['email'] ?? '—'); ?></p>
            </div>
        </div>

        <!-- Profile Info -->
        <div class="section">
            <div class="section-title"><i class="fa-solid fa-user" style="color:#818cf8;"></i> Profile Info</div>
            <div class="info-grid">
                <div class="info-box">
                    <div class="info-box-label">Wake Up Time</div>
                    <div class="info-box-value"><?php echo $view_student['wake_up_time'] ? date('h:i A', strtotime($view_student['wake_up_time'])) : '—'; ?></div>
                </div>
                <div class="info-box">
                    <div class="info-box-label">Sleep Time</div>
                    <div class="info-box-value"><?php echo $view_student['sleep_time'] ? date('h:i A', strtotime($view_student['sleep_time'])) : '—'; ?></div>
                </div>
                <div class="info-box">
                    <div class="info-box-label">Max Study Hours</div>
                    <div class="info-box-value"><?php echo $view_student['max_study_hours'] ?? '—'; ?> hrs/day</div>
                </div>
                <div class="info-box">
                    <div class="info-box-label">Preferred Time</div>
                    <div class="info-box-value" style="font-size:12px;">
                        <?php
                        $prefs = json_decode($view_student['preferred_time'] ?? '[]', true) ?? [];
                        echo !empty($prefs) ? implode(', ', $prefs) : '—';
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Subjects -->
        <div class="section">
            <div class="section-title"><i class="fa-solid fa-book" style="color:#818cf8;"></i> Subjects (<?php echo count($view_subjects); ?>)</div>
            <?php if (empty($view_subjects)): ?>
                <p style="color:rgba(255,255,255,0.2); font-size:13px; text-align:center; padding:16px;">No subjects added.</p>
            <?php else: ?>
                <?php foreach ($view_subjects as $sub):
                    [$label, $color, $bg] = confBadge($sub['confidence_level']);
                ?>
                <div class="subject-pill" style="background:<?php echo $bg; ?>; border-color:<?php echo $color; ?>30;">
                    <span class="subject-pill-name"><?php echo htmlspecialchars($sub['subject']); ?></span>
                    <span class="subject-pill-conf" style="background:<?php echo $bg; ?>; color:<?php echo $color; ?>;">
                        <?php echo $label; ?> (<?php echo $sub['confidence_level']; ?>%)
                    </span>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Class Timetable -->
        <div class="section">
            <div class="section-title"><i class="fa-solid fa-calendar-days" style="color:#818cf8;"></i> Class Timetable (<?php echo count($view_classes); ?>)</div>
            <?php if (empty($view_classes)): ?>
                <p style="color:rgba(255,255,255,0.2); font-size:13px; text-align:center; padding:16px;">No classes added.</p>
            <?php else: ?>
                <?php foreach ($view_classes as $c): ?>
                <div class="class-row">
                    <div class="class-day"><?php echo substr($c['day'], 0, 3); ?></div>
                    <div class="class-subject"><?php echo htmlspecialchars($c['subject']); ?></div>
                    <div class="class-time"><?php echo substr($c['start_time'],0,5); ?> – <?php echo substr($c['end_time'],0,5); ?></div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- AI Timetable -->
        <div class="section">
            <div class="section-title"><i class="fa-solid fa-wand-magic-sparkles" style="color:#818cf8;"></i> AI Timetable This Week (<?php echo count($view_timetable); ?>)</div>
            <?php if (empty($view_timetable)): ?>
                <p style="color:rgba(255,255,255,0.2); font-size:13px; text-align:center; padding:16px;">No AI sessions this week.</p>
            <?php else: ?>
                <?php foreach ($view_timetable as $t): ?>
                <div class="session-row">
                    <div class="session-date"><?php echo date('D d/m', strtotime($t['study_date'])); ?></div>
                    <div class="session-subject"><?php echo htmlspecialchars($t['subject']); ?></div>
                    <div class="session-time"><?php echo date('h:i A', strtotime($t['start_time'])); ?> – <?php echo date('h:i A', strtotime($t['end_time'])); ?></div>
                    <span class="session-status <?php echo $t['is_completed'] ? 'status-done' : 'status-pending'; ?>">
                        <?php echo $t['is_completed'] ? 'Done' : 'Pending'; ?>
                    </span>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Progress -->
        <div class="section">
            <div class="section-title"><i class="fa-solid fa-chart-line" style="color:#818cf8;"></i> Recent Progress (<?php echo count($view_progress); ?>)</div>
            <?php if (empty($view_progress)): ?>
                <p style="color:rgba(255,255,255,0.2); font-size:13px; text-align:center; padding:16px;">No progress records yet.</p>
            <?php else: ?>
                <?php foreach ($view_progress as $pr): ?>
                <div class="progress-row">
                    <div style="flex:1;">
                        <div class="progress-subject"><?php echo htmlspecialchars($pr['subject']); ?></div>
                        <?php if ($pr['remark']): ?>
                            <div class="progress-remark">💬 <?php echo htmlspecialchars($pr['remark']); ?></div>
                        <?php endif; ?>
                    </div>
                    <span class="session-status status-done"><?php echo $pr['status']; ?></span>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Delete -->
        <form method="POST" action="admin_students.php"
              onsubmit="return confirm('Delete <?php echo htmlspecialchars($view_student['username']); ?>? This removes all their data permanently.')">
            <input type="hidden" name="delete_student" value="1">
            <input type="hidden" name="student_id" value="<?php echo $view_student['student_id']; ?>">
            <button type="submit" style="
                width:100%; padding:12px; border-radius:10px; margin-top:8px;
                background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.3);
                color:#fca5a5; font-size:14px; font-weight:600; cursor:pointer;
                display:flex; align-items:center; justify-content:center; gap:8px;
                transition:all 0.2s;
            " onmouseover="this.style.background='rgba(239,68,68,0.25)'"
               onmouseout="this.style.background='rgba(239,68,68,0.1)'">
                <i class="fa-solid fa-trash"></i> Delete This Student Account
            </button>
        </form>

    </div>
</div>
<?php endif; ?>

<script>
    function closeDetail() {
        window.location.href = 'admin_students.php<?php echo $search ? "?search=" . urlencode($search) : ""; ?>';
    }
    const overlay = document.getElementById('detailOverlay');
    if (overlay) {
        overlay.addEventListener('click', function(e) {
            if (e.target === this) closeDetail();
        });
    }
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeDetail();
    });
</script>
</body>
</html>