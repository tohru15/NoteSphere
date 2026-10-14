
<?php

/**
 * NoteSphere - Student Help Board
 * Facebook-style Help Board
 *
 * - No sidebar
 * - No storage widget
 * - Student posts/questions
 * - Anonymous posting
 * - Comments
 * - Hide post
 * - Admin warnings with X button
 * - Admin-controlled student posting
 * - Help Board-only student ban protection
 * - Does NOT block the whole student account
 */

session_start();

require_once __DIR__ . '/../includes/db_config.php';

/** @var PDO $conn */

// =========================================================
// STUDENT SECURITY CHECK
// =========================================================
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'student'
) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];

// =========================================================
// CHECK IF STUDENT IS BANNED FROM HELP BOARD ONLY
// =========================================================
// IMPORTANT:
// This does NOT block the student's whole account.
// It only prevents access to Help Board.
$student_name = 'Student';
$is_banned = false;

try {
    $stmt = $conn->prepare("
        SELECT
            full_name,
            is_banned
        FROM users
        WHERE user_id = ?
        LIMIT 1
    ");

    $stmt->execute([$user_id]);

    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($student) {

        $student_name = $student['full_name'] ?? 'Student';

        $is_banned =
            isset($student['is_banned']) &&
            (int) $student['is_banned'] === 1;

        // =====================================================
        // HELP BOARD ONLY BAN
        // =====================================================
        if ($is_banned) {

            $_SESSION['help_board_banned_message'] =
                'Your access to the Help Board has been disabled by the administrator.';

            header("Location: student_home.php");
            exit();
        }
    }

} catch (PDOException $e) {

    // Fallback in case the is_banned column does not exist yet.
    // This keeps the rest of the Help Board working.
    try {

        $stmt = $conn->prepare("
            SELECT full_name
            FROM users
            WHERE user_id = ?
            LIMIT 1
        ");

        $stmt->execute([$user_id]);

        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($student) {
            $student_name =
                $student['full_name'] ?? 'Student';
        }

    } catch (PDOException $innerException) {
        $student_name = 'Student';
    }
}

// =========================================================
// CSRF TOKEN
// =========================================================
if (empty($_SESSION['help_board_csrf'])) {
    $_SESSION['help_board_csrf'] =
        bin2hex(random_bytes(32));
}

$csrf_token =
    $_SESSION['help_board_csrf'];

// =========================================================
// FLASH MESSAGE
// =========================================================
$flash_message =
    $_SESSION['help_board_message'] ?? '';

$flash_type =
    $_SESSION['help_board_message_type'] ?? 'success';

unset($_SESSION['help_board_message']);
unset($_SESSION['help_board_message_type']);

// =========================================================
// HELPER FUNCTION
// =========================================================
function h($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

// =========================================================
// ADMIN HELP BOARD POSTING SETTING
// =========================================================
try {

    $conn->exec("
        CREATE TABLE IF NOT EXISTS help_board_settings (
            id INT NOT NULL PRIMARY KEY,
            posting_enabled TINYINT(1) NOT NULL DEFAULT 1,
            updated_by INT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
    ");

    $conn->exec("
        INSERT IGNORE INTO help_board_settings
        (
            id,
            posting_enabled
        )
        VALUES
        (
            1,
            1
        )
    ");

} catch (PDOException $e) {
    // Do nothing.
}

// =========================================================
// GET CURRENT ADMIN POSTING STATUS
// =========================================================
$posting_enabled = 1;

try {

    $posting_stmt = $conn->query("
        SELECT posting_enabled
        FROM help_board_settings
        WHERE id = 1
        LIMIT 1
    ");

    $posting_setting =
        $posting_stmt->fetch(PDO::FETCH_ASSOC);

    if ($posting_setting) {
        $posting_enabled =
            (int) $posting_setting['posting_enabled'];
    }

} catch (PDOException $e) {

    // Fail-safe:
    // Do not allow new student posts.
    $posting_enabled = 0;
}

// =========================================================
// POST ACTIONS
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // =====================================================
    // RE-CHECK HELP BOARD BAN BEFORE ANY POST ACTION
    // =====================================================
    // This is important because a banned student might
    // manually send a POST request.
    try {

        $ban_check_stmt = $conn->prepare("
            SELECT is_banned
            FROM users
            WHERE user_id = ?
            LIMIT 1
        ");

        $ban_check_stmt->execute([$user_id]);

        $ban_check = $ban_check_stmt->fetch(PDO::FETCH_ASSOC);

        if (
            $ban_check &&
            (int) $ban_check['is_banned'] === 1
        ) {

            $_SESSION['help_board_banned_message'] =
                'Your access to the Help Board has been disabled by the administrator.';

            header("Location: student_home.php");
            exit();
        }

    } catch (PDOException $e) {
        // Continue normally if the optional ban column
        // is unavailable.
    }

    // =====================================================
    // CSRF CHECK
    // =====================================================
    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals(
            $csrf_token,
            $_POST['csrf_token']
        )
    ) {

        $_SESSION['help_board_message'] =
            'Invalid request. Please try again.';

        $_SESSION['help_board_message_type'] =
            'error';

        header("Location: help_board.php");
        exit();
    }

    $action =
        $_POST['action'] ?? '';

    // =====================================================
    // CREATE QUESTION / POST
    // =====================================================
    if ($action === 'create_question') {

        // Re-check admin posting setting server-side
        $current_posting_enabled = 0;

        try {

            $check_posting_stmt =
                $conn->query("
                    SELECT posting_enabled
                    FROM help_board_settings
                    WHERE id = 1
                    LIMIT 1
                ");

            $check_posting =
                $check_posting_stmt->fetch(PDO::FETCH_ASSOC);

            if ($check_posting) {

                $current_posting_enabled =
                    (int)
                    $check_posting['posting_enabled'];
            }

        } catch (PDOException $e) {

            $current_posting_enabled = 0;
        }

        if ($current_posting_enabled !== 1) {

            $_SESSION['help_board_message'] =
                'Student posting is currently disabled by the administrator.';

            $_SESSION['help_board_message_type'] =
                'error';

            header("Location: help_board.php");
            exit();
        }

        $title =
            trim($_POST['title'] ?? '');

        $details =
            trim($_POST['details'] ?? '');

        $is_anonymous =
            isset($_POST['is_anonymous'])
                ? 1
                : 0;

        if (
            $title === '' ||
            $details === ''
        ) {

            $_SESSION['help_board_message'] =
                'Please complete the question title and details.';

            $_SESSION['help_board_message_type'] =
                'error';

            header("Location: help_board.php");
            exit();
        }

        if (mb_strlen($title) > 255) {

            $_SESSION['help_board_message'] =
                'Question title is too long.';

            $_SESSION['help_board_message_type'] =
                'error';

            header("Location: help_board.php");
            exit();
        }

        try {

            $stmt = $conn->prepare("
                INSERT INTO help_questions
                (
                    student_id,
                    title,
                    details,
                    is_anonymous
                )
                VALUES (?, ?, ?, ?)
            ");

            $stmt->execute([
                $user_id,
                $title,
                $details,
                $is_anonymous
            ]);

            $_SESSION['help_board_message'] =
                'Your question was posted successfully.';

            $_SESSION['help_board_message_type'] =
                'success';

        } catch (PDOException $e) {

            $_SESSION['help_board_message'] =
                'Unable to post your question. Please try again.';

            $_SESSION['help_board_message_type'] =
                'error';
        }

        header("Location: help_board.php");
        exit();
    }

    // =====================================================
    // ADD COMMENT + CREATE NOTIFICATION
    // =====================================================
    if ($action === 'add_comment') {

        $question_id =
            (int) ($_POST['question_id'] ?? 0);

        $comment =
            trim($_POST['comment'] ?? '');

        if (
            $question_id <= 0 ||
            $comment === ''
        ) {

            $_SESSION['help_board_message'] =
                'Please enter a comment.';

            $_SESSION['help_board_message_type'] =
                'error';

            header("Location: help_board.php");
            exit();
        }

        try {

            $check = $conn->prepare("
                SELECT
                    id,
                    student_id,
                    title
                FROM help_questions
                WHERE id = ?
                LIMIT 1
            ");

            $check->execute([
                $question_id
            ]);

            $question =
                $check->fetch(PDO::FETCH_ASSOC);

            if (!$question) {

                $_SESSION['help_board_message'] =
                    'The post no longer exists.';

                $_SESSION['help_board_message_type'] =
                    'error';

                header("Location: help_board.php");
                exit();
            }

            $post_owner_id =
                (int) $question['student_id'];

            $stmt = $conn->prepare("
                INSERT INTO help_comments
                (
                    question_id,
                    user_id,
                    comment
                )
                VALUES (?, ?, ?)
            ");

            $stmt->execute([
                $question_id,
                $user_id,
                $comment
            ]);

            $comment_id =
                (int) $conn->lastInsertId();

            // =================================================
            // CREATE NOTIFICATION FOR POST OWNER
            // =================================================
            if ($post_owner_id !== $user_id) {

                $user_stmt = $conn->prepare("
                    SELECT full_name
                    FROM users
                    WHERE user_id = ?
                    LIMIT 1
                ");

                $user_stmt->execute([
                    $user_id
                ]);

                $commenter =
                    $user_stmt->fetch(PDO::FETCH_ASSOC);

                $commenter_name =
                    $commenter['full_name']
                    ?? 'A student';

                $notification_title =
                    'New comment on your post';

                $notification_message =
                    $commenter_name .
                    ' commented on "' .
                    $question['title'] .
                    '".';

                $notification_stmt =
                    $conn->prepare("
                        INSERT INTO notifications
                        (
                            user_id,
                            type,
                            title,
                            message,
                            question_id,
                            comment_id
                        )
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");

                $notification_stmt->execute([
                    $post_owner_id,
                    'comment',
                    $notification_title,
                    $notification_message,
                    $question_id,
                    $comment_id
                ]);
            }

            $_SESSION['help_board_message'] =
                'Comment added successfully.';

            $_SESSION['help_board_message_type'] =
                'success';

        } catch (PDOException $e) {

            $_SESSION['help_board_message'] =
                'Unable to add your comment. Please try again.';

            $_SESSION['help_board_message_type'] =
                'error';
        }

        header("Location: help_board.php");
        exit();
    }

    // =====================================================
    // DISMISS WARNING
    // =====================================================
    if ($action === 'dismiss_warning') {

        $warning_id =
            (int) ($_POST['warning_id'] ?? 0);

        if ($warning_id > 0) {

            try {

                $stmt = $conn->prepare("
                    UPDATE student_warnings
                    SET dismissed = 1
                    WHERE id = ?
                    AND student_id = ?
                ");

                $stmt->execute([
                    $warning_id,
                    $user_id
                ]);

            } catch (PDOException $e) {
                // Ignore.
            }
        }

        header("Location: help_board.php");
        exit();
    }

    // =====================================================
    // HIDE POST
    // =====================================================
    if ($action === 'hide_post') {

        $question_id =
            (int) ($_POST['question_id'] ?? 0);

        if ($question_id <= 0) {

            $_SESSION['help_board_message'] =
                'Invalid post.';

            $_SESSION['help_board_message_type'] =
                'error';

            header("Location: help_board.php");
            exit();
        }

        try {

            // Check if post exists
            $check_post = $conn->prepare("
                SELECT id
                FROM help_questions
                WHERE id = ?
                LIMIT 1
            ");

            $check_post->execute([
                $question_id
            ]);

            $existing_post =
                $check_post->fetch(PDO::FETCH_ASSOC);

            if (!$existing_post) {

                $_SESSION['help_board_message'] =
                    'The post no longer exists.';

                $_SESSION['help_board_message_type'] =
                    'error';

                header("Location: help_board.php");
                exit();
            }

            // Create hidden-post record
            $hide_stmt = $conn->prepare("
                SELECT id
                FROM help_hidden_posts
                WHERE question_id = ?
                AND user_id = ?
                LIMIT 1
            ");

            $hide_stmt->execute([
                $question_id,
                $user_id
            ]);

            $already_hidden =
                $hide_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$already_hidden) {

                $insert_hide =
                    $conn->prepare("
                        INSERT INTO help_hidden_posts
                        (
                            question_id,
                            user_id
                        )
                        VALUES (?, ?)
                    ");

                $insert_hide->execute([
                    $question_id,
                    $user_id
                ]);
            }

            $_SESSION['help_board_message'] =
                'Post hidden from your Help Board.';

            $_SESSION['help_board_message_type'] =
                'success';

        } catch (PDOException $e) {

            $_SESSION['help_board_message'] =
                'Unable to hide this post. Please try again.';

            $_SESSION['help_board_message_type'] =
                'error';
        }

        header("Location: help_board.php");
        exit();
    }
}

// =========================================================
// SEARCH
// =========================================================
$search =
    trim($_GET['search'] ?? '');

// =========================================================
// GET ADMIN WARNINGS
// =========================================================
$warnings = [];

try {

    $warning_stmt = $conn->prepare("
        SELECT
            id,
            title,
            message,
            created_at
        FROM student_warnings
        WHERE student_id = ?
        AND dismissed = 0
        ORDER BY created_at DESC
    ");

    $warning_stmt->execute([
        $user_id
    ]);

    $warnings =
        $warning_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $warnings = [];
}

// =========================================================
// GET HELP BOARD POSTS
// =========================================================
$questions = [];

try {

    $sql = "
        SELECT
            q.id,
            q.student_id,
            q.title,
            q.details,
            q.is_anonymous,
            q.created_at,
            u.full_name
        FROM help_questions q
        LEFT JOIN users u
            ON u.user_id = q.student_id
        LEFT JOIN help_hidden_posts hp
            ON hp.question_id = q.id
            AND hp.user_id = ?
        WHERE hp.id IS NULL
    ";

    $params = [
        $user_id
    ];

    if ($search !== '') {

        $sql .= "
            AND (
                q.title LIKE ?
                OR q.details LIKE ?
                OR u.full_name LIKE ?
            )
        ";

        $search_value =
            '%' . $search . '%';

        $params[] =
            $search_value;

        $params[] =
            $search_value;

        $params[] =
            $search_value;
    }

    $sql .= "
        ORDER BY q.created_at DESC
    ";

    $stmt =
        $conn->prepare($sql);

    $stmt->execute($params);

    $questions =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $questions = [];
}

// =========================================================
// GET COMMENTS
// =========================================================
$comments_by_question = [];

if (!empty($questions)) {

    $question_ids =
        array_column(
            $questions,
            'id'
        );

    $placeholders =
        implode(
            ',',
            array_fill(
                0,
                count($question_ids),
                '?'
            )
        );

    try {

        $comment_stmt =
            $conn->prepare("
                SELECT
                    c.id,
                    c.question_id,
                    c.user_id,
                    c.comment,
                    c.created_at,
                    u.full_name
                FROM help_comments c
                LEFT JOIN users u
                    ON u.user_id = c.user_id
                WHERE c.question_id IN ($placeholders)
                ORDER BY c.created_at ASC
            ");

        $comment_stmt->execute(
            $question_ids
        );

        $all_comments =
            $comment_stmt->fetchAll(
                PDO::FETCH_ASSOC
            );

        foreach (
            $all_comments as $comment
        ) {

            $comments_by_question[
                $comment['question_id']
            ][] = $comment;
        }

    } catch (PDOException $e) {

        $comments_by_question = [];
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Help Board | NoteSphere</title>

    <!-- =====================================================
         AUTOMATIC THEME
    ====================================================== -->
    <script>
        (function () {

            const savedTheme =
                localStorage.getItem('notesphere_theme') ||
                localStorage.getItem('theme') ||
                'dark';

            const normalizedTheme =
                savedTheme === 'light'
                    ? 'light'
                    : 'dark';

            document.documentElement.setAttribute(
                'data-theme',
                normalizedTheme
            );

        })();
    </script>

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>

        /* =========================================================
           NOTESPHERE HELP BOARD
           RESPONSIVE + DARK/LIGHT THEME
        ========================================================= */

        :root {

            --bg: #050505;

            --bg-image: url('../assets/registerbg.png');

            --topbar: rgba(8, 8, 8, 0.94);

            --card: #0d0d0d;

            --card-hover: #151515;

            --border: #252525;

            --border-hover: #383838;

            --text: #f8fafc;

            --sub-text: #bdbdbd;

            --muted: #777777;

            --input-bg: #151515;

            --input-border: #303030;

            --button-bg: #171717;

            --button-hover: #2b2b2b;

            --green: #39ff14;

            --green-text: #050505;

            --green-soft: #0e170c;

            --warning-bg:
                linear-gradient(
                    135deg,
                    #2b1600,
                    #171008
                );

            --warning-border: #9a5b00;

            --warning-text: #ffb300;

            --warning-message: #e5e7eb;

            --warning-close-bg:
                rgba(255,255,255,0.08);

            --warning-close-hover:
                rgba(255,255,255,0.18);

            --shadow:
                rgba(0,0,0,0.35);
        }

        /* =========================================================
           LIGHT THEME
        ========================================================= */

        html[data-theme="light"] {

            --bg: #f4f6f8;

            --topbar:
                rgba(255,255,255,0.94);

            --card: #ffffff;

            --card-hover: #f3f6f4;

            --border: #dfe6e1;

            --border-hover: #cbd6cf;

            --text: #17211b;

            --sub-text: #59635d;

            --muted: #7a847e;

            --input-bg: #f8fafc;

            --input-border: #dfe6e1;

            --button-bg: #edf1ee;

            --button-hover: #e1e7e3;

            --green: #24a812;

            --green-text: #ffffff;

            --green-soft: #edf9ea;

            --warning-bg:
                linear-gradient(
                    135deg,
                    #fff7df,
                    #fffaf0
                );

            --warning-border: #d89a00;

            --warning-text: #a96c00;

            --warning-message: #5b4b2a;

            --warning-close-bg:
                rgba(0,0,0,0.06);

            --warning-close-hover:
                rgba(0,0,0,0.12);

            --shadow:
                rgba(0,0,0,0.10);
        }

        /* =========================================================
           RESET
        ========================================================= */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            min-height: 100%;
            scroll-behavior: smooth;
            background: var(--bg);
        }

        /* =========================================================
           HISTORY-STYLE BACKGROUND
           EXACT SAME BACKGROUND TREATMENT AS HELP HISTORY
        ========================================================= */

        body {
            position: relative;
            min-height: 100vh;
            font-family:
                Arial,
                Helvetica,
                sans-serif;
            color: var(--text);
            overflow-x: hidden;
            background-color: var(--bg);
            background-image:
                linear-gradient(
                    rgba(5, 5, 5, 0.78),
                    rgba(5, 5, 5, 0.93)
                ),
                url('../assets/registerbg.png');
            background-position:
                center center;
            background-repeat:
                no-repeat;
            background-size:
                cover;
            background-attachment:
                fixed;
            transition:
                background-color 0.25s ease,
                color 0.25s ease;
        }

        /* =========================================================
           LIGHT MODE BODY BACKGROUND
           EXACT SAME LIGHT BACKGROUND AS HELP HISTORY
        ========================================================= */

        html[data-theme="light"] body {
            background-color: #f4f6f8;
            background-image:
                linear-gradient(
                    rgba(248, 250, 252, 0.78),
                    rgba(248, 250, 252, 0.92)
                ),
                url('../assets/registerbg.png');
            background-position:
                center center;
            background-repeat:
                no-repeat;
            background-size:
                cover;
            background-attachment:
                fixed;
        }

        /* =========================================================
           SUBTLE BACKGROUND GLOW
           EXACT SAME GLOW AS HELP HISTORY
        ========================================================= */

        body::before {
            content: "";
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: -1;
            background:
                radial-gradient(
                    circle at top center,
                    rgba(57, 255, 20, 0.07),
                    transparent 42%
                );
        }

        html[data-theme="light"] body::before {
            background:
                radial-gradient(
                    circle at top center,
                    rgba(32, 185, 0, 0.045),
                    transparent 42%
                );
        }

        /* =========================================================
           TOP BAR
        ========================================================= */

        .topbar {

            position: sticky;

            top: 0;

            z-index: 1000;

            width: 100%;

            min-height: 70px;

            background: var(--topbar);

            border-bottom:
                1px solid var(--border);

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding:
                0 22px;

            backdrop-filter: blur(16px);

            -webkit-backdrop-filter:
                blur(16px);

            transition:
                background 0.25s ease,
                border-color 0.25s ease;
        }

        .top-left {

            display: flex;

            align-items: center;

            flex:
                0 0 180px;

            min-width: 0;
        }

        .back-btn {

            width: 42px;

            height: 42px;

            flex-shrink: 0;

            border: none;

            border-radius: 50%;

            background: var(--button-bg);

            color: var(--text);

            font-size: 23px;

            line-height: 1;

            cursor: pointer;

            display: flex;

            align-items: center;

            justify-content: center;

            transition:
                background 0.2s ease,
                transform 0.2s ease;
        }

        .back-btn:hover {

            background:
                var(--button-hover);

            transform:
                scale(1.06);
        }

        .brand {

            margin-left: 12px;

            font-size: 19px;

            font-weight: 700;

            color: var(--green);

            white-space: nowrap;
        }

        /* =========================================================
           TOP NAV
        ========================================================= */

        .top-nav {

            display: flex;

            align-items: center;

            justify-content: center;

            gap: 6px;

            flex: 1;

            min-width: 0;
        }

        .nav-item {

            width: 105px;

            height: 58px;

            flex-shrink: 1;

            border-radius: 10px;

            display: flex;

            flex-direction: column;

            align-items: center;

            justify-content: center;

            text-decoration: none;

            color: var(--muted);

            transition:
                background 0.2s ease,
                color 0.2s ease;

            position: relative;

            min-width: 0;
        }

        .nav-item svg {

            width: 22px;

            height: 22px;

            margin-bottom: 3px;

            flex-shrink: 0;
        }

        .nav-item span {

            font-size: 11px;

            white-space: nowrap;

            overflow: hidden;

            text-overflow: ellipsis;

            max-width: 100%;
        }

        .nav-item:hover {

            background:
                var(--card-hover);

            color: var(--text);
        }

        .nav-item.active {

            color: var(--green);
        }

        .nav-item.active::after {

            content: "";

            position: absolute;

            bottom: 0;

            left: 20px;

            right: 20px;

            height: 3px;

            border-radius: 10px;

            background:
                var(--green);
        }

        /* =========================================================
           RIGHT SIDE
        ========================================================= */

        .top-right {

            flex:
                0 0 180px;

            min-width: 0;

            display: flex;

            justify-content: flex-end;
        }

        .profile-btn {

            display: flex;

            align-items: center;

            gap: 8px;

            max-width: 100%;

            text-decoration: none;

            color: var(--text);

            padding:
                7px 10px;

            border-radius: 10px;

            transition:
                background 0.2s ease;
        }

        .profile-btn:hover {

            background:
                var(--button-bg);
        }

        .profile-circle {

            width: 36px;

            height: 36px;

            flex-shrink: 0;

            border-radius: 50%;

            background:
                var(--green);

            color:
                var(--green-text);

            display: flex;

            align-items: center;

            justify-content: center;

            font-weight: 700;

            font-size: 15px;
        }

        .profile-name {

            max-width: 105px;

            white-space: nowrap;

            overflow: hidden;

            text-overflow: ellipsis;

            font-size: 13px;
        }

        /* =========================================================
           MAIN PAGE
        ========================================================= */

        .page {

            width: 100%;

            max-width: 800px;

            margin:
                0 auto;

            padding:
                28px 15px 70px;
        }

        .page-heading {

            margin-bottom: 20px;
        }

        .page-heading h1 {

            font-size: 27px;

            line-height: 1.2;

            margin-bottom: 6px;
        }

        .page-heading p {

            color: var(--muted);

            font-size: 14px;

            line-height: 1.5;
        }

        /* =========================================================
           WARNING
        ========================================================= */

        .warning-card {

            position: relative;

            background:
                var(--warning-bg);

            border:
                1px solid var(--warning-border);

            border-left:
                4px solid var(--warning-text);

            border-radius: 14px;

            padding:
                16px 48px 16px 18px;

            margin-bottom: 15px;

            overflow: hidden;
        }

        .warning-title {

            color:
                var(--warning-text);

            font-weight: 700;

            font-size: 15px;

            line-height: 1.4;

            margin-bottom: 7px;
        }

        .warning-message {

            color:
                var(--warning-message);

            line-height: 1.55;

            font-size: 14px;

            white-space: pre-wrap;

            word-break: break-word;
        }

        .warning-date {

            margin-top: 9px;

            color:
                var(--muted);

            font-size: 11px;
        }

        .warning-close {

            position: absolute;

            top: 12px;

            right: 12px;

            width: 30px;

            height: 30px;

            border: none;

            border-radius: 50%;

            background:
                var(--warning-close-bg);

            color:
                var(--text);

            font-size: 18px;

            line-height: 1;

            cursor: pointer;

            display: flex;

            align-items: center;

            justify-content: center;
        }

        .warning-close:hover {

            background:
                var(--warning-close-hover);
        }

        /* =========================================================
           SEARCH
        ========================================================= */

        .search-box {

            background:
                var(--card);

            border:
                1px solid var(--border);

            border-radius: 14px;

            padding: 12px;

            margin-bottom: 15px;

            box-shadow:
                0 8px 25px var(--shadow);
        }

        .search-form {

            display: flex;

            gap: 8px;

            width: 100%;
        }

        .search-input {

            flex: 1;

            min-width: 0;

            width: 100%;

            background:
                var(--input-bg);

            border:
                1px solid var(--input-border);

            border-radius: 10px;

            padding:
                12px 14px;

            color:
                var(--text);

            outline: none;

            font-size: 14px;

            transition:
                border-color 0.2s ease,
                background 0.2s ease;
        }

        .search-input::placeholder {

            color:
                var(--muted);
        }

        .search-input:focus {

            border-color:
                var(--green);
        }

        .search-btn {

            flex-shrink: 0;

            border: none;

            background:
                var(--green);

            color:
                var(--green-text);

            font-weight: 700;

            border-radius: 10px;

            padding:
                0 18px;

            min-height: 42px;

            cursor: pointer;

            transition:
                filter 0.2s ease,
                transform 0.15s ease;
        }

        .search-btn:hover {

            filter: brightness(0.92);

            transform:
                translateY(-1px);
        }

        /* =========================================================
           CREATE POST
        ========================================================= */

        .create-card {

            background:
                var(--card);

            border:
                1px solid var(--border);

            border-radius: 15px;

            padding: 18px;

            margin-bottom: 18px;

            box-shadow:
                0 8px 25px var(--shadow);
        }

        .create-top {

            display: flex;

            align-items: center;

            gap: 12px;

            margin-bottom: 15px;

            min-width: 0;
        }

        .avatar {

            width: 42px;

            height: 42px;

            flex-shrink: 0;

            border-radius: 50%;

            background:
                var(--green);

            color:
                var(--green-text);

            display: flex;

            align-items: center;

            justify-content: center;

            font-weight: 800;
        }

        .create-name {

            font-weight: 700;

            font-size: 14px;

            overflow: hidden;

            text-overflow: ellipsis;

            white-space: nowrap;
        }

        .create-sub {

            color:
                var(--muted);

            font-size: 12px;

            margin-top: 2px;

            line-height: 1.4;
        }

        .create-input {

            display: block;

            width: 100%;

            background:
                var(--input-bg);

            border:
                1px solid var(--input-border);

            border-radius: 10px;

            color:
                var(--text);

            padding:
                12px 13px;

            margin-bottom: 10px;

            outline: none;

            font-family: inherit;

            font-size: 14px;

            transition:
                border-color 0.2s ease,
                background 0.2s ease;
        }

        .create-input::placeholder {

            color:
                var(--muted);
        }

        .create-input:focus {

            border-color:
                var(--green);
        }

        textarea.create-input {

            min-height: 110px;

            resize: vertical;
        }

        .create-bottom {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 10px;

            padding-top: 4px;
        }

        .anonymous-label {

            display: flex;

            align-items: center;

            gap: 8px;

            color:
                var(--sub-text);

            font-size: 13px;

            cursor: pointer;

            min-width: 0;
        }

        .anonymous-label input {

            accent-color:
                var(--green);

            width: 16px;

            height: 16px;

            flex-shrink: 0;
        }

        .post-btn {

            flex-shrink: 0;

            border: none;

            border-radius: 9px;

            background:
                var(--green);

            color:
                var(--green-text);

            font-weight: 800;

            padding:
                11px 20px;

            cursor: pointer;

            transition:
                filter 0.2s ease,
                transform 0.15s ease;
        }

        .post-btn:hover {

            filter: brightness(0.9);

            transform:
                translateY(-1px);
        }

        .post-btn:disabled {

            opacity: 0.55;

            cursor: not-allowed;

            transform: none;
        }

        /* =========================================================
           FEED
        ========================================================= */

        .feed-title {

            color:
                var(--sub-text);

            font-size: 13px;

            font-weight: 700;

            margin:
                20px 3px 10px;

            text-transform: uppercase;

            letter-spacing: 0.5px;
        }

        /* =========================================================
           POST CARD
        ========================================================= */

        .post-card {

            background:
                var(--card);

            border:
                1px solid var(--border);

            border-radius: 15px;

            margin-bottom: 15px;

            overflow: visible;

            box-shadow:
                0 8px 25px var(--shadow);
        }

        .post-header {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 10px;

            padding:
                17px 18px 10px;
        }

        .post-user {

            display: flex;

            align-items: center;

            gap: 11px;

            min-width: 0;
        }

        .post-user > div:last-child {

            min-width: 0;
        }

        .post-user-name {

            font-size: 14px;

            font-weight: 700;

            overflow: hidden;

            text-overflow: ellipsis;

            white-space: nowrap;
        }

        .post-time {

            color:
                var(--muted);

            font-size: 11px;

            margin-top: 3px;
        }

        /* =========================================================
           POST MENU
        ========================================================= */

        .post-menu {

            position: relative;

            flex-shrink: 0;
        }

        .menu-btn {

            width: 34px;

            height: 34px;

            border: none;

            border-radius: 50%;

            background: transparent;

            color:
                var(--muted);

            cursor: pointer;

            font-size: 20px;

            display: flex;

            align-items: center;

            justify-content: center;
        }

        .menu-btn:hover {

            background:
                var(--button-bg);

            color:
                var(--text);
        }

        .menu-form {

            position: absolute;

            top: 38px;

            right: 0;

            display: none;

            background:
                var(--card);

            border:
                1px solid var(--border-hover);

            border-radius: 10px;

            overflow: hidden;

            min-width: 130px;

            z-index: 100;

            box-shadow:
                0 8px 25px var(--shadow);
        }

        .menu-form.show {

            display: block;
        }

        .hide-btn {

            width: 100%;

            border: none;

            background: transparent;

            color:
                var(--text);

            text-align: left;

            padding:
                11px 13px;

            cursor: pointer;
        }

        .hide-btn:hover {

            background:
                var(--button-hover);
        }

        /* =========================================================
           POST CONTENT
        ========================================================= */

        .post-content {

            padding:
                3px 18px 17px;
        }

        .post-title {

            font-size: 19px;

            line-height: 1.35;

            font-weight: 700;

            margin-bottom: 8px;

            color:
                var(--text);

            overflow-wrap: anywhere;
        }

        .post-details {

            color:
                var(--sub-text);

            font-size: 14px;

            line-height: 1.6;

            white-space: pre-wrap;

            word-break: break-word;

            overflow-wrap: anywhere;
        }

        .post-footer {

            border-top:
                1px solid var(--border);

            padding:
                10px 18px;

            color:
                var(--muted);

            font-size: 12px;
        }

        /* =========================================================
           COMMENTS
        ========================================================= */

        .comments {

            border-top:
                1px solid var(--border);

            padding:
                12px 18px 16px;
        }

        .comment {

            display: flex;

            gap: 9px;

            margin-bottom: 10px;

            min-width: 0;
        }

        .comment-avatar {

            width: 30px;

            height: 30px;

            flex-shrink: 0;

            border-radius: 50%;

            background:
                var(--button-hover);

            color:
                var(--text);

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 11px;

            font-weight: 700;
        }

        .comment-body {

            background:
                var(--card-hover);

            border-radius: 12px;

            padding:
                8px 11px;

            flex: 1;

            min-width: 0;
        }

        .comment-name {

            font-weight: 700;

            font-size: 12px;

            margin-bottom: 3px;
        }

        .comment-text {

            color:
                var(--sub-text);

            font-size: 13px;

            line-height: 1.45;

            white-space: pre-wrap;

            word-break: break-word;

            overflow-wrap: anywhere;
        }

        .comment-time {

            color:
                var(--muted);

            font-size: 10px;

            margin-top: 4px;
        }

        .comment-form {

            display: flex;

            gap: 8px;

            margin-top: 8px;

            width: 100%;
        }

        .comment-input {

            flex: 1;

            min-width: 0;

            width: 100%;

            border:
                1px solid var(--input-border);

            background:
                var(--input-bg);

            color:
                var(--text);

            border-radius: 18px;

            padding:
                9px 13px;

            outline: none;

            font-family: inherit;

            font-size: 13px;
        }

        .comment-input::placeholder {

            color:
                var(--muted);
        }

        .comment-input:focus {

            border-color:
                var(--green);
        }

        .comment-btn {

            width: 38px;

            height: 38px;

            flex-shrink: 0;

            border: none;

            border-radius: 50%;

            background:
                var(--green);

            color:
                var(--green-text);

            cursor: pointer;

            font-weight: 900;

            font-size: 15px;

            display: flex;

            align-items: center;

            justify-content: center;
        }

        /* =========================================================
           EMPTY STATE
        ========================================================= */

        .empty-card {

            background:
                var(--card);

            border:
                1px solid var(--border);

            border-radius: 15px;

            text-align: center;

            padding:
                45px 20px;

            color:
                var(--muted);

            box-shadow:
                0 8px 25px var(--shadow);
        }

        .empty-icon {

            font-size: 42px;

            margin-bottom: 12px;
        }

        .empty-card h3 {

            color:
                var(--text);

            margin-bottom: 6px;
        }

        /* =========================================================
           TABLET
        ========================================================= */

        @media (max-width: 900px) {

            .top-left {
                flex-basis: 130px;
            }

            .top-right {
                flex-basis: 130px;
            }

            .nav-item {
                width: 82px;
            }

            .profile-name {
                max-width: 75px;
            }
        }

        /* =========================================================
           MOBILE
        ========================================================= */

        @media (max-width: 750px) {

            .topbar {

                min-height: 64px;

                padding:
                    0 10px;

                gap: 5px;
            }

            .top-left {

                flex:
                    0 0 auto;
            }

            .brand {

                display: none;
            }

            .back-btn {

                width: 38px;

                height: 38px;

                font-size: 21px;
            }

            .top-nav {

                flex: 1;

                gap: 2px;

                justify-content: center;
            }

            .nav-item {

                width: 64px;

                height: 54px;

                border-radius: 9px;
            }

            .nav-item svg {

                width: 20px;

                height: 20px;
            }

            .nav-item span {

                font-size: 9px;
            }

            .nav-item.active::after {

                left: 15px;

                right: 15px;

                height: 2px;
            }

            .top-right {

                flex:
                    0 0 auto;

                width: auto;
            }

            .profile-name {

                display: none;
            }

            .profile-btn {

                padding:
                    4px;
            }

            .profile-circle {

                width: 36px;

                height: 36px;
            }

            .page {

                width: 100%;

                padding:
                    20px 10px 50px;
            }

            .page-heading {

                margin-bottom: 16px;
            }

            .page-heading h1 {

                font-size: 24px;
            }

            .page-heading p {

                font-size: 13px;
            }

            .warning-card {

                padding:
                    15px 45px 15px 15px;

                border-radius: 12px;
            }

            .search-box {

                padding: 10px;

                border-radius: 12px;
            }

            .create-card {

                padding: 15px;

                border-radius: 13px;
            }

            .post-card {

                border-radius: 13px;
            }

            .post-header {

                padding:
                    14px 14px 9px;
            }

            .post-content {

                padding:
                    3px 14px 15px;
            }

            .comments {

                padding:
                    11px 14px 14px;
            }

            .post-title {

                font-size: 17px;
            }

            .post-details {

                font-size: 13px;

                line-height: 1.55;
            }
        }

        /* =========================================================
           SMALL PHONES
        ========================================================= */

        @media (max-width: 480px) {

            .topbar {

                min-height: 60px;

                padding:
                    0 7px;
            }

            .back-btn {

                width: 34px;

                height: 34px;

                font-size: 19px;
            }

            .top-nav {

                gap: 0;
            }

            .nav-item {

                width: 58px;

                height: 50px;
            }

            .nav-item svg {

                width: 18px;

                height: 18px;

                margin-bottom: 2px;
            }

            .nav-item span {

                font-size: 8px;
            }

            .profile-circle {

                width: 34px;

                height: 34px;

                font-size: 14px;
            }

            .page {

                padding:
                    17px 8px 45px;
            }

            .page-heading h1 {

                font-size: 22px;
            }

            .page-heading p {

                font-size: 12px;
            }

            /* Search */

            .search-form {

                flex-direction: column;

                gap: 7px;
            }

            .search-input {

                min-height: 42px;
            }

            .search-btn {

                width: 100%;

                height: 42px;

                padding: 0;
            }

            /* Create post */

            .create-card {

                padding: 13px;
            }

            .create-top {

                gap: 9px;
            }

            .avatar {

                width: 38px;

                height: 38px;
            }

            .create-name {

                font-size: 13px;
            }

            .create-sub {

                font-size: 11px;
            }

            textarea.create-input {

                min-height: 100px;
            }

            .create-bottom {

                align-items:
                    stretch;

                flex-direction:
                    column;

                gap: 12px;
            }

            .anonymous-label {

                padding:
                    2px 0;
            }

            .post-btn {

                width: 100%;

                min-height: 42px;
            }

            /* Post */

            .post-header {

                padding:
                    12px 12px 8px;
            }

            .post-content {

                padding:
                    3px 12px 14px;
            }

            .post-title {

                font-size: 16px;

                line-height: 1.4;
            }

            .post-details {

                font-size: 13px;
            }

            .post-footer {

                padding:
                    9px 12px;
            }

            .comments {

                padding:
                    10px 12px 13px;
            }

            .comment {

                gap: 7px;
            }

            .comment-avatar {

                width: 28px;

                height: 28px;
            }

            .comment-body {

                padding:
                    7px 9px;
            }

            .comment-text {

                font-size: 12px;
            }

            .comment-form {

                gap: 6px;
            }

            .comment-input {

                font-size: 12px;

                padding:
                    9px 11px;
            }

            .comment-btn {

                width: 36px;

                height: 36px;
            }

            .empty-card {

                padding:
                    38px 15px;
            }
        }

        /* =========================================================
           VERY SMALL PHONES
        ========================================================= */

        @media (max-width: 350px) {

            .topbar {

                padding:
                    0 5px;
            }

            .back-btn {

                width: 32px;

                height: 32px;
            }

            .nav-item {

                width: 52px;
            }

            .nav-item span {

                font-size: 7px;
            }

            .profile-circle {

                width: 32px;

                height: 32px;
            }

            .page {

                padding-left: 6px;

                padding-right: 6px;
            }
        }

        /* =========================================================
           TOUCH DEVICES
        ========================================================= */

        @media (hover: none) {

            .nav-item:hover,
            .back-btn:hover,
            .menu-btn:hover,
            .post-btn:hover,
            .search-btn:hover {

                transform: none;

                filter: none;
            }
        }

        /* =========================================================
           ACCESSIBILITY
        ========================================================= */

        button,
        input,
        textarea {

            -webkit-tap-highlight-color:
                transparent;
        }

        button:focus-visible,
        a:focus-visible,
        input:focus-visible,
        textarea:focus-visible {

            outline:
                2px solid var(--green);

            outline-offset:
                2px;
        }

    </style>

</head>

<body>

    <!-- =====================================================
         TOP NAVIGATION
    ===================================================== -->

    <header class="topbar">

        <div class="top-left">

            <button
                type="button"
                class="back-btn"
                onclick="window.location.href='student_home.php';"
                title="Back"
            >
                ×
            </button>

            <div class="brand">
                NoteSphere
            </div>

        </div>

        <nav class="top-nav">

            <!-- HOME -->

            <a
                href="help_board.php"
                class="nav-item active"
                title="Home"
            >

                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                >

                    <path
                        d="M3 10.5L12 3l9 7.5"
                    />

                    <path
                        d="M5 9.5V21h14V9.5"
                    />

                    <path
                        d="M9 21v-7h6v7"
                    />

                </svg>

                <span>
                    Home
                </span>

            </a>

            <!-- HISTORY -->

            <a
                href="student_history.php"
                class="nav-item"
                title="History"
            >

                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                >

                    <circle
                        cx="12"
                        cy="12"
                        r="9"
                    />

                    <path
                        d="M12 7v5l3 2"
                    />

                </svg>

                <span>
                    History
                </span>

            </a>

            <!-- NOTIFICATIONS -->

            <a
                href="student_notifications.php"
                class="nav-item"
                title="Notifications"
            >

                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                >

                    <path
                        d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"
                    />

                    <path
                        d="M10 21h4"
                    />

                </svg>

                <span>
                    Notifications
                </span>

            </a>

        </nav>

        <div class="top-right">

            <a
                href="student_profile.php"
                class="profile-btn"
            >

                <div class="profile-circle">

                    <?php

                    echo h(
                        strtoupper(
                            mb_substr(
                                $student_name,
                                0,
                                1
                            )
                        )
                    );

                    ?>

                </div>

                <span class="profile-name">

                    <?php
                    echo h($student_name);
                    ?>

                </span>

            </a>

        </div>

    </header>

    <!-- =====================================================
         MAIN CONTENT
    ===================================================== -->

    <main class="page">

        <!-- PAGE TITLE -->

        <div class="page-heading">

            <h1>
                Help Board
            </h1>

            <p>
                Ask questions, share ideas, and help your fellow students.
            </p>

        </div>

        <!-- =================================================
             ADMIN WARNINGS
        ================================================= -->

        <?php if (!empty($warnings)): ?>

            <?php foreach ($warnings as $warning): ?>

                <div
                    class="warning-card"
                    id="warning-<?php echo (int) $warning['id']; ?>"
                >

                    <form
                        method="POST"
                        class="warning-dismiss-form"
                    >

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?php echo h($csrf_token); ?>"
                        >

                        <input
                            type="hidden"
                            name="action"
                            value="dismiss_warning"
                        >

                        <input
                            type="hidden"
                            name="warning_id"
                            value="<?php echo (int) $warning['id']; ?>"
                        >

                        <button
                            type="submit"
                            class="warning-close"
                            title="Close warning"
                        >
                            ×
                        </button>

                    </form>

                    <div class="warning-title">

                        ⚠️

                        <?php

                        echo h(
                            $warning['title']
                            ?: 'Admin Warning'
                        );

                        ?>

                    </div>

                    <div class="warning-message">

                        <?php

                        echo h(
                            $warning['message']
                        );

                        ?>

                    </div>

                    <div class="warning-date">

                        Sent on

                        <?php

                        echo date(
                            'M d, Y · h:i A',
                            strtotime(
                                $warning['created_at']
                            )
                        );

                        ?>

                    </div>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>

        <!-- =================================================
             SEARCH
        ================================================= -->

        <div class="search-box">

            <form
                method="GET"
                class="search-form"
            >

                <input
                    type="text"
                    name="search"
                    class="search-input"
                    placeholder="Search questions or students..."
                    value="<?php echo h($search); ?>"
                >

                <button
                    type="submit"
                    class="search-btn"
                >
                    Search
                </button>

            </form>

        </div>

        <!-- =================================================
             CREATE POST
        ================================================= -->

        <div class="create-card">

            <div class="create-top">

                <div class="avatar">

                    <?php

                    echo h(
                        strtoupper(
                            mb_substr(
                                $student_name,
                                0,
                                1
                            )
                        )
                    );

                    ?>

                </div>

                <div>

                    <div class="create-name">

                        <?php
                        echo h($student_name);
                        ?>

                    </div>

                    <div class="create-sub">

                        <?php if ($posting_enabled === 1): ?>

                            Create a question or share something helpful.

                        <?php else: ?>

                            Student posting is currently disabled by the administrator.

                        <?php endif; ?>

                    </div>

                </div>

            </div>

            <form
                method="POST"
                id="createPostForm"
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php echo h($csrf_token); ?>"
                >

                <input
                    type="hidden"
                    name="action"
                    value="create_question"
                >

                <input
                    type="text"
                    name="title"
                    class="create-input"
                    placeholder="What do you want to ask?"
                    maxlength="255"
                    <?php
                    echo $posting_enabled === 1
                        ? 'required'
                        : 'disabled';
                    ?>
                >

                <textarea
                    name="details"
                    class="create-input"
                    placeholder="Write the details of your question..."
                    <?php
                    echo $posting_enabled === 1
                        ? 'required'
                        : 'disabled';
                    ?>
                ></textarea>

                <div class="create-bottom">

                    <label class="anonymous-label">

                        <input
                            type="checkbox"
                            name="is_anonymous"
                            value="1"
                            <?php
                            echo $posting_enabled === 1
                                ? ''
                                : 'disabled';
                            ?>
                        >

                        Post anonymously

                    </label>

                    <button
                        type="submit"
                        class="post-btn"
                        <?php
                        echo $posting_enabled === 1
                            ? ''
                            : 'disabled';
                        ?>
                    >

                        <?php if ($posting_enabled === 1): ?>

                            Post Question

                        <?php else: ?>

                            Posting Disabled

                        <?php endif; ?>

                    </button>

                </div>

            </form>

        </div>

        <!-- =================================================
             FEED TITLE
        ================================================= -->

        <div class="feed-title">

            <?php

            if ($search !== '') {

                echo 'Search Results';

            } else {

                echo 'Latest Questions';

            }

            ?>

        </div>

        <!-- =================================================
             QUESTIONS
        ================================================= -->

        <?php if (!empty($questions)): ?>

            <?php foreach ($questions as $question): ?>

                <?php

                $question_id =
                    (int) $question['id'];

                $display_name =
                    $question['is_anonymous']
                        ? 'Anonymous Student'
                        : (
                            $question['full_name']
                            ?: 'Student'
                        );

                $initial =
                    strtoupper(
                        mb_substr(
                            $display_name,
                            0,
                            1
                        )
                    );

                ?>

                <article class="post-card">

                    <!-- POST HEADER -->

                    <div class="post-header">

                        <div class="post-user">

                            <div class="avatar">

                                <?php
                                echo h($initial);
                                ?>

                            </div>

                            <div>

                                <div class="post-user-name">

                                    <?php

                                    echo h(
                                        $display_name
                                    );

                                    ?>

                                </div>

                                <div class="post-time">

                                    <?php

                                    echo date(
                                        'M d, Y · h:i A',
                                        strtotime(
                                            $question['created_at']
                                        )
                                    );

                                    ?>

                                </div>

                            </div>

                        </div>

                        <!-- POST MENU -->

                        <div class="post-menu">

                            <button
                                type="button"
                                class="menu-btn"
                                onclick="toggleMenu(<?php echo $question_id; ?>)"
                            >
                                ⋯
                            </button>

                            <div
                                class="menu-form"
                                id="menu-<?php echo $question_id; ?>"
                            >

                                <form
                                    method="POST"
                                    onsubmit="return confirmHidePost();"
                                >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?php echo h($csrf_token); ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="hide_post"
                                    >

                                    <input
                                        type="hidden"
                                        name="question_id"
                                        value="<?php echo $question_id; ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="hide-btn"
                                    >
                                        Hide post
                                    </button>

                                </form>

                            </div>

                        </div>

                    </div>

                    <!-- POST CONTENT -->

                    <div class="post-content">

                        <div class="post-title">

                            <?php

                            echo h(
                                $question['title']
                            );

                            ?>

                        </div>

                        <div class="post-details">

                            <?php

                            echo h(
                                $question['details']
                            );

                            ?>

                        </div>

                    </div>

                    <!-- POST FOOTER -->

                    <div class="post-footer">

                        💬

                        <?php

                        echo count(
                            $comments_by_question[
                                $question_id
                            ] ?? []
                        );

                        ?>

                        comments

                    </div>

                    <!-- COMMENTS -->

                    <div class="comments">

                        <?php

                        $post_comments =
                            $comments_by_question[
                                $question_id
                            ] ?? [];

                        ?>

                        <?php if (!empty($post_comments)): ?>

                            <?php foreach ($post_comments as $comment): ?>

                                <?php

                                $comment_name =
                                    $comment['full_name']
                                    ?: 'Student';

                                $comment_initial =
                                    strtoupper(
                                        mb_substr(
                                            $comment_name,
                                            0,
                                            1
                                        )
                                    );

                                ?>

                                <div class="comment">

                                    <div class="comment-avatar">

                                        <?php

                                        echo h(
                                            $comment_initial
                                        );

                                        ?>

                                    </div>

                                    <div class="comment-body">

                                        <div class="comment-name">

                                            <?php

                                            echo h(
                                                $comment_name
                                            );

                                            ?>

                                        </div>

                                        <div class="comment-text">

                                            <?php

                                            echo h(
                                                $comment['comment']
                                            );

                                            ?>

                                        </div>

                                        <div class="comment-time">

                                            <?php

                                            echo date(
                                                'M d, Y · h:i A',
                                                strtotime(
                                                    $comment['created_at']
                                                )
                                            );

                                            ?>

                                        </div>

                                    </div>

                                </div>

                            <?php endforeach; ?>

                        <?php endif; ?>

                        <!-- COMMENT FORM -->

                        <form
                            method="POST"
                            class="comment-form"
                        >

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?php echo h($csrf_token); ?>"
                            >

                            <input
                                type="hidden"
                                name="action"
                                value="add_comment"
                            >

                            <input
                                type="hidden"
                                name="question_id"
                                value="<?php echo $question_id; ?>"
                            >

                            <input
                                type="text"
                                name="comment"
                                class="comment-input"
                                placeholder="Write a comment..."
                                maxlength="2000"
                                required
                            >

                            <button
                                type="submit"
                                class="comment-btn"
                                title="Send comment"
                            >
                                ➤
                            </button>

                        </form>

                    </div>

                </article>

            <?php endforeach; ?>

        <?php else: ?>

            <div class="empty-card">

                <div class="empty-icon">
                    💬
                </div>

                <h3>
                    No questions found
                </h3>

                <p>
                    Be the first student to ask a question.
                </p>

            </div>

        <?php endif; ?>

    </main>

    <script>

        // =====================================================
        // SYSTEM THEME SYNC
        // =====================================================

        function applySystemTheme(theme) {

            const normalizedTheme =
                theme === 'light'
                    ? 'light'
                    : 'dark';

            document.documentElement.setAttribute(
                'data-theme',
                normalizedTheme
            );
        }

        window.addEventListener(
            'storage',
            function (event) {

                if (
                    event.key === 'notesphere_theme' ||
                    event.key === 'theme'
                ) {

                    if (event.newValue) {

                        applySystemTheme(
                            event.newValue
                        );
                    }
                }
            }
        );

        window.addEventListener(
            'notesphere-theme-change',
            function (event) {

                if (
                    event.detail &&
                    event.detail.theme
                ) {

                    applySystemTheme(
                        event.detail.theme
                    );
                }
            }
        );

        // =====================================================
        // BACK BUTTON
        // =====================================================

        function goBack() {

            if (document.referrer) {

                history.back();

            } else {

                window.location.href =
                    'student_home.php';
            }
        }

        // =====================================================
        // POST MENU
        // =====================================================

        function toggleMenu(id) {

            const menu =
                document.getElementById(
                    'menu-' + id
                );

            document
                .querySelectorAll('.menu-form')
                .forEach(function(item) {

                    if (item !== menu) {

                        item.classList.remove(
                            'show'
                        );
                    }
                });

            if (menu) {

                menu.classList.toggle('show');
            }
        }

        // =====================================================
        // CLOSE MENU WHEN CLICKING OUTSIDE
        // =====================================================

        document.addEventListener(
            'click',
            function(event) {

                if (
                    !event.target.closest(
                        '.post-menu'
                    )
                ) {

                    document
                        .querySelectorAll(
                            '.menu-form'
                        )
                        .forEach(
                            function(menu) {

                                menu.classList.remove(
                                    'show'
                                );
                            }
                        );
                }
            }
        );

        // =====================================================
        // HIDE POST CONFIRMATION
        // =====================================================

        function confirmHidePost() {

            return confirm(
                'Hide this post from your Help Board?'
            );
        }

        // =====================================================
        // PREVENT SUBMISSION IF POSTING IS DISABLED
        // =====================================================

        const createPostForm =
            document.getElementById(
                'createPostForm'
            );

        const postingEnabled =
            <?php
            echo $posting_enabled === 1
                ? 'true'
                : 'false';
            ?>;

        if (
            createPostForm &&
            !postingEnabled
        ) {

            createPostForm.addEventListener(
                'submit',
                function(event) {

                    event.preventDefault();

                    const currentTheme =
                        document.documentElement
                            .getAttribute(
                                'data-theme'
                            ) || 'dark';

                    const isLightTheme =
                        currentTheme === 'light';

                    Swal.fire({

                        icon: 'warning',

                        title: 'Posting Disabled',

                        text:
                            'Student posting is currently disabled by the administrator.',

                        background:
                            isLightTheme
                                ? '#ffffff'
                                : '#111111',

                        color:
                            isLightTheme
                                ? '#17211b'
                                : '#ffffff',

                        confirmButtonColor:
                            isLightTheme
                                ? '#24a812'
                                : '#39ff14',

                        confirmButtonText:
                            'OK'
                    });
                }
            );
        }

        // =====================================================
        // SHOW HELP BOARD BAN MESSAGE
        // =====================================================

        <?php if (
            !empty(
                $_SESSION[
                    'help_board_banned_message'
                ]
            )
        ): ?>

            const bannedMessage =
                <?php
                echo json_encode(
                    $_SESSION[
                        'help_board_banned_message'
                    ]
                );
                ?>;

            <?php
            unset(
                $_SESSION[
                    'help_board_banned_message'
                ]
            );
            ?>

            const bannedTheme =
                document.documentElement
                    .getAttribute(
                        'data-theme'
                    ) || 'dark';

            const bannedIsLight =
                bannedTheme === 'light';

            Swal.fire({

                icon: 'warning',

                title: 'Help Board Access Disabled',

                text: bannedMessage,

                background:
                    bannedIsLight
                        ? '#ffffff'
                        : '#111111',

                color:
                    bannedIsLight
                        ? '#17211b'
                        : '#ffffff',

                confirmButtonColor:
                    bannedIsLight
                        ? '#24a812'
                        : '#39ff14',

                confirmButtonText:
                    'OK'
            });

        <?php endif; ?>

        // =====================================================
        // SWEET ALERT MESSAGE
        // =====================================================

        <?php if ($flash_message !== ''): ?>

            const currentTheme =
                document.documentElement
                    .getAttribute(
                        'data-theme'
                    ) || 'dark';

            const isLightTheme =
                currentTheme === 'light';

            const flashIcon =
                <?php

                echo json_encode(
                    $flash_type === 'error'
                        ? 'error'
                        : 'success'
                );

                ?>;

            const flashTitle =
                <?php

                echo json_encode(
                    $flash_type === 'error'
                        ? 'Something went wrong'
                        : 'Success'
                );

                ?>;

            const flashText =
                <?php

                echo json_encode(
                    $flash_message
                );

                ?>;

            Swal.fire({

                icon:
                    flashIcon,

                title:
                    flashTitle,

                text:
                    flashText,

                background:
                    isLightTheme
                        ? '#ffffff'
                        : '#111111',

                color:
                    isLightTheme
                        ? '#17211b'
                        : '#ffffff',

                confirmButtonColor:
                    isLightTheme
                        ? '#24a812'
                        : '#39ff14',

                confirmButtonText:
                    'OK'
            });

        <?php endif; ?>

    </script>

</body>

</html>
