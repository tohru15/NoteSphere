
<?php
// =========================================================
// NoteSphere - Admin Help Board Management
// =========================================================

session_start();

require_once __DIR__ . '/../includes/db_config.php';

/** @var PDO $conn */

// =========================================================
// ADMIN SECURITY CHECK
// =========================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$admin_id = (int) $_SESSION['user_id'];

// =========================================================
// SETTINGS
// =========================================================
const WARNING_BAN_THRESHOLD = 3;

// =========================================================
// HELPERS
// =========================================================
function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirectBack()
{
    $url = $_SERVER['PHP_SELF'];

    if (!empty($_GET['search'])) {
        $url .= '?search=' . urlencode($_GET['search']);
    }

    header("Location: " . $url);
    exit();
}

function setFlash($message, $type = 'success')
{
    $_SESSION['helpboard_flash'] = [
        'message' => $message,
        'type' => $type
    ];
}

// =========================================================
// CSRF TOKEN
// =========================================================
if (empty($_SESSION['helpboard_csrf'])) {
    $_SESSION['helpboard_csrf'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['helpboard_csrf'];

// =========================================================
// MAKE SURE is_banned COLUMN EXISTS
// =========================================================
try {
    $checkColumn = $conn->query("SHOW COLUMNS FROM users LIKE 'is_banned'");
    $columnExists = $checkColumn->fetch(PDO::FETCH_ASSOC);

    if (!$columnExists) {
        $conn->exec("
            ALTER TABLE users
            ADD COLUMN is_banned TINYINT(1) NOT NULL DEFAULT 0
        ");
    }
} catch (PDOException $e) {
    setFlash(
        'The account status field could not be prepared. Please check your users table.',
        'error'
    );
}

// =========================================================
// CREATE HELP BOARD SETTINGS IF NOT EXISTS
// =========================================================
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS help_board_settings (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            posting_enabled TINYINT(1) NOT NULL DEFAULT 1,
            updated_by INT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP
        )
    ");

    $settingsCount = $conn->query("
        SELECT COUNT(*) FROM help_board_settings
    ")->fetchColumn();

    if ((int)$settingsCount === 0) {
        $insertSettings = $conn->prepare("
            INSERT INTO help_board_settings
                (posting_enabled, updated_by)
            VALUES
                (1, ?)
        ");

        $insertSettings->execute([$admin_id]);
    }
} catch (PDOException $e) {
    // Do not stop the page if the table already exists
}

// =========================================================
// CREATE STUDENT WARNINGS TABLE IF NOT EXISTS
// =========================================================
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS student_warnings (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            dismissed TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX(student_id)
        )
    ");
} catch (PDOException $e) {
    // Table may already exist
}

// =========================================================
// CREATE HIDDEN POSTS TABLE IF NOT EXISTS
// =========================================================
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS help_hidden_posts (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            question_id INT NOT NULL,
            user_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_hidden_post_user (question_id, user_id),
            INDEX(question_id),
            INDEX(user_id)
        )
    ");
} catch (PDOException $e) {
    // Table may already exist
}

// =========================================================
// GET POSTING STATUS
// =========================================================
$posting_enabled = true;

try {
    $settingsStmt = $conn->query("
        SELECT posting_enabled
        FROM help_board_settings
        ORDER BY id ASC
        LIMIT 1
    ");

    $settingsRow = $settingsStmt->fetch(PDO::FETCH_ASSOC);

    if ($settingsRow) {
        $posting_enabled = (bool)$settingsRow['posting_enabled'];
    }
} catch (PDOException $e) {
    $posting_enabled = true;
}

// =========================================================
// POST ACTIONS
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // =====================================================
    // CSRF CHECK
    // =====================================================
    $submitted_token = $_POST['csrf_token'] ?? '';

    if (
        empty($submitted_token) ||
        !hash_equals($csrf_token, $submitted_token)
    ) {
        setFlash('Invalid security token. Please try again.', 'error');
        redirectBack();
    }

    $action = $_POST['action'] ?? '';

    // =====================================================
    // TOGGLE STUDENT POSTING
    // =====================================================
    if ($action === 'toggle_posting') {

        $newStatus = isset($_POST['posting_enabled'])
            ? (int)$_POST['posting_enabled']
            : 0;

        $newStatus = $newStatus === 1 ? 1 : 0;

        try {

            $update = $conn->prepare("
                UPDATE help_board_settings
                SET posting_enabled = ?, updated_by = ?
                WHERE id = (
                    SELECT id FROM (
                        SELECT id
                        FROM help_board_settings
                        ORDER BY id ASC
                        LIMIT 1
                    ) AS temp
                )
            ");

            $update->execute([
                $newStatus,
                $admin_id
            ]);

            setFlash(
                $newStatus === 1
                    ? 'Student posting has been enabled.'
                    : 'Student posting has been disabled.',
                'success'
            );

        } catch (PDOException $e) {
            setFlash(
                'Unable to update student posting status.',
                'error'
            );
        }

        redirectBack();
    }

    // =====================================================
    // SEND WARNING
    // =====================================================
    if ($action === 'send_warning') {

        $student_id = (int)($_POST['student_id'] ?? 0);
        $warning_title = trim($_POST['warning_title'] ?? '');
        $warning_message = trim($_POST['warning_message'] ?? '');

        if ($student_id <= 0) {
            setFlash('Invalid student.', 'error');
            redirectBack();
        }

        if ($warning_title === '' || $warning_message === '') {
            setFlash(
                'Please provide both warning title and message.',
                'error'
            );
            redirectBack();
        }

        try {

            $studentCheck = $conn->prepare("
                SELECT user_id, full_name
                FROM users
                WHERE user_id = ?
                LIMIT 1
            ");

            $studentCheck->execute([$student_id]);

            $student = $studentCheck->fetch(PDO::FETCH_ASSOC);

            if (!$student) {
                setFlash('Student account was not found.', 'error');
                redirectBack();
            }

            $warningStmt = $conn->prepare("
                INSERT INTO student_warnings
                    (student_id, title, message)
                VALUES
                    (?, ?, ?)
            ");

            $warningStmt->execute([
                $student_id,
                $warning_title,
                $warning_message
            ]);

            // Try sending notification.
            // If notification table has different columns,
            // the warning itself will still be saved.
            try {

                $notificationStmt = $conn->prepare("
                    INSERT INTO notifications
                        (user_id, type, title, message)
                    VALUES
                        (?, ?, ?, ?)
                ");

                $notificationStmt->execute([
                    $student_id,
                    'warning',
                    $warning_title,
                    $warning_message
                ]);

            } catch (PDOException $notificationError) {
                // Warning was already successfully saved.
            }

            setFlash(
                'Warning sent to ' . $student['full_name'] . '.',
                'success'
            );

        } catch (PDOException $e) {

            setFlash(
                'Unable to send the warning.',
                'error'
            );
        }

        redirectBack();
    }

    // =====================================================
    // HIDE POST
    // =====================================================
    if ($action === 'hide_post') {

        $question_id = (int)($_POST['question_id'] ?? 0);

        if ($question_id <= 0) {
            setFlash('Invalid post.', 'error');
            redirectBack();
        }

        try {

            $check = $conn->prepare("
                SELECT id
                FROM help_questions
                WHERE id = ?
                LIMIT 1
            ");

            $check->execute([$question_id]);

            if (!$check->fetch()) {
                setFlash('Post not found.', 'error');
                redirectBack();
            }

            $hide = $conn->prepare("
                INSERT IGNORE INTO help_hidden_posts
                    (question_id, user_id)
                VALUES
                    (?, ?)
            ");

            $hide->execute([
                $question_id,
                $admin_id
            ]);

            setFlash(
                'Post hidden from the admin view.',
                'success'
            );

        } catch (PDOException $e) {

            setFlash(
                'Unable to hide the post.',
                'error'
            );
        }

        redirectBack();
    }

    // =====================================================
    // UNHIDE POST
    // =====================================================
    if ($action === 'unhide_post') {

        $question_id = (int)($_POST['question_id'] ?? 0);

        if ($question_id <= 0) {
            setFlash('Invalid post.', 'error');
            redirectBack();
        }

        try {

            $unhide = $conn->prepare("
                DELETE FROM help_hidden_posts
                WHERE question_id = ?
                  AND user_id = ?
            ");

            $unhide->execute([
                $question_id,
                $admin_id
            ]);

            setFlash(
                'Post restored to the admin view.',
                'success'
            );

        } catch (PDOException $e) {

            setFlash(
                'Unable to restore the post.',
                'error'
            );
        }

        redirectBack();
    }

    // =====================================================
    // DELETE ONE COMMENT
    // =====================================================
    if ($action === 'delete_comment') {

        $comment_id = (int)($_POST['comment_id'] ?? 0);

        if ($comment_id <= 0) {
            setFlash('Invalid comment.', 'error');
            redirectBack();
        }

        try {

            $delete = $conn->prepare("
                DELETE FROM help_comments
                WHERE id = ?
            ");

            $delete->execute([$comment_id]);

            setFlash(
                'Comment permanently deleted.',
                'success'
            );

        } catch (PDOException $e) {

            setFlash(
                'Unable to delete the comment.',
                'error'
            );
        }

        redirectBack();
    }

    // =====================================================
    // DELETE ONE POST PERMANENTLY
    // =====================================================
    if ($action === 'delete_post') {

        $question_id = (int)($_POST['question_id'] ?? 0);

        if ($question_id <= 0) {
            setFlash('Invalid post.', 'error');
            redirectBack();
        }

        try {

            $conn->beginTransaction();

            // ---------------------------------------------
            // Check if post exists
            // ---------------------------------------------
            $checkPost = $conn->prepare("
                SELECT id
                FROM help_questions
                WHERE id = ?
                LIMIT 1
            ");

            $checkPost->execute([$question_id]);

            if (!$checkPost->fetch()) {
                $conn->rollBack();

                setFlash(
                    'The post no longer exists.',
                    'error'
                );

                redirectBack();
            }

            // ---------------------------------------------
            // Get comment IDs first
            // This helps remove related notifications
            // when those columns exist.
            // ---------------------------------------------
            $commentIds = [];

            $commentIdStmt = $conn->prepare("
                SELECT id
                FROM help_comments
                WHERE question_id = ?
            ");

            $commentIdStmt->execute([$question_id]);

            while ($row = $commentIdStmt->fetch(PDO::FETCH_ASSOC)) {
                $commentIds[] = (int)$row['id'];
            }

            // ---------------------------------------------
            // Remove notifications connected to the post
            // if the notifications table has question_id.
            // ---------------------------------------------
            try {

                $deleteQuestionNotifications = $conn->prepare("
                    DELETE FROM notifications
                    WHERE question_id = ?
                ");

                $deleteQuestionNotifications->execute([
                    $question_id
                ]);

            } catch (PDOException $ignore) {
                // Column may not exist. Continue.
            }

            // ---------------------------------------------
            // Remove notifications connected to comments
            // if notifications.comment_id exists.
            // ---------------------------------------------
            if (!empty($commentIds)) {

                try {

                    $placeholders = implode(
                        ',',
                        array_fill(
                            0,
                            count($commentIds),
                            '?'
                        )
                    );

                    $deleteCommentNotifications = $conn->prepare("
                        DELETE FROM notifications
                        WHERE comment_id IN ($placeholders)
                    ");

                    $deleteCommentNotifications->execute(
                        $commentIds
                    );

                } catch (PDOException $ignore) {
                    // Column may not exist. Continue.
                }
            }

            // ---------------------------------------------
            // DELETE COMMENTS
            // ---------------------------------------------
            $deleteComments = $conn->prepare("
                DELETE FROM help_comments
                WHERE question_id = ?
            ");

            $deleteComments->execute([
                $question_id
            ]);

            // ---------------------------------------------
            // DELETE HIDDEN POST RECORDS
            // ---------------------------------------------
            $deleteHidden = $conn->prepare("
                DELETE FROM help_hidden_posts
                WHERE question_id = ?
            ");

            $deleteHidden->execute([
                $question_id
            ]);

            // ---------------------------------------------
            // DELETE THE ACTUAL POST
            // ---------------------------------------------
            $deletePost = $conn->prepare("
                DELETE FROM help_questions
                WHERE id = ?
            ");

            $deletePost->execute([
                $question_id
            ]);

            $conn->commit();

            setFlash(
                'Post permanently deleted. It can no longer be seen by the student or other users.',
                'success'
            );

        } catch (PDOException $e) {

            if ($conn->inTransaction()) {
                $conn->rollBack();
            }

            setFlash(
                'Unable to permanently delete the post. Please check related database records.',
                'error'
            );
        }

        redirectBack();
    }

    // =====================================================
    // DELETE ALL POSTS PERMANENTLY
    // =====================================================
    if ($action === 'delete_all_posts') {

        try {

            $conn->beginTransaction();

            // ---------------------------------------------
            // Get all question IDs
            // ---------------------------------------------
            $questionIds = [];

            $questionIdStmt = $conn->query("
                SELECT id
                FROM help_questions
            ");

            while ($row = $questionIdStmt->fetch(PDO::FETCH_ASSOC)) {
                $questionIds[] = (int)$row['id'];
            }

            // ---------------------------------------------
            // Get all comment IDs
            // ---------------------------------------------
            $commentIds = [];

            $commentIdStmt = $conn->query("
                SELECT id
                FROM help_comments
            ");

            while ($row = $commentIdStmt->fetch(PDO::FETCH_ASSOC)) {
                $commentIds[] = (int)$row['id'];
            }

            // ---------------------------------------------
            // Remove related notifications when possible
            // ---------------------------------------------
            if (!empty($questionIds)) {

                try {

                    $questionPlaceholders = implode(
                        ',',
                        array_fill(
                            0,
                            count($questionIds),
                            '?'
                        )
                    );

                    $deleteQuestionNotifications = $conn->prepare("
                        DELETE FROM notifications
                        WHERE question_id IN ($questionPlaceholders)
                    ");

                    $deleteQuestionNotifications->execute(
                        $questionIds
                    );

                } catch (PDOException $ignore) {
                    // Continue if question_id does not exist.
                }
            }

            if (!empty($commentIds)) {

                try {

                    $commentPlaceholders = implode(
                        ',',
                        array_fill(
                            0,
                            count($commentIds),
                            '?'
                        )
                    );

                    $deleteCommentNotifications = $conn->prepare("
                        DELETE FROM notifications
                        WHERE comment_id IN ($commentPlaceholders)
                    ");

                    $deleteCommentNotifications->execute(
                        $commentIds
                    );

                } catch (PDOException $ignore) {
                    // Continue if comment_id does not exist.
                }
            }

            // ---------------------------------------------
            // DELETE ALL COMMENTS
            // ---------------------------------------------
            $conn->exec("
                DELETE FROM help_comments
            ");

            // ---------------------------------------------
            // DELETE ALL HIDDEN POST RECORDS
            // ---------------------------------------------
            $conn->exec("
                DELETE FROM help_hidden_posts
            ");

            // ---------------------------------------------
            // DELETE ALL POSTS
            // ---------------------------------------------
            $deletedCount = $conn->exec("
                DELETE FROM help_questions
            ");

            $conn->commit();

            if ((int)$deletedCount > 0) {

                setFlash(
                    'All Help Board posts and comments have been permanently deleted.',
                    'success'
                );

            } else {

                setFlash(
                    'There are no Help Board posts to delete.',
                    'success'
                );
            }

        } catch (PDOException $e) {

            if ($conn->inTransaction()) {
                $conn->rollBack();
            }

            setFlash(
                'Unable to delete all Help Board posts. Please check related database records.',
                'error'
            );
        }

        redirectBack();
    }

    // =====================================================
    // BAN STUDENT
    // =====================================================
    if ($action === 'ban_student') {

        $student_id = (int)($_POST['student_id'] ?? 0);

        if ($student_id <= 0) {
            setFlash('Invalid student account.', 'error');
            redirectBack();
        }

        try {

            // ---------------------------------------------
            // Verify student
            // ---------------------------------------------
            $studentStmt = $conn->prepare("
                SELECT user_id, full_name, role, is_banned
                FROM users
                WHERE user_id = ?
                LIMIT 1
            ");

            $studentStmt->execute([$student_id]);

            $student = $studentStmt->fetch(PDO::FETCH_ASSOC);

            if (!$student) {
                setFlash(
                    'Student account not found.',
                    'error'
                );
                redirectBack();
            }

            if (
                isset($student['role']) &&
                strtolower((string)$student['role']) !== 'student'
            ) {
                setFlash(
                    'Only student accounts can be banned from this page.',
                    'error'
                );
                redirectBack();
            }

            // ---------------------------------------------
            // Count warnings
            // ---------------------------------------------
            $warningCountStmt = $conn->prepare("
                SELECT COUNT(*)
                FROM student_warnings
                WHERE student_id = ?
            ");

            $warningCountStmt->execute([
                $student_id
            ]);

            $warningCount = (int)$warningCountStmt->fetchColumn();

            if ($warningCount < WARNING_BAN_THRESHOLD) {

                setFlash(
                    'The student must have at least ' .
                    WARNING_BAN_THRESHOLD .
                    ' warnings before the account can be banned.',
                    'error'
                );

                redirectBack();
            }

            // ---------------------------------------------
            // BAN ACCOUNT
            // ---------------------------------------------
            $banStmt = $conn->prepare("
                UPDATE users
                SET is_banned = 1
                WHERE user_id = ?
            ");

            $banStmt->execute([
                $student_id
            ]);

            // ---------------------------------------------
            // Optional notification
            // ---------------------------------------------
            try {

                $notificationStmt = $conn->prepare("
                    INSERT INTO notifications
                        (user_id, type, title, message)
                    VALUES
                        (?, ?, ?, ?)
                ");

                $notificationStmt->execute([
                    $student_id,
                    'account',
                    'Account Temporarily Disabled',
                    'Your NoteSphere account has been disabled by the administrator because it reached the warning limit.'
                ]);

            } catch (PDOException $ignore) {
                // Account was already banned.
            }

            setFlash(
                $student['full_name'] . ' has been banned.',
                'success'
            );

        } catch (PDOException $e) {

            setFlash(
                'Unable to ban the student account.',
                'error'
            );
        }

        redirectBack();
    }

    // =====================================================
    // UNBAN STUDENT
    // =====================================================
    if ($action === 'unban_student') {

        $student_id = (int)($_POST['student_id'] ?? 0);

        if ($student_id <= 0) {
            setFlash('Invalid student account.', 'error');
            redirectBack();
        }

        try {

            $studentStmt = $conn->prepare("
                SELECT user_id, full_name, role
                FROM users
                WHERE user_id = ?
                LIMIT 1
            ");

            $studentStmt->execute([
                $student_id
            ]);

            $student = $studentStmt->fetch(PDO::FETCH_ASSOC);

            if (!$student) {
                setFlash(
                    'Student account not found.',
                    'error'
                );
                redirectBack();
            }

            if (
                isset($student['role']) &&
                strtolower((string)$student['role']) !== 'student'
            ) {
                setFlash(
                    'Only student accounts can be unbanned from this page.',
                    'error'
                );
                redirectBack();
            }

            // ---------------------------------------------
            // ENABLE ACCOUNT AGAIN
            // ---------------------------------------------
            $unbanStmt = $conn->prepare("
                UPDATE users
                SET is_banned = 0
                WHERE user_id = ?
            ");

            $unbanStmt->execute([
                $student_id
            ]);

            // ---------------------------------------------
            // Optional notification
            // ---------------------------------------------
            try {

                $notificationStmt = $conn->prepare("
                    INSERT INTO notifications
                        (user_id, type, title, message)
                    VALUES
                        (?, ?, ?, ?)
                ");

                $notificationStmt->execute([
                    $student_id,
                    'account',
                    'Account Enabled',
                    'Your NoteSphere account has been enabled again by the administrator.'
                ]);

            } catch (PDOException $ignore) {
                // Account was already enabled.
            }

            setFlash(
                $student['full_name'] . ' can use the account again.',
                'success'
            );

        } catch (PDOException $e) {

            setFlash(
                'Unable to enable the student account.',
                'error'
            );
        }

        redirectBack();
    }
}

// =========================================================
// SEARCH
// =========================================================
$search = trim($_GET['search'] ?? '');

// =========================================================
// FETCH QUESTIONS
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

            u.full_name,
            u.is_banned,

            (
                SELECT COUNT(*)
                FROM student_warnings sw
                WHERE sw.student_id = q.student_id
            ) AS warning_count,

            CASE
                WHEN hp.id IS NULL THEN 0
                ELSE 1
            END AS is_hidden

        FROM help_questions q

        LEFT JOIN users u
            ON u.user_id = q.student_id

        LEFT JOIN help_hidden_posts hp
            ON hp.question_id = q.id
           AND hp.user_id = ?

    ";

    $params = [$admin_id];

    if ($search !== '') {

        $sql .= "
            WHERE
                q.title LIKE ?
                OR q.details LIKE ?
                OR u.full_name LIKE ?
        ";

        $searchParam = '%' . $search . '%';

        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    $sql .= "
        ORDER BY q.created_at DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $questions = [];

    setFlash(
        'Unable to load Help Board posts.',
        'error'
    );
}

// =========================================================
// FETCH COMMENTS
// =========================================================
$commentsByQuestion = [];

if (!empty($questions)) {

    $questionIds = array_column($questions, 'id');

    $placeholders = implode(
        ',',
        array_fill(
            0,
            count($questionIds),
            '?'
        )
    );

    try {

        $commentSql = "
            SELECT
                c.id,
                c.question_id,
                c.user_id,
                c.comment,
                c.created_at,
                u.full_name,
                u.role

            FROM help_comments c

            LEFT JOIN users u
                ON u.user_id = c.user_id

            WHERE c.question_id IN ($placeholders)

            ORDER BY c.created_at ASC
        ";

        $commentStmt = $conn->prepare($commentSql);
        $commentStmt->execute($questionIds);

        while ($comment = $commentStmt->fetch(PDO::FETCH_ASSOC)) {

            $questionId = (int)$comment['question_id'];

            if (!isset($commentsByQuestion[$questionId])) {
                $commentsByQuestion[$questionId] = [];
            }

            $commentsByQuestion[$questionId][] = $comment;
        }

    } catch (PDOException $e) {
        $commentsByQuestion = [];
    }
}

// =========================================================
// FLASH MESSAGE
// =========================================================
$flash = $_SESSION['helpboard_flash'] ?? null;

unset($_SESSION['helpboard_flash']);

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Help Board Management | NoteSphere</title>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family:
                "Segoe UI",
                Tahoma,
                Geneva,
                Verdana,
                sans-serif;

            background: #0f172a;
            color: #f8fafc;

            min-height: 100vh;
        }

        /* =====================================================
           DARK MODE
        ===================================================== */

        :root {
            --bg: #0f172a;
            --card: #111827;
            --card2: #1e293b;
            --text: #f8fafc;
            --muted: #94a3b8;
            --border: rgba(255,255,255,.08);
            --input: #0b1220;
            --blue: #3b82f6;
            --blue2: #2563eb;
            --danger: #ef4444;
            --success: #22c55e;
            --warning: #f59e0b;
        }

        /* =====================================================
           LIGHT MODE
        ===================================================== */

        html[data-theme="light"] body {
            background: #f4f7fb;
            color: #172033;
        }

        html[data-theme="light"] {
            --bg: #f4f7fb;
            --card: #ffffff;
            --card2: #f8fafc;
            --text: #172033;
            --muted: #64748b;
            --border: rgba(15,23,42,.10);
            --input: #ffffff;
            --blue: #2563eb;
            --blue2: #1d4ed8;
            --danger: #dc2626;
            --success: #16a34a;
            --warning: #d97706;
        }

        /* =====================================================
           TOPBAR
        ===================================================== */

        .topbar {
            height: 70px;

            display: flex;
            align-items: center;
            gap: 15px;

            padding: 0 24px;

            background: var(--card);

            border-bottom: 1px solid var(--border);

            position: sticky;
            top: 0;
            z-index: 100;
        }

        .back-btn {
            width: 42px;
            height: 42px;

            border: 1px solid var(--border);
            border-radius: 12px;

            display: flex;
            align-items: center;
            justify-content: center;

            color: var(--text);
            text-decoration: none;

            background: var(--card2);

            font-size: 20px;

            transition: .2s;
        }

        .back-btn:hover {
            background: var(--blue);
            color: white;
            transform: translateY(-1px);
        }

        .brand {
            font-size: 20px;
            font-weight: 700;
        }

        .brand span {
            color: var(--blue);
        }

        /* =====================================================
           CONTAINER
        ===================================================== */

        .container {
            width: min(1180px, calc(100% - 32px));

            margin: 30px auto 50px;
        }

        .page-header {
            margin-bottom: 22px;
        }

        .page-header h1 {
            font-size: 30px;
            margin-bottom: 7px;
        }

        .page-header p {
            color: var(--muted);
            font-size: 14px;
        }

        /* =====================================================
           CONTROL CARD
        ===================================================== */

        .control-card {
            background: var(--card);

            border: 1px solid var(--border);

            border-radius: 18px;

            padding: 20px;

            display: flex;
            justify-content: space-between;
            align-items: center;

            gap: 20px;

            margin-bottom: 20px;

            box-shadow:
                0 10px 30px rgba(0,0,0,.08);
        }

        .control-info h2 {
            font-size: 17px;
            margin-bottom: 6px;
        }

        .control-info p {
            color: var(--muted);
            font-size: 13px;
            line-height: 1.5;
        }

        .status {
            display: inline-flex;
            align-items: center;
            gap: 7px;

            font-size: 12px;
            font-weight: 700;

            padding: 6px 10px;

            border-radius: 999px;

            margin-top: 10px;
        }

        .status.enabled {
            background: rgba(34,197,94,.12);
            color: #22c55e;
        }

        .status.disabled {
            background: rgba(239,68,68,.12);
            color: #ef4444;
        }

        .control-button {
            border: none;
            border-radius: 11px;

            padding: 12px 18px;

            font-weight: 700;
            cursor: pointer;

            color: white;

            transition: .2s;
        }

        .control-button:hover {
            transform: translateY(-1px);
            opacity: .92;
        }

        .disable-btn {
            background: #ef4444;
        }

        .enable-btn {
            background: #22c55e;
        }

        /* =====================================================
           SEARCH
        ===================================================== */

        .search-box {
            background: var(--card);

            border: 1px solid var(--border);

            border-radius: 16px;

            padding: 15px;

            margin-bottom: 20px;
        }

        .search-box form:first-child {
            display: flex;
            gap: 10px;
        }

        .search-box input {
            flex: 1;

            min-width: 0;

            padding: 13px 15px;

            border-radius: 11px;

            border: 1px solid var(--border);

            outline: none;

            background: var(--input);
            color: var(--text);

            font-size: 14px;
        }

        .search-box input:focus {
            border-color: var(--blue);
        }

        .search-button {
            border: none;

            background: var(--blue);

            color: white;

            padding: 0 18px;

            border-radius: 11px;

            cursor: pointer;

            font-weight: 700;
        }

        /* =====================================================
           POST CARD
        ===================================================== */

        .post-card {
            background: var(--card);

            border: 1px solid var(--border);

            border-radius: 18px;

            padding: 20px;

            margin-bottom: 18px;

            box-shadow:
                0 8px 24px rgba(0,0,0,.06);
        }

        .hidden-post {
            opacity: .72;
        }

        .hidden-label {
            display: inline-block;

            background: rgba(245,158,11,.12);

            color: #f59e0b;

            padding: 5px 9px;

            border-radius: 999px;

            font-size: 11px;

            font-weight: 700;

            margin-bottom: 10px;
        }

        .post-meta {
            display: flex;
            align-items: center;
            flex-wrap: wrap;

            gap: 8px;

            margin-bottom: 10px;
        }

        .author {
            font-size: 13px;
            font-weight: 700;
        }

        .date {
            color: var(--muted);
            font-size: 12px;
        }

        .post-title {
            font-size: 19px;

            margin-bottom: 9px;

            line-height: 1.35;
        }

        .post-details {
            color: var(--muted);

            font-size: 14px;

            line-height: 1.6;

            white-space: pre-wrap;

            margin-bottom: 15px;
        }

        /* =====================================================
           POST ACTIONS
        ===================================================== */

        .post-actions {
            display: flex;
            flex-wrap: wrap;

            gap: 8px;

            margin-top: 12px;
        }

        .action-btn {
            border: none;

            border-radius: 10px;

            padding: 9px 12px;

            font-size: 12px;

            font-weight: 700;

            cursor: pointer;

            text-decoration: none;

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 5px;

            transition: .2s;
        }

        .action-btn:hover {
            transform: translateY(-1px);
            opacity: .92;
        }

        .warning-btn {
            background: rgba(245,158,11,.13);
            color: #f59e0b;
        }

        .hide-btn {
            background: rgba(239,68,68,.13);
            color: #ef4444;
        }

        .restore-btn {
            background: rgba(34,197,94,.13);
            color: #22c55e;
        }

        /* =====================================================
           COMMENTS
        ===================================================== */

        .comments {
            margin-top: 18px;

            padding-top: 16px;

            border-top: 1px solid var(--border);
        }

        .comments-title {
            font-size: 13px;

            font-weight: 700;

            margin-bottom: 12px;
        }

        .comment {
            background: var(--card2);

            border-radius: 12px;

            padding: 12px;

            margin-bottom: 8px;
        }

        .comment-top {
            display: flex;

            justify-content: space-between;

            gap: 10px;

            margin-bottom: 6px;
        }

        .comment-author {
            font-size: 12px;
            font-weight: 700;
        }

        .comment-date {
            font-size: 11px;
            color: var(--muted);
        }

        .comment-text {
            font-size: 13px;

            color: var(--muted);

            line-height: 1.5;

            white-space: pre-wrap;
        }

        .comment-delete {
            border: none;

            background: transparent;

            color: #ef4444;

            cursor: pointer;

            font-size: 11px;

            font-weight: 700;
        }

        /* =====================================================
           EMPTY
        ===================================================== */

        .empty {
            background: var(--card);

            border: 1px solid var(--border);

            border-radius: 18px;

            padding: 50px 20px;

            text-align: center;

            color: var(--muted);
        }

        .empty-icon {
            font-size: 40px;
            margin-bottom: 10px;
        }

        .empty h3 {
            color: var(--text);
            margin-bottom: 5px;
        }

        /* =====================================================
           MODAL
        ===================================================== */

        .modal-overlay {
            position: fixed;

            inset: 0;

            background: rgba(0,0,0,.65);

            display: none;

            align-items: center;
            justify-content: center;

            padding: 20px;

            z-index: 999;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal {
            width: min(500px, 100%);

            background: var(--card);

            border: 1px solid var(--border);

            border-radius: 18px;

            padding: 22px;

            box-shadow:
                0 25px 80px rgba(0,0,0,.3);
        }

        .modal-header {
            display: flex;

            align-items: center;

            justify-content: space-between;

            margin-bottom: 18px;
        }

        .modal-header h2 {
            font-size: 20px;
        }

        .close-modal {
            border: none;

            background: var(--card2);

            color: var(--text);

            width: 35px;
            height: 35px;

            border-radius: 9px;

            cursor: pointer;

            font-size: 18px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;

            margin-bottom: 7px;

            font-size: 13px;

            font-weight: 700;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;

            background: var(--input);

            color: var(--text);

            border: 1px solid var(--border);

            border-radius: 10px;

            padding: 12px;

            outline: none;

            font-family: inherit;
        }

        .form-group textarea {
            min-height: 130px;

            resize: vertical;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            border-color: var(--blue);
        }

        .send-warning {
            width: 100%;

            border: none;

            border-radius: 11px;

            background: var(--warning);

            color: white;

            padding: 12px;

            font-weight: 700;

            cursor: pointer;
        }

        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 700px) {

            .topbar {
                padding: 0 15px;
            }

            .container {
                width: min(100% - 20px, 1180px);

                margin-top: 20px;
            }

            .page-header h1 {
                font-size: 25px;
            }

            .control-card {
                flex-direction: column;
                align-items: stretch;
            }

            .control-button {
                width: 100%;
            }

            .search-box form:first-child {
                flex-direction: column;
            }

            .search-button {
                padding: 12px;
            }

            .post-card {
                padding: 16px;
            }

            .post-actions {
                flex-direction: column;
            }

            .post-actions .action-btn {
                width: 100%;
            }

            .comment-top {
                flex-direction: column;
            }
        }

    </style>

</head>

<body>

    <!-- =====================================================
         TOPBAR
    ====================================================== -->

    <div class="topbar">

        <a
            href="admin_dashboard.php"
            class="back-btn"
            title="Back to Admin Dashboard"
        >
            ✕
        </a>

        <div class="brand">
            Note<span>Sphere</span>
        </div>

    </div>

    <!-- =====================================================
         MAIN CONTAINER
    ====================================================== -->

    <main class="container">

        <div class="page-header">

            <h1>Help Board Management</h1>

            <p>
                Manage student posts, warnings, comments, and account access.
            </p>

        </div>

        <!-- =================================================
             STUDENT POSTING CONTROL
        ================================================== -->

        <div class="control-card">

            <div class="control-info">

                <h2>Student Posting Control</h2>

                <p>
                    Control whether students are allowed to create
                    new Help Board posts.
                </p>

                <?php if ($posting_enabled): ?>

                    <span class="status enabled">
                        ● Posting Enabled
                    </span>

                <?php else: ?>

                    <span class="status disabled">
                        ● Posting Disabled
                    </span>

                <?php endif; ?>

            </div>

            <form method="POST">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= h($csrf_token) ?>"
                >

                <input
                    type="hidden"
                    name="action"
                    value="toggle_posting"
                >

                <input
                    type="hidden"
                    name="posting_enabled"
                    value="<?= $posting_enabled ? '0' : '1' ?>"
                >

                <?php if ($posting_enabled): ?>

                    <button
                        type="submit"
                        class="control-button disable-btn"
                        onclick="return confirmDisablePosting(event);"
                    >
                        Disable Posting
                    </button>

                <?php else: ?>

                    <button
                        type="submit"
                        class="control-button enable-btn"
                    >
                        Enable Posting
                    </button>

                <?php endif; ?>

            </form>

        </div>

        <!-- =================================================
             SEARCH + DELETE ALL
        ================================================== -->

        <div class="search-box">

            <form method="GET">

                <input
                    type="text"
                    name="search"
                    value="<?= h($search) ?>"
                    placeholder="Search posts, details, or student name..."
                >

                <button
                    type="submit"
                    class="search-button"
                >
                    Search
                </button>

            </form>

            <?php if (!empty($questions)): ?>

                <form
                    method="POST"
                    style="margin-top:10px;"
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= h($csrf_token) ?>"
                    >

                    <input
                        type="hidden"
                        name="action"
                        value="delete_all_posts"
                    >

                    <button
                        type="submit"
                        class="action-btn hide-btn"
                        style="width:100%;"
                        onclick="return confirmDeleteAllPosts(event);"
                    >
                        🗑 Delete All Help Board Posts
                    </button>

                </form>

            <?php endif; ?>

        </div>

        <!-- =================================================
             POSTS
        ================================================== -->

        <?php if (empty($questions)): ?>

            <div class="empty">

                <div class="empty-icon">
                    📭
                </div>

                <h3>
                    No Help Board Posts Found
                </h3>

                <p>
                    There are currently no posts matching your search.
                </p>

            </div>

        <?php else: ?>

            <?php foreach ($questions as $post): ?>

                <?php

                    $question_id = (int)$post['id'];

                    $warning_count = (int)$post['warning_count'];

                    $is_banned = (int)$post['is_banned'] === 1;

                    $is_hidden = (int)$post['is_hidden'] === 1;

                    $displayName = !empty($post['is_anonymous'])
                        ? 'Anonymous Student'
                        : ($post['full_name'] ?: 'Unknown Student');

                ?>

                <article
                    class="post-card <?= $is_hidden ? 'hidden-post' : '' ?>"
                >

                    <?php if ($is_hidden): ?>

                        <div class="hidden-label">
                            Hidden from Admin View
                        </div>

                    <?php endif; ?>

                    <div class="post-meta">

                        <span class="author">
                            <?= h($displayName) ?>
                        </span>

                        <span class="date">
                            •
                            <?= h(date('M d, Y h:i A', strtotime($post['created_at']))) ?>
                        </span>

                    </div>

                    <h2 class="post-title">
                        <?= h($post['title']) ?>
                    </h2>

                    <div class="post-details">
                        <?= h($post['details']) ?>
                    </div>

                    <!-- =====================================
                         ACTIONS
                    ====================================== -->

                    <div class="post-actions">

                        <!-- WARNING COUNT -->

                        <span
                            class="action-btn warning-btn"
                            style="cursor:default;"
                        >
                            ⚠ Warnings:
                            <?= $warning_count ?>
                        </span>

                        <!-- SEND WARNING -->

                        <button
                            type="button"
                            class="action-btn warning-btn"
                            onclick='openWarningModal(
                                <?= (int)$post['student_id'] ?>,
                                <?= json_encode($post['full_name'] ?: 'Student') ?>
                            )'
                        >
                            ⚠ Send Warning
                        </button>

                        <!-- HIDE / RESTORE -->

                        <?php if ($is_hidden): ?>

                            <form method="POST">

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= h($csrf_token) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="unhide_post"
                                >

                                <input
                                    type="hidden"
                                    name="question_id"
                                    value="<?= $question_id ?>"
                                >

                                <button
                                    type="submit"
                                    class="action-btn restore-btn"
                                >
                                    ↩ Restore
                                </button>

                            </form>

                        <?php else: ?>

                            <form method="POST">

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= h($csrf_token) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="hide_post"
                                >

                                <input
                                    type="hidden"
                                    name="question_id"
                                    value="<?= $question_id ?>"
                                >

                                <button
                                    type="submit"
                                    class="action-btn hide-btn"
                                    onclick="return confirmHidePost(event);"
                                >
                                    👁 Hide
                                </button>

                            </form>

                        <?php endif; ?>

                        <!-- =================================
                             DELETE POST
                        ================================== -->

                        <form method="POST">

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= h($csrf_token) ?>"
                            >

                            <input
                                type="hidden"
                                name="action"
                                value="delete_post"
                            >

                            <input
                                type="hidden"
                                name="question_id"
                                value="<?= $question_id ?>"
                            >

                            <button
                                type="submit"
                                class="action-btn hide-btn"
                                onclick="return confirmDeletePost(event);"
                            >
                                🗑 Delete Post
                            </button>

                        </form>

                        <!-- =================================
                             BAN / UNBAN
                        ================================== -->

                        <?php if ($is_banned): ?>

                            <form method="POST">

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= h($csrf_token) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="unban_student"
                                >

                                <input
                                    type="hidden"
                                    name="student_id"
                                    value="<?= (int)$post['student_id'] ?>"
                                >

                                <button
                                    type="submit"
                                    class="action-btn restore-btn"
                                    onclick="return confirmUnbanStudent(event);"
                                >
                                    🔓 Unban Student
                                </button>

                            </form>

                        <?php elseif ($warning_count >= WARNING_BAN_THRESHOLD): ?>

                            <form method="POST">

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= h($csrf_token) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="ban_student"
                                >

                                <input
                                    type="hidden"
                                    name="student_id"
                                    value="<?= (int)$post['student_id'] ?>"
                                >

                                <button
                                    type="submit"
                                    class="action-btn hide-btn"
                                    onclick="return confirmBanStudent(event);"
                                >
                                    🔒 Ban Student
                                </button>

                            </form>

                        <?php endif; ?>

                    </div>

                    <!-- =================================================
                         COMMENTS
                    ================================================== -->

                    <?php

                        $postComments =
                            $commentsByQuestion[$question_id] ?? [];

                    ?>

                    <div class="comments">

                        <div class="comments-title">
                            💬 Comments
                            (<?= count($postComments) ?>)
                        </div>

                        <?php if (empty($postComments)): ?>

                            <div
                                style="
                                    color:var(--muted);
                                    font-size:12px;
                                "
                            >
                                No comments yet.
                            </div>

                        <?php else: ?>

                            <?php foreach ($postComments as $comment): ?>

                                <div class="comment">

                                    <div class="comment-top">

                                        <div>

                                            <span class="comment-author">
                                                <?= h($comment['full_name'] ?: 'Unknown User') ?>
                                            </span>

                                            <?php if (!empty($comment['role'])): ?>

                                                <span
                                                    style="
                                                        color:var(--muted);
                                                        font-size:10px;
                                                        margin-left:5px;
                                                    "
                                                >
                                                    <?= h(ucfirst($comment['role'])) ?>
                                                </span>

                                            <?php endif; ?>

                                        </div>

                                        <div>

                                            <span class="comment-date">
                                                <?= h(date(
                                                    'M d, Y h:i A',
                                                    strtotime($comment['created_at'])
                                                )) ?>
                                            </span>

                                            <form
                                                method="POST"
                                                style="display:inline;"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= h($csrf_token) ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="delete_comment"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="comment_id"
                                                    value="<?= (int)$comment['id'] ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    class="comment-delete"
                                                    onclick="return confirmDeleteComment(event);"
                                                >
                                                    Remove
                                                </button>

                                            </form>

                                        </div>

                                    </div>

                                    <div class="comment-text">
                                        <?= h($comment['comment']) ?>
                                    </div>

                                </div>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </div>

                </article>

            <?php endforeach; ?>

        <?php endif; ?>

    </main>

    <!-- =====================================================
         WARNING MODAL
    ====================================================== -->

    <div
        class="modal-overlay"
        id="warningModal"
    >

        <div class="modal">

            <div class="modal-header">

                <h2>
                    Send Warning
                </h2>

                <button
                    type="button"
                    class="close-modal"
                    onclick="closeWarningModal()"
                >
                    ✕
                </button>

            </div>

            <form method="POST">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= h($csrf_token) ?>"
                >

                <input
                    type="hidden"
                    name="action"
                    value="send_warning"
                >

                <input
                    type="hidden"
                    name="student_id"
                    id="warningStudentId"
                >

                <div class="form-group">

                    <label>
                        Student
                    </label>

                    <input
                        type="text"
                        id="warningStudentName"
                        readonly
                    >

                </div>

                <div class="form-group">

                    <label>
                        Warning Title
                    </label>

                    <input
                        type="text"
                        name="warning_title"
                        placeholder="Enter warning title..."
                        required
                    >

                </div>

                <div class="form-group">

                    <label>
                        Warning Message
                    </label>

                    <textarea
                        name="warning_message"
                        placeholder="Write the warning message..."
                        required
                    ></textarea>

                </div>

                <button
                    type="submit"
                    class="send-warning"
                >
                    Send Warning
                </button>

            </form>

        </div>

    </div>

    <!-- =====================================================
         JAVASCRIPT
    ====================================================== -->

    <script>

        // =====================================================
        // THEME
        // =====================================================

        const savedTheme =
            localStorage.getItem('notesphere_admin_theme') ||
            localStorage.getItem('notesphere_theme') ||
            localStorage.getItem('theme') ||
            'dark';

        document.documentElement.setAttribute(
            'data-theme',
            savedTheme
        );


        // =====================================================
        // WARNING MODAL
        // =====================================================

        function openWarningModal(studentId, studentName) {

            document.getElementById(
                'warningStudentId'
            ).value = studentId;

            document.getElementById(
                'warningStudentName'
            ).value = studentName;

            document.getElementById(
                'warningModal'
            ).classList.add('active');
        }


        function closeWarningModal() {

            document.getElementById(
                'warningModal'
            ).classList.remove('active');
        }


        document.getElementById(
            'warningModal'
        ).addEventListener('click', function(event) {

            if (event.target === this) {
                closeWarningModal();
            }

        });


        // =====================================================
        // DISABLE POSTING
        // =====================================================

        function confirmDisablePosting(event) {

            event.preventDefault();

            const form = event.target.closest('form');

            Swal.fire({

                title: 'Disable Student Posting?',

                text:
                    'Students will not be able to create new Help Board posts while this is disabled.',

                icon: 'warning',

                showCancelButton: true,

                confirmButtonText: 'Yes, Disable',

                cancelButtonText: 'Cancel',

                confirmButtonColor: '#ef4444'

            }).then((result) => {

                if (result.isConfirmed) {
                    form.submit();
                }

            });

            return false;
        }


        // =====================================================
        // HIDE POST
        // =====================================================

        function confirmHidePost(event) {

            event.preventDefault();

            const form = event.target.closest('form');

            Swal.fire({

                title: 'Hide this post?',

                text:
                    'The post will be hidden from your admin view.',

                icon: 'warning',

                showCancelButton: true,

                confirmButtonText: 'Yes, Hide',

                cancelButtonText: 'Cancel',

                confirmButtonColor: '#ef4444'

            }).then((result) => {

                if (result.isConfirmed) {
                    form.submit();
                }

            });

            return false;
        }


        // =====================================================
        // DELETE ONE POST
        // =====================================================

        function confirmDeletePost(event) {

            event.preventDefault();

            const form = event.target.closest('form');

            Swal.fire({

                title: 'Delete this post permanently?',

                html:
                    '<div style="line-height:1.6;">' +
                    'This will permanently remove the post ' +
                    '<b>and its comments</b> from the database.<br><br>' +
                    'The student who posted it and other users ' +
                    'will no longer be able to see it.' +
                    '</div>',

                icon: 'error',

                showCancelButton: true,

                confirmButtonText: 'Yes, Delete Permanently',

                cancelButtonText: 'Cancel',

                confirmButtonColor: '#dc2626'

            }).then((result) => {

                if (result.isConfirmed) {
                    form.submit();
                }

            });

            return false;
        }


        // =====================================================
        // DELETE ALL POSTS
        // =====================================================

        function confirmDeleteAllPosts(event) {

            event.preventDefault();

            const form = event.target.closest('form');

            Swal.fire({

                title: 'Delete ALL Help Board Posts?',

                html:
                    '<div style="line-height:1.6;">' +
                    '<b>This action cannot be undone.</b><br><br>' +
                    'All Help Board posts, comments, and related ' +
                    'post records will be permanently deleted.' +
                    '</div>',

                icon: 'error',

                showCancelButton: true,

                confirmButtonText: 'Yes, Delete Everything',

                cancelButtonText: 'Cancel',

                confirmButtonColor: '#dc2626'

            }).then((result) => {

                if (result.isConfirmed) {
                    form.submit();
                }

            });

            return false;
        }


        // =====================================================
        // DELETE COMMENT
        // =====================================================

        function confirmDeleteComment(event) {

            event.preventDefault();

            const form = event.target.closest('form');

            Swal.fire({

                title: 'Delete this comment?',

                text:
                    'This comment will be permanently removed.',

                icon: 'warning',

                showCancelButton: true,

                confirmButtonText: 'Delete',

                cancelButtonText: 'Cancel',

                confirmButtonColor: '#dc2626'

            }).then((result) => {

                if (result.isConfirmed) {
                    form.submit();
                }

            });

            return false;
        }


        // =====================================================
        // BAN STUDENT
        // =====================================================

        function confirmBanStudent(event) {

            event.preventDefault();

            const form = event.target.closest('form');

            Swal.fire({

                title: 'Ban this student?',

                html:
                    '<div style="line-height:1.6;">' +
                    'The student account will be disabled.<br><br>' +
                    'The account can be enabled again later using ' +
                    '<b>Unban Student</b>.' +
                    '</div>',

                icon: 'warning',

                showCancelButton: true,

                confirmButtonText: 'Yes, Ban Student',

                cancelButtonText: 'Cancel',

                confirmButtonColor: '#dc2626'

            }).then((result) => {

                if (result.isConfirmed) {
                    form.submit();
                }

            });

            return false;
        }


        // =====================================================
        // UNBAN STUDENT
        // =====================================================

        function confirmUnbanStudent(event) {

            event.preventDefault();

            const form = event.target.closest('form');

            Swal.fire({

                title: 'Enable this student account?',

                text:
                    'The student will be allowed to use the account again.',

                icon: 'question',

                showCancelButton: true,

                confirmButtonText: 'Yes, Enable Account',

                cancelButtonText: 'Cancel',

                confirmButtonColor: '#22c55e'

            }).then((result) => {

                if (result.isConfirmed) {
                    form.submit();
                }

            });

            return false;
        }

        <?php if ($flash): ?>

        // =====================================================
        // FLASH MESSAGE
        // =====================================================

        Swal.fire({

            icon: <?= json_encode(
                $flash['type'] === 'success'
                    ? 'success'
                    : 'error'
            ) ?>,

            title: <?= json_encode(
                $flash['type'] === 'success'
                    ? 'Success'
                    : 'Error'
            ) ?>,

            text: <?= json_encode(
                $flash['message']
            ) ?>,

            confirmButtonColor: '#2563eb',

            timer: 2500,

            timerProgressBar: true

        });

        <?php endif; ?>

    </script>

</body>

</html>

