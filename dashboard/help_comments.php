<?php
session_start();
require_once '../includes/db_config.php';

// Siguraduhing naka-login ang student at tama ang role
if (!isset($_SESSION['user_id']) || (isset($_SESSION['role']) && $_SESSION['role'] !== 'Student')) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Kunin ang details ng current user
$user_stmt = $conn->prepare("SELECT full_name, email, profile_pic FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$current_user = $user_stmt->fetch(PDO::FETCH_ASSOC);

$profile_pic = !empty($current_user['profile_pic']) ? $current_user['profile_pic'] : 'https://api.dicebear.com/7.x/avataaars/svg?seed=' . urlencode($current_user['email'] ?? 'user');

// 1. Bilangin ang mga unread notifications (mula sa help board replies/interactions)
$notif_count_stmt = $conn->prepare("SELECT COUNT(*) FROM help_notifications WHERE user_id = ? AND is_read = 0");
$notif_count_stmt->execute([$user_id]);
$unread_notifications_count = $notif_count_stmt->fetchColumn();

// 2. Bilangin ang mga UNREAD ANNOUNCEMENTS (Sinusubukan ang mga posibleng table at column names)
$announcement_count = 0;
try {
    // Sinusubukan kung may announcements table na may is_read o status column
    $ann_stmt = $conn->prepare("SELECT COUNT(*) FROM announcements WHERE (is_read = 0 OR status = 'Unread')");
    $ann_stmt->execute();
    $announcement_count = $ann_stmt->fetchColumn();
} catch (Exception $e) {
    try {
        // Fallback kung sakaling walang is_read column pero may announcements table
        $ann_stmt2 = $conn->query("SELECT COUNT(*) FROM announcements");
        $announcement_count = $ann_stmt2->fetchColumn();
    } catch (Exception $ex) {
        $announcement_count = 0;
    }
}

// Kabuuang bilang ng mga unread para sa bell icon badge
$total_unread = $unread_notifications_count + $announcement_count;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- ANTI-CACHE HEADERS: Para iwas ERR_FAILED at cache issues -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    
    <title>Student Dashboard - NoteSphere</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- SweetAlert2 CSS para sa magandang popup alerts -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { 
            --neon: #39FF14; 
            --neon-glow: rgba(57, 255, 20, 0.15);
            --bg: #050505; 
            --card-bg: #0a0a0a; 
            --text: #f8fafc; 
            --sidebar-bg: #000;
            --border-color: #1a1a1a; 
            --sub-text: #888;
            --hover-bg: rgba(255, 255, 20, 0.05);
            --input-bg: #030303;
        }

        body.light-mode {
            --bg: #f4f6f9;
            --card-bg: #ffffff;
            --text: #000000;
            --sidebar-bg: #ffffff;
            --border-color: #e2e8f0;
            --sub-text: #64748b;
            --neon: #2ecc71;
            --neon-glow: rgba(46, 204, 113, 0.1);
            --hover-bg: #f2f2f2;
            --input-bg: #f8fafc;
        }

        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
            font-family: 'Plus Jakarta Sans', sans-serif; 
        }

        body { 
            background: var(--bg);
            color: var(--text);
            display: flex; 
            flex-direction: column;
            min-height: 100vh;
            padding-bottom: 40px;
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        .fb-header { 
            background-color: var(--card-bg); 
            border-bottom: 1px solid var(--border-color); 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            padding: 0 15px; 
            height: 60px; 
            position: sticky; 
            top: 0; 
            z-index: 100; 
            box-shadow: 0 1px 3px rgba(0,0,0,0.1); 
        }
        .header-left h2 { color: var(--neon); font-size: 1.3rem; font-weight: 800; }
        .header-right-group { display: flex; align-items: center; gap: 8px; }
        
        .nav-icon-btn { 
            width: 38px; 
            height: 38px; 
            border-radius: 50%; 
            background: var(--hover-bg); 
            border: 1px solid var(--border-color); 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            cursor: pointer; 
            color: var(--sub-text); 
            text-decoration: none; 
            font-size: 1.05rem; 
            position: relative;
            transition: background 0.2s, color 0.2s; 
        }
        .nav-icon-btn:hover { background: var(--border-color); color: var(--neon); }
        .nav-profile-pic { width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 1px solid var(--border-color); }

        /* Badge Counter */
        .badge-count {
            position: absolute;
            top: -4px;
            right: -4px;
            background: #ff4757;
            color: white;
            font-size: 0.65rem;
            font-weight: 700;
            padding: 2px 5px;
            border-radius: 50%;
            border: 2px solid var(--card-bg);
        }

        .dashboard-container { 
            width: 100%; 
            max-width: 1000px; 
            margin: 20px auto; 
            padding: 0 15px; 
        }

        .welcome-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .welcome-card h1 { font-size: 1.4rem; color: var(--neon); margin-bottom: 6px; }
        .welcome-card p { color: var(--sub-text); font-size: 0.9rem; line-height: 1.4; }

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 15px;
        }

        .menu-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 18px;
            text-decoration: none;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 14px;
            transition: transform 0.2s, border-color 0.2s;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .menu-card:hover {
            transform: translateY(-2px);
            border-color: var(--neon);
        }
        .menu-icon {
            font-size: 1.8rem;
            width: 50px;
            height: 50px;
            background: var(--hover-bg);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--border-color);
            flex-shrink: 0;
        }
        .menu-info h3 { font-size: 1rem; font-weight: 700; margin-bottom: 3px; }
        .menu-info p { font-size: 0.82rem; color: var(--sub-text); line-height: 1.3; }

        /* Mobile Responsive Adjustments */
        @media (max-width: 480px) {
            .header-left h2 { font-size: 1.1rem; }
            .nav-icon-btn { width: 34px; height: 34px; font-size: 0.9rem; }
            .nav-profile-pic { width: 34px; height: 34px; }
            .welcome-card { padding: 15px; }
            .welcome-card h1 { font-size: 1.2rem; }
            .menu-card { padding: 14px; }
            .menu-icon { width: 44px; height: 44px; font-size: 1.5rem; }
        }
    </style>
