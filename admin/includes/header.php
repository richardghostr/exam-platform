<header class="admin-header">
    <div class="admin-header-left">
        <button id="sidebar-toggle" class="sidebar-toggle">
            <i class="fas fa-bars"></i>
        </button>
        <div class="search-container">
            <input type="text" placeholder="Rechercher..." class="search-input">
            <button class="search-button">
                <i class="fas fa-search"></i>
            </button>
        </div>
    </div>
    <div class="admin-header-right">
        <div class="admin-notifications">
            <button class="notification-button">
                <i class="fas fa-bell"></i>
                <?php
                // Récupérer le nombre de notifications non lues
                $notifications_count = 0; // À remplacer par une requête réelle
                if ($notifications_count > 0): ?>
                    <span class="notification-badge"><?php echo $notifications_count; ?></span>
                <?php endif; ?>
            </button>
        </div>
        <div class="admin-user-menu">
            <button class="user-menu-button">
                <?php if (!empty($_SESSION['profile_image'])): ?>
                    <img src="<?php echo htmlspecialchars($_SESSION['profile_image']); ?>" alt="Photo de profil" class="user-avatar">
                <?php else: ?>
                    <div class="user-avatar-placeholder">
                        <i class="fas fa-user"></i>
                    </div>
                <?php endif; ?>
                <span class="user-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?></span>
                <i class="fas fa-chevron-down"></i>
            </button>
            <div class="user-dropdown">
                <ul>
                    <li><a href="profile.php"><i class="fas fa-user-circle"></i> Mon profil</a></li>
                    <li><a href="settings.php"><i class="fas fa-cog"></i> Paramètres</a></li>
                    <li><a href="../api/auth.php?action=logout"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
                </ul>
            </div>
        </div>
    </div>
</header>
