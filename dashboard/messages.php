
<?php

// =========================================================
// NoteSphere - Unified Student & Professor Consultation Chat
// Theme Adaptive + Mobile Ready
// =========================================================

session_start();

require_once __DIR__ . '/../includes/db_config.php';

/** @var PDO $conn */

// =========================================================
// AUTHENTICATION CHECK
// =========================================================

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$role    = $_SESSION['role'];

$selected_student_id = isset($_GET['student_id'])
    ? (int) $_GET['student_id']
    : null;

$prof_id = isset($_GET['prof_id'])
    ? (int) $_GET['prof_id']
    : null;

// =========================================================
// ALLOWED ROLES ONLY
// =========================================================

if (!in_array($role, ['student', 'professor'], true)) {
    header("Location: ../auth/login.php");
    exit();
}

// =========================================================
// HELPER: UPLOAD CHAT FILE
// =========================================================

function uploadChatFile(): array
{
    $result = [
        'file_path' => null,
        'file_type' => null
    ];

    if (
        !isset($_FILES['chat_file']) ||
        $_FILES['chat_file']['error'] !== UPLOAD_ERR_OK
    ) {
        return $result;
    }

    $file_tmp  = $_FILES['chat_file']['tmp_name'];
    $file_name = $_FILES['chat_file']['name'];
    $file_size = (int) $_FILES['chat_file']['size'];

    // Maximum file size: 25 MB
    $max_size = 25 * 1024 * 1024;

    if ($file_size <= 0 || $file_size > $max_size) {
        return $result;
    }

    // Get extension
    $extension = strtolower(
        pathinfo($file_name, PATHINFO_EXTENSION)
    );

    // Allowed extensions
    $allowed_extensions = [
        'jpg',
        'jpeg',
        'png',
        'gif',
        'webp',
        'mp4',
        'webm',
        'mov',
        'pdf',
        'doc',
        'docx',
        'xls',
        'xlsx',
        'ppt',
        'pptx',
        'txt',
        'zip'
    ];

    if (!in_array($extension, $allowed_extensions, true)) {
        return $result;
    }

    // Create upload directory
    $upload_dir = __DIR__ . '/../uploads/messages/';

    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0777, true)) {
            return $result;
        }
    }

    // Generate safer unique filename
    $safe_name = preg_replace(
        '/[^A-Za-z0-9._-]/',
        '_',
        basename($file_name)
    );

    $unique_name = time()
        . '_'
        . bin2hex(random_bytes(5))
        . '_'
        . $safe_name;

    $destination = $upload_dir . $unique_name;

    if (!move_uploaded_file($file_tmp, $destination)) {
        return $result;
    }

    // Determine file type
    if (
        in_array(
            $extension,
            ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            true
        )
    ) {
        $file_type = 'image';
    } elseif (
        in_array(
            $extension,
            ['mp4', 'webm', 'mov'],
            true
        )
    ) {
        $file_type = 'video';
    } else {
        $file_type = 'file';
    }

    $result['file_path'] = 'messages/' . $unique_name;
    $result['file_type'] = $file_type;

    return $result;
}

// =========================================================
// STUDENT SIDE
// =========================================================

