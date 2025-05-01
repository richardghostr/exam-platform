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
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/fr.js"></script>
  <link rel="stylesheet" href="../assets/css/teacher.css">
  <?php if (isset($extraCss)): ?>
    <?php foreach ($extraCss as $css): ?>
      <link rel="stylesheet" href="<?php echo $css; ?>">
    <?php endforeach; ?>
  <?php endif; ?>
  
</head>

<body>
  <div class="app-container">
    <?php include 'sidebar.php'; ?>

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
          <div class="search-box" >
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

      <!-- Page Content -->