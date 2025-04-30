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

// Récupérer les examens qui ont des questions à réponse libre à noter
$sql = "
    SELECT 
        e.id,
        e.title,
        e.subject,
        e.status,
        COUNT(er.id) as total_submissions,
        SUM(CASE WHEN er.is_graded = 0 THEN 1 ELSE 0 END) as pending_grades
    FROM exams e
    JOIN exam_results er ON e.id = er.exam_id
    WHERE e.teacher_id = $teacherId 
    AND e.has_essay = 1
    AND er.status = 'completed'
    GROUP BY e.id
    HAVING pending_grades > 0
    ORDER BY e.created_at DESC
";

$examsToGrade = $conn->query($sql);

// Récupérer les examens récemment notés
$recentlyGraded = $conn->query("
    SELECT 
        e.id,
        e.title,
        e.subject,
        u.username,
        u.first_name,
        u.last_name,
        er.score,
        er.graded_at
    FROM exam_results er
    JOIN exams e ON er.exam_id = e.id
    JOIN users u ON er.user_id = u.id
    WHERE e.teacher_id = $teacherId 
    AND er.is_graded = 1
    ORDER BY er.graded_at DESC
    LIMIT 5
");

$pageTitle = "Noter les examens";
include '../includes/header.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> | ExamSafe</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/teacher.css">
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <img src="../assets/images/logo.png" alt="ExamSafe Logo">
                    <span>ExamSafe</span>
                </div>
                <button class="menu-toggle" id="menu-toggle">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
            
            <nav class="sidebar-nav">
                <ul>
                    <li class="nav-item">
                        <a href="index.php" class="nav-link">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Tableau de bord</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="create-exam.php" class="nav-link">
                            <i class="fas fa-plus-circle"></i>
                            <span>Créer un examen</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="manage-exams.php" class="nav-link">
                            <i class="fas fa-file-alt"></i>
                            <span>Gérer les examens</span>
                        </a>
                    </li>
                    <li class="nav-item active">
                        <a href="grade-exams.php" class="nav-link">
                            <i class="fas fa-check-circle"></i>
                            <span>Noter les examens</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="reports.php" class="nav-link">
                            <i class="fas fa-chart-bar"></i>
                            <span>Rapports</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../profile.php" class="nav-link">
                            <i class="fas fa-user"></i>
                            <span>Mon profil</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../logout.php" class="nav-link">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Déconnexion</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="header">
                <div class="header-left">
                    <button class="sidebar-toggle" id="sidebar-toggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1 class="page-title">Noter les examens</h1>
                </div>
                
                <div class="header-right">
                    <div class="search-box">
                        <input type="text" placeholder="Rechercher...">
                        <i class="fas fa-search"></i>
                    </div>
                    
                    <div class="notifications">
                        <button class="notification-btn">
                            <i class="fas fa-bell"></i>
                            <span class="badge">3</span>
                        </button>
                    </div>
                    
                    <div class="user-profile">
                        <img src="../assets/images/avatar.png" alt="Avatar" class="avatar">
                        <div class="user-info">
                            <span class="user-name"><?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?></span>
                            <span class="user-role">Enseignant</span>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Grade Exams Content -->
            <div class="dashboard">
                <div class="card table-card">
                    <div class="card-header">
                        <h2 class="card-title">Examens à noter</h2>
                    </div>
                    <div class="card-body">
                        <?php if ($examsToGrade->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Titre</th>
                                            <th>Matière</th>
                                            <th>Statut</th>
                                            <th>Soumissions totales</th>
                                            <th>En attente de notation</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($exam = $examsToGrade->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <div class="table-user">
                                                        <div class="user-info">
                                                            <span class="user-name"><?php echo htmlspecialchars($exam['title']); ?></span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($exam['subject']); ?></td>
                                                <td>
                                                    <span class="status-badge <?php echo $exam['status']; ?>">
                                                        <?php echo ucfirst($exam['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $exam['total_submissions']; ?></td>
                                                <td>
                                                    <span class="badge badge-warning"><?php echo $exam['pending_grades']; ?></span>
                                                </td>
                                                <td>
                                                    <a href="grade-exam-submissions.php?id=<?php echo $exam['id']; ?>" class="btn btn-primary btn-sm">
                                                        Noter
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-icon">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <h3>Aucun examen à noter</h3>
                                <p>Tous les examens ont été notés ou aucun examen ne contient de questions à réponse libre.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card table-card">
                    <div class="card-header">
                        <h2 class="card-title">Examens récemment notés</h2>
                    </div>
                    <div class="card-body">
                        <?php if ($recentlyGraded->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Examen</th>
                                            <th>Étudiant</th>
                                            <th>Score</th>
                                            <th>Date de notation</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($result = $recentlyGraded->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <div class="table-user">
                                                        <div class="user-info">
                                                            <span class="user-name"><?php echo htmlspecialchars($result['title']); ?></span>
                                                            <span class="user-date"><?php echo htmlspecialchars($result['subject']); ?></span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($result['first_name'] . ' ' . $result['last_name']); ?></td>
                                                <td><?php echo $result['score']; ?>%</td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($result['graded_at'])); ?></td>
                                                <td>
                                                    <a href="view-submission.php?exam_id=<?php echo $result['id']; ?>&user_id=<?php echo $result['user_id']; ?>" class="btn btn-info btn-sm">
                                                        <i class="fas fa-eye"></i> Voir
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-icon">
                                    <i class="fas fa-history"></i>
                                </div>
                                <h3>Aucun examen récemment noté</h3>
                                <p>Vous n'avez pas encore noté d'examens.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Exemple de notation</h2>
                    </div>
                    <div class="card-body">
                        <div class="grading-container">
                            <div class="question-panel">
                                <h3>Question</h3>
                                <div class="question-text">
                                    Expliquez les principes fondamentaux de la programmation orientée objet et donnez des exemples concrets de leur application.
                                </div>
                                <div class="question-info">
                                    <p><strong>Type:</strong> Question à réponse libre</p>
                                    <p><strong>Points maximum:</strong> 10</p>
                                </div>
                            </div>
                            
                            <div class="answer-panel">
                                <h3>Réponse de l'étudiant</h3>
                                <div class="student-answer">
                                    <p>La programmation orientée objet (POO) est un paradigme de programmation basé sur le concept d'objets qui contiennent des données et du  est un paradigme de programmation basé sur le concept d'objets qui contiennent des données et du code. Les principes fondamentaux sont :

1. <strong>Encapsulation</strong> : Regrouper les données et les méthodes qui les manipulent. Par exemple, une classe "Compte" qui encapsule le solde et les méthodes pour déposer/retirer de l'argent.

2. <strong>Héritage</strong> : Permet à une classe d'hériter des propriétés d'une autre classe. Par exemple, une classe "CompteCourant" qui hérite de "Compte" mais ajoute des fonctionnalités spécifiques.

3. <strong>Polymorphisme</strong> : Capacité d'un objet à prendre plusieurs formes. Par exemple, une méthode "calculerIntérêts" qui se comporte différemment selon qu'elle est appelée sur un "CompteCourant" ou un "CompteEpargne".
                                </div>
                                
                                <div class="grading-form">
                                    <h3>Notation</h3>
                                    <div class="score-input">
                                        <label for="score">Score:</label>
                                        <input type="number" id="score" class="form-control" min="0" max="10" value="8">
                                        <span class="max-score">/ 10</span>
                                    </div>
                                    <div class="form-group">
                                        <label for="feedback">Commentaire:</label>
                                        <textarea id="feedback" class="form-control" rows="4">Bonne explication des concepts fondamentaux de la POO. Les exemples sont pertinents. Il manque cependant une mention de l'abstraction et des interfaces qui sont aussi des concepts importants en POO.</textarea>
                                    </div>
                                    <div class="form-buttons">
                                        <button class="btn btn-primary">Enregistrer</button>
                                        <button class="btn btn-secondary">Suivant</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Toggle sidebar
        const menuToggle = document.getElementById('menu-toggle');
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const appContainer = document.querySelector('.app-container');
        
        menuToggle.addEventListener('click', function() {
            appContainer.classList.toggle('sidebar-collapsed');
        });
        
        sidebarToggle.addEventListener('click', function() {
            appContainer.classList.toggle('sidebar-collapsed');
        });
    });
    </script>
</body>
</html>
