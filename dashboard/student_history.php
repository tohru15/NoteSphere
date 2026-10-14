
<?php

/**
 * =========================================================
 * NoteSphere - Student Help Board History
 * Light / Dark Mode Compatible
 * =========================================================
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
// HELPER
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
// GET STUDENT
// =========================================================

$student_name = 'Student';

try {

    $stmt = $conn->prepare("
        SELECT full_name
        FROM users
        WHERE user_id = ?
        LIMIT 1
    ");

    $stmt->execute([$user_id]);

    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($student && !empty($student['full_name'])) {
        $student_name = $student['full_name'];
    }

} catch (PDOException $e) {

    $student_name = 'Student';
}


// =========================================================
// GET MY QUESTIONS
// =========================================================

$my_questions = [];

try {

    $stmt = $conn->prepare("
        SELECT
            id,
            title,
            details,
            is_anonymous,
            created_at
        FROM help_questions
        WHERE student_id = ?
        ORDER BY created_at DESC
    ");

    $stmt->execute([$user_id]);

    $my_questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $my_questions = [];
}


// =========================================================
// GET MY COMMENTS
// Comments written by the current student
// =========================================================

$my_comments = [];

try {

    $stmt = $conn->prepare("
        SELECT
            c.id,
            c.question_id,
            c.comment,
            c.created_at,
            q.title AS question_title
        FROM help_comments c
        LEFT JOIN help_questions q
            ON q.id = c.question_id
        WHERE c.user_id = ?
        ORDER BY c.created_at DESC
    ");

    $stmt->execute([$user_id]);

    $my_comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $my_comments = [];
}


// =========================================================
// GET COMMENTS ON MY POSTS
// Comments made by other students on the current
// student's own Help Board posts
// =========================================================

$comments_on_my_posts = [];

try {

    $stmt = $conn->prepare("
        SELECT
            c.id,
            c.question_id,
            c.user_id,
            c.comment,
            c.created_at,
            q.title AS question_title,
            u.full_name AS commenter_name
        FROM help_comments c

        INNER JOIN help_questions q
            ON q.id = c.question_id

        LEFT JOIN users u
            ON u.user_id = c.user_id

        WHERE q.student_id = ?
          AND c.user_id <> ?

        ORDER BY c.created_at DESC
    ");

    $stmt->execute([
        $user_id,
        $user_id
    ]);

    $comments_on_my_posts =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $comments_on_my_posts = [];
}


// =========================================================
// GET HIDDEN POSTS
// =========================================================

$hidden_posts = [];

try {

    $stmt = $conn->prepare("
        SELECT
            hp.id,
            hp.question_id,
            hp.hidden_at,
            q.title,
            q.details,
            q.created_at AS post_created_at
        FROM help_hidden_posts hp

        LEFT JOIN help_questions q
            ON q.id = hp.question_id

        WHERE hp.user_id = ?

        ORDER BY hp.hidden_at DESC
    ");

    $stmt->execute([$user_id]);

    $hidden_posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $hidden_posts = [];
}


// =========================================================
// GET ADMIN WARNINGS
// =========================================================

$warnings = [];

try {

    $stmt = $conn->prepare("
        SELECT
            id,
            title,
            message,
            dismissed,
            created_at
        FROM student_warnings
        WHERE student_id = ?
        ORDER BY created_at DESC
    ");

    $stmt->execute([$user_id]);

    $warnings = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $warnings = [];
}


// =========================================================
// COUNTS
// =========================================================

$total_questions = count($my_questions);

$total_comments = count($my_comments);

$total_comments_on_my_posts =
    count($comments_on_my_posts);

$total_hidden = count($hidden_posts);

$total_warnings = count($warnings);


// =========================================================
// PROFILE INITIAL
// =========================================================

$profile_initial = 'S';

if (!empty($student_name)) {

    $profile_initial = mb_substr(
        trim($student_name),
        0,
        1,
        'UTF-8'
    );

    $profile_initial = mb_strtoupper(
        $profile_initial,
        'UTF-8'
    );
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
        History | NoteSphere
    </title>


    <!-- =====================================================
         APPLY SAVED THEME BEFORE PAGE LOAD
    ====================================================== -->

    <script>

        (function () {

            const savedTheme =
                localStorage.getItem('notesphere_theme') ||
                localStorage.getItem('theme');

            if (savedTheme === 'light') {

                document.documentElement.classList.add(
                    'light-mode'
                );

            } else {

                document.documentElement.classList.remove(
                    'light-mode'
                );
            }

        })();

    </script>


    <!-- =====================================================
         PAGE CSS
    ====================================================== -->

    <style>

        /* =================================================
           ROOT VARIABLES
        ================================================= */

        :root {

            --bg: #050505;

            --surface:
                rgba(13, 13, 13, 0.88);

            --surface-soft:
                rgba(20, 20, 20, 0.82);

            --surface-hover:
                rgba(28, 28, 28, 0.94);

            --border:
                rgba(255, 255, 255, 0.08);

            --border-strong:
                rgba(57, 255, 20, 0.22);

            --text:
                #ffffff;

            --text-soft:
                #bdbdbd;

            --text-muted:
                #858585;

            --green:
                #39ff14;

            --green-soft:
                rgba(57, 255, 20, 0.10);

            --green-border:
                rgba(57, 255, 20, 0.25);

            --warning:
                #ffb020;

            --warning-soft:
                rgba(255, 176, 32, 0.10);

            --warning-border:
                rgba(255, 176, 32, 0.25);

            --danger:
                #ff5c5c;

            --shadow:
                0 10px 35px rgba(0, 0, 0, 0.30);

            --radius:
                16px;
        }


        /* =================================================
           LIGHT MODE VARIABLES
        ================================================= */

        html.light-mode {

            --bg:
                #f4f6f8;

            --surface:
                rgba(255, 255, 255, 0.90);

            --surface-soft:
                rgba(255, 255, 255, 0.84);

            --surface-hover:
                rgba(255, 255, 255, 0.98);

            --border:
                rgba(0, 0, 0, 0.08);

            --border-strong:
                rgba(32, 185, 0, 0.24);

            --text:
                #171717;

            --text-soft:
                #555555;

            --text-muted:
                #777777;

            --green:
                #20b900;

            --green-soft:
                rgba(32, 185, 0, 0.08);

            --green-border:
                rgba(32, 185, 0, 0.22);

            --warning:
                #d98b00;

            --warning-soft:
                rgba(217, 139, 0, 0.09);

            --warning-border:
                rgba(217, 139, 0, 0.22);

            --danger:
                #d93636;

            --shadow:
                0 10px 30px rgba(0, 0, 0, 0.10);
        }


        /* =================================================
           RESET
        ================================================= */

        * {

            margin:
                0;

            padding:
                0;

            box-sizing:
                border-box;
        }


        html {

            min-height:
                100%;

            scroll-behavior:
                smooth;
        }


        body {

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            color:
                var(--text);

            min-height:
                100vh;

            overflow-x:
                hidden;

            background-color:
                var(--bg);

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


        /* =================================================
           LIGHT MODE BODY BACKGROUND
        ================================================= */

        html.light-mode body {

            background-color:
                var(--bg);

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


        /* =================================================
           SUBTLE BACKGROUND GLOW
        ================================================= */

        body::before {

            content:
                "";

            position:
                fixed;

            inset:
                0;

            pointer-events:
                none;

            z-index:
                -1;

            background:
                radial-gradient(
                    circle at top center,
                    rgba(57, 255, 20, 0.07),
                    transparent 42%
                );
        }


        html.light-mode body::before {

            background:
                radial-gradient(
                    circle at top center,
                    rgba(32, 185, 0, 0.045),
                    transparent 42%
                );
        }


        /* =================================================
           TOPBAR
        ================================================= */

        .topbar {

            position:
                sticky;

            top:
                0;

            z-index:
                1000;

            width:
                100%;

            height:
                70px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                12px;

            padding:
                0 22px;

            background:
                rgba(5, 5, 5, 0.88);

            border-bottom:
                1px solid var(--border);

            backdrop-filter:
                blur(16px);

            -webkit-backdrop-filter:
                blur(16px);
        }


        html.light-mode .topbar {

            background:
                rgba(255, 255, 255, 0.88);
        }


        /* =================================================
           TOP LEFT
        ================================================= */

        .top-left {

            display:
                flex;

            align-items:
                center;

            gap:
                12px;

            min-width:
                180px;
        }


        /* =================================================
           BACK BUTTON
        ================================================= */

        .back-btn {

            width:
                40px;

            height:
                40px;

            border:
                1px solid var(--border);

            border-radius:
                50%;

            background:
                rgba(255, 255, 255, 0.04);

            color:
                var(--text);

            font-size:
                23px;

            line-height:
                1;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            cursor:
                pointer;

            transition:
                0.2s ease;
        }


        .back-btn:hover {

            color:
                var(--green);

            border-color:
                var(--green-border);

            background:
                var(--green-soft);

            transform:
                translateY(-1px);
        }


        /* =================================================
           BRAND
        ================================================= */

        .brand {

            font-size:
                19px;

            font-weight:
                800;

            letter-spacing:
                0.3px;

            color:
                var(--green);

            white-space:
                nowrap;
        }


        /* =================================================
           NAVIGATION
        ================================================= */

        .top-nav {

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            gap:
                4px;

            height:
                100%;

            flex:
                1;

            min-width:
                0;
        }


        .nav-item {

            position:
                relative;

            width:
                105px;

            height:
                58px;

            display:
                flex;

            flex-direction:
                column;

            align-items:
                center;

            justify-content:
                center;

            gap:
                4px;

            color:
                var(--text-muted);

            text-decoration:
                none;

            border-radius:
                10px;

            transition:
                color 0.2s ease,
                background 0.2s ease;
        }


        .nav-item svg {

            width:
                20px;

            height:
                20px;

            flex-shrink:
                0;
        }


        .nav-item span {

            font-size:
                10px;

            font-weight:
                600;

            white-space:
                nowrap;
        }


        .nav-item:hover {

            color:
                var(--text);

            background:
                rgba(255, 255, 255, 0.04);
        }


        .nav-item.active {

            color:
                var(--green);
        }


        .nav-item.active::after {

            content:
                "";

            position:
                absolute;

            left:
                18px;

            right:
                18px;

            bottom:
                3px;

            height:
                2px;

            border-radius:
                10px;

            background:
                var(--green);

            box-shadow:
                0 0 8px rgba(57, 255, 20, 0.45);
        }


        /* =================================================
           TOP RIGHT
        ================================================= */

        .top-right {

            min-width:
                180px;

            display:
                flex;

            justify-content:
                flex-end;

            align-items:
                center;
        }


        .profile-btn {

            display:
                flex;

            align-items:
                center;

            gap:
                9px;

            padding:
                5px 8px;

            border-radius:
                30px;

            text-decoration:
                none;

            color:
                var(--text);

            transition:
                background 0.2s ease;

            min-width:
                0;
        }


        .profile-btn:hover {

            background:
                rgba(255, 255, 255, 0.05);
        }


        .profile-circle {

            width:
                38px;

            height:
                38px;

            flex-shrink:
                0;

            border-radius:
                50%;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            background:
                var(--green);

            color:
                #050505;

            font-size:
                15px;

            font-weight:
                800;

            box-shadow:
                0 0 12px rgba(57, 255, 20, 0.16);
        }


        .profile-name {

            max-width:
                130px;

            overflow:
                hidden;

            text-overflow:
                ellipsis;

            white-space:
                nowrap;

            font-size:
                13px;

            font-weight:
                600;
        }


        /* =================================================
           MAIN PAGE
        ================================================= */

        .page {

            width:
                100%;

            max-width:
                850px;

            margin:
                0 auto;

            padding:
                38px 20px 70px;
        }


        /* =================================================
           HEADING
        ================================================= */

        .heading {

            margin-bottom:
                26px;
        }


        .heading h1 {

            font-size:
                30px;

            line-height:
                1.2;

            font-weight:
                800;

            margin-bottom:
                7px;

            color:
                var(--text);
        }


        .heading p {

            color:
                var(--text-soft);

            font-size:
                14px;

            line-height:
                1.6;
        }


        /* =================================================
           SUMMARY
        ================================================= */

        .summary {

            display:
                grid;

            grid-template-columns:
                repeat(5, 1fr);

            gap:
                10px;

            margin-bottom:
                34px;
        }


        .summary-card {

            min-width:
                0;

            padding:
                18px 10px;

            text-align:
                center;

            border:
                1px solid var(--border);

            border-radius:
                14px;

            background:
                var(--surface);

            box-shadow:
                var(--shadow);

            backdrop-filter:
                blur(10px);

            -webkit-backdrop-filter:
                blur(10px);

            transition:
                0.2s ease;
        }


        .summary-card:hover {

            border-color:
                var(--green-border);

            transform:
                translateY(-2px);
        }


        .summary-number {

            color:
                var(--green);

            font-size:
                25px;

            font-weight:
                800;

            line-height:
                1.2;

            margin-bottom:
                5px;
        }


        .summary-label {

            color:
                var(--text-soft);

            font-size:
                11px;

            font-weight:
                600;

            line-height:
                1.3;
        }


        /* =================================================
           SECTION TITLE
        ================================================= */

        .section-title {

            display:
                flex;

            align-items:
                center;

            gap:
                7px;

            margin-top:
                28px;

            margin-bottom:
                12px;

            color:
                var(--text);

            font-size:
                16px;

            font-weight:
                800;
        }


        /* =================================================
           HISTORY CARD
        ================================================= */

        .history-card {

            width:
                100%;

            padding:
                18px;

            margin-bottom:
                10px;

            border:
                1px solid var(--border);

            border-radius:
                var(--radius);

            background:
                var(--surface);

            box-shadow:
                var(--shadow);

            backdrop-filter:
                blur(12px);

            -webkit-backdrop-filter:
                blur(12px);

            transition:
                border-color 0.2s ease,
                transform 0.2s ease,
                background 0.2s ease;
        }


        .history-card:hover {

            border-color:
                var(--border-strong);

            background:
                var(--surface-hover);

            transform:
                translateY(-1px);
        }


        /* =================================================
           HISTORY TOP
        ================================================= */

        .history-top {

            display:
                flex;

            align-items:
                flex-start;

            justify-content:
                space-between;

            gap:
                15px;

            margin-bottom:
                10px;
        }


        .history-title {

            min-width:
                0;

            color:
                var(--text);

            font-size:
                15px;

            font-weight:
                700;

            line-height:
                1.45;

            word-break:
                break-word;
        }


        .history-date {

            flex-shrink:
                0;

            color:
                var(--text-muted);

            font-size:
                10px;

            line-height:
                1.4;

            text-align:
                right;

            white-space:
                nowrap;
        }


        /* =================================================
           HISTORY TEXT
        ================================================= */

        .history-text {

            color:
                var(--text-soft);

            font-size:
                13px;

            line-height:
                1.65;

            white-space:
                pre-wrap;

            word-break:
                break-word;

            margin-bottom:
                12px;
        }


        /* =================================================
           COMMENT AUTHOR
        ================================================= */

        .comment-author {

            display:
                flex;

            align-items:
                center;

            gap:
                8px;

            margin-bottom:
                9px;

            color:
                var(--text-soft);

            font-size:
                12px;

            font-weight:
                600;
        }


        .comment-avatar {

            width:
                28px;

            height:
                28px;

            border-radius:
                50%;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            background:
                var(--green);

            color:
                #050505;

            font-size:
                11px;

            font-weight:
                800;

            flex-shrink:
                0;
        }


        /* =================================================
           BADGES
        ================================================= */

        .badge {

            display:
                inline-flex;

            align-items:
                center;

            width:
                fit-content;

            padding:
                5px 9px;

            border-radius:
                999px;

            color:
                var(--green);

            background:
                var(--green-soft);

            border:
                1px solid var(--green-border);

            font-size:
                10px;

            font-weight:
                700;
        }


        .badge.hidden {

            color:
                #8e8e8e;

            background:
                rgba(128, 128, 128, 0.10);

            border-color:
                rgba(128, 128, 128, 0.20);
        }


        .badge.warning {

            color:
                var(--warning);

            background:
                var(--warning-soft);

            border-color:
                var(--warning-border);
        }


        .badge.received {

            color:
                #4db8ff;

            background:
                rgba(77, 184, 255, 0.10);

            border-color:
                rgba(77, 184, 255, 0.25);
        }


        html.light-mode .badge.received {

            color:
                #087bb8;

            background:
                rgba(8, 123, 184, 0.08);

            border-color:
                rgba(8, 123, 184, 0.20);
        }


        /* =================================================
           WARNING CARD
        ================================================= */

        .warning-history {

            border-color:
                var(--warning-border);
        }


        .warning-history:hover {

            border-color:
                rgba(255, 176, 32, 0.40);
        }


        .warning-message {

            padding:
                12px 13px;

            margin-bottom:
                12px;

            color:
                var(--text-soft);

            background:
                var(--warning-soft);

            border-left:
                3px solid var(--warning);

            border-radius:
                8px;

            font-size:
                13px;

            line-height:
                1.6;

            white-space:
                pre-wrap;

            word-break:
                break-word;
        }


        /* =================================================
           EMPTY STATE
        ================================================= */

        .empty {

            width:
                100%;

            padding:
                24px 16px;

            margin-bottom:
                10px;

            text-align:
                center;

            color:
                var(--text-muted);

            font-size:
                13px;

            line-height:
                1.5;

            border:
                1px dashed var(--border);

            border-radius:
                13px;

            background:
                rgba(255, 255, 255, 0.02);
        }


        /* =================================================
           750px
        ================================================= */

        @media (max-width: 750px) {

            .topbar {

                height:
                    64px;

                padding:
                    0 8px;

                gap:
                    4px;
            }


            .top-left {

                width:
                    auto;

                min-width:
                    auto;

                flex:
                    0 0 auto;
            }


            .back-btn {

                width:
                    38px;

                height:
                    38px;

                font-size:
                    20px;
            }


            .brand {

                display:
                    none;
            }


            .top-nav {

                flex:
                    1 1 auto;

                min-width:
                    0;

                gap:
                    2px;

                overflow:
                    hidden;
            }


            .nav-item {

                flex:
                    1 1 0;

                width:
                    auto;

                min-width:
                    45px;

                max-width:
                    75px;

                height:
                    54px;
            }


            .nav-item svg {

                width:
                    20px;

                height:
                    20px;
            }


            .nav-item span {

                font-size:
                    9px;
            }


            .nav-item.active::after {

                left:
                    12px;

                right:
                    12px;
            }


            .top-right {

                width:
                    auto;

                min-width:
                    auto;

                flex:
                    0 0 auto;
            }


            .profile-btn {

                padding:
                    4px;
            }


            .profile-name {

                display:
                    none;
            }


            .profile-circle {

                width:
                    34px;

                height:
                    34px;
            }


            .page {

                width:
                    100%;

                max-width:
                    100%;

                padding:
                    24px 12px 55px;
            }


            .heading h1 {

                font-size:
                    25px;
            }


            .heading p {

                font-size:
                    13px;
            }


            .summary {

                grid-template-columns:
                    repeat(2, 1fr);

                gap:
                    8px;
            }


            .summary-card {

                min-width:
                    0;

                padding:
                    14px 8px;
            }


            .summary-number {

                font-size:
                    21px;
            }


            .history-card {

                padding:
                    15px;
            }


            .history-top {

                gap:
                    8px;
            }


            .history-title {

                min-width:
                    0;

                word-break:
                    break-word;
            }
        }


        /* =================================================
           480px
        ================================================= */

        @media (max-width: 480px) {

            body {

                background-attachment:
                    scroll;
            }


            html.light-mode body {

                background-attachment:
                    scroll;
            }


            .topbar {

                height:
                    60px;

                padding:
                    0 6px;
            }


            .back-btn {

                width:
                    36px;

                height:
                    36px;

                font-size:
                    19px;
            }


            .top-nav {

                gap:
                    0;
            }


            .nav-item {

                min-width:
                    42px;

                height:
                    52px;
            }


            .nav-item svg {

                width:
                    18px;

                height:
                    18px;

                margin-bottom:
                    2px;
            }


            .nav-item span {

                font-size:
                    8px;
            }


            .nav-item.active::after {

                left:
                    9px;

                right:
                    9px;

                height:
                    2px;
            }


            .profile-circle {

                width:
                    32px;

                height:
                    32px;

                font-size:
                    13px;
            }


            .page {

                padding:
                    20px 9px 45px;
            }


            .heading {

                margin-bottom:
                    20px;
            }


            .heading h1 {

                font-size:
                    22px;
            }


            .heading p {

                font-size:
                    12px;

                line-height:
                    1.5;
            }


            .summary {

                gap:
                    7px;
            }


            .summary-card {

                padding:
                    13px 6px;

                border-radius:
                    11px;
            }


            .summary-number {

                font-size:
                    20px;
            }


            .summary-label {

                font-size:
                    10px;
            }


            .section-title {

                font-size:
                    15px;

                margin-top:
                    23px;
            }


            .history-card {

                padding:
                    14px;

                border-radius:
                    12px;
            }


            .history-top {

                flex-direction:
                    column;

                gap:
                    5px;
            }


            .history-date {

                white-space:
                    normal;

                text-align:
                    left;

                font-size:
                    10px;
            }


            .history-title {

                font-size:
                    14px;
            }


            .history-text,
            .warning-message {

                font-size:
                    12px;
            }


            .empty {

                padding:
                    20px 12px;

                font-size:
                    12px;
            }
        }


        /* =================================================
           350px
        ================================================= */

        @media (max-width: 350px) {

            .topbar {

                padding:
                    0 4px;
            }


            .back-btn {

                width:
                    33px;

                height:
                    33px;

                font-size:
                    18px;
            }


            .nav-item {

                min-width:
                    38px;
            }


            .nav-item svg {

                width:
                    17px;

                height:
                    17px;
            }


            .nav-item span {

                font-size:
                    7px;
            }


            .profile-circle {

                width:
                    30px;

                height:
                    30px;

                font-size:
                    12px;
            }


            .page {

                padding-left:
                    7px;

                padding-right:
                    7px;
            }


            .summary {

                gap:
                    5px;
            }


            .summary-card {

                padding:
                    11px 4px;
            }


            .summary-number {

                font-size:
                    18px;
            }


            .summary-label {

                font-size:
                    9px;
            }
        }

    </style>

</head>


<body>


<!-- =====================================================
     TOP BAR
===================================================== -->

<header class="topbar">


    <!-- =================================================
         LEFT
    ================================================== -->

    <div class="top-left">

        <button
            type="button"
            class="back-btn"
            onclick="window.location.href='student_home.php';"
            title="Back to Home"
            aria-label="Back to Home"
        >
            ×
        </button>


        <div class="brand">
            NoteSphere
        </div>

    </div>


    <!-- =================================================
         NAVIGATION
    ================================================== -->

    <nav
        class="top-nav"
        aria-label="Student Navigation"
    >


        <!-- HOME -->

        <a
            href="student_home.php"
            class="nav-item"
            title="Home"
        >

            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
            >

                <path d="M3 10.5L12 3l9 7.5"></path>

                <path d="M5 9.5V21h14V9.5"></path>

                <path d="M9 21v-7h6v7"></path>

            </svg>

            <span>
                Home
            </span>

        </a>


        <!-- HISTORY -->

        <a
            href="student_history.php"
            class="nav-item active"
            title="History"
            aria-current="page"
        >

            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
            >

                <circle
                    cx="12"
                    cy="12"
                    r="9"
                ></circle>

                <path
                    d="M12 7v5l3 2"
                ></path>

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
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
            >

                <path
                    d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"
                ></path>

                <path
                    d="M10 21h4"
                ></path>

            </svg>

            <span>
                Notifications
            </span>

        </a>

    </nav>


    <!-- =================================================
         RIGHT PROFILE
    ================================================== -->

    <div class="top-right">

        <a
            href="student_profile.php"
            class="profile-btn"
            title="My Profile"
        >

            <div class="profile-circle">

                <?php

                echo h($profile_initial);

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
     MAIN
===================================================== -->

<main class="page">


    <!-- =================================================
         PAGE HEADING
    ================================================== -->

    <div class="heading">

        <h1>
            My History
        </h1>

        <p>
            Your activity and records from the Help Board.
        </p>

    </div>


    <!-- =================================================
         SUMMARY
    ================================================== -->

    <div class="summary">


        <!-- QUESTIONS -->

        <div class="summary-card">

            <div class="summary-number">

                <?php

                echo $total_questions;

                ?>

            </div>

            <div class="summary-label">
                My Questions
            </div>

        </div>


        <!-- MY COMMENTS -->

        <div class="summary-card">

            <div class="summary-number">

                <?php

                echo $total_comments;

                ?>

            </div>

            <div class="summary-label">
                My Comments
            </div>

        </div>


        <!-- COMMENTS RECEIVED -->

        <div class="summary-card">

            <div class="summary-number">

                <?php

                echo $total_comments_on_my_posts;

                ?>

            </div>

            <div class="summary-label">
                Comments Received
            </div>

        </div>


        <!-- HIDDEN -->

        <div class="summary-card">

            <div class="summary-number">

                <?php

                echo $total_hidden;

                ?>

            </div>

            <div class="summary-label">
                Hidden Posts
            </div>

        </div>


        <!-- WARNINGS -->

        <div class="summary-card">

            <div class="summary-number">

                <?php

                echo $total_warnings;

                ?>

            </div>

            <div class="summary-label">
                Admin Warnings
            </div>

        </div>


    </div>


    <!-- =================================================
         MY QUESTIONS
    ================================================== -->

    <div class="section-title">
        📝 My Questions
    </div>


    <?php if (!empty($my_questions)): ?>


        <?php foreach ($my_questions as $question): ?>

            <div class="history-card">


                <div class="history-top">


                    <div class="history-title">

                        <?php

                        echo h(
                            $question['title']
                        );

                        ?>

                    </div>


                    <div class="history-date">

                        <?php

                        $question_date =
                            !empty($question['created_at'])
                                ? strtotime(
                                    $question['created_at']
                                )
                                : false;

                        echo $question_date
                            ? date(
                                'M d, Y · h:i A',
                                $question_date
                            )
                            : 'Date unavailable';

                        ?>

                    </div>

                </div>


                <div class="history-text">

                    <?php

                    echo h(
                        $question['details']
                    );

                    ?>

                </div>


                <span class="badge">

                    <?php

                    echo !empty(
                        $question['is_anonymous']
                    )
                        ? 'Anonymous Post'
                        : 'Posted by Me';

                    ?>

                </span>


            </div>

        <?php endforeach; ?>


    <?php else: ?>


        <div class="empty">
            You have not posted a question yet.
        </div>


    <?php endif; ?>


    <!-- =================================================
         MY COMMENTS
    ================================================== -->

    <div class="section-title">
        💬 My Comments
    </div>


    <?php if (!empty($my_comments)): ?>


        <?php foreach ($my_comments as $comment): ?>

            <div class="history-card">


                <div class="history-top">


                    <div class="history-title">

                        Comment on:

                        <?php

                        echo h(
                            $comment['question_title']
                                ?: 'Deleted Question'
                        );

                        ?>

                    </div>


                    <div class="history-date">

                        <?php

                        $comment_date =
                            !empty($comment['created_at'])
                                ? strtotime(
                                    $comment['created_at']
                                )
                                : false;

                        echo $comment_date
                            ? date(
                                'M d, Y · h:i A',
                                $comment_date
                            )
                            : 'Date unavailable';

                        ?>

                    </div>

                </div>


                <div class="history-text">

                    <?php

                    echo h(
                        $comment['comment']
                    );

                    ?>

                </div>


                <span class="badge">
                    My Comment
                </span>


            </div>

        <?php endforeach; ?>


    <?php else: ?>


        <div class="empty">
            You have not commented on any post yet.
        </div>


    <?php endif; ?>


    <!-- =================================================
         COMMENTS ON MY POSTS
    ================================================== -->

    <div class="section-title">
        💬 Comments on My Posts
    </div>


    <?php if (!empty($comments_on_my_posts)): ?>


        <?php foreach ($comments_on_my_posts as $comment): ?>

            <?php

            $commenter_name =
                !empty($comment['commenter_name'])
                    ? $comment['commenter_name']
                    : 'Student';

            $commenter_initial =
                mb_substr(
                    trim($commenter_name),
                    0,
                    1,
                    'UTF-8'
                );

            $commenter_initial =
                mb_strtoupper(
                    $commenter_initial,
                    'UTF-8'
                );

            ?>


            <div class="history-card">


                <div class="history-top">


                    <div class="history-title">

                        Comment on your post:

                        <?php

                        echo h(
                            $comment['question_title']
                                ?: 'Deleted Question'
                        );

                        ?>

                    </div>


                    <div class="history-date">

                        <?php

                        $received_comment_date =
                            !empty($comment['created_at'])
                                ? strtotime(
                                    $comment['created_at']
                                )
                                : false;

                        echo $received_comment_date
                            ? date(
                                'M d, Y · h:i A',
                                $received_comment_date
                            )
                            : 'Date unavailable';

                        ?>

                    </div>

                </div>


                <div class="comment-author">

                    <div class="comment-avatar">

                        <?php

                        echo h(
                            $commenter_initial
                        );

                        ?>

                    </div>


                    <span>

                        <?php

                        echo h(
                            $commenter_name
                        );

                        ?>

                        commented on your post.

                    </span>

                </div>


                <div class="history-text">

                    <?php

                    echo h(
                        $comment['comment']
                    );

                    ?>

                </div>


                <span class="badge received">
                    Comment Received
                </span>


            </div>

        <?php endforeach; ?>


    <?php else: ?>


        <div class="empty">
            No one has commented on your posts yet.
        </div>


    <?php endif; ?>


    <!-- =================================================
         HIDDEN POSTS
    ================================================== -->

    <div class="section-title">
        👁️ Hidden Posts
    </div>


    <?php if (!empty($hidden_posts)): ?>


        <?php foreach ($hidden_posts as $hidden): ?>

            <div class="history-card">


                <div class="history-top">


                    <div class="history-title">

                        <?php

                        echo h(
                            $hidden['title']
                                ?: 'Deleted Question'
                        );

                        ?>

                    </div>


                    <div class="history-date">

                        Hidden:

                        <?php

                        $hidden_date =
                            !empty($hidden['hidden_at'])
                                ? strtotime(
                                    $hidden['hidden_at']
                                )
                                : false;

                        echo $hidden_date
                            ? date(
                                'M d, Y · h:i A',
                                $hidden_date
                            )
                            : 'Date unavailable';

                        ?>

                    </div>

                </div>


                <div class="history-text">

                    <?php

                    echo h(
                        $hidden['details']
                            ?: 'This post is no longer available.'
                    );

                    ?>

                </div>


                <span class="badge hidden">
                    Hidden from my feed
                </span>


            </div>

        <?php endforeach; ?>


    <?php else: ?>


        <div class="empty">
            You have no hidden posts.
        </div>


    <?php endif; ?>


    <!-- =================================================
         ADMIN WARNINGS
    ================================================== -->

    <div class="section-title">
        ⚠️ Admin Warnings
    </div>


    <?php if (!empty($warnings)): ?>


        <?php foreach ($warnings as $warning): ?>

            <div class="history-card warning-history">


                <div class="history-top">


                    <div class="history-title">

                        <?php

                        echo h(
                            $warning['title']
                                ?: 'Admin Warning'
                        );

                        ?>

                    </div>


                    <div class="history-date">

                        <?php

                        $warning_date =
                            !empty($warning['created_at'])
                                ? strtotime(
                                    $warning['created_at']
                                )
                                : false;

                        echo $warning_date
                            ? date(
                                'M d, Y · h:i A',
                                $warning_date
                            )
                            : 'Date unavailable';

                        ?>

                    </div>

                </div>


                <div class="warning-message">

                    <?php

                    echo h(
                        $warning['message']
                    );

                    ?>

                </div>


                <span class="badge warning">

                    <?php

                    echo !empty(
                        $warning['dismissed']
                    )
                        ? 'Dismissed'
                        : 'Active';

                    ?>

                </span>


            </div>

        <?php endforeach; ?>


    <?php else: ?>


        <div class="empty">
            You have no admin warnings.
        </div>


    <?php endif; ?>


</main>


<!-- =====================================================
     JAVASCRIPT
===================================================== -->

<script>


    // =====================================================
    // BACK FUNCTION
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
    // THEME SYNC
    // =====================================================

    window.addEventListener(
        'storage',
        function (event) {

            if (
                event.key ===
                'notesphere_theme'
            ) {

                if (
                    event.newValue ===
                    'light'
                ) {

                    document.documentElement
                        .classList
                        .add('light-mode');

                } else {

                    document.documentElement
                        .classList
                        .remove('light-mode');

                }

            }

        }
    );


    // =====================================================
    // KEEP THEME CONSISTENT WHEN PAGE LOADS
    // =====================================================

    (function () {

        const savedTheme =
            localStorage.getItem('notesphere_theme') ||
            localStorage.getItem('theme');

        if (savedTheme === 'light') {

            document.documentElement
                .classList
                .add('light-mode');

        } else {

            document.documentElement
                .classList
                .remove('light-mode');

        }

    })();


</script>


</body>

</html>

