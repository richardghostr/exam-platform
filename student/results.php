<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Vérifier si l'utilisateur est connecté et est un étudiant

// Récupérer l'ID de l'étudiant
$studentId = $_SESSION['user_id'];

// Filtres
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$subject = isset($_GET['subject']) ? intval($_GET['subject']) : 0;
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Construire la requête de base
$query = "
    SELECT ea.*, e.title, e.duration, s.name as subject_name, 
    (SELECT COUNT(*) FROM proctoring_incidents WHERE attempt_id = ea.id) as incidents_count
    FROM exam_attempts ea 
    JOIN exams e ON ea.exam_id = e.id 
    JOIN subjects s ON e.subject = s.id 
    WHERE ea.user_id = ? AND ea.status = 'completed'
";

// Ajouter les filtres
$params = [$studentId];
$types = "i";

if ($filter === 'passed') {
    $query .= " AND ea.score >= 60";
} elseif ($filter === 'failed') {
    $query .= " AND ea.score < 60";
} elseif ($filter === 'recent') {
    $query .= " AND ea.end_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
}

if ($subject > 0) {
    $query .= " AND e.subject = ?";
    $params[] = $subject;
    $types .= "i";
}

if (!empty($search)) {
    $query .= " AND (e.title LIKE ? OR s.name LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "ss";
}

// Ajouter ORDER BY
$query .= " ORDER BY ea.end_time DESC";

// Exécuter la requête
$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$results = $stmt->get_result();

