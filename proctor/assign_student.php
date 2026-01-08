<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'proctor') {
    header("Location: ../login.php");
    exit;
}

include __DIR__ . '/../db.php';

// Get proctor's assigned block
$proctor_block_id = null;
$proctor_block_number = null;
$proctor_capacity = null;

if (isset($_SESSION['proctor_id'])) {
    $stmt = $conn->prepare("SELECT block_id FROM proctors WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['proctor_id']);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $proctor_block_id = $row['block_id'];
    }
    $stmt->close();
}

if ($proctor_block_id !== null) {
    $stmt = $conn->prepare("SELECT block_number, capacity FROM blocks WHERE id = ?");
    $stmt->bind_param("i", $proctor_block_id);
    $stmt->execute();
    $block_info = $stmt->get_result()->fetch_assoc();
    if ($block_info) {
        $proctor_block_number = $block_info['block_number'];
        $proctor_capacity = (int)$block_info['capacity'];
    }
    $stmt->close();
}

$msg = '';
$search = trim($_GET['search'] ?? '');

// Handle key toggle
if (isset($_POST['toggle_key'])) {
    $student_id = (int)$_POST['student_id'];
    $new_key = (int)(isset($_POST['new_key']) ? $_POST['new_key'] : 0);
    $s = $conn->prepare("UPDATE students SET `key` = ? WHERE id = ?");
    $s->bind_param("ii", $new_key, $student_id);
    if ($s->execute()) {
        $msg = $new_key
            ? "<div style='color:green'>Marked key as returned.</div>"
            : "<div style='color:orange'>Marked key as missing.</div>";
    }
    $s->close();
}

// Handle block & room assignment
if (isset($_POST['assign'])) {
    $student_id = (int)$_POST['student_id'];
    $block_id = (int)$_POST['block_id'];
    $room_number = (int)$_POST['room_number'];

    // Server-side validation
    $max_room = $proctor_block_id ? $proctor_capacity : 999;
    if ($proctor_block_id !== null && $block_id != $proctor_block_id) {
        $msg = "<div style='color:red'>You can only assign students to your block.</div>";
    } elseif ($room_number < 1 || $room_number > $max_room) {
        $msg = "<div style='color:red'>Invalid room number.</div>";
    } else {
        $stmt = $conn->prepare("UPDATE students SET block_id = ?, room_number = ? WHERE id = ?");
        $stmt->bind_param("iii", $block_id, $room_number, $student_id);
        if ($stmt->execute()) {
            $msg = $stmt->affected_rows > 0
                ? "<div style='color:green'>Student updated successfully.</div>"
                : "<div style='color:orange'>No changes made.</div>";
        }
        $stmt->close();
    }
}

// Fetch students
$students_query = "
    SELECT s.id, s.student_id, s.first_name, s.last_name, s.phone,
           b.id AS block_id, b.block_number, s.room_number, s.`key` AS has_key
    FROM students s
    LEFT JOIN blocks b ON s.block_id = b.id
";
$params = [];
$types = "";

if ($proctor_block_id !== null) {
    $students_query .= " WHERE s.block_id = ?";
    $types .= "i";
    $params[] = $proctor_block_id;
}

if ($search !== '') {
    $like = "%$search%";
    $students_query .= $proctor_block_id !== null ? " AND " : " WHERE ";
    $students_query .= "(s.student_id LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ?)";
    $types .= "sss";
    $params = array_merge($params, [$like, $like, $like]);
}

$students_query .= " ORDER BY s.student_id";
$stmt = $conn->prepare($students_query);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$students = $stmt->get_result();

// Blocks for assignment dropdown (if no fixed block)
$blocks = null;
if ($proctor_block_id === null) {
    $blocks = $conn->query("SELECT id, block_number, capacity FROM blocks ORDER BY block_number");
}

