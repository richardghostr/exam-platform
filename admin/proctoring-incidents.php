<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Vérifier si l'utilisateur est connecté et est un administrateur
if (!isLoggedIn() || !isAdmin()) {
    header('Location:login.php');
    exit();
}

// Paramètres de pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$itemsPerPage = 10;
$offset = ($page - 1) * $itemsPerPage;

// Paramètres de filtrage
$examId = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
$studentId = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
$severity = isset($_GET['severity']) ? $_GET['severity'] : '';
$incidentType = isset($_GET['incident_type']) ? $_GET['incident_type'] : '';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';

// Construire la requête de base
$baseQuery = "
    SELECT pi.*, ea.exam_id, e.title as exam_title, u.username, u.first_name, u.last_name
    FROM proctoring_incidents pi
    JOIN exam_attempts ea ON pi.attempt_id = ea.id
    JOIN exams e ON ea.exam_id = e.id
    JOIN users u ON ea.user_id = u.id
    WHERE 1=1
";

$countQuery = "
    SELECT COUNT(*) as total
    FROM proctoring_incidents pi
    JOIN exam_attempts ea ON pi.attempt_id = ea.id
    JOIN exams e ON ea.exam_id = e.id
    JOIN users u ON ea.user_id = u.id
    WHERE 1=1
";

$params = [];
$types = "";

// Ajouter les filtres
if ($examId > 0) {
    $baseQuery .= " AND ea.exam_id = ?";
    $countQuery .= " AND ea.exam_id = ?";
    $params[] = $examId;
    $types .= "i";
}

if ($studentId > 0) {
    $baseQuery .= " AND ea.user_id = ?";
    $countQuery .= " AND ea.user_id = ?";
    $params[] = $studentId;
    $types .= "i";
}

if (!empty($severity)) {
    $baseQuery .= " AND pi.severity = ?";
    $countQuery .= " AND pi.severity = ?";
    $params[] = $severity;
    $types .= "s";
}

if (!empty($incidentType)) {
    $baseQuery .= " AND pi.incident_type = ?";
    $countQuery .= " AND pi.incident_type = ?";
    $params[] = $incidentType;
    $types .= "s";
}

if (!empty($startDate)) {
    $baseQuery .= " AND pi.timestamp >= ?";
    $countQuery .= " AND pi.timestamp >= ?";
    $params[] = $startDate . ' 00:00:00';
    $types .= "s";
}

if (!empty($endDate)) {
    $baseQuery .= " AND pi.timestamp <= ?";
    $countQuery .= " AND pi.timestamp <= ?";
    $params[] = $endDate . ' 23:59:59';
    $types .= "s";
}

if (!empty($status)) {
    $baseQuery .= " AND pi.status = ?";
    $countQuery .= " AND pi.status = ?";
    $params[] = $status;
    $types .= "s";
}

// Exécuter la requête pour obtenir le nombre total d'incidents
$countStmt = $conn->prepare($countQuery);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalItems = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalItems / $itemsPerPage);

// Ajouter l'ordre et la pagination à la requête principale
$baseQuery .= " ORDER BY pi.timestamp DESC LIMIT ?, ?";
$params[] = $offset;
$params[] = $itemsPerPage;
$types .= "ii";