// Récupérer les matières pour le filtre
$subjectsQuery = $conn->query("
    SELECT DISTINCT s.id, s.name 
    FROM subjects s 
    JOIN exams e ON s.id = e.subject 
    JOIN exam_attempts ea ON e.id = ea.exam_id 
    WHERE ea.user_id = $studentId AND ea.status = 'completed'
    ORDER BY s.name
");

// Récupérer les statistiques
// 1. Nombre total d'examens passés
$totalExamsQuery = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM exam_attempts 
    WHERE user_id = ? AND status = 'completed'
");
$totalExamsQuery->bind_param("i", $studentId);
$totalExamsQuery->execute();
$totalExams = $totalExamsQuery->get_result()->fetch_assoc()['count'];

// 2. Score moyen
$averageScoreQuery = $conn->prepare("
    SELECT AVG(score) as avg_score 
    FROM exam_attempts 
    WHERE user_id = ? AND status = 'completed'
");
$averageScoreQuery->bind_param("i", $studentId);
$averageScoreQuery->execute();
$averageScore = $averageScoreQuery->get_result()->fetch_assoc()['avg_score'];
$averageScore = $averageScore ? round($averageScore, 1) : 0;

// 3. Nombre d'examens réussis
$passedExamsQuery = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM exam_attempts 
    WHERE user_id = ? AND status = 'completed' AND score >= 60
");
$passedExamsQuery->bind_param("i", $studentId);
$passedExamsQuery->execute();
$passedExams = $passedExamsQuery->get_result()->fetch_assoc()['count'];

// 4. Nombre d'examens échoués
$failedExams = $totalExams - $passedExams;

// 5. Taux de réussite
$successRate = $totalExams > 0 ? round(($passedExams / $totalExams) * 100, 1) : 0;

$pageTitle = "Mes résultats";
include 'includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">Mes résultats</h1>
    <p class="page-subtitle">Consultez vos performances aux examens.</p>
</div>

<div class="stats-container">
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-title">Examens passés</div>
            <div class="stat-icon blue">
                <i class="fas fa-file-alt"></i>
            </div>
        </div>
        <div class="stat-value"><?php echo $totalExams; ?></div>
        <div class="stat-description">
            Total des examens complétés
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-title">Score moyen</div>
            <div class="stat-icon orange">
                <i class="fas fa-star"></i>
            </div>
        </div>
        <div class="stat-value"><?php echo $averageScore; ?>%</div>
        <div class="stat-description">
            <?php 
            if ($averageScore >= 80) {
                echo '<span class="trend-up">Excellent travail!</span>';
            } elseif ($averageScore >= 60) {
                echo '<span class="trend-up">Bon travail!</span>';
            } else {
                echo '<span class="trend-down">Continuez vos efforts</span>';
            }
            ?>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-title">Examens réussis</div>
            <div class="stat-icon green">
                <i class="fas fa-check-circle"></i>
            </div>
        </div>
        <div class="stat-value"><?php echo $passedExams; ?></div>
        <div class="stat-description">
            <span class="trend-up"><?php echo $successRate; ?>% de taux de réussite</span>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-title">Examens échoués</div>
            <div class="stat-icon red">
                <i class="fas fa-times-circle"></i>
            </div>
        </div>
        <div class="stat-value"><?php echo $failedExams; ?></div>
        <div class="stat-description">
            <span class="trend-down"><?php echo $totalExams > 0 ? round(($failedExams / $totalExams) * 100, 1) : 0; ?>% des examens</span>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Filtrer les résultats</h2>
    </div>
    <div class="card-body">
        <form action="" method="GET" class="exams-filter">
            <div class="filter-group">
                <label for="filter" class="filter-label">Statut</label>
                <select name="filter" id="filter" class="filter-select">
                    <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>Tous les résultats</option>
                    <option value="passed" <?php echo $filter === 'passed' ? 'selected' : ''; ?>>Examens réussis</option>
                    <option value="failed" <?php echo $filter === 'failed' ? 'selected' : ''; ?>>Examens échoués</option>
                    <option value="recent" <?php echo $filter === 'recent' ? 'selected' : ''; ?>>Récents (30 jours)</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="subject" class="filter-label">Matière</label>
                <select name="subject" id="subject" class="filter-select">
                    <option value="0">Toutes les matières</option>
                    <?php while ($subject_row = $subjectsQuery->fetch_assoc()): ?>
                        <option value="<?php echo $subject_row['id']; ?>" <?php echo $subject == $subject_row['id'] ? 'selected' : ''; ?>>
                            <?php echo $subject_row['name']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="search" class="filter-label">Recherche</label>
                <input type="text" name="search" id="search" class="filter-input" placeholder="Rechercher un examen..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <div class="filter-group" style="display: flex; align-items: flex-end;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i> Filtrer
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Résultats des examens</h2>
    </div>
    <div class="card-body">
        <?php if ($results->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="results-table">
                    <thead>
                        <tr>
                            <th>Examen</th>
                            <th>Matière</th>
                            <th>Date</th>
                            <th>Durée</th>
                            <th>Score</th>
                            <th>Statut</th>
                            <th>Incidents</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($result = $results->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $result['title']; ?></td>
                                <td><?php echo $result['subject_name']; ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($result['end_time'])); ?></td>
                                <td>
                                    <?php 
                                    $start = new DateTime($result['start_time']);
                                    $end = new DateTime($result['end_time']);
                                    $duration = $start->diff($end);
                                    echo $duration->format('%H:%I:%S');
                                    ?>
                                </td>
                                <td>
                                    <span class="score <?php 
                                        if ($result['score'] >= 80) echo 'score-high';
                                        elseif ($result['score'] >= 60) echo 'score-medium';
                                        else echo 'score-low';
                                    ?>">
                                        <?php echo $result['score']; ?>%
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php 
                                        if ($result['score'] >= 60) echo 'badge-success';
                                        else echo 'badge-danger';
                                    ?>">
                                        <?php echo $result['score'] >= 60 ? 'Réussi' : 'Échoué'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($result['incidents_count'] > 0): ?>
                                        <span class="badge badge-warning">
                                            <?php echo $result['incidents_count']; ?> incident(s)
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">
                                            Aucun
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="exam-result.php?attempt_id=<?php echo $result['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i> Détails
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info" style="text-align: center; padding: 2rem;">
                <i class="fas fa-info-circle fa-2x" style="margin-bottom: 1rem;"></i>
                <h3>Aucun résultat trouvé</h3>
                <p>Vous n'avez pas encore passé d'examens ou aucun résultat ne correspond à vos critères de recherche.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

