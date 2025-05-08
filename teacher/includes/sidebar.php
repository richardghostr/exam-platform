<!-- Sidebar -->
<aside class="sidebar" style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
  width: 270px;
  height: 100vh;
  position: fixed;
  left: 0;
  top: 0;
  box-shadow: 2px 0 10px rgba(0, 0, 0, 0.2);
  color: #ecf0f1;
;
  " >

    <div class="sidebar-header">
        
        <div class="logo">
            <img src="../assets/images/logo.png" alt="ExamSafe Logo" height="80px">
            <span>ExamSafe</span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="sidebar-title" style="margin-left: 20px;margin-top: 15px;margin-bottom: 5px;">MENU</div>
        <ul style="margin: 5px;">
            <li class="nav-item ">
                <a href="index.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Tableau de bord</span>
                </a>
            </li>
            <li class="nav-item ">
                <a href="create-exam.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'create-exam.php' ? 'active' : ''; ?>">
                    <i class="fas fa-plus-circle"></i>
                    <span>Créer un examen</span>
                </a>
            </li>
            <li class="nav-item ">
                <a href="manage-exams.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage-exams.php' ? 'active' : ''; ?>">
                    <i class="fas fa-file-alt"></i>
                    <span>Gérer les examens</span>
                </a>
            </li>
            <li class="nav-item ">
                <a href="grade-exams.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'grade-exams.php' ? 'active' : ''; ?>">
                    <i class="fas fa-check-circle"></i>
                    <span>Noter les examens</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="proctoring-incidents.php" class="nav-link <?php echo $activeMenu === 'proctoring' ? 'active' : ''; ?>">
                    <i class="nav-icon fas fa-exclamation-triangle"></i>
                    <p>Incidents de surveillance</p>
                </a>
            </li>
            <li class="nav-item ">
                <a href="reports.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar"></i>
                    <span>Rapports</span>
                </a>
            </li>
        </ul>
        <div class="sidebar-title" style="margin: 20px;">AUTRES</div>
        <ul style="margin: 5px;">

            <li class="nav-item ">
                <a href="profile.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user"></i>
                    <span>Mon profil</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="../logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Déconnexion</span>
                </a>
            </li>
        </ul>
    </nav>
</aside>