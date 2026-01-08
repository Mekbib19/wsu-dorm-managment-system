<?php
// session_helpers.php
function log_session_event($conn, $role, $user_identifier, $session_id, $action) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $stmt = $conn->prepare("INSERT INTO session_logs (role, user_identifier, session_id, action, ip, user_agent) VALUES (?,?,?,?,?,?)");
    if ($stmt) {
        $stmt->bind_param("ssssss", $role, $user_identifier, $session_id, $action, $ip, $ua);
        $stmt->execute();
        $stmt->close();
    }
}
