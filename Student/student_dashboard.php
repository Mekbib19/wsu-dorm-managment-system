<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit;
}

include __DIR__ . '/../db.php';

$student_id = $_SESSION['student_id'];
$msg = '';

// CSRF token
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

/* ───────────────── Resolve Report ───────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resolve_report'])) {

    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        die('Invalid CSRF token');
    }

    $report_id = (int)$_POST['report_id'];

    $stmt = $conn->prepare("
        UPDATE maintenance_reports 
        SET status = 'Resolved' 
        WHERE id = ? AND student_id = ? AND status != 'Resolved'
    ");
    $stmt->bind_param("ii", $report_id, $student_id);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $msg = "<div class='alert success'>
                <i class='fas fa-check-circle'></i>
                <div>
                    <strong>Success!</strong>
                    Report marked as Resolved
                </div>
                </div>";
    } else {
        $msg = "<div class='alert error'>
                <i class='fas fa-exclamation-circle'></i>
                <div>
                    <strong>Error!</strong>
                    Unable to update report
                </div>
                </div>";
    }
    $stmt->close();
}

/* ───────────────── Student Info ───────────────── */
$stmt = $conn->prepare("
    SELECT s.student_id, s.first_name, s.last_name, s.phone,
           s.block_id, s.room_number, s.key, b.block_number
    FROM students s
    LEFT JOIN blocks b ON s.block_id = b.id
    WHERE s.id = ?
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$me = $stmt->get_result()->fetch_assoc();
$stmt->close();

/* ───────────────── Reports ───────────────── */
$stmt = $conn->prepare("
    SELECT id, type, description, status, created_at
    FROM maintenance_reports
    WHERE student_id = ?
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$reports = $stmt->get_result();

$stmt = $conn->prepare("SELECT username FROM proctors WHERE block_id=?");
$stmt->bind_param("i", $me['block_id']);
$stmt->execute();
$proctors = $stmt->get_result();
$proctor = $proctors->num_rows > 0 ? $proctors->fetch_assoc()['username'] : 'Not assigned';

$hasRoom = !empty($me['room_number']);
$keyReturned = ($me['key'] == 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard | DMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-light: #6366f1;
            --primary-dark: #4338ca;
            --success: #10b981;
            --success-light: #d1fae5;
            --warning: #f59e0b;
            --warning-light: #fef3c7;
            --danger: #ef4444;
            --danger-light: #fee2e2;
            --info: #0ea5e9;
            --info-light: #e0f2fe;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.12);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1);
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4edf5 100%);
            color: var(--gray-800);
            line-height: 1.6;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px 0;
            border-bottom: 1px solid var(--gray-200);
        }

        .welcome-section h1 {
            font-size: 28px;
            color: var(--gray-900);
            margin-bottom: 5px;
        }

        .welcome-section p {
            color: var(--gray-600);
            font-size: 16px;
        }

        .student-badge {
            background: var(--primary);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
        }

        /* Alerts */
        .alert {
            padding: 16px 20px;
            border-radius: var(--radius-sm);
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { transform: translateY(-10px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .alert.success {
            background: var(--success-light);
            border-left: 4px solid var(--success);
            color: var(--gray-800);
        }

        .alert.error {
            background: var(--danger-light);
            border-left: 4px solid var(--danger);
            color: var(--gray-800);
        }

        .alert i {
            font-size: 20px;
        }

        .alert.success i {
            color: var(--success);
        }

        .alert.error i {
            color: var(--danger);
        }

        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        /* Cards */
        .card {
            background: white;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-md);
            padding: 24px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--gray-100);
        }

        .card-header i {
            color: var(--primary);
            font-size: 20px;
        }

        .card-header h3 {
            font-size: 18px;
            color: var(--gray-900);
        }

        /* Info Card */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .info-item {
            padding: 12px 0;
        }

        .info-label {
            display: block;
            font-size: 13px;
            color: var(--gray-500);
            margin-bottom: 5px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-value {
            font-size: 16px;
            color: var(--gray-900);
            font-weight: 600;
        }

        .key-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }

        .key-status.returned {
            background: var(--success-light);
            color: #065f46;
        }

        .key-status.not-returned {
            background: var(--danger-light);
            color: #991b1b;
        }

        /* Reports */
        .report-item {
            background: var(--gray-50);
            border-radius: var(--radius-sm);
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid transparent;
        }

        .report-item.pending {
            border-left-color: var(--warning);
        }

        .report-item.progress {
            border-left-color: var(--info);
        }

        .report-item.resolved {
            border-left-color: var(--success);
        }

        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .report-title {
            font-weight: 600;
            color: var(--gray-900);
            font-size: 16px;
        }

        .status-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pending {
            background: var(--warning-light);
            color: #92400e;
        }

        .status-progress {
            background: var(--info-light);
            color: #0c4a6e;
        }

        .status-resolved {
            background: var(--success-light);
            color: #065f46;
        }

        .report-description {
            color: var(--gray-600);
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .report-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
            color: var(--gray-500);
        }

        .resolve-btn {
            background: var(--success);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: var(--radius-sm);
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .resolve-btn:hover {
            background: #059669;
        }

        /* Dormmates Table */
        .dormmates-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .dormmates-table th {
            background: var(--gray-50);
            padding: 12px 16px;
            text-align: left;
            font-weight: 600;
            color: var(--gray-700);
            border-bottom: 2px solid var(--gray-200);
        }

        .dormmates-table td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--gray-100);
        }

        .dormmates-table tr:hover {
            background: var(--gray-50);
        }

        /* Actions Section */
        .actions-section {
            background: white;
            border-radius: var(--radius-md);
            padding: 30px;
            margin-top: 40px;
            box-shadow: var(--shadow-md);
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 25px 20px;
            background: white;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius-md);
            text-decoration: none;
            color: var(--gray-700);
            transition: all 0.3s ease;
            text-align: center;
        }

        .action-btn:hover:not(:disabled) {
            border-color: var(--primary);
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
            color: var(--primary);
        }

        .action-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .action-btn i {
            font-size: 28px;
            margin-bottom: 12px;
            color: var(--primary);
        }

        .action-btn .btn-text {
            font-weight: 600;
            font-size: 16px;
        }

        .action-btn .btn-subtext {
            font-size: 13px;
            color: var(--gray-500);
            margin-top: 5px;
        }

        .action-btn.logout {
            border-color: var(--danger-light);
            color: var(--danger);
        }

        .action-btn.logout i {
            color: var(--danger);
        }

        .action-btn.logout:hover {
            border-color: var(--danger);
            background: var(--danger-light);
        }

        /* Empty States */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--gray-500);
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            color: var(--gray-300);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .actions-grid {
                grid-template-columns: 1fr;
            }
            
            .card {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="welcome-section">
                <h1>Welcome back, <?= htmlspecialchars($me['first_name']) ?>!</h1>
                <p>Student Dashboard • Dormitory Management System</p>
            </div>
            <div class="student-badge">
                Student ID: <?= htmlspecialchars($me['student_id']) ?>
            </div>
        </div>

        <!-- Alert Messages -->
        <?= $msg ?>

        <!-- Dashboard Grid -->
        <div class="dashboard-grid">
            <!-- Student Information Card -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-user-circle"></i>
                    <h3>Personal Information</h3>
                </div>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Full Name</span>
                        <span class="info-value"><?= htmlspecialchars($me['first_name'] . ' ' . $me['last_name']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Phone</span>
                        <span class="info-value"><?= $me['phone'] ? htmlspecialchars($me['phone']) : 'Not set' ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Block & Room</span>
                        <span class="info-value"><?= $me['block_number'] ? htmlspecialchars($me['block_number']) : '—' ?> • <?= $me['room_number'] ? htmlspecialchars($me['room_number']) : '—' ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Key Status</span>
                        <span class="key-status <?= $me['key'] ? 'returned' : 'not-returned' ?>">
                            <i class="fas fa-<?= $me['key'] ? 'check' : 'times' ?>"></i>
                            <?= $me['key'] ? 'Returned' : 'Not returned' ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Assigned Proctor</span>
                        <span class="info-value"><?= htmlspecialchars($proctor) ?></span>
                    </div>
                </div>
            </div>

            <!-- Reports Summary Card -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-clipboard-list"></i>
                    <h3>Maintenance Reports</h3>
                </div>
                <?php if ($reports->num_rows === 0): ?>
                    <div class="empty-state">
                        <i class="fas fa-clipboard"></i>
                        <p>No reports submitted yet</p>
                    </div>
                <?php else: ?>
                    <div style="max-height: 400px; overflow-y: auto; padding-right: 10px;">
                    <?php while ($r = $reports->fetch_assoc()):
                        $statusClass = match($r['status']) {
                            'Pending' => 'pending',
                            'In Progress' => 'progress',
                            'Resolved' => 'resolved',
                            default => 'pending'
                        }; ?>
                        <div class="report-item <?= $statusClass ?>">
                            <div class="report-header">
                                <div class="report-title"><?= htmlspecialchars($r['type']) ?></div>
                                <span class="status-badge status-<?= $statusClass ?>">
                                    <?= $r['status'] ?>
                                </span>
                            </div>
                            <div class="report-description">
                                <?= htmlspecialchars(substr($r['description'], 0, 120)) ?>
                                <?= strlen($r['description']) > 120 ? '...' : '' ?>
                            </div>
                            <div class="report-footer">
                                <span>
                                    <i class="far fa-clock"></i>
                                    <?= date('d M Y, H:i', strtotime($r['created_at'])) ?>
                                </span>
                                <?php if ($r['status'] !== 'Resolved'): ?>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
                                    <input type="hidden" name="resolve_report" value="1">
                                    <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                                    <button type="submit" class="resolve-btn">
                                        <i class="fas fa-check"></i>
                                        Mark Resolved
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Dormmates Card -->
            <?php if ($hasRoom): ?>
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-users"></i>
                    <h3>Dormmates</h3>
                </div>
                <?php
                $stmt = $conn->prepare("
                    SELECT first_name, last_name, phone FROM students
                    WHERE room_number = ? AND id != ? AND block_id = ?
                ");
                $stmt->bind_param("iii", $me['room_number'], $student_id, $me['block_id']);
                $stmt->execute();
                $dormmates = $stmt->get_result();
                
                if ($dormmates->num_rows > 0): ?>
                    <table class="dormmates-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Phone</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($d = $dormmates->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($d['first_name'] . ' ' . $d['last_name']) ?></td>
                                <td><?= htmlspecialchars($d['phone'] ?: 'Not set') ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-user-friends"></i>
                        <p>No dormmates in this room</p>
                    </div>
                <?php endif; ?>
                <?php $stmt->close(); ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Actions Section -->
        <div class="actions-section">
            <div class="card-header">
                <i class="fas fa-tools"></i>
                <h3>Quick Actions</h3>
            </div>
            <div class="actions-grid">
                <?php if ($hasRoom): ?>
                    <a href="report.php" class="action-btn">
                        <i class="fas fa-plus-circle"></i>
                        <span class="btn-text">Report Issue</span>
                        <span class="btn-subtext">Submit maintenance request</span>
                    </a>
                <?php else: ?>
                    <button class="action-btn" disabled>
                        <i class="fas fa-plus-circle"></i>
                        <span class="btn-text">Report Issue</span>
                        <span class="btn-subtext">Assign a room first</span>
                    </button>
                <?php endif; ?>

                <?php if ($hasRoom && $keyReturned): ?>
                    <a href="clearance.php" class="action-btn">
                        <i class="fas fa-file-certificate"></i>
                        <span class="btn-text">Generate Clearance</span>
                        <span class="btn-subtext">Get dorm clearance certificate</span>
                    </a>
                <?php else: ?>
                    <button class="action-btn" disabled>
                        <i class="fas fa-file-certificate"></i>
                        <span class="btn-text">Generate Clearance</span>
                        <span class="btn-subtext"><?= !$hasRoom ? 'Assign a room first' : 'Return key first' ?></span>
                    </button>
                <?php endif; ?>

                <a href="edit_profile.php" class="action-btn">
                    <i class="fas fa-user-edit"></i>
                    <span class="btn-text">Edit Profile</span>
                    <span class="btn-subtext">Update personal information</span>
                </a>

                <a href="../logout.php" class="action-btn logout">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="btn-text">Logout</span>
                    <span class="btn-subtext">Sign out from system</span>
                </a>
            </div>
        </div>
    </div>

    <script>
        // Add some interactive effects
        document.addEventListener('DOMContentLoaded', function() {
            // Animate cards on load
            const cards = document.querySelectorAll('.card');
            cards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
                card.style.animation = 'slideIn 0.5s ease forwards';
            });
            
            // Form submission confirmation
            const resolveForms = document.querySelectorAll('form');
            resolveForms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    if (this.querySelector('.resolve-btn')) {
                        if (!confirm('Are you sure you want to mark this report as resolved?')) {
                            e.preventDefault();
                        }
                    }
                });
            });
        });
    </script>
</body>
</html>