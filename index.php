<?php
session_start();

// If user is already logged in as student, redirect to student dashboard
if (isset($_SESSION['student_id'])) {
    header("Location: student/dashboard.php");
    exit();
}

// If user is already logged in as admin, redirect to admin dashboard
if (isset($_SESSION['admin_id'])) {
    header("Location: admin/admin_dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart AI-Powered Study Planner | Master Your Academic Journey</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300..800;1,300..800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        :root {
            --primary: #4f46e5;
            --primary-light: #e0e7ff;
            --primary-accent: #6366f1;
            --secondary: #06b6d4;
            --accent-pink: #ec4899;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark-bg: #0b0f17;
            --dark-surface: #111827;
            --dark-card: #1f2937;
            --light-bg: #f8fafc;
            --white: #ffffff;
            --text-main: #334155;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --radius-xl: 24px;
            --radius-lg: 16px;
            --radius-md: 12px;
            --shadow-sm: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            --shadow-glow: 0 0 35px rgba(79, 70, 229, 0.25);
            --shadow-lg: 0 20px 30px -10px rgba(15, 23, 42, 0.08);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        html {
            scroll-behavior: smooth;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            background-color: var(--light-bg);
            color: var(--text-main);
            overflow-x: hidden;
            line-height: 1.6;
        }

        /* Ambient Glow Backgrounds */
        .ambient-glow {
            position: absolute;
            border-radius: 50%;
            filter: blur(120px);
            z-index: -1;
            pointer-events: none;
        }

        .glow-1 {
            top: -100px;
            right: -50px;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.18) 0%, rgba(255, 255, 255, 0) 70%);
        }

        .glow-2 {
            top: 400px;
            left: -100px;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(6, 182, 212, 0.15) 0%, rgba(255, 255, 255, 0) 70%);
        }

        /* Glassmorphic Navbar */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            background: rgba(255, 255, 255, 0.75);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(226, 232, 240, 0.8);
            padding: 1rem 3rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: var(--transition);
        }

        .brand-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: #0f172a;
            font-weight: 800;
            font-size: 1.3rem;
            letter-spacing: -0.5px;
        }

        .brand-icon {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: var(--white);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            box-shadow: 0 4px 14px rgba(79, 70, 229, 0.35);
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 2.5rem;
            list-style: none;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--text-main);
            font-weight: 600;
            font-size: 0.95rem;
            transition: var(--transition);
            position: relative;
        }

        .nav-links a:hover {
            color: var(--primary);
        }

        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: -6px;
            left: 0;
            width: 0%;
            height: 2px;
            background: var(--primary);
            border-radius: 2px;
            transition: var(--transition);
        }

        .nav-links a:hover::after {
            width: 100%;
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .btn {
            padding: 0.7rem 1.5rem;
            border-radius: var(--radius-md);
            font-weight: 700;
            font-size: 0.95rem;
            text-decoration: none;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            border: none;
        }

        .btn-ghost {
            color: var(--text-main);
            background: transparent;
        }

        .btn-ghost:hover {
            background: var(--primary-light);
            color: var(--primary);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-accent));
            color: var(--white);
            box-shadow: 0 6px 20px rgba(79, 70, 229, 0.35);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(79, 70, 229, 0.45);
        }

        .btn-outline {
            border: 2px solid var(--border);
            background: var(--white);
            color: var(--text-main);
        }

        .btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-light);
        }

        /* Hero Section (Centered Layout) */
        .hero-section {
            padding: 10rem 3rem 6rem;
            max-width: 900px;
            margin: 0 auto;
            text-align: center;
            position: relative;
        }

        .hero-title {
            font-size: 3.6rem;
            font-weight: 800;
            line-height: 1.12;
            color: #0f172a;
            margin-bottom: 1.25rem;
            letter-spacing: -1.5px;
        }

        .hero-title span.highlight {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-subtitle {
            font-size: 1.15rem;
            color: var(--text-muted);
            margin: 0 auto 2.25rem;
            line-height: 1.7;
            max-width: 90%;
        }

        .hero-cta {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1.25rem;
            flex-wrap: wrap;
        }

        .hero-stats-row {
            display: flex;
            justify-content: center;
            gap: 3.5rem;
            margin-top: 3.5rem;
            padding-top: 2rem;
            border-top: 1px solid var(--border);
        }

        .stat-item h4 {
            font-size: 2rem;
            font-weight: 800;
            color: #0f172a;
            font-family: 'Space Grotesk', sans-serif;
            line-height: 1;
        }

        .stat-item p {
            font-size: 0.85rem;
            color: var(--text-muted);
            font-weight: 600;
            margin-top: 6px;
        }

        /* Interactive Demo Section */
        .interactive-section {
            padding: 6rem 3rem;
            max-width: 1280px;
            margin: 0 auto;
        }

        .demo-box {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius-xl);
            padding: 3rem;
            box-shadow: var(--shadow-lg);
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            align-items: center;
        }

        .demo-control-group {
            margin-bottom: 1.5rem;
        }

        .demo-control-group label {
            display: block;
            font-weight: 700;
            font-size: 0.9rem;
            color: #0f172a;
            margin-bottom: 8px;
        }

        .demo-input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            font-weight: 600;
            background: #ffffff;
            color: #334155;
            outline: none;
            transition: var(--transition);
        }

        .demo-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
        }

        .demo-slider {
            width: 100%;
            accent-color: var(--primary);
            cursor: pointer;
        }

        .slider-labels {
            display: flex;
            justify-content: space-between;
            font-size: 0.78rem;
            color: var(--text-muted);
            font-weight: 600;
            margin-top: 4px;
        }

        .calc-result-card {
            background: var(--dark-surface);
            border-radius: var(--radius-lg);
            padding: 2rem;
            color: var(--white);
            border: 1px solid rgba(255,255,255,0.08);
            position: relative;
        }

        .calc-duration-badge {
            font-size: 2.25rem;
            font-weight: 800;
            color: var(--danger);
            font-family: 'Space Grotesk', sans-serif;
            margin: 8px 0;
            transition: var(--transition);
        }

        /* Features Section */
        .features-section {
            padding: 7rem 3rem;
            background: var(--white);
            position: relative;
        }

        .section-header {
            text-align: center;
            max-width: 700px;
            margin: 0 auto 4.5rem;
        }

        .section-header h2 {
            font-size: 2.5rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 1rem;
            letter-spacing: -1px;
        }

        .section-header p {
            color: var(--text-muted);
            font-size: 1.1rem;
        }

        .features-grid {
            max-width: 1280px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 2rem;
        }

        .feature-card {
            background: var(--light-bg);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 2.25rem;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            opacity: 0;
            transition: var(--transition);
        }

        .feature-card:hover {
            transform: translateY(-6px);
            background: var(--white);
            box-shadow: var(--shadow-lg);
            border-color: rgba(79, 70, 229, 0.2);
        }

        .feature-card:hover::before {
            opacity: 1;
        }

        .feature-icon {
            width: 58px;
            height: 58px;
            border-radius: 16px;
            background: var(--primary-light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            margin-bottom: 1.5rem;
        }

        .feature-card h3 {
            font-size: 1.25rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 0.6rem;
        }

        .feature-card p {
            color: var(--text-muted);
            font-size: 0.95rem;
            line-height: 1.65;
        }

        /* Workflow Section */
        .workflow-section {
            padding: 7rem 3rem;
            max-width: 1280px;
            margin: 0 auto;
        }

        .steps-wrapper {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-top: 3.5rem;
        }

        .step-box {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 2.25rem 1.75rem;
            text-align: center;
            position: relative;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
        }

        .step-box:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .step-number {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: var(--white);
            font-weight: 800;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }

        .step-box h4 {
            font-size: 1.15rem;
            color: #0f172a;
            margin-bottom: 0.6rem;
            font-weight: 700;
        }

        .step-box p {
            font-size: 0.92rem;
            color: var(--text-muted);
            line-height: 1.6;
        }

        /* Banner CTA */
        .cta-banner {
            max-width: 1280px;
            margin: 0 auto 7rem;
            padding: 0 3rem;
        }

        .banner-card {
            background: linear-gradient(135deg, #3730a3, #4f46e5, #0284c7);
            border-radius: 28px;
            padding: 4.5rem 3rem;
            text-align: center;
            color: var(--white);
            box-shadow: 0 25px 50px -12px rgba(79, 70, 229, 0.4);
            position: relative;
            overflow: hidden;
        }

        .banner-card h2 {
            font-size: 2.75rem;
            font-weight: 800;
            margin-bottom: 1.25rem;
            letter-spacing: -1px;
        }

        .banner-card p {
            font-size: 1.15rem;
            opacity: 0.92;
            max-width: 650px;
            margin: 0 auto 2.25rem;
            line-height: 1.6;
        }

        .btn-white {
            background: var(--white);
            color: var(--primary);
            font-weight: 800;
            padding: 0.95rem 2.25rem;
            border-radius: var(--radius-md);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }

        .btn-white:hover {
            background: #f8fafc;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }

        /* Footer */
        footer {
            background: var(--dark-bg);
            color: #94a3b8;
            padding: 4.5rem 3rem 2.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
        }

        .footer-content {
            max-width: 1280px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 2rem;
            padding-bottom: 2.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .footer-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--white);
            font-weight: 700;
            font-size: 1.1rem;
        }

        .footer-bottom {
            max-width: 1280px;
            margin: 2rem auto 0;
            text-align: center;
            font-size: 0.875rem;
        }

        /* Mobile Responsiveness */
        @media (max-width: 1024px) {
            .hero-section {
                padding-top: 8rem;
            }
            .demo-box { grid-template-columns: 1fr; }
            .nav-links { display: none; }
            .navbar { padding: 1rem 1.5rem; }
        }

        @media (max-width: 640px) {
            .hero-title { font-size: 2.5rem; }
            .banner-card h2 { font-size: 2rem; }
            .hero-stats-row { flex-direction: column; gap: 1.5rem; }
            .cta-banner, .hero-section, .interactive-section { padding-left: 1.5rem; padding-right: 1.5rem; }
        }
    </style>
</head>
<body>

    <div class="ambient-glow glow-1"></div>
    <div class="ambient-glow glow-2"></div>

    <!-- Navigation Header -->
    <nav class="navbar">
        <a href="index.php" class="brand-logo">
            <div class="brand-icon">
                <i class="fa-solid fa-graduation-cap"></i>
            </div>
            <span>Smart AI-Powered Study Planner</span>
        </a>

        <ul class="nav-links">
            <li><a href="#demo">Live Demo</a></li>
            <li><a href="#features">AI Logic Features</a></li>
            <li><a href="#workflow">How It Works</a></li>
            <li><a href="#about">About System</a></li>
        </ul>

        <div class="nav-actions">
            <a href="student/login.php" class="btn btn-ghost">
                Sign In
            </a>
            <a href="student/register.php" class="btn btn-primary">
                Get Started <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>
    </nav>

    <!-- Hero Landing Section -->
    <header class="hero-section">
        <div class="hero-content">
            <h1 class="hero-title">
                Master Your Semester With <span class="highlight">Smart AI</span> Scheduling.
            </h1>
            <p class="hero-subtitle">
                Eliminate schedule clashes and study burnout. Our rule-based AI automatically balances your class timetable, confidence levels, and personal sleep habits into an ideal weekly plan.
            </p>
            <div class="hero-cta">
                <a href="student/register.php" class="btn btn-primary" style="padding: 0.9rem 2rem;">
                    Build My Timetable
                </a>
                <a href="admin/admin_login.php" class="btn btn-outline" style="padding: 0.9rem 1.8rem;">
                     Admin Portal
                </a>
            </div>

            <div class="hero-stats-row">
                <div class="stat-item">
                    <h4>100%</h4>
                    <p>Conflict-Free Schedules</p>
                </div>
                <div class="stat-item">
                    <h4>15 Min</h4>
                    <p>Rest Buffer Enforced</p>
                </div>
                <div class="stat-item">
                    <h4>0-100%</h4>
                    <p>Dynamic Confidence Scaling</p>
                </div>
            </div>
        </div>
    </header>

    <!-- Interactive AI Rule Engine Demo Section -->
    <section class="interactive-section" id="demo">
        <div class="section-header">
            <h2>Experience The AI Logic In Action</h2>
            <p>Type your subject and adjust confidence levels below to see how our engine calculates required study durations.</p>
        </div>

        <div class="demo-box">
            <div class="demo-controls">
                <div class="demo-control-group">
                    <label for="subjectInput">Assigned Subject:</label>
                    <input type="text" id="subjectInput" class="demo-input" placeholder="Enter your subject" value="Your Subject" oninput="updateDemo()">
                </div>

                <div class="demo-control-group">
                    <label for="confRange">Subject Confidence Level: <span id="confValue" style="color:var(--primary); font-weight:800;">25%</span></label>
                    <input type="range" id="confRange" min="0" max="100" value="25" class="demo-slider" oninput="updateDemo()">
                    <div class="slider-labels">
                        <span>0% (Needs Heavy Focus)</span>
                        <span>50% (Moderate)</span>
                        <span>100% (Mastered)</span>
                    </div>
                </div>
            </div>

            <div class="calc-result-card">
                <span style="font-size:0.8rem; text-transform:uppercase; letter-spacing:1px; color:#94a3b8; font-weight:700;">AI Allocated Study Duration</span>
                <div class="calc-duration-badge" id="durationOutput">2.0 Hours</div>
                <p style="font-size:0.85rem; color:#cbd5e1; line-height:1.5;" id="explanationOutput">
                    Because your confidence level for "Fundamentals of Programming" is low (25%), the AI engine allocates a maximum 2.0-hour deep focus block to help you master fundamental concepts.
                </p>
                <div style="margin-top:1.25rem; display:flex; gap:8px; align-items:center; font-size:0.78rem; color:var(--secondary);">
                    <i class="fa-solid fa-circle-info"></i> Includes mandatory 15-minute rest buffer following session
                </div>
            </div>
        </div>
    </section>

    <!-- Key System Features Section -->
    <section class="features-section" id="features">
        <div class="section-header">
            <h2>Designed To Help You Excel Every Semester</h2>
            <p>Our rule-based AI engine seamlessly coordinates academic schedules, study preferences, and rest periods.</p>
        </div>

        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-brain"></i></div>
                <h3>Confidence-Based Durations</h3>
                <p>Lower subject confidence triggers higher study allocations (up to 2 hours), while mastered subjects receive concise revision slots (30 minutes).</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-calendar-xmark"></i></div>
                <h3>Class Conflict Prevention</h3>
                <p>Your weekly university class timetable is treated as locked, forbidden slots—guaranteeing zero study overlaps with lectures.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-bed"></i></div>
                <h3>Sleep & Wake Time Aware</h3>
                <p>Respects your physiological sleep-wake boundaries by scheduling sessions strictly within your active daily hours.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-rotate-left"></i></div>
                <h3>Carry-Forward Progress</h3>
                <p>Incomplete study sessions automatically carry over into today's open slots so you never fall behind on your goals.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-gauge-high"></i></div>
                <h3>Burnout Protection</h3>
                <p>Max daily study caps and mandatory 15-minute rest buffers between sessions keep your routine sustainable.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-chart-pie"></i></div>
                <h3>Progress Tracker</h3>
                <p>Log finished sessions, track weekly completion percentages, and add personalized study notes to review your performance.</p>
            </div>
        </div>
    </section>

    <!-- Workflow Steps Section -->
    <section class="workflow-section" id="workflow">
        <div class="section-header">
            <h2>4 Simple Steps To Your Optimized Plan</h2>
            <p>Setting up your entire semester's AI timetable takes less than 3 minutes.</p>
        </div>

        <div class="steps-wrapper">
            <div class="step-box">
                <div class="step-number">1</div>
                <h4>Configure Profile</h4>
                <p>Set up your wake/sleep cycles, study hour preferences, and daily max limits.</p>
            </div>

            <div class="step-box">
                <div class="step-number">2</div>
                <h4>Input Timetable</h4>
                <p>Add your weekly class schedule to lock in mandatory lecture hours.</p>
            </div>

            <div class="step-box">
                <div class="step-number">3</div>
                <h4>Set Confidence</h4>
                <p>List your subjects and rate your current confidence levels (0% to 100%).</p>
            </div>

            <div class="step-box">
                <div class="step-number">4</div>
                <h4>Generate Plan</h4>
                <p>Click once to let the AI logic assemble your personalized weekly study schedule.</p>
            </div>
        </div>
    </section>

    <!-- Call to Action Banner -->
    <section class="cta-banner">
        <div class="banner-card">
            <h2>Ready To Build Better Study Habits?</h2>
            <p>Join students who organize their semester with confidence, structure, and zero last-minute cramming.</p>
            <a href="student/register.php" class="btn btn-white">
                Create Free Account <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>
    </section>

    <!-- Footer -->
    <footer id="about">
        <div class="footer-content">
            <div class="footer-brand">
                <div class="brand-icon" style="width:38px; height:38px; font-size:1.1rem;">
                    <i class="fa-solid fa-graduation-cap"></i>
                </div>
                <span>Smart AI-Powered Study Planner </span>
            </div>
            <div class="footer-links">
                <a href="student/login.php" style="color:#94a3b8; text-decoration:none; margin-right:1.5rem; font-weight:600;">Student Portal</a>
                <a href="admin/admin_login.php" style="color:#94a3b8; text-decoration:none; font-weight:600;">Admin Portal</a>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> Smart AI-Powered Study Planner. All Rights Reserved.</p>
        </div>
    </footer>

    <!-- Interactive Script for Demo Slider and Subject Input -->
    <script>
        function updateDemo() {
            let val = document.getElementById('confRange').value;
            let subject = document.getElementById('subjectInput').value.trim();
            if (!subject) {
                subject = "this subject";
            } else {
                subject = '"' + subject + '"';
            }

            document.getElementById('confValue').innerText = val + '%';
            
            let durationOutput = document.getElementById('durationOutput');
            let explanationOutput = document.getElementById('explanationOutput');

            if (val < 35) {
                durationOutput.innerText = "2.0 Hours";
                durationOutput.style.color = "#ef4444";
                explanationOutput.innerText = "Because your confidence score for " + subject + " is low (" + val + "%), the AI engine allocates a full 2.0-hour deep focus block to help you master difficult material.";
            } else if (val < 70) {
                durationOutput.innerText = "1.0 Hour";
                durationOutput.style.color = "#f59e0b";
                explanationOutput.innerText = "With a moderate confidence level (" + val + "%) for " + subject + ", the AI engine assigns a 1.0-hour session to maintain steady understanding without overloading your daily queue.";
            } else {
                durationOutput.innerText = "0.5 Hours (30 Min)";
                durationOutput.style.color = "#10b981";
                explanationOutput.innerText = "Since you have high confidence (" + val + "%) in " + subject + ", the AI assigns a quick 30-minute revision slot to review key topics efficiently.";
            }
        }
    </script>
</body>
</html>