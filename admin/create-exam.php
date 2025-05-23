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
    $startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $endDate = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    $isProctored = isset($_POST['is_proctored']) ? 1 : 0;
    $status = $_POST['status'];
    
    // Validation
    if (empty($title) || empty($subject) || $duration <= 0 || $passingScore <= 0) {
        $errorMessage = "Veuillez remplir tous les champs obligatoires.";
    } else {
        try {
            // Validation des dates et formatage
            if (!empty($startDate)) {
                // Vérifier si la date est au format valide
                $startDateObj = new DateTime($startDate);
                $startDate = $startDateObj->format('Y-m-d H:i:s');
            }
            
            if (!empty($endDate)) {
                // Vérifier si la date est au format valide
                $endDateObj = new DateTime($endDate);
                $endDate = $endDateObj->format('Y-m-d H:i:s');
            }
            
            // Préparation de la requête selon que les dates sont fournies ou non
            if ($startDate && $endDate) {
                $stmt = $conn->prepare("INSERT INTO exams (title, description, subject,end_date, duration, passing_score, start_date, is_proctored, status,created_by,created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param("ssssiisisi", $title, $description, $subject,$endDate, $duration, $passingScore,$startDate, $isProctored, $status, $_SESSION['user_id']);
            } elseif ($startDate && !$endDate) {
                $stmt = $conn->prepare("INSERT INTO exams (title, description, subject, duration, passing_score, start_date, is_proctored, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param("sssiisisi", $title, $description, $subject, $duration, $passingScore, $startDate, $isProctored, $status, $_SESSION['user_id']);
            } elseif (!$startDate && $endDate) {
                $stmt = $conn->prepare("INSERT INTO exams (title, description, subject, duration, passing_score, end_date, is_proctored, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param("sssiisisi", $title, $description, $subject, $duration, $passingScore, $endDate, $isProctored, $status, $_SESSION['user_id']);
            } else {
                $stmt = $conn->prepare("INSERT INTO exams (title, description, subject, duration, passing_score, is_proctored, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param("sssiisis", $title, $description, $subject, $duration, $passingScore, $isProctored, $status, $_SESSION['user_id']);
            }
            
            if ($stmt->execute()) {
                $examId = $conn->insert_id;
                $successMessage = "L'examen a été créé avec succès. Vous pouvez maintenant ajouter des questions.";
                
                // Rediriger vers la page d'ajout de questions
                header("Location: add-questions.php?exam_id=$examId");
                exit();
            } else {
                $errorMessage = "Une erreur s'est produite lors de la création de l'examen. Veuillez réessayer.";
            }
        } catch (Exception $e) {
            $errorMessage = "Erreur: " . $e->getMessage();
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
                                    <small class="form-text text-muted">Format: YYYY-MM-DD HH:MM</small>
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="end_date" class="form-label">Date de fin</label>
                                    <input type="datetime-local" id="end_date" name="end_date" class="form-control">
                                    <small class="form-text text-muted">Format: YYYY-MM-DD HH:MM</small>
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
    // Validation côté client des dates
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    
    // S'assurer que la date de fin est postérieure à la date de début
    endDateInput.addEventListener('change', function() {
        if (startDateInput.value && endDateInput.value) {
            const startDate = new Date(startDateInput.value);
            const endDate = new Date(endDateInput.value);
            
            if (endDate <= startDate) {
                alert('La date de fin doit être postérieure à la date de début.');
                endDateInput.value = '';
            }
        }
    });
    
    // S'assurer que la date de début est antérieure à la date de fin
    startDateInput.addEventListener('change', function() {
        if (startDateInput.value && endDateInput.value) {
            const startDate = new Date(startDateInput.value);
            const endDate = new Date(endDateInput.value);
            
            if (endDate <= startDate) {
                alert('La date de début doit être antérieure à la date de fin.');
                startDateInput.value = '';
            }
        }
    });
});
</script>