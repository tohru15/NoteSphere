<?php

// =========================================================
// NoteSphere - Student Dashboard
// =========================================================

session_start();

// =========================================================
// SECURITY CHECK
// =========================================================

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'student'
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

} catch (PDOException $e) {

    die(
        "Database Connection Error: " .
        $e->getMessage()
    );
}

$user_id = (int) $_SESSION['user_id'];

// =========================================================
// DEFAULT VALUES
// =========================================================

$fullname = 'Student';
$course = '';
$year_level = '';
$section = '';

$active_semester = '1st Semester';
$active_year = '2026-2027';

$professors = [];
$total_unread = 0;

// =========================================================
// STORAGE - USE ADMIN DASHBOARD SETTINGS
// =========================================================

require_once __DIR__ . '/../storage_helper.php';

// Fallback only if no valid admin quota is saved.
$default_quota = 500 * 1024 * 1024 * 1024;

$student_storage = [
    'used_bytes' => 0,
    'quota_bytes' => $default_quota
];

$admin_student_quota_bytes = null;

// 1. Read the current student quota saved by the admin.
try {
    $stmt_storage_settings = $pdo->prepare("
        SELECT student_storage_gb
        FROM system_storage
        WHERE id = 1
        LIMIT 1
    ");

    $stmt_storage_settings->execute();

    $storage_settings = $stmt_storage_settings->fetch(
        PDO::FETCH_ASSOC
    );

    if (
        $storage_settings &&
        isset($storage_settings['student_storage_gb']) &&
        is_numeric($storage_settings['student_storage_gb']) &&
        (float) $storage_settings['student_storage_gb'] > 0
    ) {
        $admin_student_quota_bytes =
            (float) $storage_settings['student_storage_gb']
            * 1024 * 1024 * 1024;
    }
} catch (PDOException $e) {
    error_log(
        'NoteSphere student storage settings error: ' .
        $e->getMessage()
    );
}

// 2. Get the student's actual storage usage.
try {
    $student_storage = ns_get_user_storage(
        $pdo,
        $user_id,
        'student'
    );
} catch (Throwable $e) {
    error_log(
        'NoteSphere student storage usage error: ' .
        $e->getMessage()
    );
}

// 3. Calculate usage and quota.
// The admin's saved quota takes priority over any hardcoded
// or outdated quota returned by storage_helper.php.

$used_bytes = isset($student_storage['used_bytes'])
    ? max(0, (float) $student_storage['used_bytes'])
    : 0;

if (
    $admin_student_quota_bytes !== null &&
    $admin_student_quota_bytes > 0
) {
    $quota_bytes = $admin_student_quota_bytes;
} elseif (
    isset($student_storage['quota_bytes']) &&
    is_numeric($student_storage['quota_bytes']) &&
    (float) $student_storage['quota_bytes'] > 0
) {
    $quota_bytes = (float) $student_storage['quota_bytes'];
} else {
    $quota_bytes = $default_quota;
}

// Remaining storage is calculated using the latest quota.
$remaining_bytes = max(
    0,
    $quota_bytes - $used_bytes
);

// =========================================================
// STORAGE FORMATTER
// =========================================================

function formatStorageSize($bytes)
{
    $bytes = max(0, (float) $bytes);

    if ($bytes >= 1024 * 1024 * 1024) {
        return number_format(
            $bytes / (1024 * 1024 * 1024),
            2
        ) . ' GB';
    }

    if ($bytes >= 1024 * 1024) {
        return number_format(
            $bytes / (1024 * 1024),
            2
        ) . ' MB';
    }

    if ($bytes >= 1024) {
        return number_format(
            $bytes / 1024,
            2
        ) . ' KB';
    }

    return number_format($bytes, 0) . ' B';
}

$used_text = formatStorageSize($used_bytes);
$quota_text = formatStorageSize($quota_bytes);
$remaining_text = formatStorageSize($remaining_bytes);

// =========================================================
// STORAGE STATUS
// =========================================================

if ($remaining_bytes <= 0) {
    $storage_status = 'Full';
    $storage_status_class = 'storage-danger';
} elseif ($used_bytes >= ($quota_bytes * 0.90)) {
    $storage_status = 'Almost Full';
    $storage_status_class = 'storage-danger';
} elseif ($used_bytes >= ($quota_bytes * 0.75)) {
    $storage_status = 'Getting Full';
    $storage_status_class = 'storage-warning';
} else {
    $storage_status = 'Available';
    $storage_status_class = 'storage-good';
}


// =========================================================
// FETCH STUDENT INFORMATION
// =========================================================

try {

    $stmt_user = $pdo->prepare("
        SELECT
            user_id,
            full_name,
            email,
            course,
            year_level,
            section,
            student_status,
            profile_pic
        FROM users
        WHERE user_id = ?
        LIMIT 1
    ");

    $stmt_user->execute([
        $user_id
    ]);

    $user_data =
        $stmt_user->fetch(PDO::FETCH_ASSOC);

    if ($user_data) {

        $fullname =
            $user_data['full_name']
            ?? 'Student';

        $course =
            $user_data['course']
            ?? '';

        $year_level =
            $user_data['year_level']
            ?? '';

        $section =
            $user_data['section']
            ?? '';
    }

} catch (PDOException $e) {

    $fullname =
        $_SESSION['fullname']
        ?? 'Student';
}

// =========================================================
// STUDENT INFORMATION TEXT
// =========================================================

$student_details = [];

if ($course !== '') {

    $student_details[] =
        $course;
}

if ($year_level !== '') {

    $student_details[] =
        'Year ' . $year_level;
}

if ($section !== '') {

    $student_details[] =
        'Section ' . $section;
}

$student_details_text =
    !empty($student_details)
        ? implode(' • ', $student_details)
        : 'Student Account';

// =========================================================
// FETCH ACTIVE SEMESTER & SCHOOL YEAR
// =========================================================

try {

    $stmt_settings = $pdo->prepare("
        SELECT
            current_semester,
            current_academic_year
        FROM system_settings
        WHERE id = 1
        LIMIT 1
    ");

    $stmt_settings->execute();

    $current_settings =
        $stmt_settings->fetch(PDO::FETCH_ASSOC);

    if ($current_settings) {

        if (
            !empty(
                $current_settings['current_semester']
            )
        ) {

            $active_semester =
                trim(
                    $current_settings[
                        'current_semester'
                    ]
                );
        }

        if (
            !empty(
                $current_settings[
                    'current_academic_year'
                ]
            )
        ) {

            $active_year =
                trim(
                    $current_settings[
                        'current_academic_year'
                    ]
                );
        }
    }

} catch (PDOException $e) {

    $active_semester =
        '1st Semester';

    $active_year =
        '2026-2027';
}

// =========================================================
// FETCH PROFESSORS WITH CURRENT ANNOUNCEMENTS
// =========================================================

try {

    $stmt = $pdo->prepare("
        SELECT
            u.user_id,
            u.full_name,

            COUNT(
                DISTINCT a.id
            ) AS total_announcements,

            COUNT(
                DISTINCT CASE
                    WHEN ar.id IS NULL
                    THEN a.id
                    ELSE NULL
                END
            ) AS unread_announcements

        FROM users u

        INNER JOIN announcements a
            ON a.professor_id = u.user_id
            AND a.status = 'active'
            AND TRIM(a.semester) = TRIM(?)
            AND TRIM(a.academic_year) = TRIM(?)

        LEFT JOIN announcement_reads ar
            ON ar.announcement_id = a.id
            AND ar.student_id = ?

        WHERE
            u.role = 'professor'

        GROUP BY
            u.user_id,
            u.full_name

        HAVING
            COUNT(DISTINCT a.id) > 0

        ORDER BY
            u.full_name ASC
    ");

    $stmt->execute([
        $active_semester,
        $active_year,
        $user_id
    ]);

    $professors =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (PDOException $e) {

    // =====================================================
    // FALLBACK
    // =====================================================

    try {

        $stmt_fallback = $pdo->prepare("
            SELECT
                u.user_id,
                u.full_name,

                COUNT(
                    DISTINCT a.id
                ) AS total_announcements,

                0 AS unread_announcements

            FROM users u

            INNER JOIN announcements a
                ON a.professor_id = u.user_id
                AND a.status = 'active'
                AND TRIM(a.semester) = TRIM(?)
                AND TRIM(a.academic_year) = TRIM(?)

            WHERE
                u.role = 'professor'

            GROUP BY
                u.user_id,
                u.full_name

            HAVING
                COUNT(DISTINCT a.id) > 0

            ORDER BY
                u.full_name ASC
        ");

        $stmt_fallback->execute([
            $active_semester,
            $active_year
        ]);

        $professors =
            $stmt_fallback->fetchAll(
                PDO::FETCH_ASSOC
            );

    } catch (PDOException $fallbackError) {

        $professors = [];
    }
}

// =========================================================
// TOTAL UNREAD
// =========================================================

$total_unread = 0;

foreach ($professors as $prof) {

    $total_unread +=
        (int) (
            $prof['unread_announcements']
            ?? 0
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
    NoteSphere - Student Dashboard
</title>

<link
    href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
    rel="stylesheet"
>

<style>

/* =========================================================
   VARIABLES
========================================================= */

:root {

    --neon: #39FF14;
    --neon-glow: rgba(57, 255, 20, 0.12);

    --card-bg: #0c0c0c;

    --text: #f8fafc;
    --sub-text: #94a3b8;

    --border: #1f293d;

    --sidebar-width: 260px;

    --danger: #ff4d4d;
    --warning: #f59e0b;

    --storage-bg: rgba(57, 255, 20, 0.07);
}


/* =========================================================
   LIGHT MODE
========================================================= */

body.light-mode {

    --card-bg: rgba(255, 255, 255, 0.92);

    --text: #111827;

    --sub-text: #475569;

    --border: rgba(15, 23, 42, 0.12);

    --neon: #22a447;

    --neon-glow: rgba(34, 164, 71, 0.12);

    --storage-bg: rgba(34, 164, 71, 0.08);
}


/* =========================================================
   RESET
========================================================= */

* {

    margin: 0;
    padding: 0;

    box-sizing: border-box;

    font-family:
        'Plus Jakarta Sans',
        sans-serif;
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

    transition:
        color 0.3s ease;
}


/* =========================================================
   BACKGROUND
========================================================= */

body::before {

    content: '';

    position: fixed;

    top: 0;
    left: 0;

    width: 100vw;
    height: 100vh;

    z-index: -2;

    background:
        linear-gradient(
            rgba(11, 11, 11, 0.84),
            rgba(11, 11, 11, 0.91)
        ),
        url('../assets/registerbg.png')
        no-repeat
        center
        center;

    background-size: cover;

    pointer-events: none;
}


/* =========================================================
   LIGHT MODE BACKGROUND
========================================================= */

body.light-mode::before {

    background:
        linear-gradient(
            rgba(255, 255, 255, 0.18),
            rgba(255, 255, 255, 0.18)
        ),
        url('../assets/registerbg.png')
        no-repeat
        center
        center;

    background-size: cover;
}


/* =========================================================
   SIDEBAR
========================================================= */

.sidebar {

    width: var(--sidebar-width);

    height: 100vh;

    background: var(--card-bg);

    border-right:
        1px solid var(--border);

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


/* =========================================================
   MENU
========================================================= */

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

    transition:
        all 0.3s ease;
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

    background:
        var(--neon-glow);

    color: var(--neon);
}


.menu a.active {

    border:
        1px solid
        rgba(57, 255, 20, 0.2);
}


/* =========================================================
   MORE MENU
========================================================= */

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

    transition:
        all 0.3s ease;
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

    transition:
        transform 0.3s ease;
}


.more-toggle-btn:hover {

    background:
        var(--neon-glow);

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


/* =========================================================
   STORAGE WIDGET
========================================================= */

.storage-widget {

    margin-top: 18px;

    margin-bottom: 10px;

    padding: 15px;

    background:
        var(--storage-bg);

    border:
        1px solid var(--border);

    border-radius: 15px;
}


.storage-title {

    display: flex;

    align-items: center;

    gap: 8px;

    color: var(--text);

    font-size: 0.83rem;

    font-weight: 800;

    margin-bottom: 12px;
}


.storage-title svg {

    width: 17px;
    height: 17px;

    stroke: currentColor;

    stroke-width: 2;

    fill: none;
}


.storage-info {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 8px;

    margin-bottom: 7px;
}


.storage-used {

    color: var(--text);

    font-size: 0.74rem;

    font-weight: 800;
}


.storage-bottom {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 8px;

    margin-top: 9px;
}


.storage-remaining {

    color: var(--sub-text);

    font-size: 0.69rem;

    line-height: 1.4;
}


.storage-status {

    font-size: 0.65rem;

    font-weight: 800;

    padding: 4px 7px;

    border-radius: 7px;

    white-space: nowrap;
}


.storage-good {

    color: var(--neon);

    background:
        var(--neon-glow);
}


.storage-warning {

    color: var(--warning);

    background:
        rgba(245, 158, 11, 0.10);
}


.storage-danger {

    color: var(--danger);

    background:
        rgba(255, 77, 77, 0.10);
}


/* =========================================================
   SIDEBAR FOOTER
========================================================= */

.sidebar-footer {

    display: flex;

    flex-direction: column;

    gap: 10px;

    margin-top: 20px;
}


.theme-toggle-btn {

    width: 100%;

    padding: 12px 16px;

    background:
        var(--card-bg);

    border:
        1px solid var(--border);

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


/* =========================================================
   MOBILE HEADER
========================================================= */

.mobile-header {

    display: none;

    align-items: center;

    justify-content: space-between;

    padding: 15px 20px;

    background:
        var(--card-bg);

    border-bottom:
        1px solid var(--border);

    position: fixed;

    top: 0;
    left: 0;

    width: 100%;

    z-index: 1000;
}


.sidebar-toggle-btn {

    background:
        var(--card-bg);

    border:
        1px solid var(--border);

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


/* =========================================================
   SIDEBAR OVERLAY
========================================================= */

.sidebar-overlay {

    display: none;

    position: fixed;

    top: 0;
    left: 0;

    width: 100vw;
    height: 100vh;

    background:
        rgba(0, 0, 0, 0.6);

    z-index: 1001;

    backdrop-filter: blur(2px);
}


/* =========================================================
   MAIN
========================================================= */

main {

    margin-left:
        var(--sidebar-width);

    flex: 1;

    padding: 40px;

    max-width:
        calc(
            100vw - var(--sidebar-width)
        );

    width: 100%;

    box-sizing: border-box;

    overflow-x: hidden;
}


/* =========================================================
   WELCOME
========================================================= */

.welcome-box {

    background:
        rgba(12, 12, 12, 0.88);

    border:
        1px solid var(--border);

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
}


body.light-mode .welcome-box {

    background:
        rgba(255, 255, 255, 0.88);
}


.welcome-content {

    min-width: 0;

    flex: 1;
}


.welcome-box h1 {

    font-size: 1.8rem;

    font-weight: 800;

    margin-bottom: 7px;

    word-break: break-word;
}


.welcome-box p {

    color: var(--sub-text);

    font-size: 0.92rem;

    line-height: 1.6;
}


.student-info {

    margin-top: 10px;

    color: var(--sub-text);

    font-size: 0.78rem;

    font-weight: 600;

    line-height: 1.6;
}


.student-info strong {

    color: var(--text);
}


.semester-area {

    display: flex;

    flex-direction: column;

    align-items: flex-end;

    gap: 8px;
}


.semester-badge {

    background:
        var(--neon-glow);

    color: var(--neon);

    padding: 7px 14px;

    border-radius: 20px;

    font-size: 0.82rem;

    font-weight: 700;

    border:
        1px solid var(--neon);

    white-space: nowrap;
}


/* =========================================================
   SECTION HEADER
========================================================= */

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

    margin-top: 5px;

    line-height: 1.5;
}


/* =========================================================
   PROFESSOR GRID
========================================================= */

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

    background:
        rgba(12, 12, 12, 0.88);

    border:
        1px solid var(--border);

    border-radius: 16px;

    padding: 24px;

    text-decoration: none;

    color: var(--text);

    transition:
        all 0.2s ease;

    display: flex;

    flex-direction: column;

    justify-content: space-between;

    box-sizing: border-box;

    position: relative;

    min-height: 190px;
}


body.light-mode .prof-card {

    background:
        rgba(255, 255, 255, 0.88);
}


.prof-card:hover {

    border-color:
        var(--neon);

    transform:
        translateY(-3px);

    box-shadow:
        0 10px 25px
        var(--neon-glow);
}


.prof-card-content {

    flex: 1;

    text-decoration: none;

    color: inherit;
}


.prof-card h3 {

    font-size: 1.15rem;

    font-weight: 700;

    margin-bottom: 7px;

    color: var(--text);

    word-break: break-word;
}


.prof-card p {

    font-size: 0.84rem;

    color: var(--sub-text);

    line-height: 1.6;
}


/* =========================================================
   NOTIFICATION
========================================================= */

.notif-badge {

    position: absolute;

    top: 18px;
    right: 18px;

    background:
        var(--danger);

    color: #ffffff;

    font-size: 0.70rem;

    font-weight: 700;

    padding: 5px 9px;

    border-radius: 20px;

    display: flex;

    align-items: center;

    gap: 4px;

    box-shadow:
        0 4px 10px
        rgba(
            255,
            77,
            77,
            0.3
        );

    animation:
        pulse-badge 2s infinite;

    z-index: 2;
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


/* =========================================================
   PROFESSOR FOOTER
========================================================= */

.prof-footer {

    display: flex;

    justify-content: space-between;

    align-items: center;

    border-top:
        1px solid var(--border);

    padding-top: 14px;

    gap: 10px;

    margin-top: 15px;
}


.badge {

    background:
        var(--neon-glow);

    color: var(--neon);

    padding: 5px 12px;

    font-size: 0.76rem;

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

    background:
        var(--neon-glow);
}


/* =========================================================
   EMPTY STATE
========================================================= */

.empty-state {

    text-align: center;

    padding: 45px 20px;

    background:
        rgba(12, 12, 12, 0.88);

    border:
        1px solid var(--border);

    border-radius: 16px;

    color: var(--sub-text);
}


body.light-mode .empty-state {

    background:
        rgba(255, 255, 255, 0.88);
}


.empty-state-icon {

    font-size: 2.5rem;

    margin-bottom: 10px;
}


.empty-state h3 {

    color: var(--text);

    margin-bottom: 7px;
}


.empty-state p {

    font-size: 0.84rem;

    line-height: 1.6;
}


/* =========================================================
   MOBILE
========================================================= */

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

        transform:
            translateX(-100%);
    }

    body.sidebar-open .sidebar {

        transform:
            translateX(0);
    }

    body.sidebar-open
    .sidebar-overlay {

        display: block;
    }

    .welcome-box {

        padding:
            22px 18px;

        align-items:
            flex-start;
    }

    .welcome-box h1 {

        font-size:
            1.45rem;
    }

    .welcome-box p {

        font-size:
            0.84rem;
    }

    .student-info {

        font-size:
            0.74rem;
    }

    .semester-area {

        width: 100%;

        align-items:
            flex-start;
    }

    .semester-badge {

        font-size:
            0.76rem;
    }

    .prof-grid {

        grid-template-columns:
            1fr;
    }

    .prof-card {

        min-height:
            180px;
    }

    .storage-widget {

        margin-top:
            14px;
    }
}


/* =========================================================
   SMALL MOBILE
========================================================= */

@media (max-width: 480px) {

    main {

        padding-left:
            12px;

        padding-right:
            12px;
    }

    .mobile-header {

        padding:
            13px 14px;
    }

    .welcome-box {

        border-radius:
            15px;

        padding:
            20px 16px;
    }

    .welcome-box h1 {

        font-size:
            1.28rem;
    }

    .section-header h2 {

        font-size:
            1.15rem;
    }

    .prof-card {

        padding:
            20px;
    }

    .notif-badge {

        top:
            15px;

        right:
            15px;
    }

    .prof-card h3 {

        font-size:
            1.05rem;

        padding-right:
            65px;
    }

    .prof-footer {

        flex-wrap:
            wrap;
    }
}

</style>

</head>

<body>


<!-- =====================================================
     MOBILE HEADER
====================================================== -->

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


<!-- =====================================================
     SIDEBAR OVERLAY
====================================================== -->

<div
    class="sidebar-overlay"
    onclick="toggleMobileSidebar()"
></div>


<!-- =====================================================
     SIDEBAR
====================================================== -->

<nav
    class="sidebar"
    id="sidebar"
>

    <div class="sidebar-top">


        <!-- BRAND -->

        <div class="sidebar-brand">

            <img
                src="../assets/cdmlogo.png"
                alt="Logo"
            >

            <h2>
                NoteSphere
            </h2>

        </div>


        <!-- MENU -->

        <div class="menu">


            <!-- DASHBOARD -->

            <a
                href="student_home.php"
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

                <span>
                    Dashboard
                </span>

            </a>


            <!-- VIEW NOTES -->

            <a href="view_notes.php">

                <svg viewBox="0 0 24 24">

                    <path
                        d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"
                    ></path>

                    <path
                        d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"
                    ></path>

                </svg>

                <span>
                    View Notes
                </span>

            </a>


            <!-- HELP BOARD -->

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

                <span>
                    Help Board (Q&A)
                </span>

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

                    <div
                        class="more-toggle-left"
                    >

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

                        <span>
                            More
                        </span>

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


                    <!-- ARCHIVE -->

                    <a href="archive_sy.php">

                        <svg viewBox="0 0 24 24">

                            <path
                                d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"
                            ></path>

                            <path
                                d="M6.5 2H20v20H6.5A2.5 2.5 0 0 0 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"
                            ></path>

                        </svg>

                        <span>
                            Archive SY
                        </span>

                    </a>


                    <!-- ENROLLMENT -->

                    <a href="enrollment_update.php">

                        <svg viewBox="0 0 24 24">

                            <path
                                d="M22 10v6M2 10l10-5 10 5-10 5z"
                            ></path>

                            <path
                                d="M6 12v5c3 3 9 3 12 0v-5"
                            ></path>

                        </svg>

                        <span>
                            Enrollment Update
                        </span>

                    </a>


                    <!-- SYSTEM GUIDE -->

                    <a href="system_guide.php">

                        <svg viewBox="0 0 24 24">

                            <path
                                d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"
                            ></path>

                            <path
                                d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"
                            ></path>

                        </svg>

                        <span>
                            System Guide
                        </span>

                    </a>

                </div>

            </div>

        </div>


        <!-- =================================================
             STORAGE
        ================================================== -->

        <div class="storage-widget">

            <div class="storage-title">

                <svg viewBox="0 0 24 24">

                    <ellipse
                        cx="12"
                        cy="5"
                        rx="9"
                        ry="3"
                    ></ellipse>

                    <path
                        d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"
                    ></path>

                    <path
                        d="M3 12c0 1.66 4 3 9 3s9-1.34 9-3"
                    ></path>

                </svg>

                <span>
                    My Storage
                </span>

            </div>


            <div class="storage-info">

                <span class="storage-used">

                    <?= htmlspecialchars($used_text) ?>

                    /

                    <?= htmlspecialchars($quota_text) ?>

                </span>

            </div>


                <span
                    class="storage-status <?= htmlspecialchars($storage_status_class) ?>"
                >

                    <?= htmlspecialchars($storage_status) ?>

                </span>

            </div>

        </div>

    </div>


    <!-- =================================================
         SIDEBAR FOOTER
    ================================================== -->

    <div class="sidebar-footer">


        <!-- THEME -->

        <button
            onclick="toggleGlobalTheme()"
            class="theme-toggle-btn"
            id="globalThemeBtn"
        >

            <span id="themeIcon">
                🌞
            </span>

            <span id="themeText">
                Light Mode
            </span>

        </button>


        <!-- LOGOUT -->

        <a
            href="../auth/logout.php"
            class="theme-toggle-btn"
            style="
                color:#ff4d4d;
                border-color:
                rgba(255,77,77,0.3);
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

            <span>
                Logout
            </span>

        </a>

    </div>

</nav>


<!-- =====================================================
     MAIN CONTENT
====================================================== -->

<main>


    <!-- =================================================
         WELCOME
    ================================================== -->

    <div class="welcome-box">

        <div class="welcome-content">

            <h1>

                Hello,

                <?= htmlspecialchars($fullname) ?>! 👋

            </h1>


            <p>

                Welcome back to your
                NoteSphere student dashboard.

            </p>


            <div class="student-info">

                <strong>

                    <?= htmlspecialchars($student_details_text) ?>

                </strong>

            </div>

        </div>


        <div class="semester-area">

            <div class="semester-badge">

                <?= htmlspecialchars(
                    $active_semester
                    . ' | '
                    . $active_year
                ) ?>

            </div>

        </div>

    </div>


    <!-- =================================================
         PROFESSORS HEADER
    ================================================== -->

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

                    <?= $total_unread ?>

                    unread

                </span>

            <?php endif; ?>

        </h2>


        <p>

            Professors with announcements
            for the current semester and
            school year.

        </p>

    </div>


    <!-- =================================================
         PROFESSOR CARDS
    ================================================== -->

    <?php if (!empty($professors)): ?>

        <div class="prof-grid">

            <?php foreach ($professors as $prof): ?>

                <?php

                $unread =
                    (int) (
                        $prof[
                            'unread_announcements'
                        ]
                        ?? 0
                    );

                $total =
                    (int) (
                        $prof[
                            'total_announcements'
                        ]
                        ?? 0
                    );

                $prof_id =
                    (int) (
                        $prof['user_id']
                        ?? 0
                    );

                ?>

                <div class="prof-card">


                    <!-- NOTIFICATION -->

                    <?php if ($unread > 0): ?>

                        <div
                            class="notif-badge"
                            title="<?= $unread ?> unread announcement(s)"
                        >

                            🔔

                            <span>

                                <?= $unread ?>

                                New

                            </span>

                        </div>

                    <?php endif; ?>


                    <!-- PROFESSOR CONTENT -->

                    <a
                        href="professor_announcements.php?prof_id=<?= $prof_id ?>"
                        class="prof-card-content"
                    >

                        <div>

                            <h3
                                style="
                                    padding-right:
                                    <?= $unread > 0 ? '75px' : '0' ?>;
                                "
                            >

                                Prof.

                                <?= htmlspecialchars(
                                    $prof['full_name']
                                ) ?>

                            </h3>


                            <p>

                                View announcements,
                                assignments, modules,
                                and updates from
                                this professor.

                            </p>

                        </div>

                    </a>


                    <!-- FOOTER -->

                    <div class="prof-footer">

                        <span class="badge">

                            <?= $total ?>

                            <?= $total == 1
                                ? 'Announcement'
                                : 'Announcements'
                            ?>

                        </span>


                        <a
                            href="professor_announcements.php?prof_id=<?= $prof_id ?>"
                            class="view-btn"
                        >

                            Open 

                        </a>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    <?php else: ?>


        <!-- EMPTY STATE -->

        <div class="empty-state">

            <div class="empty-state-icon">
                📢
            </div>


            <h3>

                No Announcements Yet

            </h3>


            <p>

                There are no active professor
                announcements for

                <?= htmlspecialchars(
                    $active_semester
                ) ?>

                of

                <?= htmlspecialchars(
                    $active_year
                ) ?>

                yet.

            </p>

        </div>

    <?php endif; ?>

</main>


<!-- =====================================================
     JAVASCRIPT
====================================================== -->
<script>


// =========================================================
// MOBILE SIDEBAR
// =========================================================

function toggleMobileSidebar() {

    document.body.classList.toggle(
        'sidebar-open'
    );
}


// =========================================================
// MORE MENU
// =========================================================

function toggleMoreMenu(event) {

    event.preventDefault();

    const submenu =
        document.getElementById(
            'moreSubmenu'
        );

    const chevron =
        document.getElementById(
            'moreChevron'
        );

    if (
        submenu.style.display === 'none' ||
        submenu.style.display === ''
    ) {

        submenu.style.display =
            'flex';

        chevron.style.transform =
            'rotate(180deg)';

    } else {

        submenu.style.display =
            'none';

        chevron.style.transform =
            'rotate(0deg)';
    }
}


// =========================================================
// THEME
// =========================================================

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
        isLight
            ? 'light'
            : 'dark'
    );

    updateThemeButton(
        isLight
    );
}


function updateThemeButton(isLight) {

    document.getElementById(
        'themeIcon'
    ).textContent =
        isLight
            ? '🌙'
            : '🌞';

    document.getElementById(
        'themeText'
    ).textContent =
        isLight
            ? 'Dark Mode'
            : 'Light Mode';
}


// =========================================================
// LOAD SAVED THEME
// =========================================================

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