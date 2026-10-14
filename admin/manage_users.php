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

$current_admin_id = (int) $_SESSION['user_id'];


// =========================================================
// CSRF TOKEN
// =========================================================

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];


// =========================================================
// GET CURRENT ADMIN NAME
// =========================================================

$admin_name = 'Administrator';

try {

    $stmt = $conn->prepare("
        SELECT full_name
        FROM users
        WHERE user_id = ?
        LIMIT 1
    ");

    $stmt->execute([$current_admin_id]);

    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin && !empty($admin['full_name'])) {
        $admin_name = $admin['full_name'];
    }

} catch (PDOException $e) {

    $admin_name = 'Administrator';
}


// =========================================================
// ADD PROFESSOR
// =========================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['add_professor'])
) {

    $posted_token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($csrf_token, $posted_token)) {

        $_SESSION['swal'] = [
            'icon' => 'error',
            'title' => 'Security Error',
            'text' => 'Invalid security token. Please try again.'
        ];

        header("Location: manage_users.php");
        exit();
    }

    $professor_name = trim($_POST['professor_name'] ?? '');
    $professor_email = trim($_POST['professor_email'] ?? '');
    $temporary_password = $_POST['temporary_password'] ?? '';

    if ($professor_name === '') {

        $_SESSION['swal'] = [
            'icon' => 'warning',
            'title' => 'Name Required',
            'text' => 'Please enter the professor name.'
        ];

        header("Location: manage_users.php");
        exit();
    }

    if (mb_strlen($professor_name) > 150) {

        $_SESSION['swal'] = [
            'icon' => 'warning',
            'title' => 'Name Too Long',
            'text' => 'Professor name must not exceed 150 characters.'
        ];

        header("Location: manage_users.php");
        exit();
    }

    if ($professor_email === '') {

        $_SESSION['swal'] = [
            'icon' => 'warning',
            'title' => 'Email Required',
            'text' => 'Please enter the professor email.'
        ];

        header("Location: manage_users.php");
        exit();
    }

    if (!filter_var($professor_email, FILTER_VALIDATE_EMAIL)) {

        $_SESSION['swal'] = [
            'icon' => 'warning',
            'title' => 'Invalid Email',
            'text' => 'Please enter a valid email address.'
        ];

        header("Location: manage_users.php");
        exit();
    }

    if ($temporary_password === '') {

        $_SESSION['swal'] = [
            'icon' => 'warning',
            'title' => 'Temporary Password Required',
            'text' => 'Please enter a temporary password.'
        ];

        header("Location: manage_users.php");
        exit();
    }

    if (strlen($temporary_password) < 6) {

        $_SESSION['swal'] = [
            'icon' => 'warning',
            'title' => 'Password Too Short',
            'text' => 'Temporary password must contain at least 6 characters.'
        ];

        header("Location: manage_users.php");
        exit();
    }

    if (strlen($temporary_password) > 255) {

        $_SESSION['swal'] = [
            'icon' => 'warning',
            'title' => 'Password Too Long',
            'text' => 'Temporary password is too long.'
        ];

        header("Location: manage_users.php");
        exit();
    }

    try {

        $email_check = $conn->prepare("
            SELECT user_id, full_name, role
            FROM users
            WHERE email = ?
            LIMIT 1
        ");

        $email_check->execute([
            $professor_email
        ]);

        $existing_user = $email_check->fetch(PDO::FETCH_ASSOC);

        if ($existing_user) {

            $_SESSION['swal'] = [
                'icon' => 'warning',
                'title' => 'Email Already Registered',
                'text' => 'This email is already registered to another account.'
            ];

            header("Location: manage_users.php");
            exit();
        }

        $hashed_password = password_hash(
            $temporary_password,
            PASSWORD_DEFAULT
        );

        $insert_stmt = $conn->prepare("
            INSERT INTO users
            (
                full_name,
                email,
                password,
                role,
                created_at
            )
            VALUES
            (
                ?,
                ?,
                ?,
                'professor',
                NOW()
            )
        ");

        $insert_stmt->execute([
            $professor_name,
            $professor_email,
            $hashed_password
        ]);

        $_SESSION['swal'] = [
            'icon' => 'success',
            'title' => 'Professor Added',
            'text' => $professor_name . ' has been added successfully as a professor.'
        ];

    } catch (PDOException $e) {

        $_SESSION['swal'] = [
            'icon' => 'error',
            'title' => 'Add Professor Failed',
            'text' => 'An error occurred while creating the professor account.'
        ];
    }

    header("Location: manage_users.php");
    exit();
}


// =========================================================
// DELETE USER
// =========================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['delete_user'])
) {

    $posted_token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($csrf_token, $posted_token)) {

        $_SESSION['swal'] = [
            'icon' => 'error',
            'title' => 'Security Error',
            'text' => 'Invalid security token. Please try again.'
        ];

        header("Location: manage_users.php");
        exit();
    }

    $delete_user_id = (int) ($_POST['user_id'] ?? 0);

    if ($delete_user_id === $current_admin_id) {

        $_SESSION['swal'] = [
            'icon' => 'warning',
            'title' => 'Action Not Allowed',
            'text' => 'You cannot delete your own administrator account.'
        ];

        header("Location: manage_users.php");
        exit();
    }

    if ($delete_user_id <= 0) {

        $_SESSION['swal'] = [
            'icon' => 'error',
            'title' => 'Invalid User',
            'text' => 'The selected user is invalid.'
        ];

        header("Location: manage_users.php");
        exit();
    }

    try {

        $check_stmt = $conn->prepare("
            SELECT full_name, role
            FROM users
            WHERE user_id = ?
            LIMIT 1
        ");

        $check_stmt->execute([
            $delete_user_id
        ]);

        $user_to_delete = $check_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user_to_delete) {

            $_SESSION['swal'] = [
                'icon' => 'error',
                'title' => 'User Not Found',
                'text' => 'The selected user no longer exists.'
            ];

            header("Location: manage_users.php");
            exit();
        }

        $delete_stmt = $conn->prepare("
            DELETE FROM users
            WHERE user_id = ?
            LIMIT 1
        ");

        $delete_stmt->execute([
            $delete_user_id
        ]);

        if ($delete_stmt->rowCount() > 0) {

            $_SESSION['swal'] = [
                'icon' => 'success',
                'title' => 'User Deleted',
                'text' => $user_to_delete['full_name'] . ' has been removed successfully.'
            ];

        } else {

            $_SESSION['swal'] = [
                'icon' => 'error',
                'title' => 'Delete Failed',
                'text' => 'The user could not be deleted.'
            ];
        }

    } catch (PDOException $e) {

        $_SESSION['swal'] = [
            'icon' => 'error',
            'title' => 'Delete Failed',
            'text' => 'An error occurred while deleting the user.'
        ];
    }

    header("Location: manage_users.php");
    exit();
}


