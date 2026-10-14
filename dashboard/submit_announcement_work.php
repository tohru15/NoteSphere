<?php

session_start();

require_once('../includes/db_config.php');

/** @var PDO $conn */

// =========================================================
// SECURITY CHECK
// =========================================================

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'student'
) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];

// =========================================================
// ONLY ALLOW POST REQUEST
// =========================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: student_dashboard.php");
    exit();
}

// =========================================================
// GET FORM DATA
// =========================================================

$announcement_id = isset($_POST['announcement_id'])
    ? (int) $_POST['announcement_id']
    : 0;

$student_note = trim($_POST['student_note'] ?? '');

// =========================================================
// BASIC VALIDATION
// =========================================================

if ($announcement_id <= 0) {

    $_SESSION['error_message'] = 'Invalid announcement selected.';

    header("Location: student_dashboard.php");
    exit();
}

// =========================================================
// CHECK ANNOUNCEMENT
// =========================================================

$announcement_sql = "
    SELECT
        id,
        professor_id,
        title,
        year_level,
        section,
        subject,
        deadline
    FROM announcements
    WHERE id = ?
    LIMIT 1
";

$announcement_stmt = $conn->prepare($announcement_sql);
$announcement_stmt->execute([$announcement_id]);

$announcement = $announcement_stmt->fetch(PDO::FETCH_ASSOC);

if (!$announcement) {

    $_SESSION['error_message'] = 'The announcement could not be found.';

    header("Location: student_dashboard.php");
    exit();
}

// =========================================================
// CHECK UPLOADED FILE
// =========================================================

if (
    !isset($_FILES['work_file']) ||
    !is_array($_FILES['work_file'])
) {

    $_SESSION['error_message'] = 'Please select a file to upload.';

    header(
        "Location: student_dashboard.php?prof_id=" .
        (int)$announcement['professor_id']
    );

    exit();
}

$file = $_FILES['work_file'];

// =========================================================
// CHECK UPLOAD ERROR
// =========================================================

if ($file['error'] !== UPLOAD_ERR_OK) {

    $upload_errors = [

        UPLOAD_ERR_INI_SIZE =>
            'The uploaded file is too large.',

        UPLOAD_ERR_FORM_SIZE =>
            'The uploaded file is too large.',

        UPLOAD_ERR_PARTIAL =>
            'The file was only partially uploaded.',

        UPLOAD_ERR_NO_FILE =>
            'Please select a file.',

        UPLOAD_ERR_NO_TMP_DIR =>
            'Server upload folder is missing.',

        UPLOAD_ERR_CANT_WRITE =>
            'The server could not save the uploaded file.',

        UPLOAD_ERR_EXTENSION =>
            'The upload was stopped by a server extension.'
    ];

    $_SESSION['error_message'] =
        $upload_errors[$file['error']]
        ?? 'An unknown upload error occurred.';

    header(
        "Location: student_dashboard.php?prof_id=" .
        (int)$announcement['professor_id']
    );

    exit();
}

// =========================================================
// FILE INFORMATION
// =========================================================

$original_name = $file['name'];
$tmp_name      = $file['tmp_name'];
$file_size     = (int)$file['size'];

$extension = strtolower(
    pathinfo($original_name, PATHINFO_EXTENSION)
);

// =========================================================
// ALLOWED FILE TYPES
// =========================================================

$allowed_extensions = [
    'pdf',
    'docx',
    'zip'
];

if (!in_array($extension, $allowed_extensions, true)) {

    $_SESSION['error_message'] =
        'Invalid file type. Only PDF, DOCX, and ZIP files are allowed.';

    header(
        "Location: student_dashboard.php?prof_id=" .
        (int)$announcement['professor_id']
    );

    exit();
}

// =========================================================
// FILE SIZE LIMIT
// =========================================================
// 50 MB maximum
// =========================================================

$max_file_size = 50 * 1024 * 1024;

if ($file_size <= 0) {

    $_SESSION['error_message'] =
        'The uploaded file is empty.';

    header(
        "Location: student_dashboard.php?prof_id=" .
        (int)$announcement['professor_id']
    );

    exit();
}

if ($file_size > $max_file_size) {

    $_SESSION['error_message'] =
        'File is too large. Maximum allowed size is 50 MB.';

    header(
        "Location: student_dashboard.php?prof_id=" .
        (int)$announcement['professor_id']
    );

    exit();
}

// =========================================================
// CHECK REAL UPLOADED FILE
// =========================================================

if (!is_uploaded_file($tmp_name)) {

    $_SESSION['error_message'] =
        'Invalid file upload.';

    header(
        "Location: student_dashboard.php?prof_id=" .
        (int)$announcement['professor_id']
    );

    exit();
}

