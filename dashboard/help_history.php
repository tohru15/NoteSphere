
<?php

session_start();

require_once '../includes/db_config.php';

/** @var PDO $conn */

// =========================================================
// PROFESSOR LOGIN SECURITY
// =========================================================

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'professor'
) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];


// =========================================================
// GET CURRENT PROFESSOR
// =========================================================

$user_stmt = $conn->prepare("
    SELECT
        full_name,
        email,
        profile_pic
    FROM users
    WHERE user_id = ?
    LIMIT 1
");

$user_stmt->execute([$user_id]);

$current_user = $user_stmt->fetch(PDO::FETCH_ASSOC);

$fullname = $current_user['full_name'] ?? 'Professor';
$email = $current_user['email'] ?? '';


// =========================================================
// PROFILE PICTURE
// =========================================================

function get_profile_picture($pic, $email)
{
    if (!empty($pic)) {

        if (strpos($pic, 'http') === 0) {
            return $pic;
        }

        $clean_path = str_replace('../', '', $pic);

        return '../' . ltrim($clean_path, '/');
    }

    return 'https://api.dicebear.com/7.x/avataaars/svg?seed='
        . urlencode($email);
}

$profile_pic = get_profile_picture(
    $current_user['profile_pic'] ?? '',
    $email
);

$_SESSION['profile_pic'] = $profile_pic;


// =========================================================
// DELETE POST
// =========================================================

if (isset($_GET['delete_id'])) {

    $delete_id = (int) $_GET['delete_id'];

    $del_stmt = $conn->prepare("
        DELETE FROM help_questions
        WHERE id = ?
        AND student_id = ?
    ");

    $del_stmt->execute([
        $delete_id,
        $user_id
    ]);

    header("Location: help_history.php");
    exit();
}


// =========================================================
// UPDATE POST
// help_questions uses `details`
// =========================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['update_post'])
) {

    $post_id = (int) ($_POST['post_id'] ?? 0);

    $title = trim($_POST['title'] ?? '');

    $details = trim($_POST['details'] ?? '');

    if (
        !empty($title) &&
        !empty($details)
    ) {

        $update_stmt = $conn->prepare("
            UPDATE help_questions
            SET
                title = ?,
                details = ?
            WHERE id = ?
            AND student_id = ?
        ");

        $update_stmt->execute([
            $title,
            $details,
            $post_id,
            $user_id
        ]);

        header("Location: help_history.php");
        exit();
    }
}


// =========================================================
// GET MY QUESTIONS
// =========================================================

$stmt = $conn->prepare("
    SELECT
        q.*,

        (
            SELECT COUNT(*)
            FROM help_comments c
            WHERE c.question_id = q.id
        ) AS comment_count

    FROM help_questions q

    WHERE q.student_id = ?

    ORDER BY q.created_at DESC
");

$stmt->execute([$user_id]);

$my_questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>My Post History - NoteSphere</title>


    <!-- GOOGLE FONT -->

    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >


    <!-- SWEETALERT -->

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


    <style>

        /* =====================================================
           THEME
        ===================================================== */

        :root {

            --neon: #39FF14;

            --neon-glow:
                rgba(57, 255, 20, 0.15);

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

            --neon-glow:
                rgba(16, 185, 129, 0.1);

            --hover-bg: #f1f5f9;

        }


        /* =====================================================
           RESET
        ===================================================== */

        * {

            margin: 0;

            padding: 0;

            box-sizing: border-box;

            font-family:
                'Plus Jakarta Sans',
                sans-serif;

        }


        /* =====================================================
           FIXED BACKGROUND
        ===================================================== */

        .page-bg {

            position: fixed;

            top: 0;

            left: 0;

            width: 100vw;

            height: 100vh;

            z-index: -999;

            background:

                linear-gradient(
                    rgba(11, 11, 11, 0.85),
                    rgba(11, 11, 11, 0.92)
                ),

                url('../assets/registerbg.png')
                no-repeat
                center
                center;

            background-size: cover;

            background-attachment: fixed;

            pointer-events: none;

        }


        body.light-mode .page-bg {

            background:

                url('../assets/registerbg.png')
                no-repeat
                center
                center;

            background-size: cover;

            background-attachment: fixed;

        }


        /* =====================================================
           BODY
        ===================================================== */

        body {

            position: relative;

            color: var(--text);

            display: flex;

            flex-direction: column;

            min-height: 100vh;

            padding-bottom: 40px;

            transition:
                color 0.3s ease;

        }


        /* =====================================================
           HEADER
        ===================================================== */

        .history-header {

            width: 100%;

            max-width: 680px;

            margin: 25px auto 10px auto;

            padding: 0 15px;

            display: flex;

            align-items: center;

            justify-content: space-between;

        }


        .history-title-wrap {

            display: flex;

            align-items: center;

            gap: 10px;

        }


        .history-title-wrap h2 {

            font-size: 1.25rem;

            font-weight: 700;

            color: var(--text);

        }


        /* =====================================================
           CLOSE BUTTON
        ===================================================== */

        .close-btn {

            width: 38px;

            height: 38px;

            border-radius: 50%;

            background: var(--card-bg);

            border:
                1px solid var(--border-color);

            display: flex;

            align-items: center;

            justify-content: center;

            color: var(--sub-text);

            text-decoration: none;

            font-size: 1.2rem;

            transition: all 0.2s ease;

            box-shadow:
                0 2px 10px rgba(0, 0, 0, 0.1);

        }


        .close-btn:hover {

            background: var(--hover-bg);

            color: var(--danger-red);

            border-color: var(--danger-red);

        }


        /* =====================================================
           CONTAINER
        ===================================================== */

        .fb-container {

            width: 100%;

            max-width: 680px;

            margin: 0 auto;

            padding: 0 15px;

        }


        /* =====================================================
           POST CARD
        ===================================================== */

        .post-card {

            background: var(--card-bg);

            border-radius: 16px;

            box-shadow:
                0 8px 30px rgba(0, 0, 0, 0.12);

            border:
                1px solid var(--border-color);

            margin-bottom: 20px;

            padding: 20px;

            position: relative;

            transition:
                background-color 0.3s ease,
                border-color 0.3s ease;

        }


        .post-header {

            display: flex;

            align-items: center;

            justify-content: space-between;

            margin-bottom: 12px;

        }


        .author-info {

            display: flex;

            align-items: center;

            gap: 12px;

        }


        .post-header img {

            width: 46px;

            height: 46px;

            border-radius: 50%;

            object-fit: cover;

            border:
                2px solid var(--neon);

            background: var(--border-color);

        }


        .author-name {

            font-weight: 700;

            font-size: 0.95rem;

            color: var(--text);

        }


        .post-time {

            font-size: 0.75rem;

            color: var(--sub-text);

            margin-top: 2px;

        }


        .post-title {

            font-weight: 700;

            font-size: 1.15rem;

            margin-bottom: 10px;

            color: var(--text);

        }


        .post-body {

            font-size: 0.95rem;

            line-height: 1.5;

            margin-bottom: 16px;

            word-break: break-word;

            color: var(--text);

        }


        /* =====================================================
           ANONYMOUS BADGE
        ===================================================== */

        .badge-anonymous {

            display: inline-block;

            background: var(--hover-bg);

            color: var(--sub-text);

            font-size: 0.7rem;

            padding: 2px 6px;

            border-radius: 6px;

            margin-top: 4px;

            font-weight: 600;

            border:
                1px solid var(--border-color);

            text-transform: uppercase;

        }


        /* =====================================================
           MENU
        ===================================================== */

        .menu-container {

            position: relative;

        }


        .dots-btn {

            background: none;

            border: none;

            font-size: 1.2rem;

            cursor: pointer;

            color: var(--sub-text);

            padding: 6px 10px;

            border-radius: 50%;

            transition:
                background 0.2s,
                color 0.2s;

        }


        .dots-btn:hover {

            background: var(--hover-bg);

            color: var(--text);

        }


        .dropdown-menu {

            display: none;

            position: absolute;

            right: 0;

            top: 35px;

            background: var(--card-bg);

            border:
                1px solid var(--border-color);

            box-shadow:
                0 10px 25px rgba(0, 0, 0, 0.2);

            border-radius: 12px;

            z-index: 10;

            width: 150px;

            overflow: hidden;

        }


        .dropdown-menu button {

            display: block;

            width: 100%;

            padding: 10px 14px;

            text-align: left;

            background: none;

            border: none;

            color: var(--text);

            font-size: 0.85rem;

            font-weight: 600;

            cursor: pointer;

            transition:
                background 0.2s;

        }


        .dropdown-menu button:hover {

            background: var(--hover-bg);

            color: var(--neon);

        }


        .text-danger {

            color:
                var(--danger-red) !important;

        }


        .text-danger:hover {

            color:
                var(--danger-red) !important;

            background:
                rgba(255, 77, 77, 0.1) !important;

        }


        /* =====================================================
           FOOTER
        ===================================================== */

        .post-footer-actions {

            display: flex;

            align-items: center;

            gap: 15px;

            border-top:
                1px solid var(--border-color);

            padding-top: 12px;

            margin-top: 12px;

        }


        .action-btn {

            background: none;

            border: none;

            color: var(--sub-text);

            font-size: 0.9rem;

            cursor: pointer;

            display: flex;

            align-items: center;

            gap: 6px;

            text-decoration: none;

            font-weight: 600;

            transition:
                color 0.2s;

        }


        .action-btn:hover {

            color: var(--neon);

        }

    </style>

</head>


<body>


    <!-- =====================================================
         BACKGROUND
    ====================================================== -->

    <div class="page-bg"></div>


    <!-- =====================================================
         HEADER
    ====================================================== -->

    <div class="history-header">

        <div class="history-title-wrap">

            <h2>
                📜 My Posted Questions History
            </h2>

        </div>


        <a
            href="help_board.php"
            class="close-btn"
            title="Close & Return"
        >

            &#10005;

        </a>

    </div>


    <!-- =====================================================
         POSTS
    ====================================================== -->

    <div class="fb-container">

        <div class="feed-list">


            <?php if (!empty($my_questions)): ?>


                <?php foreach ($my_questions as $q): ?>


                    <div
                        class="post-card"
                        id="post-<?php echo (int) $q['id']; ?>"
                    >


                        <!-- POST HEADER -->

                        <div class="post-header">

                            <div class="author-info">

                                <img
                                    src="<?php echo htmlspecialchars($profile_pic); ?>"
                                    alt="User"
                                >


                                <div>

                                    <div class="author-name">

                                        <?php
                                        echo htmlspecialchars(
                                            $fullname
                                        );
                                        ?>


                                        <?php if (
                                            !empty($q['is_anonymous']) &&
                                            (int) $q['is_anonymous'] === 1
                                        ): ?>

                                            <span class="badge-anonymous">
                                                Posted Anonymously
                                            </span>

                                        <?php endif; ?>

                                    </div>


                                    <div class="post-time">

                                        <?php

                                        echo date(
                                            'M d, Y • h:i A',
                                            strtotime(
                                                $q['created_at']
                                            )
                                        );

                                        ?>

                                    </div>

                                </div>

                            </div>


                            <!-- POST MENU -->

                            <div class="menu-container">

                                <button
                                    type="button"
                                    class="dots-btn"
                                    onclick="toggleMenu(event, this)"
                                >
                                    &#8942;
                                </button>


                                <div class="dropdown-menu">


                                    <!-- EDIT -->

                                    <button
                                        type="button"
                                        onclick='confirmEdit(
                                            <?php echo (int) $q['id']; ?>,
                                            <?php
                                            echo json_encode(
                                                $q['title'] ?? '',
                                                JSON_HEX_TAG |
                                                JSON_HEX_APOS |
                                                JSON_HEX_QUOT |
                                                JSON_HEX_AMP
                                            );
                                            ?>,
                                            <?php
                                            echo json_encode(
                                                $q['details'] ?? '',
                                                JSON_HEX_TAG |
                                                JSON_HEX_APOS |
                                                JSON_HEX_QUOT |
                                                JSON_HEX_AMP
                                            );
                                            ?>
                                        )'
                                    >
                                        Edit Post
                                    </button>


                                    <!-- DELETE -->

                                    <button
                                        type="button"
                                        class="text-danger"
                                        onclick="confirmDelete(
                                            <?php echo (int) $q['id']; ?>
                                        )"
                                    >
                                        Delete Post
                                    </button>

                                </div>

                            </div>

                        </div>


                        <!-- TITLE -->

                        <div class="post-title">

                            <?php
                            echo htmlspecialchars(
                                $q['title'] ?? ''
                            );
                            ?>

                        </div>


                        <!-- DETAILS -->

                        <div class="post-body">

                            <?php

                            echo nl2br(
                                htmlspecialchars(
                                    $q['details'] ?? ''
                                )
                            );

                            ?>

                        </div>


                        <!-- COMMENTS -->

                        <div class="post-footer-actions">

                            <a
                                href="view_post.php?id=<?php echo (int) $q['id']; ?>"
                                class="action-btn"
                            >

                                <svg
                                    width="18"
                                    height="18"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    viewBox="0 0 24 24"
                                >

                                    <path
                                        d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"
                                    ></path>

                                </svg>


                                <span>

                                    <?php
                                    echo (int) $q['comment_count'];
                                    ?>

                                </span>

                                Comments

                            </a>

                        </div>


                    </div>


                <?php endforeach; ?>


            <?php else: ?>


                <!-- EMPTY STATE -->

                <div
                    style="
                        text-align: center;
                        color: var(--sub-text);
                        padding: 50px;
                        background: var(--card-bg);
                        border-radius: 16px;
                        border: 1px solid var(--border-color);
                    "
                >

                    <p
                        style="
                            font-size: 1.1rem;
                            margin-bottom: 8px;
                        "
                    >
                        You haven't posted any questions yet.
                    </p>


                    <a
                        href="help_board.php"
                        style="
                            color: var(--neon);
                            font-weight: bold;
                            text-decoration: none;
                        "
                    >
                        Go to Help Board to create a post
                    </a>

                </div>


            <?php endif; ?>


        </div>

    </div>


    <script>


        // =====================================================
        // THEME
        // =====================================================

        function applyTheme(theme) {

            if (theme === 'light') {

                document.body.classList.add(
                    'light-mode'
                );

            } else {

                document.body.classList.remove(
                    'light-mode'
                );

            }

        }


        document.addEventListener(
            'DOMContentLoaded',
            function () {

                const savedTheme =
                    localStorage.getItem(
                        'notesphere_theme'
                    ) ||
                    localStorage.getItem(
                        'theme'
                    ) ||
                    'dark';


                applyTheme(savedTheme);

            }
        );


        // =====================================================
        // LIVE THEME UPDATE
        // =====================================================

        window.addEventListener(
            'storage',
            function (event) {

                if (
                    event.key ===
                        'notesphere_theme' ||
                    event.key === 'theme'
                ) {

                    if (event.newValue) {

                        applyTheme(
                            event.newValue
                        );

                    }

                }

            }
        );


        // =====================================================
        // DROPDOWN MENU
        // =====================================================

        function toggleMenu(event, btn) {

            event.stopPropagation();


            document
                .querySelectorAll('.dropdown-menu')
                .forEach(function (menu) {

                    if (
                        menu !==
                        btn.nextElementSibling
                    ) {

                        menu.style.display =
                            'none';

                    }

                });


            const menu =
                btn.nextElementSibling;


            menu.style.display =
                menu.style.display === 'block'
                    ? 'none'
                    : 'block';

        }


        window.addEventListener(
            'click',
            function () {

                document
                    .querySelectorAll(
                        '.dropdown-menu'
                    )
                    .forEach(function (menu) {

                        menu.style.display =
                            'none';

                    });

            }
        );


        // =====================================================
        // DELETE
        // =====================================================

        function confirmDelete(postId) {

            Swal.fire({

                title: 'Delete this post?',

                text:
                    "You won't be able to revert this once deleted.",

                icon: 'warning',

                showCancelButton: true,

                confirmButtonColor:
                    '#ff4d4d',

                cancelButtonColor:
                    '#65676b',

                confirmButtonText:
                    'Yes, delete it',

                cancelButtonText:
                    'Cancel'

            }).then(function (result) {

                if (result.isConfirmed) {

                    window.location.href =
                        'help_history.php?delete_id=' +
                        encodeURIComponent(
                            postId
                        );

                }

            });

        }


        // =====================================================
        // EDIT
        // =====================================================

        function confirmEdit(
            postId,
            currentTitle,
            currentDetails
        ) {

            Swal.fire({

                title: 'Edit Post',

                html: `

                    <input
                        id="swal-input1"
                        class="swal2-input"
                        placeholder="Title"
                        value="${escapeHtml(currentTitle)}"
                        style="
                            width: 90%;
                            font-size: 0.95rem;
                        "
                    >

                    <textarea
                        id="swal-input2"
                        class="swal2-textarea"
                        placeholder="Question details..."
                        style="
                            width: 90%;
                            font-size: 0.95rem;
                            height: 100px;
                        "
                    >${escapeHtml(currentDetails)}</textarea>

                `,

                focusConfirm: false,

                showCancelButton: true,

                confirmButtonText:
                    'Save Changes',

                cancelButtonText:
                    'Cancel',

                confirmButtonColor:
                    '#39FF14',

                preConfirm: function () {

                    const title =
                        document
                            .getElementById(
                                'swal-input1'
                            )
                            .value
                            .trim();


                    const details =
                        document
                            .getElementById(
                                'swal-input2'
                            )
                            .value
                            .trim();


                    if (
                        !title ||
                        !details
                    ) {

                        Swal.showValidationMessage(
                            'Both title and details cannot be empty!'
                        );

                        return false;

                    }


                    return {
                        title: title,
                        details: details
                    };

                }

            }).then(function (result) {

                if (result.isConfirmed) {

                    const form =
                        document.createElement(
                            'form'
                        );

                    form.method = 'POST';

                    form.action =
                        'help_history.php';


                    // POST ID

                    const inputId =
                        document.createElement(
                            'input'
                        );

                    inputId.type = 'hidden';

                    inputId.name = 'post_id';

                    inputId.value = postId;


                    // TITLE

                    const inputTitle =
                        document.createElement(
                            'input'
                        );

                    inputTitle.type = 'hidden';

                    inputTitle.name = 'title';

                    inputTitle.value =
                        result.value.title;


                    // DETAILS

                    const inputDetails =
                        document.createElement(
                            'input'
                        );

                    inputDetails.type = 'hidden';

                    inputDetails.name =
                        'details';

                    inputDetails.value =
                        result.value.details;


                    // SUBMIT

                    const inputSubmit =
                        document.createElement(
                            'input'
                        );

                    inputSubmit.type = 'hidden';

                    inputSubmit.name =
                        'update_post';

                    inputSubmit.value = '1';


                    form.appendChild(
                        inputId
                    );

                    form.appendChild(
                        inputTitle
                    );

                    form.appendChild(
                        inputDetails
                    );

                    form.appendChild(
                        inputSubmit
                    );


                    document.body.appendChild(
                        form
                    );


                    form.submit();

                }

            });

        }


        // =====================================================
        // ESCAPE HTML
        // =====================================================

        function escapeHtml(value) {

            return String(value)

                .replace(
                    /&/g,
                    '&amp;'
                )

                .replace(
                    /</g,
                    '&lt;'
                )

                .replace(
                    />/g,
                    '&gt;'
                )

                .replace(
                    /"/g,
                    '&quot;'
                )

                .replace(
                    /'/g,
                    '&#039;'
                );

        }

    </script>

</body>

</html>