// =========================================================
// FILTERS
// =========================================================

$search = trim($_GET['search'] ?? '');

$role_filter = $_GET['role'] ?? 'all';

$allowed_roles = [
    'all',
    'student',
    'professor',
    'admin'
];

if (!in_array($role_filter, $allowed_roles, true)) {
    $role_filter = 'all';
}


// =========================================================
// GET USER COUNTS
// =========================================================

$total_users = 0;
$total_students = 0;
$total_professors = 0;
$total_admins = 0;

try {

    $count_stmt = $conn->query("
        SELECT
            COUNT(*) AS total_users,

            SUM(
                CASE
                    WHEN role = 'student'
                    THEN 1
                    ELSE 0
                END
            ) AS total_students,

            SUM(
                CASE
                    WHEN role = 'professor'
                    THEN 1
                    ELSE 0
                END
            ) AS total_professors,

            SUM(
                CASE
                    WHEN role = 'admin'
                    THEN 1
                    ELSE 0
                END
            ) AS total_admins

        FROM users
    ");

    $counts = $count_stmt->fetch(PDO::FETCH_ASSOC);

    if ($counts) {

        $total_users =
            (int) ($counts['total_users'] ?? 0);

        $total_students =
            (int) ($counts['total_students'] ?? 0);

        $total_professors =
            (int) ($counts['total_professors'] ?? 0);

        $total_admins =
            (int) ($counts['total_admins'] ?? 0);
    }

} catch (PDOException $e) {

    $total_users = 0;
    $total_students = 0;
    $total_professors = 0;
    $total_admins = 0;
}


// =========================================================
// GET USERS
// =========================================================

$users = [];

try {

    $sql = "
        SELECT
            user_id,
            full_name,
            email,
            course,
            student_status,
            year_level,
            section,
            enrolled_subjects,
            role,
            created_at,
            profile_pic
        FROM users
        WHERE 1 = 1
    ";

    $params = [];

    if ($role_filter !== 'all') {

        $sql .= " AND role = ?";

        $params[] = $role_filter;
    }

    if ($search !== '') {

        $sql .= "
            AND (
                full_name LIKE ?
                OR email LIKE ?
                OR course LIKE ?
                OR student_status LIKE ?
                OR year_level LIKE ?
                OR section LIKE ?
                OR enrolled_subjects LIKE ?
            )
        ";

        $search_value = '%' . $search . '%';

        $params[] = $search_value;
        $params[] = $search_value;
        $params[] = $search_value;
        $params[] = $search_value;
        $params[] = $search_value;
        $params[] = $search_value;
        $params[] = $search_value;
    }

    $sql .= "
        ORDER BY
            CASE
                WHEN role = 'admin' THEN 1
                WHEN role = 'professor' THEN 2
                WHEN role = 'student' THEN 3
                ELSE 4
            END,
            full_name ASC
    ";

    $stmt = $conn->prepare($sql);

    $stmt->execute($params);

    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $users = [];
}


// =========================================================
// HELPER FUNCTIONS
// =========================================================

function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


function getInitials($name)
{
    $name = trim((string) $name);

    if ($name === '') {
        return 'U';
    }

    $parts = preg_split('/\s+/', $name);

    if (count($parts) === 1) {
        return strtoupper(
            substr($parts[0], 0, 1)
        );
    }

    $first = substr(
        $parts[0],
        0,
        1
    );

    $last = substr(
        $parts[count($parts) - 1],
        0,
        1
    );

    return strtoupper(
        $first . $last
    );
}


function formatRole($role)
{
    switch ($role) {

        case 'admin':
            return 'Administrator';

        case 'professor':
            return 'Professor';

        case 'student':
            return 'Student';

        default:
            return ucfirst((string) $role);
    }
}


function getRoleClass($role)
{
    switch ($role) {

        case 'admin':
            return 'role-admin';

        case 'professor':
            return 'role-professor';

        case 'student':
            return 'role-student';

        default:
            return 'role-default';
    }
}


function getUserDetails($user)
{
    $role = $user['role'] ?? '';

    if ($role === 'student') {

        $details = [];

        if (!empty($user['student_status'])) {
            $details[] = $user['student_status'];
        }

        if (
            $user['year_level'] !== null &&
            $user['year_level'] !== ''
        ) {
            $details[] =
                'Year ' . $user['year_level'];
        }

        if (!empty($user['section'])) {
            $details[] =
                'Section ' . $user['section'];
        }

        return !empty($details)
            ? implode(' • ', $details)
            : 'Student Account';
    }

    if ($role === 'professor') {

        if (!empty($user['course'])) {

            return 'Professor • ' .
                $user['course'];
        }

        return 'Professor Account';
    }

    if ($role === 'admin') {
        return 'Administrator Account';
    }

    return 'System Account';
}


// =========================================================
// SWEETALERT SESSION MESSAGE
// =========================================================

$swal = $_SESSION['swal'] ?? null;

unset($_SESSION['swal']);

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Manage Users | NoteSphere</title>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>

        :root {

            --primary: #233961;
            --primary-light: #3D8BFF;

            --bg: #f5f7fb;
            --card: #ffffff;

            --text: #1e293b;
            --muted: #64748b;
            --border: #e2e8f0;

            --success: #16a34a;
            --warning: #f59e0b;
            --danger: #dc2626;

            --shadow:
                0 8px 25px rgba(15, 23, 42, 0.06);

            --radius: 16px;
        }


        /* =====================================================
           SYSTEM DARK MODE
           Uses the same NoteSphere admin theme setting.
        ====================================================== */

        body.dark {

            --bg: #0f172a;
            --card: #1e293b;

            --text: #f1f5f9;
            --muted: #94a3b8;
            --border: #334155;

            --shadow:
                0 8px 25px rgba(0, 0, 0, 0.25);
        }


        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }


        body {

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: var(--bg);
            color: var(--text);

            min-height: 100vh;

            transition:
                background 0.25s ease,
                color 0.25s ease;
        }


        button,
        input,
        select {
            font-family: inherit;
        }


        a {
            text-decoration: none;
            color: inherit;
        }


        /* =====================================================
           BACK / CLOSE BUTTON
        ====================================================== */

        .back-btn {

            position: fixed;

            top: 18px;
            left: 20px;

            width: 42px;
            height: 42px;

            border-radius: 11px;

            border:
                1px solid var(--border);

            background: var(--card);

            color: var(--text);

            display: flex;

            align-items: center;

            justify-content: center;

            cursor: pointer;

            z-index: 2000;

            box-shadow:
                0 5px 18px rgba(15, 23, 42, 0.08);

            transition:
                background 0.2s ease,
                color 0.2s ease,
                transform 0.2s ease;
        }


        .back-btn:hover {

            color: var(--danger);

            background:
                rgba(220, 38, 38, 0.06);

            transform:
                translateY(-1px);
        }


        .back-btn svg {

            width: 20px;
            height: 20px;

            stroke: currentColor;
        }


        /* =====================================================
           MAIN
        ====================================================== */

        .main {

            min-height: 100vh;
        }


        .topbar {

            height: 76px;

            background: var(--card);

            border-bottom:
                1px solid var(--border);

            display: flex;
            align-items: center;

            padding:
                0 30px 0 82px;

            position: sticky;

            top: 0;

            z-index: 100;

            box-shadow:
                0 2px 10px rgba(15, 23, 42, 0.03);
        }


        .page-heading h1 {

            font-size: 20px;
            font-weight: 800;

            color: var(--text);
        }


        .page-heading p {

            font-size: 12px;

            color: var(--muted);

            margin-top: 4px;
        }


        .content {

            padding: 30px;
        }


        .content-header {

            display: flex;
            align-items: flex-start;

            justify-content: space-between;

            gap: 20px;

            margin-bottom: 24px;
        }


        .content-title h2 {

            font-size: 25px;
            font-weight: 800;
        }


        .content-title p {

            color: var(--muted);

            font-size: 13px;

            margin-top: 6px;
        }


        .add-professor-btn {

            border: none;

            background:
                linear-gradient(
                    135deg,
                    #233961,
                    #3D8BFF
                );

            color: #ffffff;

            padding:
                12px 18px;

            border-radius: 11px;

            font-size: 13px;

            font-weight: 800;

            cursor: pointer;

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 8px;

            box-shadow:
                0 7px 18px rgba(35, 57, 97, 0.18);

            transition:
                transform 0.2s ease,
                box-shadow 0.2s ease;
        }


        .add-professor-btn:hover {

            transform: translateY(-1px);

            box-shadow:
                0 10px 22px rgba(35, 57, 97, 0.25);
        }


        .plus-icon {

            width: 21px;
            height: 21px;

            border-radius: 50%;

            background:
                rgba(255, 255, 255, 0.20);

            display: flex;

            align-items: center;

            justify-content: center;
        }


        .plus-icon svg {

            width: 14px;
            height: 14px;

            stroke: currentColor;
        }


        .stats {

            display: grid;

            grid-template-columns:
                repeat(4, minmax(0, 1fr));

            gap: 18px;

            margin-bottom: 22px;
        }


        .stat-card {

            background: var(--card);

            border:
                1px solid var(--border);

            border-radius: var(--radius);

            padding: 19px;

            box-shadow: var(--shadow);

            display: flex;

            align-items: center;

            gap: 14px;
        }


        .stat-icon {

            width: 48px;
            height: 48px;

            border-radius: 13px;

            background:
                rgba(61, 139, 255, 0.12);

            color: var(--primary-light);

            display: flex;

            align-items: center;

            justify-content: center;

            flex-shrink: 0;
        }


        .stat-icon svg {

            width: 23px;
            height: 23px;

            stroke: currentColor;
        }


        .stat-number {

            font-size: 23px;

            font-weight: 800;

            line-height: 1;
        }


        .stat-label {

            color: var(--muted);

            font-size: 12px;

            margin-top: 6px;
        }


        .users-card {

            background: var(--card);

            border:
                1px solid var(--border);

            border-radius: var(--radius);

            box-shadow: var(--shadow);

            overflow: hidden;
        }


        .toolbar {

            padding: 20px;

            border-bottom:
                1px solid var(--border);

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 15px;

            flex-wrap: wrap;
        }


        .search-form {

            display: flex;

            gap: 9px;

            flex: 1;

            min-width: 260px;

            max-width: 540px;
        }


        .search-box {

            position: relative;

            flex: 1;
        }


        .search-box input {

            width: 100%;

            height: 43px;

            border:
                1px solid var(--border);

            border-radius: 10px;

            padding:
                0 14px 0 40px;

            outline: none;

            background: var(--card);

            color: var(--text);

            font-size: 13px;
        }


        .search-box input:focus {

            border-color:
                var(--primary-light);

            box-shadow:
                0 0 0 3px rgba(61, 139, 255, 0.10);
        }


        .search-icon {

            position: absolute;

            left: 14px;

            top: 50%;

            transform:
                translateY(-50%);

            color: var(--muted);

            display: flex;

            align-items: center;

            justify-content: center;
        }


        .search-icon svg {

            width: 17px;
            height: 17px;

            stroke: currentColor;
        }


        .search-btn {

            height: 43px;

            padding:
                0 18px;

            border: none;

            border-radius: 10px;

            background: var(--primary);

            color: #ffffff;

            font-weight: 700;

            cursor: pointer;
        }


        .search-btn:hover {
            background: #1b2e50;
        }


        .clear-btn {

            height: 43px;

            padding:
                0 16px;

            border:
                1px solid var(--border);

            border-radius: 10px;

            background: var(--card);

            color: var(--text);

            font-weight: 600;

            cursor: pointer;

            display: flex;

            align-items: center;

            justify-content: center;
        }


        .role-tabs {

            display: flex;

            gap: 7px;

            flex-wrap: wrap;
        }


        .role-tab {

            padding:
                9px 13px;

            border:
                1px solid var(--border);

            border-radius: 9px;

            color: var(--muted);

            font-size: 12px;

            font-weight: 700;

            transition: 0.2s;

            background: var(--card);
        }


        .role-tab:hover {

            color: var(--primary-light);

            border-color:
                var(--primary-light);
        }


        .role-tab.active {

            background: var(--primary);

            border-color: var(--primary);

            color: #ffffff;
        }


        .table-wrap {

            width: 100%;

            overflow-x: auto;
        }


        table {

            width: 100%;

            border-collapse: collapse;

            min-width: 900px;
        }


        thead {

            background:
                rgba(148, 163, 184, 0.07);
        }


        th {

            text-align: left;

            padding:
                14px 18px;

            color: var(--muted);

            font-size: 10px;

            text-transform: uppercase;

            letter-spacing: 0.6px;

            font-weight: 800;

            white-space: nowrap;

            border-bottom:
                1px solid var(--border);
        }


        td {

            padding:
                15px 18px;

            border-bottom:
                1px solid var(--border);

            vertical-align: middle;

            font-size: 13px;
        }


        tbody tr {

            transition:
                background 0.2s ease;
        }


        tbody tr:hover {

            background:
                rgba(61, 139, 255, 0.035);
        }


        tbody tr:last-child td {

            border-bottom: none;
        }


        .user-cell {

            display: flex;

            align-items: center;

            gap: 12px;

            min-width: 230px;
        }


        .user-avatar {

            width: 43px;
            height: 43px;

            border-radius: 50%;

            background:
                linear-gradient(
                    135deg,
                    #233961,
                    #3D8BFF
                );

            color: #ffffff;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 13px;

            font-weight: 800;

            flex-shrink: 0;

            overflow: hidden;
        }


        .user-avatar img {

            width: 100%;
            height: 100%;

            object-fit: cover;
        }


        .user-name {

            font-weight: 800;

            color: var(--text);

            line-height: 1.3;
        }


        .user-id {

            color: var(--muted);

            font-size: 10px;

            margin-top: 3px;
        }


        .email {

            color: var(--muted);

            font-size: 12px;

            word-break: break-word;

            max-width: 220px;
        }


        .course {

            font-weight: 700;

            color: var(--text);

            font-size: 12px;
        }


        .details {

            color: var(--muted);

            font-size: 10px;

            margin-top: 4px;

            line-height: 1.5;
        }


        .subjects {

            color: var(--muted);

            font-size: 10px;

            margin-top: 3px;

            max-width: 220px;

            line-height: 1.5;
        }


        .role-badge {

            display: inline-flex;

            align-items: center;

            padding:
                6px 9px;

            border-radius: 7px;

            font-size: 10px;

            font-weight: 800;

            white-space: nowrap;
        }


        .role-admin {

            color: #7c3aed;

            background:
                rgba(124, 58, 237, 0.10);
        }


        .role-professor {

            color: #0369a1;

            background:
                rgba(3, 105, 161, 0.10);
        }


        .role-student {

            color: #15803d;

            background:
                rgba(21, 128, 61, 0.10);
        }


        .role-default {

            color: var(--muted);

            background:
                rgba(100, 116, 139, 0.10);
        }


        .date {

            color: var(--muted);

            font-size: 11px;

            white-space: nowrap;
        }


        .actions {

            display: flex;

            align-items: center;

            gap: 7px;

            white-space: nowrap;
        }


        .action-btn {

            width: 35px;
            height: 35px;

            border-radius: 9px;

            display: inline-flex;

            align-items: center;

            justify-content: center;

            border:
                1px solid var(--border);

            background: var(--card);

            cursor: pointer;

            transition: 0.2s;
        }


        .action-btn svg {

            width: 16px;
            height: 16px;

            stroke: currentColor;
        }


        .edit-btn {

            color: var(--primary-light);
        }


        .edit-btn:hover {

            background:
                rgba(61, 139, 255, 0.10);

            border-color:
                rgba(61, 139, 255, 0.30);
        }


        .delete-btn {

            color: var(--danger);
        }


        .delete-btn:hover {

            background:
                rgba(220, 38, 38, 0.08);

            border-color:
                rgba(220, 38, 38, 0.25);
        }


        .empty {

            padding: 65px 20px;

            text-align: center;
        }


        .empty-icon {

            width: 64px;
            height: 64px;

            margin:
                0 auto 14px;

            border-radius: 18px;

            background:
                rgba(61, 139, 255, 0.10);

            color: var(--primary-light);

            display: flex;

            align-items: center;

            justify-content: center;
        }


        .empty-icon svg {

            width: 30px;
            height: 30px;

            stroke: currentColor;
        }


        .empty h3 {

            font-size: 16px;

            font-weight: 800;
        }


        .empty p {

            color: var(--muted);

            font-size: 12px;

            margin-top: 6px;
        }


        .modal-overlay {

            position: fixed;

            inset: 0;

            background:
                rgba(15, 23, 42, 0.58);

            backdrop-filter: blur(5px);

            display: none;

            align-items: center;

            justify-content: center;

            padding: 20px;

            z-index: 5000;
        }


        .modal-overlay.show {

            display: flex;
        }


        .professor-modal {

            width: 100%;

            max-width: 500px;

            background: var(--card);

            border:
                1px solid var(--border);

            border-radius: 20px;

            box-shadow:
                0 25px 70px rgba(0, 0, 0, 0.25);

            overflow: hidden;

            animation:
                modalIn 0.2s ease;
        }


        @keyframes modalIn {

            from {

                opacity: 0;

                transform:
                    translateY(12px)
                    scale(0.98);
            }

            to {

                opacity: 1;

                transform:
                    translateY(0)
                    scale(1);
            }
        }


        .modal-header {

            padding:
                22px 24px;

            border-bottom:
                1px solid var(--border);

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 15px;
        }


        .modal-header-left {

            display: flex;

            align-items: center;

            gap: 13px;
        }


        .modal-icon {

            width: 45px;
            height: 45px;

            border-radius: 13px;

            background:
                rgba(61, 139, 255, 0.12);

            color: var(--primary-light);

            display: flex;

            align-items: center;

            justify-content: center;
        }


        .modal-icon svg {

            width: 22px;
            height: 22px;

            stroke: currentColor;
        }


        .modal-title {

            font-size: 18px;

            font-weight: 800;

            color: var(--text);
        }


        .modal-subtitle {

            font-size: 11px;

            color: var(--muted);

            margin-top: 3px;
        }


        .modal-close {

            width: 35px;
            height: 35px;

            border: none;

            border-radius: 9px;

            background:
                rgba(100, 116, 139, 0.08);

            color: var(--muted);

            cursor: pointer;

            display: flex;

            align-items: center;

            justify-content: center;
        }


        .modal-close svg {

            width: 18px;
            height: 18px;

            stroke: currentColor;
        }


        .modal-close:hover {

            background:
                rgba(220, 38, 38, 0.10);

            color: var(--danger);
        }


        .modal-body {

            padding: 24px;
        }


        .form-group {

            margin-bottom: 17px;
        }


        .form-group:last-child {

            margin-bottom: 0;
        }


        .form-label {

            display: block;

            font-size: 12px;

            font-weight: 800;

            color: var(--text);

            margin-bottom: 7px;
        }


        .form-input-wrap {

            position: relative;
        }


        .form-input {

            width: 100%;

            height: 45px;

            border:
                1px solid var(--border);

            border-radius: 10px;

            background: var(--card);

            color: var(--text);

            padding:
                0 13px;

            outline: none;

            font-size: 13px;

            transition:
                border-color 0.2s,
                box-shadow 0.2s;
        }


        .form-input:focus {

            border-color:
                var(--primary-light);

            box-shadow:
                0 0 0 3px rgba(61, 139, 255, 0.10);
        }


        .password-input {

            padding-right: 48px;
        }


        .password-toggle {

            position: absolute;

            right: 7px;

            top: 50%;

            transform:
                translateY(-50%);

            width: 34px;
            height: 34px;

            border: none;

            background: transparent;

            color: var(--muted);

            cursor: pointer;

            border-radius: 8px;

            display: flex;

            align-items: center;

            justify-content: center;
        }


        .password-toggle svg {

            width: 18px;
            height: 18px;

            stroke: currentColor;
        }


        .password-toggle:hover {

            background:
                rgba(100, 116, 139, 0.08);
        }


        .form-help {

            color: var(--muted);

            font-size: 10px;

            margin-top: 5px;

            line-height: 1.5;
        }


        .modal-footer {

            padding:
                18px 24px;

            border-top:
                1px solid var(--border);

            display: flex;

            justify-content: flex-end;

            gap: 9px;
        }


        .modal-btn {

            height: 42px;

            padding:
                0 17px;

            border-radius: 10px;

            font-size: 12px;

            font-weight: 800;

            cursor: pointer;

            display: inline-flex;

            align-items: center;

            justify-content: center;
        }


        .cancel-modal-btn {

            border:
                1px solid var(--border);

            background: var(--card);

            color: var(--text);
        }


        .cancel-modal-btn:hover {

            background:
                rgba(100, 116, 139, 0.07);
        }


        .save-professor-btn {

            border: none;

            background: var(--primary);

            color: #ffffff;

            gap: 5px;
        }


        .save-professor-btn svg {

            width: 15px;
            height: 15px;

            stroke: currentColor;
        }


        .save-professor-btn:hover {

            background: #1b2e50;
        }


        @media (max-width: 1100px) {

            .stats {

                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
            }
        }


        @media (max-width: 800px) {

            .topbar {

                padding:
                    0 18px 0 72px;
            }


            .content {

                padding:
                    20px 15px;
            }


            .stats {

                grid-template-columns:
                    1fr;
            }


            .content-header {

                flex-direction: column;
            }


            .add-professor-btn {

                width: 100%;
            }
        }


        @media (max-width: 520px) {

            .back-btn {

                top: 15px;
                left: 15px;

                width: 38px;
                height: 38px;
            }


            .topbar {

                height: 70px;

                padding:
                    0 14px 0 65px;
            }


            .search-form {

                min-width: 100%;

                max-width: none;

                flex-wrap: wrap;
            }


            .search-box {

                min-width: 100%;
            }


            .search-btn,
            .clear-btn {

                flex: 1;
            }


            .role-tabs {

                width: 100%;
            }


            .role-tab {

                flex: 1;

                text-align: center;
            }


            .modal-body {

                padding:
                    20px;
            }


            .modal-footer {

                padding:
                    16px 20px;

                flex-direction: column-reverse;
            }


            .modal-btn {

                width: 100%;
            }
        }

    </style>

