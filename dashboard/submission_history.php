<?php
// submission_history.php (Student View)
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../auth/login.php");
    exit();
}

$host = 'localhost';
$dbname = 'notesphere_db'; 
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database Connection Error: " . $e->getMessage());
}

$student_id = $_SESSION['user_id'];
$prof_id = isset($_GET['prof_id']) ? intval($_GET['prof_id']) : 0;
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'tasks'; // Default to tasks so they are separated

if ($prof_id <= 0) {
    header("Location: student_dashboard.php");
    exit();
}

// Fetch professor information
try {
    $stmt_prof = $pdo->prepare("SELECT full_name FROM users WHERE id = ? AND role = 'professor'");
    $stmt_prof->execute([$prof_id]);
    $prof_data = $stmt_prof->fetch(PDO::FETCH_ASSOC);
    
    if (!$prof_data) {
        header("Location: student_dashboard.php");
        exit();
    }
    $prof_name = $prof_data['full_name'];
} catch (Exception $e) {
    $prof_name = "Professor";
}

// Build query based on strict filter separation
$query = "
    SELECT a.*, s.status as sub_status, s.student_note, s.file_path, s.grade, s.submitted_at as sub_date
    FROM announcement_submissions s
    JOIN announcements a ON s.announcement_id = a.id
    WHERE a.professor_id = ? AND s.user_id = ?
";

if ($filter === 'tasks') {
    $query .= " AND s.status = 'Submitted'";
} elseif ($filter === 'announcements') {
    $query .= " AND s.status = 'Read'";
}

$query .= " ORDER BY s.submitted_at DESC";

