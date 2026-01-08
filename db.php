<?php
// db.php
$host = 'localhost';
$db   = 'wsudorm';               // ← changed to match your dump
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

// Include session helpers
include_once __DIR__ . '/session_helpers.php';

// Ensure required schema exists for session timeout feature (best-effort)
try {
    $tables = ['students','proctors','admin'];
    foreach ($tables as $t) {
        $res = $conn->query("SHOW COLUMNS FROM `$t` LIKE 'last_activity'");
        if ($res && $res->num_rows == 0) {
            $conn->query("ALTER TABLE `$t` ADD COLUMN last_activity DATETIME DEFAULT NULL");
        }
    }

    $conn->query("CREATE TABLE IF NOT EXISTS session_logs (
      id INT AUTO_INCREMENT PRIMARY KEY,
      role ENUM('student','proctor','admin') NOT NULL,
      user_identifier VARCHAR(255) NOT NULL,
      session_id VARCHAR(255),
      action VARCHAR(50) NOT NULL,
      ip VARCHAR(45),
      user_agent VARCHAR(255),
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (Exception $e) {
    // ignore errors — the migration file can be applied manually if DB user lacks privileges
}

// Validate active PHP session against DB session_id for each role to ensure the session wasn't revoked
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!empty($_SESSION['role'])) {
    if ($_SESSION['role'] === 'student' && !empty($_SESSION['student_id'])) {
        $stmt = $conn->prepare("SELECT session_id, last_activity FROM students WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $_SESSION['student_id']);
            $stmt->execute();
            $r = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $db_sid = $r['session_id'] ?? null;
            $db_last_activity = $r['last_activity'] ?? null;
            $timeout_seconds = 30 * 60; // 30 minutes

            // If no session stored or mismatch, revoke
            if ($db_sid === null || $db_sid !== session_id()) {
                session_unset();
                session_destroy();
                header('Location: login.php?session_revoked=1');
                exit;
            }

            // If last_activity missing or expired, revoke and clear DB
            if (empty($db_last_activity) || strtotime($db_last_activity) < time() - $timeout_seconds) {
                $clear = $conn->prepare("UPDATE students SET session_id = NULL, last_activity = NULL WHERE id = ?");
                $clear->bind_param("i", $_SESSION['student_id']);
                $clear->execute();
                $clear->close();

                if (function_exists('log_session_event')) {
                    log_session_event($conn, 'student', (string)$_SESSION['student_id'], $db_sid, 'timeout');
                }

                session_unset();
                session_destroy();
                header('Location: login.php?session_revoked=1');
                exit;
            }

            // update last_activity to now
            $now = date('Y-m-d H:i:s');
            $up = $conn->prepare("UPDATE students SET last_activity = ? WHERE id = ?");
            $up->bind_param("si", $now, $_SESSION['student_id']);
            $up->execute();
            $up->close();
        }
    } elseif ($_SESSION['role'] === 'proctor' && !empty($_SESSION['proctor_id'])) {
        $stmt = $conn->prepare("SELECT session_id, last_activity FROM proctors WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $_SESSION['proctor_id']);
            $stmt->execute();
            $r = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $db_sid = $r['session_id'] ?? null;
            $db_last_activity = $r['last_activity'] ?? null;
            $timeout_seconds = 30 * 60; // 30 minutes

            if ($db_sid === null || $db_sid !== session_id()) {
                session_unset();
                session_destroy();
                header('Location: login.php?session_revoked=1');
                exit;
            }

            if (empty($db_last_activity) || strtotime($db_last_activity) < time() - $timeout_seconds) {
                $clear = $conn->prepare("UPDATE proctors SET session_id = NULL, last_activity = NULL WHERE id = ?");
                $clear->bind_param("i", $_SESSION['proctor_id']);
                $clear->execute();
                $clear->close();

                if (function_exists('log_session_event')) {
                    log_session_event($conn, 'proctor', (string)$_SESSION['proctor_id'], $db_sid, 'timeout');
                }

                session_unset();
                session_destroy();
                header('Location: login.php?session_revoked=1');
                exit;
            }

            $now = date('Y-m-d H:i:s');
            $up = $conn->prepare("UPDATE proctors SET last_activity = ? WHERE id = ?");
            $up->bind_param("si", $now, $_SESSION['proctor_id']);
            $up->execute();
            $up->close();
        }
    } elseif ($_SESSION['role'] === 'admin' && !empty($_SESSION['admin_username'])) {
        $stmt = $conn->prepare("SELECT session_id, last_activity FROM admin WHERE username = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $_SESSION['admin_username']);
            $stmt->execute();
            $r = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $db_sid = $r['session_id'] ?? null;
            $db_last_activity = $r['last_activity'] ?? null;
            $timeout_seconds = 30 * 60; // 30 minutes

            if ($db_sid === null || $db_sid !== session_id()) {
                session_unset();
                session_destroy();
                header('Location: login.php?session_revoked=1');
                exit;
            }

            if (empty($db_last_activity) || strtotime($db_last_activity) < time() - $timeout_seconds) {
                $clear = $conn->prepare("UPDATE admin SET session_id = NULL, last_activity = NULL WHERE username = ?");
                $clear->bind_param("s", $_SESSION['admin_username']);
                $clear->execute();
                $clear->close();

                if (function_exists('log_session_event')) {
                    log_session_event($conn, 'admin', $_SESSION['admin_username'], $db_sid, 'timeout');
                }

                session_unset();
                session_destroy();
                header('Location: login.php?session_revoked=1');
                exit;
            }

            $now = date('Y-m-d H:i:s');
            $up = $conn->prepare("UPDATE admin SET last_activity = ? WHERE username = ?");
            $up->bind_param("ss", $now, $_SESSION['admin_username']);
            $up->execute();
            $up->close();
        }
    }
}

?>