</head>


<body>


<!-- =========================================================
     X / BACK BUTTON
========================================================== -->

<button
    type="button"
    class="back-btn"
    onclick="goBack()"
    title="Back"
    aria-label="Back"
>

    <svg
        viewBox="0 0 24 24"
        fill="none"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
    >
        <path d="M18 6L6 18"></path>
        <path d="M6 6l12 12"></path>
    </svg>

</button>


<!-- =========================================================
     MAIN
========================================================== -->

<main class="main">

    <header class="topbar">

        <div class="page-heading">

            <h1>
                Manage Users
            </h1>

            <p>
                Manage student, professor, and administrator accounts.
            </p>

        </div>

    </header>


    <section class="content">


        <div class="content-header">

            <div class="content-title">

                <h2>
                    User Accounts
                </h2>

                <p>
                    View, edit, search, and remove registered users.
                </p>

            </div>


            <button
                type="button"
                class="add-professor-btn"
                onclick="openProfessorModal()"
            >

                <span class="plus-icon">

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke-width="2.5"
                        stroke-linecap="round"
                    >
                        <path d="M12 5v14"></path>
                        <path d="M5 12h14"></path>
                    </svg>

                </span>

                Add Professor

            </button>

        </div>


        <!-- =================================================
             STATISTICS
        ================================================== -->

        <div class="stats">


            <!-- TOTAL USERS -->

            <div class="stat-card">

                <div class="stat-icon">

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>

                </div>

                <div>

                    <div class="stat-number">
                        <?= number_format($total_users); ?>
                    </div>

                    <div class="stat-label">
                        Total Users
                    </div>

                </div>

            </div>


            <!-- STUDENTS -->

            <div class="stat-card">

                <div class="stat-icon">

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="M2 10l10-5 10 5-10 5L2 10z"></path>
                        <path d="M6 12.5V17c2 2 10 2 12 0v-4.5"></path>
                        <path d="M22 10v6"></path>
                    </svg>

                </div>

                <div>

                    <div class="stat-number">
                        <?= number_format($total_students); ?>
                    </div>

                    <div class="stat-label">
                        Students
                    </div>

                </div>

            </div>


            <!-- PROFESSORS -->

            <div class="stat-card">

                <div class="stat-icon">

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <circle cx="12" cy="7" r="4"></circle>
                        <path d="M4 21a8 8 0 0 1 16 0"></path>
                        <path d="M17 11l2 2 3-3"></path>
                    </svg>

                </div>

                <div>

                    <div class="stat-number">
                        <?= number_format($total_professors); ?>
                    </div>

                    <div class="stat-label">
                        Professors
                    </div>

                </div>

            </div>


            <!-- ADMINISTRATORS -->

            <div class="stat-card">

                <div class="stat-icon">

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="M12 3l7 4v5c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V7l7-4z"></path>
                        <path d="M9 12l2 2 4-4"></path>
                    </svg>

                </div>

                <div>

                    <div class="stat-number">
                        <?= number_format($total_admins); ?>
                    </div>

                    <div class="stat-label">
                        Administrators
                    </div>

                </div>

            </div>

        </div>


        <div class="users-card">


            <div class="toolbar">


                <form
                    method="GET"
                    action="manage_users.php"
                    class="search-form"
                >

                    <input
                        type="hidden"
                        name="role"
                        value="<?= e($role_filter); ?>"
                    >


                    <div class="search-box">

                        <span class="search-icon">

                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <circle cx="11" cy="11" r="7"></circle>
                                <path d="M20 20l-4-4"></path>
                            </svg>

                        </span>


                        <input
                            type="text"
                            name="search"
                            placeholder="Search name, email, course, section..."
                            value="<?= e($search); ?>"
                            autocomplete="off"
                        >

                    </div>


                    <button
                        type="submit"
                        class="search-btn"
                    >
                        Search
                    </button>


                    <?php if (
                        $search !== '' ||
                        $role_filter !== 'all'
                    ): ?>

                        <a
                            href="manage_users.php"
                            class="clear-btn"
                        >
                            Clear
                        </a>

                    <?php endif; ?>

                </form>


                <div class="role-tabs">

                    <a
                        href="manage_users.php?role=all<?= $search !== '' ? '&search=' . urlencode($search) : ''; ?>"
                        class="role-tab <?= $role_filter === 'all' ? 'active' : ''; ?>"
                    >
                        All
                    </a>


                    <a
                        href="manage_users.php?role=student<?= $search !== '' ? '&search=' . urlencode($search) : ''; ?>"
                        class="role-tab <?= $role_filter === 'student' ? 'active' : ''; ?>"
                    >
                        Students
                    </a>


                    <a
                        href="manage_users.php?role=professor<?= $search !== '' ? '&search=' . urlencode($search) : ''; ?>"
                        class="role-tab <?= $role_filter === 'professor' ? 'active' : ''; ?>"
                    >
                        Professors
                    </a>


                    <a
                        href="manage_users.php?role=admin<?= $search !== '' ? '&search=' . urlencode($search) : ''; ?>"
                        class="role-tab <?= $role_filter === 'admin' ? 'active' : ''; ?>"
                    >
                        Admins
                    </a>

                </div>

            </div>


            <?php if (!empty($users)): ?>

                <div class="table-wrap">

                    <table>

                        <thead>

                            <tr>

                                <th>User</th>
                                <th>Email</th>
                                <th>Academic Information</th>
                                <th>Role</th>
                                <th>Registered</th>
                                <th>Actions</th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php foreach ($users as $user): ?>

                            <?php

                            $user_id =
                                (int) $user['user_id'];

                            $full_name =
                                trim(
                                    $user['full_name'] ?? ''
                                );

                            if ($full_name === '') {
                                $full_name = 'Unnamed User';
                            }

                            $role =
                                $user['role'] ?? '';

                            $profile_pic =
                                trim(
                                    $user['profile_pic'] ?? ''
                                );

                            $details =
                                getUserDetails($user);

                            ?>


                            <tr>


                                <td>

                                    <div class="user-cell">

                                        <div class="user-avatar">

                                            <?php if ($profile_pic !== ''): ?>

                                                <?php

                                                $profile_path =
                                                    '../' .
                                                    ltrim(
                                                        $profile_pic,
                                                        '/'
                                                    );

                                                ?>

                                                <img
                                                    src="<?= e($profile_path); ?>"
                                                    alt="Profile"
                                                    onerror="this.style.display='none'; this.parentElement.innerText='<?= e(getInitials($full_name)); ?>';"
                                                >

                                            <?php else: ?>

                                                <?= e(
                                                    getInitials($full_name)
                                                ); ?>

                                            <?php endif; ?>

                                        </div>


                                        <div>

                                            <div class="user-name">
                                                <?= e($full_name); ?>
                                            </div>

                                            <div class="user-id">
                                                User ID:
                                                <?= $user_id; ?>
                                            </div>

                                        </div>

                                    </div>

                                </td>


                                <td>

                                    <div class="email">
                                        <?= e($user['email'] ?? '—'); ?>
                                    </div>

                                </td>


                                <td>

                                    <?php if (!empty($user['course'])): ?>

                                        <div class="course">
                                            <?= e($user['course']); ?>
                                        </div>

                                    <?php endif; ?>


                                    <div class="details">
                                        <?= e($details); ?>
                                    </div>


                                    <?php if (
                                        $role === 'student' &&
                                        !empty($user['enrolled_subjects'])
                                    ): ?>

                                        <div class="subjects">

                                            Subjects:

                                            <?= e(
                                                $user['enrolled_subjects']
                                            ); ?>

                                        </div>

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <span
                                        class="role-badge <?= e(getRoleClass($role)); ?>"
                                    >
                                        <?= e(formatRole($role)); ?>
                                    </span>

                                </td>


                                <td>

                                    <div class="date">

                                        <?php

                                        if (!empty($user['created_at'])) {

                                            echo e(
                                                date(
                                                    'M d, Y',
                                                    strtotime(
                                                        $user['created_at']
                                                    )
                                                )
                                            );

                                        } else {

                                            echo '—';
                                        }

                                        ?>

                                    </div>

                                </td>


                                <td>

                                    <div class="actions">


                                        <!-- EDIT ICON -->

                                        <button
                                            type="button"
                                            class="action-btn edit-btn"
                                            title="Edit User"
                                            onclick="confirmEdit(
                                                <?= $user_id; ?>,
                                                <?= htmlspecialchars(
                                                    json_encode(
                                                        $full_name,
                                                        JSON_HEX_TAG |
                                                        JSON_HEX_APOS |
                                                        JSON_HEX_AMP |
                                                        JSON_HEX_QUOT
                                                    ),
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ); ?>
                                            )"
                                        >

                                            <svg
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke-width="2"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                            >
                                                <path d="M12 20h9"></path>
                                                <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L8 18l-4 1 1-4L16.5 3.5z"></path>
                                            </svg>

                                        </button>


                                        <?php if (
                                            $user_id !== $current_admin_id
                                        ): ?>

                                            <!-- DELETE ICON -->

                                            <button
                                                type="button"
                                                class="action-btn delete-btn"
                                                title="Delete User"
                                                onclick="confirmDelete(
                                                    <?= $user_id; ?>,
                                                    <?= htmlspecialchars(
                                                        json_encode(
                                                            $full_name,
                                                            JSON_HEX_TAG |
                                                            JSON_HEX_APOS |
                                                            JSON_HEX_AMP |
                                                            JSON_HEX_QUOT
                                                        ),
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ); ?>
                                                )"
                                            >

                                                <svg
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke-width="2"
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                >
                                                    <path d="M3 6h18"></path>
                                                    <path d="M8 6V4h8v2"></path>
                                                    <path d="M19 6l-1 15H6L5 6"></path>
                                                    <path d="M10 11v6"></path>
                                                    <path d="M14 11v6"></path>
                                                </svg>

                                            </button>

                                        <?php else: ?>

                                            <button
                                                type="button"
                                                class="action-btn"
                                                title="You cannot delete your own account"
                                                style="
                                                    opacity:0.35;
                                                    cursor:not-allowed;
                                                "
                                                onclick="cannotDeleteSelf()"
                                            >

                                                <svg
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke-width="2"
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                >
                                                    <path d="M3 6h18"></path>
                                                    <path d="M8 6V4h8v2"></path>
                                                    <path d="M19 6l-1 15H6L5 6"></path>
                                                    <path d="M10 11v6"></path>
                                                    <path d="M14 11v6"></path>
                                                </svg>

                                            </button>

                                        <?php endif; ?>


                                    </div>

                                </td>


                            </tr>

                        <?php endforeach; ?>


                        </tbody>

                    </table>

                </div>


            <?php else: ?>


                <div class="empty">

                    <div class="empty-icon">

                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>

                    </div>

                    <h3>
                        No Users Found
                    </h3>

                    <p>

                        <?php if ($search !== ''): ?>

                            No user matched your search:

                            <strong>
                                <?= e($search); ?>
                            </strong>

                        <?php elseif ($role_filter !== 'all'): ?>

                            There are no
                            <?= e(formatRole($role_filter)); ?>
                            accounts yet.

                        <?php else: ?>

                            No user accounts are available.

                        <?php endif; ?>

                    </p>

                </div>


            <?php endif; ?>


        </div>


    </section>