try {
    $stmt_history = $pdo->prepare($query);
    $stmt_history->execute([$prof_id, $student_id]);
    $submissions = $stmt_history->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $submissions = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submission History | NoteSphere</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --neon: #39FF14;
            --neon-glow: rgba(57, 255, 20, 0.12);
            --card-bg: #0c0c0c;
            --text: #f8fafc;
            --sub-text: #94a3b8;
            --border: #1f293d;
            --sidebar-width: 260px;
        }
        body.light-mode {
            --card-bg: #ffffff;
            --text: #000000;
            --border: #e2e8f0;
            --sub-text: #475569;
            --neon: #2ecc71;
            --neon-glow: rgba(46, 204, 113, 0.1);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { 
            background: linear-gradient(rgba(11, 11, 11, 0.85), rgba(11, 11, 11, 0.92)), url('../assets/registerbg.png') no-repeat center fixed; 
            background-size: cover;
            color: var(--text); 
            display: flex; 
            min-height: 100vh; 
            overflow-x: hidden; 
            transition: background 0.3s ease, color 0.3s ease; 
        }
        body.light-mode {
            background:  url('../assets/registerbg.png') no-repeat center fixed;
            background-size: cover;
        }

        /* Sidebar Styles */
        .sidebar { width: var(--sidebar-width); height: 100vh; background: var(--card-bg); border-right: 1px solid var(--border); padding: 35px 20px; position: fixed; top: 0; left: 0; display: flex; flex-direction: column; justify-content: space-between; z-index: 1002; transition: transform 0.3s ease; overflow-y: auto; }
        .sidebar-top { display: flex; flex-direction: column; }
        .sidebar-brand { display: flex; align-items: center; gap: 12px; margin-bottom: 30px; padding-left: 5px; }
        .sidebar-brand h2 { font-weight: 800; color: var(--neon); font-size: 1.4rem; }
        .sidebar-brand img { width: 35px; height: auto; }
        
        .menu { display: flex; flex-direction: column; gap: 6px; }
        .menu a { display: flex; align-items: center; gap: 14px; padding: 12px 16px; text-decoration: none; color: var(--sub-text); font-weight: 600; font-size: 0.92rem; border-radius: 12px; transition: all 0.3s ease; }
        .menu a svg { width: 20px; height: 20px; stroke: currentColor; stroke-width: 2; fill: none; flex-shrink: 0; }
        .menu a:hover, .menu a.active { background: var(--neon-glow); color: var(--neon); }
        .menu a.active { border: 1px solid rgba(57, 255, 20, 0.2); }

        /* More Menu Styles */
        .more-toggle-btn { width: 100%; display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; background: transparent; border: none; color: var(--sub-text); font-weight: 600; font-size: 0.92rem; border-radius: 12px; cursor: pointer; transition: all 0.3s ease; }
        .more-toggle-left { display: flex; align-items: center; gap: 14px; }
        .more-toggle-btn svg { width: 20px; height: 20px; stroke: currentColor; stroke-width: 2; fill: none; flex-shrink: 0; }
        .more-chevron { width: 16px !important; height: 16px !important; transition: transform 0.3s ease; }
        .more-toggle-btn:hover { background: var(--neon-glow); color: var(--neon); }
        .submenu-container { display: flex; flex-direction: column; gap: 4px; padding-left: 15px; margin-top: 4px; }
        .submenu-container a { padding: 10px 14px; font-size: 0.88rem; }

        .sidebar-footer { display: flex; flex-direction: column; gap: 10px; margin-top: 20px; background: var(--card-bg); padding-top: 10px; }
        .theme-toggle-btn { width: 100%; padding: 12px 16px; background: var(--card-bg); border: 1px solid var(--border); color: var(--text); border-radius: 12px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 12px; transition: 0.3s; font-size: 0.9rem; text-decoration: none; }
        .theme-toggle-btn:hover { border-color: var(--neon); color: var(--neon); background: var(--neon-glow); }

        /* Mobile Header with Hamburger */
        .mobile-header { display: none; align-items: center; justify-content: space-between; padding: 15px 20px; background: var(--card-bg); border-bottom: 1px solid var(--border); position: fixed; top: 0; left: 0; width: 100%; z-index: 1000; }
        .sidebar-toggle-btn { background: var(--card-bg); border: 1px solid var(--border); cursor: pointer; display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 10px; color: var(--text); }
        .sidebar-toggle-btn svg { width: 20px; height: 20px; stroke: currentColor; stroke-width: 2; fill: none; }

        /* Sidebar Overlay */
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0, 0, 0, 0.6); z-index: 1001; backdrop-filter: blur(2px); }

        main { margin-left: var(--sidebar-width); flex: 1; padding: 30px; max-width: 900px; width: 100%; margin-right: auto; }

        .header-nav { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
        .back-circle-btn { display: inline-flex; align-items: center; gap: 8px; text-decoration: none; color: var(--text); font-weight: 600; font-size: 0.85rem; background: var(--card-bg); border: 1px solid var(--border); padding: 6px 14px 6px 6px; border-radius: 30px; transition: all 0.2s ease; box-shadow: 0 1px 2px rgba(0,0,0,0.02); }
        .back-circle-btn .icon-circle { width: 28px; height: 28px; border-radius: 50%; background: var(--neon-glow); color: var(--neon); display: flex; align-items: center; justify-content: center; }
        .back-circle-btn .icon-circle svg { width: 16px; height: 16px; stroke: currentColor; stroke-width: 2.5; fill: none; }
        .back-circle-btn:hover { border-color: var(--neon); color: var(--neon); }

        .page-title-box { background: var(--card-bg); border: 1px solid var(--border); border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); transition: background 0.3s ease, border-color 0.3s ease; }
        .page-title-box h1 { font-size: 1.3rem; font-weight: 700; color: var(--text); margin-bottom: 4px; }
        .page-title-box p { font-size: 0.85rem; color: var(--sub-text); }

        .filter-tabs { display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap; }
        .filter-btn { padding: 6px 16px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; text-decoration: none; border: 1px solid var(--border); background: var(--card-bg); color: var(--sub-text); transition: 0.2s; }
        .filter-btn.active, .filter-btn:hover { background: var(--neon-glow); color: var(--neon); border-color: var(--neon); }

        .history-list { display: flex; flex-direction: column; gap: 12px; }
        .history-card { background: var(--card-bg); border: 1px solid var(--border); border-radius: 12px; padding: 20px; display: flex; flex-direction: column; gap: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); transition: background 0.3s ease, border-color 0.3s ease; }
        
        .history-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; }
        .history-top h3 { font-size: 1rem; font-weight: 700; color: var(--text); }
        .history-top span { font-size: 0.78rem; color: var(--sub-text); }

        .status-badge { display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; }
        .status-submitted { background: var(--neon-glow); color: var(--neon); }
        .status-read { background: var(--neon-glow); color: var(--neon); }

        .file-link { display: inline-flex; align-items: center; gap: 6px; font-size: 0.85rem; color: var(--neon); text-decoration: none; font-weight: 600; background: var(--neon-glow); padding: 8px 12px; border-radius: 8px; width: fit-content; transition: opacity 0.2s; }
        .file-link:hover { opacity: 0.85; }

        .history-grade { background: var(--neon-glow); color: var(--neon); padding: 8px 12px; border-radius: 8px; font-size: 0.85rem; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; width: fit-content; }

        /* Responsive Breakpoints */
        @media (max-width: 992px) {
            .mobile-header { display: flex; }
            .sidebar { transform: translateX(-100%); }
            .sidebar.show { transform: translateX(0); }
            .sidebar-overlay.show { display: block; }
            main { margin-left: 0; padding: 90px 20px 30px 20px; max-width: 100%; }
        }
    </style>
    <script>
        // Apply theme immediately before paint to prevent flash
        if (localStorage.getItem('notesphere_theme') === 'light') {
            document.documentElement.classList.add('light-mode');
            window.addEventListener('DOMContentLoaded', () => document.body.classList.add('light-mode'));
        }
    </script>
