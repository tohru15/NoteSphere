<?php

// =========================================================
// NoteSphere - View Note
// CLEAN VIEWER
// NO SIDEBAR
// MOBILE READY
// FULL OFFICE PREVIEW + SECURE FILE STREAM
// =========================================================

session_start();

require_once('../includes/db_config.php');

/** @var PDO $conn */

// =========================================================
// SECURITY CHECK
// =========================================================

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role'])
) {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$role    = $_SESSION['role'];

// =========================================================
// ERROR PAGE FUNCTION
// =========================================================

function showErrorPage(
    string $title,
    string $message
): void {

    $safe_title = htmlspecialchars(
        $title,
        ENT_QUOTES,
        'UTF-8'
    );

    $safe_message = htmlspecialchars(
        $message,
        ENT_QUOTES,
        'UTF-8'
    );

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
            <?php echo $safe_title; ?> - NoteSphere
        </title>

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

        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

        <style>

            * {
                box-sizing: border-box;
            }

            :root {
                --neon: #39ff14;
                --bg: #080808;
                --card: #101010;
                --border: #252525;
                --text: #f8fafc;
                --sub: #94a3b8;
            }

            html,
            body {
                margin: 0;
                width: 100%;
                min-height: 100%;
            }

            body {

                min-height: 100vh;

                display: flex;

                align-items: center;

                justify-content: center;

                padding: 20px;

                background:
                    linear-gradient(
                        rgba(8,8,8,0.94),
                        rgba(8,8,8,0.97)
                    ),
                    url("../assets/registerbg.png")
                    center/cover fixed
                    no-repeat;

                color: var(--text);

                font-family:
                    "Plus Jakarta Sans",
                    Arial,
                    sans-serif;
            }

            .error-box {

                width: min(
                    500px,
                    100%
                );

                text-align: center;

                background:
                    rgba(16,16,16,0.96);

                border:
                    1px solid var(--border);

                border-radius: 20px;

                padding:
                    35px 25px;

                box-shadow:
                    0 20px 60px
                    rgba(0,0,0,0.45);
            }

            .error-icon {

                width: 64px;
                height: 64px;

                margin:
                    0 auto 18px;

                display: flex;

                align-items: center;

                justify-content: center;

                border-radius: 50%;

                background:
                    rgba(255,77,77,0.08);

                border:
                    1px solid
                    rgba(255,77,77,0.18);

                font-size: 30px;
            }

            h1 {

                margin:
                    0 0 12px;

                font-size:
                    1.35rem;

                font-weight: 800;
            }

            p {

                margin: 0;

                color: var(--sub);

                line-height: 1.7;

                font-size:
                    0.88rem;
            }

            .buttons {

                display: flex;

                justify-content: center;

                margin-top: 25px;
            }

            .btn {

                min-height: 44px;

                display: inline-flex;

                align-items: center;

                justify-content: center;

                padding:
                    0 22px;

                border-radius: 10px;

                text-decoration: none;

                font-weight: 800;

                font-size:
                    0.82rem;

                transition:
                    0.2s ease;
            }

            .home-btn {

                background:
                    var(--neon);

                color: #000;

                box-shadow:
                    0 0 20px
                    rgba(57,255,20,0.12);
            }

            .home-btn:hover {

                transform:
                    translateY(-1px);

                box-shadow:
                    0 0 28px
                    rgba(57,255,20,0.22);
            }

            @media(max-width:500px) {

                body {
                    padding: 15px;
                }

                .error-box {

                    padding:
                        28px 18px;

                    border-radius:
                        16px;
                }

                .btn {
                    width: 100%;
                }

            }

        </style>

    </head>

    <body>

        <div class="error-box">

            <div class="error-icon">
                ⚠️
            </div>

            <h1>
                <?php echo $safe_title; ?>
            </h1>

            <p>
                <?php echo $safe_message; ?>
            </p>

            <div class="buttons">

                <a
                    href="student_home.php"
                    class="btn home-btn"
                >
                    Return to Dashboard
                </a>

            </div>

        </div>

        <script>

            Swal.fire({

                icon: 'error',

                title:
                    <?php echo json_encode($title); ?>,

                text:
                    <?php echo json_encode($message); ?>,

                confirmButtonColor:
                    '#39FF14',

                color:
                    '#ffffff',

                background:
                    '#111111'

            });

        </script>

    </body>

    </html>

    <?php

    exit;
}

// =========================================================
// NOTE ID
// =========================================================

$note_id = isset($_GET['note_id'])
    ? (int) $_GET['note_id']
    : 0;

if ($note_id <= 0) {

    showErrorPage(
        'Invalid Note',
        'The note you are trying to view is invalid.'
    );
}

// =========================================================
// GET NOTE
// =========================================================

try {

    $stmt = $conn->prepare("
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
        'The note could not be loaded right now.'
    );
}

// =========================================================
// CHECK NOTE
// =========================================================

if (!$note) {

    showErrorPage(
        'Note Not Found',
        'This note no longer exists or is unavailable.'
    );
}

// =========================================================
// STUDENT APPROVAL CHECK
// =========================================================

if (
    $role === 'student' &&
    strtolower((string)$note['status']) !== 'approved'
) {

    showErrorPage(
        'Note Not Available',
        'This note is not currently available for viewing.'
    );
}

// =========================================================
// STORED FILE
// =========================================================

$stored_file = trim(
    (string)$note['file_path']
);

if ($stored_file === '') {

    showErrorPage(
        'File Not Available',
        'This note does not have an attached file.'
    );
}

// =========================================================
// PROJECT ROOT
// =========================================================

$project_root = realpath(
    __DIR__ . '/..'
);

if ($project_root === false) {

    showErrorPage(
        'System Error',
        'The NoteSphere project directory could not be located.'
    );
}

// =========================================================
// NORMALIZE STORED PATH
// =========================================================

$stored_file = str_replace(
    '\\',
    '/',
    $stored_file
);

$stored_file = trim(
    $stored_file,
    " \t\n\r\0\x0B\"'"
);

$stored_file = explode(
    '?',
    $stored_file,
    2
)[0];

$stored_file = explode(
    '#',
    $stored_file,
    2
)[0];

$stored_file = rawurldecode(
    $stored_file
);

// =========================================================
// HANDLE FULL URL
// =========================================================

if (
    preg_match(
        '#^https?://#i',
        $stored_file
    )
) {

    $url_path = parse_url(
        $stored_file,
        PHP_URL_PATH
    );

    if ($url_path) {
        $stored_file = $url_path;
    }
}

// =========================================================
// FILE NAME
// =========================================================

$file_name = basename(
    $stored_file
);

$file_name = trim(
    $file_name
);

if (
    $file_name === '' ||
    $file_name === '.' ||
    $file_name === '..'
) {

    showErrorPage(
        'Invalid File',
        'The attached file path is invalid.'
    );
}

// =========================================================
// FIND ACTUAL FILE
// =========================================================

function findNoteFile(
    string $project_root,
    string $stored_file,
    string $file_name
) {

    $possible_files = [];

    // -----------------------------------------------------
    // Exact stored path
    // -----------------------------------------------------

    $clean_path = ltrim(
        $stored_file,
        '/'
    );

    while (
        strpos(
            $clean_path,
            '../'
        ) === 0
    ) {

        $clean_path = substr(
            $clean_path,
            3
        );
    }

    while (
        strpos(
            $clean_path,
            './'
        ) === 0
    ) {

        $clean_path = substr(
            $clean_path,
            2
        );
    }

    if (
        preg_match(
            '/^[A-Za-z]:[\\\\\/]/',
            $clean_path
        )
    ) {

        if (
            is_file($clean_path)
        ) {

            $real_path = realpath(
                $clean_path
            );

            if ($real_path !== false) {
                return $real_path;
            }
        }

    } else {

        $possible_files[] =
            $project_root .
            DIRECTORY_SEPARATOR .
            str_replace(
                '/',
                DIRECTORY_SEPARATOR,
                $clean_path
            );
    }

    // -----------------------------------------------------
    // New NoteSphere storage
    // -----------------------------------------------------

    $possible_files[] =
        $project_root .
        DIRECTORY_SEPARATOR .
        'uploads' .
        DIRECTORY_SEPARATOR .
        'notes' .
        DIRECTORY_SEPARATOR .
        $file_name;

    // -----------------------------------------------------
    // Old uploads location
    // -----------------------------------------------------

    $possible_files[] =
        $project_root .
        DIRECTORY_SEPARATOR .
        'uploads' .
        DIRECTORY_SEPARATOR .
        $file_name;

    // -----------------------------------------------------
    // Other known locations
    // -----------------------------------------------------

    $folders = [

        'uploads/submissions',
        'uploads/messages',
        'uploads/announcements',
        'uploads/files',
        'uploads/documents',
        'storage',
        'files',
        'assets/uploads'

    ];

    foreach (
        $folders as $folder
    ) {

        $possible_files[] =
            $project_root .
            DIRECTORY_SEPARATOR .
            str_replace(
                '/',
                DIRECTORY_SEPARATOR,
                $folder
            ) .
            DIRECTORY_SEPARATOR .
            $file_name;
    }

    // -----------------------------------------------------
    // Check candidates
    // -----------------------------------------------------

    foreach (
        $possible_files as $candidate
    ) {

        if (
            !is_file($candidate) ||
            !is_readable($candidate)
        ) {
            continue;
        }

        $real = realpath(
            $candidate
        );

        if (
            $real !== false &&
            stripos(
                $real,
                $project_root
            ) === 0
        ) {

            return $real;
        }
    }

    // -----------------------------------------------------
    // Recursive search
    // -----------------------------------------------------

    try {

        $skip = [

            'vendor',
            'node_modules',
            '.git',
            'temp',
            'tmp'

        ];

        $iterator =
            new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $project_root,
                    FilesystemIterator::SKIP_DOTS
                )
            );

        foreach (
            $iterator as $file
        ) {

            if (
                !$file->isFile()
            ) {
                continue;
            }

            $pathname =
                $file->getPathname();

            $skip_this = false;

            foreach (
                $skip as $folder
            ) {

                if (
                    stripos(
                        $pathname,
                        DIRECTORY_SEPARATOR .
                        $folder .
                        DIRECTORY_SEPARATOR
                    ) !== false
                ) {

                    $skip_this = true;
                    break;
                }
            }

            if ($skip_this) {
                continue;
            }

            if (
                strcasecmp(
                    $file->getFilename(),
                    $file_name
                ) === 0
            ) {

                $real =
                    realpath($pathname);

                if (
                    $real !== false &&
                    stripos(
                        $real,
                        $project_root
                    ) === 0
                ) {

                    return $real;
                }
            }
        }

    } catch (Throwable $e) {

        // Ignore recursive search errors.
    }

    return false;
}

