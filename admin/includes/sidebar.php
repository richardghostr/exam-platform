<aside class="admin-sidebar">
    <div class="admin-sidebar-header">
        <img src="../assets/images/logo.png" alt="ExamSafe Logo" class="admin-logo">
        <h2>ExamSafe</h2>
    </div>
    
    <nav class="admin-nav">
        <ul>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : ''; ?>">
                <a href="index.php">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Tableau de bord</span>
                </a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) === 'create-exam.php' ? 'active' : ''; ?>">
                <a href="create-exam.php">
                    <i class="fas fa-plus-circle"></i>
                    <span>Créer un examen</span>
                </a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) === 'manage-exams.php' ? 'active' : ''; ?>">
                <a href="manage-exams.php">
                    <i class="fas fa-file-alt"></i>
                    <span>Gérer les examens</span>
                </a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : ''; ?>">
                <a href="users.php">
                    <i class="fas fa-users"></i>
                    <span>Utilisateurs</span>
                </a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'active' : ''; ?>">
                <a href="reports.php">
                    <i class="fas fa-chart-bar"></i>
                    <span>Rapports</span>
                </a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : ''; ?>">
                <a href="settings.php">
                    <i class="fas fa-cog"></i>
                    <span>Paramètres</span>
                </a>
            </li>
            <li>
                <a href="../api/auth.php?action=logout">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Déconnexion</span>
                </a>
            </li>
        </ul>
    </nav>
</aside>
