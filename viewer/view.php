<?php

// =========================================================
// NoteSphere - File Viewer
// Admin Theme
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
// GET FILE INFORMATION
//
// Supports:
// view.php?file=filename.docx
// view.php?path=uploads/filename.docx
// view.php?id=NOTE_ID
// =========================================================

$request_file = isset($_GET['file'])
    ? trim((string) $_GET['file'])
    : '';

$request_path = isset($_GET['path'])
    ? trim((string) $_GET['path'])
    : '';

$request_id = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

// =========================================================
// BASE DIRECTORIES
// =========================================================

$project_root =
    realpath(__DIR__ . '/..');

$search_directories = [

    $project_root . DIRECTORY_SEPARATOR . 'uploads',

    $project_root . DIRECTORY_SEPARATOR . 'assets' .
    DIRECTORY_SEPARATOR . 'uploads',

    $project_root . DIRECTORY_SEPARATOR . 'storage',

    $project_root . DIRECTORY_SEPARATOR . 'files'

];

// =========================================================
// FIND FILE FROM DATABASE BY NOTE ID
// =========================================================

$database_file_path = '';

if ($request_id > 0) {

    try {

        $stmt = $conn->prepare("
            SELECT *
            FROM notes
            WHERE note_id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $request_id
        ]);

        $note_data =
            $stmt->fetch(PDO::FETCH_ASSOC);

        if ($note_data) {

            $possible_columns = [

                'file_path',
                'filepath',
                'file',
                'file_url',
                'filename',
                'file_name'

            ];

            foreach (
                $possible_columns as $column
            ) {

                if (
                    isset($note_data[$column]) &&
                    trim((string) $note_data[$column]) !== ''
                ) {

                    $database_file_path =
                        trim(
                            (string)
                            $note_data[$column]
                        );

                    break;
                }
            }
        }

    } catch (PDOException $e) {

        $database_file_path = '';
    }
}

// =========================================================
// FILE SEARCH FUNCTION
// =========================================================

function findActualFile(
    $requestedPath,
    $searchDirectories,
    $projectRoot
) {

    if (
        !is_string($requestedPath) ||
        trim($requestedPath) === ''
    ) {
        return false;
    }

    $requestedPath =
        trim($requestedPath);

    // =====================================================
    // NORMALIZE SLASHES
    // =====================================================

    $requestedPath =
        str_replace(
            ['/', '\\'],
            DIRECTORY_SEPARATOR,
            $requestedPath
        );

    // =====================================================
    // REMOVE QUERY / URL PARTS
    // =====================================================

    $requestedPath =
        preg_replace(
            '/[?#].*$/',
            '',
            $requestedPath
        );

    // =====================================================
    // IF ABSOLUTE PATH
    // =====================================================

    if (
        preg_match(
            '/^[A-Za-z]:\\\\/',
            $requestedPath
        )
    ) {

        $real =
            realpath($requestedPath);

        if (
            $real &&
            is_file($real)
        ) {

            $realRoot =
                realpath($projectRoot);

            if (
                $realRoot &&
                strpos(
                    strtolower($real),
                    strtolower($realRoot)
                ) === 0
            ) {

                return $real;
            }
        }
    }

    // =====================================================
    // CLEAN PROJECT-RELATIVE PATH
    // =====================================================

    $cleanPath =
        ltrim(
            $requestedPath,
            DIRECTORY_SEPARATOR
        );

    // Prevent ../ traversal
    while (
        strpos(
            $cleanPath,
            '..' . DIRECTORY_SEPARATOR
        ) === 0
    ) {

        $cleanPath =
            substr(
                $cleanPath,
                3
            );
    }

    // =====================================================
    // TRY DIRECT PROJECT PATH
    // =====================================================

    $directPath =
        $projectRoot .
        DIRECTORY_SEPARATOR .
        $cleanPath;

    $realDirect =
        realpath($directPath);

    if (
        $realDirect &&
        is_file($realDirect)
    ) {

        $realRoot =
            realpath($projectRoot);

        if (
            $realRoot &&
            strpos(
                strtolower($realDirect),
                strtolower($realRoot)
            ) === 0
        ) {

            return $realDirect;
        }
    }

    // =====================================================
    // TRY BASENAME IN ALL STORAGE DIRECTORIES
    // =====================================================

    $basename =
        basename($cleanPath);

    foreach (
        $searchDirectories as $directory
    ) {

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

            foreach (
                $iterator as $file
            ) {

                if (!$file->isFile()) {
                    continue;
                }

                if (
                    strtolower(
                        $file->getFilename()
                    ) !== strtolower($basename)
                ) {
                    continue;
                }

                $real =
                    $file->getRealPath();

                if ($real) {
                    return $real;
                }
            }

        } catch (Exception $e) {

            continue;
        }
    }

    return false;
}

