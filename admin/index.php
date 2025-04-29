<?php
// Inclure les fichiers de configuration et fonctions
include_once '../includes/config.php';
include_once '../includes/functions.php';

// Vérifier si l'utilisateur est connecté et a le rôle admin
require_login('login.php');
require_role('admin', '../index.php');

// Connexion à la base de données
include_once '../includes/db.php';

// Récupérer les statistiques générales
$stats_query = "
    SELECT 
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM users WHERE role = 'student') as student_count,
        (SELECT COUNT(*) FROM users WHERE role = 'teacher') as teacher_count,
        (SELECT COUNT(*) FROM exams) as total_exams,
        (SELECT COUNT(*) FROM exams WHERE status = 'published') as active_exams,
        (SELECT COUNT(*) FROM exam_attempts) as total_attempts,
        (SELECT COUNT(*) FROM exam_attempts WHERE status = 'completed') as completed_attempts,
        (SELECT COUNT(*) FROM proctoring_incidents) as total_incidents
";

$result = $conn->query($stats_query);
$stats = $result->fetch_assoc();

// Récupérer les examens récents
$recent_exams_query = "
    SELECT e.*, u.username as creator_name, 
           (SELECT COUNT(*) FROM exam_enrollments WHERE exam_id = e.id) as enrollment_count,
           (SELECT COUNT(*) FROM exam_attempts ea JOIN exam_enrollments ee ON ea.enrollment_id = ee.id WHERE ee.exam_id = e.id) as attempt_count
    FROM exams e
    JOIN users u ON e.created_by = u.id
    ORDER BY e.created_at DESC
    LIMIT 5
";

$recent_exams_result = $conn->query($recent_exams_query);
$recent_exams = $recent_exams_result->fetch_all(MYSQLI_ASSOC);

// Récupérer les incidents récents
$recent_incidents_query = "
    SELECT pi.*, ea.id as attempt_id, u.username, u.full_name, e.title as exam_title
    FROM proctoring_incidents pi
    JOIN exam_attempts ea ON pi.attempt_id = ea.id
    JOIN exam_enrollments ee ON ea.enrollment_id = ee.id
    JOIN users u ON ee.student_id = u.id
    JOIN exams e ON ee.exam_id = e.id
    ORDER BY pi.timestamp DESC
    LIMIT 10
";

$recent_incidents_result = $conn->query($recent_incidents_query);
$recent_incidents = $recent_incidents_result->fetch_all(MYSQLI_ASSOC);

// Récupérer les utilisateurs récents
$recent_users_query = "
    SELECT *
    FROM users
    ORDER BY created_at DESC
    LIMIT 5
";

$recent_users_result = $conn->query($recent_users_query);
$recent_users = $recent_users_result->fetch_all(MYSQLI_ASSOC);

