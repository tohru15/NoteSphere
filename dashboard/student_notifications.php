<?php

/**
 * NoteSphere - Student Notifications
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
// GET STUDENT NAME
// =========================================================

$student_name = 'Student';

try {

    $stmt = $conn->prepare("
        SELECT full_name
        FROM users
        WHERE user_id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $user_id
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $student_name = $user['full_name'] ?? 'Student';

} catch (Exception $e) {

    // Continue loading page

}


// =========================================================
// OPEN NOTIFICATION
// =========================================================

if (
    isset($_GET['open']) &&
    is_numeric($_GET['open'])
) {

    $notification_id = (int) $_GET['open'];

    try {

        // -------------------------------------------------
        // GET NOTIFICATION
        // -------------------------------------------------

        $stmt = $conn->prepare("
            SELECT
                question_id
            FROM notifications
            WHERE id = ?
            AND user_id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $notification_id,
            $user_id
        ]);

        $notification = $stmt->fetch(PDO::FETCH_ASSOC);


        // -------------------------------------------------
        // MARK AS READ
        // -------------------------------------------------

        $stmt = $conn->prepare("
            UPDATE notifications
            SET is_read = 1
            WHERE id = ?
            AND user_id = ?
        ");

        $stmt->execute([
            $notification_id,
            $user_id
        ]);


        // -------------------------------------------------
        // GO TO EXACT HELP BOARD POST
        // -------------------------------------------------

        if (
            $notification &&
            !empty($notification['question_id'])
        ) {

            $question_id = (int) $notification['question_id'];

            header(
                "Location: help_board.php#post-" . $question_id
            );

            exit();
        }

    } catch (PDOException $e) {

        // Continue

    }


    // If notification does not have a valid post
    header("Location: student_notifications.php");
    exit();
}


// =========================================================
// MARK ALL AS READ
// =========================================================

if (
    isset($_GET['mark_all']) &&
    $_GET['mark_all'] === '1'
) {

    try {

        $stmt = $conn->prepare("
            UPDATE notifications
            SET is_read = 1
            WHERE user_id = ?
        ");

        $stmt->execute([
            $user_id
        ]);

    } catch (PDOException $e) {

        // Ignore error

    }

    header("Location: student_notifications.php");
    exit();
}


// =========================================================
// GET NOTIFICATIONS
// =========================================================

$notifications = [];

try {

    $stmt = $conn->prepare("
        SELECT
            n.id,
            n.type,
            n.title,
            n.message,
            n.question_id,
            n.comment_id,
            n.is_read,
            n.created_at,

            c.comment AS comment_text,

            q.title AS question_title,

            u.full_name AS sender_name

        FROM notifications n

        LEFT JOIN help_comments c
            ON c.id = n.comment_id

        LEFT JOIN help_questions q
            ON q.id = n.question_id

        LEFT JOIN users u
            ON u.user_id = c.user_id

        WHERE n.user_id = ?

        ORDER BY n.created_at DESC
    ");

    $stmt->execute([
        $user_id
    ]);

    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $notifications = [];

}


// =========================================================
// UNREAD COUNT
// =========================================================

$unread_count = 0;

try {

    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM notifications
        WHERE user_id = ?
        AND is_read = 0
    ");

    $stmt->execute([
        $user_id
    ]);

    $unread_count = (int) $stmt->fetchColumn();

} catch (PDOException $e) {

    $unread_count = 0;

}


// =========================================================
// PROFILE INITIAL
// =========================================================

$profile_initial = 'S';

if (!empty($student_name)) {

    $profile_initial = mb_strtoupper(
        mb_substr(
            trim($student_name),
            0,
            1
        )
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
        Notifications | NoteSphere
    </title>


    <!-- =====================================================
         AUTOMATIC THEME
         No theme button on this page.
    ====================================================== -->

    <script>

        (function () {

            const savedTheme =
                localStorage.getItem('notesphere_theme') ||
                localStorage.getItem('theme') ||
                'dark';

            document.documentElement.setAttribute(
                'data-theme',
                savedTheme
            );

        })();

    </script>


    <style>

        /* =====================================================
           RESET
        ====================================================== */

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }


        /* =====================================================
           DARK THEME DEFAULT
        ====================================================== */

        :root {

            --bg: #050505;

            --topbar: rgba(8, 8, 8, 0.96);

            --card: #0d0d0d;

            --card-hover: #151515;

            --border: #252525;

            --border-hover: #383838;

            --text: #f8fafc;

            --sub-text: #bdbdbd;

            --muted: #777777;

            --icon-bg: #182414;

            --green: #39ff14;

            --green-soft: #0e170c;

            --button: #171717;

            --button-hover: #2b2b2b;

            --shadow: rgba(0, 0, 0, 0.35);

        }


        /* =====================================================
           LIGHT THEME
        ====================================================== */

        html[data-theme="light"] {

            --bg: #f4f7f5;

            --topbar: rgba(255, 255, 255, 0.96);

            --card: #ffffff;

            --card-hover: #f1f5f2;

            --border: #dfe6e1;

            --border-hover: #cbd6cf;

            --text: #17211b;

            --sub-text: #59635d;

            --muted: #7a847e;

            --icon-bg: #e8f6e5;

            --green: #24a812;

            --green-soft: #edf9ea;

            --button: #edf1ee;

            --button-hover: #e1e7e3;

            --shadow: rgba(0, 0, 0, 0.08);

        }


        /* =====================================================
           HTML / BODY
        ====================================================== */

        html,
        body {

            min-height: 100%;

            background:
                var(--bg);

        }


        /* =====================================================
           BODY
        ====================================================== */

        body {

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            color:
                var(--text);

            min-height:
                100vh;

            background:
                linear-gradient(
                    rgba(5, 5, 5, 0.72),
                    rgba(5, 5, 5, 0.72)
                ),
                url('../assets/registerbg.png')
                center center /
                cover fixed
                no-repeat;

            transition:
                background 0.25s ease,
                color 0.25s ease;

        }


        /* =====================================================
           LIGHT MODE BACKGROUND
        ====================================================== */

        html[data-theme="light"] body {

            background:
                linear-gradient(
                    rgba(244, 247, 245, 0.82),
                    rgba(244, 247, 245, 0.82)
                ),
                url('../assets/registerbg.png')
                center center /
                cover fixed
                no-repeat;

        }


        /* =====================================================
           TOP BAR
        ====================================================== */

        .topbar {

            position:
                sticky;

            top:
                0;

            z-index:
                1000;

            height:
                70px;

            background:
                var(--topbar);

            border-bottom:
                1px solid var(--border);

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            padding:
                0 25px;

            backdrop-filter:
                blur(12px);

            transition:
                background 0.25s ease,
                border-color 0.25s ease;

        }


        /* =====================================================
           TOP LEFT
        ====================================================== */

        .top-left {

            width:
                180px;

            display:
                flex;

            align-items:
                center;

        }


        .back-btn {

            width:
                42px;

            height:
                42px;

            border:
                none;

            border-radius:
                50%;

            background:
                var(--button);

            color:
                var(--text);

            font-size:
                22px;

            cursor:
                pointer;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            transition:
                0.2s;

        }


        .back-btn:hover {

            background:
                var(--button-hover);

            transform:
                scale(1.04);

        }


        .brand {

            margin-left:
                12px;

            font-size:
                19px;

            font-weight:
                700;

            color:
                var(--green);

        }


        /* =====================================================
           NAV
        ====================================================== */

        .top-nav {

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            gap:
                8px;

            flex:
                1;

        }


        .nav-item {

            width:
                115px;

            height:
                58px;

            border-radius:
                10px;

            display:
                flex;

            flex-direction:
                column;

            align-items:
                center;

            justify-content:
                center;

            text-decoration:
                none;

            color:
                var(--muted);

            position:
                relative;

            transition:
                0.2s;

        }


        .nav-item:hover {

            background:
                var(--card-hover);

            color:
                var(--text);

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

            bottom:
                0;

            left:
                20px;

            right:
                20px;

            height:
                3px;

            background:
                var(--green);

            border-radius:
                10px;

        }


        .nav-item svg {

            width:
                22px;

            height:
                22px;

            margin-bottom:
                3px;

        }


        .nav-item span {

            font-size:
                11px;

        }


        /* =====================================================
           NOTIFICATION BADGE
        ====================================================== */

        .notification-icon {

            position:
                relative;

        }


        .notification-count {

            position:
                absolute;

            top:
                5px;

            right:
                25px;

            min-width:
                17px;

            height:
                17px;

            padding:
                0 4px;

            background:
                #ff3b30;

            color:
                #ffffff;

            border-radius:
                20px;

            font-size:
                9px;

            font-weight:
                800;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            border:
                2px solid var(--topbar);

        }


        /* =====================================================
           TOP RIGHT
        ====================================================== */

        .top-right {

            width:
                220px;

            display:
                flex;

            justify-content:
                flex-end;

            align-items:
                center;

            gap:
                7px;

        }


        /* =====================================================
           PROFILE
        ====================================================== */

        .profile-btn {

            display:
                flex;

            align-items:
                center;

            gap:
                8px;

            text-decoration:
                none;

            color:
                var(--text);

            padding:
                8px 10px;

            border-radius:
                10px;

            transition:
                0.2s;

        }


        .profile-btn:hover {

            background:
                var(--card-hover);

        }


        .profile-circle {

            width:
                36px;

            height:
                36px;

            border-radius:
                50%;

            background:
                var(--green);

            color:
                #050505;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            font-weight:
                700;

            flex-shrink:
                0;

        }


        .profile-name {

            max-width:
                105px;

            white-space:
                nowrap;

            overflow:
                hidden;

            text-overflow:
                ellipsis;

            font-size:
                13px;

            color:
                var(--text);

        }


        /* =====================================================
           MAIN
        ====================================================== */

        .page {

            width:
                100%;

            max-width:
                750px;

            margin:
                auto;

            padding:
                28px 15px 60px;

        }


        /* =====================================================
           HEADING
        ====================================================== */

        .heading {

            display:
                flex;

            justify-content:
                space-between;

            align-items:
                center;

            margin-bottom:
                18px;

        }


        .heading h1 {

            font-size:
                27px;

            color:
                var(--text);

        }


        .mark-read {

            color:
                var(--green);

            text-decoration:
                none;

            font-size:
                12px;

            font-weight:
                700;

            padding:
                7px 10px;

            border-radius:
                7px;

            transition:
                0.2s;

        }


        .mark-read:hover {

            background:
                var(--green-soft);

            text-decoration:
                none;

        }


        /* =====================================================
           NOTIFICATION CARD
        ====================================================== */

        .notification {

            display:
                flex;

            gap:
                13px;

            padding:
                16px;

            background:
                var(--card);

            border:
                1px solid var(--border);

            border-radius:
                14px;

            margin-bottom:
                10px;

            text-decoration:
                none;

            color:
                var(--text);

            transition:
                0.2s;

            position:
                relative;

            box-shadow:
                0 4px 14px var(--shadow);

        }


        .notification:hover {

            background:
                var(--card-hover);

            border-color:
                var(--border-hover);

            transform:
                translateY(-1px);

        }


        .notification.unread {

            background:
                var(--green-soft);

            border-left:
                4px solid var(--green);

        }


        .notification-icon-box {

            width:
                45px;

            height:
                45px;

            border-radius:
                50%;

            background:
                var(--icon-bg);

            color:
                var(--green);

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            flex-shrink:
                0;

            font-size:
                20px;

        }


        .notification-content {

            flex:
                1;

            min-width:
                0;

        }


        .notification-title {

            font-weight:
                700;

            font-size:
                14px;

            margin-bottom:
                4px;

            color:
                var(--text);

            padding-right:
                20px;

        }


        .notification-message {

            color:
                var(--sub-text);

            font-size:
                13px;

            line-height:
                1.45;

            word-break:
                break-word;

        }


        /* =====================================================
           POST REFERENCE
        ====================================================== */

        .notification-post {

            margin-top:
                8px;

            padding:
                7px 9px;

            border-radius:
                8px;

            background:
                var(--button);

            color:
                var(--sub-text);

            font-size:
                11px;

            line-height:
                1.4;

        }


        .notification-post strong {

            color:
                var(--text);

        }


        /* =====================================================
           COMMENT PREVIEW
        ====================================================== */

        .comment-preview {

            margin-top:
                7px;

            padding:
                8px 10px;

            border-left:
                2px solid var(--green);

            background:
                var(--button);

            border-radius:
                7px;

            color:
                var(--sub-text);

            font-size:
                12px;

            line-height:
                1.45;

            word-break:
                break-word;

        }


        .comment-label {

            display:
                block;

            color:
                var(--green);

            font-size:
                10px;

            font-weight:
                700;

            margin-bottom:
                3px;

            text-transform:
                uppercase;

        }


        .notification-time {

            color:
                var(--muted);

            font-size:
                10px;

            margin-top:
                7px;

        }


        .unread-dot {

            width:
                9px;

            height:
                9px;

            border-radius:
                50%;

            background:
                var(--green);

            position:
                absolute;

            right:
                15px;

            top:
                19px;

        }


        /* =====================================================
           EMPTY
        ====================================================== */

        .empty {

            background:
                var(--card);

            border:
                1px solid var(--border);

            border-radius:
                15px;

            padding:
                55px 20px;

            text-align:
                center;

            color:
                var(--muted);

            box-shadow:
                0 4px 14px var(--shadow);

        }


        .empty-icon {

            width:
                70px;

            height:
                70px;

            margin:
                0 auto 15px;

            border-radius:
                50%;

            background:
                var(--icon-bg);

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            font-size:
                32px;

        }


        .empty h3 {

            color:
                var(--text);

            margin-bottom:
                7px;

            font-size:
                16px;

        }


        .empty p {

            color:
                var(--sub-text);

            font-size:
                13px;

            line-height:
                1.5;

            max-width:
                420px;

            margin:
                auto;

        }


        /* =====================================================
           RESPONSIVE
        ====================================================== */

        @media (max-width: 850px) {

            .topbar {

                padding:
                    0 15px;

            }

            .top-left {

                width:
                    150px;

            }

            .top-right {

                width:
                    150px;

            }

        }


        @media (max-width: 750px) {

            .topbar {

                padding:
                    0 10px;

            }

            .top-left {

                width:
                    auto;

            }

            .brand {

                display:
                    none;

            }

            .top-right {

                width:
                    auto;

            }

            .profile-name {

                display:
                    none;

            }

            .nav-item {

                width:
                    70px;

            }

            .nav-item span {

                font-size:
                    9px;

            }

        }


        @media (max-width: 480px) {

            .topbar {

                height:
                    62px;

            }

            .back-btn {

                width:
                    38px;

                height:
                    38px;

            }

            .profile-circle {

                width:
                    34px;

                height:
                    34px;

            }

            .top-nav {

                gap:
                    2px;

            }

            .nav-item {

                width:
                    58px;

                height:
                    54px;

            }

            .nav-item svg {

                width:
                    19px;

                height:
                    19px;

            }

            .nav-item span {

                font-size:
                    8px;

            }

            .notification-count {

                right:
                    11px;

            }

            .page {

                padding:
                    20px 10px 50px;

            }

            .heading h1 {

                font-size:
                    23px;

            }

            .heading {

                align-items:
                    flex-start;

                gap:
                    10px;

            }

            .mark-read {

                font-size:
                    11px;

            }

            .notification {

                padding:
                    13px;

                gap:
                    10px;

            }

            .notification-icon-box {

                width:
                    40px;

                height:
                    40px;

                font-size:
                    18px;

            }

            .notification-title {

                font-size:
                    13px;

            }

            .notification-message {

                font-size:
                    12px;

            }

            .notification-post {

                font-size:
                    10px;

            }

            .comment-preview {

                font-size:
                    11px;

            }

        }

    </style>

