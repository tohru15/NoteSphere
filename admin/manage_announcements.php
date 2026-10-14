
<?php

// =========================================================
// NoteSphere - Professor Announcements Management
// =========================================================

session_start();

require_once __DIR__ . '/../includes/db_config.php';

/** @var PDO $conn */
$pdo = $conn;

date_default_timezone_set('Asia/Manila');

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
// HELPER
// =========================================================

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// =========================================================
// SAFE ATTACHMENT PATH RESOLVER
// =========================================================

function ns_admin_resolve_upload_file($storedPath)
{
    if (!is_string($storedPath) || trim($storedPath) === '') {
        return null;
    }

    $path = trim(str_replace('\\', '/', $storedPath));

    if (preg_match('~^(https?://|www\.)~i', $path)) {
        return null;
    }

    if (preg_match('~^[A-Za-z]:/~', $path) || strpos($path, '/') === 0) {
        return null;
    }

    $projectRoot = realpath(dirname(__DIR__));
    $uploadsRoot = realpath(dirname(__DIR__) . '/uploads');

    if ($projectRoot === false || $uploadsRoot === false) {
        return null;
    }

    while (strpos($path, '../') === 0) {
        $path = substr($path, 3);
    }

    while (strpos($path, './') === 0) {
        $path = substr($path, 2);
    }

    $candidate = $projectRoot . '/' . ltrim($path, '/');
    $realFile = realpath($candidate);

    if ($realFile === false || !is_file($realFile)) {
        return null;
    }

    $allowedPrefix = rtrim($uploadsRoot, DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR;

    if (stripos($realFile, $allowedPrefix) !== 0) {
        return null;
    }

    return $realFile;
}

// =========================================================
// CHECK IF ANOTHER DATABASE RECORD USES THE FILE
// =========================================================

function ns_admin_upload_is_referenced(PDO $pdo, $realFile)
{
    $tables = [
        'announcements' => [
            'attachment',
            'attachment_path',
            'file_path',
            'filepath',
            'file_name',
            'filename'
        ],
        'notes' => [
            'file_path',
            'filepath',
            'file_name',
            'filename',
            'attachment',
            'attachment_path'
        ],
        'announcement_submissions' => [
            'file_path',
            'filepath',
            'file_name',
            'filename',
            'attachment',
            'attachment_path'
        ]
    ];

    foreach ($tables as $table => $possibleColumns) {
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM `" . $table . "`");
            $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            continue;
        }

        foreach ($possibleColumns as $column) {
            if (!in_array($column, $existingColumns, true)) {
                continue;
            }

            try {
                $stmt = $pdo->query("
                    SELECT `" . $column . "`
                    FROM `" . $table . "`
                    WHERE `" . $column . "` IS NOT NULL
                      AND `" . $column . "` <> ''
                ");

                while (($storedPath = $stmt->fetchColumn()) !== false) {
                    $referencedFile = ns_admin_resolve_upload_file($storedPath);

                    if (
                        $referencedFile !== null &&
                        strcasecmp($referencedFile, $realFile) === 0
                    ) {
                        return true;
                    }
                }
            } catch (PDOException $e) {
                continue;
            }
        }
    }

    return false;
}

// =========================================================
// DELETE ATTACHMENT IF NO DATABASE RECORD USES IT
// =========================================================

function ns_admin_delete_upload_if_unused(PDO $pdo, $storedPath)
{
    $realFile = ns_admin_resolve_upload_file($storedPath);

    if ($realFile === null) {
        return;
    }

    if (ns_admin_upload_is_referenced($pdo, $realFile)) {
        return;
    }

    if (is_file($realFile) && !@unlink($realFile)) {
        error_log(
            'NoteSphere: Unable to delete announcement attachment: ' . $realFile
        );
    }
}

// =========================================================
// CSRF TOKEN
// =========================================================

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

// =========================================================
// ADMIN INFORMATION
// =========================================================

$admin_name = 'Administrator';

try {
    $stmt = $pdo->prepare("
        SELECT full_name
        FROM users
        WHERE user_id = ?
          AND role = 'admin'
        LIMIT 1
    ");

    $stmt->execute([(int) $_SESSION['user_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin && !empty($admin['full_name'])) {
        $admin_name = trim($admin['full_name']);
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
// MESSAGES
// =========================================================

$message = '';
$error = '';

// =========================================================
// HANDLE POST ACTIONS
// =========================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted_token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($submitted_token) ||
        $submitted_token === '' ||
        !hash_equals($csrf_token, $submitted_token)
    ) {
        $error = 'Invalid security token. Please refresh the page and try again.';
    } else {
        $action = $_POST['action'] ?? '';

        // DELETE ONE ANNOUNCEMENT

        if ($action === 'delete') {
            $announcement_id = isset($_POST['announcement_id'])
                ? (int) $_POST['announcement_id']
                : 0;

            if ($announcement_id <= 0) {
                $error = 'Invalid announcement ID.';
            } else {
                try {
                    $stmt = $pdo->prepare("
                        SELECT attachment
                        FROM announcements
                        WHERE id = ?
                        LIMIT 1
                    ");

                    $stmt->execute([$announcement_id]);
                    $announcement = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$announcement) {
                        $error = 'The announcement was not found.';
                    } else {
                        $storedAttachment = $announcement['attachment'] ?? '';

                        $deleteStmt = $pdo->prepare("
                            DELETE FROM announcements
                            WHERE id = ?
                        ");

                        $deleteStmt->execute([$announcement_id]);

                        if ($deleteStmt->rowCount() > 0) {
                            if (
                                is_string($storedAttachment) &&
                                trim($storedAttachment) !== ''
                            ) {
                                ns_admin_delete_upload_if_unused(
                                    $pdo,
                                    $storedAttachment
                                );
                            }

                            $message = 'Professor announcement successfully deleted.';
                        } else {
                            $error = 'The announcement could not be deleted.';
                        }
                    }
                } catch (PDOException $e) {
                    error_log(
                        'NoteSphere announcement delete error: ' . $e->getMessage()
                    );

                    $error = 'Error deleting the announcement.';
                }
            }

        // DELETE ALL ANNOUNCEMENTS

        } elseif ($action === 'delete_all') {
            try {
                $stmt = $pdo->query("
                    SELECT attachment
                    FROM announcements
                    WHERE attachment IS NOT NULL
                      AND attachment <> ''
                ");

                $attachmentPaths = $stmt->fetchAll(PDO::FETCH_COLUMN);

                $deleteStmt = $pdo->prepare("DELETE FROM announcements");
                $deleteStmt->execute();

                $deleted_count = $deleteStmt->rowCount();

                foreach (array_unique($attachmentPaths) as $path) {
                    ns_admin_delete_upload_if_unused($pdo, $path);
                }

                if ($deleted_count > 0) {
                    $message = $deleted_count . ' announcement' .
                        ($deleted_count > 1 ? 's' : '') .
                        ' successfully deleted.';
                } else {
                    $message = 'There are no professor announcements to delete.';
                }
            } catch (PDOException $e) {
                error_log(
                    'NoteSphere delete-all announcements error: ' . $e->getMessage()
                );

                $error = 'Error deleting professor announcements.';
            }
        } else {
            $error = 'Invalid action.';
        }
    }
}

// =========================================================
// FETCH ALL PROFESSOR ANNOUNCEMENTS
// =========================================================

$announcements = [];

try {
    $stmt = $pdo->query("
        SELECT
            a.*,
            u.full_name AS professor_name,
            u.email AS professor_email,
            u.role AS professor_role
        FROM announcements a
        LEFT JOIN users u
            ON a.professor_id = u.user_id
        ORDER BY a.created_at DESC
    ");

    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('NoteSphere announcement listing error: ' . $e->getMessage());

    $announcements = [];

    if (empty($error)) {
        $error = 'Unable to load professor announcements.';
    }
}

$professor_count = count($announcements);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>NoteSphere - Professor Announcements</title>

    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --primary: #233961;
            --primary-light: #3D8BFF;
            --background: #f5f7fb;
            --card: #ffffff;
            --text: #1f2937;
            --muted: #6b7280;
            --border: #e5e7eb;
            --danger: #dc2626;
            --success: #16a34a;
            --shadow: 0 10px 30px rgba(15, 23, 42, 0.07);
        }

        body.dark-mode {
            --background: #0f172a;
            --card: #111827;
            --text: #f8fafc;
            --muted: #94a3b8;
            --border: #243044;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
        }

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
           TOPBAR
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
            background: #dc2626;
            border-color: #dc2626;
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

        /* PAGE */

        .page {
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            padding: 34px 38px 50px;
        }

        /* HIDE OLD BACK BUTTON */

        .back-btn {
            display: none;
        }

        /* PAGE HEADER */

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 25px;
        }

        .page-title {
            color: var(--text);
            font-size: 25px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        .page-description {
            color: var(--muted);
            font-size: 12px;
            margin-top: 5px;
            line-height: 1.6;
        }

        /* COUNT CARD */

        .count-card {
            min-width: 160px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 15px;
            padding: 13px 16px;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 11px;
        }

        .count-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: rgba(61, 139, 255, 0.10);
            color: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 19px;
        }

        .count-label {
            color: var(--muted);
            font-size: 9px;
            font-weight: 700;
        }

        .count-number {
            color: var(--text);
            font-size: 18px;
            font-weight: 800;
            margin-top: 2px;
        }

        /* MAIN CARD */

        .announcement-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 18px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .card-header {
            padding: 19px 22px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            flex-wrap: wrap;
        }

        .card-title {
            color: var(--text);
            font-size: 14px;
            font-weight: 800;
        }

        .card-subtitle {
            color: var(--muted);
            font-size: 10px;
            margin-top: 4px;
        }

        /* DELETE ALL */

        .delete-all-form {
            margin: 0;
        }

        .delete-all-btn {
            border: 1px solid rgba(220, 38, 38, 0.25);
            background: rgba(220, 38, 38, 0.06);
            color: var(--danger);
            border-radius: 9px;
            padding: 9px 13px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            font-size: 10px;
            font-weight: 800;
            cursor: pointer;
            transition: 0.2s;
        }

        .delete-all-btn:hover {
            background: var(--danger);
            border-color: var(--danger);
            color: #ffffff;
        }

        .delete-all-btn:disabled {
            opacity: 0.45;
            cursor: not-allowed;
            color: var(--muted);
            background: rgba(107, 114, 128, 0.06);
            border-color: var(--border);
        }

        /* TABLE */

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1100px;
        }

        th {
            padding: 13px 20px;
            background: rgba(107, 114, 128, 0.04);
            color: var(--muted);
            border-bottom: 1px solid var(--border);
            font-size: 9px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-align: left;
            white-space: nowrap;
        }

        td {
            padding: 17px 20px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
            color: var(--text);
            font-size: 11px;
        }

        tbody tr {
            transition: background 0.2s ease;
        }

        tbody tr:hover {
            background: rgba(61, 139, 255, 0.025);
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        /* PROFESSOR */

        .professor {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 190px;
        }

        .professor-avatar {
            width: 38px;
            height: 38px;
            border-radius: 11px;
            background: rgba(61, 139, 255, 0.10);
            color: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 800;
            flex-shrink: 0;
        }

        .professor-name {
            color: var(--text);
            font-size: 11px;
            font-weight: 800;
        }

        .professor-label {
            display: inline-block;
            margin-top: 4px;
            padding: 3px 7px;
            border-radius: 5px;
            background: rgba(61, 139, 255, 0.10);
            border: 1px solid rgba(61, 139, 255, 0.18);
            color: var(--primary-light);
            font-size: 8px;
            font-weight: 800;
            text-transform: uppercase;
        }

        /* ANNOUNCEMENT */

        .announcement-title {
            color: var(--text);
            font-size: 11px;
            font-weight: 800;
            margin-bottom: 5px;
        }

        .announcement-content {
            color: var(--muted);
            font-size: 10px;
            line-height: 1.7;
            max-width: 500px;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        /* DETAILS */

        .details {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-top: 8px;
        }

        .detail-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 7px;
            border-radius: 6px;
            background: rgba(61, 139, 255, 0.07);
            border: 1px solid rgba(61, 139, 255, 0.12);
            color: var(--primary-light);
            font-size: 8px;
            font-weight: 700;
        }

        /* DATE */

        .date {
            color: var(--muted);
            font-size: 10px;
            line-height: 1.6;
            white-space: nowrap;
        }

        /* STATUS */

        .status {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 8px;
            font-weight: 800;
            text-transform: uppercase;
            margin-top: 8px;
        }

        .status-active {
            background: rgba(22, 163, 74, 0.10);
            color: #16a34a;
        }

        .status-inactive {
            background: rgba(220, 38, 38, 0.10);
            color: #dc2626;
        }

        /* DELETE BUTTON */

        .delete-btn {
            border: 1px solid rgba(220, 38, 38, 0.25);
            background: rgba(220, 38, 38, 0.05);
            color: var(--danger);
            border-radius: 7px;
            padding: 7px 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            font-size: 9px;
            font-weight: 800;
            cursor: pointer;
            transition: 0.2s;
        }

        .delete-btn:hover {
            background: var(--danger);
            color: #ffffff;
            border-color: var(--danger);
        }

        .delete-icon {
            width: 13px;
            height: 13px;
            stroke: currentColor;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
            fill: none;
        }

        /* EMPTY STATE */

        .empty {
            text-align: center;
            padding: 70px 25px;
        }

        .empty-icon {
            width: 62px;
            height: 62px;
            margin: 0 auto 13px;
            border-radius: 17px;
            background: rgba(61, 139, 255, 0.08);
            color: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 25px;
        }

        .empty-title {
            color: var(--text);
            font-size: 15px;
            font-weight: 800;
        }

        .empty-text {
            color: var(--muted);
            font-size: 11px;
            margin-top: 5px;
        }

        /* RESPONSIVE */

        @media (max-width: 700px) {
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

            .page {
                padding: 25px 16px 35px;
            }

            .page-header {
                align-items: stretch;
                flex-direction: column;
            }

            .page-title {
                font-size: 22px;
            }

            .count-card {
                width: 100%;
            }

            .card-header {
                align-items: stretch;
                flex-direction: column;
            }

            .delete-all-btn {
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

            .profile-avatar {
                width: 35px;
                height: 35px;
            }

            .page {
                padding-right: 12px;
                padding-left: 12px;
            }
        }
    </style>
</head>

<body>

    <!-- =====================================================
         TOPBAR
         X BUTTON BESIDE NOTESPHERE
         DIRECT LINK TO ADMIN DASHBOARD
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
                    <?php echo e($admin_name); ?>
                </div>

                <div class="profile-role">Administrator</div>
            </div>

            <div class="profile-avatar">
                <?php echo e($admin_initial); ?>
            </div>

        </div>

    </header>

    <main class="page">

        <!-- PAGE HEADER -->

        <section class="page-header">

            <div>
                <h1 class="page-title">Professor Announcements</h1>

                <p class="page-description">
                    View and manage all announcements shared by professors with their students.
                </p>
            </div>

            <div class="count-card">

                <div class="count-icon">▣</div>

                <div>
                    <div class="count-label">PROFESSOR POSTS</div>

                    <div class="count-number">
                        <?php echo number_format($professor_count); ?>
                    </div>
                </div>

            </div>

        </section>

        <!-- ANNOUNCEMENT CARD -->

        <section class="announcement-card">

            <div class="card-header">

                <div>
                    <div class="card-title">All Professor Announcements</div>

                    <div class="card-subtitle">
                        These are the announcements currently posted by professors.
                    </div>
                </div>

                <form
                    method="POST"
                    class="delete-all-form"
                    id="deleteAllForm"
                >
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php echo e($csrf_token); ?>"
                    >

                    <input
                        type="hidden"
                        name="action"
                        value="delete_all"
                    >

                    <button
                        type="submit"
                        class="delete-all-btn"
                        id="deleteAllBtn"
                        <?php echo $professor_count <= 0 ? 'disabled' : ''; ?>
                    >
                        <svg class="delete-icon" viewBox="0 0 24 24" aria-hidden="true">
                            <polyline points="3 6 5 6 21 6"></polyline>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        </svg>

                        Delete All
                    </button>
                </form>

            </div>

            <div class="table-wrapper">

                <table>
                    <thead>
                        <tr>
                            <th>Professor</th>
                            <th>Announcement</th>
                            <th>Class Details</th>
                            <th>Date Posted</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if (!empty($announcements)): ?>

                        <?php foreach ($announcements as $row): ?>

                            <?php
                            $professor_name = trim(
                                (string) ($row['professor_name'] ?? 'Unknown Professor')
                            );

                            if ($professor_name === '') {
                                $professor_name = 'Unknown Professor';
                            }

                            $professor_initial = strtoupper(
                                substr($professor_name, 0, 1)
                            );

                            if ($professor_initial === '') {
                                $professor_initial = 'P';
                            }

                            $title = $row['title'] ?? 'Untitled Announcement';
                            $content = $row['message'] ?? '';
                            $subject = $row['subject'] ?? '';
                            $year_level = $row['year_level'] ?? '';
                            $section = $row['section'] ?? '';
                            $category = $row['category'] ?? '';
                            $semester = $row['semester'] ?? '';
                            $academic_year = $row['academic_year'] ?? '';
                            $status = $row['status'] ?? 'active';

                            $created_timestamp = !empty($row['created_at'])
                                ? strtotime($row['created_at'])
                                : false;
                            ?>

                            <tr>

                                <!-- PROFESSOR -->

                                <td>
                                    <div class="professor">

                                        <div class="professor-avatar">
                                            <?php echo e($professor_initial); ?>
                                        </div>

                                        <div>
                                            <div class="professor-name">
                                                <?php echo e($professor_name); ?>
                                            </div>

                                            <span class="professor-label">
                                                Professor
                                            </span>
                                        </div>

                                    </div>
                                </td>

                                <!-- ANNOUNCEMENT -->

                                <td>
                                    <div class="announcement-title">
                                        <?php echo e($title); ?>
                                    </div>

                                    <div class="announcement-content"><?php
                                        echo trim((string) $content) !== ''
                                            ? e($content)
                                            : 'No announcement message provided.';
                                    ?></div>
                                </td>

                                <!-- CLASS DETAILS -->

                                <td>

                                    <div class="details">

                                        <?php if ($subject !== ''): ?>
                                            <span class="detail-badge">
                                                Subject: <?php echo e($subject); ?>
                                            </span>
                                        <?php endif; ?>

                                        <?php if ($year_level !== ''): ?>
                                            <span class="detail-badge">
                                                <?php echo e($year_level); ?>
                                            </span>
                                        <?php endif; ?>

                                        <?php if ($section !== ''): ?>
                                            <span class="detail-badge">
                                                Section <?php echo e($section); ?>
                                            </span>
                                        <?php endif; ?>

                                        <?php if ($category !== ''): ?>
                                            <span class="detail-badge">
                                                <?php echo e($category); ?>
                                            </span>
                                        <?php endif; ?>

                                    </div>

                                    <?php if ($semester !== '' || $academic_year !== ''): ?>

                                        <div class="details">

                                            <?php if ($semester !== ''): ?>
                                                <span class="detail-badge">
                                                    <?php echo e($semester); ?>
                                                </span>
                                            <?php endif; ?>

                                            <?php if ($academic_year !== ''): ?>
                                                <span class="detail-badge">
                                                    AY <?php echo e($academic_year); ?>
                                                </span>
                                            <?php endif; ?>

                                        </div>

                                    <?php endif; ?>

                                    <span class="status <?php
                                        echo strtolower((string) $status) === 'active'
                                            ? 'status-active'
                                            : 'status-inactive';
                                    ?>">
                                        <?php echo e($status); ?>
                                    </span>

                                </td>

                                <!-- DATE POSTED -->

                                <td>
                                    <div class="date">

                                        <?php if ($created_timestamp !== false): ?>

                                            <?php echo e(date('M d, Y', $created_timestamp)); ?>

                                            <br>

                                            <?php echo e(date('h:i A', $created_timestamp)); ?>

                                        <?php else: ?>

                                            —

                                        <?php endif; ?>

                                    </div>
                                </td>

                                <!-- ACTION -->

                                <td>

                                    <form method="POST" class="delete-form">

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?php echo e($csrf_token); ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="delete"
                                        >

                                        <input
                                            type="hidden"
                                            name="announcement_id"
                                            value="<?php echo (int) $row['id']; ?>"
                                        >

                                        <button type="submit" class="delete-btn">

                                            <svg
                                                class="delete-icon"
                                                viewBox="0 0 24 24"
                                                aria-hidden="true"
                                            >
                                                <polyline points="3 6 5 6 21 6"></polyline>
                                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                            </svg>

                                            Delete

                                        </button>

                                    </form>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="5">

                                <div class="empty">

                                    <div class="empty-icon">✓</div>

                                    <div class="empty-title">
                                        No Professor Announcements
                                    </div>

                                    <div class="empty-text">
                                        There are currently no professor announcements in the system.
                                    </div>

                                </div>

                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>
                </table>

            </div>

        </section>

    </main>

    <script>
        // =====================================================
        // APPLY SAVED SYSTEM THEME
        // =====================================================

        function applySystemTheme() {
            const savedTheme = localStorage.getItem('notesphere_admin_theme');

            document.body.classList.toggle(
                'dark-mode',
                savedTheme === 'dark'
            );
        }

        applySystemTheme();

        // =====================================================
        // DELETE ONE ANNOUNCEMENT
        // =====================================================

        document.querySelectorAll('.delete-form').forEach(function(form) {
            form.addEventListener('submit', function(event) {
                event.preventDefault();

                Swal.fire({
                    title: 'Delete this announcement?',
                    text: 'This professor announcement will be permanently removed from the system.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, delete it',
                    cancelButtonText: 'Cancel',
                    reverseButtons: true,
                    confirmButtonColor: '#dc2626',
                    cancelButtonColor: '#6b7280'
                }).then(function(result) {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });
        });

        // =====================================================
        // DELETE ALL ANNOUNCEMENTS
        // =====================================================

        const deleteAllForm = document.getElementById('deleteAllForm');

        if (deleteAllForm) {
            deleteAllForm.addEventListener('submit', function(event) {
                event.preventDefault();

                const count = <?php echo (int) $professor_count; ?>;

                if (count <= 0) {
                    Swal.fire({
                        icon: 'info',
                        title: 'Nothing to Delete',
                        text: 'There are currently no professor announcements.',
                        confirmButtonColor: '#233961'
                    });

                    return;
                }

                Swal.fire({
                    title: 'Delete all professor announcements?',
                    html: 'You are about to delete <strong>' +
                        count +
                        '</strong> professor announcement' +
                        (count > 1 ? 's' : '') +
                        '.<br><br><strong>This action cannot be undone.</strong>',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, delete all',
                    cancelButtonText: 'Cancel',
                    reverseButtons: true,
                    confirmButtonColor: '#dc2626',
                    cancelButtonColor: '#6b7280'
                }).then(function(result) {
                    if (result.isConfirmed) {
                        deleteAllForm.submit();
                    }
                });
            });
        }

        // =====================================================
        // SUCCESS MESSAGE
        // =====================================================

        <?php if (!empty($message)): ?>

        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: <?php echo json_encode($message); ?>,
            confirmButtonColor: '#233961'
        });

        <?php endif; ?>

        // =====================================================
        // ERROR MESSAGE
        // =====================================================

        <?php if (!empty($error)): ?>

        Swal.fire({
            icon: 'error',
            title: 'Something went wrong',
            text: <?php echo json_encode($error); ?>,
            confirmButtonColor: '#dc2626'
        });

        <?php endif; ?>
    </script>

</body>
</html>
