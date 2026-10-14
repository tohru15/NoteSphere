<?php
session_start();
require_once('../includes/db_config.php');
/** @var PDO $conn */

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'professor') {
    header("Location: ../auth/login.php"); exit();
}

$prof_id = $_SESSION['user_id'] ?? 1;
$success_msg = ''; $error_msg = '';

if (isset($_POST['post_announcement'])) {
    $title = trim($_POST['title']);
    $subject = trim($_POST['subject']);
    $year_level = trim($_POST['year_level']);
    $section = trim($_POST['section']);
    $content = trim($_POST['content']);
    $meet_link = trim($_POST['meet_link']);
    $quiz_link = trim($_POST['quiz_link']);
    $deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : null;

    if (!empty($title) && !empty($content)) {
        try {
            $stmt = $conn->prepare("INSERT INTO announcements (professor_id, title, subject, year_level, section, message, meet_link, quiz_link, deadline, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())");
            $stmt->execute([$prof_id, $title, $subject, $year_level, $section, $content, $meet_link, $quiz_link, $deadline]);
            $success_msg = "Tagumpay na na-broadcast ang post na may kasamang Class Link at Quiz/Deadline!";
        } catch (Exception $e) {
            $error_msg = "Error: " . $e->getMessage();
        }
    } else {
        $error_msg = "Kailangan punan ang Title at Content!";
    }
}

// Delete logic
if (isset($_GET['delete'])) {
    $conn->prepare("DELETE FROM announcements WHERE id = ? AND professor_id = ?")->execute([$_GET['delete'], $prof_id]);
    header("Location: professor_announcements.php"); exit();
}

