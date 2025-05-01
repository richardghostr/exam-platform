<aside class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-header">
        <a href="index.php" class="sidebar-logo">
            <div style="margin-left: -20px;margin-right: -20px;"><img src="logo.png" alt="logo ExamSafe" height="80px" ></div>
            <span class="logo-text">ExamSafe</span>
        </a>
    </div>
    
    <div class="sidebar-menu">
        <div class="menu-category">MENU</div>
        <ul>
            <li class="menu-item">
                <a href="index.php" class="menu-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                    <span class="menu-icon"><i class="fas fa-tachometer-alt"></i></span>
                    <span>Tableau de bord</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="create-exam.php" class="menu-link <?php echo basename($_SERVER['PHP_SELF']) == 'create-exam.php' ? 'active' : ''; ?>">
                    <span class="menu-icon"><i class="fas fa-plus-circle"></i></span>
                    <span>Créer un examen</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="manage-exams.php" class="menu-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage-exams.php' ? 'active' : ''; ?>">
                    <span class="menu-icon"><i class="fas fa-file-alt"></i></span>
                    <span>Gérer les examens</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="users.php" class="menu-link <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
                    <span class="menu-icon"><i class="fas fa-users"></i></span>
                    <span>Utilisateurs</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="reports.php" class="menu-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                    <span class="menu-icon"><i class="fas fa-chart-bar"></i></span>
                    <span>Rapports</span>
                </a>
            </li>
        </ul>
        
        <div class="menu-category">AUTRES</div>
        <ul>
            <li class="menu-item">
                <a href="settings.php" class="menu-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
                    <span class="menu-icon"><i class="fas fa-cog"></i></span>
                    <span>Paramètres</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="logout.php" class="menu-link">
                    <span class="menu-icon"><i class="fas fa-sign-out-alt"></i></span>
                    <span>Déconnexion</span>
                </a>
            </li>
        </ul>
    </div>
</aside>