$actual_file = findNoteFile(
    $project_root,
    $stored_file,
    $file_name
);

if (
    $actual_file === false
) {

    showErrorPage(
        'File Not Found',
        'The note file could not be found in NoteSphere storage.'
    );
}

// =========================================================
// EXTENSION
// =========================================================

$extension = strtolower(
    pathinfo(
        $actual_file,
        PATHINFO_EXTENSION
    )
);

// =========================================================
// DISPLAY NAME
// =========================================================

$display_file_name =
    basename($actual_file);

// =========================================================
// MIME
// =========================================================

$mime_types = [

    'pdf'  => 'application/pdf',

    'txt'  => 'text/plain',
    'csv'  => 'text/csv',
    'log'  => 'text/plain',
    'md'   => 'text/markdown',

    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
    'bmp'  => 'image/bmp',
    'svg'  => 'image/svg+xml',

    'mp3'  => 'audio/mpeg',
    'wav'  => 'audio/wav',
    'ogg'  => 'audio/ogg',

    'mp4'  => 'video/mp4',
    'webm' => 'video/webm',
    'mov'  => 'video/quicktime'

];

$mime_type =
    $mime_types[$extension]
    ?? 'application/octet-stream';

// =========================================================
// DOWNLOAD URL
// =========================================================

$download_url =
    'download_note.php?note_id=' .
    urlencode(
        (string)$note_id
    );

// =========================================================
// STREAM REQUEST
// =========================================================

$is_stream =
    isset($_GET['stream']) &&
    $_GET['stream'] === '1';

$is_raw =
    isset($_GET['raw']) &&
    $_GET['raw'] === '1';

