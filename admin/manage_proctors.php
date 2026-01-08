<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
include __DIR__ . '/../db.php';

$msg = '';

// Handle new proctor creation
if (isset($_POST['add_proctor'])) {
    $username   = trim($_POST['username']);
    $password   = password_hash(trim($_POST['password']),PASSWORD_DEFAULT);
    $block_id   = !empty($_POST['block_id']) ? (int)$_POST['block_id'] : null;

    if (empty($username) || empty($password)) {
        $msg = "<span style='color:red'>Username and password are required.</span>";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO proctors (username, password, block_id) 
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param("ssi", $username, $password, $block_id);

        if ($stmt->execute()) {
            $msg = "<span class='success'>Proctor added successfully.</span>";
        } else {
            $msg = "<span class='error'>Error: " . htmlspecialchars($conn->error) . "</span>";
        }
    }
}

// Fetch all blocks for dropdown
$blocks = $conn->query("SELECT id, block_number FROM blocks ORDER BY block_number");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Proctors - Admin</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="admin.css">
<link rel="stylesheet" href="add.css">
</head>
<body>

<?php include 'sidebar.php'; ?>

    <div class="container">
        <h2>Manage Proctors</h2>

        <?php if ($msg): ?>
            <div class="msg <?= strpos($msg, 'success') !== false ? 'success' : 'error' ?>">
                <?= $msg ?>
            </div>
        <?php endif; ?>

        <h3>Add New Proctor</h3>
        <form method="post">
            <div class="form-group">
                <label for="username">Username <span style="color:red;">*</span></label>
                <input type="text" name="username" id="username" required>
            </div>

            <div class="form-group">
                <label for="password">Password <span style="color:red;">*</span></label>
                <input type="password" name="password" id="password" required>
            </div>

            <div class="form-group">
                <label for="block_id">Assign to Block (optional)</label>
                <select name="block_id" id="block_id">
                    <option value="">-- No block (can manage all) --</option>
                    <?php while ($b = $blocks->fetch_assoc()): ?>
                        <option value="<?= $b['id'] ?>">Block <?= $b['block_number'] ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <button type="submit" name="add_proctor">Create Proctor</button>
        </form>
    </div>
</div>

</body>
</html>