</head>


<body>


<!-- =========================================================
     TOP BAR
========================================================== -->

<header class="topbar">


    <!-- BACK -->

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


    <!-- NAV -->

    <nav class="top-nav">


        <!-- HOME -->

        <a
            href="help_board.php"
            class="nav-item"
        >

            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
            >

                <path d="M3 10.5L12 3l9 7.5" />

                <path d="M5 9.5V21h14V9.5" />

                <path d="M9 21v-7h6v7" />

            </svg>

            <span>
                Home
            </span>

        </a>


        <!-- HISTORY -->

        <a
            href="student_history.php"
            class="nav-item"
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
            class="nav-item active notification-icon"
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


            <?php if ($unread_count > 0): ?>

                <span class="notification-count">

                    <?php
                    echo $unread_count > 99
                        ? '99+'
                        : $unread_count;
                    ?>

                </span>

            <?php endif; ?>


            <span>
                Notifications
            </span>

        </a>

    </nav>


    <!-- RIGHT SIDE -->

    <div class="top-right">


        <!-- PROFILE -->

        <a
            href="student_profile.php"
            class="profile-btn"
            title="<?php echo h($student_name); ?>"
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


<!-- =========================================================
     MAIN CONTENT
========================================================== -->

<main class="page">


    <div class="heading">

        <h1>
            Notifications
        </h1>


        <?php if ($unread_count > 0): ?>

            <a
                href="student_notifications.php?mark_all=1"
                class="mark-read"
            >
                Mark all as read
            </a>

        <?php endif; ?>

    </div>


    <?php if (!empty($notifications)): ?>


        <?php foreach ($notifications as $notification): ?>

            <a
                href="student_notifications.php?open=<?php echo (int) $notification['id']; ?>"
                class="notification <?php echo !$notification['is_read'] ? 'unread' : ''; ?>"
            >


                <!-- ICON -->

                <div class="notification-icon-box">

                    <?php
                    if ($notification['type'] === 'comment') {
                        echo '💬';
                    } else {
                        echo '🔔';
                    }
                    ?>

                </div>


                <!-- CONTENT -->

                <div class="notification-content">


                    <!-- TITLE -->

                    <div class="notification-title">

                        <?php
                        echo h(
                            $notification['title']
                        );
                        ?>

                    </div>


                    <!-- MESSAGE -->

                    <div class="notification-message">

                        <?php
                        echo h(
                            $notification['message']
                        );
                        ?>

                    </div>


                    <!-- POST REFERENCE -->

                    <?php if (!empty($notification['question_title'])): ?>

                        <div class="notification-post">

                            <strong>
                                Your post:
                            </strong>

                            <?php
                            echo h(
                                $notification['question_title']
                            );
                            ?>

                        </div>

                    <?php endif; ?>


                    <!-- COMMENT PREVIEW -->

                    <?php if (
                        !empty($notification['comment_text'])
                    ): ?>

                        <div class="comment-preview">

                            <span class="comment-label">
                                Comment
                            </span>

                            <?php
                            echo h(
                                $notification['comment_text']
                            );
                            ?>

                        </div>

                    <?php endif; ?>


                    <!-- TIME -->

                    <div class="notification-time">

                        <?php

                        $timestamp = strtotime(
                            $notification['created_at']
                        );

                        echo $timestamp
                            ? date(
                                'M d, Y · h:i A',
                                $timestamp
                            )
                            : '';

                        ?>

                    </div>

                </div>


                <!-- UNREAD DOT -->

                <?php if (!$notification['is_read']): ?>

                    <div class="unread-dot"></div>

                <?php endif; ?>


            </a>

        <?php endforeach; ?>


    <?php else: ?>


        <!-- EMPTY STATE -->

        <div class="empty">

            <div class="empty-icon">
                🔔
            </div>

            <h3>
                No notifications yet
            </h3>

            <p>
                You will see notifications here when someone comments on your post.
            </p>

        </div>


    <?php endif; ?>


</main>


<!-- =========================================================
     JAVASCRIPT
========================================================== -->

<script>


    // =====================================================
    // THEME
    // =====================================================

    function syncStudentTheme() {

        const savedTheme =
            localStorage.getItem('notesphere_theme') ||
            localStorage.getItem('theme') ||
            'dark';

        document.documentElement.setAttribute(
            'data-theme',
            savedTheme
        );

    }


    // Initial theme

    syncStudentTheme();


    // =====================================================
    // DETECT THEME CHANGES FROM OTHER NOTESPHERE PAGES
    // =====================================================

    window.addEventListener(
        'storage',
        function (event) {

            if (
                event.key === 'notesphere_theme' ||
                event.key === 'theme'
            ) {

                syncStudentTheme();

            }

        }
    );


</script>


</body>

</html>