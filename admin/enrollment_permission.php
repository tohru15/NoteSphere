<?php

session_start();

require_once '../includes/db_config.php';

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

// =========================================================
// CSRF TOKEN
// =========================================================

if (empty($_SESSION['enrollment_permission_csrf'])) {
    $_SESSION['enrollment_permission_csrf'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['enrollment_permission_csrf'];

// =========================================================
// DEFAULT VALUES
// =========================================================

$current_academic_year = 'Not Set';
$current_semester = 'Not Set';
$allow_student_update = 0;

// =========================================================
// GET CURRENT SETTINGS
// =========================================================

try {

    $settings_stmt = $conn->prepare("
        SELECT
            current_sy,
            current_sem,
            allow_student_update
        FROM system_settings
        LIMIT 1
    ");

    $settings_stmt->execute();

    $settings = $settings_stmt->fetch(PDO::FETCH_ASSOC);

    if ($settings) {

        $current_academic_year = !empty($settings['current_sy'])
            ? $settings['current_sy']
            : 'Not Set';

        $current_semester = !empty($settings['current_sem'])
            ? $settings['current_sem']
            : 'Not Set';

        $allow_student_update = (int) (
            $settings['allow_student_update'] ?? 0
        );
    }

} catch (PDOException $e) {

    $_SESSION['error_message'] =
        "Unable to load the current enrollment settings.";
}

// =========================================================
// HANDLE ON / OFF
// =========================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // -----------------------------------------------------
    // CSRF CHECK
    // -----------------------------------------------------

    $posted_token = $_POST['csrf_token'] ?? '';

    if (
        empty($posted_token) ||
        !hash_equals($csrf_token, $posted_token)
    ) {

        $_SESSION['error_message'] =
            "Invalid security token. Please try again.";

        header("Location: enrollment_permission.php");
        exit();
    }

    // -----------------------------------------------------
    // GET NEW STATUS
    // 1 = ON
    // 0 = OFF
    // -----------------------------------------------------

    $permission_status = $_POST['permission_status'] ?? '';

    if (!in_array($permission_status, ['0', '1'], true)) {

        $_SESSION['error_message'] =
            "Invalid enrollment permission setting.";

        header("Location: enrollment_permission.php");
        exit();
    }

    $new_status = (int) $permission_status;

    try {

        // -------------------------------------------------
        // GET SETTINGS ROW
        // -------------------------------------------------

        $check_stmt = $conn->prepare("
            SELECT id
            FROM system_settings
            LIMIT 1
        ");

        $check_stmt->execute();

        $settings_row = $check_stmt->fetch(PDO::FETCH_ASSOC);

        // -------------------------------------------------
        // UPDATE EXISTING SETTINGS
        // -------------------------------------------------

        if ($settings_row) {

            // Only update enrollment permission.
            // School Year and Semester remain unchanged.

            $update_stmt = $conn->prepare("
                UPDATE system_settings
                SET allow_student_update = ?
                WHERE id = ?
                LIMIT 1
            ");

            $update_stmt->execute([
                $new_status,
                $settings_row['id']
            ]);
        }

        // -------------------------------------------------
        // CREATE SETTINGS ROW IF NONE EXISTS
        // -------------------------------------------------

        else {

            $insert_stmt = $conn->prepare("
                INSERT INTO system_settings
                (
                    current_sy,
                    current_sem,
                    allow_student_update
                )
                VALUES
                (
                    ?,
                    ?,
                    ?
                )
            ");

            $insert_stmt->execute([
                '',
                '',
                $new_status
            ]);
        }

        // -------------------------------------------------
        // VERIFY UPDATE
        // -------------------------------------------------

        $verify_stmt = $conn->prepare("
            SELECT allow_student_update
            FROM system_settings
            LIMIT 1
        ");

        $verify_stmt->execute();

        $verified_status = $verify_stmt->fetchColumn();

        if ((int) $verified_status === $new_status) {

            if ($new_status === 1) {

                $_SESSION['success_message'] =
                    "Student enrollment updates are now ON.";

            } else {

                $_SESSION['success_message'] =
                    "Student enrollment updates are now OFF.";
            }

        } else {

            $_SESSION['error_message'] =
                "The enrollment permission could not be verified.";
        }

    } catch (PDOException $e) {

        $_SESSION['error_message'] =
            "Unable to update enrollment permission.";
    }

    header("Location: enrollment_permission.php");
    exit();
}

// =========================================================
// FLASH MESSAGES
// =========================================================

$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';

unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Enrollment Permission | NoteSphere</title>

    <!-- =====================================================
         GOOGLE FONT
    ====================================================== -->

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <!-- =====================================================
         SWEETALERT
    ====================================================== -->

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>

        /* =====================================================
           ROOT
        ===================================================== */

        :root {

            --primary: #39ff14;
            --primary-dark: #1ed600;

            --bg: #06080d;

            --card: rgba(13, 17, 25, 0.94);

            --card-soft: rgba(148, 163, 184, 0.04);

            --text: #f8fafc;

            --sub-text: #94a3b8;

            --border: rgba(148, 163, 184, 0.15);

            --success: #22c55e;

            --danger: #ff4d5a;

            --warning: #fbbf24;

            --shadow:
                0 20px 50px rgba(0, 0, 0, 0.30);
        }


        /* =====================================================
           LIGHT MODE
        ===================================================== */

        body.light-mode {

            --bg: #f4f7fb;

            --card: rgba(255, 255, 255, 0.94);

            --card-soft: rgba(15, 23, 42, 0.025);

            --text: #172033;

            --sub-text: #64748b;

            --border: rgba(15, 23, 42, 0.10);

            --shadow:
                0 15px 40px rgba(15, 23, 42, 0.08);
        }


        /* =====================================================
           RESET
        ===================================================== */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }


        html {
            scroll-behavior: smooth;
        }


        body {

            min-height: 100vh;

            font-family:
                'Plus Jakarta Sans',
                sans-serif;

            background: var(--bg);

            color: var(--text);

            overflow-x: hidden;

            transition:
                background 0.3s ease,
                color 0.3s ease;
        }


        button,
        input {
            font-family: inherit;
        }


        button {
            cursor: pointer;
        }


        /* =====================================================
           CDM LOGO BACKGROUND
        ===================================================== */

        body::before {

            content: "";

            position: fixed;

            inset: 0;

            z-index: -2;

            background-image:
                url("../assets/cdmlogo.png");

            background-repeat: no-repeat;

            background-position:
                center center;

            background-size:
                min(650px, 75vw);

            opacity: 0.055;

            filter:
                brightness(0.65)
                contrast(1.15);

            pointer-events: none;

            transition:
                opacity 0.35s ease,
                filter 0.35s ease;
        }


        /* =====================================================
           DARK MODE LOGO
        ===================================================== */

        body:not(.light-mode)::before {

            opacity: 0.055;

            filter:
                brightness(0.55)
                contrast(1.25);
        }


        /* =====================================================
           LIGHT MODE LOGO
        ===================================================== */

        body.light-mode::before {

            opacity: 0.075;

            filter:
                brightness(1.15)
                contrast(0.75);
        }


        /* =====================================================
           BACKGROUND OVERLAY
        ===================================================== */

        body::after {

            content: "";

            position: fixed;

            inset: 0;

            z-index: -1;

            background:
                radial-gradient(
                    circle at 10% 10%,
                    rgba(57, 255, 20, 0.07),
                    transparent 30%
                ),
                radial-gradient(
                    circle at 90% 80%,
                    rgba(57, 255, 20, 0.05),
                    transparent 30%
                ),
                linear-gradient(
                    135deg,
                    rgba(0, 0, 0, 0.28),
                    rgba(0, 0, 0, 0.05)
                );

            pointer-events: none;
        }


        body.light-mode::after {

            background:
                radial-gradient(
                    circle at 10% 10%,
                    rgba(57, 255, 20, 0.045),
                    transparent 30%
                ),
                radial-gradient(
                    circle at 90% 80%,
                    rgba(57, 255, 20, 0.025),
                    transparent 30%
                ),
                linear-gradient(
                    135deg,
                    rgba(255, 255, 255, 0.48),
                    rgba(255, 255, 255, 0.08)
                );
        }


        /* =====================================================
           PAGE
        ===================================================== */

        .page-container {

            width: 100%;

            max-width: 950px;

            margin: 0 auto;

            padding:
                35px 25px 50px;
        }


        /* =====================================================
           TOP BAR
        ===================================================== */

        .top-bar {

            display: flex;

            justify-content:
                space-between;

            align-items:
                center;

            gap: 20px;

            margin-bottom:
                25px;
        }


        .top-title h1 {

            font-size:
                clamp(24px, 4vw, 32px);

            font-weight:
                800;

            letter-spacing:
                -0.8px;

            margin-bottom:
                7px;
        }


        .top-title p {

            color:
                var(--sub-text);

            font-size:
                12px;

            line-height:
                1.6;
        }


        /* =====================================================
           TOP ACTIONS
        ===================================================== */

        .top-actions {

            display: flex;

            align-items: center;

            gap: 9px;

            flex-shrink: 0;
        }


        /* =====================================================
           THEME BUTTON
        ===================================================== */

        .theme-btn {

            width: 43px;

            height: 43px;

            border:
                1px solid var(--border);

            border-radius:
                12px;

            background:
                var(--card);

            color:
                var(--text);

            display: flex;

            align-items:
                center;

            justify-content:
                center;

            font-size:
                17px;

            box-shadow:
                var(--shadow);

            transition:
                background 0.2s ease,
                transform 0.2s ease,
                border-color 0.2s ease;
        }


        .theme-btn:hover {

            transform:
                translateY(-2px);

            border-color:
                rgba(57, 255, 20, 0.30);
        }


        /* =====================================================
           CLOSE BUTTON
        ===================================================== */

        .close-btn {

            width: 43px;

            height: 43px;

            border:
                1px solid var(--border);

            border-radius:
                12px;

            background:
                var(--card);

            color:
                var(--sub-text);

            font-size:
                23px;

            display: flex;

            align-items:
                center;

            justify-content:
                center;

            box-shadow:
                var(--shadow);

            transition:
                background 0.2s ease,
                color 0.2s ease,
                transform 0.2s ease;
        }


        .close-btn:hover {

            background:
                rgba(255, 77, 90, 0.10);

            color:
                var(--danger);

            transform:
                scale(1.04);
        }


        /* =====================================================
           CARDS
        ===================================================== */

        .academic-card,
        .permission-card {

            background:
                var(--card);

            border:
                1px solid var(--border);

            border-radius:
                20px;

            padding:
                27px;

            box-shadow:
                var(--shadow);

            backdrop-filter:
                blur(14px);

            -webkit-backdrop-filter:
                blur(14px);

            transition:
                background 0.3s ease,
                border-color 0.3s ease,
                box-shadow 0.3s ease;
        }


        .academic-card {

            margin-bottom:
                20px;
        }


        /* =====================================================
           ACADEMIC CARD
        ===================================================== */

        .academic-card h2 {

            font-size:
                17px;

            font-weight:
                800;

            margin-bottom:
                7px;
        }


        .academic-card-description {

            color:
                var(--sub-text);

            font-size:
                11px;

            line-height:
                1.6;

            margin-bottom:
                20px;
        }


        .academic-period {

            display: grid;

            grid-template-columns:
                repeat(2, minmax(0, 1fr));

            gap:
                14px;
        }


        .period-box {

            background:
                var(--card-soft);

            border:
                1px solid var(--border);

            border-radius:
                14px;

            padding:
                17px;
        }


        .period-label {

            color:
                var(--sub-text);

            font-size:
                10px;

            font-weight:
                700;

            margin-bottom:
                7px;

            text-transform:
                uppercase;

            letter-spacing:
                0.6px;
        }


        .period-value {

            color:
                var(--primary);

            font-size:
                18px;

            font-weight:
                800;
        }


        /* =====================================================
           PERMISSION HEADER
        ===================================================== */

        .permission-header {

            display: flex;

            justify-content:
                space-between;

            align-items:
                flex-start;

            gap:
                20px;
        }


        .permission-title {

            flex:
                1;
        }


        .permission-title h2 {

            font-size:
                18px;

            font-weight:
                800;

            margin-bottom:
                7px;
        }


        .permission-title p {

            color:
                var(--sub-text);

            font-size:
                11px;

            line-height:
                1.7;

            max-width:
                680px;
        }


        /* =====================================================
           STATUS BADGE
        ===================================================== */

        .status-badge {

            display: inline-flex;

            align-items:
                center;

            gap:
                7px;

            padding:
                8px 13px;

            border-radius:
                30px;

            font-size:
                10px;

            font-weight:
                800;

            white-space:
                nowrap;
        }


        .status-badge.allowed {

            background:
                rgba(34, 197, 94, 0.12);

            color:
                #4ade80;

            border:
                1px solid rgba(34, 197, 94, 0.20);
        }


        .status-badge.locked {

            background:
                rgba(255, 77, 90, 0.10);

            color:
                #ff7180;

            border:
                1px solid rgba(255, 77, 90, 0.18);
        }


        body.light-mode .status-badge.allowed {

            background:
                #dcfce7;

            color:
                #166534;
        }


        body.light-mode .status-badge.locked {

            background:
                #fee2e2;

            color:
                #991b1b;
        }


        .status-dot {

            width:
                7px;

            height:
                7px;

            border-radius:
                50%;

            background:
                currentColor;

            box-shadow:
                0 0 8px currentColor;
        }


        /* =====================================================
           TOGGLE AREA
        ===================================================== */

        .toggle-area {

            margin-top:
                25px;

            padding-top:
                23px;

            border-top:
                1px solid var(--border);

            display: flex;

            justify-content:
                space-between;

            align-items:
                center;

            gap:
                20px;
        }


        .toggle-info {

            flex:
                1;
        }


        .toggle-info strong {

            display:
                block;

            font-size:
                14px;

            font-weight:
                800;

            margin-bottom:
                6px;
        }


        .toggle-info span {

            display:
                block;

            color:
                var(--sub-text);

            font-size:
                11px;

            line-height:
                1.5;
        }


        /* =====================================================
           PERMISSION CONTROL
        ===================================================== */

        .permission-control {

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            gap:
                10px;

            flex-shrink:
                0;
        }


        .switch-label {

            font-size:
                10px;

            font-weight:
                800;

            min-width:
                28px;

            text-align:
                center;

            color:
                var(--sub-text);

            transition:
                color 0.2s ease;
        }


        .switch-label.active {

            color:
                var(--primary);
        }


        /* =====================================================
           SWITCH FORM
        ===================================================== */

        #permissionForm {

            margin: 0;

            padding: 0;
        }


        /* =====================================================
           SWITCH
        ===================================================== */

        .switch {

            position:
                relative;

            width:
                76px;

            height:
                40px;

            display:
                inline-block;

            flex-shrink:
                0;
        }


        .switch input {

            position:
                absolute;

            opacity:
                0;

            width:
                0;

            height:
                0;
        }


        .slider {

            position:
                absolute;

            inset:
                0;

            cursor:
                pointer;

            background:
                rgba(148, 163, 184, 0.30);

            border:
                1px solid var(--border);

            border-radius:
                50px;

            transition:
                background 0.25s ease,
                border-color 0.25s ease,
                box-shadow 0.25s ease;
        }


        /* OFF TEXT */

        .slider::after {

            content:
                "OFF";

            position:
                absolute;

            right:
                9px;

            top:
                50%;

            transform:
                translateY(-50%);

            color:
                var(--sub-text);

            font-size:
                8px;

            font-weight:
                800;

            transition:
                opacity 0.2s ease;
        }


        /* KNOB */

        .slider::before {

            content:
                "";

            position:
                absolute;

            width:
                30px;

            height:
                30px;

            left:
                4px;

            top:
                4px;

            background:
                #ffffff;

            border-radius:
                50%;

            box-shadow:
                0 3px 10px rgba(0, 0, 0, 0.30);

            transition:
                transform 0.25s ease;
        }


        /* ON */

        .switch input:checked + .slider {

            background:
                var(--primary);

            border-color:
                var(--primary);

            box-shadow:
                0 0 18px rgba(57, 255, 20, 0.22);
        }


        .switch input:checked + .slider::before {

            transform:
                translateX(36px);
        }


        .switch input:checked + .slider::after {

            content:
                "ON";

            left:
                10px;

            right:
                auto;

            color:
                #061006;
        }


        .switch:hover .slider {

            border-color:
                rgba(57, 255, 20, 0.55);
        }


        .switch input:focus-visible + .slider {

            outline:
                3px solid rgba(57, 255, 20, 0.22);

            outline-offset:
                3px;
        }


        /* =====================================================
           TOGGLE STATUS
        ===================================================== */

        .toggle-status {

            margin-top:
                18px;

            padding-top:
                15px;

            border-top:
                1px solid var(--border);

            font-size:
                11px;

            font-weight:
                800;

            text-align:
                right;
        }


        .toggle-status.on {

            color:
                var(--primary);
        }


        .toggle-status.off {

            color:
                var(--danger);
        }


        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 650px) {

            .page-container {

                padding:
                    22px 14px 40px;
            }


            .top-bar {

                align-items:
                    flex-start;
            }


            .top-title h1 {

                font-size:
                    23px;
            }


            .top-title p {

                font-size:
                    11px;

                max-width:
                    300px;
            }


            .top-actions {

                gap:
                    6px;
            }


            .theme-btn,
            .close-btn {

                width:
                    39px;

                height:
                    39px;

                border-radius:
                    10px;
            }


            .academic-card,
            .permission-card {

                padding:
                    20px;

                border-radius:
                    17px;
            }


            .academic-period {

                grid-template-columns:
                    1fr;
            }


            .permission-header {

                flex-direction:
                    column;

                gap:
                    14px;
            }


            .toggle-area {

                align-items:
                    flex-start;

                flex-direction:
                    column;

                gap:
                    17px;
            }


            .permission-control {

                width:
                    100%;

                justify-content:
                    space-between;
            }


            .toggle-status {

                text-align:
                    left;
            }
        }


        @media (max-width: 400px) {

            .page-container {

                padding:
                    18px 11px 35px;
            }


            .top-title h1 {

                font-size:
                    21px;
            }


            .top-title p {

                font-size:
                    10px;
            }


            .academic-card,
            .permission-card {

                padding:
                    17px;
            }


            .permission-title h2 {

                font-size:
                    16px;
            }


            .toggle-info strong {

                font-size:
                    13px;
            }


            .toggle-info span {

                font-size:
                    10px;
            }


            .switch {

                width:
                    70px;

                height:
                    37px;
            }


            .slider::before {

                width:
                    27px;

                height:
                    27px;

                left:
                    4px;

                top:
                    4px;
            }


            .switch input:checked + .slider::before {

                transform:
                    translateX(32px);
            }
        }

    </style>

</head>


<body>

    <div class="page-container">

        <!-- =================================================
             TOP BAR
        ================================================== -->

        <div class="top-bar">

            <div class="top-title">

                <h1>
                    Enrollment Permission
                </h1>

                <p>
                    Control whether students can update their enrollment information.
                </p>

            </div>


            <div class="top-actions">

                <!-- THEME BUTTON -->

                <!-- CLOSE BUTTON -->

                <button
                    type="button"
                    class="close-btn"
                    onclick="goBack()"
                    title="Back to Admin Dashboard"
                    aria-label="Back to Admin Dashboard"
                >

                    ×

                </button>

            </div>

        </div>


        <!-- =================================================
             CURRENT ACADEMIC PERIOD
        ================================================== -->

        <section class="academic-card">

            <h2>
                Current Academic Period
            </h2>

            <p class="academic-card-description">

                This information is automatically taken from the
                School Year and Semester currently set by the administrator.

            </p>


            <div class="academic-period">

                <div class="period-box">

                    <div class="period-label">
                        School Year
                    </div>

                    <div class="period-value">

                        <?php

                        echo htmlspecialchars(
                            $current_academic_year,
                            ENT_QUOTES,
                            'UTF-8'
                        );

                        ?>

                    </div>

                </div>


                <div class="period-box">

                    <div class="period-label">
                        Semester
                    </div>

                    <div class="period-value">

                        <?php

                        echo htmlspecialchars(
                            $current_semester,
                            ENT_QUOTES,
                            'UTF-8'
                        );

                        ?>

                    </div>

                </div>

            </div>

        </section>


        <!-- =================================================
             ENROLLMENT PERMISSION
        ================================================== -->

        <section class="permission-card">

            <div class="permission-header">

                <div class="permission-title">

                    <h2>
                        Student Enrollment Updates
                    </h2>

                    <p>

                        When this setting is ON, students can update
                        their enrollment information. When it is OFF,
                        students cannot make enrollment changes.

                    </p>

                </div>


                <!-- CURRENT STATUS -->

                <?php if ($allow_student_update === 1): ?>

                    <div class="status-badge allowed">

                        <span class="status-dot"></span>

                        ON

                    </div>

                <?php else: ?>

                    <div class="status-badge locked">

                        <span class="status-dot"></span>

                        OFF

                    </div>

                <?php endif; ?>

            </div>


            <!-- =================================================
                 ON / OFF CONTROL
            ================================================== -->

            <div class="toggle-area">

                <div class="toggle-info">

                    <strong>
                        Allow Student Enrollment Updates
                    </strong>

                    <span>

                        <?php if ($allow_student_update === 1): ?>

                            Students can currently update their enrollment.

                        <?php else: ?>

                            Students cannot currently update their enrollment.

                        <?php endif; ?>

                    </span>

                </div>


                <div class="permission-control">

                    <!-- OFF LABEL -->

                    <span
                        class="switch-label
                        <?php
                        echo $allow_student_update === 0
                            ? 'active'
                            : '';
                        ?>"
                        id="offLabel"
                    >

                        OFF

                    </span>


                    <!-- FORM -->

                    <form
                        method="POST"
                        action="enrollment_permission.php"
                        id="permissionForm"
                    >

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?php
                            echo htmlspecialchars(
                                $csrf_token,
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>"
                        >


                        <input
                            type="hidden"
                            name="permission_status"
                            id="permissionStatus"
                            value="<?php
                            echo $allow_student_update === 1
                                ? '1'
                                : '0';
                            ?>"
                        >


                        <label
                            class="switch"
                            title="Turn enrollment updates ON or OFF"
                        >

                            <input
                                type="checkbox"
                                id="permissionToggle"
                                <?php
                                echo $allow_student_update === 1
                                    ? 'checked'
                                    : '';
                                ?>
                            >

                            <span class="slider"></span>

                        </label>

                    </form>


                    <!-- ON LABEL -->

                    <span
                        class="switch-label
                        <?php
                        echo $allow_student_update === 1
                            ? 'active'
                            : '';
                        ?>"
                        id="onLabel"
                    >

                        ON

                    </span>

                </div>

            </div>


            <!-- =================================================
                 CURRENT TOGGLE STATUS
            ================================================== -->

            <div
                class="toggle-status
                <?php
                echo $allow_student_update === 1
                    ? 'on'
                    : 'off';
                ?>"
                id="toggleStatus"
            >

                <?php

                echo $allow_student_update === 1
                    ? 'ON — Students Can Update'
                    : 'OFF — Students Cannot Update';

                ?>

            </div>

        </section>

    </div>


    <script>

        // =====================================================
        // ELEMENTS
        // =====================================================

        const permissionToggle =
            document.getElementById('permissionToggle');

        const permissionStatus =
            document.getElementById('permissionStatus');

        const permissionForm =
            document.getElementById('permissionForm');

        const toggleStatus =
            document.getElementById('toggleStatus');

        const offLabel =
            document.getElementById('offLabel');

        const onLabel =
            document.getElementById('onLabel');

        const themeIcon =
            document.getElementById('themeIcon');


        // =====================================================
        // BACK BUTTON
        // =====================================================

        function goBack() {

            window.location.href =
                'admin_dashboard.php';
        }


        // =====================================================
        // APPLY THEME
        // SAME NOTESPHERE THEME STORAGE
        // =====================================================

        function applyTheme(theme) {

            if (theme === 'light') {

                document.body.classList.add(
                    'light-mode'
                );

                themeIcon.textContent =
                    '☾';

            } else {

                document.body.classList.remove(
                    'light-mode'
                );

                themeIcon.textContent =
                    '☀';
            }
        }


        // =====================================================
        // TOGGLE GLOBAL THEME
        // =====================================================

        function toggleGlobalTheme() {

            const isLight =
                document.body.classList.contains(
                    'light-mode'
                );

            const newTheme =
                isLight
                    ? 'dark'
                    : 'light';

            localStorage.setItem(
                'notesphere_theme',
                newTheme
            );

            applyTheme(newTheme);
        }


        // =====================================================
        // LOAD SAVED THEME
        // =====================================================

        const savedTheme =
            localStorage.getItem(
                'notesphere_theme'
            ) || 'dark';

        applyTheme(savedTheme);


        // =====================================================
        // SWEETALERT THEME
        // =====================================================

        function swalTheme() {

            const isLight =
                document.body.classList.contains(
                    'light-mode'
                );

            return {

                background:
                    isLight
                        ? '#ffffff'
                        : '#0d1119',

                color:
                    isLight
                        ? '#172033'
                        : '#f8fafc'
            };
        }


        // =====================================================
        // UPDATE TOGGLE VISUAL
        // =====================================================

        function updateToggleVisual(isOn) {

            if (isOn) {

                toggleStatus.textContent =
                    'ON — Students Can Update';

                toggleStatus.classList.remove(
                    'off'
                );

                toggleStatus.classList.add(
                    'on'
                );

                onLabel.classList.add(
                    'active'
                );

                offLabel.classList.remove(
                    'active'
                );

            } else {

                toggleStatus.textContent =
                    'OFF — Students Cannot Update';

                toggleStatus.classList.remove(
                    'on'
                );

                toggleStatus.classList.add(
                    'off'
                );

                offLabel.classList.add(
                    'active'
                );

                onLabel.classList.remove(
                    'active'
                );
            }
        }


        // =====================================================
        // ON / OFF TOGGLE
        // =====================================================

        permissionToggle.addEventListener(
            'change',
            function () {

                const willEnable =
                    this.checked;


                // =================================================
                // TURN ON
                // =================================================

                if (willEnable) {

                    Swal.fire({

                        title:
                            'Turn Enrollment Updates ON?',

                        text:
                            'Students will be allowed to update their enrollment information.',

                        icon:
                            'question',

                        showCancelButton:
                            true,

                        confirmButtonText:
                            'Yes, Turn ON',

                        cancelButtonText:
                            'Cancel',

                        reverseButtons:
                            true,

                        confirmButtonColor:
                            '#39ff14',

                        cancelButtonColor:
                            '#64748b',

                        ...swalTheme()

                    }).then((result) => {

                        if (result.isConfirmed) {

                            permissionStatus.value =
                                '1';

                            permissionForm.submit();

                        } else {

                            permissionToggle.checked =
                                false;

                            updateToggleVisual(
                                false
                            );
                        }

                    });

                }


                // =================================================
                // TURN OFF
                // =================================================

                else {

                    Swal.fire({

                        title:
                            'Turn Enrollment Updates OFF?',

                        text:
                            'Students will no longer be allowed to update their enrollment information.',

                        icon:
                            'warning',

                        showCancelButton:
                            true,

                        confirmButtonText:
                            'Yes, Turn OFF',

                        cancelButtonText:
                            'Cancel',

                        reverseButtons:
                            true,

                        confirmButtonColor:
                            '#ff4d5a',

                        cancelButtonColor:
                            '#64748b',

                        ...swalTheme()

                    }).then((result) => {

                        if (result.isConfirmed) {

                            permissionStatus.value =
                                '0';

                            permissionForm.submit();

                        } else {

                            permissionToggle.checked =
                                true;

                            updateToggleVisual(
                                true
                            );
                        }

                    });
                }

            }
        );


        // =====================================================
        // SUCCESS ALERT
        // =====================================================

        <?php if (!empty($success_message)): ?>

        Swal.fire({

            title:
                'Success',

            text:
                <?php

                echo json_encode(
                    $success_message,
                    JSON_HEX_TAG |
                    JSON_HEX_AMP |
                    JSON_HEX_APOS |
                    JSON_HEX_QUOT
                );

                ?>,

            icon:
                'success',

            confirmButtonText:
                'OK',

            confirmButtonColor:
                '#39ff14',

            ...swalTheme()

        });

        <?php endif; ?>


        // =====================================================
        // ERROR ALERT
        // =====================================================

        <?php if (!empty($error_message)): ?>

        Swal.fire({

            title:
                'Error',

            text:
                <?php

                echo json_encode(
                    $error_message,
                    JSON_HEX_TAG |
                    JSON_HEX_AMP |
                    JSON_HEX_APOS |
                    JSON_HEX_QUOT
                );

                ?>,

            icon:
                'error',

            confirmButtonText:
                'OK',

            confirmButtonColor:
                '#ff4d5a',

            ...swalTheme()

        });

        <?php endif; ?>

    </script>

</body>

</html>