// =========================================================
// DETERMINE REQUESTED FILE
// =========================================================

$file_to_find = '';

if ($database_file_path !== '') {

    $file_to_find =
        $database_file_path;

} elseif ($request_path !== '') {

    $file_to_find =
        $request_path;

} elseif ($request_file !== '') {

    $file_to_find =
        $request_file;
}

// =========================================================
// FIND ACTUAL FILE
// =========================================================

$actual_file =
    findActualFile(
        $file_to_find,
        $search_directories,
        $project_root
    );

// =========================================================
// FILE INFORMATION
// =========================================================

$file_exists =
    ($actual_file !== false);

$file_name =
    $file_exists
        ? basename($actual_file)
        : (
            $file_to_find !== ''
                ? basename($file_to_find)
                : 'File Viewer'
        );

$file_extension =
    strtolower(
        pathinfo(
            $file_name,
            PATHINFO_EXTENSION
        )
    );

// =========================================================
// MIME TYPES
// =========================================================

$mime_types = [

    'pdf' =>
        'application/pdf',

    'jpg' =>
        'image/jpeg',

    'jpeg' =>
        'image/jpeg',

    'png' =>
        'image/png',

    'gif' =>
        'image/gif',

    'webp' =>
        'image/webp',

    'svg' =>
        'image/svg+xml',

    'txt' =>
        'text/plain',

    'html' =>
        'text/html',

    'htm' =>
        'text/html',

    'mp4' =>
        'video/mp4',

    'webm' =>
        'video/webm',

    'mp3' =>
        'audio/mpeg'

];

$mime_type =
    $mime_types[$file_extension]
    ?? 'application/octet-stream';

// =========================================================
// LIBREOFFICE PREVIEW
// =========================================================
//
// DOC/DOCX
// XLS/XLSX
// PPT/PPTX
//
// LibreOffice converts them into PDF.
// =========================================================

$office_extensions = [

    'doc',
    'docx',
    'xls',
    'xlsx',
    'ppt',
    'pptx',
    'odt',
    'ods',
    'odp'

];

$is_office_file =
    in_array(
        $file_extension,
        $office_extensions,
        true
    );

// =========================================================
// PDF PREVIEW PATH
// =========================================================

$preview_file =
    $actual_file;

$preview_is_temporary =
    false;

$preview_error = '';

// =========================================================
// CONVERT OFFICE FILE TO PDF
// =========================================================

