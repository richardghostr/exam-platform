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

// Traitement du formulaire de création d'examen
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $subject = trim($_POST['subject']);
    $duration = (int)$_POST['duration'];
    $passingScore = (int)$_POST['passing_score'];
    $startDate = $_POST['start_date'];
    $endDate = $_POST['end_date'];
    $isProctored = isset($_POST['is_proctored']) ? 1 : 0;
    $status = $_POST['status'];
    
    // Validation
    if (empty($title) || empty($subject) || $duration <= 0 || $passingScore <= 0) {
        $errorMessage = "Veuillez remplir tous les champs obligatoires.";
    } else {
        // Insérer l'examen dans la base de données
        $stmt = $conn->prepare("INSERT INTO exams (title, description, subject, duration, passing_score, start_date, end_date, is_proctored, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("sssiiisisi", $title, $description, $subject, $duration, $passingScore, $startDate, $endDate, $isProctored, $status, $_SESSION['user_id']);
        
        if ($stmt->execute()) {
            $examId = $conn->insert_id;
            $successMessage = "L'examen a été créé avec succès. Vous pouvez maintenant ajouter des questions.";
            
            // Rediriger vers la page d'ajout de questions
            header("Location: add-questions.php?exam_id=$examId");
            exit();
        } else {
            $errorMessage = "Une erreur s'est produite lors de la création de l'examen. Veuillez réessayer.";
        }
    }
}

// Récupérer la liste des matières
$subjects = [];
$stmt = $conn->prepare("SELECT * FROM subjects ORDER BY name");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $subjects[] = $row;
}

$pageTitle = "Créer un nouvel examen";
include 'includes/header.php';
?>

    
    <div class="card mb-20">
        <div class="admin-header">
            <div>
                <div class="page-path">
                    <a href="index.php">Dashboard</a>
                    <span class="separator">/</span>
                    <a href="manage-exams.php">Examens</a>
                    <span class="separator">/</span>
                    <span>Créer un examen</span>
                </div>
                <h1 class="page-title"><?php echo $pageTitle; ?></h1>
            </div>
            
        
        </div>
        
        <div class="main-content">
            <?php if (!empty($successMessage)): ?>
                <div class="alert alert-success">
                    <?php echo $successMessage; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($errorMessage)): ?>
                <div class="alert alert-danger">
                    <?php echo $errorMessage; ?>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Informations de l'examen</h2>
                </div>
                <div class="card-body">
                    <form method="post" action="" class="form">
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="title" class="form-label">Titre de l'examen *</label>
                                    <input type="text" id="title" name="title" class="form-control" required>
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="subject" class="form-label">Matière *</label>
                                    <select id="subject" name="subject" class="form-control" required>
                                        <option value="">Sélectionner une matière</option>
                                        <?php foreach ($subjects as $subject): ?>
                                            <option value="<?php echo $subject['id']; ?>"><?php echo $subject['name']; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="description" class="form-label">Description</label>
                            <textarea id="description" name="description" class="form-control" rows="4"></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="duration" class="form-label">Durée (minutes) *</label>
                                    <input type="number" id="duration" name="duration" class="form-control" min="1" value="60" required>
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="passing_score" class="form-label">Score de réussite (%) *</label>
                                    <input type="number" id="passing_score" name="passing_score" class="form-control" min="1" max="100" value="60" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="start_date" class="form-label">Date de début</label>
                                    <input type="datetime-local" id="start_date" name="start_date" class="form-control">
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="end_date" class="form-label">Date de fin</label>
                                    <input type="datetime-local" id="end_date" name="end_date" class="form-control">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="status" class="form-label">Statut *</label>
                                    <select id="status" name="status" class="form-control" required>
                                        <option value="draft">Brouillon</option>
                                        <option value="published">Publié</option>
                                        <option value="scheduled">Planifié</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label class="form-label d-block">Options</label>
                                    <div class="d-flex align-items-center mt-10">
                                        <label class="switch">
                                            <input type="checkbox" name="is_proctored" id="is_proctored" checked>
                                            <span class="slider"></span>
                                        </label>
                                        <span class="ml-10">Activer la surveillance</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card-footer">
                            <button type="button" class="btn btn-secondary" onclick="window.location.href='manage-exams.php'">Annuler</button>
                            <button type="submit" class="btn btn-primary">Créer l'examen</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>


<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialiser les datepickers et autres plugins JS si nécessaire
});
</script>

