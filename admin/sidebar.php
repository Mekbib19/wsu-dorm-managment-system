<?php $current = basename($_SERVER['PHP_SELF']); ?>
<div class="sidebar">
        <div class="sidebar-header">
            <h2>
                <img src="../wsulogo.jpg" alt="WSU Logo" style="width: 40px; height: auto;">
<br><br>
                <span>WSU Dorm Admin</span>
            </h2>
        </div>
        
        <nav class="sidebar-nav">
            <div class="nav-item">
                <a href="dashboard.php" class="nav-link <?= ($current === 'dashboard.php') ? 'active' : '' ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="add_block.php" class="nav-link <?= ($current === 'add_block.php') ? 'active' : '' ?>">
                    <i class="fas fa-building"></i>
                    <span>Blocks</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="add_student.php" class="nav-link <?= ($current === 'add_student.php') ? 'active' : '' ?>">
                    <i class="fas fa-user-plus"></i>
                    <span>Add Student</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="view_students.php" class="nav-link <?= ($current === 'view_students.php') ? 'active' : '' ?>">
                    <i class="fas fa-users"></i>
                    <span>View Students</span>
                </a>
            </div>
             <div class="nav-item">
                <a href="proctor_view.php" class="nav-link <?= ($current === 'proctor_view.php') ? 'active' : '' ?>">
                    <i class="fas fa-users"></i>
                    <span>Proctor View</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="manage_proctors.php" class="nav-link <?= ($current === 'manage_proctors.php') ? 'active' : '' ?>">
                    <i class="fas fa-user-plus"></i>
                    <span>Proctors</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="view_reports.php" class="nav-link <?= ($current === 'view_reports.php') ? 'active' : '' ?>">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>Reports</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="otp_analysis.php" class="nav-link <?= ($current === 'otp_analysis.php') ? 'active' : '' ?>">
                    <i class="fas fa-key"></i>
                    <span>OTP Analysis</span>
                </a>
            </div>
            
        </nav>
        
        <div class="sidebar-footer">
            <button onclick="location.href='../logout.php'" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </button>
        </div>
    </div>

   