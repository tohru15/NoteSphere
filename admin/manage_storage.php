
<?php

// =========================================================
// NoteSphere - Admin Academic Settings
// Academic Settings + Separate Enrollment Update Control
// =========================================================

session_start();

require_once __DIR__ . '/../includes/db_config.php';

/** @var PDO $conn */

// =========================================================
// ADMIN SECURITY CHECK
// =========================================================

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {
    header("Location: ../auth/login.php");
    exit();
}

date_default_timezone_set('Asia/Manila');

// =========================================================
// CSRF TOKEN
// =========================================================

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

// =========================================================
// HELPER
// =========================================================

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// =========================================================
// ADMIN INFORMATION
// =========================================================

$admin_name = 'Administrator';

try {
    $adminStmt = $conn->prepare("
        SELECT full_name
        FROM users
        WHERE user_id = ?
          AND role = 'admin'
        LIMIT 1
    ");

    $adminStmt->execute([(int) $_SESSION['user_id']]);
    $adminInfo = $adminStmt->fetch(PDO::FETCH_ASSOC);

    if (!empty($adminInfo['full_name'])) {
        $admin_name = trim($adminInfo['full_name']);
    } elseif (!empty($_SESSION['full_name'])) {
        $admin_name = trim($_SESSION['full_name']);
    } elseif (!empty($_SESSION['name'])) {
        $admin_name = trim($_SESSION['name']);
    }
} catch (PDOException $e) {
    error_log('NoteSphere admin information error: ' . $e->getMessage());

    if (!empty($_SESSION['full_name'])) {
        $admin_name = trim($_SESSION['full_name']);
    } elseif (!empty($_SESSION['name'])) {
        $admin_name = trim($_SESSION['name']);
    }
}

if ($admin_name === '') {
    $admin_name = 'Administrator';
}

$admin_initial = strtoupper(substr($admin_name, 0, 1));

if ($admin_initial === '') {
    $admin_initial = 'A';
}

// =========================================================
// DEFAULT VALUES
// =========================================================

$currentAcademicYear = '2026-2027';
$currentSemester = '1st Semester';
$allowStudentUpdate = false;
$updatedAt = null;

$settingsSuccess = '';
$settingsError = '';

// =========================================================
// POST ACTIONS
// =========================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // =====================================================
    // CSRF CHECK
    // =====================================================

    $submittedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($submittedToken) ||
        $submittedToken === '' ||
        !hash_equals($_SESSION['csrf_token'], $submittedToken)
    ) {
        $_SESSION['settings_error'] =
            'Invalid security token. Please try again.';

        header("Location: manage_storage.php");
        exit();
    }

    $action = $_POST['action'] ?? '';

    // =====================================================
    // UPDATE ACADEMIC SETTINGS
    // =====================================================

    if ($action === 'update_academic_settings') {

        $academicYear = trim(
            (string) ($_POST['current_academic_year'] ?? '')
        );

        $semester = trim(
            (string) ($_POST['current_semester'] ?? '')
        );

        // VALIDATE SCHOOL YEAR

        if (
            $academicYear === '' ||
            !preg_match(
                '/^\d{4}-\d{4}$/',
                $academicYear
            )
        ) {
            $_SESSION['settings_error'] =
                'Please enter a valid academic year, such as 2026-2027.';

            header("Location: manage_storage.php");
            exit();
        }

        // VALIDATE SEMESTER

        $allowedSemesters = [
            '1st Semester',
            '2nd Semester',
            'Summer'
        ];

        if (!in_array($semester, $allowedSemesters, true)) {
            $_SESSION['settings_error'] =
                'Invalid semester selected.';

            header("Location: manage_storage.php");
            exit();
        }

        // UPDATE DATABASE

        try {
            $updateStmt = $conn->prepare("
                UPDATE system_settings
                SET
                    current_academic_year = ?,
                    current_semester = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = 1
            ");

            $updateStmt->execute([
                $academicYear,
                $semester
            ]);

            $_SESSION['settings_success'] =
                'School Year and Semester successfully updated!';

        } catch (PDOException $e) {
            error_log(
                'NoteSphere academic settings update error: ' .
                $e->getMessage()
            );

            $_SESSION['settings_error'] =
                'Unable to update the academic settings.';
        }

        header("Location: manage_storage.php");
        exit();
    }

    // =====================================================
    // UPDATE ENROLLMENT ACCESS
    // =====================================================

    if ($action === 'update_enrollment_access') {

        $enrollmentAccess = isset($_POST['allow_student_update'])
            ? 1
            : 0;

        try {
            $updateEnrollmentStmt = $conn->prepare("
                UPDATE system_settings
                SET
                    allow_student_update = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = 1
            ");

            $updateEnrollmentStmt->execute([
                $enrollmentAccess
            ]);

            if ($enrollmentAccess === 1) {
                $_SESSION['settings_success'] =
                    'Student Enrollment Update has been opened.';
            } else {
                $_SESSION['settings_success'] =
                    'Student Enrollment Update has been locked.';
            }

        } catch (PDOException $e) {
            error_log(
                'NoteSphere enrollment access update error: ' .
                $e->getMessage()
            );

            $_SESSION['settings_error'] =
                'Unable to update the enrollment access setting.';
        }

        header("Location: manage_storage.php");
        exit();
    }

    // INVALID ACTION

    $_SESSION['settings_error'] = 'Invalid action.';
    header("Location: manage_storage.php");
    exit();
}

// =========================================================
// GET CURRENT SYSTEM SETTINGS
// =========================================================

try {
    $settingsStmt = $conn->prepare("
        SELECT
            current_academic_year,
            current_semester,
            allow_student_update,
            updated_at
        FROM system_settings
        WHERE id = 1
        LIMIT 1
    ");

    $settingsStmt->execute();

    $settings = $settingsStmt->fetch(PDO::FETCH_ASSOC);

    if ($settings) {

        if (!empty($settings['current_academic_year'])) {
            $currentAcademicYear =
                trim($settings['current_academic_year']);
        }

        if (!empty($settings['current_semester'])) {
            $currentSemester =
                trim($settings['current_semester']);
        }

        $allowStudentUpdate =
            isset($settings['allow_student_update']) &&
            (int) $settings['allow_student_update'] === 1;

        $updatedAt = $settings['updated_at'] ?? null;
    }

} catch (PDOException $e) {
    error_log(
        'NoteSphere settings retrieval error: ' . $e->getMessage()
    );

    $currentAcademicYear = '2026-2027';
    $currentSemester = '1st Semester';
    $allowStudentUpdate = false;
}

// =========================================================
// SESSION MESSAGES
// =========================================================

if (isset($_SESSION['settings_success'])) {
    $settingsSuccess = $_SESSION['settings_success'];
    unset($_SESSION['settings_success']);
}

if (isset($_SESSION['settings_error'])) {
    $settingsError = $_SESSION['settings_error'];
    unset($_SESSION['settings_error']);
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

    <title>NoteSphere - Academic Settings</title>

    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        /* =====================================================
           ROOT VARIABLES
        ===================================================== */

        :root {
            --primary: #233961;
            --primary-light: #3D8BFF;

            --background: #f5f7fb;
            --card: #ffffff;

            --text: #1f2937;
            --muted: #6b7280;

            --border: #e5e7eb;

            --success: #16a34a;
            --warning: #f59e0b;
            --danger: #dc2626;

            --shadow: 0 8px 25px rgba(35, 57, 97, 0.08);
        }

        body.dark-mode {
            --background: #0f172a;
            --card: #111827;

            --text: #f8fafc;
            --muted: #94a3b8;

            --border: #243044;

            --shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
        }

        /* =====================================================
           RESET
        ===================================================== */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            min-height: 100vh;
            background: var(--background);
            color: var(--text);
            transition: background 0.25s ease, color 0.25s ease;
        }

        /* =====================================================
           TOPBAR - MATCH PROFESSOR ANNOUNCEMENTS PAGE
        ===================================================== */

        .topbar {
            width: 100%;
            min-height: 76px;
            background: var(--card);
            border-bottom: 1px solid var(--border);

            display: flex;
            align-items: center;
            justify-content: space-between;

            padding: 0 38px;

            position: sticky;
            top: 0;
            z-index: 100;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .brand-name {
            font-size: 20px;
            font-weight: 800;
            letter-spacing: -0.5px;
            color: var(--primary);
        }

        body.dark-mode .brand-name {
            color: #ffffff;
        }

        /* X BUTTON BESIDE NOTESPHERE */

        .close-dashboard-btn {
            width: 36px;
            height: 36px;
            flex-shrink: 0;

            display: inline-flex;
            align-items: center;
            justify-content: center;

            border: 1px solid var(--border);
            border-radius: 10px;

            background: var(--card);
            color: var(--muted);

            text-decoration: none;
            transition: all 0.2s ease;
        }

        .close-dashboard-btn:hover {
            background: var(--danger);
            border-color: var(--danger);
            color: #ffffff;
            transform: scale(1.04);
        }

        .close-dashboard-btn:focus-visible {
            outline: 3px solid rgba(61, 139, 255, 0.35);
            outline-offset: 3px;
        }

        .close-dashboard-btn svg {
            width: 18px;
            height: 18px;
            fill: none;
            stroke: currentColor;
            stroke-width: 2.2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        /* ADMIN PROFILE */

        .profile {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .profile-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;

            background: var(--primary);
            color: #ffffff;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 13px;
            font-weight: 800;
            flex-shrink: 0;
        }

        .profile-details {
            text-align: right;
        }

        .profile-name {
            color: var(--text);
            font-size: 12px;
            font-weight: 800;
        }

        .profile-role {
            color: var(--muted);
            font-size: 10px;
            margin-top: 3px;
        }

        /* =====================================================
           MAIN PAGE
        ===================================================== */

        .main {
            width: 100%;
            min-height: calc(100vh - 76px);
            padding: 34px 38px 50px;
        }

        .page-container {
            width: 100%;
            max-width: 1100px;
            margin: 0 auto;
        }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 25px;
        }

        .page-title h1 {
            font-size: 25px;
            font-weight: 800;
            letter-spacing: -0.5px;
            color: var(--text);
        }

        .page-title p {
            margin-top: 6px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.6;
        }

        /* =====================================================
           CURRENT SETTINGS
        ===================================================== */

        .current-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 22px;
        }

        .current-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 20px;
            box-shadow: var(--shadow);
            transition: background 0.25s ease, border-color 0.25s ease;
        }

        .current-label {
            color: var(--muted);
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 7px;
        }

        .current-value {
            font-size: 19px;
            font-weight: 800;
            color: var(--text);
            overflow-wrap: anywhere;
        }

        /* =====================================================
           CARD
        ===================================================== */

        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 17px;
            padding: 25px;
            margin-bottom: 22px;
            box-shadow: var(--shadow);
            transition: background 0.25s ease, border-color 0.25s ease;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 15px;
            margin-bottom: 20px;
        }

        .card-title h2 {
            font-size: 15px;
            font-weight: 800;
            color: var(--text);
        }

        .card-title p {
            margin-top: 5px;
            color: var(--muted);
            font-size: 11px;
            line-height: 1.6;
        }

        /* =====================================================
           FORM
        ===================================================== */

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 18px;
        }

        .form-group label {
            font-size: 11px;
            font-weight: 800;
            color: var(--text);
        }

        input,
        select {
            width: 100%;
            padding: 12px 13px;

            border: 1px solid var(--border);
            border-radius: 10px;

            background: var(--card);
            color: var(--text);

            outline: none;
            font-size: 12px;

            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        input:focus,
        select:focus {
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(61, 139, 255, 0.10);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        /* =====================================================
           INFO BOX
        ===================================================== */

        .info-box {
            padding: 13px 15px;
            border: 1px solid rgba(61, 139, 255, 0.20);
            background: rgba(61, 139, 255, 0.06);
            border-radius: 11px;

            color: var(--muted);
            font-size: 11px;
            line-height: 1.7;

            margin-bottom: 20px;
        }

        .info-box strong {
            color: var(--text);
        }

        /* =====================================================
           FORM ACTIONS
        ===================================================== */

        .form-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 5px;
        }

        .save-btn {
            border: none;
            background: var(--primary-light);
            color: #ffffff;

            padding: 11px 20px;
            border-radius: 10px;

            font-size: 11px;
            font-weight: 800;

            cursor: pointer;
            transition: 0.2s ease;
        }

        .save-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 7px 18px rgba(61, 139, 255, 0.22);
        }

        .save-btn:focus-visible {
            outline: 3px solid rgba(61, 139, 255, 0.35);
            outline-offset: 3px;
        }

        /* =====================================================
           ENROLLMENT CONTROL
        ===================================================== */

        .enrollment-card {
            border: 1px solid var(--border);
        }

        .enrollment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 18px;
        }

        .enrollment-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;

            padding: 7px 12px;
            border-radius: 20px;

            font-size: 10px;
            font-weight: 800;
            white-space: nowrap;
        }

        .enrollment-open {
            color: var(--success);
            background: rgba(22, 163, 74, 0.10);
            border: 1px solid rgba(22, 163, 74, 0.25);
        }

        .enrollment-locked {
            color: var(--danger);
            background: rgba(220, 38, 38, 0.08);
            border: 1px solid rgba(220, 38, 38, 0.20);
        }

        /* =====================================================
           TOGGLE
        ===================================================== */

        .toggle-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;

            padding: 17px;
            border: 1px solid var(--border);
            border-radius: 13px;
            margin-bottom: 18px;
        }

        .toggle-text {
            flex: 1;
        }

        .toggle-title {
            font-size: 12px;
            font-weight: 800;
            color: var(--text);
            margin-bottom: 5px;
        }

        .toggle-description {
            color: var(--muted);
            font-size: 10px;
            line-height: 1.7;
        }

        /* =====================================================
           SWITCH
        ===================================================== */

        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 28px;
            flex-shrink: 0;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            inset: 0;
            background: #9ca3af;
            border-radius: 30px;
            transition: 0.25s;
        }

        .slider::before {
            content: '';
            position: absolute;
            width: 22px;
            height: 22px;
            left: 3px;
            top: 3px;

            background: #ffffff;
            border-radius: 50%;
            transition: 0.25s;

            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.18);
        }

        .switch input:checked + .slider {
            background: var(--success);
        }

        .switch input:checked + .slider::before {
            transform: translateX(22px);
        }

        .switch input:focus-visible + .slider {
            outline: 3px solid rgba(61, 139, 255, 0.35);
            outline-offset: 3px;
        }

        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 800px) {
            .main {
                padding: 25px 22px 35px;
            }

            .current-grid {
                grid-template-columns: 1fr;
            }

            .form-grid {
                grid-template-columns: 1fr;
                gap: 0;
            }
        }

        @media (max-width: 600px) {
            .topbar {
                min-height: 70px;
                padding: 12px 18px;
            }

            .brand {
                gap: 9px;
            }

            .brand-name {
                font-size: 18px;
            }

            .close-dashboard-btn {
                width: 33px;
                height: 33px;
            }

            .profile {
                gap: 8px;
            }

            .profile-details {
                display: none;
            }

            .profile-avatar {
                width: 36px;
                height: 36px;
            }

            .main {
                padding: 25px 16px 35px;
            }

            .page-header {
                align-items: stretch;
            }

            .page-title h1 {
                font-size: 22px;
            }

            .card {
                padding: 18px;
            }

            .enrollment-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .toggle-row {
                align-items: flex-start;
                padding: 14px;
            }

            .form-actions {
                justify-content: stretch;
            }

            .save-btn {
                width: 100%;
            }
        }

        @media (max-width: 380px) {
            .topbar {
                padding: 12px;
            }

            .brand-name {
                font-size: 16px;
            }

            .close-dashboard-btn {
                width: 31px;
                height: 31px;
            }

            .main {
                padding: 20px 12px 30px;
            }

            .card {
                padding: 15px;
            }

            .current-card {
                padding: 16px;
            }
        }
    </style>