</main>


<!-- =========================================================
     ADD PROFESSOR MODAL
========================================================== -->

<div
    class="modal-overlay"
    id="professorModal"
>

    <div
        class="professor-modal"
        onclick="event.stopPropagation()"
    >


        <div class="modal-header">

            <div class="modal-header-left">

                <div class="modal-icon">

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M3 21a6 6 0 0 1 12 0"></path>
                        <path d="M19 8v6"></path>
                        <path d="M16 11h6"></path>
                    </svg>

                </div>

                <div>

                    <div class="modal-title">
                        Add Professor
                    </div>

                    <div class="modal-subtitle">
                        Create a new professor account.
                    </div>

                </div>

            </div>


            <button
                type="button"
                class="modal-close"
                onclick="closeProfessorModal()"
                title="Close"
                aria-label="Close"
            >

                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                >
                    <path d="M6 6l12 12"></path>
                    <path d="M18 6L6 18"></path>
                </svg>

            </button>

        </div>


        <form
            method="POST"
            action="manage_users.php"
            id="addProfessorForm"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($csrf_token); ?>"
            >

            <input
                type="hidden"
                name="add_professor"
                value="1"
            >


            <div class="modal-body">


                <div class="form-group">

                    <label
                        for="professor_name"
                        class="form-label"
                    >
                        Professor Name
                    </label>


                    <input
                        type="text"
                        id="professor_name"
                        name="professor_name"
                        class="form-input"
                        placeholder="Enter professor full name"
                        maxlength="150"
                        required
                        autocomplete="name"
                    >

                </div>


                <div class="form-group">

                    <label
                        for="professor_email"
                        class="form-label"
                    >
                        Professor Email
                    </label>


                    <input
                        type="email"
                        id="professor_email"
                        name="professor_email"
                        class="form-input"
                        placeholder="Enter professor email"
                        maxlength="255"
                        required
                        autocomplete="email"
                    >

                </div>


                <div class="form-group">

                    <label
                        for="temporary_password"
                        class="form-label"
                    >
                        Temporary Password
                    </label>


                    <div class="form-input-wrap">

                        <input
                            type="password"
                            id="temporary_password"
                            name="temporary_password"
                            class="form-input password-input"
                            placeholder="Enter temporary password"
                            minlength="6"
                            maxlength="255"
                            required
                            autocomplete="new-password"
                        >


                        <button
                            type="button"
                            class="password-toggle"
                            id="passwordToggle"
                            onclick="toggleProfessorPassword()"
                            title="Show password"
                        >

                            <svg
                                id="passwordEyeIcon"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>

                        </button>

                    </div>


                    <div class="form-help">
                        The professor can use this temporary password to log in and later reset the password using Forgot Password.
                    </div>

                </div>


            </div>


            <div class="modal-footer">

                <button
                    type="button"
                    class="modal-btn cancel-modal-btn"
                    onclick="closeProfessorModal()"
                >
                    Cancel
                </button>


                <button
                    type="submit"
                    class="modal-btn save-professor-btn"
                >

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="M12 5v14"></path>
                        <path d="M5 12h14"></path>
                    </svg>

                    Create Professor

                </button>

            </div>

        </form>

    </div>