// =========================================================
// OFFICE FILES
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
        $extension,
        $office_extensions,
        true
    );

// =========================================================
// FIND LIBREOFFICE
// =========================================================

function findLibreOffice(): ?string
{
    // -----------------------------------------------------
    // WINDOWS ENVIRONMENT PATHS
    // -----------------------------------------------------

    $program_files =
        getenv('ProgramFiles');

    $program_files_x86 =
        getenv('ProgramFiles(x86)');

    $program_w6432 =
        getenv('ProgramW6432');

    $local_appdata =
        getenv('LOCALAPPDATA');

    $paths = [];

    // -----------------------------------------------------
    // STANDARD 64-BIT LOCATIONS
    // -----------------------------------------------------

    if ($program_files) {

        $paths[] =
            $program_files .
            '\\LibreOffice\\program\\soffice.com';

        $paths[] =
            $program_files .
            '\\LibreOffice\\program\\soffice.exe';

        $paths[] =
            $program_files .
            '\\LibreOffice\\program\\soffice.bin';
    }

    // -----------------------------------------------------
    // 64-BIT PROGRAM FILES
    // -----------------------------------------------------

    if ($program_w6432) {

        $paths[] =
            $program_w6432 .
            '\\LibreOffice\\program\\soffice.com';

        $paths[] =
            $program_w6432 .
            '\\LibreOffice\\program\\soffice.exe';

        $paths[] =
            $program_w6432 .
            '\\LibreOffice\\program\\soffice.bin';
    }

    // -----------------------------------------------------
    // 32-BIT LOCATION
    // -----------------------------------------------------

    if ($program_files_x86) {

        $paths[] =
            $program_files_x86 .
            '\\LibreOffice\\program\\soffice.com';

        $paths[] =
            $program_files_x86 .
            '\\LibreOffice\\program\\soffice.exe';

        $paths[] =
            $program_files_x86 .
            '\\LibreOffice\\program\\soffice.bin';
    }

    // -----------------------------------------------------
    // LOCAL APPDATA
    // -----------------------------------------------------

    if ($local_appdata) {

        $paths[] =
            $local_appdata .
            '\\Programs\\LibreOffice\\program\\soffice.com';

        $paths[] =
            $local_appdata .
            '\\Programs\\LibreOffice\\program\\soffice.exe';

        $paths[] =
            $local_appdata .
            '\\Programs\\LibreOffice\\program\\soffice.bin';

        $paths[] =
            $local_appdata .
            '\\LibreOffice\\program\\soffice.com';

        $paths[] =
            $local_appdata .
            '\\LibreOffice\\program\\soffice.exe';
    }

    // -----------------------------------------------------
    // COMMON MANUAL INSTALLATION PATHS
    // -----------------------------------------------------

    $paths[] =
        'C:\\Program Files\\LibreOffice\\program\\soffice.com';

    $paths[] =
        'C:\\Program Files\\LibreOffice\\program\\soffice.exe';

    $paths[] =
        'C:\\Program Files\\LibreOffice\\program\\soffice.bin';

    $paths[] =
        'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.com';

    $paths[] =
        'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe';

    $paths[] =
        'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.bin';

    // -----------------------------------------------------
    // CHECK DIRECT PATHS
    // -----------------------------------------------------

    foreach (
        $paths as $path
    ) {

        if (
            !empty($path) &&
            is_file($path) &&
            is_readable($path)
        ) {

            return $path;
        }
    }

    // -----------------------------------------------------
    // SEARCH LIBREOFFICE FOLDERS
    // -----------------------------------------------------

    $search_roots = [];

    if ($program_files) {
        $search_roots[] =
            rtrim(
                $program_files,
                '\\/'
            );
    }

    if ($program_files_x86) {
        $search_roots[] =
            rtrim(
                $program_files_x86,
                '\\/'
            );
    }

    if ($program_w6432) {
        $search_roots[] =
            rtrim(
                $program_w6432,
                '\\/'
            );
    }

    foreach (
        $search_roots as $root
    ) {

        $patterns = [

            $root .
            '\\LibreOffice*\\program\\soffice.com',

            $root .
            '\\LibreOffice*\\program\\soffice.exe',

            $root .
            '\\LibreOffice*\\program\\soffice.bin'

        ];

        foreach (
            $patterns as $pattern
        ) {

            $matches =
                @glob($pattern);

            if (
                !empty($matches)
            ) {

                foreach (
                    $matches as $match
                ) {

                    if (
                        is_file($match) &&
                        is_readable($match)
                    ) {

                        return $match;
                    }
                }
            }
        }
    }

    // -----------------------------------------------------
    // WINDOWS PATH SEARCH
    // -----------------------------------------------------

    if (
        function_exists('shell_exec')
    ) {

        $commands = [

            'where soffice.com 2>NUL',

            'where soffice.exe 2>NUL',

            'where soffice 2>NUL'

        ];

        foreach (
            $commands as $command
        ) {

            $which =
                @shell_exec($command);

            if (
                empty($which)
            ) {
                continue;
            }

            $lines =
                preg_split(
                    '/\r\n|\r|\n/',
                    trim($which)
                );

            foreach (
                $lines as $line
            ) {

                $line = trim($line);

                if (
                    $line !== '' &&
                    is_file($line) &&
                    is_readable($line)
                ) {

                    return $line;
                }
            }
        }
    }

    return null;
}

// =========================================================
// REMOVE DIRECTORY
// =========================================================

function removeDirectory(
    string $directory
): void {

    if (
        !is_dir($directory)
    ) {
        return;
    }

    try {

        $items =
            new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $directory,
                    FilesystemIterator::SKIP_DOTS
                ),
                RecursiveIteratorIterator::CHILD_FIRST
            );

        foreach (
            $items as $item
        ) {

            if (
                $item->isDir()
            ) {

                @rmdir(
                    $item->getRealPath()
                );

            } else {

                @unlink(
                    $item->getRealPath()
                );
            }
        }

    } catch (Throwable $e) {
        // Ignore cleanup error.
    }

    @rmdir(
        $directory
    );
}

// =========================================================
// CONVERT OFFICE TO PDF
// =========================================================