// Exécuter la requête principale
$stmt = $conn->prepare($baseQuery);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Récupérer la liste des examens pour le filtre
$examsQuery = $conn->prepare("
    SELECT id, title 
    FROM exams 
    ORDER BY created_at DESC
");
$examsQuery->execute();
$examsResult = $examsQuery->get_result();
$exams = [];
while ($exam = $examsResult->fetch_assoc()) {
    $exams[] = $exam;
}

// Récupérer la liste des étudiants pour le filtre
$studentsQuery = $conn->prepare("
    SELECT DISTINCT u.id, u.username, u.first_name, u.last_name
    FROM users u
    JOIN exam_attempts ea ON u.id = ea.user_id
    ORDER BY u.last_name, u.first_name
");
$studentsQuery->execute();
$studentsResult = $studentsQuery->get_result();
$students = [];
while ($student = $studentsResult->fetch_assoc()) {
    $students[] = $student;
}

// Récupérer les types d'incidents uniques
$incidentTypesQuery = $conn->query("
    SELECT DISTINCT incident_type 
    FROM proctoring_incidents 
    ORDER BY incident_type
");
$incidentTypes = [];
while ($type = $incidentTypesQuery->fetch_assoc()) {
    $incidentTypes[] = $type['incident_type'];
}

// Traiter les actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'update_status') {
        $incidentId = isset($_POST['incident_id']) ? intval($_POST['incident_id']) : 0;
        $newStatus = isset($_POST['status']) ? $_POST['status'] : '';
        $notes = isset($_POST['notes']) ? $_POST['notes'] : '';

        if ($incidentId > 0 && !empty($newStatus)) {
            $updateQuery = $conn->prepare("
                UPDATE proctoring_incidents 
                SET status = ?, notes = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $updateQuery->bind_param("ssi", $newStatus, $notes, $incidentId);

            if ($updateQuery->execute()) {
                setFlashMessage('success', 'Le statut de l\'incident a été mis à jour avec succès.');
            } else {
                setFlashMessage('danger', 'Une erreur est survenue lors de la mise à jour du statut.');
            }

            // Rediriger pour éviter la soumission multiple du formulaire
            header('Location: ' . $_SERVER['PHP_SELF'] . '?' . http_build_query($_GET));
            exit();
        }
    }
}

// Statistiques des incidents
$statsQuery = $conn->prepare("
    SELECT 
        COUNT(*) as total_incidents,
        SUM(CASE WHEN pi.severity = 'high' THEN 1 ELSE 0 END) as high_severity,
        SUM(CASE WHEN pi.severity = 'medium' THEN 1 ELSE 0 END) as medium_severity,
        SUM(CASE WHEN pi.severity = 'low' THEN 1 ELSE 0 END) as low_severity,
        SUM(CASE WHEN pi.status = 'pending' THEN 1 ELSE 0 END) as pending_incidents,
        SUM(CASE WHEN pi.status = 'reviewed' THEN 1 ELSE 0 END) as reviewed_incidents,
        SUM(CASE WHEN pi.status = 'dismissed' THEN 1 ELSE 0 END) as dismissed_incidents
    FROM proctoring_incidents pi
");
$statsQuery->execute();
$stats = $statsQuery->get_result()->fetch_assoc();

$pageTitle = "Gestion des incidents de surveillance";
$activeMenu = "proctoring";
include 'includes/header.php';
?>

<div class="content-wrapper">
    <style>
        a{
            text-decoration: none;
        }
        /* Container principal */
        .container-fluid {
          width: 100%;
            border-radius: 8px;
        }

        /* Cartes de statistiques */
        .small-box {
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            border-top: 4px solid;
            position: relative;
            overflow: hidden;
        }

        .small-box .inner {
            padding: 15px;
            text-align: center;
        }

        .small-box h3 {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0 170px 10px 0;
            color:rgb(255, 255, 255);
        }

        .small-box p {
            font-size: 1rem;
            color:rgb(255, 255, 255);
            margin-right: 60px;
            
        }

        /* Couleurs spécifiques */
        .small-box.bg-info {
            border-top-color: #17a2b8;
            background-color: #111c44 !important;
            color: #17a2b8;
           
        }

        .small-box.bg-danger {
            border-top-color: #dc3545;
            background-color: #111c44 !important;
            color: #dc3545;
        }

        .small-box.bg-warning {
            border-top-color: #ffc107;
            background-color: #111c44 !important;
            color: #ffc107;
        }

        .small-box.bg-success {
            border-top-color: #28a745;
            background-color: #111c44 !important;
            color: #28a745;
        }

        /* Icônes */
        .small-box .icon {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 70px;
            opacity: 0.2;
            transition: all 0.3s linear;
            margin-right: 10px;
        }

        /* Section de recherche */
      

        /* Adaptation responsive */
        @media (max-width: 768px) {
            .small-box {
                margin-bottom: 15px;
            }

            .small-box h3 {
                font-size: 2rem;
            }

            .small-box .icon {
                font-size: 50px;
            }
        }
    </style>
    

    <section class="content" style="margin-top: 0;">
        <div class="container-fluid">
            <?php displayFlashMessages(); ?>

            <!-- Statistiques des incidents -->
            <div class="row" style="display: flex;justify-content: space-between;">
                <div class="col-lg-3 col-6"style="width: 23%;">
                    <div class="small-box bg-info" >
                        <div class="inner">
                            <h3><?php echo $stats['total_incidents']; ?></h3>
                            <p>Total des incidents</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-6"style="width: 23%;">
                    <div class="small-box bg-danger" >
                        <div class="inner">
                            <h3>
                                <?php echo isset($stats['high_severity']) ? round($stats['high_severity'], 1) : 0; ?>
                            </h3>
                            <p>Incidents critiques</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-radiation"></i>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-6"style="width: 23%;">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3>
                                <?php echo isset($stats['pending_incidents']) ? round($stats['pending_incidents'], 1) : 0; ?></h3>
                            <p>Incidents en attente</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-6"style="width: 23%;">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3>
                                <?php echo isset($stats['reviewed_incidents']) ? round($stats['reviewed_incidents'], 1) : 0; ?></h3>
                            <p>Incidents traités</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtres -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Filtres</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                            <i class="fas fa-minus"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <form method="GET" action="<?php echo $_SERVER['PHP_SELF']; ?>" id="filter-form">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="exam_id">Examen</label>
                                    <select class="form-control" id="exam_id" name="exam_id">
                                        <option value="0">Tous les examens</option>
                                        <?php foreach ($exams as $exam): ?>
                                            <option value="<?php echo $exam['id']; ?>" <?php echo $examId == $exam['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($exam['title']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="student_id">Étudiant</label>
                                    <select class="form-control" id="student_id" name="student_id">
                                        <option value="0">Tous les étudiants</option>
                                        <?php foreach ($students as $student): ?>
                                            <option value="<?php echo $student['id']; ?>" <?php echo $studentId == $student['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($student['last_name'] . ' ' . $student['first_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="severity">Sévérité</label>
                                    <select class="form-control" id="severity" name="severity">
                                        <option value="">Toutes les sévérités</option>
                                        <option value="high" <?php echo $severity === 'high' ? 'selected' : ''; ?>>Critique</option>
                                        <option value="medium" <?php echo $severity === 'medium' ? 'selected' : ''; ?>>Moyenne</option>
                                        <option value="low" <?php echo $severity === 'low' ? 'selected' : ''; ?>>Faible</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="incident_type">Type d'incident</label>
                                    <select class="form-control" id="incident_type" name="incident_type">
                                        <option value="">Tous les types</option>
                                        <?php foreach ($incidentTypes as $type): ?>
                                            <option value="<?php echo $type; ?>" <?php echo $incidentType === $type ? 'selected' : ''; ?>>
                                                <?php echo getIncidentTypeLabel($type); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="start_date">Date de début</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $startDate; ?>">
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="end_date">Date de fin</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $endDate; ?>">
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="status">Statut</label>
                                    <select class="form-control" id="status" name="status">
                                        <option value="">Tous les statuts</option>
                                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>En attente</option>
                                        <option value="reviewed" <?php echo $status === 'reviewed' ? 'selected' : ''; ?>>Traité</option>
                                        <option value="dismissed" <?php echo $status === 'dismissed' ? 'selected' : ''; ?>>Ignoré</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="form-group" style="margin-top: 32px;">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-filter"></i> Filtrer
                                    </button>
                                    <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-default">
                                        <i class="fas fa-sync"></i> Réinitialiser
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Liste des incidents -->
            <div class="card" style="display: flex;flex-direction: column;justify-content: center;">
                <div class="card-header">
                    <h3 class="card-title">Liste des incidents de surveillance</h3>
                </div>
                <div class="card-body">
                    <?php if ($result->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped" style="width:100%;text-align:center">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Examen</th>
                                        <th>Étudiant</th>
                                        <th>Type d'incident</th>
                                        <th>Sévérité</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($incident = $result->fetch_assoc()): ?>
                                       
                                        <tr>
                                            <td><?php echo $incident['id']; ?><br></td>
                                         
                                            <td><?php echo htmlspecialchars($incident['exam_title']); ?><br></td>
                                            <td><?php echo htmlspecialchars($incident['last_name'] . ' ' . $incident['first_name']); ?><br></td>
                                            <td><?php echo getIncidentTypeLabel($incident['incident_type']); ?><br></td>
                                           
                                            <td>
                                                <span class="badge badge-<?php echo getSeverityClass($incident['severity']); ?>">
                                                    <?php echo getSeverityLabel($incident['severity']); ?>
                                                </span><br>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo getStatusClass($incident['status']); ?>">
                                                    <?php echo getStatusLabel($incident['status']); ?>
                                                </span><br>
                                            </td>
                                            <td>
                                                <button class="btn btn-icon btn-sm view-incident"
                                                    data-id="<?php echo $incident['id']; ?>"
                                                    data-exam="<?php echo htmlspecialchars($incident['exam_title']); ?>"
                                                    data-student="<?php echo htmlspecialchars($incident['first_name'] . ' ' . $incident['last_name']); ?>"
                                                    data-type="<?php echo htmlspecialchars($incident['incident_type']); ?>"
                                                    data-date="<?php echo date('d/m/Y H:i', strtotime($incident['timestamp'])); ?>"
                                                    data-details="<?php echo htmlspecialchars($incident['description']); ?>"
                                                    data-image="<?php echo !empty($incident['image_path']) ? htmlspecialchars($incident['image_path']) : '../assets/images/placeholder.jpg'; ?>">
                                                    <i class="fas fa-eye"></i>
                                                </button><br>
                                            </td>
                                        </tr>

                                       
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
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
                                    <form method="POST" action="<?php echo $_SERVER['PHP_SELF'] . '?' . http_build_query($_GET); ?>" style="margin-left: 20px;margin-right:20px">

                                        <div class="form-group">
                                            <label for="status<?php echo $incident['id']; ?>">Mettre à jour le statut</label>
                                            <select class="form-control"   name="status">
                                                <option value="pending">En attente</option>
                                                <option value="reviewed">Traité</option>
                                                <option value="dismissed" >Ignoré</option>
                                            </select>
                                        </div>

                                        <div class="form-group">
                                            <label for="notes<?php echo $incident['id']; ?>">Notes</label>
                                            <textarea class="form-control" id="notes<?php echo $incident['id']; ?>" name="notes" rows="3"><?php echo !empty($incident['notes']) ? htmlspecialchars($incident['notes']) : 'Aucune note'; ?></textarea>
                                        </div>

                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Enregistrer les modifications
                                        </button>
                                    </form><br>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary close-modal">Fermer</button>
                                    </div>
                                </div>
                            </div>
                        </div><br>
                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <div class="pagination-container" >
                                <ul class="pagination" style="display: flex;justify-content:space-between;width:30%;margin-left:20px;">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="<?php echo $_SERVER['PHP_SELF'] . '?' . http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                                <i class="fas fa-chevron-left"></i> Précédent
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="<?php echo $_SERVER['PHP_SELF'] . '?' . http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>

                                    <?php if ($page < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="<?php echo $_SERVER['PHP_SELF'] . '?' . http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                                Suivant <i class="fas fa-chevron-right"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Aucun incident de surveillance n'a été trouvé avec les critères sélectionnés.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialiser les sélecteurs avec recherche
        $('.form-control').select2({
            width: '100%',
            placeholder: 'Sélectionner une option'
        });

        // Validation des dates
        document.getElementById('filter-form').addEventListener('submit', function(e) {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;

            if (startDate && endDate && new Date(startDate) > new Date(endDate)) {
                e.preventDefault();
                alert('La date de début doit être antérieure à la date de fin.');
            }
        });
    });
</script>

<?php

/**
 * Retourne une étiquette lisible pour un type d'incident
 * 
 * @param string $type Type d'incident
 * @return string Étiquette lisible
 */
function getIncidentTypeLabel($type)
{
    switch ($type) {
        case 'face_not_detected':
            return 'Visage non détecté';
        case 'multiple_faces':
            return 'Plusieurs visages détectés';
        case 'face_position':
            return 'Position du visage incorrecte';
        case 'webcam_access_denied':
            return 'Accès à la webcam refusé';
        case 'tab_switch':
            return 'Changement d\'onglet';
        case 'screen_activity':
            return 'Activité suspecte sur l\'écran';
        case 'audio_activity':
            return 'Activité audio suspecte';
        default:
            return ucfirst(str_replace('_', ' ', $type));
    }
}

/**
 * Retourne une classe CSS pour la sévérité d'un incident
 * 
 * @param string $severity Sévérité de l'incident
 * @return string Classe CSS
 */
function getSeverityClass($severity)
{
    switch ($severity) {
        case 'high':
            return 'danger';
        case 'medium':
            return 'warning';
        case 'low':
            return 'info';
        default:
            return 'secondary';
    }
}

/**
 * Retourne une étiquette lisible pour la sévérité d'un incident
 * 
 * @param string $severity Sévérité de l'incident
 * @return string Étiquette lisible
 */
function getSeverityLabel($severity)
{
    switch ($severity) {
        case 'high':
            return 'Critique';
        case 'medium':
            return 'Moyenne';
        case 'low':
            return 'Faible';
        default:
            return 'Inconnue';
    }
}

/**
 * Retourne une classe CSS pour le statut d'un incident
 * 
 * @param string $status Statut de l'incident
 * @return string Classe CSS
 */
function getStatusClass($status)
{
    switch ($status) {
        case 'pending':
            return 'warning';
        case 'reviewed':
            return 'success';
        case 'dismissed':
            return 'secondary';
        default:
            return 'info';
    }
}

/**
 * Retourne une étiquette lisible pour le statut d'un incident
 * 
 * @param string $status Statut de l'incident
 * @return string Étiquette lisible
 */
function getStatusLabel($status)
{
    switch ($status) {
        case 'pending':
            return 'En attente';
        case 'reviewed':
            return 'Traité';
        case 'dismissed':
            return 'Ignoré';
        default:
            return 'Inconnu';
    }
}

/**
 * Formate une date et heure pour l'affichage
 * 
 * @param string $datetime Date et heure au format MySQL
 * @return string Date et heure formatées
 */
function formatDateTime($datetime)
{
    $date = new DateTime($datetime);
    return $date->format('d/m/Y H:i:s');
}
?><script>
    // Gestion du modal d'incident
    const viewIncidentBtns = document.querySelectorAll('.view-incident');
    const incidentModal = document.getElementById('incidentModal');
    const closeModalBtns = document.querySelectorAll('.close-modal');

    if (viewIncidentBtns.length > 0 && incidentModal && closeModalBtns.length > 0) {
        viewIncidentBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                // Récupérer les données de l'incident depuis les attributs data-*
                const exam = this.getAttribute('data-exam');
                const student = this.getAttribute('data-student');
                const type = this.getAttribute('data-type');
                const date = this.getAttribute('data-date');
                const details = this.getAttribute('data-details');
                const imagePath = this.getAttribute('data-image');
     


                // Remplir le modal avec les données
                document.getElementById('incident-exam').textContent = exam;
                document.getElementById('incident-student').textContent = student;
                document.getElementById('incident-type').textContent = type;
                document.getElementById('incident-date').textContent = date;
                document.getElementById('incident-description').textContent = details;
                document.getElementById('incident-image').src = imagePath;
            

                // Afficher le modal
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
        const reviewIncidentBtn = document.getElementById('review-incident');
        if (reviewIncidentBtn) {
            reviewIncidentBtn.addEventListener('click', function() {
                alert('Redirection vers la page de révision détaillée de l\'incident...');
                // Ici, vous redirigeriez normalement vers une page de révision détaillée
            });
        }
    }
</script>

<style>
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

.modal.show {
  display: block;
}

.modal-dialog {
  margin: 10% auto;
  width: 90%;
  max-width: 600px;
}

.modal-content {
    background-color: #1a2357;;
  border-radius: var(--card-border-radius);
  box-shadow: var(--box-shadow);
  animation: modalFadeIn 0.3s;
  border-radius: 20px;
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
  justify-content: space-between;
  align-items: center;
  padding: 1rem 1.5rem;
  border-bottom: 1px solid var(--border-color);
}

.modal-title {
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--dark-color);
  margin: 0;
}

.close-modal {
  background: none;
  border: none;
  font-size: 1.5rem;
  color: var(--secondary-color);
  cursor: pointer;
}

.modal-body {
  padding: 1.5rem;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  padding: 1rem 1.5rem;
  border-top: 1px solid var(--border-color);
}
</style>