</div>


<!-- =========================================================
     HIDDEN DELETE FORM
========================================================== -->

<form
    method="POST"
    action="manage_users.php"
    id="deleteForm"
    style="display:none;"
>

    <input
        type="hidden"
        name="csrf_token"
        value="<?= e($csrf_token); ?>"
    >

    <input
        type="hidden"
        name="user_id"
        id="deleteUserId"
        value=""
    >

    <input
        type="hidden"
        name="delete_user"
        value="1"
    >

</form>


<script>

/* =========================================================
   NOTE SPHERE THEME
   Automatically adopts the system/admin theme.
   No visible theme button on this page.
========================================================== */

(function applyNoteSphereTheme() {

    const savedTheme =
        localStorage.getItem('notesphere_admin_theme');

    if (savedTheme === 'dark') {

        document.body.classList.add('dark');

    } else {

        document.body.classList.remove('dark');

    }

})();


/* =========================================================
   KEEP THEME IN SYNC
   If another NoteSphere page changes the theme while
   this page is open in another tab, this page follows it.
========================================================== */

window.addEventListener(
    'storage',
    function (event) {

        if (event.key !== 'notesphere_admin_theme') {
            return;
        }

        document.body.classList.toggle(
            'dark',
            event.newValue === 'dark'
        );

    }
);


