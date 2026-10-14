
<?php

session_start();

require_once '../includes/db_config.php';

/** @var PDO $conn */

if (!isset($_SESSION['reset_email'], $_SESSION['reset_otp'])) {
    header('Location: forgot_password.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $entered_otp = trim($_POST['otp'] ?? '');
    $new_password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($entered_otp === '') {

        $error = 'Please enter the OTP.';

    } elseif (!preg_match('/^[0-9]{6}$/', $entered_otp)) {

        $error = 'OTP must be 6 digits.';

    } elseif ($new_password === '') {

        $error = 'Please create a new password.';

    } elseif (strlen($new_password) < 8) {

        $error = 'Password must be at least 8 characters.';

    } elseif ($confirm_password === '') {

        $error = 'Please confirm your password.';

    } elseif ($new_password !== $confirm_password) {

        $error = 'Passwords do not match.';

    } elseif ($entered_otp !== (string) $_SESSION['reset_otp']) {

        $error = 'Invalid OTP code. Please try again.';

    } else {

        try {

            $email = $_SESSION['reset_email'];

            $hashed_password = password_hash(
                $new_password,
                PASSWORD_DEFAULT
            );

            $stmt = $conn->prepare(
                'UPDATE users SET password = ? WHERE email = ?'
            );

            $stmt->execute([
                $hashed_password,
                $email
            ]);

            if ($stmt->rowCount() > 0) {

                unset($_SESSION['reset_email']);
                unset($_SESSION['reset_otp']);

                echo '<!DOCTYPE html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Password Updated | NoteSphere</title>
                    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                </head>
                <body style="background:#0a0a0a;">

                <script>
                    Swal.fire({
                        icon: "success",
                        title: "Password Updated",
                        text: "Your password has been updated. You can now login.",
                        confirmButtonColor: "#39FF14",
                        background: "#0a0a0a",
                        color: "#fff"
                    }).then(function() {
                        window.location.href = "login.php";
                    });
                </script>

                </body>
                </html>';

                exit();

            } else {

                $error =
                    'Something went wrong. Please try again.';
            }

        } catch (PDOException $e) {

            $error =
                'Something went wrong with the database.';
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

    <title>Reset Password | NoteSphere</title>

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
                rgba(0,0,0,0.8);
        }

        h2 {

            font-size: 1.75rem;

            font-weight: 800;

            margin-bottom: 8px;

            text-align: center;
        }

        .subtitle {

            font-size: 0.85rem;

            color: #666;

            text-align: center;

            margin-bottom: 30px;

            line-height: 1.5;
        }

        form {

            display: flex;

            flex-direction: column;

            width: 100%;
        }

        .input-group {

            margin-bottom: 15px;

            width: 100%;
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
                rgba(57,255,20,0.08);
        }

        input::placeholder {
            color: #666;
        }

        .show-pass-container {

            color: #888;

            display: flex;

            align-items: center;

            font-size: 0.85rem;

            margin-bottom: 20px;

            cursor: pointer;

            user-select: none;
        }

        .show-pass-container input {

            width: 16px;
            height: 16px;

            margin-right: 8px;

            accent-color: #39FF14;

            cursor: pointer;
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
        }

        button[type="submit"]:hover {

            background: #32e010;

            transform: translateY(-2px);

            box-shadow:
                0 5px 20px
                rgba(57,255,20,0.3);
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

    <h2>Reset Password</h2>

    <p class="subtitle">
        Enter the OTP sent to your email
        and create a new password.
    </p>

    <form
        method="POST"
        id="resetForm"
        novalidate
    >

        <div class="input-group">

            <input
                type="text"
                name="otp"
                id="otp"
                placeholder="Enter 6-digit OTP"
                maxlength="6"
                inputmode="numeric"
                autocomplete="off"
            >

        </div>

        <div class="input-group">

            <input
                type="password"
                name="password"
                id="password"
                placeholder="New Password"
                autocomplete="new-password"
            >

        </div>

        <div class="input-group">

            <input
                type="password"
                name="confirm_password"
                id="confirm_password"
                placeholder="Confirm New Password"
                autocomplete="new-password"
            >

        </div>

        <label class="show-pass-container">

            <input
                type="checkbox"
                id="showPassword"
            >

            Show Passwords

        </label>

        <button type="submit">
            Update Password
        </button>

    </form>

</div>

<script>

const form =
    document.getElementById('resetForm');

const otp =
    document.getElementById('otp');

const password =
    document.getElementById('password');

const confirmPassword =
    document.getElementById('confirm_password');

const showPassword =
    document.getElementById('showPassword');

showPassword.addEventListener('change', function () {

    if (this.checked) {

        password.type = 'text';
        confirmPassword.type = 'text';

    } else {

        password.type = 'password';
        confirmPassword.type = 'password';
    }

});

otp.addEventListener('input', function () {

    this.value =
        this.value.replace(/\D/g, '');

});

form.addEventListener('submit', function (event) {

    event.preventDefault();

    const otpValue = otp.value.trim();
    const passwordValue = password.value;
    const confirmValue = confirmPassword.value;

    if (otpValue === '') {

        Swal.fire({

            icon: 'warning',

            title: 'OTP Required',

            text: 'Please enter the OTP.',

            confirmButtonColor: '#39FF14',

            background: '#0a0a0a',

            color: '#fff'

        });

        otp.focus();

        return;
    }

    if (!/^[0-9]{6}$/.test(otpValue)) {

        Swal.fire({

            icon: 'warning',

            title: 'Invalid OTP',

            text: 'OTP must be 6 digits.',

            confirmButtonColor: '#39FF14',

            background: '#0a0a0a',

            color: '#fff'

        });

        otp.focus();

        return;
    }

    if (passwordValue === '') {

        Swal.fire({

            icon: 'warning',

            title: 'Password Required',

            text: 'Please create a new password.',

            confirmButtonColor: '#39FF14',

            background: '#0a0a0a',

            color: '#fff'

        });

        password.focus();

        return;
    }

    if (passwordValue.length < 8) {

        Swal.fire({

            icon: 'warning',

            title: 'Password Too Short',

            text: 'Password must be at least 8 characters.',

            confirmButtonColor: '#39FF14',

            background: '#0a0a0a',

            color: '#fff'

        });

        password.focus();

        return;
    }

    if (confirmValue === '') {

        Swal.fire({

            icon: 'warning',

            title: 'Confirm Password',

            text: 'Please confirm your password.',

            confirmButtonColor: '#39FF14',

            background: '#0a0a0a',

            color: '#fff'

        });

        confirmPassword.focus();

        return;
    }

    if (passwordValue !== confirmValue) {

        Swal.fire({

            icon: 'warning',

            title: 'Password Mismatch',

            text: 'Passwords do not match.',

            confirmButtonColor: '#39FF14',

            background: '#0a0a0a',

            color: '#fff'

        });

        confirmPassword.focus();

        return;
    }

    form.submit();

});

</script>

<?php if (!empty($error)): ?>

<script>

Swal.fire({

    icon: 'error',

    title: 'Reset Password',

    text: <?= json_encode($error) ?>,

    confirmButtonColor: '#39FF14',

    background: '#0a0a0a',

    color: '#fff'

});

</script>

<?php endif; ?>

</body>

</html>

