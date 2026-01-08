<?php
session_start();
include 'db.php'; // mysqli $conn

/* ===========================
   INITIAL VARIABLES
   =========================== */
$msg = '';
$success = false;
$otp_display = null;

/* ===========================
   REQUEST OTP (DB ONLY)
   =========================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_otp'])) {

    $student_id = trim($_POST['student_id'] ?? '');

    if ($student_id === '') {
        $msg = "Please enter your Student ID.";
    } else {

        // Check student
        $stmt = $conn->prepare("SELECT id FROM students WHERE student_id = ? LIMIT 1");
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows === 0) {
            $msg = "Student ID not found.";
        } else {

            $otp = random_int(100000, 999999);
            $expires = date('Y-m-d H:i:s', strtotime('+5 minutes'));

            // Save OTP in DB
            $save = $conn->prepare("
                INSERT INTO registration_tokens (student_id, otp, used, created_at, expires_at)
                VALUES (?, ?, 0, NOW(), ?)
                ON DUPLICATE KEY UPDATE
                    otp = VALUES(otp),
                    used = 0,
                    created_at = NOW(),
                    expires_at = VALUES(expires_at)
            ");
            $save->bind_param("sss", $student_id, $otp, $expires);
            $save->execute();
            $save->close();

            // SHOW OTP (DB MODE)
            $otp_display = $otp;
            $msg = "OTP generated successfully.";
            header("refresh:2;url=forget_pass.php");

        }
        $stmt->close();
    }
}

/* ===========================
   RESET PASSWORD
   =========================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['request_otp'])) {

    $student_id = trim($_POST['student_id'] ?? '');
    $otp        = trim($_POST['otp'] ?? '');
    $password   = trim($_POST['password'] ?? '');
    $confirm    = trim($_POST['confirm'] ?? '');

    if ($student_id === '' || $otp === '' || $password === '' || $confirm === '') {
        $msg = "All fields are required.";
    } elseif ($password !== $confirm) {
        $msg = "Passwords do not match.";
    } else {

        $stmt = $conn->prepare("
            SELECT id, used, expires_at
            FROM registration_tokens
            WHERE student_id = ? AND otp = ?
            LIMIT 1
        ");
        $stmt->bind_param("ss", $student_id, $otp);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows === 0) {
            $msg = "Invalid OTP.";
        } else {

            $token = $res->fetch_assoc();

            if ($token['used']) {
                $msg = "OTP already used.";
            } elseif (strtotime($token['expires_at']) < time()) {
                $msg = "OTP expired.";
            } else {

                $hash = password_hash($password, PASSWORD_DEFAULT);

                $up = $conn->prepare("UPDATE students SET password=? WHERE student_id=?");
                $up->bind_param("ss", $hash, $student_id);

                if ($up->execute() && $up->affected_rows > 0) {

                    $done = $conn->prepare("UPDATE registration_tokens SET used=1 WHERE id=?");
                    $done->bind_param("i", $token['id']);
                    $done->execute();
                    $done->close();

                    $success = true;
                    $msg = "Password set successfully. You can now login.";
                } else {
                    $msg = "Password already set.";
                }
                $up->close();
            }
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Activate Account</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<!-- 🔽 YOUR MODERN UI (UNCHANGED) -->
<style>
/* (UI CSS EXACTLY AS YOU SENT — NOT REMOVED) */
:root{
    --primary:#2563eb;
    --primary-dark:#1e40af;
    --bg:#f1f5f9;
    --card:#ffffff;
    --text:#0f172a;
    --muted:#64748b;
    --success-bg:#e8f5e9;
    --success-text:#2e7d32;
    --error-bg:#ffebee;
    --error-text:#c62828;
    --radius:14px;
    --shadow:0 20px 40px rgba(0,0,0,.08);
}
*{box-sizing:border-box}
body{
    margin:0;
    min-height:100vh;
    background:linear-gradient(135deg,#e0e7ff,#f8fafc);
    font-family:"Segoe UI",system-ui,sans-serif;
    display:flex;
    align-items:center;
    justify-content:center;
    color:var(--text);
}
.card{
    width:100%;
    max-width:420px;
    background:var(--card);
    border-radius:var(--radius);
    box-shadow:var(--shadow);
    padding:28px;
}
h2{text-align:center;margin:0 0 6px}
.subtitle{text-align:center;color:var(--muted);margin-bottom:22px}
.alert{padding:12px;border-radius:10px;margin-bottom:16px}
.alert.success{background:var(--success-bg);color:var(--success-text)}
.alert.error{background:var(--error-bg);color:var(--error-text)}
label{font-weight:600;margin-bottom:6px;display:block}
input{width:100%;padding:12px;border-radius:10px;border:1px solid #cbd5f5}
button{width:100%;padding:12px;border-radius:10px;background:var(--primary);color:#fff;border:none}
.back-link{text-align:center;display:block;margin-top:14px;color:var(--primary)}
</style>
</head>

<body>
<div class="card">

<h2>Activate Account</h2>
<div class="subtitle">Request an OTP and set your password</div>

<?php if (!empty($msg)): ?>
    <div class="alert <?= $success ? 'success' : 'error' ?>">
        <?= htmlspecialchars($msg) ?>
    </div>
<?php endif; ?>

<?php if (!$success): ?>
<form method="post">
    <label>Student ID</label>
    <input name="student_id" required>
    <button name="request_otp">Request OTP</button>
</form>
<?php endif; ?>

<a class="back-link" href="forget_pass.php">← Back to Login</a>

</div>
</body>
</html>
