<header class="header">
    <div class="container">
        <div class="logo">
            <a href="index.php">
                <h1>ExamSafe</h1>
            </a>
        </div>
        <nav class="main-nav">
            <ul>
                <li><a href="index.php" <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'class="active"' : ''; ?>>Accueil</a></li>
                <li><a href="features.php" <?php echo basename($_SERVER['PHP_SELF']) == 'features.php' ? 'class="active"' : ''; ?>>Fonctionnalités</a></li>
                <li><a href="pricing.php" <?php echo basename($_SERVER['PHP_SELF']) == 'pricing.php' ? 'class="active"' : ''; ?>>Tarifs</a></li>
                <li><a href="about.php" <?php echo basename($_SERVER['PHP_SELF']) == 'about.php' ? 'class="active"' : ''; ?>>À propos</a></li>
                <li><a href="contact.php" <?php echo basename($_SERVER['PHP_SELF']) == 'contact.php' ? 'class="active"' : ''; ?>>Contact</a></li>
            </ul>
        </nav>
        <div class="auth-buttons">
            <?php if (is_logged_in()): ?>
                <?php if (has_role('teacher')): ?>
                    <a href="teacher/index.php" class="btn btn-outline">Tableau de bord</a>
                <?php else: ?>
                    <a href="student/index.php" class="btn btn-outline">Mes examens</a>
                <?php endif; ?>
                <a href="logout.php" class="btn btn-primary">Déconnexion</a>
            <?php else: ?>
                <a href="login.php" class="btn btn-outline">Connexion</a>
                <a href="register.php" class="btn btn-primary">Inscription</a>
            <?php endif; ?>
        </div>
        <div class="mobile-menu-toggle">
            <span></span>
            <span></span>
            <span></span>
        </div>
    </div>
</header>
