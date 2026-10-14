<?php
session_start();

/*
=========================================================
    NoteSphere - Student Notes
    Storage:
    uploads/notes/
    uploads/notes/thumbnails/
=========================================================
*/

/* =========================================================
   DATABASE CONNECTION
========================================================= */

$host = 'localhost';
$db   = '1notesphere_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host={$host};dbname={$db};charset={$charset}";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}


/* =========================================================
   LOGIN CHECK
========================================================= */

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'student'
) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];


/* =========================================================
   CSRF
========================================================= */

if (empty($_SESSION['notesphere_csrf'])) {
    $_SESSION['notesphere_csrf'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['notesphere_csrf'];


/* =========================================================
   PROJECT / STORAGE DIRECTORIES
========================================================= */

$project_root = realpath(__DIR__ . '/..');

if (!$project_root) {
    die("NoteSphere project folder could not be located.");
}

$uploads_dir = $project_root .
    DIRECTORY_SEPARATOR .
    'uploads';

$notes_dir = $uploads_dir .
    DIRECTORY_SEPARATOR .
    'notes';

$notes_thumbnails_dir = $notes_dir .
    DIRECTORY_SEPARATOR .
    'thumbnails';


/* Create directories if needed */

if (!is_dir($uploads_dir)) {
    @mkdir($uploads_dir, 0777, true);
}

if (!is_dir($notes_dir)) {
    @mkdir($notes_dir, 0777, true);
}

if (!is_dir($notes_thumbnails_dir)) {
    @mkdir($notes_thumbnails_dir, 0777, true);
}


/* =========================================================
   ACTION VARIABLES
========================================================= */

$action_success = '';
$action_error   = '';
$action_type    = '';


/* =========================================================
   HELPER:
   GET PHYSICAL NOTE FILE PATH
========================================================= */

function get_note_file_physical_path($stored_path)
{
    global $project_root;
    global $uploads_dir;
    global $notes_dir;

    if (empty($stored_path)) {
        return null;
    }

    $stored_path = trim((string)$stored_path);
    $stored_path = trim($stored_path, "\"'");
    $stored_path = str_replace('\\', '/', $stored_path);

    /*
    ---------------------------------------------------------
    If database contains URL
    ---------------------------------------------------------
    */
    if (preg_match('#^https?://#i', $stored_path)) {

        $url_path = parse_url(
            $stored_path,
            PHP_URL_PATH
        );

        if ($url_path) {
            $stored_path = ltrim(
                $url_path,
                '/'
            );
        }
    }


    /*
    ---------------------------------------------------------
    If database contains Windows physical path
    ---------------------------------------------------------
    */
    if (preg_match('/^[A-Za-z]:\//', $stored_path)) {

        if (is_file($stored_path)) {
            return $stored_path;
        }

        $stored_path = basename(
            $stored_path
        );
    }


    /*
    ---------------------------------------------------------
    Remove ../ and ./
    ---------------------------------------------------------
    */
    while (
        strpos(
            $stored_path,
            '../'
        ) === 0
    ) {
        $stored_path = substr(
            $stored_path,
            3
        );
    }

    while (
        strpos(
            $stored_path,
            './'
        ) === 0
    ) {
        $stored_path = substr(
            $stored_path,
            2
        );
    }

    $stored_path = ltrim(
        $stored_path,
        '/'
    );


    /*
    ---------------------------------------------------------
    EXACT PROJECT PATH

    Example:
    uploads/notes/note_abc.pdf
    ---------------------------------------------------------
    */
    if (
        strpos(
            $stored_path,
            'uploads/'
        ) === 0
    ) {

        $exact_path =
            $project_root .
            DIRECTORY_SEPARATOR .
            str_replace(
                '/',
                DIRECTORY_SEPARATOR,
                $stored_path
            );

        if (is_file($exact_path)) {
            return $exact_path;
        }
    }


    /*
    ---------------------------------------------------------
    Try filename inside notes
    ---------------------------------------------------------
    */

    $filename = basename(
        $stored_path
    );

    $candidate =
        $notes_dir .
        DIRECTORY_SEPARATOR .
        $filename;

    if (is_file($candidate)) {
        return $candidate;
    }


    /*
    ---------------------------------------------------------
    Try old uploads root
    ---------------------------------------------------------
    */

    $candidate =
        $uploads_dir .
        DIRECTORY_SEPARATOR .
        $filename;

    if (is_file($candidate)) {
        return $candidate;
    }


    /*
    ---------------------------------------------------------
    Try old folders
    ---------------------------------------------------------
    */

    $folders = [

        $uploads_dir .
        DIRECTORY_SEPARATOR .
        'submissions',

        $uploads_dir .
        DIRECTORY_SEPARATOR .
        'announcements',

        $uploads_dir .
        DIRECTORY_SEPARATOR .
        'messages',

        $uploads_dir .
        DIRECTORY_SEPARATOR .
        'files',

        $uploads_dir .
        DIRECTORY_SEPARATOR .
        'documents',

        $project_root .
        DIRECTORY_SEPARATOR .
        'storage',

        $project_root .
        DIRECTORY_SEPARATOR .
        'files'

    ];


    foreach (
        $folders as $folder
    ) {

        $candidate =
            $folder .
            DIRECTORY_SEPARATOR .
            $filename;

        if (
            is_file($candidate)
        ) {
            return $candidate;
        }
    }


    /*
    ---------------------------------------------------------
    FINAL FALLBACK:
    Search project recursively
    ---------------------------------------------------------
    */

    try {

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

            if (!$file->isFile()) {
                continue;
            }

            if (
                strcasecmp(
                    $file->getFilename(),
                    $filename
                ) === 0
            ) {

                return $file->getPathname();
            }
        }

    } catch (Exception $e) {

        // Ignore recursive search error.

    }

    return null;
}


/* =========================================================
   DELETE NOTE FILE
========================================================= */

function delete_note_file($stored_path)
{
    $physical =
        get_note_file_physical_path(
            $stored_path
        );

    if (
        $physical &&
        is_file($physical)
    ) {

        @unlink($physical);
    }
}


/* =========================================================
   POST ACTIONS
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {

    $action =
        $_POST['action'] ?? '';

    $posted_csrf =
        $_POST['csrf_token'] ?? '';


    if (
        !hash_equals(
            $_SESSION['notesphere_csrf'],
            $posted_csrf
        )
    ) {

        $action_error =
            "Invalid request. Please try again.";

        $action_type = 'error';

    } else {


        /* =================================================
           QUICK UPLOAD
        ================================================= */

        if (
            $action === 'quick_upload'
        ) {

            $title =
                trim(
                    $_POST['title'] ?? ''
                );

            $description =
                trim(
                    $_POST['description'] ?? ''
                );

            $institute =
                trim(
                    $_POST['institute'] ?? 'General'
                );

            $submission_type =
                $_POST['submission_type'] ?? 'upload';


            $file_path = '';
            $thumbnail_name = '';

            $uploaded_file_physical = '';
            $uploaded_thumbnail_physical = '';


            if (
                empty($title)
            ) {

                $action_error =
                    "Note title is required.";

                $action_type =
                    'error';

            } else {


                /* =========================================
                   THUMBNAIL
                ========================================= */

                if (
                    isset($_FILES['thumbnail']) &&
                    $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK
                ) {

                    $thumb_tmp =
                        $_FILES['thumbnail']['tmp_name'];

                    $thumb_ext =
                        strtolower(
                            pathinfo(
                                $_FILES['thumbnail']['name'],
                                PATHINFO_EXTENSION
                            )
                        );

                    $allowed_img = [
                        'jpg',
                        'jpeg',
                        'png',
                        'webp'
                    ];


                    if (
                        in_array(
                            $thumb_ext,
                            $allowed_img,
                            true
                        )
                    ) {

                        $thumbnail_name =
                            'thumb_' .
                            bin2hex(
                                random_bytes(8)
                            ) .
                            '.' .
                            $thumb_ext;

                        $thumbnail_destination =
                            $notes_thumbnails_dir .
                            DIRECTORY_SEPARATOR .
                            $thumbnail_name;


                        if (
                            move_uploaded_file(
                                $thumb_tmp,
                                $thumbnail_destination
                            )
                        ) {

                            $uploaded_thumbnail_physical =
                                $thumbnail_destination;

                        } else {

                            $action_error =
                                "Failed to save thumbnail.";

                            $action_type =
                                'error';
                        }

                    } else {

                        $action_error =
                            "Invalid thumbnail format.";

                        $action_type =
                            'error';
                    }
                }


                /* =========================================
                   FILE / WRITE NOTE
                ========================================= */

                if (
                    empty($action_error)
                ) {

                    if (
                        $submission_type === 'upload'
                    ) {


                        if (
                            isset($_FILES['note_file']) &&
                            $_FILES['note_file']['error'] === UPLOAD_ERR_OK
                        ) {

                            $file_tmp =
                                $_FILES['note_file']['tmp_name'];

                            $file_name =
                                $_FILES['note_file']['name'];

                            $file_ext =
                                strtolower(
                                    pathinfo(
                                        $file_name,
                                        PATHINFO_EXTENSION
                                    )
                                );


                            $allowed_files = [
                                'pdf',
                                'doc',
                                'docx',
                                'ppt',
                                'pptx',
                                'xls',
                                'xlsx',
                                'txt',
                                'zip'
                            ];


                            if (
                                in_array(
                                    $file_ext,
                                    $allowed_files,
                                    true
                                )
                            ) {

                                $generated_filename =
                                    'note_' .
                                    bin2hex(
                                        random_bytes(8)
                                    ) .
                                    '.' .
                                    $file_ext;


                                $file_destination =
                                    $notes_dir .
                                    DIRECTORY_SEPARATOR .
                                    $generated_filename;


                                /*
                                IMPORTANT:
                                Database stores only relative path.
                                */

                                $file_path =
                                    'uploads/notes/' .
                                    $generated_filename;


                                if (
                                    move_uploaded_file(
                                        $file_tmp,
                                        $file_destination
                                    )
                                ) {

                                    if (
                                        is_file(
                                            $file_destination
                                        )
                                    ) {

                                        $uploaded_file_physical =
                                            $file_destination;

                                    } else {

                                        $action_error =
                                            "The uploaded file could not be verified.";

                                        $action_type =
                                            'error';
                                    }

                                } else {

                                    $action_error =
                                        "Failed to save the uploaded note file.";

                                    $action_type =
                                        'error';
                                }

                            } else {

                                $action_error =
                                    "Invalid file format. Allowed: PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX, TXT, ZIP.";

                                $action_type =
                                    'error';
                            }

                        } else {

                            $action_error =
                                "Please select a file to upload.";

                            $action_type =
                                'error';
                        }


                    } else {


                        /* =================================
                           WRITE NOTE
                        ================================= */

                        $note_content =
                            $_POST['note_content'] ?? '';


                        if (
                            trim($note_content) !== ''
                        ) {

                            $generated_filename =
                                'written_' .
                                bin2hex(
                                    random_bytes(8)
                                ) .
                                '.txt';


                            $written_destination =
                                $notes_dir .
                                DIRECTORY_SEPARATOR .
                                $generated_filename;


                            $file_path =
                                'uploads/notes/' .
                                $generated_filename;


                            if (
                                file_put_contents(
                                    $written_destination,
                                    $note_content
                                ) !== false
                            ) {

                                $uploaded_file_physical =
                                    $written_destination;

                            } else {

                                $action_error =
                                    "Failed to save your written note.";

                                $action_type =
                                    'error';
                            }

                        } else {

                            $action_error =
                                "Note content cannot be empty.";

                            $action_type =
                                'error';
                        }
                    }
                }


                /* =========================================
                   SAVE DATABASE
                ========================================= */

                if (
                    empty($action_error)
                ) {

                    try {

                        $stmt =
                            $pdo->prepare("
                                INSERT INTO notes
                                (
                                    user_id,
                                    title,
                                    description,
                                    institute,
                                    file_path,
                                    thumbnail,
                                    status,
                                    created_at
                                )
                                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                            ");


                        $thumbnail_db_path = '';


                        if (
                            !empty($thumbnail_name)
                        ) {

                            $thumbnail_db_path =
                                'uploads/notes/thumbnails/' .
                                $thumbnail_name;
                        }


                        $stmt->execute([
                            $user_id,
                            $title,
                            $description,
                            $institute,
                            $file_path,
                            $thumbnail_db_path,
                            'pending'
                        ]);


                        $action_success =
                            "Note successfully submitted for review!";

                        $action_type =
                            'upload_success';

                    } catch (
                        PDOException $e
                    ) {


                        if (
                            !empty($uploaded_file_physical) &&
                            is_file($uploaded_file_physical)
                        ) {

                            @unlink(
                                $uploaded_file_physical
                            );
                        }


                        if (
                            !empty($uploaded_thumbnail_physical) &&
                            is_file($uploaded_thumbnail_physical)
                        ) {

                            @unlink(
                                $uploaded_thumbnail_physical
                            );
                        }


                        $action_error =
                            "Database error occurred while saving your note.";

                        $action_type =
                            'error';
                    }

                } else {


                    if (
                        !empty($uploaded_file_physical) &&
                        is_file($uploaded_file_physical)
                    ) {

                        @unlink(
                            $uploaded_file_physical
                        );
                    }


                    if (
                        !empty($uploaded_thumbnail_physical) &&
                        is_file($uploaded_thumbnail_physical)
                    ) {

                        @unlink(
                            $uploaded_thumbnail_physical
                        );
                    }
                }
            }
        }


        /* =================================================
           RATE NOTE
        ================================================= */

        if (
            $action === 'rate_note'
        ) {

            $note_id =
                intval(
                    $_POST['note_id'] ?? 0
                );

            $rating =
                intval(
                    $_POST['rating'] ?? 0
                );


            if (
                $note_id > 0 &&
                $rating >= 1 &&
                $rating <= 5
            ) {

                $check_note =
                    $pdo->prepare("
                        SELECT id
                        FROM notes
                        WHERE id = ?
                        AND status = 'approved'
                        LIMIT 1
                    ");

                $check_note->execute([
                    $note_id
                ]);


                if (
                    $check_note->fetch()
                ) {

                    try {

                        $stmt =
                            $pdo->prepare("
                                INSERT INTO note_ratings
                                (
                                    note_id,
                                    user_id,
                                    rating
                                )
                                VALUES (?, ?, ?)
                                ON DUPLICATE KEY UPDATE
                                rating = ?
                            ");


                        $stmt->execute([
                            $note_id,
                            $user_id,
                            $rating,
                            $rating
                        ]);


                        $action_success =
                            "Your rating has been saved.";

                        $action_type =
                            'rating_success';

                    } catch (
                        PDOException $e
                    ) {

                        $action_error =
                            "Unable to save your rating.";

                        $action_type =
                            'error';
                    }

                } else {

                    $action_error =
                        "This note is no longer available.";

                    $action_type =
                        'error';
                }
            }
        }


        /* =================================================
           DELETE NOTE
        ================================================= */

        if (
            $action === 'delete_note'
        ) {

            $note_id =
                intval(
                    $_POST['note_id'] ?? 0
                );


            if (
                $note_id > 0
            ) {

                $stmt =
                    $pdo->prepare("
                        SELECT
                            id,
                            user_id,
                            title,
                            file_path,
                            thumbnail
                        FROM notes
                        WHERE id = ?
                        AND user_id = ?
                        LIMIT 1
                    ");


                $stmt->execute([
                    $note_id,
                    $user_id
                ]);


                $note =
                    $stmt->fetch();


                if (
                    $note
                ) {

                    try {

                        $pdo->beginTransaction();


                        $rating_delete =
                            $pdo->prepare("
                                DELETE FROM note_ratings
                                WHERE note_id = ?
                            ");

                        $rating_delete->execute([
                            $note_id
                        ]);


                        $delete_stmt =
                            $pdo->prepare("
                                DELETE FROM notes
                                WHERE id = ?
                                AND user_id = ?
                            ");

                        $delete_stmt->execute([
                            $note_id,
                            $user_id
                        ]);


                        $pdo->commit();


                        delete_note_file(
                            $note['file_path']
                        );

                        delete_note_file(
                            $note['thumbnail']
                        );


                        $action_success =
                            "Your note has been deleted permanently.";

                        $action_type =
                            'delete_success';

                    } catch (
                        PDOException $e
                    ) {

                        if (
                            $pdo->inTransaction()
                        ) {

                            $pdo->rollBack();
                        }


                        $action_error =
                            "Unable to delete the note.";

                        $action_type =
                            'error';
                    }

                } else {

                    $action_error =
                        "You can only delete your own notes.";

                    $action_type =
                        'error';
                }
            }
        }


        /* =================================================
           EDIT NOTE
        ================================================= */

        if (
            $action === 'edit_note'
        ) {

            $note_id =
                intval(
                    $_POST['note_id'] ?? 0
                );

            $title =
                trim(
                    $_POST['edit_title'] ?? ''
                );

            $description =
                trim(
                    $_POST['edit_description'] ?? ''
                );

            $institute =
                trim(
                    $_POST['edit_institute'] ?? 'General'
                );


            if (
                $note_id <= 0
            ) {

                $action_error =
                    "Invalid note.";

                $action_type =
                    'error';

            } elseif (
                empty($title)
            ) {

                $action_error =
                    "Note title is required.";

                $action_type =
                    'error';

            } else {


                $stmt =
                    $pdo->prepare("
                        SELECT
                            id,
                            user_id,
                            file_path,
                            thumbnail
                        FROM notes
                        WHERE id = ?
                        AND user_id = ?
                        LIMIT 1
                    ");


                $stmt->execute([
                    $note_id,
                    $user_id
                ]);


                $existing =
                    $stmt->fetch();


                if (
                    !$existing
                ) {

                    $action_error =
                        "You can only edit your own notes.";

                    $action_type =
                        'error';

                } else {


                    $old_file =
                        $existing['file_path'];

                    $old_thumbnail =
                        $existing['thumbnail'];


                    $new_file_path =
                        $old_file;

                    $new_thumbnail =
                        $old_thumbnail;


                    $new_uploaded_file = '';
                    $new_uploaded_thumbnail = '';
                    $edit_error = '';


                    /* =====================================
                       REPLACE FILE
                    ===================================== */

                    if (
                        isset($_FILES['edit_note_file']) &&
                        $_FILES['edit_note_file']['error'] !== UPLOAD_ERR_NO_FILE
                    ) {

                        if (
                            $_FILES['edit_note_file']['error'] !== UPLOAD_ERR_OK
                        ) {

                            $edit_error =
                                "There was a problem uploading the new file.";

                        } else {

                            $file_name =
                                $_FILES['edit_note_file']['name'];

                            $file_ext =
                                strtolower(
                                    pathinfo(
                                        $file_name,
                                        PATHINFO_EXTENSION
                                    )
                                );


                            $allowed_files = [
                                'pdf',
                                'doc',
                                'docx',
                                'ppt',
                                'pptx',
                                'xls',
                                'xlsx',
                                'txt',
                                'zip'
                            ];


                            if (
                                in_array(
                                    $file_ext,
                                    $allowed_files,
                                    true
                                )
                            ) {

                                $generated_filename =
                                    'note_' .
                                    bin2hex(
                                        random_bytes(8)
                                    ) .
                                    '.' .
                                    $file_ext;


                                $new_file_destination =
                                    $notes_dir .
                                    DIRECTORY_SEPARATOR .
                                    $generated_filename;


                                $new_file_path =
                                    'uploads/notes/' .
                                    $generated_filename;


                                if (
                                    move_uploaded_file(
                                        $_FILES['edit_note_file']['tmp_name'],
                                        $new_file_destination
                                    )
                                ) {

                                    $new_uploaded_file =
                                        $new_file_destination;

                                } else {

                                    $edit_error =
                                        "Failed to save the new file.";
                                }

                            } else {

                                $edit_error =
                                    "Invalid file format. Allowed: PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX, TXT, ZIP.";
                            }
                        }
                    }


                    /* =====================================
                       REPLACE THUMBNAIL
                    ===================================== */

                    if (
                        empty($edit_error) &&
                        isset($_FILES['edit_thumbnail']) &&
                        $_FILES['edit_thumbnail']['error'] !== UPLOAD_ERR_NO_FILE
                    ) {

                        if (
                            $_FILES['edit_thumbnail']['error'] !== UPLOAD_ERR_OK
                        ) {

                            $edit_error =
                                "There was a problem uploading the new thumbnail.";

                        } else {

                            $thumb_ext =
                                strtolower(
                                    pathinfo(
                                        $_FILES['edit_thumbnail']['name'],
                                        PATHINFO_EXTENSION
                                    )
                                );


                            $allowed_img = [
                                'jpg',
                                'jpeg',
                                'png',
                                'webp'
                            ];


                            if (
                                in_array(
                                    $thumb_ext,
                                    $allowed_img,
                                    true
                                )
                            ) {

                                $new_thumbnail_name =
                                    'thumb_' .
                                    bin2hex(
                                        random_bytes(8)
                                    ) .
                                    '.' .
                                    $thumb_ext;


                                $new_thumbnail_destination =
                                    $notes_thumbnails_dir .
                                    DIRECTORY_SEPARATOR .
                                    $new_thumbnail_name;


                                $new_thumbnail =
                                    'uploads/notes/thumbnails/' .
                                    $new_thumbnail_name;


                                if (
                                    move_uploaded_file(
                                        $_FILES['edit_thumbnail']['tmp_name'],
                                        $new_thumbnail_destination
                                    )
                                ) {

                                    $new_uploaded_thumbnail =
                                        $new_thumbnail_destination;

                                } else {

                                    $edit_error =
                                        "Failed to save the new thumbnail.";
                                }

                            } else {

                                $edit_error =
                                    "Invalid thumbnail format.";
                            }
                        }
                    }


                    /* =====================================
                       HANDLE ERROR
                    ===================================== */

                    if (
                        !empty($edit_error)
                    ) {

                        if (
                            !empty($new_uploaded_file) &&
                            is_file($new_uploaded_file)
                        ) {

                            @unlink(
                                $new_uploaded_file
                            );
                        }


                        if (
                            !empty($new_uploaded_thumbnail) &&
                            is_file($new_uploaded_thumbnail)
                        ) {

                            @unlink(
                                $new_uploaded_thumbnail
                            );
                        }


                        $action_error =
                            $edit_error;

                        $action_type =
                            'error';

                    } else {


                        try {

                            $update =
                                $pdo->prepare("
                                    UPDATE notes
                                    SET
                                        title = ?,
                                        description = ?,
                                        institute = ?,
                                        file_path = ?,
                                        thumbnail = ?,
                                        status = 'pending'
                                    WHERE id = ?
                                    AND user_id = ?
                                ");


                            $update->execute([
                                $title,
                                $description,
                                $institute,
                                $new_file_path,
                                $new_thumbnail,
                                $note_id,
                                $user_id
                            ]);


                            if (
                                $new_file_path !==
                                $old_file
                            ) {

                                delete_note_file(
                                    $old_file
                                );
                            }


                            if (
                                $new_thumbnail !==
                                $old_thumbnail
                            ) {

                                delete_note_file(
                                    $old_thumbnail
                                );
                            }


                            $action_success =
                                "Changes saved. Your note is now waiting for admin review.";

                            $action_type =
                                'edit_success';

                        } catch (
                            PDOException $e
                        ) {


                            if (
                                !empty($new_uploaded_file) &&
                                is_file($new_uploaded_file)
                            ) {

                                @unlink(
                                    $new_uploaded_file
                                );
                            }


                            if (
                                !empty($new_uploaded_thumbnail) &&
                                is_file($new_uploaded_thumbnail)
                            ) {

                                @unlink(
                                    $new_uploaded_thumbnail
                                );
                            }


                            $action_error =
                                "Unable to update the note.";

                            $action_type =
                                'error';
                        }
                    }
                }
            }
        }
    }
}


/* =========================================================
   SEARCH
========================================================= */

$search =
    isset($_GET['search'])
        ? trim($_GET['search'])
        : '';

$selected_institute =
    isset($_GET['institute'])
        ? trim($_GET['institute'])
        : '';

$selected_note_id =
    isset($_GET['note_id'])
        ? intval($_GET['note_id'])
        : 0;

$is_direct_note =
    $selected_note_id > 0;

$notes = [];


/* =========================================================
   DIRECT NOTE
========================================================= */

if (
    $is_direct_note
) {

    $stmt =
        $pdo->prepare("
            SELECT
                n.*,
                u.full_name AS uploader,
                COALESCE(
                    AVG(r.rating),
                    0
                ) AS avg_rating,
                COUNT(
                    r.rating
                ) AS total_ratings
            FROM notes n
            JOIN users u
                ON n.user_id = u.user_id
            LEFT JOIN note_ratings r
                ON n.id = r.note_id
            WHERE n.id = ?
            AND n.status = 'approved'
            GROUP BY n.id
            LIMIT 1
        ");


    $stmt->execute([
        $selected_note_id
    ]);


    $shared_note =
        $stmt->fetch();


    if (
        $shared_note
    ) {

        $notes[] =
            $shared_note;
    }
}


/* =========================================================
   SEARCHED NOTES
========================================================= */

elseif (
    !empty($search) ||
    !empty($selected_institute)
) {

    $sql = "
        SELECT
            n.*,
            u.full_name AS uploader,
            COALESCE(
                AVG(r.rating),
                0
            ) AS avg_rating,
            COUNT(
                r.rating
            ) AS total_ratings
        FROM notes n
        JOIN users u
            ON n.user_id = u.user_id
        LEFT JOIN note_ratings r
            ON n.id = r.note_id
        WHERE n.status = 'approved'
    ";


    $params = [];


    if (
        !empty($search)
    ) {

        $sql .= "
            AND (
                n.title LIKE ?
                OR n.description LIKE ?
                OR n.institute LIKE ?
            )
        ";


        $params[] =
            "%{$search}%";

        $params[] =
            "%{$search}%";

        $params[] =
            "%{$search}%";
    }


    if (
        !empty($selected_institute)
    ) {

        $sql .= "
            AND n.institute = ?
        ";

        $params[] =
            $selected_institute;
    }


    $sql .= "
        GROUP BY n.id
        ORDER BY n.created_at DESC
    ";


    $stmt =
        $pdo->prepare($sql);

    $stmt->execute(
        $params
    );

    $notes =
        $stmt->fetchAll();
}


/* =========================================================
   USER UPLOAD HISTORY
========================================================= */

$history_stmt =
    $pdo->prepare("
        SELECT *
        FROM notes
        WHERE user_id = ?
        ORDER BY created_at DESC
    ");

$history_stmt->execute([
    $user_id
]);

$user_uploads =
    $history_stmt->fetchAll();

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
    NoteSphere Hub - View Notes
</title>

<link
    href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
    rel="stylesheet"
>

<script
    src="https://cdn.jsdelivr.net/npm/sweetalert2@11"
></script>


<style>

/* =========================================================
   ROOT
========================================================= */

:root {

    --neon: #39ff14;

    --neon-glow:
        rgba(
            57,
            255,
            20,
            0.12
        );

    --card-bg: #0c0c0c;

    --bg: #080808;

    --text: #f8fafc;

    --sub-text: #94a3b8;

    --border: #1f293d;

    --sidebar-width: 260px;

    --danger: #ff4d4d;

    --warning: #f59e0b;

    --storage-bg:
        rgba(
            57,
            255,
            20,
            0.07
        );
}


body.light-mode {

    --card-bg:
        rgba(
            255,
            255,
            255,
            0.94
        );

    --bg: #f4f7f5;

    --text: #111827;

    --sub-text: #475569;

    --border:
        rgba(
            15,
            23,
            42,
            0.12
        );

    --neon: #22a447;

    --neon-glow:
        rgba(
            34,
            164,
            71,
            0.12
        );

    --storage-bg:
        rgba(
            34,
            164,
            71,
            0.08
        );

    --danger: #dc2626;

    --warning: #d97706;
}


/* =========================================================
   GLOBAL
========================================================= */

* {

    margin: 0;

    padding: 0;

    box-sizing: border-box;

    font-family:
        'Plus Jakarta Sans',
        sans-serif;
}


html,
body {

    width: 100%;

    max-width: 100vw;

    min-height: 100%;

    overflow-x: hidden;
}


body {

    position: relative;

    color: var(--text);

    background: var(--bg);

    display: flex;

    min-height: 100vh;

    transition:
        background .3s ease,
        color .3s ease;
}


body::before {

    content: '';

    position: fixed;

    top: 0;

    left: 0;

    width: 100vw;

    height: 100vh;

    z-index: -2;

    background:
        linear-gradient(
            rgba(11,11,11,.84),
            rgba(11,11,11,.91)
        ),
        url('../assets/registerbg.png')
        no-repeat
        center center;

    background-size: cover;

    pointer-events: none;
}


body.light-mode::before {

    background:
        linear-gradient(
            rgba(241,248,244,.82),
            rgba(255,255,255,.90)
        ),
        url('../assets/registerbg.png')
        no-repeat
        center center;

    background-size: cover;
}


/* =========================================================
   SIDEBAR
========================================================= */

.sidebar {

    width: var(--sidebar-width);

    height: 100vh;

    background: var(--card-bg);

    border-right:
        1px solid
        var(--border);

    padding: 35px 20px;

    position: fixed;

    top: 0;

    left: 0;

    display: flex;

    flex-direction: column;

    justify-content: space-between;

    z-index: 1002;

    transition:
        transform .3s ease,
        background .3s ease,
        border-color .3s ease;

    overflow-y: auto;
}


.sidebar-top {

    display: flex;

    flex-direction: column;
}


.sidebar-brand {

    display: flex;

    align-items: center;

    gap: 12px;

    margin-bottom: 30px;

    padding-left: 5px;
}


.sidebar-brand h2 {

    font-weight: 800;

    color: var(--neon);

    font-size: 1.4rem;
}


.sidebar-brand img {

    width: 35px;

    height: auto;
}


.menu {

    display: flex;

    flex-direction: column;

    gap: 6px;
}


.menu a {

    display: flex;

    align-items: center;

    gap: 14px;

    padding: 12px 16px;

    text-decoration: none;

    color: var(--sub-text);

    font-weight: 600;

    font-size: .92rem;

    border-radius: 12px;

    transition: all .3s ease;
}


.menu a svg {

    width: 20px;

    height: 20px;

    stroke: currentColor;

    stroke-width: 2;

    fill: none;

    flex-shrink: 0;
}


.menu a:hover,
.menu a.active {

    background:
        var(--neon-glow);

    color: var(--neon);
}


.menu a.active {

    border:
        1px solid
        rgba(
            57,
            255,
            20,
            .20
        );
}


body.light-mode .menu a.active {

    border-color:
        rgba(
            34,
            164,
            71,
            .20
        );
}


/* =========================================================
   MORE
========================================================= */

.more-toggle-btn {

    width: 100%;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding: 12px 16px;

    background: transparent;

    border: none;

    color: var(--sub-text);

    font-weight: 600;

    font-size: .92rem;

    border-radius: 12px;

    cursor: pointer;

    transition: all .3s ease;
}


.more-toggle-left {

    display: flex;

    align-items: center;

    gap: 14px;
}


.more-toggle-left svg,
.more-chevron {

    width: 20px;

    height: 20px;

    stroke: currentColor;

    stroke-width: 2;

    fill: none;
}


.more-chevron {

    transition:
        transform .3s ease;
}


.more-toggle-btn:hover {

    background:
        var(--neon-glow);

    color: var(--neon);
}


.submenu-container {

    display: flex;

    flex-direction: column;

    gap: 4px;

    padding-left: 15px;

    margin-top: 4px;
}


.submenu-container a {

    padding: 10px 14px;

    font-size: .88rem;
}


/* =========================================================
   SIDEBAR STORAGE
========================================================= */

.sidebar-storage {

    margin-top: 18px;

    padding: 14px;

    background:
        var(--storage-bg);

    border:
        1px solid
        var(--border);

    border-radius: 14px;

    transition:
        background .3s ease,
        border-color .3s ease;
}


.sidebar-storage-header {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 10px;

    margin-bottom: 9px;
}


.sidebar-storage-title {

    display: flex;

    align-items: center;

    gap: 8px;

    color: var(--text);

    font-size: .78rem;

    font-weight: 700;
}


.sidebar-storage-title svg {

    width: 17px;

    height: 17px;

    stroke: var(--neon);

    fill: none;

    stroke-width: 2;
}


.sidebar-storage-status {

    font-size: .68rem;

    color: var(--neon);

    font-weight: 700;

    white-space: nowrap;
}


.sidebar-storage-info {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 10px;

    margin-bottom: 8px;

    font-size: .68rem;

    color: var(--sub-text);
}


.storage-progress {

    width: 100%;

    height: 8px;

    display: block;

    border: none;

    border-radius: 20px;

    overflow: hidden;

    appearance: none;

    -webkit-appearance: none;

    background:
        rgba(
            148,
            163,
            184,
            .15
        );
}


.storage-progress::-webkit-progress-bar {

    background:
        rgba(
            148,
            163,
            184,
            .15
        );

    border-radius: 20px;
}


.storage-progress::-webkit-progress-value {

    background: var(--neon);

    border-radius: 20px;

    transition:
        width .4s ease;
}


.storage-progress::-moz-progress-bar {

    background: var(--neon);

    border-radius: 20px;

    transition:
        width .4s ease;
}


.sidebar-storage-remaining {

    margin-top: 8px;

    font-size: .68rem;

    color: var(--sub-text);

    line-height: 1.4;
}


.sidebar-storage-remaining strong {

    color: var(--text);
}


/* =========================================================
   SIDEBAR FOOTER
========================================================= */

.sidebar-footer {

    display: flex;

    flex-direction: column;

    gap: 10px;

    margin-top: 20px;
}


.theme-toggle-btn {

    width: 100%;

    padding: 12px 16px;

    background: var(--card-bg);

    border:
        1px solid
        var(--border);

    color: var(--text);

    border-radius: 12px;

    font-weight: 600;

    cursor: pointer;

    display: flex;

    align-items: center;

    gap: 12px;

    transition: .3s;

    font-size: .9rem;

    text-decoration: none;
}


.theme-toggle-btn:hover {

    border-color: var(--neon);

    color: var(--neon);
}


/* =========================================================
   MOBILE HEADER
========================================================= */

.mobile-header {

    display: none;

    align-items: center;

    justify-content: space-between;

    padding: 15px 20px;

    background: var(--card-bg);

    border-bottom:
        1px solid
        var(--border);

    position: fixed;

    top: 0;

    left: 0;

    width: 100%;

    z-index: 1000;
}


.sidebar-toggle-btn {

    background: var(--card-bg);

    border:
        1px solid
        var(--border);

    cursor: pointer;

    display: flex;

    align-items: center;

    justify-content: center;

    width: 40px;

    height: 40px;

    border-radius: 10px;

    color: var(--text);
}


.sidebar-toggle-btn svg {

    width: 20px;

    height: 20px;

    stroke: currentColor;

    stroke-width: 2;

    fill: none;
}


.sidebar-overlay {

    display: none;

    position: fixed;

    top: 0;

    left: 0;

    width: 100vw;

    height: 100vh;

    background:
        rgba(0,0,0,.60);

    z-index: 1001;

    backdrop-filter:
        blur(2px);
}


/* =========================================================
   MAIN
========================================================= */

main {

    margin-left:
        var(--sidebar-width);

    flex: 1;

    padding: 40px;

    max-width:
        calc(
            100vw -
            var(--sidebar-width)
        );

    width: 100%;

    overflow-x: hidden;
}


/* =========================================================
   ACTION BUTTONS
========================================================= */

.action-buttons-wrapper {

    display: flex;

    justify-content: flex-end;

    gap: 12px;

    margin-bottom: 25px;
}


.history-btn,
.upload-trigger-btn {

    display: flex;

    align-items: center;

    justify-content: center;

    gap: 8px;

    padding: 10px 18px;

    border-radius: 12px;

    font-weight: 600;

    font-size: .9rem;

    cursor: pointer;

    transition: .3s ease;

    text-decoration: none;
}


.history-btn {

    background: var(--card-bg);

    border:
        1px solid
        var(--border);

    color: var(--text);
}


.history-btn:hover {

    border-color: var(--neon);

    color: var(--neon);
}


.upload-trigger-btn {

    background: var(--neon);

    border: none;

    color: #000;

    font-weight: 700;

    box-shadow:
        0 4px 15px
        rgba(
            57,
            255,
            20,
            .30
        );
}


.upload-trigger-btn:hover {

    opacity: .9;

    transform:
        translateY(-1px);
}


/* =========================================================
   LANDING
========================================================= */

.google-hub-container {

    display: flex;

    flex-direction: column;

    align-items: center;

    justify-content: center;

    min-height: 60vh;

    text-align: center;
}


.hub-logo {

    font-size: 3rem;

    font-weight: 800;

    margin-bottom: 25px;

    letter-spacing: -1px;
}


.hub-logo span {

    color: var(--neon);
}


.google-search-bar {

    width: 100%;

    max-width: 650px;

    display: flex;

    gap: 10px;

    background: var(--card-bg);

    border:
        1px solid
        var(--border);

    padding: 8px;

    border-radius: 16px;

    box-shadow:
        0 10px 30px
        rgba(0,0,0,.20);

    margin-bottom: 35px;

    transition: .3s;
}


.google-search-bar:focus-within {

    border-color: var(--neon);

    box-shadow:
        0 0 20px
        var(--neon-glow);
}


.google-search-input {

    flex: 1;

    background: transparent;

    border: none;

    padding: 12px 16px;

    color: var(--text);

    font-size: 1rem;

    outline: none;
}


.google-search-input::placeholder {

    color: var(--sub-text);
}


.google-search-btn {

    background: var(--neon);

    color: #000;

    border: none;

    padding: 0 24px;

    border-radius: 10px;

    font-weight: 700;

    cursor: pointer;

    transition: .3s ease;
}


.google-search-btn:hover {

    opacity: .9;

    transform:
        translateY(-1px);
}


/* =========================================================
   INSTITUTE
========================================================= */

.institute-grid {

    display: grid;

    grid-template-columns:
        repeat(
            auto-fit,
            minmax(
                200px,
                1fr
            )
        );

    gap: 15px;

    width: 100%;

    max-width: 800px;
}


.institute-card {

    background: var(--card-bg);

    border:
        1px solid
        var(--border);

    padding: 28px 20px;

    border-radius: 16px;

    text-decoration: none;

    display: flex;

    flex-direction: column;

    align-items: center;

    justify-content: center;

    gap: 10px;

    min-height: 130px;

    transition: .3s ease;

    cursor: pointer;
}


.institute-card:hover {

    border-color: var(--neon);

    transform:
        translateY(-3px);

    box-shadow:
        0 10px 25px
        var(--neon-glow);
}


.institute-icon {

    font-size: 2rem;
}


.institute-name {

    color: var(--text);

    font-weight: 700;

    font-size: .95rem;
}


/* =========================================================
   BACK BUTTON
========================================================= */

.top-back-row {

    display: flex;

    justify-content: flex-start;

    align-items: center;

    margin-bottom: 22px;
}


.btn-back {

    width: 44px;

    height: 44px;

    border-radius: 50%;

    display: flex;

    align-items: center;

    justify-content: center;

    background: var(--card-bg);

    border:
        1px solid
        var(--border);

    color: var(--text);

    text-decoration: none;

    transition: all .3s ease;

    flex-shrink: 0;

    box-shadow:
        0 4px 15px
        rgba(0,0,0,.15);
}


.btn-back:hover {

    border-color: var(--neon);

    color: var(--neon);

    background:
        var(--neon-glow);

    transform:
        translateX(-3px);

    box-shadow:
        0 0 18px
        var(--neon-glow);
}


.btn-back svg {

    width: 20px;

    height: 20px;

    display: block;
}


/* =========================================================
   RESULTS
========================================================= */

.results-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 25px;

    gap: 15px;
}


.results-header h2 {

    font-size: 1.25rem;
}


.grid-container {

    display: grid;

    grid-template-columns:
        repeat(
            auto-fill,
            minmax(
                280px,
                1fr
            )
        );

    gap: 20px;
}


/* =========================================================
   CARD
========================================================= */

.card {

    background: var(--card-bg);

    border:
        1px solid
        var(--border);

    border-radius: 16px;

    padding: 20px;

    display: flex;

    flex-direction: column;

    justify-content: space-between;

    transition: .3s;

    position: relative;
}


.card:hover {

    border-color: var(--neon);

    transform:
        translateY(-3px);

    box-shadow:
        0 10px 25px
        var(--neon-glow);
}


/* =========================================================
   NOTE MENU
========================================================= */

.note-top-row {

    display: flex;

    justify-content: flex-end;

    align-items: center;

    position: relative;

    height: 34px;

    margin-bottom: 5px;
}


.dots-btn {

    width: 34px;

    height: 34px;

    border-radius: 50%;

    border:
        1px solid
        var(--border);

    background: var(--card-bg);

    color: var(--sub-text);

    display: flex;

    align-items: center;

    justify-content: center;

    cursor: pointer;

    font-size: 1.3rem;

    line-height: 1;

    padding: 0;

    transition: .3s ease;
}


.dots-btn:hover {

    color: var(--neon);

    border-color: var(--neon);

    background:
        var(--neon-glow);
}


.note-menu {

    position: absolute;

    top: 41px;

    right: 0;

    width: 175px;

    background: var(--card-bg);

    border:
        1px solid
        var(--border);

    border-radius: 12px;

    box-shadow:
        0 15px 35px
        rgba(0,0,0,.40);

    padding: 6px;

    display: none;

    flex-direction: column;

    gap: 3px;

    z-index: 50;
}


.note-menu.show {

    display: flex;
}


.owner-label {

    font-size: .68rem;

    color: var(--sub-text);

    padding:
        7px 10px 4px;
}


.note-menu-btn {

    width: 100%;

    border: none;

    background: transparent;

    color: var(--text);

    padding: 10px 12px;

    border-radius: 8px;

    font-size: .82rem;

    font-weight: 600;

    text-align: left;

    cursor: pointer;

    display: flex;

    align-items: center;

    gap: 8px;

    transition: .2s;
}


.note-menu-btn:hover {

    background:
        var(--neon-glow);

    color: var(--neon);
}


.note-menu-btn.delete-option:hover {

    background:
        rgba(
            255,
            77,
            77,
            .10
        );

    color: var(--danger);
}


/* =========================================================
   PREVIEW
========================================================= */

.preview-box {

    width: 100%;

    height: 140px;

    background: var(--border);

    border-radius: 10px;

    position: relative;

    overflow: hidden;

    margin-bottom: 15px;
}


.preview-box img {

    width: 100%;

    height: 100%;

    object-fit: cover;

    display: block;
}


.file-badge {

    position: absolute;

    top: 10px;

    right: 10px;

    background:
        rgba(
            0,
            0,
            0,
            .70
        );

    backdrop-filter:
        blur(4px);

    color: var(--neon);

    padding:
        4px 8px;

    border-radius: 6px;

    font-size: .75rem;

    font-weight: 700;

    z-index: 2;
}


/* =========================================================
   RATING
========================================================= */

.star-rating-box {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 10px;

    background:
        rgba(
            255,
            255,
            255,
            .03
        );

    border:
        1px solid
        var(--border);

    padding:
        8px 12px;

    border-radius: 10px;

    margin-top: 12px;
}


/* =========================================================
   VIEW / DOWNLOAD BUTTON
========================================================= */

.view-note-btn {

    display: block;

    width: 100%;

    text-align: center;

    background:
        var(--neon-glow);

    border:
        1px solid
        rgba(
            57,
            255,
            20,
            .30
        );

    color: var(--neon);

    padding: 10px;

    border-radius: 10px;

    text-decoration: none;

    font-weight: 700;

    font-size: .88rem;

    margin-top: 15px;

    transition: .3s;
}


.view-note-btn:hover {

    background: var(--neon);

    color: #000;
}


body.light-mode .view-note-btn {

    border-color:
        rgba(
            34,
            164,
            71,
            .25
        );
}


/* =========================================================
   MODALS
========================================================= */

.modal-overlay {

    display: none;

    position: fixed;

    top: 0;

    left: 0;

    width: 100vw;

    height: 100vh;

    background:
        rgba(
            0,
            0,
            0,
            .70
        );

    backdrop-filter:
        blur(4px);

    z-index: 2000;

    align-items: center;

    justify-content: center;

    padding: 20px;
}


.modal-content {

    background: var(--card-bg);

    border:
        1px solid
        var(--border);

    width: 100%;

    max-width: 500px;

    padding: 30px;

    border-radius: 20px;

    position: relative;

    max-height: 90vh;

    overflow-y: auto;

    box-shadow:
        0 20px 40px
        rgba(0,0,0,.40);
}


.close-modal {

    position: absolute;

    top: 20px;

    right: 20px;

    background: transparent;

    border: none;

    color: var(--sub-text);

    font-size: 1.5rem;

    cursor: pointer;

    transition: .3s;
}


.close-modal:hover {

    color: var(--neon);
}


/* =========================================================
   FORM
========================================================= */

.form-group {

    margin-bottom: 16px;
}


.form-label {

    display: block;

    font-size: .85rem;

    font-weight: 600;

    color: var(--sub-text);

    margin-bottom: 6px;
}


.form-input,
.form-select,
.form-textarea {

    width: 100%;

    background: var(--bg);

    border:
        1px solid
        var(--border);

    padding:
        10px 14px;

    border-radius: 10px;

    color: var(--text);

    font-size: .9rem;

    outline: none;

    transition: .3s;
}


.form-input:focus,
.form-select:focus,
.form-textarea:focus {

    border-color: var(--neon);

    box-shadow:
        0 0 0 3px
        var(--neon-glow);
}


.form-input::placeholder,
.form-textarea::placeholder {

    color: var(--sub-text);
}


.current-file-box {

    background:
        var(--neon-glow);

    border:
        1px solid
        rgba(
            57,
            255,
            20,
            .20
        );

    padding:
        10px 12px;

    border-radius: 10px;

    font-size: .78rem;

    color: var(--sub-text);

    margin-bottom: 16px;

    word-break:
        break-word;
}


/* =========================================================
   GENERAL
========================================================= */

.muted-text {

    color: var(--sub-text);
}


.text-neon {

    color: var(--neon);
}


.hidden {

    display: none !important;
}


/* =========================================================
   SCROLLBAR
========================================================= */

.sidebar::-webkit-scrollbar,
.modal-content::-webkit-scrollbar {

    width: 6px;
}


.sidebar::-webkit-scrollbar-track,
.modal-content::-webkit-scrollbar-track {

    background: transparent;
}


.sidebar::-webkit-scrollbar-thumb,
.modal-content::-webkit-scrollbar-thumb {

    background:
        rgba(
            148,
            163,
            184,
            .25
        );

    border-radius: 20px;
}


.sidebar::-webkit-scrollbar-thumb:hover,
.modal-content::-webkit-scrollbar-thumb:hover {

    background: var(--neon);
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 768px) {

    .mobile-header {

        display: flex;
    }


    main {

        margin-left: 0;

        padding:
            90px 16px 20px;

        max-width: 100vw;
    }


    .sidebar {

        transform:
            translateX(-100%);
    }


    body.sidebar-open .sidebar {

        transform:
            translateX(0);
    }


    body.sidebar-open .sidebar-overlay {

        display: block;
    }


    .hub-logo {

        font-size: 2.2rem;
    }


    .google-search-bar {

        flex-direction: column;
    }


    .google-search-btn {

        min-height: 44px;
    }


    .action-buttons-wrapper {

        justify-content: stretch;
    }


    .history-btn,
    .upload-trigger-btn {

        flex: 1;
    }


    .results-header {

        flex-direction: column;

        align-items: flex-start;
    }


    .grid-container {

        grid-template-columns:
            repeat(
                auto-fill,
                minmax(
                    240px,
                    1fr
                )
            );
    }
}


@media (max-width: 480px) {

    main {

        padding-left: 12px;

        padding-right: 12px;
    }


    .institute-grid {

        grid-template-columns: 1fr;
    }


    .institute-card {

        min-height: 110px;
    }


    .hub-logo {

        font-size: 2rem;
    }


    .card {

        padding: 16px;
    }


    .modal-content {

        padding:
            25px 20px;
    }


    .action-buttons-wrapper {

        flex-direction: row;

        gap: 8px;
    }


    .history-btn,
    .upload-trigger-btn {

        padding:
            10px 12px;

        font-size:
            .82rem;
    }


    .sidebar-storage {

        margin-top: 14px;
    }
}


@media (max-width: 360px) {

    .action-buttons-wrapper {

        flex-direction: column;
    }


    .history-btn,
    .upload-trigger-btn {

        width: 100%;

        flex: none;
    }


    .google-search-btn {

        width: 100%;
    }
}

</style>

</head>


<body>


<!-- =====================================================
     MOBILE HEADER
===================================================== -->

<header class="mobile-header">

    <div
        style="
            display:flex;
            align-items:center;
            gap:10px;
        "
    >

        <img
            src="../assets/cdmlogo.png"
            alt="Logo"
            style="
                width:30px;
                height:auto;
            "
        >

        <h2
            style="
                font-size:1.2rem;
                color:var(--neon);
            "
        >
            NoteSphere
        </h2>

    </div>


    <button
        class="sidebar-toggle-btn"
        onclick="toggleMobileSidebar()"
        aria-label="Toggle Sidebar"
    >

        <svg viewBox="0 0 24 24">

            <line
                x1="3"
                y1="12"
                x2="21"
                y2="12"
            ></line>

            <line
                x1="3"
                y1="6"
                x2="21"
                y2="6"
            ></line>

            <line
                x1="3"
                y1="18"
                x2="21"
                y2="18"
            ></line>

        </svg>

    </button>

</header>


<!-- =====================================================
     SIDEBAR OVERLAY
===================================================== -->

<div
    class="sidebar-overlay"
    onclick="toggleMobileSidebar()"
></div>


<!-- =====================================================
     SIDEBAR
===================================================== -->

<nav
    class="sidebar"
    id="sidebar"
>

    <div class="sidebar-top">

        <div class="sidebar-brand">

            <img
                src="../assets/cdmlogo.png"
                alt="Logo"
            >

            <h2>
                NoteSphere
            </h2>

        </div>


        <div class="menu">


            <!-- DASHBOARD -->

            <a
                href="student_home.php"
            >

                <svg viewBox="0 0 24 24">

                    <path
                        d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"
                    ></path>

                    <polyline
                        points="9 22 9 12 15 12 15 22"
                    ></polyline>

                </svg>

                <span>
                    Dashboard
                </span>

            </a>


            <!-- VIEW NOTES -->

            <a
                href="view_notes.php"
                class="active"
            >

                <svg viewBox="0 0 24 24">

                    <path
                        d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"
                    ></path>

                    <path
                        d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"
                    ></path>

                </svg>

                <span>
                    View Notes
                </span>

            </a>


            <!-- HELP BOARD -->

            <a
                href="help_board.php"
            >

                <svg viewBox="0 0 24 24">

                    <path
                        d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"
                    ></path>

                    <circle
                        cx="12"
                        cy="10"
                        r="1"
                    ></circle>

                </svg>

                <span>
                    Help Board (Q&amp;A)
                </span>

            </a>


            <!-- MORE -->

            <div
                class="menu-item-group"
                style="margin-top:5px;"
            >

                <button
                    type="button"
                    onclick="toggleMoreMenu(event)"
                    class="more-toggle-btn"
                    id="moreToggleBtn"
                >

                    <div
                        class="more-toggle-left"
                    >

                        <svg viewBox="0 0 24 24">

                            <circle
                                cx="12"
                                cy="12"
                                r="1"
                            ></circle>

                            <circle
                                cx="19"
                                cy="12"
                                r="1"
                            ></circle>

                            <circle
                                cx="5"
                                cy="12"
                                r="1"
                            ></circle>

                        </svg>

                        <span>
                            More
                        </span>

                    </div>


                    <svg
                        class="more-chevron"
                        id="moreChevron"
                        viewBox="0 0 24 24"
                    >

                        <polyline
                            points="6 9 12 15 18 9"
                        ></polyline>

                    </svg>

                </button>


                <div
                    id="moreSubmenu"
                    class="submenu-container"
                    style="display:none;"
                >

                    <a
                        href="archive_sy.php"
                    >

                        <svg viewBox="0 0 24 24">

                            <path
                                d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"
                            ></path>

                            <path
                                d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"
                            ></path>

                        </svg>

                        <span>
                            Archive SY
                        </span>

                    </a>


                    <a
                        href="enrollment_update.php"
                    >

                        <svg viewBox="0 0 24 24">

                            <path
                                d="M22 10v6M2 10l10-5 10 5-10 5z"
                            ></path>

                            <path
                                d="M6 12v5c3 3 9 3 12 0v-5"
                            ></path>

                        </svg>

                        <span>
                            Enrollment Update
                        </span>

                    </a>


                    <a
                        href="system_guide.php"
                    >

                        <svg viewBox="0 0 24 24">

                            <path
                                d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"
                            ></path>

                            <path
                                d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"
                            ></path>

                        </svg>

                        <span>
                            System Guide
                        </span>

                    </a>

                </div>

            </div>

        </div>

    </div>


    <!-- SIDEBAR FOOTER -->

    <div class="sidebar-footer">

        <button
            onclick="toggleGlobalTheme()"
            class="theme-toggle-btn"
            id="globalThemeBtn"
        >

            <span id="themeIcon">
                🌞
            </span>

            <span id="themeText">
                Light Mode
            </span>

        </button>


        <a
            href="../auth/logout.php"
            class="theme-toggle-btn"
            style="
                color:#ff4d4d;
                border-color:
                rgba(
                    255,
                    77,
                    77,
                    .3
                );
            "
        >

            <svg
                viewBox="0 0 24 24"
                style="
                    width:18px;
                    height:18px;
                    stroke:currentColor;
                    stroke-width:2;
                    fill:none;
                "
            >

                <path
                    d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"
                ></path>

                <polyline
                    points="16 17 21 12 16 7"
                ></polyline>

            </svg>

            <span>
                Logout
            </span>

        </a>

    </div>

</nav>


<!-- =====================================================
     MAIN
===================================================== -->

<main>


<?php

if (
    $search === '' &&
    $selected_institute === '' &&
    !$is_direct_note
):

?>


<!-- =====================================================
     LANDING PAGE
===================================================== -->

<div
    class="google-hub-container"
>

    <h1
        class="hub-logo"
    >

        Note

        <span>
            Sphere
        </span>

        Hub

    </h1>


    <form
        method="GET"
        action=""
        class="google-search-bar"
    >

        <input
            type="text"
            name="search"
            class="google-search-input"
            placeholder="Search notes, subjects, or keywords..."
            value="<?php
            echo htmlspecialchars(
                $search
            );
            ?>"
        >


        <button
            type="submit"
            class="google-search-btn"
        >

            Search Notes

        </button>

    </form>


    <p
        style="
            color:var(--sub-text);
            margin-bottom:20px;
            font-weight:600;
            font-size:.9rem;
        "
    >

        Select your Institute:

    </p>


    <!-- INSTITUTE CARDS -->

    <div
        class="institute-grid"
    >


        <!-- ICS -->

        <a
            href="view_notes.php?institute=ICS"
            class="institute-card"
        >

            <span
                class="institute-icon"
            >
                💻
            </span>

            <span
                class="institute-name"
            >
                ICS
            </span>

        </a>


        <!-- ITE -->

        <a
            href="view_notes.php?institute=ITE"
            class="institute-card"
        >

            <span
                class="institute-icon"
            >
                📚
            </span>

            <span
                class="institute-name"
            >
                ITE
            </span>

        </a>


        <!-- IBE -->

        <a
            href="view_notes.php?IBE"
            class="institute-card"
        >

            <span
                class="institute-icon"
            >
                📊
            </span>

            <span
                class="institute-name"
            >
                IBE
            </span>

        </a>

    </div>

</div>


<?php else: ?>


<!-- =====================================================
     RESULTS PAGE
===================================================== -->

<div
    class="top-back-row"
>

    <a
        href="student_home.php"
        class="btn-back"
        title="Back to Dashboard"
        aria-label="Back to Dashboard"
    >

        <svg
            width="20"
            height="20"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2.5"
            stroke-linecap="round"
            stroke-linejoin="round"
        >

            <line
                x1="19"
                y1="12"
                x2="5"
                y2="12"
            ></line>

            <polyline
                points="12 19 5 12 12 5"
            ></polyline>

        </svg>

    </a>

</div>


<!-- ACTION BUTTONS -->

<?php if (!$is_direct_note): ?>

<div
    class="action-buttons-wrapper"
>

    <button
        class="history-btn"
        onclick="openHistoryModal()"
        title="Upload History"
    >

        <svg
            viewBox="0 0 24 24"
            width="18"
            height="18"
            stroke="currentColor"
            fill="none"
            stroke-width="2"
        >

            <path
                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
            ></path>

        </svg>

        <span>
            History
        </span>

    </button>


    <button
        class="upload-trigger-btn"
        onclick="openUploadModal()"
        title="Upload Note"
    >

        <svg
            viewBox="0 0 24 24"
            width="18"
            height="18"
            stroke="currentColor"
            fill="none"
            stroke-width="2"
        >

            <path
                d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"
            ></path>

            <polyline
                points="17 8 12 3 7 8"
            ></polyline>

            <line
                x1="12"
                y1="3"
                x2="12"
                y2="15"
            ></line>

        </svg>

        <span>
            Upload
        </span>

    </button>

</div>

<?php endif; ?>


<!-- RESULTS -->

<div
    class="results-section"
>

    <div
        class="results-header"
    >

        <div>

            <?php if ($is_direct_note): ?>

                <h2>
                    Shared Note
                </h2>

            <?php else: ?>

                <h2>

                    Results for:

                    <span
                        style="
                            color:var(--neon);
                        "
                    >

                        <?php

                        echo htmlspecialchars(
                            !empty(
                                $selected_institute
                            )
                                ? $selected_institute
                                : $search
                        );

                        ?>

                    </span>

                </h2>

            <?php endif; ?>

        </div>

    </div>


    <?php if (count($notes) > 0): ?>

    <div
        class="grid-container"
    >


        <?php foreach ($notes as $row): ?>


        <?php

        /*
        =====================================================
        IMPORTANT PATH FIX
        =====================================================
        */

        $stored_file_path =
            trim(
                (string)(
                    $row['file_path']
                    ?? ''
                )
            );


        /*
        New format:
        uploads/notes/file.pdf

        Result:
        ../uploads/notes/file.pdf
        */

        if (
            stripos(
                $stored_file_path,
                'uploads/'
            ) === 0
        ) {

            $file_path =
                '../' .
                ltrim(
                    $stored_file_path,
                    '/'
                );

        } else {

            /*
            Old format:
            note_xxxxx.pdf
            */

            $file_path =
                '../uploads/' .
                ltrim(
                    $stored_file_path,
                    '/'
                );
        }


        /*
        =====================================================
        THUMBNAIL PATH FIX
        =====================================================
        */

        $stored_thumbnail =
            trim(
                (string)(
                    $row['thumbnail']
                    ?? ''
                )
            );


        if (
            empty($stored_thumbnail)
        ) {

            $thumbPath =
                '../assets/default_thumb.png';

        } elseif (
            stripos(
                $stored_thumbnail,
                'uploads/'
            ) === 0
        ) {

            $thumbPath =
                '../' .
                ltrim(
                    $stored_thumbnail,
                    '/'
                );

        } else {

            $thumbPath =
                '../uploads/' .
                ltrim(
                    $stored_thumbnail,
                    '/'
                );
        }


        /*
        =====================================================
        FILE EXTENSION
        =====================================================
        */

        $ext =
            strtoupper(
                pathinfo(
                    $stored_file_path,
                    PATHINFO_EXTENSION
                )
            );


        $avg_rating =
            number_format(
                $row['avg_rating'],
                1
            );


        $total_ratings =
            $row['total_ratings'];


        $post_date =
            date(
                'M d, Y • h:i A',
                strtotime(
                    $row['created_at']
                )
            );


        $is_owner =
            (
                (int)$row['user_id']
                === $user_id
            );


        $edit_title =
            htmlspecialchars(
                $row['title'],
                ENT_QUOTES,
                'UTF-8'
            );


        $edit_description =
            htmlspecialchars(
                $row['description'] ?? '',
                ENT_QUOTES,
                'UTF-8'
            );


        $edit_institute =
            htmlspecialchars(
                $row['institute'] ?? 'General',
                ENT_QUOTES,
                'UTF-8'
            );

        ?>


        <!-- NOTE CARD -->

        <div
            class="card"
        >


            <!-- TOP -->

            <div
                class="note-top-row"
            >

                <button
                    type="button"
                    class="dots-btn"
                    onclick="toggleNoteMenu(event, <?php echo (int)$row['id']; ?>)"
                    title="More options"
                    aria-label="More options"
                >
                    ⋮
                </button>


                <!-- MENU -->

                <div
                    class="note-menu"
                    id="noteMenu_<?php echo (int)$row['id']; ?>"
                >


                    <!-- COPY -->

                    <button
                        type="button"
                        class="note-menu-btn"
                        onclick="copyNoteLink(<?php echo (int)$row['id']; ?>)"
                    >

                        🔗

                        Copy Link

                    </button>


                    <?php if ($is_owner): ?>

                    <div
                        class="owner-label"
                    >
                        Your Upload
                    </div>


                    <!-- EDIT -->

                    <button
                        type="button"
                        class="note-menu-btn"
                        onclick="openEditModal(this)"
                        data-id="<?php echo (int)$row['id']; ?>"
                        data-title="<?php echo $edit_title; ?>"
                        data-description="<?php echo $edit_description; ?>"
                        data-institute="<?php echo $edit_institute; ?>"
                        data-file="<?php echo htmlspecialchars($row['file_path'], ENT_QUOTES, 'UTF-8'); ?>"
                    >

                        ✏️

                        Edit

                    </button>


                    <!-- DELETE -->

                    <button
                        type="button"
                        class="note-menu-btn delete-option"
                        onclick="confirmDelete(
                            <?php echo (int)$row['id']; ?>,
                            <?php echo json_encode($row['title']); ?>
                        )"
                    >

                        🗑️

                        Delete

                    </button>

                    <?php endif; ?>

                </div>

            </div>


            <div>


                <!-- PREVIEW -->

                <div
                    class="preview-box"
                >

                    <span
                        class="file-badge"
                    >

                        <?php
                        echo htmlspecialchars(
                            $ext
                        );
                        ?>

                    </span>


                    <img
                        src="<?php
                        echo htmlspecialchars(
                            $thumbPath,
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>"
                        alt="Thumbnail"
                        onerror="this.src='../assets/default_thumb.png';"
                    >

                </div>


                <!-- TITLE -->

                <h3>

                    <?php

                    echo htmlspecialchars(
                        $row['title']
                    );

                    ?>

                </h3>


                <!-- DESCRIPTION -->

                <p
                    style="
                        color:var(--sub-text);
                        font-size:.85rem;
                        margin:10px 0;
                    "
                >

                    <?php

                    echo htmlspecialchars(
                        $row['description'] ?? ''
                    );

                    ?>

                </p>


                <!-- INSTITUTE -->

                <small
                    style="
                        color:var(--neon);
                        display:block;
                        font-weight:700;
                        margin-bottom:3px;
                    "
                >

                    Institute:

                    <?php

                    echo htmlspecialchars(
                        $row['institute']
                        ?? 'General'
                    );

                    ?>

                </small>


                <!-- UPLOADER -->

                <small
                    style="
                        color:var(--sub-text);
                        display:block;
                        margin-bottom:3px;
                    "
                >

                    By:

                    <?php

                    echo htmlspecialchars(
                        $row['uploader']
                    );

                    ?>

                </small>


                <!-- DATE -->

                <small
                    style="
                        color:var(--sub-text);
                        display:block;
                        font-size:.78rem;
                        margin-bottom:8px;
                    "
                >

                    📅

                    <?php

                    echo $post_date;

                    ?>

                </small>


                <!-- RATING -->

                <div
                    class="star-rating-box"
                >

                    <span
                        style="
                            color:#f1c40f;
                            font-weight:700;
                            font-size:.9rem;
                        "
                    >

                        ⭐

                        <?php
                        echo $avg_rating;
                        ?>

                        / 5

                        (

                        <?php
                        echo $total_ratings;
                        ?>

                        )

                    </span>


                    <form
                        method="POST"
                        class="star-form"
                    >

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?php
                            echo htmlspecialchars(
                                $csrf_token
                            );
                            ?>"
                        >

                        <input
                            type="hidden"
                            name="action"
                            value="rate_note"
                        >

                        <input
                            type="hidden"
                            name="note_id"
                            value="<?php
                            echo (int)$row['id'];
                            ?>"
                        >


                        <select
                            name="rating"
                            onchange="this.form.submit()"
                            style="
                                background:var(--card-bg);
                                color:var(--text);
                                border:1px solid var(--border);
                                border-radius:6px;
                                padding:2px 4px;
                                font-size:.75rem;
                                cursor:pointer;
                            "
                        >

                            <option
                                value=""
                                disabled
                                selected
                            >
                                Rate
                            </option>

                            <option value="1">
                                1 ⭐
                            </option>

                            <option value="2">
                                2 ⭐
                            </option>

                            <option value="3">
                                3 ⭐
                            </option>

                            <option value="4">
                                4 ⭐
                            </option>

                            <option value="5">
                                5 ⭐
                            </option>

                        </select>

                    </form>

                </div>

            </div>


            <!-- =================================================
                 DOWNLOAD
            ================================================= -->

            <a
                href="note_view.php?note_id=<?php echo (int)$row['id']; ?>&download=1"
                class="view-note-btn"
                title="Download this note"
            >

                DOWNLOAD

            </a>

        </div>


        <?php endforeach; ?>

    </div>


    <?php else: ?>


    <p
        style="
            color:var(--sub-text);
            text-align:center;
            padding:40px 0;
        "
    >

        <?php

        if (
            $is_direct_note
        ) {

            echo
                'This note is no longer available.';

        } else {

            echo
                'No approved notes found matching your criteria.';
        }

        ?>

    </p>


    <?php endif; ?>

</div>


<?php endif; ?>

</main>


<!-- =====================================================
     DELETE FORM
===================================================== -->

<form
    method="POST"
    id="deleteForm"
    style="display:none;"
>

    <input
        type="hidden"
        name="csrf_token"
        value="<?php
        echo htmlspecialchars(
            $csrf_token
        );
        ?>"
    >

    <input
        type="hidden"
        name="action"
        value="delete_note"
    >

    <input
        type="hidden"
        name="note_id"
        id="deleteNoteId"
        value=""
    >

</form>


<!-- =====================================================
     UPLOAD MODAL
===================================================== -->

<div
    class="modal-overlay"
    id="uploadModal"
>

    <div
        class="modal-content"
    >

        <button
            class="close-modal"
            onclick="closeUploadModal()"
        >
            &times;
        </button>


        <h3
            style="
                margin-bottom:20px;
                color:var(--text);
            "
        >

            Upload New Note

        </h3>


        <form
            method="POST"
            enctype="multipart/form-data"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?php
                echo htmlspecialchars(
                    $csrf_token
                );
                ?>"
            >

            <input
                type="hidden"
                name="action"
                value="quick_upload"
            >


            <div class="form-group">

                <label class="form-label">
                    Note Title
                </label>

                <input
                    type="text"
                    name="title"
                    class="form-input"
                    required
                    placeholder="Enter title..."
                >

            </div>


            <div class="form-group">

                <label class="form-label">
                    Description
                </label>

                <textarea
                    name="description"
                    class="form-textarea"
                    rows="3"
                    placeholder="Brief description..."
                ></textarea>

            </div>


            <div class="form-group">

                <label class="form-label">
                    Institute
                </label>

                <select
                    name="institute"
                    class="form-select"
                >

                    <option value="ITE">
                        ITE (IT &amp; CS)
                    </option>

                    <option value="Education">
                        Education
                    </option>

                    <option value="Business">
                        Business / Accountancy
                    </option>

                    <option value="Criminal Justice">
                        Criminal Justice
                    </option>

                    <option value="General">
                        General
                    </option>

                </select>

            </div>


            <div class="form-group">

                <label class="form-label">
                    Submission Type
                </label>

                <select
                    name="submission_type"
                    class="form-select"
                    id="submissionTypeSelect"
                    onchange="toggleSubmissionType()"
                >

                    <option value="upload">
                        Upload File (PDF, DOCX, PPT, etc.)
                    </option>

                    <option value="write">
                        Write Note Directly
                    </option>

                </select>

            </div>


            <div
                class="form-group"
                id="fileUploadGroup"
            >

                <label class="form-label">
                    Select File
                </label>

                <input
                    type="file"
                    name="note_file"
                    class="form-input"
                >

            </div>


            <div
                class="form-group"
                id="writeNoteGroup"
                style="display:none;"
            >

                <label class="form-label">
                    Note Content
                </label>

                <textarea
                    name="note_content"
                    class="form-textarea"
                    rows="5"
                    placeholder="Type your notes here..."
                ></textarea>

            </div>


            <div class="form-group">

                <label class="form-label">
                    Thumbnail Image (Optional)
                </label>

                <input
                    type="file"
                    name="thumbnail"
                    class="form-input"
                    accept="image/*"
                >

            </div>


            <button
                type="submit"
                class="upload-trigger-btn"
                style="
                    width:100%;
                    padding:12px;
                "
            >

                Submit Note

            </button>

        </form>

    </div>

</div>


<!-- =====================================================
     EDIT MODAL
===================================================== -->

<div
    class="modal-overlay"
    id="editModal"
>

    <div
        class="modal-content"
    >

        <button
            class="close-modal"
            onclick="closeEditModal()"
        >
            &times;
        </button>


        <h3
            style="
                margin-bottom:20px;
                color:var(--text);
            "
        >

            Edit Your Note

        </h3>


        <form
            method="POST"
            enctype="multipart/form-data"
            id="editNoteForm"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?php
                echo htmlspecialchars(
                    $csrf_token
                );
                ?>"
            >

            <input
                type="hidden"
                name="action"
                value="edit_note"
            >

            <input
                type="hidden"
                name="note_id"
                id="edit_note_id"
                value=""
            >


            <div class="form-group">

                <label class="form-label">
                    Note Title
                </label>

                <input
                    type="text"
                    name="edit_title"
                    id="edit_title"
                    class="form-input"
                    required
                >

            </div>


            <div class="form-group">

                <label class="form-label">
                    Description
                </label>

                <textarea
                    name="edit_description"
                    id="edit_description"
                    class="form-textarea"
                    rows="4"
                ></textarea>

            </div>


            <div class="form-group">

                <label class="form-label">
                    Institute
                </label>

                <select
                    name="edit_institute"
                    id="edit_institute"
                    class="form-select"
                >

                    <option value="ITE">
                        ITE (IT &amp; CS)
                    </option>

                    <option value="Education">
                        Education
                    </option>

                    <option value="Business">
                        Business / Accountancy
                    </option>

                    <option value="Criminal Justice">
                        Criminal Justice
                    </option>

                    <option value="General">
                        General
                    </option>

                </select>

            </div>


            <div
                class="current-file-box"
                id="currentFileBox"
            >

                Current file:

                <strong
                    id="currentFileName"
                >
                    -
                </strong>

            </div>


            <div class="form-group">

                <label class="form-label">

                    Replace File

                    <span
                        style="
                            font-weight:400;
                            opacity:.7;
                        "
                    >
                        (Optional)
                    </span>

                </label>

                <input
                    type="file"
                    name="edit_note_file"
                    class="form-input"
                >

                <small
                    style="
                        color:var(--sub-text);
                        font-size:.72rem;
                    "
                >

                    Leave empty if you want to keep the current file.

                </small>

            </div>


            <div class="form-group">

                <label class="form-label">

                    Replace Thumbnail

                    <span
                        style="
                            font-weight:400;
                            opacity:.7;
                        "
                    >
                        (Optional)
                    </span>

                </label>

                <input
                    type="file"
                    name="edit_thumbnail"
                    class="form-input"
                    accept="image/*"
                >

            </div>


            <button
                type="button"
                class="upload-trigger-btn"
                onclick="confirmEditSubmit()"
                style="
                    width:100%;
                    padding:12px;
                "
            >

                Save Changes

            </button>

        </form>

    </div>

</div>


<!-- =====================================================
     HISTORY MODAL
===================================================== -->

<div
    class="modal-overlay"
    id="historyModal"
>

    <div
        class="modal-content"
    >

        <button
            class="close-modal"
            onclick="closeHistoryModal()"
        >
            &times;
        </button>


        <h3
            style="
                margin-bottom:20px;
                color:var(--text);
            "
        >

            Your Upload History

        </h3>


        <?php if (count($user_uploads) > 0): ?>

        <div
            style="
                display:flex;
                flex-direction:column;
                gap:12px;
            "
        >

            <?php foreach ($user_uploads as $item): ?>

            <div
                style="
                    background:var(--bg);
                    border:1px solid var(--border);
                    padding:12px;
                    border-radius:10px;
                "
            >

                <h4
                    style="
                        font-size:.95rem;
                        color:var(--text);
                    "
                >

                    <?php

                    echo htmlspecialchars(
                        $item['title']
                    );

                    ?>

                </h4>


                <div
                    style="
                        display:flex;
                        justify-content:space-between;
                        align-items:center;
                        gap:10px;
                        margin-top:6px;
                    "
                >

                    <span
                        style="
                            font-size:.75rem;
                            color:var(--sub-text);
                        "
                    >

                        Institute:

                        <?php

                        echo htmlspecialchars(
                            $item['institute']
                        );

                        ?>

                    </span>


                    <span
                        style="
                            font-size:.75rem;
                            padding:2px 8px;
                            border-radius:4px;
                            font-weight:700;

                            <?php

                            if (
                                $item['status']
                                === 'approved'
                            ) {

                                echo '
                                    background:
                                    rgba(
                                        57,
                                        255,
                                        20,
                                        .1
                                    );
                                    color:
                                    var(--neon);
                                ';

                            } elseif (
                                $item['status']
                                === 'pending'
                            ) {

                                echo '
                                    background:
                                    rgba(
                                        241,
                                        196,
                                        15,
                                        .1
                                    );
                                    color:
                                    #f1c40f;
                                ';

                            } else {

                                echo '
                                    background:
                                    rgba(
                                        255,
                                        77,
                                        77,
                                        .1
                                    );
                                    color:
                                    #ff4d4d;
                                ';
                            }

                            ?>"
                    >

                        <?php

                        echo ucfirst(
                            $item['status']
                        );

                        ?>

                    </span>

                </div>

            </div>

            <?php endforeach; ?>

        </div>

        <?php else: ?>

        <p
            style="
                color:var(--sub-text);
                text-align:center;
            "
        >

            You haven't uploaded
            any notes yet.

        </p>

        <?php endif; ?>

    </div>

</div>


<!-- =====================================================
     JAVASCRIPT
===================================================== -->

<script>


/* =====================================================
   MOBILE SIDEBAR
===================================================== */

function toggleMobileSidebar() {

    document.body.classList.toggle(
        'sidebar-open'
    );

}


/* =====================================================
   MORE MENU
===================================================== */

function toggleMoreMenu(event) {

    event.preventDefault();

    const submenu =
        document.getElementById(
            'moreSubmenu'
        );

    const chevron =
        document.getElementById(
            'moreChevron'
        );


    if (
        submenu.style.display === 'none' ||
        submenu.style.display === ''
    ) {

        submenu.style.display =
            'flex';

        chevron.style.transform =
            'rotate(180deg)';

    } else {

        submenu.style.display =
            'none';

        chevron.style.transform =
            'rotate(0deg)';
    }
}


/* =====================================================
   THEME
===================================================== */

function toggleGlobalTheme() {

    document.body.classList.toggle(
        'light-mode'
    );


    const isLight =
        document.body.classList.contains(
            'light-mode'
        );


    localStorage.setItem(
        'notesphere_theme',
        isLight
            ? 'light'
            : 'dark'
    );


    updateThemeButton(
        isLight
    );
}


function updateThemeButton(
    isLight
) {

    document.getElementById(
        'themeIcon'
    ).textContent =
        isLight
            ? '🌙'
            : '🌞';


    document.getElementById(
        'themeText'
    ).textContent =
        isLight
            ? 'Dark Mode'
            : 'Light Mode';
}


/* =====================================================
   LOAD THEME
===================================================== */

if (
    localStorage.getItem(
        'notesphere_theme'
    ) === 'light'
) {

    document.body.classList.add(
        'light-mode'
    );

    updateThemeButton(
        true
    );
}


/* =====================================================
   UPLOAD MODAL
===================================================== */

function openUploadModal() {

    document.getElementById(
        'uploadModal'
    ).style.display =
        'flex';
}


function closeUploadModal() {

    document.getElementById(
        'uploadModal'
    ).style.display =
        'none';
}


/* =====================================================
   HISTORY MODAL
===================================================== */

function openHistoryModal() {

    document.getElementById(
        'historyModal'
    ).style.display =
        'flex';
}


function closeHistoryModal() {

    document.getElementById(
        'historyModal'
    ).style.display =
        'none';
}


/* =====================================================
   EDIT MODAL
===================================================== */

function openEditModal(button) {

    const id =
        button.dataset.id;

    const title =
        button.dataset.title;

    const description =
        button.dataset.description;

    const institute =
        button.dataset.institute;

    const file =
        button.dataset.file;


    document.getElementById(
        'edit_note_id'
    ).value =
        id;


    document.getElementById(
        'edit_title'
    ).value =
        title;


    document.getElementById(
        'edit_description'
    ).value =
        description;


    document.getElementById(
        'edit_institute'
    ).value =
        institute;


    document.getElementById(
        'currentFileName'
    ).textContent =
        file;


    closeAllNoteMenus();


    document.getElementById(
        'editModal'
    ).style.display =
        'flex';
}


function closeEditModal() {

    document.getElementById(
        'editModal'
    ).style.display =
        'none';
}


/* =====================================================
   CONFIRM EDIT
===================================================== */

function confirmEditSubmit() {

    Swal.fire({

        title:
            'Save Changes?',

        text:
            'Your edited note will be submitted again for admin review.',

        icon:
            'question',

        showCancelButton:
            true,

        confirmButtonText:
            'Yes, Save Changes',

        cancelButtonText:
            'Cancel',

        confirmButtonColor:
            '#39FF14',

        cancelButtonColor:
            '#6b7280',

        background:
            document.body.classList.contains(
                'light-mode'
            )
                ? '#ffffff'
                : '#111111',

        color:
            document.body.classList.contains(
                'light-mode'
            )
                ? '#000000'
                : '#ffffff'

    }).then(
        function(result) {

            if (
                result.isConfirmed
            ) {

                document.getElementById(
                    'editNoteForm'
                ).submit();

            }

        }
    );
}


/* =====================================================
   SUBMISSION TYPE
===================================================== */

function toggleSubmissionType() {

    const type =
        document.getElementById(
            'submissionTypeSelect'
        ).value;


    const fileGroup =
        document.getElementById(
            'fileUploadGroup'
        );


    const writeGroup =
        document.getElementById(
            'writeNoteGroup'
        );


    if (
        type === 'upload'
    ) {

        fileGroup.style.display =
            'block';

        writeGroup.style.display =
            'none';

    } else {

        fileGroup.style.display =
            'none';

        writeGroup.style.display =
            'block';
    }
}


/* =====================================================
   NOTE MENU
===================================================== */

function toggleNoteMenu(
    event,
    noteId
) {

    event.stopPropagation();


    const menu =
        document.getElementById(
            'noteMenu_' +
            noteId
        );


    document
        .querySelectorAll(
            '.note-menu'
        )
        .forEach(
            function(item) {

                if (
                    item !== menu
                ) {

                    item.classList.remove(
                        'show'
                    );
                }

            }
        );


    menu.classList.toggle(
        'show'
    );
}


/* =====================================================
   CLOSE MENUS
===================================================== */

function closeAllNoteMenus() {

    document
        .querySelectorAll(
            '.note-menu'
        )
        .forEach(
            function(menu) {

                menu.classList.remove(
                    'show'
                );

            }
        );
}


/* =====================================================
   COPY NOTE LINK
===================================================== */

function copyNoteLink(
    noteId
) {

    const url =
        window.location.origin +
        window.location.pathname +
        '?note_id=' +
        encodeURIComponent(
            noteId
        );


    closeAllNoteMenus();


    if (
        navigator.clipboard &&
        window.isSecureContext
    ) {

        navigator.clipboard
            .writeText(url)
            .then(
                function() {

                    Swal.fire({

                        icon:
                            'success',

                        title:
                            'Link Copied!',

                        text:
                            'You can now send this note link to another student.',

                        confirmButtonText:
                            'Done',

                        confirmButtonColor:
                            '#39FF14',

                        background:
                            document.body.classList.contains(
                                'light-mode'
                            )
                                ? '#ffffff'
                                : '#111111',

                        color:
                            document.body.classList.contains(
                                'light-mode'
                            )
                                ? '#000000'
                                : '#ffffff'

                    });

                }
            )
            .catch(
                function() {

                    copyUsingFallback(
                        url
                    );

                }
            );

    } else {

        copyUsingFallback(
            url
        );
    }
}


/* =====================================================
   COPY FALLBACK
===================================================== */

function copyUsingFallback(
    url
) {

    const tempInput =
        document.createElement(
            'input'
        );


    tempInput.value =
        url;


    document.body.appendChild(
        tempInput
    );


    tempInput.select();


    tempInput.setSelectionRange(
        0,
        99999
    );


    let copied = false;


    try {

        copied =
            document.execCommand(
                'copy'
            );

    } catch (
        error
    ) {

        copied =
            false;
    }


    document.body.removeChild(
        tempInput
    );


    if (
        copied
    ) {

        Swal.fire({

            icon:
                'success',

            title:
                'Link Copied!',

            text:
                'The note link is ready to share.',

            confirmButtonText:
                'Done',

            confirmButtonColor:
                '#39FF14'

        });

    } else {

        Swal.fire({

            icon:
                'info',

            title:
                'Copy the Link',

            html:
                '<div style="word-break:break-all;text-align:left;padding:10px;border:1px solid #333;border-radius:8px;">' +
                escapeHtml(url) +
                '</div>',

            confirmButtonText:
                'Okay',

            confirmButtonColor:
                '#39FF14'

        });

    }
}


/* =====================================================
   DELETE
===================================================== */

function confirmDelete(
    noteId,
    noteTitle
) {

    closeAllNoteMenus();


    Swal.fire({

        title:
            'Delete this note?',

        html:
            'Are you sure you want to delete <strong>"' +
            escapeHtml(noteTitle) +
            '"</strong>?<br><br>This action cannot be undone.',

        icon:
            'warning',

        showCancelButton:
            true,

        confirmButtonText:
            'Yes, Delete',

        cancelButtonText:
            'Cancel',

        confirmButtonColor:
            '#ff4d4d',

        cancelButtonColor:
            '#6b7280',

        background:
            document.body.classList.contains(
                'light-mode'
            )
                ? '#ffffff'
                : '#111111',

        color:
            document.body.classList.contains(
                'light-mode'
            )
                ? '#000000'
                : '#ffffff'

    }).then(
        function(result) {

            if (
                result.isConfirmed
            ) {

                document.getElementById(
                    'deleteNoteId'
                ).value =
                    noteId;


                document.getElementById(
                    'deleteForm'
                ).submit();

            }

        }
    );
}


/* =====================================================
   ESCAPE HTML
===================================================== */

function escapeHtml(
    text
) {

    const div =
        document.createElement(
            'div'
        );


    div.textContent =
        text;


    return div.innerHTML;
}


/* =====================================================
   OUTSIDE CLICK
===================================================== */

window.addEventListener(
    'click',
    function(event) {

        const uploadModal =
            document.getElementById(
                'uploadModal'
            );


        const historyModal =
            document.getElementById(
                'historyModal'
            );


        const editModal =
            document.getElementById(
                'editModal'
            );


        if (
            event.target ===
            uploadModal
        ) {

            closeUploadModal();
        }


        if (
            event.target ===
            historyModal
        ) {

            closeHistoryModal();
        }


        if (
            event.target ===
            editModal
        ) {

            closeEditModal();
        }


        if (
            !event.target.closest(
                '.note-menu'
            ) &&
            !event.target.closest(
                '.dots-btn'
            )
        ) {

            closeAllNoteMenus();
        }

    }
);

</script>


<!-- =====================================================
     SERVER SIDE SWEET ALERT
===================================================== -->

<script>

<?php if (
    $action_type === 'upload_success'
): ?>

Swal.fire({

    icon:
        'success',

    title:
        'Upload Submitted!',

    text:
        <?php
        echo json_encode(
            $action_success
        );
        ?>,

    confirmButtonText:
        'Okay',

    confirmButtonColor:
        '#39FF14',

    background:
        document.body.classList.contains(
            'light-mode'
        )
            ? '#ffffff'
            : '#111111',

    color:
        document.body.classList.contains(
            'light-mode'
        )
            ? '#000000'
            : '#ffffff'

});

<?php endif; ?>


<?php if (
    $action_type === 'edit_success'
): ?>

Swal.fire({

    icon:
        'success',

    title:
        'Note Updated!',

    text:
        <?php
        echo json_encode(
            $action_success
        );
        ?>,

    confirmButtonText:
        'Okay',

    confirmButtonColor:
        '#39FF14'

});

<?php endif; ?>


<?php if (
    $action_type === 'delete_success'
): ?>

Swal.fire({

    icon:
        'success',

    title:
        'Deleted!',

    text:
        <?php
        echo json_encode(
            $action_success
        );
        ?>,

    confirmButtonText:
        'Okay',

    confirmButtonColor:
        '#39FF14'

});

<?php endif; ?>


<?php if (
    $action_type === 'rating_success'
): ?>

Swal.fire({

    icon:
        'success',

    title:
        'Rating Saved!',

    text:
        <?php
        echo json_encode(
            $action_success
        );
        ?>,

    timer:
        1400,

    showConfirmButton:
        false,

    background:
        document.body.classList.contains(
            'light-mode'
        )
            ? '#ffffff'
            : '#111111',

    color:
        document.body.classList.contains(
            'light-mode'
        )
            ? '#000000'
            : '#ffffff'

});

<?php endif; ?>


<?php if (
    $action_type === 'error' &&
    !empty($action_error)
): ?>

Swal.fire({

    icon:
        'error',

    title:
        'Something went wrong',

    text:
        <?php
        echo json_encode(
            $action_error
        );
        ?>,

    confirmButtonText:
        'Okay',

    confirmButtonColor:
        '#ff4d4d'

});

<?php endif; ?>

</script>


</body>

</html>