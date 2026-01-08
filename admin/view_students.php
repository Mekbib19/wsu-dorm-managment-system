<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

include __DIR__ . '/../db.php';

// Auto filter: try to detect the logged-in user's assigned block (proctor)
$auto_block_id = null;
$auto_block_number = null;
$auto_applied = false;
$current_user = $_SESSION['username'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
if ($current_user) {
    if (isset($_SESSION['username'])) {
        $u = $_SESSION['username'];
        $stmt = $conn->prepare("SELECT block_id FROM proctors WHERE username = ?");
        $stmt->bind_param("s", $u);
    } else {
        $u = $current_user;
        $stmt = $conn->prepare("SELECT block_id FROM proctors WHERE id = ?");
        $stmt->bind_param("i", $u);
    }
    if ($stmt) {
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            if (!empty($row['block_id'])) {
                $auto_block_id = (int)$row['block_id'];
                $auto_applied = !isset($_GET['all']); // bypass with ?all=1
                if ($auto_applied) {
                    $bstmt = $conn->prepare("SELECT block_number FROM blocks WHERE id = ?");
                    $bstmt->bind_param("i", $auto_block_id);
                    $bstmt->execute();
                    $bn = $bstmt->get_result()->fetch_assoc();
                    $auto_block_number = $bn['block_number'] ?? null;
                    $bstmt->close();
                }
            }
        }
        $stmt->close();
    }
}

// Optional: search filter
$search = trim($_GET['search'] ?? '');
$where = "";
$params = [];
$types = "";

if ($search !== '') {
    $like = "%$search%";
    $where = " WHERE (s.student_id LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ?)";
    $types = "sss";
    $params = [$like, $like, $like];
}

if ($auto_applied && $auto_block_id) {
    $where .= ($where ? " AND " : " WHERE ") . " s.block_id = ?";
    $types .= "i";
    $params[] = $auto_block_id;
}

// Fetch students
$sql = "
    SELECT s.student_id, s.first_name, s.last_name, s.phone, 
           b.block_number, s.room_number 
    FROM students s 
    LEFT JOIN blocks b ON s.block_id = b.id
    $where
    ORDER BY s.student_id
";
$stmt = $conn->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$students = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Students List - Admin</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="admin.css">
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="container">
        <div class="header">
            <h2><?= ($auto_applied ? 'Students in My Block' : 'Students in Dorm') ?></h2>
            <?php if ($auto_applied && $auto_block_number): ?>
                <p style="margin-top:6px; color:var(--muted);">Showing students in <strong>Block <?= htmlspecialchars($auto_block_number) ?></strong>. <a href="?all=1">Show all</a></p>
            <?php elseif ($auto_applied && $auto_block_id): ?>
                <p style="margin-top:6px; color:var(--muted);">Showing students in your assigned block (ID <?= htmlspecialchars($auto_block_id) ?>). <a href="?all=1">Show all</a></p>
            <?php endif; ?>

            <form class="search-form" method="get">
                <input type="text" name="search" placeholder="Search by ID or name..." 
                       value="<?= htmlspecialchars($search) ?>">
                <button type="submit"><i class="fas fa-search"></i></button>
                <?php if ($search): ?>
                    <a href="?" style="color:var(--primary);">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if ($students->num_rows === 0): ?>
            <div class="empty-state">
                <i class="fas fa-users-slash" style="font-size:4rem; color:var(--muted); margin-bottom:20px;"></i>
                <h3>No students found</h3>
                <p>
                    <?php if ($search): ?>
                        No matches for your search.
                    <?php else: ?>
                        No students registered yet.
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <div class="student-grid">
                <?php while ($student = $students->fetch_assoc()): ?>
                    <div class="student-card">
                        <div class="student-id">
                            <?= htmlspecialchars($student['student_id'] ?? '—') ?>
                        </div>
                        <div class="info-row">
                            <span class="label">Name</span>
                            <span><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">Phone</span>
                            <span><?= htmlspecialchars($student['phone'] ?: '—') ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">Block</span>
                            <span><?= $student['block_number'] ? "Block {$student['block_number']}" : 'Not assigned' ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">Room</span>
                            <span><?= htmlspecialchars($student['room_number'] ?: '—') ?></span>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>

       
    </div>
</div>

</body>
</html>
