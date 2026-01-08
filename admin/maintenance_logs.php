<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

include __DIR__ . '/../db.php';

// ────────────────────────────────────────────────
// Quick stats
// ────────────────────────────────────────────────
$blocks_count     = $conn->query("SELECT COUNT(*) FROM blocks")->fetch_row()[0] ?? 0;
$students_count   = $conn->query("SELECT COUNT(*) FROM students")->fetch_row()[0] ?? 0;
$pending_reports  = $conn->query("SELECT COUNT(*) FROM maintenance_reports WHERE status = 'Pending'")->fetch_row()[0] ?? 0;
$proctors_count   = $conn->query("SELECT COUNT(*) FROM proctors")->fetch_row()[0] ?? 0;
$stats = $conn->query("
    SELECT
        COUNT(*) AS total,
        SUM(used = 1) AS used,
        SUM(used = 0 AND expires_at > NOW()) AS active,
        SUM(used = 0 AND expires_at <= NOW()) AS expired
    FROM registration_tokens
")->fetch_assoc();


// Recent reports (last 5)
$stmt = $conn->prepare("
    SELECT r.id, r.type, r.status, r.created_at,
           s.student_id, s.first_name, s.last_name
    FROM maintenance_reports r
    LEFT JOIN students s ON r.student_id = s.id
    ORDER BY r.created_at DESC
    LIMIT 5
");
$stmt->execute();
$recent_reports = $stmt->get_result();

// Announcements
$stmt = $conn->prepare("SELECT message, created_at FROM announcements ORDER BY created_at DESC");
$stmt->execute();
$announcements = $stmt->get_result();

// Blocks for OTP dropdown
$stmt = $conn->prepare("SELECT id, block_number FROM blocks ORDER BY block_number");
$stmt->execute();
$blocks_result = $stmt->get_result();

// Fetch all blocks for dropdown
$blocks = $conn->query("SELECT id, block_number FROM blocks ORDER BY block_number");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - WSU Dorm</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="admin.css">
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="container" align="center">
    <div class="page-header">
        <h1>Admin Dashboard</h1>


    <div class="section">
        <h3>Recent Maintenance Reports </h3>
        <?php if ($recent_reports->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Student</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($row = $recent_reports->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['id'] ?></td>
                        <td>
                            <?php 
                            if ($row['student_id']) {
                                echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name'] . ' (' . $row['student_id'] . ')');
                            } else {
                                echo '—';
                            }
                            ?>
                        </td>
                        <td><?= htmlspecialchars($row['type']) ?></td>
                        <td><?= htmlspecialchars($row['status']) ?></td>
                        <td><?= date('d M Y • H:i', strtotime($row['created_at'])) ?></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="text-align:center; color:var(--muted);">No recent reports.</p>
        <?php endif; ?>
    </div>
 
    </div>

</body>
</html>