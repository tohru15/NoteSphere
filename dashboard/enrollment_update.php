
<?php

// =========================================================
// NoteSphere - Student Enrollment Update
// Student Home UI - No Sidebar
// =========================================================

session_start();

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'student'
) {
    header("Location: ../auth/login.php");
    exit();
}


// =========================================================
// DATABASE CONNECTION
// =========================================================

$host = 'localhost';
$dbname = '1notesphere_db';
$username = 'root';
$password = '';

try {

    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );

    $pdo->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

} catch (PDOException $e) {

    die("Database Connection Error: " . $e->getMessage());
}


$user_id = (int) $_SESSION['user_id'];


// =========================================================
// DEFAULT VALUES
// =========================================================

$active_semester = '1st Semester';
$active_year = '2026-2027';

$allowEnrollmentUpdate = false;

$currentUser = null;
$archives = [];

$errorMessage = '';
$successMessage = '';


// =========================================================
// GET SYSTEM SETTINGS
// =========================================================

try {

    $stmt_settings = $pdo->prepare("
        SELECT
            current_semester,
            current_academic_year,
            allow_student_update
        FROM system_settings
        WHERE id = 1
        LIMIT 1
    ");

    $stmt_settings->execute();

    $settings = $stmt_settings->fetch(PDO::FETCH_ASSOC);

    if ($settings) {

        if (!empty($settings['current_semester'])) {
            $active_semester =
                trim($settings['current_semester']);
        }

        if (!empty($settings['current_academic_year'])) {
            $active_year =
                trim($settings['current_academic_year']);
        }

        $allowEnrollmentUpdate =
            !empty($settings['allow_student_update']) &&
            (int)$settings['allow_student_update'] === 1;
    }

} catch (PDOException $e) {

    // Kapag walang permission setting,
    // SAFE DEFAULT = LOCKED
    $allowEnrollmentUpdate = false;
}


// =========================================================
// FETCH CURRENT STUDENT
// =========================================================

try {

    $stmt_user = $pdo->prepare("
        SELECT
            user_id,
            full_name,
            email,
            course,
            student_status,
            year_level,
            section,
            enrolled_subjects,
            profile_pic
        FROM users
        WHERE user_id = ?
        LIMIT 1
    ");

    $stmt_user->execute([$user_id]);

    $currentUser =
        $stmt_user->fetch(PDO::FETCH_ASSOC);

    if (!$currentUser) {

        session_destroy();

        header("Location: ../auth/login.php");
        exit();
    }

} catch (PDOException $e) {

    die("Unable to load student information.");
}


// =========================================================
// CURRENT VALUES
// =========================================================

$currentYearLevel =
    $currentUser['year_level'] ?? '';

$currentSection =
    $currentUser['section'] ?? '';

$currentStudentStatus =
    $currentUser['student_status'] ?? 'Regular';

$currentSubjects =
    $currentUser['enrolled_subjects'] ?? '';


// =========================================================
// PREPARE MULTIPLE SECTIONS
// =========================================================

$sectionValues = [];

if (!empty($currentSection)) {

    $parts = explode(',', $currentSection);

    foreach ($parts as $part) {

        $part = trim($part);

        if ($part !== '') {
            $sectionValues[] = $part;
        }
    }
}

if (empty($sectionValues)) {
    $sectionValues[] = '';
}


// =========================================================
// POST - UPDATE ENROLLMENT
// =========================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    // -----------------------------------------------------
    // ADMIN PERMISSION CHECK
    // -----------------------------------------------------

    if (!$allowEnrollmentUpdate) {

        $_SESSION['enrollment_error'] =
            "Enrollment update is currently locked by the administrator.";

        header("Location: enrollment_update.php");
        exit();
    }


    // -----------------------------------------------------
    // GET FORM VALUES
    // -----------------------------------------------------

    $yearLevel =
        trim($_POST['year_level'] ?? '');

    $studentStatus =
        trim($_POST['student_status'] ?? '');

    $subjects =
        trim($_POST['enrolled_subjects'] ?? '');

    $postedSections =
        $_POST['sections'] ?? [];


    // -----------------------------------------------------
    // YEAR LEVEL
    // -----------------------------------------------------

    if ($yearLevel === '') {

        $_SESSION['enrollment_error'] =
            "Please enter your year level.";

        header("Location: enrollment_update.php");
        exit();
    }

    if (!ctype_digit($yearLevel)) {

        $_SESSION['enrollment_error'] =
            "Year level must contain numbers only.";

        header("Location: enrollment_update.php");
        exit();
    }

    if ((int)$yearLevel < 1) {

        $_SESSION['enrollment_error'] =
            "Year level must be at least 1.";

        header("Location: enrollment_update.php");
        exit();
    }


    // -----------------------------------------------------
    // STUDENT STATUS
    // -----------------------------------------------------

    $allowedStatuses = [
        'Regular',
        'Irregular'
    ];

    if (
        !in_array(
            $studentStatus,
            $allowedStatuses,
            true
        )
    ) {

        $_SESSION['enrollment_error'] =
            "Invalid student status.";

        header("Location: enrollment_update.php");
        exit();
    }


    // -----------------------------------------------------
    // MULTIPLE SECTIONS
    // -----------------------------------------------------

    $cleanSections = [];

    if (is_array($postedSections)) {

        foreach ($postedSections as $section) {

            $section = trim($section);

            if ($section === '') {
                continue;
            }

            // Remove commas because the system
            // separates sections using commas.
            $section = str_replace(',', '', $section);

            // Remove extra spaces
            $section =
                preg_replace('/\s+/', ' ', $section);

            $section = trim($section);

            if ($section === '') {
                continue;
            }

            // Prevent duplicate sections
            $alreadyExists = false;

            foreach ($cleanSections as $existing) {

                if (
                    strtolower($existing) ===
                    strtolower($section)
                ) {
                    $alreadyExists = true;
                    break;
                }
            }

            if (!$alreadyExists) {
                $cleanSections[] = $section;
            }
        }
    }


    // -----------------------------------------------------
    // REQUIRE SECTION
    // -----------------------------------------------------

    if (empty($cleanSections)) {

        $_SESSION['enrollment_error'] =
            "Please enter at least one section.";

        header("Location: enrollment_update.php");
        exit();
    }


    // -----------------------------------------------------
    // SAVE MULTIPLE SECTIONS AS ONE VALUE
    // -----------------------------------------------------

    $sectionsString =
        implode(', ', $cleanSections);


    if (strlen($sectionsString) > 255) {

        $_SESSION['enrollment_error'] =
            "The sections are too long. Please reduce the number of sections.";

        header("Location: enrollment_update.php");
        exit();
    }


    // -----------------------------------------------------
    // SUBJECTS
    // -----------------------------------------------------

    if ($subjects === '') {

        $_SESSION['enrollment_error'] =
            "Please enter your enrolled subjects.";

        header("Location: enrollment_update.php");
        exit();
    }


    // =====================================================
    // TRANSACTION
    // =====================================================

    try {

        $pdo->beginTransaction();


        // -------------------------------------------------
        // GET OLD DATA
        // -------------------------------------------------

        $oldStmt = $pdo->prepare("
            SELECT
                course,
                year_level,
                section,
                student_status,
                enrolled_subjects
            FROM users
            WHERE user_id = ?
            LIMIT 1
            FOR UPDATE
        ");

        $oldStmt->execute([$user_id]);

        $oldData =
            $oldStmt->fetch(PDO::FETCH_ASSOC);


        if (!$oldData) {

            throw new Exception(
                "Student account could not be found."
            );
        }


        // -------------------------------------------------
        // ARCHIVE OLD ENROLLMENT
        // -------------------------------------------------

        $archiveStmt = $pdo->prepare("
            INSERT INTO student_archives (
                user_id,
                course,
                year_level,
                section,
                student_status,
                enrolled_subjects,
                academic_year,
                semester
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $archiveStmt->execute([
            $user_id,
            $oldData['course'] ?? '',
            $oldData['year_level'] ?? 0,
            $oldData['section'] ?? '',
            $oldData['student_status'] ?? 'Regular',
            $oldData['enrolled_subjects'] ?? '',
            $active_year,
            $active_semester
        ]);


        // -------------------------------------------------
        // UPDATE USERS
        // -------------------------------------------------

        $updateStmt = $pdo->prepare("
            UPDATE users
            SET
                year_level = ?,
                section = ?,
                enrolled_subjects = ?,
                student_status = ?
            WHERE user_id = ?
        ");

        $updateStmt->execute([
            (int)$yearLevel,
            $sectionsString,
            $subjects,
            $studentStatus,
            $user_id
        ]);


        // -------------------------------------------------
        // COMMIT
        // -------------------------------------------------

        $pdo->commit();


        $_SESSION['enrollment_success'] =
            "Your enrollment information has been updated successfully.";

        header("Location: enrollment_update.php");
        exit();

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $_SESSION['enrollment_error'] =
            "Unable to update your enrollment. Please try again.";

        header("Location: enrollment_update.php");
        exit();
    }
}


// =========================================================
// SESSION MESSAGES
// =========================================================

if (isset($_SESSION['enrollment_success'])) {

    $successMessage =
        $_SESSION['enrollment_success'];

    unset($_SESSION['enrollment_success']);
}

if (isset($_SESSION['enrollment_error'])) {

    $errorMessage =
        $_SESSION['enrollment_error'];

    unset($_SESSION['enrollment_error']);
}


// =========================================================
// REFRESH CURRENT USER
// =========================================================

try {

    $stmt_user = $pdo->prepare("
        SELECT
            user_id,
            full_name,
            email,
            course,
            student_status,
            year_level,
            section,
            enrolled_subjects,
            profile_pic
        FROM users
        WHERE user_id = ?
        LIMIT 1
    ");

    $stmt_user->execute([$user_id]);

    $currentUser =
        $stmt_user->fetch(PDO::FETCH_ASSOC);


    $currentYearLevel =
        $currentUser['year_level'] ?? '';

    $currentSection =
        $currentUser['section'] ?? '';

    $currentStudentStatus =
        $currentUser['student_status'] ?? 'Regular';

    $currentSubjects =
        $currentUser['enrolled_subjects'] ?? '';


    // -----------------------------------------------------
    // REFRESH SECTIONS
    // -----------------------------------------------------

    $sectionValues = [];

    if (!empty($currentSection)) {

        $parts =
            explode(',', $currentSection);

        foreach ($parts as $part) {

            $part = trim($part);

            if ($part !== '') {
                $sectionValues[] = $part;
            }
        }
    }

    if (empty($sectionValues)) {
        $sectionValues[] = '';
    }

} catch (PDOException $e) {

    $sectionValues = [''];
}


// =========================================================
// GET ARCHIVES
// =========================================================

try {

    $archiveListStmt = $pdo->prepare("
        SELECT
            archive_id,
            course,
            year_level,
            section,
            student_status,
            enrolled_subjects,
            academic_year,
            semester,
            archived_at
        FROM student_archives
        WHERE user_id = ?
        ORDER BY archived_at DESC
    ");

    $archiveListStmt->execute([$user_id]);

    $archives =
        $archiveListStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $archives = [];
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
    NoteSphere - Enrollment Update
</title>

<link
    href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
    rel="stylesheet"
>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


<style>

:root {

    --neon: #39FF14;
    --neon-glow: rgba(57,255,20,0.12);

    --card-bg: #0c0c0c;

    --text: #f8fafc;
    --sub-text: #94a3b8;

    --border: #1f293d;

    --danger: #ff4d4d;

    --input-bg: #111111;
}


body.light-mode {

    --card-bg: #ffffff;

    --text: #000000;

    --border: #e2e8f0;

    --sub-text: #000000;

    --neon: #2ecc71;

    --neon-glow: rgba(46,204,113,0.12);

    --input-bg: #ffffff;
}


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
    min-height: 100%;

    overflow-x: hidden;
}


body {

    position: relative;

    color: var(--text);

    min-height: 100vh;

    padding: 40px;

    transition:
        color 0.3s ease;
}


/* =========================================================
   BACKGROUND
========================================================= */

body::before {

    content: '';

    position: fixed;

    top: 0;
    left: 0;

    width: 100vw;
    height: 100vh;

    z-index: -1;

    background:

        linear-gradient(
            rgba(11,11,11,0.88),
            rgba(11,11,11,0.94)
        ),

        url('../assets/registerbg.png')
        no-repeat
        center
        center;

    background-size: cover;

    pointer-events: none;
}


body.light-mode::before {

    background:

        url('../assets/registerbg.png')
        no-repeat
        center
        center;

    background-size: cover;
}


/* =========================================================
   TOP BAR
========================================================= */

.top-bar {

    width: 100%;

    display: flex;

    align-items: center;

    justify-content: space-between;

    margin-bottom: 25px;
}


.page-brand {

    display: flex;

    align-items: center;

    gap: 12px;
}


.page-brand img {

    width: 38px;
    height: auto;
}


.page-brand h2 {

    color: var(--neon);

    font-size: 1.35rem;

    font-weight: 800;
}


.top-actions {

    display: flex;

    align-items: center;

    gap: 10px;
}


/* =========================================================
   THEME BUTTON
========================================================= */

.theme-btn {

    width: 42px;
    height: 42px;

    border-radius: 11px;

    border: 1px solid var(--border);

    background: var(--card-bg);

    color: var(--text);

    cursor: pointer;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 17px;

    transition: 0.3s;
}


.theme-btn:hover {

    border-color: var(--neon);

    color: var(--neon);
}


/* =========================================================
   CLOSE BUTTON
========================================================= */

.close-btn {

    width: 42px;
    height: 42px;

    border-radius: 11px;

    border: 1px solid var(--border);

    background: var(--card-bg);

    color: var(--text);

    cursor: pointer;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 25px;

    line-height: 1;

    transition: 0.3s;
}


.close-btn:hover {

    color: var(--danger);

    border-color: var(--danger);

    transform: scale(1.03);
}


/* =========================================================
   MAIN WRAPPER
========================================================= */

.container {

    width: 100%;

    max-width: 1100px;

    margin: 0 auto;
}


/* =========================================================
   WELCOME / HEADER CARD
========================================================= */

.welcome-box {

    background:

        linear-gradient(
            135deg,
            var(--card-bg),
            rgba(57,255,20,0.03)
        );

    border: 1px solid var(--border);

    border-radius: 20px;

    padding: 30px;

    margin-bottom: 25px;

    display: flex;

    justify-content: space-between;

    align-items: center;

    flex-wrap: wrap;

    gap: 20px;

    transition:
        background 0.3s ease,
        border-color 0.3s ease;
}


.welcome-box h1 {

    font-size: 1.8rem;

    font-weight: 800;

    margin-bottom: 7px;
}


.welcome-box p {

    color: var(--sub-text);

    font-size: 0.9rem;

    line-height: 1.5;
}


.semester-badge {

    background: var(--neon-glow);

    color: var(--neon);

    padding: 7px 14px;

    border-radius: 20px;

    font-size: 0.82rem;

    font-weight: 700;

    border: 1px solid var(--neon);

    white-space: nowrap;
}


/* =========================================================
   STATUS BADGES
========================================================= */

.status-wrap {

    display: flex;

    align-items: center;

    gap: 8px;

    flex-wrap: wrap;

    justify-content: flex-end;
}


.status-badge {

    padding: 7px 13px;

    border-radius: 20px;

    font-size: 0.78rem;

    font-weight: 700;
}


.status-open {

    color: var(--neon);

    background: var(--neon-glow);

    border: 1px solid var(--neon);
}


.status-locked {

    color: var(--danger);

    background: rgba(255,77,77,0.10);

    border: 1px solid rgba(255,77,77,0.35);
}


/* =========================================================
   SECTION HEADER
========================================================= */

.section-header {

    margin-top: 10px;

    margin-bottom: 18px;
}


.section-header h2 {

    font-size: 1.3rem;

    font-weight: 700;
}


.section-header p {

    color: var(--sub-text);

    font-size: 0.86rem;

    margin-top: 5px;

    line-height: 1.5;
}


/* =========================================================
   CARD
========================================================= */

.card {

    background: var(--card-bg);

    border: 1px solid var(--border);

    border-radius: 18px;

    padding: 25px;

    margin-bottom: 22px;

    transition:
        background 0.3s ease,
        border-color 0.3s ease;
}


.card-title {

    margin-bottom: 22px;
}


.card-title h2 {

    font-size: 1.12rem;

    font-weight: 700;
}


.card-title p {

    color: var(--sub-text);

    font-size: 0.82rem;

    margin-top: 5px;
}


/* =========================================================
   FORM GRID
========================================================= */

.form-grid {

    display: grid;

    grid-template-columns:
        repeat(2, minmax(0, 1fr));

    gap: 20px;
}


.form-group {

    display: flex;

    flex-direction: column;

    gap: 8px;
}


.form-group.full {

    grid-column: 1 / -1;
}


label {

    color: var(--text);

    font-size: 0.82rem;

    font-weight: 700;
}


.required {

    color: var(--danger);
}


input,
select,
textarea {

    width: 100%;

    padding: 12px 13px;

    border-radius: 11px;

    border: 1px solid var(--border);

    background: var(--input-bg);

    color: var(--text);

    outline: none;

    font-size: 0.85rem;

    transition:
        border-color 0.2s ease,
        box-shadow 0.2s ease;
}


input:focus,
select:focus,
textarea:focus {

    border-color: var(--neon);

    box-shadow:
        0 0 0 3px
        var(--neon-glow);
}


textarea {

    min-height: 120px;

    resize: vertical;
}


.helper {

    color: var(--sub-text);

    font-size: 0.72rem;

    line-height: 1.4;
}


/* =========================================================
   MULTIPLE SECTION INPUT
========================================================= */

.section-list {

    display: flex;

    flex-direction: column;

    gap: 9px;
}


.section-row {

    display: flex;

    gap: 9px;

    align-items: center;
}


.section-row input {

    flex: 1;
}


.remove-section {

    width: 42px;
    height: 42px;

    flex-shrink: 0;

    border-radius: 10px;

    border: 1px solid
        rgba(255,77,77,0.35);

    background:
        rgba(255,77,77,0.08);

    color: var(--danger);

    cursor: pointer;

    font-size: 20px;

    transition: 0.2s;
}


.remove-section:hover {

    background:
        rgba(255,77,77,0.16);
}


.add-section-btn {

    margin-top: 4px;

    width: fit-content;

    padding: 10px 14px;

    border-radius: 10px;

    border: 1px dashed var(--neon);

    background: var(--neon-glow);

    color: var(--neon);

    cursor: pointer;

    font-size: 0.8rem;

    font-weight: 700;

    transition: 0.2s;
}


.add-section-btn:hover {

    transform: translateY(-1px);

    box-shadow:
        0 6px 15px
        var(--neon-glow);
}


/* =========================================================
   BUTTONS
========================================================= */

.form-actions {

    display: flex;

    justify-content: flex-end;

    margin-top: 22px;
}


.save-btn {

    border: none;

    background: var(--neon);

    color: #000;

    padding: 12px 22px;

    border-radius: 11px;

    font-size: 0.84rem;

    font-weight: 800;

    cursor: pointer;

    transition: 0.2s;
}


.save-btn:hover {

    transform: translateY(-1px);

    box-shadow:
        0 8px 20px
        var(--neon-glow);
}


/* =========================================================
   LOCKED STATE
========================================================= */

.locked-card {

    text-align: center;

    padding: 55px 25px;
}


.lock-icon {

    width: 62px;
    height: 62px;

    margin: 0 auto 16px;

    border-radius: 50%;

    display: flex;

    align-items: center;

    justify-content: center;

    background:
        rgba(255,77,77,0.10);

    color: var(--danger);

    font-size: 27px;
}


.locked-card h2 {

    font-size: 1.15rem;

    margin-bottom: 8px;
}


.locked-card p {

    max-width: 600px;

    margin: 0 auto;

    color: var(--sub-text);

    font-size: 0.84rem;

    line-height: 1.6;
}


/* =========================================================
   ARCHIVES
========================================================= */

.archive-item {

    padding: 17px;

    border-radius: 14px;

    border: 1px solid var(--border);

    background:
        rgba(57,255,20,0.02);

    margin-bottom: 10px;
}


.archive-top {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 12px;

    margin-bottom: 12px;
}


.archive-period {

    color: var(--neon);

    font-size: 0.82rem;

    font-weight: 800;
}


.archive-date {

    color: var(--sub-text);

    font-size: 0.7rem;
}


.archive-details {

    display: grid;

    grid-template-columns:
        repeat(2, minmax(0, 1fr));

    gap: 12px;
}


.archive-detail {

    color: var(--text);

    font-size: 0.8rem;

    line-height: 1.5;
}


.archive-detail strong {

    display: block;

    color: var(--sub-text);

    font-size: 0.67rem;

    text-transform: uppercase;

    margin-bottom: 2px;
}


.empty-state {

    text-align: center;

    color: var(--sub-text);

    padding: 25px 10px;

    font-size: 0.82rem;
}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 768px) {

    body {

        padding:
            20px 14px;
    }


    .top-bar {

        margin-bottom: 18px;
    }


    .page-brand h2 {

        font-size: 1.15rem;
    }


    .page-brand img {

        width: 32px;
    }


    .theme-btn,
    .close-btn {

        width: 39px;
        height: 39px;
    }


    .welcome-box {

        padding:
            22px 18px;

        border-radius: 16px;
    }


    .welcome-box h1 {

        font-size: 1.4rem;
    }


    .status-wrap {

        width: 100%;

        justify-content: flex-start;
    }


    .form-grid {

        grid-template-columns: 1fr;
    }


    .form-group.full {

        grid-column: auto;
    }


    .card {

        padding: 19px;

        border-radius: 15px;
    }


    .archive-details {

        grid-template-columns: 1fr;
    }


    .archive-top {

        flex-direction: column;

        align-items: flex-start;
    }

}


@media (max-width: 480px) {

    body {

        padding:
            15px 10px;
    }


    .page-brand h2 {

        font-size: 1rem;
    }


    .top-actions {

        gap: 6px;
    }


    .welcome-box {

        padding: 19px 16px;
    }


    .welcome-box h1 {

        font-size: 1.25rem;
    }


    .section-header h2 {

        font-size: 1.1rem;
    }


    .form-actions {

        justify-content: stretch;
    }


    .save-btn {

        width: 100%;
    }

}

</style>

</head>


<body>


<!-- =====================================================
     TOP BAR
====================================================== -->

<div class="top-bar">

    <div class="page-brand">

        <img
            src="../assets/cdmlogo.png"
            alt="CDM Logo"
        >

        <h2>
            NoteSphere
        </h2>

    </div>


    <div class="top-actions">

    

        <button
            type="button"
            class="close-btn"
            onclick="goBack()"
            title="Back"
            aria-label="Back"
        >
            ×
        </button>

    </div>

</div>


<!-- =====================================================
     MAIN CONTAINER
====================================================== -->

<div class="container">


    <!-- =================================================
         HEADER
    ================================================== -->

    <div class="welcome-box">

        <div>

            <h1>
                Enrollment Update
            </h1>

            <p>
                Update your current academic information
                for the active school year and semester.
            </p>

        </div>


        <div class="status-wrap">

            <span class="semester-badge">

                <?= htmlspecialchars(
                    $active_semester
                    . ' | '
                    . $active_year
                ) ?>

            </span>


            <?php if ($allowEnrollmentUpdate): ?>

                <span class="status-badge status-open">
                    ✓ Open
                </span>

            <?php else: ?>

                <span class="status-badge status-locked">
                    🔒 Locked
                </span>

            <?php endif; ?>

        </div>

    </div>


    <!-- =================================================
         FORM SECTION
    ================================================== -->

    <div class="section-header">

        <h2>
            Current Enrollment
        </h2>

        <p>
            Your enrollment information for the current
            academic period.
        </p>

    </div>


    <?php if (!$allowEnrollmentUpdate): ?>


        <!-- =============================================
             LOCKED
        ============================================== -->

        <div class="card locked-card">

            <div class="lock-icon">
                🔒
            </div>

            <h2>
                Enrollment Update is Currently Locked
            </h2>

            <p>
                The administrator has not opened the
                enrollment update period yet. You can
                still view your enrollment archives,
                but you cannot submit an update until
                the administrator enables it.
            </p>

        </div>


    <?php else: ?>


        <!-- =============================================
             FORM
        ============================================== -->

        <div class="card">

            <div class="card-title">

                <h2>
                    Update Enrollment Information
                </h2>

                <p>
                    Please make sure all information
                    is correct before saving.
                </p>

            </div>


            <form
                method="POST"
                action=""
                id="enrollmentForm"
            >


                <div class="form-grid">


                    <!-- =================================
                         YEAR LEVEL
                    ================================== -->

                    <div class="form-group">

                        <label for="year_level">

                            Year Level
                            <span class="required">*</span>

                        </label>


                        <input
                            type="number"
                            id="year_level"
                            name="year_level"
                            min="1"
                            step="1"
                            value="<?= htmlspecialchars(
                                $currentYearLevel
                            ) ?>"
                            placeholder="Example: 3"
                            required
                        >


                        <span class="helper">

                            Enter your current year level.

                        </span>

                    </div>


                    <!-- =================================
                         STATUS
                    ================================== -->

                    <div class="form-group">

                        <label for="student_status">

                            Student Status
                            <span class="required">*</span>

                        </label>


                        <select
                            id="student_status"
                            name="student_status"
                            required
                        >

                            <option
                                value="Regular"
                                <?= $currentStudentStatus === 'Regular'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Regular
                            </option>


                            <option
                                value="Irregular"
                                <?= $currentStudentStatus === 'Irregular'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Irregular
                            </option>

                        </select>

                    </div>


                    <!-- =================================
                         SECTIONS
                    ================================== -->

                    <div class="form-group full">

                        <label>

                            Section/s
                            <span class="required">*</span>

                        </label>


                        <div
                            class="section-list"
                            id="sectionList"
                        >

                            <?php foreach (
                                $sectionValues
                                as $section
                            ): ?>

                                <div class="section-row">

                                    <input
                                        type="text"
                                        name="sections[]"
                                        value="<?= htmlspecialchars(
                                            $section
                                        ) ?>"
                                        placeholder="Example: 3D"
                                        maxlength="50"
                                        required
                                    >


                                    <button
                                        type="button"
                                        class="remove-section"
                                        title="Remove Section"
                                    >
                                        ×
                                    </button>

                                </div>

                            <?php endforeach; ?>

                        </div>


                        <button
                            type="button"
                            class="add-section-btn"
                            id="addSectionBtn"
                        >
                            + Add Section
                        </button>


                        <span class="helper">

                            Add as many sections as needed.
                            Example: 3D, 3E, 3F, 3G.

                        </span>

                    </div>


                    <!-- =================================
                         SUBJECTS
                    ================================== -->

                    <div class="form-group full">

                        <label for="enrolled_subjects">

                            Enrolled Subjects
                            <span class="required">*</span>

                        </label>


                        <textarea
                            id="enrolled_subjects"
                            name="enrolled_subjects"
                            placeholder="Enter your enrolled subjects..."
                            required
                        ><?= htmlspecialchars(
                            $currentSubjects
                        ) ?></textarea>


                        <span class="helper">

                            You may separate subjects
                            using commas.

                        </span>

                    </div>


                </div>


                <!-- =====================================
                     SAVE
                ====================================== -->

                <div class="form-actions">

                    <button
                        type="submit"
                        class="save-btn"
                    >
                        Save Enrollment
                    </button>

                </div>


            </form>

        </div>


    <?php endif; ?>


    <!-- =================================================
         ARCHIVES
    ================================================== -->

    <div class="section-header">

        <h2>
            Enrollment Archives
        </h2>

        <p>
            Previous enrollment information is stored
            here for your reference.
        </p>

    </div>


    <div class="card">


        <?php if (!empty($archives)): ?>


            <?php foreach ($archives as $archive): ?>


                <div class="archive-item">


                    <div class="archive-top">

                        <div class="archive-period">

                            <?= htmlspecialchars(
                                $archive['academic_year']
                            ) ?>

                            ·

                            <?= htmlspecialchars(
                                $archive['semester']
                            ) ?>

                        </div>


                        <div class="archive-date">

                            <?=
                            !empty(
                                $archive['archived_at']
                            )
                                ? date(
                                    'M d, Y h:i A',
                                    strtotime(
                                        $archive['archived_at']
                                    )
                                )
                                : ''
                            ?>

                        </div>

                    </div>


                    <div class="archive-details">


                        <div class="archive-detail">

                            <strong>
                                Course
                            </strong>

                            <?= htmlspecialchars(
                                $archive['course']
                            ) ?>

                        </div>


                        <div class="archive-detail">

                            <strong>
                                Year Level
                            </strong>

                            <?= htmlspecialchars(
                                $archive['year_level']
                            ) ?>

                        </div>


                        <div class="archive-detail">

                            <strong>
                                Section/s
                            </strong>

                            <?= htmlspecialchars(
                                $archive['section']
                            ) ?>

                        </div>


                        <div class="archive-detail">

                            <strong>
                                Student Status
                            </strong>

                            <?= htmlspecialchars(
                                $archive['student_status']
                            ) ?>

                        </div>


                        <div class="archive-detail">

                            <strong>
                                Enrolled Subjects
                            </strong>

                            <?= htmlspecialchars(
                                $archive['enrolled_subjects']
                            ) ?>

                        </div>


                    </div>


                </div>


            <?php endforeach; ?>


        <?php else: ?>


            <div class="empty-state">

                No previous enrollment records yet.

            </div>


        <?php endif; ?>


    </div>


</div>


<script>


// =========================================================
// BACK / CLOSE
// =========================================================

function goBack() {

    if (document.referrer) {

        history.back();

    } else {

        window.location.href =
            'student_home.php';

    }

}


// =========================================================
// THEME
// =========================================================

const themeBtn =
    document.getElementById('themeBtn');


function updateThemeButton(isLight) {

    themeBtn.textContent =
        isLight ? '🌙' : '🌞';

    themeBtn.title =
        isLight
            ? 'Switch to Dark Mode'
            : 'Switch to Light Mode';
}


function loadTheme() {

    const savedTheme =
        localStorage.getItem(
            'notesphere_theme'
        );


    if (savedTheme === 'light') {

        document.body.classList.add(
            'light-mode'
        );

        updateThemeButton(true);

    } else {

        document.body.classList.remove(
            'light-mode'
        );

        updateThemeButton(false);
    }
}


themeBtn.addEventListener(
    'click',
    function () {

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


        updateThemeButton(isLight);

    }
);


loadTheme();


// =========================================================
// MULTIPLE SECTION INPUT
// =========================================================

const sectionList =
    document.getElementById(
        'sectionList'
    );


const addSectionBtn =
    document.getElementById(
        'addSectionBtn'
    );


function createSectionRow() {

    const row =
        document.createElement('div');

    row.className =
        'section-row';


    const input =
        document.createElement('input');

    input.type = 'text';

    input.name = 'sections[]';

    input.placeholder =
        'Example: 3D';

    input.maxLength = 50;

    input.required = true;


    const removeBtn =
        document.createElement('button');

    removeBtn.type = 'button';

    removeBtn.className =
        'remove-section';

    removeBtn.title =
        'Remove Section';

    removeBtn.textContent = '×';


    removeBtn.addEventListener(
        'click',
        function () {

            const rows =
                sectionList.querySelectorAll(
                    '.section-row'
                );


            if (rows.length <= 1) {

                input.value = '';

                input.focus();

                return;
            }


            row.remove();

        }
    );


    row.appendChild(input);

    row.appendChild(removeBtn);


    return row;
}


addSectionBtn.addEventListener(
    'click',
    function () {

        const newRow =
            createSectionRow();


        sectionList.appendChild(
            newRow
        );


        newRow
            .querySelector('input')
            .focus();

    }
);


// =========================================================
// EXISTING REMOVE BUTTONS
// =========================================================

document
    .querySelectorAll(
        '.remove-section'
    )
    .forEach(function (button) {

        button.addEventListener(
            'click',
            function () {

                const rows =
                    sectionList.querySelectorAll(
                        '.section-row'
                    );


                if (rows.length <= 1) {

                    const input =
                        rows[0].querySelector(
                            'input'
                        );

                    input.value = '';

                    input.focus();

                    return;
                }


                button
                    .closest('.section-row')
                    .remove();

            }
        );

    });


// =========================================================
// SUCCESS MESSAGE
// =========================================================

<?php if (!empty($successMessage)): ?>

Swal.fire({

    icon: 'success',

    title: 'Enrollment Updated',

    text: <?= json_encode(
        $successMessage
    ) ?>,

    confirmButtonColor: '#39FF14',

    confirmButtonText: 'Okay'

});

<?php endif; ?>


// =========================================================
// ERROR MESSAGE
// =========================================================

<?php if (!empty($errorMessage)): ?>

Swal.fire({

    icon: 'error',

    title: 'Unable to Update',

    text: <?= json_encode(
        $errorMessage
    ) ?>,

    confirmButtonColor: '#ff4d4d',

    confirmButtonText: 'Okay'

});

<?php endif; ?>


</script>


</body>

</html>
