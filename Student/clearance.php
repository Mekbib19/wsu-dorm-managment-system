<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit;
}
include __DIR__.'/../db.php';

$student_id = $_SESSION['student_id'];
if(!isset($_SESSION['student_id'])){
    header("Location:student_dashboard.php");
}

$stmt = $conn->prepare("
    SELECT s.student_id, s.first_name, s.last_name, s.phone, 
           b.block_number, s.room_number, s.created_at,s.key
    FROM students s
    LEFT JOIN blocks b ON s.block_id = b.id
    WHERE s.id = ?
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$me = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Clearance Form</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .print-area { border: 2px solid #000; padding: 30px; max-width: 700px; margin: auto; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>

<div class="print-area">
    <h2 style="text-align:center;">WSU Dorm Clearance Form</h2>
    <hr>

    <p><strong>Student ID:</strong> <?= htmlspecialchars($me['student_id'] ?? '—') ?></p>
    <p><strong>Full Name:</strong> <?= htmlspecialchars(($me['first_name'] ?? '') . ' ' . ($me['last_name'] ?? '')) ?></p>
    <p><strong>Block:</strong> <?= $me['block_number'] ? "Block {$me['block_number']}" : 'Not assigned' ?></p>
    <p><strong>Room Number:</strong> <?= $me['room_number'] ?: '—' ?></p>
    <p><strong>Phone:</strong> <?= htmlspecialchars($me['phone'] ?: '—') ?></p>
    <p><strong>Registration Date:</strong> <?= date('Y-m-d', strtotime($me['created_at'] ?? date('Y-m-d'))) ?></p>

    <br><br>
    <p style="text-align:center;">Items Checklist (to be filled by proctor/admin):</p>
    <table border="1" cellpadding="8" style="width:100%; border-collapse: collapse;">
        <tr><th>Item</th><th>Quantity/Condition</th></tr>
        <tr><td>Bag & Suitcase</td><td>____________________</td></tr>
        <tr><td>Shouse</td><td>____________________</td></tr>
        <tr><td>Tishert</td><td>____________________</td></tr>
        <tr><td>Trouser</td><td>____________________</td></tr>
        <tr><td>Room Condition</td><td>____________________</td></tr>
        <tr><td>Keys Returned</td><td><?= $me['key'] ? 'Yes' : 'No' ?></td></tr>
    </table>

    <br><br>
    <p style="text-align:right;">Date: <?= date('Y/m/d') ?></p>
</div>

<br class="no-print">
<button class="no-print" onclick="window.print()">Print Clearance Form</button>
<br class="no-print"><a href="student_dashboard.php">← Back to Dashboard</a>

</body>
</html>