<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Vérifier si l'utilisateur est connecté et est un administrateur
if (!isLoggedIn() || !isAdmin()) {
    header('Location: login.php');
    exit();
}

// Fonction pour enregistrer les rapports générés
function saveGeneratedReport($conn, $reportData)
{
    $query = "INSERT INTO generated_reports 
              (token, report_name, report_type, start_date, end_date, format, generated_at, generated_by) 
              VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)";

    $stmt = $conn->prepare($query);
    $stmt->bind_param(
        'ssssssi',
        $reportData['token'],
        $reportData['report_name'],
        $reportData['report_type'],
        $reportData['start_date'],
        $reportData['end_date'],
        $reportData['format'],
        $_SESSION['user_id']
    );

    return $stmt->execute();
}

// Fonction pour récupérer les rapports générés
function getGeneratedReports($conn, $limit = 5)
{
    $query = "SELECT * FROM generated_reports 
              ORDER BY generated_at DESC 
              LIMIT ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $limit);
    $stmt->execute();

    $result = $stmt->get_result();
    $reports = [];

    while ($row = $result->fetch_assoc()) {
        $reports[] = $row;
    }

    return $reports;
}

// Créer la table si elle n'existe pas (à exécuter une seule fois)
function createReportsTable($conn)
{
    $query = "CREATE TABLE IF NOT EXISTS generated_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        token VARCHAR(32) NOT NULL,
        report_name VARCHAR(255) NOT NULL,
        report_type VARCHAR(50) NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        format VARCHAR(10) NOT NULL,
        generated_at DATETIME NOT NULL,
        generated_by INT NOT NULL,
        FOREIGN KEY (generated_by) REFERENCES users(id)
    )";

    return $conn->query($query);
}

// Exécuter la création de table (à commenter après la première exécution)
// createReportsTable($conn);

// Récupérer les rapports récents
$recentReports = getGeneratedReports($conn, 5);

// Récupérer les statistiques générales
$totalStudents = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'student'")->fetch_assoc()['count'];
$totalTeachers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'teacher'")->fetch_assoc()['count'];
$totalExams = $conn->query("SELECT COUNT(*) as count FROM exams")->fetch_assoc()['count'];
$totalCompletedExams = $conn->query("SELECT COUNT(*) as count FROM exam_results WHERE status = 'completed'")->fetch_assoc()['count'];

