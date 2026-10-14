<?php
session_start();
include('../includes/db_config.php');
/** @var PDO $conn */

// Ensure the user is logged in as a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch student details
$studentStmt = $conn->prepare("SELECT full_name, course FROM users WHERE id = ?");
$studentStmt->execute([$user_id]);
$student = $studentStmt->fetch(PDO::FETCH_ASSOC);

// TODO: Kunin ang mga nakatago/na-archive na announcements ng student na ito (kung meron kang student_hidden_announcements table)
// TODO: Kunin din ang mga natapos at na-grade na submissions ng student mula sa submissions table
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Announcement & Activity Archives | NoteSphere</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#0f172a">
    <style>
        :root { 
            --neon: #39FF14; 
            --neon-glow: rgba(57, 255, 20, 0.15);
            --bg: #050505; 
            --card-bg: #0a0a0a; 
            --text: #f8fafc; 
            --border-color: #1a1a1a; 
            --sub-text: #888;
            --table-hover: #141414;
        }
        body.light-mode {
            --bg: #f4f6f9;
            --card-bg: #ffffff;
            --text: #000000;
            --border-color: #e2e8f0;
            --sub-text: #64748b;
            --neon: #2ecc71;
            --neon-glow: rgba(46, 204, 113, 0.1);
            --table-hover: #f8fafc;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        
        html, body {
            width: 100%;
            min-height: 100vh;
            overflow-x: hidden;
            background: linear-gradient(rgba(11, 11, 11, 0.85), rgba(11, 11, 11, 0.92)), url('../assets/registerbg.png') no-repeat center fixed;
            background-size: cover;
            color: var(--text);
        }
        body.light-mode {
            background: linear-gradient(rgba(244, 246, 249, 0.85), rgba(244, 246, 249, 0.92)), url('../assets/registerbg.png') no-repeat center fixed;
            background-size: cover;
        }

        .container-wrapper {
            width: 100%;
            max-width: 950px;
            margin: 0 auto;
            padding: 30px 15px;
        }
        .card-archive { 
            background: var(--card-bg); 
            border: 1px solid var(--border-color); 
            border-radius: 20px; 
            padding: 30px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.6);
        }
        .text-neon { color: var(--neon) !important; }
        
        .btn-back {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 50%;
            color: var(--text);
            text-decoration: none;
            margin-bottom: 20px;
            transition: 0.3s;
        }
        .btn-back:hover {
            border-color: var(--neon);
            color: var(--neon);
            transform: translateX(-3px);
        }

        /* Custom Tabs */
        .nav-pills .nav-link {
            color: var(--sub-text);
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.85rem;
            padding: 10px 20px;
            margin-right: 10px;
            transition: 0.2s;
        }
        .nav-pills .nav-link.active {
            background: var(--neon-glow) !important;
            color: var(--neon) !important;
            border-color: var(--neon);
        }
        
        .table-responsive {
            margin-top: 20px;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid var(--border-color);
        }
        table {
            width: 100%;
            margin-bottom: 0;
            color: var(--text) !important;
            background-color: var(--card-bg) !important;
            font-size: 0.9rem;
        }
        th, td {
            padding: 14px 16px;
            vertical-align: middle;
            border-color: var(--border-color) !important;
        }
        th {
            background-color: var(--border-color) !important;
            color: var(--text) !important;
            font-weight: 700;
        }
        tbody tr {
            background-color: var(--card-bg) !important;
            transition: 0.2s;
        }
        tbody tr:hover {
            background-color: var(--table-hover) !important;
        }
        .badge-grade {
            background: var(--neon-glow);
            color: var(--neon);
            border: 1px solid var(--neon);
            padding: 5px 10px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.75rem;
        }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--sub-text);
        }
        hr { border-color: var(--border-color); }

        /* Three vertical dots dropdown menu styling */
        .dropdown-menu {
            background-color: var(--card-bg) !important;
            border: 1px solid var(--border-color) !important;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            border-radius: 10px;
            padding: 8px;
        }
        .dropdown-item {
            color: var(--text) !important;
            font-size: 0.85rem;
            padding: 8px 12px;
            border-radius: 6px;
            transition: 0.2s;
            cursor: pointer;
        }
        .dropdown-item:hover {
            background-color: var(--neon-glow) !important;
            color: var(--neon) !important;
        }
        .btn-options {
            background: transparent;
            border: none;
            color: var(--sub-text);
            font-size: 1.3rem;
            letter-spacing: 1px;
            padding: 4px 10px;
            border-radius: 6px;
            transition: 0.2s;
        }
        .btn-options:hover, .btn-options:focus {
            color: var(--neon);
            background: var(--border-color);
        }

        @media (max-width: 576px) {
            .container-wrapper { padding: 15px 10px; }
            .card-archive { padding: 20px; border-radius: 15px; }
            table { font-size: 0.8rem; }
            th, td { padding: 10px 8px; }
        }
    </style>
