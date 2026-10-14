<?php
session_start();
include('../includes/db_config.php');

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header("Location: view_notes.php");
    exit();
}

$note_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// 1. I-fetch ang existing rating kung meron na
$stmt = $conn->prepare("SELECT rating FROM note_ratings WHERE note_id = ? AND user_id = ?");
$stmt->execute([$note_id, $user_id]);
$existing_rating = $stmt->fetchColumn(); // Magbabalik ng 1-5 o false

// 2. Pag-process ng submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $rating = $_POST['rating'];

    $stmt = $conn->prepare("INSERT INTO note_ratings (note_id, user_id, rating) 
                            VALUES (?, ?, ?) 
                            ON DUPLICATE KEY UPDATE rating = ?");
    $stmt->execute([$note_id, $user_id, $rating, $rating]);

    header("Location: view_notes.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Rate Note | NoteSphere</title>
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#0f172a">
    <style>
        body { background: #000; color: #fff; display: flex; justify-content: center; align-items: center; min-height: 100vh; font-family: 'Plus Jakarta Sans', sans-serif; }
        .rate-card { background: #121212; padding: 40px; border-radius: 20px; text-align: center; border: 1px solid #333; width: 350px; }
        
        .star-rating-input { display: flex; flex-direction: row-reverse; justify-content: center; gap: 5px; margin: 20px 0; }
        .star-rating-input input { display: none; }
        
        .star-rating-input label { 
            font-size: 3rem; 
            color: #333; 
            cursor: pointer; 
            transition: 0.2s; 
        }
        
        /* Hover at Select effects */
        .star-rating-input label:hover,
        .star-rating-input label:hover ~ label,
        .star-rating-input input:checked ~ label {
            color: #39FF14;
        }
    </style>
</head>
<body>
    <div class="rate-card">
        <h2><?php echo ($existing_rating) ? "Update your rating" : "Rate this Note"; ?></h2>
        <form method="POST">
            <div class="star-rating-input">
                <?php for($i=5; $i>=1; $i--): ?>
                    <input type="radio" name="rating" id="star<?php echo $i; ?>" value="<?php echo $i; ?>" 
                           <?php echo ($existing_rating == $i) ? 'checked' : ''; ?> required>
                    <label for="star<?php echo $i; ?>">★</label>
                <?php endfor; ?>
            </div>
            <button type="submit" style="width: 100%; padding: 12px; background: #39FF14; border:none; border-radius:10px; font-weight:800; cursor:pointer; color: #000;">
                <?php echo ($existing_rating) ? "Update Rating" : "Submit Rating"; ?>
            </button>
        </form>
        <a href="view_notes.php" style="color: #666; font-size: 0.8rem; display: block; margin-top: 20px; text-decoration: none;">Back to Library</a>
    </div>
    <script>
    // Awtomatikong basahin at i-apply ang tema sa bawat page pagka-load
    if (localStorage.getItem('notesphere_theme') === 'light') {
        document.body.classList.add('light-mode');
    }
</script>
</body>
</html>