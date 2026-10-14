<?php

// =========================================================
// NoteSphere - Admin Note Viewer
// FULL PREVIEW + SECURE FILE STREAM
// =========================================================

session_start();

require_once __DIR__ . '/../includes/db_config.php';

/** @var PDO $conn */

// =========================================================
// SECURITY CHECK
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
// DATABASE
// =========================================================

$pdo = $conn;

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
// ERROR PAGE
// =========================================================

function showErrorPage($title, $message)
{
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
            NoteSphere - <?php echo e($title); ?>
        </title>

        <link
            href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
            rel="stylesheet"
        >

        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

        <style>

            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
                font-family:
                    'Plus Jakarta Sans',
                    sans-serif;
            }

            body {

                min-height: 100vh;

                background:
                    #0b1220;

                color:
                    #f8fafc;

                display:
                    flex;

                align-items:
                    center;

                justify-content:
                    center;

                padding:
                    20px;

                position:
                    relative;

                overflow:
                    hidden;
            }

            body::before {

                content: '';

                position: fixed;

                inset: 0;

                background:
                    radial-gradient(
                        circle at top right,
                        rgba(61,139,255,0.12),
                        transparent 35%
                    ),
                    radial-gradient(
                        circle at bottom left,
                        rgba(57,255,20,0.06),
                        transparent 35%
                    );

                pointer-events:
                    none;
            }

            .close-btn {

                position:
                    fixed;

                top:
                    20px;

                left:
                    20px;

                width:
                    44px;

                height:
                    44px;

                border:
                    1px solid #26344b;

                border-radius:
                    12px;

                background:
                    #111827;

                color:
                    #ffffff;

                display:
                    flex;

                align-items:
                    center;

                justify-content:
                    center;

                text-decoration:
                    none;

                font-size:
                    25px;

                z-index:
                    10;

                transition:
                    0.2s;
            }

            .close-btn:hover {

                color:
                    #3D8BFF;

                border-color:
                    #3D8BFF;

                transform:
                    translateY(-1px);
            }

            .error-card {

                width:
                    min(100%, 520px);

                background:
                    rgba(17,24,39,0.96);

                border:
                    1px solid #26344b;

                border-radius:
                    20px;

                padding:
                    34px 28px;

                text-align:
                    center;

                box-shadow:
                    0 25px 70px rgba(0,0,0,0.35);

                position:
                    relative;

                z-index:
                    2;
            }

            .error-icon {

                width:
                    64px;

                height:
                    64px;

                margin:
                    0 auto 18px;

                border-radius:
                    18px;

                display:
                    flex;

                align-items:
                    center;

                justify-content:
                    center;

                background:
                    rgba(220,38,38,0.10);

                color:
                    #ef4444;

                font-size:
                    28px;

                font-weight:
                    800;
            }

            h1 {

                font-size:
                    21px;

                font-weight:
                    800;

                margin-bottom:
                    9px;
            }

            p {

                color:
                    #94a3b8;

                font-size:
                    12px;

                line-height:
                    1.7;
            }

            .home-link {

                display:
                    inline-flex;

                margin-top:
                    22px;

                padding:
                    11px 17px;

                border-radius:
                    9px;

                background:
                    #233961;

                color:
                    #ffffff;

                text-decoration:
                    none;

                font-size:
                    11px;

                font-weight:
                    800;

                transition:
                    0.2s;
            }

            .home-link:hover {

                background:
                    #3D8BFF;
            }

            @media (max-width: 600px) {

                .close-btn {

                    top:
                        14px;

                    left:
                        14px;

                    width:
                        40px;

                    height:
                        40px;
                }

                .error-card {

                    padding:
                        28px 20px;
                }

                h1 {

                    font-size:
                        18px;
                }
            }

        </style>

    </head>

    <body>

        <a
            href="manage_notes.php"
            class="close-btn"
            aria-label="Back to Manage Notes"
            title="Back to Manage Notes"
        >
            ×
        </a>

        <div class="error-card">

            <div class="error-icon">
                !
            </div>

            <h1>
                <?php echo e($title); ?>
            </h1>

            <p>
                <?php echo e($message); ?>
            </p>

            <a
                href="manage_notes.php"
                class="home-link"
            >
                Back to Manage Notes
            </a>

        </div>

    </body>

    </html>
    <?php

    exit();
}

// =========================================================
// GET NOTE ID
// =========================================================

$note_id = isset($_GET['note_id'])
    ? (int)$_GET['note_id']
    : 0;

if ($note_id <= 0) {

    showErrorPage(
        'Invalid Note',
        'The note ID provided is invalid.'
    );
}

// =========================================================
// FETCH NOTE
// =========================================================

