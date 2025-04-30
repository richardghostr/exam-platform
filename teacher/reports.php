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
include '../includes/header.php';
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
            <div class="stats-overview">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Total des examens</h3>
                        <p class="stat-number"><?php echo $totalExams; ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Étudiants</h3>
                        <p class="stat-number"><?php echo $totalStudents; ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Examens complétés</h3>
                        <p class="stat-number"><?php echo $totalCompletedExams; ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Score moyen</h3>
                        <p class="stat-number"><?php echo round($avgScore, 1); ?>%</p>
                    </div>
                </div>
            </div>
            
            <div class="dashboard-section">
                <div class="dashboard-row">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h2>Examens créés par mois</h2>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="examsChart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h2>Score moyen par matière</h2>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="scoresChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="dashboard-section">
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2>Incidents de surveillance récents</h2>
                    </div>
                    <div class="card-body">
                        <?php if ($proctorIncidents->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="teacher-table">
                                    <thead>
                                        <tr>
                                            <th>Examen</th>
                                            <th>Étudiant</th>
                                            <th>Type d'incident</th>
                                            <th>Date</th>
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
                                                <td><?php echo date('d/m/Y H:i', strtotime($incident['timestamp'])); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-info view-incident" data-id="<?php echo $incident['id']; ?>">
                                                        <i class="fas fa-eye"></i> Détails
                                                    </button>
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
            </div>
            
            <div class="dashboard-section">
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2>Générer des rapports</h2>
                    </div>
                    <div class="card-body">
                        <form id="reportForm" class="teacher-form">
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
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="dateRange">Période</label>
                                    <select id="dateRange" name="dateRange" class="form-control">
                                        <option value="7days">7 derniers jours</option>
                                        <option value="30days">30 derniers jours</option>
                                        <option value="90days">90 derniers jours</option>
                                        <option value="year">Année en cours</option>
                                        <option value="custom">Personnalisé</option>
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
                            
                            <div id="customDateRange" class="form-row" style="display: none;">
                                <div class="form-group">
                                    <label for="startDate">Date de début</label>
                                    <input type="date" id="startDate" name="startDate" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label for="endDate">Date de fin</label>
                                    <input type="date" id="endDate" name="endDate" class="form-control">
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
            datasets: [{
                label: 'Nombre d\'examens',
                data: [12, 19, 15, 25, 22, 30, 18, 15, 20, 25, 28, 30],
                backgroundColor: 'rgba(74, 108, 247, 0.6)',
                borderColor: 'rgba(74, 108, 247, 1)',
                borderWidth: 1
            }]
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
    
    // Configuration du graphique des scores moyens par matière
    const scoresCtx = document.getElementById('scoresChart').getContext('2d');
    const scoresChart = new Chart(scoresCtx, {
        type: 'horizontalBar',
        data: {
            labels: ['Mathématiques', 'Physique', 'Chimie', 'Biologie', 'Histoire', 'Géographie', 'Anglais'],
            datasets: [{
                label: 'Score moyen (%)',
                data: [75, 68, 82, 79, 85, 72, 88],
                backgroundColor: 'rgba(40, 167, 69, 0.6)',
                borderColor: 'rgba(40, 167, 69, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    beginAtZero: true,
                    max: 100
                }
            }
        }
    });
    
    // Afficher/masquer les dates personnalisées
    document.getElementById('dateRange').addEventListener('change', function() {
        const customDateRange = document.getElementById('customDateRange');
        if (this.value === 'custom') {
            customDateRange.style.display = 'flex';
        } else {
            customDateRange.style.display = 'none';
        }
    });
    
    // Soumission du formulaire de rapport
    document.getElementById('reportForm').addEventListener('submit', function(e) {
        e.preventDefault();
        alert('Le rapport est en cours de génération et sera disponible dans quelques instants.');
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
