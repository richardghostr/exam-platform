<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Vérifier si l'utilisateur est connecté et est un étudiant


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
$stats = $statsQuery->get_result()->fetch_assoc();

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
include 'includes/header.php';
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
        
        <div class="result-analytics">
            <h3>Analyse de performance</h3>
            
            <div class="analytics-row">
                <div class="analytics-card">
                    <h4>Répartition des réponses</h4>
                    <div class="chart-container">
                        <canvas id="answersDistributionChart"></canvas>
                    </div>
                </div>
                
                <div class="analytics-card">
                    <h4>Temps par question</h4>
                    <div class="chart-container">
                        <canvas id="timePerQuestionChart"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="analytics-card full-width">
                <h4>Comparaison avec la moyenne de la classe</h4>
                <div class="chart-container">
                    <canvas id="comparisonChart"></canvas>
                </div>
            </div>
            
            <div class="analytics-card full-width">
                <h4>Recommandations d'amélioration</h4>
                <div class="recommendations">
                    <?php
                    // Simuler des recommandations basées sur les performances
                    $weakAreas = [];
                    $questionsResult->data_seek(0); // Réinitialiser le pointeur
                    
                    while ($question = $questionsResult->fetch_assoc()) {
                        if (!$question['is_correct']) {
                            // Extraire la catégorie/sujet de la question (simulation)
                            $category = $question['category'] ?? 'general';
                            if (!isset($weakAreas[$category])) {
                                $weakAreas[$category] = 0;
                            }
                            $weakAreas[$category]++;
                        }
                    }
                    
                    // Trier par nombre de questions incorrectes
                    arsort($weakAreas);
                    
                    // Générer des recommandations pour les 3 premiers domaines faibles
                    $count = 0;
                    foreach ($weakAreas as $area => $incorrectCount) {
                        if ($count++ >= 3) break;
                        
                        // Simuler des recommandations spécifiques
                        $recommendations = [
                            'general' => [
                                'Révisez les concepts fondamentaux du cours.',
                                'Pratiquez avec des exercices supplémentaires.',
                                'Formez un groupe d\'étude avec vos camarades.'
                            ],
                            'math' => [
                                'Revoyez les formules mathématiques clés.',
                                'Pratiquez la résolution de problèmes similaires.',
                                'Utilisez des outils de visualisation pour mieux comprendre les concepts.'
                            ],
                            'physics' => [
                                'Concentrez-vous sur la compréhension des principes physiques.',
                                'Faites des exercices pratiques pour renforcer votre compréhension.',
                                'Visualisez les problèmes avant de les résoudre.'
                            ],
                            'chemistry' => [
                                'Mémorisez les formules chimiques importantes.',
                                'Pratiquez l\'équilibrage des équations.',
                                'Révisez la nomenclature chimique.'
                            ],
                            'biology' => [
                                'Créez des schémas pour visualiser les processus biologiques.',
                                'Utilisez des mnémoniques pour mémoriser les termes complexes.',
                                'Étudiez les relations entre les différents systèmes.'
                            ],
                            'history' => [
                                'Créez une chronologie des événements clés.',
                                'Concentrez-vous sur les causes et les conséquences.',
                                'Utilisez des cartes mentales pour relier les événements.'
                            ]
                        ];
                        
                        // Afficher les recommandations
                        $areaName = ucfirst($area);
                        $areaRecommendations = $recommendations[$area] ?? $recommendations['general'];
                        ?>
                        <div class="recommendation-item">
                            <div class="recommendation-header">
                                <div class="recommendation-icon">
                                    <i class="fas fa-lightbulb"></i>
                                </div>
                                <h5>Amélioration en <?php echo $areaName; ?></h5>
                            </div>
                            <div class="recommendation-content">
                                <ul>
                                    <?php foreach ($areaRecommendations as $rec): ?>
                                        <li><?php echo $rec; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                        <?php
                    }
                    
                    // Si aucune zone faible n'est identifiée
                    if (empty($weakAreas)) {
                        ?>
                        <div class="recommendation-item">
                            <div class="recommendation-header">
                                <div class="recommendation-icon success">
                                    <i class="fas fa-trophy"></i>
                                </div>
                                <h5>Excellente performance !</h5>
                            </div>
                            <div class="recommendation-content">
                                <p>Félicitations pour votre excellent résultat ! Continuez à maintenir ce niveau de performance.</p>
                                <ul>
                                    <li>Partagez vos techniques d'étude avec vos camarades.</li>
                                    <li>Explorez des sujets avancés pour approfondir vos connaissances.</li>
                                    <li>Envisagez de participer à des compétitions ou des projets spéciaux.</li>
                                </ul>
                            </div>
                        </div>
                        <?php
                    }
                    ?>
                </div>
            </div>
        </div>
        
        <div class="result-footer">
            <div class="share-results">
                <h4>Partager vos résultats</h4>
                <div class="share-buttons">
                    <button class="btn btn-outline-primary share-btn">
                        <i class="fas fa-envelope"></i> Email
                    </button>
                    <button class="btn btn-outline-primary share-btn">
                        <i class="fas fa-download"></i> Télécharger PDF
                    </button>
                    <button class="btn btn-outline-primary share-btn">
                        <i class="fas fa-share-alt"></i> Partager
                    </button>
                </div>
            </div>
            
            <div class="next-steps">
                <h4>Prochaines étapes</h4>
                <div class="next-steps-buttons">
                    <a href="dashboard.php" class="btn btn-primary">
                        <i class="fas fa-home"></i> Retour au tableau de bord
                    </a>
                    <a href="exams.php" class="btn btn-outline-primary">
                        <i class="fas fa-file-alt"></i> Voir les examens disponibles
                    </a>
                    <a href="#" class="btn btn-outline-primary">
                        <i class="fas fa-question-circle"></i> Demander de l'aide
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion de l'impression
    document.getElementById('printResultBtn').addEventListener('click', function() {
        window.print();
    });
    
    // Initialisation des graphiques
    initCharts();
    
    function initCharts() {
        // Graphique de répartition des réponses
        const answersCtx = document.getElementById('answersDistributionChart').getContext('2d');
        const answersChart = new Chart(answersCtx, {
            type: 'pie',
            data: {
                labels: ['Correctes', 'Incorrectes', 'Partiellement correctes'],
                datasets: [{
                    data: [
                        <?php 
                            $questionsResult->data_seek(0);
                            $correct = 0;
                            $incorrect = 0;
                            $partial = 0;
                            
                            while ($q = $questionsResult->fetch_assoc()) {
                                if ($q['is_correct']) {
                                    $correct++;
                                } elseif ($q['points_awarded'] > 0) {
                                    $partial++;
                                } else {
                                    $incorrect++;
                                }
                            }
                            
                            echo $correct . ', ' . $incorrect . ', ' . $partial;
                        ?>
                    ],
                    backgroundColor: [
                        'rgba(76, 175, 80, 0.7)',
                        'rgba(244, 67, 54, 0.7)',
                        'rgba(255, 152, 0, 0.7)'
                    ],
                    borderColor: [
                        'rgba(76, 175, 80, 1)',
                        'rgba(244, 67, 54, 1)',
                        'rgba(255, 152, 0, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                    }
                }
            }
        });
        
        // Graphique de temps par question (simulé)
        const timeCtx = document.getElementById('timePerQuestionChart').getContext('2d');
        const timeChart = new Chart(timeCtx, {
            type: 'bar',
            data: {
                labels: [
                    <?php 
                        $questionsResult->data_seek(0);
                        $labels = [];
                        while ($q = $questionsResult->fetch_assoc()) {
                            $labels[] = 'Q' . count($labels) + 1;
                        }
                        echo "'" . implode("', '", $labels) . "'";
                    ?>
                ],
                datasets: [{
                    label: 'Temps (secondes)',
                    data: [
                        <?php 
                            // Simuler des temps aléatoires pour chaque question
                            $times = [];
                            for ($i = 0; $i < count($labels); $i++) {
                                $times[] = rand(30, 180);
                            }
                            echo implode(', ', $times);
                        ?>
                    ],
                    backgroundColor: 'rgba(33, 150, 243, 0.7)',
                    borderColor: 'rgba(33, 150, 243, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Temps (secondes)'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Questions'
                        }
                    }
                }
            }
        });
        
        // Graphique de comparaison avec la moyenne de la classe
        const comparisonCtx = document.getElementById('comparisonChart').getContext('2d');
        const comparisonChart = new Chart(comparisonCtx, {
            type: 'radar',
            data: {
                labels: ['Compréhension', 'Application', 'Analyse', 'Évaluation', 'Création'],
                datasets: [
                    {
                        label: 'Votre performance',
                        data: [
                            <?php 
                                // Simuler des scores pour différentes compétences
                                $yourScores = [
                                    rand(60, 100), // Compréhension
                                    rand(60, 100), // Application
                                    rand(60, 100), // Analyse
                                    rand(60, 100), // Évaluation
                                    rand(60, 100)  // Création
                                ];
                                echo implode(', ', $yourScores);
                            ?>
                        ],
                        backgroundColor: 'rgba(67, 97, 238, 0.2)',
                        borderColor: 'rgba(67, 97, 238, 1)',
                        pointBackgroundColor: 'rgba(67, 97, 238, 1)',
                        pointBorderColor: '#fff',
                        pointHoverBackgroundColor: '#fff',
                        pointHoverBorderColor: 'rgba(67, 97, 238, 1)'
                    },
                    {
                        label: 'Moyenne de la classe',
                        data: [
                            <?php 
                                // Simuler des scores moyens pour la classe
                                $classScores = [];
                                foreach ($yourScores as $score) {
                                    // Générer un score proche de votre score mais légèrement différent
                                    $classScores[] = max(0, min(100, $score + rand(-15, 15)));
                                }
                                echo implode(', ', $classScores);
                            ?>
                        ],
                        backgroundColor: 'rgba(255, 99, 132, 0.2)',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        pointBackgroundColor: 'rgba(255, 99, 132, 1)',
                        pointBorderColor: '#fff',
                        pointHoverBackgroundColor: '#fff',
                        pointHoverBorderColor: 'rgba(255, 99, 132, 1)'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    r: {
                        angleLines: {
                            display: true
                        },
                        suggestedMin: 0,
                        suggestedMax: 100
                    }
                }
            }
        });
    }
});
</script>