// =========================================================
// CREATE UPLOAD DIRECTORY
// =========================================================

$upload_dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads';

if (!is_dir($upload_dir)) {

    if (!mkdir($upload_dir, 0755, true)) {

        $_SESSION['error_message'] =
            'The upload folder could not be created.';

        header(
            "Location: student_dashboard.php?prof_id=" .
            (int)$announcement['professor_id']
        );

        exit();
    }
}

// =========================================================
// GENERATE SAFE UNIQUE FILE NAME
// =========================================================

$random_name = bin2hex(random_bytes(16));

$new_filename =
    'submission_' .
    $user_id .
    '_' .
    $announcement_id .
    '_' .
    $random_name .
    '.' .
    $extension;

$destination =
    $upload_dir .
    DIRECTORY_SEPARATOR .
    $new_filename;

// =========================================================
// CHECK EXISTING SUBMISSION
// =========================================================

$existing_sql = "
    SELECT
        id,
        file_path
    FROM announcement_submissions
    WHERE announcement_id = ?
      AND student_id = ?
    ORDER BY id DESC
    LIMIT 1
";

$existing_stmt = $conn->prepare($existing_sql);
$existing_stmt->execute([
    $announcement_id,
    $user_id
]);

$existing_submission = $existing_stmt->fetch(PDO::FETCH_ASSOC);

// =========================================================
// MOVE FILE TO UPLOAD DIRECTORY
// =========================================================

if (!move_uploaded_file($tmp_name, $destination)) {

    $_SESSION['error_message'] =
        'The file could not be saved on the server.';

    header(
        "Location: student_dashboard.php?prof_id=" .
        (int)$announcement['professor_id']
    );

    exit();
}

// =========================================================
// DATABASE TRANSACTION
// =========================================================

try {

    $conn->beginTransaction();

    // -----------------------------------------------------
    // IF EXISTING SUBMISSION
    // -----------------------------------------------------

    if ($existing_submission) {

        $submission_id = (int)$existing_submission['id'];

        // -------------------------------------------------
        // Delete old physical file
        // -------------------------------------------------

        $old_file = trim(
            $existing_submission['file_path'] ?? ''
        );

        if ($old_file !== '') {

            $old_file_path =
                $upload_dir .
                DIRECTORY_SEPARATOR .
                basename($old_file);

            if (
                is_file($old_file_path) &&
                realpath($old_file_path) !== realpath($destination)
            ) {
                @unlink($old_file_path);
            }
        }

        // -------------------------------------------------
        // Update existing submission
        // -------------------------------------------------

        $update_sql = "
            UPDATE announcement_submissions
            SET
                file_path = ?,
                student_note = ?,
                status = 'submitted',
                submitted_at = NOW()
            WHERE id = ?
              AND student_id = ?
        ";

        $update_stmt = $conn->prepare($update_sql);

        $update_stmt->execute([
            $new_filename,
            $student_note !== '' ? $student_note : null,
            $submission_id,
            $user_id
        ]);

    } else {

        // -------------------------------------------------
        // CREATE NEW SUBMISSION
        // -------------------------------------------------

        $insert_sql = "
            INSERT INTO announcement_submissions
            (
                announcement_id,
                student_id,
                submission_text,
                file_path,
                student_note,
                status,
                grade,
                feedback,
                submitted_at
            )
            VALUES
            (
                ?,
                ?,
                NULL,
                ?,
                ?,
                'submitted',
                NULL,
                NULL,
                NOW()
            )
        ";

        $insert_stmt = $conn->prepare($insert_sql);

        $insert_stmt->execute([
            $announcement_id,
            $user_id,
            $new_filename,
            $student_note !== '' ? $student_note : null
        ]);
    }

    // =====================================================
    // COMMIT
    // =====================================================

    $conn->commit();

    // =====================================================
    // SUCCESS MESSAGE
    // =====================================================

    $_SESSION['success_message'] =
        'Your work has been submitted successfully.';

} catch (Throwable $e) {

    // =====================================================
    // ROLLBACK
    // =====================================================

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    // Delete uploaded file if database failed
    if (is_file($destination)) {
        @unlink($destination);
    }

    $_SESSION['error_message'] =
        'Something went wrong while saving your submission.';

    // Optional for debugging:
    // error_log($e->getMessage());
}

// =========================================================
// RETURN TO PROFESSOR ANNOUNCEMENTS
// =========================================================

header(
    "Location: student_dashboard.php?prof_id=" .
    (int)$announcement['professor_id']
);

exit();
?>