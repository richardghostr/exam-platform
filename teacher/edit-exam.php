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
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: manage-exams.php');
    exit();
}

$examId = intval($_GET['id']);

// Vérifier si l'examen appartient à l'enseignant
$examQuery = $conn->query("SELECT * FROM exams WHERE id = $examId AND teacher_id = $teacherId");
if ($examQuery->num_rows === 0) {
    header('Location: manage-exams.php');
    exit();
}

$exam = $examQuery->fetch_assoc();

// Récupérer les classes/groupes disponibles
$classesQuery = $conn->query("SELECT * FROM classes ORDER BY name ASC");

// Récupérer les classes assignées à cet examen
$assignedClassesQuery = $conn->query("SELECT class_id FROM exam_classes WHERE exam_id = $examId");
$assignedClasses = [];
while ($row = $assignedClassesQuery->fetch_assoc()) {
    $assignedClasses[] = $row['class_id'];
}

// Traitement du formulaire de modification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $conn->real_escape_string($_POST['title']);
    $description = $conn->real_escape_string($_POST['description']);
    $subject = $conn->real_escape_string($_POST['subject']);
    $duration = intval($_POST['duration']);
    $passingScore = intval($_POST['passing_score']);
    $startDate = $conn->real_escape_string($_POST['start_date']);
    $endDate = $conn->real_escape_string($_POST['end_date']);
    $status = $conn->real_escape_string($_POST['status']);
    $proctoring = isset($_POST['proctoring']) ? 1 : 0;
    $randomizeQuestions = isset($_POST['randomize_questions']) ? 1 : 0;
    $randomizeQuestions = isset($_POST['randomize_questions']) ? 1 : 0;
    $showResults = isset($_POST['show_results']) ? 1 : 0;
    
    // Mettre à jour les informations de l'examen
    $updateExam = $conn->query("
        UPDATE exams SET 
            title = '$title', 
            description = '$description', 
            subject = '$subject', 
            duration = $duration, 
            passing_score = $passingScore, 
            start_date = '$startDate', 
            end_date = '$endDate', 
            status = '$status', 
            proctoring_enabled = $proctoring, 
            randomize_questions = $randomizeQuestions, 
            show_results = $showResults, 
            updated_at = NOW() 
        WHERE id = $examId AND teacher_id = $teacherId
    ");
    
    if ($updateExam) {
        // Mettre à jour les classes assignées
        $conn->query("DELETE FROM exam_classes WHERE exam_id = $examId");
        
        if (isset($_POST['classes']) && is_array($_POST['classes'])) {
            foreach ($_POST['classes'] as $classId) {
                $classId = intval($classId);
                $conn->query("INSERT INTO exam_classes (exam_id, class_id) VALUES ($examId, $classId)");
            }
        }
        
        // Rediriger avec un message de succès
        header("Location: edit-exam.php?id=$examId&success=1");
        exit();
    }
}

$pageTitle = "Modifier l'examen";
include 'includes/header.php';
?>

<div class="app-container" >
    
    <main class="main-content"style="margin-left: 25px;margin-right: 20px;margin-top: 30px;border-radius: 20px;background-color: white;box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);margin-bottom: 20px;">
        
        <div class="content-wrapper">
            
            <div class="content-body">
                <style>/* Style de base pour les alertes */
.alert {
    position: relative;
    display: flex;
    align-items: flex-start;
    padding: 16px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 0.95rem;
    line-height: 1.5;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    border-left: 4px solid transparent;
}

/* Alerte succès */
.alert-success {
    background-color: #f0fdf4;
    color: #166534;
    border-color: #22c55e;
}

/* Icônes des alertes */
.alert i {
    margin-right: 12px;
    font-size: 1.2rem;
    margin-top: 2px;
    flex-shrink: 0;
}

.alert-success i {
    color: #22c55e;
}

/* Bouton de fermeture */
.close-alert {
    position: absolute;
    top: 16px;
    right: 20px;
    background: none;
    border: none;
    font-size: 1.3rem;
    line-height: 1;
    color: inherit;
    opacity: 0.7;
    cursor: pointer;
    transition: opacity 0.2s;
    padding: 0;
    margin-left: auto;
}

.close-alert:hover {
    opacity: 1;
}

/* Animation d'apparition */
@keyframes fadeInSlide {
    0% {
        opacity: 0;
        transform: translateY(-10px);
    }
    100% {
        opacity: 1;
        transform: translateY(0);
    }
}

.alert {
    animation: fadeInSlide 0.4s ease-out forwards;
}

/* Effet au survol */
.alert:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.1);
}

