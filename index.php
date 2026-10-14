<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once('includes/db_config.php');

if (isset($_SESSION['user_id']))
{
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'professor') {
        header("Location: professor/professor_dashboard.php");
    } else {
        header("Location: dashboard/student_dashboard.php");
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>CDM Portal | NoteSphere</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    
    <!-- PWA Manifest Link -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#0d6efd">

    <!-- Service Worker Registration Script -->
    <script>
      if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('sw.js')
            .then(reg => console.log('Service Worker registered!', reg))
            .catch(err => console.log('Service Worker registration failed: ', err));
        });
      }
    </script>
<style>
    html, body {
        width: 100%;
        height: 100%;
        overflow: hidden;
        position: fixed;
        -webkit-overflow-scrolling: touch;
    }

    body { 
        font-family: 'Plus Jakarta Sans', sans-serif; 
        background: url('assets/login.jpg');
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        height: 100vh;
        height: 100dvh;
        display: flex; 
        align-items: center; 
        justify-content: center;
        color: #fff;
        margin: 0;
        padding: 15px;
        box-sizing: border-box;
        touch-action: manipulation;
    }

    .main-container { 
        text-align: center;
        width: 100%;
        max-width: 450px;
        padding: 15px;
        box-sizing: border-box;
    }

    .cdmlogo-img {
        width: 110px;
        max-width: 100%;
        margin-bottom: 5px;
    }

    h1 {
        font-size: clamp(2.5rem, 10vw, 3.5rem);
        font-weight: 800;
        line-height: 0.9;
        margin-bottom: 20px;
        letter-spacing: -2px;
    }

    .subtitle {
        color: #39FF14;
        font-size: 10px;
        font-weight: 800;
        margin-bottom: 40px;
        letter-spacing: 3px;
        text-transform: uppercase;
    }

    .button-group {
        display: flex;
        flex-direction: column;
        gap: 12px;
        width: 100%;
        max-width: 320px;
        margin: 0 auto;
    }

    .btn { 
        padding: 17px;
        text-decoration: none;
        border-radius: 22px;
        font-weight: 700;
        transition: 0.3s;
        border: 2px solid transparent;
        display: block;
        width: 100%;
        box-sizing: border-box;
        text-align: center;
        -webkit-tap-highlight-color: transparent;
    }

    .btn-login { 
        background: #39FF14;
        color: #000; 
    }

    .btn-login:hover { 
        transform: scale(1.02); 
    }

    .btn-register { 
        border: 2px solid rgba(255, 255, 255, 0.15); 
        color: #fff;
    }

    .btn-register:hover { 
        background: rgba(255, 255, 255, 0.05);
    }

    .info-link { 
        margin-top: 15px;
        color: #666; 
        font-size: 0.7rem; 
        text-transform: uppercase; 
        letter-spacing: 2px; 
        cursor: pointer; 
        transition: 0.3s;
        display: inline-block;
        -webkit-tap-highlight-color: transparent;
    }

    .info-link:hover { 
        color: #39FF14; 
    }

    /* Modal Design with Animation */
    .modal { 
        display: none; 
        position: fixed; 
        top: 0; 
        left: 0; 
        width: 100%; 
        height: 100%; 
        background: rgba(0,0,0,0.85); 
        backdrop-filter: blur(5px); 
        -webkit-backdrop-filter: blur(5px);
        z-index: 1000; 
        justify-content: center; 
        align-items: center; 
        opacity: 0; 
        transition: opacity 0.3s ease; 
        padding: 20px;
        box-sizing: border-box;
    }

    .modal.show { 
        display: flex; 
        opacity: 1; 
    }

    .modal-content { 
        background: #0a0a0a; 
        padding: 30px 20px; 
        border-radius: 20px; 
        border: 1px solid #333; 
        width: 100%;
        max-width: 450px; 
        text-align: left; 
        transform: translateY(20px); 
        transition: transform 0.3s ease; 
        box-sizing: border-box;
        max-height: 85vh;
        overflow-y: auto;
    }

    .modal.show .modal-content { 
        transform: translateY(0); 
    }

    .modal-content::-webkit-scrollbar {
        width: 6px;
    }
    .modal-content::-webkit-scrollbar-thumb {
        background: #333;
        border-radius: 4px;
    }
    
    .close-btn { 
        margin-top: 20px; 
        padding: 10px 25px; 
        background: transparent; 
        border: 1px solid #39FF14; 
        border-radius: 8px; 
        font-weight: 700; 
        cursor: pointer; 
        color: #39FF14; 
        transition: 0.3s; 
        display: block;
        margin-left: auto;
        margin-right: auto;
        -webkit-tap-highlight-color: transparent;
    }

    .close-btn:hover { 
        background: #39FF14; 
        color: #000; 
    }

    /* Custom PWA Install Floating Card (Desktop Default: Bottom-Right) */
    .pwa-floating-card {
        position: fixed;
        bottom: 20px;
        right: 20px;
        width: 300px;
        background: rgba(10, 10, 10, 0.95);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        padding: 15px;
        border-radius: 14px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.4);
        display: none;
        flex-direction: column;
        gap: 10px;
        z-index: 9999;
        border: 1px solid #333;
        animation: slideUp 0.3s ease-out;
    }

    /* Mobile View: Top Notification Banner Style */
    @media (max-width: 768px) {
        .pwa-floating-card {
            top: 15px;
            left: 15px;
            right: 15px;
            bottom: auto;
            width: auto;
            max-width: 100%;
            animation: slideDown 0.3s ease-out;
        }
    }

    .pwa-card-content {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .pwa-card-icon img {
        width: 36px;
        height: 36px;
        object-fit: contain;
        border-radius: 6px;
    }

    .pwa-card-text h4 {
        margin: 0 0 3px 0;
        font-size: 14px;
        color: #fff;
    }

    .pwa-card-text p {
        margin: 0;
        font-size: 11px;
        color: #aaa;
        line-height: 1.3;
    }

    .pwa-card-actions {
        display: flex;
        justify-content: flex-end;
        gap: 6px;
    }

    .btn-pwa-install {
        background-color: #39FF14;
        color: #000;
        border: none;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
        transition: 0.2s;
    }

    .btn-pwa-install:hover {
        opacity: 0.9;
        transform: scale(1.02);
    }

    .btn-pwa-close {
        background-color: transparent;
        color: #888;
        border: 1px solid #444;
        border-radius: 6px;
        padding: 6px 10px;
        font-size: 12px;
        cursor: pointer;
        transition: 0.2s;
    }

    .btn-pwa-close:hover {
        background-color: rgba(255, 255, 255, 0.05);
        color: #fff;
    }

    @keyframes slideUp {
        from {
            transform: translateY(20px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    @keyframes slideDown {
        from {
            transform: translateY(-20px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }
</style>
</head>
<body>

    <div class="main-container">
        <!-- Main CDM Logo -->
        <img src="assets/cdmlogo.png" alt="CDM Logo" class="cdmlogo-img">
        <h1>NoteSphere</h1>
        <br>
        <br>

        <div class="button-group">
            <a href="auth/login.php" class="btn btn-login">Login Access</a>
            <a href="auth/register.php" class="btn btn-register">Register</a>
            <br>
            
            <a class="info-link" onclick="showAbout()">System Guide for Students</a>
        </div>
    </div>

    <!-- Custom PWA Install Floating Card -->
    <div id="pwaInstallCard" class="pwa-floating-card">
        <div class="pwa-card-content">
            <!-- App Icon (logo.png) -->
            <div class="pwa-card-icon">
                <img src="assets/logo.png" alt="NoteSphere App Icon">
            </div>
            <div class="pwa-card-text">
                <h4>Install NoteSphere</h4>
                <p>Add to your home screen for quick and easy access!</p>
            </div>
        </div>
        <div class="pwa-card-actions">
            <button id="pwaInstallBtn" class="btn-pwa-install">Install</button>
            <button id="pwaCloseBtn" class="btn-pwa-close">✕</button>
        </div>
    </div>

    <!-- PWA Install Guide Modal (replaces browser alert) -->
    <div id="pwaGuideModal" class="modal">
        <div class="modal-content">
            <h2 style="color: #39FF14; margin-bottom: 15px; font-size: 1.2rem; text-align: center;">How to Install NoteSphere</h2>
            
            <p style="font-size: 0.9rem; color: #ccc; line-height: 1.5; margin-bottom: 15px;">
                Since direct auto-install is restricted by your browser settings (local environment / HTTP), follow these quick steps:
            </p>

            <div style="font-size: 0.85rem; color: #aaa; line-height: 1.6; display: flex; flex-direction: column; gap: 12px;">
                <div>
                    <strong style="color: #fff;">📱 For Mobile Phones (Android / iOS):</strong><br>
                    1. Tap your browser's menu icon (<strong style="color: #39FF14;">⋮</strong> three dots at the top right).<br>
                    2. Look for <strong style="color: #fff;">"Add to Home Screen"</strong> or <strong style="color: #fff;">"Install app"</strong>.<br>
                    3. Confirm to add NoteSphere to your home screen!
                </div>

                <div>
                    <strong style="color: #fff;">💻 For PC / Computer:</strong><br>
                    1. Click the install icon (<strong style="color: #39FF14;">🖥️ / ➕</strong>) located on the right side of your browser's address bar.<br>
                    2. Alternatively, click your browser menu and select <strong style="color: #fff;">"Install NoteSphere..."</strong>.
                </div>
            </div>

            <button class="close-btn" onclick="hidePwaGuide()">Got it!</button>
        </div>
    </div>

    <!-- About Modal -->
    <div id="aboutModal" class="modal">
        <div class="modal-content">
            <h2 style="color: #39FF14; margin-bottom: 15px; font-size: 1.2rem; text-align: center;">NoteSphere Student User Guide</h2>
            
            <p style="font-size: 0.9rem; color: #ccc; line-height: 1.5; margin-bottom: 12px;">
                
                Welcome to NoteSphere! This guide helps you easily navigate and use the platform for your studies at Colegio de Montalban.
            </p>

            <div style="font-size: 0.85rem; color: #aaa; line-height: 1.5; display: flex; flex-direction: column; gap: 10px;">
                <div>
                    <strong style="color: #fff;">1. Registration & Account Security</strong><br>
                    • <strong style="color: #ddd;">Sign Up:</strong> Enter your Student ID, Name, Email, Year Level, Course, Section, and Subject Codes.<br>
                    • <strong style="color: #ddd;">Review & OTP Verification:</strong> A review card will appear to check your details, followed by an email OTP verification.
                </div>

                <div>
                    <strong style="color: #fff;">2. Student Dashboard & Announcements</strong><br>
                    • <strong style="color: #ddd;">Welcome & Notifications:</strong> View greeting, professor cards, and announcement counts.<br>
                    • <strong style="color: #ddd;">Submissions & History:</strong> Submit requirements and track your grades (Pending or Graded).<br>
                    • <strong style="color: #ddd;">Consultation Chat:</strong> Message your professor directly for inquiries.
                </div>

                <div>
                    <strong style="color: #fff;">3. View Notes & Institutes</strong><br>
                    • <strong style="color: #ddd;">Search & Filters:</strong> Locate category-filtered notes easily.<br>
                    • <strong style="color: #ddd;">Uploading Notes:</strong> Upload or write notes subject to admin approval.<br>
                    • <strong style="color: #ddd;">Rating System:</strong> Rate other students' notes from 1 to 5 stars.
                </div>

                <div>
                    <strong style="color: #fff;">4. Help Board Q&A & Profile</strong><br>
                    • <strong style="color: #ddd;">Questions & Discussions:</strong> Post academic questions anonymously or with your role.<br>
                    • <strong style="color: #ddd;">Profile Settings:</strong> Update profile info anytime.
                </div>

                <div>
                    <strong style="color: #fff;">5. "More" Menu & Utilities</strong><br>
                    • <strong style="color: #ddd;">Archive SY & Enrollment Update:</strong> Access older announcements or request section updates.<br>
                    • <strong style="color: #ddd;">Theme & Logout:</strong> Toggle display modes or log out safely.
                </div>
            </div>

            <button class="close-btn" onclick="hideAbout()">Close</button>
        </div>
    </div>

    <script>
        function showAbout() { 
            const modal = document.getElementById('aboutModal');
            modal.classList.add('show');
        }
        function hideAbout() { 
            const modal = document.getElementById('aboutModal');
            modal.classList.remove('show');
        }

        function showPwaGuide() {
            const modal = document.getElementById('pwaGuideModal');
            modal.classList.add('show');
        }
        function hidePwaGuide() {
            const modal = document.getElementById('pwaGuideModal');
            modal.classList.remove('show');
            installCard.style.display = 'none';
        }

        window.onclick = function(event) {
            const aboutModal = document.getElementById('aboutModal');
            const pwaGuideModal = document.getElementById('pwaGuideModal');
            if (event.target == aboutModal) { hideAbout(); }
            if (event.target == pwaGuideModal) { hidePwaGuide(); }
        }

        // PWA Install Prompt Script with Mobile Force-Display Fallback
        let deferredPrompt;
        const installCard = document.getElementById('pwaInstallCard');
        const installBtn = document.getElementById('pwaInstallBtn');
        const closeBtn = document.getElementById('pwaCloseBtn');

        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            installCard.style.display = 'flex';
        });

        // Fallback: Force show the card on mobile/PC after 1.5 seconds even if the browser event is delayed
        setTimeout(() => {
            if (!sessionStorage.getItem('pwaClosed')) {
                installCard.style.display = 'flex';
            }
        }, 1500);

        installBtn.addEventListener('click', async () => {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                const { outcome } = await deferredPrompt.userChoice;
                if (outcome === 'accepted') {
                    console.log('User accepted the PWA install');
                }
                deferredPrompt = null;
                installCard.style.display = 'none';
            } else {
                // Show our sleek custom Instruction Modal Card instead of browser alert()
                showPwaGuide();
            }
        });

        closeBtn.addEventListener('click', () => {
            installCard.style.display = 'none';
            sessionStorage.setItem('pwaClosed', 'true');
        });
    </script>
</body>
</html>