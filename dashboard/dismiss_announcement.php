<?php
session_start();
include('../includes/db_config.php'); 
/** @var PDO $conn */

// 1. Siguraduhin na naka-login ang student at may announcement_id na ipinasa sa POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['announcement_id'])) {
    
    // Kunin ang user_id ng student na naka-login mula sa session
    $user_id = $_SESSION['user_id'] ?? null;
    $announcement_id = $_POST['announcement_id'];

    if ($user_id && $announcement_id) {
        // 2. Ginamit na natin ang user_id para magtugma sa database table mo
        $sql = "INSERT IGNORE INTO dismissed_announcements (user_id, announcement_id) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$user_id, $announcement_id]);
    }

    // 3. Pagkatapos ma-dismiss, ibalik agad ang student sa dashboard
    header("Location: student_dashboard.php");
    exit();
} else {
    // Kapag direktang binuksan sa URL o walang POST, ibalik din sa dashboard
    header("Location: student_dashboard.php");
    exit();
}
?>