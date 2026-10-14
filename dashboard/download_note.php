<?php
session_start();

require_once '../includes/db_config.php';

/** @var PDO $conn */

// =========================================================
// CHECK LOGIN
// =========================================================
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Access denied.');
}

// =========================================================
// GET NOTE ID FROM URL
// Example:
// download_note.php?note_id=9
// =========================================================
$note_id = filter_input(INPUT_GET, 'note_id', FILTER_VALIDATE_INT);

if (!$note_id || $note_id <= 0) {
    http_response_code(400);
    exit('Invalid note ID.');
}

// =========================================================
// GET NOTE INFORMATION
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

    $stmt->execute([$note_id]);

    $note = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    http_response_code(500);
    exit('Database error.');

}

// =========================================================
// CHECK IF NOTE EXISTS
// =========================================================
if (!$note) {
    http_response_code(404);
    exit('Note not found.');
}

// =========================================================
// GET FILE PATH
// =========================================================
$file_path = trim((string)$note['file_path']);

if ($file_path === '') {
    http_response_code(404);
    exit('No file is attached to this note.');
}

// =========================================================
// FIND FILE
// =========================================================

$file_candidates = [];

/*
|--------------------------------------------------------------------------
| Possible location #1
|--------------------------------------------------------------------------
| If file_path is relative to dashboard
*/
$file_candidates[] =
    __DIR__ . DIRECTORY_SEPARATOR . ltrim($file_path, '/\\');

/*
|--------------------------------------------------------------------------
| Possible location #2
|--------------------------------------------------------------------------
| If file_path is relative to NoteSphere root
*/
$file_candidates[] =
    dirname(__DIR__) . DIRECTORY_SEPARATOR . ltrim($file_path, '/\\');

/*
|--------------------------------------------------------------------------
| Remove duplicate paths
|--------------------------------------------------------------------------
*/
$file_candidates = array_unique($file_candidates);

$real_file = null;

foreach ($file_candidates as $candidate) {

    $candidate = str_replace(
        ['/', '\\'],
        DIRECTORY_SEPARATOR,
        $candidate
    );

    if (is_file($candidate) && is_readable($candidate)) {

        $real_file = realpath($candidate);

        break;
    }
}

// =========================================================
// FILE NOT FOUND
// =========================================================
if ($real_file === false || $real_file === null) {

    http_response_code(404);

    exit(
        'The note file could not be found on the server.'
    );
}

// =========================================================
// SECURITY CHECK
// Make sure file is inside NoteSphere
// =========================================================

$noteSphereRoot = realpath(dirname(__DIR__));

if ($noteSphereRoot === false) {

    http_response_code(500);
    exit('System directory error.');
}

if (
    strpos(
        $real_file,
        $noteSphereRoot . DIRECTORY_SEPARATOR
    ) !== 0
) {

    http_response_code(403);
    exit('Invalid file location.');
}

// =========================================================
// GET FILE EXTENSION
// =========================================================

$extension = strtolower(
    pathinfo($real_file, PATHINFO_EXTENSION)
);

// =========================================================
// CREATE SAFE DOWNLOAD FILE NAME
// =========================================================

$title = trim((string)$note['title']);

if ($title === '') {
    $title = 'Note_' . $note_id;
}

// Remove unsafe characters
$safe_title = preg_replace(
    '/[^\p{L}\p{N}\-_ ]/u',
    '',
    $title
);

$safe_title = trim($safe_title);

if ($safe_title === '') {
    $safe_title = 'Note_' . $note_id;
}

// Add original extension
$download_name = $safe_title;

if ($extension !== '') {
    $download_name .= '.' . $extension;
}

// =========================================================
// MIME TYPES
// =========================================================

$mime_types = [

    'pdf' =>
        'application/pdf',

    'doc' =>
        'application/msword',

    'docx' =>
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',

    'ppt' =>
        'application/vnd.ms-powerpoint',

    'pptx' =>
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',

    'xls' =>
        'application/vnd.ms-excel',

    'xlsx' =>
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',

    'txt' =>
        'text/plain',

    'zip' =>
        'application/zip',

    'jpg' =>
        'image/jpeg',

    'jpeg' =>
        'image/jpeg',

    'png' =>
        'image/png',

    'webp' =>
        'image/webp'
];

$mime_type =
    $mime_types[$extension]
    ?? 'application/octet-stream';

// =========================================================
// CLEAR OUTPUT BUFFER
// =========================================================

while (ob_get_level()) {
    ob_end_clean();
}

// =========================================================
// DOWNLOAD HEADERS
// =========================================================

header('Content-Description: File Transfer');

header(
    'Content-Type: ' . $mime_type
);

header(
    'Content-Disposition: attachment; filename="' .
    $download_name .
    '"'
);

header(
    'Content-Length: ' .
    filesize($real_file)
);

header('Cache-Control: no-cache, must-revalidate');

header('Pragma: public');

header('Expires: 0');

// =========================================================
// SEND FILE
// =========================================================

readfile($real_file);

exit;
?>