/* =========================================================
   BACK BUTTON
========================================================== */

function goBack() {

    if (window.history.length > 1) {

        window.history.back();

    } else {

        window.location.href = 'admin_dashboard.php';

    }
}


/* =========================================================
   ADD PROFESSOR MODAL
========================================================== */

const professorModal =
    document.getElementById('professorModal');


function openProfessorModal() {

    professorModal.classList.add('show');

    document.body.style.overflow = 'hidden';

    setTimeout(function () {

        document.getElementById(
            'professor_name'
        ).focus();

    }, 100);
}


function closeProfessorModal() {

    professorModal.classList.remove('show');

    document.body.style.overflow = '';

    document.getElementById(
        'addProfessorForm'
    ).reset();

    const passwordInput =
        document.getElementById(
            'temporary_password'
        );

    passwordInput.type = 'password';

    document.getElementById(
        'passwordEyeIcon'
    ).innerHTML =
        '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"></path><circle cx="12" cy="12" r="3"></circle>';

    document.getElementById(
        'passwordToggle'
    ).title = 'Show password';
}


professorModal.addEventListener(
    'click',
    function (event) {

        if (event.target === professorModal) {
            closeProfessorModal();
        }

    }
);


document.addEventListener(
    'keydown',
    function (event) {

        if (
            event.key === 'Escape' &&
            professorModal.classList.contains('show')
        ) {
            closeProfessorModal();
        }

    }
);


