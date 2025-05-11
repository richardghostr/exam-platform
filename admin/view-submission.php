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

// Vérifier si l'ID du résultat est fourni
if (!isset($_GET['result_id']) || empty($_GET['result_id'])) {
    header('Location: exam-results.php');
    exit();
}

$resultId = intval($_GET['result_id']);
$teacherId = $_SESSION['user_id'];

// Récupérer les détails de la soumission
$submissionQuery = $conn->prepare("
    SELECT 
        er.*,
        e.title as exam_title,
        e.passing_score,
        e.duration,
        e.subject,
        s.name as subject_name,
        u.username,
        u.first_name,
        u.last_name,
        u.email,
        u.profile_image,
        c.name as class_name
    FROM exam_results er
    JOIN exams e ON er.exam_id = e.id
    JOIN subjects s ON e.subject = s.id
    JOIN users u ON er.user_id = u.id
    LEFT JOIN user_classes uc ON u.id = uc.user_id
    LEFT JOIN classes c ON uc.class_id = c.id
    WHERE er.id = ? AND e.teacher_id = ?
");
$submissionQuery->bind_param("ii", $resultId, $teacherId);
$submissionQuery->execute();
$submissionResult = $submissionQuery->get_result();

if ($submissionResult->num_rows === 0) {
    header('Location: exam-results.php');
    exit();
}

$submission = $submissionResult->fetch_assoc();

// Récupérer les questions et réponses
$questionsQuery = $conn->prepare("
    SELECT q.*, ua.answer_text, ua.selected_options, ua.is_correct, ua.points_awarded
    FROM questions q
    LEFT JOIN user_answers ua ON q.id = ua.question_id AND ua.attempt_id = ?
    WHERE q.exam_id = ?
    ORDER BY q.id ASC
");
$questionsQuery->bind_param("ii", $resultId, $submission['exam_id']);
$questionsQuery->execute();
$questionsResult = $questionsQuery->get_result();

// Calculer les statistiques de la soumission
$totalPoints = 0;
$earnedPoints = 0;
$correctAnswers = 0;
$totalQuestions = $questionsResult->num_rows;

while ($question = $questionsResult->fetch_assoc()) {
    $totalPoints += $question['points'];
    $earnedPoints += $question['points_awarded'] ?? 0;
    if ($question['is_correct']) {
        $correctAnswers++;
    }
}
$questionsResult->data_seek(0); // Réinitialiser le pointeur pour réutiliser les résultats

$pageTitle = "Détails de la soumission";
include 'includes/header.php';
?>

<div class="container">
    <div class="submission-details">
        <div class="submission-header">
            <div class="submission-title">
                <h1>Détails de la soumission</h1>
                <h2><?php echo htmlspecialchars($submission['exam_title']); ?></h2>
            </div>
            <div class="submission-actions">
                <a href="view-results.php?exam_id=<?php echo $submission['exam_id']; ?>" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left"></i> Retour aux résultats
                </a>
                <button class="btn btn-primary" id="printSubmissionBtn">
                    <i class="fas fa-print"></i> Imprimer
                </button>
            </div>
        </div>
        <br>
        <div class="student-info" style="background-color: #1a2357;border-radius:20px ;box-shadow: 0 7px 14px rgba(0, 117, 255, 0.4);">
            <div class="student-profile">
                <?php if ($submission['profile_image']): ?>
                    <img src="<?php echo htmlspecialchars($submission['profile_image']); ?>" alt="Photo de profil" class="profile-img">
                <?php else: ?>
                    <div class="profile-initials">
                        <?php echo strtoupper(substr($submission['first_name'], 0, 1) . substr($submission['last_name'], 0, 1)); ?>
                    </div>
                <?php endif; ?>
                <div class="profile-details">
                    <h3><?php echo htmlspecialchars($submission['first_name'] . ' ' . $submission['last_name']); ?></h3>
                    <p><?php echo htmlspecialchars($submission['email']); ?></p>
                    <?php if ($submission['class_name']): ?>
                        <p><strong>Classe:</strong> <?php echo htmlspecialchars($submission['class_name']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="submission-summary"style="background-color: #1a2357">
                <div class="summary-card <?php echo $submission['score'] >= $submission['passing_score'] ? 'passed' : 'failed'; ?>">
                    <div class="summary-header">
                        <div class="summary-title">
                            <h3>Résumé</h3>
                            <span class="result-badge <?php echo $submission['score'] >= $submission['passing_score'] ? 'passed' : 'failed'; ?>">
                                <?php echo $submission['score'] >= $submission['passing_score'] ? 'Réussi' : 'Échoué'; ?>
                            </span>
                        </div>
                        <div class="summary-score">
                            <div class="score-circle">
                                <span class="score-value"><?php echo round($submission['score']); ?>%</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="summary-details">
                        <div class="detail-item">
                            <div class="detail-label">Score:</div>
                            <div class="detail-value"><?php echo $submission['score']; ?>%</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Score minimum:</div>
                            <div class="detail-value"><?php echo $submission['passing_score']; ?>%</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Questions correctes:</div>
                            <div class="detail-value"><?php echo $correctAnswers; ?> / <?php echo $totalQuestions; ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Points obtenus:</div>
                            <div class="detail-value"><?php echo $earnedPoints; ?> / <?php echo $totalPoints; ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Temps passé:</div>
                            <div class="detail-value">
                                <?php 
                                    $minutes = floor($submission['time_spent'] / 60);
                                    $seconds = $submission['time_spent'] % 60;
                                    echo sprintf('%02d:%02d', $minutes, $seconds);
                                ?>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Date de soumission:</div>
                            <div class="detail-value"><?php echo date('d/m/Y H:i', strtotime($submission['completed_at'])); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div><br>
        
        <div class="questions-review">
            <h3>Revue des questions</h3>
            
            <div class="questions-list">
                <?php 
                $questionNumber = 1;
                while ($question = $questionsResult->fetch_assoc()): 
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
                            
                            <?php if ($question['question_type'] !== 'essay' && $question['question_type'] !== 'short_answer'): ?>
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
                                    <div class="answer-label">Réponse de l'étudiant:</div>
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
                        
                        <?php if ($question['question_type'] === 'essay' && $submission['status'] === 'completed' && !$submission['is_graded']): ?>
                            <div class="grading-form">
                                <form action="grade-question.php" method="POST">
                                    <input type="hidden" name="result_id" value="<?php echo $resultId; ?>">
                                    <input type="hidden" name="question_id" value="<?php echo $question['id']; ?>">
                                    
                                    <div class="form-group">
                                        <label for="points_<?php echo $question['id']; ?>">Points attribués (max <?php echo $question['points']; ?>):</label>
                                        <input type="number" id="points_<?php echo $question['id']; ?>" name="points_awarded" 
                                               min="0" max="<?php echo $question['points']; ?>" 
                                               value="<?php echo $question['points_awarded'] ?? 0; ?>" class="form-control">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="feedback_<?php echo $question['id']; ?>">Feedback:</label>
                                        <textarea id="feedback_<?php echo $question['id']; ?>" name="feedback" class="form-control" rows="3"><?php echo htmlspecialchars($question['feedback'] ?? ''); ?></textarea>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
        
        <div class="submission-footer">
            <div class="teacher-notes">
                <h4>Notes de l'enseignant</h4>
                <form action="save-teacher-notes.php" method="POST">
                    <input type="hidden" name="result_id" value="<?php echo $resultId; ?>">
                    <textarea name="teacher_notes" class="form-control" rows="4" placeholder="Ajoutez des notes ou des commentaires sur cette soumission..."><?php echo htmlspecialchars($submission['teacher_notes'] ?? ''); ?></textarea>
                    <button type="submit" class="btn btn-primary mt-2">Enregistrer les notes</button>
                </form>
            </div>
            
            <div class="submission-actions-footer">
                <?php if ($submission['status'] === 'completed' && !$submission['is_graded'] && $submission['has_essay']): ?>
                    <a href="grade-submission.php?result_id=<?php echo $resultId; ?>" class="btn btn-primary">
                        <i class="fas fa-check"></i> Finaliser la notation
                    </a>
                <?php endif; ?>
                
                <a href="send-feedback.php?result_id=<?php echo $resultId; ?>" class="btn btn-outline-primary">
                    <i class="fas fa-envelope"></i> Envoyer le feedback
                </a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion de l'impression
    document.getElementById('printSubmissionBtn').addEventListener('click', function() {
        window.print();
    });
});
</script>

<style>
/* Styles pour la page de détails de soumission */
.submission-details {
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    margin-bottom: 2rem;
    overflow: hidden;
}

.submission-header {
    
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--gray-200);
}