</head>
<body>

<!-- Mobile Header -->
<div class="mobile-header">
    <div style="display: flex; align-items: center; gap: 10px;">
        <img src="../assets/cdmlogo.png" alt="Logo" style="width: 28px; height: auto;">
        <h2 style="font-size: 1.1rem; color: var(--neon); font-weight: 700;">NoteSphere</h2>
    </div>
    <button class="sidebar-toggle-btn" id="sidebarToggleBtn" onclick="toggleSidebar()">
        <svg viewBox="0 0 24 24"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
    </button>
</div>

<!-- Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<nav class="sidebar" id="sidebar">
    <div class="sidebar-top">
        <div class="sidebar-brand">
            <img src="../assets/cdmlogo.png" alt="Logo">
            <h2>NoteSphere</h2>
        </div>
        <div class="menu">
            <a href="student_dashboard.php">
                <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                <span>Dashboard</span>
            </a>
            <a href="view_notes.php">
                <svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
                <span>View Notes</span>
            </a>
            <a href="help_board.php">
                <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path><circle cx="12" cy="10" r="1"></circle></svg>
                <span>Help Board (Q&A)</span>
            </a>

            <!-- More Dropdown Group -->
            <div class="menu-item-group" style="margin-top: 5px;">
                <button type="button" onclick="toggleMoreMenu(event)" class="more-toggle-btn" id="moreToggleBtn">
                    <div class="more-toggle-left">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="1"></circle><circle cx="19" cy="12" r="1"></circle><circle cx="5" cy="12" r="1"></circle></svg>
                        <span>More</span>
                    </div>
                    <svg class="more-chevron" id="moreChevron" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </button>

                <div id="moreSubmenu" class="submenu-container" style="display: none;">
                    <a href="archive_sy.php">
                        <svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
                        <span>Archive SY</span>
                    </a>
                    <a href="enrollment_update.php">
                        <svg viewBox="0 0 24 24"><path d="M22 10v6M2 10l10-5 10 5-10 5z"></path><path d="M6 12v5c3 3 9 3 12 0v-5"></path></svg>
                        <span>Enrollment Update</span>
                    </a>
                    <a href="system_guide.php">
                        <svg viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path></svg>
                        <span>System Guide</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="sidebar-footer">
        <button onclick="toggleGlobalTheme()" class="theme-toggle-btn" id="globalThemeBtn">
            <span id="themeIcon">🌞</span> <span id="themeText">Light Mode</span>
        </button>
        <a href="../auth/logout.php" class="theme-toggle-btn" style="color: #ff4d4d; border-color: rgba(255, 77, 77, 0.3);">
            <svg viewBox="0 0 24 24" style="width: 18px; height: 18px; stroke: currentColor; stroke-width: 2; fill: none;"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline></svg> <span>Logout</span>
        </a>
    </div>
</nav>

