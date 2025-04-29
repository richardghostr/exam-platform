<?php
// Inclure les fichiers de configuration et fonctions
include_once '../includes/config.php';
include_once '../includes/functions.php';

// Vérifier si l'utilisateur est connecté et a le rôle admin
require_login('../login.php');
require_role('admin', '../index.php');

// Connexion à la base de données
include_once '../includes/db.php';

$success = '';
$error = '';

// Traiter le formulaire de création d'examen
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $duration = $_POST['duration'] ?? 0;
    $passing_score = $_POST['passing_score'] ?? 0;
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $status = $_POST['status'] ?? 'draft';
    $instructions = $_POST['instructions'] ?? '';
    $proctoring_level = $_POST['proctoring_level'] ?? 'standard';
    
    // Validation des données
    if (empty($title) || empty($description) || empty($duration) || empty($passing_score)) {
        $error = 'Veuillez remplir tous les champs obligatoires.';
    } else {
        // Insérer l'examen dans la base de données
        $stmt = $conn->prepare("
            INSERT INTO exams (
                title, description, duration, passing_score, start_date, end_date, 
                status, instructions, proctoring_level, created_by, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->bind_param(
            "ssidsssssi",
            $title, $description, $duration, $passing_score, $start_date, $end_date,
            $status, $instructions, $proctoring_level, $_SESSION['user_id']
        );
        
        if ($stmt->execute()) {
            $exam_id = $stmt->insert_id;
            $success = 'Examen créé avec succès. Vous pouvez maintenant ajouter des questions.';
            
            // Rediriger vers la page d'ajout de questions
            redirect("edit-exam.php?id=$exam_id&section=questions");
        } else {
            $error = 'Une erreur est survenue lors de la création de l\'examen : ' . $conn->error;
        }
        
        $stmt->close();
    }
}

// Récupérer les catégories d'examens
$categories_query = "SELECT * FROM exam_categories ORDER BY name";
$categories_result = $conn->query($categories_query);
$categories = $categories_result->fetch_all(MYSQLI_ASSOC);

// Fermer la connexion à la base de données
$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer un examen - ExamSafe</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
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
                    <h1>Créer un nouvel examen</h1>
                    <nav class="breadcrumb">
                        <a href="index.php">Tableau de bord</a> /
                        <a href="manage-exams.php">Examens</a> /
                        <span>Créer un examen</span>
                    </nav>
                </div>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header">
                        <h2>Informations de l'examen</h2>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" class="form">
                            <div class="form-row">
                                <div class="form-group col-md-8">
                                    <label for="title">Titre de l'examen *</label>
                                    <input type="text" id="title" name="title" required>
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="status">Statut</label>
                                    <select id="status" name="status">
                                        <option value="draft">Brouillon</option>
                                        <option value="published">Publié</option>
                                        <option value="archived">Archivé</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="description">Description *</label>
                                <textarea id="description" name="description" rows="3" required></textarea>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="category">Catégorie</label>
                                    <select id="category" name="category">
                                        <option value="">Sélectionner une catégorie</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="duration">Durée (minutes) *</label>
                                    <input type="number" id="duration" name="duration" min="1" required>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="start_date">Date de début</label>
                                    <input type="datetime-local" id="start_date" name="start_date">
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="end_date">Date de fin</label>
                                    <input type="datetime-local" id="end_date" name="end_date">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="passing_score">Score de réussite (%) *</label>
                                    <input type="number" id="passing_score" name="passing_score" min="0" max="100" required>
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="proctoring_level">Niveau de surveillance</label>
                                    <select id="proctoring_level" name="proctoring_level">
                                        <option value="none">Aucune surveillance</option>
                                        <option value="basic">Surveillance de base</option>
                                        <option value="standard" selected>Surveillance standard</option>
                                        <option value="advanced">Surveillance avancée</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="instructions">Instructions pour les étudiants</label>
                                <textarea id="instructions" name="instructions" rows="5"></textarea>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">Créer l'examen</button>
                                <a href="manage-exams.php" class="btn btn-outline">Annuler</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="../assets/js/admin.js"></script>
</body>
</html>
