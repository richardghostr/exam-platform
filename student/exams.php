<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';


// Récupérer l'ID de l'étudiant
$studentId = $_SESSION['user_id'];

// Filtres
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$subject = isset($_GET['subject']) ? intval($_GET['subject']) : 0;
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Construire la requête de base
$query = "
    SELECT e.*, s.name as subject_name,
    (SELECT COUNT(*) FROM questions WHERE exam_id = e.id) as question_count
    FROM exams e
    JOIN subjects s ON e.subject = s.id
    WHERE 1=1
";

// Ajouter les filtres
$params = [];
$types = "";

// Filtre par statut
if ($filter === 'available') {
    $query .= " AND e.status = 'published' AND e.start_date <= NOW() AND e.end_date >= NOW()";
} elseif ($filter === 'upcoming') {
    $query .= " AND e.status = 'scheduled' AND e.start_date > NOW()";
} elseif ($filter === 'completed') {
    $query .= " AND e.id IN (SELECT exam_id FROM exam_attempts WHERE user_id = ? AND status = 'completed')";
    $params[] = $studentId;
    $types .= "i";
}

// Filtre par matière
if ($subject > 0) {
    $query .= " AND e.subject = ?";
    $params[] = $subject;
    $types .= "i";
}

// Filtre par recherche
if (!empty($search)) {
    $query .= " AND (e.title LIKE ? OR e.description LIKE ? OR s.name LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "sss";
}

// Ajouter la condition pour montrer uniquement les examens accessibles à l'étudiant
$query .= " AND (
    (e.status = 'published' AND e.start_date <= NOW() AND e.end_date >= NOW())
    OR (e.status = 'scheduled' AND e.start_date > NOW())
    OR EXISTS (SELECT 1 FROM exam_enrollments ee WHERE ee.exam_id = e.id AND ee.student_id = ?)
)";
$params[] = $studentId;
$types .= "i";

// Ajouter ORDER BY
if ($filter === 'upcoming') {
    $query .= " ORDER BY e.start_date ASC";
} elseif ($filter === 'available') {
    $query .= " ORDER BY e.end_date ASC";
} else {
    $query .= " ORDER BY e.created_at DESC";
}

// Exécuter la requête
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$exams = $stmt->get_result();

// Récupérer les matières pour le filtre
$subjectsQuery = $conn->query("SELECT id, name FROM subjects ORDER BY name");

// Récupérer les statistiques
// 1. Nombre d'examens disponibles
$availableExamsQuery = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM exams e 
    WHERE e.status = 'published' 
    AND e.start_date <= NOW() 
    AND e.end_date >= NOW()
    AND (
        EXISTS (SELECT 1 FROM exam_enrollments ee WHERE ee.exam_id = e.id AND ee.student_id = ?)
        OR NOT EXISTS (SELECT 1 FROM exam_classes ec WHERE ec.exam_id = e.id)
    )
");
$availableExamsQuery->bind_param("i", $studentId);
$availableExamsQuery->execute();
$availableExams = $availableExamsQuery->get_result()->fetch_assoc()['count'];

// 2. Nombre d'examens à venir
$upcomingExamsQuery = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM exams e 
    WHERE e.status = 'scheduled' 
    AND e.start_date > NOW()
    AND (
        EXISTS (SELECT 1 FROM exam_enrollments ee WHERE ee.exam_id = e.id AND ee.student_id = ?)
        OR NOT EXISTS (SELECT 1 FROM exam_classes ec WHERE ec.exam_id = e.id)
    )
");
$upcomingExamsQuery->bind_param("i", $studentId);
$upcomingExamsQuery->execute();
$upcomingExams = $upcomingExamsQuery->get_result()->fetch_assoc()['count'];