if (
    $file_exists &&
    $is_office_file
) {

    $libreoffice_paths = [

        'C:\\Program Files\\LibreOffice\\program\\soffice.exe',

        'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe'

    ];

    $soffice =
        '';

    foreach (
        $libreoffice_paths as $possible_soffice
    ) {

        if (
            is_file(
                $possible_soffice
            )
        ) {

            $soffice =
                $possible_soffice;

            break;
        }
    }

    if ($soffice === '') {

        $preview_error =
            'LibreOffice is not installed. Please install LibreOffice on the computer running XAMPP.';

    } else {

        // =================================================
        // CACHE DIRECTORY
        // =================================================

        $preview_directory =
            $project_root .
            DIRECTORY_SEPARATOR .
            'storage' .
            DIRECTORY_SEPARATOR .
            'preview';

        if (
            !is_dir(
                $preview_directory
            )
        ) {

            @mkdir(
                $preview_directory,
                0777,
                true
            );
        }

        if (
            is_dir(
                $preview_directory
            ) &&
            is_writable(
                $preview_directory
            )
        ) {

            // =============================================
            // CREATE UNIQUE CACHE NAME
            // =============================================

            $file_hash =
                md5(
                    $actual_file .
                    '|' .
                    filemtime(
                        $actual_file
                    ) .
                    '|' .
                    filesize(
                        $actual_file
                    )
                );

            $cached_pdf =
                $preview_directory .
                DIRECTORY_SEPARATOR .
                $file_hash .
                '.pdf';

            // =============================================
            // USE EXISTING CACHE
            // =============================================

            if (
                is_file(
                    $cached_pdf
                ) &&
                filesize(
                    $cached_pdf
                ) > 0
            ) {

                $preview_file =
                    $cached_pdf;

                $preview_is_temporary =
                    true;

            } else {

                // =========================================
                // CREATE TEMP INPUT COPY
                //
                // This avoids issues with spaces/special
                // characters in original paths.
                // =========================================

                $temp_input =
                    $preview_directory .
                    DIRECTORY_SEPARATOR .
                    $file_hash .
                    '_input.' .
                    $file_extension;

                if (
                    !is_file(
                        $temp_input
                    )
                ) {

                    @copy(
                        $actual_file,
                        $temp_input
                    );
                }

                // =========================================
                // LIBREOFFICE COMMAND
                // =========================================

                $command =
                    '"' .
                    $soffice .
                    '" --headless --convert-to pdf --outdir "' .
                    $preview_directory .
                    '" "' .
                    $temp_input .
                    '" 2>&1';

                @exec(
                    $command,
                    $command_output,
                    $command_status
                );

                // =========================================
                // LIBREOFFICE USES INPUT BASENAME
                // =========================================

                $generated_pdf =
                    $preview_directory .
                    DIRECTORY_SEPARATOR .
                    pathinfo(
                        $temp_input,
                        PATHINFO_FILENAME
                    ) .
                    '.pdf';

                if (
                    is_file(
                        $generated_pdf
                    ) &&
                    filesize(
                        $generated_pdf
                    ) > 0
                ) {

                    // =====================================
                    // MOVE TO CLEAN CACHE NAME
                    // =====================================

                    if (
                        $generated_pdf !==
                        $cached_pdf
                    ) {

                        @rename(
                            $generated_pdf,
                            $cached_pdf
                        );
                    }

                    if (
                        is_file(
                            $cached_pdf
                        )
                    ) {

                        $preview_file =
                            $cached_pdf;

                        $preview_is_temporary =
                            true;
                    }

                }

                // =========================================
                // REMOVE TEMP INPUT
                // =========================================

                if (
                    is_file(
                        $temp_input
                    )
                ) {

                    @unlink(
                        $temp_input
                    );
                }

                // =========================================
                // CHECK RESULT
                // =========================================

                if (
                    !$preview_is_temporary
                ) {

                    $preview_error =
                        'LibreOffice could not create a PDF preview for this file.';
                }
            }

        } else {

            $preview_error =
                'Preview storage folder is not writable.';
        }
    }
}

// =========================================================
// FILE SIZE
// =========================================================

$file_size_display =
    '';

