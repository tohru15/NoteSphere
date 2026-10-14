<?php
// =========================================================
// NoteSphere - Admin Download Note
// ONE CLICK = ONE DOWNLOAD
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
    http_response_code(403);
    exit('Access denied.');
}

// =========================================================
// GET NOTE ID
// =========================================================

$noteId = filter_input(
    INPUT_GET,
    'note_id',
    FILTER_VALIDATE_INT
);

if (!$noteId || $noteId <= 0) {
    http_response_code(400);
    exit('Invalid note ID.');
}

// =========================================================
// FIND NOTE
// =========================================================

try {

    $stmt = $conn->prepare("
        SELECT
            id,
            title,
            file_path
        FROM notes
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$noteId]);

    $note = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    http_response_code(500);
    exit('Database error.');
}

// =========================================================
// NOTE NOT FOUND
// =========================================================

if (!$note) {
    http_response_code(404);
    exit('Note not found.');
}

// =========================================================
// FIND ACTUAL FILE
// =========================================================

function findNoteFile($storedPath)
{
    $projectRoot = dirname(__DIR__);

    if (empty($storedPath)) {
        return false;
    }

    // Remove query string
    $storedPath = explode('?', $storedPath, 2)[0];

    // Remove fragment
    $storedPath = explode('#', $storedPath, 2)[0];

    // Decode URL characters
    $storedPath = rawurldecode($storedPath);

    // Normalize slashes
    $storedPath = str_replace('\\', '/', $storedPath);

    // -----------------------------------------------------
    // If stored path is a URL
    // -----------------------------------------------------

    if (
        preg_match('/^https?:\/\//i', $storedPath) ||
        preg_match('/^\/\//', $storedPath)
    ) {
        $urlPath = parse_url($storedPath, PHP_URL_PATH);

        if ($urlPath) {
            $storedPath = $urlPath;
        }
    }

    // -----------------------------------------------------
    // Absolute Windows path
    // Example:
    // C:/xampp/htdocs/NoteSphere/uploads/file.pdf
    // -----------------------------------------------------

    if (
        preg_match('/^[A-Za-z]:\//', $storedPath) &&
        is_file($storedPath)
    ) {
        return realpath($storedPath);
    }

    // -----------------------------------------------------
    // Remove leading slash
    // -----------------------------------------------------

    $relativePath = ltrim($storedPath, '/');

    // -----------------------------------------------------
    // Direct path from NoteSphere root
    // -----------------------------------------------------

    $directPath =
        $projectRoot .
        DIRECTORY_SEPARATOR .
        str_replace(
            '/',
            DIRECTORY_SEPARATOR,
            $relativePath
        );

    if (is_file($directPath)) {
        return realpath($directPath);
    }

    // -----------------------------------------------------
    // Get filename only
    // -----------------------------------------------------

    $fileName = basename($storedPath);

    if (
        $fileName === '' ||
        $fileName === '.' ||
        $fileName === '..'
    ) {
        return false;
    }

    // -----------------------------------------------------
    // Known NoteSphere folders
    // -----------------------------------------------------

    $folders = [

        'uploads',

        'uploads/notes',

        'uploads/submissions',

        'uploads/messages',

        'uploads/announcements',

        'uploads/files',

        'uploads/documents',

        'storage',

        'files',

        'assets/uploads'

    ];

    foreach ($folders as $folder) {

        $candidate =
            $projectRoot .
            DIRECTORY_SEPARATOR .
            str_replace(
                '/',
                DIRECTORY_SEPARATOR,
                $folder
            ) .
            DIRECTORY_SEPARATOR .
            $fileName;

        if (is_file($candidate)) {
            return realpath($candidate);
        }
    }

    // -----------------------------------------------------
    // Recursive search
    // -----------------------------------------------------

    $searchDirectories = [

        $projectRoot . DIRECTORY_SEPARATOR . 'uploads',

        $projectRoot . DIRECTORY_SEPARATOR . 'storage',

        $projectRoot . DIRECTORY_SEPARATOR . 'files',

        $projectRoot . DIRECTORY_SEPARATOR . 'assets'

    ];

    foreach ($searchDirectories as $directory) {

        if (!is_dir($directory)) {
            continue;
        }

        try {

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $directory,
                    FilesystemIterator::SKIP_DOTS
                ),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($iterator as $fileInfo) {

                if (!$fileInfo->isFile()) {
                    continue;
                }

                if (
                    strcasecmp(
                        $fileInfo->getFilename(),
                        $fileName
                    ) === 0
                ) {
                    return $fileInfo->getRealPath();
                }
            }

        } catch (Throwable $e) {
            // Continue
        }
    }

    return false;
}

