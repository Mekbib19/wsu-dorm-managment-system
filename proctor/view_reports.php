<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'proctor') {
    header("Location: ../login.php");
    exit;
}

include __DIR__ . '/../db.php';

// Get proctor's assigned block
$proctor_block_id = null;
if (isset($_SESSION['proctor_id'])) {
    $stmt = $conn->prepare("SELECT block_id FROM proctors WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['proctor_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $proctor_block_id = $row['block_id'];
    }
    $stmt->close();
}

// Build WHERE clause safely
$where = "";
$types = "";
$params = [];

if ($proctor_block_id !== null) {
    $where = " WHERE r.block_id = ?";
    $types = "i";
    $params[] = $proctor_block_id;
}

// Fetch reports
$sql = "
    SELECT r.id, r.type, r.description, r.status, r.created_at,
           s.student_id, s.first_name, s.last_name,
           b.block_number, r.room_number
    FROM maintenance_reports r
    LEFT JOIN students s ON r.student_id = s.id
    LEFT JOIN blocks b ON r.block_id = b.id
    $where
    ORDER BY r.created_at DESC
";

$stmt = $conn->prepare($sql);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Reports - Proctor</title>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f8f9fa;
            margin: 0;
            padding: 30px;
            color: #333;
        }
        h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            text-align: center;
        }
        table {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto 30px;
            border-collapse: collapse;
            background: white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border-radius: 8px;
            overflow: hidden;
        }
        th, td {
            padding: 14px 16px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            background: #4f46e5;
            color: white;
            font-weight: 600;
        }
        tr:hover {
            background: #f1f5f9;
        }
        .no-reports {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            color: #64748b;
            font-size: 1.1rem;
        }
        .back-link {
            display: inline-block;
            margin: 20px 0;
            color: #4f46e5;
            text-decoration: none;
            font-weight: 500;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        .description {
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    </style>
</head>
<body>

<h2>Maintenance Reports <?= $proctor_block_id !== null ? "in Your Block" : "" ?></h2>

<?php if ($result->num_rows === 0): ?>
    <div class="no-reports">
        <h3>No reports found</h3>
        <p>
            <?php if ($proctor_block_id !== null): ?>
                No maintenance issues reported in your block yet.
            <?php else: ?>
                No maintenance reports in the system.
            <?php endif; ?>
        </p>
    </div>
<?php else: ?>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Student</th>
            <th>Type</th>
            <th>Description</th>
            <th>Block</th>
            <th>Room</th>
            <th>Status</th>
            <th>Date</th>
        </tr>
    </thead>
    <tbody>
    <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
            <td><?= $row['id'] ?></td>
            <td>
                <?php 
                if ($row['student_id']) {
                    echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name'] . ' (' . $row['student_id'] . ')');
                } else {
                    echo 'Unknown';
                }
                ?>
            </td>
            <td><?= htmlspecialchars($row['type']) ?></td>
            <td class="description" title="<?= htmlspecialchars($row['description'] ?? '-') ?>">
                <?= htmlspecialchars(substr($row['description'] ?? '-', 0, 80)) . (strlen($row['description'] ?? '') > 80 ? '...' : '') ?>
            </td>
            <td><?= $row['block_number'] ? "Block " . htmlspecialchars($row['block_number']) : '-' ?></td>
            <td><?= $row['room_number'] ?: '-' ?></td>
            <td>  <formm method="post" action="update_report_status.php" style="display:inline;">
                <span style="color: 
                    <?php 
                    if ($row['status'] === 'Pending') {
                    echo '#ef4444';
                    
                    }
                            
                    elseif ($row['status'] === 'In Progress') echo '#f59e0b';
                    else echo '#10b981';
                    ?>">
                    <?= htmlspecialchars($row['status']) ?>
                </span>
            </td>
            <td><?= date('Y-m-d H:i', strtotime($row['created_at'])) ?></td>
        </tr>
    <?php endwhile; ?>
    </tbody>
</table>

<?php endif; ?>

<div style="text-align: center; margin-top: 30px;">
    <a href="dashboard.php" class="back-link">← Back to Proctor Dashboard</a> | 
    <a href="../logout.php" class="back-link" style="color: #ef4444;">Logout</a>
</div>

</body>
</html>