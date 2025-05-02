<?php
// Inclure les fichiers de configuration et fonctions
include_once '../includes/config.php';
include_once '../includes/functions.php';

// Vérifier si l'utilisateur est connecté et a le rôle étudiant
require_login('../login.php');
require_role('student', '../index.php');

// Connexion à la base de données
include_once '../includes/db.php';

// Récupérer les examens disponibles pour l'étudiant
$user_id = $_SESSION['user_id'];

// Examens à venir
$upcoming_exams_query = "
    SELECT e.*, COUNT(q.id) as question_count
    FROM exams e
    LEFT JOIN questions q ON e.id = q.exam_id
    LEFT JOIN exam_enrollments ee ON e.id = ee.exam_id AND ee.student_id = ?
    WHERE e.status = 'published' 
    AND e.start_date > NOW()
    AND (ee.id IS NULL OR ee.status = 'enrolled')
    GROUP BY e.id
    ORDER BY e.start_date ASC
    LIMIT 5
";

$stmt = $conn->prepare($upcoming_exams_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$upcoming_exams = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Examens en cours
$current_exams_query = "
  SELECT 
    e.id,
    e.title,
    e.description,
    e.start_date,
    e.end_date,
    e.duration,
    e.passing_score,
    e.status,
    MAX(ea.score) as score,
    MAX(ea.status) as attempt_status
FROM exams e
JOIN exam_enrollments ee ON e.id = ee.exam_id AND ee.student_id = ?
LEFT JOIN exam_attempts ea ON ee.id = ea.enrollment_id
WHERE (e.end_time < NOW() OR ee.status = 'completed')
GROUP BY 
    e.id,
    e.title,
    e.description,
    e.start_date,
    e.end_date,
    e.duration,
    e.passing_score,
    e.status
ORDER BY e.end_time DESC
LIMIT 5
";

$stmt = $conn->prepare($current_exams_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$current_exams = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Examens passés
$past_exams_query = "
    SELECT e.*, ea.score, ea.status as attempt_status
    FROM exams e
    JOIN exam_enrollments ee ON e.id = ee.exam_id AND ee.student_id = ?
    LEFT JOIN exam_attempts ea ON ee.id = ea.enrollment_id
    WHERE (e.end_date < NOW() OR ee.status = 'completed')
    GROUP BY e.id
    ORDER BY e.end_date DESC
    LIMIT 5
";

$stmt = $conn->prepare($past_exams_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$past_exams = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Récupérer les statistiques de l'étudiant
$stats_query = "
    SELECT 
        COUNT(DISTINCT ea.id) as total_exams_taken,
        AVG(ea.score) as average_score,
        SUM(CASE WHEN ea.score >= e.passing_score THEN 1 ELSE 0 END) as exams_passed,
        SUM(CASE WHEN ea.score < e.passing_score THEN 1 ELSE 0 END) as exams_failed
    FROM exam_attempts ea
    JOIN exam_enrollments ee ON ea.enrollment_id = ee.id
    JOIN exams e ON ee.exam_id = e.id
    WHERE ee.student_id = ? AND ea.status = 'completed'
";

$stmt = $conn->prepare($stats_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fermer la connexion à la base de données
$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord étudiant - ExamSafe</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/student.css">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</head>

<body>
    <!-- En-tête -->
    <?php include '../includes/header.php'; ?>

    <!-- Contenu principal -->
    <main class="dashboard">
        <div class="container">
            <div class="dashboard-header">
                <h1>Tableau de bord étudiant</h1>
                <p>Bienvenue, <?php echo htmlspecialchars($_SESSION['username']); ?></p>
            </div>

            <!-- Statistiques -->
            <section class="stats-section">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-clipboard-check"></i>
                        </div>
                        <div class="stat-content">
                            <h3>Examens passés</h3>
                            <p class="stat-value"><?php echo $stats['total_exams_taken'] ?? 0; ?></p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-content">
                            <h3>Score moyen</h3>
                            <p class="stat-value"><?php echo $stats['average_score'] ? number_format($stats['average_score'], 1) . '%' : 'N/A'; ?></p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-trophy"></i>
                        </div>
                        <div class="stat-content">
                            <h3>Examens réussis</h3>
                            <p class="stat-value"><?php echo $stats['exams_passed'] ?? 0; ?></p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="stat-content">
                            <h3>Examens échoués</h3>
                            <p class="stat-value"><?php echo $stats['exams_failed'] ?? 0; ?></p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Examens en cours -->
            <section class="exams-section">
                <div class="section-header">
                    <h2>Examens en cours</h2>
                    <a href="exams.php?filter=current" class="btn btn-outline btn-sm">Voir tout</a>
                </div>

                <?php if (empty($current_exams)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-hourglass-half"></i>
                        </div>
                        <h3>Aucun examen en cours</h3>
                        <p>Vous n'avez pas d'examens en cours pour le moment.</p>
                    </div>
                <?php else: ?>
                    <div class="exams-grid">
                        <?php foreach ($current_exams as $exam): ?>
                            <div class="exam-card">
                                <div class="exam-status">En cours</div>
                                <h3><?php echo htmlspecialchars($exam['title']); ?></h3>
                                <div class="exam-info">
                                    <div class="info-item">
                                        <i class="fas fa-clock"></i>
                                        <span>Durée: <?php echo $exam['duration']; ?> min</span>
                                    </div>
                                    <div class="info-item">
                                        <i class="fas fa-question-circle"></i>
                                        <span><?php echo $exam['question_count']; ?> questions</span>
                                    </div>
                                    <div class="info-item">
                                        <i class="fas fa-calendar-alt"></i>
                                        <span>Fin: <?php echo format_date($exam['end_time']); ?></span>
                                    </div>
                                </div>
                                <div class="exam-description">
                                    <?php echo truncate_text(htmlspecialchars($exam['description']), 100); ?>
                                </div>
                                <div class="exam-actions">
                                    <a href="take-exam.php?id=<?php echo $exam['enrollment_id']; ?>" class="btn btn-primary btn-block">Commencer l'examen</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Examens à venir -->
            <section class="exams-section">
                <div class="section-header">
                    <h2>Examens à venir</h2>
                    <a href="exams.php?filter=upcoming" class="btn btn-outline btn-sm">Voir tout</a>
                </div>

                <?php if (empty($upcoming_exams)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-calendar-plus"></i>
                        </div>
                        <h3>Aucun examen à venir</h3>
                        <p>Vous n'avez pas d'examens programmés pour le moment.</p>
                    </div>
                <?php else: ?>
                    <div class="exams-grid">
                        <?php foreach ($upcoming_exams as $exam): ?>
                            <div class="exam-card">
                                <div class="exam-status upcoming">À venir</div>
                                <h3><?php echo htmlspecialchars($exam['title']); ?></h3>
                                <div class="exam-info">
                                    <div class="info-item">
                                        <i class="fas fa-clock"></i>
                                        <span>Durée: <?php echo $exam['duration']; ?> min</span>
                                    </div>
                                    <div class="info-item">
                                        <i class="fas fa-question-circle"></i>
                                        <span><?php echo $exam['question_count']; ?> questions</span>
                                    </div>
                                    <div class="info-item">
                                        <i class="fas fa-calendar-alt"></i>
                                        <span>Début: <?php echo format_date($exam['start_time']); ?></span>
                                    </div>
                                </div>
                                <div class="exam-description">
                                    <?php echo truncate_text(htmlspecialchars($exam['description']), 100); ?>
                                </div>
                                <div class="exam-actions">
                                    <a href="exam-details.php?id=<?php echo $exam['id']; ?>" class="btn btn-outline btn-block">Voir les détails</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Examens passés -->
            <section class="exams-section">
                <div class="section-header">
                    <h2>Examens passés</h2>
                    <a href="exams.php?filter=past" class="btn btn-outline btn-sm">Voir tout</a>
                </div>

                <?php if (empty($past_exams)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-history"></i>
                        </div>
                        <h3>Aucun examen passé</h3>
                        <p>Vous n'avez pas encore passé d'examens.</p>
                    </div>
                <?php else: ?>
                    <div class="exams-grid">
                        <?php foreach ($past_exams as $exam): ?>
                            <div class="exam-card">
                                <div class="exam-status completed">Terminé</div>
                                <h3><?php echo htmlspecialchars($exam['title']); ?></h3>
                                <div class="exam-info">
                                    <div class="info-item">
                                        <i class="fas fa-chart-pie"></i>
                                        <span>Score: <?php echo isset($exam['score']) ? $exam['score'] . '%' : 'N/A'; ?></span>
                                    </div>
                                    <div class="info-item">
                                        <i class="fas fa-check-circle"></i>
                                        <span>Statut:
                                            <?php
                                            if (!isset($exam['score'])) {
                                                echo 'Non noté';
                                            } elseif ($exam['score'] >= $exam['passing_score']) {
                                                echo '<span class="text-success">Réussi</span>';
                                            } else {
                                                echo '<span class="text-danger">Échoué</span>';
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    <div class="info-item">
                                        <i class="fas fa-calendar-check"></i>
                                        <span>Date: <?php echo format_date($exam['end_time'], 'd/m/Y'); ?></span>
                                    </div>
                                </div>
                                <div class="exam-actions">
                                    <a href="exam-results.php?id=<?php echo $exam['id']; ?>" class="btn btn-outline btn-block">Voir les résultats</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <!-- Pied de page -->
    <?php include '../includes/footer.php'; ?>

    <!-- Scripts JS -->
    <script src="../assets/js/main.js"></script>
</body>

</html>