function convertOfficeToPdf(
    string $actual_file,
    int $note_id
): array {

    // -----------------------------------------------------
    // CHECK EXEC
    // -----------------------------------------------------

    if (
        !function_exists('exec')
    ) {

        return [
            'success' => false,
            'error' =>
                'Office preview is unavailable because PHP execution support is disabled. Please enable exec in php.ini.'
        ];
    }

    // -----------------------------------------------------
    // FIND LIBREOFFICE
    // -----------------------------------------------------

    $soffice =
        findLibreOffice();

    if (
        $soffice === null
    ) {

        return [
            'success' => false,
            'error' =>
                'LibreOffice could not be detected. Please restart XAMPP/Apache after installing LibreOffice.'
        ];
    }

    // -----------------------------------------------------
    // TEMP FOLDER
    // -----------------------------------------------------

    $temp_root =
        sys_get_temp_dir();

    $conversion_folder =
        $temp_root .
        DIRECTORY_SEPARATOR .
        'notesphere_preview_' .
        $note_id .
        '_' .
        bin2hex(
            random_bytes(5)
        );

    if (
        !@mkdir(
            $conversion_folder,
            0777,
            true
        )
    ) {

        return [
            'success' => false,
            'error' =>
                'NoteSphere could not create a temporary preview folder.'
        ];
    }

    // -----------------------------------------------------
    // LIBREOFFICE PROFILE
    // -----------------------------------------------------

    $profile_folder =
        $conversion_folder .
        DIRECTORY_SEPARATOR .
        'profile';

    if (
        !@mkdir(
            $profile_folder,
            0777,
            true
        )
    ) {

        removeDirectory(
            $conversion_folder
        );

        return [
            'success' => false,
            'error' =>
                'NoteSphere could not create a LibreOffice profile.'
        ];
    }

    // -----------------------------------------------------
    // PROFILE URL
    // -----------------------------------------------------

    $profile_path =
        str_replace(
            '\\',
            '/',
            $profile_folder
        );

    $profile_url =
        'file:///' .
        ltrim(
            $profile_path,
            '/'
        );

    // -----------------------------------------------------
    // OUTPUT FOLDER
    // -----------------------------------------------------

    $output_folder =
        str_replace(
            '\\',
            '/',
            $conversion_folder
        );

    // -----------------------------------------------------
    // BUILD COMMAND
    // -----------------------------------------------------

    $command =
        escapeshellarg($soffice) .
        ' --headless' .
        ' --invisible' .
        ' --nodefault' .
        ' --nologo' .
        ' --nofirststartwizard' .
        ' -env:UserInstallation=' .
        escapeshellarg($profile_url) .
        ' --convert-to pdf' .
        ' --outdir ' .
        escapeshellarg($output_folder) .
        ' ' .
        escapeshellarg($actual_file) .
        ' 2>&1';

    $output = [];

    $return_code = -1;

    @exec(
        $command,
        $output,
        $return_code
    );

    // -----------------------------------------------------
    // EXPECTED PDF
    // -----------------------------------------------------

    $expected_pdf =
        $conversion_folder .
        DIRECTORY_SEPARATOR .
        pathinfo(
            $actual_file,
            PATHINFO_FILENAME
        ) .
        '.pdf';

    // -----------------------------------------------------
    // WAIT FOR CONVERSION
    // -----------------------------------------------------

    $attempts = 0;

    while (
        $attempts < 40
    ) {

        if (
            is_file($expected_pdf) &&
            filesize($expected_pdf) > 0
        ) {

            break;
        }

        usleep(250000);

        $attempts++;
    }

    // -----------------------------------------------------
    // EXPECTED PDF FOUND
    // -----------------------------------------------------

    if (
        is_file($expected_pdf) &&
        filesize($expected_pdf) > 0
    ) {

        return [
            'success' => true,
            'pdf' => $expected_pdf,
            'folder' => $conversion_folder
        ];
    }

    // -----------------------------------------------------
    // SEARCH ANY PDF
    // -----------------------------------------------------

    $pdf_files =
        glob(
            $conversion_folder .
            DIRECTORY_SEPARATOR .
            '*.pdf'
        );

    if (
        !empty($pdf_files)
    ) {

        foreach (
            $pdf_files as $pdf
        ) {

            if (
                is_file($pdf) &&
                filesize($pdf) > 0
            ) {

                return [
                    'success' => true,
                    'pdf' => $pdf,
                    'folder' => $conversion_folder
                ];
            }
        }
    }

    // -----------------------------------------------------
    // ERROR OUTPUT
    // -----------------------------------------------------

    $error_output =
        trim(
            implode(
                ' ',
                $output
            )
        );

    removeDirectory(
        $conversion_folder
    );

    if (
        $error_output === ''
    ) {

        $error_output =
            'LibreOffice was found, but it could not convert the document.';
    }

    return [
        'success' => false,
        'error' =>
            $error_output
    ];
}

// =========================================================
// OFFICE STREAM
// =========================================================

if (
    $is_office &&
    ($is_stream || $is_raw)
) {

    $conversion =
        convertOfficeToPdf(
            $actual_file,
            $note_id
        );

    if (
        !$conversion['success']
    ) {

        showErrorPage(
            'Office Preview Unavailable',
            $conversion['error']
        );
    }

    $pdf_file =
        $conversion['pdf'];

    $conversion_folder =
        $conversion['folder'];

    $pdf_name =
        pathinfo(
            $display_file_name,
            PATHINFO_FILENAME
        ) .
        '.pdf';

    // -----------------------------------------------------
    // CLEAR OUTPUT
    // -----------------------------------------------------

    while (
        ob_get_level() > 0
    ) {

        @ob_end_clean();
    }

    // -----------------------------------------------------
    // PDF HEADERS
    // -----------------------------------------------------

    header(
        'Content-Type: application/pdf'
    );

    header(
        'Content-Disposition: inline; filename="' .
        addslashes($pdf_name) .
        '"'
    );

    header(
        'Content-Length: ' .
        filesize($pdf_file)
    );

    header(
        'X-Content-Type-Options: nosniff'
    );

    header(
        'Cache-Control: private, max-age=0, must-revalidate'
    );

    header(
        'Pragma: public'
    );

    // -----------------------------------------------------
    // SEND PDF
    // -----------------------------------------------------

    readfile(
        $pdf_file
    );

    // -----------------------------------------------------
    // CLEAN TEMP FILES
    // -----------------------------------------------------

    removeDirectory(
        $conversion_folder
    );

    exit;
}

