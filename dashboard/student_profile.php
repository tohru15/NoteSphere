<?php
/**
 * NoteSphere - Student Profile
 */

require_once __DIR__ . '/../includes/db_config.php';

/** @var PDO $conn */

// =========================================================
// STUDENT SECURITY CHECK
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

$success_msg = '';
$error_msg = '';


// =========================================================
// HELPER
// =========================================================
function h($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


// =========================================================
// CHECK IF PROFILE_PIC COLUMN EXISTS
// =========================================================
$has_profile_pic = false;

try {

    $column_check = $conn->query("
        SHOW COLUMNS FROM users LIKE 'profile_pic'
    ");

    $has_profile_pic =
        $column_check->fetch(PDO::FETCH_ASSOC) !== false;

} catch (PDOException $e) {

    $has_profile_pic = false;
}


// =========================================================
// UPDATE PROFILE
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');

    // ---------------------------------------------------------
    // BASIC VALIDATION
    // ---------------------------------------------------------
    if ($full_name === '') {

        $error_msg =
            "Please enter your full name.";

    } elseif ($email === '') {

        $error_msg =
            "Please enter your email address.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error_msg =
            "Please enter a valid email address.";
    }


    // ---------------------------------------------------------
    // GET CURRENT PROFILE PICTURE
    // ---------------------------------------------------------
    $current_db_pic = null;

    if (
        empty($error_msg) &&
        $has_profile_pic
    ) {

        try {

            $stmt_old = $conn->prepare("
                SELECT profile_pic
                FROM users
                WHERE user_id = ?
                LIMIT 1
            ");

            $stmt_old->execute([
                $user_id
            ]);

            $current_db_pic =
                $stmt_old->fetchColumn();

        } catch (PDOException $e) {

            $current_db_pic = null;
        }
    }


    $profile_pic_db = null;


    // =========================================================
    // PROFILE PICTURE UPLOAD
    // =========================================================
    if (
        empty($error_msg) &&
        isset($_FILES['profile_pic']) &&
        $_FILES['profile_pic']['error'] !== UPLOAD_ERR_NO_FILE
    ) {

        // -----------------------------------------------------
        // PROFILE_PIC COLUMN CHECK
        // -----------------------------------------------------
        if (!$has_profile_pic) {

            $error_msg =
                "Your users table does not have a profile_pic column. Please add it first.";

        } else {

            if (
                $_FILES['profile_pic']['error'] !==
                UPLOAD_ERR_OK
            ) {

                $error_msg =
                    "There was a problem uploading the image. Please try again.";

            } else {

                $file_tmp =
                    $_FILES['profile_pic']['tmp_name'];

                $file_name =
                    $_FILES['profile_pic']['name'];

                $file_ext = strtolower(
                    pathinfo(
                        $file_name,
                        PATHINFO_EXTENSION
                    )
                );


                // -------------------------------------------------
                // ALLOWED IMAGE EXTENSIONS
                // -------------------------------------------------
                $allowed_extensions = [
                    'jpg',
                    'jpeg',
                    'png',
                    'webp',
                    'gif',
                    'bmp',
                    'jfif',
                    'heic'
                ];


                if (
                    !in_array(
                        $file_ext,
                        $allowed_extensions,
                        true
                    )
                ) {

                    $error_msg =
                        "Please select a valid image file.";

                } else {

                    // -------------------------------------------------
                    // UPLOAD DIRECTORY
                    // -------------------------------------------------
                    $upload_dir =
                        __DIR__ . '/../uploads/';

                    if (!is_dir($upload_dir)) {

                        if (
                            !mkdir(
                                $upload_dir,
                                0777,
                                true
                            )
                        ) {

                            $error_msg =
                                "Unable to create the upload folder.";
                        }
                    }


                    if (empty($error_msg)) {

                        // -------------------------------------------------
                        // UNIQUE FILE NAME
                        // -------------------------------------------------
                        $new_file_name =
                            'user_' .
                            $user_id .
                            '_' .
                            time() .
                            '_' .
                            uniqid() .
                            '.' .
                            $file_ext;

                        $destination =
                            $upload_dir .
                            $new_file_name;


                        // -------------------------------------------------
                        // MOVE FILE
                        // -------------------------------------------------
                        if (
                            move_uploaded_file(
                                $file_tmp,
                                $destination
                            )
                        ) {

                            $profile_pic_db =
                                'uploads/' .
                                $new_file_name;


                            // -------------------------------------------------
                            // DELETE OLD PROFILE PICTURE
                            // -------------------------------------------------
                            if (
                                !empty($current_db_pic) &&
                                strpos(
                                    $current_db_pic,
                                    'http'
                                ) !== 0 &&
                                $current_db_pic !== 'profile'
                            ) {

                                $old_file_path =
                                    __DIR__ .
                                    '/../' .
                                    ltrim(
                                        $current_db_pic,
                                        '/'
                                    );

                                if (
                                    file_exists(
                                        $old_file_path
                                    )
                                ) {

                                    @unlink(
                                        $old_file_path
                                    );
                                }
                            }

                        } else {

                            $error_msg =
                                "There was a problem uploading the image. Please try again.";
                        }
                    }
                }
            }
        }
    }


    // =========================================================
    // SAVE PROFILE
    // =========================================================
    if (empty($error_msg)) {

        try {

            // -------------------------------------------------
            // WITH NEW PROFILE PICTURE
            // -------------------------------------------------
            if (
                $profile_pic_db !== null &&
                $has_profile_pic
            ) {

                $update_stmt = $conn->prepare("
                    UPDATE users
                    SET
                        full_name = ?,
                        email = ?,
                        profile_pic = ?
                    WHERE user_id = ?
                ");

                $update_stmt->execute([
                    $full_name,
                    $email,
                    $profile_pic_db,
                    $user_id
                ]);

            } else {

                // -------------------------------------------------
                // WITHOUT PROFILE PICTURE
                // -------------------------------------------------
                $update_stmt = $conn->prepare("
                    UPDATE users
                    SET
                        full_name = ?,
                        email = ?
                    WHERE user_id = ?
                ");

                $update_stmt->execute([
                    $full_name,
                    $email,
                    $user_id
                ]);
            }


            // -------------------------------------------------
            // UPDATE SESSION NAME
            // -------------------------------------------------
            $_SESSION['full_name'] =
                $full_name;


            // -------------------------------------------------
            // SUCCESS
            // -------------------------------------------------
            $_SESSION['success_msg'] =
                "Your profile has been successfully updated!";


            header(
                "Location: student_profile.php"
            );

            exit();

        } catch (PDOException $e) {

            $error_msg =
                "Unable to update your profile. Please try again.";
        }
    }
}


// =========================================================
// GET CURRENT USER
// =========================================================
try {

    if ($has_profile_pic) {

        $stmt = $conn->prepare("
            SELECT *
            FROM users
            WHERE user_id = ?
            LIMIT 1
        ");

    } else {

        $stmt = $conn->prepare("
            SELECT
                user_id,
                full_name,
                email
            FROM users
            WHERE user_id = ?
            LIMIT 1
        ");
    }


    $stmt->execute([
        $user_id
    ]);

    $user = $stmt->fetch(
        PDO::FETCH_ASSOC
    );

} catch (PDOException $e) {

    $user = false;

    $error_msg =
        "Unable to load your profile information.";
}


// =========================================================
// USER NOT FOUND
// =========================================================
if (!$user) {

    session_destroy();

    header(
        "Location: ../auth/login.php"
    );

    exit();
}


// =========================================================
// PROFILE PICTURE PATH
// =========================================================
$profile_pic = '';

if (
    $has_profile_pic &&
    isset($user['profile_pic']) &&
    !empty($user['profile_pic']) &&
    $user['profile_pic'] !== 'profile'
) {

    if (
        strpos(
            $user['profile_pic'],
            'http'
        ) === 0
    ) {

        $profile_pic =
            $user['profile_pic'];

    } else {

        $profile_pic =
            '../' .
            ltrim(
                $user['profile_pic'],
                '/'
            );
    }
}


// =========================================================
// PROFILE INITIAL
// =========================================================
$profile_initial = 'S';

if (!empty($user['full_name'])) {

    $profile_initial =
        mb_strtoupper(
            mb_substr(
                trim($user['full_name']),
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
        Edit Profile | NoteSphere
    </title>


    <!-- =====================================================
         THEME
         READS THE THEME SELECTED ON STUDENT HOME
    ====================================================== -->
    <script>
        (function () {

            const savedTheme =
                localStorage.getItem('notesphere_theme') ||
                localStorage.getItem('theme') ||
                'dark';

            document.documentElement.setAttribute(
                'data-theme',
                savedTheme
            );

        })();
    </script>


    <!-- =====================================================
         GOOGLE FONT
    ====================================================== -->
    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >


    <style>

        /* =====================================================
           RESET
        ====================================================== */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }


        /* =====================================================
           DARK THEME - DEFAULT
        ====================================================== */

        :root {

            --neon: #39FF14;

            --neon-glow:
                rgba(57, 255, 20, 0.15);

            --bg-overlay:
                linear-gradient(
                    rgba(11, 11, 11, 0.75),
                    rgba(11, 11, 11, 0.85)
                );

            --card-bg:
                rgba(18, 18, 20, 0.92);

            --text:
                #f8fafc;

            --border-color:
                #27272a;

            --sub-text:
                #94a3b8;

            --input-bg:
                #09090b;

            --hover-bg:
                #1e1e24;

            --shadow:
                rgba(0, 0, 0, 0.35);
        }


        /* =====================================================
           LIGHT THEME
        ====================================================== */

        html[data-theme="light"] {

            --neon: #10b981;

            --neon-glow:
                rgba(16, 185, 129, 0.15);

            --bg-overlay:
                linear-gradient(
                    rgba(244, 246, 249, 0.45),
                    rgba(244, 246, 249, 0.65)
                );

            --card-bg:
                rgba(255, 255, 255, 0.92);

            --text:
                #0f172a;

            --border-color:
                #e2e8f0;

            --sub-text:
                #64748b;

            --input-bg:
                #f8fafc;

            --hover-bg:
                #f1f5f9;

            --shadow:
                rgba(0, 0, 0, 0.10);
        }


        /* =====================================================
           HTML
        ====================================================== */

        html {

            background:
                var(--bg-overlay),
                url('../assets/registerbg.png')
                no-repeat
                center center
                fixed;

            background-size: cover;

            min-height: 100%;
        }


        /* =====================================================
           BODY
        ====================================================== */

        body {

            background: transparent;

            color: var(--text);

            display: flex;

            justify-content: center;

            align-items: center;

            min-height: 100vh;

            padding: 20px;

            transition:
                color 0.3s ease;
        }


        /* =====================================================
           CONTAINER
        ====================================================== */

        .container {

            width: 100%;

            max-width: 500px;

            margin: auto;
        }


        /* =====================================================
           CARD
        ====================================================== */

        .card {

            background:
                var(--card-bg);

            backdrop-filter:
                blur(8px);

            -webkit-backdrop-filter:
                blur(8px);

            border:
                1px solid var(--border-color);

            border-radius:
                20px;

            padding:
                30px;

            box-shadow:
                0 12px 32px
                var(--shadow);

            transition:
                background-color 0.3s ease,
                border-color 0.3s ease,
                color 0.3s ease;
        }


        /* =====================================================
           HEADER
        ====================================================== */

        .header {

            display: flex;

            align-items: center;

            justify-content: space-between;

            margin-bottom: 25px;
        }


        .header h2 {

            font-size:
                1.15rem;

            font-weight:
                700;

            color:
                var(--text);

            display: flex;

            align-items: center;

            gap: 8px;
        }


        /* =====================================================
           CLOSE BUTTON
        ====================================================== */

        .close-btn-card {

            width:
                34px;

            height:
                34px;

            border-radius:
                50%;

            background:
                var(--hover-bg);

            border:
                1px solid var(--border-color);

            display: flex;

            align-items: center;

            justify-content: center;

            cursor: pointer;

            color:
                var(--sub-text);

            text-decoration:
                none;

            transition:
                all 0.2s ease;

            flex-shrink: 0;
        }


        .close-btn-card svg {

            width:
                16px;

            height:
                16px;

            stroke:
                currentColor;

            stroke-width:
                2.2;

            fill:
                none;

            stroke-linecap:
                round;

            stroke-linejoin:
                round;
        }


        .close-btn-card:hover {

            background:
                var(--border-color);

            color:
                var(--neon);

            border-color:
                var(--neon);
        }


        /* =====================================================
           AVATAR
        ====================================================== */

        .avatar-preview {

            display: flex;

            flex-direction: column;

            align-items: center;

            margin-bottom: 20px;
        }


        .avatar-preview img {

            width:
                90px;

            height:
                90px;

            border-radius:
                50%;

            object-fit:
                cover;

            border:
                2px solid var(--neon);

            margin-bottom:
                12px;

            background:
                var(--border-color);
        }


        .avatar-initials {

            width:
                90px;

            height:
                90px;

            border-radius:
                50%;

            background:
                var(--neon-glow);

            border:
                2px solid var(--neon);

            color:
                var(--neon);

            display: flex;

            align-items: center;

            justify-content: center;

            font-weight:
                800;

            font-size:
                2.2rem;

            margin-bottom:
                12px;
        }


        /* =====================================================
           FORM
        ====================================================== */

        .form-group {

            margin-bottom:
                15px;
        }


        .form-group label {

            display:
                block;

            font-size:
                0.85rem;

            font-weight:
                600;

            color:
                var(--sub-text);

            margin-bottom:
                6px;
        }


        .form-control {

            width:
                100%;

            padding:
                12px 14px;

            background:
                var(--input-bg);

            border:
                1px solid var(--border-color);

            color:
                var(--text);

            border-radius:
                12px;

            font-size:
                0.95rem;

            outline:
                none;

            transition:
                border-color 0.2s,
                background-color 0.3s,
                color 0.3s;
        }


        .form-control:focus {

            border-color:
                var(--neon);
        }


        /* =====================================================
           FILE INPUT
        ====================================================== */

        input[type="file"] {

            cursor:
                pointer;
        }


        input[type="file"]::file-selector-button {

            background:
                var(--hover-bg);

            color:
                var(--text);

            border:
                1px solid var(--border-color);

            border-radius:
                8px;

            padding:
                6px 10px;

            margin-right:
                8px;

            cursor:
                pointer;
        }


        input[type="file"]::file-selector-button:hover {

            border-color:
                var(--neon);
        }


        /* =====================================================
           SAVE BUTTON
        ====================================================== */

        .btn-save {

            background:
                var(--neon);

            color:
                #000;

            border:
                none;

            padding:
                12px;

            font-weight:
                800;

            border-radius:
                12px;

            cursor:
                pointer;

            width:
                100%;

            font-size:
                0.95rem;

            margin-top:
                10px;

            transition:
                opacity 0.2s,
                transform 0.2s;
        }


        .btn-save:hover {

            opacity:
                0.9;

            transform:
                scale(1.01);
        }


        /* =====================================================
           SUCCESS MESSAGE
        ====================================================== */

        .alert-success {

            background:
                rgba(16, 185, 129, 0.1);

            border:
                1px solid var(--neon);

            color:
                var(--neon);

            padding:
                10px;

            border-radius:
                10px;

            margin-bottom:
                15px;

            font-size:
                0.85rem;

            text-align:
                center;
        }


        /* =====================================================
           ERROR MESSAGE
        ====================================================== */

        .alert-error {

            background:
                rgba(255, 77, 77, 0.1);

            border:
                1px solid #ff4d4d;

            color:
                #ff4d4d;

            padding:
                10px;

            border-radius:
                10px;

            margin-bottom:
                15px;

            font-size:
                0.85rem;

            text-align:
                center;
        }


        /* =====================================================
           MOBILE
        ====================================================== */

        @media (max-width: 520px) {

            body {

                padding:
                    12px;
            }

            .card {

                padding:
                    22px;

                border-radius:
                    17px;
            }

            .header h2 {

                font-size:
                    1rem;
            }

            .avatar-preview img,
            .avatar-initials {

                width:
                    80px;

                height:
                    80px;
            }

            .avatar-initials {

                font-size:
                    1.9rem;
            }
        }

    </style>

</head>


<body>

<div class="container">

    <div class="card">

        <!-- =================================================
             HEADER
        ================================================== -->

        <div class="header">

            <h2>
                ⚙️ Edit Profile
            </h2>

            <a
                href="help_board.php"
                class="close-btn-card"
                title="Back"
            >

                <svg
                    viewBox="0 0 24 24"
                >

                    <line
                        x1="18"
                        y1="6"
                        x2="6"
                        y2="18"
                    ></line>

                    <line
                        x1="6"
                        y1="6"
                        x2="18"
                        y2="18"
                    ></line>

                </svg>

            </a>

        </div>


        <!-- =================================================
             SUCCESS MESSAGE
        ================================================== -->

        <?php if (isset($_SESSION['success_msg'])): ?>

            <div class="alert-success">

                <?php

                echo h(
                    $_SESSION['success_msg']
                );

                unset(
                    $_SESSION['success_msg']
                );

                ?>

            </div>

        <?php endif; ?>


        <!-- =================================================
             ERROR MESSAGE
        ================================================== -->

        <?php if (!empty($error_msg)): ?>

            <div class="alert-error">

                <?php
                echo h($error_msg);
                ?>

            </div>

        <?php endif; ?>


        <!-- =================================================
             PROFILE FORM
        ================================================== -->

        <form
            action="student_profile.php"
            method="POST"
            enctype="multipart/form-data"
        >

            <!-- =============================================
                 FULL NAME
            ============================================== -->

            <div class="form-group">

                <label>
                    Full Name
                </label>

                <input
                    type="text"
                    name="full_name"
                    class="form-control"
                    value="<?php
                        echo h(
                            $user['full_name']
                        );
                    ?>"
                    required
                >

            </div>


            <!-- =============================================
                 EMAIL
            ============================================== -->

            <div class="form-group">

                <label>
                    Email Address
                </label>

                <input
                    type="email"
                    name="email"
                    class="form-control"
                    value="<?php
                        echo h(
                            $user['email']
                        );
                    ?>"
                    required
                >

            </div>


            <!-- =============================================
                 SAVE
            ============================================== -->

            <button
                type="submit"
                class="btn-save"
            >
                Save Changes
            </button>

        </form>

    </div>

</div>

</body>
</html>