</head>
<body>

    <div class="container-wrapper">
        <a href="student_dashboard.php" class="btn-back" title="Back to Dashboard">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        </a>

        <div class="card-archive">
            <h2 class="text-neon fw-bold mb-2">Announcement & Activity Archives</h2>
            <p class="text-muted small mb-4">View your hidden/archived announcements and track your completed or graded activity submissions.</p>
            
            <!-- Navigation Tabs -->
            <ul class="nav nav-pills mb-4" id="archiveTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="announcements-tab" data-bs-toggle="pill" data-bs-target="#announcements-content" type="button" role="tab">Hidden Announcements</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="activities-tab" data-bs-toggle="pill" data-bs-target="#activities-content" type="button" role="tab">Completed & Graded Activities</button>
                </li>
            </ul>

            <div class="tab-content" id="archiveTabContent">
                <!-- Tab 1: Hidden/Archived Announcements -->
                <div class="tab-pane fade show active" id="announcements-content" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Announcement Title / Content</th>
                                    <th>Posted By / Date</th>
                                    <th>Hidden Date</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Sample Row (Palitan ng dynamic data mula sa database) -->
                                <tr>
                                    <td>
                                        <strong class="text-neon">Midterm Examination Guidelines</strong><br>
                                        <span class="small text-muted">Please read the attached PDF rules carefully before taking the test...</span>
                                    </td>
                                    <td><small class="text-muted">Prof. Smith<br>Oct 12, 2026</small></td>
                                    <td><small class="text-muted">Oct 15, 2026</small></td>
                                    <td class="text-end">
                                        <div class="dropdown">
                                            <button class="btn-options" type="button" data-bs-toggle="dropdown" aria-expanded="false">&#8942;</button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li><a class="dropdown-item" href="#" onclick="alert('Restore announcement to dashboard')">Restore to Dashboard</a></li>
                                                <li><a class="dropdown-item text-danger" href="#" onclick="alert('Permanently deleted')">Delete Forever</a></li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tab 2: Completed & Graded Activities -->
                <div class="tab-pane fade" id="activities-content" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Activity Title</th>
                                    <th>Submitted File</th>
                                    <th>Grade / Status</th>
                                    <th>Professor Feedback</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Sample Row (Palitan ng dynamic data mula sa submissions table) -->
                                <tr>
                                    <td>
                                        <strong class="text-neon">Database Schema Design (Capstone)</strong><br>
                                        <span class="small text-muted">Submitted on: Oct 20, 2026</span>
                                    </td>
                                    <td><a href="#" class="small text-neon text-decoration-underline">NoteSphere_ERD.pdf</a></td>
                                    <td><span class="badge-grade">95 / 100 (Passed)</span></td>
                                    <td><small class="text-muted">"Excellent normalization and table relationships!"</small></td>
                                    <td class="text-end">
                                        <div class="dropdown">
                                            <button class="btn-options" type="button" data-bs-toggle="dropdown" aria-expanded="false">&#8942;</button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li><a class="dropdown-item" href="#" onclick="alert('View submission details')">View Submission</a></li>
                                                <li><a class="dropdown-item" href="#" onclick="alert('Download feedback file')">Download Attachment</a></li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Theme persistence
        if (localStorage.getItem('notesphere_theme') === 'light') {
            document.body.classList.add('light-mode');
        }
    </script>
</body>
</html>