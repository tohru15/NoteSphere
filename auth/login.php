
<?php

session_start();

require_once '../includes/db_config.php';

/** @var PDO $conn */

$error = '';
$errorType = 'Login Failed';

// =========================================================
// DEVICE DETECTION
// Note: User-Agent detection is not foolproof.
// =========================================================

$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

$isMobileDevice = (bool) preg_match(
    '/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini|Mobile|Tablet/i',
    $userAgent
);

// =========================================================
// PROCESS LOGIN
// =========================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // =========================================================
    // GET LOGIN INPUT
    // =========================================================

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // =========================================================
    // VALIDATION
    // =========================================================

    if ($email === '') {

        $error = 'Please enter your email.';
        $errorType = 'Email Required';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = 'Please enter a valid email address.';
        $errorType = 'Invalid Email';

    } elseif ($password === '') {

        $error = 'Please enter your password.';
        $errorType = 'Password Required';

    } else {

        try {

            // =====================================================
            // GET USER ACCOUNT
            // =====================================================

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
                    password,
                    role,
                    warning_count,
                    account_status
                FROM users
                WHERE email = ?
                LIMIT 1
            ");

            $stmt->execute([$email]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // =====================================================
            // ACCOUNT DOES NOT EXIST / WRONG PASSWORD
            // =====================================================

            if (
                !$user ||
                !password_verify($password, $user['password'])
            ) {

                $error = 'Incorrect email or password.';
                $errorType = 'Login Failed';

            }

            // =====================================================
            // CHECK BANNED ACCOUNT
            // =====================================================

            elseif (
                isset($user['account_status']) &&
                strtolower((string) $user['account_status']) === 'banned'
            ) {

                $error = 'Your account has been banned. Please contact the administrator.';
                $errorType = 'Account Banned';

            }

            // =====================================================
            // CHECK USER ROLE
            // =====================================================

            elseif (
                !in_array(
                    $user['role'],
                    ['student', 'professor', 'admin'],
                    true
                )
            ) {

                $error = 'Your account role is not valid.';
                $errorType = 'Invalid Account Role';

            }

            // =====================================================
            // BLOCK ADMIN LOGIN ON MOBILE / TABLET
            // =====================================================

            elseif (
                $user['role'] === 'admin' &&
                $isMobileDevice
            ) {

                $error = 'Admin login is only available on a desktop or laptop. Please use a computer to access the Admin Dashboard.';
                $errorType = 'Desktop Access Required';

            }

            // =====================================================
            // LOGIN SUCCESS
            // =====================================================

            else {

                // Regenerate session ID after successful authentication.
                session_regenerate_id(true);

                // Store user session.
                $_SESSION['user_id'] = (int) $user['user_id'];

                $_SESSION['role'] = $user['role'];

                $_SESSION['full_name'] = $user['full_name'];

                $_SESSION['email'] = $user['email'];

                $_SESSION['course'] = $user['course'] ?? '';

                $_SESSION['student_status'] =
                    $user['student_status'] ?? '';

                $_SESSION['year_level'] =
                    $user['year_level'] ?? '';

                $_SESSION['section'] =
                    $user['section'] ?? '';

                $_SESSION['enrolled_subjects'] =
                    $user['enrolled_subjects'] ?? '';

                $_SESSION['warning_count'] =
                    (int) ($user['warning_count'] ?? 0);

                $_SESSION['account_status'] =
                    $user['account_status'] ?? 'active';

                // Record the detected device type for optional
                // checks on protected admin pages.
                $_SESSION['login_device'] =
                    $isMobileDevice ? 'mobile' : 'desktop';

                // =================================================
                // REDIRECT BASED ON ROLE
                // =================================================

                if ($user['role'] === 'student') {

                    header('Location: ../dashboard/student_home.php');
                    exit();

                } elseif ($user['role'] === 'professor') {

                    header('Location: ../professor/professor_dashboard.php');
                    exit();

                } elseif ($user['role'] === 'admin') {

                    header('Location: ../admin/admin_dashboard.php');
                    exit();

                }

            }

        } catch (PDOException $e) {

            // Keep technical database details out of the user interface.
            error_log('NoteSphere login database error: ' . $e->getMessage());

            $error = 'Something went wrong with the database. Please try again.';
            $errorType = 'System Error';

        }

    }

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

    <title>Login | NoteSphere</title>

    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap"
        rel="stylesheet"
    >

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html,
        body {
            width: 100%;
            min-height: 100%;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: url('../assets/login1.jpg') no-repeat center center;
            background-size: cover;
            background-attachment: fixed;
            color: #fff;

            display: flex;
            justify-content: center;
            align-items: center;

            min-height: 100vh;
            padding: 20px;
        }

        /* =====================================================
           BACK BUTTON
        ===================================================== */

        .btn-back-fixed {
            position: fixed;
            top: 25px;
            left: 25px;

            display: flex;
            align-items: center;

            text-decoration: none;
            color: #888;

            z-index: 100;
        }

        .back-circle {
            width: 42px;
            height: 42px;

            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 50%;

            display: flex;
            align-items: center;
            justify-content: center;

            background: rgba(0,0,0,0.4);

            backdrop-filter: blur(5px);

            transition: 0.3s;
        }

        .btn-back-fixed:hover .back-circle {
            border-color: #39FF14;
            color: #39FF14;
            transform: translateX(-3px);
        }

        /* =====================================================
           LOGIN CARD
        ===================================================== */

        .card {
            width: 100%;
            max-width: 440px;

            padding: 40px 30px;

            border: 1px solid #1a1a1a;
            border-radius: 24px;

            background: #0a0a0a;

            box-shadow: 0 20px 40px rgba(0,0,0,0.8);
        }

        h2 {
            font-size: 1.75rem;
            font-weight: 800;

            margin-bottom: 8px;

            text-align: center;
        }

        .desc {
            font-size: 0.85rem;
            color: #666;

            text-align: center;

            margin-bottom: 30px;
        }

        /* =====================================================
           FORM
        ===================================================== */

        form {
            display: flex;
            flex-direction: column;

            width: 100%;
        }

        .input-group {
            margin-bottom: 15px;
        }

        input {
            width: 100%;

            padding: 14px 16px;

            border-radius: 12px;

            border: 1px solid #222;

            background: #111;
            color: #fff;

            font-size: 0.95rem;

            outline: none;

            transition: 0.3s;
        }

        input:focus {
            border-color: #39FF14;

            background: #141414;

            box-shadow:
                0 0 0 4px rgba(57,255,20,0.08);
        }

        /* =====================================================
           PASSWORD
        ===================================================== */

        .password-box {
            position: relative;
        }

        .password-box input {
            padding-right: 70px;
        }

        .show-password {
            position: absolute;

            right: 12px;
            top: 50%;

            transform: translateY(-50%);

            background: transparent;
            border: none;

            color: #888;

            cursor: pointer;

            font-size: 0.75rem;
        }

        .show-password:hover {
            color: #39FF14;
        }

        /* =====================================================
           FORGOT PASSWORD
        ===================================================== */

        .forgot {
            text-align: right;

            margin-top: -5px;
            margin-bottom: 20px;
        }

        .forgot a {
            color: #39FF14;

            text-decoration: none;

            font-size: 0.8rem;
        }

        .forgot a:hover {
            text-decoration: underline;
        }

        /* =====================================================
           LOGIN BUTTON
        ===================================================== */

        button[type="submit"] {
            width: 100%;

            padding: 14px;

            border-radius: 12px;

            border: none;

            background: #39FF14;
            color: #000;

            font-weight: 800;

            font-size: 0.95rem;

            cursor: pointer;

            transition: 0.3s;
        }

        button[type="submit"]:hover {
            background: #32e010;

            transform: translateY(-2px);

            box-shadow:
                0 5px 20px rgba(57,255,20,0.3);
        }

        /* =====================================================
           REGISTER
        ===================================================== */

        .register-text {
            text-align: center;

            margin-top: 25px;

            color: #666;

            font-size: 0.85rem;
        }

        .register-text a {
            color: #39FF14;

            text-decoration: none;

            font-weight: 600;
        }

        .register-text a:hover {
            text-decoration: underline;
        }

        /* =====================================================
           MOBILE
        ===================================================== */

        @media (max-width: 480px) {

            .btn-back-fixed {
                top: 15px;
                left: 15px;
            }

            .card {
                padding: 30px 20px;
            }
        }

    </style>

</head>

<body>

    <!-- =====================================================
         BACK BUTTON
    ===================================================== -->

    <a
        href="../index.php"
        class="btn-back-fixed"
        aria-label="Back to homepage"
    >

        <div class="back-circle">

            <svg
                width="18"
                height="18"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
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

        </div>

    </a>

    <!-- =====================================================
         LOGIN CARD
    ===================================================== -->

    <div class="card">

        <h2>Welcome Back</h2>

        <p class="desc">
            Login to your NoteSphere account.
        </p>

        <form
            method="POST"
            id="loginForm"
            novalidate
        >

            <!-- EMAIL -->

            <div class="input-group">

                <input
                    type="email"
                    name="email"
                    id="email"
                    placeholder="Enter your email"
                    autocomplete="email"
                    aria-label="Email address"
                >

            </div>

            <!-- PASSWORD -->

            <div class="input-group password-box">

                <input
                    type="password"
                    name="password"
                    id="password"
                    placeholder="Enter your password"
                    autocomplete="current-password"
                    aria-label="Password"
                >

                <button
                    type="button"
                    class="show-password"
                    id="showPassword"
                    aria-label="Show password"
                >
                    Show
                </button>

            </div>

            <!-- FORGOT PASSWORD -->

            <div class="forgot">

                <a href="forgot_password.php">
                    Forgot Password?
                </a>

            </div>

            <!-- LOGIN -->

            <button type="submit">
                Login
            </button>

        </form>

        <!-- REGISTER -->

        <p class="register-text">

            Don't have an account?

            <a href="register.php">
                Register
            </a>

        </p>

    </div>

    <script>

        // =====================================================
        // ELEMENTS
        // =====================================================

        const form = document.getElementById('loginForm');
        const email = document.getElementById('email');
        const password = document.getElementById('password');
        const showPassword = document.getElementById('showPassword');

        // =====================================================
        // SHOW / HIDE PASSWORD
        // =====================================================

        showPassword.addEventListener('click', function () {

            if (password.type === 'password') {

                password.type = 'text';
                showPassword.textContent = 'Hide';
                showPassword.setAttribute('aria-label', 'Hide password');

            } else {

                password.type = 'password';
                showPassword.textContent = 'Show';
                showPassword.setAttribute('aria-label', 'Show password');

            }

        });

        // =====================================================
        // FORM VALIDATION
        // =====================================================

        form.addEventListener('submit', function (event) {

            event.preventDefault();

            const emailValue = email.value.trim();
            const passwordValue = password.value;

            // EMAIL REQUIRED

            if (emailValue === '') {

                Swal.fire({
                    icon: 'warning',
                    title: 'Email Required',
                    text: 'Please enter your email.',
                    confirmButtonColor: '#39FF14',
                    background: '#0a0a0a',
                    color: '#fff'
                });

                email.focus();
                return;
            }

            // EMAIL FORMAT

            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailValue)) {

                Swal.fire({
                    icon: 'warning',
                    title: 'Invalid Email',
                    text: 'Please enter a valid email address.',
                    confirmButtonColor: '#39FF14',
                    background: '#0a0a0a',
                    color: '#fff'
                });

                email.focus();
                return;
            }

            // PASSWORD REQUIRED

            if (passwordValue === '') {

                Swal.fire({
                    icon: 'warning',
                    title: 'Password Required',
                    text: 'Please enter your password.',
                    confirmButtonColor: '#39FF14',
                    background: '#0a0a0a',
                    color: '#fff'
                });

                password.focus();
                return;
            }

            // SUBMIT TO PHP

            form.submit();

        });

    </script>

    <?php if (!empty($error)): ?>

        <script>

            Swal.fire({

                icon: 'error',

                title: <?= json_encode($errorType) ?>,

                text: <?= json_encode($error) ?>,

                confirmButtonColor: '#39FF14',

                background: '#0a0a0a',

                color: '#fff'

            });

        </script>

    <?php endif; ?>

</body>

</html>
