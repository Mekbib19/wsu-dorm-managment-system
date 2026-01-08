<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

include __DIR__ . '/../db.php';

$msg = '';
$msg_type = 'info'; // success, error, warning

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $block_number = trim($_POST['block_number'] ?? '');
    $capacity     = trim($_POST['capacity'] ?? '');

    // Basic server-side validation
    if (!is_numeric($block_number) || $block_number <= 0) {
        $msg = "Block number must be a positive number.";
        $msg_type = 'error';
    } elseif (!is_numeric($capacity) || $capacity <= 0) {
        $msg = "Capacity must be a positive number.";
        $msg_type = 'error';
    } else {
        // Check if block number already exists
        $stmt = $conn->prepare("SELECT id FROM blocks WHERE block_number = ?");
        $stmt->bind_param("i", $block_number);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $msg = "Block number $block_number already exists.";
            $msg_type = 'error';
        } else {
            $stmt = $conn->prepare("INSERT INTO blocks (block_number, capacity) VALUES (?, ?)");
            $stmt->bind_param("ii", $block_number, $capacity);

            if ($stmt->execute()) {
                $msg = "Block $block_number added successfully!";
                $msg_type = 'success';
                // Optional: reset form
                $_POST = [];
            } else {
                $msg = "Database error: " . htmlspecialchars($stmt->error);
                $msg_type = 'error';
            }
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
    <title>Add New Block - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="add.css">
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="container">
    <div class="card">
        <h2>Add New Block</h2>


        <?php if ($msg): ?>
            <div class="alert alert-<?= $msg_type ?>">
                <i class="fas <?= $msg_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label for="block_number">Block Number <span style="color:red;">*</span></label>
                <input type="number" id="block_number" name="block_number" min="1" required 
                       value="<?= htmlspecialchars($_POST['block_number'] ?? '') ?>" 
                       placeholder="e.g. 1, 2, 3...">
            </div>

            <div class="form-group">
                <label for="capacity">Capacity (max students) <span style="color:red;">*</span></label>
                <input type="number" id="capacity" name="capacity" min="1" required 
                       value="<?= htmlspecialchars($_POST['capacity'] ?? '') ?>" 
                       placeholder="e.g. 60">
            </div>

            <button type="submit" name="save">
                <i class="fas fa-plus"></i> Add Block
            </button>
        </form>

       
    </div>
</div>

</body>
</html>