// =========================================================
// PDF STREAM
// =========================================================

if (
    $extension === 'pdf' &&
    ($is_stream || $is_raw)
) {

    while (
        ob_get_level() > 0
    ) {

        @ob_end_clean();
    }

    header(
        'Content-Type: application/pdf'
    );

    header(
        'Content-Disposition: inline; filename="' .
        addslashes($display_file_name) .
        '"'
    );

    header(
        'Content-Length: ' .
        filesize($actual_file)
    );

    header(
        'X-Content-Type-Options: nosniff'
    );

    header(
        'Cache-Control: private, max-age=0, must-revalidate'
    );

    header(
        'Pragma: public'
    );

    readfile(
        $actual_file
    );

    exit;
}

// =========================================================
// IMAGE STREAM
// =========================================================

$image_extensions = [

    'jpg',
    'jpeg',
    'png',
    'gif',
    'webp',
    'bmp',
    'svg'

];

if (
    in_array(
        $extension,
        $image_extensions,
        true
    ) &&
    ($is_stream || $is_raw)
) {

    while (
        ob_get_level() > 0
    ) {

        @ob_end_clean();
    }

    header(
        'Content-Type: ' .
        $mime_type
    );

    header(
        'Content-Disposition: inline; filename="' .
        addslashes($display_file_name) .
        '"'
    );

    header(
        'Content-Length: ' .
        filesize($actual_file)
    );

    header(
        'X-Content-Type-Options: nosniff'
    );

    readfile(
        $actual_file
    );

    exit;
}

// =========================================================
// TEXT STREAM
// =========================================================

$text_extensions = [

    'txt',
    'csv',
    'log',
    'md'

];

if (
    in_array(
        $extension,
        $text_extensions,
        true
    ) &&
    ($is_stream || $is_raw)
) {

    $content =
        @file_get_contents(
            $actual_file
        );

    if (
        $content === false
    ) {

        showErrorPage(
            'Unable to Read File',
            'The text file could not be opened.'
        );
    }

    while (
        ob_get_level() > 0
    ) {

        @ob_end_clean();
    }

    header(
        'Content-Type: text/plain; charset=UTF-8'
    );

    header(
        'Content-Disposition: inline; filename="' .
        addslashes($display_file_name) .
        '"'
    );

    header(
        'X-Content-Type-Options: nosniff'
    );

    echo $content;

    exit;
}

// =========================================================
// MEDIA STREAM
// =========================================================

$media_extensions = [

    'mp3',
    'wav',
    'ogg',
    'mp4',
    'webm',
    'mov'

];

if (
    in_array(
        $extension,
        $media_extensions,
        true
    ) &&
    ($is_stream || $is_raw)
) {

    while (
        ob_get_level() > 0
    ) {

        @ob_end_clean();
    }

    $size =
        filesize($actual_file);

    header(
        'Content-Type: ' .
        $mime_type
    );

    header(
        'Content-Disposition: inline; filename="' .
        addslashes($display_file_name) .
        '"'
    );

    header(
        'Content-Length: ' .
        $size
    );

    header(
        'Accept-Ranges: bytes'
    );

    header(
        'X-Content-Type-Options: nosniff'
    );

    readfile(
        $actual_file
    );

    exit;
}

// =========================================================
// PAGE INFORMATION
// =========================================================

$title =
    htmlspecialchars(
        $note['title'] ?? 'Note',
        ENT_QUOTES,
        'UTF-8'
    );

$description =
    htmlspecialchars(
        $note['description'] ?? '',
        ENT_QUOTES,
        'UTF-8'
    );

$institute =
    htmlspecialchars(
        $note['institute'] ?? 'General',
        ENT_QUOTES,
        'UTF-8'
    );

$uploader =
    htmlspecialchars(
        $note['uploader'] ?? 'Unknown',
        ENT_QUOTES,
        'UTF-8'
    );

$created_at =
    !empty($note['created_at'])
    ? date(
        'F d, Y h:i A',
        strtotime($note['created_at'])
    )
    : 'Unknown date';

$created_at =
    htmlspecialchars(
        $created_at,
        ENT_QUOTES,
        'UTF-8'
    );

$safe_file_name =
    htmlspecialchars(
        $display_file_name,
        ENT_QUOTES,
        'UTF-8'
    );

// =========================================================
// PREVIEW URL
// =========================================================

$preview_url =
    'note_view.php?note_id=' .
    urlencode(
        (string)$note_id
    ) .
    '&stream=1';

// =========================================================
// CAN PREVIEW?
// =========================================================

$browser_preview_extensions = [

    'pdf',

    'jpg',
    'jpeg',
    'png',
    'gif',
    'webp',
    'bmp',
    'svg',

    'txt',
    'csv',
    'log',
    'md',

    'mp3',
    'wav',
    'ogg',

    'mp4',
    'webm',
    'mov'

];