<main>
    <div class="header-nav">
        <a href="professor_announcements.php?prof_id=<?= $prof_id ?>" class="back-circle-btn">
            <div class="icon-circle">
                <svg viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            </div>
           
        </a>
    </div>

    <div class="page-title-box">
        <h1>Submission History</h1>
        <p>Prof. <?= htmlspecialchars($prof_name) ?> — View your submitted tasks separately from general read announcements.</p>
    </div>

    <div class="filter-tabs">
        <a href="submission_history.php?prof_id=<?= $prof_id ?>&filter=tasks" class="filter-btn <?= $filter === 'tasks' ? 'active' : '' ?>">Tasks & Submissions</a>
        <a href="submission_history.php?prof_id=<?= $prof_id ?>&filter=announcements" class="filter-btn <?= $filter === 'announcements' ? 'active' : '' ?>">General Announcements</a>
    </div>

    <?php if (empty($submissions)): ?>
        <div style="text-align: center; padding: 40px; background: var(--card-bg); border: 1px solid var(--border); border-radius: 12px; color: var(--sub-text); font-size: 0.9rem;">
            <?= $filter === 'tasks' ? 'No submitted tasks found for this class yet.' : 'No general announcements read yet.' ?>
        </div>
    <?php else: ?>
        <div class="history-list">
            <?php foreach ($submissions as $sub): 
                $status = $sub['sub_status'];
                $status_class = ($status === 'Submitted') ? 'status-submitted' : 'status-read';
                $display_title = !empty($sub['title']) ? $sub['title'] : (!empty($sub['subject']) ? $sub['subject'] : 'Untitled Task / Announcement');
            ?>
                <div class="history-card">
                    <div class="history-top">
                        <div>
                            <h3><?= htmlspecialchars($display_title) ?></h3>
                            <span><?= !empty($sub['sub_date']) ? date('M d, Y - h:i A', strtotime($sub['sub_date'])) : 'N/A' ?></span>
                        </div>
                        <span class="status-badge <?= $status_class ?>"><?= htmlspecialchars($status) ?></span>
                    </div>

                    <p style="font-size: 0.9rem; color: var(--text); line-height: 1.5;"><?= nl2br(htmlspecialchars($sub['message'])) ?></p>

                    <?php if ($status === 'Submitted'): ?>
                        <?php if (!empty($sub['file_path'])): ?>
                            <a href="<?= htmlspecialchars($sub['file_path']) ?>" target="_blank" class="file-link">
                                &#128206; View Attached File
                            </a>
                        <?php endif; ?>

                        <?php if (!empty($sub['student_note'])): ?>
                            <div style="font-size: 0.85rem; background: var(--card-bg); color: var(--text); padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border);">
                                <strong style="color: var(--neon);">Your Note:</strong> <?= htmlspecialchars($sub['student_note']) ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($sub['grade'])): ?>
                            <div class="history-grade">
                                <span>&#11088; Grade: <?= htmlspecialchars($sub['grade']) ?></span>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        sidebar.classList.toggle('show');
        overlay.classList.toggle('show');
    }

    function toggleMoreMenu(event) {
        event.preventDefault();
        const submenu = document.getElementById('moreSubmenu');
        const chevron = document.getElementById('moreChevron');
        if (submenu.style.display === 'none' || submenu.style.display === '') {
            submenu.style.display = 'flex';
            chevron.style.transform = 'rotate(180deg)';
        } else {
            submenu.style.display = 'none';
            chevron.style.transform = 'rotate(0deg)';
        }
    }

    function toggleGlobalTheme() {
        document.body.classList.toggle('light-mode');
        const isLight = document.body.classList.contains('light-mode');
        localStorage.setItem('notesphere_theme', isLight ? 'light' : 'dark');
        updateThemeButton(!isLight);
    }
    
    function updateThemeButton(isDark) {
        document.getElementById('themeIcon').textContent = isDark ? '🌞' : '🌙';
        document.getElementById('themeText').textContent = isDark ? 'Light Mode' : 'Dark Mode';
    }

    // Sync theme on page load
    if (localStorage.getItem('notesphere_theme') === 'light') {
        document.body.classList.add('light-mode');
        updateThemeButton(false);
    } else {
        document.body.classList.remove('light-mode');
        updateThemeButton(true);
    }
</script>
</body>
</html>