.submission-title h1 {
    font-size: 1.75rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.submission-title h2 {
    font-size: 1.25rem;
    font-weight: 500;
    color: var(--gray-600);
}

.submission-actions {
    display: flex;
    gap: 1rem;
}

.student-info {
    padding: 2rem;
    display: flex;
    flex-wrap: wrap;
    gap: 2rem;
    border-bottom: 1px solid var(--gray-200);
}

.student-profile {
    flex: 1;
    min-width: 300px;
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.profile-img {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    object-fit: cover;
}

.profile-initials {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    /* background-color: var(--primary-color); */
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    font-weight: 600;
}

.profile-details h3 {
    font-size: 1.5rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.profile-details p {
    margin-bottom: 0.5rem;
    color: var(--gray-600);
}

.submission-summary {
    flex: 1;
    min-width: 300px;
}

.summary-card {
    border-radius: var(--border-radius);
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
}

.summary-card.passed {
    border: 1px solid rgba(76, 175, 80, 0.3);
}

.summary-card.failed {
    border: 1px solid rgba(244, 67, 54, 0.3);
}

.summary-header {
    padding: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background-color: #2d3867;
    border-bottom: 1px solid var(--gray-200);
}

.summary-title h3 {
    font-size: 1.25rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.result-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 500;
}

.result-badge.passed {
    background-color: rgba(76, 175, 80, 0.1);
    color: var(--success-color);
}

.result-badge.failed {
    background-color: rgba(244, 67, 54, 0.1);
    color: var(--danger-color);
}

.summary-score {
    display: flex;
    align-items: center;
    justify-content: center;
}

.score-circle {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.5rem;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
}

.passed .score-circle {
    background-color: rgba(76, 175, 80, 0.1);
    color: var(--success-color);
    border: 2px solid var(--success-color);
}

.failed .score-circle {
    background-color: rgba(244, 67, 54, 0.1);
    color: var(--danger-color);
    border: 2px solid var(--danger-color);
}

.summary-details {
    padding: 1.5rem;
}

.detail-item {
    display: flex;
    margin-bottom: 1rem;
}

.detail-item:last-child {
    margin-bottom: 0;
}

.detail-label {
    width: 50%;
    font-weight: 500;
    color: var(--gray-700);
}

.detail-value {
    width: 50%;
}

.questions-review {
    border-bottom: 1px solid var(--gray-200);
}

.questions-review h3 {
    font-size: 1.25rem;
    font-weight: 600;
    margin-bottom: 1.5rem;
}

.questions-list {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.question-item {
    border: 1px solid var(--gray-200);
    border-radius: var(--border-radius);
    overflow: hidden;
}

.question-item.correct {
    border-left: 4px solid var(--success-color);
}

.question-item.incorrect {
    border-left: 4px solid var(--danger-color);
}

.question-header {
    padding: 1rem 1.5rem;
    background-color: #1a2357;
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.question-number {
    font-weight: 600;
}

.question-points {
    font-weight: 500;
}

.question-status {
    font-weight: 500;
}

.question-item.correct .question-status {
    color: var(--success-color);
}

.question-item.incorrect .question-status {
    color: var(--danger-color);
}

.question-content {
    padding: 1.5rem;
}

.question-text {
    margin-bottom: 1.5rem;
    font-weight: 500;
}

.options-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.option-item {
    display: flex;
    align-items: flex-start;
    padding: 0.75rem;
    border-radius: var(--border-radius);
    border: 1px solid var(--gray-200);
    position: relative;
}

.option-item.selected-correct {
    background-color: rgba(76, 175, 80, 0.05);
    border-color: rgba(76, 175, 80, 0.3);
}

.option-item.selected-incorrect {
    background-color: rgba(244, 67, 54, 0.05);
    border-color: rgba(244, 67, 54, 0.3);
}

.option-item.unselected-correct {
    border-color: rgba(76, 175, 80, 0.3);
    border-style: dashed;
}

.option-marker {
    margin-right: 0.75rem;
    color: var(--gray-500);
}

.selected-correct .option-marker {
    color: var(--success-color);
}

.selected-incorrect .option-marker {
    color: var(--danger-color);
}

.option-text {
    flex: 1;
}

.option-correct-marker {
    position: absolute;
    right: 0.75rem;
    color: var(--success-color);
}

.essay-answer {
    padding: 1.5rem;
    background-color: #f8f9fa;
    border-radius: var(--border-radius);
    border: 1px solid var(--gray-200);
}

.answer-label {
    font-weight: 500;
    margin-bottom: 0.75rem;
}

.answer-text {
    white-space: pre-line;
}

.question-feedback {
    margin-top: 1.5rem;
    padding: 1.5rem;
    background-color: rgba(255, 152, 0, 0.05);
    border-radius: var(--border-radius);
    border: 1px solid rgba(255, 152, 0, 0.2);
}

.feedback-label {
    font-weight: 500;
    margin-bottom: 0.75rem;
    color: var(--warning-color);
}

.grading-form {
    margin-top: 1.5rem;
    padding: 1.5rem;
    background-color: #f8f9fa;
    border-radius: var(--border-radius);
    border: 1px solid var(--gray-200);
}

.submission-footer {
    padding: 2rem;
    display: flex;
    flex-wrap: wrap;
    gap: 2rem;
}

.teacher-notes {
    flex: 1;
    min-width: 300px;
}

.teacher-notes h4 {
    font-size: 1.125rem;
    font-weight: 600;
    margin-bottom: 1rem;
}

.submission-actions-footer {
    flex: 1;
    min-width: 300px;
    display: flex;
    align-items: flex-end;
    justify-content: flex-end;
    gap: 1rem;
}

/* Impression */
@media print {
    .container {
        width: 100%;
        max-width: none;
        padding: 0;
    }
    
    .submission-actions, .submission-actions-footer {
        display: none;
    }
    
    .submission-details {
        box-shadow: none;
        border: none;
    }
    
    .question-item {
        break-inside: avoid;
    }
}

/* Responsive */
@media (max-width: 768px) {
    .submission-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .submission-actions {
        margin-top: 1rem;
        width: 100%;
    }
    
    .submission-actions .btn {
        flex: 1;
    }
    
    .summary-header {
        flex-direction: column;
    }
    
    .summary-score {
        margin-top: 1rem;
    }
    
    .question-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .question-status {
        margin-top: 0.5rem;
    }
    
    .submission-footer {
        flex-direction: column;
    }
    
    .submission-actions-footer {
        justify-content: flex-start;
    }
}
</style>

