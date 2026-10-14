<?php

// =========================================================
// NoteSphere - Edit User
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
// CSRF TOKEN
// =========================================================

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

// =========================================================
// GET USER ID
// =========================================================

$user_id = isset($_GET['user_id'])
    ? (int) $_GET['user_id']
    : 0;

if ($user_id <= 0) {

    $_SESSION['admin_message'] = 'Invalid user ID.';
    $_SESSION['admin_message_type'] = 'error';

    header("Location: manage_users.php");
    exit();
}

// =========================================================
// GET USER DATA
// =========================================================

try {

    $stmt = $conn->prepare("
        SELECT
            user_id,
            full_name,
            email,
            course,
            student_status,
            year_level,
            section,
            enrolled_subjects,
            role,
            created_at,
            profile_pic
        FROM users
        WHERE user_id = ?
        LIMIT 1
    ");

    $stmt->execute([$user_id]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {

        $_SESSION['admin_message'] = 'User not found.';
        $_SESSION['admin_message_type'] = 'error';

        header("Location: manage_users.php");
        exit();
    }

} catch (PDOException $e) {

    die(
        "Database Error: " .
        htmlspecialchars($e->getMessage())
    );
}

// =========================================================
// UPDATE USER
// =========================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // -----------------------------------------------------
    // CSRF CHECK
    // -----------------------------------------------------

    $submitted_token = $_POST['csrf_token'] ?? '';

    if (
        empty($submitted_token) ||
        !hash_equals($csrf_token, $submitted_token)
    ) {

        $_SESSION['admin_message'] =
            'Invalid security token. Please try again.';

        $_SESSION['admin_message_type'] = 'error';

        header(
            "Location: edit_user.php?user_id=" .
            $user_id
        );

        exit();
    }

    // -----------------------------------------------------
    // FORM DATA
    // -----------------------------------------------------

    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $course = trim($_POST['course'] ?? '');
    $student_status = trim($_POST['student_status'] ?? '');
    $year_level = trim($_POST['year_level'] ?? '');
    $section = trim($_POST['section'] ?? '');
    $enrolled_subjects = trim(
        $_POST['enrolled_subjects'] ?? ''
    );
    $role = trim($_POST['role'] ?? '');

    // -----------------------------------------------------
    // VALIDATION
    // -----------------------------------------------------

    if ($full_name === '') {

        $_SESSION['admin_message'] =
            'Full name is required.';

        $_SESSION['admin_message_type'] = 'error';

        header(
            "Location: edit_user.php?user_id=" .
            $user_id
        );

        exit();
    }

    if ($email === '') {

        $_SESSION['admin_message'] =
            'Email is required.';

        $_SESSION['admin_message_type'] = 'error';

        header(
            "Location: edit_user.php?user_id=" .
            $user_id
        );

        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $_SESSION['admin_message'] =
            'Please enter a valid email address.';

        $_SESSION['admin_message_type'] = 'error';

        header(
            "Location: edit_user.php?user_id=" .
            $user_id
        );

        exit();
    }

    // -----------------------------------------------------
    // ALLOWED ROLES
    // -----------------------------------------------------

    $allowed_roles = [
        'student',
        'professor',
        'admin'
    ];

    if (!in_array($role, $allowed_roles, true)) {

        $_SESSION['admin_message'] =
            'Invalid user role.';

        $_SESSION['admin_message_type'] = 'error';

        header(
            "Location: edit_user.php?user_id=" .
            $user_id
        );

        exit();
    }

    // -----------------------------------------------------
    // CHECK DUPLICATE EMAIL
    // -----------------------------------------------------

    try {

        $email_check = $conn->prepare("
            SELECT user_id
            FROM users
            WHERE email = ?
            AND user_id != ?
            LIMIT 1
        ");

        $email_check->execute([
            $email,
            $user_id
        ]);

        if ($email_check->fetch(PDO::FETCH_ASSOC)) {

            $_SESSION['admin_message'] =
                'That email address is already being used by another account.';

            $_SESSION['admin_message_type'] = 'error';

            header(
                "Location: edit_user.php?user_id=" .
                $user_id
            );

            exit();
        }

        // -------------------------------------------------
        // UPDATE USER
        // -------------------------------------------------

        $update = $conn->prepare("
            UPDATE users
            SET
                full_name = ?,
                email = ?,
                course = ?,
                student_status = ?,
                year_level = ?,
                section = ?,
                enrolled_subjects = ?,
                role = ?
            WHERE user_id = ?
            LIMIT 1
        ");

        $update->execute([
            $full_name,
            $email,
            $course,
            $student_status,
            $year_level,
            $section,
            $enrolled_subjects,
            $role,
            $user_id
        ]);

        // -------------------------------------------------
        // SUCCESS
        // -------------------------------------------------

        $_SESSION['admin_message'] =
            'User account updated successfully.';

        $_SESSION['admin_message_type'] =
            'success';

        header("Location: manage_users.php");
        exit();

    } catch (PDOException $e) {

        $_SESSION['admin_message'] =
            'Unable to update user: ' .
            $e->getMessage();

        $_SESSION['admin_message_type'] =
            'error';

        header(
            "Location: edit_user.php?user_id=" .
            $user_id
        );

        exit();
    }
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
// ADMIN NAME
// =========================================================

$admin_name = 'Administrator';

try {

    $admin_stmt = $conn->prepare("
        SELECT full_name
        FROM users
        WHERE user_id = ?
        LIMIT 1
    ");

    $admin_stmt->execute([
        (int) $_SESSION['user_id']
    ]);

    $admin = $admin_stmt->fetch(PDO::FETCH_ASSOC);

    if (
        $admin &&
        !empty($admin['full_name'])
    ) {
        $admin_name = $admin['full_name'];
    }

} catch (PDOException $e) {
    // Keep default
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

    <title>Edit User - NoteSphere</title>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>

        /* =====================================================
           ROOT VARIABLES
        ===================================================== */

        :root {

            --bg: #f4f6fa;

            --card: #ffffff;

            --text: #202938;

            --muted: #7b8494;

            --border: #e4e8ef;

            --input-bg: #ffffff;

            --primary: #3157a4;

            --primary-hover: #274789;

            --soft: #f5f7fb;

            --close-bg: #f1f3f6;

            --close-hover: #e5e8ed;

            --shadow:
                0 12px 35px rgba(
                    31,
                    41,
                    55,
                    0.08
                );
        }

        /* =====================================================
           DARK MODE
        ===================================================== */

        body.dark-mode {

            --bg: #121722;

            --card: #1b2230;

            --text: #f2f4f8;

            --muted: #9ba5b5;

            --border: #303949;

            --input-bg: #151b26;

            --primary: #5d86dc;

            --primary-hover: #7298e6;

            --soft: #202837;

            --close-bg: #252d3a;

            --close-hover: #313b4b;

            --shadow:
                0 15px 40px rgba(
                    0,
                    0,
                    0,
                    0.30
                );
        }

        /* =====================================================
           RESET
        ===================================================== */

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html,
        body {
            min-height: 100%;
        }

        body {

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: var(--bg);

            color: var(--text);

            transition:
                background 0.25s ease,
                color 0.25s ease;
        }

        /* =====================================================
           PAGE
        ===================================================== */

        .page {

            min-height: 100vh;

            padding:
                40px
                25px;
        }

        .container {

            width: 100%;

            max-width: 920px;

            margin: 0 auto;
        }

        /* =====================================================
           EDIT CARD
        ===================================================== */

        .card {

            position: relative;

            width: 100%;

            background: var(--card);

            border:
                1px solid
                var(--border);

            border-radius: 20px;

            padding: 36px;

            box-shadow: var(--shadow);

            transition:
                background 0.25s ease,
                border-color 0.25s ease,
                box-shadow 0.25s ease;
        }

        /* =====================================================
           CLOSE BUTTON
        ===================================================== */

        .close-button {

            position: absolute;

            top: 20px;

            right: 20px;

            width: 40px;

            height: 40px;

            border: none;

            border-radius: 50%;

            background: var(--close-bg);

            color: var(--muted);

            font-size: 24px;

            line-height: 40px;

            text-align: center;

            text-decoration: none;

            cursor: pointer;

            transition: 0.2s ease;
        }

        .close-button:hover {

            background:
                var(--close-hover);

            color: var(--text);

            transform: rotate(90deg);
        }

        /* =====================================================
           HEADER
        ===================================================== */

        .header {

            padding-right: 55px;

            margin-bottom: 28px;
        }

        .header h1 {

            font-size: 28px;

            font-weight: 700;

            color: var(--text);

            margin-bottom: 8px;
        }

        .header p {

            font-size: 14px;

            line-height: 1.6;

            color: var(--muted);
        }

        /* =====================================================
           ACCOUNT INFO
        ===================================================== */

        .account-info {

            margin-bottom: 28px;

            padding: 17px 18px;

            background: var(--soft);

            border:
                1px solid
                var(--border);

            border-radius: 13px;

            color: var(--muted);

            font-size: 14px;

            line-height: 1.8;
        }

        .account-info strong {

            color: var(--text);
        }

        /* =====================================================
           FORM GRID
        ===================================================== */

        .form-grid {

            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 20px;
        }

        .form-group {

            display: flex;

            flex-direction: column;
        }

        .form-group.full {

            grid-column:
                1 / -1;
        }

        /* =====================================================
           LABEL
        ===================================================== */

        label {

            margin-bottom: 8px;

            font-size: 13px;

            font-weight: 700;

            color: var(--text);
        }

        /* =====================================================
           INPUTS
        ===================================================== */

        input,
        select,
        textarea {

            width: 100%;

            border:
                1px solid
                var(--border);

            border-radius: 11px;

            padding:
                12px
                14px;

            background: var(--input-bg);

            color: var(--text);

            font-family: inherit;

            font-size: 14px;

            outline: none;

            transition: 0.2s ease;
        }

        input::placeholder,
        textarea::placeholder {

            color: var(--muted);
        }

        input:focus,
        select:focus,
        textarea:focus {

            border-color:
                var(--primary);

            box-shadow:
                0 0 0 3px
                rgba(
                    49,
                    87,
                    164,
                    0.12
                );
        }

        textarea {

            min-height: 110px;

            resize: vertical;
        }

        /* =====================================================
           FORM ACTIONS
        ===================================================== */

        .form-actions {

            display: flex;

            justify-content: flex-end;

            gap: 12px;

            margin-top: 30px;

            padding-top: 24px;

            border-top:
                1px solid
                var(--border);
        }

        .btn {

            border: none;

            border-radius: 10px;

            padding:
                12px
                22px;

            font-size: 14px;

            font-weight: 700;

            cursor: pointer;

            text-decoration: none;

            display: inline-flex;

            align-items: center;

            justify-content: center;

            transition: 0.2s ease;
        }

        .btn-cancel {

            background:
                var(--soft);

            color: var(--text);

            border:
                1px solid
                var(--border);
        }

        .btn-cancel:hover {

            transform: translateY(-1px);
        }

        .btn-save {

            background:
                var(--primary);

            color: #ffffff;

            min-width: 140px;
        }

        .btn-save:hover {

            background:
                var(--primary-hover);

            transform: translateY(-1px);
        }

        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 700px) {

            .page {

                padding:
                    20px
                    12px;
            }

            .card {

                padding:
                    25px
                    20px;
            }

            .form-grid {

                grid-template-columns: 1fr;
            }

            .form-group.full {

                grid-column: auto;
            }

            .header {

                padding-right: 45px;
            }

            .header h1 {

                font-size: 23px;
            }

            .form-actions {

                flex-direction: column;
            }

            .btn {

                width: 100%;
            }

        }

    </style>

</head>

<body>

<div class="page">

    <div class="container">

        <div class="card">

            <!-- =================================================
                 CLOSE BUTTON
            ================================================== -->

            <a
                href="manage_users.php"
                class="close-button"
                title="Close"
                aria-label="Close"
            >
                ×
            </a>

            <!-- =================================================
                 HEADER
            ================================================== -->

            <div class="header">

                <h1>
                    Edit User
                </h1>

                <p>
                    Update the information of this
                    NoteSphere account.
                </p>

            </div>

            <!-- =================================================
                 ACCOUNT INFO
            ================================================== -->

            <div class="account-info">

                Editing account:
                <strong>
                    <?= e($user['full_name']) ?>
                </strong>

                <br>

                User ID:
                <strong>
                    #<?= e($user['user_id']) ?>
                </strong>

            </div>

            <!-- =================================================
                 FORM
            ================================================== -->

            <form
                method="POST"
                action="edit_user.php?user_id=<?= $user_id ?>"
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($csrf_token) ?>"
                >

                <div class="form-grid">

                    <!-- FULL NAME -->

                    <div class="form-group">

                        <label for="full_name">
                            Full Name
                        </label>

                        <input
                            type="text"
                            id="full_name"
                            name="full_name"
                            value="<?= e(
                                $user['full_name']
                            ) ?>"
                            required
                        >

                    </div>

                    <!-- EMAIL -->

                    <div class="form-group">

                        <label for="email">
                            Email Address
                        </label>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="<?= e(
                                $user['email']
                            ) ?>"
                            required
                        >

                    </div>

                    <!-- COURSE -->

                    <div class="form-group">

                        <label for="course">
                            Course
                        </label>

                        <input
                            type="text"
                            id="course"
                            name="course"
                            value="<?= e(
                                $user['course'] ?? ''
                            ) ?>"
                            placeholder="Example: BSIT"
                        >

                    </div>

                    <!-- STUDENT STATUS -->

                    <div class="form-group">

                        <label for="student_status">
                            Student Status
                        </label>

                        <select
                            id="student_status"
                            name="student_status"
                        >

                            <option
                                value=""
                                <?= (
                                    $user['student_status']
                                    ?? ''
                                ) === ''
                                    ? 'selected'
                                    : '' ?>
                            >
                                Select Status
                            </option>

                            <option
                                value="Regular"
                                <?= (
                                    $user['student_status']
                                    ?? ''
                                ) === 'Regular'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Regular
                            </option>

                            <option
                                value="Irregular"
                                <?= (
                                    $user['student_status']
                                    ?? ''
                                ) === 'Irregular'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Irregular
                            </option>

                        </select>

                    </div>

                    <!-- YEAR LEVEL -->

                    <div class="form-group">

                        <label for="year_level">
                            Year Level
                        </label>

                        <select
                            id="year_level"
                            name="year_level"
                        >

                            <option
                                value=""
                                <?= (
                                    $user['year_level']
                                    ?? ''
                                ) === ''
                                    ? 'selected'
                                    : '' ?>
                            >
                                Select Year Level
                            </option>

                            <option
                                value="1st Year"
                                <?= (
                                    $user['year_level']
                                    ?? ''
                                ) === '1st Year'
                                    ? 'selected'
                                    : '' ?>
                            >
                                1st Year
                            </option>

                            <option
                                value="2nd Year"
                                <?= (
                                    $user['year_level']
                                    ?? ''
                                ) === '2nd Year'
                                    ? 'selected'
                                    : '' ?>
                            >
                                2nd Year
                            </option>

                            <option
                                value="3rd Year"
                                <?= (
                                    $user['year_level']
                                    ?? ''
                                ) === '3rd Year'
                                    ? 'selected'
                                    : '' ?>
                            >
                                3rd Year
                            </option>

                            <option
                                value="4th Year"
                                <?= (
                                    $user['year_level']
                                    ?? ''
                                ) === '4th Year'
                                    ? 'selected'
                                    : '' ?>
                            >
                                4th Year
                            </option>

                        </select>

                    </div>

                    <!-- SECTION -->

                    <div class="form-group">

                        <label for="section">
                            Section
                        </label>

                        <input
                            type="text"
                            id="section"
                            name="section"
                            value="<?= e(
                                $user['section'] ?? ''
                            ) ?>"
                            placeholder="Example: BSIT 3D"
                        >

                    </div>

                    <!-- ENROLLED SUBJECTS -->

                    <div class="form-group full">

                        <label for="enrolled_subjects">
                            Enrolled Subjects
                        </label>

                        <textarea
                            id="enrolled_subjects"
                            name="enrolled_subjects"
                            placeholder="Example: Web Development, Database Management, Systems Integration"
                        ><?= e(
                            $user['enrolled_subjects'] ?? ''
                        ) ?></textarea>

                    </div>

                    <!-- ROLE -->

                    <div class="form-group">

                        <label for="role">
                            Account Role
                        </label>

                        <select
                            id="role"
                            name="role"
                            required
                        >

                            <option
                                value="student"
                                <?= $user['role'] === 'student'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Student
                            </option>

                            <option
                                value="professor"
                                <?= $user['role'] === 'professor'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Professor
                            </option>

                            <option
                                value="admin"
                                <?= $user['role'] === 'admin'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Administrator
                            </option>

                        </select>

                    </div>

                </div>

                <!-- =================================================
                     ACTION BUTTONS
                ================================================== -->

                <div class="form-actions">

                    <a
                        href="manage_users.php"
                        class="btn btn-cancel"
                    >
                        Cancel
                    </a>

                    <button
                        type="submit"
                        class="btn btn-save"
                    >
                        Save Changes
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

<!-- =========================================================
     AUTOMATIC THEME ADOPTION
========================================================= -->

<script>

(function () {

    const savedTheme =
        localStorage.getItem(
            'notesphere_admin_theme'
        );

    if (
        savedTheme === 'dark' ||
        savedTheme === 'dark-mode'
    ) {

        document.body.classList.add(
            'dark-mode'
        );

    } else {

        document.body.classList.remove(
            'dark-mode'
        );

    }

})();

</script>

<!-- =========================================================
     SWEETALERT MESSAGE
========================================================= -->

<?php

if (
    isset($_SESSION['admin_message']) &&
    isset($_SESSION['admin_message_type'])
):

    $message =
        $_SESSION['admin_message'];

    $message_type =
        $_SESSION['admin_message_type'];

    unset($_SESSION['admin_message']);
    unset($_SESSION['admin_message_type']);

?>

<script>

Swal.fire({

    icon:
        <?= json_encode(
            $message_type
        ) ?>,

    title:
        <?= json_encode(
            $message_type === 'success'
                ? 'Success'
                : 'Error'
        ) ?>,

    text:
        <?= json_encode(
            $message
        ) ?>,

    confirmButtonColor:
        '#3157a4'

});

</script>

<?php endif; ?>

</body>

</html>