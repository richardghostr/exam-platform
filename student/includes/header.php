<?php
// Définir la fonction getNotificationIcon
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
  header('Location: ../login.php');
  exit();
}
// Définir la fonction getTimeAgo
function getTimeAgo($datetime) {
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
function getNotificationIcon($type) {
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
    <link rel="shortcut icon" href="../assets/images/favicon.ico" type="image/x-icon">
    
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
<style>
  /* ========== VARIABLES ========== */
:root {
  --primary-color: #4361ee;
  --primary-light: #4895ef;
  --primary-dark: #3f37c9;
  --secondary-color: #4cc9f0;
  --success-color: #4caf50;
  --danger-color: #f44336;
  --warning-color: #ff9800;
  --info-color: #2196f3;
  --dark-color: #212529;
  --light-color: #f8f9fa;
  --gray-100: #f8f9fa;
  --gray-200: #e9ecef;
  --gray-300: #dee2e6;
  --gray-400: #ced4da;
  --gray-500: #adb5bd;
  --gray-600: #6c757d;
  --gray-700: #495057;
  --gray-800: #343a40;
  --gray-900: #212529;
  --font-family: "Poppins", sans-serif;
  --border-radius: 0.5rem;
  --box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
  --transition: all 0.3s ease;
}

/* ========== RESET & BASE STYLES ========== */
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: var(--font-family);
  background-color: #f5f7fb;
  color: var(--gray-800);
  line-height: 1.6;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

a {
  text-decoration: none;
  color: var(--primary-color);
  transition: var(--transition);
}

a:hover {
  color: var(--primary-dark);
}

ul {
  list-style: none;
}

img {
  max-width: 100%;
}

/* ========== LAYOUT ========== */
.container {
  width: 100%;
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 1rem;
}

.main-wrapper {
  display: flex;
  min-height: 100vh;
}

.content-wrapper {
  flex: 1;
  padding: 2rem;
  margin-left: 250px;
  transition: var(--transition);
}

.content-wrapper.full-width {
  margin-left: 0;
}

/* ========== HEADER ========== */
.header {
  background-color: #fff;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 1000;
  height: 70px;
  display: flex;
  align-items: center;
  padding: 0 2rem;
}

.header-container {
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 100%;
}

.logo {
  display: flex;
  align-items: center;
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--primary-color);
}

.logo img {
  height: 40px;
  margin-right: 0.5rem;
}

.header-right {
  display: flex;
  align-items: center;
}

.nav-toggle {
  display: none;
  background: none;
  border: none;
  font-size: 1.5rem;
  color: var(--gray-700);
  cursor: pointer;
  margin-right: 1rem;
}

.user-menu {
  position: relative;
}

.user-menu-btn {
  display: flex;
  align-items: center;
  background: none;
  border: none;
  cursor: pointer;
  padding: 0.5rem;
  border-radius: var(--border-radius);
  transition: var(--transition);
}

.user-menu-btn:hover {
  background-color: var(--gray-100);
}

.user-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  object-fit: cover;
  margin-right: 0.5rem;
}

.user-name {
  font-weight: 600;
  margin-right: 0.5rem;
}

.user-menu-dropdown {
  position: absolute;
  top: 100%;
  right: 0;
  background-color: #fff;
  border-radius: var(--border-radius);
  box-shadow: var(--box-shadow);
  min-width: 200px;
  z-index: 1000;
  display: none;
}

.user-menu-dropdown.show {
  display: block;
}

.user-menu-item {
  padding: 0.75rem 1rem;
  display: flex;
  align-items: center;
  color: var(--gray-800);
  transition: var(--transition);
}

.user-menu-item:hover {
  background-color: var(--gray-100);
}

.user-menu-item i {
  margin-right: 0.5rem;
  width: 20px;
  text-align: center;
}

.notifications {
  position: relative;
  margin-right: 1.5rem;
}

