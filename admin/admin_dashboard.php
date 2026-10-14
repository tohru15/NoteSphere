<?php

// =========================================================
// NoteSphere - Admin Dashboard
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
// DEFAULT SETTINGS
// =========================================================

$default_system_storage_tb = 10.00;
$default_student_storage_gb = 500.00;
$default_professor_storage_gb = 1000.00;
$default_max_users = 1000;

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
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

// =========================================================
// ADMIN NAME
// ACTUAL USERS TABLE:
// user_id
// full_name
// email
// role
// =========================================================

$admin_name = 'Administrator';

try {

    $admin_stmt = $conn->prepare("
        SELECT full_name
        FROM users
        WHERE user_id = ?
        LIMIT 1
    ");

    $admin_stmt->execute([
        (int) $_SESSION['user_id']
    ]);

    $admin_data = $admin_stmt->fetch(PDO::FETCH_ASSOC);

    if (
        $admin_data &&
        !empty($admin_data['full_name'])
    ) {
        $admin_name = $admin_data['full_name'];
    }

} catch (PDOException $e) {

    // Use default administrator name
}

// =========================================================
// INITIAL VALUES
// =========================================================

$total_users = 0;
$total_announcements = 0;
$approved_notes = 0;
$pending_notes = 0;

$total_storage_tb = $default_system_storage_tb;
$student_storage_gb = $default_student_storage_gb;
$professor_storage_gb = $default_professor_storage_gb;
$max_users = $default_max_users;

$storage_error = '';
$storage_success = '';

// =========================================================
// CREATE SYSTEM STORAGE TABLE IF NEEDED
// =========================================================

try {

    $conn->exec("
        CREATE TABLE IF NOT EXISTS system_storage (

            id INT NOT NULL PRIMARY KEY,

            total_storage_tb
                DECIMAL(10,2)
                NOT NULL
                DEFAULT 10.00,

            student_storage_gb
                DECIMAL(10,2)
                NOT NULL
                DEFAULT 500.00,

            professor_storage_gb
                DECIMAL(10,2)
                NOT NULL
                DEFAULT 1000.00,

            updated_at
                TIMESTAMP
                DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP
        )
    ");

    // =====================================================
    // CHECK EXISTING COLUMNS
    // =====================================================

    $columns_stmt = $conn->query(
        "SHOW COLUMNS FROM system_storage"
    );

    $columns = $columns_stmt->fetchAll(
        PDO::FETCH_COLUMN
    );

    // =====================================================
    // TOTAL STORAGE
    // =====================================================

    if (!in_array(
        'total_storage_tb',
        $columns,
        true
    )) {

        $conn->exec("
            ALTER TABLE system_storage
            ADD COLUMN total_storage_tb
            DECIMAL(10,2)
            NOT NULL
            DEFAULT 10.00
        ");
    }

    // =====================================================
    // STUDENT STORAGE
    // =====================================================

    if (!in_array(
        'student_storage_gb',
        $columns,
        true
    )) {

        $conn->exec("
            ALTER TABLE system_storage
            ADD COLUMN student_storage_gb
            DECIMAL(10,2)
            NOT NULL
            DEFAULT 500.00
        ");
    }

    // =====================================================
    // PROFESSOR STORAGE
    // =====================================================

    if (!in_array(
        'professor_storage_gb',
        $columns,
        true
    )) {

        $conn->exec("
            ALTER TABLE system_storage
            ADD COLUMN professor_storage_gb
            DECIMAL(10,2)
            NOT NULL
            DEFAULT 1000.00
        ");
    }

    // =====================================================
    // DEFAULT STORAGE ROW
    // =====================================================

    $storage_insert = $conn->prepare("
        INSERT INTO system_storage (
            id,
            total_storage_tb,
            student_storage_gb,
            professor_storage_gb
        )
        VALUES (
            1,
            ?,
            ?,
            ?
        )
        ON DUPLICATE KEY UPDATE
            id = id
    ");

    $storage_insert->execute([
        $default_system_storage_tb,
        $default_student_storage_gb,
        $default_professor_storage_gb
    ]);

} catch (PDOException $e) {

    $storage_error =
        'System storage settings are not available.';
}

// =========================================================
// CREATE SYSTEM LIMITS TABLE
// =========================================================

try {

    $conn->exec("
        CREATE TABLE IF NOT EXISTS system_limits (

            id INT NOT NULL PRIMARY KEY,

            max_users
                INT
                NOT NULL
                DEFAULT 1000,

            updated_at
                TIMESTAMP
                DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP
        )
    ");

    $limit_insert = $conn->prepare("
        INSERT INTO system_limits (
            id,
            max_users
        )
        VALUES (
            1,
            ?
        )
        ON DUPLICATE KEY UPDATE
            id = id
    ");

    $limit_insert->execute([
        $default_max_users
    ]);

} catch (PDOException $e) {

    // Use default maximum users
}

// =========================================================
// GET TOTAL USERS
// =========================================================

try {

    $user_count_stmt = $conn->query("
        SELECT COUNT(*)
        FROM users
    ");

    $total_users = (int) $user_count_stmt->fetchColumn();

} catch (PDOException $e) {

    $total_users = 0;
}

// =========================================================
// MANAGE STORAGE / SYSTEM SETTINGS
// =========================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['storage_action']) &&
    $_POST['storage_action'] === 'manage_storage'
) {

    // =====================================================
    // CSRF CHECK
    // =====================================================

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals(
            $_SESSION['csrf_token'],
            $_POST['csrf_token']
        )
    ) {

        $storage_error =
            'Invalid security token. Please try again.';

    } else {

        $add_system_storage_tb =
            isset($_POST['add_system_storage_tb'])
                ? (float) $_POST['add_system_storage_tb']
                : 0;

        $new_student_storage_gb =
            isset($_POST['student_storage_gb'])
                ? (float) $_POST['student_storage_gb']
                : 0;

        $new_professor_storage_gb =
            isset($_POST['professor_storage_gb'])
                ? (float) $_POST['professor_storage_gb']
                : 0;

        $new_max_users =
            isset($_POST['max_users'])
                ? (int) $_POST['max_users']
                : 0;

        // =================================================
        // VALIDATION
        // =================================================

        if ($add_system_storage_tb < 0) {

            $storage_error =
                'Additional system storage cannot be negative.';

        } elseif ($new_student_storage_gb <= 0) {

            $storage_error =
                'Student storage must be greater than 0 GB.';

        } elseif ($new_professor_storage_gb <= 0) {

            $storage_error =
                'Professor storage must be greater than 0 GB.';

        } elseif ($new_max_users <= 0) {

            $storage_error =
                'Maximum users must be greater than 0.';

        } elseif ($new_max_users > 1000000) {

            $storage_error =
                'Maximum users cannot be more than 1,000,000.';

        } elseif ($new_max_users < $total_users) {

            $storage_error =
                'Maximum users cannot be lower than the current number of users.';

        } else {

            try {

                $conn->beginTransaction();

                // =========================================
                // GET CURRENT STORAGE
                // =========================================

                $current_storage_stmt = $conn->query("
                    SELECT total_storage_tb
                    FROM system_storage
                    WHERE id = 1
                    LIMIT 1
                    FOR UPDATE
                ");

                $current_storage =
                    $current_storage_stmt->fetch(
                        PDO::FETCH_ASSOC
                    );

                if (!$current_storage) {

                    $insert_storage = $conn->prepare("
                        INSERT INTO system_storage (
                            id,
                            total_storage_tb,
                            student_storage_gb,
                            professor_storage_gb
                        )
                        VALUES (
                            1,
                            ?,
                            ?,
                            ?
                        )
                    ");

                    $insert_storage->execute([
                        $default_system_storage_tb,
                        $new_student_storage_gb,
                        $new_professor_storage_gb
                    ]);

                } else {

                    $current_total_storage =
                        (float) $current_storage[
                            'total_storage_tb'
                        ];

                    $new_total_storage =
                        $current_total_storage +
                        $add_system_storage_tb;

                    $update_storage = $conn->prepare("
                        UPDATE system_storage
                        SET
                            total_storage_tb = ?,
                            student_storage_gb = ?,
                            professor_storage_gb = ?
                        WHERE id = 1
                    ");

                    $update_storage->execute([
                        $new_total_storage,
                        $new_student_storage_gb,
                        $new_professor_storage_gb
                    ]);
                }

                // =========================================
                // MAX USERS
                // =========================================

                $update_limits = $conn->prepare("
                    INSERT INTO system_limits (
                        id,
                        max_users
                    )
                    VALUES (
                        1,
                        ?
                    )
                    ON DUPLICATE KEY UPDATE
                        max_users = VALUES(max_users)
                ");

                $update_limits->execute([
                    $new_max_users
                ]);

                // =========================================
                // COMMIT
                // =========================================

                $conn->commit();

                $storage_success =
                    'System settings updated successfully.';

            } catch (PDOException $e) {

                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }

                $storage_error =
                    'Unable to update system settings. Please try again.';
            }
        }
    }
}

// =========================================================
// GET CURRENT STORAGE SETTINGS
// =========================================================

try {

    $storage_stmt = $conn->query("
        SELECT
            total_storage_tb,
            student_storage_gb,
            professor_storage_gb
        FROM system_storage
        WHERE id = 1
        LIMIT 1
    ");

    $storage_data =
        $storage_stmt->fetch(PDO::FETCH_ASSOC);

    if ($storage_data) {

        $total_storage_tb =
            (float) $storage_data['total_storage_tb'];

        $student_storage_gb =
            (float) $storage_data['student_storage_gb'];

        $professor_storage_gb =
            (float) $storage_data['professor_storage_gb'];
    }

} catch (PDOException $e) {

    // Keep default values
}

// =========================================================
// GET MAXIMUM USERS
// =========================================================

try {

    $limit_stmt = $conn->query("
        SELECT max_users
        FROM system_limits
        WHERE id = 1
        LIMIT 1
    ");

    $limit_data =
        $limit_stmt->fetch(PDO::FETCH_ASSOC);

    if ($limit_data) {

        $max_users =
            (int) $limit_data['max_users'];
    }

} catch (PDOException $e) {

    $max_users =
        $default_max_users;
}

// =========================================================
// USER CAPACITY
// =========================================================

$remaining_user_slots =
    max(
        0,
        $max_users - $total_users
    );

$user_capacity_percentage = 0;

if ($max_users > 0) {

    $user_capacity_percentage =
        (
            $total_users /
            $max_users
        ) * 100;

    $user_capacity_percentage =
        min(
            100,
            max(
                0,
                $user_capacity_percentage
            )
        );
}

// =========================================================
// ANNOUNCEMENTS COUNT
// =========================================================

try {

    $announcement_stmt = $conn->query("
        SELECT COUNT(*)
        FROM announcements
    ");

    $total_announcements =
        (int) $announcement_stmt->fetchColumn();

} catch (PDOException $e) {

    $total_announcements = 0;
}

// =========================================================
// APPROVED NOTES
// =========================================================

try {

    $approved_notes_stmt = $conn->query("
        SELECT COUNT(*)
        FROM notes
        WHERE status = 'approved'
    ");

    $approved_notes =
        (int) $approved_notes_stmt->fetchColumn();

} catch (PDOException $e) {

    $approved_notes = 0;
}

// =========================================================
// PENDING NOTES
// =========================================================

try {

    $pending_notes_stmt = $conn->query("
        SELECT COUNT(*)
        FROM notes
        WHERE status = 'pending'
    ");

    $pending_notes =
        (int) $pending_notes_stmt->fetchColumn();

} catch (PDOException $e) {

    $pending_notes = 0;
}

// =========================================================
// STORAGE SCANNING
// =========================================================

$total_used_bytes = 0;

$known_files = [];

// =========================================================
// STORAGE DIRECTORIES
// =========================================================

$storage_directories = [

    __DIR__ . '/../uploads',

    __DIR__ . '/../assets/uploads',

    __DIR__ . '/../storage',

    __DIR__ . '/../files'

];

// =========================================================
// SCAN ACTUAL FILES
// =========================================================

foreach ($storage_directories as $directory) {

    if (!is_dir($directory)) {
        continue;
    }

    try {

        $iterator =
            new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $directory,
                    FilesystemIterator::SKIP_DOTS
                )
            );

        foreach ($iterator as $file) {

            if (!$file->isFile()) {
                continue;
            }

            $real_path =
                $file->getRealPath();

            if (!$real_path) {
                continue;
            }

            if (isset($known_files[$real_path])) {
                continue;
            }

            $known_files[$real_path] = true;

            $file_size =
                $file->getSize();

            if (
                $file_size !== false &&
                $file_size > 0
            ) {

                $total_used_bytes +=
                    $file_size;
            }
        }

    } catch (Exception $e) {

        // Ignore inaccessible folders
    }
}

// =========================================================
// CHECK FILES REFERENCED BY NOTES
// =========================================================

$note_file_columns = [

    'file_path',
    'filepath',
    'file',
    'file_url',
    'filename',
    'file_name'

];

try {

    $note_columns_stmt =
        $conn->query(
            "SHOW COLUMNS FROM notes"
        );

    $available_note_columns =
        $note_columns_stmt->fetchAll(
            PDO::FETCH_COLUMN
        );

    foreach (
        $note_file_columns as $file_column
    ) {

        if (!in_array(
            $file_column,
            $available_note_columns,
            true
        )) {
            continue;
        }

        $safe_column =
            str_replace(
                '`',
                '',
                $file_column
            );

        $file_stmt = $conn->query("
            SELECT `$safe_column`
            FROM notes
            WHERE `$safe_column` IS NOT NULL
            AND `$safe_column` <> ''
        ");

        $file_paths =
            $file_stmt->fetchAll(
                PDO::FETCH_COLUMN
            );

        foreach ($file_paths as $file_path) {

            if (
                !is_string($file_path) ||
                trim($file_path) === ''
            ) {
                continue;
            }

            $clean_path =
                ltrim(
                    trim($file_path),
                    '/\\'
                );

            $possible_paths = [

                __DIR__ .
                '/../' .
                $clean_path,

                __DIR__ .
                '/../uploads/' .
                basename($clean_path),

                __DIR__ .
                '/../assets/uploads/' .
                basename($clean_path),

                __DIR__ .
                '/../storage/' .
                basename($clean_path),

                __DIR__ .
                '/../files/' .
                basename($clean_path)

            ];

            foreach ($possible_paths as $possible_path) {

                if (!is_file($possible_path)) {
                    continue;
                }

                $real_path =
                    realpath($possible_path);

                if (!$real_path) {
                    continue;
                }

                if (isset($known_files[$real_path])) {
                    break;
                }

                $known_files[$real_path] = true;

                $file_size =
                    filesize($real_path);

                if (
                    $file_size !== false &&
                    $file_size > 0
                ) {

                    $total_used_bytes +=
                        $file_size;
                }

                break;
            }
        }
    }

} catch (PDOException $e) {

    // Ignore notes file errors
}

// =========================================================
// STORAGE UNITS
// =========================================================

$kb_in_bytes = 1024;

$mb_in_bytes =
    1024 * 1024;

$gb_in_bytes =
    1024 * 1024 * 1024;

$tb_in_bytes =
    1024 * 1024 * 1024 * 1024;

// =========================================================
// TOTAL CAPACITY
// =========================================================

$total_capacity_bytes =
    $total_storage_tb *
    $tb_in_bytes;

// =========================================================
// USED STORAGE
// =========================================================

$used_storage_tb =
    $total_used_bytes /
    $tb_in_bytes;

$used_storage_gb =
    $total_used_bytes /
    $gb_in_bytes;

$used_storage_mb =
    $total_used_bytes /
    $mb_in_bytes;

$used_storage_kb =
    $total_used_bytes /
    $kb_in_bytes;

// =========================================================
// AVAILABLE STORAGE
// =========================================================

$available_storage_tb =
    max(
        0,
        $total_storage_tb -
        $used_storage_tb
    );

// =========================================================
// USED STORAGE DISPLAY
// =========================================================

if ($total_used_bytes >= $tb_in_bytes) {

    $used_storage_display =
        number_format(
            $used_storage_tb,
            2
        ) . ' TB';

} elseif ($total_used_bytes >= $gb_in_bytes) {

    $used_storage_display =
        number_format(
            $used_storage_gb,
            2
        ) . ' GB';

} elseif ($total_used_bytes >= $mb_in_bytes) {

    $used_storage_display =
        number_format(
            $used_storage_mb,
            2
        ) . ' MB';

} elseif ($total_used_bytes >= $kb_in_bytes) {

    $used_storage_display =
        number_format(
            $used_storage_kb,
            2
        ) . ' KB';

} elseif ($total_used_bytes > 0) {

    $used_storage_display =
        number_format(
            $total_used_bytes,
            0
        ) . ' Bytes';

} else {

    $used_storage_display =
        '0 Bytes';
}

// =========================================================
// AVAILABLE STORAGE DISPLAY
// =========================================================

if ($available_storage_tb >= 1) {

    $available_storage_display =
        number_format(
            $available_storage_tb,
            2
        ) . ' TB';

} else {

    $available_storage_gb =
        $available_storage_tb * 1024;

    $available_storage_display =
        number_format(
            $available_storage_gb,
            2
        ) . ' GB';
}

// =========================================================
// STORAGE PERCENTAGE
// =========================================================

$storage_percentage = 0;

if ($total_capacity_bytes > 0) {

    $storage_percentage =
        (
            $total_used_bytes /
            $total_capacity_bytes
        ) * 100;

    $storage_percentage =
        min(
            100,
            max(
                0,
                $storage_percentage
            )
        );
}

// =========================================================
// STORAGE BAR
// =========================================================

$storage_bar_percentage =
    $storage_percentage;

if (
    $storage_bar_percentage > 0 &&
    $storage_bar_percentage < 0.08
) {

    $storage_bar_percentage = 0.08;
}

$storage_fill_width =
    number_format(
        $storage_bar_percentage,
        2,
        '.',
        ''
    );

$storage_fill_min_width =
    $storage_percentage > 0
        ? '4px'
        : '0';

// =========================================================
// STORAGE STATUS
// =========================================================

$storage_status = 'Available';

if ($storage_percentage >= 90) {

    $storage_status = 'Almost Full';

} elseif ($storage_percentage >= 75) {

    $storage_status = 'High Usage';

} elseif ($storage_percentage >= 50) {

    $storage_status = 'Moderate Usage';
}

// =========================================================
// USER CAPACITY BAR
// =========================================================

$user_capacity_width =
    number_format(
        $user_capacity_percentage,
        2,
        '.',
        ''
    );

// =========================================================
// ADMIN INITIAL
// =========================================================

$admin_initial = 'A';

$clean_admin_name =
    trim($admin_name);

if ($clean_admin_name !== '') {

    $admin_initial =
        strtoupper(
            substr(
                $clean_admin_name,
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
        NoteSphere - Admin Dashboard
    </title>

    <script>
        (function () {

            const savedTheme =
                localStorage.getItem(
                    'notesphere_admin_theme'
                );

            if (savedTheme === 'dark') {

                document.documentElement.classList.add(
                    'dark-mode'
                );
            }

        })();
    </script>

    <script
        src="https://cdn.jsdelivr.net/npm/sweetalert2@11"
    ></script>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

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
            --warning: #f59e0b;
            --danger: #dc2626;

            --shadow:
                0 8px 25px rgba(35, 57, 97, 0.08);
        }

        html.dark-mode {

            --background: #111827;
            --card: #1f2937;

            --text: #f9fafb;
            --muted: #9ca3af;

            --border: #374151;
            --sidebar: #182235;

            --shadow:
                0 8px 25px rgba(0, 0, 0, 0.25);
        }

        body {

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: var(--background);
            color: var(--text);

            min-height: 100vh;
        }

        button,
        input {
            font-family: inherit;
        }

        .dashboard-wrapper {

            display: flex;
            min-height: 100vh;
        }

        /* =====================================================
           SIDEBAR
        ====================================================== */

        .sidebar {

            width: 250px;

            background: var(--sidebar);

            border-right:
                1px solid var(--border);

            padding: 25px 18px;

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

            background: var(--primary);

            color: #ffffff;

            display: flex;

            align-items: center;
            justify-content: center;

            font-size: 20px;
            font-weight: bold;
        }

        .cdm-logo {

            font-size: 11px;

            font-weight: 800;

            letter-spacing: 0.5px;

            text-transform: uppercase;
        }

        .logo-text {

            font-size: 21px;
            font-weight: 700;

            color: var(--primary);
        }

        html.dark-mode .logo-text {
            color: #ffffff;
        }

        .admin-label {

            font-size: 13px;
            font-weight: 700;

            color: var(--text);

            padding:
                0 10px 4px;
        }

        .admin-role-label {

            font-size: 11px;

            color: var(--muted);

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

            color: var(--muted);

            text-decoration: none;

            font-size: 14px;

            transition: 0.2s ease;
        }

        .sidebar-link:hover {

            background:
                rgba(61, 139, 255, 0.08);

            color: var(--primary-light);
        }

        .sidebar-link.active {

            background: var(--primary);

            color: #ffffff;
        }

        .sidebar-icon {

            width: 22px;
            height: 22px;

            display: flex;

            align-items: center;
            justify-content: center;

            flex-shrink: 0;
        }

        .sidebar-icon svg {

            width: 19px;
            height: 19px;

            display: block;

            stroke: currentColor;
        }

        /* =====================================================
           MAIN CONTENT
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

            background: var(--card);

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

            color: var(--text);

            margin-bottom: 4px;
        }

        .page-title p {

            font-size: 12px;

            color: var(--muted);
        }

        .topbar-right {

            display: flex;

            align-items: center;

            gap: 18px;
        }

        .theme-button {

            width: 38px;
            height: 38px;

            border:
                1px solid var(--border);

            background: var(--card);

            color: var(--text);

            border-radius: 10px;

            cursor: pointer;

            font-size: 17px;

            display: flex;

            align-items: center;
            justify-content: center;
        }

        .theme-button:hover {

            background:
                rgba(61, 139, 255, 0.08);
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

            background: var(--primary);

            color: #ffffff;

            display: flex;

            align-items: center;
            justify-content: center;

            font-weight: bold;
            font-size: 15px;
        }

        .admin-profile-name {

            font-size: 13px;

            font-weight: 700;

            color: var(--text);

            max-width: 180px;

            white-space: nowrap;

            overflow: hidden;

            text-overflow: ellipsis;
        }

        .admin-profile-role {

            font-size: 11px;

            color: var(--muted);

            margin-top: 2px;
        }

        /* =====================================================
           CONTENT
        ====================================================== */

        .content {
            padding: 30px;
        }

        /* =====================================================
           STATS
        ====================================================== */

        .stats-grid {

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 18px;

            margin-bottom: 22px;
        }

        .stat-card {

            background: var(--card);

            border:
                1px solid var(--border);

            border-radius: 15px;

            padding: 20px;

            box-shadow: var(--shadow);
        }

        .stat-top {

            display: flex;

            align-items: center;

            justify-content: space-between;

            margin-bottom: 14px;
        }

        .stat-title {

            font-size: 12px;

            color: var(--muted);

            font-weight: 600;
        }

        .stat-icon {

            width: 38px;
            height: 38px;

            border-radius: 10px;

            background:
                rgba(61, 139, 255, 0.1);

            color: var(--primary-light);

            display: flex;

            align-items: center;
            justify-content: center;

            font-size: 17px;
        }

        .stat-number {

            font-size: 28px;

            font-weight: 700;

            color: var(--text);

            margin-bottom: 5px;
        }

        .stat-limit {

            font-size: 11px;

            color: var(--muted);
        }

        /* =====================================================
           DASHBOARD GRID
        ====================================================== */

        .dashboard-grid {

            display: grid;

            grid-template-columns:
                1.4fr 1fr;

            gap: 22px;
        }

        .dashboard-card {

            background: var(--card);

            border:
                1px solid var(--border);

            border-radius: 15px;

            box-shadow: var(--shadow);

            overflow: hidden;
        }

        .card-header {

            padding: 20px;

            border-bottom:
                1px solid var(--border);

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 15px;
        }

        .card-header h2 {

            font-size: 15px;

            color: var(--text);
        }

        .card-header p {

            font-size: 11px;

            color: var(--muted);

            margin-top: 4px;
        }

        .card-body {
            padding: 20px;
        }

        /* =====================================================
           STORAGE
        ====================================================== */

        .storage-main {

            display: flex;

            justify-content: space-between;

            align-items: flex-end;

            margin-bottom: 14px;
        }

        .storage-used {

            font-size: 26px;

            font-weight: 700;

            color: var(--text);

            margin-bottom: 3px;
        }

        .storage-total {

            font-size: 12px;

            color: var(--muted);
        }

        .storage-bar {

            width: 100%;

            height: 10px;

            background:
                rgba(107, 114, 128, 0.14);

            border-radius: 20px;

            overflow: hidden;

            margin-bottom: 10px;
        }

        .storage-fill {

            height: 100%;

            background: var(--primary);

            border-radius: inherit;

            transition:
                width 0.5s ease;
        }

        .storage-info {

            display: flex;

            justify-content: space-between;

            gap: 10px;

            font-size: 11px;

            color: var(--muted);
        }

        .storage-status {

            font-weight: 700;

            color: var(--success);
        }

        .storage-details {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 10px;

            margin-top: 20px;
        }

        .storage-detail {

            border:
                1px solid var(--border);

            border-radius: 10px;

            padding: 12px;
        }

        .storage-detail-label {

            font-size: 10px;

            color: var(--muted);

            margin-bottom: 5px;
        }

        .storage-detail-value {

            font-size: 15px;

            font-weight: 700;

            color: var(--text);
        }

        /* =====================================================
           SYSTEM INFORMATION
        ====================================================== */

        .info-row {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 15px;

            padding: 3px 0;

            border-bottom:
                1px solid var(--border);
        }

        .info-row:last-of-type {
            border-bottom: none;
        }

        .info-label {

            font-size: 12px;

            color: var(--muted);
        }

        .info-value {

            font-size: 13px;

            font-weight: 700;

            color: var(--text);

            text-align: right;
        }

        /* =====================================================
           USER CAPACITY
        ====================================================== */

        .capacity-bar {

            width: 100%;

            height: 8px;

            background:
                rgba(107, 114, 128, 0.14);

            border-radius: 20px;

            overflow: hidden;

            margin-top: 15px;
        }

        .capacity-fill {

            height: 100%;

            background: var(--primary);

            border-radius: inherit;

            transition:
                width 0.5s ease;
        }

        .capacity-labels {

            display: flex;

            justify-content: space-between;

            margin-top: 8px;

            font-size: 10px;

            color: var(--muted);
        }

        /* =====================================================
           BUTTONS
        ====================================================== */

        .primary-button {

            border: none;

            background: var(--primary);

            color: #ffffff;

            padding:
                10px 15px;

            border-radius: 9px;

            font-size: 12px;

            font-weight: 600;

            cursor: pointer;

            transition: 0.2s ease;
        }

        .primary-button:hover {

            background: var(--primary-light);

            transform:
                translateY(-1px);
        }

        .secondary-button {

            border:
                1px solid var(--border);

            background: var(--card);

            color: var(--text);

            padding:
                10px 15px;

            border-radius: 9px;

            font-size: 12px;

            cursor: pointer;
        }

        /* =====================================================
           MODAL
        ====================================================== */

        .modal-overlay {

            position: fixed;

            inset: 0;

            background:
                rgba(0, 0, 0, 0.55);

            display: none;

            align-items: center;

            justify-content: center;

            padding: 20px;

            z-index: 999;
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal {

            width: 100%;

            max-width: 560px;

            max-height: 90vh;

            overflow-y: auto;

            background: var(--card);

            border-radius: 16px;

            box-shadow:
                0 20px 60px
                rgba(0, 0, 0, 0.25);
        }

        .modal-header {

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding: 20px;

            border-bottom:
                1px solid var(--border);
        }

        .modal-header h2 {

            font-size: 17px;

            color: var(--text);
        }

        .modal-close {

            width: 32px;
            height: 32px;

            border: none;

            border-radius: 8px;

            background:
                rgba(107, 114, 128, 0.1);

            color: var(--text);

            cursor: pointer;

            font-size: 17px;
        }

        .modal-body {
            padding: 20px;
        }

        .form-group {
            margin-bottom: 17px;
        }

        .form-group label {

            display: block;

            font-size: 12px;

            font-weight: 600;

            color: var(--text);

            margin-bottom: 7px;
        }

        .form-group input {

            width: 100%;

            padding:
                11px 12px;

            border:
                1px solid var(--border);

            border-radius: 9px;

            background: var(--background);

            color: var(--text);

            outline: none;

            font-size: 13px;
        }

        .form-group input:focus {

            border-color:
                var(--primary-light);
        }

        .form-help {

            font-size: 10px;

            color: var(--muted);

            margin-top: 5px;

            line-height: 1.5;
        }

        .modal-footer {

            padding:
                15px 20px 20px;

            display: flex;

            justify-content: flex-end;

            gap: 10px;
        }

        /* =====================================================
           MOBILE
        ====================================================== */

        .mobile-header {

            display: none;

            height: 65px;

            background: var(--card);

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

            font-weight: 700;

            color: var(--primary);

            font-size: 19px;
        }

        html.dark-mode .mobile-logo {
            color: #ffffff;
        }

        /* =====================================================
           RESPONSIVE
        ====================================================== */

        @media (max-width: 1100px) {

            .stats-grid {

                grid-template-columns:
                    repeat(2, 1fr);
            }

            .dashboard-grid {

                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {

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
                padding: 18px;
            }

            .stats-grid {

                grid-template-columns:
                    1fr 1fr;

                gap: 12px;
            }

            .stat-card {
                padding: 15px;
            }

            .stat-number {
                font-size: 23px;
            }

            .storage-details {
                grid-template-columns: 1fr;
            }

            .storage-info {

                flex-direction: column;

                gap: 5px;
            }
        }

        @media (max-width: 480px) {

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .card-header {

                align-items: flex-start;

                flex-direction: column;
            }

            .modal {
                max-height: 95vh;
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

            <div class="logo-icon cdm-logo">
                CDM
            </div>

            <div class="logo-text">
                NoteSphere
            </div>

        </div>

        <div class="admin-label">
            <?php echo e($admin_name); ?>
        </div>

        <div class="admin-role-label">
            Administrator
        </div>

        <nav class="sidebar-menu">

            <!-- DASHBOARD -->

            <a
                href="admin_dashboard.php"
                class="sidebar-link active"
            >

                <span class="sidebar-icon">

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="M3 10.5L12 3l9 7.5"></path>
                        <path d="M5 9.5V21h14V9.5"></path>
                        <path d="M9 21v-7h6v7"></path>
                    </svg>

                </span>

                <span>
                    Dashboard
                </span>

            </a>

            <!-- MANAGE USERS -->

            <a
                href="manage_users.php"
                class="sidebar-link"
            >

                <span class="sidebar-icon">

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

                </span>

                <span>
                    Manage Users
                </span>

            </a>

            <!-- MANAGE NOTES -->

            <a
                href="manage_notes.php"
                class="sidebar-link"
            >

                <span class="sidebar-icon">

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="M4 4h16v16H4z"></path>
                        <path d="M8 8h8"></path>
                        <path d="M8 12h8"></path>
                        <path d="M8 16h5"></path>
                    </svg>

                </span>

                <span>
                    Manage Notes
                </span>

            </a>

            <!-- HELPBOARD -->

            <a
                href="manage_helpboard.php"
                class="sidebar-link"
            >

                <span class="sidebar-icon">

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <circle cx="12" cy="12" r="9"></circle>
                        <path d="M9.5 9a2.5 2.5 0 1 1 4.2 1.8c-.9.8-1.7 1.3-1.7 2.7"></path>
                        <path d="M12 17h.01"></path>
                    </svg>

                </span>

                <span>
                    Helpboard
                </span>

            </a>

            <!-- ANNOUNCEMENTS -->

            <a
                href="manage_announcements.php"
                class="sidebar-link"
            >

                <span class="sidebar-icon">

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="M4 11h4l9-5v12l-9-5H4z"></path>
                        <path d="M8 13v5"></path>
                        <path d="M20 9v6"></path>
                    </svg>

                </span>

                <span>
                    Announcements
                </span>

            </a>

            <!-- STORAGE -->

            <a
                href="manage_storage.php"
                class="sidebar-link"
            >

                <span class="sidebar-icon">

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <ellipse cx="12" cy="5" rx="8" ry="3"></ellipse>
                        <path d="M4 5v7c0 1.7 3.6 3 8 3s8-1.3 8-3V5"></path>
                        <path d="M4 12v7c0 1.7 3.6 3 8 3s8-1.3 8-3v-7"></path>
                    </svg>

                </span>

                <span>
                    Academic Settings
                </span>

            </a>

            <!-- LOGOUT -->

            <a
                href="../auth/logout.php"
                class="sidebar-link"
                id="logoutLink"
            >

                <span class="sidebar-icon">

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="M10 17l5-5-5-5"></path>
                        <path d="M15 12H3"></path>
                        <path d="M21 3v18"></path>
                    </svg>

                </span>

                <span>
                    Logout
                </span>

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
                title="Toggle theme"
            >
                ☾
            </button>

        </div>

        <!-- TOPBAR -->

        <header class="topbar">

            <div class="page-title">

                <h1>
                    Admin Dashboard
                </h1>

                <p>
                    Manage and monitor your NoteSphere system
                </p>

            </div>

            <div class="topbar-right">

                <button
                    type="button"
                    class="theme-button"
                    id="themeButton"
                    title="Toggle theme"
                >
                    ☾
                </button>

                <div class="admin-profile">

                    <div class="admin-avatar">
                        <?php echo e($admin_initial); ?>
                    </div>

                    <div>

                        <div class="admin-profile-name">
                            <?php echo e($admin_name); ?>
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

            <!-- =================================================
                 STAT CARDS
            ================================================== -->

            <div class="stats-grid">

                <!-- USERS -->

                <div class="stat-card">

                    <div class="stat-top">

                        <div class="stat-title">
                            Total Users
                        </div>

                        <div class="stat-icon">
                            👥
                        </div>

                    </div>

                    <div class="stat-number">

                        <?php
                        echo number_format(
                            $total_users
                        );
                        ?>

                    </div>

                    <div class="stat-limit">

                        Maximum:
                        <?php
                        echo number_format(
                            $max_users
                        );
                        ?>
                        users

                    </div>

                </div>

                <!-- ANNOUNCEMENTS -->

                <div class="stat-card">

                    <div class="stat-top">

                        <div class="stat-title">
                            Announcements
                        </div>

                        <div class="stat-icon">
                            📢
                        </div>

                    </div>

                    <div class="stat-number">

                        <?php
                        echo number_format(
                            $total_announcements
                        );
                        ?>

                    </div>

                    <div class="stat-limit">
                        From professors
                    </div>

                </div>

                <!-- APPROVED NOTES -->

                <div class="stat-card">

                    <div class="stat-top">

                        <div class="stat-title">
                            Approved Notes
                        </div>

                        <div class="stat-icon">
                            ✓
                        </div>

                    </div>

                    <div class="stat-number">

                        <?php
                        echo number_format(
                            $approved_notes
                        );
                        ?>

                    </div>

                    <div class="stat-limit">
                        Available to users
                    </div>

                </div>

                <!-- PENDING NOTES -->

                <div class="stat-card">

                    <div class="stat-top">

                        <div class="stat-title">
                            Pending Notes
                        </div>

                        <div class="stat-icon">
                            ⏳
                        </div>

                    </div>

                    <div class="stat-number">

                        <?php
                        echo number_format(
                            $pending_notes
                        );
                        ?>

                    </div>

                    <div class="stat-limit">
                        Waiting for approval
                    </div>

                </div>

            </div>

            <!-- =================================================
                 DASHBOARD GRID
            ================================================== -->

            <div class="dashboard-grid">

                <!-- STORAGE -->

                <div class="dashboard-card">

                    <div class="card-header">

                        <div>

                            <h2>
                                System Storage
                            </h2>

                            <p>
                                Overall storage usage
                            </p>

                        </div>

                        <button
                            type="button"
                            class="primary-button"
                            id="openStorageModal"
                        >
                            Manage Storage
                        </button>

                    </div>

                    <div class="card-body">

                        <div class="storage-main">

                            <div>

                                <div class="storage-used">

                                    <?php
                                    echo e(
                                        $used_storage_display
                                    );
                                    ?>

                                </div>

                                <div class="storage-total">

                                    of
                                    <?php
                                    echo number_format(
                                        $total_storage_tb,
                                        2
                                    );
                                    ?>
                                    TB used

                                </div>

                            </div>

                        </div>

                        <div class="storage-info">

                            <span>

                                Used:
                                <?php
                                echo e(
                                    $used_storage_display
                                );
                                ?>

                            </span>

                            <span class="storage-status">

                                <?php
                                echo e(
                                    $storage_status
                                );
                                ?>

                            </span>


                        </div>

                        <div class="storage-details">

                            <div class="storage-detail">

                                <div class="storage-detail-label">
                                    System Storage
                                </div>

                                <div class="storage-detail-value">

                                    <?php
                                    echo number_format(
                                        $total_storage_tb,
                                        2
                                    );
                                    ?>
                                    TB

                                </div>

                            </div>

                            <div class="storage-detail">

                                <div class="storage-detail-label">
                                    Student Quota
                                </div>

                                <div class="storage-detail-value">

                                    <?php
                                    echo number_format(
                                        $student_storage_gb,
                                        2
                                    );
                                    ?>
                                    GB

                                </div>

                            </div>

                            <div class="storage-detail">

                                <div class="storage-detail-label">
                                    Professor Quota
                                </div>

                                <div class="storage-detail-value">

                                    <?php
                                    echo number_format(
                                        $professor_storage_gb,
                                        2
                                    );
                                    ?>
                                    GB

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- SYSTEM INFORMATION -->

                <div class="dashboard-card">

                    <div class="card-header">

                        <div>
                            <h2>System Information</h2>
                            <p>Current NoteSphere settings</p>
                        </div>

                    </div>

                    <div class="card-body">

                        <div class="info-row">

                            <span class="info-label">
                                Administrator
                            </span>

                            <span class="info-value">
                                <?php echo e($admin_name); ?>
                            </span>

                        </div>

                        <div class="info-row">

                            <span class="info-label">
                                Maximum Users
                            </span>

                            <span class="info-value">
                                <?php echo number_format($max_users); ?>
                            </span>

                        </div>

                        <div class="info-row">

                            <span class="info-label">
                                Current Users
                            </span>

                            <span class="info-value">
                                <?php echo number_format($total_users); ?>
                            </span>

                        </div>

                        <div class="info-row">

                            <span class="info-label">
                                Remaining Slots
                            </span>

                            <span class="info-value">
                                <?php echo number_format($remaining_user_slots); ?>
                            </span>

                        </div>

                        <!-- USER CAPACITY PROGRESS BAR -->

                        <div class="capacity-bar">

                            <div
                                class="capacity-fill"
                                style="width: <?php echo e($user_capacity_width); ?>%;"
                            ></div>

                        </div>

                        <div class="capacity-labels">

                            <span>
                                <?php echo number_format($total_users); ?>
                                registered
                            </span>

                            <span>
                                <?php echo number_format($user_capacity_percentage, 1); ?>%
                                of
                                <?php echo number_format($max_users); ?>
                                maximum
                            </span>

                        </div>

                    </div>

                </div>

<!-- =========================================================
     STORAGE MODAL
========================================================== -->

<div
    class="modal-overlay"
    id="storageModal"
>

    <div class="modal">

        <div class="modal-header">

            <h2>
                Manage System Settings
            </h2>

            <button
                type="button"
                class="modal-close"
                id="closeStorageModal"
            >
                ×
            </button>

        </div>

        <form
            method="POST"
            id="storageForm"
        >

            <input
                type="hidden"
                name="storage_action"
                value="manage_storage"
            >

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo e($csrf_token); ?>"
            >

            <div class="modal-body">

                <!-- SYSTEM STORAGE -->

                <div class="form-group">

                    <label for="add_system_storage_tb">
                        Add System Storage (TB)
                    </label>

                    <input
                        type="number"
                        id="add_system_storage_tb"
                        name="add_system_storage_tb"
                        min="0"
                        step="0.01"
                        value="0"
                    >

                    <div class="form-help">

                        Current system storage:
                        <?php
                        echo number_format(
                            $total_storage_tb,
                            2
                        );
                        ?>
                        TB.

                        Add additional storage if the
                        system is getting full.

                    </div>

                </div>

                <!-- STUDENT STORAGE -->

                <div class="form-group">

                    <label for="student_storage_gb">
                        Student Storage Quota (GB)
                    </label>

                    <input
                        type="number"
                        id="student_storage_gb"
                        name="student_storage_gb"
                        min="0.01"
                        step="0.01"
                        value="<?php
                            echo e(
                                number_format(
                                    $student_storage_gb,
                                    2,
                                    '.',
                                    ''
                                )
                            );
                        ?>"
                        required
                    >

                    <div class="form-help">

                        Maximum storage assigned to
                        each student.

                    </div>

                </div>

                <!-- PROFESSOR STORAGE -->

                <div class="form-group">

                    <label for="professor_storage_gb">
                        Professor Storage Quota (GB)
                    </label>

                    <input
                        type="number"
                        id="professor_storage_gb"
                        name="professor_storage_gb"
                        min="0.01"
                        step="0.01"
                        value="<?php
                            echo e(
                                number_format(
                                    $professor_storage_gb,
                                    2,
                                    '.',
                                    ''
                                )
                            );
                        ?>"
                        required
                    >

                    <div class="form-help">

                        Maximum storage assigned to
                        each professor.

                    </div>

                </div>

                <!-- MAX USERS -->

                <div class="form-group">

                    <label for="max_users">
                        Maximum System Users
                    </label>

                    <input
                        type="number"
                        id="max_users"
                        name="max_users"
                        min="<?php
                            echo max(
                                1,
                                $total_users
                            );
                        ?>"
                        max="1000000"
                        step="1"
                        value="<?php
                            echo e(
                                $max_users
                            );
                        ?>"
                        required
                    >

                    <div class="form-help">

                        Maximum number of accounts that
                        can exist in the NoteSphere system.

                        Current users:
                        <?php
                        echo number_format(
                            $total_users
                        );
                        ?>.

                    </div>

                </div>

            </div>

            <div class="modal-footer">

                <button
                    type="button"
                    class="secondary-button"
                    id="cancelStorageModal"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    class="primary-button"
                >
                    Save Changes
                </button>

            </div>

        </form>

    </div>

</div>

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
            document.documentElement.classList.contains(
                'dark-mode'
            );

        if (themeButton) {

            themeButton.textContent =
                isDark ? '☀' : '☾';
        }

        if (mobileThemeButton) {

            mobileThemeButton.textContent =
                isDark ? '☀' : '☾';
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
            isDark ? 'dark' : 'light'
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
    // STORAGE MODAL
    // ========================================================

    const storageModal =
        document.getElementById(
            'storageModal'
        );

    const openStorageModal =
        document.getElementById(
            'openStorageModal'
        );

    const closeStorageModal =
        document.getElementById(
            'closeStorageModal'
        );

    const cancelStorageModal =
        document.getElementById(
            'cancelStorageModal'
        );

    function openModal() {

        if (storageModal) {

            storageModal.classList.add(
                'show'
            );
        }
    }

    function closeModal() {

        if (storageModal) {

            storageModal.classList.remove(
                'show'
            );
        }
    }

    if (openStorageModal) {

        openStorageModal.addEventListener(
            'click',
            openModal
        );
    }

    if (closeStorageModal) {

        closeStorageModal.addEventListener(
            'click',
            closeModal
        );
    }

    if (cancelStorageModal) {

        cancelStorageModal.addEventListener(
            'click',
            closeModal
        );
    }

    if (storageModal) {

        storageModal.addEventListener(
            'click',
            function (event) {

                if (
                    event.target ===
                    storageModal
                ) {

                    closeModal();
                }

            }
        );
    }

    // ========================================================
    // SAVE CONFIRMATION
    // ========================================================

    const storageForm =
        document.getElementById(
            'storageForm'
        );

    if (storageForm) {

        storageForm.addEventListener(
            'submit',
            function (event) {

                const addStorageInput =
                    document.getElementById(
                        'add_system_storage_tb'
                    );

                const studentStorageInput =
                    document.getElementById(
                        'student_storage_gb'
                    );

                const professorStorageInput =
                    document.getElementById(
                        'professor_storage_gb'
                    );

                const maxUsersInput =
                    document.getElementById(
                        'max_users'
                    );

                const addStorage =
                    parseFloat(
                        addStorageInput.value
                    ) || 0;

                const studentStorage =
                    parseFloat(
                        studentStorageInput.value
                    ) || 0;

                const professorStorage =
                    parseFloat(
                        professorStorageInput.value
                    ) || 0;

                const maxUsers =
                    parseInt(
                        maxUsersInput.value,
                        10
                    ) || 0;

                if (addStorage < 0) {

                    event.preventDefault();

                    Swal.fire({

                        icon: 'error',

                        title: 'Invalid Storage',

                        text:
                            'Additional system storage cannot be negative.'

                    });

                    return;
                }

                if (studentStorage <= 0) {

                    event.preventDefault();

                    Swal.fire({

                        icon: 'error',

                        title:
                            'Invalid Student Storage',

                        text:
                            'Student storage must be greater than 0 GB.'

                    });

                    return;
                }

                if (professorStorage <= 0) {

                    event.preventDefault();

                    Swal.fire({

                        icon: 'error',

                        title:
                            'Invalid Professor Storage',

                        text:
                            'Professor storage must be greater than 0 GB.'

                    });

                    return;
                }

                if (maxUsers <= 0) {

                    event.preventDefault();

                    Swal.fire({

                        icon: 'error',

                        title:
                            'Invalid User Limit',

                        text:
                            'Maximum users must be greater than 0.'

                    });

                    return;
                }

                if (maxUsers > 1000000) {

                    event.preventDefault();

                    Swal.fire({

                        icon: 'error',

                        title:
                            'Invalid User Limit',

                        text:
                            'Maximum users cannot be more than 1,000,000.'

                    });

                    return;
                }

                if (
                    maxUsers <
                    <?php echo (int) $total_users; ?>
                ) {

                    event.preventDefault();

                    Swal.fire({

                        icon: 'error',

                        title:
                            'Invalid User Limit',

                        text:
                            'Maximum users cannot be lower than the current number of users.'

                    });

                    return;
                }

                event.preventDefault();

                Swal.fire({

                    icon: 'question',

                    title:
                        'Save Changes?',

                    text:
                        'The storage settings and maximum user limit will be updated.',

                    showCancelButton: true,

                    confirmButtonText:
                        'Yes, Save Changes',

                    cancelButtonText:
                        'Cancel',

                    reverseButtons: true

                }).then(
                    function (result) {

                        if (
                            result.isConfirmed
                        ) {

                            storageForm.submit();
                        }

                    }
                );
            }
        );
    }

    // ========================================================
    // LOGOUT CONFIRMATION
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

<?php if ($storage_success !== ''): ?>

<script>

    Swal.fire({

        icon: 'success',

        title: 'Success',

        text:
            <?php
            echo json_encode(
                $storage_success
            );
            ?>,

        confirmButtonText:
            'OK'

    });

</script>

<?php endif; ?>

<?php if ($storage_error !== ''): ?>

<script>

    Swal.fire({

        icon: 'error',

        title:
            'Something went wrong',

        text:
            <?php
            echo json_encode(
                $storage_error
            );
            ?>,

        confirmButtonText:
            'OK'

    });

</script>

<?php endif; ?>

</body>
</html>