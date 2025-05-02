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
$activeExams = $conn->query("SELECT COUNT(*) as count FROM exams WHERE teacher_id = $teacherId AND status = 'active'")->fetch_assoc()['count'];
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
        AVG(er.score) as avg_score,
        COUNT(er.id) as count
    FROM exam_results er
    JOIN exams e ON er.exam_id = e.id
    WHERE e.teacher_id = $teacherId AND er.status = 'completed'
    GROUP BY e.subject
    ORDER BY avg_score DESC
");
$avgScoreResult = $conn->query("
    SELECT AVG(er.score) as avg_score 
    FROM exam_results er 
    JOIN exams e ON er.exam_id = e.id 
    WHERE e.teacher_id = $teacherId AND er.status = 'completed'
")->fetch_assoc();
$avgScore = $avgScoreResult['avg_score'] !== null ? round($avgScoreResult['avg_score'], 1) : 0;
// Récupérer les incidents de surveillance
$proctorIncidents = $conn->query("
    SELECT 
        p.id,
        p.exam_id,
        e.title as exam_title,
        u.id as user_id,
        u.username,
        u.first_name,
        u.last_name,
        p.incident_type,
        p.timestamp,
        p.details,
        p.status
    FROM proctoring_incidents p
    JOIN exams e ON p.exam_id = e.id
    JOIN users u ON p.user_id = u.id
    WHERE e.teacher_id = $teacherId
    ORDER BY p.timestamp DESC
    LIMIT 10
");

// Récupérer les examens pour le formulaire de génération de rapports
$exams = $conn->query("
    SELECT id, title, subject
    FROM exams
    WHERE teacher_id = $teacherId
    ORDER BY created_at DESC
");

$pageTitle = "Rapports et Statistiques";
include 'includes/header.php';
?>
<style>
    /* Style général */
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-color: #f8f9fa;
    color: #333;
    line-height: 1.6;
}

/* En-tête */
.d-flex.justify-content-between {
    padding: 15px 20px;
    background-color: #fff;
    border-bottom: 1px solid #e0e0e0;
}

.h2 {
    font-size: 1.5rem;
    font-weight: 600;
    margin: 0;
    color: #333;
}

/* Cartes de statistiques */
.stat-card {
    border: none;
    border-radius: 10px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    transition: transform 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.card-body {
    padding: 20px;
}

.card-subtitle {
    font-size: 0.75rem;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
}

.bg-primary-light {
    background-color: rgba(13, 110, 253, 0.1);
}

.bg-success-light {
    background-color: rgba(25, 135, 84, 0.1);
}

.bg-info-light {
    background-color: rgba(13, 202, 240, 0.1);
}

.bg-warning-light {
    background-color: rgba(255, 193, 7, 0.1);
}

/* Graphiques */
.card {
    border: none;
    border-radius: 10px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    margin-bottom: 20px;
}

.card-header {
    background-color: #fff;
    border-bottom: 1px solid #e0e0e0;
    padding: 15px 20px;
}

/* Tableau des incidents */
.table {
    width: 100%;
    border-collapse: collapse;
}

.table th {
    background-color: #f8f9fa;
    font-weight: 600;
    padding: 12px 15px;
    text-align: left;
    border-bottom: 2px solid #e0e0e0;
}

.table td {
    padding: 12px 15px;
    border-bottom: 1px solid #e0e0e0;
    vertical-align: middle;
}

.table-hover tbody tr:hover {
    background-color: rgba(0, 0, 0, 0.02);
}

.avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background-color: #4e73df;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.75rem;
    margin-right: 10px;
}

/* Badges */
.badge {
    font-size: 0.75rem;
    font-weight: 600;
    padding: 4px 8px;
    border-radius: 4px;
}

.bg-primary {
    background-color: #4e73df;
}

.bg-success {
    background-color: #1cc88a;
}

.bg-warning {
    background-color: #f6c23e;
    color: #000;
}

.bg-danger {
    background-color: #e74a3b;
}

.bg-info {
    background-color: #36b9cc;
}

/* Boutons */
.btn {
    font-size: 0.875rem;
    font-weight: 500;
    padding: 8px 16px;
    border-radius: 4px;
    transition: all 0.3s ease;
}

.btn-sm {
    padding: 5px 10px;
    font-size: 0.75rem;
}

.btn-outline-secondary {
    border-color: #d1d3e2;
    color: #6e707e;
}

.btn-outline-secondary:hover {
    background-color: #f8f9fa;
    border-color: #d1d3e2;
}

.btn-primary {
    background-color: #4e73df;
    border-color: #4e73df;
}

.btn-primary:hover {
    background-color: #3a5bc7;
    border-color: #3a5bc7;
}

.btn-outline-primary {
    border-color: #4e73df;
    color: #4e73df;
}

.btn-outline-primary:hover {
    background-color: #4e73df;
    color: #fff;
}

/* Formulaire */
.form-label {
    font-size: 0.875rem;
    font-weight: 500;
    margin-bottom: 5px;
}

.form-control, .form-select {
    padding: 8px 12px;
    font-size: 0.875rem;
    border: 1px solid #d1d3e2;
    border-radius: 4px;
}

.form-control:focus, .form-select:focus {
    border-color: #bac8f3;
    box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
}

/* Modals */
.modal-content {
    border: none;
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.modal-header {
    border-bottom: 1px solid #e0e0e0;
    padding: 15px 20px;
}

.modal-title {
    font-size: 1.25rem;
    font-weight: 600;
}

.modal-body {
    padding: 20px;
}

.modal-footer {
    border-top: 1px solid #e0e0e0;
    padding: 15px 20px;
}

/* Utilitaires */
.mb-4 {
    margin-bottom: 1.5rem !important;
}

.mt-4 {
    margin-top: 1.5rem !important;
}

.py-5 {
    padding-top: 3rem !important;
    padding-bottom: 3rem !important;
}

.text-muted {
    color: #858796 !important;
}

.text-success {
    color: #1cc88a !important;
}

.text-warning {
    color: #f6c23e !important;
}

.text-info {
    color: #36b9cc !important;
}

/* Responsive */
@media (max-width: 768px) {
    .col-md-3, .col-md-6 {
        flex: 0 0 100%;
        max-width: 100%;
    }
    
    .stat-card {
        margin-bottom: 15px;
    }
}
</style>
<div class="container-fluid">
    <div class="row" style="width: 100%;">
        <!-- Sidebar --
        
        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4" style="margin-top: 30px;">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom" style="margin-bottom:20px;border-radius: 20px;margin-right:20px;margin-left:25px;background-color: white;box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);">
                <h1 class="h2"><?php echo $pageTitle; ?></h1><br>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#exportModal">
                            <i class="fas fa-download me-1"></i> Exporter
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="printReport">
                            <i class="fas fa-print me-1"></i> Imprimer
                        </button>
                    </div><br>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#generateReportModal">
                        <i class="fas fa-file-alt me-1"></i> Générer un rapport
                    </button>
                </div>
            </div>
            
            <!-- Cartes de statistiques -->
            <div class="row mb-4" style="display: flex;flex-wrap:wrap;margin-left:25px;margin-top:0px;justify-content:space-between;margin-right:20px" >
                <div class="col-md-3" style="width: 23%; margin-top  : 20px;">
                    <div class="card stat-card" style="display: flex;flex-direction:column;justify-content:space-between;height: 100%;">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-subtitle text-muted">Total des examens</h6>
                                    <h2 class="mt-2 mb-0"><?php echo $totalExams; ?></h2>
                                </div>
                                <div class="stat-icon bg-primary-light text-primary" style="margin-left: -8px;">
                                    <i class="fas fa-file-alt" ></i>
                                </div>
                            </div>
                            <div class="progress mt-3" style="height: 5px;">
                                <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo min(100, ($totalExams / 50) * 100); ?>%"></div>
                            </div>
                            <div class="mt-2 small">
                                <span class="text-success">
                                    <i class="fas fa-arrow-up"></i> <?php echo $activeExams; ?>
                                </span>
                                <span class="text-muted ms-1">examens actifs</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3" style="width: 23%;margin-top: 20px;">
                    <div class="card stat-card" style="display: flex;flex-direction:column;justify-content:space-between;height: 100%;">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-subtitle text-muted">Étudiants Inscrits</h6>
                                    <h2 class="mt-2 mb-0"><?php echo $totalStudents; ?></h2>
                                </div>
                                <div class="stat-icon bg-success-light text-success" style="margin-left: -8px;">
                                    <i class="fas fa-user-graduate"></i>
                                </div>
                            </div>
                            <div class="progress mt-3" style="height: 5px;">
                                <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo min(100, ($totalStudents / 100) * 100); ?>%"></div>
                            </div>
                            <div class="mt-2 small">
                                <span class="text-success">
                                    <i class="fas fa-arrow-up"></i> 8%
                                </span>
                                <span class="text-muted ms-1">ce mois</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3" style="width: 23%;margin-top  : 20px;">
                    <div class="card stat-card" style="display: flex;flex-direction:column;justify-content:space-between;height: 100%;">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-subtitle text-muted">Examens complétés</h6>
                                    <h2 class="mt-2 mb-0"><?php echo $totalCompletedExams; ?></h2>
                                </div>
                                <div class="stat-icon bg-info-light text-info" style="margin-left: -8px;">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                            </div>
                            <div class="progress mt-3" style="height: 5px;">
                                <div class="progress-bar bg-info" role="progressbar" style="width: <?php echo $totalExams > 0 ? ($totalCompletedExams / $totalExams) * 100 : 0; ?>%"></div>
                            </div>
                            <div class="mt-2 small">
                                <span class="text-info">
                                    <?php echo $totalExams > 0 ? round(($totalCompletedExams / $totalExams) * 100) : 0; ?>%
                                </span>
                                <span class="text-muted ms-1">taux de complétion</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3" style="width: 23%;margin-top  : 20px;">
                    <div class="card stat-card" style="display: flex;flex-direction:column;justify-content:space-between;height: 100%;">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-subtitle text-muted">Score moyen</h6>
                                    <h2 class="mt-2 mb-0"><?php echo round($avgScore, 1); ?>%</h2>
                                </div>
                                <div class="stat-icon bg-warning-light text-warning" style="margin-left: -8px;">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                            </div>
                            <div class="progress mt-3" style="height: 5px;">
                                <div class="progress-bar bg-warning" role="progressbar" style="width: <?php echo $avgScore; ?>%"></div>
                            </div>
                            <div class="mt-2 small">
                                <span class="<?php echo $avgScore >= 70 ? 'text-success' : 'text-warning'; ?>">
                                    <i class="fas fa-<?php echo $avgScore >= 70 ? 'arrow-up' : 'arrow-down'; ?>"></i> 
                                    <?php echo $avgScore >= 70 ? 'Bon' : 'Moyen'; ?>
                                </span>
                                <span class="text-muted ms-1">niveau global</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Graphiques -->
            <div class="row mb-4" >
                <div class="col-md-6" style="border-radius: 20px;width:96.5%;margin-left:25px;background-color: white;box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);">
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center" style="border-top-left-radius: 20px;border-top-right-radius: 20px;">
                            <h5 class="mb-0">Examens par mois</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="examsChart" height="250"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6" style="border-radius: 20px;width:96.5%;margin-left:25px;background-color: white;box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);">
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center" style="border-top-left-radius: 20px;border-top-right-radius: 20px;">
                            <h5 class="mb-0">Scores moyens par matière</h5>
                        
                        </div>
                        <div class="card-body">
                            <canvas id="subjectsChart" height="250"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Incidents de surveillance -->
            <div class="card mb-4" style="border-radius: 20px;width:96.5%;margin-left:25px;background-color: white;box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);">
                <div class="card-header d-flex justify-content-between align-items-center" style="border-top-left-radius: 20px;border-top-right-radius: 20px;">
                    <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2 text-warning" style="margin-right: 5px;"></i>Incidents de surveillance</h5>
                    <a href="all-incidents.php" class="btn btn-sm btn-outline-primary" style="text-decoration: none;">
                        Voir tous les incidents
                    </a>
                </div>
                <div class="card-body">
                    <?php if ($proctorIncidents->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Étudiant</th>
                                        <th>Examen</th>
                                        <th>Type d'incident</th>
                                        <th>Date</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($incident = $proctorIncidents->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar me-2">
                                                        <?php echo strtoupper(substr($incident['first_name'], 0, 1) . substr($incident['last_name'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <?php echo htmlspecialchars($incident['first_name'] . ' ' . $incident['last_name']); ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($incident['exam_title']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo getIncidentClass($incident['incident_type']); ?>">
                                                    <?php echo htmlspecialchars($incident['incident_type']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($incident['timestamp'])); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $incident['status'] === 'resolved' ? 'success' : 'warning'; ?>">
                                                    <?php echo ucfirst($incident['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-info view-incident" data-id="<?php echo $incident['id']; ?>" data-bs-toggle="modal" data-bs-target="#incidentModal">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <a href="review-incident.php?id=<?php echo $incident['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5" style="text-align: center;">
                            <div class="mb-3">
                                <i class="fas fa-shield-alt text-success fa-4x"></i>
                            </div>
                            <h4>Aucun incident récent</h4>
                            <p class="text-muted">Aucun incident de surveillance n'a été détecté récemment.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Formulaire de génération de rapports -->
            <div class="card mb-4" style="border-radius: 20px;width:96.5%;margin-left:25px;background-color: white;box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);">
                <div class="card-header" style="border-top-left-radius: 20px;border-top-right-radius: 20px;">
                    <h5 class="mb-0"><i class="fas fa-file-alt me-2" style="margin-right: 3px;"></i>Générer des rapports personnalisés</h5>
                </div>
                <div class="card-body">
                    <form id="reportForm" class="row g-3">
                        <div class="col-md-4">
                            <label for="reportType" class="form-label" >Type de rapport</label>
                            <select id="reportType" name="reportType" class="form-select">
                                <option value="exam_results">Résultats d'examens</option>
                                <option value="student_performance">Performance des étudiants</option>
                                <option value="proctoring_incidents">Incidents de surveillance</option>
                                <option value="question_analysis">Analyse des questions</option>
                            </select>
                        </div>
                        <br>
                        <div class="col-md-4">
                            <label for="examId" class="form-label">Examen</label>
                            <select id="examId" name="examId" class="form-select">
                                <option value="all">Tous les examens</option>
                                <?php while ($exam = $exams->fetch_assoc()): ?>
                                    <option value="<?php echo $exam['id']; ?>"><?php echo htmlspecialchars($exam['title']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <br>
                        <div class="col-md-4">
                            <label for="format" class="form-label">Format</label>
                            <select id="format" name="format" class="form-select">
                                <option value="pdf">PDF</option>
                                <option value="excel">Excel</option>
                                <option value="csv">CSV</option>
                            </select>
                        </div>
                        
                        <div class="col-12 mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-file-download me-1"></i> Générer le rapport
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Modal pour afficher les détails d'un incident -->
<div class="modal fade" id="incidentModal" tabindex="-1" aria-labelledby="incidentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="incidentModalLabel">Détails de l'incident</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <h6>Informations générales</h6>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between">
                                    <span>Examen:</span>
                                    <span id="incident-exam" class="fw-bold"></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span>Étudiant:</span>
                                    <span id="incident-student" class="fw-bold"></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span>Type d'incident:</span>
                                    <span id="incident-type" class="fw-bold"></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span>Date:</span>
                                    <span id="incident-date" class="fw-bold"></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span>Statut:</span>
                                    <span id="incident-status" class="fw-bold"></span>
                                </li>
                            </ul>
                        </div>
                        
                        <div>
                            <h6>Description</h6>
                            <p id="incident-description" class="p-3 bg-light rounded"></p>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <h6>Preuve</h6>
                        <div id="incident-evidence-container" class="text-center">
                            <img id="incident-image" src="../assets/images/placeholder.jpg" alt="Preuve de l'incident" class="img-fluid rounded">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                <button type="button" class="btn btn-primary" id="review-incident">Examiner</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour exporter les rapports -->
<div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exportModalLabel">Exporter les données</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form>
                    <div class="mb-3">
                        <label for="exportType" class="form-label">Type de données</label>
                        <select id="exportType" class="form-select">
                            <option value="all_data">Toutes les données</option>
                            <option value="exam_results">Résultats d'examens</option>
                            <option value="student_data">Données des étudiants</option>
                            <option value="incidents">Incidents de surveillance</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="exportFormat" class="form-label">Format</label>
                        <select id="exportFormat" class="form-select">
                            <option value="excel">Excel (.xlsx)</option>
                            <option value="csv">CSV</option>
                            <option value="pdf">PDF</option>
                            <option value="json">JSON</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="dateRange" class="form-label">Période</label>
                        <select id="dateRange" class="form-select">
                            <option value="all_time">Tout le temps</option>
                            <option value="this_month">Ce mois</option>
                            <option value="last_month">Mois dernier</option>
                            <option value="this_year">Cette année</option>
                            <option value="custom">Personnalisé</option>
                        </select>
                    </div>
                    <div class="mb-3 date-range-custom d-none">
                        <div class="row">
                            <div class="col-md-6">
                                <label for="startDate" class="form-label">Date de début</label>
                                <input type="date" class="form-control" id="startDate">
                            </div>
                            <div class="col-md-6">
                                <label for="endDate" class="form-label">Date de fin</label>
                                <input type="date" class="form-control" id="endDate">
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary">Exporter</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour générer un rapport -->
<div class="modal fade" id="generateReportModal" tabindex="-1" aria-labelledby="generateReportModalLabel" aria-hidden="true" style="background-color: red;">
    <div class="modal-dialog modal-lg" >
        <div class="modal-content" style="border-radius: 20px;width:96.5%;margin-left:25px;background-color: white;box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);">
            <div class="modal-header">
                <h5 class="modal-title" id="generateReportModalLabel">Générer un rapport détaillé</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="reportTitle" class="form-label">Titre du rapport</label>
                            <input type="text" class="form-control" id="reportTitle" placeholder="Ex: Rapport trimestriel des examens">
                        </div>
                        <div class="col-md-6">
                            <label for="reportCategory" class="form-label">Catégorie</label>
                            <select id="reportCategory" class="form-select">
                                <option value="performance">Performance</option>
                                <option value="analytics">Analytique</option>
                                <option value="summary">Résumé</option>
                                <option value="detailed">Détaillé</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="reportPeriod" class="form-label">Période</label>
                            <select id="reportPeriod" class="form-select">
                                <option value="last_week">Dernière semaine</option>
                                <option value="last_month">Dernier mois</option>
                                <option value="last_quarter">Dernier trimestre</option>
                                <option value="last_year">Dernière année</option>
                                <option value="custom">Personnalisé</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="reportFormat" class="form-label">Format</label>
                            <select id="reportFormat" class="form-select">
                                <option value="pdf">PDF</option>
                                <option value="docx">Word (.docx)</option>
                                <option value="pptx">PowerPoint (.pptx)</option>
                                <option value="html">HTML</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Éléments à inclure</label>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="" id="includeOverview" checked>
                                    <label class="form-check-label" for="includeOverview">
                                        Vue d'ensemble
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="" id="includeExamStats" checked>
                                    <label class="form-check-label" for="includeExamStats">
                                        Statistiques des examens
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="" id="includeStudentPerf" checked>
                                    <label class="form-check-label" for="includeStudentPerf">
                                        Performance des étudiants
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="" id="includeCharts" checked>
                                    <label class="form-check-label" for="includeCharts">
                                        Graphiques et visualisations
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="" id="includeIncidents">
                                    <label class="form-check-label" for="includeIncidents">
                                        Incidents de surveillance
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="" id="includeRecommendations">
                                    <label class="form-check-label" for="includeRecommendations">
                                        Recommandations
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reportNotes" class="form-label">Notes additionnelles</label>
                        <textarea class="form-control" id="reportNotes" rows="3" placeholder="Ajoutez des notes ou des commentaires à inclure dans le rapport..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary">Générer</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialisation des tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });
    
    // Configuration du graphique des examens par mois
    const months = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'];
    const examsCtx = document.getElementById('examsChart').getContext('2d');
    const examsChart = new Chart(examsCtx, {
        type: 'bar',
        data: {
            labels: months,
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
            },
            plugins: {
                legend: {
                    position: 'top',
                }
            }
        }
    });
    
    // Configuration du graphique circulaire des matières
    const subjectsCtx = document.getElementById('subjectsChart').getContext('2d');
    const subjectsChart = new Chart(subjectsCtx, {
        type: 'doughnut',
        data: {
            labels: ['Mathématiques', 'Physique', 'Chimie', 'Biologie', 'Informatique'],
            datasets: [{
                data: [35, 25, 20, 15, 5],
                backgroundColor: [
                    '#4e73df',
                    '#1cc88a',
                    '#36b9cc',
                    '#f6c23e',
                    '#e74a3b'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: {
                    position: 'right',
                }
            }
        }
    });
    
    // Gestion du modal d'incident
    const viewIncidentBtns = document.querySelectorAll('.view-incident');
    viewIncidentBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            const incidentId = this.getAttribute('data-id');
            // Ici, vous feriez normalement une requête AJAX pour récupérer les détails de l'incident
            // Pour l'exemple, nous allons simplement remplir le modal avec des données fictives
            document.getElementById('incident-exam').textContent = "Physique quantique";
            document.getElementById('incident-student').textContent = "Jean Dupont";
            document.getElementById('incident-type').textContent = "Détection de visage";
            document.getElementById('incident-date').textContent = "18/04/2023, 09:15";
            document.getElementById('incident-status').textContent = "En attente";
            document.getElementById('incident-status').className = "fw-bold text-warning";
            document.getElementById('incident-description').textContent = "L'étudiant a quitté le champ de vision de la caméra pendant plus de 30 secondes.";
        });
    });
    
    // Gestion du bouton d'examen d'incident
    document.getElementById('review-incident').addEventListener('click', function() {
        window.location.href = 'review-incident.php?id=1'; // Remplacer par l'ID réel
    });
    
    // Gestion du formulaire de génération de rapports
    document.getElementById('reportForm').addEventListener('submit', function(e) {
        e.preventDefault();
        // Ici, vous traiteriez normalement le formulaire pour générer le rapport
        alert('Génération du rapport en cours...');
    });
    
    // Gestion du bouton d'impression
    document.getElementById('printReport').addEventListener('click', function() {
        window.print();
    });
    
    // Gestion de l'affichage des dates personnalisées dans le modal d'export
    document.getElementById('dateRange').addEventListener('change', function() {
        const dateRangeCustom = document.querySelector('.date-range-custom');
        if (this.value === 'custom') {
            dateRangeCustom.classList.remove('d-none');
        } else {
            dateRangeCustom.classList.add('d-none');
        }
    });
});

// Fonction pour obtenir la classe de couleur en fonction du type d'incident
function getIncidentClass(type) {
    switch(type) {
        case 'Face Detection':
            return 'warning';
        case 'Multiple Faces':
            return 'danger';
        case 'No Face':
            return 'danger';
        case 'Tab Switch':
            return 'warning';
        case 'Audio Detection':
            return 'info';
        default:
            return 'secondary';
    }
}
</script>