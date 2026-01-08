<?php
session_start();
include 'db.php';

if (isset($_SESSION['role'])) {
    $cur_sid = session_id();

    if ($_SESSION['role'] === 'student') {
        $stmt = $conn->prepare("UPDATE students SET session_id = NULL, last_activity = NULL WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['student_id']);
        $user_identifier = (string)$_SESSION['student_id'];
    } elseif ($_SESSION['role'] === 'proctor') {
        $stmt = $conn->prepare("UPDATE proctors SET session_id = NULL, last_activity = NULL WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['proctor_id']);
        $user_identifier = (string)$_SESSION['proctor_id'];
    } else {
        $stmt = $conn->prepare("UPDATE admin SET session_id = NULL, last_activity = NULL WHERE username = ?");
        $stmt->bind_param("s", $_SESSION['admin_username']);
        $user_identifier = $_SESSION['admin_username'];
    }

    if ($stmt) {
        $stmt->execute();
        $stmt->close();
    }

    if (function_exists('log_session_event')) {
        log_session_event($conn, $_SESSION['role'], $user_identifier ?? '', $cur_sid, 'logout');
    }

    // Remove remember token if present for this browser
    if (!empty($_COOKIE['remember_token'])) {
        $token_hash = hash('sha256', $_COOKIE['remember_token']);
        $del = $conn->prepare("DELETE FROM remember_tokens WHERE token_hash = ?");
        $del->bind_param("s", $token_hash); $del->execute(); $del->close();
        setcookie('remember_token', '', time()-3600, '/');
    }
}

session_destroy();
header("Location: login.php?logged_out=1");
exit;
?>
