<?php
session_start();
require_once "db.php";
require_once "../admin/audit_trail.php"; // ✅ include this

if (isset($_SESSION['user'])) {
    $user_id = $_SESSION['user']['id'];

    // Log the logout action
    recordAudit($pdo, $user_id, AUDIT_LOGOUT, 'User logged out.');
}

// Destroy session
$_SESSION = [];
session_destroy();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_start();
session_unset();
session_destroy();
header("Location: login.php");
exit;

