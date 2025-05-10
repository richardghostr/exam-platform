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

// Récupérer les statistiques générales
$totalStudents = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'student'")->fetch_assoc()['count'];
$totalTeachers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'teacher'")->fetch_assoc()['count'];
$totalExams = $conn->query("SELECT COUNT(*) as count FROM exams")->fetch_assoc()['count'];
// $totalCompletedExams = $conn->query("SELECT COUNT(*) as count FROM exam_results WHERE status = 'completed'")->fetch_assoc()['count'];

// Récupérer les examens les plus populaires (avec le plus d'étudiants inscrits)
$popularExams = $conn->query("
    SELECT 
        e.id,
        e.title,
        COUNT(ee.user_id) as student_count,
        s.name as subject_name
    FROM exams e
    LEFT JOIN exam_attempts ee ON e.id = ee.exam_id
    LEFT JOIN subjects s ON e.subject = s.id
    GROUP BY e.id
    ORDER BY student_count DESC
    LIMIT 4
");

// Icônes par matière (vous pouvez personnaliser cela)
$subjectIcons = [
    'Mathématiques' => 'fa-calculator',
    'Informatique' => 'fa-laptop-code',
    'Physique' => 'fa-atom',
    'Chimie' => 'fa-flask',
    'Biologie' => 'fa-dna'
];
$up = $conn->prepare("UPDATE `exam_results` er JOIN `exam_attempts` ea ON er.id=ea.id SET er.score =ea.score,er.points_earned=ea.score WHERE er.exam_id=ea.exam_id AND er.user_id=ea.user_id AND er.created_at=ea.created_at");
$up->execute();
// // Récupérer les examens récents
$recentExams = $conn->query("
    SELECT 
        e.id,
        e.title,
        e.subject,
        e.status,
        e.created_at,
        u.username as teacher_name
    FROM exams e
    JOIN users u ON e.teacher_id = u.id
    ORDER BY e.created_at DESC
    LIMIT 5
");

// Récupérer les utilisateurs récents
$recentUsers = $conn->query("
    SELECT 
        id,
        username,
        email,
        role,
        created_at
    FROM users
    ORDER BY created_at DESC
    LIMIT 5
");

$pageTitle = "Tableau de bord";
include 'includes/header.php';
?>

<h1 class="page-title" style="margin-bottom: 20px; margin-left: 5px">Tableau de bord</h1>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-header">
            <h3 class="stat-title">Revenus</h3>
            <a href="reports.php" class="text-primary">Voir rapport</a>
        </div>
        <div class="stat-value">7.852.000</div>
        <div class="stat-trend trend-up">
            <i class="fas fa-arrow-up trend-icon"></i> 2.1% vs semaine dernière
        </div>
        <div class="stat-period">Ventes du 1-12 Mars, 2025</div>
    </div>

    <div class="stat-card" >
        <div class="stat-header">
            <h3 class="stat-title">Étudiants</h3>
            <a href="users.php?role=student" id="et" class="text-primary">Voir tous</a>
        </div>
        <div class="stat-value"><?php echo $totalStudents; ?></div>
        <div class="stat-trend trend-up">
            <i class="fas fa-arrow-up trend-icon"></i> 5.2% vs mois dernier
        </div>
        <div class="stat-period">Total des étudiants inscrits</div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <h3 class="stat-title">Enseignants</h3>
            <a href="users.php?role=teacher" class="text-primary">Voir tous</a>
        </div>
        <div class="stat-value"><?php echo $totalTeachers; ?></div>
        <div class="stat-trend trend-up">
            <i class="fas fa-arrow-up trend-icon"></i> 3.1% vs mois dernier
        </div>
        <div class="stat-period">Total des enseignants inscrits</div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <h3 class="stat-title">Examens</h3>
            <a href="manage-exams.php" class="text-primary">Voir tous</a>
        </div>
        <div class="stat-value"><?php echo $totalExams; ?></div>
        <div class="stat-trend trend-up">
            <i class="fas fa-arrow-up trend-icon"></i> 7.8% vs mois dernier
        </div>
        <div class="stat-period">Total des examens créés</div>
    </div>
</div>

<div class="section">
    <div class="section-header">
        <h2 class="section-title">Horaires des examens</h2>
        <div class="section-actions">
            <a href="users.php" class="text-primary">
                <h2 class="section-title">Utilisateurs Journaliers</h2>
            </a>
        </div>
    </div>

    <div class="card" style="display: flex;justify-content:space-between">

        <div class="card-body" style="width: 48%;">
            <div class="chart-container">
                <canvas id="examTimeChart"></canvas>
            </div>

            <div class="d-flex justify-content-center gap-20 mt-20">
                <div class="d-flex align-items-center gap-10">
                    <span class="badge badge-primary"></span>
                    <span>Matin (28%)</span>
                </div>
                <div class="d-flex align-items-center gap-10">
                    <span class="badge badge-success"></span>
                    <span>Après-midi (40%)</span>
                </div>
                <div class="d-flex align-items-center gap-10">
                    <span class="badge badge-warning"></span>
                    <span>Soir (32%)</span>
                </div>
            </div>
        </div>
        <div class="card-body" style="width: 48%;">
            <div class="chart-container">
                <canvas id="studentChart"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="section">
    <div class="section-header">
        <h2 class="section-title">Examens récents</h2>
        <div class="section-actions">
            <a href="manage-exams.php" class="btn btn-primary btn-sm">Voir tous</a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Titre</th>
                            <th>Matière</th>
                            <th>Enseignant</th>
                            <th>Statut</th>
                            <th>Date de création</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recentExams->num_rows > 0): ?>
                            <?php while ($exam = $recentExams->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($exam['title']); ?></td>
                                    <td><?php echo htmlspecialchars($exam['subject']); ?></td>
                                    <td><?php echo htmlspecialchars($exam['teacher_name']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo getStatusClass($exam['status']); ?>">
                                            <?php echo ucfirst($exam['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($exam['created_at'])); ?></td>
                                    <td>
                                        <a href="view-exam.php?id=<?php echo $exam['id']; ?>" class="btn btn-info btn-sm">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">Aucun examen récent</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="section">
    <div class="row">
        <div class="col-md-6">
            <div class="section-header">
                <h2 class="section-title">Évaluation</h2>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-center">
                        <div class="donut-chart">
                            <div class="donut-segment" style="background-color: rgba(99, 102, 241, 0.7);"></div>
                            <div class="donut-label">
                                <div class="donut-percent">85%</div>
                                <div class="donut-title">Satisfaction</div>
                            </div>
                        </div>

                        <div class="donut-chart">
                            <div class="donut-segment" style="background-color: rgba(16, 185, 129, 0.7);"></div>
                            <div class="donut-label">
                                <div class="donut-percent">92%</div>
                                <div class="donut-title">Fiabilité</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="section-header">
                <h2 class="section-title">Examens les plus populaires</h2>
            </div>

            <div class="card">
                <div class="card-body">
                    <ul class="popular-exams-list">
                        <?php if ($popularExams->num_rows > 0): ?>
                            <?php while ($exam = $popularExams->fetch_assoc()): ?>
                                <li class="d-flex justify-content-between align-items-center mb-10">
                                    <div class="d-flex align-items-center gap-10">
                                        <div class="exam-icon">
                                            <i class="fas <?php echo $subjectIcons[$exam['subject_name']] ?? 'fa-book'; ?>"></i>
                                        </div>
                                        <span><?php echo htmlspecialchars($exam['title']); ?></span>
                                    </div>
                                    <span class="badge badge-primary">
                                        <?php echo $exam['student_count']; ?> étudiant<?php echo $exam['student_count'] > 1 ? 's' : ''; ?>
                                    </span>
                                </li>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <li class="text-center">Aucun examen avec des inscriptions</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Graphique pour les horaires d'examens
        const examTimeCtx = document.getElementById('examTimeChart').getContext('2d');
        const examTimeChart = new Chart(examTimeCtx, {
            type: 'doughnut',
            data: {
                labels: ['Matin', 'Après-midi', 'Soir'],
                datasets: [{
                    data: [28, 40, 32],
                    backgroundColor: [
                        'rgba(99, 102, 241, 0.7)',
                        'rgba(16, 185, 129, 0.7)',
                        'rgba(245, 158, 11, 0.7)'
                    ],
                    borderColor: [
                        'rgba(99, 102, 241, 1)',
                        'rgba(16, 185, 129, 1)',
                        'rgba(245, 158, 11, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                cutout: '70%'
            }
        });

        // Helper function for status badges
        function getStatusClass(status) {
            switch (status) {
                case 'active':
                    return 'success';
                case 'draft':
                    return 'warning';
                case 'completed':
                    return 'info';
                default:
                    return 'secondary';
            }
        }
    });
</script>

<?php
// Helper function for status badges
function getStatusClass($status)
{
    switch ($status) {
        case 'active':
            return 'success';
        case 'draft':
            return 'warning';
        case 'completed':
            return 'info';
        default:
            return 'secondary';
    }
}
?>

<?php include 'includes/footer.php'; ?>
<?php
// Récupérer les données d'inscription quotidiennes
$dailyRegistrations = $conn->query("
    SELECT 
        DATE(created_at) as registration_date,
        COUNT(*) as user_count,
        SUM(CASE WHEN role = 'student' THEN 1 ELSE 0 END) as student_count,
        SUM(CASE WHEN role = 'teacher' THEN 1 ELSE 0 END) as teacher_count
    FROM users
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY registration_date ASC
");

// Préparer les données pour le graphique
$labels = [];
$totalData = [];
$studentData = [];
$teacherData = [];

while ($row = $dailyRegistrations->fetch_assoc()) {
    $labels[] = date('d M', strtotime($row['registration_date']));
    $totalData[] = $row['user_count'];
    $studentData[] = $row['student_count'];
    $teacherData[] = $row['teacher_count'];
}
?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const studentCtx = document.getElementById('studentChart').getContext('2d');

        const studentChart = new Chart(studentCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [{
                        label: 'Total des inscriptions',
                        data: <?php echo json_encode($totalData); ?>,
                        backgroundColor: 'rgba(99, 102, 241, 0.2)',
                        borderColor: 'rgba(99, 102, 241, 1)',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Étudiants',
                        data: <?php echo json_encode($studentData); ?>,
                        backgroundColor: 'rgba(16, 185, 129, 0.2)',
                        borderColor: 'rgba(16, 185, 129, 1)',
                        borderWidth: 2,
                        tension: 0.4
                    },
                    {
                        label: 'Enseignants',
                        data: <?php echo json_encode($teacherData); ?>,
                        backgroundColor: 'rgba(245, 158, 11, 0.2)',
                        borderColor: 'rgba(245, 158, 11, 1)',
                        borderWidth: 2,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            boxWidth: 12,
                            padding: 20
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        },
                        grid: {
                            drawBorder: false,
                        },

                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                interaction: {
                    mode: 'nearest',
                    axis: 'x',
                    intersect: false
                }
            }
        });
    }); // Dans votre JavaScript
    fetch('api/daily-registrations.php')
        .then(response => response.json())
        .then(data => {
            // Mettre à jour le graphique avec les nouvelles données
            studentChart.data.labels = data.labels;
            studentChart.data.datasets[0].data = data.total;
            studentChart.data.datasets[1].data = data.students;
            studentChart.data.datasets[2].data = data.teachers;
            studentChart.update();
        });
</script>