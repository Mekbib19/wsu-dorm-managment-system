<?php
session_start();
require_once 'db.php';

define('SESSION_TIMEOUT', 600); // 10 minutes

if (empty($_SESSION['role']) || empty($_SESSION['session_id'])) {
    header("Location: ../login.php?session_revoked=1");
    exit;
}

$tableMap = [
    'student' => ['table' => 'students', 'id' => 'id', 'key' => 'student_id'],
    'proctor' => ['table' => 'proctors', 'id' => 'id', 'key' => 'proctor_id'],
    'admin'   => ['table' => 'admin',   'id' => 'username', 'key' => 'admin_username']
];

$role = $_SESSION['role'];
$uid  = $_SESSION[$tableMap[$role]['key']];
$sid  = $_SESSION['session_id'];

$stmt = $conn->prepare("
    SELECT session_id, last_activity
    FROM {$tableMap[$role]['table']}
    WHERE {$tableMap[$role]['id']} = ?
");
$stmt->bind_param(is_int($uid) ? "i" : "s", $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (
    !$user ||
    $user['session_id'] !== $sid ||
    strtotime($user['last_activity']) < time() - SESSION_TIMEOUT
) {
    // Session expired or hijacked
    $clear = $conn->prepare("
        UPDATE {$tableMap[$role]['table']}
        SET session_id = NULL, last_activity = NULL
        WHERE {$tableMap[$role]['id']} = ?
    ");
    $clear->bind_param(is_int($uid) ? "i" : "s", $uid);
    $clear->execute();
    $clear->close();

    session_destroy();
    header("Location: ../login.php?session_revoked=1");
    exit;
}

// ✅ Update activity
$now = date('Y-m-d H:i:s');
$up = $conn->prepare("
    UPDATE {$tableMap[$role]['table']}
    SET last_activity = ?
    WHERE {$tableMap[$role]['id']} = ?
");
$up->bind_param(is_int($uid) ? "si" : "ss", $now, $uid);
$up->execute();
$up->close();
