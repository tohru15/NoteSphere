```php
<?php

// =========================================================
// NoteSphere - Forgot Password
// =========================================================

session_start();

require_once '../includes/db_config.php';

/** @var PDO $conn */

// =========================================================
// PHPMailer
// =========================================================

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// =========================================================
// PHPMailer FILES
// =========================================================

require '../includes/PHPMailer-master/PHPMailer-master/src/Exception.php';
require '../includes/PHPMailer-master/PHPMailer-master/src/PHPMailer.php';
require '../includes/PHPMailer-master/PHPMailer-master/src/SMTP.php';

// =========================================================
// ERROR VARIABLE
// =========================================================

$error = '';

// =========================================================
// FORM SUBMISSION
// =========================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');

    // =====================================================
    // VALIDATE EMAIL
    // =====================================================

    if ($email === '') {

        $error = 'Please enter your email.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = 'Please enter a valid email address.';

    } else {

        try {

            // =================================================
            // CHECK IF EMAIL EXISTS
            // =================================================

            $stmt = $conn->prepare("
                SELECT user_id, email
                FROM users
                WHERE email = ?
                LIMIT 1
            ");

            $stmt->execute([$email]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // =================================================
            // EMAIL NOT FOUND
            // =================================================

            if (!$user) {

                $error = 'This email is not registered.';

            } else {

                // =================================================
                // GENERATE OTP
                // =================================================

                $otp = random_int(100000, 999999);

                // =================================================
                // STORE RESET DATA
                // =================================================

                $_SESSION['reset_email'] = $email;
                $_SESSION['reset_otp'] = (string) $otp;
                $_SESSION['reset_otp_created'] = time();

                // =================================================
                // CREATE PHPMailer
                // =================================================

                $mail = new PHPMailer(true);

                try {

                    // =================================================
                    // SMTP SETTINGS
                    // =================================================

                    $mail->isSMTP();

                    $mail->Host = 'smtp.gmail.com';

                    $mail->SMTPAuth = true;

                    // YOUR NOTE SPHERE GMAIL
                    $mail->Username = 'notesphere.system@gmail.com';

                    // =================================================
                    // IMPORTANT:
                    // PUT YOUR NEW GOOGLE APP PASSWORD HERE.
                    //
                    // DO NOT USE YOUR NORMAL GMAIL PASSWORD.
                    // =================================================

                    $mail->Password = 'jcgd qdtp dqab frvi';

                    // Gmail TLS
                    $mail->SMTPSecure =
                        PHPMailer::ENCRYPTION_STARTTLS;

                    $mail->Port = 587;

                    // =================================================
                    // SENDER
                    // =================================================

                    $mail->setFrom(
                        'notesphere.system@gmail.com',
                        'NoteSphere System'
                    );

                    // =================================================
                    // RECIPIENT
                    // =================================================

                    $mail->addAddress($email);

                    // =================================================
                    // EMAIL SETTINGS
                    // =================================================

                    $mail->isHTML(true);

                    $mail->CharSet = 'UTF-8';

                    $mail->Subject =
                        'NoteSphere - Password Reset OTP';

                    // =================================================
                    // HTML EMAIL
                    // =================================================

                    $mail->Body = "
                        <div style='
                            font-family: Arial, sans-serif;
                            max-width: 600px;
                            margin: 0 auto;
                            padding: 30px;
                            background: #0a0a0a;
                            color: #ffffff;
                            border-radius: 15px;
                        '>

                            <h2 style='
                                color: #ffffff;
                                margin-bottom: 20px;
                            '>
                                NoteSphere Password Reset
                            </h2>

                            <p style='
                                color: #cccccc;
                                line-height: 1.6;
                            '>
                                We received a request to reset
                                your NoteSphere password.
                            </p>

                            <p style='
                                color: #cccccc;
                                line-height: 1.6;
                            '>
                                Your verification code is:
                            </p>

                            <div style='
                                margin: 25px 0;
                                padding: 20px;
                                text-align: center;
                                background: #111111;
                                border-radius: 12px;
                                border: 1px solid #222222;
                            '>

                                <span style='
                                    font-size: 36px;
                                    font-weight: bold;
                                    letter-spacing: 8px;
                                    color: #39FF14;
                                '>
                                    {$otp}
                                </span>

                            </div>

                            <p style='
                                color: #cccccc;
                                line-height: 1.6;
                            '>
                                Enter this code on the password
                                reset page to continue.
                            </p>

                            <p style='
                                color: #888888;
                                font-size: 13px;
                                line-height: 1.6;
                            '>
                                If you did not request a password
                                reset, you can safely ignore this email.
                            </p>

                            <hr style='
                                border: none;
                                border-top: 1px solid #222222;
                                margin: 25px 0;
                            '>

                            <p style='
                                color: #666666;
                                font-size: 12px;
                                text-align: center;
                            '>
                                NoteSphere System
                            </p>

                        </div>
                    ";

                    // =================================================
                    // PLAIN TEXT EMAIL
                    // =================================================

                    $mail->AltBody =
                        "Your NoteSphere password reset OTP is: {$otp}";

                    // =================================================
                    // SEND EMAIL
                    // =================================================

                    $mail->send();

                    // =================================================
                    // SUCCESS
                    // =================================================

                    header('Location: reset_password.php');
                    exit();

                } catch (Exception $e) {

                    // Remove reset session if email failed
                    unset($_SESSION['reset_email']);
                    unset($_SESSION['reset_otp']);
                    unset($_SESSION['reset_otp_created']);

                    // Show actual PHPMailer error
                    $error =
                        'We could not send the verification code. '
                        . 'Mailer Error: '
                        . $mail->ErrorInfo;
                }
            }

        } catch (PDOException $e) {

            $error =
                'Something went wrong with the database. '
                . $e->getMessage();
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

    <title>Forgot Password | NoteSphere</title>

    <!-- =====================================================
         GOOGLE FONT
    ====================================================== -->

    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap"
        rel="stylesheet"
    >

    <!-- =====================================================
         SWEETALERT2
    ====================================================== -->

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

            font-family:
                'Plus Jakarta Sans',
                sans-serif;

            background:
                url('../assets/login1.jpg')
                no-repeat
                center center;

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

            border:
                1px solid
                rgba(255, 255, 255, 0.15);

            border-radius: 50%;

            display: flex;

            align-items: center;

            justify-content: center;

            background:
                rgba(0, 0, 0, 0.4);

            backdrop-filter: blur(5px);

            transition: 0.3s;
        }

        .btn-back-fixed:hover .back-circle {

            border-color: #39FF14;

            color: #39FF14;

            transform: translateX(-3px);
        }

        /* =====================================================
           CARD
        ===================================================== */

        .card {

            width: 100%;

            max-width: 440px;

            padding: 40px 30px;

            border:
                1px solid
                #1a1a1a;

            border-radius: 24px;

            background: #0a0a0a;

            box-shadow:
                0 20px 40px
                rgba(0, 0, 0, 0.8);
        }

        /* =====================================================
           TITLE
        ===================================================== */

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

            line-height: 1.5;
        }

        /* =====================================================
           FORM
        ===================================================== */

        form {

            display: flex;

            flex-direction: column;
        }

        .input-group {

            margin-bottom: 15px;
        }

        input {

            width: 100%;

            padding: 14px 16px;

            border-radius: 12px;

            border:
                1px solid
                #222;

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
                0 0 0 4px
                rgba(57, 255, 20, 0.08);
        }

        input::placeholder {

            color: #666;
        }

        /* =====================================================
           SUBMIT BUTTON
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
                0 5px 20px
                rgba(57, 255, 20, 0.3);
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
        href="login.php"
        class="btn-back-fixed"
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
         CARD
    ===================================================== -->

    <div class="card">

        <h2>
            Forgot Password
        </h2>

        <p class="desc">
            Enter your registered email address
            to receive an OTP.
        </p>

        <form
            method="POST"
            id="forgotForm"
            novalidate
        >

            <div class="input-group">

                <input
                    type="email"
                    name="email"
                    id="email"
                    placeholder="Enter your email address"
                    autocomplete="email"
                    value="<?= htmlspecialchars(
                        $_POST['email'] ?? '',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >

            </div>

            <button type="submit">
                Send OTP
            </button>

        </form>

    </div>

    <!-- =====================================================
         JAVASCRIPT
    ===================================================== -->

    <script>

        const form =
            document.getElementById('forgotForm');

        const emailInput =
            document.getElementById('email');

        form.addEventListener(
            'submit',
            function(event) {

                event.preventDefault();

                const email =
                    emailInput.value.trim();

                // =================================================
                // EMPTY EMAIL
                // =================================================

                if (email === '') {

                    Swal.fire({

                        icon: 'warning',

                        title: 'Email Required',

                        text: 'Please enter your email.',

                        confirmButtonColor: '#39FF14',

                        background: '#0a0a0a',

                        color: '#fff'

                    });

                    emailInput.focus();

                    return;
                }

                // =================================================
                // EMAIL VALIDATION
                // =================================================

                if (
                    !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)
                ) {

                    Swal.fire({

                        icon: 'warning',

                        title: 'Invalid Email',

                        text: 'Please enter a valid email.',

                        confirmButtonColor: '#39FF14',

                        background: '#0a0a0a',

                        color: '#fff'

                    });

                    emailInput.focus();

                    return;
                }

                // =================================================
                // CONFIRM SEND OTP
                // =================================================

                Swal.fire({

                    title: 'Send OTP?',

                    html:
                        'Are you sure you want to send an OTP to:<br><br>' +
                        '<b style="color:#39FF14;">' +
                        email
                            .replace(/</g, '&lt;')
                            .replace(/>/g, '&gt;') +
                        '</b>',

                    icon: 'question',

                    showCancelButton: true,

                    confirmButtonColor: '#39FF14',

                    cancelButtonColor: '#d33',

                    confirmButtonText: 'Send OTP',

                    cancelButtonText: 'Cancel',

                    background: '#0a0a0a',

                    color: '#fff'

                }).then(function(result) {

                    if (result.isConfirmed) {

                        form.submit();

                    }

                });

            }
        );

    </script>

    <!-- =====================================================
         SERVER ERROR
    ===================================================== -->

    <?php if (!empty($error)): ?>

        <script>

            Swal.fire({

                icon: 'error',

                title: 'Forgot Password',

                text: <?= json_encode($error) ?>,

                confirmButtonColor: '#39FF14',

                background: '#0a0a0a',

                color: '#fff',

                width: '450px'

            });

        </script>

    <?php endif; ?>

</body>

</html>
```
