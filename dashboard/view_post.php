<?php
session_start();
require_once '../includes/db_config.php';
/** @var PDO $conn */

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$post_id = $_GET['id'] ?? null;

if (!$post_id) {
    header("Location: help_board.php");
    exit();
}

if (isset($_GET['mark_read'])) {
    $notif_id = $_GET['mark_read'];
    $mark_stmt = $conn->prepare("UPDATE help_notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $mark_stmt->execute([$notif_id, $user_id]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_comment'])) {
    $comment_text = trim($_POST['comment_text']);
    if (!empty($comment_text)) {
        // Updated to use user_id matching help_comments structure
        $c_stmt = $conn->prepare("INSERT INTO help_comments (question_id, user_id, comment_text, created_at) VALUES (?, ?, ?, NOW())");
        $c_stmt->execute([$post_id, $user_id, $comment_text]);

        $q_owner_stmt = $conn->prepare("SELECT student_id FROM help_questions WHERE id = ?");
        $q_owner_stmt->execute([$post_id]);
        $owner = $q_owner_stmt->fetch(PDO::FETCH_ASSOC);

        if ($owner && $owner['student_id'] != $user_id) {
            $notif_insert = $conn->prepare("INSERT INTO help_notifications (user_id, actor_id, question_id, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
            $notif_insert->execute([$owner['student_id'], $user_id, $post_id]);
        }

        header("Location: view_post.php?id=" . $post_id);
        exit();
    }
}

$post_stmt = $conn->prepare("
    SELECT q.*, u.full_name, u.role, u.profile_pic, u.email 
    FROM help_questions q 
    JOIN users u ON q.student_id = u.id 
    WHERE q.id = ?
");
$post_stmt->execute([$post_id]);
$post = $post_stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    header("Location: help_board.php");
    exit();
}

// Updated JOIN clause to use c.user_id instead of c.student_id
$comm_stmt = $conn->prepare("
    SELECT c.*, u.full_name, u.role, u.profile_pic, u.email 
    FROM help_comments c 
    JOIN users u ON c.user_id = u.id 
    WHERE c.question_id = ? 
    ORDER BY c.created_at ASC
");
$comm_stmt->execute([$post_id]);
$comments = $comm_stmt->fetchAll(PDO::FETCH_ASSOC);

$user_stmt = $conn->prepare("SELECT full_name, email, profile_pic FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$current_user = $user_stmt->fetch(PDO::FETCH_ASSOC);

function get_profile_picture($pic, $email) {
    if (!empty($pic)) {
        if (strpos($pic, 'http') === 0) {
            return $pic;
        } else {
            $clean_path = str_replace('../', '', $pic);
            return '../' . ltrim($clean_path, '/');
        }
    }
    return 'https://api.dicebear.com/7.x/avataaars/svg?seed=' . urlencode($email ?? 'user');
}

$profile_pic = get_profile_picture($current_user['profile_pic'] ?? '', $current_user['email'] ?? 'user');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Post - NoteSphere</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --card-bg: #ffffff; 
            --text-color: #0f172a;
            --sub-text: #64748b; 
            --border-color: #e2e8f0; 
            --primary-blue: #10b981; 
            --hover-bg: #f1f5f9;
            --textarea-bg: #f8fafc;
            --badge-bg: #f1f5f9;
            --target-bg: rgba(16, 185, 129, 0.12);
            --target-border: #10b981;
        }
        
        [data-theme="dark"] {
            --bg-overlay: linear-gradient(rgba(11, 11, 11, 0.88), rgba(11, 11, 11, 0.95));
            --card-bg: #121214; 
            --text-color: #f8fafc;
            --sub-text: #94a3b8; 
            --border-color: #27272a; 
            --primary-blue: #39FF14; 
            --hover-bg: #1e1e24;
            --textarea-bg: #09090b;
            --badge-bg: #1c1c21;
            --target-bg: rgba(55, 255, 20, 0.73);
            --target-border: #39FF14;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
        
        html {
            background:  url('../assets/registerbg.png') no-repeat center center fixed;
            background-size: cover;
            min-height: 100%;
        }

        body {
            background: transparent;
            color: var(--text-color); 
            padding: 16px 10px; 
            min-height: 100vh;
            transition: color 0.3s ease; 
        }
        
        .fb-container { max-width: 560px; margin: 0 auto; }
        
        .top-nav-card {
            background: var(--card-bg);
            border-radius: 14px;
            border: 1px solid var(--border-color);
            padding: 12px 16px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            transition: background-color 0.3s ease, border-color 0.3s ease;
        }

        .top-nav-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1.02rem;
            font-weight: 700;
            color: var(--text-color);
        }

        .top-nav-title span.emoji {
            font-size: 1.15rem;
        }

        .close-btn-card {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: var(--hover-bg);
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--sub-text);
            text-decoration: none;
            transition: all 0.2s ease;
            flex-shrink: 0;
        }
        .close-btn-card svg { width: 16px; height: 16px; stroke: currentColor; stroke-width: 2.2; fill: none; stroke-linecap: round; stroke-linejoin: round; }
        .close-btn-card:hover { background: var(--border-color); color: var(--primary-blue); border-color: var(--primary-blue); }

        .post-card, .comment-card, .comment-box-card { 
            background: var(--card-bg); 
            border-radius: 14px; 
            border: 1px solid var(--border-color); 
            padding: 16px; 
            margin-bottom: 12px; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.06); 
            transition: background-color 0.3s ease, border-color 0.3s ease; 
        }
        
        .post-header, .comment-header { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
        .avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid var(--primary-blue); background: var(--border-color); }
        .author-info h4 { font-size: 0.95rem; font-weight: 700; color: var(--text-color); }
        .author-info span { font-size: 0.72rem; color: var(--sub-text); }
        
        .post-content p, .comment-body p { font-size: 0.92rem; line-height: 1.5; margin-bottom: 8px; word-break: break-word; color: var(--text-color); }
        .comments-section-title { font-size: 0.95rem; font-weight: 700; margin: 16px 0 10px 0; color: var(--text-color); padding-left: 2px; }
        
        .comment-card:target {
            background-color: var(--target-bg) !important;
            border: 2px solid var(--target-border) !important;
        }
        
        .comment-form textarea { width: 100%; padding: 12px; border-radius: 10px; border: 1px solid var(--border-color); background: var(--textarea-bg); color: var(--text-color); resize: vertical; min-height: 75px; font-size: 0.9rem; margin-bottom: 10px; outline: none; transition: border-color 0.2s; }
        .comment-form textarea:focus { border-color: var(--primary-blue); }
        .submit-btn { background: var(--primary-blue); color: #000; border: none; padding: 8px 16px; border-radius: 8px; font-weight: 800; cursor: pointer; font-size: 0.85rem; float: right; transition: opacity 0.2s; }
        .submit-btn:hover { opacity: 0.9; }
        .clearfix::after { content: ""; clear: both; display: table; }

        @media(max-width: 480px) {
            body { padding: 10px 6px; }
            .top-nav-card, .post-card, .comment-card, .comment-box-card { padding: 14px; border-radius: 12px; }
            .top-nav-title { font-size: 0.95rem; }
        }
    </style>
</head>
<body>
    <div class="fb-container">
        <!-- Top Compact Card Header matching reference style -->
        <div class="top-nav-card">
            <div class="top-nav-title">
                <span class="emoji">📜</span>
                <span>View Post Details</span>
            </div>
            <a href="help_board.php" class="close-btn-card" title="Back">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </a>
        </div>

        <!-- Main Post -->
        <div class="post-card">
            <div class="post-header">
                <img src="<?php echo htmlspecialchars(get_profile_picture($post['profile_pic'] ?? '', $post['email'] ?? '')); ?>" class="avatar" alt="Avatar">
                <div class="author-info">
                    <h4><?php echo htmlspecialchars($post['full_name']); ?></h4>
                    <span><?php echo date('M d, Y • h:i A', strtotime($post['created_at'])); ?></span>
                </div>
            </div>
            <div class="post-content">
                <?php if (!empty($post['title'])): ?>
                    <h3 style="margin-bottom: 8px; font-size: 1.05rem; font-weight: 700;"><?php echo htmlspecialchars($post['title']); ?></h3>
                <?php endif; ?>
                <p><?php echo nl2br(htmlspecialchars($post['question'] ?? $post['content'] ?? '')); ?></p>
            </div>
        </div>

        <div class="comments-section-title"> Comments (<?php echo count($comments); ?>)</div>

        <!-- Comments List -->
        <div class="comments-list">
            <?php if (!empty($comments)): ?>
                <?php foreach ($comments as $c): ?>
                    <?php 
                        $comment_body = $c['comment_text'] ?? '';
                        $c_pic = get_profile_picture($c['profile_pic'] ?? '', $c['email'] ?? '');
                    ?>
                    <div class="comment-card" id="comment-<?php echo $c['id']; ?>">
                        <div class="comment-header">
                            <img src="<?php echo htmlspecialchars($c_pic); ?>" class="avatar" alt="Avatar">
                            <div class="author-info">
                                <h4><?php echo htmlspecialchars($c['full_name'] ?? 'User'); ?> <span style="font-size: 0.65rem; background: var(--badge-bg); color: var(--sub-text); padding: 2px 6px; border-radius: 5px; font-weight: 600;"><?php echo htmlspecialchars($c['role'] ?? 'Student'); ?></span></h4>
                                <span><?php echo date('M d, Y • h:i A', strtotime($c['created_at'])); ?></span>
                            </div>
                        </div>
                        <div class="comment-body">
                            <p><?php echo nl2br(htmlspecialchars($comment_body)); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color: var(--sub-text); text-align: center; padding: 16px 0; font-size: 0.88rem;">No comments yet. Be the first to reply!</p>
            <?php endif; ?>
        </div>

        <!-- Comment Form Box -->
        <div class="comment-box-card" style="margin-top: 10px;">
            <form action="view_post.php?id=<?php echo $post_id; ?>" method="POST" class="comment-form clearfix">
                <textarea name="comment_text" placeholder="Write a comment..." required></textarea>
                <button type="submit" name="submit_comment" class="submit-btn">Post Comment</button>
            </form>
        </div>
    </div>

    <script>
        function applyTheme(theme) {
            if (theme === 'dark') {
                document.documentElement.setAttribute('data-theme', 'dark');
            } else {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        }

        document.addEventListener("DOMContentLoaded", () => {
            const savedTheme = localStorage.getItem('notesphere_theme') || localStorage.getItem('theme') || 'dark';
            applyTheme(savedTheme);

            if (window.location.hash) {
                const targetElement = document.querySelector(window.location.hash);
                if (targetElement) {
                    setTimeout(() => {
                        targetElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }, 200);
                }
            }
        });

        window.addEventListener('storage', (event) => {
            if (event.key === 'notesphere_theme' || event.key === 'theme') {
                if (event.newValue) applyTheme(event.newValue);
            }
        });
    </script>
</body>
</html>