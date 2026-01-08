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
$pending_reports  = $conn->query("SELECT COUNT(*) FROM maintenance_reports WHERE status = 'Pending' OR status = 'In Progress'")->fetch_row()[0] ?? 0;
$proctors_count   = $conn->query("SELECT COUNT(*) FROM proctors")->fetch_row()[0] ?? 0;
$maintenance_count = $conn->query("SELECT COUNT(*) FROM maintenance_reports")->fetch_row()[0] ?? 0;
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
$stmt = $conn->prepare("SELECT message, created_at FROM announcements ORDER BY created_at DESC LIMIT 3");
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
    <style>
        :root {
            --primary: #4e73df;
            --primary-dark: #2e59d9;
            --secondary: #858796;
            --success: #1cc88a;
            --info: #36b9cc;
            --warning: #f6c23e;
            --danger: #e74a3b;
            --light: #f8f9fc;
            --dark: #3a3b45;
            --sidebar-width: 250px;
            --border-radius: 8px;
            --shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background-color: #f5f7fb;
            color: #333;
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar Styles */
       
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 2rem;
            min-height: 100vh;
        }
        
        .page-header {
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e3e6f0;
        }
        
        .page-header h1 {
            color: var(--dark);
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }
        
        .page-subtitle {
            color: var(--secondary);
            font-size: 0.95rem;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }
        
        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--shadow);
            transition: transform 0.3s, box-shadow 0.3s;
            border-top: 4px solid var(--primary);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 2rem 0 rgba(58, 59, 69, 0.2);
        }
        
        .stat-card:nth-child(2) {
            border-top-color: var(--success);
        }
        
        .stat-card:nth-child(3) {
            border-top-color: var(--info);
        }
        
        .stat-card:nth-child(4) {
            border-top-color: var(--danger);
        }
        
        .stat-card:nth-child(5) {
            border-top-color: var(--warning);
        }
        
        .stat-icon {
            font-size: 1.8rem;
            margin-bottom: 1rem;
            color: var(--primary);
        }
        
        .stat-card:nth-child(2) .stat-icon {
            color: var(--success);
        }
        
        .stat-card:nth-child(3) .stat-icon {
            color: var(--info);
        }
        
        .stat-card:nth-child(4) .stat-icon {
            color: var(--danger);
        }
        
        .stat-card:nth-child(5) .stat-icon {
            color: var(--warning);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
            color: var(--dark);
        }
        
        .stat-label {
            color: var(--secondary);
            font-size: 0.9rem;
            margin-bottom: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .quick-link {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: var(--primary);
            text-decoration: none;
            font-size: 0.875rem;
            padding: 0.375rem 0.75rem;
            background-color: rgba(78, 115, 223, 0.1);
            border-radius: 4px;
            transition: all 0.3s;
        }
        
        .quick-link:hover {
            background-color: rgba(78, 115, 223, 0.2);
            text-decoration: none;
        }
        
        /* Recent Reports Section */
        .section {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }
        
        .section h3 {
            color: var(--dark);
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #e3e6f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section h3 i {
            color: var(--primary);
        }
        
        /* Table Styles */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        table thead {
            background-color: #f8f9fc;
        }
        
        table th {
            padding: 0.875rem 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--dark);
            border-bottom: 2px solid #e3e6f0;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        table td {
            padding: 1rem;
            border-bottom: 1px solid #e3e6f0;
            color: #555;
        }
        
        table tbody tr:hover {
            background-color: #f8f9fc;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-pending {
            background-color: rgba(246, 194, 62, 0.2);
            color: #b58900;
        }
        
        .status-resolved {
            background-color: rgba(28, 200, 138, 0.2);
            color: #1cc88a;
        }
        
        .status-processing {
            background-color: rgba(54, 185, 204, 0.2);
            color: #36b9cc;
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }
            
            .sidebar-header h2 span,
            .nav-link span {
                display: none;
            }
            
            .nav-link {
                justify-content: center;
                padding: 1rem;
            }
            
            .main-content {
                margin-left: 70px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            table {
                font-size: 0.9rem;
            }
            
            table th, table td {
                padding: 0.75rem 0.5rem;
            }
        }
        
        @media (max-width: 480px) {
            .main-content {
                padding: 1rem;
            }
            
            .stat-card {
                padding: 1rem;
            }
            
            .stat-number {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Left Sidebar -->
    <?php include 'sidebar.php'; ?>
 <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h1>Admin Dashboard</h1>
            <p class="page-subtitle">Welcome back, Administrator! Here's what's happening today.</p>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-building stat-icon"></i>
                <div class="stat-number"><?= number_format($blocks_count) ?></div>
                <div class="stat-label">Blocks</div>
                <a href="add_block.php" class="quick-link">
                    <i class="fas fa-plus"></i> Add New Block
                </a>
            </div>

            <div class="stat-card">
                <i class="fas fa-user-plus stat-icon"></i>
                <div class="stat-number"><?= number_format($students_count) ?></div>
                <div class="stat-label">Total Students</div>
                <a href="add_student.php" class="quick-link">
                    <i class="fas fa-plus"></i> Add New Student
                </a>
            </div>

            <div class="stat-card">
                <i class="fas fa-user-plus stat-icon"></i>
                <div class="stat-number"><?= number_format($proctors_count) ?></div>
                <div class="stat-label">Proctors</div>
                <a href="manage_proctors.php" class="quick-link">
                    <i class="fas fa-plus"></i> Manage Proctors
                </a>
            </div>

            <div class="stat-card">
                <i class="fas fa-exclamation-triangle stat-icon"></i>
                <div class="stat-number"><?= number_format($pending_reports) ?></div>
                <div class="stat-label">Pending Reports</div>
                <a href="view_reports.php" class="quick-link">
                    <i class="fas fa-eye"></i> View Reports
                </a>
            </div>

            <div class="stat-card">
                <i class="fas fa-key stat-icon"></i>
                <div class="stat-number"><?= $stats['total'] ?? 0 ?></div>
                <div class="stat-label">OTP Tokens</div>
                <a href="otp_analysis.php" class="quick-link">
                    <i class="fas fa-chart-bar"></i> OTP Analysis
                </a>
            </div>

            <div class="stat-card">
                <i class="fas fa-tools stat-icon"></i>
                <div class="stat-number"><?= number_format($maintenance_count) ?></div>
                <div class="stat-label">Maintenance Logs</div>
                <a href="view_reports.php" class="quick-link">
                    <i class="fas fa-history"></i> View Logs
                </a>
            </div>
        </div>
        <!-- Recent Reports Section -->
        <div class="section">
            <h3><i class="fas fa-history"></i> Recent Maintenance Reports (Last 5)</h3>
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
                        <?php while ($row = $recent_reports->fetch_assoc()): 
                            $statusClass = '';
                            if ($row['status'] == 'Pending') $statusClass = 'status-pending';
                            if ($row['status'] == 'Resolved') $statusClass = 'status-resolved';
                            if ($row['status'] == 'Processing') $statusClass = 'status-processing';
                        ?>
                        <tr>
                            <td><strong>#<?= $row['id'] ?></strong></td>
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
                            <td>
                                <span class="status-badge <?= $statusClass ?>">
                                    <?= htmlspecialchars($row['status']) ?>
                                </span>
                            </td>
                            <td><?= date('d M Y • H:i', strtotime($row['created_at'])) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align:center; color:var(--secondary); padding: 2rem;">
                    <i class="fas fa-clipboard-list" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                    No recent reports found
                </p>
            <?php endif; ?>
            
            <div style="text-align: center; margin-top: 1.5rem;">
                <a href="view_reports.php" class="quick-link" style="padding: 0.5rem 1.5rem;">
                    <i class="fas fa-list"></i> View All Reports
                </a>
            </div>
        </div>

        <!-- Quick Actions Section -->
        <div class="section">
            <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem; margin-top: 1rem;">
                <a href="add_student.php" class="quick-link" style="justify-content: center; padding: 1rem;">
                    <i class="fas fa-user-plus"></i> Add Student
                </a>
                <a href="add_block.php" class="quick-link" style="justify-content: center; padding: 1rem;">
                    <i class="fas fa-building"></i> Add Block
                </a>
                <a href="generate_otp.php" class="quick-link" style="justify-content: center; padding: 1rem;">
                    <i class="fas fa-key"></i> Generate OTP
                </a>

            </div>
        </div>
    </div>

    <script>
        // Add active class to current page link
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = window.location.pathname.split('/').pop();
            const navLinks = document.querySelectorAll('.nav-link');
            
            navLinks.forEach(link => {
                const linkPage = link.getAttribute('href');
                if (currentPage === linkPage) {
                    link.classList.add('active');
                } else {
                    link.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>