// Récupérer les données pour le graphique des examens par mois
$examsByMonth = $conn->query("
    SELECT 
        MONTH(created_at) as month, 
        COUNT(*) as count 
    FROM exams 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY MONTH(created_at)
    ORDER BY month
");

// Récupérer les données pour le graphique des résultats moyens
$avgResults = $conn->query("
    SELECT 
        e.subject,
        AVG(er.score) as avg_score
    FROM exam_results er
    JOIN exams e ON er.exam_id = e.id
    WHERE er.status = 'completed'
    GROUP BY e.subject
    ORDER BY avg_score DESC
    LIMIT 10
");

// Récupérer les incidents de surveillance
$proctorIncidents = $conn->query("
    SELECT pi.*, ea.exam_id, e.title as exam_title, u.username, u.first_name, u.last_name
    FROM proctoring_incidents pi
    JOIN exam_attempts ea ON pi.attempt_id = ea.id
    JOIN exams e ON ea.exam_id = e.id
    JOIN users u ON ea.user_id = u.id
    WHERE 1=1
    LIMIT 20
");

$pageTitle = "Rapports et Statistiques";
include 'includes/header.php';
?>

<h1 class="page-title" style="margin-bottom: 20px; margin-left: 5px">Rapports et Statistiques</h1>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-header">
            <h3 class="stat-title">Étudiants</h3>
        </div>
        <div class="stat-value"><?php echo $totalStudents; ?></div>
        <div class="stat-trend trend-up">
            <i class="fas fa-arrow-up trend-icon"></i> 5.2% vs mois dernier
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <h3 class="stat-title">Enseignants</h3>
        </div>
        <div class="stat-value"><?php echo $totalTeachers; ?></div>
        <div class="stat-trend trend-up">
            <i class="fas fa-arrow-up trend-icon"></i> 3.1% vs mois dernier
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <h3 class="stat-title">Examens</h3>
        </div>
        <div class="stat-value"><?php echo $totalExams; ?></div>
        <div class="stat-trend trend-up">
            <i class="fas fa-arrow-up trend-icon"></i> 7.8% vs mois dernier
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <h3 class="stat-title">Examens Complétés</h3>
        </div>
        <div class="stat-value"><?php echo $totalCompletedExams; ?></div>
        <div class="stat-trend trend-up">
            <i class="fas fa-arrow-up trend-icon"></i> 12.4% vs mois dernier
        </div>
    </div>
</div>

<div class="section">
    <div class="section-header">
        <h2 class="section-title">Examens créés par mois</h2>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="chart-container">
                <canvas id="examsChart"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="section">
    <div class="section-header">
        <h2 class="section-title">Score moyen par matière</h2>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="chart-container">
                <canvas id="scoresChart"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="section">
    <div class="section-header">
        <h2 class="section-title">Incidents de surveillance récents</h2>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Examen</th>
                            <th>Étudiant</th>
                            <th>Type d'incident</th>
                            <th>Date</th>
                            <th>Détails</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($proctorIncidents->num_rows > 0): ?>
                            <?php while ($incident = $proctorIncidents->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($incident['exam_title']); ?></td>
                                    <td><?php echo htmlspecialchars($incident['username']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo getIncidentBadgeClass($incident['incident_type']); ?>">
                                            <?php echo htmlspecialchars($incident['incident_type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($incident['timestamp'])); ?></td>
                                    <td><?php echo htmlspecialchars(substr($incident['description'], 0, 50)) . (strlen($incident['details']) > 50 ? '...' : ''); ?></td>
                                    <td>
                                        <button class="btn btn-icon btn-sm view-incident"
                                            data-id="<?php echo $incident['id']; ?>"
                                            data-exam="<?php echo htmlspecialchars($incident['exam_title']); ?>"
                                            data-student="<?php echo htmlspecialchars($incident['first_name'] . ' ' . $incident['last_name']); ?>"
                                            data-type="<?php echo htmlspecialchars($incident['incident_type']); ?>"
                                            data-date="<?php echo date('d/m/Y H:i', strtotime($incident['timestamp'])); ?>"
                                            data-details="<?php echo htmlspecialchars($incident['description']); ?>"
                                            data-image="<?php echo !empty($incident['image_path']) ? htmlspecialchars($incident['image_path']) : '../assets/images/placeholder.jpg'; ?>">
                                            <i class="fas fa-eye"></i>
                                        </button><br>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">Aucun incident récent</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <!-- Modal pour afficher les détails d'un incident -->
            <div class="modal" id="incidentModal">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3 class="modal-title">Détails de l'incident</h3>
                            <button type="button" class="close-modal">&times;</button>
                        </div>
                        <div class="modal-body">
                            <div class="incident-details">
                                <div class="incident-info">
                                    <p><strong>Examen:</strong> <span id="incident-exam"></span></p>
                                    <p><strong>Étudiant:</strong> <span id="incident-student"></span></p>
                                    <p><strong>Type d'incident:</strong> <span id="incident-type"></span></p>
                                    <p><strong>Date:</strong> <span id="incident-date"></span></p>
                                </div>
                                <div class="incident-description">
                                    <h4>Description</h4>
                                    <p id="incident-description"></p>
                                </div>
                                <div class="incident-evidence">
                                    <h4>Preuves</h4>
                                    <div id="incident-evidence-container">
                                        <img id="incident-image" src="../assets/images/placeholder.jpg" alt="Preuve de l'incident">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary close-modal">Fermer</button>
                        </div>
                    </div>
                </div>
            </div><br>
        </div>
    </div>
</div>

<div class="section">
    <div class="row">
        <div class="col-md-6">
            <div class="section-header">
                <h2 class="section-title">Générer des rapports</h2>
            </div>

            <div class="card">
                <div class="card-body">
                    <form id="reportForm" class="admin-form">
                        <input type="hidden" name="report_token" id="report_token" value="<?php echo bin2hex(random_bytes(16)); ?>">
                        <div class="form-group">
                            <label for="reportType" class="form-label">Type de rapport</label>
                            <select id="reportType" name="reportType" class="form-control">
                                <option value="exam_results">Résultats d'examens</option>
                                <option value="user_activity">Activité des utilisateurs</option>
                                <option value="proctoring_incidents">Incidents de surveillance</option>
                                <option value="system_usage">Utilisation du système</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="dateRange" class="form-label">Période</label>
                            <select id="dateRange" name="dateRange" class="form-control">
                                <option value="7days">7 derniers jours</option>
                                <option value="30days">30 derniers jours</option>
                                <option value="90days">90 derniers jours</option>
                                <option value="year">Année en cours</option>
                                <option value="custom">Personnalisé</option>
                            </select>
                        </div>

                        <div id="customDateRange" class="form-row" style="display: none;">
                            <div class="form-col">
                                <label for="startDate" class="form-label">Date de début</label>
                                <input type="date" id="startDate" name="startDate" class="form-control">
                            </div>
                            <div class="form-col">
                                <label for="endDate" class="form-label">Date de fin</label>
                                <input type="date" id="endDate" name="endDate" class="form-control">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="format" class="form-label">Format</label>
                            <select id="format" name="format" class="form-control">
                                <option value="pdf">PDF</option>
                                <option value="excel">Excel</option>
                                <option value="csv">CSV</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Générer le rapport</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="section-header">
                <h2 class="section-title">Rapports récents</h2>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Nom du rapport</th>
                                    <th>Date de génération</th>
                                    <th>Type</th>
                                    <th>Format</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($recentReports)): ?>
                                    <?php foreach ($recentReports as $report): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($report['report_name']); ?></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($report['generated_at'])); ?></td>
                                            <td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $report['report_type']))); ?></td>
                                            <td><?php echo strtoupper($report['format']); ?></td>
                                            <td>
                                                <a href="download_report.php?token=<?php echo $report['token']; ?>"
                                                    class="btn btn-primary btn-sm">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center">Aucun rapport généré récemment</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Configuration du graphique des examens par mois
        const examsCtx = document.getElementById('examsChart').getContext('2d');
        const examsChart = new Chart(examsCtx, {
            type: 'bar',
            data: {
                labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'],
                datasets: [{
                    label: 'Nombre d\'examens',
                    data: [12, 19, 15, 25, 22, 30, 18, 15, 20, 25, 28, 30],
                    backgroundColor: 'rgba(99, 102, 241, 0.6)',
                    borderColor: 'rgba(99, 102, 241, 1)',
                    borderWidth: 1
                }]
            },
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
                }
            }
        });



        // Afficher/masquer les dates personnalisées
        document.getElementById('dateRange').addEventListener('change', function() {
            const customDateRange = document.getElementById('customDateRange');
            if (this.value === 'custom') {
                customDateRange.style.display = 'flex';
            } else {
                customDateRange.style.display = 'none';
            }
        });

        // Soumission du formulaire de rapport
        document.getElementById('reportForm').addEventListener('submit', function(e) {
            e.preventDefault();
            alert('Le rapport est en cours de génération et sera disponible dans quelques instants.');
        });
    });

    // Helper function for incident badges
    function getIncidentBadgeClass(type) {
        switch (type) {
            case 'face_detection':
                return 'danger';
            case 'eye_tracking':
                return 'warning';
            case 'audio':
                return 'info';
            case 'screen':
                return 'secondary';
            default:
                return 'primary';
        }
    }
