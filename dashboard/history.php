<?php

// =========================================================
// NoteSphere - Student History
// =========================================================

session_start();


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
// DATABASE
// =========================================================

require_once __DIR__ . '/../includes/db_config.php';

/** @var PDO $conn */

$student_id = (int) $_SESSION['user_id'];


// =========================================================
// FETCH HISTORY
//
// Includes:
// 1. Announcements marked as read
// 2. Announcements with submitted work
// 3. Grade / professor feedback
// =========================================================

$history = [];

$database_error = null;

try {

    $stmt = $conn->prepare("
        SELECT

            a.id AS announcement_id,

            a.professor_id,

            a.title,

            a.subject,

            a.year_level,

            a.section,

            a.message,

            a.attachment,

            a.category,

            a.meet_link,

            a.quiz_link,

            a.deadline,

            a.semester,

            a.academic_year,

            a.status,

            a.created_at,

            u.full_name AS professor_name,

            ar.read_at,

            s.id AS submission_id,

            s.submission_text,

            s.file_path,

            s.grade,

            s.feedback,

            s.submitted_at

        FROM announcements a

        LEFT JOIN users u
            ON u.user_id = a.professor_id

        LEFT JOIN announcement_reads ar
            ON ar.announcement_id = a.id
            AND ar.student_id = ?

        LEFT JOIN announcement_submissions s
            ON s.announcement_id = a.id
            AND s.student_id = ?

        WHERE

            ar.id IS NOT NULL

            OR

            s.id IS NOT NULL

        ORDER BY

            COALESCE(
                s.submitted_at,
                ar.read_at,
                a.created_at
            ) DESC
    ");


    $stmt->execute([
        $student_id,
        $student_id
    ]);


    $history = $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


} catch (PDOException $e) {

    $history = [];

    $database_error =
        "Unable to load your history.";
}


// =========================================================
// COUNTS
// =========================================================

$read_count = 0;

$submitted_count = 0;


foreach ($history as $item) {

    if (
        !empty($item['read_at'])
    ) {

        $read_count++;
    }


    if (
        !empty($item['submission_id'])
    ) {

        $submitted_count++;
    }
}


// =========================================================
// FORMAT FILE NAME
// =========================================================

function getFileName($path)
{
    if (empty($path)) {

        return '';
    }

    return basename($path);
}


// =========================================================
// FORMAT DATE
// =========================================================

function formatHistoryDate($date)
{
    if (empty($date)) {

        return '';
    }

    $timestamp = strtotime($date);

    if ($timestamp === false) {

        return '';
    }

    return date(
        'M d, Y • h:i A',
        $timestamp
    );
}


// =========================================================
// CHECK IF VALUE EXISTS
// =========================================================

function hasValue($value)
{
    return (
        $value !== null &&
        trim((string) $value) !== ''
    );
}


// =========================================================
// GET SUBMISSION STATUS
//
// IMPORTANT:
// Grade OR professor feedback means the
// submission has already been reviewed.
// =========================================================

function getSubmissionStatus($item)
{

    if (
        empty($item['submission_id'])
    ) {

        return 'Read';
    }


    $has_grade =
        hasValue(
            $item['grade'] ?? null
        );


    $has_feedback =
        hasValue(
            $item['feedback'] ?? null
        );


    if (
        $has_grade ||
        $has_feedback
    ) {

        return 'Graded';
    }


    return 'Submitted';
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

    <title>
        Student History | NoteSphere
    </title>


    <!-- =====================================================
         FONT
    ====================================================== -->

    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >


    <!-- =====================================================
         FONT AWESOME
    ====================================================== -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >


    <style>

        /* =====================================================
           THEME
        ====================================================== */

        :root {

            --bg: #050505;

            --card-bg: #0c0c0c;

            --card-hover: #111111;

            --text: #f8fafc;

            --sub-text: #94a3b8;

            --border: #1f293d;

            --input-bg: #090909;

            --neon: #39FF14;

            --neon-soft:
                rgba(57, 255, 20, 0.10);

            --blue:
                #3b82f6;

            --blue-soft:
                rgba(59, 130, 246, 0.10);

            --green:
                #22c55e;

            --green-soft:
                rgba(34, 197, 94, 0.10);

            --orange:
                #f59e0b;

            --orange-soft:
                rgba(245, 158, 11, 0.10);

            --shadow:
                0 20px 50px
                rgba(0, 0, 0, 0.35);
        }


        /* =====================================================
           LIGHT MODE
        ====================================================== */

        body.light-mode {

            --bg: #f4f6f9;

            --card-bg: #ffffff;

            --card-hover: #f8fafc;

            --text: #0f172a;

            --sub-text: #64748b;

            --border: #e2e8f0;

            --input-bg: #f8fafc;

            --neon: #10b981;

            --neon-soft:
                rgba(16, 185, 129, 0.10);

            --shadow:
                0 15px 35px
                rgba(15, 23, 42, 0.08);
        }


        /* =====================================================
           RESET
        ====================================================== */

        * {

            margin: 0;

            padding: 0;

            box-sizing: border-box;
        }


        /* =====================================================
           BODY
        ====================================================== */

        body {

            min-height: 100vh;

            font-family:
                'Plus Jakarta Sans',
                sans-serif;

            color: var(--text);

            background:
                linear-gradient(
                    rgba(5, 5, 5, 0.86),
                    rgba(5, 5, 5, 0.94)
                ),
                url('../assets/registerbg.png');

            background-size: cover;

            background-position: center;

            background-attachment: fixed;

            transition:
                background 0.3s ease,
                color 0.3s ease;
        }


        body.light-mode {

            background:
                linear-gradient(
                    rgba(244, 246, 249, 0.94),
                    rgba(244, 246, 249, 0.97)
                ),
                url('../assets/registerbg.png');

            background-size: cover;

            background-position: center;

            background-attachment: fixed;
        }


        /* =====================================================
           PAGE
        ====================================================== */

        .page {

            width: 100%;

            min-height: 100vh;

            padding:
                24px
                30px
                60px;
        }


        /* =====================================================
           TOP BAR
        ====================================================== */

        .top-bar {

            width: 100%;

            max-width: 1100px;

            margin: 0 auto 25px;

            display: flex;

            align-items: center;

            gap: 14px;
        }


        /* =====================================================
           X BUTTON
        ====================================================== */

        .close-btn {

            width: 46px;

            height: 46px;

            flex-shrink: 0;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 14px;

            border:
                1px solid
                var(--border);

            background:
                var(--card-bg);

            color: var(--text);

            text-decoration: none;

            font-size: 18px;

            box-shadow:
                var(--shadow);

            transition:
                0.2s ease;
        }


        .close-btn:hover {

            color: var(--neon);

            border-color: var(--neon);

            transform:
                translateY(-2px);
        }


        /* =====================================================
           HEADER
        ====================================================== */

        .header {

            flex: 1;

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 15px;
        }


        .header-left {

            display: flex;

            align-items: center;

            gap: 13px;
        }


        .header-icon {

            width: 48px;

            height: 48px;

            flex-shrink: 0;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 15px;

            color: var(--neon);

            background:
                var(--neon-soft);

            border:
                1px solid
                rgba(57, 255, 20, 0.15);

            font-size: 20px;
        }


        body.light-mode
        .header-icon {

            border-color:
                rgba(16, 185, 129, 0.15);
        }


        .header-text h1 {

            font-size: 22px;

            font-weight: 800;

            letter-spacing: -0.5px;
        }


        .header-text p {

            margin-top: 3px;

            color: var(--sub-text);

            font-size: 13px;
        }


        /* =====================================================
           STATS
        ====================================================== */

        .stats {

            display: flex;

            align-items: center;

            gap: 8px;
        }


        .stat {

            min-width: 75px;

            padding:
                9px
                11px;

            text-align: center;

            background:
                var(--card-bg);

            border:
                1px solid
                var(--border);

            border-radius: 12px;
        }


        .stat strong {

            display: block;

            color: var(--neon);

            font-size: 15px;
        }


        .stat span {

            color: var(--sub-text);

            font-size: 9px;

            font-weight: 700;
        }


        /* =====================================================
           MAIN
        ====================================================== */

        .content {

            width: 100%;

            max-width: 1100px;

            margin: 0 auto;
        }


        /* =====================================================
           ERROR
        ====================================================== */

        .error-box {

            margin-bottom: 18px;

            padding: 15px 18px;

            border-radius: 15px;

            color: #fca5a5;

            background:
                rgba(239, 68, 68, 0.10);

            border:
                1px solid
                rgba(239, 68, 68, 0.25);

            font-size: 12px;
        }


        /* =====================================================
           HISTORY CARD
        ====================================================== */

        .history-card {

            margin-bottom: 18px;

            overflow: hidden;

            background:
                var(--card-bg);

            border:
                1px solid
                var(--border);

            border-radius: 22px;

            box-shadow:
                var(--shadow);

            transition:
                0.2s ease;
        }


        .history-card:hover {

            transform:
                translateY(-2px);

            border-color:
                rgba(57, 255, 20, 0.30);
        }


        body.light-mode
        .history-card:hover {

            border-color:
                rgba(16, 185, 129, 0.30);
        }


        /* =====================================================
           CARD HEADER
        ====================================================== */

        .card-header {

            padding:
                20px 22px 16px;

            border-bottom:
                1px solid
                var(--border);
        }


        .card-header-row {

            display: flex;

            align-items: flex-start;

            justify-content: space-between;

            gap: 15px;
        }


        .announcement-title {

            font-size: 17px;

            font-weight: 800;

            line-height: 1.4;

            word-break: break-word;
        }


        .professor {

            margin-top: 7px;

            color: var(--sub-text);

            font-size: 11px;

            display: flex;

            align-items: center;

            gap: 6px;
        }


        .professor i {

            color: var(--neon);
        }


        /* =====================================================
           TYPE BADGE
        ====================================================== */

        .type-badge {

            flex-shrink: 0;

            display: inline-flex;

            align-items: center;

            gap: 6px;

            padding:
                7px
                11px;

            border-radius: 999px;

            font-size: 9px;

            font-weight: 800;

            text-transform: uppercase;

            letter-spacing: 0.4px;
        }


        .type-read {

            color: var(--blue);

            background:
                var(--blue-soft);

            border:
                1px solid
                rgba(59, 130, 246, 0.20);
        }


        .type-submitted {

            color: var(--orange);

            background:
                var(--orange-soft);

            border:
                1px solid
                rgba(245, 158, 11, 0.20);
        }


        .type-graded {

            color: var(--green);

            background:
                var(--green-soft);

            border:
                1px solid
                rgba(34, 197, 94, 0.20);
        }


        /* =====================================================
           META
        ====================================================== */

        .meta {

            display: flex;

            align-items: center;

            flex-wrap: wrap;

            gap: 10px;

            margin-top: 13px;

            color: var(--sub-text);

            font-size: 10px;
        }


        .meta-item {

            display: inline-flex;

            align-items: center;

            gap: 5px;
        }


        .meta-item i {

            color: var(--neon);
        }


        /* =====================================================
           MESSAGE
        ====================================================== */

        .message {

            padding:
                19px 22px;

            color: var(--sub-text);

            font-size: 13px;

            line-height: 1.75;

            white-space: pre-wrap;

            word-break: break-word;
        }


        /* =====================================================
           INFORMATION GRID
        ====================================================== */

        .info-grid {

            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 10px;

            padding:
                0 22px
                18px;
        }


        .info-box {

            padding:
                12px 14px;

            border-radius: 14px;

            background:
                var(--input-bg);

            border:
                1px solid
                var(--border);
        }


        .info-label {

            color: var(--sub-text);

            font-size: 9px;

            font-weight: 700;

            text-transform: uppercase;

            letter-spacing: 0.5px;
        }


        .info-value {

            margin-top: 5px;

            color: var(--text);

            font-size: 11px;

            font-weight: 700;

            word-break: break-word;
        }


        /* =====================================================
           SUBMISSION
        ====================================================== */

        .submission {

            margin:
                0 22px
                20px;

            padding:
                17px;

            border-radius: 16px;

            background:
                var(--neon-soft);

            border:
                1px solid
                rgba(57, 255, 20, 0.15);
        }


        body.light-mode
        .submission {

            border-color:
                rgba(16, 185, 129, 0.15);
        }


        .submission-title {

            display: flex;

            align-items: center;

            gap: 8px;

            color: var(--text);

            font-size: 12px;

            font-weight: 800;

            margin-bottom: 12px;
        }


        .submission-title i {

            color: var(--neon);
        }


        .submission-row {

            display: flex;

            flex-wrap: wrap;

            gap: 10px;
        }


        .submission-item {

            padding:
                9px 11px;

            border-radius: 10px;

            background:
                var(--card-bg);

            border:
                1px solid
                var(--border);

            color: var(--sub-text);

            font-size: 10px;
        }


        .submission-item strong {

            color: var(--text);
        }


        /* =====================================================
           FILE LINK
        ====================================================== */

        .file-link {

            display: inline-flex;

            align-items: center;

            gap: 7px;

            margin-top: 10px;

            padding:
                9px
                12px;

            border-radius: 10px;

            background:
                var(--card-bg);

            border:
                1px solid
                var(--border);

            color: var(--neon);

            text-decoration: none;

            font-size: 10px;

            font-weight: 700;

            transition:
                0.2s ease;
        }


        .file-link:hover {

            border-color:
                var(--neon);

            transform:
                translateY(-1px);
        }


        /* =====================================================
           FEEDBACK
        ====================================================== */

        .feedback {

            margin-top: 10px;

            padding:
                11px 12px;

            border-radius: 10px;

            background:
                var(--card-bg);

            border:
                1px solid
                var(--border);
        }


        .feedback-label {

            color: var(--sub-text);

            font-size: 9px;

            font-weight: 800;

            text-transform: uppercase;

            margin-bottom: 5px;
        }


        .feedback-text {

            color: var(--text);

            font-size: 11px;

            line-height: 1.6;

            white-space: pre-wrap;
        }


        /* =====================================================
           ATTACHMENT
        ====================================================== */

        .announcement-attachment {

            margin:
                0 22px
                20px;
        }


        .attachment-link {

            display: inline-flex;

            align-items: center;

            gap: 8px;

            padding:
                10px 13px;

            border-radius: 11px;

            background:
                var(--input-bg);

            border:
                1px solid
                var(--border);

            color: var(--text);

            text-decoration: none;

            font-size: 10px;

            font-weight: 700;

            transition:
                0.2s ease;
        }


        .attachment-link i {

            color: var(--neon);
        }


        .attachment-link:hover {

            border-color:
                var(--neon);
        }


        /* =====================================================
           EMPTY STATE
        ====================================================== */

        .empty-state {

            padding:
                70px 25px;

            text-align: center;

            background:
                var(--card-bg);

            border:
                1px solid
                var(--border);

            border-radius: 24px;

            box-shadow:
                var(--shadow);
        }


        .empty-icon {

            width: 75px;

            height: 75px;

            margin:
                0 auto 20px;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 22px;

            color: var(--neon);

            background:
                var(--neon-soft);

            font-size: 30px;
        }


        .empty-state h2 {

            font-size: 20px;

            margin-bottom: 8px;
        }


        .empty-state p {

            max-width: 470px;

            margin: 0 auto;

            color: var(--sub-text);

            font-size: 13px;

            line-height: 1.7;
        }


        /* =====================================================
           FOOTER
        ====================================================== */

        .footer {

            margin-top: 25px;

            text-align: center;

            color: var(--sub-text);

            font-size: 10px;
        }


        /* =====================================================
           MOBILE
        ====================================================== */

        @media (max-width: 700px) {

            .page {

                padding:
                    15px
                    14px
                    40px;
            }


            .top-bar {

                align-items:
                    flex-start;

                margin-bottom:
                    18px;
            }


            .close-btn {

                width: 42px;

                height: 42px;

                border-radius: 12px;
            }


            .header {

                align-items:
                    flex-start;

                flex-direction:
                    column;

                gap: 9px;
            }


            .header-left {

                gap: 9px;
            }


            .header-icon {

                width: 42px;

                height: 42px;

                border-radius: 13px;

                font-size: 17px;
            }


            .header-text h1 {

                font-size: 18px;
            }


            .header-text p {

                font-size: 10px;
            }


            .stats {

                width: 100%;
            }


            .stat {

                flex: 1;

                min-width: 0;
            }


            .history-card {

                border-radius: 18px;

                margin-bottom:
                    14px;
            }


            .card-header {

                padding:
                    16px
                    16px
                    13px;
            }


            .card-header-row {

                flex-direction:
                    column;

                gap: 10px;
            }


            .announcement-title {

                font-size: 15px;
            }


            .type-badge {

                align-self:
                    flex-start;
            }


            .professor {

                font-size: 10px;
            }


            .meta {

                font-size: 9px;

                gap: 7px;
            }


            .message {

                padding:
                    15px 16px;

                font-size: 12px;
            }


            .info-grid {

                grid-template-columns:
                    1fr;

                padding:
                    0 16px
                    15px;
            }


            .announcement-attachment {

                margin:
                    0 16px
                    15px;
            }


            .submission {

                margin:
                    0 16px
                    16px;

                padding:
                    14px;
            }


            .submission-row {

                flex-direction:
                    column;
            }


            .submission-item {

                width: 100%;
            }


            .empty-state {

                padding:
                    55px 18px;

                border-radius:
                    20px;
            }


            .empty-icon {

                width: 65px;

                height: 65px;

                font-size: 25px;
            }


            .empty-state h2 {

                font-size: 17px;
            }


            .empty-state p {

                font-size: 11px;
            }
        }


        /* =====================================================
           SMALL PHONE
        ====================================================== */

        @media (max-width: 400px) {

            .page {

                padding-left: 10px;

                padding-right: 10px;
            }


            .header-text h1 {

                font-size: 16px;
            }


            .header-text p {

                font-size: 9px;
            }


            .announcement-title {

                font-size: 14px;
            }
        }

    </style>

</head>


<body>

<div class="page">


    <!-- =====================================================
         TOP BAR
    ====================================================== -->

    <div class="top-bar">


        <!-- X BUTTON -->

        <a
            href="student_home.php"
            class="close-btn"
            title="Back"
        >

            <i class="fa-solid fa-xmark"></i>

        </a>


        <!-- HEADER -->

        <div class="header">

            <div class="header-left">

                <div class="header-icon">

                    <i
                        class="fa-solid fa-clock-rotate-left"
                    ></i>

                </div>


                <div class="header-text">

                    <h1>
                        Student History
                    </h1>

                    <p>
                        Your announcements and submitted work
                    </p>

                </div>

            </div>


            <div class="stats">

                <div class="stat">

                    <strong>
                        <?= $read_count ?>
                    </strong>

                    <span>
                        READ
                    </span>

                </div>


                <div class="stat">

                    <strong>
                        <?= $submitted_count ?>
                    </strong>

                    <span>
                        SUBMITTED
                    </span>

                </div>

            </div>

        </div>

    </div>


    <!-- =====================================================
         CONTENT
    ====================================================== -->

    <main class="content">


        <?php if ($database_error !== null): ?>

            <div class="error-box">

                <i
                    class="fa-solid fa-triangle-exclamation"
                ></i>

                Unable to load your history.

            </div>

        <?php endif; ?>


        <?php if (!empty($history)): ?>


            <?php foreach ($history as $item): ?>

                <?php

                $has_submission =
                    !empty(
                        $item['submission_id']
                    );


                $history_status =
                    getSubmissionStatus(
                        $item
                    );


                if (
                    $history_status ===
                    'Graded'
                ) {

                    $badge_class =
                        'type-graded';

                    $badge_icon =
                        'fa-solid fa-star';

                } elseif (
                    $history_status ===
                    'Submitted'
                ) {

                    $badge_class =
                        'type-submitted';

                    $badge_icon =
                        'fa-solid fa-paper-plane';

                } else {

                    $badge_class =
                        'type-read';

                    $badge_icon =
                        'fa-solid fa-check';
                }

                ?>


                <!-- =================================================
                     HISTORY CARD
                ================================================== -->

                <article class="history-card">


                    <!-- CARD HEADER -->

                    <div class="card-header">

                        <div class="card-header-row">


                            <div>

                                <h2
                                    class="announcement-title"
                                >

                                    <?= htmlspecialchars(
                                        $item['title']
                                        ??
                                        'Untitled Announcement'
                                    ) ?>

                                </h2>


                                <div
                                    class="professor"
                                >

                                    <i
                                        class="fa-solid fa-user-tie"
                                    ></i>

                                    Prof.

                                    <?= htmlspecialchars(
                                        $item['professor_name']
                                        ??
                                        'Professor'
                                    ) ?>

                                </div>

                            </div>


                            <span
                                class="type-badge <?= $badge_class ?>"
                            >

                                <i
                                    class="<?= $badge_icon ?>"
                                ></i>

                                <?= htmlspecialchars(
                                    $history_status
                                ) ?>

                            </span>

                        </div>


                        <div class="meta">


                            <?php if (
                                !empty(
                                    $item['subject']
                                )
                            ): ?>

                                <span
                                    class="meta-item"
                                >

                                    <i
                                        class="fa-solid fa-book"
                                    ></i>

                                    <?= htmlspecialchars(
                                        $item['subject']
                                    ) ?>

                                </span>

                            <?php endif; ?>


                            <?php if (
                                !empty(
                                    $item['category']
                                )
                            ): ?>

                                <span
                                    class="meta-item"
                                >

                                    <i
                                        class="fa-solid fa-tag"
                                    ></i>

                                    <?= htmlspecialchars(
                                        $item['category']
                                    ) ?>

                                </span>

                            <?php endif; ?>


                            <span
                                class="meta-item"
                            >

                                <i
                                    class="fa-regular fa-calendar"
                                ></i>

                                Posted

                                <?= formatHistoryDate(
                                    $item['created_at']
                                ) ?>

                            </span>


                            <?php if (
                                !empty(
                                    $item['read_at']
                                )
                            ): ?>

                                <span
                                    class="meta-item"
                                >

                                    <i
                                        class="fa-solid fa-check-double"
                                    ></i>

                                    Read

                                    <?= formatHistoryDate(
                                        $item['read_at']
                                    ) ?>

                                </span>

                            <?php endif; ?>


                        </div>

                    </div>


                    <!-- MESSAGE -->

                    <?php if (
                        !empty(
                            $item['message']
                        )
                    ): ?>

                        <div class="message">

                            <?= htmlspecialchars(
                                $item['message']
                            ) ?>

                        </div>

                    <?php endif; ?>


                    <!-- =================================================
                         INFORMATION
                    ================================================== -->

                    <div class="info-grid">


                        <?php if (
                            !empty(
                                $item['year_level']
                            )
                        ): ?>

                            <div class="info-box">

                                <div class="info-label">

                                    Year Level

                                </div>

                                <div class="info-value">

                                    Year

                                    <?= htmlspecialchars(
                                        $item['year_level']
                                    ) ?>

                                </div>

                            </div>

                        <?php endif; ?>


                        <?php if (
                            !empty(
                                $item['section']
                            )
                        ): ?>

                            <div class="info-box">

                                <div class="info-label">

                                    Section

                                </div>

                                <div class="info-value">

                                    <?= htmlspecialchars(
                                        $item['section']
                                    ) ?>

                                </div>

                            </div>

                        <?php endif; ?>


                        <?php if (
                            !empty(
                                $item['semester']
                            )
                        ): ?>

                            <div class="info-box">

                                <div class="info-label">

                                    Semester

                                </div>

                                <div class="info-value">

                                    <?= htmlspecialchars(
                                        $item['semester']
                                    ) ?>

                                </div>

                            </div>

                        <?php endif; ?>


                        <?php if (
                            !empty(
                                $item['academic_year']
                            )
                        ): ?>

                            <div class="info-box">

                                <div class="info-label">

                                    Academic Year

                                </div>

                                <div class="info-value">

                                    <?= htmlspecialchars(
                                        $item['academic_year']
                                    ) ?>

                                </div>

                            </div>

                        <?php endif; ?>


                        <?php if (
                            !empty(
                                $item['deadline']
                            )
                        ): ?>

                            <div class="info-box">

                                <div class="info-label">

                                    Deadline

                                </div>

                                <div class="info-value">

                                    <?= formatHistoryDate(
                                        $item['deadline']
                                    ) ?>

                                </div>

                            </div>

                        <?php endif; ?>


                    </div>


                    <!-- =================================================
                         ANNOUNCEMENT ATTACHMENT
                    ================================================== -->

                    <?php if (
                        !empty(
                            $item['attachment']
                        )
                    ): ?>

                        <div
                            class="announcement-attachment"
                        >

                            <a
                                href="<?= htmlspecialchars(
                                    $item['attachment']
                                ) ?>"
                                target="_blank"
                                class="attachment-link"
                            >

                                <i
                                    class="fa-solid fa-paperclip"
                                ></i>

                                View Announcement Attachment

                            </a>

                        </div>

                    <?php endif; ?>


                    <!-- =================================================
                         SUBMISSION
                    ================================================== -->

                    <?php if ($has_submission): ?>

                        <div class="submission">


                            <div
                                class="submission-title"
                            >

                                <i
                                    class="fa-solid fa-file-circle-check"
                                ></i>

                                Your Submission

                            </div>


                            <div
                                class="submission-row"
                            >


                                <?php if (
                                    !empty(
                                        $item['submitted_at']
                                    )
                                ): ?>

                                    <div
                                        class="submission-item"
                                    >

                                        <strong>
                                            Submitted:
                                        </strong>

                                        <?= formatHistoryDate(
                                            $item['submitted_at']
                                        ) ?>

                                    </div>

                                <?php endif; ?>


                                <?php if (
                                    hasValue(
                                        $item['grade']
                                        ?? null
                                    )
                                ): ?>

                                    <div
                                        class="submission-item"
                                    >

                                        <strong>
                                            Grade:
                                        </strong>

                                        <?= htmlspecialchars(
                                            $item['grade']
                                        ) ?>

                                    </div>

                                <?php else: ?>

                                    <div
                                        class="submission-item"
                                    >

                                        <strong>
                                            Grade:
                                        </strong>

                                        Not yet graded

                                    </div>

                                <?php endif; ?>


                            </div>


                            <!-- =================================================
                                 SUBMISSION TEXT
                            ================================================== -->

                            <?php if (
                                !empty(
                                    $item['submission_text']
                                )
                            ): ?>

                                <div
                                    class="feedback"
                                >

                                    <div
                                        class="feedback-label"
                                    >

                                        Your Work / Comment

                                    </div>


                                    <div
                                        class="feedback-text"
                                    >

                                        <?= htmlspecialchars(
                                            $item['submission_text']
                                        ) ?>

                                    </div>

                                </div>

                            <?php endif; ?>


                            <!-- =================================================
                                 SUBMITTED FILE
                            ================================================== -->

                            <?php if (
                                !empty(
                                    $item['file_path']
                                )
                            ): ?>

                                <a
                                    href="<?= htmlspecialchars(
                                        $item['file_path']
                                    ) ?>"
                                    target="_blank"
                                    class="file-link"
                                >

                                    <i
                                        class="fa-solid fa-file"
                                    ></i>

                                    <?= htmlspecialchars(
                                        getFileName(
                                            $item['file_path']
                                        )
                                    ) ?>

                                </a>

                            <?php endif; ?>


                            <!-- =================================================
                                 PROFESSOR FEEDBACK
                            ================================================== -->

                            <?php if (
                                hasValue(
                                    $item['feedback']
                                    ?? null
                                )
                            ): ?>

                                <div
                                    class="feedback"
                                >

                                    <div
                                        class="feedback-label"
                                    >

                                        Professor Feedback

                                    </div>


                                    <div
                                        class="feedback-text"
                                    >

                                        <?= htmlspecialchars(
                                            $item['feedback']
                                        ) ?>

                                    </div>

                                </div>

                            <?php endif; ?>


                        </div>

                    <?php endif; ?>


                </article>

            <?php endforeach; ?>


        <?php else: ?>


            <!-- =================================================
                 EMPTY STATE
            ================================================== -->

            <div class="empty-state">


                <div class="empty-icon">

                    <i
                        class="fa-solid fa-clock-rotate-left"
                    ></i>

                </div>


                <h2>

                    No History Yet

                </h2>


                <p>

                    Announcements that you mark as read
                    and work that you submit will appear
                    here.

                </p>


            </div>

        <?php endif; ?>


        <div class="footer">

            NoteSphere • Student Announcement History

        </div>


    </main>

</div>


<!-- =========================================================
     THEME
========================================================= -->

<script>

    // ========================================================
    // APPLY THEME
    // ========================================================

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


    // ========================================================
    // GET SAVED THEME
    // ========================================================

    const savedTheme =
        localStorage.getItem(
            'notesphere_theme'
        ) ||
        localStorage.getItem(
            'theme'
        ) ||
        'dark';


    applyTheme(savedTheme);


    // ========================================================
    // WATCH FOR THEME CHANGES
    // ========================================================

    window.addEventListener(
        'storage',
        function(event) {

            if (
                event.key ===
                    'notesphere_theme' ||

                event.key ===
                    'theme'
            ) {

                applyTheme(
                    event.newValue || 'dark'
                );
            }
        }
    );

</script>


</body>

</html>