$can_preview =
    in_array(
        $extension,
        $browser_preview_extensions,
        true
    ) ||
    $is_office;

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
        <?php echo $title; ?> - NoteSphere
    </title>

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

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>

        /* =====================================================
           ROOT
        ===================================================== */

        :root {

            --neon:
                #39ff14;

            --neon-soft:
                rgba(57,255,20,0.08);

            --neon-border:
                rgba(57,255,20,0.18);

            --bg:
                #080808;

            --card:
                rgba(12,12,12,0.96);

            --card-2:
                #101010;

            --text:
                #f8fafc;

            --sub:
                #94a3b8;

            --border:
                #1f293d;

            --border-light:
                #292929;

        }

        /* =====================================================
           RESET
        ===================================================== */

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {

            margin: 0;

            min-height: 100vh;

            color:
                var(--text);

            font-family:
                "Plus Jakarta Sans",
                Arial,
                sans-serif;

            background:
                linear-gradient(
                    rgba(8,8,8,0.91),
                    rgba(8,8,8,0.96)
                ),
                url("../assets/registerbg.png")
                center/cover fixed
                no-repeat;
        }

        a,
        button {

            -webkit-tap-highlight-color:
                transparent;
        }

        /* =====================================================
           PAGE
        ===================================================== */

        .page {

            width: 100%;

            min-height: 100vh;

            padding:
                24px;
        }

        .page-inner {

            width:
                min(
                    1450px,
                    100%
                );

            margin:
                0 auto;
        }

        /* =====================================================
           TOP BAR
        ===================================================== */

        .top-row {

            width: 100%;

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 15px;

            margin-bottom:
                18px;
        }

        /* =====================================================
           CLOSE BUTTON
        ===================================================== */

        .close-btn {

            width: 43px;

            height: 43px;

            display: inline-flex;

            align-items: center;

            justify-content: center;

            flex-shrink: 0;

            border-radius: 11px;

            border:
                1px solid
                var(--border-light);

            background:
                rgba(255,255,255,0.035);

            color:
                #ffffff;

            text-decoration: none;

            transition:
                0.2s ease;

            box-shadow:
                0 8px 25px
                rgba(0,0,0,0.18);
        }

        .close-btn svg {

            width: 19px;

            height: 19px;

            stroke:
                currentColor;
        }

        .close-btn:hover {

            color:
                var(--neon);

            border-color:
                var(--neon-border);

            background:
                var(--neon-soft);

            transform:
                translateY(-1px);
        }

        /* =====================================================
           DOWNLOAD BUTTON
        ===================================================== */

        .download-btn {

            min-height: 43px;

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 8px;

            padding:
                0 17px;

            border-radius: 10px;

            background:
                var(--neon);

            color:
                #000000;

            text-decoration: none;

            font-size:
                0.78rem;

            font-weight:
                800;

            letter-spacing:
                0.01em;

            box-shadow:
                0 0 20px
                rgba(57,255,20,0.10);

            transition:
                0.2s ease;
        }

        .download-btn svg {

            width: 17px;

            height: 17px;

            stroke:
                currentColor;
        }

        .download-btn:hover {

            transform:
                translateY(-1px);

            box-shadow:
                0 0 28px
                rgba(57,255,20,0.22);
        }

        .download-btn:active {

            transform:
                translateY(0);
        }

        /* =====================================================
           NOTE CARD
        ===================================================== */

        .note-card {

            width: 100%;

            overflow: hidden;

            background:
                var(--card);

            border:
                1px solid
                var(--border);

            border-radius:
                17px;

            box-shadow:
                0 20px 65px
                rgba(0,0,0,0.32);
        }

        /* =====================================================
           NOTE HEADER
        ===================================================== */

        .note-header {

            padding:
                23px 24px;

            border-bottom:
                1px solid
                var(--border);
        }

        .note-title {

            margin: 0;

            color:
                #ffffff;

            font-size:
                clamp(
                    1.05rem,
                    2vw,
                    1.45rem
                );

            line-height:
                1.35;

            font-weight:
                800;

            word-break:
                break-word;
        }

        .note-description {

            margin:
                8px 0 0;

            color:
                var(--sub);

            font-size:
                0.82rem;

            line-height:
                1.65;

            word-break:
                break-word;
        }

        /* =====================================================
           META
        ===================================================== */

        .note-meta {

            display: flex;

            align-items: center;

            flex-wrap: wrap;

            gap: 8px;

            margin-top:
                14px;
        }

        .meta-pill {

            display: inline-flex;

            align-items: center;

            min-height:
                28px;

            padding:
                0 10px;

            border-radius:
                8px;

            border:
                1px solid
                #252525;

            background:
                rgba(255,255,255,0.025);

            color:
                #b9c0ca;

            font-size:
                0.66rem;

            font-weight:
                700;

            max-width: 100%;

            word-break:
                break-word;
        }

        .meta-pill.green {

            color:
                var(--neon);

            border-color:
                var(--neon-border);

            background:
                var(--neon-soft);
        }

        /* =====================================================
           VIEWER WRAPPER
        ===================================================== */

        .viewer-wrapper {

            padding:
                18px;

            background:
                #070707;
        }

        .viewer-box {

            width: 100%;

            min-height:
                65vh;

            position: relative;

            overflow: hidden;

            border:
                1px solid
                #222;

            border-radius:
                12px;

            background:
                #111;
        }

        /* =====================================================
           LOADING
        ===================================================== */

        .viewer-loading {

            position: absolute;

            inset: 0;

            z-index: 5;

            display: flex;

            align-items: center;

            justify-content: center;

            flex-direction: column;

            gap: 12px;

            padding: 25px;

            text-align: center;

            background:
                #101010;

            color:
                var(--sub);

            font-size:
                0.8rem;
        }

        .viewer-loading small {

            max-width:
                420px;

            color:
                #64748b;

            line-height:
                1.6;
        }

        .spinner {

            width:
                34px;

            height:
                34px;

            border-radius:
                50%;

            border:
                3px solid
                #303030;

            border-top-color:
                var(--neon);

            animation:
                spin 0.8s linear infinite;
        }

        @keyframes spin {

            to {
                transform:
                    rotate(360deg);
            }

        }

        /* =====================================================
           IFRAME
        ===================================================== */

        iframe {

            display:
                block;

            width: 100%;

            height:
                75vh;

            min-height:
                520px;

            border: 0;

            background:
                #ffffff;
        }

        /* =====================================================
           IMAGE
        ===================================================== */

        .image-preview {

            width: 100%;

            min-height:
                65vh;

            display: flex;

            align-items: center;

            justify-content: center;

            padding:
                25px;

            background:
                #080808;
        }

        .image-preview img {

            display: block;

            max-width:
                100%;

            max-height:
                72vh;

            object-fit:
                contain;

            border-radius:
                8px;
        }

        /* =====================================================
           MEDIA
        ===================================================== */

        .media-preview {

            width: 100%;

            min-height:
                65vh;

            display: flex;

            align-items: center;

            justify-content: center;

            padding:
                25px;

            background:
                #080808;
        }

        .media-preview audio,
        .media-preview video {

            width:
                min(
                    1000px,
                    100%
                );

            max-height:
                75vh;
        }

        /* =====================================================
           TEXT
        ===================================================== */

        .text-preview {

            width: 100%;

            min-height:
                65vh;

            padding:
                25px;

            background:
                #101010;

            overflow:
                auto;
        }

        .text-preview pre {

            margin: 0;

            white-space:
                pre-wrap;

            word-break:
                break-word;

            font-family:
                Consolas,
                "Courier New",
                monospace;

            color:
                #e5e7eb;

            font-size:
                0.88rem;

            line-height:
                1.7;
        }

        /* =====================================================
           UNSUPPORTED
        ===================================================== */

        .unsupported {

            min-height:
                65vh;

            display: flex;

            align-items: center;

            justify-content: center;

            padding:
                30px;

            text-align:
                center;
        }

        .unsupported-inner {

            width:
                min(
                    450px,
                    100%
                );
        }

        .file-icon {

            width:
                70px;

            height:
                70px;

            margin:
                0 auto 16px;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius:
                18px;

            background:
                var(--neon-soft);

            border:
                1px solid
                var(--neon-border);

            font-size:
                32px;
        }

        .unsupported h2 {

            margin:
                0 0 10px;

            font-size:
                1.15rem;

            font-weight:
                800;
        }

        .unsupported p {

            margin:
                6px 0;

            color:
                var(--sub);

            font-size:
                0.82rem;

            line-height:
                1.6;
        }

        .unsupported strong {

            color:
                #e5e7eb;

            word-break:
                break-word;
        }

        /* =====================================================
           FOOTER
        ===================================================== */

        .note-footer {

            padding:
                17px 24px;

            border-top:
                1px solid
                var(--border);

            display: flex;

            align-items: center;

            justify-content: flex-start;
        }

        .footer-info {

            color:
                var(--sub);

            font-size:
                0.7rem;

            line-height:
                1.7;

            word-break:
                break-word;
        }

        .footer-info strong {

            color:
                #d9dee6;
        }

        /* =====================================================
           TABLET
        ===================================================== */

        @media(max-width: 900px) {

            .page {

                padding:
                    18px;
            }

            iframe {

                height:
                    68vh;

                min-height:
                    450px;
            }

            .viewer-wrapper {

                padding:
                    10px;
            }

        }

        /* =====================================================
           MOBILE
        ===================================================== */

        @media(max-width: 600px) {

            .page {

                padding:
                    10px;
            }

            .top-row {

                margin-bottom:
                    10px;
            }

            .close-btn {

                width:
                    41px;

                height:
                    41px;

                border-radius:
                    10px;
            }

            .download-btn {

                min-height:
                    41px;

                padding:
                    0 13px;

                font-size:
                    0.7rem;

                border-radius:
                    9px;
            }

            .note-card {

                border-radius:
                    13px;
            }

            .note-header {

                padding:
                    17px 15px;
            }

            .note-title {

                font-size:
                    1rem;

                line-height:
                    1.4;
            }

            .note-description {

                font-size:
                    0.75rem;
            }

            .note-meta {

                gap:
                    6px;

                margin-top:
                    12px;
            }

            .meta-pill {

                min-height:
                    26px;

                padding:
                    0 8px;

                font-size:
                    0.59rem;
            }

            .viewer-wrapper {

                padding:
                    7px;
            }

            .viewer-box {

                min-height:
                    55vh;

                border-radius:
                    9px;
            }

            iframe {

                height:
                    65vh;

                min-height:
                    400px;
            }

            .image-preview {

                min-height:
                    55vh;

                padding:
                    12px;
            }

            .media-preview {

                min-height:
                    55vh;

                padding:
                    12px;
            }

            .text-preview {

                min-height:
                    55vh;

                padding:
                    15px;
            }

            .text-preview pre {

                font-size:
                    0.76rem;
            }

            .unsupported {

                min-height:
                    55vh;

                padding:
                    25px 15px;
            }

            .unsupported h2 {

                font-size:
                    1rem;
            }

            .unsupported p {

                font-size:
                    0.75rem;
            }

            .note-footer {

                padding:
                    14px 15px;
            }

        }

        /* =====================================================
           VERY SMALL MOBILE
        ===================================================== */

        @media(max-width: 380px) {

            .page {

                padding:
                    8px;
            }

            .download-btn {

                padding:
                    0 11px;
            }

            .note-header {

                padding:
                    15px 13px;
            }

            .viewer-wrapper {

                padding:
                    5px;
            }

            iframe {

                min-height:
                    360px;

                height:
                    62vh;
            }

        }

        /* =====================================================
           SCROLLBAR
        ===================================================== */

        ::-webkit-scrollbar {

            width:
                7px;

            height:
                7px;
        }

        ::-webkit-scrollbar-track {

            background:
                #080808;
        }

        ::-webkit-scrollbar-thumb {

            background:
                #2d2d2d;

            border-radius:
                20px;
        }

        ::-webkit-scrollbar-thumb:hover {

            background:
                #454545;
        }

    </style>