// =========================================================
// LOCATE FILE
// =========================================================

$realFile = findNoteFile($note['file_path']);

if (
    !$realFile ||
    !is_file($realFile)
) {
    http_response_code(404);
    exit('The note file could not be found on the server.');
}

// =========================================================
// SECURITY CHECK
// =========================================================

$realFile = realpath($realFile);
$projectRoot = realpath(dirname(__DIR__));

if (
    !$realFile ||
    !$projectRoot
) {
    http_response_code(404);
    exit('File not found.');
}

// Make sure the file is inside NoteSphere
$projectPrefix =
    strtolower(
        $projectRoot .
        DIRECTORY_SEPARATOR
    );

$realFileLower =
    strtolower($realFile);

if (
    strpos(
        $realFileLower,
        $projectPrefix
    ) !== 0
) {
    http_response_code(403);
    exit('Access denied.');
}

// =========================================================
// FILE INFORMATION
// =========================================================

$fileName = basename($realFile);

$fileSize = filesize($realFile);

$extension = strtolower(
    pathinfo(
        $fileName,
        PATHINFO_EXTENSION
    )
);

// =========================================================
// MIME TYPE
// =========================================================

$mimeType = 'application/octet-stream';

$mimeMap = [

    // PDF
    'pdf' =>
        'application/pdf',

    // Word
    'doc' =>
        'application/msword',

    'docx' =>
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',

    // Excel
    'xls' =>
        'application/vnd.ms-excel',

    'xlsx' =>
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',

    // PowerPoint
    'ppt' =>
        'application/vnd.ms-powerpoint',

    'pptx' =>
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',

    // OpenDocument
    'odt' =>
        'application/vnd.oasis.opendocument.text',

    'ods' =>
        'application/vnd.oasis.opendocument.spreadsheet',

    'odp' =>
        'application/vnd.oasis.opendocument.presentation',

    // Text
    'txt' =>
        'text/plain',

    'csv' =>
        'text/csv',

    // Images
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

    // Audio
    'mp3' =>
        'audio/mpeg',

    'wav' =>
        'audio/wav',

    // Video
    'mp4' =>
        'video/mp4',

    'webm' =>
        'video/webm'

];

if (isset($mimeMap[$extension])) {

    $mimeType =
        $mimeMap[$extension];

} elseif (function_exists('finfo_open')) {

    $finfo =
        finfo_open(
            FILEINFO_MIME_TYPE
        );

    if ($finfo) {

        $detected =
            finfo_file(
                $finfo,
                $realFile
            );

        if ($detected) {
            $mimeType = $detected;
        }

        finfo_close($finfo);
    }
}

// =========================================================
// CLEAN OUTPUT BUFFER
// =========================================================

while (
    ob_get_level() > 0
) {
    ob_end_clean();
}

// =========================================================
// HEADERS
// =========================================================

header(
    'Content-Type: ' .
    $mimeType
);

header(
    'Content-Disposition: attachment; filename="' .
    addcslashes(
        $fileName,
        '"\\'
    ) .
    '"'
);

header(
    'Content-Length: ' .
    $fileSize
);

header(
    'Content-Transfer-Encoding: binary'
);

header(
    'Accept-Ranges: bytes'
);

header(
    'Cache-Control: private, no-store, no-cache, must-revalidate'
);

header(
    'Pragma: public'
);

header(
    'Expires: 0'
);

// =========================================================
// SEND FILE
// =========================================================

$handle =
    fopen(
        $realFile,
        'rb'
    );

if ($handle === false) {

    http_response_code(500);

    exit(
        'Unable to open the file.'
    );
}

// =========================================================
// STREAM FILE ONCE
// =========================================================

while (!feof($handle)) {

    $chunk =
        fread(
            $handle,
            1024 * 1024
        );

    if ($chunk === false) {
        break;
    }

    echo $chunk;

    flush();
}

fclose($handle);

exit;