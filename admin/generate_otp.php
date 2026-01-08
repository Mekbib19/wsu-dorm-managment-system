<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

include __DIR__ . '/../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['generate_otp'])) {
    header("Location: add_student.php");
    exit;
}

$student_id = strtoupper(trim($_POST['student_id'] ?? ''));
$block_id   = (int)($_POST['block_id'] ?? 0);

function myidrule($student_id) {
    // Format: UGR/#####/##
    return preg_match('/^UGR\/\d{5}\/\d{2}$/', $student_id);
}

/* ───── VALIDATION ───── */
if ($student_id === '' || !$block_id) {
    $_SESSION['otp_message'] = "Student ID and Block are required.";
    header("Location: add_student.php");
    exit;
}

if (!myidrule($student_id)) {
    $_SESSION['otp_message'] = "Invalid Student ID format. Use UGR/#####/##";
    header("Location: add_student.php");
    exit;
}

/* ───── CHECK STUDENT ───── */
$stmt = $conn->prepare("SELECT id FROM students WHERE student_id = ? LIMIT 1");
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student_exists = $result->num_rows > 0;
$stmt->close();

/* ───── GET BLOCK NUMBER ───── */
$stmt = $conn->prepare("SELECT block_number FROM blocks WHERE id = ?");
$stmt->bind_param("i", $block_id);
$stmt->execute();
$stmt->bind_result($block_number);
$stmt->fetch();
$stmt->close();

/* ───── CASE A: STUDENT EXISTS ───── */
if ($student_exists) {

    $stmt = $conn->prepare("UPDATE students SET block_id = ? WHERE student_id = ?");
    $stmt->bind_param("is", $block_id, $student_id);
    $stmt->execute();
    $stmt->close();

    $_SESSION['otp_message'] = "Student already registered. Block updated.";
    header("Location: add_student.php");
    exit;
}

/* ───── CASE B: NEW STUDENT ───── */
$otp = random_int(100000, 999999);
$expire_at = date('Y-m-d H:i:s', strtotime('+5 minutes'));

$stmt = $conn->prepare("
    INSERT INTO registration_tokens (student_id, otp, temp_block, created_at, expires_at)
    VALUES (?, ?, ?, NOW(), ?)
    ON DUPLICATE KEY UPDATE
        otp = VALUES(otp),
        temp_block = VALUES(temp_block),
        used = 0,
        created_at = NOW(),
        expires_at = VALUES(expires_at)
");
$stmt->bind_param("siis", $student_id, $otp, $block_id, $expire_at);
$stmt->execute();
$stmt->close();

/* ───── OTP SESSION DATA ───── */
$_SESSION['otp_data'] = [
    'student_id' => $student_id,
    'block'      => 'Block ' . $block_number,
    'otp'        => $otp,
    'date'       => date('d M Y H:i'),
    'expires_at' => $expire_at
];

$_SESSION['otp_message'] = "OTP generated successfully.";

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
setcookie('last_student_id', $student_id, time()+2592000, '/', '', $secure, true);
setcookie('last_block_id', (string)$block_id, time()+2592000, '/', '', $secure, true);

header("Location: add_student.php");
exit;