</script>

<?php
// Helper function for incident badges
function getIncidentBadgeClass($type)
{
    switch ($type) {
        case 'face_detection':
            return 'danger';
        case 'eye_tracking':
            return 'warning';
        case 'audio':
            return 'info';
        case 'screen':
            return 'secondary';
        default:
            return 'primary';
    }
}
?>

<?php include 'includes/footer.php'; ?>
<?php

// Récupérer les scores moyens par matière depuis la base de données
$avgScoresBySubject = $conn->query("
    SELECT 
        s.name as subject_name,
        AVG(er.score) as avg_score,
        COUNT(er.id) as exam_count
    FROM exam_results er
    JOIN exams e ON er.exam_id = e.id
    JOIN subjects s ON e.subject = s.id
    WHERE er.status = 'completed'
    GROUP BY s.name
    ORDER BY avg_score DESC
");

// Préparer les données pour le graphique
$subjectLabels = [];
$subjectScores = [];
$subjectColors = [];

// Couleurs pour les différentes matières
$colors = [
    'rgba(75, 192, 192, 0.6)',  // Mathématiques
    'rgba(54, 162, 235, 0.6)',   // Physique
    'rgba(255, 206, 86, 0.6)',   // Chimie
    'rgba(75, 192, 192, 0.6)',   // Biologie
    'rgba(153, 102, 255, 0.6)'   // Informatique
];

$colorIndex = 0;

while ($row = $avgScoresBySubject->fetch_assoc()) {
    $subjectLabels[] = $row['subject_name'];
    $subjectScores[] = round($row['avg_score'], 1);
    $subjectColors[] = $colors[$colorIndex % count($colors)];
    $colorIndex++;
}

// Convertir en JSON pour JavaScript
$subjectLabelsJson = json_encode($subjectLabels);
$subjectScoresJson = json_encode($subjectScores);
$subjectColorsJson = json_encode($subjectColors);
// Dans la requête SQL, ajoutez COUNT(er.id) as exam_count
// Puis dans le PHP après la boucle:
$subjectCountsJson = json_encode(array_column($avgScoresBySubject->fetch_all(MYSQLI_ASSOC), 'exam_count'));
?>

<script>
    // Configuration du graphique des scores moyens par matière
    const scoresCtx = document.getElementById('scoresChart').getContext('2d');
    const scoresChart = new Chart(scoresCtx, {
        type: 'bar',
        data: {
            labels: <?php echo $subjectLabelsJson; ?>,
            datasets: [{
                label: 'Score moyen (%)',
                data: <?php echo $subjectScoresJson; ?>,
                backgroundColor: <?php echo $subjectColorsJson; ?>,
                borderColor: <?php echo str_replace('0.6', '1', $subjectColorsJson); ?>,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y', // Pour un graphique horizontal
            scales: {
                x: {
                    beginAtZero: true,
                    max: 100,
                    title: {
                        display: true,
                        text: 'Score moyen (%)'
                    }
                },
                y: {
                    title: {
                        display: true,
                        text: 'Matières'
                    }
                }
            },
            // Dans les options du graphique:
            plugins: {
                tooltip: {
                    callbacks: {
                        afterLabel: function(context) {
                            const examCounts = <?php echo $subjectCountsJson; ?>;
                            return `Nombre d'examens: ${examCounts[context.dataIndex]}`;
                        }
                    }
                }
            }
        }
    });
</script>

<?php   // Récupérer les données des examens groupés par mois et statut
$examsByStatus = $conn->query("
    SELECT 
        MONTH(created_at) as month,
        YEAR(created_at) as year,
        status,
        COUNT(*) as count
    FROM exams
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY YEAR(created_at), MONTH(created_at), status
    ORDER BY year, month, status
");

// Préparer les données pour le graphique
$months = [];
$statusData = [
    'draft' => [],
    'published' => [],
    'scheduled' => [],
    'completed' => []
];

// Initialiser les données
while ($row = $examsByStatus->fetch_assoc()) {
    $monthYear = date('M Y', mktime(0, 0, 0, $row['month'], 1, $row['year']));

    if (!in_array($monthYear, $months)) {
        $months[] = $monthYear;
    }

    $statusData[$row['status']][$monthYear] = $row['count'];
}

// Remplir les données manquantes avec 0
foreach ($statusData as $status => $data) {
    foreach ($months as $month) {
        if (!isset($data[$month])) {
            $statusData[$status][$month] = 0;
        }
    }
    // Réorganiser les données dans l'ordre des mois
    ksort($statusData[$status]);
}

// Convertir en JSON pour JavaScript
$monthsJson = json_encode($months);
$draftDataJson = json_encode(array_values($statusData['draft']));
$publishedDataJson = json_encode(array_values($statusData['published']));
$scheduledDataJson = json_encode(array_values($statusData['scheduled']));
$completedDataJson = json_encode(array_values($statusData['completed']));
?>
<script>
    // Configuration du graphique des examens par mois et statut
    const examsCtx = document.getElementById('examsChart').getContext('2d');
    const examsChart = new Chart(examsCtx, {
        type: 'bar',
        data: {
            labels: <?php echo $monthsJson; ?>,
            datasets: [{
                    label: 'Brouillons',
                    data: <?php echo $draftDataJson; ?>,
                    backgroundColor: 'rgba(201, 203, 207, 0.6)',
                    borderColor: 'rgba(201, 203, 207, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Publiés',
                    data: <?php echo $publishedDataJson; ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.6)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Planifiés',
                    data: <?php echo $scheduledDataJson; ?>,
                    backgroundColor: 'rgba(255, 206, 86, 0.6)',
                    borderColor: 'rgba(255, 206, 86, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Terminés',
                    data: <?php echo $completedDataJson; ?>,
                    backgroundColor: 'rgba(75, 192, 192, 0.6)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    stacked: false, // Désactive l'empilement
                },
                y: {
                    stacked: false, // Désactive l'empilement
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            },

            plugins: {
                tooltip: {
                    callbacks: {
                        afterLabel: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            return `Total: ${total}`;
                        }
                    }
                },
                legend: {
                    position: 'bottom',
                }
            }
        }
    });
</script>
<script>
    // Gestion du modal d'incident
    const viewIncidentBtns = document.querySelectorAll('.view-incident');
    const incidentModal = document.getElementById('incidentModal');
    const closeModalBtns = document.querySelectorAll('.close-modal');

    if (viewIncidentBtns.length > 0 && incidentModal && closeModalBtns.length > 0) {
        viewIncidentBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                // Récupérer les données de l'incident depuis les attributs data-*
                const exam = this.getAttribute('data-exam');
                const student = this.getAttribute('data-student');
                const type = this.getAttribute('data-type');
                const date = this.getAttribute('data-date');
                const details = this.getAttribute('data-details');
                const imagePath = this.getAttribute('data-image');



                // Remplir le modal avec les données
                document.getElementById('incident-exam').textContent = exam;
                document.getElementById('incident-student').textContent = student;
                document.getElementById('incident-type').textContent = type;
                document.getElementById('incident-date').textContent = date;
                document.getElementById('incident-description').textContent = details;
                document.getElementById('incident-image').src = imagePath;


                // Afficher le modal
                incidentModal.classList.add('show');
            });
        });

        closeModalBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                incidentModal.classList.remove('show');
            });
        });

        window.addEventListener('click', function(event) {
            if (event.target == incidentModal) {
                incidentModal.classList.remove('show');
            }
        });

        // Bouton d'examen d'incident
        const reviewIncidentBtn = document.getElementById('review-incident');
        if (reviewIncidentBtn) {
            reviewIncidentBtn.addEventListener('click', function() {
                alert('Redirection vers la page de révision détaillée de l\'incident...');
                // Ici, vous redirigeriez normalement vers une page de révision détaillée
            });
        }
    }
