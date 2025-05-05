<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';


$up=$conn->prepare("UPDATE `exams` SET `is_proctored`=1 WHERE `proctoring_enabled`=1");
$up->execute();
// Récupérer l'ID de l'étudiant
$studentId = $_SESSION['user_id'];

// Récupérer les statistiques de l'étudiant
// 1. Nombre total d'examens passés
$examsCompletedQuery = $conn->prepare("
    SELECT COUNT(DISTINCT ea.exam_id) as count 
    FROM exam_attempts ea 
    WHERE ea.user_id = ? AND ea.status = 'completed'
");
$examsCompletedQuery->bind_param("i", $studentId);
$examsCompletedQuery->execute();
$examsCompleted = $examsCompletedQuery->get_result()->fetch_assoc()['count'];

// 2. Nombre d'examens disponibles
// Modification: Utiliser exam_enrollments au lieu de class_students
$availableExamsQuery = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM exams e 
    WHERE (e.status = 'published' AND e.start_date <= NOW() AND e.end_date >= NOW())
    OR EXISTS (
        SELECT 1 FROM exam_enrollments ee 
        WHERE ee.exam_id = e.id AND ee.student_id = ? AND ee.status = 'enrolled'
    )
");
$availableExamsQuery->bind_param("i", $studentId);
$availableExamsQuery->execute();
$availableExams = $availableExamsQuery->get_result()->fetch_assoc()['count'];

// 3. Score moyen
$averageScoreQuery = $conn->prepare("
    SELECT AVG(ea.score) as avg_score 
    FROM exam_attempts ea 
    WHERE ea.user_id = ? AND ea.status = 'completed'
");
$averageScoreQuery->bind_param("i", $studentId);
$averageScoreQuery->execute();
$averageScore = $averageScoreQuery->get_result()->fetch_assoc()['avg_score'];
$averageScore = $averageScore ? round($averageScore, 1) : 0;

// 4. Nombre d'examens à venir
$upcomingExamsQuery = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM exams e 
    WHERE e.status = 'scheduled' 
    AND e.start_date > NOW()
    AND (
        EXISTS (SELECT 1 FROM exam_enrollments ee WHERE ee.exam_id = e.id AND ee.student_id = ?)
        OR e.created_by IN (SELECT teacher_id FROM classes c JOIN class_enrollments ce ON c.id = ce.class_id WHERE ce.user_id = ?)
    )
");
$upcomingExamsQuery->bind_param("ii", $studentId, $studentId);
$upcomingExamsQuery->execute();
$upcomingExams = $upcomingExamsQuery->get_result()->fetch_assoc()['count'];

// Récupérer les examens à venir
$upcomingExamsListQuery = $conn->prepare("
    SELECT e.*, s.name as subject_name 
    FROM exams e 
    JOIN subjects s ON e.subject = s.id 
    WHERE e.status = 'scheduled' 
    AND e.start_date > NOW() 
    AND (
        EXISTS (SELECT 1 FROM exam_enrollments ee WHERE ee.exam_id = e.id AND ee.student_id = ?)
        OR e.created_by IN (SELECT teacher_id FROM classes c JOIN class_enrollments ce ON c.id = ce.class_id WHERE ce.user_id = ?)
    )
    ORDER BY e.start_date ASC 
    LIMIT 5
");
$upcomingExamsListQuery->bind_param("ii", $studentId, $studentId);
$upcomingExamsListQuery->execute();
$upcomingExamsList = $upcomingExamsListQuery->get_result();

// Récupérer les examens disponibles
$availableExamsListQuery = $conn->prepare("
    SELECT e.*, s.name as subject_name 
    FROM exams e 
    JOIN subjects s ON e.subject = s.id 
    WHERE (
        (e.status = 'published' AND e.start_date <= NOW() AND e.end_date >= NOW())
        OR EXISTS (SELECT 1 FROM exam_enrollments ee WHERE ee.exam_id = e.id AND ee.student_id = ? AND ee.status = 'enrolled')
    )
    ORDER BY e.end_date ASC 
    LIMIT 5
");
$availableExamsListQuery->bind_param("i", $studentId);
$availableExamsListQuery->execute();
$availableExamsList = $availableExamsListQuery->get_result();

// Récupérer les derniers résultats
$recentResultsQuery = $conn->prepare("
    SELECT ea.*, e.title, e.duration, s.name as subject_name 
    FROM exam_attempts ea 
    JOIN exams e ON ea.exam_id = e.id 
    JOIN subjects s ON e.subject = s.id 
    WHERE ea.user_id = ? AND ea.status = 'completed' 
    ORDER BY ea.end_time DESC 
    LIMIT 5
");
$recentResultsQuery->bind_param("i", $studentId);
$recentResultsQuery->execute();
$recentResults = $recentResultsQuery->get_result();

$pageTitle = "Tableau de bord";
include 'includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">Tableau de bord</h1>
    <p class="page-subtitle">Bienvenue, <?php echo $user['first_name']; ?>! Voici un aperçu de vos activités.</p>
</div>

<div class="stats-container">
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-title">Examens passés</div>
            <div class="stat-icon blue">
                <i class="fas fa-check-circle"></i>
            </div>
        </div>
        <div class="stat-value"><?php echo $examsCompleted; ?></div>
        <div class="stat-description">
            <?php 
            // Calculer la tendance (exemple)
            $lastMonthCompletedQuery = $conn->prepare("
                SELECT COUNT(*) as count 
                FROM exam_attempts ea 
                WHERE ea.user_id = ? AND ea.status = 'completed'
                AND ea.end_time >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
            ");
            $lastMonthCompletedQuery->bind_param("i", $studentId);
            $lastMonthCompletedQuery->execute();
            $lastMonthCompleted = $lastMonthCompletedQuery->get_result()->fetch_assoc()['count'];
            
            $trend = $lastMonthCompleted > 0 ? '+' . $lastMonthCompleted : '0';
            echo '<span class="trend-up"><i class="fas fa-arrow-up"></i> ' . $trend . ' ce mois-ci</span>';
            ?>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-title">Examens disponibles</div>
            <div class="stat-icon green">
                <i class="fas fa-file-alt"></i>
            </div>
        </div>
        <div class="stat-value"><?php echo $availableExams; ?></div>
        <div class="stat-description">
            <span class="trend-up">À compléter avant les dates limites</span>
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
            // Déterminer la tendance du score
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
            <div class="stat-title">Examens à venir</div>
            <div class="stat-icon red">
                <i class="fas fa-calendar-alt"></i>
            </div>
        </div>
        <div class="stat-value"><?php echo $upcomingExams; ?></div>
        <div class="stat-description">
            <span class="trend-up">Planifiés dans votre calendrier</span>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Examens disponibles</h2>
                <div class="card-actions">
                    <a href="exams.php" class="btn btn-sm btn-outline-primary">Voir tous</a>
                </div>
            </div>
            <div class="card-body">
                <?php if ($availableExamsList->num_rows > 0): ?>
                    <div class="exams-list">
                        <?php while ($exam = $availableExamsList->fetch_assoc()): ?>
                            <div class="exam-card">
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
                                            <?php 
                                            // Compter le nombre de questions
                                            $questionsCountQuery = $conn->prepare("SELECT COUNT(*) as count FROM questions WHERE exam_id = ?");
                                            $questionsCountQuery->bind_param("i", $exam['id']);
                                            $questionsCountQuery->execute();
                                            $questionsCount = $questionsCountQuery->get_result()->fetch_assoc()['count'];
                                            echo $questionsCount . ' questions';
                                            ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="exam-body">
                                    <div class="exam-meta">
                                        <div class="exam-date">
                                            <i class="fas fa-calendar-alt"></i>
                                            Fin: <?php echo date('d/m/Y H:i', strtotime($exam['end_date'])); ?>
                                        </div>
                                        <span class="exam-status status-available">Disponible</span>
                                    </div>
                                    <p class="exam-description">
                                        <?php 
                                        // Limiter la description à 100 caractères
                                        echo strlen($exam['description']) > 100 ? 
                                            substr($exam['description'], 0, 100) . '...' : 
                                            $exam['description']; 
                                        ?>
                                    </p>
                                </div>
                                <div class="exam-footer">
                                    <a href="take-exam.php?id=<?php echo $exam['id']; ?>" class="btn btn-primary btn-block">
                                        <i class="fas fa-play-circle"></i> Commencer l'examen
                                    </a>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        Aucun examen disponible pour le moment.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Examens à venir</h2>
                <div class="card-actions">
                    <a href="exams.php?filter=upcoming" class="btn btn-sm btn-outline-primary">Voir tous</a>
                </div>
            </div>
            <div class="card-body">
                <?php if ($upcomingExamsList->num_rows > 0): ?>
                    <div class="exams-list">
                        <?php while ($exam = $upcomingExamsList->fetch_assoc()): ?>
                            <div class="exam-card">
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
                                            <?php 
                                            // Compter le nombre de questions
                                            $questionsCountQuery = $conn->prepare("SELECT COUNT(*) as count FROM questions WHERE exam_id = ?");
                                            $questionsCountQuery->bind_param("i", $exam['id']);
                                            $questionsCountQuery->execute();
                                            $questionsCount = $questionsCountQuery->get_result()->fetch_assoc()['count'];
                                            echo $questionsCount . ' questions';
                                            ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="exam-body">
                                    <div class="exam-meta">
                                        <div class="exam-date">
                                            <i class="fas fa-calendar-alt"></i>
                                            Début: <?php echo date('d/m/Y H:i', strtotime($exam['start_date'])); ?>
                                        </div>
                                        <span class="exam-status status-upcoming">À venir</span>
                                    </div>
                                    <p class="exam-description">
                                        <?php 
                                        // Limiter la description à 100 caractères
                                        echo strlen($exam['description']) > 100 ? 
                                            substr($exam['description'], 0, 100) . '...' : 
                                            $exam['description']; 
                                        ?>
                                    </p>
                                </div>
                                <div class="exam-footer">
                                    <button class="btn btn-outline-primary btn-block" disabled>
                                        <i class="fas fa-clock"></i> Pas encore disponible
                                    </button>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        Aucun examen à venir pour le moment.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Derniers résultats</h2>
        <div class="card-actions">
            <a href="results.php" class="btn btn-sm btn-outline-primary">Voir tous</a>
        </div>
    </div>
    <div class="card-body">
        <?php if ($recentResults->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="results-table">
                    <thead>
                        <tr>
                            <th>Examen</th>
                            <th>Matière</th>
                            <th>Date</th>
                            <th>Score</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($result = $recentResults->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $result['title']; ?></td>
                                <td><?php echo $result['subject_name']; ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($result['end_time'])); ?></td>
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
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                Vous n'avez pas encore passé d'examens.
            </div>
        <?php endif; ?>
    </div>
</div>