/* =========================================================
   SHOW / HIDE PROFESSOR PASSWORD
========================================================== */

function toggleProfessorPassword() {

    const passwordInput =
        document.getElementById(
            'temporary_password'
        );

    const toggle =
        document.getElementById(
            'passwordToggle'
        );

    const icon =
        document.getElementById(
            'passwordEyeIcon'
        );


    if (passwordInput.type === 'password') {

        passwordInput.type = 'text';

        icon.innerHTML =
            '<path d="M3 3l18 18"></path><path d="M10.6 10.6a2 2 0 0 0 2.8 2.8"></path><path d="M9.9 5.1A10.8 10.8 0 0 1 12 5c6.5 0 10 7 10 7a17.4 17.4 0 0 1-3.1 3.9"></path><path d="M6.6 6.6C3.7 8.4 2 12 2 12s3.5 7 10 7c1.7 0 3.2-.4 4.5-1"></path>';

        toggle.title = 'Hide password';

    } else {

        passwordInput.type = 'password';

        icon.innerHTML =
            '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"></path><circle cx="12" cy="12" r="3"></circle>';

        toggle.title = 'Show password';
    }
}


/* =========================================================
   EDIT USER CONFIRMATION
========================================================== */

function confirmEdit(userId, userName) {

    Swal.fire({

        title: 'Edit User?',

        html:
            'You are about to edit the account of<br>' +
            '<strong>' +
            escapeHtml(userName) +
            '</strong>.',

        icon: 'question',

        showCancelButton: true,

        confirmButtonText:
            'Yes, Edit User',

        cancelButtonText:
            'Cancel',

        confirmButtonColor:
            '#233961',

        cancelButtonColor:
            '#64748b',

        reverseButtons: true

    }).then(function (result) {

        if (result.isConfirmed) {

            window.location.href =
                'edit_user.php?user_id=' +
                encodeURIComponent(userId);
        }

    });
}