try {

    $stmt = $pdo->prepare("
        SELECT
            n.id,
            n.title,
            n.description,
            n.file_path,
            n.thumbnail,
            n.institute,
            n.status,
            n.user_id,
            n.created_at,
            u.full_name AS uploader

        FROM notes n

        LEFT JOIN users u
            ON n.user_id = u.user_id

        WHERE n.id = ?

        LIMIT 1
    ");

    $stmt->execute([
        $note_id
    ]);

    $note = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    showErrorPage(
        'Database Error',
        'The note could not be loaded.'
    );
}

// =========================================================
// NOTE EXISTS
// =========================================================

if (!$note) {

    showErrorPage(
        'Note Not Found',
        'The requested note does not exist.'
    );
}

// =========================================================
// FILE PATH
// =========================================================

$stored_path =
    trim(
        (string)($note['file_path'] ?? '')
    );

if ($stored_path === '') {

    showErrorPage(
        'File Not Available',
        'This note does not have an uploaded file.'
    );
}

// =========================================================
// NORMALIZE FILE PATH
// =========================================================

$clean_path = $stored_path;

// Remove query string
$clean_path = preg_replace(
    '/[?#].*$/',
    '',
    $clean_path
);

// Decode URL encoding
$clean_path = urldecode(
    $clean_path
);

// Normalize slash
$clean_path = str_replace(
    '\\',
    '/',
    $clean_path
);

// =========================================================
// GET BASENAME
// =========================================================

$file_basename =
    basename(
        parse_url(
            $clean_path,
            PHP_URL_PATH
        ) ?: $clean_path
    );

// =========================================================
// FIND FILE
// =========================================================

function findNoteFile($stored_path)
{
    $project_root =
        realpath(
            __DIR__ . '/..'
        );

    if (!$project_root) {
        return false;
    }

    $clean =
        preg_replace(
            '/[?#].*$/',
            '',
            $stored_path
        );

    $clean =
        urldecode(
            $clean
        );

    $clean =
        str_replace(
            '\\',
            '/',
            $clean
        );

    $clean =
        ltrim(
            $clean,
            '/'
        );

    // -----------------------------------------------------
    // REMOVE COMMON URL PREFIXES
    // -----------------------------------------------------

    $clean =
        preg_replace(
            '#^https?://[^/]+/#i',
            '',
            $clean
        );

    $clean =
        preg_replace(
            '#^NoteSphere/#i',
            '',
            $clean
        );

    // -----------------------------------------------------
    // POSSIBLE DIRECT PATHS
    // -----------------------------------------------------

    $possible_paths = [];

    $possible_paths[] =
        $project_root . '/' . $clean;

    $possible_paths[] =
        $project_root . '/' .
        ltrim($clean, '/');

    // uploads/filename
    if (
        stripos(
            $clean,
            'uploads/'
        ) === 0
    ) {

        $possible_paths[] =
            $project_root . '/' . $clean;
    }

    // basename only
    $basename =
        basename($clean);

    if ($basename !== '') {

        $possible_paths[] =
            $project_root .
            '/uploads/' .
            $basename;

        $possible_paths[] =
            $project_root .
            '/uploads/notes/' .
            $basename;

        $possible_paths[] =
            $project_root .
            '/uploads/submissions/' .
            $basename;

        $possible_paths[] =
            $project_root .
            '/uploads/messages/' .
            $basename;

        $possible_paths[] =
            $project_root .
            '/uploads/announcements/' .
            $basename;

        $possible_paths[] =
            $project_root .
            '/uploads/files/' .
            $basename;

        $possible_paths[] =
            $project_root .
            '/uploads/documents/' .
            $basename;

        $possible_paths[] =
            $project_root .
            '/storage/' .
            $basename;

        $possible_paths[] =
            $project_root .
            '/files/' .
            $basename;

        $possible_paths[] =
            $project_root .
            '/assets/uploads/' .
            $basename;
    }

    // -----------------------------------------------------
    // CHECK DIRECT PATHS
    // -----------------------------------------------------

    foreach (
        $possible_paths
        as $possible
    ) {

        $real =
            realpath($possible);

        if (
            $real !== false &&
            is_file($real)
        ) {

            return $real;
        }
    }

    // -----------------------------------------------------
    // RECURSIVE SEARCH
    // -----------------------------------------------------

    if (
        $basename !== '' &&
        is_dir($project_root)
    ) {

        $folders = [

            $project_root . '/uploads',

            $project_root . '/storage',

            $project_root . '/files',

            $project_root . '/assets/uploads'
        ];

        foreach (
            $folders
            as $folder
        ) {

            if (
                !is_dir($folder)
            ) {
                continue;
            }

            try {

                $iterator =
                    new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator(
                            $folder,
                            FilesystemIterator::SKIP_DOTS
                        )
                    );

                foreach (
                    $iterator
                    as $file
                ) {

                    if (
                        !$file->isFile()
                    ) {
                        continue;
                    }

                    if (
                        strcasecmp(
                            $file->getFilename(),
                            $basename
                        ) === 0
                    ) {

                        return
                            $file->getRealPath();
                    }
                }

            } catch (Throwable $e) {

                // Continue searching
            }
        }
    }

    return false;
}

