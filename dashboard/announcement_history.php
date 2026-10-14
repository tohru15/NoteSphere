<?php
// announcement_history.php
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

// Fetch read announcement history joined with announcements, professor info, and student submissions/grades
try {
    $stmt = $pdo->prepare("
        SELECT 
            ah.read_at,
            ah.is_read,
            a.id AS announcement_id,
            a.professor_id,
            a.title,
            a.message,
            a.created_at,
            u.full_name AS professor_name,
            sub.id AS submission_id,
            sub.submission_text,
            sub.file_path AS student_file,
            sub.submitted_at,
            sub.status AS sub_status,
            sub.grade,
            sub.feedback
        FROM announcement_history ah
        JOIN announcements a ON ah.announcement_id = a.id
        JOIN users u ON a.professor_id = u.id
        LEFT JOIN announcement_submissions sub ON sub.announcement_id = a.id AND sub.student_id = ?
        WHERE ah.student_id = ?
        ORDER BY ah.read_at DESC
    ");
    $stmt->execute([$student_id, $student_id]);
    $history_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $history_items = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Read History & Grades - NoteSphere</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --neon: #39FF14;
            --neon-glow: rgba(57, 255, 20, 0.12);
            --bg: #050505;
            --card-bg: #0c0c0c;
            --text: #f8fafc;
            --sub-text: #94a3b8;
            --border: #1f293d;
            --sidebar-width: 260px;
        }
        body.light-mode {
            --bg: #f8fafc; --card-bg: #ffffff; --text: #0f172a; --sub-text: #64748b; --border: #e2e8f0; --neon: #16a34a; --neon-glow: rgba(22, 163, 74, 0.1);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background: var(--bg); color: var(--text); display: flex; min-height: 100vh; overflow-x: hidden; width: 100%; position: relative; }

        .sidebar { width: var(--sidebar-width); height: 100vh; background: var(--card-bg); border-right: 1px solid var(--border); padding: 35px 20px; position: fixed; top: 0; left: 0; display: flex; flex-direction: column; justify-content: space-between; z-index: 1002; transition: transform 0.3s ease; }
        .sidebar-top { display: flex; flex-direction: column; }
        .sidebar-brand { display: flex; align-items: center; gap: 12px; margin-bottom: 30px; padding-left: 5px; }
        .sidebar-brand h2 { font-weight: 800; color: var(--neon); font-size: 1.4rem; }
        .sidebar-brand img { width: 35px; height: auto; }
        .menu { display: flex; flex-direction: column; gap: 6px; }
        .menu a { display: flex; align-items: center; gap: 14px; padding: 12px 16px; text-decoration: none; color: var(--sub-text); font-weight: 600; font-size: 0.92rem; border-radius: 12px; transition: all 0.3s ease; }
        .menu a svg { width: 20px; height: 20px; stroke: currentColor; stroke-width: 2; fill: none; flex-shrink: 0; }
        .menu a:hover, .menu a.active { background: var(--neon-glow); color: var(--neon); }

        .sidebar-footer { display: flex; flex-direction: column; gap: 10px; }
        .theme-toggle-btn { width: 100%; padding: 12px 16px; background: var(--card-bg); border: 1px solid var(--border); color: var(--text); border-radius: 12px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 12px; font-size: 0.9rem; }

        main { margin-left: var(--sidebar-width); flex: 1; padding: 40px; max-width: calc(100vw - var(--sidebar-width)); width: 100%; overflow-x: hidden; }
        
        .header-nav { display: flex; align-items: center; justify-content: space-between; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
        .back-btn { background: var(--card-bg); border: 1px solid var(--border); color: var(--text); padding: 10px 18px; border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 8px; transition: 0.2s; white-space: nowrap; }
        .back-btn:hover { border-color: var(--neon); color: var(--neon); }

        .history-list { display: flex; flex-direction: column; gap: 16px; width: 100%; }
        .history-card { background-color: var(--card-bg); border: 1px solid var(--border); border-radius: 16px; padding: 22px; display: grid; grid-template-columns: 2fr 1.2fr; gap: 20px; width: 100%; word-break: break-word; overflow: hidden; }
        @media(max-width: 900px) { .history-card { grid-template-columns: 1fr; } }

        .history-main h3 { font-size: 1.1rem; font-weight: 700; margin-bottom: 6px; color: var(--text); word-break: break-word; }
        .history-main p { color: var(--sub-text); font-size: 0.88rem; line-height: 1.5; margin-bottom: 12px; white-space: pre-wrap; word-break: break-word; }
        .prof-tag { font-size: 0.8rem; color: var(--neon); font-weight: 600; }

        .history-sidebar { background: rgba(255,255,255,0.01); border: 1px solid var(--border); border-radius: 12px; padding: 16px; display: flex; flex-direction: column; justify-content: space-between; gap: 10px; overflow: hidden; }
        .status-badge { display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
        .status-pending { background: rgba(234, 179, 8, 0.1); color: #eab308; border: 1px solid rgba(234, 179, 8, 0.2); }
        .status-submitted { background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.2); }
        .status-graded { background: var(--neon-glow); color: var(--neon); border: 1px solid var(--neon); }

        .grade-box { font-size: 0.95rem; font-weight: 700; color: var(--text); margin-top: 4px; }
        .feedback-text { font-size: 0.82rem; color: var(--sub-text); font-style: italic; margin-top: 4px; word-break: break-word; }
        .no-data { color: var(--sub-text); font-style: italic; background: var(--card-bg); padding: 40px; border-radius: 16px; text-align: center; border: 1px dashed var(--border); grid-column: 1 / -1; }

        /* Mobile Responsive Drawer & Viewport Lockdown */
        .mobile-header { display: none; background: var(--card-bg); border-bottom: 1px solid var(--border); padding: 15px 20px; align-items: center; justify-content: space-between; position: fixed; top: 0; left: 0; width: 100%; z-index: 1003; }
        .menu-toggle-btn { background: none; border: none; color: var(--text); cursor: pointer; display: flex; align-items: center; }
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.6); z-index: 1001; }

        @media (max-width: 992px) {
            .mobile-header { display: flex; }
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .sidebar-overlay.active { display: block; }
            main { margin-left: 0; max-width: 100vw; padding: 20px; padding-top: 90px; }
        }
    </style>
</head>
<body>

<div class="mobile-header">
    <div style="display: flex; align-items: center; gap: 10px;">
        <img src="../assets/cdmlogo.png" alt="Logo" style="width: 28px; height: auto;">
        <h2 style="font-size: 1.2rem; color: var(--neon);">NoteSphere</h2>
    </div>
    <button class="menu-toggle-btn" onclick="toggleSidebar()">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
    </button>
</div>

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
            <a href="announcement_history.php" class="active">
                <svg viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"></path></svg>
                <span>Read History & Grades</span>
            </a>
            <a href="help_board.php">
                <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path><circle cx="12" cy="10" r="1"></circle></svg>
                <span>Help Board (Q&A)</span>
            </a>
        </div>
    </div>
    <div class="sidebar-footer">
        <button onclick="toggleGlobalTheme()" class="theme-toggle-btn" id="globalThemeBtn">
            <span id="themeIcon">🌞</span> <span id="themeText">Light Mode</span>
        </button>
        <a href="../auth/logout.php" class="theme-toggle-btn" style="text-decoration: none;">
            <svg viewBox="0 0 24 24" style="width: 18px; height: 18px; stroke: currentColor; stroke-width: 2; fill: none;"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline></svg>
            <span>Logout</span>
        </a>
    </div>
</nav>

<main>
    <div class="header-nav">
        <div>
            <h1 style="font-size: 1.6rem; font-weight: 800; word-break: break-word;">Read History & Teacher Grades</h1>
            <p style="color: var(--sub-text); font-size: 0.9rem; margin-top: 4px;">Track announcements you have read and monitor your submitted task grades.</p>
        </div>
        <a href="student_dashboard.php" class="back-btn">
            ← Back to Dashboard
        </a>
    </div>

    <div class="history-list">
        <?php if (!empty($history_items)): ?>
            <?php foreach ($history_items as $item): ?>
                <div class="history-card">
                    <div class="history-main">
                        <span class="prof-tag">Prof. <?php echo htmlspecialchars($item['professor_name']); ?></span>
                        <h3><?php echo htmlspecialchars($item['title']); ?></h3>
                        <p><?php echo nl2br(htmlspecialchars($item['message'] ?? '')); ?></p>
                        <div style="font-size: 0.75rem; color: var(--sub-text);">
                            Read on: <?php echo date("M d, Y - h:i A", strtotime($item['read_at'])); ?>
                        </div>
                    </div>

                    <div class="history-sidebar">
                        <div>
                            <div style="font-size: 0.78rem; color: var(--sub-text); margin-bottom: 4px;">Submission & Grade Status:</div>
                            <?php if (!empty($item['submission_id'])): ?>
                                <?php if ($item['grade'] !== null): ?>
                                    <span class="status-badge status-graded">Graded</span>
                                    <div class="grade-box">Grade: <?php echo htmlspecialchars($item['grade']); ?></div>
                                    <?php if (!empty($item['feedback'])): ?>
                                        <div class="feedback-text">Feedback: "<?php echo htmlspecialchars($item['feedback']); ?>"</div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="status-badge status-submitted">Submitted</span>
                                    <div style="font-size: 0.8rem; color: var(--sub-text); margin-top: 6px;">Waiting for professor review.</div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="status-badge status-pending">No Submission Required / Sent</span>
                                <div style="font-size: 0.8rem; color: var(--sub-text); margin-top: 6px;">Read-only announcement or task not yet submitted.</div>
                            <?php endif; ?>
                        </div>

                        <div style="border-top: 1px solid var(--border); padding-top: 10px; font-size: 0.78rem;">
                            <a href="professor_announcements.php?prof_id=<?php echo $item['professor_id']; ?>" style="color: var(--neon); text-decoration: none; font-weight: 600;">View Full Post →</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-data">
                You haven't read or tracked any announcements in your history yet. Browse your professors' announcements to populate this section.
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        sidebar.classList.toggle('open');
        overlay.classList.toggle('active');
    }

    function toggleGlobalTheme() {
        document.body.classList.toggle('light-mode');
        const isLight = document.body.classList.contains('light-mode');
        localStorage.setItem('notesphere_theme', isLight ? 'light' : 'dark');
        updateThemeButton(isLight);
    }
    function updateThemeButton(isLight) {
        document.getElementById('themeIcon').textContent = isLight ? '🌙' : '🌞';
        document.getElementById('themeText').textContent = isLight ? 'Dark Mode' : 'Light Mode';
    }
    if (localStorage.getItem('notesphere_theme') === 'light') {
        document.body.classList.add('light-mode');
        updateThemeButton(true);
    }
</script>
</body>
</html>