if (isset($_POST['toggle_key'])) {
    $student_id = (int)$_POST['student_id'];

    $check = $conn->prepare("SELECT room_number FROM students WHERE id=?");
    $check->bind_param("i", $student_id);
    $check->execute();
    $row = $check->get_result()->fetch_assoc();
    $check->close();

    if (empty($row['room_number'])) {
        $msg = "<div class='error'>Assign room before changing key status.</div>";
    } else {
        $new_key = (int)$_POST['new_key'];
        $s = $conn->prepare("UPDATE students SET `key`=? WHERE id=?");
        $s->bind_param("ii", $new_key, $student_id);
        $s->execute();
        $s->close();

        $msg = $new_key
            ? "<div class='success'>Key marked as returned.</div>"
            : "<div class='warning'>Key marked as missing.</div>";
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Proctor – Assign Students</title>
<style>
body { font-family: Arial,sans-serif; background:#f4f6f8; padding:20px; }
.container { max-width:1000px; margin:auto; background:#fff; padding:20px; border-radius:8px; }
table { width:100%; border-collapse: collapse; margin-top:20px; }
th, td { border:1px solid #ccc; padding:8px; text-align:center; }
th { background:#eee; }
input, select, button { padding:6px; margin:2px; }
button { cursor:pointer; }
.success { color:green; }
.warning { color:orange; }
.error { color:red; }
</style>
</head>
<body>
<div class="container">
<h2>Assign / Update Students</h2>
<?= $msg ?>

<form method="get">
<input type="text" name="search" placeholder="Search by ID or name" value="<?= htmlspecialchars($search) ?>">
<button type="submit">Search</button>
<a href="?" style="margin-left:10px;">Clear</a>
</form>

<table>
<thead>
<tr>
<th>ID</th>
<th>Name</th>
<th>Phone</th>
<th>Block</th>
<th>Room</th>
<th>Key</th>
<th>Actions</th>
</tr>
</thead>
<tbody>
<?php if ($students->num_rows === 0): ?>
<tr><td colspan="7">No students found.</td></tr>
<?php else: ?>
<?php while($s = $students->fetch_assoc()): ?>
<tr>
<td><?= htmlspecialchars($s['student_id']) ?></td>
<td><?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?></td>
<td><?= htmlspecialchars($s['phone'] ?: '—') ?></td>
<td>
<?php if ($proctor_block_id !== null): ?>
<?= htmlspecialchars($proctor_block_number) ?>
<input type="hidden" form="assign_<?= $s['id'] ?>" name="block_id" value="<?= $proctor_block_id ?>">
<?php else: ?>
<select form="assign_<?= $s['id'] ?>" name="block_id" required>
<option value="">Select Block</option>
<?php if($blocks): while($b=$blocks->fetch_assoc()): ?>
<option value="<?= $b['id'] ?>" <?= $s['block_id']==$b['id']?'selected':'' ?>>Block <?= $b['block_number'] ?></option>
<?php endwhile; $blocks->data_seek(0); endif; ?>
</select>
<?php endif; ?>
</td>
<td>
<input type="number" form="assign_<?= $s['id'] ?>" name="room_number" value="<?= htmlspecialchars($s['room_number'] ?? '') ?>" min="1" max="<?= $proctor_capacity ?? 999 ?>" required>
</td>
<td>
<?php $disableKeyBtn = empty($s['room_number']); ?>

<form method="post">
    <input type="hidden" name="student_id" value="<?= $s['id'] ?>">
    <input type="hidden" name="new_key" value="<?= $s['has_key'] ? 0 : 1 ?>">

    <button
        type="submit"
        name="toggle_key"
        <?= $disableKeyBtn ? 'disabled' : '' ?>
        style="
            padding:6px 12px;
            border:none;
            border-radius:6px;
            color:#fff;
            font-weight:600;
            cursor: <?= $disableKeyBtn ? 'not-allowed' : 'pointer' ?>;
            background: <?= $s['has_key'] ? '#2ea44f' : '#dc3545' ?>;
            opacity: <?= $disableKeyBtn ? '0.6' : '1' ?>;
        "
        title="<?= $disableKeyBtn ? 'Assign room first' : '' ?>"
    >
        <?= $s['has_key'] ? 'Returned' : 'Missing' ?>
    </button>
</form>


</td>
<td>
<form id="assign_<?= $s['id'] ?>" method="post">
<input type="hidden" name="student_id" value="<?= $s['id'] ?>">
<button type="submit" name="assign">Update</button>
</form>
</td>
</tr>
<?php endwhile; ?>
<?php endif; ?>
</tbody>
</table>
<br>
<a href="dashboard.php">Back to Dashboard</a> | <a href="../logout.php">Logout</a>
</div>
</body>
</html>