</head>

<body>

<!-- ========================================================
     PAGE
========================================================= -->

<div class="page">

    <div class="page-inner">

        <!-- =================================================
             TOP BAR
        ================================================== -->

        <div class="top-row">

            <!-- X / CLOSE -->

            <a
                href="student_home.php"
                class="close-btn"
                aria-label="Back to Dashboard"
                title="Back to Dashboard"
            >

                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2.2"
                    stroke-linecap="round"
                >

                    <line
                        x1="6"
                        y1="6"
                        x2="18"
                        y2="18"
                    />

                    <line
                        x1="18"
                        y1="6"
                        x2="6"
                        y2="18"
                    />

                </svg>

            </a>

            <!-- ONLY DOWNLOAD BUTTON -->

            <a
                href="<?php echo htmlspecialchars(
                    $download_url,
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>"
                class="download-btn"
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
                        d="M12 3v12"
                    />

                    <path
                        d="m7 10 5 5 5-5"
                    />

                    <path
                        d="M5 21h14"
                    />

                </svg>

                DOWNLOAD

            </a>

        </div>

        <!-- =================================================
             NOTE CARD
        ================================================== -->

        <section class="note-card">

            <!-- =============================================
                 NOTE INFORMATION
            ============================================== -->

            <div class="note-header">

                <h1 class="note-title">
                    <?php echo $title; ?>
                </h1>

                <?php if ($description !== ''): ?>

                    <p class="note-description">
                        <?php echo $description; ?>
                    </p>

                <?php endif; ?>

                <div class="note-meta">

                    <span class="meta-pill green">
                        <?php echo $institute; ?>
                    </span>

                    <span class="meta-pill">

                        <?php
                        echo strtoupper(
                            htmlspecialchars(
                                $extension,
                                ENT_QUOTES,
                                'UTF-8'
                            )
                        );
                        ?>

                    </span>

                    <span class="meta-pill">

                        Uploaded by:
                        <?php echo $uploader; ?>

                    </span>

                    <span class="meta-pill">

                        <?php echo $created_at; ?>

                    </span>

                </div>

            </div>

            <!-- =============================================
                 VIEWER
            ============================================== -->

            <div class="viewer-wrapper">

                <div class="viewer-box">

                    <?php if ($is_office): ?>

                        <!-- =================================
                             OFFICE FILE
                        ================================== -->

                        <div
                            class="viewer-loading"
                            id="officeLoading"
                        >

                            <div class="spinner"></div>

                            <div>
                                Preparing document preview...
                            </div>

                            <small>
                                LibreOffice is converting this
                                document to PDF for preview.
                            </small>

                        </div>

                        <iframe
                            src="<?php echo htmlspecialchars(
                                $preview_url,
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>"
                            title="Office document preview"
                            onload="hideViewerLoading()"
                        ></iframe>

                    <?php elseif ($extension === 'pdf'): ?>

                        <!-- =================================
                             PDF
                        ================================== -->

                        <div
                            class="viewer-loading"
                            id="pdfLoading"
                        >

                            <div class="spinner"></div>

                            <div>
                                Loading PDF preview...
                            </div>

                        </div>

                        <iframe
                            src="<?php echo htmlspecialchars(
                                $preview_url,
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>"
                            title="PDF preview"
                            onload="hideViewerLoading()"
                        ></iframe>

                    <?php elseif (
                        in_array(
                            $extension,
                            [
                                'jpg',
                                'jpeg',
                                'png',
                                'gif',
                                'webp',
                                'bmp',
                                'svg'
                            ],
                            true
                        )
                    ): ?>

                        <!-- =================================
                             IMAGE
                        ================================== -->

                        <div class="image-preview">

                            <img
                                src="<?php echo htmlspecialchars(
                                    $preview_url,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>"
                                alt="<?php echo $title; ?>"
                            >

                        </div>

                    <?php elseif (
                        in_array(
                            $extension,
                            [
                                'mp3',
                                'wav',
                                'ogg',
                                'mp4',
                                'webm',
                                'mov'
                            ],
                            true
                        )
                    ): ?>

                        <!-- =================================
                             MEDIA
                        ================================== -->

                        <div class="media-preview">

                            <?php if (
                                in_array(
                                    $extension,
                                    [
                                        'mp3',
                                        'wav',
                                        'ogg'
                                    ],
                                    true
                                )
                            ): ?>

                                <audio
                                    controls
                                    preload="metadata"
                                >

                                    <source
                                        src="<?php echo htmlspecialchars(
                                            $preview_url,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); ?>"
                                        type="<?php echo htmlspecialchars(
                                            $mime_type,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); ?>"
                                    >

                                    Your browser does not support
                                    audio playback.

                                </audio>

                            <?php else: ?>

                                <video
                                    controls
                                    preload="metadata"
                                >

                                    <source
                                        src="<?php echo htmlspecialchars(
                                            $preview_url,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); ?>"
                                        type="<?php echo htmlspecialchars(
                                            $mime_type,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); ?>"
                                    >

                                    Your browser does not support
                                    video playback.

                                </video>

                            <?php endif; ?>

                        </div>

                    <?php elseif (
                        in_array(
                            $extension,
                            [
                                'txt',
                                'csv',
                                'log',
                                'md'
                            ],
                            true
                        )
                    ): ?>

                        <!-- =================================
                             TEXT
                        ================================== -->

                        <?php

                        $text_content =
                            @file_get_contents(
                                $actual_file
                            );

                        if (
                            $text_content === false
                        ) {

                            $text_content =
                                'Unable to read this text file.';
                        }

                        ?>

                        <div class="text-preview">

                            <pre><?php
                                echo htmlspecialchars(
                                    $text_content,
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                            ?></pre>

                        </div>

                    <?php else: ?>

                        <!-- =================================
                             UNSUPPORTED
                        ================================== -->

                        <div class="unsupported">

                            <div class="unsupported-inner">

                                <div class="file-icon">
                                    📄
                                </div>

                                <h2>
                                    Preview Not Available
                                </h2>

                                <p>
                                    This file type cannot be
                                    previewed directly inside
                                    the browser.
                                </p>

                                <p>
                                    <strong>
                                        <?php echo $safe_file_name; ?>
                                    </strong>
                                </p>

                                <p>
                                    Use the
                                    <strong>
                                        DOWNLOAD
                                    </strong>
                                    button above to get the
                                    original file.
                                </p>

                            </div>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

            <!-- =============================================
                 FOOTER INFORMATION
            ============================================== -->

            <div class="note-footer">

                <div class="footer-info">

                    <strong>
                        File:
                    </strong>

                    <?php echo $safe_file_name; ?>

                    <br>

                    <strong>
                        Status:
                    </strong>

                    <span
                        style="color:var(--neon);"
                    >
                        Approved
                    </span>

                </div>

            </div>

        </section>

    </div>

</div>

<script>

// =========================================================
// HIDE VIEWER LOADING
// =========================================================

function hideViewerLoading() {

    const officeLoading =
        document.getElementById(
            'officeLoading'
        );

    const pdfLoading =
        document.getElementById(
            'pdfLoading'
        );

    if (officeLoading) {

        setTimeout(
            function() {

                officeLoading.style.display =
                    'none';

            },
            500
        );
    }

    if (pdfLoading) {

        setTimeout(
            function() {

                pdfLoading.style.display =
                    'none';

            },
            500
        );
    }
}

// =========================================================
// PAGE READY
// =========================================================

document.addEventListener(
    'DOMContentLoaded',
    function() {

        // Prevent accidental double-tap zoom
        // on the top controls on mobile.

        const controls =
            document.querySelectorAll(
                '.close-btn, .download-btn'
            );

        controls.forEach(
            function(control) {

                control.addEventListener(
                    'touchstart',
                    function() {},
                    {
                        passive: true
                    }
                );

            }
        );

    }
);

</script>

</body>

</html>