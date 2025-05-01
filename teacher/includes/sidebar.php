<!-- Sidebar -->
<aside class="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <img src="../assets/images/logo.png" alt="ExamSafe Logo">
            <span>ExamSafe</span>
        </div>
        <button class="menu-toggle" id="menu-toggle">
            <i class="fas fa-bars"></i>
        </button>
    </div>
    
    <nav class="sidebar-nav">
        <ul>
            <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                <a href="index.php" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Tableau de bord</span>
                </a>
            </li>
            <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'create-exam.php' ? 'active' : ''; ?>">
                <a href="create-exam.php" class="nav-link">
                    <i class="fas fa-plus-circle"></i>
                    <span>Créer un examen</span>
                </a>
            </li>
            <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'manage-exams.php' ? 'active' : ''; ?>">
                <a href="manage-exams.php" class="nav-link">
                    <i class="fas fa-file-alt"></i>
                    <span>Gérer les examens</span>
                </a>
            </li>
            <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'grade-exams.php' ? 'active' : ''; ?>">
                <a href="grade-exams.php" class="nav-link">
                    <i class="fas fa-check-circle"></i>
                    <span>Noter les examens</span>
                </a>
            </li>
            <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                <a href="reports.php" class="nav-link">
                    <i class="fas fa-chart-bar"></i>
                    <span>Rapports</span>
                </a>
            </li>
            <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
                <a href="profile.php" class="nav-link">
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
