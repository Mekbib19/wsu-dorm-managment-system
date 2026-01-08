<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

include __DIR__ . '/../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['progress'])) {
    $reportId = $_POST['report_id'] ?? '';
    if ($reportId !== '') {
        $update_stmt = $conn->prepare("UPDATE `maintenance_reports` SET `status` = 'In Progress' WHERE `id` = ?");
        $update_stmt->bind_param("i", $reportId);
        $update_stmt->execute();
        $update_stmt->close();
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }
}

// Search & filter
$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');

$where = [];
$params = [];
$types = "";

if ($search !== '') {
    $like = "%$search%";
    $where[] = "(r.type LIKE ? OR r.description LIKE ? OR s.student_id LIKE ? OR CONCAT(s.first_name, ' ', s.last_name) LIKE ?)";
    $types .= "ssss";
    $params = array_merge($params, [$like, $like, $like, $like]);
}

if ($status_filter !== '') {
    $where[] = "r.status = ?";
    $types .= "s";
    $params[] = $status_filter;
}

$where_sql = $where ? " WHERE " . implode(" AND ", $where) : "";

// Fetch reports (limit to latest 50 for performance)
$sql = "
    SELECT r.id, r.type, r.description, r.status, r.created_at,
           s.student_id, s.first_name, s.last_name,
           b.block_number, r.room_number
    FROM maintenance_reports r
    LEFT JOIN students s ON r.student_id = s.id
    LEFT JOIN blocks b ON r.block_id = b.id
    $where_sql
    ORDER BY r.created_at DESC
    LIMIT 50
";

$stmt = $conn->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$reports = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Reports - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="admin.css">
</head>
<body>
<?php include 'sidebar.php'; ?>

<!-- Wrap the page content -->
<div class="main-content">

<div class="container">
    <div class="header">
        <h2>Maintenance Reports</h2>

        <form class="filter-form" method="get">
            <input type="text" name="search" placeholder="Search type, description, student..." 
                   value="<?= htmlspecialchars($search) ?>">
            <select name="status">
                <option value="">All Status</option>
                <option value="Pending" <?= $status_filter === 'Pending' ? 'selected' : '' ?>>Pending</option>
                <option value="In Progress" <?= $status_filter === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                <option value="Resolved" <?= $status_filter === 'Resolved' ? 'selected' : '' ?>>Resolved</option>
            </select>
            <button type="submit"><i class="fas fa-filter"></i> Filter</button>
        </form>
    </div>

    <?php if ($reports->num_rows === 0): ?>
        <div class="empty-state">
            <i class="fas fa-tools" style="font-size:4rem; color:var(--muted); margin-bottom:20px;"></i>
            <h3>No reports found</h3>
            <p>
                <?php if ($search || $status_filter): ?>
                    Try adjusting your filters.
                <?php else: ?>
                    No maintenance reports in the system yet.
                <?php endif; ?>
            </p>
        </div>
    <?php else: ?>
        <div class="report-grid">
            <?php while ($row = $reports->fetch_assoc()): ?>
                <div class="report-card">
                    <div class="report-header">
                        <div class="report-id">Report #<?= $row['id'] ?></div>
                        <span class="status status-<?= strtolower(str_replace(' ', '-', $row['status'])) ?>">
                            <?= htmlspecialchars($row['status'])
                            ?>
                        </span>
                        
                    </div>

                    <div class="report-desc" title="<?= htmlspecialchars($row['description'] ?? '-') ?>">
                        <?= htmlspecialchars(substr($row['description'] ?? '-', 0, 120)) . (strlen($row['description'] ?? '') > 120 ? '...' : '') ?>
                    </div>

                    <div class="report-meta">
                        <strong>Student:</strong> 
                        <?php 
                        if ($row['student_id']) {
                            echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name'] . ' (' . $row['student_id'] . ')');
                        } else {
                            echo 'Unknown';
                        }
                        ?><br>
                        <strong>Block:</strong> <?= $row['block_number'] ? "Block " . htmlspecialchars($row['block_number']) : '-' ?><br>
                        <strong>Room:</strong> <?= $row['room_number'] ?: '-' ?><br>
                        <strong>Reported:</strong> <?= date('d M Y • H:i', strtotime($row['created_at'])) ?>
                         <span>
                            <?php if($row['status']=='Pending'){ ?>
                                <form method="post" style="display:inline">
                                    <input type="hidden" name="report_id" value="<?= htmlspecialchars($row['id']) ?>">
                                    <button class="status" type="submit" name="progress" value="1">Progress</button>
                                </form>
                            <?php } ?>
                    </span>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>
</div>
</div>


</body>
</html>