// =========================================================
// LOCATE ACTUAL FILE
// =========================================================

$actual_file =
    findNoteFile(
        $stored_path
    );

if (
    $actual_file === false ||
    !is_file($actual_file)
) {

    showErrorPage(
        'File Not Found',
        'The uploaded file for this note is not available on the server.'
    );
}

// =========================================================
// FILE INFORMATION
// =========================================================

$file_size =
    filesize($actual_file);

$file_extension =
    strtolower(
        pathinfo(
            $actual_file,
            PATHINFO_EXTENSION
        )
    );

// =========================================================
// MIME TYPE
// =========================================================

$mime_type = 'application/octet-stream';

if (
    function_exists('finfo_open')
) {

    $finfo =
        finfo_open(
            FILEINFO_MIME_TYPE
        );

    if ($finfo) {

        $detected =
            finfo_file(
                $finfo,
                $actual_file
            );

        if (
            $detected
        ) {

            $mime_type =
                $detected;
        }

        finfo_close(
            $finfo
        );
    }
}

// =========================================================
// COMMON EXTENSION MIME FIXES
// =========================================================

$mime_map = [

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

    'bmp' =>
        'image/bmp',

    'svg' =>
        'image/svg+xml',

    'txt' =>
        'text/plain',

    'csv' =>
        'text/csv',

    'mp4' =>
        'video/mp4',

    'webm' =>
        'video/webm',

    'mp3' =>
        'audio/mpeg',

    'wav' =>
        'audio/wav'
];

if (
    isset(
        $mime_map[$file_extension]
    )
) {

    $mime_type =
        $mime_map[$file_extension];
}

// =========================================================
// OFFICE EXTENSIONS
// =========================================================

$office_extensions = [

    'doc',
    'docx',

    'ppt',
    'pptx',

    'xls',
    'xlsx',

    'odt',
    'ods',
    'odp'
];

$is_office =
    in_array(
        $file_extension,
        $office_extensions,
        true
    );

// =========================================================
// STREAM MODE
// =========================================================

$stream =
    isset($_GET['stream']) &&
    $_GET['stream'] === '1';

// =========================================================
// FIND LIBREOFFICE
// =========================================================

function findLibreOffice()
{
    $possible = [

        'C:\\Program Files\\LibreOffice\\program\\soffice.exe',

        'C:\\Program Files\\LibreOffice\\program\\soffice.com',

        'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',

        'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.com',

        getenv('LOCALAPPDATA') .
        '\\Programs\\LibreOffice\\program\\soffice.exe',

        getenv('LOCALAPPDATA') .
        '\\Programs\\LibreOffice\\program\\soffice.com',

        getenv('APPDATA') .
        '\\LibreOffice\\program\\soffice.exe'
    ];

    foreach (
        $possible
        as $path
    ) {

        if (
            !empty($path) &&
            is_file($path)
        ) {

            return $path;
        }
    }

    // -----------------------------------------------------
    // WINDOWS WHERE COMMAND
    // -----------------------------------------------------

    $commands = [

        'where soffice.exe',

        'where soffice.com',

        'where soffice'
    ];

    foreach (
        $commands
        as $command
    ) {

        $output = [];

        $return_code = 1;

        @exec(
            $command,
            $output,
            $return_code
        );

        if (
            $return_code === 0 &&
            !empty($output)
        ) {

            foreach (
                $output
                as $line
            ) {

                $line =
                    trim($line);

                if (
                    $line !== '' &&
                    is_file($line)
                ) {

                    return $line;
                }
            }
        }
    }

    return false;
}

// =========================================================
// CONVERT OFFICE FILE TO PDF
// =========================================================

