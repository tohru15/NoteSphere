<?php
// student_dashboard.php
session_start();

// Security check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../auth/login.php");
    exit();
}

// ===============================
// DATABASE CONNECTION
// ===============================
$host = 'localhost';
$dbname = 'cdm_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database Connection Error: " . $e->getMessage());
}

$user_id = $_SESSION['user_id'];

// ===============================
// FETCH STUDENT INFORMATION
// ===============================
try {
    $stmt_user = $pdo->prepare("
        SELECT full_name, course, year_level, section
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    $stmt_user->execute([$user_id]);
    $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);

    $fullname = $user_data['full_name']
        ?? $_SESSION['fullname']
        ?? 'Student';

} catch (Exception $e) {
    $fullname = $_SESSION['fullname'] ?? 'Student';
}

// ===============================
// FETCH ACTIVE SEMESTER & SCHOOL YEAR
// IMPORTANT:
// Same columns used in professor_announcements.php
// ===============================
try {

    $stmt_settings = $pdo->query("
        SELECT current_semester, current_academic_year
        FROM system_settings
        LIMIT 1
    ");

    $current_settings = $stmt_settings->fetch(PDO::FETCH_ASSOC);

    $active_semester = !empty($current_settings['current_semester'])
        ? trim($current_settings['current_semester'])
        : '1st Semester';

    $active_year = !empty($current_settings['current_academic_year'])
        ? trim($current_settings['current_academic_year'])
        : '2026-2027';

} catch (Exception $e) {

    $active_semester = '1st Semester';
    $active_year = '2026-2027';
}

// ===============================
// FETCH PROFESSORS + ANNOUNCEMENTS
// ===============================
//
// IMPORTANT:
// Only announcements that are:
// 1. Approved
// 2. Current semester
// 3. Current academic year
//
// are shown/count.
//
// unread_announcements:
// Announcement is unread if there is NO matching
// record in announcement_reads for this student.
//
// This follows the same logic as professor_announcements.php.
// ===============================

$professors = [];

try {

    $stmt = $pdo->prepare("
        SELECT
            u.id,
            u.full_name,

            COUNT(DISTINCT a.id) AS total_announcements,

            COUNT(
                DISTINCT CASE
                    WHEN ar.id IS NULL THEN a.id
                    ELSE NULL
                END
            ) AS unread_announcements

        FROM users u

        LEFT JOIN announcements a
            ON a.professor_id = u.id
            AND a.status = 'approved'
            AND TRIM(a.semester) = TRIM(?)
            AND TRIM(a.academic_year) = TRIM(?)

        LEFT JOIN announcement_reads ar
            ON ar.announcement_id = a.id
            AND ar.student_id = ?

        WHERE u.role = 'professor'

        GROUP BY u.id, u.full_name

        HAVING COUNT(DISTINCT a.id) > 0

        ORDER BY u.full_name ASC
    ");

    $stmt->execute([
        $active_semester,
        $active_year,
        $user_id
    ]);

    $professors = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {

    // If announcement_reads table has an issue,
    // still show current approved announcements.

    try {

        $stmt_fallback = $pdo->prepare("
            SELECT
                u.id,
                u.full_name,
                COUNT(DISTINCT a.id) AS total_announcements,
                0 AS unread_announcements

            FROM users u

            LEFT JOIN announcements a
                ON a.professor_id = u.id
                AND a.status = 'approved'
                AND TRIM(a.semester) = TRIM(?)
                AND TRIM(a.academic_year) = TRIM(?)

            WHERE u.role = 'professor'

            GROUP BY u.id, u.full_name

            HAVING COUNT(DISTINCT a.id) > 0

            ORDER BY u.full_name ASC
        ");

        $stmt_fallback->execute([
            $active_semester,
            $active_year
        ]);

        $professors = $stmt_fallback->fetchAll(PDO::FETCH_ASSOC);

    } catch (Exception $ex) {

        $professors = [];
    }
}

// ===============================
// TOTAL UNREAD COUNT
// ===============================
$total_unread = 0;

foreach ($professors as $prof) {
    $total_unread += (int)$prof['unread_announcements'];
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>NoteSphere - Student Dashboard</title>

    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <style>

        :root {
            --neon: #39FF14;
            --neon-glow: rgba(57, 255, 20, 0.12);
            --card-bg: #0c0c0c;
            --text: #f8fafc;
            --sub-text: #94a3b8;
            --border: #1f293d;
            --sidebar-width: 260px;
            --danger: #ff4d4d;
        }

        body.light-mode {
            --bg: url('../assets/registerbg.png') no-repeat center fixed;
            --card-bg: #ffffff;
            --text: #000000;
            --sidebar-bg: #ffffff;
            --border: #e2e8f0;
            --sub-text: #000000;
            --neon: #2ecc71;
            --neon-glow: rgba(46, 204, 113, 0.12);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        html,
        body {
            width: 100%;
            max-width: 100vw;
            overflow-x: hidden;
        }

        body {
            position: relative;
            color: var(--text);
            display: flex;
            min-height: 100vh;
            transition: color 0.3s ease;
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
                url('../assets/registerbg.png')
                no-repeat
                center
                center;

            background-size: cover;
        }

        /* =========================
           SIDEBAR
        ========================= */

        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;

            background: var(--card-bg);

            border-right: 1px solid var(--border);

            padding: 35px 20px;

            position: fixed;
            top: 0;
            left: 0;

            display: flex;
            flex-direction: column;
            justify-content: space-between;

            z-index: 1002;

            transition:
                transform 0.3s ease,
                background 0.3s ease,
                border-color 0.3s ease;

            overflow-y: auto;
        }

        .sidebar-top {
            display: flex;
            flex-direction: column;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;

            margin-bottom: 30px;
            padding-left: 5px;
        }

        .sidebar-brand h2 {
            font-weight: 800;
            color: var(--neon);
            font-size: 1.4rem;
        }

        .sidebar-brand img {
            width: 35px;
            height: auto;
        }

        .menu {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .menu a {
            display: flex;
            align-items: center;
            gap: 14px;

            padding: 12px 16px;

            text-decoration: none;
            color: var(--sub-text);

            font-weight: 600;
            font-size: 0.92rem;

            border-radius: 12px;

            transition: all 0.3s ease;
        }

        .menu a svg {
            width: 20px;
            height: 20px;

            stroke: currentColor;
            stroke-width: 2;
            fill: none;

            flex-shrink: 0;
        }

        .menu a:hover,
        .menu a.active {
            background: var(--neon-glow);
            color: var(--neon);
        }

        .menu a.active {
            border: 1px solid rgba(57, 255, 20, 0.2);
        }

        /* =========================
           MORE MENU
        ========================= */

        .more-toggle-btn {
            width: 100%;

            display: flex;
            align-items: center;
            justify-content: space-between;

            padding: 12px 16px;

            background: transparent;
            border: none;

            color: var(--sub-text);

            font-weight: 600;
            font-size: 0.92rem;

            border-radius: 12px;

            cursor: pointer;

            transition: all 0.3s ease;
        }

        .more-toggle-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .more-toggle-left svg,
        .more-chevron {
            width: 20px;
            height: 20px;

            stroke: currentColor;
            stroke-width: 2;
            fill: none;

            flex-shrink: 0;
        }

        .more-chevron {
            transition: transform 0.3s ease;
        }

        .more-toggle-btn:hover {
            background: var(--neon-glow);
            color: var(--neon);
        }

        .submenu-container {
            display: flex;
            flex-direction: column;

            gap: 4px;

            padding-left: 15px;
            margin-top: 4px;
        }

        .submenu-container a {
            padding: 10px 14px;
            font-size: 0.88rem;
        }

        /* =========================
           SIDEBAR FOOTER
        ========================= */

        .sidebar-footer {
            display: flex;
            flex-direction: column;

            gap: 10px;

            margin-top: 20px;
        }

        .theme-toggle-btn {
            width: 100%;

            padding: 12px 16px;

            background: var(--card-bg);

            border: 1px solid var(--border);

            color: var(--text);

            border-radius: 12px;

            font-weight: 600;

            cursor: pointer;

            display: flex;
            align-items: center;

            gap: 12px;

            transition: 0.3s;

            font-size: 0.9rem;

            text-decoration: none;
        }

        .theme-toggle-btn:hover {
            border-color: var(--neon);
            color: var(--neon);
        }

        /* =========================
           MOBILE HEADER
        ========================= */

        .mobile-header {
            display: none;

            align-items: center;
            justify-content: space-between;

            padding: 15px 20px;

            background: var(--card-bg);

            border-bottom: 1px solid var(--border);

            position: fixed;

            top: 0;
            left: 0;

            width: 100%;

            z-index: 1000;

            box-sizing: border-box;
        }

        .sidebar-toggle-btn {
            background: var(--card-bg);

            border: 1px solid var(--border);

            cursor: pointer;

            display: flex;
            align-items: center;
            justify-content: center;

            width: 40px;
            height: 40px;

            border-radius: 10px;

            color: var(--text);
        }

        .sidebar-toggle-btn svg {
            width: 20px;
            height: 20px;

            stroke: currentColor;
            stroke-width: 2;
            fill: none;
        }

        /* =========================
           SIDEBAR OVERLAY
        ========================= */

        .sidebar-overlay {
            display: none;

            position: fixed;

            top: 0;
            left: 0;

            width: 100vw;
            height: 100vh;

            background: rgba(0, 0, 0, 0.6);

            z-index: 1001;

            backdrop-filter: blur(2px);
        }

        /* =========================
           MAIN
        ========================= */

        main {
            margin-left: var(--sidebar-width);

            flex: 1;

            padding: 40px;

            max-width: calc(100vw - var(--sidebar-width));

            width: 100%;

            box-sizing: border-box;

            overflow-x: hidden;
        }

        /* =========================
           WELCOME
        ========================= */

        .welcome-box {
            background:
                linear-gradient(
                    135deg,
                    var(--card-bg),
                    rgba(57, 255, 20, 0.03)
                );

            border: 1px solid var(--border);

            border-radius: 20px;

            padding: 30px;

            margin-bottom: 30px;

            display: flex;

            justify-content: space-between;

            align-items: center;

            flex-wrap: wrap;

            gap: 20px;

            transition:
                background 0.3s ease,
                border-color 0.3s ease;

            box-sizing: border-box;
        }

        .welcome-box h1 {
            font-size: 1.8rem;

            font-weight: 800;

            margin-bottom: 6px;
        }

        .welcome-box p {
            color: var(--sub-text);

            font-size: 0.95rem;
        }

        .semester-badge {
            background: var(--neon-glow);

            color: var(--neon);

            padding: 6px 14px;

            border-radius: 20px;

            font-size: 0.85rem;

            font-weight: 700;

            border: 1px solid var(--neon);
        }

        /* =========================
           SECTION HEADER
        ========================= */

        .section-header {
            margin-bottom: 20px;
            margin-top: 10px;
        }

        .section-header h2 {
            font-size: 1.3rem;
            font-weight: 700;
        }

        .section-header p {
            color: var(--sub-text);

            font-size: 0.88rem;

            margin-top: 4px;
        }

        /* =========================
           PROFESSOR GRID
        ========================= */

        .prof-grid {
            display: grid;

            grid-template-columns:
                repeat(
                    auto-fill,
                    minmax(280px, 1fr)
                );

            gap: 20px;

            width: 100%;
        }

        .prof-card {
            background: var(--card-bg);

            border: 1px solid var(--border);

            border-radius: 16px;

            padding: 24px;

            text-decoration: none;

            color: var(--text);

            transition: all 0.2s ease;

            display: flex;

            flex-direction: column;

            justify-content: space-between;

            box-sizing: border-box;

            position: relative;

            min-height: 190px;
        }

        .prof-card:hover {
            border-color: var(--neon);

            transform: translateY(-3px);

            box-shadow:
                0 10px 25px
                var(--neon-glow);
        }

        .prof-card h3 {
            font-size: 1.15rem;

            font-weight: 700;

            margin-bottom: 6px;

            color: var(--text);

            word-break: break-word;
        }

        .prof-card p {
            font-size: 0.85rem;

            color: var(--sub-text);

            margin-bottom: 20px;

            line-height: 1.6;
        }

        /* =========================
           NOTIFICATION BADGE
        ========================= */

        .notif-badge {
            position: absolute;

            top: 18px;
            right: 18px;

            background: var(--danger);

            color: #ffffff;

            font-size: 0.72rem;

            font-weight: 700;

            padding: 5px 9px;

            border-radius: 20px;

            display: flex;

            align-items: center;

            gap: 4px;

            box-shadow:
                0 4px 10px
                rgba(255, 77, 77, 0.3);

            animation: pulse-badge 2s infinite;

            z-index: 2;
        }

        .notif-badge svg {
            width: 13px;
            height: 13px;

            stroke: currentColor;

            stroke-width: 3;

            fill: none;
        }

        @keyframes pulse-badge {

            0% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.05);
            }

            100% {
                transform: scale(1);
            }

        }

        /* =========================
           PROFESSOR FOOTER
        ========================= */

        .prof-footer {
            display: flex;

            justify-content: space-between;

            align-items: center;

            border-top: 1px solid var(--border);

            padding-top: 14px;

            gap: 10px;

            margin-top: 10px;
        }

        .badge {
            background: var(--neon-glow);

            color: var(--neon);

            padding: 5px 12px;

            font-size: 0.78rem;

            font-weight: 700;

            border-radius: 8px;
        }

        .view-btn {
            font-size: 0.82rem;

            color: var(--neon);

            font-weight: 700;

            text-decoration: none;

            padding: 6px 10px;

            border-radius: 8px;

            transition: 0.2s;
        }

        .view-btn:hover {
            background: var(--neon-glow);
        }

        /* =========================
           EMPTY STATE
        ========================= */

        .empty-state {
            text-align: center;

            padding: 45px 20px;

            background: var(--card-bg);

            border: 1px solid var(--border);

            border-radius: 16px;

            color: var(--sub-text);
        }

        .empty-state-icon {
            font-size: 2.5rem;

            margin-bottom: 10px;
        }

        .empty-state h3 {
            color: var(--text);

            margin-bottom: 6px;
        }

        /* =========================
           MOBILE
        ========================= */

        @media (max-width: 768px) {

            .mobile-header {
                display: flex;
            }

            main {
                margin-left: 0;

                padding:
                    90px
                    16px
                    20px
                    16px;

                max-width: 100vw;
            }

            .sidebar {
                transform: translateX(-100%);
            }

            body.sidebar-open .sidebar {
                transform: translateX(0);
            }

            body.sidebar-open .sidebar-overlay {
                display: block;
            }

            .welcome-box {
                padding: 22px 18px;

                align-items: flex-start;
            }

            .welcome-box h1 {
                font-size: 1.45rem;
            }

            .welcome-box p {
                font-size: 0.85rem;
            }

            .semester-badge {
                font-size: 0.78rem;
            }

            .prof-grid {
                grid-template-columns: 1fr;
            }

            .prof-card {
                min-height: 180px;
            }

        }

        @media (max-width: 480px) {

            main {
                padding-left: 12px;
                padding-right: 12px;
            }

            .welcome-box {
                border-radius: 15px;
                padding: 20px 16px;
            }

            .welcome-box h1 {
                font-size: 1.3rem;
            }

            .section-header h2 {
                font-size: 1.15rem;
            }

            .prof-card {
                padding: 20px;
            }

            .notif-badge {
                top: 15px;
                right: 15px;
            }

            .prof-card h3 {
                font-size: 1.05rem;
                padding-right: 55px;
            }

            .prof-footer {
                flex-wrap: wrap;
            }

        }

    </style>

</head>

<body>

<!-- =========================
     MOBILE HEADER
========================= -->

<header class="mobile-header">

    <div
        style="
            display:flex;
            align-items:center;
            gap:10px;
        "
    >

        <img
            src="../assets/cdmlogo.png"
            alt="Logo"
            style="
                width:30px;
                height:auto;
            "
        >

        <h2
            style="
                font-size:1.2rem;
                color:var(--neon);
            "
        >
            NoteSphere
        </h2>

    </div>

    <button
        class="sidebar-toggle-btn"
        onclick="toggleMobileSidebar()"
        aria-label="Toggle Sidebar"
    >

        <svg viewBox="0 0 24 24">

            <line
                x1="3"
                y1="12"
                x2="21"
                y2="12"
            ></line>

            <line
                x1="3"
                y1="6"
                x2="21"
                y2="6"
            ></line>

            <line
                x1="3"
                y1="18"
                x2="21"
                y2="18"
            ></line>

        </svg>

    </button>

</header>


<!-- =========================
     SIDEBAR OVERLAY
========================= -->

<div
    class="sidebar-overlay"
    onclick="toggleMobileSidebar()"
></div>


<!-- =========================
     SIDEBAR
========================= -->

<nav
    class="sidebar"
    id="sidebar"
>

    <div class="sidebar-top">

        <div class="sidebar-brand">

            <img
                src="../assets/cdmlogo.png"
                alt="Logo"
            >

            <h2>NoteSphere</h2>

        </div>


        <div class="menu">

            <a
                href="student_dashboard.php"
                class="active"
            >

                <svg viewBox="0 0 24 24">

                    <path
                        d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"
                    ></path>

                    <polyline
                        points="9 22 9 12 15 12 15 22"
                    ></polyline>

                </svg>

                <span>Dashboard</span>

            </a>


            <a href="view_notes.php">

                <svg viewBox="0 0 24 24">

                    <path
                        d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"
                    ></path>

                    <path
                        d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"
                    ></path>

                </svg>

                <span>View Notes</span>

            </a>


            <a href="help_board.php">

                <svg viewBox="0 0 24 24">

                    <path
                        d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"
                    ></path>

                    <circle
                        cx="12"
                        cy="10"
                        r="1"
                    ></circle>

                </svg>

                <span>Help Board (Q&A)</span>

            </a>


            <!-- MORE -->

            <div
                class="menu-item-group"
                style="margin-top:5px;"
            >

                <button
                    type="button"
                    onclick="toggleMoreMenu(event)"
                    class="more-toggle-btn"
                    id="moreToggleBtn"
                >

                    <div class="more-toggle-left">

                        <svg viewBox="0 0 24 24">

                            <circle
                                cx="12"
                                cy="12"
                                r="1"
                            ></circle>

                            <circle
                                cx="19"
                                cy="12"
                                r="1"
                            ></circle>

                            <circle
                                cx="5"
                                cy="12"
                                r="1"
                            ></circle>

                        </svg>

                        <span>More</span>

                    </div>

                    <svg
                        class="more-chevron"
                        id="moreChevron"
                        viewBox="0 0 24 24"
                    >

                        <polyline
                            points="6 9 12 15 18 9"
                        ></polyline>

                    </svg>

                </button>


                <div
                    id="moreSubmenu"
                    class="submenu-container"
                    style="display:none;"
                >

                    <a href="archive_sy.php">

                        <svg viewBox="0 0 24 24">

                            <path
                                d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"
                            ></path>

                            <path
                                d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"
                            ></path>

                        </svg>

                        <span>Archive SY</span>

                    </a>


                    <a href="enrollment_update.php">

                        <svg viewBox="0 0 24 24">

                            <path
                                d="M22 10v6M2 10l10-5 10 5-10 5z"
                            ></path>

                            <path
                                d="M6 12v5c3 3 9 3 12 0v-5"
                            ></path>

                        </svg>

                        <span>Enrollment Update</span>

                    </a>


                    <a href="system_guide.php">

                        <svg viewBox="0 0 24 24">

                            <path
                                d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"
                            ></path>

                            <path
                                d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"
                            ></path>

                        </svg>

                        <span>System Guide</span>

                    </a>

                </div>

            </div>

        </div>

    </div>


    <!-- SIDEBAR FOOTER -->

    <div class="sidebar-footer">

        <button
            onclick="toggleGlobalTheme()"
            class="theme-toggle-btn"
            id="globalThemeBtn"
        >

            <span id="themeIcon">🌞</span>

            <span id="themeText">Light Mode</span>

        </button>


        <a
            href="../auth/logout.php"
            class="theme-toggle-btn"
            style="
                color:#ff4d4d;
                border-color:rgba(255,77,77,0.3);
            "
        >

            <svg
                viewBox="0 0 24 24"
                style="
                    width:18px;
                    height:18px;
                    stroke:currentColor;
                    stroke-width:2;
                    fill:none;
                "
            >

                <path
                    d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"
                ></path>

                <polyline
                    points="16 17 21 12 16 7"
                ></polyline>

            </svg>

            <span>Logout</span>

        </a>

    </div>

</nav>


<!-- =========================
     MAIN CONTENT
========================= -->

<main>

    <!-- WELCOME -->

    <div class="welcome-box">

        <div>

            <h1>
                Hello,
                <?php echo htmlspecialchars($fullname); ?>! 👋
            </h1>

            <p>
                Select a professor below to view their announcements,
                quizzes, and modules.
            </p>

        </div>


        <div class="semester-badge">

            <?php
            echo htmlspecialchars(
                $active_semester . ' | ' . $active_year
            );
            ?>

        </div>

    </div>


    <!-- PROFESSORS HEADER -->

    <div class="section-header">

        <h2>
            Professors

            <?php if ($total_unread > 0): ?>

                <span
                    style="
                        display:inline-flex;
                        align-items:center;
                        gap:4px;
                        margin-left:8px;
                        color:#ff4d4d;
                        font-size:0.75rem;
                        vertical-align:middle;
                    "
                >

                    🔔
                    <?php echo $total_unread; ?> unread

                </span>

            <?php endif; ?>

        </h2>

        <p>
            Tap a professor's name to open their announcement board
            or message them for consultation.
        </p>

    </div>


    <!-- =========================
         PROFESSOR CARDS
    ========================= -->

    <?php if (!empty($professors)): ?>

        <div class="prof-grid">

            <?php foreach ($professors as $prof): ?>

                <?php
                $unread =
                    (int)$prof['unread_announcements'];

                $total =
                    (int)$prof['total_announcements'];
                ?>

                <div class="prof-card">


                    <!-- NOTIFICATION -->

                    <?php if ($unread > 0): ?>

                        <div
                            class="notif-badge"
                            title="<?php echo $unread; ?> unread announcement(s);"
                        >

                            <svg viewBox="0 0 24 24">

                                <path
                                    d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"
                                ></path>

                                <path
                                    d="M13.73 21a2 2 0 0 1-3.46 0"
                                ></path>

                            </svg>

                            <span>
                                <?php echo $unread; ?> New
                            </span>

                        </div>

                    <?php endif; ?>


                    <!-- PROFESSOR CONTENT -->

                    <a
                        href="professor_announcements.php?prof_id=<?php echo (int)$prof['id']; ?>"
                        style="
                            text-decoration:none;
                            color:inherit;
                            flex:1;
                            display:flex;
                            flex-direction:column;
                            justify-content:space-between;
                        "
                    >

                        <div>

                            <h3
                                style="
                                    padding-right:
                                    <?php echo ($unread > 0)
                                        ? '70px'
                                        : '0'; ?>;
                                "
                            >

                                Prof.
                                <?php
                                echo htmlspecialchars(
                                    $prof['full_name']
                                );
                                ?>

                            </h3>

                            <p>
                                Click to open updates, assignments,
                                and files from this professor.
                            </p>

                        </div>

                    </a>


                    <!-- FOOTER -->

                    <div class="prof-footer">

                        <span class="badge">

                            <?php echo $total; ?>

                            <?php
                            echo ($total == 1)
                                ? ' Announcement'
                                : ' Announcements';
                            ?>

                        </span>


                        <a
                            href="professor_announcements.php?prof_id=<?php echo (int)$prof['id']; ?>"
                            class="view-btn"
                        >
                            Open →
                        </a>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>


    <?php else: ?>

        <!-- EMPTY STATE -->

        <div class="empty-state">

            <div class="empty-state-icon">
                📚
            </div>

            <h3>
                No Announcements Yet
            </h3>

            <p>
                There are no approved professor announcements
                for the current semester and school year.
            </p>

        </div>

    <?php endif; ?>

</main>


<!-- =========================
     JAVASCRIPT
========================= -->

<script>

    // =========================
    // MOBILE SIDEBAR
    // =========================

    function toggleMobileSidebar() {

        document.body.classList.toggle(
            'sidebar-open'
        );

    }


    // =========================
    // MORE MENU
    // =========================

    function toggleMoreMenu(event) {

        event.preventDefault();

        const submenu =
            document.getElementById('moreSubmenu');

        const chevron =
            document.getElementById('moreChevron');


        if (
            submenu.style.display === 'none' ||
            submenu.style.display === ''
        ) {

            submenu.style.display = 'flex';

            chevron.style.transform =
                'rotate(180deg)';

        } else {

            submenu.style.display = 'none';

            chevron.style.transform =
                'rotate(0deg)';
        }

    }


    // =========================
    // THEME
    // =========================

    function toggleGlobalTheme() {

        document.body.classList.toggle(
            'light-mode'
        );

        const isLight =
            document.body.classList.contains(
                'light-mode'
            );

        localStorage.setItem(
            'notesphere_theme',
            isLight ? 'light' : 'dark'
        );

        updateThemeButton(isLight);
    }


    function updateThemeButton(isLight) {

        document.getElementById(
            'themeIcon'
        ).textContent =
            isLight ? '🌙' : '🌞';


        document.getElementById(
            'themeText'
        ).textContent =
            isLight ? 'Dark Mode' : 'Light Mode';

    }


    // =========================
    // LOAD SAVED THEME
    // =========================

    if (
        localStorage.getItem(
            'notesphere_theme'
        ) === 'light'
    ) {

        document.body.classList.add(
            'light-mode'
        );

        updateThemeButton(true);

    }

</script>

</body>
</html>