<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit;
}
include __DIR__.'/../db.php';

$student_id = $_SESSION['student_id'];
$msg = '';

// Fetch current student data
$stmt = $conn->prepare("SELECT first_name, last_name, phone, password FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$me = $stmt->get_result()->fetch_assoc();

if (!$me) {
    die("Student not found.");
}

function validName($text) {
    return preg_match("/^[A-Za-z]+$/", $text);
}
function phone($text) {
    return preg_match("/^(09\d{8}|\+2519\d{8})$/", $text);
}
if (isset($_POST['update'])) {
    $first_name       = trim($_POST['first_name']);
    $last_name        = trim($_POST['last_name']);
    $phone            = trim($_POST['phone']);
    $old_password     = trim($_POST['old_password'] ?? '');
    $new_password     = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    // Validation 1: Names required and alphabetic
    if (empty($first_name) || !validName($first_name) || empty($last_name) || !validName($last_name)) {
        $msg = "<div class='msg error'>Valid first and last names are required.</div>";
    }
    if( empty($last_name) || !phone($phone)){
      $msg = "<div class='msg error'>Valid Phone Number required.</div>";  
    }
    // Validation 2: Old password required if changing password
    elseif (!empty($new_password) && empty($old_password)) {
        $msg = "<div class='msg error'>Old password is required to change password.</div>";
    }
    // Validation 3: Old password check
    elseif (!empty($old_password) && !password_verify($old_password, $me['password'])) {
        $msg = "<div class='msg error'>Old password is incorrect.</div>";
    }
    // Validation 4: New password match
    elseif (!empty($new_password) && $new_password !== $confirm_password) {
        $msg = "<div class='msg error'>New passwords do not match.</div>";
    }
    else {
        // Prepare UPDATE query
        if (!empty($new_password)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $sql = "UPDATE students SET first_name = ?, last_name = ?, phone = ?, password = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssi", $first_name, $last_name, $phone, $hashed_password, $student_id);
        } else {
            $sql = "UPDATE students SET first_name = ?, last_name = ?, phone = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssi", $first_name, $last_name, $phone, $student_id);
        }

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $msg = "<div class='msg success'>Profile updated successfully!</div>";
                // Refresh student data
                $stmt->close();
                $stmt = $conn->prepare("SELECT first_name, last_name, phone, password FROM students WHERE id = ?");
                $stmt->bind_param("i", $student_id);
                $stmt->execute();
                $me = $stmt->get_result()->fetch_assoc();
            } else {
                $msg = "<div class='msg error'>No changes detected.</div>";
            }
        } else {
            $msg = "<div class='msg error'>Update failed: " . htmlspecialchars($conn->error) . "</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Profile</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 500px; margin: 20px auto; padding: 20px; }
        label { display: block; margin: 10px 0 5px; font-weight: bold; }
        input { width: 100%; padding: 10px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
        input:focus { border-color: #007bff; outline: none; }
        button { background: #007bff; color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        button:hover { background: #0056b3; }
        .msg { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>

<h2>Edit Your Profile</h2>

<?= $msg ?>

<form method="post">
    <label>First Name <span style="color:red">*</span></label>
    <input type="text" name="first_name" value="<?= htmlspecialchars($me['first_name'] ?? '') ?>" required maxlength="100">

    <label>Last Name <span style="color:red">*</span></label>
    <input type="text" name="last_name" value="<?= htmlspecialchars($me['last_name'] ?? '') ?>" required maxlength="100">

    <label>Phone Number</label>
    <input type="tel" name="phone" value="<?= htmlspecialchars($me['phone'] ?? '') ?>" maxlength="20" placeholder="09XXXXXXXX">

    <label>Old Password (required if changing password)</label>
    <input type="password" name="old_password" placeholder="Enter current password">

    <label>New Password (leave blank to keep current)</label>
    <input type="password" name="password" placeholder="Enter new password" minlength="6">

    <label>Confirm New Password</label>
    <input type="password" name="confirm_password" placeholder="Confirm new password" minlength="6">

    <br><br>
    <button type="submit" name="update">Update Profile</button>
</form>

<br>
<a href="student_dashboard.php" style="color: #007bff; text-decoration: none;">← Back to Dashboard</a> | 
<a href="../logout.php" style="color: #dc3545; text-decoration: none;">Logout</a>

</body>
</html>