function convertOfficeToPdf($source_file)
{
    $soffice =
        findLibreOffice();

    if (!$soffice) {

        return [
            'success' => false,
            'error' =>
                'LibreOffice was not found on this computer.'
        ];
    }

    $temp_base =
        sys_get_temp_dir();

    $unique =
        'notesphere_admin_' .
        bin2hex(
            random_bytes(8)
        );

    $output_dir =
        $temp_base .
        DIRECTORY_SEPARATOR .
        $unique;

    $profile_dir =
        $temp_base .
        DIRECTORY_SEPARATOR .
        $unique .
        '_profile';

    if (
        !mkdir(
            $output_dir,
            0777,
            true
        )
    ) {

        return [
            'success' => false,
            'error' =>
                'Unable to create the temporary conversion folder.'
        ];
    }

    if (
        !mkdir(
            $profile_dir,
            0777,
            true
        )
    ) {

        @rmdir($output_dir);

        return [
            'success' => false,
            'error' =>
                'Unable to create the LibreOffice profile folder.'
        ];
    }

    $source_file =
        realpath($source_file);

    if (!$source_file) {

        return [
            'success' => false,
            'error' =>
                'Source file could not be located.'
        ];
    }

    $output_dir_arg =
        escapeshellarg(
            $output_dir
        );

    $profile_uri =
        'file:///' .
        str_replace(
            '\\',
            '/',
            $profile_dir
        );

    $profile_arg =
        escapeshellarg(
            '-env:UserInstallation=' .
            $profile_uri
        );

    $source_arg =
        escapeshellarg(
            $source_file
        );

    $soffice_arg =
        escapeshellarg(
            $soffice
        );

    $command =
        '"' .
        $soffice .
        '" ' .
        $profile_arg .
        ' --headless ' .
        '--convert-to pdf ' .
        '--outdir ' .
        $output_dir_arg .
        ' ' .
        $source_arg .
        ' 2>&1';

    $output = [];

    $return_code = 1;

    @exec(
        $command,
        $output,
        $return_code
    );

    // -----------------------------------------------------
    // WAIT FOR OUTPUT
    // -----------------------------------------------------

    $pdf_file = false;

    for (
        $i = 0;
        $i < 60;
        $i++
    ) {

        $pdfs =
            glob(
                $output_dir .
                DIRECTORY_SEPARATOR .
                '*.pdf'
            );

        if (
            !empty($pdfs)
        ) {

            $pdf_file =
                $pdfs[0];

            break;
        }

        usleep(100000);
    }

    if (
        !$pdf_file ||
        !is_file($pdf_file)
    ) {

        $error_text =
            trim(
                implode(
                    "\n",
                    $output
                )
            );

        if (
            $error_text === ''
        ) {

            $error_text =
                'LibreOffice could not convert this document to PDF.';
        }

        return [
            'success' => false,
            'error' =>
                $error_text,
            'temp_dir' =>
                $output_dir,
            'profile_dir' =>
                $profile_dir
        ];
    }

    return [

        'success' =>
            true,

        'pdf' =>
            $pdf_file,

        'temp_dir' =>
            $output_dir,

        'profile_dir' =>
            $profile_dir
    ];
}

// =========================================================
// STREAM FILE
// =========================================================

if ($stream) {

    // =====================================================
    // OFFICE PREVIEW
    // =====================================================

    if ($is_office) {

        $conversion =
            convertOfficeToPdf(
                $actual_file
            );

        if (
            !$conversion['success']
        ) {

            showErrorPage(
                'Preview Not Available',
                $conversion['error']
            );
        }

        $pdf_file =
            $conversion['pdf'];

        if (
            !is_file($pdf_file)
        ) {

            showErrorPage(
                'Preview Error',
                'The document could not be converted for preview.'
            );
        }

        header(
            'Content-Type: application/pdf'
        );

        header(
            'Content-Length: ' .
            filesize($pdf_file)
        );

        header(
            'Content-Disposition: inline; filename="'
            .
            basename(
                pathinfo(
                    $actual_file,
                    PATHINFO_FILENAME
                )
            )
            .
            '.pdf"'
        );

        header(
            'X-Content-Type-Options: nosniff'
        );

        readfile(
            $pdf_file
        );

        // -------------------------------------------------
        // CLEAN TEMP FILES
        // -------------------------------------------------

        register_shutdown_function(
            function () use ($conversion) {

                if (
                    !empty(
                        $conversion['pdf']
                    ) &&
                    is_file(
                        $conversion['pdf']
                    )
                ) {

                    @unlink(
                        $conversion['pdf']
                    );
                }

                if (
                    !empty(
                        $conversion['temp_dir']
                    ) &&
                    is_dir(
                        $conversion['temp_dir']
                    )
                ) {

                    @rmdir(
                        $conversion['temp_dir']
                    );
                }

                if (
                    !empty(
                        $conversion['profile_dir']
                    ) &&
                    is_dir(
                        $conversion['profile_dir']
                    )
                ) {

                    @rmdir(
                        $conversion['profile_dir']
                    );
                }
            }
        );

        exit();
    }


    // =====================================================
    // PDF
    // =====================================================

    if (
        $file_extension === 'pdf'
    ) {

        header(
            'Content-Type: application/pdf'
        );

        header(
            'Content-Length: ' .
            $file_size
        );

        header(
            'Content-Disposition: inline; filename="' .
            basename($actual_file) .
            '"'
        );

        header(
            'X-Content-Type-Options: nosniff'
        );

        readfile(
            $actual_file
        );

        exit();
    }


    // =====================================================
    // IMAGE
    // =====================================================

    if (
        strpos(
            $mime_type,
            'image/'
        ) === 0
    ) {

        header(
            'Content-Type: ' .
            $mime_type
        );

        header(
            'Content-Length: ' .
            $file_size
        );

        header(
            'Content-Disposition: inline; filename="' .
            basename($actual_file) .
            '"'
        );

        header(
            'X-Content-Type-Options: nosniff'
        );

        readfile(
            $actual_file
        );

        exit();
    }


    // =====================================================
    // TEXT
    // =====================================================

    if (
        strpos(
            $mime_type,
            'text/'
        ) === 0
    ) {

        header(
            'Content-Type: ' .
            $mime_type .
            '; charset=UTF-8'
        );

        header(
            'Content-Disposition: inline; filename="' .
            basename($actual_file) .
            '"'
        );

        header(
            'X-Content-Type-Options: nosniff'
        );

        readfile(
            $actual_file
        );

        exit();
    }


    // =====================================================
    // VIDEO
    // =====================================================

    if (
        strpos(
            $mime_type,
            'video/'
        ) === 0
    ) {

        header(
            'Content-Type: ' .
            $mime_type
        );

        header(
            'Content-Length: ' .
            $file_size
        );

        header(
            'Content-Disposition: inline; filename="' .
            basename($actual_file) .
            '"'
        );

        readfile(
            $actual_file
        );

        exit();
    }


    // =====================================================
    // AUDIO
    // =====================================================

    if (
        strpos(
            $mime_type,
            'audio/'
        ) === 0
    ) {

        header(
            'Content-Type: ' .
            $mime_type
        );

        header(
            'Content-Length: ' .
            $file_size
        );

        header(
            'Content-Disposition: inline; filename="' .
            basename($actual_file) .
            '"'
        );

        readfile(
            $actual_file
        );

        exit();
    }


    // =====================================================
    // UNSUPPORTED
    // =====================================================

    showErrorPage(
        'Preview Not Available',
        'This file type cannot be previewed directly inside the browser.'
    );
}