$my_announcements = $conn->prepare("SELECT * FROM announcements WHERE professor_id = ? ORDER BY created_at DESC");
$my_announcements->execute([$prof_id]);
$announcements = $my_announcements->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Post Announcement & LMS Tools | NoteSphere</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#0f172a">
    <style>
        :root { 
            --neon: #39FF14; --neon-glow: rgba(57, 255, 20, 0.15);
            --bg: #050505; --card-bg: #0a0a0a; --text: #f8fafc; 
            --sidebar-bg: #000; --border-color: #1a1a1a; --sub-text: #888;
            --sidebar-width: 280px; --danger: #ff4d4d; --view: #2ecc71; --edit: #3498db;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background: var(--bg); color: var(--text); display: flex; min-height: 100vh; }
        
        .sidebar { width: var(--sidebar-width); height: 100vh; background: var(--sidebar-bg); border-right: 1px solid var(--border-color); padding: 35px 25px; position: fixed; display: flex; flex-direction: column; justify-content: space-between; z-index: 100; }
        .sidebar-brand { display: flex; align-items: center; gap: 12px; margin-bottom: 40px; }
        .sidebar-brand img { width: 45px; height: 45px; border-radius: 8px; border: 2px solid var(--neon); object-fit: cover; }
        .sidebar-brand h2 { font-weight: 800; color: var(--neon); font-size: 1.3rem; }
        .menu { display: flex; flex-direction: column; gap: 8px; }
        .menu a { display: flex; align-items: center; gap: 14px; padding: 14px 18px; text-decoration: none; color: var(--sub-text); font-weight: 600; font-size: 0.92rem; border-radius: 12px; transition: 0.3s; }
        .menu a:hover, .menu a.active { background: var(--neon-glow); color: var(--neon); }

        .main { margin-left: 280px; padding: 40px; width: calc(100% - 280px); }
        h1 { font-size: 1.8rem; font-weight: 800; margin-bottom: 25px; }

        .grid-layout { display: grid; grid-template-columns: 1.2fr 1fr; gap: 30px; }
        .card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 24px; padding: 30px; box-shadow: 0 8px 30px rgba(0,0,0,0.4); }
        
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; color: var(--text); font-weight: 600; margin-bottom: 6px; font-size: 0.85rem; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 11px 15px; border-radius: 12px; border: 1px solid var(--border-color); background: var(--bg); color: var(--text); outline: none; font-size: 0.88rem; }
        .form-group textarea { resize: vertical; height: 100px; }
        
        .btn-submit { width: 100%; background: var(--neon); color: #000; border: none; padding: 14px; border-radius: 12px; font-weight: 800; cursor: pointer; transition: 0.3s; }
        .btn-submit:hover { opacity: 0.9; transform: translateY(-2px); }

        .alert-success { background: rgba(46, 204, 113, 0.1); border: 1px solid var(--view); color: var(--view); padding: 12px; border-radius: 10px; margin-bottom: 20px; font-size: 0.9rem; font-weight: 600; }
        .alert-error { background: rgba(255, 77, 77, 0.1); border: 1px solid var(--danger); color: var(--danger); padding: 12px; border-radius: 10px; margin-bottom: 20px; font-size: 0.9rem; font-weight: 600; }

        .ann-list-item { background: var(--bg); border: 1px solid var(--border-color); padding: 18px; border-radius: 16px; margin-bottom: 15px; }
        .ann-title { font-weight: 700; font-size: 1.05rem; color: var(--text); margin-bottom: 4px; }
        .ann-meta { font-size: 0.78rem; color: var(--view); font-weight: 600; margin-bottom: 8px; }
        .lms-badge { display: inline-block; background: rgba(52, 152, 219, 0.15); color: var(--edit); border: 1px solid rgba(52, 152, 219, 0.3); padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; margin-top: 6px; margin-right: 5px; text-decoration: none; }
    </style>
</head>
<body>
    <nav class="sidebar">
        <div class="sidebar-top">
            <div class="sidebar-brand">
                <img src="../assets/cdmlogo.png" alt="CDM">
                <h2>NoteSphere</h2>
            </div>
            <div class="menu">
                <a href="professor_dashboard.php">Dashboard</a>
                <a href="professor_announcements.php" class="active">Announcements & LMS</a>
            </div>
        </div>
        <div class="menu">
            <a href="../auth/logout.php" style="color: var(--danger);">Logout</a>
        </div>
    </nav>

    <main class="main">
        <h1>Broadcast Announcement & LMS Tools</h1>

        <?php if(!empty($success_msg)): ?><div class="alert-success"><?= $success_msg ?></div><?php endif; ?>
        <?php if(!empty($error_msg)): ?><div class="alert-error"><?= $error_msg ?></div><?php endif; ?>

        <div class="grid-layout">
            <!-- Form -->
            <div class="card">
                <h3 style="margin-bottom: 18px; font-size: 1.15rem; color: var(--neon);">Gumawa ng Post / Task</h3>
                <form method="POST">
                    <div class="form-group">
                        <label>Title</label>
                        <input type="text" name="title" placeholder="Hal. Quiz 1 sa System Integration" required>
                    </div>
                    <div class="form-group">
                        <label>Subject / Course Code</label>
                        <input type="text" name="subject" placeholder="Hal. IT 311">
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div class="form-group">
                            <label>Year Level</label>
                            <select name="year_level">
                                <option value="All Years">All Years</option>
                                <option value="1st Year">1st Year</option>
                                <option value="2nd Year">2nd Year</option>
                                <option value="3rd Year">3rd Year</option>
                                <option value="4th Year">4th Year</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Section</label>
                            <input type="text" name="section" placeholder="Hal. 3A o All">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Mensahe / Panuto</label>
                        <textarea name="content" placeholder="Ilagay ang detalye dito..." required></textarea>
                    </div>
                    <div class="form-group">
                        <label>📹 Google Meet Link (Opsyonal)</label>
                        <input type="url" name="meet_link" placeholder="https://meet.google.com/abc-defg-hij">
                    </div>
                    <div class="form-group">
                        <label>📝 Google Form Quiz / Exam Link (Opsyonal)</label>
                        <input type="url" name="quiz_link" placeholder="https://forms.gle/xxxxxx">
                    </div>
                    <div class="form-group">
                        <label>⏰ Deadline ng Quiz/Exam (Opsyonal)</label>
                        <input type="datetime-local" name="deadline">
                    </div>
                    <button type="submit" name="post_announcement" class="btn-submit">I-publish ang Post</button>
                </form>
            </div>

            <!-- Listahan -->
            <div class="card">
                <h3 style="margin-bottom: 18px; font-size: 1.15rem; color: var(--neon);">History ng mga Post Mo</h3>
                <div style="max-height: 650px; overflow-y: auto; padding-right: 5px;">
                    <?php if(empty($announcements)): ?>
                        <p style="color: var(--sub-text); text-align: center; padding: 30px;">Wala ka pa ring post.</p>
                    <?php else: ?>
                        <?php foreach($announcements as $a): ?>
                            <div class="ann-list-item">
                                <div class="ann-title"><?= htmlspecialchars($a['title']) ?></div>
                                <div class="ann-meta">📚 <?= htmlspecialchars($a['subject']) ?> | 🎯 <?= htmlspecialchars($a['year_level']) ?>-<?= htmlspecialchars($a['section']) ?></div>
                                <div style="font-size: 0.85rem; color: var(--sub-text); margin-bottom: 8px;"><?= nl2br(htmlspecialchars($a['message'])) ?></div>
                                
                                <?php if(!empty($a['meet_link'])): ?>
                                    <a href="<?= htmlspecialchars($a['meet_link']) ?>" target="_blank" class="lms-badge">📹 Join Meet</a>
                                <?php endif; ?>
                                <?php if(!empty($a['quiz_link'])): ?>
                                    <a href="<?= htmlspecialchars($a['quiz_link']) ?>" target="_blank" class="lms-badge">📝 Take Quiz</a>
                                <?php endif; ?>
                                <?php if(!empty($a['deadline'])): ?>
                                    <div style="font-size: 0.72rem; color: var(--danger); margin-top: 6px; font-weight: 700;">⏳ Deadline: <?= $a['deadline'] ?></div>
                                <?php endif; ?>

                                <div style="margin-top: 10px; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border-color); padding-top: 8px;">
                                    <span style="font-size: 0.7rem; color: var(--sub-text);"><?= $a['created_at'] ?></span>
                                    <a href="?delete=<?= $a['id'] ?>" onclick="return confirm('Burahin?')" style="color: var(--danger); text-decoration: none; font-weight: 700; font-size: 0.75rem;">DELETE</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</body>
</html>