<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit;
}

include __DIR__ . '/../db.php';

$student_id = $_SESSION['student_id'] ?? 0;
$msg = '';

// Fetch student's block and room (once, at the top)
$stmt = $conn->prepare("SELECT block_id, room_number FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student_data = $result->fetch_assoc() ?: ['block_id' => null, 'room_number' => null];
$stmt->close();
if($student_data['room_number'] == null){
   header("Location: student_dashboard.php");
   exit;
}
$block_id = $student_data['block_id'];
$room_number = $student_data['room_number'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_report'])) {
    $type        = trim($_POST['type'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($type) || empty($description)) {
        $msg = "<div style='color:red; padding:10px; background:#fee; border:1px solid #f00; border-radius:6px; margin-bottom:15px;'>Please fill in both type and description.</div>";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO maintenance_reports 
            (student_id, type, description, block_id, room_number, status) 
            VALUES (?, ?, ?, ?, ?, 'Pending')
        ");
        $stmt->bind_param("isssi", $student_id, $type, $description, $block_id, $room_number);

        if ($stmt->execute()) {
            $msg = "<div style='color:#155724; padding:10px; background:#d4edda; border:1px solid #c3e6cb; border-radius:6px; margin-bottom:15px;'>Report submitted successfully! We will review it soon.</div>";
        } else {
            $msg = "<div style='color:#721c24; padding:10px; background:#f8d7da; border:1px solid #f5c6cb; border-radius:6px; margin-bottom:15px;'>Error: " . htmlspecialchars($stmt->error) . "</div>";
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
    <title>Report Maintenance Issue</title>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f8f9fa;
            margin: 0;
            padding: 30px;
            color: #333;
            line-height: 1.6;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        h2 {
            color: #2c3e50;
            text-align: center;
            margin-bottom: 24px;
        }
        label {
            display: block;
            margin: 16px 0 8px;
            font-weight: 600;
            color: #444;
        }
        select, textarea, input {
            width: 100%;
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 1rem;
        }
        textarea {
            resize: vertical;
            min-height: 120px;
        }
        button {
            margin-top: 20px;
            padding: 14px 28px;
            background: #10b981;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            cursor: pointer;
            transition: background 0.2s;
        }
        button:hover {
            background: #059669;
        }
        .back-links {
            margin-top: 30px;
            text-align: center;
        }
        .back-links a {
            color: #6366f1;
            text-decoration: none;
            margin: 0 16px;
            font-weight: 500;
        }
        .back-links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Report a Maintenance Problem</h2>

    <?= $msg ?>

    <form method="post">
        <label for="type">Problem Type <span style="color:red;">*</span></label>
        <select name="type" id="type" required>
            <option value="">-- Please select --</option>
            <option value="Electrical / Socket">Electrical / Socket</option>
            <option value="Water / Plumbing">Water / Plumbing</option>
            <option value="Door / Lock">Door / Lock</option>
            <option value="Window">Window</option>
            <option value="Furniture">Furniture</option>
            <option value="Other">Other</option>
        </select>

        <label for="description">Description <span style="color:red;">*</span></label>
        <textarea name="description" id="description" rows="6" required 
                  placeholder="Please describe the issue in detail..."></textarea>

        <button type="submit" name="submit_report">Submit Report</button>
    </form>

    <div class="back-links">
        <a href="student_dashboard.php">← Back to Dashboard</a> | 
        <a href="../logout.php" style="color:#ef4444;">Logout</a>
    </div>
</div>

</body>
</html>