if ($role === 'student') {

    // Professor must be selected
    if (!$prof_id) {
        header("Location: student_home.php");
        exit();
    }

    // -----------------------------------------------------
    // GET PROFESSOR
    // IMPORTANT:
    // users table uses user_id, NOT id
    // -----------------------------------------------------

    $prof_stmt = $conn->prepare("
        SELECT
            user_id,
            full_name,
            section
        FROM users
        WHERE user_id = ?
          AND role = 'professor'
        LIMIT 1
    ");

    $prof_stmt->execute([$prof_id]);

    $professor = $prof_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$professor) {
        header("Location: student_home.php");
        exit();
    }

    // -----------------------------------------------------
    // SEND MESSAGE - STUDENT TO PROFESSOR
    // -----------------------------------------------------

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $msg_content = trim($_POST['message'] ?? '');

        $file_path = null;
        $file_type = null;

        // Upload attachment if present
        if (
            isset($_FILES['chat_file']) &&
            $_FILES['chat_file']['error'] !== UPLOAD_ERR_NO_FILE
        ) {
            $upload_result = uploadChatFile();

            $file_path = $upload_result['file_path'];
            $file_type = $upload_result['file_type'];
        }

        // Only insert if there is a message OR attachment
        if ($msg_content !== '' || !empty($file_path)) {

            $insert_stmt = $conn->prepare("
                INSERT INTO messages
                (
                    sender_id,
                    receiver_id,
                    message,
                    file_path,
                    file_type,
                    created_at
                )
                VALUES (?, ?, ?, ?, ?, NOW())
            ");

            $insert_stmt->execute([
                $user_id,
                $prof_id,
                $msg_content,
                $file_path,
                $file_type
            ]);
        }

        // Return to conversation
        header(
            "Location: messages.php?prof_id="
            . $prof_id
        );
        exit();
    }

    // -----------------------------------------------------
    // GET CHAT BETWEEN STUDENT AND PROFESSOR
    // -----------------------------------------------------

    $chat_stmt = $conn->prepare("
        SELECT
            id,
            sender_id,
            receiver_id,
            message,
            file_path,
            file_type,
            created_at
        FROM messages
        WHERE
            (
                sender_id = ?
                AND receiver_id = ?
            )
            OR
            (
                sender_id = ?
                AND receiver_id = ?
            )
        ORDER BY created_at ASC, id ASC
    ");

    $chat_stmt->execute([
        $user_id,
        $prof_id,
        $prof_id,
        $user_id
    ]);

    $messages = $chat_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// =========================================================
// PROFESSOR SIDE
// =========================================================

else {

    $professor_id = $user_id;

    // -----------------------------------------------------
    // GET STUDENTS WHO HAVE CONVERSATIONS
    // IMPORTANT:
    // users table uses user_id, NOT id
    // -----------------------------------------------------

    $students_stmt = $conn->prepare("
        SELECT DISTINCT
            u.user_id,
            u.full_name,
            u.section
        FROM users u
        INNER JOIN messages m
            ON (
                u.user_id = m.sender_id
                OR
                u.user_id = m.receiver_id
            )
        WHERE
            (
                m.sender_id = ?
                OR
                m.receiver_id = ?
            )
            AND u.role = 'student'
        ORDER BY u.full_name ASC
    ");

    $students_stmt->execute([
        $professor_id,
        $professor_id
    ]);

    $students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);

    $selected_student = null;

    // -----------------------------------------------------
    // GET SELECTED STUDENT
    // -----------------------------------------------------

    if ($selected_student_id) {

        $stu_stmt = $conn->prepare("
            SELECT
                user_id,
                full_name,
                section
            FROM users
            WHERE user_id = ?
              AND role = 'student'
            LIMIT 1
        ");

        $stu_stmt->execute([
            $selected_student_id
        ]);

        $selected_student = $stu_stmt->fetch(PDO::FETCH_ASSOC);
    }

    // -----------------------------------------------------
    // SEND MESSAGE - PROFESSOR TO STUDENT
    // -----------------------------------------------------

    if (
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        $selected_student_id &&
        $selected_student
    ) {

        $msg_content = trim($_POST['message'] ?? '');

        $file_path = null;
        $file_type = null;

        // Upload attachment
        if (
            isset($_FILES['chat_file']) &&
            $_FILES['chat_file']['error'] !== UPLOAD_ERR_NO_FILE
        ) {
            $upload_result = uploadChatFile();

            $file_path = $upload_result['file_path'];
            $file_type = $upload_result['file_type'];
        }

        // Insert message
        if ($msg_content !== '' || !empty($file_path)) {

            $insert_stmt = $conn->prepare("
                INSERT INTO messages
                (
                    sender_id,
                    receiver_id,
                    message,
                    file_path,
                    file_type,
                    created_at
                )
                VALUES (?, ?, ?, ?, ?, NOW())
            ");

            $insert_stmt->execute([
                $professor_id,
                $selected_student_id,
                $msg_content,
                $file_path,
                $file_type
            ]);
        }

        // Return to selected conversation
        header(
            "Location: messages.php?student_id="
            . $selected_student_id
        );
        exit();
    }

    // -----------------------------------------------------
    // GET CHAT
    // -----------------------------------------------------

    $messages = [];

    if ($selected_student_id && $selected_student) {

        $chat_stmt = $conn->prepare("
            SELECT
                id,
                sender_id,
                receiver_id,
                message,
                file_path,
                file_type,
                created_at
            FROM messages
            WHERE
                (
                    sender_id = ?
                    AND receiver_id = ?
                )
                OR
                (
                    sender_id = ?
                    AND receiver_id = ?
                )
            ORDER BY created_at ASC, id ASC
        ");

        $chat_stmt->execute([
            $professor_id,
            $selected_student_id,
            $selected_student_id,
            $professor_id
        ]);

        $messages = $chat_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"
    >

    <title>Consultation Chat | NoteSphere</title>

    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <link rel="manifest" href="manifest.json">

    <meta
        name="theme-color"
        content="#050505"
    >

    <style>

        /* =====================================================
           THEME VARIABLES
        ===================================================== */

        :root {

            --bg-color: #f0f2f5;

            --container-bg: rgba(255, 255, 255, 0.96);

            --header-bg: rgba(248, 250, 252, 0.96);

            --text-main: #0f172a;

            --sub-text: #64748b;

            --border-clr: rgba(0, 0, 0, 0.08);

            --received-bubble-bg: rgba(241, 245, 249, 0.96);

            --received-text: #0f172a;

            --input-box-bg: rgba(241, 245, 249, 0.96);

            --input-text: #0f172a;

            --accent-green: #10b981;

            --accent-green-hover: rgba(16, 185, 129, 0.15);

            --date-badge-bg: rgba(0, 0, 0, 0.06);

            --date-badge-text: #64748b;

        }

        [data-theme="dark"] {

            --bg-color: #050505;

            --container-bg: rgba(12, 12, 12, 0.96);

            --header-bg: rgba(15, 23, 42, 0.94);

            --text-main: #f8fafc;

            --sub-text: #94a3b8;

            --border-clr: #1f293d;

            --received-bubble-bg: rgba(30, 41, 59, 0.94);

            --received-text: #f8fafc;

            --input-box-bg: rgba(20, 24, 33, 0.94);

            --input-text: #ffffff;

            --accent-green: #39FF14;

            --accent-green-hover: rgba(57, 255, 20, 0.15);

            --date-badge-bg: rgba(255, 255, 255, 0.08);

            --date-badge-text: #94a3b8;

        }

        /* =====================================================
           RESET
        ===================================================== */

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        html,
        body {

            background-color: var(--bg-color);

            background-image: url('../assets/bginmess.png');

            background-repeat: no-repeat;

            background-position: center center;

            background-attachment: fixed;

            background-size: cover;

            color: var(--text-main);

            height: 100vh;

            width: 100vw;

            overflow: hidden;

            display: flex;

            justify-content: center;

            align-items: center;

            transition:
                background-color 0.3s ease,
                color 0.3s ease;

        }

        /* =====================================================
           MAIN MESSENGER
        ===================================================== */

        .messenger-layout {

            width: 100%;

            max-width: 900px;

            height: 100vh;

            background: var(--container-bg);

            backdrop-filter: blur(8px);

            -webkit-backdrop-filter: blur(8px);

            border: 1px solid var(--border-clr);

            display: flex;

            flex-direction:
                <?php echo ($role === 'professor')
                    ? 'row'
                    : 'column'; ?>;

            overflow: hidden;

            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.3);

            transition:
                background 0.3s ease,
                border-color 0.3s ease;

        }

        /* =====================================================
           PROFESSOR SIDEBAR
        ===================================================== */

        <?php if ($role === 'professor'): ?>

        .sidebar {

            width: 300px;

            border-right: 1px solid var(--border-clr);

            display: flex;

            flex-direction: column;

            background: var(--header-bg);

            flex-shrink: 0;

        }

        .sidebar-header {

            padding: 14px 18px;

            border-bottom: 1px solid var(--border-clr);

            display: flex;

            align-items: center;

            justify-content: space-between;

        }

        .sidebar-header h2 {

            font-size: 1.05rem;

            font-weight: 700;

            color: var(--text-main);

        }

        .student-list {

            flex: 1;

            overflow-y: auto;

        }

        .student-item {

            display: flex;

            flex-direction: column;

            gap: 4px;

            padding: 13px 18px;

            text-decoration: none;

            color: var(--text-main);

            border-bottom: 1px solid var(--border-clr);

            transition: background 0.2s ease;

        }

        .student-item:hover,
        .student-item.active {

            background: var(--accent-green-hover);

        }

        .student-name {

            font-weight: 600;

            font-size: 0.9rem;

        }

        .student-meta {

            font-size: 0.75rem;

            color: var(--sub-text);

        }

        .chat-area {

            flex: 1;

            display: flex;

            flex-direction: column;

            height: 100%;

            min-width: 0;

        }

        <?php else: ?>

        .chat-area {

            width: 100%;

            height: 100%;

            display: flex;

            flex-direction: column;

        }

        <?php endif; ?>

        /* =====================================================
           HEADER
        ===================================================== */

        .messenger-header {

            padding: 14px 18px;

            background: var(--header-bg);

            border-bottom: 1px solid var(--border-clr);

            display: flex;

            align-items: center;

            justify-content: space-between;

            flex-shrink: 0;

            z-index: 5;

        }

        .messenger-header-left {

            display: flex;

            align-items: center;

            gap: 14px;

            min-width: 0;

            flex: 1;

        }

        .close-btn {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            width: 40px;

            height: 40px;

            background: var(--container-bg);

            border: 1px solid var(--border-clr);

            color: var(--text-main);

            border-radius: 12px;

            font-weight: 800;

            font-size: 1.1rem;

            text-decoration: none;

            transition: 0.3s ease;

            flex-shrink: 0;

        }

        .close-btn:hover {

            border-color: #ff4d4d;

            color: #ff4d4d;

            background: rgba(255, 77, 77, 0.08);

            transform: scale(1.03);

        }

        .prof-info {

            flex: 1;

            min-width: 0;

            display: flex;

            flex-direction: column;

        }

        .prof-info h3 {

            font-size: 1.05rem;

            color: var(--text-main);

            font-weight: 700;

            line-height: 1.2;

            white-space: nowrap;

            overflow: hidden;

            text-overflow: ellipsis;

        }

        .prof-info p {

            font-size: 0.75rem;

            color: var(--accent-green);

            display: flex;

            align-items: center;

            gap: 6px;

            margin-top: 3px;

        }

        .prof-info p::before {

            content: '';

            display: inline-block;

            width: 6px;

            height: 6px;

            background: var(--accent-green);

            border-radius: 50%;

        }

        /* =====================================================
           CHAT BODY
        ===================================================== */

        .messenger-body {

            flex: 1;

            padding: 16px;

            overflow-y: auto;

            display: flex;

            flex-direction: column;

            gap: 12px;

            background: transparent;

            -webkit-overflow-scrolling: touch;

            scrollbar-width: thin;

        }

        .date-divider {

            display: flex;

            justify-content: center;

            margin: 10px 0;

            width: 100%;

        }

        .date-divider span {

            background: var(--date-badge-bg);

            color: var(--date-badge-text);

            font-size: 0.7rem;

            font-weight: 600;

            padding: 4px 12px;

            border-radius: 12px;

            text-transform: uppercase;

        }

        .message-row {

            display: flex;

            width: 100%;

        }

        .message-row.sent {

            justify-content: flex-end;

        }

        .message-row.received {

            justify-content: flex-start;

        }

        .bubble {

            max-width: 75%;

            padding: 10px 14px;

            border-radius: 16px;

            font-size: 0.9rem;

            line-height: 1.4;

            position: relative;

            word-break: break-word;

            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.10);

        }

        .message-row.sent .bubble {

            background: var(--accent-green);

            color: #050505;

            border-bottom-right-radius: 4px;

            font-weight: 600;

        }

        .message-row.received .bubble {

            background: var(--received-bubble-bg);

            color: var(--received-text);

            border-bottom-left-radius: 4px;

            border: 1px solid var(--border-clr);

        }

        .msg-time {

            font-size: 0.62rem;

            display: block;

            margin-top: 4px;

            text-align: right;

            opacity: 0.7;

        }

        /* =====================================================
           ATTACHMENTS
        ===================================================== */

        .bubble img,
        .bubble video {

            max-width: 100%;

            max-height: 320px;

            border-radius: 8px;

            margin-bottom: 6px;

            display: block;

            object-fit: contain;

        }

        .file-attachment {

            display: inline-flex;

            align-items: center;

            gap: 6px;

            padding: 8px 12px;

            background: rgba(0, 0, 0, 0.10);

            border-radius: 8px;

            color: inherit;

            text-decoration: none;

            font-weight: 600;

            font-size: 0.85rem;

            margin-bottom: 5px;

        }

        .file-attachment:hover {

            opacity: 0.8;

        }

        /* =====================================================
           FOOTER
        ===================================================== */

        .messenger-footer {

            padding: 12px 16px;

            background: var(--header-bg);

            border-top: 1px solid var(--border-clr);

            flex-shrink: 0;

            width: 100%;

        }

        .chat-form {

            display: flex;

            gap: 8px;

            align-items: center;

            background: var(--input-box-bg);

            border-radius: 28px;

            padding: 6px 12px;

            border: 1px solid var(--border-clr);

            width: 100%;

        }

        .chat-input {

            flex: 1;

            background: transparent;

            border: none;

            outline: none;

            color: var(--input-text);

            font-size: 0.92rem;

            min-width: 0;

        }

        .chat-input::placeholder {

            color: var(--sub-text);

        }

        .attach-btn,
        .send-btn {

            background: transparent;

            border: none;

            color: var(--sub-text);

            cursor: pointer;

            display: flex;

            align-items: center;

            justify-content: center;

            width: 36px;

            height: 36px;

            border-radius: 50%;

            transition: 0.2s ease;

            flex-shrink: 0;

        }

        .attach-btn:hover {

            color: var(--accent-green);

            background: var(--accent-green-hover);

        }

        .send-btn {

            background: var(--accent-green);

            color: #000;

            font-weight: bold;

        }

        .send-btn:hover {

            transform: scale(1.05);

            opacity: 0.9;

        }

        .attach-btn svg,
        .send-btn svg {

            width: 18px;

            height: 18px;

            fill: currentColor;

        }

        /* =====================================================
           EMPTY CHAT
        ===================================================== */

        .no-chat-selected {

            flex: 1;

            display: flex;

            align-items: center;

            justify-content: center;

            color: var(--sub-text);

            text-align: center;

            padding: 20px;

            font-size: 0.9rem;

        }

        /* =====================================================
           SCROLLBAR
        ===================================================== */

        .messenger-body::-webkit-scrollbar,
        .student-list::-webkit-scrollbar {

            width: 6px;

        }

        .messenger-body::-webkit-scrollbar-thumb,
        .student-list::-webkit-scrollbar-thumb {

            background: var(--border-clr);

            border-radius: 10px;

        }

        /* =====================================================
           MOBILE
        ===================================================== */

        @media (max-width: 768px) {

            html,
            body {

                height: 100vh;

                height: 100dvh;

            }

            .messenger-layout {

                width: 100vw;

                height: 100vh;

                height: 100dvh;

                border: none;

                box-shadow: none;

            }

            <?php if ($role === 'professor'): ?>

            .sidebar {

                display:
                    <?php echo $selected_student_id
                        ? 'none'
                        : 'flex'; ?>;

                width: 100%;

                height: 100%;

            }

            .chat-area {

                display:
                    <?php echo $selected_student_id
                        ? 'flex'
                        : 'none'; ?>;

                width: 100%;

                height: 100%;

            }

            <?php endif; ?>

            .messenger-header {

                padding: 12px;

            }

            .messenger-body {

                padding: 12px;

                gap: 10px;

            }

            .bubble {

                max-width: 86%;

                font-size: 0.88rem;

            }

            .messenger-footer {

                padding: 10px 10px;

                padding-bottom:
                    max(
                        10px,
                        env(safe-area-inset-bottom)
                    );

            }

            .chat-form {

                padding: 5px 8px;

            }

            .chat-input {

                font-size: 0.88rem;

            }

        }

        @media (max-width: 420px) {

            .close-btn {

                width: 36px;

                height: 36px;

                border-radius: 10px;

            }

            .prof-info h3 {

                font-size: 0.95rem;

            }

            .prof-info p {

                font-size: 0.68rem;

            }

            .bubble {

                max-width: 90%;

                padding: 9px 12px;

            }

            .attach-btn,
            .send-btn {

                width: 34px;

                height: 34px;

            }

        }

    </style>

</head>

<body>

    <div class="messenger-layout">

        <?php if ($role === 'professor'): ?>

            <!-- =================================================
                 PROFESSOR SIDEBAR
            ================================================== -->

            <div class="sidebar">

                <div class="sidebar-header">

                    <h2>Consultations</h2>

                    <a
                        href="professor_dashboard.php"
                        class="close-btn"
                        title="Exit"
                        style="
                            width: 32px;
                            height: 32px;
                            font-size: 0.9rem;
                        "
                    >
                        ✕
                    </a>

                </div>

                <div class="student-list">

                    <?php if (!empty($students)): ?>

                        <?php foreach ($students as $stu): ?>

                            <a
                                href="messages.php?student_id=<?php echo (int) $stu['user_id']; ?>"
                                class="student-item <?php
                                    echo (
                                        $selected_student_id == $stu['user_id']
                                    )
                                        ? 'active'
                                        : '';
                                ?>"
                            >

                                <div class="student-name">

                                    <?php
                                    echo htmlspecialchars(
                                        $stu['full_name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>

                                </div>

                                <div class="student-meta">

                                    Section:
                                    <?php
                                    echo htmlspecialchars(
                                        $stu['section'] ?? 'N/A',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>

                                </div>

                            </a>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <p
                            style="
                                padding: 20px;
                                text-align: center;
                                color: var(--sub-text);
                                font-size: 0.85rem;
                            "
                        >
                            No student messages yet.
                        </p>

                    <?php endif; ?>

                </div>

            </div>

        <?php endif; ?>


        <!-- =====================================================
             CHAT AREA
        ====================================================== -->

        <div class="chat-area">

            <?php if (
                $role === 'student' ||
                ($role === 'professor' && $selected_student)
            ): ?>

                <!-- =================================================
                     CHAT HEADER
                ================================================== -->

                <div class="messenger-header">

                    <div class="messenger-header-left">

                        <div class="prof-info">

                            <h3>

                                <?php

                                if ($role === 'student') {

                                    echo 'Prof. '
                                        . htmlspecialchars(
                                            $professor['full_name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );

                                } else {

                                    echo htmlspecialchars(
                                        $selected_student['full_name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );

                                }

                                ?>

                            </h3>

                            <p>

                                <?php

                                if ($role === 'student') {

                                    echo 'Consultation Chat';

                                } else {

                                    echo 'Section: '
                                        . htmlspecialchars(
                                            $selected_student['section'] ?? 'N/A',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );

                                }

                                ?>

                            </p>

                        </div>

                    </div>


                    <!-- X BUTTON -->

                    <a
                        href="<?php

                            if ($role === 'student') {

                                echo 'professor_announcements.php?prof_id='
                                    . (int) $prof_id;

                            } else {

                                echo 'messages.php';

                            }

                        ?>"
                        class="close-btn"
                        title="Back"
                    >
                        ✕
                    </a>

                </div>


                <!-- =================================================
                     MESSAGES
                ================================================== -->

                <div
                    class="messenger-body"
                    id="chatScroll"
                >

                    <?php if (!empty($messages)): ?>

                        <?php

                        $current_date = '';

                        foreach ($messages as $msg):

                            $msg_date = date(
                                'Y-m-d',
                                strtotime($msg['created_at'])
                            );

                            if (
                                $msg_date === date('Y-m-d')
                            ) {

                                $date_label = 'Today';

                            } elseif (
                                $msg_date === date(
                                    'Y-m-d',
                                    strtotime('-1 day')
                                )
                            ) {

                                $date_label = 'Yesterday';

                            } else {

                                $date_label = date(
                                    'F j, Y',
                                    strtotime($msg['created_at'])
                                );

                            }

                            if ($current_date !== $msg_date):

                                $current_date = $msg_date;

                        ?>

                                <div class="date-divider">

                                    <span>
                                        <?php
                                        echo htmlspecialchars(
                                            $date_label,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                        ?>
                                    </span>

                                </div>

                            <?php endif; ?>


                            <?php

                            $isSent =
                                ((int) $msg['sender_id'] === $user_id);

                            ?>

                            <div
                                class="message-row <?php
                                    echo $isSent
                                        ? 'sent'
                                        : 'received';
                                ?>"
                            >

                                <div class="bubble">


                                    <!-- =================================
                                         ATTACHMENT
                                    ================================== -->

                                    <?php if (!empty($msg['file_path'])): ?>

                                        <?php

                                        $attachment_path =
                                            '../uploads/'
                                            . $msg['file_path'];

                                        ?>

                                        <?php if (
                                            $msg['file_type'] === 'image'
                                        ): ?>

                                            <a
                                                href="<?php
                                                    echo htmlspecialchars(
                                                        $attachment_path,
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    );
                                                ?>"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                            >

                                                <img
                                                    src="<?php
                                                        echo htmlspecialchars(
                                                            $attachment_path,
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        );
                                                    ?>"
                                                    alt="Uploaded Image"
                                                    loading="lazy"
                                                >

                                            </a>


                                        <?php elseif (
                                            $msg['file_type'] === 'video'
                                        ): ?>

                                            <video
                                                controls
                                                preload="metadata"
                                            >

                                                <source
                                                    src="<?php
                                                        echo htmlspecialchars(
                                                            $attachment_path,
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        );
                                                    ?>"
                                                >

                                                Your browser does not
                                                support video playback.

                                            </video>


                                        <?php else: ?>

                                            <a
                                                href="<?php
                                                    echo htmlspecialchars(
                                                        $attachment_path,
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    );
                                                ?>"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="file-attachment"
                                            >
                                                📎 Download Attachment
                                            </a>

                                        <?php endif; ?>

                                    <?php endif; ?>


                                    <!-- =================================
                                         MESSAGE TEXT
                                    ================================== -->

                                    <?php if (!empty($msg['message'])): ?>

                                        <div>

                                            <?php

                                            echo nl2br(
                                                htmlspecialchars(
                                                    $msg['message'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                )
                                            );

                                            ?>

                                        </div>

                                    <?php endif; ?>


                                    <!-- =================================
                                         MESSAGE TIME
                                    ================================== -->

                                    <span class="msg-time">

                                        <?php

                                        echo date(
                                            'h:i A',
                                            strtotime($msg['created_at'])
                                        );

                                        ?>

                                    </span>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <div
                            class="no-chat-selected"
                            style="margin: auto;"
                        >

                            <p>
                                No messages yet.
                                Start your conversation!
                            </p>

                        </div>

                    <?php endif; ?>

                </div>


                <!-- =================================================
                     MESSAGE INPUT
                ================================================== -->

                <div class="messenger-footer">

                    <form
                        action=""
                        method="POST"
                        enctype="multipart/form-data"
                        class="chat-form"
                        id="chatForm"
                    >

                        <!-- Hidden File Input -->

                        <input
                            type="file"
                            name="chat_file"
                            id="chatFile"
                            style="display: none;"
                            accept="
                                image/*,
                                video/*,
                                .pdf,
                                .doc,
                                .docx,
                                .xls,
                                .xlsx,
                                .ppt,
                                .pptx,
                                .txt,
                                .zip
                            "
                        >


                        <!-- Attachment Button -->

                        <button
                            type="button"
                            class="attach-btn"
                            title="Attach File"
                            id="attachButton"
                        >

                            <svg viewBox="0 0 24 24">

                                <path
                                    d="
                                        M16.5 6v11.5
                                        c0 2.21-1.79 4-4 4
                                        s-4-1.79-4-4V5
                                        c0-1.38 1.12-2.5 2.5-2.5
                                        s2.5 1.12 2.5 2.5v10.5
                                        c0 .55-.45 1-1 1
                                        s-1-.45-1-1V6H10v9.5
                                        c0 1.38 1.12 2.5 2.5 2.5
                                        s2.5-1.12 2.5-2.5V5
                                        c0-2.21-1.79-4-4-4
                                        S7 2.79 7 5v12.5
                                        c0 3.04 2.46 5.5 5.5 5.5
                                        s5.5-2.46 5.5-5.5V6h-1.5z
                                    "
                                />

                            </svg>

                        </button>


                        <!-- Message Input -->

                        <input
                            type="text"
                            name="message"
                            class="chat-input"
                            placeholder="Type a message..."
                            autocomplete="off"
                            maxlength="5000"
                        >


                        <!-- Send Button -->

                        <button
                            type="submit"
                            class="send-btn"
                            title="Send"
                        >

                            <svg viewBox="0 0 24 24">

                                <path
                                    d="
                                        M2.01 21L23 12
                                        2.01 3 2 10l15 2
                                        -15 2z
                                    "
                                />

                            </svg>

                        </button>

                    </form>

                </div>


            <?php else: ?>

                <!-- =================================================
                     NO STUDENT SELECTED
                ================================================== -->

                <div class="no-chat-selected">

                    <p>
                        Select a student from the sidebar
                        to start conversation.
                    </p>

                </div>

            <?php endif; ?>

        </div>

    </div>


    <!-- =========================================================
         JAVASCRIPT
    ========================================================== -->

    <script>

        // =====================================================
        // THEME
        // =====================================================

        function applyTheme(theme) {

            if (theme === 'dark') {

                document.documentElement.setAttribute(
                    'data-theme',
                    'dark'
                );

            } else {

                document.documentElement.setAttribute(
                    'data-theme',
                    'light'
                );

            }

        }


        // =====================================================
        // LOAD CHAT THEME
        // =====================================================

        document.addEventListener(
            'DOMContentLoaded',
            function () {

                let savedTheme =
                    localStorage.getItem('notesphere_theme')
                    ||
                    localStorage.getItem('theme');

                if (!savedTheme) {

                    savedTheme =
                        window.matchMedia(
                            '(prefers-color-scheme: light)'
                        ).matches
                            ? 'light'
                            : 'dark';

                }

                applyTheme(savedTheme);


                // =================================================
                // AUTO SCROLL TO BOTTOM
                // =================================================

                const chatScroll =
                    document.getElementById('chatScroll');

                if (chatScroll) {

                    chatScroll.scrollTop =
                        chatScroll.scrollHeight;

                }


                // =================================================
                // ATTACHMENT BUTTON
                // =================================================

                const attachButton =
                    document.getElementById('attachButton');

                const chatFile =
                    document.getElementById('chatFile');

                if (
                    attachButton &&
                    chatFile
                ) {

                    attachButton.addEventListener(
                        'click',
                        function () {

                            chatFile.click();

                        }
                    );


                    // Automatically submit after selecting file

                    chatFile.addEventListener(
                        'change',
                        function () {

                            if (chatFile.files.length > 0) {

                                const form =
                                    document.getElementById(
                                        'chatForm'
                                    );

                                if (form) {

                                    form.submit();

                                }

                            }

                        }
                    );

                }


                // =================================================
                // INPUT FOCUS
                // =================================================

                const chatInput =
                    document.querySelector(
                        '.chat-input'
                    );

                if (chatInput) {

                    chatInput.addEventListener(
                        'focus',
                        function () {

                            setTimeout(
                                function () {

                                    if (chatScroll) {

                                        chatScroll.scrollTop =
                                            chatScroll.scrollHeight;

                                    }

                                },
                                300
                            );

                        }
                    );

                }

            }
        );


        // =====================================================
        // THEME CHANGES FROM OTHER NOTESPHERE PAGES
        // =====================================================

        window.addEventListener(
            'storage',
            function (event) {

                if (
                    event.key === 'notesphere_theme' ||
                    event.key === 'theme'
                ) {

                    if (event.newValue) {

                        applyTheme(event.newValue);

                    }

                }

            }
        );

    </script>

</body>

</html>