// =========================================================
// DOWNLOAD URL
// =========================================================

$download_url =
    'download_note.php?note_id=' .
    $note_id;

// =========================================================
// PREVIEW URL
// =========================================================

$preview_url =
    'note_view.php?note_id=' .
    $note_id .
    '&stream=1';

// =========================================================
// FILE TYPE
// =========================================================

$is_pdf =
    $file_extension === 'pdf';

$is_image =
    strpos(
        $mime_type,
        'image/'
    ) === 0;

$is_video =
    strpos(
        $mime_type,
        'video/'
    ) === 0;

$is_audio =
    strpos(
        $mime_type,
        'audio/'
    ) === 0;

$is_text =
    strpos(
        $mime_type,
        'text/'
    ) === 0;

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
        NoteSphere - <?php echo e($note['title']); ?>
    </title>

    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>

        /* =================================================
           ROOT
        ================================================= */

        :root {

            --primary:
                #233961;

            --primary-light:
                #3D8BFF;

            --neon:
                #39FF14;

            --background:
                #0b1220;

            --card:
                #111827;

            --card-light:
                #172033;

            --text:
                #f8fafc;

            --muted:
                #94a3b8;

            --border:
                #26344b;

            --danger:
                #ef4444;

            --success:
                #22c55e;

            --shadow:
                0 20px 60px rgba(0,0,0,0.30);
        }


        /* =================================================
           RESET
        ================================================= */

        * {

            margin:
                0;

            padding:
                0;

            box-sizing:
                border-box;

            font-family:
                'Plus Jakarta Sans',
                sans-serif;
        }


        html {

            scroll-behavior:
                smooth;
        }


        body {

            min-height:
                100vh;

            background:
                var(--background);

            color:
                var(--text);

            overflow-x:
                hidden;
        }


        body::before {

            content:
                '';

            position:
                fixed;

            inset:
                0;

            background:

                radial-gradient(
                    circle at top right,
                    rgba(61,139,255,0.10),
                    transparent 35%
                ),

                radial-gradient(
                    circle at bottom left,
                    rgba(57,255,20,0.035),
                    transparent 35%
                );

            pointer-events:
                none;

            z-index:
                -1;
        }


        a {

            text-decoration:
                none;
        }


        /* =================================================
           MAIN PAGE
        ================================================= */

        .page {

            width:
                100%;

            min-height:
                100vh;

            padding:
                22px;

            position:
                relative;
        }


        /* =================================================
           TOP CONTROLS
        ================================================= */

        .top-controls {

            width:
                100%;

            max-width:
                1500px;

            margin:
                0 auto 18px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                12px;
        }


        /* =================================================
           X BUTTON
        ================================================= */

        .close-btn {

            width:
                44px;

            height:
                44px;

            flex:
                0 0 44px;

            border:
                1px solid var(--border);

            border-radius:
                12px;

            background:
                rgba(17,24,39,0.95);

            color:
                var(--text);

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            font-size:
                26px;

            line-height:
                1;

            transition:
                0.2s;

            box-shadow:
                0 8px 25px rgba(0,0,0,0.15);
        }


        .close-btn:hover {

            color:
                var(--primary-light);

            border-color:
                var(--primary-light);

            transform:
                translateY(-1px);
        }


        /* =================================================
           DOWNLOAD BUTTON
        ================================================= */

        .download-btn {

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            gap:
                8px;

            min-height:
                44px;

            padding:
                0 17px;

            border:
                1px solid rgba(57,255,20,0.35);

            border-radius:
                11px;

            background:
                rgba(57,255,20,0.08);

            color:
                var(--neon);

            font-size:
                10px;

            font-weight:
                800;

            letter-spacing:
                0.3px;

            transition:
                0.2s;

            white-space:
                nowrap;
        }


        .download-btn:hover {

            background:
                var(--neon);

            color:
                #061006;

            border-color:
                var(--neon);

            transform:
                translateY(-1px);
        }


        .download-btn svg {

            width:
                16px;

            height:
                16px;

            stroke:
                currentColor;
        }


        /* =================================================
           NOTE CARD
        ================================================= */

        .note-card {

            width:
                100%;

            max-width:
                1500px;

            margin:
                0 auto;

            background:
                rgba(17,24,39,0.96);

            border:
                1px solid var(--border);

            border-radius:
                20px;

            overflow:
                hidden;

            box-shadow:
                var(--shadow);
        }


        /* =================================================
           NOTE HEADER
        ================================================= */

        .note-header {

            padding:
                24px 26px;

            border-bottom:
                1px solid var(--border);

            background:
                rgba(255,255,255,0.015);
        }


        .note-title {

            font-size:
                clamp(20px, 3vw, 30px);

            line-height:
                1.25;

            font-weight:
                800;

            color:
                var(--text);

            word-break:
                break-word;
        }


        .note-description {

            margin-top:
                9px;

            color:
                var(--muted);

            font-size:
                12px;

            line-height:
                1.7;

            max-width:
                950px;

            word-break:
                break-word;
        }


        .note-meta {

            display:
                flex;

            align-items:
                center;

            flex-wrap:
                wrap;

            gap:
                8px;

            margin-top:
                14px;
        }


        .meta-badge {

            display:
                inline-flex;

            align-items:
                center;

            min-height:
                28px;

            padding:
                0 10px;

            border:
                1px solid var(--border);

            border-radius:
                8px;

            background:
                rgba(255,255,255,0.025);

            color:
                var(--muted);

            font-size:
                9px;

            font-weight:
                700;
        }


        .meta-badge.highlight {

            color:
                var(--primary-light);

            border-color:
                rgba(61,139,255,0.25);

            background:
                rgba(61,139,255,0.06);
        }


        .meta-badge.success {

            color:
                var(--success);

            border-color:
                rgba(34,197,94,0.25);

            background:
                rgba(34,197,94,0.06);
        }


        /* =================================================
           VIEWER
        ================================================= */

        .viewer-wrapper {

            width:
                100%;

            background:
                #0a0f1a;

            min-height:
                600px;

            position:
                relative;

            display:
                flex;

            align-items:
                stretch;

            justify-content:
                center;

            overflow:
                hidden;
        }


        .viewer-frame {

            display:
                block;

            width:
                100%;

            min-height:
                720px;

            border:
                none;

            background:
                #ffffff;
        }


        /* =================================================
           IMAGE PREVIEW
        ================================================= */

        .image-preview {

            width:
                100%;

            min-height:
                600px;

            padding:
                30px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            overflow:
                auto;
        }


        .image-preview img {

            display:
                block;

            max-width:
                100%;

            max-height:
                760px;

            object-fit:
                contain;

            border-radius:
                8px;

            box-shadow:
                0 15px 45px rgba(0,0,0,0.35);
        }


        /* =================================================
           VIDEO
        ================================================= */

        .media-preview {

            width:
                100%;

            min-height:
                600px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            padding:
                25px;

            background:
                #05080e;
        }


        .media-preview video,
        .media-preview audio {

            width:
                min(100%, 1000px);
        }


        /* =================================================
           TEXT
        ================================================= */

        .text-preview {

            width:
                100%;

            min-height:
                600px;

            padding:
                30px;

            background:
                #ffffff;

            color:
                #111827;

            overflow:
                auto;
        }


        .text-preview pre {

            white-space:
                pre-wrap;

            word-break:
                break-word;

            font-family:
                Consolas,
                'Courier New',
                monospace;

            font-size:
                13px;

            line-height:
                1.7;
        }


        /* =================================================
           UNSUPPORTED
        ================================================= */

        .unsupported-preview {

            min-height:
                600px;

            width:
                100%;

            display:
                flex;

            flex-direction:
                column;

            align-items:
                center;

            justify-content:
                center;

            padding:
                30px;

            text-align:
                center;
        }


        .unsupported-icon {

            width:
                68px;

            height:
                68px;

            border-radius:
                18px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            background:
                rgba(245,158,11,0.08);

            color:
                #f59e0b;

            font-size:
                28px;

            margin-bottom:
                14px;
        }


        .unsupported-title {

            font-size:
                16px;

            font-weight:
                800;

            margin-bottom:
                5px;
        }


        .unsupported-text {

            color:
                var(--muted);

            font-size:
                11px;

            max-width:
                500px;

            line-height:
                1.7;
        }


        /* =================================================
           FOOTER
        ================================================= */

        .note-footer {

            min-height:
                58px;

            padding:
                14px 20px;

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

            border-top:
                1px solid var(--border);

            background:
                rgba(255,255,255,0.015);
        }


        .file-info {

            color:
                var(--muted);

            font-size:
                10px;

            line-height:
                1.5;

            word-break:
                break-word;
        }


        .file-info strong {

            color:
                var(--text);

            font-weight:
                800;
        }


        .status {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                6px;

            padding:
                6px 9px;

            border-radius:
                7px;

            background:
                rgba(34,197,94,0.08);

            border:
                1px solid rgba(34,197,94,0.20);

            color:
                var(--success);

            font-size:
                8px;

            font-weight:
                800;

            text-transform:
                uppercase;
        }


        .status::before {

            content:
                '';

            width:
                5px;

            height:
                5px;

            border-radius:
                50%;

            background:
                currentColor;
        }


        /* =================================================
           MOBILE
        ================================================= */

        @media (max-width: 768px) {

            .page {

                padding:
                    14px;
            }


            .top-controls {

                margin-bottom:
                    12px;
            }


            .close-btn {

                width:
                    40px;

                height:
                    40px;

                flex-basis:
                    40px;
            }


            .download-btn {

                min-height:
                    40px;

                padding:
                    0 13px;

                font-size:
                    9px;
            }


            .note-card {

                border-radius:
                    16px;
            }


            .note-header {

                padding:
                    19px 17px;
            }


            .note-title {

                font-size:
                    20px;
            }


            .note-description {

                font-size:
                    11px;
            }


            .viewer-wrapper {

                min-height:
                    500px;
            }


            .viewer-frame {

                min-height:
                    650px;
            }


            .image-preview {

                min-height:
                    500px;

                padding:
                    15px;
            }


            .media-preview {

                min-height:
                    500px;

                padding:
                    15px;
            }


            .text-preview {

                min-height:
                    500px;

                padding:
                    18px;
            }


            .text-preview pre {

                font-size:
                    11px;
            }


            .unsupported-preview {

                min-height:
                    500px;
            }


            .note-footer {

                padding:
                    13px 16px;

                align-items:
                    flex-start;

                flex-direction:
                    column;
            }

        }


        @media (max-width: 420px) {

            .page {

                padding:
                    10px;
            }


            .top-controls {

                gap:
                    8px;
            }


            .close-btn {

                width:
                    38px;

                height:
                    38px;

                flex-basis:
                    38px;
            }


            .download-btn {

                flex:
                    1;

                min-height:
                    38px;
            }


            .note-header {

                padding:
                    17px 14px;
            }


            .note-title {

                font-size:
                    18px;
            }

        }

    </style>

