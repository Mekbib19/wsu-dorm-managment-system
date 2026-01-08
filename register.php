<?php
session_start();
include 'db.php';   // your db.php with $conn

$msg = '';
$success = false;

function validName($text) {
    return preg_match("/^[A-Za-z]+$/", $text);
}
function validPhone($text) {
    return preg_match("/^(09\d{8}|\+2519\d{8})$/", $text);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = trim($_POST['student_id'] ?? '');
    $otp        = trim($_POST['otp'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $password   = trim($_POST['password'] ?? '');
    $confirm    = trim($_POST['confirm'] ?? '');

    if (empty($student_id) || empty($otp) || empty($first_name) || empty($last_name) || empty($password) || empty($confirm)) {
        $msg = "<div class='message error'>All required fields must be filled.</div>";
    } elseif (!validName($first_name) || !validName($last_name)) {
        $msg = "<div class='message error'>Valid first and last names are required.</div>";
    } elseif (!empty($phone) && !validPhone($phone)) {
        $msg = "<div class='message error'>Valid phone number required.</div>";
    } elseif ($password !== $confirm) {
        $msg = "<div class='message error'>Passwords must match.</div>";
    } else {
        $stmt = $conn->prepare("
            SELECT id, temp_block, used, expires_at
            FROM registration_tokens
            WHERE student_id = ? AND otp = ?
            LIMIT 1
        ");
        $stmt->bind_param("ss", $student_id, $otp);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $msg = "<div class='message error'>Invalid Student ID or OTP.</div>";
        } else {
            $row = $result->fetch_assoc();

            if ((int)$row['used'] === 1) {
                $msg = "<div class='message error'>OTP has already been used.</div>";
            } elseif (strtotime($row['expires_at']) < time()) {
                $msg = "<div class='message error'>OTP has expired.</div>";
            } else {
                $block_id = $row['temp_block'];
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                $stmt2 = $conn->prepare("
                    INSERT INTO students (student_id, first_name, last_name, phone, password, block_id)
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                        first_name = VALUES(first_name),
                        last_name  = VALUES(last_name),
                        phone      = VALUES(phone),
                        password   = VALUES(password),
                        block_id   = VALUES(block_id)
                ");
                $stmt2->bind_param("sssssi", $student_id, $first_name, $last_name, $phone, $hashed_password, $block_id);

                if ($stmt2->execute()) {
                    $stmt3 = $conn->prepare("UPDATE registration_tokens SET used = 1 WHERE id = ?");
                    $stmt3->bind_param("i", $row['id']);
                    $stmt3->execute();
                    $stmt3->close();

                    $msg = "<div class='message success'>Account activated successfully! You can now <a href='login.php'>login</a>.</div>";
                    $success = true;
                } else {
                    $msg = "<div class='message error'>Database error: " . htmlspecialchars($conn->error) . "</div>";
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
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Registration / Activation</title>
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
<style>
    /* Reset and body */
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Roboto', sans-serif; background: #f0f2f5; display: flex; align-items: center; justify-content: center; min-height: 100vh; }

    /* Card container */
    .card {
        background: #fff;
        padding: 40px 30px;
        border-radius: 12px;
        box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        width: 100%;
        max-width: 450px;
        animation: fadeIn 0.7s ease;
    }

    @keyframes fadeIn { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }

    h2 { text-align: center; margin-bottom: 25px; color: #333; font-weight: 700; }
    p { text-align: center; margin-bottom: 30px; color: #666; font-size: 0.95rem; }

    .form-group { margin-bottom: 20px; }
    label { display: block; font-weight: 500; margin-bottom: 6px; color: #555; }
    input { width: 100%; padding: 12px 15px; border: 1px solid #ccc; border-radius: 8px; font-size: 15px; transition: 0.3s; }
    input:focus { border-color: #4a90e2; outline: none; box-shadow: 0 0 5px rgba(74,144,226,0.3); }

    button {
        width: 100%;
        padding: 14px;
        background: #4a90e2;
        color: #fff;
        font-size: 16px;
        font-weight: 500;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        transition: 0.3s;
    }
    button:hover { background: #357ab8; }

    .message {
        padding: 14px;
        margin-bottom: 20px;
        border-radius: 8px;
        text-align: center;
        font-weight: 500;
        font-size: 0.95rem;
    }
    .success { background: #e6f4ea; color: #2e7d32; border: 1px solid #a5d6a7; }
    .error   { background: #fdecea; color: #c62828; border: 1px solid #f5a2a2; }

    .bottom-text { text-align: center; margin-top: 25px; font-size: 0.9rem; }
    .bottom-text a { color: #4a90e2; text-decoration: none; font-weight: 500; }
    .bottom-text a:hover { text-decoration: underline; }
</style>
</head>
<body>

<div class="card">
    <h2>Activate Your Dorm Account</h2>
    <p>Enter your Student ID and the OTP provided by the Admin.</p>

    <?php if ($msg): ?>
        <div class="message <?= $success ? 'success' : 'error' ?>">
            <?= $msg ?>
        </div>
    <?php endif; ?>

    <?php if (!$success): ?>
    <form method="post">
        <div class="form-group">
            <label>Student ID <span style="color:red">*</span></label>
            <input type="text" name="student_id" placeholder="e.g. UGR/919/001" value="<?= htmlspecialchars($_POST['student_id'] ?? '') ?>" required>
        </div>

        <div class="form-group">
            <label>OTP <span style="color:red">*</span></label>
            <input type="text" name="otp" pattern="\d{4,10}" placeholder="Enter OTP" value="<?= htmlspecialchars($_POST['otp'] ?? '') ?>" required>
        </div>

        <div class="form-group">
            <label>First Name <span style="color:red">*</span></label>
            <input type="text" name="first_name" value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" required>
        </div>

        <div class="form-group">
            <label>Last Name <span style="color:red">*</span></label>
            <input type="text" name="last_name" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" required>
        </div>

        <div class="form-group">
            <label>Phone Number</label>
            <input type="tel" name="phone" placeholder="09XXXXXXXX" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label>Choose Password <span style="color:red">*</span></label>
            <input type="password" name="password" required>
        </div>

        <div class="form-group">
            <label>Confirm Password <span style="color:red">*</span></label>
            <input type="password" name="confirm" required>
        </div>

        <button type="submit">Activate Account</button>
    </form>
    <?php endif; ?>

    <div class="bottom-text">
        Already activated? <a href="login.php">Login here</a>
    </div>
</div>

</body>
</html>
