<?php
// Définir la fonction getNotificationIcon
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit();
}
// Définir la fonction getTimeAgo
function getTimeAgo($datetime)
{
    $time = strtotime($datetime);
    $timeDifference = time() - $time;

    if ($timeDifference < 60) {
        return 'Il y a quelques secondes';
    } elseif ($timeDifference < 3600) {
        return 'Il y a ' . floor($timeDifference / 60) . ' minutes';
    } elseif ($timeDifference < 86400) {
        return 'Il y a ' . floor($timeDifference / 3600) . ' heures';
    } elseif ($timeDifference < 604800) {
        return 'Il y a ' . floor($timeDifference / 86400) . ' jours';
    } else {
        return date('d/m/Y', $time);
    }
}
function getNotificationIcon($type)
{
    $icons = [
        'message' => 'envelope',
        'alert' => 'exclamation-circle',
        'reminder' => 'clock',
        'default' => 'bell'
    ];
    return isset($icons[$type]) ? $icons[$type] : $icons['default'];
}

// Vérifier si l'utilisateur est connecté et est un étudiant
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

// Récupérer les informations de l'utilisateur
$userId = $_SESSION['user_id'];
$userQuery = $conn->prepare("SELECT * FROM users WHERE id = ?");
$userQuery->bind_param("i", $userId);
$userQuery->execute();
$user = $userQuery->get_result()->fetch_assoc();

// Récupérer les notifications non lues
$notificationsQuery = $conn->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ? AND is_read = 0 
    ORDER BY created_at DESC 
    LIMIT 5
");
$notificationsQuery->bind_param("i", $userId);
$notificationsQuery->execute();
$notifications = $notificationsQuery->get_result();
$notification = $notifications->fetch_assoc();
$notificationsCount = $notifications->num_rows;

// Définir le titre de la page si non défini
if (!isset($pageTitle)) {
    $pageTitle = "Plateforme d'examen en ligne";
}

// Définir les fichiers CSS et JS supplémentaires
if (!isset($extraCss)) {
    $extraCss = [];
}
if (!isset($extraJs)) {
    $extraJs = [];
}

// Vérifier si la navigation doit être cachée
$hideNavigation = isset($hideNavigation) && $hideNavigation === true;
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> | Plateforme d'examen en ligne</title>

    <!-- Favicon -->
    <link rel="shortcut icon" href="../assets/images/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- CSS principal -->
    <link rel="stylesheet" href="../assets/css/student.css">

    <!-- CSS supplémentaires -->
    <?php foreach ($extraCss as $css): ?>
        <link rel="stylesheet" href="<?php echo $css; ?>">
    <?php endforeach; ?>
</head>

