<?php

/**
 * =========================================================
 * NoteSphere Storage Helper
 * =========================================================
 *
 * Student   = 1.50 GB
 * Professor = 20 GB
 * System    = 10 TB
 *
 * Counts:
 * - Notes
 * - Professor announcement attachments
 * - Student announcement submissions
 *
 * Link-only announcements DO NOT consume server storage.
 */

if (!defined('NOTESPHERE_STORAGE_LOADED')) {

    define('NOTESPHERE_STORAGE_LOADED', true);

    // =====================================================
    // STORAGE QUOTAS
    // =====================================================

    define(
        'NS_SYSTEM_STORAGE',
        10 * 1024 * 1024 * 1024 * 1024
    ); // 10 TB

    define(
        'NS_STUDENT_QUOTA',
        1.5 * 1024 * 1024 * 1024
    ); // 1.50 GB

    define(
        'NS_PROFESSOR_QUOTA',
        20 * 1024 * 1024 * 1024
    ); // 20 GB


    // =====================================================
    // FORMAT BYTES
    // =====================================================

    function ns_format_bytes($bytes)
    {
        $bytes = max(0, (float) $bytes);

        if ($bytes >= 1024 * 1024 * 1024 * 1024) {
            return number_format(
                $bytes / (1024 * 1024 * 1024 * 1024),
                2
            ) . ' TB';
        }

        if ($bytes >= 1024 * 1024 * 1024) {
            return number_format(
                $bytes / (1024 * 1024 * 1024),
                2
            ) . ' GB';
        }

        if ($bytes >= 1024 * 1024) {
            return number_format(
                $bytes / (1024 * 1024),
                2
            ) . ' MB';
        }

        if ($bytes >= 1024) {
            return number_format(
                $bytes / 1024,
                2
            ) . ' KB';
        }

        return number_format($bytes, 0) . ' B';
    }


    // =====================================================
    // CHECK IF VALUE IS A WEB LINK
    // =====================================================

    function ns_is_web_link($value)
    {
        if (empty($value)) {
            return false;
        }

        $value = trim((string) $value);

        return (bool) preg_match(
            '/^(https?:\/\/|www\.)/i',
            $value
        );
    }


    // =====================================================
    // FIND EXISTING COLUMN
    // =====================================================

    function ns_find_column(
        PDO $pdo,
        $table,
        $candidates
    ) {
        try {

            $stmt = $pdo->query(
                "SHOW COLUMNS FROM `" .
                str_replace('`', '', $table) .
                "`"
            );

            $columns = $stmt->fetchAll(
                PDO::FETCH_COLUMN
            );

            foreach ($candidates as $candidate) {

                if (
                    in_array(
                        $candidate,
                        $columns,
                        true
                    )
                ) {
                    return $candidate;
                }
            }

        } catch (Exception $e) {

            return null;
        }

        return null;
    }


    // =====================================================
    // RESOLVE FILE PATH
    // =====================================================

    function ns_resolve_file_path($storedPath)
    {
        if (empty($storedPath)) {
            return null;
        }

        $storedPath = trim((string) $storedPath);

        // -------------------------------------------------
        // LINKS DO NOT USE SERVER STORAGE
        // -------------------------------------------------

        if (ns_is_web_link($storedPath)) {
            return null;
        }

        // -------------------------------------------------
        // Normalize slashes
        // -------------------------------------------------

        $storedPath = str_replace(
            '\\',
            '/',
            $storedPath
        );

        // -------------------------------------------------
        // Remove quotes if accidentally saved
        // -------------------------------------------------

        $storedPath = trim(
            $storedPath,
            "\"'"
        );

        // -------------------------------------------------
        // Absolute Windows path
        //
        // Example:
        // C:/xampp/htdocs/NoteSphere/uploads/file.pdf
        // -------------------------------------------------

        if (
            preg_match(
                '/^[A-Za-z]:\//',
                $storedPath
            )
        ) {

            if (is_file($storedPath)) {
                return $storedPath;
            }

            return null;
        }

        // -------------------------------------------------
        // Remove ../
        // -------------------------------------------------

        while (
            strpos($storedPath, '../') === 0
        ) {
            $storedPath = substr(
                $storedPath,
                3
            );
        }

        // -------------------------------------------------
        // Remove ./
        // -------------------------------------------------

        while (
            strpos($storedPath, './') === 0
        ) {
            $storedPath = substr(
                $storedPath,
                2
            );
        }

        // -------------------------------------------------
        // Normalize common paths
        // -------------------------------------------------

        $storedPath = ltrim(
            $storedPath,
            '/'
        );

        // -------------------------------------------------
        // Project root
        // -------------------------------------------------

        $projectRoot = __DIR__;

        // -------------------------------------------------
        // Direct project-relative path
        //
        // Example:
        // uploads/file.pdf
        // -------------------------------------------------

        $rootFile = $projectRoot .
            '/' .
            $storedPath;

        if (is_file($rootFile)) {
            return $rootFile;
        }

        // -------------------------------------------------
        // uploads/file.pdf
        // -------------------------------------------------

        if (
            strpos(
                $storedPath,
                'uploads/'
            ) === 0
        ) {

            $uploadFile = $projectRoot .
                '/' .
                $storedPath;

            if (is_file($uploadFile)) {
                return $uploadFile;
            }
        }

        // -------------------------------------------------
        // If database contains:
        //
        // notes/file.pdf
        // professor/file.pdf
        // announcement/file.pdf
        //
        // try project root.
        // -------------------------------------------------

        $possibleFile = $projectRoot .
            '/' .
            $storedPath;

        if (is_file($possibleFile)) {
            return $possibleFile;
        }

        // -------------------------------------------------
        // Try basename inside uploads
        //
        // Useful if DB stores only:
        // filename.pdf
        // -------------------------------------------------

        $uploadFile = $projectRoot .
            '/uploads/' .
            basename($storedPath);

        if (is_file($uploadFile)) {
            return $uploadFile;
        }

        // -------------------------------------------------
        // Try common upload folders
        // -------------------------------------------------

        $possibleDirectories = [
            $projectRoot . '/uploads',
            $projectRoot . '/uploads/notes',
            $projectRoot . '/uploads/announcements',
            $projectRoot . '/uploads/announcement',
            $projectRoot . '/uploads/student',
            $projectRoot . '/uploads/professor',
            $projectRoot . '/uploads/files',
            $projectRoot . '/uploads/documents'
        ];

        $fileName = basename($storedPath);

        foreach ($possibleDirectories as $directory) {

            $candidate = $directory .
                '/' .
                $fileName;

            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }


    // =====================================================
    // GET FILE USAGE FROM A TABLE
    // =====================================================

    function ns_table_file_usage(
        PDO $pdo,
        $table,
        $userId,
        $ownerCandidates,
        $pathCandidates
    ) {

        $usage = 0;
        $seen = [];

        // -------------------------------------------------
        // CHECK TABLE
        // -------------------------------------------------

        try {

            $tableCheck = $pdo->query(
                "SHOW TABLES LIKE " .
                $pdo->quote($table)
            );

            if (
                !$tableCheck ||
                !$tableCheck->fetchColumn()
            ) {
                return 0;
            }

        } catch (Exception $e) {

            return 0;
        }


        // -------------------------------------------------
        // FIND OWNER COLUMN
        // -------------------------------------------------

        $ownerColumn = ns_find_column(
            $pdo,
            $table,
            $ownerCandidates
        );


        // -------------------------------------------------
        // FIND FILE COLUMN
        // -------------------------------------------------

        $pathColumn = ns_find_column(
            $pdo,
            $table,
            $pathCandidates
        );


        if (
            !$ownerColumn ||
            !$pathColumn
        ) {
            return 0;
        }


        // -------------------------------------------------
        // GET USER FILES
        // -------------------------------------------------

        try {

            $sql =
                "SELECT `$pathColumn`
                 FROM `$table`
                 WHERE `$ownerColumn` = ?";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                $userId
            ]);


            while (
                $row = $stmt->fetch(
                    PDO::FETCH_ASSOC
                )
            ) {

                $storedPath =
                    $row[$pathColumn] ?? '';

                if (
                    empty($storedPath)
                ) {
                    continue;
                }


                // -------------------------------------------------
                // LINK ONLY = NO STORAGE
                // -------------------------------------------------

                if (
                    ns_is_web_link(
                        $storedPath
                    )
                ) {
                    continue;
                }


                // -------------------------------------------------
                // FIND ACTUAL FILE
                // -------------------------------------------------

                $realPath =
                    ns_resolve_file_path(
                        $storedPath
                    );

                if (
                    !$realPath ||
                    !is_file($realPath)
                ) {
                    continue;
                }


                // -------------------------------------------------
                // GET REAL PATH
                // -------------------------------------------------

                $realPath =
                    realpath($realPath);

                if (!$realPath) {
                    continue;
                }


                // -------------------------------------------------
                // PREVENT DUPLICATE COUNT
                // -------------------------------------------------

                if (
                    isset(
                        $seen[$realPath]
                    )
                ) {
                    continue;
                }

                $seen[$realPath] = true;


                // -------------------------------------------------
                // GET ACTUAL FILE SIZE
                // -------------------------------------------------

                $fileSize =
                    @filesize($realPath);

                if (
                    $fileSize !== false
                ) {

                    $usage +=
                        (int) $fileSize;
                }
            }

        } catch (Exception $e) {

            return $usage;
        }

        return $usage;
    }


    // =====================================================
    // GET USER STORAGE
    // =====================================================

    function ns_get_user_storage(
        PDO $pdo,
        $userId,
        $role
    ) {

        $role = strtolower(
            trim((string) $role)
        );

        $usedBytes = 0;


        // =================================================
        // NOTES
        // =================================================

        if ($role === 'student') {

            $noteOwnerCandidates = [
                'student_id',
                'user_id',
                'uploader_id',
                'uploaded_by',
                'owner_id',
                'created_by'
            ];

        } else {

            $noteOwnerCandidates = [
                'professor_id',
                'user_id',
                'uploader_id',
                'uploaded_by',
                'owner_id',
                'created_by'
            ];
        }


        $noteFileCandidates = [
            'file_path',
            'filepath',
            'file_name',
            'filename',
            'file',
            'document_path',
            'note_file',
            'attachment',
            'attachment_path',
            'file_url'
        ];


        $usedBytes +=
            ns_table_file_usage(
                $pdo,
                'notes',
                $userId,
                $noteOwnerCandidates,
                $noteFileCandidates
            );


        // =================================================
        // PROFESSOR ANNOUNCEMENTS
        // =================================================

        if ($role === 'professor') {

            $announcementOwnerCandidates = [
                'professor_id',
                'user_id',
                'author_id',
                'created_by',
                'posted_by',
                'uploaded_by'
            ];

            $announcementFileCandidates = [
                'file_path',
                'filepath',
                'file_name',
                'filename',
                'file',
                'attachment',
                'attachment_path',
                'document_path',
                'announcement_file',
                'attachment_file'
            ];


            $usedBytes +=
                ns_table_file_usage(
                    $pdo,
                    'announcements',
                    $userId,
                    $announcementOwnerCandidates,
                    $announcementFileCandidates
                );
        }


        // =================================================
        // STUDENT ANNOUNCEMENT SUBMISSIONS
        // =================================================

        if ($role === 'student') {

            $usedBytes +=
                ns_table_file_usage(
                    $pdo,
                    'announcement_submissions',
                    $userId,
                    [
                        'student_id',
                        'user_id'
                    ],
                    [
                        'file_path',
                        'filepath',
                        'file_name',
                        'filename',
                        'file',
                        'attachment',
                        'attachment_path'
                    ]
                );
        }


        // =================================================
        // ADMIN
        // =================================================

        if ($role === 'admin') {

            return [
                'used_bytes' =>
                    $usedBytes,

                'quota_bytes' =>
                    null,

                'remaining_bytes' =>
                    null,

                'percentage' =>
                    0,

                'used_text' =>
                    ns_format_bytes(
                        $usedBytes
                    ),

                'quota_text' =>
                    'System Monitor',

                'remaining_text' =>
                    'No Personal Quota'
            ];
        }


        // =================================================
        // QUOTA
        // =================================================

        if ($role === 'student') {

            $quotaBytes =
                NS_STUDENT_QUOTA;

        } elseif ($role === 'professor') {

            $quotaBytes =
                NS_PROFESSOR_QUOTA;

        } else {

            $quotaBytes =
                NS_STUDENT_QUOTA;
        }


        // =================================================
        // REMAINING
        // =================================================

        $remainingBytes =
            max(
                0,
                $quotaBytes - $usedBytes
            );


        // =================================================
        // PERCENTAGE
        // =================================================

        $percentage =
            $quotaBytes > 0
                ? (
                    $usedBytes /
                    $quotaBytes
                ) * 100
                : 0;


        $percentage =
            min(
                100,
                max(
                    0,
                    $percentage
                )
            );


        // =================================================
        // RETURN
        // =================================================

        return [

            'used_bytes' =>
                $usedBytes,

            'quota_bytes' =>
                $quotaBytes,

            'remaining_bytes' =>
                $remainingBytes,

            'percentage' =>
                $percentage,

            'used_text' =>
                ns_format_bytes(
                    $usedBytes
                ),

            'quota_text' =>
                ns_format_bytes(
                    $quotaBytes
                ),

            'remaining_text' =>
                ns_format_bytes(
                    $remainingBytes
                )
        ];
    }


    // =====================================================
    // CHECK UPLOAD QUOTA
    // =====================================================

    function ns_can_upload(
        PDO $pdo,
        $userId,
        $role,
        $incomingBytes
    ) {

        $storage =
            ns_get_user_storage(
                $pdo,
                $userId,
                $role
            );


        // Admin has no personal quota
        if (
            $storage['quota_bytes'] === null
        ) {
            return true;
        }


        return
            (int) $incomingBytes <=
            (int) $storage['remaining_bytes'];
    }


    // =====================================================
    // SYSTEM STORAGE
    // =====================================================

    function ns_get_system_storage()
    {

        $uploadsDir =
            __DIR__ . '/uploads';


        if (
            !is_dir($uploadsDir)
        ) {

            return [

                'used_bytes' =>
                    0,

                'total_bytes' =>
                    NS_SYSTEM_STORAGE,

                'remaining_bytes' =>
                    NS_SYSTEM_STORAGE,

                'percentage' =>
                    0
            ];
        }


        $used = 0;


        try {

            $iterator =
                new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator(
                        $uploadsDir,
                        FilesystemIterator::SKIP_DOTS
                    )
                );


            foreach (
                $iterator as $file
            ) {

                if (
                    $file->isFile()
                ) {

                    $used +=
                        $file->getSize();
                }
            }

        } catch (Exception $e) {

            $used = 0;
        }


        $remaining =
            max(
                0,
                NS_SYSTEM_STORAGE - $used
            );


        $percentage =
            NS_SYSTEM_STORAGE > 0
                ? (
                    $used /
                    NS_SYSTEM_STORAGE
                ) * 100
                : 0;


       
        return [
            'used_bytes' => $used,
            'total_bytes' => NS_SYSTEM_STORAGE,
            'remaining_bytes' => $remaining,
            'percentage' => min(
                100,
                max(0, $percentage)
            )
        ];
    }
}


?>