</head>

<body>

    <!-- =====================================================
         TOPBAR
         X BUTTON BESIDE NOTESPHERE
         CLICKING X GOES DIRECTLY TO ADMIN DASHBOARD
    ====================================================== -->

    <header class="topbar">

        <div class="brand">

            <a
                href="admin_dashboard.php"
                class="close-dashboard-btn"
                title="Back to Admin Dashboard"
                aria-label="Back to Admin Dashboard"
            >
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M18 6L6 18"></path>
                    <path d="M6 6l12 12"></path>
                </svg>
            </a>

            <div class="brand-name">NoteSphere</div>

        </div>

        <div class="profile">

            <div class="profile-details">

                <div class="profile-name">
                    <?= e($admin_name) ?>
                </div>

                <div class="profile-role">
                    Administrator
                </div>

            </div>

            <div class="profile-avatar">
                <?= e($admin_initial) ?>
            </div>

        </div>

    </header>


    <main class="main">

        <div class="page-container">

            <!-- =================================================
                 PAGE HEADER
            ================================================== -->

            <section class="page-header">

                <div class="page-title">

                    <h1>Academic Settings</h1>

                    <p>
                        Manage the active school year, semester,
                        and student enrollment access.
                    </p>

                </div>

            </section>


            <!-- =================================================
                 CURRENT ACADEMIC SETTINGS
            ================================================== -->

            <section class="current-grid">

                <div class="current-card">

                    <div class="current-label">
                        Active School Year
                    </div>

                    <div class="current-value">
                        <?= e($currentAcademicYear) ?>
                    </div>

                </div>

                <div class="current-card">

                    <div class="current-label">
                        Active Semester
                    </div>

                    <div class="current-value">
                        <?= e($currentSemester) ?>
                    </div>

                </div>

            </section>


            <!-- =================================================
                 CARD 1 - ACADEMIC SETTINGS
            ================================================== -->

            <section class="card">

                <div class="card-header">

                    <div class="card-title">

                        <h2>Academic Settings</h2>

                        <p>
                            Change the active school year and semester
                            used by NoteSphere.
                        </p>

                    </div>

                </div>

                <div class="info-box">
                    These settings determine the current academic
                    period displayed to students and used for
                    enrollment updates and academic records.
                </div>

                <form method="POST" action="">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($csrf_token) ?>"
                    >

                    <input
                        type="hidden"
                        name="action"
                        value="update_academic_settings"
                    >

                    <div class="form-grid">

                        <div class="form-group">

                            <label for="current_academic_year">
                                School Year
                            </label>

                            <input
                                type="text"
                                id="current_academic_year"
                                name="current_academic_year"
                                value="<?= e($currentAcademicYear) ?>"
                                placeholder="Example: 2026-2027"
                                maxlength="9"
                                pattern="\d{4}-\d{4}"
                                title="Enter the school year in YYYY-YYYY format, such as 2026-2027."
                                required
                            >

                        </div>

                        <div class="form-group">

                            <label for="current_semester">
                                Semester
                            </label>

                            <select
                                id="current_semester"
                                name="current_semester"
                                required
                            >

                                <option
                                    value="1st Semester"
                                    <?= $currentSemester === '1st Semester'
                                        ? 'selected'
                                        : '' ?>
                                >
                                    1st Semester
                                </option>

                                <option
                                    value="2nd Semester"
                                    <?= $currentSemester === '2nd Semester'
                                        ? 'selected'
                                        : '' ?>
                                >
                                    2nd Semester
                                </option>

                                <option
                                    value="Summer"
                                    <?= $currentSemester === 'Summer'
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Summer
                                </option>

                            </select>

                        </div>

                    </div>

                    <div class="form-actions">

                        <button
                            type="submit"
                            class="save-btn"
                        >
                            Save Academic Settings
                        </button>

                    </div>

                </form>

            </section>


            <!-- =================================================
                 CARD 2 - ENROLLMENT UPDATE CONTROL
            ================================================== -->

            <section class="card enrollment-card">

                <div class="enrollment-header">

                    <div class="card-title">

                        <h2>Enrollment Update Control</h2>

                        <p>
                            Control whether students can update
                            their enrollment information.
                        </p>

                    </div>

                    <?php if ($allowStudentUpdate): ?>

                        <span class="enrollment-status enrollment-open">
                            ● Open
                        </span>

                    <?php else: ?>

                        <span class="enrollment-status enrollment-locked">
                            ● Locked
                        </span>

                    <?php endif; ?>

                </div>

                <div class="info-box">
                    When this setting is <strong>Open</strong>,
                    students can submit enrollment update requests.
                    When it is <strong>Locked</strong>, students
                    cannot submit new requests through the enrollment
                    update feature.
                </div>

                <form
                    method="POST"
                    action=""
                    id="enrollmentAccessForm"
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($csrf_token) ?>"
                    >

                    <input
                        type="hidden"
                        name="action"
                        value="update_enrollment_access"
                    >

                    <div class="toggle-row">

                        <div class="toggle-text">

                            <div class="toggle-title">
                                Allow Student Enrollment Update
                            </div>

                            <div class="toggle-description">
                                Turn this on to allow enrollment update
                                requests. Turn it off to lock this feature.
                            </div>

                        </div>

                        <label class="switch">

                            <input
                                type="checkbox"
                                name="allow_student_update"
                                id="allow_student_update"
                                value="1"
                                <?= $allowStudentUpdate ? 'checked' : '' ?>
                            >

                            <span class="slider"></span>

                        </label>

                    </div>

                    <div class="form-actions">

                        <button
                            type="submit"
                            class="save-btn"
                        >
                            Save Enrollment Setting
                        </button>

                    </div>

                </form>

            </section>

        </div>

    </main>


    <script>
        // =====================================================
        // APPLY SAVED NOTESPHERE ADMIN THEME
        // =====================================================

        function applySystemTheme() {
            const savedTheme =
                localStorage.getItem('notesphere_admin_theme');

            document.body.classList.toggle(
                'dark-mode',
                savedTheme === 'dark'
            );
        }

        applySystemTheme();


        // =====================================================
        // ENROLLMENT SETTING CONFIRMATION
        // =====================================================

        const enrollmentForm =
            document.getElementById('enrollmentAccessForm');

        const enrollmentToggle =
            document.getElementById('allow_student_update');

        const originalEnrollmentAccess =
            <?= $allowStudentUpdate ? 'true' : 'false' ?>;

        if (enrollmentForm && enrollmentToggle) {

            enrollmentForm.addEventListener('submit', function(event) {

                event.preventDefault();

                const isOpening = enrollmentToggle.checked;

                Swal.fire({
                    icon: isOpening ? 'question' : 'warning',

                    title: isOpening
                        ? 'Open Enrollment Update?'
                        : 'Lock Enrollment Update?',

                    text: isOpening
                        ? 'Students will be allowed to submit enrollment update requests.'
                        : 'Students will no longer be allowed to submit enrollment update requests.',

                    showCancelButton: true,

                    confirmButtonText: isOpening
                        ? 'Yes, Open It'
                        : 'Yes, Lock It',

                    cancelButtonText: 'Cancel',

                    reverseButtons: true,

                    confirmButtonColor: isOpening
                        ? '#16a34a'
                        : '#dc2626',

                    cancelButtonColor: '#6b7280'

                }).then(function(result) {

                    if (result.isConfirmed) {
                        enrollmentForm.submit();
                    } else {
                        enrollmentToggle.checked =
                            originalEnrollmentAccess;
                    }

                });

            });

        }


        // =====================================================
        // SUCCESS MESSAGE
        // =====================================================

        <?php if (!empty($settingsSuccess)): ?>

        Swal.fire({
            icon: 'success',
            title: 'Settings Updated',
            text: <?= json_encode($settingsSuccess) ?>,
            confirmButtonText: 'Okay',
            confirmButtonColor: '#233961'
        });

        <?php endif; ?>


        // =====================================================
        // ERROR MESSAGE
        // =====================================================

        <?php if (!empty($settingsError)): ?>

        Swal.fire({
            icon: 'error',
            title: 'Update Failed',
            text: <?= json_encode($settingsError) ?>,
            confirmButtonText: 'Okay',
            confirmButtonColor: '#dc2626'
        });

        <?php endif; ?>
    </script>

</body>

</html>