.notifications-btn {
  background: none;
  border: none;
  font-size: 1.25rem;
  color: var(--gray-700);
  cursor: pointer;
  padding: 0.5rem;
  border-radius: 50%;
  transition: var(--transition);
}

.notifications-btn:hover {
  background-color: var(--gray-100);
}

.notifications-badge {
  position: absolute;
  top: 0;
  right: 0;
  background-color: var(--danger-color);
  color: white;
  font-size: 0.75rem;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.notifications-dropdown {
  position: absolute;
  top: 100%;
  right: 0;
  background-color: #fff;
  border-radius: var(--border-radius);
  box-shadow: var(--box-shadow);
  min-width: 300px;
  z-index: 1000;
  display: none;
  max-height: 400px;
  overflow-y: auto;
}

.notifications-dropdown.show {
  display: block;
}

.notification-header {
  padding: 1rem;
  border-bottom: 1px solid var(--gray-200);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.notification-title {
  font-weight: 600;
}

.mark-all-read {
  font-size: 0.875rem;
  color: var(--primary-color);
  cursor: pointer;
}

.notification-item {
  padding: 1rem;
  border-bottom: 1px solid var(--gray-200);
  display: flex;
  align-items: flex-start;
  transition: var(--transition);
}

.notification-item:hover {
  background-color: var(--gray-100);
}

.notification-item.unread {
  background-color: rgba(67, 97, 238, 0.05);
}

.notification-icon {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background-color: var(--primary-light);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-right: 1rem;
  flex-shrink: 0;
}

.notification-content {
  flex: 1;
}

.notification-message {
  margin-bottom: 0.25rem;
}

.notification-time {
  font-size: 0.75rem;
  color: var(--gray-600);
}

.notification-footer {
  padding: 0.75rem;
  text-align: center;
  border-top: 1px solid var(--gray-200);
}

.view-all {
  color: var(--primary-color);
  font-size: 0.875rem;
}

/* ========== SIDEBAR ========== */
.sidebar {
  width: 250px;
  background-color: #fff;
  box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
  height: 100vh;
  position: fixed;
  top: 70px;
  left: 0;
  z-index: 900;
  transition: var(--transition);
  overflow-y: auto;
}

.sidebar.collapsed {
  transform: translateX(-100%);
}

.sidebar-header {
  padding: 1.5rem;
  border-bottom: 1px solid var(--gray-200);
}

.student-info {
  display: flex;
  align-items: center;
}

.student-avatar {
  width: 50px;
  height: 50px;
  border-radius: 50%;
  object-fit: cover;
  margin-right: 1rem;
}

.student-details h4 {
  font-size: 1rem;
  margin-bottom: 0.25rem;
}

.student-details p {
  font-size: 0.875rem;
  color: var(--gray-600);
}

.sidebar-menu {
  padding: 1rem 0;
}

.menu-category {
  font-size: 0.75rem;
  text-transform: uppercase;
  color: var(--gray-600);
  padding: 0.75rem 1.5rem;
  margin-top: 1rem;
}

.menu-item {
  display: block;
  padding: 0.75rem 1.5rem;
  color: var(--gray-700);
  display: flex;
  align-items: center;
  transition: var(--transition);
}

.menu-item:hover,
.menu-item.active {
  background-color: rgba(67, 97, 238, 0.1);
  color: var(--primary-color);
}

.menu-item i {
  margin-right: 0.75rem;
  width: 20px;
  text-align: center;
}

.menu-badge {
  margin-left: auto;
  background-color: var(--primary-color);
  color: white;
  font-size: 0.75rem;
  padding: 0.25rem 0.5rem;
  border-radius: 10px;
}

/* ========== DASHBOARD ========== */
.page-header {
  margin-bottom: 2rem;
}

.page-title {
  font-size: 1.75rem;
  font-weight: 700;
  margin-bottom: 0.5rem;
  color: var(--gray-900);
}

.page-subtitle {
  color: var(--gray-600);
}

.stats-container {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.stat-card {
  background-color: #fff;
  border-radius: var(--border-radius);
  box-shadow: var(--box-shadow);
  padding: 1.5rem;
  display: flex;
  flex-direction: column;
}

.stat-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1rem;
}

.stat-title {
  font-size: 1rem;
  color: var(--gray-600);
}

.stat-icon {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
}

.stat-icon.blue {
  background-color: rgba(67, 97, 238, 0.1);
  color: var(--primary-color);
}

.stat-icon.green {
  background-color: rgba(76, 175, 80, 0.1);
  color: var(--success-color);
}

.stat-icon.orange {
  background-color: rgba(255, 152, 0, 0.1);
  color: var(--warning-color);
}

.stat-icon.red {
  background-color: rgba(244, 67, 54, 0.1);
  color: var(--danger-color);
}

.stat-value {
  font-size: 2rem;
  font-weight: 700;
  margin-bottom: 0.5rem;
}

.stat-description {
  font-size: 0.875rem;
  color: var(--gray-600);
}

.trend-up {
  color: var(--success-color);
}

.trend-down {
  color: var(--danger-color);
}

/* ========== CARDS & SECTIONS ========== */
.card {
  background-color: #fff;
  border-radius: var(--border-radius);
  box-shadow: var(--box-shadow);
  margin-bottom: 2rem;
  overflow: hidden;
}

.card-header {
  padding: 1.5rem;
  border-bottom: 1px solid var(--gray-200);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.card-title {
  font-size: 1.25rem;
  font-weight: 600;
  margin: 0;
}

.card-actions {
  display: flex;
  gap: 0.5rem;
}

.card-body {
  padding: 1.5rem;
}

.card-footer {
  padding: 1rem 1.5rem;
  border-top: 1px solid var(--gray-200);
  background-color: var(--gray-100);
}

.section-title {
  font-size: 1.25rem;
  font-weight: 600;
  margin-bottom: 1.5rem;
  color: var(--gray-900);
}

/* ========== EXAMS LIST ========== */
.exams-filter {
  display: flex;
  gap: 1rem;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
}

.filter-group {
  flex: 1;
  min-width: 200px;
}

.filter-label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
}

.filter-select,
.filter-input {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid var(--gray-300);
  border-radius: var(--border-radius);
  font-family: var(--font-family);
  transition: var(--transition);
}

.filter-select:focus,
.filter-input:focus {
  outline: none;
  border-color: var(--primary-color);
  box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
}

.exams-list {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 1.5rem;
}

.exam-card {
  background-color: #fff;
  border-radius: var(--border-radius);
  box-shadow: var(--box-shadow);
  overflow: hidden;
  transition: var(--transition);
  display: flex;
  flex-direction: column;
  height: 100%;
}

.exam-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
}

.exam-header {
  padding: 1.5rem;
  border-bottom: 1px solid var(--gray-200);
}

.exam-subject {
  display: inline-block;
  padding: 0.25rem 0.75rem;
  background-color: rgba(67, 97, 238, 0.1);
  color: var(--primary-color);
  border-radius: 20px;
  font-size: 0.75rem;
  margin-bottom: 0.75rem;
}

.exam-title {
  font-size: 1.25rem;
  font-weight: 600;
  margin-bottom: 0.5rem;
}

.exam-info {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
  margin-bottom: 0.5rem;
}

.exam-info-item {
  display: flex;
  align-items: center;
  font-size: 0.875rem;
  color: var(--gray-600);
}

.exam-info-item i {
  margin-right: 0.5rem;
  width: 16px;
  text-align: center;
}

.exam-body {
  padding: 1.5rem;
  flex: 1;
}

.exam-description {
  margin-bottom: 1rem;
  color: var(--gray-700);
}

.exam-meta {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.exam-date {
  font-size: 0.875rem;
  color: var(--gray-600);
}

.exam-status {
  padding: 0.25rem 0.75rem;
  border-radius: 20px;
  font-size: 0.75rem;
  font-weight: 500;
}

.status-upcoming {
  background-color: rgba(33, 150, 243, 0.1);
  color: var(--info-color);
}

.status-available {
  background-color: rgba(76, 175, 80, 0.1);
  color: var(--success-color);
}

.status-completed {
  background-color: rgba(108, 117, 125, 0.1);
  color: var(--gray-600);
}

.status-missed {
  background-color: rgba(244, 67, 54, 0.1);
  color: var(--danger-color);
}

.exam-progress {
  height: 6px;
  background-color: var(--gray-200);
  border-radius: 3px;
  margin-bottom: 0.5rem;
  overflow: hidden;
}

.progress-bar {
  height: 100%;
  background-color: var(--primary-color);
  border-radius: 3px;
}

.progress-text {
  font-size: 0.75rem;
  color: var(--gray-600);
  text-align: right;
}

.exam-footer {
  padding: 1rem 1.5rem;
  border-top: 1px solid var(--gray-200);
  background-color: var(--gray-100);
}

.btn {
  display: inline-block;
  font-weight: 500;
  text-align: center;
  white-space: nowrap;
  vertical-align: middle;
  -webkit-user-select: none;
  user-select: none;
  border: 1px solid transparent;
  padding: 0.75rem 1.5rem;
  font-size: 1rem;
  line-height: 1.5;
  border-radius: var(--border-radius);
  transition: var(--transition);
  cursor: pointer;
}

.btn-primary {
  background-color: var(--primary-color);
  border-color: var(--primary-color);
  color: white;
}

.btn-primary:hover {
  background-color: var(--primary-dark);
  border-color: var(--primary-dark);
  color: white;
}

.btn-outline-primary {
  background-color: transparent;
  border-color: var(--primary-color);
  color: var(--primary-color);
}

.btn-outline-primary:hover {
  background-color: var(--primary-color);
  color: white;
}

.btn-success {
  background-color: var(--success-color);
  border-color: var(--success-color);
  color: white;
}

.btn-success:hover {
  background-color: #43a047;
  border-color: #43a047;
}

.btn-danger {
  background-color: var(--danger-color);
  border-color: var(--danger-color);
  color: white;
}

.btn-danger:hover {
  background-color: #e53935;
  border-color: #e53935;
}

.btn-sm {
  padding: 0.5rem 1rem;
  font-size: 0.875rem;
}

.btn-block {
  display: block;
  width: 100%;
}

/* ========== RESULTS PAGE ========== */
.results-tabs {
  display: flex;
  border-bottom: 1px solid var(--gray-300);
  margin-bottom: 1.5rem;
}

.tab-item {
  padding: 1rem 1.5rem;
  font-weight: 500;
  color: var(--gray-600);
  cursor: pointer;
  border-bottom: 2px solid transparent;
  transition: var(--transition);
}

.tab-item:hover {
  color: var(--primary-color);
}

.tab-item.active {
  color: var(--primary-color);
  border-bottom-color: var(--primary-color);
}

.results-table {
  width: 100%;
  border-collapse: collapse;
}

.results-table th,
.results-table td {
  padding: 1rem;
  text-align: left;
  border-bottom: 1px solid var(--gray-200);
}

.results-table th {
  font-weight: 600;
  color: var(--gray-700);
  background-color: var(--gray-100);
}

.results-table tr:hover {
  background-color: var(--gray-100);
}

.score {
  font-weight: 600;
}

.score-high {
  color: var(--success-color);
}

.score-medium {
  color: var(--warning-color);
}

.score-low {
  color: var(--danger-color);
}

.badge {
  display: inline-block;
  padding: 0.25rem 0.75rem;
  border-radius: 20px;
  font-size: 0.75rem;
  font-weight: 500;
}

.badge-success {
  background-color: rgba(76, 175, 80, 0.1);
  color: var(--success-color);
}

.badge-warning {
  background-color: rgba(255, 152, 0, 0.1);
  color: var(--warning-color);
}

.badge-danger {
  background-color: rgba(244, 67, 54, 0.1);
  color: var(--danger-color);
}

.badge-info {
  background-color: rgba(33, 150, 243, 0.1);
  color: var(--info-color);
}

.badge-secondary {
  background-color: rgba(108, 117, 125, 0.1);
  color: var(--gray-600);
}

/* ========== PROFILE PAGE ========== */
.profile-header {
  display: flex;
  align-items: center;
  margin-bottom: 2rem;
}

.profile-avatar {
  width: 100px;
  height: 100px;
  border-radius: 50%;
  object-fit: cover;
  margin-right: 2rem;
}

.profile-info h2 {
  font-size: 1.5rem;
  margin-bottom: 0.5rem;
}

.profile-info p {
  color: var(--gray-600);
}

.profile-tabs {
  display: flex;
  border-bottom: 1px solid var(--gray-300);
  margin-bottom: 1.5rem;
}

.profile-tab {
  padding: 1rem 1.5rem;
  font-weight: 500;
  color: var(--gray-600);
  cursor: pointer;
  border-bottom: 2px solid transparent;
  transition: var(--transition);
}

.profile-tab:hover {
  color: var(--primary-color);
}

.profile-tab.active {
  color: var(--primary-color);
  border-bottom-color: var(--primary-color);
}

.form-group {
  margin-bottom: 1.5rem;
}

.form-label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
}

.form-control {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid var(--gray-300);
  border-radius: var(--border-radius);
  font-family: var(--font-family);
  transition: var(--transition);
}

.form-control:focus {
  outline: none;
  border-color: var(--primary-color);
  box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
}

.form-text {
  font-size: 0.875rem;
  color: var(--gray-600);
  margin-top: 0.25rem;
}

.form-row {
  display: flex;
  flex-wrap: wrap;
  margin: -0.5rem;
}

.form-col {
  flex: 1;
  padding: 0.5rem;
  min-width: 200px;
}

/* ========== TAKE EXAM PAGE ========== */
.exam-container {
  background-color: #fff;
  border-radius: var(--border-radius);
  box-shadow: var(--box-shadow);
  margin-bottom: 2rem;
}

.exam-header {
  padding: 1.5rem;
  border-bottom: 1px solid var(--gray-200);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.exam-info h1 {
  font-size: 1.5rem;
  margin-bottom: 0.5rem;
}

.exam-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  color: var(--gray-600);
  font-size: 0.875rem;
}

.separator {
  color: var(--gray-400);
}

.exam-timer {
  display: flex;
  align-items: center;
  padding: 0.75rem 1.5rem;
  background-color: var(--gray-100);
  border-radius: var(--border-radius);
}

.exam-timer.warning {
  background-color: rgba(255, 152, 0, 0.1);
  color: var(--warning-color);
}

.exam-timer.danger {
  background-color: rgba(244, 67, 54, 0.1);
  color: var(--danger-color);
}

.timer-icon {
  margin-right: 0.75rem;
}

.timer-display {
  font-size: 1.25rem;
  font-weight: 600;
}

.proctoring-bar {
  padding: 0.75rem 1.5rem;
  background-color: rgba(33, 150, 243, 0.1);
  color: var(--info-color);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.proctoring-status {
  display: flex;
  align-items: center;
}

.proctoring-status i {
  margin-right: 0.5rem;
}

.webcam-container {
  position: fixed;
  bottom: 1rem;
  right: 1rem;
  width: 200px;
  height: 150px;
  border-radius: var(--border-radius);
  overflow: hidden;
  box-shadow: var(--box-shadow);
  z-index: 900;
}

.webcam-container video {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.exam-body {
  display: flex;
}

.questions-navigation {
  width: 250px;
  padding: 1.5rem;
  border-right: 1px solid var(--gray-200);
}

.navigation-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.progress-info {
  font-size: 0.875rem;
  color: var(--gray-600);
}

.questions-list {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 0.5rem;
  margin-bottom: 1.5rem;
}

.question-button {
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid var(--gray-300);
  border-radius: var(--border-radius);
  background-color: #fff;
  font-weight: 500;
  cursor: pointer;
  transition: var(--transition);
}

.question-button:hover {
  background-color: var(--gray-200);
}

.question-button.current {
  background-color: var(--primary-color);
  border-color: var(--primary-color);
  color: white;
}

.question-button.answered {
  background-color: rgba(76, 175, 80, 0.1);
  border-color: var(--success-color);
  color: var(--success-color);
}

.navigation-actions {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1.5rem;
}

.nav-button {
  flex: 1;
  padding: 0.5rem;
  border: 1px solid var(--gray-300);
  border-radius: var(--border-radius);
  background-color: #fff;
  font-size: 0.875rem;
  cursor: pointer;
  transition: var(--transition);
}

.nav-button:hover:not(:disabled) {
  background-color: var(--gray-100);
}

.nav-button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.submit-section {
  margin-top: auto;
}

.question-container {
  flex: 1;
  padding: 1.5rem;
}

.question-slide {
  display: none;
}

.question-header {
  display: flex;
  justify-content: space-between;
  margin-bottom: 1.5rem;
}

.question-number {
  font-weight: 500;
}

.question-points {
  font-weight: 500;
  color: var(--primary-color);
}

.question-content {
  margin-bottom: 2rem;
}

.question-text {
  margin-bottom: 1.5rem;
}

.options-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.option-item {
  padding: 1rem;
  border: 1px solid var(--gray-300);
  border-radius: var(--border-radius);
  transition: var(--transition);
}

.option-item:hover {
  background-color: var(--gray-100);
}

.option-label {
  display: flex;
  align-items: center;
  cursor: pointer;
}

.option-input {
  position: absolute;
  opacity: 0;
}

.option-checkbox,
.option-radio {
  width: 20px;
  height: 20px;
  border: 2px solid var(--gray-400);
  margin-right: 1rem;
  position: relative;
  transition: var(--transition);
}

.option-checkbox {
  border-radius: 4px;
}

.option-radio {
  border-radius: 50%;
}

.option-input:checked + .option-checkbox {
  background-color: var(--primary-color);
  border-color: var(--primary-color);
}

.option-input:checked + .option-checkbox::after {
  content: "";
  position: absolute;
  top: 2px;
  left: 6px;
  width: 5px;
  height: 10px;
  border: solid white;
  border-width: 0 2px 2px 0;
  transform: rotate(45deg);
}

.option-input:checked + .option-radio {
  border-color: var(--primary-color);
}

.option-input:checked + .option-radio::after {
  content: "";
  position: absolute;
  top: 4px;
  left: 4px;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background-color: var(--primary-color);
}

.essay-answer {
  width: 100%;
}

.essay-input {
  width: 100%;
  padding: 1rem;
  border: 1px solid var(--gray-300);
  border-radius: var(--border-radius);
  font-family: var(--font-family);
  resize: vertical;
  min-height: 150px;
  transition: var(--transition);
}

.essay-input:focus {
  outline: none;
  border-color: var(--primary-color);
  box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
}

.question-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.save-status {
  color: var(--success-color);
  display: none;
}

.question-navigation {
  display: flex;
  gap: 1rem;
}

/* Modal */
.modal {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.5);
  z-index: 1100;
  align-items: center;
  justify-content: center;
}

.modal-content {
  background-color: #fff;
  border-radius: var(--border-radius);
  box-shadow: var(--box-shadow);
  width: 100%;
  max-width: 600px;
  max-height: 90vh;
  overflow-y: auto;
}

.modal-header {
  padding: 1.5rem;
  border-bottom: 1px solid var(--gray-200);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header h2 {
  margin: 0;
  font-size: 1.25rem;
}

.close-modal {
  background: none;
  border: none;
  font-size: 1.5rem;
  cursor: pointer;
  color: var(--gray-600);
}

.modal-body {
  padding: 1.5rem;
}

.modal-footer {
  padding: 1rem 1.5rem;
  border-top: 1px solid var(--gray-200);
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
}

.exam-summary {
  text-align: center;
}

.summary-stats {
  display: flex;
  justify-content: space-around;
  margin: 2rem 0;
}

.stat-item {
  text-align: center;
}

.stat-value {
  font-size: 2rem;
  font-weight: 700;
  margin-bottom: 0.5rem;
}

.stat-label {
  color: var(--gray-600);
}

.warning-message {
  display: flex;
  align-items: center;
  padding: 1rem;
  background-color: rgba(255, 152, 0, 0.1);
  color: var(--warning-color);
  border-radius: var(--border-radius);
  margin-bottom: 1rem;
}

.warning-message i {
  margin-right: 0.75rem;
  font-size: 1.25rem;
}

/* Proctoring notification */
.proctoring-notification {
  position: fixed;
  top: 1rem;
  right: 1rem;
  background-color: #fff;
  border-radius: var(--border-radius);
  box-shadow: var(--box-shadow);
  padding: 1rem;
  display: flex;
  align-items: center;
  z-index: 1000;
  transform: translateX(calc(100% + 1rem));
  transition: transform 0.3s ease;
}

.proctoring-notification.show {
  transform: translateX(0);
}

.notification-icon {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background-color: var(--warning-color);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-right: 1rem;
}

.notification-title {
  font-weight: 600;
  margin-bottom: 0.25rem;
}

.notification-message {
  font-size: 0.875rem;
  color: var(--gray-600);
}

/* ========== RESPONSIVE STYLES ========== */
@media (max-width: 992px) {
  .content-wrapper {
    margin-left: 0;
    padding: 1rem;
  }

  .sidebar {
    transform: translateX(-100%);
  }

  .sidebar.show {
    transform: translateX(0);
  }

  .nav-toggle {
    display: block;
  }

  .exam-body {
    flex-direction: column;
  }

  .questions-navigation {
    width: 100%;
    border-right: none;
    border-bottom: 1px solid var(--gray-200);
  }
}

@media (max-width: 768px) {
  .header {
    padding: 0 1rem;
  }

  .user-name {
    display: none;
  }

  .page-title {
    font-size: 1.5rem;
  }

  .stats-container {
    grid-template-columns: 1fr;
  }

  .exams-list {
    grid-template-columns: 1fr;
  }

  .exam-header {
    flex-direction: column;
    align-items: flex-start;
  }

  .exam-timer {
    margin-top: 1rem;
    align-self: stretch;
  }

  .webcam-container {
    width: 150px;
    height: 112px;
  }
}

@media (max-width: 576px) {
  .questions-list {
    grid-template-columns: repeat(4, 1fr);
  }

  .profile-header {
    flex-direction: column;
    text-align: center;
  }

  .profile-avatar {
    margin-right: 0;
    margin-bottom: 1rem;
  }

  .profile-tabs {
    overflow-x: auto;
    white-space: nowrap;
  }
}

</style>
<body>
    <?php if (!$hideNavigation): ?>
    <!-- Header -->
    <header class="header">
        <div class="header-container">
            <div class="header-left">
                <button class="nav-toggle" id="navToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <a href="index.php" class="logo">
                    <img src="../assets/images/logo.png" alt="Logo">
                    <span>ExamPro</span>
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
                        <img src="<?php echo !empty($user['profile_image']) ? '../uploads/profiles/' . $user['profile_image'] : '../assets/images/default-avatar.png'; ?>" alt="Avatar" class="user-avatar">
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
        <div class="sidebar-header">
            <div class="student-info">
                <img src="<?php echo !empty($user['profile_image']) ? '../uploads/profiles/' . $user['profile_image'] : '../assets/images/default-avatar.png'; ?>" alt="Avatar" class="student-avatar">
                <div class="student-details">
                    <h4><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></h4>
                    <p>Étudiant</p>
                </div>
            </div>
        </div>
        
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
