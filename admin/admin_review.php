<?php
require_once('../includes/db_config.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php"); exit();
}

$note_id = isset($_GET['id']) ? $_GET['id'] : 0;

$stmt = $conn->prepare("SELECT notes.*, users.full_name as uploader 
                        FROM notes 
                        JOIN users ON notes.user_id = users.id 
                        WHERE notes.id = ?");
$stmt->execute([$note_id]);
$note = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$note) { die("Note not found!"); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Review Note | Admin</title>
    <style>
        :root { --accent: #c1d300; --bg: #0a0a0a; --card: #121212; --text: #ffffff; --success: #2ecc71; --danger: #ff4d4d; }
        body { background: var(--bg); color: var(--text); font-family: 'Plus Jakarta Sans', sans-serif; padding: 50px; }
        .card { background: var(--card); border: 1px solid #333; padding: 40px; border-radius: 24px; max-width: 600px; margin: auto; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        h1 { color: var(--accent); font-weight: 800; font-size: 1.8rem; margin-bottom: 20px; }
        p { color: #aaa; margin-bottom: 10px; }
        strong { color: #fff; }
        .btn { display: inline-block; padding: 12px 25px; margin-top: 20px; text-decoration: none; font-weight: 800; border-radius: 12px; transition: 0.3s; text-align: center; }
        
        /* Buttons Styling */
        .btn-view { background: #fff; color: #000; width: 58%; margin-top: 30px; margin-left: 100px; }
        .btn-app { background: var(--success); color: #000; width: 48%; }
        .btn-rej { background: var(--danger); color: #fff; width: 48%; }
        .btn:hover { opacity: 0.9; transform: translateY(-2px); }
        .action-row { display: flex; justify-content: space-between; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Reviewing: <?= htmlspecialchars($note['title']) ?></h1>
        <p><strong>Uploader:</strong> <?= htmlspecialchars($note['uploader']) ?></p>
        <p><strong>Description:</strong><br> <?= htmlspecialchars($note['description']) ?></p>
        
        <a href="../uploads/<?= htmlspecialchars($note['file_path']) ?>" target="_blank" class="btn btn-view">VIEW ATTACHED DOCUMENT</a>
        
        <div class="action-row">
            <a href="admin_dashboard.php?approve=<?= $note['id'] ?>" class="btn btn-app">APPROVE</a>
            <a href="admin_dashboard.php?reject=<?= $note['id'] ?>" class="btn btn-rej">REJECT</a>
        </div>
    </div>
</body>
</html>