/* Responsive */
@media (max-width: 768px) {
    .alert {
        padding: 14px 16px;
        font-size: 0.9rem;
        align-items: center;
    }
    
    .alert i {
        font-size: 1.1rem;
        margin-right: 10px;
    }
    
    .close-alert {
        top: 14px;
        right: 16px;
        font-size: 1.2rem;
    }
}</style>
                <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span>L'examen a été mis à jour avec succès.</span>
                    <button class="close-alert">&times;</button>
                </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Informations de l'examen</h2>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="title">Titre de l'examen</label>
                                    <input type="text" id="title" name="title" class="form-control" value="<?php echo htmlspecialchars($exam['title']); ?>" required>
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="subject">Matière</label>
                                    <input type="text" id="subject" name="subject" class="form-control" value="<?php echo htmlspecialchars($exam['subject']); ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea id="description" name="description" class="form-control" rows="3"><?php echo htmlspecialchars($exam['description']); ?></textarea>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group col-md-4">
                                    <label for="duration">Durée (minutes)</label>
                                    <input type="number" id="duration" name="duration" class="form-control" min="1" max="300" value="<?php echo $exam['duration']; ?>" required>
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="passing_score">Score de réussite (%)</label>
                                    <input type="number" id="passing_score" name="passing_score" class="form-control" min="0" max="100" value="<?php echo $exam['passing_score']; ?>" required>
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="status">Statut</label>
                                    <select id="status" name="status" class="form-control" required>
                                        <option value="draft" <?php echo $exam['status'] === 'draft' ? 'selected' : ''; ?>>Brouillon</option>
                                        <option value="published" <?php echo $exam['status'] === 'published' ? 'selected' : ''; ?>>Publié</option>
                                        <option value="archived" <?php echo $exam['status'] === 'archived' ? 'selected' : ''; ?>>Archivé</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="start_date">Date de début</label>
                                    <input type="datetime-local" id="start_date" name="start_date" class="form-control" value="<?php echo date('Y-m-d\TH:i', strtotime($exam['start_date'])); ?>" required>
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="end_date">Date de fin</label>
                                    <input type="datetime-local" id="end_date" name="end_date" class="form-control" value="<?php echo date('Y-m-d\TH:i', strtotime($exam['end_date'])); ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Classes assignées</label>
                                <div class="checkbox-group">
                                    <?php while ($class = $classesQuery->fetch_assoc()): ?>
                                        <label class="checkbox-container">
                                            <input type="checkbox" name="classes[]" value="<?php echo $class['id']; ?>" <?php echo in_array($class['id'], $assignedClasses) ? 'checked' : ''; ?>>
                                            <span class="checkmark"></span>
                                            <?php echo htmlspecialchars($class['name']); ?>
                                        </label>
                                    <?php endwhile; ?>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Options avancées</label>
                                <div class="checkbox-group">
                                    <label class="checkbox-container">
                                        <input type="checkbox" name="proctoring" <?php echo $exam['proctoring_enabled'] ? 'checked' : ''; ?>>
                                        <span class="checkmark"></span>
                                        Activer la surveillance (proctoring)
                                    </label>
                                    <label class="checkbox-container">
                                        <input type="checkbox" name="randomize_questions" <?php echo $exam['randomize_questions'] ? 'checked' : ''; ?>>
                                        <span class="checkmark"></span>
                                        Randomiser l'ordre des questions
                                    </label>
                                    <label class="checkbox-container">
                                        <input type="checkbox" name="show_results" <?php echo $exam['show_results'] ? 'checked' : ''; ?>>
                                        <span class="checkmark"></span>
                                        Afficher les résultats immédiatement après l'examen
                                    </label>
                                </div>
                            </div>
                            
                            <div class="form-buttons">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Enregistrer les modifications
                                </button>
                                <a href="manage-exams.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left"></i> Retour
                                </a>
                                <a href="add-questions.php?exam_id=<?php echo $examId; ?>" class="btn btn-success">
                                    <i class="fas fa-question-circle"></i> Gérer les questions
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Fermer les alertes
    document.querySelectorAll('.close-alert').forEach(function(btn) {
        btn.addEventListener('click', function() {
            this.parentElement.style.display = 'none';
        });
    });
    
    // Validation du formulaire
    const form = document.querySelector('form');
    form.addEventListener('submit', function(event) {
        const startDate = new Date(document.getElementById('start_date').value);
        const endDate = new Date(document.getElementById('end_date').value);
        
        if (endDate <= startDate) {
            event.preventDefault();
            alert('La date de fin doit être postérieure à la date de début.');
        }
    });
});
</script>