<style>
/* Styles pour la page de résultats d'examen */
.exam-result {
    background-color: #fff;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    margin-bottom: 2rem;
    overflow: hidden;
}

.result-header {
    padding: 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--gray-200);
}

.result-title h1 {
    font-size: 1.75rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.result-title h2 {
    font-size: 1.25rem;
    font-weight: 500;
    color: var(--gray-600);
}

.result-actions {
    display: flex;
    gap: 1rem;
}

.result-summary {
    padding: 2rem;
    display: flex;
    flex-wrap: wrap;
    gap: 2rem;
}

.summary-card {
    flex: 1;
    min-width: 300px;
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
    background-color: #f8f9fa;
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
    width: 40%;
    font-weight: 500;
    color: var(--gray-700);
}

.detail-value {
    width: 60%;
}

.stats-cards {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    flex: 1;
    min-width: 300px;
}

.stat-card {
    flex: 1;
    min-width: 100px;
    background-color: #fff;
    border-radius: var(--border-radius);
    padding: 1.25rem;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    border: 1px solid var(--gray-200);
    display: flex;
    align-items: center;
    gap: 1rem;
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background-color: rgba(67, 97, 238, 0.1);
    color: var(--primary-color);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.stat-info {
    flex: 1;
}

.stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
}

.stat-label {
    font-size: 0.875rem;
    color: var(--gray-600);
}

.result-details {
    padding: 2rem;
    border-top: 1px solid var(--gray-200);
}

.result-details h3 {
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
    background-color: #f8f9fa;
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

.result-analytics {
    padding: 2rem;
    border-top: 1px solid var(--gray-200);
}

.result-analytics h3 {
    font-size: 1.25rem;
    font-weight: 600;
    margin-bottom: 1.5rem;
}

.analytics-row {
    display: flex;
    flex-wrap: wrap;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.analytics-card {
    flex: 1;
    min-width: 300px;
    background-color: #fff;
    border-radius: var(--border-radius);
    padding: 1.5rem;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    border: 1px solid var(--gray-200);
}

.analytics-card.full-width {
    width: 100%;
    margin-bottom: 1.5rem;
}

.analytics-card h4 {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 1rem;
}

.chart-container {
    height: 250px;
    position: relative;
}

.recommendations {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.recommendation-item {
    background-color: #f8f9fa;
    border-radius: var(--border-radius);
    padding: 1.5rem;
    border-left: 4px solid var(--primary-color);
}

.recommendation-header {
    display: flex;
    align-items: center;
    margin-bottom: 1rem;
}

.recommendation-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: rgba(67, 97, 238, 0.1);
    color: var(--primary-color);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1rem;
}

.recommendation-icon.success {
    background-color: rgba(76, 175, 80, 0.1);
    color: var(--success-color);
}

.recommendation-header h5 {
    font-size: 1rem;
    font-weight: 600;
    margin: 0;
}

.recommendation-content ul {
    margin: 0;
    padding-left: 1.5rem;
}

.recommendation-content li {
    margin-bottom: 0.5rem;
}

.recommendation-content li:last-child {
    margin-bottom: 0;
}

.result-footer {
    padding: 2rem;
    border-top: 1px solid var(--gray-200);
    display: flex;
    flex-wrap: wrap;
    gap: 2rem;
}

.share-results, .next-steps {
    flex: 1;
    min-width: 300px;
}

.result-footer h4 {
    font-size: 1.125rem;
    font-weight: 600;
    margin-bottom: 1rem;
}

.share-buttons, .next-steps-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
}

.share-btn {
    flex: 1;
    min-width: 120px;
}

/* État en attente */
.exam-result-pending {
    text-align: center;
    padding: 4rem 2rem;
    background-color: #fff;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
}

.result-icon {
    font-size: 4rem;
    color: var(--primary-color);
    margin-bottom: 1.5rem;
}

.exam-result-pending h1 {
    font-size: 1.75rem;
    font-weight: 700;
    margin-bottom: 1rem;
}

.exam-result-pending p {
    font-size: 1.125rem;
    color: var(--gray-600);
    margin-bottom: 2rem;
    max-width: 600px;
    margin-left: auto;
    margin-right: auto;
}

/* Impression */
@media print {
    .container {
        width: 100%;
        max-width: none;
        padding: 0;
    }
    
    .result-actions, .share-results, .next-steps, .result-analytics {
        display: none;
    }
    
    .exam-result {
        box-shadow: none;
        border: none;
    }
    
    .question-item {
        break-inside: avoid;
    }
}

/* Responsive */
@media (max-width: 768px) {
    .result-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .result-actions {
        margin-top: 1rem;
        width: 100%;
    }
    
    .result-actions .btn {
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
}
</style>
