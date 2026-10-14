
<?php

// =========================================================
// NoteSphere - Admin Manage Notes
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
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

// =========================================================
// SAFE UPLOAD PATH RESOLVER
// Only allows deleting files inside NoteSphere/uploads/
// =========================================================

function ns_notes_resolve_upload_file($storedPath)
{
    if (
        !is_string($storedPath) ||
        trim($storedPath) === ''
    ) {
        return null;
    }

    $path = trim(
        str_replace('\\', '/', $storedPath)
    );

    // Do not delete external URLs.
    if (
        preg_match('~^(https?://|www\.)~i', $path)
    ) {
        return null;
    }

    // Reject absolute paths.
    if (
        preg_match('~^[A-Za-z]:/~', $path) ||
        strpos($path, '/') === 0
    ) {
        return null;
    }

    $projectRoot = realpath(dirname(__DIR__));
    $uploadsRoot = realpath(
        dirname(__DIR__) . '/uploads'
    );

    if (
        $projectRoot === false ||
        $uploadsRoot === false
    ) {
        return null;
    }

    // Normalize relative paths.
    while (strpos($path, '../') === 0) {
        $path = substr($path, 3);
    }

    while (strpos($path, './') === 0) {
        $path = substr($path, 2);
    }

    $candidate = $projectRoot . '/' . ltrim($path, '/');
    $realFile = realpath($candidate);

    if (
        $realFile === false ||
        !is_file($realFile)
    ) {
        return null;
    }

    $allowedPrefix = rtrim(
        $uploadsRoot,
        DIRECTORY_SEPARATOR
    ) . DIRECTORY_SEPARATOR;

    if (
        stripos($realFile, $allowedPrefix) !== 0
    ) {
        return null;
    }

    return $realFile;
}

// =========================================================
// CHECK WHETHER A FILE IS STILL REFERENCED
// =========================================================

