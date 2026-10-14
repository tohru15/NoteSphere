
<?php

// =========================================================
// NoteSphere - Professor Announcements / Student Class Stream
// Database-compatible version
// =========================================================

session_start();

date_default_timezone_set('Asia/Manila');

// =========================================================
// STUDENT SECURITY CHECK
// =========================================================

if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'student'
) {
    header("Location: ../auth/login.php");
    exit();
}

// =========================================================
// DATABASE CONNECTION
// =========================================================

$host = 'localhost';
$dbname = '1notesphere_db';
$username = 'root';
$password = '';

try {

    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );

    $pdo->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

    $pdo->setAttribute(
        PDO::ATTR_DEFAULT_FETCH_MODE,
        PDO::FETCH_ASSOC
    );

} catch (PDOException $e) {

    die(
        "Database Connection Error: " .
        htmlspecialchars($e->getMessage())
    );
}

// =========================================================
// CURRENT STUDENT / PROFESSOR
// =========================================================

$student_id = (int) $_SESSION['user_id'];

$prof_id = isset($_GET['prof_id'])
    ? (int) $_GET['prof_id']
    : 2;

// =========================================================
// HELPER: MARK ANNOUNCEMENT AS READ
// =========================================================

function markAnnouncementAsRead(
    PDO $pdo,
    int $student_id,
    int $ann_id
): void {

    // Check if already marked as read
    $check_exist = $pdo->prepare("
        SELECT id
        FROM announcement_reads
        WHERE student_id = ?
          AND announcement_id = ?
        LIMIT 1
    ");

    $check_exist->execute([
        $student_id,
        $ann_id
    ]);

    // Insert only if not yet read
    if (!$check_exist->fetch()) {

        $insert_read = $pdo->prepare("
            INSERT INTO announcement_reads
            (
                student_id,
                announcement_id
            )
            VALUES (?, ?)
        ");

        $insert_read->execute([
            $student_id,
            $ann_id
        ]);
    }
}

// =========================================================
// HANDLE TASK SUBMISSION
// =========================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['submit_task'])
) {

    $ann_id = isset($_POST['announcement_id'])
        ? (int) $_POST['announcement_id']
        : 0;

    // The database column is submission_text
    $student_note = trim(
        $_POST['student_note'] ?? ''
    );

    if ($ann_id <= 0) {

        header(
            "Location: professor_announcements.php?prof_id=" .
            $prof_id
        );

        exit();
    }

    // =====================================================
    // CHECK DEADLINE
    // =====================================================

    $stmt_check_dl = $pdo->prepare("
        SELECT deadline
        FROM announcements
        WHERE id = ?
        LIMIT 1
    ");

    $stmt_check_dl->execute([
        $ann_id
    ]);

    $dl_data = $stmt_check_dl->fetch();

    if (
        $dl_data &&
        !empty($dl_data['deadline'])
    ) {

        $deadline_timestamp = strtotime(
            $dl_data['deadline']
        );

        if (
            $deadline_timestamp !== false &&
            time() > $deadline_timestamp
        ) {

            header(
                "Location: professor_announcements.php?prof_id=" .
                $prof_id .
                "&error=deadline_passed"
            );

            exit();
        }
    }

    // =====================================================
    // FILE UPLOAD
    // =====================================================

    $file_path = null;

    if (
        isset($_FILES['submission_file']) &&
        $_FILES['submission_file']['error'] === UPLOAD_ERR_OK
    ) {

        $allowed_extensions = [
            'pdf',
            'docx',
            'doc',
            'zip',
            'rar',
            'jpg',
            'jpeg',
            'png',
            'txt'
        ];

        $file_extension = strtolower(
            pathinfo(
                $_FILES['submission_file']['name'],
                PATHINFO_EXTENSION
            )
        );

        if (
            in_array(
                $file_extension,
                $allowed_extensions,
                true
            )
        ) {

            $upload_dir = '../uploads/submissions/';

            if (!is_dir($upload_dir)) {

                mkdir(
                    $upload_dir,
                    0777,
                    true
                );
            }

            $file_name =
                'sub_' .
                $ann_id .
                '_' .
                $student_id .
                '_' .
                time() .
                '.' .
                $file_extension;

            $target_file =
                $upload_dir . $file_name;

            if (
                move_uploaded_file(
                    $_FILES['submission_file']['tmp_name'],
                    $target_file
                )
            ) {

                $file_path = $target_file;
            }
        }
    }

    // =====================================================
    // CHECK EXISTING SUBMISSION
    // =====================================================

    $check_stmt = $pdo->prepare("
        SELECT
            id,
            file_path,
            submission_text
        FROM announcement_submissions
        WHERE announcement_id = ?
          AND student_id = ?
        LIMIT 1
    ");

    $check_stmt->execute([
        $ann_id,
        $student_id
    ]);

    $existing = $check_stmt->fetch();

    // =====================================================
    // UPDATE EXISTING SUBMISSION
    // =====================================================

    if ($existing) {

        // Keep old file if no new file was uploaded
        if ($file_path === null) {

            $file_path =
                $existing['file_path'];
        }

        $update_stmt = $pdo->prepare("
            UPDATE announcement_submissions
            SET
                submission_text = ?,
                file_path = ?,
                submitted_at = NOW()
            WHERE id = ?
        ");

        $update_stmt->execute([
            $student_note,
            $file_path,
            $existing['id']
        ]);

    }

    // =====================================================
    // INSERT NEW SUBMISSION
    // =====================================================

    else {

        $insert_stmt = $pdo->prepare("
            INSERT INTO announcement_submissions
            (
                announcement_id,
                student_id,
                submission_text,
                file_path,
                submitted_at
            )
            VALUES (?, ?, ?, ?, NOW())
        ");

        $insert_stmt->execute([
            $ann_id,
            $student_id,
            $student_note,
            $file_path
        ]);
    }

    // =====================================================
    // MARK ANNOUNCEMENT AS READ AFTER SUBMISSION
    // =====================================================

    markAnnouncementAsRead(
        $pdo,
        $student_id,
        $ann_id
    );

    // =====================================================
    // REDIRECT AFTER SUBMISSION
    // =====================================================

    header(
        "Location: professor_announcements.php?prof_id=" .
        $prof_id .
        "&submitted=1"
    );

    exit();
}

// =========================================================
// HANDLE MARK AS READ
// =========================================================

if (isset($_GET['mark_done'])) {

    $ann_id = (int) $_GET['mark_done'];

    if ($ann_id > 0) {

        markAnnouncementAsRead(
            $pdo,
            $student_id,
            $ann_id
        );
    }

    header(
        "Location: professor_announcements.php?prof_id=" .
        $prof_id
    );

    exit();
}

// =========================================================
// FETCH PROFESSOR NAME
// =========================================================
// IMPORTANT:
// users table uses user_id, NOT id.
// =========================================================

$stmt_prof = $pdo->prepare("
    SELECT full_name
    FROM users
    WHERE user_id = ?
    LIMIT 1
");

$stmt_prof->execute([
    $prof_id
]);

$prof_data = $stmt_prof->fetch(
    PDO::FETCH_ASSOC
);

$prof_name = $prof_data['full_name']
    ?? 'Professor';

// =========================================================
// FETCH ACTIVE SEMESTER / ACADEMIC YEAR
// =========================================================

$stmt_settings = $pdo->query("
    SELECT *
    FROM system_settings
    LIMIT 1
");

$current_settings = $stmt_settings->fetch();

$current_settings = $current_settings ?: [];

$active_semester =
    $current_settings['current_semester']
    ?? $current_settings['setting_value']
    ?? '1st Semester';

$active_year =
    $current_settings['current_academic_year']
    ?? '2026-2027';

// =========================================================
// FETCH UNREAD ANNOUNCEMENTS
// =========================================================
//
// announcement_submissions columns:
//
// id
// announcement_id
// student_id
// submission_text
// file_path
// grade
// feedback
// submitted_at
//
// announcement_reads handles the Read state.
// =========================================================

$stmt_ann = $pdo->prepare("
    SELECT
        a.*,

        s.submission_text,
        s.file_path,
        s.grade,
        s.feedback,
        s.submitted_at AS sub_date,

        CASE
            WHEN s.id IS NOT NULL THEN 1
            ELSE 0
        END AS has_submission

    FROM announcements a

    LEFT JOIN announcement_submissions s
        ON a.id = s.announcement_id
        AND s.student_id = ?

    WHERE a.professor_id = ?

      AND NOT EXISTS (
          SELECT 1
          FROM announcement_reads ar
          WHERE ar.announcement_id = a.id
            AND ar.student_id = ?
      )

      AND (
          a.status = 'active'
          OR a.status IS NULL
      )

    ORDER BY a.created_at DESC
");

$stmt_ann->execute([
    $student_id,
    $prof_id,
    $student_id
]);

$announcements = $stmt_ann->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Prof. <?= htmlspecialchars($prof_name) ?>
        Stream | NoteSphere
    </title>

    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <style>

        /* =====================================================
           THEME VARIABLES
        ===================================================== */

        :root {

            --neon: #39FF14;
            --neon-glow: rgba(57, 255, 20, 0.12);

            --bg: #050505;
            --card-bg: #0c0c0c;

            --text: #f8fafc;
            --sub-text: #94a3b8;

            --border: #1f293d;
        }

        body.light-mode {

            --bg: #f4f6f9;
            --card-bg: #ffffff;

            --text: #0f172a;
            --sub-text: #64748b;

            --border: #e2e8f0;

            --neon: #10b981;
            --neon-glow: rgba(16, 185, 129, 0.15);
        }

        /* =====================================================
           RESET
        ===================================================== */

        * {

            margin: 0;
            padding: 0;

            box-sizing: border-box;

            font-family:
                'Plus Jakarta Sans',
                sans-serif;
        }

        /* =====================================================
           BODY
        ===================================================== */

        body {

            background: var(--bg);

            color: var(--text);

            display: flex;

            justify-content: center;

            min-height: 100vh;

            overflow-x: hidden;

            transition:
                background 0.3s ease,
                color 0.3s ease;
        }

        body::before {

            content: '';

            position: fixed;

            top: 0;
            left: 0;

            width: 100vw;
            height: 100vh;

            z-index: -1;

            background:
                linear-gradient(
                    rgba(11, 11, 11, 0.85),
                    rgba(11, 11, 11, 0.92)
                ),
                url('../assets/registerbg.png')
                no-repeat
                center
                center;

            background-size: cover;

            pointer-events: none;
        }

        body.light-mode::before {

            background:
                linear-gradient(
                    rgba(244, 246, 249, 0.90),
                    rgba(244, 246, 249, 0.94)
                ),
                url('../assets/registerbg.png')
                no-repeat
                center
                center;

            background-size: cover;
        }

        /* =====================================================
           MAIN CONTAINER
        ===================================================== */

        .main-container {

            width: 100%;

            max-width: 900px;

            padding: 40px 20px;
        }

        /* =====================================================
           TOP ACTION BAR
        ===================================================== */

        .top-action-bar {

            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-bottom: 20px;

            flex-wrap: wrap;

            gap: 10px;
        }

        .header-actions {

            display: flex;

            gap: 10px;
        }

        /* =====================================================
           ICON BUTTON
        ===================================================== */

        .icon-btn {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            width: 40px;
            height: 40px;

            background: var(--card-bg);

            border:
                1px solid
                var(--border);

            color: var(--text);

            border-radius: 12px;

            font-size: 1rem;

            text-decoration: none;

            transition: 0.3s;
        }

        .icon-btn:hover {

            border-color: var(--neon);

            color: var(--neon);

            background: var(--neon-glow);

            box-shadow:
                0 0 10px
                var(--neon-glow);
        }

        /* =====================================================
           CLOSE BUTTON
        ===================================================== */

        .close-btn:hover {

            border-color: #ff4d4d !important;

            color: #ff4d4d !important;

            background:
                rgba(
                    255,
                    77,
                    77,
                    0.08
                ) !important;

            box-shadow: none !important;
        }

        /* =====================================================
           CLASS BANNER
        ===================================================== */

        .class-banner {

            background: var(--card-bg);

            border:
                1px solid
                var(--border);

            border-radius: 16px;

            padding: 30px;

            color: var(--text);

            margin-bottom: 30px;

            position: relative;

            box-shadow:
                0 4px 20px
                rgba(0, 0, 0, 0.05);
        }

        .class-banner h1 {

            font-size: 1.8rem;

            font-weight: 800;

            color: var(--neon);

            margin-bottom: 6px;
        }

        .class-banner p {

            color: var(--sub-text);

            font-size: 0.9rem;
        }

        .class-banner .sem-tag {

            position: absolute;

            top: 20px;
            right: 20px;

            background: var(--neon-glow);

            color: var(--neon);

            border:
                1px solid
                var(--neon);

            padding: 6px 14px;

            border-radius: 20px;

            font-size: 0.8rem;

            font-weight: 700;
        }

        /* =====================================================
           ANNOUNCEMENT CARD
        ===================================================== */

        .g-card {

            background: var(--card-bg);

            border:
                1px solid
                var(--border);

            border-radius: 16px;

            padding: 24px;

            margin-bottom: 20px;

            transition:
                border-color 0.3s ease;
        }

        .g-card:hover {

            border-color: var(--neon);
        }

        .g-card-header {

            display: flex;

            justify-content: space-between;

            align-items: flex-start;

            margin-bottom: 16px;

            flex-wrap: wrap;

            gap: 10px;
        }

        .g-icon-circle {

            width: 44px;
            height: 44px;

            border-radius: 50%;

            background: var(--neon-glow);

            color: var(--neon);

            border:
                1px solid
                var(--neon);

            display: flex;

            align-items: center;

            justify-content: center;

            font-weight: 800;

            flex-shrink: 0;
        }

        .ann-content {

            font-size: 0.95rem;

            line-height: 1.7;

            margin-bottom: 16px;

            white-space: pre-wrap;

            color: var(--text);

            word-break: break-word;
        }

        /* =====================================================
           BUTTONS
        ===================================================== */

        .outline-btn {

            background: transparent;

            border:
                1px solid
                var(--border);

            color: var(--text);

            padding: 8px 14px;

            border-radius: 8px;

            font-weight: 700;

            cursor: pointer;

            text-decoration: none;

            display: inline-block;

            transition: 0.3s;

            font-size: 0.85rem;
        }

        .outline-btn:hover {

            border-color: var(--neon);

            color: var(--neon);

            background: var(--neon-glow);
        }

        .g-btn {

            background: var(--neon);

            color: #050505;

            border: none;

            padding: 10px 20px;

            border-radius: 8px;

            font-weight: 800;

            cursor: pointer;

            transition: 0.3s;

            font-size: 0.9rem;
        }

        .g-btn:hover {

            opacity: 0.85;
        }

        /* =====================================================
           SUBMISSION FORM
        ===================================================== */

        .submission-collapsible {

            display: none;

            margin-top: 15px;

            border-top:
                1px solid
                var(--border);

            padding-top: 15px;
        }

        .submission-collapsible.active {

            display: block;
        }

        .sub-form input,
        .sub-form textarea {

            width: 100%;

            background: var(--bg);

            border:
                1px solid
                var(--border);

            color: var(--text);

            padding: 10px;

            border-radius: 8px;

            margin-top: 5px;

            outline: none;

            font-size: 0.9rem;
        }

        .sub-form textarea {

            min-height: 100px;

            resize: vertical;
        }

        .sub-form input:focus,
        .sub-form textarea:focus {

            border-color: var(--neon);
        }

        /* =====================================================
           STATUS BADGES
        ===================================================== */

        .status-badge {

            font-size: 0.75rem;

            padding: 4px 10px;

            border-radius: 6px;

            font-weight: 700;

            border:
                1px solid
                var(--neon);

            color: var(--neon);

            background:
                var(--neon-glow);
        }

        /* =====================================================
           ALERTS
        ===================================================== */

        .alert-box {

            margin-bottom: 20px;

            padding: 12px 16px;

            border-radius: 10px;

            background:
                rgba(239, 68, 68, 0.10);

            border:
                1px solid
                rgba(239, 68, 68, 0.35);

            color: #ef4444;

            font-size: 0.85rem;

            font-weight: 700;
        }

        .success-box {

            margin-bottom: 20px;

            padding: 12px 16px;

            border-radius: 10px;

            background:
                var(--neon-glow);

            border:
                1px solid
                var(--neon);

            color: var(--neon);

            font-size: 0.85rem;

            font-weight: 700;
        }

        /* =====================================================
           MOBILE
        ===================================================== */

        @media (max-width: 600px) {

            .main-container {

                padding: 20px 12px;
            }

            .class-banner {

                padding: 20px 16px;
            }

            .class-banner h1 {

                font-size: 1.3rem;
            }

            .class-banner .sem-tag {

                position: static;

                display: inline-block;

                margin-top: 10px;
            }

            .top-action-bar {

                flex-direction: row;

                justify-content: space-between;

                align-items: center;
            }

            .g-card {

                padding: 16px;
            }

            .g-card-header {

                flex-direction: column;

                align-items: flex-start;
            }
        }

    </style>

</head>

<body>

<div class="main-container">

    <!-- =====================================================
         TOP ACTION BAR
    ====================================================== -->

    <div class="top-action-bar">

        <a
            href="student_home.php"
            class="icon-btn close-btn"
            title="Back to Dashboard"
        >
            ✕
        </a>

        <div class="header-actions">

            <a
                href="history.php?prof_id=<?= $prof_id ?>"
                class="icon-btn"
                title="History & Grades"
            >
                📊
            </a>

            <a
                href="messages.php?prof_id=<?= $prof_id ?>"
                class="icon-btn"
                title="Consultation Chat"
            >
                💬
            </a>

        </div>

    </div>

    <!-- =====================================================
         CLASS BANNER
    ====================================================== -->

    <div class="class-banner">

        <div class="sem-tag">

            <?= htmlspecialchars(
                $active_semester .
                ' | ' .
                $active_year
            ) ?>

        </div>

        <h1>

            Prof.
            <?= htmlspecialchars($prof_name) ?>

        </h1>

        <p>
            Class Stream & Coursework Updates
        </p>

    </div>

    <!-- =====================================================
         ALERTS
    ====================================================== -->

    <?php if (
        isset($_GET['error']) &&
        $_GET['error'] === 'deadline_passed'
    ): ?>

        <div class="alert-box">

            This task can no longer be submitted
            because the deadline has passed.

        </div>

    <?php endif; ?>


    <?php if (
        isset($_GET['submitted'])
    ): ?>

        <div class="success-box">

            Your work has been submitted successfully.

        </div>

    <?php endif; ?>


    <!-- =====================================================
         ANNOUNCEMENTS
    ====================================================== -->

    <?php if (empty($announcements)): ?>

        <div
            style="
                text-align: center;
                padding: 40px;
                background: var(--card-bg);
                border: 1px solid var(--border);
                border-radius: 16px;
            "
        >

            <p
                style="
                    font-weight: 700;
                    color: var(--text);
                "
            >

                No new active announcements.

            </p>

            <p
                style="
                    margin-top: 8px;
                    font-size: 0.85rem;
                    color: var(--sub-text);
                "
            >

                Read items and submissions can be
                viewed in your History & Grades.

            </p>

        </div>

    <?php else: ?>

        <div class="feed-list">

            <?php foreach (
                $announcements as $ann
            ): ?>

                <?php

                $has_deadline =
                    !empty($ann['deadline']);

                $is_expired =
                    $has_deadline &&
                    strtotime(
                        $ann['deadline']
                    ) < time();

                $has_submission =
                    !empty(
                        $ann['has_submission']
                    );

                ?>

                <div class="g-card">

                    <!-- =================================================
                         CARD HEADER
                    ================================================== -->

                    <div class="g-card-header">

                        <div
                            style="
                                display: flex;
                                gap: 14px;
                                align-items: center;
                            "
                        >

                            <div class="g-icon-circle">

                                <?= strtoupper(
                                    substr(
                                        $prof_name,
                                        0,
                                        1
                                    )
                                ) ?>

                            </div>

                            <div>

                                <h3
                                    style="
                                        font-size: 1rem;
                                        color: var(--text);
                                    "
                                >

                                    <?= htmlspecialchars(
                                        $ann['title']
                                    ) ?>

                                </h3>

                                <span
                                    style="
                                        font-size: 0.8rem;
                                        color: var(--sub-text);
                                    "
                                >

                                    <?= date(
                                        'M d, Y - h:i A',
                                        strtotime(
                                            $ann['created_at']
                                        )
                                    ) ?>

                                </span>

                            </div>

                        </div>


                        <!-- STATUS -->

                        <span class="status-badge">

                            <?php if (
                                $has_submission
                            ): ?>

                                Submitted

                            <?php else: ?>

                                New

                            <?php endif; ?>

                        </span>

                    </div>


                    <!-- =================================================
                         ANNOUNCEMENT MESSAGE
                    ================================================== -->

                    <div class="ann-content">

                        <?= nl2br(
                            htmlspecialchars(
                                $ann['message']
                            )
                        ) ?>

                    </div>


                    <!-- =================================================
                         OPTIONAL ATTACHMENT
                    ================================================== -->

                    <?php if (
                        !empty($ann['attachment'])
                    ): ?>

                        <div
                            style="
                                margin-bottom: 16px;
                            "
                        >

                            <a
                                href="../<?= htmlspecialchars(
                                    $ann['attachment']
                                ) ?>"
                                target="_blank"
                                class="outline-btn"
                            >

                                📎 View Attachment

                            </a>

                        </div>

                    <?php endif; ?>


                    <!-- =================================================
                         DEADLINE
                    ================================================== -->

                    <?php if (
                        $has_deadline
                    ): ?>

                        <div
                            style="
                                border-top: 1px solid var(--border);
                                padding-top: 14px;
                                display: flex;
                                justify-content: space-between;
                                align-items: center;
                                flex-wrap: wrap;
                                gap: 10px;
                            "
                        >

                            <span
                                style="
                                    font-size: 0.85rem;
                                    color: #ff5555;
                                    font-weight: 700;
                                "
                            >

                                ⏳ Due:

                                <?= date(
                                    'M d, Y - h:i A',
                                    strtotime(
                                        $ann['deadline']
                                    )
                                ) ?>

                            </span>


                            <div
                                style="
                                    display: flex;
                                    gap: 8px;
                                    align-items: center;
                                    flex-wrap: wrap;
                                "
                            >

                                <!-- MARK AS READ -->

                                <a
                                    href="professor_announcements.php?prof_id=<?= $prof_id ?>&mark_done=<?= (int) $ann['id'] ?>"
                                    class="outline-btn"
                                    style="
                                        color: var(--neon);
                                        border-color: var(--neon);
                                    "
                                >

                                    Mark as Read

                                </a>


                                <!-- ADD WORK -->

                                <?php if (
                                    !$is_expired &&
                                    !$has_submission
                                ): ?>

                                    <button
                                        type="button"
                                        class="outline-btn"
                                        onclick="toggleAddWork(<?= (int) $ann['id'] ?>)"
                                    >

                                        Add Work

                                    </button>

                                <?php endif; ?>

                            </div>

                        </div>


                        <!-- =================================================
                             SUBMISSION BOX
                        ================================================== -->

                        <?php if (
                            !$is_expired &&
                            !$has_submission
                        ): ?>

                            <div
                                class="submission-collapsible"
                                id="workBox-<?= (int) $ann['id'] ?>"
                            >

                                <form
                                    method="POST"
                                    enctype="multipart/form-data"
                                    class="sub-form"
                                >

                                    <input
                                        type="hidden"
                                        name="announcement_id"
                                        value="<?= (int) $ann['id'] ?>"
                                    >


                                    <label
                                        style="
                                            font-size: 0.8rem;
                                            font-weight: 700;
                                        "
                                    >

                                        Upload File:

                                    </label>


                                    <input
                                        type="file"
                                        name="submission_file"
                                        accept=".pdf,.doc,.docx,.zip,.rar,.jpg,.jpeg,.png,.txt"
                                    >


                                    <label
                                        style="
                                            font-size: 0.8rem;
                                            font-weight: 700;
                                            margin-top: 10px;
                                            display: block;
                                        "
                                    >

                                        Private Comment:

                                    </label>


                                    <textarea
                                        name="student_note"
                                        placeholder="Write your comment or submission details..."
                                    ></textarea>


                                    <div
                                        style="
                                            display: flex;
                                            justify-content: flex-end;
                                            gap: 10px;
                                            margin-top: 10px;
                                        "
                                    >

                                        <button
                                            type="button"
                                            class="outline-btn"
                                            onclick="toggleAddWork(<?= (int) $ann['id'] ?>)"
                                        >

                                            Cancel

                                        </button>


                                        <button
                                            type="submit"
                                            name="submit_task"
                                            class="g-btn"
                                        >

                                            Turn In Work

                                        </button>

                                    </div>

                                </form>

                            </div>

                        <?php endif; ?>


                    <?php else: ?>

                        <!-- =================================================
                             NO DEADLINE
                        ================================================== -->

                        <div
                            style="
                                border-top: 1px solid var(--border);
                                padding-top: 12px;
                                display: flex;
                                justify-content: flex-end;
                                gap: 8px;
                            "
                        >

                            <a
                                href="professor_announcements.php?prof_id=<?= $prof_id ?>&mark_done=<?= (int) $ann['id'] ?>"
                                class="outline-btn"
                                style="
                                    color: var(--neon);
                                    border-color: var(--neon);
                                "
                            >

                                Mark as Read

                            </a>

                        </div>

                    <?php endif; ?>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</div>


<!-- =========================================================
     JAVASCRIPT
========================================================= -->

<script>

    // =========================================================
    // THEME
    // =========================================================

    function applyTheme(theme) {

        if (theme === 'light') {

            document.body.classList.add(
                'light-mode'
            );

        } else {

            document.body.classList.remove(
                'light-mode'
            );
        }
    }


    // =========================================================
    // LOAD SAVED THEME
    // =========================================================

    document.addEventListener(
        "DOMContentLoaded",
        () => {

            let savedTheme =
                localStorage.getItem(
                    'notesphere_theme'
                ) ||
                localStorage.getItem(
                    'theme'
                );


            if (!savedTheme) {

                if (
                    window.matchMedia &&
                    window.matchMedia(
                        '(prefers-color-scheme: light)'
                    ).matches
                ) {

                    savedTheme = 'light';

                } else {

                    savedTheme = 'dark';
                }
            }


            applyTheme(savedTheme);

        }
    );


    // =========================================================
    // LISTEN FOR THEME CHANGES
    // =========================================================

    window.addEventListener(
        'storage',
        (event) => {

            if (
                event.key ===
                    'notesphere_theme' ||
                event.key === 'theme'
            ) {

                if (event.newValue) {

                    applyTheme(
                        event.newValue
                    );
                }
            }
        }
    );


    // =========================================================
    // TOGGLE ADD WORK
    // =========================================================

    function toggleAddWork(annId) {

        const box =
            document.getElementById(
                'workBox-' + annId
            );

        if (box) {

            box.classList.toggle(
                'active'
            );
        }
    }

</script>

</body>

</html>
