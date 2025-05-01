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

// Récupérer les statistiques générales
$totalExams = $conn->query("SELECT COUNT(*) as count FROM exams WHERE teacher_id = $teacherId")->fetch_assoc()['count'];
$totalStudents = $conn->query("
    SELECT COUNT(DISTINCT user_id) as count 
    FROM exam_results er 
    JOIN exams e ON er.exam_id = e.id 
    WHERE e.teacher_id = $teacherId
")->fetch_assoc()['count'];
$totalCompletedExams = $conn->query("
    SELECT COUNT(*) as count 
    FROM exam_results er 
    JOIN exams e ON er.exam_id = e.id 
    WHERE e.teacher_id = $teacherId AND er.status = 'completed'
")->fetch_assoc()['count'];
$avgScore = $conn->query("
    SELECT AVG(er.score) as avg_score 
    FROM exam_results er 
    JOIN exams e ON er.exam_id = e.id 
    WHERE e.teacher_id = $teacherId AND er.status = 'completed'
")->fetch_assoc()['avg_score'];

// Récupérer les données pour le graphique des examens par mois
$examsByMonth = $conn->query("
    SELECT 
        MONTH(created_at) as month, 
        COUNT(*) as count 
    FROM exams 
    WHERE teacher_id = $teacherId AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY MONTH(created_at)
    ORDER BY month
");

// Récupérer les données pour le graphique des résultats moyens par matière
$avgScoresBySubject = $conn->query("
    SELECT 
        e.subject,
        AVG(er.score) as avg_score
    FROM exam_results er
    JOIN exams e ON er.exam_id = e.id
    WHERE e.teacher_id = $teacherId AND er.status = 'completed'
    GROUP BY e.subject
    ORDER BY avg_score DESC
");

// Récupérer les incidents de surveillance
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
    LIMIT 10
");

$pageTitle = "Rapports et Statistiques";
include 'includes/header.php';
?>

<link rel="stylesheet" href="../assets/css/teacher.css">

<div class="teacher-container">
    <div class="teacher-sidebar">
        <div class="sidebar-header">
            <a href="index.php" class="sidebar-logo">
                <div class="logo-icon">E</div>
                <span class="logo-text">ExamSafe</span>
            </a>
            <button class="sidebar-toggle" id="sidebarCollapseBtn">
                <i class="fas fa-chevron-left"></i>
            </button>
        </div>
        
        <div class="sidebar-menu">
            <div class="menu-category">Menu principal</div>
            <ul class="menu-items">
                <li class="menu-item">
                    <a href="index.php" class="menu-link">
                        <span class="menu-icon"><i class="fas fa-tachometer-alt"></i></span>
                        <span class="menu-item-text">Tableau de bord</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="create-exam.php" class="menu-link">
                        <span class="menu-icon"><i class="fas fa-plus-circle"></i></span>
                        <span class="menu-item-text">Créer un examen</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="manage-exams.php" class="menu-link">
                        <span class="menu-icon"><i class="fas fa-file-alt"></i></span>
                        <span class="menu-item-text">Gérer les examens</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="grade-exams.php" class="menu-link">
                        <span class="menu-icon"><i class="fas fa-check-circle"></i></span>
                        <span class="menu-item-text">Noter les examens</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="reports.php" class="menu-link active">
                        <span class="menu-icon"><i class="fas fa-chart-bar"></i></span>
                        <span class="menu-item-text">Rapports</span>
                    </a>
                </li>
            </ul>
            
            <div class="menu-category">Configuration</div>
            <ul class="menu-items">
                <li class="menu-item">
                    <a href="../profile.php" class="menu-link">
                        <span class="menu-icon"><i class="fas fa-user"></i></span>
                        <span class="menu-item-text">Mon profil</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="../logout.php" class="menu-link">
                        <span class="menu-icon"><i class="fas fa-sign-out-alt"></i></span>
                        <span class="menu-item-text">Déconnexion</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>
    
    <div class="teacher-content">
        <div class="teacher-header">
            <div class="header-left">
                <button id="sidebarToggle" class="sidebar-toggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="header-title"><?php echo $pageTitle; ?></h1>
            </div>
            
            <div class="header-right">
                <div class="notifications">
                    <i class="fas fa-bell notifications-icon"></i>
                    <span class="notifications-badge">3</span>
                </div>
                
                <div class="user-profile">
                    <img src="../assets/images/avatar.png" alt="Avatar" class="user-avatar">
                    <span class="user-name"><?php echo $_SESSION['username']; ?></span>
                    <i class="fas fa-chevron-down dropdown-toggle"></i>
                    
                    <div class="dropdown-menu">
                        <a href="../profile.php" class="dropdown-item">
                            <i class="fas fa-user"></i> Mon profil
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="../logout.php" class="dropdown-item text-danger">
                            <i class="fas fa-sign-out-alt"></i> Déconnexion
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="main-content">
            <!-- Cartes de statistiques colorées -->
            <div class="stats-cards">
                <div class="stat-card gradient-pink">
                    <div class="stat-card-content">
                        <div class="stat-card-info">
                            <h3>Examens</h3>
                            <h2><?php echo $totalExams; ?></h2>
                        </div>
                        <div class="stat-card-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                    </div>
                    <div class="stat-card-footer">
                        <span><i class="fas fa-arrow-up"></i> 12% ce mois</span>
                    </div>
                </div>
                
                <div class="stat-card gradient-blue">
                    <div class="stat-card-content">
                        <div class="stat-card-info">
                            <h3>Étudiants</h3>
                            <h2><?php echo $totalStudents; ?></h2>
                        </div>
                        <div class="stat-card-icon">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                    </div>
                    <div class="stat-card-footer">
                        <span><i class="fas fa-arrow-up"></i> 8% ce mois</span>
                    </div>
                </div>
                
                <div class="stat-card gradient-green">
                    <div class="stat-card-content">
                        <div class="stat-card-info">
                            <h3>Score moyen</h3>
                            <h2><?php echo round($avgScore, 1); ?>%</h2>
                        </div>
                        <div class="stat-card-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                    <div class="stat-card-footer">
                        <span><i class="fas fa-arrow-up"></i> 5% ce mois</span>
                    </div>
                </div>
            </div>
            
            <!-- Graphiques -->
            <div class="dashboard-row">
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2>Examens et Résultats</h2>
                        <div class="card-actions">
                            <select class="form-control form-control-sm">
                                <option>Cette année</option>
                                <option>6 derniers mois</option>
                                <option>3 derniers mois</option>
                            </select>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="examsChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2>Répartition par matière</h2>
                        <div class="card-actions">
                            <button class="btn btn-sm btn-light"><i class="fas fa-download"></i></button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="subjectsChart"></canvas>
                        </div>
                        <div class="chart-legend">
                            <div class="legend-item">
                                <span class="legend-color" style="background-color: #FF6384;"></span>
                                <span>Mathématiques (35%)</span>
                            </div>
                            <div class="legend-item">
                                <span class="legend-color" style="background-color: #36A2EB;"></span>
                                <span>Physique (25%)</span>
                            </div>
                            <div class="legend-item">
                                <span class="legend-color" style="background-color: #FFCE56;"></span>
                                <span>Chimie (20%)</span>
                            </div>
                            <div class="legend-item">
                                <span class="legend-color" style="background-color: #4BC0C0;"></span>
                                <span>Biologie (15%)</span>
                            </div>
                            <div class="legend-item">
                                <span class="legend-color" style="background-color: #9966FF;"></span>
                                <span>Autres (5%)</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tableau des incidents récents -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h2>Incidents récents</h2>
                    <div class="card-actions">
                        <button class="btn btn-sm btn-primary">Voir tout</button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if ($proctorIncidents->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="modern-table">
                                <thead>
                                    <tr>
                                        <th>Étudiant</th>
                                        <th>Examen</th>
                                        <th>Type d'incident</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($incident = $proctorIncidents->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <div class="user-info">
                                                    <div class="user-avatar-sm">
                                                        <?php echo strtoupper(substr($incident['first_name'], 0, 1) . substr($incident['last_name'], 0, 1)); ?>
                                                    </div>
                                                    <div class="user-name">
                                                        <?php echo htmlspecialchars($incident['first_name'] . ' ' . $incident['last_name']); ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($incident['exam_title']); ?></td>
                                            <td>
                                                <span class="badge <?php echo strtolower($incident['incident_type']); ?>">
                                                    <?php echo htmlspecialchars($incident['incident_type']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($incident['timestamp'])); ?></td>
                                            <td>
                                                <div class="table-actions">
                                                    <button class="btn btn-icon btn-sm view-incident" data-id="<?php echo $incident['id']; ?>">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-icon btn-sm">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon"><i class="fas fa-shield-alt"></i></div>
                            <h3>Aucun incident récent</h3>
                            <p>Aucun incident de surveillance n'a été détecté récemment.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Formulaire de génération de rapports -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h2>Générer des rapports</h2>
                </div>
                <div class="card-body">
                    <form id="reportForm" class="modern-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="reportType">Type de rapport</label>
                                <select id="reportType" name="reportType" class="form-control">
                                    <option value="exam_results">Résultats d'examens</option>
                                    <option value="student_performance">Performance des étudiants</option>
                                    <option value="proctoring_incidents">Incidents de surveillance</option>
                                    <option value="question_analysis">Analyse des questions</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="examId">Examen</label>
                                <select id="examId" name="examId" class="form-control">
                                    <option value="all">Tous les examens</option>
                                    <!-- Options générées dynamiquement -->
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="format">Format</label>
                                <select id="format" name="format" class="form-control">
                                    <option value="pdf">PDF</option>
                                    <option value="excel">Excel</option>
                                    <option value="csv">CSV</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-buttons">
                            <button type="submit" class="btn btn-primary">Générer le rapport</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour afficher les détails d'un incident -->
<div class="modal" id="incidentModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Détails de l'incident</h2>
            <button class="close-modal">&times;</button>
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
                    <h3>Description</h3>
                    <p id="incident-description"></p>
                </div>
                <div class="incident-evidence">
                    <h3>Preuves</h3>
                    <div id="incident-evidence-container">
                        <img id="incident-image" src="../assets/images/placeholder.jpg" alt="Preuve de l'incident">
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary close-modal">Fermer</button>
            <button class="btn btn-primary" id="review-incident">Examiner</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion du menu latéral
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarCollapseBtn = document.getElementById('sidebarCollapseBtn');
    const teacherSidebar = document.querySelector('.teacher-sidebar');
    const teacherContent = document.querySelector('.teacher-content');
    
    sidebarToggle.addEventListener('click', function() {
        teacherSidebar.classList.toggle('show');
    });
    
    sidebarCollapseBtn.addEventListener('click', function() {
        teacherSidebar.classList.toggle('collapsed');
        teacherContent.classList.toggle('expanded');
        
        // Changer l'icône du bouton
        const icon = this.querySelector('i');
        if (icon.classList.contains('fa-chevron-left')) {
            icon.classList.replace('fa-chevron-left', 'fa-chevron-right');
        } else {
            icon.classList.replace('fa-chevron-right', 'fa-chevron-left');
        }
    });
    
    // Gestion des dropdowns
    const userProfile = document.querySelector('.user-profile');
    userProfile.addEventListener('click', function() {
        this.querySelector('.dropdown-menu').classList.toggle('show');
    });
    
    // Fermer les dropdowns quand on clique ailleurs
    window.addEventListener('click', function(event) {
        if (!event.target.closest('.user-profile')) {
            const dropdowns = document.querySelectorAll('.dropdown-menu');
            dropdowns.forEach(dropdown => {
                if (dropdown.classList.contains('show')) {
                    dropdown.classList.remove('show');
                }
            });
        }
    });
    
    // Configuration du graphique des examens par mois
    const examsCtx = document.getElementById('examsChart').getContext('2d');
    const examsChart = new Chart(examsCtx, {
        type: 'bar',
        data: {
            labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'],
            datasets: [
                {
                    label: 'Examens créés',
                    data: [12, 19, 15, 25, 22, 30, 18, 15, 20, 25, 28, 30],
                    backgroundColor: 'rgba(54, 162, 235, 0.6)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Examens complétés',
                    data: [10, 15, 12, 20, 18, 25, 15, 12, 18, 22, 24, 28],
                    backgroundColor: 'rgba(75, 192, 192, 0.6)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
    
    // Configuration du graphique circulaire des matières
    const subjectsCtx = document.getElementById('subjectsChart').getContext('2d');
    const subjectsChart = new Chart(subjectsCtx, {
        type: 'doughnut',
        data: {
            labels: ['Mathématiques', 'Physique', 'Chimie', 'Biologie', 'Autres'],
            datasets: [{
                data: [35, 25, 20, 15, 5],
                backgroundColor: [
                    '#FF6384',
                    '#36A2EB',
                    '#FFCE56',
                    '#4BC0C0',
                    '#9966FF'
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
            
            incidentModal.style.display = 'block';
        });
    });
    
    closeModalBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            incidentModal.style.display = 'none';
        });
    });
    
    window.addEventListener('click', function(event) {
        if (event.target == incidentModal) {
            incidentModal.style.display = 'none';
        }
    });
    
    // Bouton d'examen d'incident
    document.getElementById('review-incident').addEventListener('click', function() {
        alert('Redirection vers la page de révision détaillée de l\'incident...');
        // Ici, vous redirigeriez normalement vers une page de révision détaillée
    });
});
</script>

<?php include '../includes/footer.php'; ?>
