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

// Traitement du formulaire d'ajout de question
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_question'])) {
        $questionType = $conn->real_escape_string($_POST['question_type']);
        $questionText = $conn->real_escape_string($_POST['question_text']);
        $points = intval($_POST['points']);
        
        // Insérer la question
        $insertQuestion = $conn->query("
            INSERT INTO questions (exam_id, question_type, question_text, points) 
            VALUES ($examId, '$questionType', '$questionText', $points)
        ");
        
        if ($insertQuestion) {
            $questionId = $conn->insert_id;
            
            // Traiter les options pour les questions à choix multiples
            if ($questionType === 'multiple_choice' || $questionType === 'single_choice') {
                foreach ($_POST['options'] as $index => $optionText) {
                    if (!empty($optionText)) {
                        $optionText = $conn->real_escape_string($optionText);
                        $isCorrect = isset($_POST['correct_options']) && in_array($index, $_POST['correct_options']) ? 1 : 0;
                        
                        $conn->query("
                            INSERT INTO question_options (question_id, option_text, is_correct) 
                            VALUES ($questionId, '$optionText', $isCorrect)
                        ");
                    }
                }
            }
            
            // Mettre à jour le nombre de questions dans l'examen
            $conn->query("UPDATE exams SET question_count = question_count + 1 WHERE id = $examId");
            
            // Mettre à jour le flag has_essay si nécessaire
            if ($questionType === 'essay' && $exam['has_essay'] == 0) {
                $conn->query("UPDATE exams SET has_essay = 1 WHERE id = $examId");
            }
            
            // Rediriger avec un message de succès
            header("Location: add-questions.php?exam_id=$examId&success=1");
            exit();
        }
    }
}

