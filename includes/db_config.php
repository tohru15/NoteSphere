<?php
/**
 * NoteSphere - Database Configuration
 */

// =========================================================
// SESSION
// =========================================================

if (session_status() === PHP_SESSION_NONE) {

    // Session lifetime: 7 days
    ini_set(
        'session.gc_maxlifetime',
        604800
    );

    session_set_cookie_params(
        604800
    );

    session_start();
}


// =========================================================
// DATABASE
// =========================================================

$host = 'localhost';
$db   = '1notesphere_db';
$user = 'root';
$pass = '';

try {

    $conn = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user,
        $pass
    );

    // Throw exception kapag may database/query error
    $conn->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

    // Return associative arrays by default
    $conn->setAttribute(
        PDO::ATTR_DEFAULT_FETCH_MODE,
        PDO::FETCH_ASSOC
    );

} catch (PDOException $e) {

    die(
        "Database connection failed: " .
        htmlspecialchars(
            $e->getMessage(),
            ENT_QUOTES,
            'UTF-8'
        )
    );
}