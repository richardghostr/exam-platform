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
// $examStats = $conn->query("
//     SELECT 
//         COUNT(*) as total_exams,
//         SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_exams,
//         SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_exams,
//         SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_exams
//     FROM exams
//     WHERE teacher_id = $teacherId
// ")->fetch_assoc();

// Récupérer les statistiques des étudiants
// $studentStats = $conn->query("
//     SELECT 
//         COUNT(DISTINCT user_id) as total_students,
//         AVG(score) as avg_score,
//         MAX(score) as max_score,
//         MIN(score) as min_score
//     FROM exam_results er
//     JOIN exams e ON er.exam_id = e.id
//     WHERE e.teacher_id = $teacherId AND er.status = 'completed'
// ")->fetch_assoc();

// Récupérer les examens récents
// $recentExams = $conn->query("
//     SELECT 
//         e.id,
//         e.title,
//         e.subject,
//         e.status,
//         e.created_at,
//         COUNT(DISTINCT er.user_id) as participants,
//         AVG(er.score) as avg_score
//     FROM exams e
//     LEFT JOIN exam_results er ON e.id = er.exam_id
//     WHERE e.teacher_id = $teacherId
//     GROUP BY e.id
//     ORDER BY e.created_at DESC
//     LIMIT 5
// ");

// Récupérer les examens à noter
// $examsToGrade = $conn->query("
//     SELECT 
//         e.id,
//         e.title,
//         e.subject,
//         COUNT(er.id) as submissions,
//         COUNT(CASE WHEN er.is_graded = 0 THEN er.id ELSE NULL END) as pending_grades
//     FROM exams e
//     JOIN exam_results er ON e.id = er.exam_id
//     WHERE e.teacher_id = $teacherId AND er.status = 'completed' AND e.has_essay = 1
//     GROUP BY e.id
//     HAVING pending_grades > 0
//     ORDER BY e.created_at DESC
// ");

// // Récupérer les incidents de surveillance récents
// $proctorIncidents = $conn->query("
//     SELECT 
//         p.id,
//         p.exam_id,
//         e.title as exam_title,
//         u.username,
//         u.first_name,
//         u.last_name,
//         p.incident_type,
//         p.timestamp,
//         p.details
//     FROM proctoring_incidents p
//     JOIN exams e ON p.exam_id = e.id
//     JOIN users u ON p.user_id = u.id
//     WHERE e.teacher_id = $teacherId
//     ORDER BY p.timestamp DESC
//     LIMIT 5
// ");

