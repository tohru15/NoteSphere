<?php

// =========================================================
// NoteSphere - Manage Users
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

// =========================================================
// CSRF TOKEN
// =========================================================

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] =
        bin2hex(random_bytes(32));
}

$csrf_token =
    $_SESSION['csrf_token'];

// =========================================================
// HELPER
// =========================================================

function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

// =========================================================
// CURRENT ADMIN ID
// =========================================================

$current_admin_id =
    (int)$_SESSION['user_id'];

// =========================================================
// ROLE FILTER
// =========================================================

$allowed_roles = [
    'all',
    'student',
    'professor',
    'admin'
];

$selected_role =
    isset($_GET['role'])
        ? strtolower(trim($_GET['role']))
        : 'all';

if (
    !in_array(
        $selected_role,
        $allowed_roles,
        true
    )
) {
    $selected_role = 'all';
}

// =========================================================
// SEARCH
// =========================================================

$search =
    isset($_GET['search'])
        ? trim($_GET['search'])
        : '';

// =========================================================
// SUCCESS / ERROR
// =========================================================

$success_message = '';
$error_message = '';

// =========================================================
// DELETE USER
// =========================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action']) &&
    $_POST['action'] === 'delete_user'
) {

    // -----------------------------------------------------
    // CSRF
    // -----------------------------------------------------

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals(
            $_SESSION['csrf_token'],
            $_POST['csrf_token']
        )
    ) {

        $error_message =
            'Invalid security token. Please try again.';

    } else {

        $delete_user_id =
            isset($_POST['user_id'])
                ? (int)$_POST['user_id']
                : 0;

        // -------------------------------------------------
        // INVALID ID
        // -------------------------------------------------

        if ($delete_user_id <= 0) {

            $error_message =
                'Invalid user account.';

        // -------------------------------------------------
        // PREVENT SELF DELETE
        // -------------------------------------------------

        } elseif (
            $delete_user_id ===
            $current_admin_id
        ) {

            $error_message =
                'You cannot delete your own administrator account.';

        } else {

            try {

                // -----------------------------------------
                // CHECK USER
                // -----------------------------------------

                $check_stmt =
                    $conn->prepare("
                        SELECT
                            id,
                            first_name,
                            last_name,
                            role
                        FROM users
                        WHERE id = ?
                        LIMIT 1
                    ");

                $check_stmt->execute([
                    $delete_user_id
                ]);

                $user_to_delete =
                    $check_stmt->fetch(
                        PDO::FETCH_ASSOC
                    );

                if (!$user_to_delete) {

                    $error_message =
                        'User account not found.';

                } else {

                    // -------------------------------------
                    // DELETE USER
                    // -------------------------------------

                    $delete_stmt =
                        $conn->prepare("
                            DELETE FROM users
                            WHERE id = ?
                            LIMIT 1
                        ");

                    $delete_stmt->execute([
                        $delete_user_id
                    ]);

                    if (
                        $delete_stmt->rowCount() > 0
                    ) {

                        $success_message =
                            'User account deleted successfully.';

                    } else {

                        $error_message =
                            'Unable to delete the user account.';
                    }
                }

            } catch (PDOException $e) {

                $error_message =
                    'Unable to delete the user account. '
                    . 'The account may still be connected '
                    . 'to other system records.';
            }
        }
    }
}

// =========================================================
// GET ADMIN NAME
// =========================================================

$admin_name =
    'Administrator';

try {

    $admin_stmt =
        $conn->prepare("
            SELECT
                first_name,
                last_name,
                username
            FROM users
            WHERE id = ?
            LIMIT 1
        ");

    $admin_stmt->execute([
        $current_admin_id
    ]);

    $admin_data =
        $admin_stmt->fetch(
            PDO::FETCH_ASSOC
        );

    if ($admin_data) {

        $full_name =
            trim(
                ($admin_data['first_name'] ?? '') .
                ' ' .
                ($admin_data['last_name'] ?? '')
            );

        if ($full_name !== '') {

            $admin_name =
                $full_name;

        } elseif (
            !empty(
                $admin_data['username']
            )
        ) {

            $admin_name =
                $admin_data['username'];
        }
    }

} catch (PDOException $e) {

    // Keep default
}

// =========================================================
// GET USERS
// IMPORTANT: id IS INCLUDED HERE
// =========================================================

$users = [];

try {

    $sql = "
        SELECT
            id,
            first_name,
            last_name,
            email,
            username,
            role,
            course,
            year_level,
            section,
            created_at
        FROM users
    ";

    $conditions = [];

    $params = [];

    // -----------------------------------------------------
    // ROLE FILTER
    // -----------------------------------------------------

    if ($selected_role !== 'all') {

        $conditions[] =
            "role = ?";

        $params[] =
            $selected_role;
    }

    // -----------------------------------------------------
    // SEARCH FILTER
    // -----------------------------------------------------

    if ($search !== '') {

        $conditions[] = "
            (
                first_name LIKE ?
                OR last_name LIKE ?
                OR email LIKE ?
                OR username LIKE ?
                OR course LIKE ?
                OR section LIKE ?
            )
        ";

        $search_value =
            '%' . $search . '%';

        $params[] =
            $search_value;

        $params[] =
            $search_value;

        $params[] =
            $search_value;

        $params[] =
            $search_value;

        $params[] =
            $search_value;

        $params[] =
            $search_value;
    }

    // -----------------------------------------------------
    // WHERE
    // -----------------------------------------------------

    if (!empty($conditions)) {

        $sql .=
            " WHERE " .
            implode(
                " AND ",
                $conditions
            );
    }

    // -----------------------------------------------------
    // ORDER
    // -----------------------------------------------------

    $sql .= "
        ORDER BY
            created_at DESC,
            id DESC
    ";

    // -----------------------------------------------------
    // EXECUTE
    // -----------------------------------------------------

    $stmt =
        $conn->prepare($sql);

    $stmt->execute($params);

    $users =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (PDOException $e) {

    $error_message =
        'Unable to load user accounts.';
}

// =========================================================
// USER COUNTS
// =========================================================

$count_all = 0;
$count_students = 0;
$count_professors = 0;
$count_admins = 0;

try {

    $count_stmt =
        $conn->query("
            SELECT
                COUNT(*) AS all_users,

                SUM(
                    CASE
                        WHEN role = 'student'
                        THEN 1
                        ELSE 0
                    END
                ) AS students,

                SUM(
                    CASE
                        WHEN role = 'professor'
                        THEN 1
                        ELSE 0
                    END
                ) AS professors,

                SUM(
                    CASE
                        WHEN role = 'admin'
                        THEN 1
                        ELSE 0
                    END
                ) AS admins

            FROM users
        ");

    $counts =
        $count_stmt->fetch(
            PDO::FETCH_ASSOC
        );

    if ($counts) {

        $count_all =
            (int)($counts['all_users'] ?? 0);

        $count_students =
            (int)($counts['students'] ?? 0);

        $count_professors =
            (int)($counts['professors'] ?? 0);

        $count_admins =
            (int)($counts['admins'] ?? 0);
    }

} catch (PDOException $e) {

    // Keep defaults
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
        NoteSphere - Manage Users
    </title>

    <!-- =====================================================
         SWEETALERT
    ====================================================== -->

    <script
        src="https://cdn.jsdelivr.net/npm/sweetalert2@11"
    ></script>

    <!-- =====================================================
         THEME
    ====================================================== -->

    <script>

        (function () {

            const savedTheme =
                localStorage.getItem(
                    'notesphere_admin_theme'
                );

            if (
                savedTheme === 'dark'
            ) {

                document.documentElement
                    .classList
                    .add('dark-mode');
            }

        })();

    </script>

    <style>

        /* =====================================================
           RESET
        ====================================================== */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* =====================================================
           VARIABLES
        ====================================================== */

        :root {

            --primary: #233961;

            --primary-light: #3D8BFF;

            --background: #f5f7fb;

            --card: #ffffff;

            --text: #1f2937;

            --muted: #6b7280;

            --border: #e5e7eb;

            --sidebar: #ffffff;

            --success: #16a34a;

            --danger: #dc2626;

            --warning: #f59e0b;

            --shadow:
                0 8px 25px
                rgba(35, 57, 97, 0.08);
        }

        html.dark-mode {

            --background: #111827;

            --card: #1f2937;

            --text: #f9fafb;

            --muted: #9ca3af;

            --border: #374151;

            --sidebar: #182235;

            --shadow:
                0 8px 25px
                rgba(0, 0, 0, 0.25);
        }

        /* =====================================================
           BODY
        ====================================================== */

        body {

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background:
                var(--background);

            color:
                var(--text);

            min-height: 100vh;
        }

        /* =====================================================
           LAYOUT
        ====================================================== */

        .dashboard-wrapper {

            display: flex;

            min-height: 100vh;
        }

        /* =====================================================
           SIDEBAR
        ====================================================== */

        .sidebar {

            width: 250px;

            background:
                var(--sidebar);

            border-right:
                1px solid var(--border);

            padding:
                25px 18px;

            position: fixed;

            top: 0;

            bottom: 0;

            left: 0;

            z-index: 100;
        }

        .logo-section {

            display: flex;

            align-items: center;

            gap: 12px;

            padding:
                5px 10px 25px;

            border-bottom:
                1px solid var(--border);

            margin-bottom: 25px;
        }

        .logo-icon {

            width: 42px;

            height: 42px;

            border-radius: 12px;

            background:
                var(--primary);

            color:
                #ffffff;

            display: flex;

            align-items: center;

            justify-content: center;

            font-weight: 700;

            font-size: 20px;
        }

        .logo-text {

            font-size: 21px;

            font-weight: 700;

            color:
                var(--primary);
        }

        html.dark-mode .logo-text {

            color:
                #ffffff;
        }

        .admin-name {

            font-size: 13px;

            font-weight: 700;

            color:
                var(--text);

            padding:
                0 10px 4px;
        }

        .admin-role {

            font-size: 11px;

            color:
                var(--muted);

            padding:
                0 10px 18px;
        }

        .sidebar-menu {

            display: flex;

            flex-direction: column;

            gap: 7px;
        }

        .sidebar-link {

            display: flex;

            align-items: center;

            gap: 12px;

            padding:
                12px 14px;

            border-radius: 10px;

            text-decoration: none;

            color:
                var(--muted);

            font-size: 14px;

            transition:
                0.2s ease;
        }

        .sidebar-link:hover {

            background:
                rgba(61, 139, 255, 0.08);

            color:
                var(--primary-light);
        }

        .sidebar-link.active {

            background:
                var(--primary);

            color:
                #ffffff;
        }

        .sidebar-icon {

            width: 22px;

            text-align: center;
        }

        /* =====================================================
           MAIN
        ====================================================== */

        .main-content {

            flex: 1;

            margin-left: 250px;

            min-width: 0;
        }

        /* =====================================================
           TOPBAR
        ====================================================== */

        .topbar {

            height: 76px;

            background:
                var(--card);

            border-bottom:
                1px solid var(--border);

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding:
                0 30px;

            position: sticky;

            top: 0;

            z-index: 50;
        }

        .page-title h1 {

            font-size: 22px;

            margin-bottom: 4px;
        }

        .page-title p {

            font-size: 12px;

            color:
                var(--muted);
        }

        .topbar-right {

            display: flex;

            align-items: center;

            gap: 15px;
        }

        .theme-button {

            width: 38px;

            height: 38px;

            border:
                1px solid var(--border);

            border-radius: 10px;

            background:
                var(--card);

            color:
                var(--text);

            cursor: pointer;

            font-size: 17px;

            display: flex;

            align-items: center;

            justify-content: center;
        }

        .admin-profile {

            display: flex;

            align-items: center;

            gap: 10px;
        }

        .admin-avatar {

            width: 40px;

            height: 40px;

            border-radius: 50%;

            background:
                var(--primary);

            color:
                #ffffff;

            display: flex;

            align-items: center;

            justify-content: center;

            font-weight: 700;
        }

        .admin-profile-name {

            font-size: 13px;

            font-weight: 700;

            max-width: 180px;

            overflow: hidden;

            white-space: nowrap;

            text-overflow: ellipsis;
        }

        .admin-profile-role {

            font-size: 11px;

            color:
                var(--muted);

            margin-top: 2px;
        }

        /* =====================================================
           CONTENT
        ====================================================== */

        .content {

            padding: 30px;
        }

        /* =====================================================
           PAGE HEADER
        ====================================================== */

        .content-header {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 20px;

            margin-bottom: 22px;
        }

        .content-header h2 {

            font-size: 21px;

            margin-bottom: 5px;
        }

        .content-header p {

            font-size: 12px;

            color:
                var(--muted);
        }

        /* =====================================================
           SEARCH
        ====================================================== */

        .search-form {

            display: flex;

            align-items: center;

            gap: 8px;
        }

        .search-input {

            width: 260px;

            height: 40px;

            padding:
                0 13px;

            border:
                1px solid var(--border);

            border-radius: 9px;

            outline: none;

            background:
                var(--card);

            color:
                var(--text);

            font-size: 12px;
        }

        .search-input:focus {

            border-color:
                var(--primary-light);
        }

        .search-button {

            height: 40px;

            padding:
                0 15px;

            border: none;

            border-radius: 9px;

            background:
                var(--primary);

            color:
                #ffffff;

            cursor: pointer;

            font-size: 12px;

            font-weight: 600;
        }

        .clear-search {

            height: 40px;

            padding:
                0 13px;

            border:
                1px solid var(--border);

            border-radius: 9px;

            background:
                var(--card);

            color:
                var(--text);

            text-decoration: none;

            display: flex;

            align-items: center;

            font-size: 12px;
        }

        /* =====================================================
           ROLE TABS
        ====================================================== */

        .role-tabs {

            display: flex;

            gap: 8px;

            flex-wrap: wrap;

            margin-bottom: 18px;
        }

        .role-tab {

            text-decoration: none;

            padding:
                9px 15px;

            border:
                1px solid var(--border);

            border-radius: 9px;

            background:
                var(--card);

            color:
                var(--muted);

            font-size: 12px;

            font-weight: 600;

            transition:
                0.2s ease;
        }

        .role-tab:hover {

            border-color:
                var(--primary-light);

            color:
                var(--primary-light);
        }

        .role-tab.active {

            background:
                var(--primary);

            border-color:
                var(--primary);

            color:
                #ffffff;
        }

        /* =====================================================
           TABLE CARD
        ====================================================== */

        .table-card {

            background:
                var(--card);

            border:
                1px solid var(--border);

            border-radius: 15px;

            box-shadow:
                var(--shadow);

            overflow: hidden;
        }

        .table-wrapper {

            width: 100%;

            overflow-x: auto;
        }

        table {

            width: 100%;

            min-width: 850px;

            border-collapse: collapse;
        }

        thead {

            background:
                rgba(107, 114, 128, 0.06);
        }

        th {

            text-align: left;

            padding:
                14px 18px;

            font-size: 11px;

            color:
                var(--muted);

            font-weight: 700;

            text-transform: uppercase;

            letter-spacing: 0.3px;

            border-bottom:
                1px solid var(--border);
        }

        td {

            padding:
                15px 18px;

            font-size: 12px;

            border-bottom:
                1px solid var(--border);

            color:
                var(--text);

            vertical-align: middle;
        }

        tbody tr:last-child td {

            border-bottom: none;
        }

        tbody tr:hover {

            background:
                rgba(61, 139, 255, 0.035);
        }

        .user-name {

            font-weight: 700;

            margin-bottom: 3px;
        }

        .user-username {

            font-size: 10px;

            color:
                var(--muted);
        }

        .email-text {

            word-break: break-word;
        }

        /* =====================================================
           ROLE BADGE
        ====================================================== */

        .role-badge {

            display: inline-flex;

            align-items: center;

            padding:
                5px 9px;

            border-radius: 20px;

            font-size: 10px;

            font-weight: 700;

            text-transform: capitalize;
        }

        .role-student {

            background:
                rgba(61, 139, 255, 0.12);

            color:
                var(--primary-light);
        }

        .role-professor {

            background:
                rgba(22, 163, 74, 0.12);

            color:
                var(--success);
        }

        .role-admin {

            background:
                rgba(245, 158, 11, 0.14);

            color:
                #b45309;
        }

        html.dark-mode .role-admin {

            color:
                #fbbf24;
        }

        /* =====================================================
           COURSE DETAILS
        ====================================================== */

        .details-main {

            font-weight: 600;

            margin-bottom: 3px;
        }

        .details-sub {

            font-size: 10px;

            color:
                var(--muted);
        }

        .empty-value {

            color:
                var(--muted);
        }

        /* =====================================================
           ACTION BUTTONS
        ====================================================== */

        .actions {

            display: flex;

            align-items: center;

            gap: 7px;

            white-space: nowrap;
        }

        .action-button {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            padding:
                7px 10px;

            border-radius: 7px;

            text-decoration: none;

            font-size: 10px;

            font-weight: 700;

            border: none;

            cursor: pointer;
        }

        .edit-button {

            background:
                rgba(61, 139, 255, 0.11);

            color:
                var(--primary-light);
        }

        .delete-button {

            background:
                rgba(220, 38, 38, 0.10);

            color:
                var(--danger);
        }

        .disabled-button {

            background:
                rgba(107, 114, 128, 0.10);

            color:
                var(--muted);

            cursor:
                not-allowed;
        }

        /* =====================================================
           EMPTY STATE
        ====================================================== */

        .empty-state {

            text-align: center;

            padding: 55px 20px;

            color:
                var(--muted);
        }

        .empty-icon {

            font-size: 35px;

            margin-bottom: 12px;
        }

        .empty-title {

            font-size: 15px;

            font-weight: 700;

            color:
                var(--text);

            margin-bottom: 5px;
        }

        .empty-description {

            font-size: 11px;
        }

        /* =====================================================
           MOBILE HEADER
        ====================================================== */

        .mobile-header {

            display: none;

            height: 65px;

            background:
                var(--card);

            border-bottom:
                1px solid var(--border);

            padding:
                0 18px;

            align-items: center;

            justify-content: space-between;

            position: sticky;

            top: 0;

            z-index: 80;
        }

        .mobile-logo {

            font-size: 19px;

            font-weight: 700;

            color:
                var(--primary);
        }

        html.dark-mode .mobile-logo {

            color:
                #ffffff;
        }

        /* =====================================================
           RESPONSIVE
        ====================================================== */

        @media (max-width: 900px) {

            .sidebar {

                display: none;
            }

            .main-content {

                margin-left: 0;
            }

            .topbar {

                display: none;
            }

            .mobile-header {

                display: flex;
            }

            .content {

                padding: 20px;
            }

            .content-header {

                align-items: flex-start;

                flex-direction: column;
            }

            .search-form {

                width: 100%;
            }

            .search-input {

                flex: 1;

                width: auto;
            }
        }

        @media (max-width: 500px) {

            .content {

                padding: 15px;
            }

            .search-form {

                flex-wrap: wrap;
            }

            .search-input {

                width: 100%;

                flex: none;
            }

            .search-button,
            .clear-search {

                flex: 1;

                justify-content: center;
            }

            .role-tabs {

                gap: 6px;
            }

            .role-tab {

                padding:
                    8px 11px;
            }
        }

    </style>

</head>

<body>

<div class="dashboard-wrapper">

    <!-- =====================================================
         SIDEBAR
    ====================================================== -->

    <aside class="sidebar">

        <div class="logo-section">

            <div class="logo-icon">
                N
            </div>

            <div class="logo-text">
                NoteSphere
            </div>

        </div>

        <div class="admin-name">

            <?php
            echo e($admin_name);
            ?>

        </div>

        <div class="admin-role">
            Administrator
        </div>

        <nav class="sidebar-menu">

            <a
                href="admin_dashboard.php"
                class="sidebar-link"
            >
                <span class="sidebar-icon">
                    ⌂
                </span>

                Dashboard
            </a>

            <a
                href="manage_users.php?role=all"
                class="sidebar-link active"
            >
                <span class="sidebar-icon">
                    👥
                </span>

                Manage Users
            </a>

            <a
                href="manage_notes.php"
                class="sidebar-link"
            >
                <span class="sidebar-icon">
                    📚
                </span>

                Manage Notes
            </a>

            <a
                href="manage_announcements.php"
                class="sidebar-link"
            >
                <span class="sidebar-icon">
                    📢
                </span>

                Announcements
            </a>

            <a
                href="manage_storage.php"
                class="sidebar-link"
            >
                <span class="sidebar-icon">
                    💾
                </span>

                Storage
            </a>

            <a
                href="../auth/logout.php"
                class="sidebar-link"
                id="logoutLink"
            >
                <span class="sidebar-icon">
                    ↪
                </span>

                Logout
            </a>

        </nav>

    </aside>

    <!-- =====================================================
         MAIN
    ====================================================== -->

    <main class="main-content">

        <!-- MOBILE HEADER -->

        <div class="mobile-header">

            <div class="mobile-logo">
                NoteSphere
            </div>

            <button
                type="button"
                class="theme-button"
                id="mobileThemeButton"
            >
                ☾
            </button>

        </div>

        <!-- TOPBAR -->

        <header class="topbar">

            <div class="page-title">

                <h1>
                    Manage Users
                </h1>

                <p>
                    Edit or remove system accounts.
                </p>

            </div>

            <div class="topbar-right">

                <button
                    type="button"
                    class="theme-button"
                    id="themeButton"
                >
                    ☾
                </button>

                <div class="admin-profile">

                    <div class="admin-avatar">

                        <?php

                        $initial =
                            strtoupper(
                                substr(
                                    trim(
                                        $admin_name
                                    ),
                                    0,
                                    1
                                )
                            );

                        echo e($initial);

                        ?>

                    </div>

                    <div>

                        <div class="admin-profile-name">

                            <?php
                            echo e(
                                $admin_name
                            );
                            ?>

                        </div>

                        <div class="admin-profile-role">
                            Administrator
                        </div>

                    </div>

                </div>

            </div>

        </header>

        <!-- CONTENT -->

        <section class="content">

            <!-- HEADER -->

            <div class="content-header">

                <div>

                    <h2>
                        Manage Users
                    </h2>

                    <p>
                        View and manage all NoteSphere system accounts.
                    </p>

                </div>

                <!-- SEARCH -->

                <form
                    method="GET"
                    class="search-form"
                >

                    <input
                        type="hidden"
                        name="role"
                        value="<?php
                            echo e(
                                $selected_role
                            );
                        ?>"
                    >

                    <input
                        type="text"
                        name="search"
                        class="search-input"
                        placeholder="Search users..."
                        value="<?php
                            echo e(
                                $search
                            );
                        ?>"
                    >

                    <button
                        type="submit"
                        class="search-button"
                    >
                        Search
                    </button>

                    <?php if ($search !== ''): ?>

                        <a
                            href="manage_users.php?role=<?php echo e($selected_role); ?>"
                            class="clear-search"
                        >
                            Clear
                        </a>

                    <?php endif; ?>

                </form>

            </div>

            <!-- ROLE TABS -->

            <div class="role-tabs">

                <a
                    href="manage_users.php?role=all"
                    class="role-tab <?php
                        echo $selected_role === 'all'
                            ? 'active'
                            : '';
                    ?>"
                >
                    All Users
                    (<?php
                        echo number_format(
                            $count_all
                        );
                    ?>)
                </a>

                <a
                    href="manage_users.php?role=student"
                    class="role-tab <?php
                        echo $selected_role === 'student'
                            ? 'active'
                            : '';
                    ?>"
                >
                    Students
                    (<?php
                        echo number_format(
                            $count_students
                        );
                    ?>)
                </a>

                <a
                    href="manage_users.php?role=professor"
                    class="role-tab <?php
                        echo $selected_role === 'professor'
                            ? 'active'
                            : '';
                    ?>"
                >
                    Professors
                    (<?php
                        echo number_format(
                            $count_professors
                        );
                    ?>)
                </a>

                <a
                    href="manage_users.php?role=admin"
                    class="role-tab <?php
                        echo $selected_role === 'admin'
                            ? 'active'
                            : '';
                    ?>"
                >
                    Admins
                    (<?php
                        echo number_format(
                            $count_admins
                        );
                    ?>)
                </a>

            </div>

            <!-- TABLE -->

            <div class="table-card">

                <?php if (!empty($users)): ?>

                    <div class="table-wrapper">

                        <table>

                            <thead>

                                <tr>

                                    <th>
                                        Full Name
                                    </th>

                                    <th>
                                        Email
                                    </th>

                                    <th>
                                        Role
                                    </th>

                                    <th>
                                        Course / Details
                                    </th>

                                    <th>
                                        Date Created
                                    </th>

                                    <th>
                                        Actions
                                    </th>

                                </tr>

                            </thead>

                            <tbody>

                                <?php foreach (
                                    $users
                                    as $user
                                ): ?>

                                    <?php

                                    // IMPORTANT:
                                    // User ID is explicitly retrieved
                                    $user_id =
                                        isset(
                                            $user['id']
                                        )
                                            ? (int)$user['id']
                                            : 0;

                                    $first_name =
                                        trim(
                                            $user[
                                                'first_name'
                                            ] ?? ''
                                        );

                                    $last_name =
                                        trim(
                                            $user[
                                                'last_name'
                                            ] ?? ''
                                        );

                                    $full_name =
                                        trim(
                                            $first_name .
                                            ' ' .
                                            $last_name
                                        );

                                    if (
                                        $full_name === ''
                                    ) {

                                        $full_name =
                                            !empty(
                                                $user[
                                                    'username'
                                                ]
                                            )
                                                ? $user[
                                                    'username'
                                                ]
                                                : 'Unnamed User';
                                    }

                                    $role =
                                        strtolower(
                                            trim(
                                                $user[
                                                    'role'
                                                ] ?? ''
                                            )
                                        );

                                    $email =
                                        $user[
                                            'email'
                                        ] ?? '—';

                                    $course =
                                        trim(
                                            $user[
                                                'course'
                                            ] ?? ''
                                        );

                                    $year_level =
                                        trim(
                                            $user[
                                                'year_level'
                                            ] ?? ''
                                        );

                                    $section =
                                        trim(
                                            $user[
                                                'section'
                                            ] ?? ''
                                        );

                                    $created_at =
                                        $user[
                                            'created_at'
                                        ] ?? '';

                                    ?>

                                    <tr>

                                        <!-- NAME -->

                                        <td>

                                            <div class="user-name">

                                                <?php
                                                echo e(
                                                    $full_name
                                                );
                                                ?>

                                            </div>

                                            <?php
                                            if (
                                                !empty(
                                                    $user[
                                                        'username'
                                                    ]
                                                )
                                            ):
                                            ?>

                                                <div class="user-username">

                                                    @<?php
                                                    echo e(
                                                        $user[
                                                            'username'
                                                        ]
                                                    );
                                                    ?>

                                                </div>

                                            <?php endif; ?>

                                        </td>

                                        <!-- EMAIL -->

                                        <td>

                                            <div class="email-text">

                                                <?php
                                                echo e(
                                                    $email
                                                );
                                                ?>

                                            </div>

                                        </td>

                                        <!-- ROLE -->

                                        <td>

                                            <?php

                                            $role_class =
                                                'role-' .
                                                (
                                                    in_array(
                                                        $role,
                                                        [
                                                            'student',
                                                            'professor',
                                                            'admin'
                                                        ],
                                                        true
                                                    )
                                                        ? $role
                                                        : 'student'
                                                );

                                            ?>

                                            <span
                                                class="role-badge <?php
                                                    echo e(
                                                        $role_class
                                                    );
                                                ?>"
                                            >

                                                <?php
                                                echo e(
                                                    $role !== ''
                                                        ? ucfirst(
                                                            $role
                                                        )
                                                        : 'Unknown'
                                                );
                                                ?>

                                            </span>

                                        </td>

                                        <!-- COURSE / DETAILS -->

                                        <td>

                                            <?php if (
                                                $role ===
                                                'student'
                                            ): ?>

                                                <?php if (
                                                    $course !== ''
                                                ): ?>

                                                    <div class="details-main">

                                                        <?php
                                                        echo e(
                                                            $course
                                                        );
                                                        ?>

                                                    </div>

                                                <?php else: ?>

                                                    <div class="details-main empty-value">
                                                        —
                                                    </div>

                                                <?php endif; ?>

                                                <?php

                                                $student_details = [];

                                                if (
                                                    $year_level !== ''
                                                ) {

                                                    $student_details[] =
                                                        $year_level;
                                                }

                                                if (
                                                    $section !== ''
                                                ) {

                                                    $student_details[] =
                                                        'Section ' .
                                                        $section;
                                                }

                                                ?>

                                                <?php if (
                                                    !empty(
                                                        $student_details
                                                    )
                                                ): ?>

                                                    <div class="details-sub">

                                                        <?php
                                                        echo e(
                                                            implode(
                                                                ' • ',
                                                                $student_details
                                                            )
                                                        );
                                                        ?>

                                                    </div>

                                                <?php endif; ?>

                                            <?php elseif (
                                                $role ===
                                                'professor'
                                            ): ?>

                                                <div class="details-main">
                                                    Professor
                                                </div>

                                                <?php if (
                                                    $course !== ''
                                                ): ?>

                                                    <div class="details-sub">

                                                        <?php
                                                        echo e(
                                                            $course
                                                        );
                                                        ?>

                                                    </div>

                                                <?php endif; ?>

                                            <?php elseif (
                                                $role ===
                                                'admin'
                                            ): ?>

                                                <div class="details-main">
                                                    Administrator
                                                </div>

                                            <?php else: ?>

                                                <div class="empty-value">
                                                    —
                                                </div>

                                            <?php endif; ?>

                                        </td>

                                        <!-- DATE -->

                                        <td>

                                            <?php

                                            if (
                                                $created_at !== ''
                                            ) {

                                                $timestamp =
                                                    strtotime(
                                                        $created_at
                                                    );

                                                if (
                                                    $timestamp !== false
                                                ) {

                                                    echo e(
                                                        date(
                                                            'M d, Y',
                                                            $timestamp
                                                        )
                                                    );

                                                } else {

                                                    echo e(
                                                        $created_at
                                                    );
                                                }

                                            } else {

                                                echo '—';
                                            }

                                            ?>

                                        </td>

                                        <!-- ACTIONS -->

                                        <td>

                                            <div class="actions">

                                                <?php if (
                                                    $user_id > 0
                                                ): ?>

                                                    <a
                                                        href="edit_user.php?id=<?php
                                                            echo $user_id;
                                                        ?>"
                                                        class="action-button edit-button"
                                                    >
                                                        Edit
                                                    </a>

                                                    <?php if (
                                                        $user_id ===
                                                        $current_admin_id
                                                    ): ?>

                                                        <button
                                                            type="button"
                                                            class="action-button disabled-button"
                                                            disabled
                                                            title="You cannot delete your own account."
                                                        >
                                                            Delete
                                                        </button>

                                                    <?php else: ?>

                                                        <button
                                                            type="button"
                                                            class="action-button delete-button delete-user-button"
                                                            data-user-id="<?php
                                                                echo $user_id;
                                                            ?>"
                                                            data-user-name="<?php
                                                                echo e(
                                                                    $full_name
                                                                );
                                                            ?>"
                                                        >
                                                            Delete
                                                        </button>

                                                    <?php endif; ?>

                                                <?php else: ?>

                                                    <span class="empty-value">
                                                        —
                                                    </span>

                                                <?php endif; ?>

                                            </div>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php else: ?>

                    <div class="empty-state">

                        <div class="empty-icon">
                            👥
                        </div>

                        <div class="empty-title">
                            No users found
                        </div>

                        <div class="empty-description">

                            <?php if (
                                $search !== ''
                            ): ?>

                                No account matches your search.

                            <?php elseif (
                                $selected_role !== 'all'
                            ): ?>

                                There are no
                                <?php
                                echo e(
                                    $selected_role
                                );
                                ?>
                                accounts yet.

                            <?php else: ?>

                                There are no user accounts
                                in the system yet.

                            <?php endif; ?>

                        </div>

                    </div>

                <?php endif; ?>

            </div>

        </section>

    </main>

</div>

<!-- =========================================================
     DELETE FORM
========================================================== -->

<form
    method="POST"
    id="deleteUserForm"
    style="display:none;"
>

    <input
        type="hidden"
        name="action"
        value="delete_user"
    >

    <input
        type="hidden"
        name="csrf_token"
        value="<?php
            echo e(
                $csrf_token
            );
        ?>"
    >

    <input
        type="hidden"
        name="user_id"
        id="deleteUserId"
        value=""
    >

</form>

<!-- =========================================================
     JAVASCRIPT
========================================================== -->

<script>

    // ========================================================
    // THEME
    // ========================================================

    const themeButton =
        document.getElementById(
            'themeButton'
        );

    const mobileThemeButton =
        document.getElementById(
            'mobileThemeButton'
        );

    function updateThemeIcons() {

        const isDark =
            document.documentElement
                .classList
                .contains('dark-mode');

        if (themeButton) {

            themeButton.textContent =
                isDark
                    ? '☀'
                    : '☾';
        }

        if (mobileThemeButton) {

            mobileThemeButton.textContent =
                isDark
                    ? '☀'
                    : '☾';
        }
    }

    function toggleTheme() {

        const html =
            document.documentElement;

        const isDark =
            html.classList.toggle(
                'dark-mode'
            );

        localStorage.setItem(
            'notesphere_admin_theme',
            isDark
                ? 'dark'
                : 'light'
        );

        updateThemeIcons();
    }

    if (themeButton) {

        themeButton.addEventListener(
            'click',
            toggleTheme
        );
    }

    if (mobileThemeButton) {

        mobileThemeButton.addEventListener(
            'click',
            toggleTheme
        );
    }

    updateThemeIcons();

    // ========================================================
    // DELETE USER
    // ========================================================

    const deleteButtons =
        document.querySelectorAll(
            '.delete-user-button'
        );

    const deleteForm =
        document.getElementById(
            'deleteUserForm'
        );

    const deleteUserId =
        document.getElementById(
            'deleteUserId'
        );

    deleteButtons.forEach(
        function (button) {

            button.addEventListener(
                'click',
                function () {

                    const userId =
                        this.getAttribute(
                            'data-user-id'
                        );

                    const userName =
                        this.getAttribute(
                            'data-user-name'
                        );

                    if (
                        !userId ||
                        !deleteForm ||
                        !deleteUserId
                    ) {

                        return;
                    }

                    Swal.fire({

                        icon: 'warning',

                        title:
                            'Delete User?',

                        html:
                            'Are you sure you want to delete ' +
                            '<strong>' +
                            escapeHtml(
                                userName
                            ) +
                            '</strong>?' +
                            '<br><br>' +
                            'This action cannot be undone.',

                        showCancelButton: true,

                        confirmButtonText:
                            'Yes, Delete',

                        cancelButtonText:
                            'Cancel',

                        confirmButtonColor:
                            '#dc2626',

                        reverseButtons: true

                    }).then(
                        function (result) {

                            if (
                                result.isConfirmed
                            ) {

                                deleteUserId.value =
                                    userId;

                                deleteForm.submit();
                            }

                        }
                    );

                }
            );

        }
    );

    // ========================================================
    // SAFE HTML FOR SWEETALERT
    // ========================================================

    function escapeHtml(value) {

        return String(value)
            .replace(
                /&/g,
                '&amp;'
            )
            .replace(
                /</g,
                '&lt;'
            )
            .replace(
                />/g,
                '&gt;'
            )
            .replace(
                /"/g,
                '&quot;'
            )
            .replace(
                /'/g,
                '&#039;'
            );
    }

    // ========================================================
    // LOGOUT
    // ========================================================

    const logoutLink =
        document.getElementById(
            'logoutLink'
        );

    if (logoutLink) {

        logoutLink.addEventListener(
            'click',
            function (event) {

                event.preventDefault();

                const logoutUrl =
                    this.getAttribute(
                        'href'
                    );

                Swal.fire({

                    icon: 'question',

                    title:
                        'Logout?',

                    text:
                        'Are you sure you want to logout?',

                    showCancelButton: true,

                    confirmButtonText:
                        'Yes, Logout',

                    cancelButtonText:
                        'Cancel',

                    reverseButtons: true

                }).then(
                    function (result) {

                        if (
                            result.isConfirmed
                        ) {

                            window.location.href =
                                logoutUrl;
                        }

                    }
                );

            }
        );
    }

</script>

<!-- =========================================================
     SERVER SUCCESS MESSAGE
========================================================== -->

<?php if (
    $success_message !== ''
): ?>

<script>

    Swal.fire({

        icon: 'success',

        title: 'Success',

        text:
            <?php
            echo json_encode(
                $success_message
            );
            ?>,

        confirmButtonText:
            'OK'

    });

</script>

<?php endif; ?>

<!-- =========================================================
     SERVER ERROR MESSAGE
========================================================== -->

<?php if (
    $error_message !== ''
): ?>

<script>

    Swal.fire({

        icon: 'error',

        title:
            'Something went wrong',

        text:
            <?php
            echo json_encode(
                $error_message
            );
            ?>,

        confirmButtonText:
            'OK'

    });

</script>

<?php endif; ?>

</body>

</html>