/* =========================================================
   DELETE USER CONFIRMATION
========================================================== */

function confirmDelete(userId, userName) {

    Swal.fire({

        title: 'Delete User?',

        html:
            'You are about to permanently delete<br>' +
            '<strong>' +
            escapeHtml(userName) +
            '</strong>.<br><br>' +

            '<span style="color:#dc2626;font-size:13px;">' +
            'This action cannot be undone.' +
            '</span>',

        icon: 'warning',

        showCancelButton: true,

        confirmButtonText:
            'Yes, Delete',

        cancelButtonText:
            'Cancel',

        confirmButtonColor:
            '#dc2626',

        cancelButtonColor:
            '#64748b',

        reverseButtons: true

    }).then(function (result) {

        if (result.isConfirmed) {

            document.getElementById(
                'deleteUserId'
            ).value = userId;


            document.getElementById(
                'deleteForm'
            ).submit();
        }

    });
}


/* =========================================================
   PREVENT SELF DELETE
========================================================== */

function cannotDeleteSelf() {

    Swal.fire({

        title:
            'Action Not Allowed',

        text:
            'You cannot delete your own administrator account.',

        icon:
            'warning',

        confirmButtonColor:
            '#233961',

        confirmButtonText:
            'Okay'

    });
}


/* =========================================================
   LOGOUT
========================================================== */

function confirmLogout() {

    Swal.fire({

        title:
            'Logout?',

        text:
            'Are you sure you want to log out of NoteSphere?',

        icon:
            'question',

        showCancelButton:
            true,

        confirmButtonText:
            'Yes, Logout',

        cancelButtonText:
            'Stay',

        confirmButtonColor:
            '#dc2626',

        cancelButtonColor:
            '#64748b',

        reverseButtons:
            true

    }).then(function (result) {

        if (result.isConfirmed) {

            window.location.href =
                '../auth/logout.php';
        }

    });
}


/* =========================================================
   SAFE HTML FOR SWEETALERT
========================================================== */

function escapeHtml(text) {

    const div =
        document.createElement('div');

    div.textContent = text;

    return div.innerHTML;
}

</script>


<?php if ($swal): ?>

<script>

Swal.fire({

    icon:
        <?= json_encode(
            $swal['icon'] ?? 'info'
        ); ?>,

    title:
        <?= json_encode(
            $swal['title'] ?? 'Notice'
        ); ?>,

    text:
        <?= json_encode(
            $swal['text'] ?? ''
        ); ?>,

    confirmButtonColor:
        '#233961'

});

</script>

<?php endif; ?>


</body>

</html>