// Fermer la connexion à la base de données
$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord administrateur - ExamSafe</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- Contenu principal -->
        <main class="admin-main">
            <!-- En-tête -->
            <?php include 'includes/header.php'; ?>
            
            <!-- Contenu de la page -->
            <div class="admin-content">
                <div class="page-header">
                    <h1>Tableau de bord</h1>
                    <p>Bienvenue, <?php echo htmlspecialchars($_SESSION['username']); ?></p>
                </div>
                
                <!-- Statistiques -->
                <section class="stats-section">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-content">
                                <h3>Utilisateurs</h3>
                                <p class="stat-value"><?php echo $stats['total_users']; ?></p>
                                <div class="stat-details">
                                    <span><?php echo $stats['student_count']; ?> étudiants</span>
                                    <span><?php echo $stats['teacher_count']; ?> enseignants</span>
                                </div>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <div class="stat-content">
                                <h3>Examens</h3>
                                <p class="stat-value"><?php echo $stats['total_exams']; ?></p>
                                <div class="stat-details">
                                    <span><?php echo $stats['active_exams']; ?> actifs</span>
                                </div>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-clipboard-check"></i>
                            </div>
                            <div class="stat-content">
                                <h3>Tentatives</h3>
                                <p class="stat-value"><?php echo $stats['total_attempts']; ?></p>
                                <div class="stat-details">
                                    <span><?php echo $stats['completed_attempts']; ?> complétées</span>
                                </div>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="stat-content">
                                <h3>Incidents</h3>
                                <p class="stat-value"><?php echo $stats['total_incidents']; ?></p>
                                <div class="stat-details">
                                    <span>Surveillance automatisée</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
                
                <!-- Graphiques -->
                <section class="charts-section">
                    <div class="charts-grid">
                        <div class="chart-card">
                            <div class="chart-header">
                                <h3>Activité des examens</h3>
                                <div class="chart-actions">
                                    <select id="exam-activity-period">
                                        <option value="week">7 derniers jours</option>
                                        <option value="month">30 derniers jours</option>
                                        <option value="year">12 derniers mois</option>
                                    </select>
                                </div>
                            </div>
                            <div class="chart-body">
                                <canvas id="exam-activity-chart"></canvas>
                            </div>
                        </div>
                        <div class="chart-card">
                            <div class="chart-header">
                                <h3>Répartition des incidents</h3>
                            </div>
                            <div class="chart-body">
                                <canvas id="incidents-chart"></canvas>
                            </div>
                        </div>
                    </div>
                </section>
                
                <!-- Examens récents -->
                <section class="recent-section">
                    <div class="section-header">
                        <h2>Examens récents</h2>
                        <a href="manage-exams.php" class="btn btn-outline btn-sm">Voir tout</a>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Titre</th>
                                    <th>Créateur</th>
                                    <th>Date</th>
                                    <th>Statut</th>
                                    <th>Inscriptions</th>
                                    <th>Tentatives</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_exams)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center">Aucun examen trouvé</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recent_exams as $exam): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($exam['title']); ?></td>
                                            <td><?php echo htmlspecialchars($exam['creator_name']); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($exam['created_at'])); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo $exam['status']; ?>">
                                                    <?php 
                                                        switch($exam['status']) {
                                                            case 'draft': echo 'Brouillon'; break;
                                                            case 'published': echo 'Publié'; break;
                                                            case 'archived': echo 'Archivé'; break;
                                                            default: echo ucfirst($exam['status']);
                                                        }
                                                    ?>
                                                </span>
                                            </td>
                                            <td><?php echo $exam['enrollment_count']; ?></td>
                                            <td><?php echo $exam['attempt_count']; ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="view-exam.php?id=<?php echo $exam['id']; ?>" class="btn-icon" title="Voir">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="edit-exam.php?id=<?php echo $exam['id']; ?>" class="btn-icon" title="Modifier">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button class="btn-icon delete-btn" data-id="<?php echo $exam['id']; ?>" title="Supprimer">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
                
                <!-- Incidents récents -->
                <section class="recent-section">
                    <div class="section-header">
                        <h2>Incidents récents</h2>
                        <a href="incidents.php" class="btn btn-outline btn-sm">Voir tout</a>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Étudiant</th>
                                    <th>Examen</th>
                                    <th>Date</th>
                                    <th>Gravité</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_incidents)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center">Aucun incident trouvé</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recent_incidents as $incident): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($incident['type']); ?></td>
                                            <td><?php echo htmlspecialchars($incident['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($incident['exam_title']); ?></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($incident['timestamp'])); ?></td>
                                            <td>
                                                <span class="severity-badge severity-<?php echo strtolower($incident['severity']); ?>">
                                                    <?php echo htmlspecialchars($incident['severity']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="view-incident.php?id=<?php echo $incident['id']; ?>" class="btn-icon" title="Voir">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="review-attempt.php?id=<?php echo $incident['attempt_id']; ?>" class="btn-icon" title="Revoir la tentative">
                                                        <i class="fas fa-video"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </main>
    </div>
    
    <script>
        // Données pour les graphiques (à remplacer par des données réelles)
        const examActivityData = {
            labels: ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'],
            datasets: [
                {
                    label: 'Examens créés',
                    data: [3, 5, 2, 7, 4, 1, 2],
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Tentatives',
                    data: [10, 15, 8, 12, 20, 5, 7],
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1
                }
            ]
        };
        
        const incidentsData = {
            labels: ['Absence visage', 'Plusieurs visages', 'Regard détourné', 'Audio suspect', 'Changement d\'onglet'],
            datasets: [{
                data: [12, 8, 15, 5, 10],
                backgroundColor: [
                    'rgba(255, 99, 132, 0.7)',
                    'rgba(54, 162, 235, 0.7)',
                    'rgba(255, 206, 86, 0.7)',
                    'rgba(75, 192, 192, 0.7)',
                    'rgba(153, 102, 255, 0.7)'
                ],
                borderWidth: 1
            }]
        };
        
        // Initialiser les graphiques
        document.addEventListener('DOMContentLoaded', function() {
            // Graphique d'activité des examens
            const examActivityCtx = document.getElementById('exam-activity-chart').getContext('2d');
            const examActivityChart = new Chart(examActivityCtx, {
                type: 'bar',
                data: examActivityData,
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
            
            // Graphique des incidents
            const incidentsCtx = document.getElementById('incidents-chart').getContext('2d');
            const incidentsChart = new Chart(incidentsCtx, {
                type: 'doughnut',
                data: incidentsData,
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'right',
                        }
                    }
                }
            });
            
            // Gestion du changement de période pour le graphique d'activité
            document.getElementById('exam-activity-period').addEventListener('change', function() {
                // Ici, vous pourriez faire une requête AJAX pour obtenir de nouvelles données
                // Pour l'exemple, nous allons simplement modifier les données existantes
                const period = this.value;
                
                if (period === 'month') {
                    examActivityChart.data.labels = ['Sem 1', 'Sem 2', 'Sem 3', 'Sem 4'];
                    examActivityChart.data.datasets[0].data = [12, 19, 15, 8];
                    examActivityChart.data.datasets[1].data = [40, 35, 50, 30];
                } else if (period === 'year') {
                    examActivityChart.data.labels = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'];
                    examActivityChart.data.datasets[0].data = [5, 8, 12, 15, 10, 7, 3, 2, 20, 25, 18, 10];
                    examActivityChart.data.datasets[1].data = [20, 25, 30, 40, 35, 25, 15, 10, 50, 60, 45, 30];
                } else {
                    examActivityChart.data.labels = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
                    examActivityChart.data.datasets[0].data = [3, 5, 2, 7, 4, 1, 2];
                    examActivityChart.data.datasets[1].data = [10, 15, 8, 12, 20, 5, 7];
                }
                
                examActivityChart.update();
            });
            
            // Gestion des boutons de suppression
            document.querySelectorAll('.delete-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    if (confirm('Êtes-vous sûr de vouloir supprimer cet examen ?')) {
                        // Ici, vous pourriez faire une requête AJAX pour supprimer l'examen
                        console.log('Suppression de l\'examen ' + id);
                    }
                });
            });
        });
    </script>
</body>
</html>
