<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'proctor') {
    header("Location: ../login.php");
    exit;
}

include __DIR__ . '/../db.php';

// Get proctor details
$proctor_id = $_SESSION['proctor_id'] ?? 0;
$proctor_username = 'Proctor';
$proctor_block_id = null;
$proctor_block_number = 'Not Assigned';

$stmt = $conn->prepare("
    SELECT p.username, p.block_id, b.block_number 
    FROM proctors p 
    LEFT JOIN blocks b ON p.block_id = b.id 
    WHERE p.id = ?
");
$stmt->bind_param("i", $proctor_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $proctor_username     = $row['username'];
    $proctor_block_id     = $row['block_id'];
    $proctor_block_number = $row['block_number'] ? "Block " . $row['block_number'] : 'Not Assigned';
}
$stmt->close();

// Quick stats – safe version
$students_in_block = 0;
$pending_reports_in_block = 0;

$base_where = $proctor_block_id !== null ? " AND block_id = ?" : "";
$types = $proctor_block_id !== null ? "i" : "";
$params = $proctor_block_id !== null ? [$proctor_block_id] : [];

// Students count
$sql_students = "SELECT COUNT(*) FROM students WHERE 1=1" . $base_where;
$stmt = $conn->prepare($sql_students);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$students_in_block = $stmt->get_result()->fetch_row()[0] ?? 0;
$stmt->close();

// Pending reports count
$sql_reports = "SELECT COUNT(*) FROM maintenance_reports WHERE status = 'Pending'" . $base_where;
$stmt = $conn->prepare($sql_reports);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$pending_reports_in_block = $stmt->get_result()->fetch_row()[0] ?? 0;
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proctor Dashboard - WSU Dorm</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --success: #10b981;
            --light-bg: #f8fafc;
            --card-bg: #ffffff;
            --text: #1e293b;
            --text-muted: #64748b;
            --shadow: 0 10px 25px rgba(0,0,0,0.08);
        }
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--light-bg);
            color: var(--text);
            margin: 0;
            padding: 40px 20px;
            min-height: 100vh;
        }
        .container { max-width: 1100px; margin: 0 auto; }
        .header { text-align: center; margin-bottom: 48px; }
        .header h1 {
            font-size: 2.6rem;
            font-weight: 800;
            background: linear-gradient(90deg, var(--primary), #a855f7);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .welcome-card {
            background: white;
            border-radius: 16px;
            padding: 32px;
            box-shadow: var(--shadow);
            margin-bottom: 48px;
            text-align: center;
        }
        .welcome-card h2 { margin-bottom: 12px; font-size: 1.8rem; }
        .badge {
            padding: 8px 16px;
            background: var(--primary);
            color: white;
            border-radius: 999px;
            font-weight: 600;
            margin-top: 12px;
            display: inline-block;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 28px;
            margin-bottom: 48px;
        }
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 32px 24px;
            box-shadow: var(--shadow);
            text-align: center;
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(99,102,241,0.15);
        }
        .stat-icon {
            font-size: 3.2rem;
            margin-bottom: 16px;
            color: var(--primary);
        }
        .stat-number {
            font-size: 3.5rem;
            font-weight: 800;
            color: var(--primary);
        }
        .stat-label {
            color: var(--text-muted);
            font-size: 1.15rem;
        }
        .actions {
            max-width: 420px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .action-btn {
            padding: 16px 28px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.15rem;
            font-weight: 600;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            transition: all 0.3s ease;
        }
        .action-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(99,102,241,0.3);
        }
        .logout-btn { background: #ef4444; }
        .logout-btn:hover { background: #dc2626; }
        .modal {
    display: flex;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.5);
    align-items: center;
    justify-content: center;
    z-index: 9999;
}

.modal-content {
    background: white;
    padding: 30px;
    width: 350px;
    border-radius: 10px;
    box-shadow: var(--shadow);
}

    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>Proctor Dashboard</h1>
    </div>

    <div class="welcome-card">
        <h2>Welcome back, <?= htmlspecialchars($proctor_username) ?></h2>
        <p>Your assigned block: <span class="badge"><?= htmlspecialchars($proctor_block_number)?></p>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <i class="fas fa-users stat-icon"></i>
            <div class="stat-number"><?= number_format($students_in_block) ?></div>
            <div class="stat-label">Students in Your Block</div>
        </div>

        <div class="stat-card">
            <i class="fas fa-tools stat-icon"></i>
            <div class="stat-number"><?= number_format($pending_reports_in_block) ?></div>
            <div class="stat-label">Pending Maintenance Reports</div>
        </div>
    </div>

    <div class="actions">
        <a href="assign_student.php" class="action-btn">
            <i class="fas fa-user-plus"></i> Assign / Update Students
        </a>
        <a href="view_reports.php" class="action-btn">
            <i class="fas fa-wrench"></i> View Maintenance Reports
        </a>
        
        <a href="../logout.php" class="action-btn logout-btn">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</div>

</body>
</html>