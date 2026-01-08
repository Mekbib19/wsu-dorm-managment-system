<?php
session_start();
include 'db.php';

// Ensure remember_tokens table exists
function ensure_remember_tokens_table($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS remember_tokens (
      id INT AUTO_INCREMENT PRIMARY KEY,
      role ENUM('student','proctor','admin') NOT NULL,
      identifier VARCHAR(255) NOT NULL,
      token_hash CHAR(64) NOT NULL,
      expires_at DATETIME NOT NULL,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_token_hash (token_hash),
      INDEX idx_identifier_role (identifier, role)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $conn->query($sql);
}

// ==================== AUTO-LOGIN via Remember Me ====================
if (empty($_SESSION['role']) && !empty($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    $token_hash = hash('sha256', $token);

    $stmt = $conn->prepare("SELECT role, identifier, expires_at FROM remember_tokens WHERE token_hash = ?");
    $stmt->bind_param("s", $token_hash);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        if (strtotime($row['expires_at']) >= time()) {
            $role = $row['role'];
            $identifier = $row['identifier'];

            if ($role === 'student') {
                $stmt2 = $conn->prepare("SELECT id FROM students WHERE id = ?");
                $stmt2->bind_param("s", $identifier); // note: id is likely integer but stored as string in token
                $stmt2->execute();
                if ($u = $stmt2->get_result()->fetch_assoc()) {
                    session_regenerate_id(true);
                    $_SESSION['role'] = 'student';
                    $_SESSION['student_id'] = $u['id'];
                    $sid = session_id();
                    $_SESSION['session_id'] = $sid;
                    $now = date('Y-m-d H:i:s');
                    $up = $conn->prepare("UPDATE students SET session_id = ?, last_activity = ? WHERE id = ?");
                    $up->bind_param("ssi", $sid, $now, $u['id']);
                    $up->execute();
                    if (function_exists('log_session_event')) {
                        log_session_event($conn, 'student', (string)$u['id'], $sid, 'auto_login');
                    }
                    header("Location: student/student_dashboard.php");
                    exit;
                }
            }
            // ... same pattern for proctor and admin (copy-paste & adjust) ...
        } else {
            // expired → clean up
            $conn->prepare("DELETE FROM remember_tokens WHERE token_hash = ?")->bind_param("s", $token_hash)->execute();
            setcookie('remember_token', '', time() - 3600, '/');
        }
    }
    $stmt->close();
}

// ==================== NORMAL LOGIN HANDLING ====================
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = "Please enter username and password.";
    } else {
        $logged_in = false;

        // ──────────────── STUDENT ────────────────
        $stmt = $conn->prepare("SELECT id, password, session_id, last_activity 
                                FROM students WHERE student_id = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user['password'])) {
            $allow_login = true;

            if (!empty($user['session_id'])) {
                $minutes_ago = (time() - strtotime($user['last_activity'])) / 60;
                if ($minutes_ago > 15) {  // ← 15 minutes grace period
                    $allow_login = false;
                    $error = "This account is already logged in on another device.";
                } else {
                    // Grace period → allow re-login and clean old marker
                    $clean = $conn->prepare("UPDATE students SET session_id = NULL, last_activity = NULL WHERE id = ?");
                    $clean->bind_param("i", $user['id']);
                    $clean->execute();
                    $clean->close();
                }
            }

            if ($allow_login) {
                session_regenerate_id(true);
                $_SESSION['role'] = 'student';
                $_SESSION['student_id'] = $user['id'];
                $sid = session_id();
                $_SESSION['session_id'] = $sid;
                $now = date('Y-m-d H:i:s');

                $up = $conn->prepare("UPDATE students SET session_id = ?, last_activity = ? WHERE id = ?");
                $up->bind_param("ssi", $sid, $now, $user['id']);
                $up->execute();
                $up->close();

                if (function_exists('log_session_event')) {
                    log_session_event($conn, 'student', (string)$user['id'], $sid, 'login');
                }

                // Remember me
                if (!empty($_POST['remember_me'])) {
                    $token = bin2hex(random_bytes(32));
                    $token_hash = hash('sha256', $token);
                    $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));
                    ensure_remember_tokens_table($conn);

                    $ins = $conn->prepare("INSERT INTO remember_tokens (role, identifier, token_hash, expires_at) 
                                           VALUES ('student', ?, ?, ?)");
                    $ins->bind_param("sss", $user['id'], $token_hash, $expires_at);
                    $ins->execute();
                    $ins->close();

                    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
                    setcookie('remember_token', $token, [
                        'expires'  => time() + 30*24*3600,
                        'path'     => '/',
                        'secure'   => $secure,
                        'httponly' => true,
                        'samesite' => 'Lax'
                    ]);
                }

                header("Location: student/student_dashboard.php");
                exit;
            }
        }

        // ──────────────── PROCTOR & ADMIN ────────────────
        // Add similar blocks here (copy-paste & adjust table/fields/redirects)
        // Use the same $minutes_ago grace period logic

        if (!$logged_in && empty($error)) {
            $error = "Invalid username or password.";
        }
    }
}

