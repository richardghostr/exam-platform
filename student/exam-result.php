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



// Récupérer les statistiques de l'examen
$examId = $attempt['exam_id'];
$statsQuery = $conn->prepare("
    SELECT 
        COUNT(*) as total_attempts,
        IFNULL(AVG(score), 0) as avg_score,
        IFNULL(MAX(score), 0) as max_score
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
// $migrationQuery = "
//         INSERT INTO exam_results (
//             id, exam_id, user_id, score, total_points, points_earned, passing_score,
//             passed, time_spent, completed_at, graded_by, graded_at, feedback,
//             created_at, updated_at, status, is_graded
//         )
//         SELECT 
//             ea.id, ea.exam_id, ea.user_id, COALESCE(ea.score, 0), 100, COALESCE(ea.score, 0), 60,
//             CASE WHEN ea.score >= 60 THEN 1 ELSE 0 END,
//             TIMESTAMPDIFF(SECOND, ea.start_time, ea.end_time), ea.end_time,
//             NULL, CASE WHEN ea.status = 'graded' THEN ea.updated_at ELSE NULL END, NULL,
//             ea.created_at, ea.updated_at, ea.status,
//             CASE WHEN ea.status = 'graded' THEN 'yes' ELSE 'no' END
//         FROM 
//             exam_attempts ea
//         WHERE 
//             ea.status IN ('completed', 'graded', 'failed')
//             AND ea.end_time IS NOT NULL
//             AND NOT EXISTS (
//                 SELECT 1 FROM exam_results er WHERE er.id = ea.id
//             )
//     ";
// // $migrationQuery->bind_param("", $examId);
// $migration = $conn->prepare($migrationQuery);
// $migration->execute();
include 'includes/header.php';
?>

<div class="container"><br><br>
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
                            <?php echo (isset($attempt['score']) && $attempt['score'] >= $attempt['passing_score']) ? 'Réussi' : 'Échoué'; ?>
                        </span>
                    </div>
                    <div class="summary-score">
                        <div class="score-circle">
                            <span class="score-value"><?php echo isset($attempt['score']) ? round($attempt['score']) : 0; ?>%</span>
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
                        <div class="stat-value"><?php echo isset($stats['avg_score']) ? round($stats['avg_score'], 1) : 0; ?>%</div>
                        <div class="stat-label">Moyenne</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-value"><?php echo isset($stats['max_score']) ? round($stats['max_score'], 1) : 0; ?>%</div>
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
                    <button class="btn btn-outline-primary share-btn" id="emailShareBtn">
                        <i class="fas fa-envelope"></i> Email
                    </button>
                    <button class="btn btn-outline-primary share-btn" id="pdfDownloadBtn">
                        <i class="fas fa-download"></i> Télécharger PDF
                    </button>
                    <div class="dropdown">
                        <button class="btn btn-outline-primary share-btn dropdown-toggle" type="button" id="socialShareDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-share-alt"></i> Partager
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="socialShareDropdown">
                            <li><a class="dropdown-item" href="#" id="facebookShareBtn"><i class="fab fa-facebook"></i> Facebook</a></li>
                            <li><a class="dropdown-item" href="#" id="twitterShareBtn"><i class="fab fa-twitter"></i> Twitter</a></li>
                            <li><a class="dropdown-item" href="#" id="linkedinShareBtn"><i class="fab fa-linkedin"></i> LinkedIn</a></li>
                            <li><a class="dropdown-item" href="#" id="whatsappShareBtn"><i class="fab fa-whatsapp"></i> WhatsApp</a></li>
                        </ul>
                    </div>
                </div>

                <!-- Modal pour l'envoi par email -->
                <div class="modal fade" id="emailShareModal" tabindex="-1" aria-labelledby="emailShareModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title" id="emailShareModalLabel">
                                    <i class="fas fa-paper-plane me-2"></i>Envoyer les résultats par email
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form id="emailShareForm">
                                    <div class="mb-3">
                                        <label for="recipientEmail" class="form-label">Adresse email du destinataire</label>
                                        <input type="email" class="form-control" id="recipientEmail" required
                                            placeholder="exemple@domaine.com">
                                        <div class="form-text">Vous pouvez entrer plusieurs adresses séparées par des virgules</div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="emailSubject" class="form-label">Sujet</label>
                                        <input type="text" class="form-control" id="emailSubject"
                                            value="Résultats de l'examen: <?php echo htmlspecialchars($attempt['title']); ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label for="emailMessage" class="form-label">Message personnalisé</label>
                                        <textarea class="form-control" id="emailMessage" rows="5">Bonjour,

Voici mes résultats pour l'examen "<?php echo htmlspecialchars($attempt['title']); ?>".

Cordialement,
<?php echo htmlspecialchars($_SESSION['full_name']); ?></textarea>
                                    </div>

                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="sendCopy" checked>
                                        <label class="form-check-label" for="sendCopy">Recevoir une copie</label>
                                    </div>

                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        Un PDF des résultats sera automatiquement joint au message.
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <i class="fas fa-times me-2"></i>Annuler
                                </button>
                                <button type="button" class="btn btn-primary" id="sendEmailBtn">
                                    <i class="fas fa-paper-plane me-2"></i>Envoyer
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="next-steps">
                <h4>Prochaines étapes</h4>
                <div class="next-steps-buttons">
                    <a href="index.php" class="btn btn-primary">
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
                    datasets: [{
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

    .share-results,
    .next-steps {
        flex: 1;
        min-width: 300px;
    }

    .result-footer h4 {
        font-size: 1.125rem;
        font-weight: 600;
        margin-bottom: 1rem;
    }

    .share-buttons,
    .next-steps-buttons {
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

        .result-actions,
        .share-results,
        .next-steps,
        .result-analytics {
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

    /* Styles pour le partage */
    .share-buttons {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .share-btn {
        flex: 1;
        min-width: 120px;
    }

    .dropdown-menu {
        min-width: 200px;
    }

    .dropdown-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    /* Modal email */
    .modal-content {
        border-radius: var(--border-radius);
    }

    .modal-header {
        border-bottom: 1px solid var(--gray-200);
    }

    .modal-footer {
        border-top: 1px solid var(--gray-200);
    }

    /* Icônes réseaux sociaux */
    .fa-facebook {
        color: #3b5998;
    }

    .fa-twitter {
        color: #1da1f2;
    }

    .fa-linkedin {
        color: #0077b5;
    }

    .fa-whatsapp {
        color: #25d366;
    }
</style>

<script>
    // Fonctionnalités de partage
    document.getElementById('emailShareBtn').addEventListener('click', function() {
        const emailModal = new bootstrap.Modal(document.getElementById('emailShareModal'));
        emailModal.show();
    });

    document.getElementById('sendEmailBtn').addEventListener('click', function() {
        const recipientEmail = document.getElementById('recipientEmail').value;
        const emailMessage = document.getElementById('emailMessage').value;

        if (!recipientEmail) {
            alert('Veuillez entrer une adresse email valide');
            return;
        }

        // Afficher un indicateur de chargement
        const sendBtn = document.getElementById('sendEmailBtn');
        const originalText = sendBtn.innerHTML;
        sendBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Envoi en cours...';
        sendBtn.disabled = true;

        // Envoyer la requête AJAX
        fetch('../includes/share_results.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'send_email',
                    attempt_id: <?php echo $attemptId; ?>,
                    recipient_email: recipientEmail,
                    message: emailMessage
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Les résultats ont été envoyés avec succès !');
                    const emailModal = bootstrap.Modal.getInstance(document.getElementById('emailShareModal'));
                    emailModal.hide();
                } else {
                    alert('Erreur lors de l\'envoi: ' + (data.message || 'Une erreur est survenue'));
                }
            })
            .catch(error => {
                alert('Erreur réseau: ' + error.message);
            })
            .finally(() => {
                sendBtn.innerHTML = originalText;
                sendBtn.disabled = false;
            });
    });

    // Téléchargement PDF
    document.getElementById('pdfDownloadBtn').addEventListener('click', function() {
        const pdfBtn = this;
        const originalText = pdfBtn.innerHTML;
        pdfBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Génération...';
        pdfBtn.disabled = true;

        // Ouvrir dans un nouvel onglet pour éviter les problèmes de popup
        const newWindow = window.open('', '_blank');

        fetch('../includes/share_results.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'generate_pdf',
                    attempt_id: <?php echo $attemptId; ?>
                })
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(err => {
                        throw new Error(err.message);
                    });
                }
                return response.blob();
            })
            .then(blob => {
                const url = window.URL.createObjectURL(blob);
                if (newWindow) {
                    newWindow.location = url;
                } else {
                    // Fallback si les popups sont bloqués
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `resultats_examen_<?php echo htmlspecialchars(preg_replace('/[^a-z0-9]/i', '_', $attempt['title'])); ?>_<?php echo date('Y-m-d'); ?>.pdf`;
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    a.remove();
                }
            })
            .catch(error => {
                alert(error.message);
                if (newWindow) newWindow.close();
            })
            .finally(() => {
                pdfBtn.innerHTML = originalText;
                pdfBtn.disabled = false;
            });
    });

    // Partage sur les réseaux sociaux
    function shareOnSocialMedia(platform) {
        const examTitle = encodeURIComponent("Mes résultats pour l'examen: <?php echo htmlspecialchars($attempt['title']); ?>");
        const examScore = encodeURIComponent("J'ai obtenu <?php echo isset($attempt['score']) ? round($attempt['score']) : 0; ?>%");
        const url = encodeURIComponent(window.location.href);

        let shareUrl;

        switch (platform) {
            case 'facebook':
                shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${url}&quote=${examTitle} - ${examScore}`;
                break;
            case 'twitter':
                shareUrl = `https://twitter.com/intent/tweet?text=${examTitle} - ${examScore}&url=${url}`;
                break;
            case 'linkedin':
                shareUrl = `https://www.linkedin.com/sharing/share-offsite/?url=${url}`;
                break;
            case 'whatsapp':
                shareUrl = `https://wa.me/?text=${examTitle} - ${examScore} ${url}`;
                break;
            default:
                return;
        }

        window.open(shareUrl, '_blank', 'width=600,height=400');
    }

    document.getElementById('facebookShareBtn').addEventListener('click', function(e) {
        e.preventDefault();
        shareOnSocialMedia('facebook');
    });

    document.getElementById('twitterShareBtn').addEventListener('click', function(e) {
        e.preventDefault();
        shareOnSocialMedia('twitter');
    });

    document.getElementById('linkedinShareBtn').addEventListener('click', function(e) {
        e.preventDefault();
        shareOnSocialMedia('linkedin');
    });

    document.getElementById('whatsappShareBtn').addEventListener('click', function(e) {
        e.preventDefault();
        shareOnSocialMedia('whatsapp');

    });


    document.addEventListener('DOMContentLoaded', function() {
        // Gestionnaire pour le bouton d'envoi d'email
        document.getElementById('sendEmailBtn').addEventListener('click', function() {
            const sendBtn = this;
            const originalContent = sendBtn.innerHTML;

            // Récupérer les données du formulaire
            const recipientEmail = document.getElementById('recipientEmail').value.trim();
            const emailSubject = document.getElementById('emailSubject').value.trim();
            const emailMessage = document.getElementById('emailMessage').value.trim();
            const sendCopy = document.getElementById('sendCopy').checked;

            // Validation basique
            if (!recipientEmail) {
                showAlert('Veuillez entrer au moins une adresse email valide', 'danger');
                return;
            }

            // Afficher l'indicateur de chargement
            sendBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Envoi en cours...';
            sendBtn.disabled = true;

            // Préparer les données à envoyer
            const formData = {
                action: 'send_email',
                attempt_id: <?php echo $attemptId; ?>,
                recipient_email: recipientEmail,
                subject: emailSubject,
                message: emailMessage,
                send_copy: sendCopy
            };

            // Envoyer la requête AJAX
            fetch('../includes/share_results.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(formData)
                })
                .then(response => {
                    if (!response.ok) {
                        return response.json().then(err => {
                            throw err;
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // Fermer le modal après succès
                        const emailModal = bootstrap.Modal.getInstance(document.getElementById('emailShareModal'));
                        emailModal.hide();

                        // Afficher une notification de succès
                        showAlert('Les résultats ont été envoyés avec succès!', 'success');
                    } else {
                        throw new Error(data.message || 'Erreur lors de l\'envoi');
                    }
                })
                .catch(error => {
                    showAlert(error.message, 'danger');
                })
                .finally(() => {
                    sendBtn.innerHTML = originalContent;
                    sendBtn.disabled = false;
                });
        });

        // Fonction pour afficher des alertes
        function showAlert(message, type) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show mt-3`;
            alertDiv.role = 'alert';
            alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;

            // Insérer l'alerte avant le formulaire
            const form = document.getElementById('emailShareForm');
            form.parentNode.insertBefore(alertDiv, form);

            // Supprimer automatiquement après 5 secondes
            setTimeout(() => {
                const bsAlert = new bootstrap.Alert(alertDiv);
                bsAlert.close();
            }, 5000);
        }
    });
</script>

<style>/* Style pour le modal d'envoi d'email */
#emailShareModal .modal-content {
    border-radius: 10px;
    overflow: hidden;
}

#emailShareModal .modal-header {
    padding: 1.2rem 1.5rem;
}

#emailShareModal .modal-body {
    padding: 1.5rem;
}

#emailShareModal .modal-footer {
    padding: 1rem 1.5rem;
    background-color: #f8f9fa;
}

#emailShareModal .form-control {
    padding: 0.75rem 1rem;
    border-radius: 8px;
    border: 1px solid #ced4da;
}

#emailShareModal .form-control:focus {
    border-color: #86b7fe;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

#emailShareModal textarea.form-control {
    min-height: 120px;
}

#emailShareModal .form-check-input {
    margin-top: 0.25rem;
}

/* Animation pour le bouton d'envoi */
#sendEmailBtn {
    transition: all 0.3s ease;
}

#sendEmailBtn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}</style>

