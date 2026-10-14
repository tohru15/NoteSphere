<?php

session_start();

require_once '../includes/db_config.php';

/** @var PDO $conn */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

require_once __DIR__ . '/../includes/PHPMailer-master/PHPMailer-master/src/Exception.php';
require_once __DIR__ . '/../includes/PHPMailer-master/PHPMailer-master/src/PHPMailer.php';
require_once __DIR__ . '/../includes/PHPMailer-master/PHPMailer-master/src/SMTP.php';

// =========================================================
// VARIABLES
// =========================================================

$errors = [];

$full_name = '';
$email = '';
$course = '';
$student_status = '';
$year_level = '';
$section = '';
$enrolled_subjects = '';

$registrationLimitReached = false;

// =========================================================
// REGISTRATION FORM SUBMISSION
// =========================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // =====================================================
    // GET FORM VALUES
    // =====================================================

    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $course = trim($_POST['course'] ?? '');
    $student_status = trim($_POST['student_status'] ?? '');
    $year_level = trim($_POST['year_level'] ?? '');
    $section = trim($_POST['section'] ?? '');
    $enrolled_subjects = trim($_POST['enrolled_subjects'] ?? '');

    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // =====================================================
    // CHECK ADMIN-CONFIGURED REGISTRATION LIMIT
    // =====================================================

    try {

        // The limit must be configured in system_limits.
        // No registration limit is hard-coded in this file.

        $limitStmt = $conn->query("
            SELECT max_users
            FROM system_limits
            ORDER BY id ASC
            LIMIT 1
        ");

        $limitRow = $limitStmt->fetch(PDO::FETCH_ASSOC);

        if (
            !$limitRow ||
            !isset($limitRow['max_users']) ||
            !is_numeric($limitRow['max_users'])
        ) {
            $errors[] =
                'Registration is temporarily unavailable. Please contact the administrator.';

        } else {

            $maxUsers = (int) $limitRow['max_users'];

            // Count all existing accounts in the users table.
            $countStmt = $conn->query("
                SELECT COUNT(*)
                FROM users
            ");

            $registeredUsers = (int) $countStmt->fetchColumn();

            if (
                $maxUsers <= 0 ||
                $registeredUsers >= $maxUsers
            ) {
                $registrationLimitReached = true;

                $errors[] =
                    'Registration is currently closed because the maximum number of user accounts has been reached. Please try again later or contact the administrator.';
            }
        }

    } catch (PDOException $e) {

        // Fail closed: do not continue if the limit cannot be checked.
        error_log('NoteSphere registration limit error: ' . $e->getMessage());

        $errors[] =
            'Unable to verify registration availability. Please try again later.';
    }

    // =====================================================
    // VALIDATION
    // Only continue if registration is available.
    // =====================================================

    if (empty($errors)) {

        // -------------------------------------------------
        // FULL NAME
        // -------------------------------------------------

        if ($full_name === '') {

            $errors[] = 'Please enter your full name.';

        } elseif (strlen($full_name) < 2) {

            $errors[] = 'Please enter a valid full name.';
        }

        // -------------------------------------------------
        // EMAIL
        // -------------------------------------------------

        if ($email === '') {

            $errors[] = 'Please enter your email.';

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

            $errors[] = 'Please enter a valid email.';
        }

        // -------------------------------------------------
        // COURSE
        // -------------------------------------------------

        $allowedCourses = [
            'BSIT',
            'BSCpE',
            'BSBA',
            'BSE',
            'BECE',
            'BEE',
            'BTLE'
        ];

        if ($course === '') {

            $errors[] = 'Please select your course.';

        } elseif (!in_array($course, $allowedCourses, true)) {

            $errors[] = 'Invalid course selected.';
        }

        // -------------------------------------------------
        // STUDENT STATUS
        // -------------------------------------------------

        if ($student_status === '') {

            $errors[] = 'Please select your student status.';

        } elseif (!in_array(
            $student_status,
            ['Regular', 'Irregular'],
            true
        )) {

            $errors[] = 'Invalid student status.';
        }

        // -------------------------------------------------
        // YEAR LEVEL
        // -------------------------------------------------

        if ($year_level === '') {

            $errors[] = 'Please select your year level.';

        } elseif (!in_array(
            $year_level,
            ['1', '2', '3', '4'],
            true
        )) {

            $errors[] = 'Invalid year level.';
        }

        // -------------------------------------------------
        // SECTION
        // -------------------------------------------------

        if ($student_status === 'Regular') {

            $section = strtoupper(trim($section));

            if ($section === '') {

                $errors[] = 'Please select your section.';

            } elseif (!in_array(
                $section,
                ['A', 'B', 'C', 'D', 'E'],
                true
            )) {

                $errors[] = 'Invalid section selected.';
            }

        } elseif ($student_status === 'Irregular') {

            $section = strtoupper(trim($section));

            if ($section === '') {

                $errors[] = 'Please enter your section.';

            } elseif (strlen($section) > 50) {

                $errors[] = 'Section must not exceed 50 characters.';
            }
        }

        // -------------------------------------------------
        // SUBJECT CODES
        // -------------------------------------------------

        if ($enrolled_subjects === '') {

            $errors[] = 'Please enter your enrolled subject codes.';

        } else {

            $subjectArray = explode(',', $enrolled_subjects);
            $cleanSubjects = [];

            foreach ($subjectArray as $subject) {

                $subject = strtoupper(trim($subject));

                if ($subject !== '') {
                    $cleanSubjects[] = $subject;
                }
            }

            if (empty($cleanSubjects)) {

                $errors[] = 'Please enter at least one subject code.';

            } else {

                $enrolled_subjects = implode(', ', $cleanSubjects);
            }
        }

        // -------------------------------------------------
        // PASSWORD
        // -------------------------------------------------

        if ($password === '') {

            $errors[] = 'Please enter your password.';

        } elseif (strlen($password) < 8) {

            $errors[] = 'Password must be at least 8 characters.';
        }

        // -------------------------------------------------
        // CONFIRM PASSWORD
        // -------------------------------------------------

        if ($confirm_password === '') {

            $errors[] = 'Please confirm your password.';

        } elseif ($password !== $confirm_password) {

            $errors[] = 'Passwords do not match.';
        }

        // -------------------------------------------------
        // CHECK EXISTING EMAIL
        // -------------------------------------------------

        if (empty($errors)) {

            try {

                $stmt = $conn->prepare("
                    SELECT user_id
                    FROM users
                    WHERE email = ?
                    LIMIT 1
                ");

                $stmt->execute([$email]);

                if ($stmt->fetch()) {
                    $errors[] = 'This email is already registered.';
                }

            } catch (PDOException $e) {

                error_log('NoteSphere email check error: ' . $e->getMessage());

                $errors[] =
                    'Unable to check your registration. Please try again.';
            }
        }

        // =================================================
        // CREATE OTP AND SEND VERIFICATION EMAIL
        // =================================================

        if (empty($errors)) {

            $mail = null;

            try {

                // Generate six-digit OTP.
                $otp_code = (string) random_int(100000, 999999);

                // Hash the password before saving it in the session.
                $hashed_password = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                if ($hashed_password === false) {
                    throw new \RuntimeException(
                        'Unable to secure the password.'
                    );
                }

                // -------------------------------------------------
                // SMTP CONFIGURATION
                // -------------------------------------------------

                $mail = new PHPMailer(true);

                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;

                $mail->Username = 'notesphere.system@gmail.com';

                /*
                 * Configure this environment variable on your
                 * local/server environment with your NEW Gmail
                 * App Password. Do not put the password in source code.
                 */
                $appPassword = getenv('NOTESPHERE_GMAIL_APP_PASSWORD');

                if (!$appPassword) {
                    throw new \RuntimeException(
                        'The email service is not configured.'
                    );
                }

                // Gmail App Passwords should be stored without spaces.
                $mail->Password = preg_replace('/\s+/', '', $appPassword);

                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;
                $mail->SMTPKeepAlive = false;
                $mail->Timeout = 20;
                $mail->CharSet = 'UTF-8';

                // -------------------------------------------------
                // EMAIL SENDER AND RECEIVER
                // -------------------------------------------------

                $mail->setFrom(
                    'notesphere.system@gmail.com',
                    'NoteSphere System'
                );

                $mail->addAddress($email, $full_name);

                // -------------------------------------------------
                // EMAIL CONTENT
                // -------------------------------------------------

                $mail->isHTML(true);

                $mail->Subject = 'NoteSphere Registration Verification';

                $safeName = htmlspecialchars(
                    $full_name,
                    ENT_QUOTES,
                    'UTF-8'
                );

                $mail->Body = '
                    <div style="
                        font-family:Arial,sans-serif;
                        max-width:600px;
                        margin:auto;
                        padding:25px;
                        background:#ffffff;
                        color:#222222;
                        border-radius:12px;
                    ">
                        <h2>NoteSphere Email Verification</h2>

                        <p>Hello ' . $safeName . ',</p>

                        <p>Thank you for registering on NoteSphere.</p>

                        <p>Your verification code is:</p>

                        <div style="
                            text-align:center;
                            margin:25px 0;
                        ">
                            <span style="
                                display:inline-block;
                                padding:15px 25px;
                                background:#f2f2f2;
                                border-radius:10px;
                                font-size:32px;
                                font-weight:bold;
                                letter-spacing:8px;
                            ">' . $otp_code . '</span>
                        </div>

                        <p>
                            Enter this code on the NoteSphere verification
                            page to complete your registration.
                        </p>

                        <p>
                            If you did not create this account,
                            please ignore this email.
                        </p>

                        <br>

                        <p><strong>NoteSphere System</strong></p>
                    </div>
                ';

                $mail->AltBody =
                    'Your NoteSphere verification code is: ' . $otp_code;

                // Send email before creating the temporary session.
                $mail->send();

                // -------------------------------------------------
                // SAVE TEMPORARY REGISTRATION
                // -------------------------------------------------

                $_SESSION['temp_user'] = [
                    'full_name' => $full_name,
                    'email' => $email,
                    'course' => $course,
                    'student_status' => $student_status,
                    'year_level' => (int) $year_level,
                    'section' => $section,
                    'enrolled_subjects' => $enrolled_subjects,
                    'password' => $hashed_password,
                    'role' => 'student',
                    'otp_code' => $otp_code
                ];

                $_SESSION['verify_email'] = $email;
                $_SESSION['verify_user'] = true;

                // -------------------------------------------------
                // REDIRECT TO OTP VERIFICATION
                // -------------------------------------------------

                header('Location: verify_otp.php');
                exit();

            } catch (MailException $e) {

                error_log('NoteSphere email error: ' . $e->getMessage());

                unset(
                    $_SESSION['temp_user'],
                    $_SESSION['verify_email'],
                    $_SESSION['verify_user']
                );

                $errors[] =
                    'Unable to send the verification email. Please check your email address or try again later.';

            } catch (\Throwable $e) {

                error_log('NoteSphere registration error: ' . $e->getMessage());

                unset(
                    $_SESSION['temp_user'],
                    $_SESSION['verify_email'],
                    $_SESSION['verify_user']
                );

                $errors[] =
                    'A system error occurred during registration. Please try again later.';
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
        content="width=device-width, initial-scale=1.0"
    >

    <title>Register - NoteSphere</title>

    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: url('../assets/login1.jpg') no-repeat center center;
            background-size: cover;
            background-attachment: fixed;
            color: #fff;
        }

        .back-btn {
            position: fixed;
            top: 25px;
            left: 25px;
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(255,255,255,0.25);
            border-radius: 50%;
            background: rgba(0,0,0,0.55);
            color: #fff;
            text-decoration: none;
            font-size: 18px;
            transition: 0.3s;
        }

        .back-btn:hover {
            color: #39FF14;
            border-color: #39FF14;
        }

        .card {
            width: 100%;
            max-width: 560px;
            padding: 40px 30px;
            border: 1px solid #1a1a1a;
            border-radius: 24px;
            background: #0a0a0a;
            box-shadow: 0 20px 40px rgba(0,0,0,0.8);
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            column-gap: 15px;
        }

        .full-width {
            grid-column: 1 / -1;
        }

        h2 {
            margin: 0 0 8px;
            font-size: 1.75rem;
            font-weight: 800;
            text-align: center;
        }

        .desc {
            margin: 0 0 30px;
            color: #666;
            font-size: 0.85rem;
            text-align: center;
        }

        .input-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            color: #888;
            font-size: 0.78rem;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid #222;
            border-radius: 12px;
            background: #111;
            color: #fff;
            font-family: inherit;
            font-size: 0.95rem;
            outline: none;
            transition: 0.3s;
        }

        input:focus,
        select:focus,
        textarea:focus {
            border-color: #39FF14;
            box-shadow: 0 0 0 2px rgba(57,255,20,0.08);
        }

        select option {
            background: #000;
            color: #fff;
        }

        textarea {
            min-height: 85px;
            resize: vertical;
        }

        .subject-note {
            margin-top: -8px;
            margin-bottom: 15px;
            color: #666;
            font-size: 0.72rem;
            line-height: 1.5;
        }

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
            border: none;
            background: transparent;
            color: #888;
            font-family: inherit;
            font-size: 0.75rem;
            cursor: pointer;
        }

        .show-password:hover {
            color: #39FF14;
        }

        button[type="submit"] {
            width: 100%;
            padding: 14px;
            margin-top: 5px;
            border: none;
            border-radius: 12px;
            background: #39FF14;
            color: #000;
            font-family: inherit;
            font-size: 0.95rem;
            font-weight: 800;
            cursor: pointer;
            transition: 0.3s;
        }

        button[type="submit"]:hover {
            background: #32e010;
            box-shadow: 0 0 20px rgba(57,255,20,0.25);
        }

        button[type="submit"]:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            box-shadow: none;
        }

        .login-text {
            margin-top: 25px;
            color: #666;
            font-size: 0.85rem;
            text-align: center;
        }

        .login-text a {
            color: #39FF14;
            text-decoration: none;
        }

        .login-text a:hover {
            text-decoration: underline;
        }

        @media (max-width: 700px) {
            .back-btn {
                top: 15px;
                left: 15px;
            }

            .card {
                max-width: 440px;
                margin: 20px;
                padding: 30px 20px;
            }

            .form-grid {
                display: block;
            }

            .full-width {
                grid-column: auto;
            }
        }
    </style>

</head>

<body>

    <a href="../index.php" class="back-btn" title="Back">←</a>

    <div class="card">

        <h2>Create Account</h2>

        <p class="desc">
            Register your NoteSphere student account
        </p>

        <form method="POST" id="registerForm">

            <div class="form-grid">

                <div class="input-group">
                    <label for="full_name">Full Name</label>

                    <input
                        type="text"
                        id="full_name"
                        name="full_name"
                        placeholder="Enter your full name"
                        value="<?= htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8') ?>"
                        autocomplete="name"
                    >
                </div>

                <div class="input-group">
                    <label for="email">Email</label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="Enter your email"
                        value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
                        autocomplete="email"
                    >
                </div>

                <div class="input-group">
                    <label for="course">Course</label>

                    <select id="course" name="course">
                        <option value="">Select your course</option>
                        <option value="BSIT" <?= $course === 'BSIT' ? 'selected' : '' ?>>BSIT</option>
                        <option value="BSCpE" <?= $course === 'BSCpE' ? 'selected' : '' ?>>BSCpE</option>
                        <option value="BSBA" <?= $course === 'BSBA' ? 'selected' : '' ?>>BSBA Major in Human Resource Management</option>
                        <option value="BSE" <?= $course === 'BSE' ? 'selected' : '' ?>>BSE Major in Science</option>
                        <option value="BECE" <?= $course === 'BECE' ? 'selected' : '' ?>>BECE</option>
                        <option value="BEE" <?= $course === 'BEE' ? 'selected' : '' ?>>BEE</option>
                        <option value="BTLE" <?= $course === 'BTLE' ? 'selected' : '' ?>>BTLE</option>
                    </select>
                </div>

                <div class="input-group">
                    <label for="student_status">Student Status</label>

                    <select
                        id="student_status"
                        name="student_status"
                        onchange="updateSection()"
                    >
                        <option value="">Select your student status</option>
                        <option value="Regular" <?= $student_status === 'Regular' ? 'selected' : '' ?>>Regular</option>
                        <option value="Irregular" <?= $student_status === 'Irregular' ? 'selected' : '' ?>>Irregular</option>
                    </select>
                </div>

                <div class="input-group">
                    <label for="year_level">Year Level</label>

                    <select id="year_level" name="year_level">
                        <option value="">Select your year level</option>
                        <option value="1" <?= $year_level === '1' ? 'selected' : '' ?>>1st Year</option>
                        <option value="2" <?= $year_level === '2' ? 'selected' : '' ?>>2nd Year</option>
                        <option value="3" <?= $year_level === '3' ? 'selected' : '' ?>>3rd Year</option>
                        <option value="4" <?= $year_level === '4' ? 'selected' : '' ?>>4th Year</option>
                    </select>
                </div>

                <div class="input-group">
                    <label for="regularSection">Section</label>

                    <select id="regularSection" name="section">
                        <option value="">Select your section</option>
                        <option value="A" <?= $section === 'A' ? 'selected' : '' ?>>A</option>
                        <option value="B" <?= $section === 'B' ? 'selected' : '' ?>>B</option>
                        <option value="C" <?= $section === 'C' ? 'selected' : '' ?>>C</option>
                        <option value="D" <?= $section === 'D' ? 'selected' : '' ?>>D</option>
                        <option value="E" <?= $section === 'E' ? 'selected' : '' ?>>E</option>
                    </select>

                    <input
                        type="text"
                        id="irregularSection"
                        placeholder="Enter your section"
                        value="<?= $student_status === 'Irregular' ? htmlspecialchars($section, ENT_QUOTES, 'UTF-8') : '' ?>"
                        maxlength="50"
                        style="display:none;"
                    >
                </div>

                <div class="input-group full-width">
                    <label for="enrolled_subjects">Enrolled Subject Codes</label>

                    <textarea
                        id="enrolled_subjects"
                        name="enrolled_subjects"
                        placeholder="Example: ITELECT1, PROG1, WEBDEV1"
                    ><?= htmlspecialchars($enrolled_subjects, ENT_QUOTES, 'UTF-8') ?></textarea>

                    <div class="subject-note">
                        Use uppercase subject codes separated by commas.
                        Check your Curriculum or COR.
                    </div>
                </div>

                <div class="input-group">
                    <label for="password">Password</label>

                    <div class="password-box">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Enter your password"
                            autocomplete="new-password"
                        >

                        <button
                            type="button"
                            class="show-password"
                            onclick="togglePassword('password', this)"
                        >Show</button>
                    </div>
                </div>

                <div class="input-group">
                    <label for="confirm_password">Confirm Password</label>

                    <div class="password-box">
                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            placeholder="Confirm your password"
                            autocomplete="new-password"
                        >

                        <button
                            type="button"
                            class="show-password"
                            onclick="togglePassword('confirm_password', this)"
                        >Show</button>
                    </div>
                </div>

            </div>

            <button type="submit" id="registerButton">
                Register
            </button>

        </form>

        <div class="login-text">
            Already have an account?
            <a href="login.php">Login</a>
        </div>

    </div>

    <script>
        // =====================================================
        // UPDATE SECTION FIELD
        // =====================================================

        function updateSection() {

            const status = document.getElementById('student_status').value;
            const regularSection = document.getElementById('regularSection');
            const irregularSection = document.getElementById('irregularSection');

            if (status === 'Regular') {

                regularSection.style.display = 'block';
                regularSection.disabled = false;
                regularSection.name = 'section';

                irregularSection.style.display = 'none';
                irregularSection.disabled = true;
                irregularSection.removeAttribute('name');

            } else if (status === 'Irregular') {

                regularSection.style.display = 'none';
                regularSection.disabled = true;
                regularSection.removeAttribute('name');

                irregularSection.style.display = 'block';
                irregularSection.disabled = false;
                irregularSection.name = 'section';

            } else {

                regularSection.style.display = 'block';
                regularSection.disabled = false;
                regularSection.name = 'section';

                irregularSection.style.display = 'none';
                irregularSection.disabled = true;
                irregularSection.removeAttribute('name');
            }
        }

        // =====================================================
        // SHOW / HIDE PASSWORD
        // =====================================================

        function togglePassword(inputId, button) {

            const input = document.getElementById(inputId);

            if (input.type === 'password') {
                input.type = 'text';
                button.textContent = 'Hide';
            } else {
                input.type = 'password';
                button.textContent = 'Show';
            }
        }

        // =====================================================
        // SUBJECT CODES AUTO UPPERCASE
        // =====================================================

        document.getElementById('enrolled_subjects')
            .addEventListener('input', function () {
                this.value = this.value.toUpperCase();
            });

        // =====================================================
        // IRREGULAR SECTION AUTO UPPERCASE
        // =====================================================

        document.getElementById('irregularSection')
            .addEventListener('input', function () {
                this.value = this.value.toUpperCase();
            });

        // =====================================================
        // ESCAPE HTML FOR SUBJECT CONFIRMATION
        // =====================================================

        function escapeHtml(text) {

            const div = document.createElement('div');
            div.textContent = text;

            return div.innerHTML;
        }

        // =====================================================
        // FORM SUBMIT VALIDATION
        // =====================================================

        document.getElementById('registerForm')
            .addEventListener('submit', function (event) {

                event.preventDefault();

                const form = this;

                const fullName = document.getElementById('full_name').value.trim();
                const email = document.getElementById('email').value.trim();
                const course = document.getElementById('course').value;
                const status = document.getElementById('student_status').value;
                const year = document.getElementById('year_level').value;

                const regularSection = document.getElementById('regularSection').value.trim();
                const irregularSection = document.getElementById('irregularSection').value.trim();

                const subjects = document.getElementById('enrolled_subjects').value.trim();
                const password = document.getElementById('password').value;
                const confirmPassword = document.getElementById('confirm_password').value;

                const alertStyle = {
                    confirmButtonColor: '#39FF14',
                    background: '#0a0a0a',
                    color: '#fff'
                };

                // FULL NAME

                if (fullName === '') {
                    Swal.fire({
                        ...alertStyle,
                        icon: 'warning',
                        title: 'Full Name Required',
                        text: 'Please enter your full name.'
                    });
                    return;
                }

                if (fullName.length < 2) {
                    Swal.fire({
                        ...alertStyle,
                        icon: 'warning',
                        title: 'Invalid Full Name',
                        text: 'Please enter a valid full name.'
                    });
                    return;
                }

                // EMAIL

                if (email === '') {
                    Swal.fire({
                        ...alertStyle,
                        icon: 'warning',
                        title: 'Email Required',
                        text: 'Please enter your email.'
                    });
                    return;
                }

                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                    Swal.fire({
                        ...alertStyle,
                        icon: 'warning',
                        title: 'Invalid Email',
                        text: 'Please enter a valid email.'
                    });
                    return;
                }

                // COURSE

                if (course === '') {
                    Swal.fire({
                        ...alertStyle,
                        icon: 'warning',
                        title: 'Course Required',
                        text: 'Please select your course.'
                    });
                    return;
                }

                // STUDENT STATUS

                if (status === '') {
                    Swal.fire({
                        ...alertStyle,
                        icon: 'warning',
                        title: 'Student Status Required',
                        text: 'Please select your student status.'
                    });
                    return;
                }

                // YEAR LEVEL

                if (year === '') {
                    Swal.fire({
                        ...alertStyle,
                        icon: 'warning',
                        title: 'Year Level Required',
                        text: 'Please select your year level.'
                    });
                    return;
                }

                // SECTION

                if (status === 'Regular' && regularSection === '') {
                    Swal.fire({
                        ...alertStyle,
                        icon: 'warning',
                        title: 'Section Required',
                        text: 'Please select your section.'
                    });
                    return;
                }

                if (status === 'Irregular' && irregularSection === '') {
                    Swal.fire({
                        ...alertStyle,
                        icon: 'warning',
                        title: 'Section Required',
                        text: 'Please enter your section.'
                    });
                    return;
                }

                // SUBJECTS

                if (subjects === '') {
                    Swal.fire({
                        ...alertStyle,
                        icon: 'warning',
                        title: 'Subject Codes Required',
                        text: 'Please enter your enrolled subject codes.'
                    });
                    return;
                }

                // PASSWORD

                if (password === '') {
                    Swal.fire({
                        ...alertStyle,
                        icon: 'warning',
                        title: 'Password Required',
                        text: 'Please enter your password.'
                    });
                    return;
                }

                if (password.length < 8) {
                    Swal.fire({
                        ...alertStyle,
                        icon: 'warning',
                        title: 'Password Too Short',
                        text: 'Password must be at least 8 characters.'
                    });
                    return;
                }

                // CONFIRM PASSWORD

                if (confirmPassword === '') {
                    Swal.fire({
                        ...alertStyle,
                        icon: 'warning',
                        title: 'Confirm Password',
                        text: 'Please confirm your password.'
                    });
                    return;
                }

                if (password !== confirmPassword) {
                    Swal.fire({
                        ...alertStyle,
                        icon: 'warning',
                        title: 'Passwords Do Not Match',
                        text: 'Please make sure both passwords are the same.'
                    });
                    return;
                }

                // ENSURE THE CORRECT SECTION FIELD IS SUBMITTED

                updateSection();

                // SUBJECT CODE CONFIRMATION

                Swal.fire({
                    ...alertStyle,
                    icon: 'question',
                    title: 'Confirm Your Subject Codes',

                    html: `
                        <p>Please make sure your subject codes are correct.</p>

                        <div style="
                            background:#111;
                            border:1px solid #222;
                            border-radius:10px;
                            padding:12px;
                            margin:15px 0;
                            color:#39FF14;
                            font-weight:700;
                        ">
                            ${escapeHtml(subjects)}
                        </div>

                        <p style="color:#888;font-size:13px;">
                            Check the capital letters and compare them
                            with your Curriculum or COR.
                        </p>

                        <p style="
                            color:#ff5555;
                            font-size:12px;
                            margin-top:15px;
                        ">
                            Incorrect subject codes may prevent you from
                            receiving the correct announcements.
                        </p>
                    `,

                    showCancelButton: true,
                    confirmButtonText: 'Yes, Correct',
                    cancelButtonText: 'Review',
                    cancelButtonColor: '#555',
                    width: '600px'

                }).then(function (result) {

                    if (result.isConfirmed) {

                        updateSection();

                        const button = document.getElementById('registerButton');

                        button.disabled = true;
                        button.textContent = 'Sending Verification...';

                        // Native submit sends the form to the PHP
                        // registration-limit check.
                        HTMLFormElement.prototype.submit.call(form);
                    }
                });
            });

        // INITIAL SECTION STATE

        updateSection();
    </script>

    <?php if (!empty($errors)): ?>
        <script>
            Swal.fire({
                icon: <?= $registrationLimitReached ? "'warning'" : "'error'" ?>,

                title: <?= $registrationLimitReached
                    ? json_encode('Registration Closed')
                    : json_encode('Registration Failed') ?>,

                html: <?= json_encode(
                    implode(
                        '<br><br>',
                        array_map(
                            function ($error) {
                                return htmlspecialchars(
                                    $error,
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                            },
                            $errors
                        )
                    )
                ) ?>,

                confirmButtonText: 'OK',
                confirmButtonColor: '#39FF14',
                background: '#0a0a0a',
                color: '#fff',
                width: '600px'
            });
        </script>
    <?php endif; ?>

</body>
</html>