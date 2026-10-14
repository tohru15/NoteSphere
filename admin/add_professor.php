<?php
session_start();
require_once '../includes/db_config.php';
/** @var PDO $conn */

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $professor_id = trim($_POST['professor_id'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = 'professor';

    if (empty($professor_id) || empty($full_name) || empty($email) || empty($password)) {
        $error = "All primary information fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address format.";
    } else {
        // Check if professor_id or email already exists
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE professor_id = ? OR email = ?");
        $check_stmt->execute([$professor_id, $email]);
        
        if ($check_stmt->rowCount() > 0) {
            $error = "An account is already registered with this Professor ID or Email.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $insert_stmt = $conn->prepare("
                INSERT INTO users (professor_id, full_name, email, password, role, department, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            if ($insert_stmt->execute([$professor_id, $full_name, $email, $hashed_password, $role, $department])) {
                $success = "Professor account successfully added!";
            } else {
                $error = "An error occurred while saving. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Add Professor - NoteSphere</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { 
            --neon: #39FF14; 
            --neon-glow: rgba(57, 255, 20, 0.15);
            --bg: #030303; 
            --card-bg: #09090b; 
            --text: #f8fafc; 
            --border-color: #1f1f23; 
            --sub-text: #94a3b8;
            --hover-bg: #18181b;
            --danger-red: #ff4d4d;
        }

        body.light-mode {
            --card-bg: #ffffff;
            --text: #000000;
            --border-color: #e2e8f0;
            --sub-text: #64748b;
            --neon: #10b981;
            --neon-glow: rgba(16, 185, 129, 0.1);
            --hover-bg: #f1f5f9;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }

        .page-bg {
            position: fixed;
            top: 0; left: 0; width: 100vw; height: 100vh;
            z-index: -999;
            background: linear-gradient(rgba(11, 11, 11, 0.85), rgba(11, 11, 11, 0.92)), url('../assets/registerbg.png') no-repeat center center;
            background-size: cover;
            background-attachment: fixed;
            pointer-events: none;
        }

        body.light-mode .page-bg {
            background: url('../assets/registerbg.png') no-repeat center center;
            background-size: cover;
            background-attachment: fixed;
        }

        body { 
            position: relative;
            color: var(--text);
            display: flex; 
            flex-direction: column;
            min-height: 100vh;
            padding: 20px 15px 40px 15px;
            transition: color 0.3s ease;
            overflow-x: hidden;
        }
        
        .form-header {
            width: 100%;
            max-width: 520px;
            margin: 0 auto 15px auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .form-header h2 {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text);
        }

        .close-btn {
            width: 38px; height: 38px;
            border-radius: 50%;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            display: flex; align-items: center; justify-content: center;
            color: var(--sub-text);
            text-decoration: none;
            font-size: 1.2rem;
            transition: all 0.2s ease;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            flex-shrink: 0;
        }

        .close-btn:hover {
            background: var(--hover-bg);
            color: var(--danger-red);
            border-color: var(--danger-red);
        }

        .form-container { 
            width: 100%; 
            max-width: 520px; 
            margin: 0 auto; 
        }

        .card {
            background: var(--card-bg);
            border-radius: 16px;
            border: 1px solid var(--border-color);
            padding: 24px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
            width: 100%;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .alert-error { background: rgba(255, 77, 77, 0.1); color: var(--danger-red); border: 1px solid rgba(255, 77, 77, 0.2); }
        .alert-success { background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); }

        .input-group {
            margin-bottom: 18px;
        }

        .input-group label {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text);
        }

        .input-group input, .input-group select {
            width: 100%;
            padding: 12px 15px;
            border-radius: 10px;
            background: var(--hover-bg);
            border: 1px solid var(--border-color);
            color: var(--text);
            font-size: 0.95rem;
            outline: none;
            transition: border-color 0.2s;
        }

        .input-group input:focus, .input-group select:focus {
            border-color: var(--neon);
            box-shadow: 0 0 0 3px var(--neon-glow);
        }

        .submit-btn {
            width: 100%;
            padding: 13px;
            border-radius: 10px;
            background: var(--neon);
            color: #000;
            font-weight: 700;
            font-size: 1rem;
            border: none;
            cursor: pointer;
            transition: opacity 0.2s;
            margin-top: 10px;
        }

        .submit-btn:hover {
            opacity: 0.9;
        }

        @media (max-width: 480px) {
            body { padding: 12px 10px 30px 10px; }
            .card { padding: 16px; border-radius: 12px; }
            .form-header h2 { font-size: 1.1rem; }
        }
    </style>
</head>
<body>
    <div class="page-bg"></div>

    <div class="form-header">
        <h2>Add New Professor</h2>
        <a href="admin_dashboard.php" class="close-btn" title="Close & Return">&#10005;</a>
    </div>

    <div class="form-container">
        <div class="card">
            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form action="add_professor.php" method="POST">
                <div class="input-group">
                    <label for="professor_id">Professor ID Number</label>
                    <input type="text" id="professor_id" name="professor_id" placeholder="e.g., PROF-2026-001" required>
                </div>

                <div class="input-group">
                    <label for="full_name">Full Name (with Title)</label>
                    <input type="text" id="full_name" name="full_name" placeholder="e.g., Prof. Juan Dela Cruz" required>
                </div>

                <div class="input-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" placeholder="professor@cdm.edu.ph" required>
                </div>

                <div class="input-group">
                    <label for="department">Department / Institute</label>
                    <select id="department" name="department">
                        <option value="Institute of Computer Studies">Institute of Computer Studies (ICS)</option>
                        <option value="Institute of Education">Institute of Education</option>
                        <option value="Institute of Business and Computing">Institute of Business and Computing</option>
                    </select>
                </div>

                <div class="input-group">
                    <label for="password">Temporary Password</label>
                    <input type="password" id="password" name="password" placeholder="••••••••" required>
                </div>

                <button type="submit" class="submit-btn">Save Professor Account</button>
            </form>
        </div>
    </div>

    <script>
        function applyTheme(theme) {
            if (theme === 'light') {
                document.body.classList.add('light-mode');
            } else {
                document.body.classList.remove('light-mode');
            }
        }

        document.addEventListener("DOMContentLoaded", () => {
            const savedTheme = localStorage.getItem('notesphere_theme') || localStorage.getItem('theme') || 'dark';
            applyTheme(savedTheme);
        });

        window.addEventListener('storage', (event) => {
            if (event.key === 'notesphere_theme' || event.key === 'theme') {
                if (event.newValue) {
                    applyTheme(event.newValue);
                }
            }
        });
    </script>
</body>
</html>