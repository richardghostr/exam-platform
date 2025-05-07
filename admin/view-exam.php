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

// Vérifier si l'ID de l'examen est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: manage-exams.php');
    exit();
}

$examId = intval($_GET['id']);

// Vérifier si l'examen appartient à l'enseignant
$examQuery = $conn->query(query: "SELECT * FROM exams WHERE id = $examId");
if ($examQuery->num_rows === 0) {
    header('Location: manage-exams.php');
    exit();
}

$exam = $examQuery->fetch_assoc();

// Récupérer les classes assignées à cet examen
$classesQuery = $conn->query("
    SELECT c.* 
    FROM classes c 
    JOIN exam_classes ec ON c.id = ec.class_id 
    WHERE ec.exam_id = $examId 
    ORDER BY c.name ASC
");

// Récupérer les questions de l'examen
$questionsQuery = $conn->query("
    SELECT q.*, COUNT(qo.id) as option_count 
    FROM questions q 
    LEFT JOIN question_options qo ON q.id = qo.question_id 
    WHERE q.exam_id = $examId 
    GROUP BY q.id 
    ORDER BY q.id ASC
");

// Récupérer les statistiques de l'examen
$statsQuery = $conn->query("
    SELECT 
        COUNT(*) as total_attempts,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_attempts,
        AVG(CASE WHEN status = 'completed' THEN score ELSE NULL END) as avg_score,
        MIN(CASE WHEN status = 'completed' THEN score ELSE NULL END) as min_score,
        MAX(CASE WHEN status = 'completed' THEN score ELSE NULL END) as max_score
    FROM exam_results 
    WHERE exam_id = $examId
");
$stats = $statsQuery->fetch_assoc();

$pageTitle = "Détails de l'examen";
include 'includes/header.php';
?>
<style>
    /* Style général */

    /* Layout principal */


    a {
        text-decoration: none;
        color: inherit;
    }

    /* En-tête de l'examen */
    .exam-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 1px solid #e0e0e0;
    }

    .exam-title {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .exam-title h2 {
        margin: 0;
        font-size: 1.75rem;
    }

    .status-badge {
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .status-badge.active {
        background-color: #d4edda;
        color: #155724;
    }

    .status-badge.draft {
        background-color: #e2e3e5;
        color: #383d41;
    }

    .status-badge.scheduled {
        background-color: #cce5ff;
        color: #004085;
    }

    .exam-actions {
        display: flex;
        gap: 10px;
    }

    /* Cartes */
    .card {
        border-radius: 10px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        margin-bottom: 20px;
        border: none;
    }

    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-radius: 20px 20px 0 0;
    }

    .card-header h2 {
        margin: 0;
        font-size: 1.25rem;
    }

    .card-actions {
        display: flex;
        gap: 10px;
    }

    .card-body {
        padding: 20px;
    }

    /* Informations de l'examen */
    .exam-info {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .info-row {
        display: flex;
    }

    .info-label {
        font-weight: 600;
        width: 180px;
    }

    .info-value {
        flex: 1;
    }

    .option-badges {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .badge {
        padding: 5px 10px;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .badge-primary {
        background-color: #e3f2fd;
        color: #1976d2;
    }

    .badge-info {
        background-color: #e1f5fe;
        color: #0288d1;
    }

    .badge-success {
        background-color: #e8f5e9;
        color: #388e3c;
    }

    .badge-warning {
        background-color: #fff8e1;
        color: #ffa000;
    }

    /* Liste des questions */
    .questions-list {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .question-item {
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        overflow: hidden;
    }

    .question-header {
        display: flex;
        align-items: center;
        padding: 12px 15px;
    }

    .question-number {
        font-weight: 600;
        margin-right: 15px;
    }

    .question-type {
        font-size: 0.75rem;
        padding: 3px 8px;
        border-radius: 4px;
        color: #1976d2;
        margin-right: auto;
    }

    .question-points {
        font-weight: 600;
    }

    .question-content {
        padding: 15px;
    }

    .question-text {
        margin-bottom: 15px;
        font-size: 1rem;
        line-height: 1.5;
    }

    .question-options {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .option-item {
        display: flex;
        align-items: center;
        padding: 8px 12px;
        border-radius: 4px;
    }

    .option-marker {
        margin-right: 10px;
        color: #388e3c;
    }

    .option-item:not(.correct) .option-marker {
        color: #e53935;
    }

    /* Statistiques */

    .stat-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px;
        border-radius: 8px;
    }

    .stat-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        color: #1976d2;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
    }

    .stat-info {
        display: flex;
        flex-direction: column;
    }

    .stat-value {
        font-weight: 600;
        font-size: 1.1rem;
    }

    .stat-label {
        font-size: 0.75rem;
    }

    /* Liste des classes */
    .classes-list {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .class-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px;
        border-radius: 8px;
    }

    .class-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        color: #1976d2;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
    }

    .class-info {
        display: flex;
        flex-direction: column;
    }

    .class-name {
        font-weight: 600;
    }

    .class-details {
        font-size: 0.75rem;
    }

    /* Lien d'accès */
    .exam-link {
        display: flex;
        gap: 15px;
    }

    .link-container {
        display: flex;
        gap: 5px;
        margin-top: 10px;
    }

    .link-container input {
        flex: 1;
    }

    .copy-link {
        width: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .qr-code img {
        width: 100%;
        max-width: 150px;
        height: auto;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
    }

    /* États vides */
    .empty-state {
        text-align: center;
        padding: 30px 0;
    }

    .empty-icon {
        font-size: 3rem;
        margin-bottom: 15px;
    }

    .empty-state h3 {
        margin: 0 0 10px;
    }

    .empty-state p {

        margin-bottom: 20px;
    }

    /* Modal */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        align-items: center;
        justify-content: center;
    }

    .modal-content {
        border-radius: 10px;
        width: 100%;
        max-width: 500px;
        overflow: hidden;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    }

    .modal-header {
        padding: 15px 20px;
        border-bottom: 1px solid #e0e0e0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-header h2 {
        margin: 0;
        font-size: 1.25rem;
    }

    .close-modal {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: #6c757d;
    }

    .modal-body {
        padding: 20px;
    }

    .modal-footer {
        padding: 15px 20px;
        border-top: 1px solid #e0e0e0;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }

    /* Notification de copie */
    .copy-notification {
        position: fixed;
        bottom: 20px;
        right: 20px;
        background-color: #4caf50;
        color: white;
        padding: 12px 20px;
        border-radius: 4px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        opacity: 0;
        transform: translateY(20px);
        transition: opacity 0.3s, transform 0.3s;
    }

    .copy-notification.show {
        opacity: 1;
        transform: translateY(0);
    }

    .copy-notification i {
        margin-right: 8px;
    }

    /* Boutons */
    .btn {
        padding: 8px 16px;
        border-radius: 6px;
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s;
        border: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-primary {
        background-color: #4e73df;
        color: white;
    }

    .btn-primary:hover {
        background-color: #3a5bc7;
    }

    .btn-success {
        background-color: #1cc88a;
        color: white;
    }

    .btn-success:hover {
        background-color: #17a673;
    }

    .btn-info {
        background-color: #36b9cc;
        color: white;
    }

    .btn-info:hover {
        background-color: #2c9faf;
    }

    .btn-danger {
        background-color: #e74a3b;
        color: white;
    }

    .btn-danger:hover {
        background-color: #d62c1a;
    }

    .btn-outline-secondary {
        background-color: transparent;
        border: 1px solid #d1d3e2;
        color: #6e707e;
    }

    .btn-outline-secondary:hover {
        background-color: #f8f9fa;
    }

    .btn-sm {
        padding: 5px 10px;
        font-size: 0.75rem;
    }

    /* Formulaires */
    .form-control {
        border: 1px solid #d1d3e2;
        border-radius: 6px;
        padding: 8px 12px;
        font-size: 0.875rem;
        width: 100%;
    }

    .form-control:focus {
        border-color: #bac8f3;
        outline: none;
        box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
    }

    /* Responsive */
    @media (max-width: 992px) {
        .exam-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 15px;
        }

        .exam-actions {
            flex-wrap: wrap;
        }

        .stats-container {
            grid-template-columns: 1fr;
        }

        .exam-link {
            flex-direction: column;
        }

        .qr-code {
            margin-top: 15px;
            align-self: center;
        }
    }

    @media (max-width: 768px) {
        .info-row {
            flex-direction: column;
            gap: 5px;
        }

        .info-label {
            width: auto;
        }

        .card-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
        }

        .card-actions {
            width: 100%;
            justify-content: flex-end;
        }
    }
</style>
<div class="app-container">

    <main class="main-content" >

        <div class="content-wrapper">


            <div class="content-body">
                <div class="exam-header">
                    <div class="exam-title">
                        <h2><?php echo htmlspecialchars($exam['title']); ?></h2>
                        <span class="status-badge <?php echo $exam['status']; ?>">
                            <?php echo ucfirst($exam['status']); ?>
                        </span>
                    </div>
                    <div class="exam-actions">
                        <a href="edit-exam.php?id=<?php echo $examId; ?>" class="btn btn-primary">
                            <i class="fas fa-edit"></i> Modifier
                        </a>
                        <a href="add-questions.php?exam_id=<?php echo $examId; ?>" class="btn btn-success">
                            <i class="fas fa-question-circle"></i> Gérer les questions
                        </a>
                        <a href="view-results.php?exam_id=<?php echo $examId; ?>" class="btn btn-info">
                            <i class="fas fa-chart-bar"></i> Voir les résultats
                        </a>
                        <button class="btn btn-danger" id="deleteExamBtn">
                            <i class="fas fa-trash"></i> Supprimer
                        </button>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title">Informations générales</h2>
                            </div>
                            <div class="card-body">
                                <div class="exam-info">
                                    <div class="info-row">
                                        <div class="info-label">Matière:</div>
                                        <div class="info-value"><?php echo htmlspecialchars($exam['subject']); ?></div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-label">Description:</div>
                                        <div class="info-value"><?php echo nl2br(htmlspecialchars($exam['description'])); ?></div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-label">Durée:</div>
                                        <div class="info-value"><?php echo $exam['duration']; ?> minutes</div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-label">Score de réussite:</div>
                                        <div class="info-value"><?php echo $exam['passing_score']; ?>%</div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-label">Nombre de questions:</div>
                                        <div class=""><?php echo $questionsQuery->num_rows; ?></div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-label">Période:</div>
                                        <div class="info-value">
                                            Du <?php echo date('d/m/Y H:i', strtotime($exam['start_date'])); ?>
                                            au <?php echo date('d/m/Y H:i', strtotime($exam['end_date'])); ?>
                                        </div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-label">Options:</div>
                                        <style>
                                            /* Conteneur des badges */
                                            .option-badges {
                                                display: flex;
                                                flex-wrap: wrap;
                                                gap: 8px;
                                                margin-top: 5px;
                                            }

                                            /* Style de base des badges */
                                            .badge {
                                                display: inline-flex;
                                                align-items: center;
                                                padding: 6px 12px;
                                                border-radius: 16px;
                                                font-size: 0.75rem;
                                                font-weight: 600;
                                                line-height: 1;
                                                white-space: nowrap;
                                                transition: all 0.2s ease;
                                            }

                                            /* Variantes de couleurs */
                                            .badge-primary {
                                                display: inline-flex;
                                                align-items: center;
                                                padding: 6px 12px;
                                                border-radius: 16px;
                                                font-size: 0.75rem;
                                                font-weight: 600;
                                                line-height: 1;
                                                white-space: nowrap;
                                                transition: all 0.2s ease;
                                                background-color: rgba(78, 115, 223, 0.1);
                                                color: #4e73df;
                                                border: 1px solid rgba(78, 115, 223, 0.3);
                                            }

                                            .badge-info {display: inline-flex;
                                                align-items: center;
                                                padding: 6px 12px;
                                                border-radius: 16px;
                                                font-size: 0.75rem;
                                                font-weight: 600;
                                                line-height: 1;
                                                white-space: nowrap;
                                                transition: all 0.2s ease;
                                                background-color: rgba(54, 185, 204, 0.1);
                                                color: #36b9cc;
                                                border: 1px solid rgba(54, 185, 204, 0.3);
                                            }

                                            .badge-success {
                                                display: inline-flex;
                                                align-items: center;
                                                padding: 6px 12px;
                                                border-radius: 16px;
                                                font-size: 0.75rem;
                                                font-weight: 600;
                                                line-height: 1;
                                                white-space: nowrap;
                                                transition: all 0.2s ease;
                                                background-color: rgba(28, 200, 138, 0.1);
                                                color: #1cc88a;
                                                border: 1px solid rgba(28, 200, 138, 0.3);
                                            }

                                            .badge-warning {
                                                display: inline-flex;
                                                align-items: center;
                                                padding: 6px 12px;
                                                border-radius: 16px;
                                                font-size: 0.75rem;
                                                font-weight: 600;
                                                line-height: 1;
                                                white-space: nowrap;
                                                transition: all 0.2s ease;
                                                background-color: rgba(246, 194, 62, 0.1);
                                                color: #f6c23e;
                                                border: 1px solid rgba(246, 194, 62, 0.3);
                                            }

                                            /* Icônes optionnelles (si vous voulez en ajouter) */
                                            .badge i {
                                                margin-right: 5px;
                                                font-size: 0.7em;
                                            }

                                            /* Effets au survol */
                                            .badge:hover {
                                                transform: translateY(-1px);
                                                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                                            }

                                            /* Responsive */
                                            @media (max-width: 768px) {
                                                .option-badges {
                                                    gap: 6px;
                                                }

                                                .badge {
                                                    padding: 5px 10px;
                                                    font-size: 0.7rem;
                                                }
                                            }
                                        </style>
                                        <div class="info-value">
                                            <div class="option-badges">
                                                <?php if ($exam['proctoring_enabled']): ?>
                                                    <span class="badge-primary">Surveillance activée</span>
                                                <?php endif; ?>

                                                <?php if ($exam['randomize_questions']): ?>
                                                    <span class="badge-info">Questions aléatoires</span>
                                                <?php endif; ?>

                                                <?php if ($exam['show_results']): ?>
                                                    <span class="badge-success">Résultats immédiats</span>
                                                <?php endif; ?>

                                                <?php if ($exam['has_essay']): ?>
                                                    <span class="badge-warning">Questions à réponse libre</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title">Questions (<?php echo $questionsQuery->num_rows; ?>)</h2>
                                <div class="card-actions">
                                    <a href="add-questions.php?exam_id=<?php echo $examId; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-plus"></i> Ajouter
                                    </a>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if ($questionsQuery->num_rows > 0): ?>
                                    <div class="questions-list">
                                        <?php $questionNumber = 1; ?>
                                        <?php while ($question = $questionsQuery->fetch_assoc()): ?>
                                            <div class="question-item">
                                                <div class="question-header">
                                                    <div class="question-number"><?php echo $questionNumber++; ?></div>
                                                    <div class="question-type">
                                                        <?php
                                                        $typeLabels = [
                                                            'multiple_choice' => 'Choix multiples',
                                                            'single_choice' => 'Choix unique',
                                                            'true_false' => 'Vrai/Faux',
                                                            'essay' => 'Réponse libre'
                                                        ];
                                                        echo $typeLabels[$question['question_type']] ?? $question['question_type'];
                                                        ?>
                                                    </div>
                                                    <div class="question-points"><?php echo $question['points']; ?> pts</div>
                                                </div>
                                                <div class="question-content">
                                                    <div class="question-text"><?php echo $question['question_text']; ?></div>

                                                    <?php if ($question['question_type'] !== 'essay'): ?>
                                                        <?php
                                                        $options = $conn->query("SELECT * FROM question_options WHERE question_id = {$question['id']} ORDER BY id ASC");
                                                        ?>
                                                        <div class="question-options">
                                                            <?php while ($option = $options->fetch_assoc()): ?>
                                                                <div class="option-item <?php echo $option['is_correct'] ? 'correct' : ''; ?>">
                                                                    <span class="option-marker"><?php echo $option['is_correct'] ? '<i class="fas fa-check"></i>' : '<i class="fas fa-times"></i>'; ?></span>
                                                                    <span class="option-text"><?php echo htmlspecialchars($option['option_text']); ?></span>
                                                                </div>
                                                            <?php endwhile; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <div class="empty-icon">
                                            <i class="fas fa-question-circle"></i>
                                        </div>
                                        <h3>Aucune question</h3>
                                        <p>Cet examen ne contient pas encore de questions.</p>
                                        <a href="add-questions.php?exam_id=<?php echo $examId; ?>" class="btn btn-primary">
                                            <i class="fas fa-plus"></i> Ajouter des questions
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title">Statistiques</h2>
                            </div>
                            <div class="card-body">
                                <?php if ($stats['total_attempts'] > 0): ?>
                                    <div class="stats-container">
                                        <div class="stat-item">
                                            <div class="stat-icon">
                                                <i class="fas fa-users"></i>
                                            </div>
                                            <div class="stat-info">
                                                <div class="stat-value"><?php echo $stats['total_attempts']; ?></div>
                                                <div class="stat-label">Tentatives totales</div>
                                            </div>
                                        </div>

                                        <div class="stat-item">
                                            <div class="stat-icon">
                                                <i class="fas fa-check-circle"></i>
                                            </div>
                                            <div class="stat-info">
                                                <div class="stat-value"><?php echo $stats['completed_attempts']; ?></div>
                                                <div class="stat-label">Examens complétés</div>
                                            </div>
                                        </div>

                                        <div class="stat-item">
                                            <div class="stat-icon">
                                                <i class="fas fa-chart-line"></i>
                                            </div>
                                            <div class="stat-info">
                                                <div class="stat-value"><?php echo round($stats['avg_score'], 1); ?>%</div>
                                                <div class="stat-label">Score moyen</div>
                                            </div>
                                        </div>

                                        <div class="stat-item">
                                            <div class="stat-icon">
                                                <i class="fas fa-trophy"></i>
                                            </div>
                                            <div class="stat-info">
                                                <div class="stat-value"><?php echo round($stats['max_score'], 1); ?>%</div>
                                                <div class="stat-label">Meilleur score</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="text-center mt-3">
                                        <a href="view-results.php?exam_id=<?php echo $examId; ?>" class="btn btn-primary">
                                            <i class="fas fa-chart-bar"></i> Voir tous les résultats
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <div class="empty-icon">
                                            <i class="fas fa-chart-bar"></i>
                                        </div>
                                        <h3>Aucune donnée</h3>
                                        <p>Aucun étudiant n'a encore passé cet examen.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title">Classes assignées</h2>
                            </div>
                            <div class="card-body">
                                <?php if ($classesQuery->num_rows > 0): ?>
                                    <div class="classes-list">
                                        <?php while ($class = $classesQuery->fetch_assoc()): ?>
                                            <div class="class-item">
                                                <div class="class-icon">
                                                    <i class="fas fa-users"></i>
                                                </div>
                                                <div class="class-info">
                                                    <div class="class-name"><?php echo htmlspecialchars($class['name']); ?></div>
                                                    <div class="class-details"><?php echo htmlspecialchars($class['description']); ?></div>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <div class="empty-icon">
                                            <i class="fas fa-users"></i>
                                        </div>
                                        <h3>Aucune classe assignée</h3>
                                        <p>Cet examen n'est assigné à aucune classe.</p>
                                        <a href="edit-exam.php?id=<?php echo $examId; ?>" class="btn btn-primary">
                                            <i class="fas fa-edit"></i> Modifier l'examen
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title">Lien d'accès</h2>
                            </div>
                            <div class="card-body">
                                <div class="exam-link">
                                    <div class="link-info">
                                        <p>Partagez ce lien avec vos étudiants pour qu'ils puissent accéder à l'examen :</p>
                                        <div class="link-container">
                                            <input type="text" class="form-control" id="examLink" value="<?php echo SITE_URL . 'student/take-exam.php?id=' . $examId; ?>" readonly>
                                            <button class="btn btn-primary copy-link" data-clipboard-target="#examLink">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="qr-code">
                                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode(SITE_URL . 'student/take-exam.php?id=' . $examId); ?>" alt="QR Code">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Modal de confirmation de suppression -->
<div class="modal" id="deleteExamModal" style="justify-content: center;align-items: center;width:100% ;box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Confirmer la suppression</h2>
            <button class="close-modal">&times;</button>
        </div>
        <div class="modal-body">
            <p>Êtes-vous sûr de vouloir supprimer cet examen ? Cette action est irréversible et supprimera toutes les questions et résultats associés.</p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline-secondary close-modal">Annuler</button>
            <a href="delete-exam.php?id=<?php echo $examId; ?>" class="btn btn-danger">Supprimer</a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/clipboard@2.0.8/dist/clipboard.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialiser le presse-papier
        new ClipboardJS('.copy-link');

        // Gestion du modal de suppression
        const deleteExamBtn = document.getElementById('deleteExamBtn');
        const deleteExamModal = document.getElementById('deleteExamModal');
        const closeModalBtns = document.querySelectorAll('.close-modal');

        deleteExamBtn.addEventListener('click', function() {
            deleteExamModal.style.display = 'block';
        });

        closeModalBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                deleteExamModal.style.display = 'none';
            });
        });

        window.addEventListener('click', function(event) {
            if (event.target === deleteExamModal) {
                deleteExamModal.style.display = 'none';
            }
        });

        // Notification de copie
        document.querySelector('.copy-link').addEventListener('click', function() {
            const notification = document.createElement('div');
            notification.className = 'copy-notification';
            notification.innerHTML = '<i class="fas fa-check"></i> Lien copié !';
            document.body.appendChild(notification);

            setTimeout(function() {
                notification.classList.add('show');
            }, 10);

            setTimeout(function() {
                notification.classList.remove('show');
                setTimeout(function() {
                    document.body.removeChild(notification);
                }, 300);
            }, 2000);
        });
    });
</script>