if (
    $file_exists &&
    is_file($actual_file)
) {

    $file_size =
        filesize($actual_file);

    if ($file_size < 1024) {

        $file_size_display =
            $file_size . ' Bytes';

    } elseif ($file_size < 1024 * 1024) {

        $file_size_display =
            number_format(
                $file_size / 1024,
                2
            ) . ' KB';

    } elseif ($file_size < 1024 * 1024 * 1024) {

        $file_size_display =
            number_format(
                $file_size /
                (1024 * 1024),
                2
            ) . ' MB';

    } else {

        $file_size_display =
            number_format(
                $file_size /
                (1024 * 1024 * 1024),
                2
            ) . ' GB';
    }
}

// =========================================================
// PREVIEW TYPE
// =========================================================

$preview_type =
    'unsupported';

if ($file_exists) {

    if (
        $is_office_file &&
        $preview_is_temporary
    ) {

        $preview_type =
            'pdf';

    } elseif (
        $file_extension === 'pdf'
    ) {

        $preview_type =
            'pdf';

    } elseif (
        in_array(
            $file_extension,
            [
                'jpg',
                'jpeg',
                'png',
                'gif',
                'webp',
                'svg'
            ],
            true
        )
    ) {

        $preview_type =
            'image';

    } elseif (
        in_array(
            $file_extension,
            [
                'mp4',
                'webm'
            ],
            true
        )
    ) {

        $preview_type =
            'video';

    } elseif (
        in_array(
            $file_extension,
            [
                'mp3'
            ],
            true
        )
    ) {

        $preview_type =
            'audio';

    } elseif (
        in_array(
            $file_extension,
            [
                'txt',
                'html',
                'htm'
            ],
            true
        )
    ) {

        $preview_type =
            'text';
    }
}

// =========================================================
// PDF PREVIEW URL
// =========================================================
//
// We use a local PHP endpoint instead of exposing the
// physical filesystem path.
// =========================================================

$pdf_url = '';

if (
    $preview_type === 'pdf' &&
    $preview_file &&
    is_file($preview_file)
) {

    $preview_token =
        hash(
            'sha256',
            $preview_file .
            '|' .
            session_id()
        );

    $_SESSION[
        'notesphere_preview_' .
        $preview_token
    ] = $preview_file;

    $pdf_url =
        'serve.php?token=' .
        urlencode(
            $preview_token
        );
}

// =========================================================
// BACK URL
// =========================================================