</script>

<style>
    /* Modal */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0, 0, 0, 0.5);
    }

    .modal.show {
        display: block;
    }

    .modal-dialog {
        margin: 10% auto;
        width: 90%;
        max-width: 600px;
    }

    .modal-content {
        background-color: #1a2357;
        ;
        border-radius: var(--card-border-radius);
        box-shadow: var(--box-shadow);
        animation: modalFadeIn 0.3s;
        border-radius: 20px;
    }

    @keyframes modalFadeIn {
        from {
            opacity: 0;
            transform: translateY(-50px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem 1.5rem;
        border-bottom: 1px solid var(--border-color);
    }

    .modal-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--dark-color);
        margin: 0;
    }

    .close-modal {
        background: none;
        border: none;
        font-size: 1.5rem;
        color: var(--secondary-color);
        cursor: pointer;
    }

    .modal-body {
        padding: 1.5rem;
    }

    .modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 0.75rem;
        padding: 1rem 1.5rem;
        border-top: 1px solid var(--border-color);
    }
</style>

<script>
   document.getElementById('reportForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Récupérer les valeurs du formulaire
    const formData = new FormData(this);
    const reportType = document.getElementById('reportType').value;
    const dateRange = document.getElementById('dateRange').value;
    const format = document.getElementById('format').value;
    const token = document.getElementById('report_token').value;
    
    let startDate, endDate;
    
    if (dateRange === 'custom') {
        startDate = document.getElementById('startDate').value;
        endDate = document.getElementById('endDate').value;
    } else {
        // Calculer les dates en fonction de la période sélectionnée
        const today = new Date();
        endDate = formatDate(today);
        
        const startDateObj = new Date(today);
        switch (dateRange) {
            case '7days':
                startDateObj.setDate(today.getDate() - 7);
                break;
            case '30days':
                startDateObj.setDate(today.getDate() - 30);
                break;
            case '90days':
                startDateObj.setDate(today.getDate() - 90);
                break;
            case 'year':
                startDateObj.setFullYear(today.getFullYear() - 1);
                break;
        }
        startDate = formatDate(startDateObj);
    }
    
    // Enregistrer le rapport via AJAX avant de le générer
    fetch('save_report.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Construire l'URL de génération de rapport
            const url = `generate_report.php?type=${reportType}&start=${startDate}&end=${endDate}&format=${format}&token=${token}`;
            
            // Télécharger le rapport
            window.location.href = url;
        } else {
            alert('Erreur lors de l\'enregistrement du rapport: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Une erreur est survenue');
    });
    
    // Fonction helper pour formater la date au format YYYY-MM-DD
    function formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }
});
</script>