<?php
session_start();
include('../includes/db_config.php');
/** @var PDO $conn */

// Siguraduhin na ang nag-click ay Admin lang
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Clearing Announcements...</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body style="background: #0a0a0a;">
<?php
try {
    $conn->beginTransaction();

    // 1. Burahin muna ang mga submissions at dismissed announcements para hindi mag-error sa Foreign Key constraints (kung meron man)
    $conn->exec("DELETE FROM announcement_submissions");
    $conn->exec("DELETE FROM dismissed_announcements");

    // 2. Burahin na ang LAHAT ng announcements mula sa mga propesor
    $conn->exec("DELETE FROM announcements");

    $conn->commit();

    // SweetAlert para sa matagumpay na pagbura
    echo "
    <script>
        Swal.fire({
            title: 'MATAGUMPAY!',
            text: 'Nabura na ang lahat ng lumang announcements at submissions para sa bagong school year!',
            icon: 'success',
            confirmButtonColor: '#39FF14',
            background: '#121212',
            color: '#fff'
        }).then(() => {
            window.location.href = 'admin_dashboard.php';
        });
    </script>";

} catch (Exception $e) {
    $conn->rollBack();
    // SweetAlert para sakaling magka-error
    echo "
    <script>
        Swal.fire({
            title: 'MAY ERROR!',
            text: 'Hindi matanggal: " . addslashes($e->getMessage()) . "',
            icon: 'error',
            confirmButtonColor: '#ff4d4d',
            background: '#121212',
            color: '#fff'
        }).then(() => {
            window.location.href = 'admin_dashboard.php';
        });
    </script>";
}
?>
</body>
</html>