// Récupérer les questions existantes
$questions = $conn->query("
    SELECT q.*, COUNT(qo.id) as option_count 
    FROM questions q 
    LEFT JOIN question_options qo ON q.id = qo.question_id 
    WHERE q.exam_id = $examId 
    GROUP BY q.id 
    ORDER BY q.id ASC
");

$pageTitle = "Ajouter des questions";
include 'includes/header.php';
?>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        <?php include 'includes/navbar.php'; ?>
        
        <div class="content-wrapper">
            <div class="content-header">
                <h1 class="page-title">Ajouter des questions à l'examen</h1>
                <nav class="breadcrumb">
                    <ol>
                        <li><a href="index.php">Tableau de bord</a></li>
                        <li><a href="manage-exams.php">Gérer les examens</a></li>
                        <li class="active">Ajouter des questions</li>
                    </ol>
                </nav>
            </div>
            
            <div class="content-body">
                <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span>La question a été ajoutée avec succès.</span>
                    <button class="close-alert">&times;</button>
                </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Informations sur l'examen</h2>
                    </div>
                    <div class="card-body">
                        <div class="exam-info">
                            <div class="info-item">
                                <span class="info-label">Titre:</span>
                                <span class="info-value"><?php echo htmlspecialchars($exam['title']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Matière:</span>
                                <span class="info-value"><?php echo htmlspecialchars($exam['subject']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Durée:</span>
                                <span class="info-value"><?php echo $exam['duration']; ?> minutes</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Questions:</span>
                                <span class="info-value"><?php echo $exam['question_count']; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Statut:</span>
                                <span class="info-value status-badge <?php echo $exam['status']; ?>">
                                    <?php echo ucfirst($exam['status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Ajouter une nouvelle question</h2>
                    </div>
                    <div class="card-body">
                        <form id="questionForm" method="POST" action="">
                            <div class="form-group">
                                <label for="question_type">Type de question</label>
                                <select id="question_type" name="question_type" class="form-control" required>
                                    <option value="">Sélectionner un type</option>
                                    <option value="multiple_choice">Choix multiples</option>
                                    <option value="single_choice">Choix unique</option>
                                    <option value="true_false">Vrai/Faux</option>
                                    <option value="essay">Réponse libre</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="question_text">Texte de la question</label>
                                <textarea id="question_text" name="question_text" class="form-control rich-editor" rows="4" required></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="points">Points</label>
                                <input type="number" id="points" name="points" class="form-control" min="1" max="100" value="1" required>
                            </div>
                            
                            <!-- Options pour les questions à choix multiples/unique -->
                            <div id="options_container" class="form-group" style="display: none;">
                                <label>Options</label>
                                <div id="options_list">
                                    <div class="option-item">
                                        <div class="option-input">
                                            <input type="text" name="options[]" class="form-control" placeholder="Option 1">
                                        </div>
                                        <div class="option-correct">
                                            <label class="checkbox-container">
                                                <input type="checkbox" name="correct_options[]" value="0">
                                                <span class="checkmark"></span>
                                                Correcte
                                            </label>
                                        </div>
                                        <div class="option-actions">
                                            <button type="button" class="btn btn-icon remove-option">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" id="add_option" class="btn btn-outline-primary btn-sm mt-2">
                                    <i class="fas fa-plus"></i> Ajouter une option
                                </button>
                            </div>
                            
                            <!-- Options pour les questions Vrai/Faux -->
                            <div id="true_false_container" class="form-group" style="display: none;">
                                <label>Réponse correcte</label>
                                <div class="radio-group">
                                    <label class="radio-container">
                                        <input type="radio" name="true_false_answer" value="true" checked>
                                        <span class="radio-mark"></span>
                                        Vrai
                                    </label>
                                    <label class="radio-container">
                                        <input type="radio" name="true_false_answer" value="false">
                                        <span class="radio-mark"></span>
                                        Faux
                                    </label>
                                </div>
                            </div>
                            
                            <div class="form-buttons">
                                <button type="submit" name="add_question" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Ajouter la question
                                </button>
                                <a href="manage-exams.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left"></i> Retour
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Questions existantes (<?php echo $questions->num_rows; ?>)</h2>
                    </div>
                    <div class="card-body">
                        <?php if ($questions->num_rows > 0): ?>
                            <div class="questions-list">
                                <?php $questionNumber = 1; ?>
                                <?php while ($question = $questions->fetch_assoc()): ?>
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
                                            <div class="question-actions">
                                                <button type="button" class="btn btn-icon btn-sm edit-question" data-id="<?php echo $question['id']; ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-icon btn-sm delete-question" data-id="<?php echo $question['id']; ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
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
                                <p>Cet examen ne contient pas encore de questions. Utilisez le formulaire ci-dessus pour ajouter des questions.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Modal de confirmation de suppression -->
<div class="modal" id="deleteQuestionModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Confirmer la suppression</h2>
            <button class="close-modal">&times;</button>
        </div>
        <div class="modal-body">
            <p>Êtes-vous sûr de vouloir supprimer cette question ? Cette action est irréversible.</p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline-secondary close-modal">Annuler</button>
            <button class="btn btn-danger" id="confirmDeleteQuestion">Supprimer</button>
        </div>
    </div>
</div>

<!-- Modal d'édition de question -->
<div class="modal" id="editQuestionModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Modifier la question</h2>
            <button class="close-modal">&times;</button>
        </div>
        <div class="modal-body">
            <form id="editQuestionForm" method="POST" action="update-question.php">
                <input type="hidden" name="question_id" id="edit_question_id">
                <input type="hidden" name="exam_id" value="<?php echo $examId; ?>">
                
                <div class="form-group">
                    <label for="edit_question_text">Texte de la question</label>
                    <textarea id="edit_question_text" name="question_text" class="form-control" rows="4" required></textarea>
                </div>
                
                <div class="form-group">
                    <label for="edit_points">Points</label>
                    <input type="number" id="edit_points" name="points" class="form-control" min="1" max="100" required>
                </div>
                
                <div id="edit_options_container" class="form-group">
                    <label>Options</label>
                    <div id="edit_options_list"></div>
                    <button type="button" id="edit_add_option" class="btn btn-outline-primary btn-sm mt-2">
                        <i class="fas fa-plus"></i> Ajouter une option
                    </button>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline-secondary close-modal">Annuler</button>
            <button class="btn btn-primary" id="saveQuestionChanges">Enregistrer</button>
        </div>
    </div>
</div>

<script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/5/tinymce.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialiser l'éditeur de texte riche
    tinymce.init({
        selector: '.rich-editor',
        height: 200,
        menubar: false,
        plugins: [
            'advlist autolink lists link image charmap print preview anchor',
            'searchreplace visualblocks code fullscreen',
            'insertdatetime media table paste code help wordcount'
        ],
        toolbar: 'undo redo | formatselect | bold italic backcolor | \
                alignleft aligncenter alignright alignjustify | \
                bullist numlist outdent indent | removeformat | help'
    });
    
    // Gestion du type de question
    const questionTypeSelect = document.getElementById('question_type');
    const optionsContainer = document.getElementById('options_container');
    const trueFalseContainer = document.getElementById('true_false_container');
    
    questionTypeSelect.addEventListener('change', function() {
        const questionType = this.value;
        
        // Réinitialiser les conteneurs
        optionsContainer.style.display = 'none';
        trueFalseContainer.style.display = 'none';
        
        // Afficher le conteneur approprié en fonction du type de question
        if (questionType === 'multiple_choice' || questionType === 'single_choice') {
            optionsContainer.style.display = 'block';
            
            // Mettre à jour le type de sélection (checkbox ou radio)
            const optionCheckboxes = document.querySelectorAll('input[name="correct_options[]"]');
            optionCheckboxes.forEach(function(checkbox) {
                checkbox.type = questionType === 'multiple_choice' ? 'checkbox' : 'radio';
            });
        } else if (questionType === 'true_false') {
            trueFalseContainer.style.display = 'block';
        }
    });
    
    // Gestion des options
    const addOptionBtn = document.getElementById('add_option');
    const optionsList = document.getElementById('options_list');
    
    addOptionBtn.addEventListener('click', function() {
        const optionItems = optionsList.querySelectorAll('.option-item');
        const newIndex = optionItems.length;
        
        const optionItem = document.createElement('div');
        optionItem.className = 'option-item';
        
        const questionType = questionTypeSelect.value;
        const inputType = questionType === 'multiple_choice' ? 'checkbox' : 'radio';
        
        optionItem.innerHTML = `
            <div class="option-input">
                <input type="text" name="options[]" class="form-control" placeholder="Option ${newIndex + 1}">
            </div>
            <div class="option-correct">
                <label class="checkbox-container">
                    <input type="${inputType}" name="correct_options[]" value="${newIndex}">
                    <span class="checkmark"></span>
                    Correcte
                </label>
            </div>
            <div class="option-actions">
                <button type="button" class="btn btn-icon remove-option">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        optionsList.appendChild(optionItem);
        
        // Ajouter l'événement de suppression
        const removeBtn = optionItem.querySelector('.remove-option');
        removeBtn.addEventListener('click', function() {
            optionItem.remove();
            updateOptionIndices();
        });
    });
    
    // Fonction pour mettre à jour les indices des options
    function updateOptionIndices() {
        const optionItems = optionsList.querySelectorAll('.option-item');
        optionItems.forEach(function(item, index) {
            const input = item.querySelector('input[name="options[]"]');
            input.placeholder = `Option ${index + 1}`;
            
            const checkbox = item.querySelector('input[name="correct_options[]"]');
            checkbox.value = index;
        });
    }
    
    // Ajouter l'événement de suppression aux options existantes
    document.querySelectorAll('.remove-option').forEach(function(btn) {
        btn.addEventListener('click', function() {
            this.closest('.option-item').remove();
            updateOptionIndices();
        });
    });
    
    // Gestion de la suppression de question
    let questionIdToDelete = null;
    const deleteQuestionModal = document.getElementById('deleteQuestionModal');
    
    document.querySelectorAll('.delete-question').forEach(function(btn) {
        btn.addEventListener('click', function() {
            questionIdToDelete = this.getAttribute('data-id');
            deleteQuestionModal.style.display = 'block';
        });
    });
    
    document.getElementById('confirmDeleteQuestion').addEventListener('click', function() {
        if (questionIdToDelete) {
            window.location.href = `delete-question.php?id=${questionIdToDelete}&exam_id=${<?php echo $examId; ?>}`;
        }
    });
    
    // Gestion de l'édition de question
    const editQuestionModal = document.getElementById('editQuestionModal');
    const editQuestionForm = document.getElementById('editQuestionForm');
    const editQuestionId = document.getElementById('edit_question_id');
    const editQuestionText = document.getElementById('edit_question_text');
    const editPoints = document.getElementById('edit_points');
    const editOptionsList = document.getElementById('edit_options_list');
    
    document.querySelectorAll('.edit-question').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const questionId = this.getAttribute('data-id');
            
            // Charger les données de la question via AJAX
            fetch(`get-question.php?id=${questionId}`)
                .then(response => response.json())
                .then(data => {
                    editQuestionId.value = data.id;
                    editQuestionText.value = data.question_text;
                    editPoints.value = data.points;
                    
                    // Afficher ou masquer le conteneur d'options
                    const editOptionsContainer = document.getElementById('edit_options_container');
                    editOptionsContainer.style.display = data.question_type === 'essay' ? 'none' : 'block';
                    
                    // Remplir les options
                    editOptionsList.innerHTML = '';
                    if (data.options && data.options.length > 0) {
                        data.options.forEach(function(option, index) {
                            const optionItem = document.createElement('div');
                            optionItem.className = 'option-item';
                            
                            const inputType = data.question_type === 'multiple_choice' ? 'checkbox' : 'radio';
                            
                            optionItem.innerHTML = `
                                <input type="hidden" name="option_ids[]" value="${option.id}">
                                <div class="option-input">
                                    <input type="text" name="edit_options[]" class="form-control" value="${option.option_text}" placeholder="Option ${index + 1}">
                                </div>
                                <div class="option-correct">
                                    <label class="checkbox-container">
                                        <input type="${inputType}" name="edit_correct_options[]" value="${index}" ${option.is_correct ? 'checked' : ''}>
                                        <span class="checkmark"></span>
                                        Correcte
                                    </label>
                                </div>
                                <div class="option-actions">
                                    <button type="button" class="btn btn-icon remove-edit-option">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            `;
                            
                            editOptionsList.appendChild(optionItem);
                        });
                        
                        // Ajouter les événements de suppression
                        document.querySelectorAll('.remove-edit-option').forEach(function(btn) {
                            btn.addEventListener('click', function() {
                                this.closest('.option-item').remove();
                            });
                        });
                    }
                    
                    editQuestionModal.style.display = 'block';
                })
                .catch(error => console.error('Error:', error));
        });
    });
    
    // Ajouter une option dans le formulaire d'édition
    document.getElementById('edit_add_option').addEventListener('click', function() {
        const optionItems = editOptionsList.querySelectorAll('.option-item');
        const newIndex = optionItems.length;
        
        const optionItem = document.createElement('div');
        optionItem.className = 'option-item';
        
        optionItem.innerHTML = `
            <input type="hidden" name="option_ids[]" value="new">
            <div class="option-input">
                <input type="text" name="edit_options[]" class="form-control" placeholder="Option ${newIndex + 1}">
            </div>
            <div class="option-correct">
                <label class="checkbox-container">
                    <input type="checkbox" name="edit_correct_options[]" value="${newIndex}">
                    <span class="checkmark"></span>
                    Correcte
                </label>
            </div>
            <div class="option-actions">
                <button type="button" class="btn btn-icon remove-edit-option">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        editOptionsList.appendChild(optionItem);
        
        // Ajouter l'événement de suppression
        const removeBtn = optionItem.querySelector('.remove-edit-option');
        removeBtn.addEventListener('click', function() {
            optionItem.remove();
        });
    });
    
    // Enregistrer les modifications
    document.getElementById('saveQuestionChanges').addEventListener('click', function() {
        editQuestionForm.submit();
    });
    
    // Fermer les modals
    document.querySelectorAll('.close-modal').forEach(function(btn) {
        btn.addEventListener('click', function() {
            deleteQuestionModal.style.display = 'none';
            editQuestionModal.style.display = 'none';
        });
    });
    
    window.addEventListener('click', function(event) {
        if (event.target === deleteQuestionModal) {
            deleteQuestionModal.style.display = 'none';
        }
        if (event.target === editQuestionModal) {
            editQuestionModal.style.display = 'none';
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
