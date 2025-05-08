<?php
// Vérifier si l'utilisateur est connecté et est un enseignant
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
  header('Location: ../login.php');
  exit();
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo isset($pageTitle) ? $pageTitle : 'ExamSafe'; ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/fr.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/teacher.css">
  <script src="../../assets/js/main.js"></script>
  <script src="../../assets/js/dashboard.js"></script>
  <?php if (isset($extraCss)): ?>
    <?php foreach ($extraCss as $css): ?>
      <link rel="stylesheet" href="<?php echo $css; ?>">
    <?php endforeach; ?>
  <?php endif; ?>
  
  <style>
    /* Styles pour le menu responsive */
    .sidebar-toggle {
      display: none;
      background: none;
      border: none;
      font-size: 1.5rem;
      color: #2c3e50;
      cursor: pointer;
      margin-right: 15px;
    }
    
    .sidebar {
      transition: transform 0.3s ease;
    }
    
    @media (max-width: 992px) {
      .sidebar-toggle {
        display: block;
      }
      
      .sidebar {
        transform: translateX(-270px);
        position: fixed;
        z-index: 1000;
        height: 100vh;
      }
      
      .sidebar.show {
        transform: translateX(0);
        box-shadow: 5px 0 15px rgba(0,0,0,0.2);
      }
      
      .main-content {
        margin-left: 0 !important;
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
      
      .sidebar.show + .sidebar-overlay {
        display: block;
      }
    }
  </style>
</head>

<body>
  <div class="app-container">
    <!-- Sidebar -->
   <?php include'sidebar.php'?>
    
    <!-- Overlay pour fermer le menu en cliquant à côté -->
    <div class="sidebar-overlay"></div>

    <!-- Main Content -->
    <main class="main-content">
      <!-- Header -->
      <header class="header">
        <div class="header-left">
          <button class="sidebar-toggle" id="sidebar-toggle">
            <i class="fas fa-bars"></i>
          </button>
          <h1 class="page-title"><?php echo isset($pageTitle) ? $pageTitle : 'Tableau de bord'; ?></h1>
        </div>

        <div class="header-right">
          <div class="search-box">
            <input type="text" placeholder="Rechercher..." style="border-radius: 5px;height: 40px;width: 400px;">
            <i class="fas fa-search"></i>
          </div>

          <div class="notifications">
            <button class="notification-btn">
              <i class="fas fa-bell"></i>
              <span class="badge">3</span>
            </button>
          </div>

          <div class="user-profile">
            <img src="../assets/images/avatar.png" alt="Avatar" class="avatar">
            <div class="user-info">
              <span class="user-name">
                <?php echo isset($_SESSION['username']) ? $_SESSION['username'] : 'Teacher'; ?></span>
              <span class="user-role">Enseignant</span>
            </div>
          </div>
        </div>
      </header>

      <!-- Script pour gérer le menu responsive -->
      <script>
        document.addEventListener('DOMContentLoaded', function() {
          const sidebarToggle = document.getElementById('sidebar-toggle');
          const sidebar = document.querySelector('.sidebar');
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
          document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', function() {
              if (window.innerWidth < 992) {
                sidebar.classList.remove('show');
                overlay.style.display = 'none';
              }
            });
          });
          
          // Ajuster le comportement au redimensionnement de la fenêtre
          window.addEventListener('resize', function() {
            if (window.innerWidth >= 992) {
              sidebar.classList.remove('show');
              overlay.style.display = 'none';
            }
          });
        });
      </script>