// 3. Nombre d'examens complétés
$completedExamsQuery = $conn->prepare("
    SELECT COUNT(DISTINCT exam_id) as count 
    FROM exam_attempts 
    WHERE user_id = ? AND status = 'completed'
");
$completedExamsQuery->bind_param("i", $studentId);
$completedExamsQuery->execute();
$completedExams = $completedExamsQuery->get_result()->fetch_assoc()['count'];

$pageTitle = "Mes examens";
include 'includes/header.php';
?>

<div class="page-header">
    
</div>


<br>
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Filtrer les examens</h2>
    </div>
    <div class="card-body">
        <form action="" method="GET" class="exams-filter">
            <div class="filter-group">
                <label for="filter" class="filter-label">Statut</label>
                <select name="filter" id="filter" class="filter-select">
                    <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>Tous les examens</option>
                    <option value="available" <?php echo $filter === 'available' ? 'selected' : ''; ?>>Examens disponibles</option>
                    <option value="upcoming" <?php echo $filter === 'upcoming' ? 'selected' : ''; ?>>Examens à venir</option>
                    <option value="completed" <?php echo $filter === 'completed' ? 'selected' : ''; ?>>Examens complétés</option>
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

<div class="exams-container">
    <?php if ($exams->num_rows > 0): ?>
        <?php while ($exam = $exams->fetch_assoc()): ?>
            <?php
            // Déterminer le statut de l'examen
            $now = new DateTime();
            $startDate = new DateTime($exam['start_date']);
            $endDate = new DateTime($exam['end_date']);
            
            if ($exam['status'] === 'scheduled' && $startDate > $now) {
                $status = 'upcoming';
                $statusText = 'À venir';
            } elseif ($exam['status'] === 'published' && $now >= $startDate && $now <= $endDate) {
                $status = 'available';
                $statusText = 'Disponible';
            } else {
                // Vérifier si l'étudiant a déjà passé cet examen
                $attemptQuery = $conn->prepare("
                    SELECT id, score FROM exam_attempts 
                    WHERE exam_id = ? AND user_id = ? AND status = 'completed'
                    ORDER BY end_time DESC LIMIT 1
                ");
                $attemptQuery->bind_param("ii", $exam['id'], $studentId);
                $attemptQuery->execute();
                $attemptResult = $attemptQuery->get_result();
                
                if ($attemptResult->num_rows > 0) {
                    $attempt = $attemptResult->fetch_assoc();
                    $status = 'completed';
                    $statusText = 'Complété';
                    $score = $attempt['score'];
                    $attemptId = $attempt['id'];
                } else {
                    $status = 'expired';
                    $statusText = 'Expiré';
                }
            }
            ?>
            <div class="exam-card status-<?php echo $status; ?>">
                <div class="exam-header">
                    <span class="exam-subject"><?php echo $exam['subject_name']; ?></span>
                    <h3 class="exam-title"><?php echo $exam['title']; ?></h3>
                    <div class="exam-info">
                        <div class="exam-info-item">
                            <i class="fas fa-clock"></i>
                            <?php echo $exam['duration']; ?> minutes
                        </div>
                        <div class="exam-info-item">
                            <i class="fas fa-question-circle"></i>
                            <?php echo $exam['question_count']; ?> questions
                        </div>
                        <?php if ($exam['is_proctored']): ?>
                            <div class="exam-info-item">
                                <i class="fas fa-video"></i>
                                Surveillé
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="exam-body">
                    <div class="exam-meta">
                        <?php if ($status === 'upcoming'): ?>
                            <div class="exam-date">
                                <i class="fas fa-calendar-alt"></i>
                                Début: <?php echo date('d/m/Y H:i', strtotime($exam['start_date'])); ?>
                            </div>
                        <?php elseif ($status === 'available'): ?>
                            <div class="exam-date">
                                <i class="fas fa-calendar-alt"></i>
                                Fin: <?php echo date('d/m/Y H:i', strtotime($exam['end_date'])); ?>
                            </div>
                        <?php elseif ($status === 'completed'): ?>
                            <div class="exam-score">
                                <i class="fas fa-star"></i>
                                Score: <span class="score <?php echo $score >= 60 ? 'score-high' : 'score-low'; ?>"><?php echo $score; ?>%</span>
                            </div>
                        <?php endif; ?>
                        <span class="exam-status status-<?php echo $status; ?>"><?php echo $statusText; ?></span>
                    </div>
                    <p class="exam-description">
                        <?php 
                        echo strlen($exam['description']) > 150 ? 
                            substr($exam['description'], 0, 150) . '...' : 
                            $exam['description']; 
                        ?>
                    </p>
                </div>
                <div class="exam-footer">
                    <?php if ($status === 'available'): ?>
                        <a href="take-exam.php?id=<?php echo $exam['id']; ?>" class="btn btn-primary btn-block">
                            <i class="fas fa-play-circle"></i> Commencer l'examen
                        </a>
                    <?php elseif ($status === 'upcoming'): ?>
                        <button class="btn btn-outline-primary btn-block" disabled>
                            <i class="fas fa-clock"></i> Pas encore disponible
                        </button>
                    <?php elseif ($status === 'completed'): ?>
                        <a href="exam-result.php?attempt_id=<?php echo $attemptId; ?>" class="btn btn-outline-primary btn-block">
                            <i class="fas fa-eye"></i> Voir les résultats
                        </a>
                    <?php else: ?>
                        <button class="btn btn-outline-secondary btn-block" disabled>
                            <i class="fas fa-times-circle"></i> Expiré
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            Aucun examen ne correspond à vos critères de recherche.
        </div>
    <?php endif; ?>
</div>