<body>
    <?php if (!$hideNavigation): ?>
        <!-- Header -->
        <header class="header">
            <div class="header-container">
                <div class="header-left" style="display: flex;">
                    <button class="navbar-toggler" type="button"style="margin-right:2px;" >
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    <a href="index.php" class="logo">
                        <span>ExamSafe</span>
                    </a>
                </div>

                <div class="header-right">
                    <!-- Notifications -->
                    <div class="notifications">
                        <button class="notifications-btn" id="notificationsBtn">
                            <i class="fas fa-bell"></i>
                            <?php if ($notificationsCount > 0): ?>
                                <div class="notification-time"><?php echo getTimeAgo($notification['created_at']); ?></div>
                            <?php endif; ?>
                        </button>

                        <div class="notifications-dropdown" id="notificationsDropdown">
                            <div class="notification-header">
                                <span class="notification-title">Notifications</span>
                                <span class="mark-all-read">Tout marquer comme lu</span>
                            </div>

                            <?php if ($notificationsCount > 0): ?>
                                <?php while ($notification = $notifications->fetch_assoc()): ?>
                                    <div class="notification-item unread">
                                        <div class="notification-icon">
                                            <i class="fas fa-<?php echo getNotificationIcon($notification['type']); ?>"></i>
                                        </div>
                                        <div class="notification-content">
                                            <div class="notification-message"><?php echo $notification['message']; ?></div>
                                            <div class="notification-time"><?php echo getTimeAgo($notification['created_at']); ?></div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="notification-item">
                                    <div class="notification-content">
                                        <div class="notification-message">Aucune nouvelle notification</div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="notification-footer">
                                <a href="notifications.php" class="view-all">Voir toutes les notifications</a>
                            </div>
                        </div>
                    </div>

                    <!-- User Menu -->
                    <div class="user-menu">
                        <button class="user-menu-btn" id="userMenuBtn">
                            <img src="../<?php echo $user['profile_image']; ?>" alt="Avatar" class="user-avatar">
                            <span class="user-name"><?php echo $user['first_name']; ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </button>

                        <div class="user-menu-dropdown" id="userMenuDropdown">
                            <a href="profile.php" class="user-menu-item">
                                <i class="fas fa-user"></i>
                                Mon profil
                            </a>
                            <a href="settings.php" class="user-menu-item">
                                <i class="fas fa-cog"></i>
                                Paramètres
                            </a>
                            <a href="help.php" class="user-menu-item">
                                <i class="fas fa-question-circle"></i>
                                Aide
                            </a>
                            <a href="../logout.php" class="user-menu-item">
                                <i class="fas fa-sign-out-alt"></i>
                                Déconnexion
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="menu-category">Menu</div>

            <nav class="sidebar-menu">
                <a href="index.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i>
                    Tableau de bord
                </a>

                <a href="exams.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'exams.php' ? 'active' : ''; ?>">
                    <i class="fas fa-file-alt"></i>
                    Mes examens
                    <?php
                    // Récupérer le nombre d'examens disponibles
                    $availableExamsQuery = $conn->prepare("
                    SELECT COUNT(*) as count 
                    FROM exams e 
                    JOIN exam_classes ec ON e.id = ec.exam_id 
                    JOIN user_classes cs ON ec.class_id = cs.class_id 
                    WHERE cs.user_id = ? 
                    AND e.status = 'published' 
                    AND e.start_date <= NOW() 
                    AND e.end_date >= NOW()
                ");
                    $availableExamsQuery->bind_param("i", $userId);
                    $availableExamsQuery->execute();
                    $availableExamsCount = $availableExamsQuery->get_result()->fetch_assoc()['count'];

                    if ($availableExamsCount > 0):
                    ?>
                        <span class="menu-badge"><?php echo $availableExamsCount; ?></span>
                    <?php endif; ?>
                </a>

                <a href="results.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'results.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar"></i>
                    Mes résultats
                </a>

                <div class="menu-category">Mon compte</div>

                <a href="profile.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user"></i>
                    Profil
                </a>

                <a href="settings.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
                    <i class="fas fa-cog"></i>
                    Paramètres
                </a>
                <a href="logout.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'logout.php' ? 'active' : ''; ?>">
                    <i class="fas fa-sign-out-alt"></i>
                    Deconnexion
                </a>

                <div class="menu-category">Support</div>

                <a href="help.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'help.php' ? 'active' : ''; ?>">
                    <i class="fas fa-question-circle"></i>
                    Aide
                </a>

                <a href="contact.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'contact.php' ? 'active' : ''; ?>">
                    <i class="fas fa-envelope"></i>
                    Contact
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="content-wrapper" id="content">
            <?php
            // Afficher les messages flash s'ils existent
            if (function_exists('displayFlashMessages')) {
                displayFlashMessages();
            }
            ?>
        <?php endif; ?>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Gestion du bouton menu mobile
                const navbarToggler = document.querySelector('.navbar-toggler');
                const sidebar = document.querySelector('.sidebar');

                if (navbarToggler && sidebar) {
                    navbarToggler.addEventListener('click', function() {
                        sidebar.classList.toggle('show');
                    });
                }

                // Fermer le menu quand on clique sur un lien
                const navLinks = document.querySelectorAll('.nav-link');
                navLinks.forEach(function(link) {
                    link.addEventListener('click', function() {
                        if (window.innerWidth < 992) {
                            sidebar.classList.remove('show');
                        }
                    });
                });
            });
        </script>

        <style>
            .navbar-toggler {
                display: none;
                /* Caché par défaut sur grands écrans */
                background-color: #343a40;
                border: 1px solid rgba(255, 255, 255, 0.1);
                padding: 0.25rem 0.75rem;
                font-size: 1.25rem;
                line-height: 1;
                border-radius: 0.25rem;
                
            }

            .navbar-toggler-icon {
            justify-content: center; 
                display: inline-block;
                width: 1.2em;
                height: 1.2em;
                vertical-align: middle;
                background: no-repeat center center;
                background-size: 100% 100%;
                background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' width='30' height='30' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 0.5%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
            }

            @media (max-width: 992px) {
                .navbar-toggler {
                    display: block;
                    /* Visible sur petits écrans */
                }

                .sidebar {
                    display: none;
                    /* Menu caché par défaut sur mobile */
                }

                .sidebar.show {
                    display: block;
                    /* Menu visible quand la classe show est ajoutée */
                }
            }
        </style>