function ns_notes_upload_is_referenced(
    PDO $pdo,
    $realFile
) {
    $tables = [
        'notes' => [
            'file_path',
            'filepath',
            'file_name',
            'filename',
            'file',
            'document_path',
            'note_file',
            'attachment',
            'attachment_path',
            'file_url'
        ],
        'announcements' => [
            'attachment',
            'attachment_path',
            'file_path',
            'filepath',
            'file_name',
            'filename'
        ],
        'announcement_submissions' => [
            'file_path',
            'filepath',
            'file_name',
            'filename',
            'file',
            'attachment',
            'attachment_path'
        ]
    ];

    foreach ($tables as $table => $possibleColumns) {
        try {
            $stmt = $pdo->query(
                "SHOW COLUMNS FROM `" . $table . "`"
            );

            $existingColumns = $stmt->fetchAll(
                PDO::FETCH_COLUMN
            );
        } catch (PDOException $e) {
            continue;
        }

        foreach ($possibleColumns as $column) {
            if (
                !in_array(
                    $column,
                    $existingColumns,
                    true
                )
            ) {
                continue;
            }

            try {
                $stmt = $pdo->query(
                    "SELECT `" . $column . "`
                     FROM `" . $table . "`
                     WHERE `" . $column . "` IS NOT NULL
                       AND `" . $column . "` <> ''"
                );

                while (
                    ($storedPath = $stmt->fetchColumn()) !== false
                ) {
                    $referencedFile =
                        ns_notes_resolve_upload_file(
                            $storedPath
                        );

                    if (
                        $referencedFile !== null &&
                        strcasecmp(
                            $referencedFile,
                            $realFile
                        ) === 0
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
// DELETE FILE ONLY IF NO OTHER RECORD USES IT
// =========================================================

function ns_notes_delete_upload_if_unused(
    PDO $pdo,
    $storedPath
) {
    $realFile = ns_notes_resolve_upload_file(
        $storedPath
    );

    if ($realFile === null) {
        return;
    }

    if (
        ns_notes_upload_is_referenced(
            $pdo,
            $realFile
        )
    ) {
        return;
    }

    if (
        is_file($realFile) &&
        !@unlink($realFile)
    ) {
        error_log(
            'NoteSphere: Unable to delete note file: ' .
            $realFile
        );
    }
}

// =========================================================
// CSRF TOKEN
// =========================================================

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(
        random_bytes(32)
    );
}

$csrf_token = $_SESSION['csrf_token'];

// =========================================================
// VARIABLES
// =========================================================

$message = '';
$error = '';

// =========================================================
// CURRENT PAGE
// =========================================================

$current_page = basename($_SERVER['PHP_SELF']);

// =========================================================
// HANDLE POST ACTIONS
// =========================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // -----------------------------------------------------
    // CSRF CHECK
    // -----------------------------------------------------

    $submitted_token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($submitted_token) ||
        $submitted_token === '' ||
        !hash_equals(
            $csrf_token,
            $submitted_token
        )
    ) {
        $error =
            'Invalid security token. Please refresh the page and try again.';

    } else {

        $action = $_POST['action'] ?? '';

        $note_id = isset($_POST['note_id'])
            ? (int) $_POST['note_id']
            : 0;

        // -------------------------------------------------
        // VALIDATE NOTE ID
        // -------------------------------------------------

        if ($note_id <= 0) {

            $error = 'Invalid note ID.';

        } else {

            // =============================================
            // APPROVE NOTE
            // =============================================

            if ($action === 'approve') {

                try {
                    $stmt = $pdo->prepare("
                        UPDATE notes
                        SET status = 'approved'
                        WHERE id = ?
                          AND status = 'pending'
                    ");

                    $stmt->execute([
                        $note_id
                    ]);

                    if ($stmt->rowCount() > 0) {

                        $message =
                            'Note successfully approved!';

                    } else {

                        // Check whether it exists and is already approved.
                        $check = $pdo->prepare("
                            SELECT status
                            FROM notes
                            WHERE id = ?
                            LIMIT 1
                        ");

                        $check->execute([
                            $note_id
                        ]);

                        $existingNote = $check->fetch(
                            PDO::FETCH_ASSOC
                        );

                        if (!$existingNote) {
                            $error = 'The note was not found.';
                        } elseif (
                            $existingNote['status'] === 'approved'
                        ) {
                            $error = 'This note is already approved.';
                        } else {
                            $error = 'The note could not be approved.';
                        }
                    }

                } catch (PDOException $e) {
                    error_log(
                        'NoteSphere approve note error: ' .
                        $e->getMessage()
                    );

                    $error = 'Error approving note.';
                }

            }

            // =============================================
            // DELETE NOTE
            // =============================================

            elseif ($action === 'delete') {

                try {
                    // Fetch file paths before removing the database row.
                    $stmt = $pdo->prepare("
                        SELECT file_path, thumbnail
                        FROM notes
                        WHERE id = ?
                        LIMIT 1
                    ");

                    $stmt->execute([
                        $note_id
                    ]);

                    $note = $stmt->fetch(
                        PDO::FETCH_ASSOC
                    );

                    if (!$note) {

                        $error =
                            'The note could not be deleted because it no longer exists.';

                    } else {

                        $storedFile = $note['file_path'] ?? '';
                        $storedThumbnail = $note['thumbnail'] ?? '';

                        $deleteStmt = $pdo->prepare("
                            DELETE FROM notes
                            WHERE id = ?
                        ");

                        $deleteStmt->execute([
                            $note_id
                        ]);

                        if ($deleteStmt->rowCount() > 0) {

                            // Remove the main file only if unused.
                            if (
                                is_string($storedFile) &&
                                trim($storedFile) !== ''
                            ) {
                                ns_notes_delete_upload_if_unused(
                                    $pdo,
                                    $storedFile
                                );
                            }

                            // Remove the thumbnail only if unused.
                            if (
                                is_string($storedThumbnail) &&
                                trim($storedThumbnail) !== ''
                            ) {
                                ns_notes_delete_upload_if_unused(
                                    $pdo,
                                    $storedThumbnail
                                );
                            }

                            $message = 'Note successfully deleted.';

                        } else {

                            $error = 'The note could not be deleted.';
                        }
                    }

                } catch (PDOException $e) {
                    error_log(
                        'NoteSphere delete note error: ' .
                        $e->getMessage()
                    );

                    $error = 'Error deleting note.';
                }

            }

            // =============================================
            // INVALID ACTION
            // =============================================

            else {

                $error = 'Invalid action.';
            }
        }
    }
}

// =========================================================
// FILTER
// =========================================================

$filter = $_GET['filter'] ?? 'all';

$allowed_filters = [
    'all',
    'pending',
    'approved'
];

if (
    !in_array(
        $filter,
        $allowed_filters,
        true
    )
) {
    $filter = 'all';
}

// =========================================================
// FETCH NOTES
// =========================================================

$notes = [];

try {

    if ($filter === 'pending') {

        $query = "
            SELECT
                notes.*,
                users.full_name AS uploader_name,
                users.role AS uploader_role
            FROM notes
            INNER JOIN users
                ON notes.user_id = users.user_id
            WHERE notes.status = 'pending'
            ORDER BY notes.created_at DESC
        ";

    } elseif ($filter === 'approved') {

        $query = "
            SELECT
                notes.*,
                users.full_name AS uploader_name,
                users.role AS uploader_role
            FROM notes
            INNER JOIN users
                ON notes.user_id = users.user_id
            WHERE notes.status = 'approved'
            ORDER BY notes.created_at DESC
        ";

    } else {

        $query = "
            SELECT
                notes.*,
                users.full_name AS uploader_name,
                users.role AS uploader_role
            FROM notes
            INNER JOIN users
                ON notes.user_id = users.user_id
            ORDER BY notes.created_at DESC
        ";
    }

    $stmt = $pdo->query($query);

    $notes = $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );

} catch (PDOException $e) {
    error_log(
        'NoteSphere fetch notes error: ' .
        $e->getMessage()
    );

    $notes = [];

    if (empty($error)) {
        $error = 'Unable to load notes.';
    }
}

// =========================================================
// COUNT NOTES
// =========================================================

$total_notes = 0;
$pending_notes = 0;
$approved_notes = 0;

try {

    $count_stmt = $pdo->query("
        SELECT
            COUNT(*) AS total_notes,

            COALESCE(
                SUM(
                    CASE
                        WHEN status = 'pending'
                        THEN 1
                        ELSE 0
                    END
                ),
                0
            ) AS pending_notes,

            COALESCE(
                SUM(
                    CASE
                        WHEN status = 'approved'
                        THEN 1
                        ELSE 0
                    END
                ),
                0
            ) AS approved_notes

        FROM notes
    ");

    $count_data = $count_stmt->fetch(
        PDO::FETCH_ASSOC
    );

    if ($count_data) {
        $total_notes = (int) (
            $count_data['total_notes'] ?? 0
        );

        $pending_notes = (int) (
            $count_data['pending_notes'] ?? 0
        );

        $approved_notes = (int) (
            $count_data['approved_notes'] ?? 0
        );
    }

} catch (PDOException $e) {
    error_log(
        'NoteSphere count notes error: ' .
        $e->getMessage()
    );
}

// =========================================================
// PAGE TITLE
// =========================================================

$page_title = 'Manage Notes';

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
        NoteSphere - <?php echo e($page_title); ?>
    </title>

    <!-- Google Font -->
    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>

        /* =================================================
           ROOT
        ================================================= */

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

            --shadow:
                0 10px 30px rgba(15, 23, 42, 0.06);
        }


        /* =================================================
           DARK MODE
        ================================================= */

        body.dark-mode {

            --background: #0f172a;

            --card: #111827;

            --text: #f8fafc;

            --muted: #94a3b8;

            --border: #243044;

            --shadow:
                0 10px 30px rgba(0, 0, 0, 0.20);
        }


        /* =================================================
           RESET
        ================================================= */

        * {

            margin: 0;

            padding: 0;

            box-sizing: border-box;

            font-family:
                'Plus Jakarta Sans',
                sans-serif;
        }


        html {

            scroll-behavior: smooth;
        }


        body {

            background:
                var(--background);

            color:
                var(--text);

            min-height:
                100vh;

            transition:
                background 0.25s ease,
                color 0.25s ease;
        }


        a {

            text-decoration: none;
        }


        button,
        input,
        select {

            font-family: inherit;
        }


        /* =================================================
           BACK / X BUTTON
        ================================================= */

        .back-btn {

            position: fixed;

            top: 20px;

            left: 20px;

            width: 42px;

            height: 42px;

            border:
                1px solid var(--border);

            border-radius: 12px;

            background:
                var(--card);

            color:
                var(--text);

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            cursor:
                pointer;

            z-index:
                2000;

            box-shadow:
                var(--shadow);

            transition:
                background 0.2s ease,
                color 0.2s ease,
                border-color 0.2s ease,
                transform 0.2s ease;
        }


        .back-btn:hover {

            color:
                var(--primary-light);

            border-color:
                var(--primary-light);

            transform:
                translateY(-1px);
        }


        .back-btn svg {

            width:
                19px;

            height:
                19px;

            stroke:
                currentColor;
        }


        /* =================================================
           MAIN
        ================================================= */

        .main {

            min-height:
                100vh;

            width:
                100%;
        }


        /* =================================================
           TOPBAR
        ================================================= */

        .topbar {

            min-height:
                76px;

            background:
                var(--card);

            border-bottom:
                1px solid var(--border);

            display:
                flex;

            align-items:
                center;

            padding:
                0 32px 0 82px;

            position:
                sticky;

            top:
                0;

            z-index:
                900;

            transition:
                background 0.25s ease,
                border-color 0.25s ease;
        }


        .topbar-left h1 {

            font-size:
                20px;

            font-weight:
                800;

            color:
                var(--text);
        }


        .topbar-left p {

            color:
                var(--muted);

            font-size:
                11px;

            margin-top:
                3px;
        }


        /* =================================================
           CONTENT
        ================================================= */

        .content {

            padding:
                30px 32px 40px;

            max-width:
                1600px;

            margin:
                0 auto;
        }


        .page-header {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                20px;

            margin-bottom:
                24px;
        }


        .page-title {

            font-size:
                24px;

            font-weight:
                800;

            color:
                var(--text);

            letter-spacing:
                -0.5px;
        }


        .page-description {

            color:
                var(--muted);

            font-size:
                12px;

            margin-top:
                5px;
        }


        /* =================================================
           STAT CARDS
        ================================================= */

        .stats-grid {

            display:
                grid;

            grid-template-columns:
                repeat(3, minmax(0, 1fr));

            gap:
                16px;

            margin-bottom:
                22px;
        }


        .stat-card {

            background:
                var(--card);

            border:
                1px solid var(--border);

            border-radius:
                16px;

            padding:
                18px;

            box-shadow:
                var(--shadow);

            display:
                flex;

            align-items:
                center;

            gap:
                14px;

            transition:
                background 0.25s ease,
                border-color 0.25s ease;
        }


        .stat-icon {

            width:
                44px;

            height:
                44px;

            border-radius:
                12px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            font-size:
                19px;

            background:
                rgba(61, 139, 255, 0.10);

            color:
                var(--primary-light);

            flex-shrink:
                0;
        }


        .stat-card.pending .stat-icon {

            background:
                rgba(245, 158, 11, 0.10);

            color:
                var(--warning);
        }


        .stat-card.approved .stat-icon {

            background:
                rgba(22, 163, 74, 0.10);

            color:
                var(--success);
        }


        .stat-label {

            color:
                var(--muted);

            font-size:
                11px;

            font-weight:
                600;

            margin-bottom:
                2px;
        }


        .stat-number {

            color:
                var(--text);

            font-size:
                20px;

            font-weight:
                800;
        }


        /* =================================================
           FILTER CARD
        ================================================= */

        .filter-card {

            background:
                var(--card);

            border:
                1px solid var(--border);

            border-radius:
                16px;

            padding:
                15px;

            margin-bottom:
                18px;

            box-shadow:
                var(--shadow);

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                15px;

            flex-wrap:
                wrap;

            transition:
                background 0.25s ease,
                border-color 0.25s ease;
        }


        .filter-title {

            font-size:
                13px;

            font-weight:
                800;

            color:
                var(--text);
        }


        .filter-subtitle {

            font-size:
                10px;

            color:
                var(--muted);

            margin-top:
                2px;
        }


        .filter-buttons {

            display:
                flex;

            gap:
                7px;

            flex-wrap:
                wrap;
        }


        .filter-btn {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                6px;

            padding:
                9px 13px;

            border-radius:
                9px;

            border:
                1px solid var(--border);

            background:
                var(--card);

            color:
                var(--muted);

            font-size:
                11px;

            font-weight:
                700;

            transition:
                0.2s;
        }


        .filter-btn:hover {

            border-color:
                var(--primary-light);

            color:
                var(--primary-light);
        }


        .filter-btn.active {

            background:
                rgba(61, 139, 255, 0.10);

            border-color:
                var(--primary-light);

            color:
                var(--primary-light);
        }


        .filter-count {

            min-width:
                20px;

            height:
                20px;

            padding:
                0 5px;

            border-radius:
                6px;

            background:
                rgba(107, 114, 128, 0.10);

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            font-size:
                9px;
        }


        .filter-btn.active .filter-count {

            background:
                var(--primary-light);

            color:
                #ffffff;
        }


        /* =================================================
           NOTES TABLE CARD
        ================================================= */

        .table-card {

            background:
                var(--card);

            border:
                1px solid var(--border);

            border-radius:
                16px;

            box-shadow:
                var(--shadow);

            overflow:
                hidden;

            transition:
                background 0.25s ease,
                border-color 0.25s ease;
        }


        .table-header {

            padding:
                18px 20px;

            border-bottom:
                1px solid var(--border);

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                10px;
        }


        .table-title {

            color:
                var(--text);

            font-size:
                14px;

            font-weight:
                800;
        }


        .table-description {

            color:
                var(--muted);

            font-size:
                10px;

            margin-top:
                3px;
        }


        .table-wrapper {

            width:
                100%;

            overflow-x:
                auto;
        }


        table {

            width:
                100%;

            min-width:
                900px;

            border-collapse:
                collapse;

            text-align:
                left;
        }


        th {

            padding:
                13px 18px;

            background:
                rgba(107, 114, 128, 0.04);

            color:
                var(--muted);

            font-size:
                10px;

            font-weight:
                800;

            text-transform:
                uppercase;

            letter-spacing:
                0.4px;

            border-bottom:
                1px solid var(--border);

            white-space:
                nowrap;
        }


        td {

            padding:
                16px 18px;

            border-bottom:
                1px solid var(--border);

            vertical-align:
                middle;

            font-size:
                11px;

            color:
                var(--text);
        }


        tr:last-child td {

            border-bottom:
                none;
        }


        tbody tr {

            transition:
                background 0.2s ease;
        }


        tbody tr:hover {

            background:
                rgba(61, 139, 255, 0.025);
        }


        /* =================================================
           UPLOADER
        ================================================= */

        .uploader {

            display:
                flex;

            align-items:
                center;

            gap:
                9px;

            min-width:
                150px;
        }


        .uploader-avatar {

            width:
                34px;

            height:
                34px;

            border-radius:
                10px;

            background:
                rgba(61, 139, 255, 0.10);

            color:
                var(--primary-light);

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            font-size:
                11px;

            font-weight:
                800;

            flex-shrink:
                0;
        }


        .uploader-name {

            font-weight:
                800;

            color:
                var(--text);

            font-size:
                11px;
        }


        .role-badge {

            display:
                inline-block;

            margin-top:
                4px;

            padding:
                3px 7px;

            border-radius:
                5px;

            font-size:
                8px;

            font-weight:
                800;

            text-transform:
                uppercase;

            letter-spacing:
                0.3px;
        }


        .role-admin {

            background:
                rgba(245, 158, 11, 0.10);

            color:
                var(--warning);

            border:
                1px solid rgba(245, 158, 11, 0.20);
        }


        .role-professor {

            background:
                rgba(61, 139, 255, 0.10);

            color:
                var(--primary-light);

            border:
                1px solid rgba(61, 139, 255, 0.20);
        }


        .role-student {

            background:
                rgba(22, 163, 74, 0.10);

            color:
                var(--success);

            border:
                1px solid rgba(22, 163, 74, 0.20);
        }


        /* =================================================
           NOTE DETAILS
        ================================================= */

        .note-title {

            color:
                var(--text);

            font-weight:
                800;

            font-size:
                11px;

            margin-bottom:
                3px;
        }


        .note-subject {

            color:
                var(--primary-light);

            font-size:
                9px;

            font-weight:
                800;

            text-transform:
                uppercase;
        }


        .description-text {

            color:
                var(--muted);

            font-size:
                10px;

            line-height:
                1.5;

            max-width:
                280px;

            margin-bottom:
                7px;
        }


        /* =================================================
           VIEW FILE BUTTON
        ================================================= */

        .file-link {

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            gap:
                6px;

            color:
                var(--primary-light);

            font-size:
                9px;

            font-weight:
                800;

            padding:
                7px 10px;

            border-radius:
                7px;

            border:
                1px solid rgba(61, 139, 255, 0.25);

            background:
                rgba(61, 139, 255, 0.06);

            transition:
                0.2s;

            white-space:
                nowrap;
        }


        .file-link:hover {

            color:
                #ffffff;

            background:
                var(--primary-light);

            border-color:
                var(--primary-light);

            transform:
                translateY(-1px);
        }


        .file-link svg {

            width:
                13px;

            height:
                13px;

            stroke:
                currentColor;
        }


        .date-text {

            color:
                var(--muted);

            font-size:
                10px;

            white-space:
                nowrap;
        }


        /* =================================================
           STATUS
        ================================================= */

        .status-badge {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                5px;

            padding:
                5px 8px;

            border-radius:
                6px;

            font-size:
                8px;

            font-weight:
                800;

            text-transform:
                uppercase;
        }


        .status-badge::before {

            content:
                '';

            width:
                5px;

            height:
                5px;

            border-radius:
                50%;
        }


        .status-approved {

            background:
                rgba(22, 163, 74, 0.10);

            color:
                var(--success);

            border:
                1px solid rgba(22, 163, 74, 0.20);
        }


        .status-approved::before {

            background:
                var(--success);
        }


        .status-pending {

            background:
                rgba(245, 158, 11, 0.10);

            color:
                var(--warning);

            border:
                1px solid rgba(245, 158, 11, 0.20);
        }


        .status-pending::before {

            background:
                var(--warning);
        }


        /* =================================================
           ACTION BUTTONS
        ================================================= */

        .action-buttons {

            display:
                flex;

            align-items:
                center;

            gap:
                6px;
        }


        .action-form {

            margin:
                0;
        }


        .action-btn {

            border:
                1px solid var(--border);

            background:
                var(--card);

            color:
                var(--text);

            padding:
                7px 10px;

            border-radius:
                7px;

            font-size:
                9px;

            font-weight:
                800;

            cursor:
                pointer;

            transition:
                0.2s;

            white-space:
                nowrap;
        }


        .approve-btn {

            color:
                var(--success);

            border-color:
                rgba(22, 163, 74, 0.25);

            background:
                rgba(22, 163, 74, 0.06);
        }


        .approve-btn:hover {

            background:
                var(--success);

            color:
                #ffffff;

            border-color:
                var(--success);
        }


        .delete-btn {

            color:
                var(--danger);

            border-color:
                rgba(220, 38, 38, 0.25);

            background:
                rgba(220, 38, 38, 0.05);
        }


        .delete-btn:hover {

            background:
                var(--danger);

            color:
                #ffffff;

            border-color:
                var(--danger);
        }


        /* =================================================
           EMPTY STATE
        ================================================= */

        .empty-state {

            text-align:
                center;

            padding:
                55px 20px;
        }


        .empty-icon {

            width:
                58px;

            height:
                58px;

            margin:
                0 auto 12px;

            border-radius:
                16px;

            background:
                rgba(61, 139, 255, 0.08);

            color:
                var(--primary-light);

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            font-size:
                24px;
        }


        .empty-title {

            color:
                var(--text);

            font-size:
                14px;

            font-weight:
                800;

            margin-bottom:
                4px;
        }


        .empty-text {

            color:
                var(--muted);

            font-size:
                11px;
        }


        /* =================================================
           MOBILE
        ================================================= */

        @media (max-width: 1000px) {

            .stats-grid {

                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
            }
        }


        @media (max-width: 768px) {

            .topbar {

                padding:
                    20px 20px 20px 76px;
            }


            .content {

                padding:
                    24px 20px 35px;
            }


            .stats-grid {

                grid-template-columns:
                    1fr;
            }


            .back-btn {

                top:
                    17px;

                left:
                    17px;

                width:
                    40px;

                height:
                    40px;
            }
        }


        @media (max-width: 600px) {

            .topbar {

                min-height:
                    70px;

                align-items:
                    flex-start;

                padding:
                    18px 16px 18px 68px;
            }


            .topbar-left h1 {

                font-size:
                    18px;
            }


            .topbar-left p {

                font-size:
                    10px;

                line-height:
                    1.4;
            }


            .content {

                padding:
                    20px 14px 30px;
            }


            .page-title {

                font-size:
                    21px;
            }


            .page-description {

                font-size:
                    11px;

                line-height:
                    1.5;
            }


            .filter-card {

                align-items:
                    flex-start;

                flex-direction:
                    column;
            }


            .filter-buttons {

                width:
                    100%;
            }


            .filter-btn {

                flex:
                    1;

                justify-content:
                    center;
            }


            .table-header {

                padding:
                    16px;
            }


            .file-link {

                font-size:
                    8px;

                padding:
                    7px 9px;
            }
        }

    </style>

</head>


<body>

    <!-- =====================================================
         X / BACK BUTTON
         DIRECTLY GOES TO ADMIN DASHBOARD
    ====================================================== -->

    <a
        href="admin_dashboard.php"
        class="back-btn"
        title="Back to Admin Dashboard"
        aria-label="Back to Admin Dashboard"
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

    </a>


    <!-- =====================================================
         MAIN
    ===================================================== -->

    <div class="main">

        <!-- =================================================
             TOPBAR
        ================================================== -->

        <header class="topbar">

            <div class="topbar-left">

                <h1>
                    Manage Notes
                </h1>

                <p>
                    Review and manage shared learning materials
                </p>

            </div>

        </header>


        <!-- =================================================
             CONTENT
        ================================================= -->

        <main class="content">

            <!-- PAGE HEADER -->

            <div class="page-header">

                <div>

                    <h2 class="page-title">
                        Shared Notes
                    </h2>

                    <p class="page-description">
                        Review, approve, or remove uploaded student and professor learning materials.
                    </p>

                </div>

            </div>


            <!-- =================================================
                 STATISTICS
            ================================================== -->

            <div class="stats-grid">

                <!-- TOTAL -->

                <div class="stat-card">

                    <div class="stat-icon">
                        ▤
                    </div>

                    <div>

                        <div class="stat-label">
                            Total Notes
                        </div>

                        <div class="stat-number">
                            <?php echo number_format($total_notes); ?>
                        </div>

                    </div>

                </div>


                <!-- PENDING -->

                <div class="stat-card pending">

                    <div class="stat-icon">
                        !
                    </div>

                    <div>

                        <div class="stat-label">
                            Pending Approval
                        </div>

                        <div class="stat-number">
                            <?php echo number_format($pending_notes); ?>
                        </div>

                    </div>

                </div>


                <!-- APPROVED -->

                <div class="stat-card approved">

                    <div class="stat-icon">
                        ✓
                    </div>

                    <div>

                        <div class="stat-label">
                            Approved Notes
                        </div>

                        <div class="stat-number">
                            <?php echo number_format($approved_notes); ?>
                        </div>

                    </div>

                </div>

            </div>


            <!-- =================================================
                 FILTER
            ================================================== -->

            <div class="filter-card">

                <div>

                    <div class="filter-title">
                        Note Status
                    </div>

                    <div class="filter-subtitle">
                        Choose which notes you want to view.
                    </div>

                </div>


                <div class="filter-buttons">

                    <a
                        href="<?php echo e($current_page); ?>?filter=all"
                        class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>"
                    >

                        All Notes

                        <span class="filter-count">
                            <?php echo number_format($total_notes); ?>
                        </span>

                    </a>


                    <a
                        href="<?php echo e($current_page); ?>?filter=pending"
                        class="filter-btn <?php echo $filter === 'pending' ? 'active' : ''; ?>"
                    >

                        Pending

                        <span class="filter-count">
                            <?php echo number_format($pending_notes); ?>
                        </span>

                    </a>


                    <a
                        href="<?php echo e($current_page); ?>?filter=approved"
                        class="filter-btn <?php echo $filter === 'approved' ? 'active' : ''; ?>"
                    >

                        Approved

                        <span class="filter-count">
                            <?php echo number_format($approved_notes); ?>
                        </span>

                    </a>

                </div>

            </div>


            <!-- =================================================
                 TABLE CARD
            ================================================== -->

            <div class="table-card">

                <div class="table-header">

                    <div>

                        <div class="table-title">

                            <?php

                            if ($filter === 'pending') {

                                echo 'Pending Notes';

                            } elseif ($filter === 'approved') {

                                echo 'Approved Notes';

                            } else {

                                echo 'All Shared Notes';
                            }

                            ?>

                        </div>


                        <div class="table-description">

                            Learning materials uploaded by students and professors.

                        </div>

                    </div>

                </div>


                <div class="table-wrapper">

                    <table>

                        <thead>

                            <tr>

                                <th>
                                    Uploader
                                </th>

                                <th>
                                    Note
                                </th>

                                <th>
                                    Description / File
                                </th>

                                <th>
                                    Status
                                </th>

                                <th>
                                    Date Uploaded
                                </th>

                                <th>
                                    Actions
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                            <?php if (!empty($notes)): ?>

                                <?php foreach ($notes as $note): ?>

                                    <?php

                                    $uploader_name =
                                        $note['uploader_name']
                                        ?? 'Unknown User';

                                    $uploader_role =
                                        strtolower(
                                            $note['uploader_role']
                                            ?? 'student'
                                        );

                                    $uploader_initial =
                                        strtoupper(
                                            substr(
                                                $uploader_name,
                                                0,
                                                1
                                            )
                                        );

                                    if ($uploader_initial === '') {
                                        $uploader_initial = 'U';
                                    }

                                    $note_status =
                                        strtolower(
                                            $note['status']
                                            ?? 'approved'
                                        );

                                    $note_title =
                                        $note['title']
                                        ?? 'Untitled Note';

                                    $note_subject =
                                        $note['subject']
                                        ?? 'General';

                                    $note_description =
                                        $note['description']
                                        ?? '';

                                    $file_path =
                                        $note['file_path']
                                        ?? '';

                                    ?>


                                    <tr>

                                        <!-- UPLOADER -->

                                        <td>

                                            <div class="uploader">

                                                <div class="uploader-avatar">

                                                    <?php
                                                    echo e(
                                                        $uploader_initial
                                                    );
                                                    ?>

                                                </div>


                                                <div>

                                                    <div class="uploader-name">

                                                        <?php
                                                        echo e(
                                                            $uploader_name
                                                        );
                                                        ?>

                                                    </div>


                                                    <span
                                                        class="role-badge role-<?php echo e($uploader_role); ?>"
                                                    >

                                                        <?php
                                                        echo e(
                                                            ucfirst(
                                                                $uploader_role
                                                            )
                                                        );
                                                        ?>

                                                    </span>

                                                </div>

                                            </div>

                                        </td>


                                        <!-- NOTE -->

                                        <td>

                                            <div class="note-title">

                                                <?php
                                                echo e(
                                                    $note_title
                                                );
                                                ?>

                                            </div>


                                            <div class="note-subject">

                                                <?php
                                                echo e(
                                                    $note_subject
                                                );
                                                ?>

                                            </div>

                                        </td>


                                        <!-- DESCRIPTION / FILE -->

                                        <td>

                                            <div class="description-text">

                                                <?php

                                                if (
                                                    $note_description !== ''
                                                ) {

                                                    echo e(
                                                        $note_description
                                                    );

                                                } else {

                                                    echo
                                                        'No description provided.';
                                                }

                                                ?>

                                            </div>


                                            <?php if ($file_path !== ''): ?>

                                                <a
                                                    href="note_view.php?note_id=<?php echo (int)$note['id']; ?>"
                                                    class="file-link"
                                                    title="View this note before downloading"
                                                >

                                                    <svg
                                                        viewBox="0 0 24 24"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        stroke-width="2"
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                    >

                                                        <path
                                                            d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z"
                                                        ></path>

                                                        <circle
                                                            cx="12"
                                                            cy="12"
                                                            r="3"
                                                        ></circle>

                                                    </svg>

                                                    View File

                                                </a>

                                            <?php endif; ?>

                                        </td>


                                        <!-- STATUS -->

                                        <td>

                                            <span
                                                class="status-badge status-<?php echo e($note_status); ?>"
                                            >

                                                <?php
                                                echo e(
                                                    ucfirst(
                                                        $note_status
                                                    )
                                                );
                                                ?>

                                            </span>

                                        </td>


                                        <!-- DATE -->

                                        <td>

                                            <div class="date-text">

                                                <?php

                                                if (
                                                    !empty(
                                                        $note['created_at']
                                                    )
                                                ) {

                                                    echo e(
                                                        date(
                                                            'M d, Y',
                                                            strtotime(
                                                                $note['created_at']
                                                            )
                                                        )
                                                    );

                                                } else {

                                                    echo '—';
                                                }

                                                ?>

                                            </div>

                                        </td>


                                        <!-- ACTIONS -->

                                        <td>

                                            <div class="action-buttons">

                                                <?php
                                                if (
                                                    $note_status === 'pending'
                                                ):
                                                ?>

                                                    <form
                                                        method="POST"
                                                        class="action-form approve-form"
                                                    >

                                                        <input
                                                            type="hidden"
                                                            name="csrf_token"
                                                            value="<?php echo e($csrf_token); ?>"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="action"
                                                            value="approve"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="note_id"
                                                            value="<?php echo (int)$note['id']; ?>"
                                                        >

                                                        <button
                                                            type="submit"
                                                            class="action-btn approve-btn"
                                                        >

                                                            ✓ Approve

                                                        </button>

                                                    </form>

                                                <?php endif; ?>


                                                <!-- DELETE -->

                                                <form
                                                    method="POST"
                                                    class="action-form delete-form"
                                                >

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
                                                        name="note_id"
                                                        value="<?php echo (int)$note['id']; ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="action-btn delete-btn"
                                                    >

                                                        Delete

                                                    </button>

                                                </form>

                                            </div>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php else: ?>

                                <tr>

                                    <td colspan="6">

                                        <div class="empty-state">

                                            <div class="empty-icon">
                                                ▤
                                            </div>

                                            <div class="empty-title">
                                                No Notes Found
                                            </div>

                                            <div class="empty-text">
                                                There are no shared notes under this filter.
                                            </div>

                                        </div>

                                    </td>

                                </tr>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </main>

    </div>


    <!-- =====================================================
         JAVASCRIPT
    ====================================================== -->

    <script>

        // =================================================
        // AUTOMATIC THEME
        // =================================================

        function applyNoteSphereTheme() {

            const savedTheme =
                localStorage.getItem(
                    'notesphere_admin_theme'
                );

            document.body.classList.toggle(
                'dark-mode',
                savedTheme === 'dark'
            );
        }

        applyNoteSphereTheme();


        // =================================================
        // WATCH FOR THEME CHANGES
        // =================================================

        window.addEventListener(
            'storage',
            function (event) {

                if (
                    event.key ===
                    'notesphere_admin_theme'
                ) {

                    applyNoteSphereTheme();
                }
            }
        );


        // =================================================
        // APPROVE CONFIRMATION
        // =================================================

        const approveForms =
            document.querySelectorAll(
                '.approve-form'
            );


        approveForms.forEach(
            function (form) {

                form.addEventListener(
                    'submit',
                    function (event) {

                        event.preventDefault();

                        Swal.fire({

                            title:
                                'Approve this note?',

                            text:
                                'Once approved, the note can be made available to users.',

                            icon:
                                'question',

                            showCancelButton:
                                true,

                            confirmButtonText:
                                'Yes, approve it',

                            cancelButtonText:
                                'Cancel',

                            reverseButtons:
                                true,

                            confirmButtonColor:
                                '#16a34a',

                            cancelButtonColor:
                                '#6b7280'

                        }).then(
                            function (result) {

                                if (
                                    result.isConfirmed
                                ) {

                                    form.submit();
                                }

                            }
                        );

                    }
                );

            }
        );


        // =================================================
        // DELETE CONFIRMATION
        // =================================================

        const deleteForms =
            document.querySelectorAll(
                '.delete-form'
            );


        deleteForms.forEach(
            function (form) {

                form.addEventListener(
                    'submit',
                    function (event) {

                        event.preventDefault();

                        Swal.fire({

                            title:
                                'Delete this note?',

                            text:
                                'This action cannot be undone.',

                            icon:
                                'warning',

                            showCancelButton:
                                true,

                            confirmButtonText:
                                'Yes, delete it',

                            cancelButtonText:
                                'Cancel',

                            reverseButtons:
                                true,

                            confirmButtonColor:
                                '#dc2626',

                            cancelButtonColor:
                                '#6b7280'

                        }).then(
                            function (result) {

                                if (
                                    result.isConfirmed
                                ) {

                                    form.submit();
                                }

                            }
                        );

                    }
                );

            }
        );


        // =================================================
        // PHP SUCCESS MESSAGE
        // =================================================

        <?php if (!empty($message)): ?>

        Swal.fire({

            icon:
                'success',

            title:
                'Success!',

            text:
                <?php echo json_encode($message); ?>,

            confirmButtonColor:
                '#233961'

        });

        <?php endif; ?>


        // =================================================
        // PHP ERROR MESSAGE
        // =================================================

        <?php if (!empty($error)): ?>

        Swal.fire({

            icon:
                'error',

            title:
                'Something went wrong',

            text:
                <?php echo json_encode($error); ?>,

            confirmButtonColor:
                '#dc2626'

        });

        <?php endif; ?>

    </script>

</body>

</html>