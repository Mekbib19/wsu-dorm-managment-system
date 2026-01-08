<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

include __DIR__ . '/../db.php';

/* ───────────── OTP SUMMARY ───────────── */
$stats = $conn->query("
    SELECT
        COUNT(*) AS total,
        SUM(used = 1) AS used,
        SUM(used = 0 AND expires_at > NOW()) AS active,
        SUM(used = 0 AND expires_at <= NOW()) AS expired
    FROM registration_tokens
")->fetch_assoc();

/* ───────────── OTP PER DAY (7 DAYS) ───────────── */
$otpDays = $conn->query("
    SELECT DATE(created_at) AS day, COUNT(*) AS total
    FROM registration_tokens
    GROUP BY DATE(created_at)
    ORDER BY day DESC
    LIMIT 7
");

/* ───────────── STUDENTS PER BLOCK ───────────── */
$blockStats = $conn->query("
    SELECT b.block_number, COUNT(s.id) AS students
    FROM students s
    JOIN blocks b ON s.block_id = b.id
    GROUP BY b.block_number
    ORDER BY b.block_number
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Analytics</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="admin.css">
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="container">
        <h1>📊 Admin Analytics</h1>

        <!-- SUMMARY CARDS -->
        <div class="grid">
            <div class="card">
                <h3>Total OTPs</h3>
                <strong><?= $stats['total'] ?? 0 ?></strong>
            </div>
            <div class="card">
                <h3>Used OTPs</h3>
                <strong><?= $stats['used'] ?? 0 ?></strong>
            </div>
            <div class="card">
                <h3>Active OTPs</h3>
                <strong><?= $stats['active'] ?? 0 ?></strong>
            </div>
            <div class="card">
                <h3>Expired OTPs</h3>
                <strong><?= $stats['expired'] ?? 0 ?></strong>
            </div>
        </div>

        <!-- OTP TREND -->
        <div class="chart">
            <h2>OTP Generation (Last 7 Days)</h2>
            <br><br>
            <div class="bars">
                <?php while ($r = $otpDays->fetch_assoc()): ?>
                    <div class="bar">
                        <span><?= $r['total'] ?></span>
                        <div style="height:<?= $r['total'] * 12 ?>px"></div>
                        <small><?= $r['day'] ?></small>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>

        <!-- BLOCK TABLE -->
        <h2>Student Activation by Block</h2>
        <table>
            <tr>
                <th>Block</th>
                <th>Students</th>
            </tr>
            <?php while ($b = $blockStats->fetch_assoc()): ?>
            <tr>
                <td>Block <?= htmlspecialchars($b['block_number']) ?></td>
                <td><?= $b['students'] ?></td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>
</div>

</body>
</html>
