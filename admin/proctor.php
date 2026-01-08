<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
include __DIR__ . '/../db.php';

$msg = '';

// Fetch all proctors
$proctors = $conn->query("
    SELECT p.id, p.username, p.block_id, b.block_number 
    FROM proctors p 
    LEFT JOIN blocks b ON p.block_id = b.id 
    ORDER BY p.username
");

// Fetch all blocks for dropdown
$blocks = $conn->query("SELECT id, block_number FROM blocks ORDER BY block_number");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Proctors - Admin</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .msg { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .success { background:#d4edda; color:#155724; }
        .error   { background:#f8d7da; color:#721c24; }
        table { width:100%; border-collapse:collapse; margin-top:20px; }
        th, td { border:1px solid #ddd; padding:10px; }
        th { background:#f2f2f2; }
    </style>
</head>
<body>
<div style="display:flex; justify-content:flex-end; gap:10px; margin-bottom:20px; padding:0 20px;">
    <a href="../logout.php" 
       style="display:inline-block; color:#fff; background:#ff4d4d; padding:6px 12px; border-radius:5px; text-decoration:none; font-size:14px;">
       Logout
    </a>
    <a href="dashboard.php" 
       style="display:inline-block; color:#fff; background:#6366f1; padding:6px 12px; border-radius:5px; text-decoration:none; font-size:14px;">
       Dashboard
    </a>
</div>

<h2>Manage Proctors</h2>

<?php if ($msg): ?>
    <div class="msg <?= strpos($msg, 'success') !== false ? 'success' : 'error' ?>">
        <?= $msg ?>
    </div>
<?php endif; ?>

<hr>

<h3>Existing Proctors</h3>

<?php if ($proctors->num_rows > 0): ?>
<table>
    <tr>
        <th>ID</th>
        <th>Username</th>
        <th>Assigned Block</th>
        <th>Actions</th>
    </tr>
    <?php while ($p = $proctors->fetch_assoc()): ?>
    <tr>
        <td><?= $p['id'] ?></td>
        <td><?= htmlspecialchars($p['username']) ?></td>
        <td><?= $p['block_number'] ? "Block " . $p['block_number'] : "— (all blocks)" ?></td>
        <td>
            <!-- You can add edit/delete later -->
            <small>Edit / Delete (to be added)</small>
        </td>
    </tr>
    <?php endwhile; ?>
</table>
<?php else: ?>
    <p><em>No proctors yet.</em></p>
<?php endif; ?>

<br><br>
<a href="dashboard.php">← Back to Admin Dashboard</a> | 
<a href="../logout.php">Logout</a>

</body>
</html>