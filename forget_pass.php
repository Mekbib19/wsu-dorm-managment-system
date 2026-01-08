<?php
session_start();
include 'db.php'; // must define $conn (mysqli)

// Ensure registration_tokens table exists (avoids missing-table errors)
function ensure_registration_tokens_table($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS registration_tokens (
      id INT AUTO_INCREMENT PRIMARY KEY,
      student_id VARCHAR(255) NOT NULL,
      otp VARCHAR(50) NOT NULL,
      temp_block INT DEFAULT NULL,
      used TINYINT(1) DEFAULT 0,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      expires_at DATETIME DEFAULT NULL,
      UNIQUE KEY uq_student (student_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    return $conn->query($sql) !== false;
}

$msg = '';
$success = false;
$otp_display = null; // used for local/dev display of OTP when email not available

// Handle OTP request separately (PRG recommended, but simple handling here)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['request_otp'])) {
    $student_id = trim($_POST['student_id'] ?? '');
    if ($student_id === '') {
        $msg = "Please enter your Student ID to request an OTP.";
    } else {
        // Verify student exists and get contact (if any)
        $stmt = $conn->prepare("SELECT id, phone, email FROM students WHERE student_id = ? LIMIT 1");
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows === 0) {
            $msg = "Student ID not found.";
        } else {
            $user = $res->fetch_assoc();
            // generate OTP
            $otp = random_int(100000, 999999);
            $expires_at = date('Y-m-d H:i:s', strtotime('+5 minutes'));

            // Ensure registration_tokens table
            ensure_registration_tokens_table($conn);

            $ins = $conn->prepare("INSERT INTO registration_tokens (student_id, otp, temp_block, created_at, expires_at) VALUES (?, ?, NULL, NOW(), ?) ON DUPLICATE KEY UPDATE otp = VALUES(otp), used = 0, created_at = NOW(), expires_at = VALUES(expires_at)");
            if ($ins) {
                $ins->bind_param("sss", $student_id, (string)$otp, $expires_at);
                $ins->execute();
                $ins->close();

                // Try to send via email if email column exists
                $sent_via = null;
                if (!empty($user['email'])) {
                    $to = $user['email'];
                    $subject = "WSU Dorm - Your OTP";
                    $message = "Your OTP is: $otp. It expires in 5 minutes.";
                    $headers = "From: no-reply@localhost";
                    if (@mail($to, $subject, $message, $headers)) {
                        $sent_via = 'email';
                    }
                }

                if ($sent_via === 'email') {
                    $msg = "An OTP has been sent to your email address. Please check your inbox.";
                } else {
                    // For local/testing environments (or if no email), show OTP on screen
                    $msg = "OTP generated — for testing this server displays it below. In production this should be sent via email/SMS.";
                    $otp_display = (string)$otp;
                }
            } else {
                $msg = "Failed to create OTP. Please try again later.";
            }
        }
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST['request_otp'])) {

    $student_id = trim($_POST['student_id'] ?? '');
    $otp        = trim($_POST['otp'] ?? '');
    $password   = trim($_POST['password'] ?? '');
    $confirm    = trim($_POST['confirm'] ?? '');

    // Basic validation
    if (empty($student_id) || empty($otp) || empty($password) || empty($confirm)) {
        $msg = "All required fields must be filled.";
    } elseif ($password !== $confirm) {
        $msg = "Passwords must match.";
    } else {

        // 1️⃣ Check OTP
        $stmt = $conn->prepare("
            SELECT id, used, expires_at
            FROM registration_tokens
            WHERE student_id = ? AND otp = ?
            LIMIT 1
        ");
        $stmt->bind_param("ss", $student_id, $otp);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $msg = "Invalid Student ID or OTP.";
        } else {

            $token = $result->fetch_assoc();

            if ((int)$token['used'] === 1) {
                $msg = "OTP has already been used.";
            } elseif (strtotime($token['expires_at']) < time()) {
                $msg = "OTP has expired.";
            } else {

                // 2️⃣ Update student password
                $hashed = password_hash($password, PASSWORD_DEFAULT);

                $stmt2 = $conn->prepare("
                    UPDATE students 
                    SET password = ?
                    WHERE student_id = ?
                ");
                $stmt2->bind_param("ss", $hashed, $student_id);

                if ($stmt2->execute() && $stmt2->affected_rows > 0) {

                    // 3️⃣ Mark OTP as used
                    $stmt3 = $conn->prepare("
                        UPDATE registration_tokens 
                        SET used = 1 
                        WHERE id = ?
                    ");
                    $stmt3->bind_param("i", $token['id']);
                    $stmt3->execute();
                    $stmt3->close();

                    $success = true;
                    $msg = "Password updated successfully! You can now login.";
                    header("refresh:2;url=login.php");
                } else {
                    $msg = "Student ID not found or password already set.";
                }

                $stmt2->close();
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

<style>
:root{
    --primary:#2563eb;
    --primary-dark:#1e40af;
    --bg:#eef2ff;
    --card:#ffffff;
    --text:#0f172a;
    --muted:#64748b;
    --success-bg:#e8f5e9;
    --success-text:#2e7d32;
    --error-bg:#ffebee;
    --error-text:#c62828;
    --radius:14px;
    --shadow:0 18px 40px rgba(0,0,0,.12);
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
}

.card{
    width:100%;
    max-width:420px;
    background:var(--card);
    border-radius:var(--radius);
    box-shadow:var(--shadow);
    padding:32px;
}

h2{
    margin:0 0 8px;
    text-align:center;
    color:var(--text);
}

.subtitle{
    text-align:center;
    color:var(--muted);
    font-size:.95rem;
    margin-bottom:22px;
}

.alert{
    padding:12px 14px;
    border-radius:10px;
    margin-bottom:16px;
    font-size:.95rem;
}

.alert.success{
    background:var(--success-bg);
    color:var(--success-text);
}

.alert.error{
    background:var(--error-bg);
    color:var(--error-text);
}

input{
    width:100%;
    padding:12px 14px;
    border-radius:10px;
    border:1px solid #cbd5f5;
    font-size:.95rem;
    outline:none;
    margin-bottom:12px;
    transition:.2s;
}

input:focus{
    border-color:var(--primary);
    box-shadow:0 0 0 3px rgba(37,99,235,.15);
}

button{
    width:100%;
    padding:13px;
    border:none;
    border-radius:10px;
    background:var(--primary);
    color:#fff;
    font-size:1rem;
    font-weight:600;
    cursor:pointer;
    transition:.2s;
}

button:hover{
    background:var(--primary-dark);
}

.otp-box{
    background:#f1f5f9;
    padding:12px;
    border-radius:10px;
    margin-bottom:16px;
    text-align:center;
    font-weight:600;
    color:#334155;
}

.back-link{
    display:block;
    text-align:center;
    margin-top:16px;
    text-decoration:none;
    color:var(--primary);
    font-weight:600;
}

.back-link:hover{
    text-decoration:underline;
}
</style>
</head>

<body>

<div class="card">

    <h2>Activate Account</h2>
    <div class="subtitle">
        Verify your identity and set a new password
    </div>

    <?php if (!empty($msg)): ?>
        <div class="alert <?= $success ? 'success' : 'error' ?>">
            <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>

    <?php if (!$success): ?>

        <?php if ($otp_display): ?>
            <div class="otp-box">
                OTP (testing): <?= htmlspecialchars($otp_display) ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <input type="text"
                   name="student_id"
                   placeholder="Student ID"
                   required
                   value="<?= htmlspecialchars($_POST['student_id'] ?? '') ?>">

            <input type="number"
                   name="otp"
                   placeholder="One-Time Password"
                   required>

            <input type="password"
                   name="password"
                   placeholder="New Password"
                   required>

            <input type="password"
                   name="confirm"
                   placeholder="Confirm Password"
                   required>

            <button type="submit">
                Activate Account
            </button>
        </form>

    <?php endif; ?>

    <a class="back-link" href="login.php">
        ← Back to Login
    </a>

</div>

</body>
</html>
