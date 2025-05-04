<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';


// Récupérer l'ID de l'étudiant
$studentId = $_SESSION['user_id'];

// Vérifier si l'ID de la tentative est fourni
if (!isset($_GET['attempt_id']) || empty($_GET['attempt_id'])) {
    header('Location: dashboard.php');
    exit();
}

$attemptId = intval($_GET['attempt_id']);

// Vérifier si la tentative appartient à l'étudiant
$attemptQuery = $conn->prepare("
    SELECT ea.*, e.title, e.passing_score, e.duration, e.show_results, s.name as subject_name
    FROM exam_attempts ea 
    JOIN exams e ON ea.exam_id = e.id 
    JOIN subjects s ON e.subject = s.id
    WHERE ea.id = ? AND ea.user_id = ?
");
$attemptQuery->bind_param("ii", $attemptId, $studentId);
$attemptQuery->execute();
$attemptResult = $attemptQuery->get_result();

if ($attemptResult->num_rows === 0) {
    header('Location: dashboard.php');
    exit();
}

$attempt = $attemptResult->fetch_assoc();

// Vérifier si l'examen est terminé
if ($attempt['status'] === 'in_progress') {
    header('Location: take-exam.php?id=' . $attempt['exam_id']);
    exit();
}

// Vérifier si les résultats peuvent être affichés
if ($attempt['show_results'] == 0 && $attempt['status'] !== 'graded') {
    $pageTitle = "Examen terminé";
    include '../includes/header.php';
    ?>
    <div class="container">
        <div class="exam-result-pending">
            <div class="result-icon">
                <i class="fas fa-hourglass-half"></i>
            </div>
            <h1>Examen terminé</h1>
            <p>Votre examen a été soumis avec succès. Les résultats seront disponibles une fois que l'enseignant aura terminé la notation.</p>
            <a href="dashboard.php" class="btn btn-primary">Retour au tableau de bord</a>
        </div>
    </div>
    <?php
    include '../includes/footer.php';
    exit();
}

// Récupérer les statistiques de l'examen
$examId = $attempt['exam_id'];
$statsQuery = $conn->prepare("
    SELECT 
        COUNT(*) as total_attempts,
        AVG(score) as avg_score,
        MAX(score) as max_score
    FROM exam_attempts 
    WHERE exam_id = ? AND status IN ('completed', 'graded')
");
$statsQuery->bind_param("i", $examId);
$statsQuery->execute();
$statsResult = $statsQuery->get_result();
$stats = $statsResult->fetch_assoc();

// Récupérer les questions et réponses
$questionsQuery = $conn->prepare("
    SELECT q.*, ua.answer_text, ua.selected_options, ua.is_correct, ua.points_awarded
    FROM questions q
    LEFT JOIN user_answers ua ON q.id = ua.question_id AND ua.attempt_id = ?
    WHERE q.exam_id = ?
    ORDER BY q.id ASC
");
$questionsQuery->bind_param("ii", $attemptId, $examId);
$questionsQuery->execute();
$questionsResult = $questionsQuery->get_result();

$pageTitle = "Résultats de l'examen";
$extraCss = ['../assets/css/exam-result.css'];
include '../includes/header.php';
?>

<div class="container">
    <div class="exam-result">
        <div class="result-header">
            <div class="result-title">
                <h1>Résultats de l'examen</h1>
                <h2><?php echo htmlspecialchars($attempt['title']); ?></h2>
            </div>
            
            <div class="result-actions">
                <a href="dashboard.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left"></i> Retour au tableau de bord
                </a>
                <button class="btn btn-primary" id="printResultBtn">
                    <i class="fas fa-print"></i> Imprimer
                </button>
            </div>
        </div>
        
        <div class="result-summary">
            <div class="summary-card <?php echo $attempt['score'] >= $attempt['passing_score'] ? 'passed' : 'failed'; ?>">
                <div class="summary-header">
                    <div class="summary-title">
                        <h3>Résultat final</h3>
                        <span class="result-badge <?php echo $attempt['score'] >= $attempt['passing_score'] ? 'passed' : 'failed'; ?>">
                            <?php echo $attempt['score'] >= $attempt['passing_score'] ? 'Réussi' : 'Échoué'; ?>
                        </span>
                    </div>
                    <div class="summary-score">
                        <div class="score-circle">
                            <span class="score-value"><?php echo round($attempt['score']); ?>%</span>
                        </div>
                    </div>
                </div>
                
                <div class="summary-details">
                    <div class="detail-item">
                        <div class="detail-label">Matière:</div>
                        <div class="detail-value"><?php echo htmlspecialchars($attempt['subject_name']); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Date de l'examen:</div>
                        <div class="detail-value"><?php echo date('d/m/Y H:i', strtotime($attempt['start_time'])); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Durée:</div>
                        <div class="detail-value">
                            <?php 
                                $startTime = strtotime($attempt['start_time']);
                                $endTime = strtotime($attempt['end_time']);
                                $duration = $endTime - $startTime;
                                $minutes = floor($duration / 60);
                                $seconds = $duration % 60;
                                echo $minutes . ' min ' . $seconds . ' sec';
                            ?>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Score minimum:</div>
                        <div class="detail-value"><?php echo $attempt['passing_score']; ?>%</div>
                    </div>
                </div>
            </div>
            
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-value"><?php echo $stats['total_attempts']; ?></div>
                        <div class="stat-label">Participants</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-value"><?php echo round($stats['avg_score'], 1); ?>%</div>
                        <div class="stat-label">Moyenne</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-value"><?php echo round($stats['max_score'], 1); ?>%</div>
                        <div class="stat-label">Meilleur score</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="result-details">
            <h3>Détail des questions</h3>
            
            <div class="questions-list">
                <?php 
                $questionNumber = 1;
                $totalPoints = 0;
                $earnedPoints = 0;
                
                while ($question = $questionsResult->fetch_assoc()): 
                    $totalPoints += $question['points'];
                    $earnedPoints += $question['points_awarded'] ?? 0;
                ?>
                    <div class="question-item <?php echo $question['is_correct'] ? 'correct' : 'incorrect'; ?>">
                        <div class="question-header">
                            <div class="question-number">Question <?php echo $questionNumber++; ?></div>
                            <div class="question-points">
                                <?php echo $question['points_awarded'] ?? 0; ?> / <?php echo $question['points']; ?> points
                            </div>
                            <div class="question-status">
                                <?php if ($question['is_correct']): ?>
                                    <i class="fas fa-check-circle"></i> Correct
                                <?php else: ?>
                                    <i class="fas fa-times-circle"></i> Incorrect
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="question-content">
                            <div class="question-text"><?php echo $question['question_text']; ?></div>
                            
                            <?php if ($question['question_type'] !== 'essay'): ?>
                                <?php 
                                    $optionsQuery = $conn->prepare("SELECT * FROM question_options WHERE question_id = ? ORDER BY id ASC");
                                    $optionsQuery->bind_param("i", $question['id']);
                                    $optionsQuery->execute();
                                    $optionsResult = $optionsQuery->get_result();
                                    $selectedOptions = explode(',', $question['selected_options'] ?? '');
                                ?>
                                <div class="options-list">
                                    <?php while ($option = $optionsResult->fetch_assoc()): ?>
                                        <div class="option-item <?php 
                                            if (in_array($option['id'], $selectedOptions) && $option['is_correct']) {
                                                echo 'selected-correct';
                                            } elseif (in_array($option['id'], $selectedOptions) && !$option['is_correct']) {
                                                echo 'selected-incorrect';
                                            } elseif (!in_array($option['id'], $selectedOptions) && $option['is_correct']) {
                                                echo 'unselected-correct';
                                            }
                                        ?>">
                                            <div class="option-marker">
                                                <?php if (in_array($option['id'], $selectedOptions)): ?>
                                                    <i class="fas fa-check-circle"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-circle"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="option-text"><?php echo $option['option_text']; ?></div>
                                            <?php if ($option['is_correct']): ?>
                                                <div class="option-correct-marker">
                                                    <i class="fas fa-check"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <div class="essay-answer">
                                    <div class="answer-label">Votre réponse:</div>
                                    <div class="answer-text"><?php echo nl2br(htmlspecialchars($question['answer_text'] ?? '')); ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!$question['is_correct'] && isset($question['feedback'])): ?>
                            <div class="question-feedback">
                                <div class="feedback-label">Feedback:</div>
                                <div class="feedback-text"><?php echo nl2br(htmlspecialchars($question['feedback'])); ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion de l'impression
    document.getElementById('printResultBtn').addEventListener('click', function() {
        window.print();
    });
});
</script>

