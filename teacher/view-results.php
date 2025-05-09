<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Vérifier si l'utilisateur est connecté et est un enseignant
if (!isLoggedIn() || !isTeacher()) {
    header('Location: ../login.php');
    exit();
}

// Récupérer l'ID de l'enseignant
$teacherId = $_SESSION['user_id'];

// Vérifier si l'ID de l'examen est fourni
if (!isset($_GET['exam_id']) || empty($_GET['exam_id'])) {
    header('Location: manage-exams.php');
    exit();
}

$examId = intval($_GET['exam_id']);

// Vérifier si l'examen appartient à l'enseignant
$examQuery = $conn->query("SELECT * FROM exams WHERE id = $examId AND teacher_id = $teacherId");
if ($examQuery->num_rows === 0) {
    header('Location: manage-exams.php');
    exit();
}

$exam = $examQuery->fetch_assoc();

// Récupérer les résultats de l'examen
$resultsQuery = $conn->query("
    SELECT 
        er.*,
        u.username,
        u.first_name,
        u.last_name,
        u.email,
        c.name as class_name
    FROM exam_results er
    JOIN users u ON er.user_id = u.id
    LEFT JOIN user_classes uc ON u.id = uc.user_id
    LEFT JOIN classes c ON uc.class_id = c.id
    WHERE er.exam_id = $examId
    ORDER BY er.completed_at DESC
");

// Récupérer les statistiques de l'examen
$statsQuery = $conn->query("
    SELECT 
        COUNT(*) as total_attempts,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_attempts,
        AVG(CASE WHEN status = 'completed' THEN score ELSE NULL END) as avg_score,
        MIN(CASE WHEN status = 'completed' THEN score ELSE NULL END) as min_score,
        MAX(CASE WHEN status = 'completed' THEN score ELSE NULL END) as max_score,
        SUM(CASE WHEN status = 'completed' AND score >= {$exam['passing_score']} THEN 1 ELSE 0 END) as passed_count
    FROM exam_results 
    WHERE exam_id = $examId
");
$stats = $statsQuery->fetch_assoc();

// Récupérer les statistiques par question
$questionStatsQuery = $conn->query("
    SELECT 
        q.id,
        q.question_text,
        q.question_type,
        q.points,
        COUNT(qa.id) as total_answers,
        SUM(CASE WHEN qa.is_correct = 1 THEN 1 ELSE 0 END) as correct_answers
    FROM questions q
    LEFT JOIN question_answers qa ON q.id = qa.question_id
    WHERE q.exam_id = $examId
    GROUP BY q.id
    ORDER BY q.id ASC
");

$pageTitle = "Résultats de l'examen";
include 'includes/header.php';
?>
<style>
    a{
        text-decoration: none;
    }
    :root {
  --success-color: #28a745;
  --warning-color: #ffc107;
  --danger-color: #dc3545;
  --info-color: #17a2b8;
}

.content-header h1 {
  margin: 0;
  font-size: 1.8rem;
  color: var(--text-color);
}

/* Cards */
.card {
  background: white;
  border-radius: 10px;
  margin-bottom: 25px;
  border: none;
  overflow: hidden;
  box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
}

.card-header {
  padding: 18px 25px;
  border-bottom: 1px solid var(--border-color);
  display: flex;
  justify-content: space-between;
  align-items: center;
  background-color: white;
  
}

.card-header h2 {
  margin: 0;
  font-size: 1.3rem;
  font-weight: 600;
  color: var(--text-color);
}

.card-body {
  padding: 25px;
}

.stats-section {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 25px;
  margin-bottom: 30px;
}

.stats-card {
  background: white;
  border-radius: 10px;
  padding: 20px;
  box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
}

.stats-card h3 {
  margin-top: 0;
  margin-bottom: 20px;
  font-size: 1.1rem;
  color: var(--text-color);
  font-weight: 600;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 20px;
}

.stat-card {
  background: white;
  border-radius: 8px;
  padding: 20px;
  display: flex;
  flex-direction: column;
  box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
  border-left: 4px solid var(--primary-color);
}
.user-info {
  display: flex;
  align-items: center;
}

.user-avatar {
  width: 40px;
  height: 40px;
  background-color: var(--secondary-color);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-right: 10px;
  font-weight: bold;
  color: var(--primary-color);
}

.user-name {
  font-weight: 500;
}

.user-email {
  font-size: 0.8rem;
  color: var(--light-text);
}

/* Status badges */
.status-badge {
  padding: 5px 10px;
  border-radius: 20px;
  font-size: 0.8rem;
  font-weight: 500;
  display: inline-block;
}

.status-badge.published {
  background-color: #e6f7ee;
  color: var(--success-color);
}

.status-badge.draft {
  background-color: #fff8e6;
  color: var(--warning-color);
}

.status-badge.in-progress {
  background-color: #fee;
  color: var(--danger-color);
}

/* Score badges */
.score-badge {
  display: inline-block;
  padding: 5px 10px;
  border-radius: 20px;
  font-size: 0.8rem;
  font-weight: 500;
}

.score-badge.passed {
  background-color: #e6f7ee;
  color: var(--success-color);
}

.score-badge.failed {
  background-color: #fee;
  color: var(--danger-color);
}

/* Buttons */
.btn {
  padding: 8px 16px;
  border-radius: 6px;
  border: none;
  cursor: pointer;
  font-size: 0.9rem;
  transition: all 0.3s;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.btn-primary {
  background-color: var(--primary-color);
  color: white;
}

.btn-primary:hover {
  background-color: #3a5bef;
  transform: translateY(-2px);
}

.btn-outline-secondary {
  background-color: transparent;
  border: 1px solid var(--border-color);
  color: var(--text-color);
}

.btn-outline-secondary:hover {
  background-color: var(--secondary-color);
}

/* Action menu */
.action-menu {
  list-style: none;
  padding: 10px 0;
  margin: 0;
  background: white;
  border-radius: 6px;
  box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
  border: 1px solid var(--border-color);
  min-width: 200px;
}

.action-menu li {
  padding: 8px 15px;
  cursor: pointer;
  transition: all 0.2s;
}

.action-menu li:hover {
  background-color: var(--secondary-color);
}

.action-menu li i {
  margin-right: 10px;
  width: 20px;
  text-align: center;
}

/* Examens par mois */
.monthly-stats {
  display: flex;
  justify-content: space-between;
  margin-top: 15px;
}

.monthly-stat {
  flex: 1;
  text-align: center;
}

.monthly-stat h4 {
  margin: 0 0 5px 0;
  font-size: 0.9rem;
  color: var(--light-text);
}

.monthly-stat .value {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--text-color);
}

/* Export section */
.export-section {
  margin-bottom: 30px;
}

.export-options {
  display: flex;
  gap: 15px;
  margin-top: 15px;
}

.export-option {
  display: flex;
  align-items: center;
  padding: 10px 15px;
  background: white;
  border-radius: 6px;
  box-shadow: var(--card-shadow);
  cursor: pointer;
  transition: all 0.2s;
}

.export-option:hover {
  transform: translateY(-2px);
  box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.export-option i {
  margin-right: 10px;
  color: var(--primary-color);
}

/* Responsive */
@media (max-width: 768px) {
  .app-container {
    flex-direction: column;
  }
  
  .sidebar {
    width: 100%;
    height: auto;
  }
  
  .stats-section {
    grid-template-columns: 1fr;
  }
  
  .stats-grid {
    grid-template-columns: 1fr 1fr;
  }
}

@media (max-width: 480px) {
  .stats-grid {
    grid-template-columns: 1fr;
  }
  
  .main-content {
    padding: 15px;
  }
  
  .card-body {
    padding: 15px;
  }
}
.btn-icon {
  width: 32px;
  height: 32px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  border-radius: 50%;
}

.stat-card h4 {
  margin: 0 0 10px 0;
  font-size: 1rem;
  color: var(--light-text);
}

.stat-value {
  font-size: 1.8rem;
  font-weight: 700;
  margin-bottom: 5px;
  color: var(--text-color);
}

.stat-label {
  font-size: 0.9rem;
  color: var(--light-text);
}

.stat-trend {
  display: flex;
  align-items: center;
  font-size: 0.85rem;
  margin-top: 8px;
}

.trend-up {
  color: var(--success-color);
}

.trend-down {
  color: var(--danger-color);
}

.stat-subtext {
  font-size: 0.85rem;
  color: var(--light-text);
  margin-top: 5px;
}
</style>
<div class="app-container" >
    <main class="main-content"  style="width: 100%;margin-left: 25px;;margin-right: 20px;" >
        
        <div class="content-wrapper">
            
            <div class="content-body">
                <div class="exam-header" style="display: flex; justify-content: space-between; align-items: center;margin-top: 20px;">
                    
                    <div class="exam-title" style="display: flex; align-items: center;">
                        <h2><?php echo htmlspecialchars($exam['title']); ?></h2>
                        <span class="status-badge <?php echo $exam['status']; ?>">
                            <?php echo ucfirst($exam['status']); ?>
                        </span>
                    </div>
                    <div class="exam-actions">
                        <button class="btn btn-primary" id="exportResultsBtn">
                            <i class="fas fa-download"></i> Exporter les résultats
                        </button>
                        <a href="view-exam.php?id=<?php echo $examId; ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Retour à l'examen
                        </a>
                    </div>
                </div><br>
                
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title">Statistiques générales</h2>
                            </div>
                            <div class="card-body">
                                <div class="stats-grid">
                                    <div class="stat-card">
                                        <div class="stat-icon">
                                            <i class="fas fa-users"></i>
                                        </div>
                                        <div class="stat-content">
                                            <div class="stat-value"><?php echo $stats['total_attempts']; ?></div>
                                            <div class="stat-label">Tentatives totales</div>
                                        </div>
                                    </div>
                                    
                                    <div class="stat-card">
                                        <div class="stat-icon">
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                        <div class="stat-content">
                                            <div class="stat-value"><?php echo $stats['completed_attempts']; ?></div>
                                            <div class="stat-label">Examens complétés</div>
                                        </div>
                                    </div>
                                    
                                    <div class="stat-card">
                                        <div class="stat-icon">
                                            <i class="fas fa-trophy"></i>
                                        </div>
                                        <div class="stat-content">
                                            <div class="stat-value"><?php echo $stats['passed_count']; ?></div>
                                            <div class="stat-label">Étudiants ayant réussi</div>
                                        </div>
                                    </div>
                                    
                                    <div class="stat-card">
                                        <div class="stat-icon">
                                            <i class="fas fa-chart-line"></i>
                                        </div>
                                        <div class="stat-content">
                                            <div class="stat-value"><?php echo safeRound($stats['avg_score'], 1); ?>%</div>
                                            <div class="stat-label">Score moyen</div>
                                        </div>
                                    </div>
                                    
                                    <div class="stat-card">
                                        <div class="stat-icon">
                                            <i class="fas fa-arrow-down"></i>
                                        </div>
                                        <div class="stat-content">
                                            <div class="stat-value"><?php echo safeRound($stats['min_score'], 1); ?>%</div>
                                            <div class="stat-label">Score minimum</div>
                                        </div>
                                    </div>
                                    
                                    <div class="stat-card">
                                        <div class="stat-icon">
                                            <i class="fas fa-arrow-up"></i>
                                        </div>
                                        <div class="stat-content">
                                            <div class="stat-value"><?php echo safeRound($stats['max_score'], 1); ?>%</div>
                                            <div class="stat-label">Score maximum</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title">Résultats des étudiants</h2>
                                <div class="card-actions">
                                    <div class="search-box">
                                        <input type="text" id="searchResults" placeholder="Rechercher un étudiant...">
                                        <i class="fas fa-search"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if ($resultsQuery->num_rows > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table" id="resultsTable">
                                            <thead>
                                                <tr>
                                                    <th>Étudiant</th>
                                                    <th>Classe</th>
                                                    <th>Statut</th>
                                                    <th>Score</th>
                                                    <th>Temps</th>
                                                    <th>Date</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($result = $resultsQuery->fetch_assoc()): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="user-info">
                                                                <div class="user-avatar">
                                                                    <?php echo strtoupper(substr($result['first_name'], 0, 1) . substr($result['last_name'], 0, 1)); ?>
                                                                </div>
                                                                <div class="user-details">
                                                                    <div class="user-name"><?php echo htmlspecialchars($result['first_name'] . ' ' . $result['last_name']); ?></div>
                                                                    <div class="user-email"><?php echo htmlspecialchars($result['email']); ?></div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($result['class_name'] ?? 'N/A'); ?></td>
                                                        <td>
                                                            <span class="status-badge <?php echo $result['status']; ?>">
                                
                                                                <?php echo !empty($result['status']) ? htmlspecialchars($result['status']) : 'Aucun statut'; ?><
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php if ($result['status'] === 'completed'): ?>
                                                                <div class="score-badge <?php echo $result['score'] >= $exam['passing_score'] ? 'passed' : 'failed'; ?>">
                                                                    <?php echo $result['score']; ?>%
                                                                </div>
                                                            <?php else: ?>
                                                                <span class="text-muted">N/A</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php 
                                                                if ($result['status'] === 'completed' && $result['time_spent']) {
                                                                    $minutes = floor($result['time_spent'] / 60);
                                                                    $seconds = $result['time_spent'] % 60;
                                                                    echo sprintf('%02d:%02d', $minutes, $seconds);
                                                                } else {
                                                                    echo '<span class="text-muted">N/A</span>';
                                                                }
                                                            ?>
                                                        </td>
                                                        <td><?php echo date('d/m/Y H:i', strtotime($result['completed_at'] ?? $result['started_at'])); ?></td>
                                                        <td>
                                                            <div class="table-actions">
                                                                <a href="view-submission.php?result_id=<?php echo $result['id']; ?>" class="btn btn-icon btn-sm" title="Voir les détails">
                                                                    <i class="fas fa-eye"></i>
                                                                </a>
                                                                <?php if ($result['status'] === 'completed' && !$result['is_graded'] && $exam['has_essay']): ?>
                                                                    <a href="grade-submission.php?result_id=<?php echo $result['id']; ?>" class="btn btn-icon btn-sm btn-primary" title="Noter">
                                                                        <i class="fas fa-check"></i>
                                                                    </a>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <div class="empty-icon">
                                            <i class="fas fa-users"></i>
                                        </div>
                                        <h3>Aucun résultat</h3>
                                        <p>Aucun étudiant n'a encore passé cet examen.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title">Distribution des scores</h2>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="scoresChart"></canvas>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title">Performance par question</h2>
                            </div>
                            <div class="card-body">
                                <?php if ($questionStatsQuery->num_rows > 0): ?>
                                    <div class="question-stats">
                                        <?php while ($questionStat = $questionStatsQuery->fetch_assoc()): ?>
                                            <?php 
                                                $correctPercentage = $questionStat['total_answers'] > 0 
                                                    ? round(($questionStat['correct_answers'] / $questionStat['total_answers']) * 100) 
                                                    : 0;
                                            ?>
                                            <div class="question-stat-item">
                                                <div class="question-stat-header">
                                                    <div class="question-text">
                                                        <?php echo htmlspecialchars(substr(strip_tags($questionStat['question_text']), 0, 50)) . (strlen(strip_tags($questionStat['question_text'])) > 50 ? '...' : ''); ?>
                                                    </div>
                                                    <div class="question-type">
                                                        <?php 
                                                            $typeLabels = [
                                                                'multiple_choice' => 'QCM',
                                                                'single_choice' => 'QCU',
                                                                'true_false' => 'V/F',
                                                                'essay' => 'Rédaction'
                                                            ];
                                                            echo $typeLabels[$questionStat['question_type']] ?? $questionStat['question_type'];
                                                        ?>
                                                    </div>
                                                </div>
                                                <div class="question-stat-progress">
                                                    <div class="progress-bar">
                                                        <div class="progress-fill" style="width: <?php echo $correctPercentage; ?>%"></div>
                                                    </div>
                                                    <div class="progress-value"><?php echo $correctPercentage; ?>% correct</div>
                                                </div>
                                                <div class="question-stat-details">
                                                    <div class="detail-item">
                                                        <span class="detail-label">Réponses:</span>
                                                        <span class="detail-value"><?php echo $questionStat['total_answers']; ?></span>
                                                    </div>
                                                    <div class="detail-item">
                                                        <span class="detail-label">Correctes:</span>
                                                        <span class="detail-value"><?php echo $questionStat['correct_answers']; ?></span>
                                                    </div>
                                                    <div class="detail-item">
                                                        <span class="detail-label">Points:</span>
                                                        <span class="detail-value"><?php echo $questionStat['points']; ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <div class="empty-icon">
                                            <i class="fas fa-question-circle"></i>
                                        </div>
                                        <h3>Aucune donnée</h3>
                                        <p>Aucune donnée de performance n'est disponible pour les questions.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Modal d'exportation des résultats -->
<div class="modal" id="exportModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Exporter les résultats</h2>
            <button class="close-modal">&times;</button>
        </div>
        <div class="modal-body">
            <form id="exportForm" method="POST" action="export-results.php">
                <input type="hidden" name="exam_id" value="<?php echo $examId; ?>">
                
                <div class="form-group">
                    <label for="export_format">Format</label>
                    <select id="export_format" name="format" class="form-control">
                        <option value="csv">CSV</option>
                        <option value="excel">Excel</option>
                        <option value="pdf">PDF</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Données à inclure</label>
                    <div class="checkbox-group">
                        <label class="checkbox-container">
                            <input type="checkbox" name="include_student_info" checked>
                            <span class="checkmark"></span>
                            Informations sur les étudiants
                        </label>
                        <label class="checkbox-container">
                            <input type="checkbox" name="include_scores" checked>
                            <span class="checkmark"></span>
                            Scores et résultats
                        </label>
                        <label class="checkbox-container">
                            <input type="checkbox" name="include_answers">
                            <span class="checkmark"></span>
                            Réponses détaillées
                        </label>
                        <label class="checkbox-container">
                            <input type="checkbox" name="include_time">
                            <span class="checkmark"></span>
                            Informations temporelles
                        </label>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline-secondary close-modal">Annuler</button>
            <button class="btn btn-primary" id="confirmExport">Exporter</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Recherche dans le tableau des résultats
    const searchInput = document.getElementById('searchResults');
    const resultsTable = document.getElementById('resultsTable');
    
    if (searchInput && resultsTable) {
        searchInput.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = resultsTable.querySelectorAll('tbody tr');
            
            rows.forEach(function(row) {
                const studentName = row.querySelector('.user-name').textContent.toLowerCase();
                const studentEmail = row.querySelector('.user-email').textContent.toLowerCase();
                const className = row.cells[1].textContent.toLowerCase();
                
                if (studentName.includes(searchTerm) || studentEmail.includes(searchTerm) || className.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
    
    // Graphique de distribution des scores
    const scoresCtx = document.getElementById('scoresChart');
    
    if (scoresCtx) {
        // Données fictives pour le graphique (à remplacer par des données réelles)
        const scoresData = {
            labels: ['0-20%', '21-40%', '41-60%', '61-80%', '81-100%'],
            datasets: [{
                label: 'Nombre d\'étudiants',
                data: [2, 5, 10, 15, 8],
                backgroundColor: [
                    'rgba(255, 99, 132, 0.6)',
                    'rgba(255, 159, 64, 0.6)',
                    'rgba(255, 205, 86, 0.6)',
                    'rgba(75, 192, 192, 0.6)',
                    'rgba(54, 162, 235, 0.6)'
                ],
                borderColor: [
                    'rgb(255, 99, 132)',
                    'rgb(255, 159, 64)',
                    'rgb(255, 205, 86)',
                    'rgb(75, 192, 192)',
                    'rgb(54, 162, 235)'
                ],
                borderWidth: 1
            }]
        };
        
        new Chart(scoresCtx, {
            type: 'bar',
            data: scoresData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    }
    
    // Gestion du modal d'exportation
    const exportResultsBtn = document.getElementById('exportResultsBtn');
    const exportModal = document.getElementById('exportModal');
    const closeModalBtns = document.querySelectorAll('.close-modal');
    const confirmExportBtn = document.getElementById('confirmExport');
    const exportForm = document.getElementById('exportForm');
    
    if (exportResultsBtn && exportModal) {
        exportResultsBtn.addEventListener('click', function() {
            exportModal.style.display = 'block';
        });
        
        closeModalBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                exportModal.style.display = 'none';
            });
        });
        
        window.addEventListener('click', function(event) {
            if (event.target === exportModal) {
                exportModal.style.display = 'none';
            }
        });
        
        confirmExportBtn.addEventListener('click', function() {
            exportForm.submit();
        });
    }
});
</script>