$back_url =
    '../admin/manage_notes.php';

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
        NoteSphere - File Viewer
    </title>

    <script>

        (function () {

            const savedTheme =
                localStorage.getItem(
                    'notesphere_admin_theme'
                );

            if (
                savedTheme === 'dark'
            ) {

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

            --danger: #dc2626;

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

            --shadow:
                0 8px 25px
                rgba(0, 0, 0, 0.25);
        }

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

            overflow: hidden;
        }

        /* =====================================================
           TOPBAR
        ====================================================== */

        .viewer-topbar {

            height: 76px;

            background:
                var(--card);

            border-bottom:
                1px solid var(--border);

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding:
                0 28px;

            position: relative;

            z-index: 20;
        }

        .viewer-brand {

            display: flex;

            align-items: center;

            gap: 12px;
        }

        .brand-icon {

            width: 42px;

            height: 42px;

            border-radius: 12px;

            background:
                var(--primary);

            color: #ffffff;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 11px;

            font-weight: 800;

            letter-spacing: .4px;
        }

        .brand-text {

            font-size: 20px;

            font-weight: 700;

            color:
                var(--primary);
        }

        html.dark-mode
        .brand-text {

            color: #ffffff;
        }

        .viewer-title-area {

            position: absolute;

            left: 50%;

            transform:
                translateX(-50%);

            text-align: center;

            max-width: 48%;

            pointer-events: none;
        }

        .viewer-title {

            font-size: 15px;

            font-weight: 700;

            color:
                var(--text);

            white-space: nowrap;

            overflow: hidden;

            text-overflow: ellipsis;
        }

        .viewer-subtitle {

            font-size: 10px;

            color:
                var(--muted);

            margin-top: 3px;
        }

        .viewer-actions {

            display: flex;

            align-items: center;

            gap: 9px;
        }

        .theme-button {

            width: 38px;

            height: 38px;

            border:
                1px solid var(--border);

            background:
                var(--card);

            color:
                var(--text);

            border-radius: 10px;

            cursor: pointer;

            font-size: 17px;

            display: flex;

            align-items: center;

            justify-content: center;

            transition: .2s ease;
        }

        .theme-button:hover {

            background:
                rgba(61, 139, 255, .08);
        }

        .close-button {

            width: 40px;

            height: 40px;

            border: none;

            border-radius: 10px;

            background:
                rgba(220, 38, 38, .09);

            color:
                var(--danger);

            cursor: pointer;

            display: flex;

            align-items: center;

            justify-content: center;

            transition: .2s ease;
        }

        .close-button:hover {

            background:
                var(--danger);

            color: #ffffff;

            transform:
                translateY(-1px);
        }

        .close-button svg {

            width: 19px;

            height: 19px;

            stroke: currentColor;
        }

        /* =====================================================
           VIEWER AREA
        ====================================================== */

        .viewer-page {

            height:
                calc(100vh - 76px);

            padding:
                22px 28px;

            display: flex;

            flex-direction: column;

            gap: 15px;
        }

        .file-information {

            background:
                var(--card);

            border:
                1px solid var(--border);

            border-radius: 13px;

            padding:
                12px 16px;

            box-shadow:
                var(--shadow);

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 15px;

            flex-shrink: 0;
        }

        .file-name {

            display: flex;

            align-items: center;

            gap: 11px;

            min-width: 0;
        }

        .file-icon {

            width: 37px;

            height: 37px;

            border-radius: 9px;

            background:
                rgba(61, 139, 255, .1);

            color:
                var(--primary-light);

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 17px;

            flex-shrink: 0;
        }

        .file-name-text {

            min-width: 0;
        }

        .file-name-main {

            font-size: 13px;

            font-weight: 700;

            color:
                var(--text);

            overflow: hidden;

            text-overflow: ellipsis;

            white-space: nowrap;
        }

        .file-meta {

            font-size: 10px;

            color:
                var(--muted);

            margin-top: 3px;
        }

        .viewer-content {

            flex: 1;

            min-height: 0;

            background:
                var(--card);

            border:
                1px solid var(--border);

            border-radius: 15px;

            box-shadow:
                var(--shadow);

            overflow: hidden;

            position: relative;
        }

        .pdf-frame {

            width: 100%;

            height: 100%;

            border: none;

            display: block;

            background: #ffffff;
        }

        .image-preview {

            width: 100%;

            height: 100%;

            object-fit: contain;

            padding: 20px;
        }

        .video-preview {

            width: 100%;

            height: 100%;

            object-fit: contain;

            background: #000000;
        }

        .audio-wrapper {

            width: 100%;

            height: 100%;

            display: flex;

            align-items: center;

            justify-content: center;

            padding: 30px;
        }

        .audio-wrapper audio {

            width: min(600px, 90%);

        }

        .text-preview {

            width: 100%;

            height: 100%;

            padding: 25px;

            overflow: auto;

            white-space: pre-wrap;

            font-family:
                Consolas,
                monospace;

            font-size: 13px;

            line-height: 1.6;

            color:
                var(--text);
        }

        .empty-state {

            width: 100%;

            height: 100%;

            display: flex;

            flex-direction: column;

            align-items: center;

            justify-content: center;

            text-align: center;

            padding: 30px;
        }

        .empty-icon {

            width: 68px;

            height: 68px;

            border-radius: 18px;

            background:
                rgba(220, 38, 38, .09);

            color:
                var(--danger);

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 30px;

            margin-bottom: 17px;
        }

        .empty-state h2 {

            font-size: 18px;

            color:
                var(--text);

            margin-bottom: 7px;
        }

        .empty-state p {

            font-size: 12px;

            line-height: 1.6;

            color:
                var(--muted);

            max-width: 550px;
        }

        .empty-file {

            margin-top: 12px;

            padding:
                9px 13px;

            background:
                rgba(61, 139, 255, .08);

            color:
                var(--primary-light);

            border-radius: 8px;

            font-size: 11px;

            max-width: 90%;

            overflow-wrap: anywhere;
        }

        /* =====================================================
           MOBILE
        ====================================================== */

        @media (max-width: 768px) {

            .viewer-topbar {

                height: 65px;

                padding:
                    0 16px;
            }

            .brand-icon {

                width: 36px;

                height: 36px;
            }

            .brand-text {

                font-size: 17px;
            }

            .viewer-title-area {

                display: none;
            }

            .viewer-page {

                height:
                    calc(100vh - 65px);

                padding:
                    14px;

                gap: 10px;
            }

            .file-information {

                padding:
                    10px 12px;
            }

            .file-icon {

                width: 34px;

                height: 34px;
            }

            .file-name-main {

                max-width: 190px;
            }

        }

    </style>

</head>

<body>

<!-- =========================================================
     TOPBAR
========================================================== -->

<header class="viewer-topbar">

    <div class="viewer-brand">

        <div class="brand-icon">
            CDM
        </div>

        <div class="brand-text">
            NoteSphere
        </div>

    </div>

    <div class="viewer-title-area">

        <div class="viewer-title">
            <?php echo e($file_name); ?>
        </div>

        <div class="viewer-subtitle">
            File Viewer
        </div>

    </div>

    <div class="viewer-actions">

        <!-- THEME -->

        <button
            type="button"
            class="theme-button"
            id="themeButton"
            title="Toggle theme"
        >
            ☾
        </button>

        <!-- CLOSE / BACK -->

        <button
            type="button"
            class="close-button"
            id="closeViewer"
            title="Back to Manage Notes"
        >

            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
            >

                <path
                    d="M6 6l12 12"
                ></path>

                <path
                    d="M18 6L6 18"
                ></path>

            </svg>

        </button>

    </div>

</header>

<!-- =========================================================
     VIEWER PAGE
========================================================== -->

<main class="viewer-page">

    <?php if ($file_exists): ?>

        <!-- FILE INFORMATION -->

        <div class="file-information">

            <div class="file-name">

                <div class="file-icon">

                    <?php

                    if (
                        in_array(
                            $file_extension,
                            [
                                'doc',
                                'docx'
                            ],
                            true
                        )
                    ) {

                        echo '📄';

                    } elseif (
                        in_array(
                            $file_extension,
                            [
                                'xls',
                                'xlsx'
                            ],
                            true
                        )
                    ) {

                        echo '📊';

                    } elseif (
                        in_array(
                            $file_extension,
                            [
                                'ppt',
                                'pptx'
                            ],
                            true
                        )
                    ) {

                        echo '📑';

                    } elseif (
                        $file_extension === 'pdf'
                    ) {

                        echo '📕';

                    } elseif (
                        in_array(
                            $file_extension,
                            [
                                'jpg',
                                'jpeg',
                                'png',
                                'gif',
                                'webp'
                            ],
                            true
                        )
                    ) {

                        echo '🖼️';

                    } else {

                        echo '📁';
                    }

                    ?>

                </div>

                <div class="file-name-text">

                    <div class="file-name-main">

                        <?php
                        echo e(
                            $file_name
                        );
                        ?>

                    </div>

                    <div class="file-meta">

                        <?php
                        echo strtoupper(
                            $file_extension
                        );
                        ?>

                        <?php if ($file_size_display !== ''): ?>

                            ·

                            <?php
                            echo e(
                                $file_size_display
                            );
                            ?>

                        <?php endif; ?>

                    </div>

                </div>

            </div>

        </div>

        <!-- =================================================
             VIEWER
        ================================================== -->

        <div class="viewer-content">

            <?php if (
                $preview_type === 'pdf' &&
                $pdf_url !== ''
            ): ?>

                <iframe
                    class="pdf-frame"
                    src="<?php echo e($pdf_url); ?>"
                    title="File Preview"
                ></iframe>

            <?php elseif (
                $preview_type === 'image'
            ): ?>

                <img
                    class="image-preview"
                    src="serve.php?token=<?php
                        echo e(
                            $preview_token
                        );
                    ?>"
                    alt="<?php
                        echo e(
                            $file_name
                        );
                    ?>"
                >

            <?php elseif (
                $preview_type === 'video'
            ): ?>

                <video
                    class="video-preview"
                    controls
                >

                    <source
                        src="serve.php?token=<?php
                            echo e(
                                $preview_token
                            );
                        ?>"
                        type="<?php
                            echo e(
                                $mime_type
                            );
                        ?>"
                    >

                    Your browser does not support
                    video playback.

                </video>

            <?php elseif (
                $preview_type === 'audio'
            ): ?>

                <div class="audio-wrapper">

                    <audio
                        controls
                    >

                        <source
                            src="serve.php?token=<?php
                                echo e(
                                    $preview_token
                                );
                            ?>"
                            type="<?php
                                echo e(
                                    $mime_type
                                );
                            ?>"
                        >

                        Your browser does not support
                        audio playback.

                    </audio>

                </div>

            <?php elseif (
                $preview_type === 'text'
            ): ?>

                <iframe
                    class="pdf-frame"
                    src="serve.php?token=<?php
                        echo e(
                            $preview_token
                        );
                    ?>"
                    title="Text Preview"
                ></iframe>

            <?php elseif (
                $is_office_file &&
                $preview_error !== ''
            ): ?>

                <div class="empty-state">

                    <div class="empty-icon">
                        ⚠
                    </div>

                    <h2>
                        Preview Setup Required
                    </h2>

                    <p>

                        <?php
                        echo e(
                            $preview_error
                        );
                        ?>

                    </p>

                    <div class="empty-file">

                        <?php
                        echo e(
                            $file_name
                        );
                        ?>

                    </div>

                </div>

            <?php else: ?>

                <div class="empty-state">

                    <div class="empty-icon">
                        📁
                    </div>

                    <h2>
                        Preview Not Available
                    </h2>

                    <p>

                        This file type cannot be
                        previewed directly inside
                        NoteSphere.

                    </p>

                    <div class="empty-file">

                        <?php
                        echo e(
                            $file_name
                        );
                        ?>

                    </div>

                </div>

            <?php endif; ?>

        </div>

    <?php else: ?>

        <!-- =================================================
             FILE NOT FOUND
        ================================================== -->

        <div class="viewer-content">

            <div class="empty-state">

                <div class="empty-icon">
                    ×
                </div>

                <h2>
                    File Not Found
                </h2>

                <p>

                    NoteSphere could not locate
                    the requested file in the
                    configured storage folders.

                </p>

                <?php if ($file_to_find !== ''): ?>

                    <div class="empty-file">

                        <?php
                        echo e(
                            $file_to_find
                        );
                        ?>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    <?php endif; ?>

</main>

<script>

// ========================================================
// THEME
// ========================================================

const themeButton =
    document.getElementById(
        'themeButton'
    );

function updateThemeIcon() {

    const isDark =
        document.documentElement.classList.contains(
            'dark-mode'
        );

    if (themeButton) {

        themeButton.textContent =
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

    updateThemeIcon();
}

if (themeButton) {

    themeButton.addEventListener(
        'click',
        toggleTheme
    );
}

updateThemeIcon();

// ========================================================
// CLOSE / BACK
// ========================================================

const closeViewer =
    document.getElementById(
        'closeViewer'
    );

if (closeViewer) {

    closeViewer.addEventListener(
        'click',
        function () {

            window.location.href =
                <?php
                echo json_encode(
                    $back_url
                );
                ?>;

        }
    );
}

</script>

</body>

</html>