$pageTitle = "Tableau de bord de l'enseignant";
include '../includes/header.php';
?>
<link rel="stylesheet" href="../assets/css/teacher.css">
<div class="teacher-container">
    <div class="teacher-sidebar">
        <div class="sidebar-header">
            <div class="logo">ExamSafe</div>
            <button class="sidebar-toggle"><i class="fas fa-bars"></i></button>
        </div>
        
        <div class="sidebar-menu">
            <ul>
                <li>
                    <a href="index.php" class="active">
                        <i class="fas fa-tachometer-alt"></i> Tableau de bord
                    </a>
                </li>
                <li>
                    <a href="create-exam.php">
                        <i class="fas fa-plus-circle"></i> Créer un examen
                    </a>
                </li>
                <li>
                    <a href="manage-exams.php">
                        <i class="fas fa-file-alt"></i> Gérer les examens
                    </a>
                </li>
                <li>
                    <a href="grade-exams.php">
                        <i class="fas fa-check-circle"></i> Noter les examens
                    </a>
                </li>
                <li>
                    <a href="reports.php">
                        <i class="fas fa-chart-bar"></i> Rapports
                    </a>
                </li>
                <li>
                    <a href="../logout.php">
                        <i class="fas fa-sign-out-alt"></i> Déconnexion
                    </a>
                </li>
            </ul>
        </div>
    </div>
    
    <div class="teacher-content">
        <div class="teacher-header">
            <h1>Bienvenue, <?php echo $_SESSION['first_name']; ?></h1>
            <div class="user-info">
                <span><?php echo date('d F Y'); ?></span>
                <div class="user-dropdown">
                    <button class="user-dropdown-btn">
                        <img src="../assets/images/avatar.png" alt="Avatar" class="avatar">
                        <span><?php echo $_SESSION['username']; ?></span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="user-dropdown-content">
                        <a href="../profile.php"><i class="fas fa-user"></i> Profil</a>
                        <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="stats-overview">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
                <div class="stat-info">
                    <h3>Total des examens</h3>
                    <p class="stat-number"><?php echo $examStats['total_exams']; ?></p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-play-circle"></i></div>
                <div class="stat-info">
                    <h3>Examens actifs</h3>
                    <p class="stat-number"><?php echo $examStats['active_exams']; ?></p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
                <div class="stat-info">
                    <h3>Étudiants</h3>
                    <p class="stat-number"><?php echo $studentStats['total_students']; ?></p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                <div class="stat-info">
                    <h3>Score moyen</h3>
                    <p class="stat-number"><?php echo round($studentStats['avg_score'], 1); ?>%</p>
                </div>
            </div>
        </div>
        
        <div class="dashboard-section">
            <div class="dashboard-card">
                <div class="card-header">
                    <h2>Examens récents</h2>
                    <a href="manage-exams.php" class="btn btn-sm btn-primary">Voir tous</a>
                </div>
                <div class="table-responsive">
                    <table class="teacher-table">
                        <thead>
                            <tr>
                                <th>Titre</th>
                                <th>Matière</th>
                                <th>Statut</th>
                                <th>Participants</th>
                                <th>Score moyen</th>
                                <th>Date de création</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recentExams->num_rows > 0): ?>
                                <?php while ($exam = $recentExams->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($exam['title']); ?></td>
                                        <td><?php echo htmlspecialchars($exam['subject']); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo $exam['status']; ?>">
                                                <?php echo ucfirst($exam['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $exam['participants']; ?></td>
                                        <td><?php echo $exam['avg_score'] ? round($exam['avg_score'], 1) . '%' : 'N/A'; ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($exam['created_at'])); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="edit-exam.php?id=<?php echo $exam['id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="view-results.php?id=<?php echo $exam['id']; ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-chart-bar"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center">Aucun examen récent</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="dashboard-section">
            <div class="dashboard-row">
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2>Examens à noter</h2>
                        <a href="grade-exams.php" class="btn btn-sm btn-primary">Voir tous</a>
                    </div>
                    <?php if ($examsToGrade->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="teacher-table">
                                <thead>
                                    <tr>
                                        <th>Titre</th>
                                        <th>Matière</th>
                                        <th>Soumissions</th>
                                        <th>En attente</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($exam = $examsToGrade->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($exam['title']); ?></td>
                                            <td><?php echo htmlspecialchars($exam['subject']); ?></td>
                                            <td><?php echo $exam['submissions']; ?></td>
                                            <td>
                                                <span class="badge badge-warning"><?php echo $exam['pending_grades']; ?></span>
                                            </td>
                                            <td>
                                                <a href="grade-exam.php?id=<?php echo $exam['id']; ?>" class="btn btn-sm btn-primary">
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
                            <div class="empty-icon"><i class="fas fa-check-circle"></i></div>
                            <p>Aucun examen en attente de notation</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2>Incidents de surveillance récents</h2>
                        <a href="proctoring-incidents.php" class="btn btn-sm btn-primary">Voir tous</a>
                    </div>
                    <?php if ($proctorIncidents->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="teacher-table">
                                <thead>
                                    <tr>
                                        <th>Examen</th>
                                        <th>Étudiant</th>
                                        <th>Type</th>
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
                            <div class="empty-icon"><i class="fas fa-shield-alt"></i></div>
                            <p>Aucun incident récent</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="dashboard-section">
            <div class="dashboard-card">
                <div class="card-header">
                    <h2>Activité récente</h2>
                </div>
                <div class="activity-timeline">
                    <div class="timeline-item">
                        <div class="timeline-icon"><i class="fas fa-file-alt"></i></div>
                        <div class="timeline-content">
                            <h3>Examen créé</h3>
                            <p>Vous avez créé l'examen "Introduction à la programmation"</p>
                            <span class="timeline-date">Aujourd'hui, 10:30</span>
                        </div>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-icon"><i class="fas fa-user-graduate"></i></div>
                        <div class="timeline-content">
                            <h3>Nouvel étudiant inscrit</h3>
                            <p>L'étudiant "Jean Dupont" s'est inscrit à votre examen</p>
                            <span class="timeline-date">Hier, 15:45</span>
                        </div>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-icon"><i class="fas fa-check-circle"></i></div>
                        <div class="timeline-content">
                            <h3>Examen terminé</h3>
                            <p>L'examen "Mathématiques avancées" est terminé</p>
                            <span class="timeline-date">20/04/2023, 14:20</span>
                        </div>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-icon"><i class="fas fa-exclamation-triangle"></i></div>
                        <div class="timeline-content">
                            <h3>Incident de surveillance</h3>
                            <p>Un incident a été détecté pendant l'examen "Physique quantique"</p>
                            <span class="timeline-date">18/04/2023, 09:15</span>
                        </div>
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
                        <img id="incident-image" src="/placeholder.svg" alt="Preuve de l'incident">
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion du menu latéral
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    const teacherSidebar = document.querySelector('.teacher-sidebar');
    const teacherContent = document.querySelector('.teacher-content');
    
    sidebarToggle.addEventListener('click', function() {
        teacherSidebar.classList.toggle('collapsed');
        teacherContent.classList.toggle('expanded');
    });
    
    // Gestion du dropdown utilisateur
    const userDropdownBtn = document.querySelector('.user-dropdown-btn');
    const userDropdownContent = document.querySelector('.user-dropdown-content');
    
    userDropdownBtn.addEventListener('click', function() {
        userDropdownContent.classList.toggle('show');
    });
    
    // Fermer le dropdown quand on clique ailleurs
    window.addEventListener('click', function(event) {
        if (!event.target.matches('.user-dropdown-btn') && !event.target.closest('.user-dropdown-btn')) {
            if (userDropdownContent.classList.contains('show')) {
                userDropdownContent.classList.remove('show');
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
            document.getElementById('incident-image').src = "../assets/images/incident-example.jpg";
            
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

<style>
/* Styles spécifiques à la page d'accueil de l'enseignant */
.teacher-container {
    display: flex;
    min-height: calc(100vh - 60px);
    background-color: #f5f7fb;
}

.teacher-sidebar {
    width: 250px;
    background-color: #fff;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    position: fixed;
    height: calc(100vh - 60px);
    overflow-y: auto;
    z-index: 100;
    transition: all 0.3s ease;
}

.teacher-sidebar.collapsed {
    width: 70px;
}

.teacher-content {
    flex: 1;
    margin-left: 250px;
    padding: 20px;
    transition: all 0.3s ease;
}

.teacher-content.expanded {
    margin-left: 70px;
}

.sidebar-header {
    padding: 20px 15px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid #e9ecef;
}

.sidebar-header .logo {
    font-size: 20px;
    font-weight: 700;
    color: #4a6cf7;
}

.sidebar-toggle {
    background: none;
    border: none;
    color: #6c757d;
    font-size: 18px;
    cursor: pointer;
}

.sidebar-menu ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.sidebar-menu li {
    margin-bottom: 5px;
}

.sidebar-menu a {
    display: flex;
    align-items: center;
    padding: 12px 15px;
    color: #555;
    text-decoration: none;
    transition: all 0.3s ease;
    border-left: 3px solid transparent;
}

.sidebar-menu a:hover {
    background-color: #f8f9fa;
    color: #4a6cf7;
    border-left-color: #4a6cf7;
}

.sidebar-menu a.active {
    background-color: rgba(74, 108, 247, 0.1);
    color: #4a6cf7;
    border-left-color: #4a6cf7;
}

.sidebar-menu i {
    margin-right: 10px;
    font-size: 16px;
    width: 20px;
    text-align: center;
}

.teacher-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #e9ecef;
}

.teacher-header h1 {
    font-size: 24px;
    font-weight: 600;
    color: #333;
    margin: 0;
}

.user-info {
    display: flex;
    align-items: center;
}

.user-info span {
    margin-right: 20px;
    color: #6c757d;
}

.user-dropdown {
    position: relative;
}

.user-dropdown-btn {
    display: flex;
    align-items: center;
    background: none;
    border: none;
    cursor: pointer;
    padding: 5px 10px;
    border-radius: 4px;
}

.user-dropdown-btn:hover {
    background-color: #f8f9fa;
}

.avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    margin-right: 10px;
}

.user-dropdown-btn i {
    margin-left: 5px;
    color: #6c757d;
}

.user-dropdown-content {
    display: none;
    position: absolute;
    right: 0;
    background-color: #fff;
    min-width: 160px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    z-index: 1;
    border-radius: 4px;
}

.user-dropdown-content.show {
    display: block;
}

.user-dropdown-content a {
    color: #333;
    padding: 12px 16px;
    text-decoration: none;
    display: block;
}

.user-dropdown-content a:hover {
    background-color: #f8f9fa;
}

.user-dropdown-content i {
    margin-right: 10px;
    width: 16px;
    text-align: center;
}

.stats-overview {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.stat-card {
    background-color: #fff;
    border-radius: 4px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    padding: 20px;
    display: flex;
    align-items: center;
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background-color: rgba(74, 108, 247, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
}

.stat-icon i {
    font-size: 20px;
    color: #4a6cf7;
}

.stat-info h3 {
    font-size: 14px;
    font-weight: 500;
    color: #6c757d;
    margin: 0 0 5px 0;
}

.stat-number {
    font-size: 24px;
    font-weight: 600;
    color: #333;
    margin: 0;
}

.dashboard-section {
    margin-bottom: 20px;
}

.dashboard-row {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(450px, 1fr));
    gap: 20px;
}

.dashboard-card {
    background-color: #fff;
    border-radius: 4px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid #e9ecef;
}

.card-header h2 {
    font-size: 18px;
    font-weight: 600;
    margin: 0;
    color: #333;
}

.teacher-table {
    width: 100%;
    border-collapse: collapse;
}

.teacher-table th,
.teacher-table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #e9ecef;
}

.teacher-table th {
    font-weight: 600;
    color: #333;
    background-color: #f8f9fa;
}

.teacher-table tbody tr:hover {
    background-color: #f8f9fa;
}

.status-badge {
    display: inline-block;
    padding: 4px 8px;
    font-size: 12px;
    font-weight: 500;
    line-height: 1;
    text-align: center;
    white-space: nowrap;
    vertical-align: baseline;
    border-radius: 50px;
}

.status-badge.active {
    color: #fff;
    background-color: #28a745;
}

.status-badge.draft {
    color: #212529;
    background-color: #ffc107;
}

.status-badge.completed {
    color: #fff;
    background-color: #6c757d;
}

.action-buttons {
    display: flex;
    gap: 5px;
}

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

.empty-state p {
    color: #6c757d;
    margin: 0;
}

.incident-badge {
    display: inline-block;
    padding: 4px 8px;
    font-size: 12px;
    font-weight: 500;
    line-height: 1;
    text-align: center;
    white-space: nowrap;
    vertical-align: baseline;
    border-radius: 50px;
}

.incident-badge.face_detection {
    color: #fff;
    background-color: #dc3545;
}

.incident-badge.eye_tracking {
    color: #212529;
    background-color: #ffc107;
}

.incident-badge.audio {
    color: #fff;
    background-color: #17a2b8;
}

.incident-badge.screen {
    color: #fff;
    background-color: #6c757d;
}

.activity-timeline {
    padding: 20px;
}

.timeline-item {
    display: flex;
    margin-bottom: 20px;
    position: relative;
}

.timeline-item:last-child {
    margin-bottom: 0;
}

.timeline-item:before {
    content: '';
    position: absolute;
    left: 25px;
    top: 50px;
    bottom: -20px;
    width: 2px;
    background-color: #e9ecef;
}

.timeline-item:last-child:before {
    display: none;
}

.timeline-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background-color: rgba(74, 108, 247, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    z-index: 1;
}

.timeline-icon i {
    font-size: 20px;
    color: #4a6cf7;
}

.timeline-content {
    flex: 1;
    background-color: #f8f9fa;
    border-radius: 4px;
    padding: 15px;
}

.timeline-content h3 {
    font-size: 16px;
    font-weight: 600;
    margin: 0 0 5px 0;
    color: #333;
}

.timeline-content p {
    margin: 0 0 10px 0;
    color: #555;
}

.timeline-date {
    font-size: 12px;
    color: #6c757d;
}

/* Modal */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0, 0, 0, 0.5);
}

.modal-content {
    background-color: #fff;
    margin: 10% auto;
    width: 70%;
    max-width: 800px;
    border-radius: 4px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
    animation: modalFadeIn 0.3s;
}

@keyframes modalFadeIn {
    from {opacity: 0; transform: translateY(-50px);}
    to {opacity: 1; transform: translateY(0);}
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid #e9ecef;
}

.modal-header h2 {
    font-size: 20px;
    font-weight: 600;
    margin: 0;
    color: #333;
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
    border-top: 1px solid #e9ecef;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.incident-details {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.incident-info {
    grid-column: 1 / -1;
    background-color: #f8f9fa;
    padding: 15px;
    border-radius: 4px;
}

.incident-info p {
    margin: 5px 0;
}

.incident-description,
.incident-evidence {
    background-color: #f8f9fa;
    padding: 15px;
    border-radius: 4px;
}

.incident-description h3,
.incident-evidence h3 {
    font-size: 16px;
    font-weight: 600;
    margin: 0 0 10px 0;
    color: #333;
}

.incident-evidence-container {
    text-align: center;
}

.incident-evidence img {
    max-width: 100%;
    border-radius: 4px;
}

/* Responsive */
@media (max-width: 768px) {
    .teacher-sidebar {
        width: 0;
        transform: translateX(-100%);
    }
    
    .teacher-content {
        margin-left: 0;
    }
    
    .teacher-sidebar.show {
        width: 250px;
        transform: translateX(0);
    }
    
    .stats-overview,
    .dashboard-row {
        grid-template-columns: 1fr;
    }
    
    .incident-details {
        grid-template-columns: 1fr;
    }
    
    .modal-content {
        width: 90%;
    }
}
</style>

<?php include '../includes/footer.php'; ?>