</head>
<body>

    <header class="fb-header">
        <div class="header-left">
            <h2>NoteSphere</h2>
        </div>
        <div class="header-right-group">
            <a href="help_board.php" class="nav-icon-btn" title="Help Board Feed">🏠</a>
            
            <a href="help_notifications.php" class="nav-icon-btn" title="Mga Notipikasyon">
                🔔
                <?php if ($total_unread > 0): ?>
                    <span class="badge-count"><?php echo $total_unread; ?></span>
                <?php endif; ?>
            </a>

            <a href="profile.php"><img src="<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile" class="nav-profile-pic"></a>
            
            <!-- Logout na may SweetAlert Confirmation -->
            <a href="#" onclick="confirmLogout(event)" class="nav-icon-btn" title="Mag-logout">🚪</a>
        </div>
    </header>

    <div class="dashboard-container">
        <div class="welcome-card">
            <h1>Maligayang pagbabalik, <?php echo htmlspecialchars($current_user['full_name']); ?>! 👋</h1>
            <p>I-access ang iyong mga tanong, tulong sa pag-aaral, at student help board sa ibaba.</p>
        </div>

        <div class="cards-grid">
            <a href="help_board.php" class="menu-card">
                <div class="menu-icon">💬</div>
                <div class="menu-info">
                    <h3>Help Board Feed</h3>
                    <p>Magtanong at makipag-ugnayan sa mga kapwa estudyante.</p>
                </div>
            </a>

            <a href="help_history.php" class="menu-card">
                <div class="menu-icon">📜</div>
                <div class="menu-info">
                    <h3>Kasaysayan ng Post</h3>
                    <p>Tingnan ang iyong mga nakaraang tanong at komento.</p>
                </div>
            </a>

            <a href="help_notifications.php" class="menu-card">
                <div class="menu-icon">🔔</div>
                <div class="menu-info">
                    <h3>Notipikasyon & Anunsyo</h3>
                    <p><?php echo $total_unread; ?> bagong abiso o anunsyo ang naghihintay sa iyo.</p>
                </div>
            </a>

            <a href="profile.php" class="menu-card">
                <div class="menu-icon">⚙️</div>
                <div class="menu-info">
                    <h3>Profile ng Account</h3>
                    <p>I-manage ang iyong mga detalye at tema (Dark/Light mode).</p>
                </div>
            </a>
        </div>
    </div>

    <script>
        // Automatic Service Worker Cleanup para iwas cache/ERR_FAILED errors sa localhost
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.getRegistrations().then(function(registrations) {
                for(let registration of registrations) {
                    registration.unregister();
                }
            }).catch(err => console.log(err));
        }

        // SweetAlert para sa Logout Confirmation
        function confirmLogout(event) {
            event.preventDefault();
            Swal.fire({
                title: 'Gusto mo nang lumabas?',
                text: "Mag-a-out ka na ba sa iyong NoteSphere account?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#39FF14',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Oo, Mag-logout',
                cancelButtonText: 'Kanselahin',
                background: '#0a0a0a',
                color: '#f8fafc'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../auth/logout.php';
                }
            });
        }

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
                if (event.newValue) applyTheme(event.newValue);
            }
        });
    </script>
</body>
</html>