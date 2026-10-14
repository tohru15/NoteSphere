<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../auth/login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>System Guide | NoteSphere</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#0f172a">
    <style>
        :root { 
            --neon: #39FF14; 
            --neon-glow: rgba(57, 255, 20, 0.15);
            --bg: #050505; 
            --card-bg: #0a0a0a; 
            --text: #f8fafc; 
            --border-color: #1a1a1a; 
            --sub-text: #888;
        }

        body.light-mode {
            --bg: #f4f6f9;
            --card-bg: #ffffff;
            --text: #000000;
            --border-color: #e2e8f0;
            --sub-text: #64748b;
            --neon: #2ecc71;
            --neon-glow: rgba(46, 204, 113, 0.1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        
        html, body {
            width: 100%;
            min-height: 100vh;
            overflow-x: hidden;
            background: linear-gradient(rgba(11, 11, 11, 0.85), rgba(11, 11, 11, 0.92)), url('../assets/registerbg.png') no-repeat center fixed;
            background-size: cover;
            color: var(--text);
        }

        body.light-mode {
            background: linear-gradient(rgba(244, 246, 249, 0.85), rgba(244, 246, 249, 0.92)), url('../assets/registerbg.png') no-repeat center fixed;
            background-size: cover;
        }

        .container-wrapper {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            padding: 30px 15px;
        }

        .card-guide { 
            background: var(--card-bg); 
            border: 1px solid var(--border-color); 
            border-radius: 20px; 
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.6);
        }

        .text-neon { 
            color: var(--neon) !important; 
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--sub-text);
            text-decoration: none;
            font-size: 0.9rem;
            margin-bottom: 20px;
            transition: 0.3s;
        }

        .btn-back:hover {
            color: var(--neon);
            transform: translateX(-3px);
        }

        .guide-section {
            margin-top: 30px;
        }

        .guide-section h5 {
            color: var(--neon);
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 10px;
        }

        .guide-section p {
            color: var(--sub-text);
            font-size: 0.95rem;
            line-height: 1.6;
        }

        hr {
            border-color: var(--border-color);
        }

        @media (max-width: 576px) {
            .container-wrapper {
                padding: 15px 10px;
            }
            .card-guide {
                padding: 20px;
                border-radius: 15px;
            }
        }
    </style>
</head>
<body>

    <div class="container-wrapper">
        <a href="student_home.php" class="btn-back" title="Back to Dashboard">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        </a>

        <div class="card-guide">
            
           <p style="font-size: 0.9rem; color: #ccc; line-height: 1.5; margin-bottom: 12px;">
                <strong style="color: #39FF14;">NoteSphere Student User Guide</strong><br>
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
    </div>

    <script>
        // Automatically check and apply theme on page load
        if (localStorage.getItem('notesphere_theme') === 'light') {
            document.body.classList.add('light-mode');
        }
    </script>
</body>
</html>