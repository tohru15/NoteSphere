
<?php

session_start();

require_once '../includes/db_config.php';

/** @var PDO $conn */

// =========================================================
// CHECK TEMPORARY REGISTRATION SESSION
// =========================================================

if (
    !isset($_SESSION['verify_user']) ||
    !isset($_SESSION['temp_user'])
) {
    header("Location: register.php");
    exit();
}

$error = "";


// =========================================================
// VERIFY OTP
// =========================================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $entered_otp = trim($_POST['otp_code'] ?? '');

    $session_otp = trim(
        $_SESSION['temp_user']['otp_code'] ?? ''
    );


    // =====================================================
    // VALIDATE OTP FORMAT
    // =====================================================

    if (!preg_match('/^[0-9]{6}$/', $entered_otp)) {

        $error = "Please enter the 6-digit OTP code.";

    } elseif (
        $session_otp === '' ||
        !hash_equals($session_otp, $entered_otp)
    ) {

        $error = "Invalid OTP code. Please check your email again.";

    } else {

        $user_data = $_SESSION['temp_user'];


        // =================================================
        // GET USER DATA
        // =================================================

        $full_name = trim($user_data['full_name'] ?? '');
        $email = trim($user_data['email'] ?? '');
        $course = trim($user_data['course'] ?? '');

        $student_status = trim(
            $user_data['student_status']
            ?? $user_data['student_type']
            ?? ''
        );

        $year_level = (int) ($user_data['year_level'] ?? 0);
        $section = trim($user_data['section'] ?? '');

        $enrolled_subjects = trim(
            $user_data['enrolled_subjects'] ?? ''
        );

        $password = $user_data['password'] ?? '';

        // All registrations from this page are students.
        $role = 'student';


        // =================================================
        // VALIDATE REQUIRED SESSION DATA
        // =================================================

        if (
            $full_name === '' ||
            !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            $course === '' ||
            $student_status === '' ||
            $year_level < 1 ||
            $year_level > 4 ||
            $password === ''
        ) {

            $error =
                "Registration information is incomplete. Please register again.";

        } else {

            // =============================================
            // CHECK LIMIT AND CREATE ACCOUNT
            // =============================================

            try {

                /*
                 * Use a transaction and lock the limit record
                 * while checking the count and inserting.
                 *
                 * This helps prevent simultaneous registrations
                 * from exceeding the configured limit, provided
                 * the relevant tables use InnoDB.
                 */

                $conn->beginTransaction();


                // =========================================
                // READ ADMIN-CONFIGURED USER LIMIT
                // =========================================

                $limitStmt = $conn->query("
                    SELECT max_users
                    FROM system_limits
                    ORDER BY id ASC
                    LIMIT 1
                    FOR UPDATE
                ");

                $limitRow = $limitStmt->fetch(PDO::FETCH_ASSOC);


                // =========================================
                // REQUIRE A CONFIGURED LIMIT
                // =========================================

                if (
                    !$limitRow ||
                    !isset($limitRow['max_users']) ||
                    !is_numeric($limitRow['max_users'])
                ) {

                    $conn->rollBack();

                    $error =
                        "Registration is temporarily unavailable. Please contact the administrator.";

                } else {

                    $maxUsers = (int) $limitRow['max_users'];


                    // =====================================
                    // COUNT EXISTING ACCOUNTS
                    // =====================================

                    $countStmt = $conn->query("
                        SELECT COUNT(*)
                        FROM users
                    ");

                    $registeredUsers = (int) $countStmt->fetchColumn();


                    // =====================================
                    // STOP IF LIMIT HAS BEEN REACHED
                    // =====================================

                    if (
                        $maxUsers <= 0 ||
                        $registeredUsers >= $maxUsers
                    ) {

                        $conn->rollBack();

                        $error =
                            "Registration is currently closed because the maximum number of user accounts has been reached. Please contact the administrator.";

                    } else {

                        // =================================
                        // CHECK WHETHER EMAIL ALREADY EXISTS
                        // =================================

                        $emailStmt = $conn->prepare("
                            SELECT user_id
                            FROM users
                            WHERE email = ?
                            LIMIT 1
                        ");

                        $emailStmt->execute([$email]);

                        if ($emailStmt->fetch()) {

                            $conn->rollBack();

                            $error =
                                "This email is already registered.";

                        } else {

                            // =============================
                            // INSERT NEW STUDENT ACCOUNT
                            // =============================

                            $stmt = $conn->prepare("
                                INSERT INTO users (
                                    full_name,
                                    email,
                                    course,
                                    student_status,
                                    year_level,
                                    section,
                                    enrolled_subjects,
                                    password,
                                    role
                                )
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                            ");

                            $stmt->execute([
                                $full_name,
                                $email,
                                $course,
                                $student_status,
                                $year_level,
                                $section !== '' ? $section : null,
                                $enrolled_subjects,
                                $password,
                                $role
                            ]);


                            // =============================
                            // COMMIT ACCOUNT CREATION
                            // =============================

                            $conn->commit();


                            // =============================
                            // CLEAR TEMPORARY SESSION
                            // =============================

                            unset(
                                $_SESSION['verify_user'],
                                $_SESSION['temp_user'],
                                $_SESSION['verify_email']
                            );


                            // =============================
                            // SUCCESS MESSAGE
                            // =============================

                            echo "
                            <!DOCTYPE html>
                            <html lang='en'>
                            <head>
                                <meta charset='UTF-8'>
                                <meta
                                    name='viewport'
                                    content='width=device-width, initial-scale=1.0'
                                >
                                <title>Email Verified | NoteSphere</title>
                                <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
                            </head>

                            <body style='background:#000;'>

                                <script>
                                    Swal.fire({
                                        title: 'Email Verified!',
                                        text: 'Your account has been created successfully. You can now log in.',
                                        icon: 'success',
                                        background: '#0a0a0a',
                                        color: '#fff',
                                        confirmButtonColor: '#39FF14',
                                        confirmButtonText: 'Go to Login'
                                    }).then(() => {
                                        window.location.href = 'login.php';
                                    });
                                </script>

                            </body>
                            </html>
                            ";

                            exit();
                        }
                    }
                }

            } catch (PDOException $e) {

                // =========================================
                // ROLLBACK IF TRANSACTION IS STILL ACTIVE
                // =========================================

                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }

                // =========================================
                // HANDLE DUPLICATE EMAIL
                // =========================================

                $sqlState = $e->errorInfo[0] ?? (string) $e->getCode();

                if ($sqlState === '23000') {

                    $error =
                        "This email is already registered.";

                } else {

                    // Keep database details out of the public page.
                    error_log(
                        'NoteSphere OTP registration error: '
                        . $e->getMessage()
                    );

                    $error =
                        "Something went wrong while creating your account. Please try again.";
                }

            } catch (Throwable $e) {

                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }

                error_log(
                    'NoteSphere OTP system error: '
                    . $e->getMessage()
                );

                $error =
                    "A system error occurred. Please try again.";
            }
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
        content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"
    >

    <title>Email Verification | NoteSphere</title>

    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap"
        rel="stylesheet"
    >

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html,
        body {
            width: 100%;
            height: 100%;
            overflow: hidden;
            position: fixed;
            -webkit-overflow-scrolling: touch;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: url('../assets/login1.jpg') no-repeat center center;
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            color: #fff;

            display: flex;
            justify-content: center;
            align-items: center;

            height: 100vh;
            height: 100dvh;

            padding: 20px;

            touch-action: manipulation;
        }

        .card {
            width: 100%;
            max-width: 420px;

            padding: 40px 30px;

            border: 1px solid #1a1a1a;
            border-radius: 24px;

            background-color: #222;

            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.8);

            text-align: center;
        }

        h2 {
            font-size: 1.75rem;
            font-weight: 800;

            margin-bottom: 8px;

            letter-spacing: -0.5px;

            color: #32e010;
        }

        .subtitle {
            font-size: 0.85rem;

            color: #999;

            line-height: 1.5;

            margin-bottom: 30px;
        }

        form {
            display: flex;
            flex-direction: column;

            width: 100%;
        }

        .input-group {
            position: relative;

            margin-bottom: 20px;

            width: 100%;
        }

        input {
            width: 100%;

            padding: 14px 16px;

            border-radius: 12px;

            border: 1px solid #222;

            background: #111;

            color: #fff;

            font-size: 1.5rem;

            text-align: center;

            letter-spacing: 8px;

            outline: none;

            transition: 0.3s;
        }

        input:focus {
            border-color: #39FF14;

            background: #141414;

            box-shadow:
                0 0 0 4px rgba(57, 255, 20, 0.08);
        }

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

            letter-spacing: 0.5px;

            -webkit-tap-highlight-color: transparent;
        }

        button[type="submit"]:hover {
            background: #32e010;

            transform: translateY(-2px);

            box-shadow:
                0 5px 20px rgba(57, 255, 20, 0.3);
        }

        .alert {
            padding: 12px;

            border-radius: 10px;

            margin-bottom: 20px;

            font-size: 0.85rem;

            background: rgba(255, 0, 0, 0.1);

            border: 1px solid rgba(255, 0, 0, 0.2);

            color: #ff6b6b;

            text-align: center;

            width: 100%;
        }

        @media (max-width: 480px) {

            .card {
                padding: 30px 20px;
            }

        }

    </style>

</head>

<body>

    <div class="card">

        <h2>Enter OTP Code</h2>

        <div class="subtitle">
            We have sent a 6-digit verification code to your registered email address.
        </div>

        <?php if (!empty($error)): ?>

            <div class="alert">
                <?= htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </div>

        <?php endif; ?>


        <form method="POST">

            <div class="input-group">

                <input
                    type="text"
                    name="otp_code"
                    maxlength="6"
                    minlength="6"
                    pattern="[0-9]{6}"
                    inputmode="numeric"
                    placeholder="------"
                    required
                    autofocus
                    autocomplete="off"
                >

            </div>

            <button type="submit">
                Verify Email
            </button>

        </form>

    </div>

</body>

</html>