</head>


<body>

    <main class="page">

        <!-- =================================================
             TOP CONTROLS
        ================================================== -->

        <div class="top-controls">

            <!-- X / BACK -->

            <a
                href="manage_notes.php"
                class="close-btn"
                aria-label="Back to Manage Notes"
                title="Back to Manage Notes"
            >
                ×
            </a>


            <!-- ONLY ONE DOWNLOAD BUTTON -->

            <a
                href="<?php echo e($download_url); ?>"
                class="download-btn"
            >

                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >

                    <path
                        d="M12 3v12"
                    ></path>

                    <path
                        d="M7 10l5 5 5-5"
                    ></path>

                    <path
                        d="M5 21h14"
                    ></path>

                </svg>

                DOWNLOAD

            </a>

        </div>


        <!-- =================================================
             NOTE CARD
        ================================================== -->

        <section class="note-card">


            <!-- =================================================
                 NOTE HEADER
            ================================================== -->

            <header class="note-header">

                <h1 class="note-title">

                    <?php
                    echo e(
                        $note['title']
                        ?? 'Untitled Note'
                    );
                    ?>

                </h1>


                <?php if (
                    !empty(
                        $note['description']
                    )
                ): ?>

                    <p class="note-description">

                        <?php
                        echo e(
                            $note['description']
                        );
                        ?>

                    </p>

                <?php endif; ?>


                <div class="note-meta">

                    <?php if (
                        !empty(
                            $note['uploader']
                        )
                    ): ?>

                        <span class="meta-badge">

                            Uploaded by:

                            &nbsp;

                            <?php
                            echo e(
                                $note['uploader']
                            );
                            ?>

                        </span>

                    <?php endif; ?>


                    <?php if (
                        !empty(
                            $note['institute']
                        )
                    ): ?>

                        <span class="meta-badge highlight">

                            <?php
                            echo e(
                                $note['institute']
                            );
                            ?>

                        </span>

                    <?php endif; ?>


                    <span class="meta-badge success">

                        Admin View

                    </span>

                </div>

            </header>


            <!-- =================================================
                 VIEWER
            ================================================== -->

            <div class="viewer-wrapper">

                <?php if (
                    $is_pdf ||
                    $is_office
                ): ?>

                    <!--
                        PDF and Office documents.

                        Office files are converted to PDF
                        by the PHP stream handler above.
                    -->

                    <iframe
                        src="<?php echo e($preview_url); ?>"
                        class="viewer-frame"
                        title="Document Preview"
                        loading="eager"
                    ></iframe>


                <?php elseif ($is_image): ?>

                    <div class="image-preview">

                        <img
                            src="<?php echo e($preview_url); ?>"
                            alt="<?php echo e($note['title']); ?>"
                        >

                    </div>


                <?php elseif ($is_video): ?>

                    <div class="media-preview">

                        <video
                            controls
                            preload="metadata"
                        >

                            <source
                                src="<?php echo e($preview_url); ?>"
                                type="<?php echo e($mime_type); ?>"
                            >

                            Your browser does not support video playback.

                        </video>

                    </div>


                <?php elseif ($is_audio): ?>

                    <div class="media-preview">

                        <audio
                            controls
                            preload="metadata"
                        >

                            <source
                                src="<?php echo e($preview_url); ?>"
                                type="<?php echo e($mime_type); ?>"
                            >

                            Your browser does not support audio playback.

                        </audio>

                    </div>


                <?php elseif ($is_text): ?>

                    <div class="text-preview">

                        <pre><?php

                            $text_content =
                                @file_get_contents(
                                    $actual_file
                                );

                            if (
                                $text_content !== false
                            ) {

                                echo e(
                                    $text_content
                                );

                            } else {

                                echo
                                    'Unable to read this text file.';
                            }

                        ?></pre>

                    </div>


                <?php else: ?>

                    <div class="unsupported-preview">

                        <div class="unsupported-icon">
                            !
                        </div>

                        <div class="unsupported-title">

                            Preview Not Available

                        </div>

                        <div class="unsupported-text">

                            This file type cannot be previewed directly inside the browser.
                            You may use the DOWNLOAD button above to save the file.

                        </div>

                    </div>

                <?php endif; ?>

            </div>


            <!-- =================================================
                 FOOTER
            ================================================== -->

            <footer class="note-footer">

                <div class="file-info">

                    <strong>
                        File:
                    </strong>

                    <?php
                    echo e(
                        $file_basename
                    );
                    ?>

                    &nbsp;•&nbsp;

                    <?php

                    if (
                        $file_size >=
                        1024 * 1024
                    ) {

                        echo
                            number_format(
                                $file_size /
                                (1024 * 1024),
                                2
                            )
                            .
                            ' MB';

                    } elseif (
                        $file_size >= 1024
                    ) {

                        echo
                            number_format(
                                $file_size /
                                1024,
                                2
                            )
                            .
                            ' KB';

                    } else {

                        echo
                            number_format(
                                $file_size
                            )
                            .
                            ' B';
                    }

                    ?>

                </div>


                <div class="status">

                    <?php

                    echo e(
                        ucfirst(
                            $note['status']
                            ?? 'unknown'
                        )
                    );

                    ?>

                </div>

            </footer>

        </section>

    </main>


    <!-- =================================================
         JAVASCRIPT
    ================================================== -->

    <script>

        // =================================================
        // PREVIEW ERROR HANDLER
        // =================================================

        const viewerFrame =
            document.querySelector(
                '.viewer-frame'
            );


        if (viewerFrame) {

            viewerFrame.addEventListener(
                'error',
                function () {

                    Swal.fire({

                        icon:
                            'error',

                        title:
                            'Preview Error',

                        text:
                            'The file could not be displayed.',

                        confirmButtonColor:
                            '#233961'

                    });

                }
            );
        }

    </script>

</body>

</html>