// If already logged in → redirect to logout (prevents double login confusion)
if (!empty($_SESSION['role'])) {
    header("Location: logout.php");
    exit;
}
?>

<!-- Your HTML form goes here -->
<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
</head>
<body>
    <?php if ($error): ?>
        <p style="color:red;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <form method="post">
        <label>Username / Student ID:<br>
            <input type="text" name="username" required>
        </label><br><br>

        <label>Password:<br>
            <input type="password" name="password" required>
        </label><br><br>

        <label>
            <input type="checkbox" name="remember_me" value="1"> Remember me (30 days)
        </label><br><br>

        <button type="submit">Login</button>
    </form>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WSU Dorm Login</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 420px;
            margin: 60px auto;
            padding: 20px;
            background: #f8f9fa;
        }
        h2 { text-align: center; color: #333; }
        .error { color: #dc3545; text-align: center; font-weight: bold; }
        form { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        label { display: block; margin: 15px 0 5px; font-weight: bold; color: #444; }
        input { 
            width: 100%; 
            padding: 12px; 
            box-sizing: border-box; 
            border: 1px solid #ccc; 
            border-radius: 4px; 
            font-size: 16px;
        }
        input:focus { border-color: #007bff; outline: none; }
        .button-group { margin-top: 25px; text-align: center; }
        button {
            background: #007bff;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
        }
        button:hover { background: #0056b3; }
        .register-link { margin-left: 20px; color: #007bff; text-decoration: none; font-size: 14px; }
        .register-link:hover { text-decoration: underline; }
        .info { margin-top: 20px; font-size: 0.9em; color: #666; text-align: center; }
        .remember-row{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-top:12px;
    gap:10px;
}

.remember-box{
    display:flex;
    align-items:center;
    cursor:pointer;
    user-select:none;
}

.remember-box input{
    display:none;
}

.checkmark{
    width:18px;
    height:18px;
    border:2px solid #007bff;
    border-radius:4px;
    margin-right:10px;
    position:relative;
}

.remember-box input:checked + .checkmark{
    background:#007bff;
}

.remember-box input:checked + .checkmark::after{
    content:"";
    position:absolute;
    left:5px;
    top:2px;
    width:4px;
    height:8px;
    border:solid #fff;
    border-width:0 2px 2px 0;
    transform:rotate(45deg);
}

.remember-text{
    font-size:14px;
    color:#333;
}

.remember-text small{
    display:block;
    font-size:12px;
    color:#777;
}

.forgot-link{
    font-size:14px;
    color:#007bff;
    text-decoration:none;
}

.forgot-link:hover{
    text-decoration:underline;
}

    </style>
</head>
<body>
    
    
    
    <?php if ($error || $error_html): ?>
        <p class="error"><?= $error_html ?: htmlspecialchars($error) ?></p>
        <?php endif; ?>
        
        <?php if (!$blocked_login): ?>
            <form method="post">
    <img src="wsulogo.jpg" alt="wsu logo" style="display:block; margin:auto; width:120px; height:auto; border-radius:50%;">
    <p align="center">WSU Dorm Management System</p>
    <?php if(isset($_GET['logged_out']) && $_GET['logged_out'] == 1): ?>
        <p align="center" style="color:green;">You have been logged out successfully.</p>
    <?php endif; ?>
    <input type="text" name="username" required autofocus placeholder="Username or Id" class="fa fa-user"><br><br>
    <input type="password" name="password" required class="fa fa-lock" placeholder="Password">
   <div class="remember-row">
    <label class="remember-box">
        <input type="checkbox" name="remember_me">
        <span class="checkmark"></span>
        <span class="remember-text">
            Remember me
            <small>Keep me signed in for 30 days</small>
        </span>
    </label>

    <a href="otp.php" class="forgot-link">Forgot password?</a>
</div>


    <div class="button-group">
        <a href="register.php" class="register-link" align="left">I don't have an account</a>&nbsp;&nbsp;
        <button type="submit">Login</button><br>
    </div>
</form>
<?php else: ?>
    <div style="text-align:center; margin:20px;">
        <a href="<?= htmlspecialchars(
            isset($dashboard_link) ? $dashboard_link : 'login.php'
        ) ?>">Go to your dashboard</a> or <a href="logout.php">Logout</a>
    </div>
<?php endif; ?>

<div class="info">
</div>

</body>
</html>