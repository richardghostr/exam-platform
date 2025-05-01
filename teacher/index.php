<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Vérifier si l'utilisateur est connecté et est un enseignant
if (!isLoggedIn() || !isTeacher()) {
    header('Location: ../login.php');
    exit();
}

// Récupérer l'ID de l'enseignant
$teacherId = $_SESSION['user_id'];

// Récupérer les statistiques des examens de l'enseignant
$examStats = $conn->query("
    SELECT 
        COUNT(*) as total_exams,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_exams,
        SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_exams,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_exams
    FROM exams
    WHERE teacher_id = $teacherId
")->fetch_assoc();

// Récupérer les statistiques des étudiants
$studentStats = $conn->query("
    SELECT 
        COUNT(DISTINCT user_id) as total_students,
        AVG(score) as avg_score,
        MAX(score) as max_score,
        MIN(score) as min_score
    FROM exam_results er
    JOIN exams e ON er.exam_id = e.id
    WHERE e.teacher_id = $teacherId AND er.status = 'completed'
")->fetch_assoc();

// Récupérer les examens récents
$recentExams = $conn->query("
    SELECT 
        e.id,
        e.title,
        e.subject,
        e.status,
        e.created_at,
        COUNT(DISTINCT er.user_id) as participants,
        AVG(er.score) as avg_score
    FROM exams e
    LEFT JOIN exam_results er ON e.id = er.exam_id
    WHERE e.teacher_id = $teacherId
    GROUP BY e.id
    ORDER BY e.created_at DESC
    LIMIT 5
");

// Récupérer les examens à noter
$examsToGrade = $conn->query("
    SELECT 
        e.id,
        e.title,
        e.subject,
        COUNT(er.id) as submissions,
        COUNT(CASE WHEN er.is_graded = 0 THEN er.id ELSE NULL END) as pending_grades
    FROM exams e
    JOIN exam_results er ON e.id = er.exam_id
    WHERE e.teacher_id = $teacherId AND er.status = 'completed' AND e.has_essay = 1
    GROUP BY e.id
    HAVING pending_grades > 0
    ORDER BY e.created_at DESC
");

// Récupérer les incidents de surveillance récents
$proctorIncidents = $conn->query("
    SELECT 
        p.id,
        p.exam_id,
        e.title as exam_title,
        u.username,
        u.first_name,
        u.last_name,
        p.incident_type,
        p.timestamp,
        p.details
    FROM proctoring_incidents p
    JOIN exams e ON p.exam_id = e.id
    JOIN users u ON p.user_id = u.id
    WHERE e.teacher_id = $teacherId
    ORDER BY p.timestamp DESC
    LIMIT 5
");

$pageTitle = "Tableau de bord de l'enseignant";
include '../includes/header.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> | ExamSafe</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
   
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Styles généraux pour l'interface enseignant */
:root {
  --primary-color: #6366f1;
  --primary-light: rgba(99, 102, 241, 0.1);
  --secondary-color: #6c757d;
  --success-color: #10b981;
  --danger-color: #ef4444;
  --warning-color: #f59e0b;
  --info-color: #3b82f6;
  --light-color: #f8f9fa;
  --dark-color: #1f2937;
  --sidebar-width: 250px;
  --sidebar-collapsed-width: 70px;
  --header-height: 60px;
  --border-radius: 12px;
  --card-border-radius: 16px;
  --box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  --transition-speed: 0.3s;

  /* Couleurs des dégradés pour les cartes */
  --gradient-pink-start: #ff6b6b;
  --gradient-pink-end: #ff8787;
  --gradient-blue-start: #4facfe;
  --gradient-blue-end: #00f2fe;
  --gradient-green-start: #0ba360;
  --gradient-green-end: #3cba92;
}

* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, "Open Sans",
    "Helvetica Neue", sans-serif;
  background-color: #f5f7fb;
  color: #333;
  line-height: 1.5;
}

/* Layout d'enseignant */
.teacher-container {
  display: flex;
  min-height: 100vh;
}

.teacher-sidebar {
  width: var(--sidebar-width);
  background-color: #fff;
  box-shadow: var(--box-shadow);
  position: fixed;
  height: 100vh;
  overflow-y: auto;
  z-index: 100;
  transition: width var(--transition-speed) ease;
}

.teacher-sidebar.collapsed {
  width: var(--sidebar-collapsed-width);
}

.teacher-content {
  flex: 1;
  margin-left: var(--sidebar-width);
  transition: margin-left var(--transition-speed) ease;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

.teacher-content.expanded {
  margin-left: var(--sidebar-collapsed-width);
}

/* Header d'enseignant */
.teacher-header {
  height: var(--header-height);
  background-color: #fff;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05);
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0 20px;
  position: sticky;
  top: 0;
  z-index: 99;
}

.header-left {
  display: flex;
  align-items: center;
}

.header-title {
  font-size: 20px;
  font-weight: 600;
  color: #333;
  margin-left: 15px;
}

.header-search {
  display: flex;
  align-items: center;
  background-color: #f5f7fb;
  border-radius: 50px;
  padding: 8px 15px;
  width: 300px;
  margin-left: 20px;
}

.header-search input {
  border: none;
  background: transparent;
  width: 100%;
  padding: 5px;
  outline: none;
  font-size: 14px;
}

.header-search button {
  background: none;
  border: none;
  color: #6c757d;
  cursor: pointer;
}

.header-right {
  display: flex;
  align-items: center;
  gap: 20px;
}

.notifications {
  position: relative;
}

.notifications-icon {
  font-size: 20px;
  color: #6c757d;
  cursor: pointer;
}

.notifications-badge {
  position: absolute;
  top: -5px;
  right: -5px;
  background-color: var(--danger-color);
  color: white;
  border-radius: 50%;
  width: 18px;
  height: 18px;
  font-size: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.user-profile {
  display: flex;
  align-items: center;
  cursor: pointer;
}

.user-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  margin-right: 10px;
  object-fit: cover;
}

.user-name {
  font-weight: 500;
  font-size: 14px;
}

.dropdown-toggle {
  margin-left: 5px;
  font-size: 12px;
  color: #6c757d;
}

/* Sidebar */
.sidebar-header {
  height: var(--header-height);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 20px;
  border-bottom: 1px solid #eee;
}

.sidebar-logo {
  display: flex;
  align-items: center;
  text-decoration: none;
}

.logo-icon {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  background-color: var(--primary-color);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: bold;
  font-size: 16px;
}

.logo-text {
  margin-left: 10px;
  font-size: 18px;
  font-weight: 600;
  color: #333;
  transition: opacity var(--transition-speed) ease;
}

.sidebar-toggle {
  background: none;
  border: none;
  color: #6c757d;
  cursor: pointer;
  font-size: 16px;
  padding: 5px;
}

.sidebar-menu {
  padding: 20px 0;
}

.menu-category {
  padding: 0 20px;
  margin-bottom: 10px;
  font-size: 11px;
  text-transform: uppercase;
  color: #6c757d;
  font-weight: 600;
  letter-spacing: 0.5px;
  transition: opacity var(--transition-speed) ease;
}

.sidebar-collapsed .menu-category,
.sidebar-collapsed .logo-text,
.sidebar-collapsed .menu-item-text {
  opacity: 0;
  visibility: hidden;
}

.menu-items {
  list-style: none;
  padding: 0;
  margin: 0;
}

.menu-item {
  margin-bottom: 5px;
}

.menu-link {
  display: flex;
  align-items: center;
  padding: 10px 20px;
  text-decoration: none;
  color: #555;
  transition: all 0.2s ease;
  border-left: 3px solid transparent;
}

.menu-link:hover {
  background-color: var(--primary-light);
  color: var(--primary-color);
}

.menu-link.active {
  background-color: var(--primary-light);
  color: var(--primary-color);
  border-left-color: var(--primary-color);
}

.menu-icon {
  font-size: 18px;
  min-width: 30px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.menu-item-text {
  transition: opacity var(--transition-speed) ease;
}

/* Main Content */
.main-content {
  padding: 20px;
  flex: 1;
}

.page-header {
  margin-bottom: 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.page-title {
  font-size: 24px;
  font-weight: 600;
  color: #333;
}

.page-actions {
  display: flex;
  gap: 10px;
}

/* Nouvelles cartes statistiques colorées */
.stats-cards {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 20px;
  margin-bottom: 20px;
}

.stat-card {
  border-radius: var(--card-border-radius);
  overflow: hidden;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.stat-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
}

.gradient-pink {
  background: linear-gradient(135deg, var(--gradient-pink-start), var(--gradient-pink-end));
}

.gradient-blue {
  background: linear-gradient(135deg, var(--gradient-blue-start), var(--gradient-blue-end));
}

.gradient-green {
  background: linear-gradient(135deg, var(--gradient-green-start), var(--gradient-green-end));
}

.stat-card-content {
  padding: 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  color: white;
}

.stat-card-info h3 {
  font-size: 16px;
  font-weight: 500;
  margin: 0 0 5px 0;
  opacity: 0.9;
}

.stat-card-info h2 {
  font-size: 28px;
  font-weight: 700;
  margin: 0;
}

.stat-card-icon {
  width: 50px;
  height: 50px;
  border-radius: 50%;
  background-color: rgba(255, 255, 255, 0.2);
  display: flex;
  align-items: center;
  justify-content: center;
}

.stat-card-icon i {
  font-size: 24px;
  color: white;
}

.stat-card-footer {
  padding: 10px 20px;
  background-color: rgba(255, 255, 255, 0.1);
  color: white;
  font-size: 12px;
}

.stat-card-footer i {
  margin-right: 5px;
}

/* Dashboard Sections */
.dashboard-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
  gap: 20px;
  margin-bottom: 20px;
}

.dashboard-card {
  background-color: #fff;
  border-radius: var(--border-radius);
  box-shadow: var(--box-shadow);
  overflow: hidden;
  margin-bottom: 20px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 15px 20px;
  border-bottom: 1px solid #eee;
}

.card-header h2 {
  font-size: 16px;
  font-weight: 600;
  margin: 0;
  color: #333;
}

.card-actions {
  display: flex;
  gap: 10px;
  align-items: center;
}

.card-body {
  padding: 20px;
}

.card-body.p-0 {
  padding: 0;
}

.chart-container {
  height: 300px;
  position: relative;
}

.chart-legend {
  display: flex;
  flex-wrap: wrap;
  gap: 15px;
  margin-top: 20px;
  justify-content: center;
}

.legend-item {
  display: flex;
  align-items: center;
  font-size: 14px;
}

.legend-color {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  margin-right: 8px;
}

/* Tables */
.table-responsive {
  overflow-x: auto;
}

.modern-table {
  width: 100%;
  border-collapse: collapse;
}

.modern-table th,
.modern-table td {
  padding: 12px 15px;
  text-align: left;
}

.modern-table th {
  font-weight: 600;
  color: #333;
  background-color: #f8f9fa;
  border-bottom: 1px solid #eee;
}

.modern-table tbody tr {
  border-bottom: 1px solid #eee;
}

.modern-table tbody tr:hover {
  background-color: #f8f9fa;
}

.modern-table tbody tr:last-child {
  border-bottom: none;
}

/* User info in table */
.user-info {
  display: flex;
  align-items: center;
  gap: 10px;
}

.user-avatar-sm {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background-color: var(--primary-color);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 12px;
}

.table-actions {
  display: flex;
  gap: 5px;
}

/* Status Badges */
.badge {
  display: inline-block;
  padding: 4px 10px;
  font-size: 12px;
  font-weight: 500;
  line-height: 1;
  text-align: center;
  white-space: nowrap;
  vertical-align: baseline;
  border-radius: 50px;
}

.face_detection {
  color: #fff;
  background-color: var(--danger-color);
}

.eye_tracking {
  color: #212529;
  background-color: var(--warning-color);
}

.audio {
  color: #fff;
  background-color: var(--info-color);
}

.screen {
  color: #fff;
  background-color: var(--secondary-color);
}

/* Forms */
.modern-form .form-group {
  margin-bottom: 20px;
}

.modern-form label {
  display: block;
  margin-bottom: 8px;
  font-weight: 500;
  color: #333;
}

.modern-form .form-control {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid #ced4da;
  border-radius: var(--border-radius);
  font-size: 14px;
  transition: border-color 0.15s ease-in-out;
}

.modern-form .form-control:focus {
  border-color: var(--primary-color);
  outline: 0;
  box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25);
}

.modern-form .form-text {
  display: block;
  margin-top: 5px;
  font-size: 12px;
  color: #6c757d;
}

.modern-form .form-row {
  display: flex;
  flex-wrap: wrap;
  margin-right: -10px;
  margin-left: -10px;
  gap: 20px;
}

.modern-form .form-row > .form-group {
  padding-right: 10px;
  padding-left: 10px;
  flex: 1;
}

.modern-form .form-buttons {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 20px;
}

/* Buttons */
.btn {
  display: inline-block;
  font-weight: 500;
  text-align: center;
  white-space: nowrap;
  vertical-align: middle;
  user-select: none;
  border: 1px solid transparent;
  padding: 8px 16px;
  font-size: 14px;
  line-height: 1.5;
  border-radius: var(--border-radius);
  transition: all 0.15s ease-in-out;
  cursor: pointer;
}

.btn-primary {
  color: #fff;
  background-color: var(--primary-color);
  border-color: var(--primary-color);
}

.btn-primary:hover {
  background-color: #5253cc;
  border-color: #5253cc;
}

.btn-secondary {
  color: #fff;
  background-color: var(--secondary-color);
  border-color: var(--secondary-color);
}

.btn-secondary:hover {
  background-color: #5a6268;
  border-color: #5a6268;
}

.btn-success {
  color: #fff;
  background-color: var(--success-color);
  border-color: var(--success-color);
}

.btn-success:hover {
  background-color: #0d9e6d;
  border-color: #0d9e6d;
}

.btn-danger {
  color: #fff;
  background-color: var(--danger-color);
  border-color: var(--danger-color);
}

.btn-danger:hover {
  background-color: #d32f2f;
  border-color: #d32f2f;
}

.btn-warning {
  color: #212529;
  background-color: var(--warning-color);
  border-color: var(--warning-color);
}

.btn-warning:hover {
  background-color: #e0a800;
  border-color: #e0a800;
}

.btn-info {
  color: #fff;
  background-color: var(--info-color);
  border-color: var(--info-color);
}

.btn-info:hover {
  background-color: #0d6efd;
  border-color: #0d6efd;
}

.btn-light {
  color: #212529;
  background-color: #f8f9fa;
  border-color: #f8f9fa;
}

.btn-light:hover {
  background-color: #e2e6ea;
  border-color: #e2e6ea;
}

.btn-sm {
  padding: 4px 8px;
  font-size: 12px;
}

.btn-lg {
  padding: 10px 20px;
  font-size: 16px;
}

.btn-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  padding: 0;
  border-radius: 8px;
}

.btn-icon i {
  font-size: 14px;
}

/* Dropdowns */
.dropdown {
  position: relative;
  display: inline-block;
}

.dropdown-toggle {
  cursor: pointer;
}

.dropdown-toggle::after {
  display: inline-block;
  margin-left: 0.255em;
  vertical-align: 0.255em;
  content: "";
  border-top: 0.3em solid;
  border-right: 0.3em solid transparent;
  border-bottom: 0;
  border-left: 0.3em solid transparent;
}

.dropdown-menu {
  position: absolute;
  top: 100%;
  right: 0;
  z-index: 1000;
  display: none;
  min-width: 10rem;
  padding: 0.5rem 0;
  margin: 0.125rem 0 0;
  font-size: 14px;
  color: #212529;
  text-align: left;
  list-style: none;
  background-color: #fff;
  background-clip: padding-box;
  border: 1px solid rgba(0, 0, 0, 0.15);
  border-radius: var(--border-radius);
  box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}

.dropdown-menu.show {
  display: block;
}

.dropdown-item {
  display: flex;
  align-items: center;
  width: 100%;
  padding: 0.5rem 1.5rem;
  clear: both;
  font-weight: 400;
  color: #212529;
  text-align: inherit;
  white-space: nowrap;
  background-color: transparent;
  border: 0;
  text-decoration: none;
}

.dropdown-item:hover,
.dropdown-item:focus {
  color: #16181b;
  text-decoration: none;
  background-color: #f8f9fa;
}

.dropdown-item i {
  margin-right: 8px;
  font-size: 14px;
}

.dropdown-divider {
  height: 0;
  margin: 0.5rem 0;
  overflow: hidden;
  border-top: 1px solid #e9ecef;
}

.text-danger {
  color: var(--danger-color) !important;
}

/* Alerts */
.alert {
  position: relative;
  padding: 12px 20px;
  margin-bottom: 20px;
  border: 1px solid transparent;
  border-radius: var(--border-radius);
}

.alert-success {
  color: #0f5132;
  background-color: #d1e7dd;
  border-color: #badbcc;
}

.alert-danger {
  color: #842029;
  background-color: #f8d7da;
  border-color: #f5c2c7;
}

.alert-warning {
  color: #664d03;
  background-color: #fff3cd;
  border-color: #ffecb5;
}

.alert-info {
  color: #055160;
  background-color: #cff4fc;
  border-color: #b6effb;
}

/* Tabs */
.nav-tabs {
  display: flex;
  list-style: none;
  padding: 0;
  margin: 0;
  border-bottom: 1px solid #dee2e6;
}

.nav-item {
  margin-bottom: -1px;
}

.nav-link {
  display: block;
  padding: 0.5rem 1rem;
  text-decoration: none;
  color: #495057;
  background-color: transparent;
  border: 1px solid transparent;
  border-top-left-radius: 0.25rem;
  border-top-right-radius: 0.25rem;
}

.nav-link:hover,
.nav-link:focus {
  border-color: #e9ecef #e9ecef #dee2e6;
}

.nav-link.active {
  color: var(--primary-color);
  background-color: #fff;
  border-color: #dee2e6 #dee2e6 #fff;
}

.tab-content {
  padding: 20px 0;
}

.tab-pane {
  display: none;
}

.tab-pane.active {
  display: block;
}

/* Empty States */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40px 20px;
  text-align: center;
}

.empty-icon {
  font-size: 48px;
  color: #6c757d;
  margin-bottom: 15px;
}

.empty-state h3 {
  font-size: 18px;
  font-weight: 600;
  margin-bottom: 10px;
  color: #333;
}

.empty-state p {
  color: #6c757d;
  margin-bottom: 20px;
  max-width: 500px;
}

/* Modals */
.modal {
  display: none;
  position: fixed;
  z-index: 1050;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  overflow: auto;
  background-color: rgba(0, 0, 0, 0.5);
}

.modal-content {
  position: relative;
  background-color: #fff;
  margin: 10% auto;
  padding: 0;
  border-radius: var(--border-radius);
  box-shadow: 0 5px 15px rgba(0, 0, 0, 0.5);
  width: 80%;
  max-width: 600px;
  animation: modalFadeIn 0.3s;
}

@keyframes modalFadeIn {
  from {
    opacity: 0;
    transform: translateY(-50px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 15px 20px;
  border-bottom: 1px solid #dee2e6;
}

.modal-header h2 {
  margin: 0;
  font-size: 18px;
  font-weight: 600;
}

.close-modal {
  background: none;
  border: none;
  font-size: 24px;
  font-weight: 700;
  color: #6c757d;
  cursor: pointer;
}

.modal-body {
  padding: 20px;
}

.modal-footer {
  padding: 15px 20px;
  border-top: 1px solid #dee2e6;
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

/* Responsive */
@media (max-width: 992px) {
  .stats-cards,
  .dashboard-row {
    grid-template-columns: 1fr;
  }

  .header-search {
    display: none;
  }
}

@media (max-width: 768px) {
  .teacher-sidebar {
    width: 0;
    transform: translateX(-100%);
  }

  .teacher-content {
    margin-left: 0;
  }

  .teacher-sidebar.show {
    width: var(--sidebar-width);
    transform: translateX(0);
  }

  .modal-content {
    width: 95%;
  }

  .user-name {
    display: none;
  }

  .form-row {
    flex-direction: column;
  }

  .form-row > .form-group {
    width: 100%;
  }
}

/* Animation */
@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

.fade-in {
  animation: fadeIn 0.3s ease-in-out;
}

    </style>
</head>
<body>
    <div class="app-container">
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
                    <li class="nav-item active">
                        <a href="index.php" class="nav-link">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Tableau de bord</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="create-exam.php" class="nav-link">
                            <i class="fas fa-plus-circle"></i>
                            <span>Créer un examen</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="manage-exams.php" class="nav-link">
                            <i class="fas fa-file-alt"></i>
                            <span>Gérer les examens</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="grade-exams.php" class="nav-link">
                            <i class="fas fa-check-circle"></i>
                            <span>Noter les examens</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="reports.php" class="nav-link">
                            <i class="fas fa-chart-bar"></i>
                            <span>Rapports</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../profile.php" class="nav-link">
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
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="header">
                <div class="header-left">
                    <button class="sidebar-toggle" id="sidebar-toggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1 class="page-title">Tableau de bord</h1>
                </div>
                
                <div class="header-right">
                    <div class="search-box">
                        <input type="text" placeholder="Rechercher...">
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
                            <span class="user-name"><?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?></span>
                            <span class="user-role">Enseignant</span>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Dashboard Content -->
            <div class="dashboard">
                <!-- Stats Cards -->
                <div class="stats-cards">
                    <div class="card stat-card gradient-orange">
                        <div class="card-body">
                            <div class="stat-icon">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <div class="stat-details">
                                <h3 class="stat-title">Total des examens</h3>
                                <p class="stat-value"><?php echo $examStats['total_exams']; ?></p>
                                <p class="stat-change positive">
                                    <i class="fas fa-arrow-up"></i> 12% depuis le mois dernier
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card stat-card gradient-blue">
                        <div class="card-body">
                            <div class="stat-icon">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                            <div class="stat-details">
                                <h3 class="stat-title">Étudiants</h3>
                                <p class="stat-value"><?php echo $studentStats['total_students']; ?></p>
                                <p class="stat-change positive">
                                    <i class="fas fa-arrow-up"></i> 8% depuis le mois dernier
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card stat-card gradient-green">
                        <div class="card-body">
                            <div class="stat-icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="stat-details">
                                <h3 class="stat-title">Score moyen</h3>
                                <p class="stat-value"><?php echo round($studentStats['avg_score'], 1); ?>%</p>
                                <p class="stat-change positive">
                                    <i class="fas fa-arrow-up"></i> 5% depuis le mois dernier
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Charts Section -->
                <div class="charts-section">
                    <div class="card chart-card">
                        <div class="card-header">
                            <h2 class="card-title">Activité des examens</h2>
                            <div class="card-actions">
                                <button class="btn btn-icon">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <canvas id="examsActivityChart" height="250"></canvas>
                        </div>
                    </div>
                    
                    <div class="card chart-card">
                        <div class="card-header">
                            <h2 class="card-title">Répartition des statuts</h2>
                            <div class="card-actions">
                                <button class="btn btn-icon">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <canvas id="examStatusChart" height="250"></canvas>
                            <div class="chart-legend">
                                <div class="legend-item">
                                    <span class="legend-color" style="background-color: #4CAF50;"></span>
                                    <span class="legend-label">Actifs</span>
                                </div>
                                <div class="legend-item">
                                    <span class="legend-color" style="background-color: #FFC107;"></span>
                                    <span class="legend-label">Brouillons</span>
                                </div>
                                <div class="legend-item">
                                    <span class="legend-color" style="background-color: #2196F3;"></span>
                                    <span class="legend-label">Terminés</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Exams Table -->
                <div class="card table-card">
                    <div class="card-header">
                        <h2 class="card-title">Examens récents</h2>
                        <div class="card-actions">
                            <a href="manage-exams.php" class="btn btn-primary btn-sm">Voir tous</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Titre</th>
                                        <th>Matière</th>
                                        <th>Statut</th>
                                        <th>Participants</th>
                                        <th>Score moyen</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($recentExams->num_rows > 0): ?>
                                        <?php while ($exam = $recentExams->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <div class="table-user">
                                                        <div class="user-info">
                                                            <span class="user-name"><?php echo htmlspecialchars($exam['title']); ?></span>
                                                            <span class="user-date"><?php echo date('d/m/Y', strtotime($exam['created_at'])); ?></span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($exam['subject']); ?></td>
                                                <td>
                                                    <span class="status-badge <?php echo $exam['status']; ?>">
                                                        <?php echo ucfirst($exam['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $exam['participants']; ?></td>
                                                <td><?php echo $exam['avg_score'] ? round($exam['avg_score'], 1) . '%' : 'N/A'; ?></td>
                                                <td>
                                                    <div class="table-actions">
                                                        <a href="edit-exam.php?id=<?php echo $exam['id']; ?>" class="btn btn-icon btn-sm" title="Modifier">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="view-results.php?id=<?php echo $exam['id']; ?>" class="btn btn-icon btn-sm" title="Résultats">
                                                            <i class="fas fa-chart-bar"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center">Aucun examen récent</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Exams to Grade and Incidents -->
                <div class="dashboard-row">
                    <div class="card table-card">
                        <div class="card-header">
                            <h2 class="card-title">Examens à noter</h2>
                            <div class="card-actions">
                                <a href="grade-exams.php" class="btn btn-primary btn-sm">Voir tous</a>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if ($examsToGrade->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Titre</th>
                                                <th>Soumissions</th>
                                                <th>En attente</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($exam = $examsToGrade->fetch_assoc()): ?>
                                                <tr>
                                                    <td>
                                                        <div class="table-user">
                                                            <div class="user-info">
                                                                <span class="user-name"><?php echo htmlspecialchars($exam['title']); ?></span>
                                                                <span class="user-date"><?php echo htmlspecialchars($exam['subject']); ?></span>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td><?php echo $exam['submissions']; ?></td>
                                                    <td>
                                                        <span class="badge badge-warning"><?php echo $exam['pending_grades']; ?></span>
                                                    </td>
                                                    <td>
                                                        <a href="grade-exam.php?id=<?php echo $exam['id']; ?>" class="btn btn-primary btn-sm">
                                                            Noter
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <div class="empty-icon">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <p>Aucun examen en attente de notation</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="card table-card">
                        <div class="card-header">
                            <h2 class="card-title">Incidents récents</h2>
                            <div class="card-actions">
                                <a href="proctoring-incidents.php" class="btn btn-primary btn-sm">Voir tous</a>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if ($proctorIncidents->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Examen</th>
                                                <th>Étudiant</th>
                                                <th>Type</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($incident = $proctorIncidents->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($incident['exam_title']); ?></td>
                                                    <td><?php echo htmlspecialchars($incident['first_name'] . ' ' . $incident['last_name']); ?></td>
                                                    <td>
                                                        <span class="incident-badge <?php echo strtolower($incident['incident_type']); ?>">
                                                            <?php echo htmlspecialchars($incident['incident_type']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-icon btn-sm view-incident" data-id="<?php echo $incident['id']; ?>">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <div class="empty-icon">
                                        <i class="fas fa-shield-alt"></i>
                                    </div>
                                    <p>Aucun incident récent</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Modal pour afficher les détails d'un incident -->
    <div class="modal" id="incidentModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title">Détails de l'incident</h3>
                    <button type="button" class="close-modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="incident-details">
                        <div class="incident-info">
                            <p><strong>Examen:</strong> <span id="incident-exam"></span></p>
                            <p><strong>Étudiant:</strong> <span id="incident-student"></span></p>
                            <p><strong>Type d'incident:</strong> <span id="incident-type"></span></p>
                            <p><strong>Date:</strong> <span id="incident-date"></span></p>
                        </div>
                        <div class="incident-description">
                            <h4>Description</h4>
                            <p id="incident-description"></p>
                        </div>
                        <div class="incident-evidence">
                            <h4>Preuves</h4>
                            <div id="incident-evidence-container">
                                <img id="incident-image" src="../assets/images/placeholder.jpg" alt="Preuve de l'incident">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary close-modal">Fermer</button>
                    <button type="button" class="btn btn-primary" id="review-incident">Examiner</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Toggle sidebar
        const menuToggle = document.getElementById('menu-toggle');
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const appContainer = document.querySelector('.app-container');
        
        menuToggle.addEventListener('click', function() {
            appContainer.classList.toggle('sidebar-collapsed');
        });
        
        sidebarToggle.addEventListener('click', function() {
            appContainer.classList.toggle('sidebar-collapsed');
        });
        
        // Exams Activity Chart
        const examsActivityCtx = document.getElementById('examsActivityChart').getContext('2d');
        const examsActivityChart = new Chart(examsActivityCtx, {
            type: 'bar',
            data: {
                labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'],
                datasets: [{
                    label: 'Examens créés',
                    data: [12, 19, 15, 8, 22, 14, 10, 17, 21, 15, 19, 23],
                    backgroundColor: '#FF6384',
                    borderColor: '#FF6384',
                    borderWidth: 1
                }, {
                    label: 'Examens complétés',
                    data: [10, 15, 12, 6, 17, 10, 8, 15, 18, 12, 15, 20],
                    backgroundColor: '#36A2EB',
                    borderColor: '#36A2EB',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                        align: 'end'
                    }
                }
            }
        });
        
        // Exam Status Chart
        const examStatusCtx = document.getElementById('examStatusChart').getContext('2d');
        const examStatusChart = new Chart(examStatusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Actifs', 'Brouillons', 'Terminés'],
                datasets: [{
                    data: [
                        <?php echo $examStats['active_exams']; ?>, 
                        <?php echo $examStats['draft_exams']; ?>, 
                        <?php echo $examStats['completed_exams']; ?>
                    ],
                    backgroundColor: [
                        '#4CAF50',
                        '#FFC107',
                        '#2196F3'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
        
        // Gestion du modal d'incident
        const viewIncidentBtns = document.querySelectorAll('.view-incident');
        const incidentModal = document.getElementById('incidentModal');
        const closeModalBtns = document.querySelectorAll('.close-modal');
        
        viewIncidentBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                const incidentId = this.getAttribute('data-id');
                // Ici, vous feriez normalement une requête AJAX pour récupérer les détails de l'incident
                // Pour l'exemple, nous allons simplement remplir le modal avec des données fictives
                document.getElementById('incident-exam').textContent = "Physique quantique";
                document.getElementById('incident-student').textContent = "Jean Dupont";
                document.getElementById('incident-type').textContent = "Détection de visage";
                document.getElementById('incident-date').textContent = "18/04/2023, 09:15";
                document.getElementById('incident-description').textContent = "L'étudiant a quitté le champ de vision de la caméra pendant plus de 30 secondes.";
                
                incidentModal.classList.add('show');
            });
        });
        
        closeModalBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                incidentModal.classList.remove('show');
            });
        });
        
        window.addEventListener('click', function(event) {
            if (event.target == incidentModal) {
                incidentModal.classList.remove('show');
            }
        });
        
        // Bouton d'examen d'incident
        document.getElementById('review-incident').addEventListener('click', function() {
            alert('Redirection vers la page de révision détaillée de l\'incident...');
            // Ici, vous redirigeriez normalement vers une page de révision détaillée
        });
    });
    </script>
</body>
</html>
