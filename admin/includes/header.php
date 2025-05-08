<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ExamSafe Admin' : 'ExamSafe Admin'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <?php if (isset($extraCss)): ?>
        <?php foreach ($extraCss as $css): ?>
            <link rel="stylesheet" href="<?php echo $css; ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/js/all.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="../../assets/js/admin.js"></script>
    <?php if (isset($extraJs)): ?>
        <?php foreach ($extraJs as $js): ?>
            <script src="<?php echo $js; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/fr.js"></script>
    <link rel="stylesheet" href="../assets/css/admin.css">
    
    
    <style>
        /* Styles pour le menu responsive */
        .sidebar-toggle {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            /* z-index: 1100; */
            background: #2c3e50;
            color: white;
            border: none;
            border-radius: 5px;
            padding: 10px;
            font-size: 1.2rem;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        .admin-sidebar {
            transition: transform 0.3s ease;
            z-index: 1000;
        }
        
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 999;
            display: none;
        }
        
        @media (max-width: 992px) {
            .sidebar-toggle {
                display: block;
            }
            
            .admin-sidebar {
                transform: translateX(-100%);
                position: fixed;
                height: 100vh;
            }
            
            .admin-sidebar.show {
                transform: translateX(0);
                box-shadow: 5px 0 15px rgba(0,0,0,0.2);
            }
            
            .admin-content {
                margin-left: 0 !important;
            }
            
            .admin-sidebar.show + .sidebar-overlay {
                display: block;
            }
            
            .search-bar {
                width: 100% !important;
                margin-left: 0 !important;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Bouton pour ouvrir/fermer le menu -->
        
        
        <!-- Sidebar -->
      <?php include'sidebar.php'?>
        <!-- Overlay pour fermer le menu en cliquant à côté -->
        <div class="sidebar-overlay"></div>
        <button class="sidebar-toggle" id="sidebar-toggle"  style="margin-top: -5px;">
            <i class="fas fa-bars"></i>
        </button>
        <div class="admin-content">
            <header class="admin-header" style="margin-top: -20px;">
                <div class="search-bar" style="margin-left: -20px;height: 50px;width: 400px;">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Rechercher...">
                </div>
                
                <div class="header-actions">
                    <div class="notifications">
                        <i class="fas fa-bell notifications-icon"></i>
                        <span class="notifications-badge">3</span>
                    </div>
                    
                    <div class="user-profile">
                        <img src="../assets/images/avatar.png" alt="Avatar" class="user-avatar">
                        <div class="user-info">
                            <span class="user-name"><a href="profile.php"><?php echo isset($_SESSION['username']) ? $_SESSION['username'] : 'Admin'; ?></a></span>
                        </div>
                    </div>
                </div>
            </header>
            
            <main class="main-content">
            
            <script>
                // Gestion du menu responsive
                document.addEventListener('DOMContentLoaded', function() {
                    const sidebarToggle = document.getElementById('sidebar-toggle');
                    const sidebar = document.getElementById('adminSidebar');
                    const overlay = document.querySelector('.sidebar-overlay');
                    
                    // Toggle sidebar
                    sidebarToggle.addEventListener('click', function() {
                        sidebar.classList.toggle('show');
                        overlay.style.display = sidebar.classList.contains('show') ? 'block' : 'none';
                    });
                    
                    // Fermer le sidebar en cliquant sur l'overlay
                    overlay.addEventListener('click', function() {
                        sidebar.classList.remove('show');
                        overlay.style.display = 'none';
                    });
                    
                    // Fermer le sidebar en cliquant sur un lien
                    document.querySelectorAll('.menu-link').forEach(link => {
                        link.addEventListener('click', function() {
                            if (window.innerWidth < 992) {
                                sidebar.classList.remove('show');
                                overlay.style.display = 'none';
                            }
                        });
                    });
                    
                    // Ajuster le comportement au redimensionnement
                    window.addEventListener('resize', function() {
                        if (window.innerWidth >= 992) {
                            sidebar.classList.remove('show');
                            overlay.style.display = 'none';
                        }
                    });
                });
            </script>