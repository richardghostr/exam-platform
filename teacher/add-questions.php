<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Vérifier si l'utilisateur est connecté et est un administrateur
if (!isLoggedIn() || !isTeacher()) {
    header('Location: ../login.php');
    exit();
}

// Vérifier si l'ID de l'examen est fourni
if (!isset($_GET['exam_id']) || empty($_GET['exam_id'])) {
    header('Location: manage-exams.php');
    exit();
}

$exam_id = intval($_GET['exam_id']);

// Récupérer les détails de l'examen
$examQuery = $conn->prepare("SELECT e.*, s.name as subject_name 
                            FROM exams e 
                            JOIN subjects s ON e.subject = s.id 
                            WHERE e.id = ?");
$examQuery->bind_param("i", $exam_id);
$examQuery->execute();
$exam = $examQuery->get_result()->fetch_assoc();

if (!$exam) {
    header('Location: manage-exams.php');
    exit();
}

// Traitement de l'ajout d'une question
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_question'])) {
    $question_text = $_POST['question_text'];
    $question_type = $_POST['question_type'];
    $points = $_POST['points'];
    $difficulty = $_POST['difficulty'];

    // Validation des données
    if (empty($question_text)) {
        $error = "Le texte de la question est obligatoire.";
    } else {
        // Insérer la question
        $stmt = $conn->prepare("INSERT INTO questions (exam_id, question_text, question_type, points, difficulty) 
                               VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issis", $exam_id, $question_text, $question_type, $points, $difficulty);

        if ($stmt->execute()) {
            $question_id = $stmt->insert_id;

            // Traiter les options de réponse pour les questions à choix
            if ($question_type === 'multiple_choice' || $question_type === 'single_choice' || $question_type === 'true_false') {
                $options = $_POST['options'];

                // Correction ici: s'assurer que $correct_options est toujours un tableau
                if (isset($_POST['correct_options'])) {
                    $correct_options = is_array($_POST['correct_options']) ? $_POST['correct_options'] : [$_POST['correct_options']];
                } else {
                    $correct_options = [];
                }

                foreach ($options as $key => $option_text) {
                    if (!empty($option_text)) {
                        $is_correct = in_array((string)$key, $correct_options) ? 1 : 0;

                        $optionStmt = $conn->prepare("INSERT INTO question_options (question_id, option_text, is_correct) 
                                                    VALUES (?, ?, ?)");
                        $optionStmt->bind_param("isi", $question_id, $option_text, $is_correct);
                        $optionStmt->execute();
                    }
                }
            }

            $success = "La question a été ajoutée avec succès.";
        } else {
            $error = "Erreur lors de l'ajout de la question: " . $conn->error;
        }
    }
}

// Récupérer les questions existantes pour cet examen
$questionsQuery = $conn->prepare("SELECT * FROM questions WHERE exam_id = ? ORDER BY id");
$questionsQuery->bind_param("i", $exam_id);
$questionsQuery->execute();
$questions = $questionsQuery->get_result();

$pageTitle = "Ajouter des questions à l'examen";
include 'includes/header.php';
?>
<style>
    .admin-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 1rem;
    }

    .admin-table th,
    .admin-table td {

        padding: 12px 15px;
        text-align: left;
        border-bottom: 1px solid #e0e0e0;
    }

    .admin-table th {
        background-color: #f5f5f5;
        font-weight: 600;
    }

    .alert {
        position: relative;
        padding: 0.75rem 1.25rem;
        margin-bottom: 1rem;
        border: 1px solid transparent;
        border-radius: 0.25rem;
    }

    .alert-danger {
        color: #721c24;
        background-color: #f8d7da;
        border-color: #f5c6cb;
    }

    .alert-success {
        color: #155724;
        background-color: #d4edda;
        border-color: #c3e6cb;
    }

    .alert-info {
        color: #0c5460;
        background-color: #d1ecf1;
        border-color: #bee5eb;
    }

    .table-responsive {
        display: block;
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .row {
        display: flex;
        flex-wrap: wrap;
        margin-right: -15px;
        margin-left: -15px;
    }

    .col-md-6 {
        flex: 0 0 50%;
        max-width: 50%;
        padding-right: 15px;
        padding-left: 15px;
    }

    .col-md-4 {
        flex: 0 0 33.333333%;
        max-width: 33.333333%;
        padding-right: 15px;
        padding-left: 15px;
    }

    /* Icônes */
    .fas {
        font-size: 0.875em;
    }

    .mb-20 {
        margin-bottom: 20px;
    }

    .mt-20 {
        margin-top: 20px;
    }

    .ml-10 {
        margin-left: 10px;
    }

    .option-row {
        margin-bottom: 10px;
    }

    .remove-option {
        margin-left: 10px;
    }

    .gap-10 {
        gap: 10px;
    }

    .d-flex {
        display: flex;
    }

    .align-items-center {
        align-items: center;
    }

    .justify-content-between {
        justify-content: space-between;
    }

    .flex-grow-1 {
        flex-grow: 1;
    }

    .text-muted {
        color: #6c757d !important;
    }
</style>
<div class="d-flex justify-content-between align-items-center mb-20" style="margin-top: 20px;margin-right:20px;margin-bottom: 20px;justify-content: flex-end;">
    <div>
        <a href="manage-exams.php" class="btn btn-secondary" style="text-decoration: none;">
            <i class="fas fa-arrow-left"></i> Retour aux examens
        </a>
        <a href="view-exam.php?id=<?php echo $exam_id; ?>" class="btn btn-info" style="text-decoration: none;margin-left: 10px;">
            <i class="fas fa-eye"></i> Voir l'examen
        </a>
    </div>
</div>

<div class="card mb-20" style="border-radius: 20px;margin-right: 20px;margin-left:25px;background-color: white;box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);">
    <div class="card-header">
        <h2 class="card-title">Détails de l'examen</h2>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <p><strong>Titre:</strong> <?php echo htmlspecialchars($exam['title']); ?></p>
                <p><strong>Matière:</strong> <?php echo htmlspecialchars($exam['subject_name']); ?></p>
                <p><strong>Durée:</strong> <?php echo $exam['duration']; ?> minutes</p>
            </div>
            <div class="col-md-6">
                <p><strong>Note de passage:</strong> <?php echo $exam['passing_score']; ?>%</p>
                <p><strong>Statut:</strong>
                    <span class="info-value status-badge <?php echo $exam['status']; ?>">
                        <?php echo ucfirst($exam['status']); ?>
                    </span>
                </p>
                <p><strong>Surveillance:</strong> <?php echo $exam['is_proctored'] ? 'Activée' : 'Désactivée'; ?></p>
            </div>
        </div>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger" style="border-radius: 10px;margin-right: 20px;margin-left:25px;margin-top: 20px;">
        <?php echo $error; ?>
    </div>
<?php endif; ?>

<?php if (isset($success)): ?>
    <div class="alert alert-success" style="border-radius: 10px;margin-right: 20px;margin-left:25px;margin-top: 20px;">
        <?php echo $success; ?>
    </div>
<?php endif; ?>

<div class="card mb-20" style="margin-top:30px;border-radius: 20px;margin-right: 20px;margin-left:25px;background-color: white;box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);">
    <div class="card-header">
        <h2 class="card-title">Questions existantes (<?php echo $questions->num_rows; ?>)</h2>
    </div>
    <div class="card-body">
        <?php if ($questions->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Question</th>
                            <th>Type</th>
                            <th>Points</th>
                            <th>Difficulté</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($question = $questions->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $question['id']; ?></td>
                                <td><?php echo htmlspecialchars(substr($question['question_text'], 0, 100)) . (strlen($question['question_text']) > 100 ? '...' : ''); ?></td>
                                <td>
                                    <span>
                                        <?php echo getQuestionTypeLabel($question['question_type']); ?>
                                    </span>
                                </td>
                                <td><?php echo $question['points']; ?></td>
                                <td>
                                    <span>
                                        <?php echo getDifficultyBadgeClass($question['difficulty']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex gap-10">
                                        <a href="edit-question.php?id=<?php echo $question['id']; ?>" class="btn btn-info btn-sm">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="?exam_id=<?php echo $exam_id; ?>&delete_question=<?php echo $question['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette question ?');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                Aucune question n'a encore été ajoutée à cet examen.
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card" style="margin-top:30px;border-radius: 20px;margin-right: 20px;margin-left:25px;background-color: white;box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);">
    <div class="card-header">
        <h2 class="card-title">Ajouter une nouvelle question</h2>
    </div>
    <div class="card-body">
        <form action="" method="post" id="questionForm">
            <div class="form-group">
                <label for="question_text" class="form-label">Texte de la question *</label>
                <textarea id="question_text" name="question_text" class="form-control" rows="4" required></textarea>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="question_type" class="form-label">Type de question *</label>
                        <select id="question_type" name="question_type" class="form-control" required>
                            <option value="multiple_choice">Choix multiple</option>
                            <option value="single_choice">Choix unique</option>
                            <option value="true_false">Vrai/Faux</option>
                            <option value="short_answer">Réponse courte</option>
                            <option value="essay">Réponse longue</option>
                        </select>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group">
                        <label for="points" class="form-label">Points *</label>
                        <input type="number" id="points" name="points" class="form-control" min="1" value="1" required>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group">
                        <label for="difficulty" class="form-label">Difficulté *</label>
                        <select id="difficulty" name="difficulty" class="form-control" required>
                            <option value="easy">Facile</option>
                            <option value="medium" selected>Moyenne</option>
                            <option value="hard">Difficile</option>
                        </select>
                    </div>
                </div>
            </div>

            <div id="options_container" class="mt-20">
                <h3>Options de réponse</h3>
                <p class="text-muted" style="margin-bottom: 15px;margin-top:5px">Cochez les options qui sont correctes.</p>

                <div id="options_list">
                    <div class="option-row d-flex align-items-center mb-10">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="correct_options[]" value="0">
                        </div>
                        <div class="flex-grow-1">
                            <input type="text" name="options[]" class="form-control" placeholder="Option de réponse">
                        </div>
                        <div>
                            <button type="button" class="btn btn-danger btn-sm remove-option ml-10">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <div class="option-row d-flex align-items-center mb-10">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="correct_options[]" value="1">
                        </div>
                        <div class="flex-grow-1">
                            <input type="text" name="options[]" class="form-control" placeholder="Option de réponse">
                        </div>
                        <div>
                            <button type="button" class="btn btn-danger btn-sm remove-option ml-10">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <button type="button" id="add_option" class="btn btn-secondary mt-10">
                    <i class="fas fa-plus"></i> Ajouter une option
                </button>
            </div>

            <div class="mt-20">
                <button type="submit" name="add_question" class="btn btn-primary">
                    <i class="fas fa-save"></i> Enregistrer la question
                </button>
            </div>
        </form>
    </div>
</div>
<script src="https://cdn.tiny.cloud/1/1dqpp2wweuwe4ayuoiglr95zm8l7a678dl1af8u6qm2m3kip/tinymce/5/tinymce.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
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

        const questionTypeSelect = document.getElementById('question_type');
        const optionsContainer = document.getElementById('options_container');
        const optionsList = document.getElementById('options_list');
        const addOptionBtn = document.getElementById('add_option');

        // Fonction pour gérer l'affichage des options en fonction du type de question
        function toggleOptionsVisibility() {
            const questionType = questionTypeSelect.value;

            if (questionType === 'multiple_choice' || questionType === 'single_choice') {
                optionsContainer.style.display = 'block';
                updateCheckboxType(questionType === 'single_choice' ? 'radio' : 'checkbox');
            } else if (questionType === 'true_false') {
                optionsContainer.style.display = 'block';
                // Réinitialiser les options pour Vrai/Faux
                optionsList.innerHTML = '';
                addTrueFalseOptions();
                updateCheckboxType('radio');
            } else {
                optionsContainer.style.display = 'none';
            }
        }

        // Fonction pour ajouter les options Vrai/Faux
        function addTrueFalseOptions() {
            const trueOption = createOptionRow(0, 'Vrai');
            const falseOption = createOptionRow(1, 'Faux');

            optionsList.appendChild(trueOption);
            optionsList.appendChild(falseOption);

            // Désactiver le bouton d'ajout d'option pour Vrai/Faux
            addOptionBtn.style.display = 'none';
        }

        // Fonction pour créer une nouvelle ligne d'option
        function createOptionRow(index, value = '') {
            const row = document.createElement('div');
            row.className = 'option-row d-flex align-items-center mb-10';

            const checkboxType = questionTypeSelect.value === 'single_choice' || questionTypeSelect.value === 'true_false' ? 'radio' : 'checkbox';
            const inputName = checkboxType === 'radio' ? 'correct_options' : 'correct_options[]';

            row.innerHTML = `
            <div class="form-check">
                <input class="form-check-input" type="${checkboxType}" name="${inputName}" value="${index}">
            </div>
            <div class="flex-grow-1">
                <input type="text" name="options[]" class="form-control" placeholder="Option de réponse" value="${value}">
            </div>
            <div>
                <button type="button" class="btn btn-danger btn-sm remove-option ml-10">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;

            // Ajouter un gestionnaire d'événements pour le bouton de suppression
            row.querySelector('.remove-option').addEventListener('click', function() {
                if (questionTypeSelect.value !== 'true_false') {
                    row.remove();
                    updateOptionIndexes();
                }
            });

            return row;
        }

        // Fonction pour mettre à jour les index des options
        function updateOptionIndexes() {
            const options = optionsList.querySelectorAll('.option-row');
            options.forEach((option, index) => {
                const input = option.querySelector('.form-check-input');
                input.value = index;
            });
        }

        // Fonction pour mettre à jour le type de case à cocher
        function updateCheckboxType(type) {
            const options = optionsList.querySelectorAll('.option-row');
            const inputName = type === 'radio' ? 'correct_options' : 'correct_options[]';

            options.forEach((option, index) => {
                const checkboxDiv = option.querySelector('.form-check');
                const oldInput = option.querySelector('.form-check-input');
                const isChecked = oldInput.checked;

                checkboxDiv.innerHTML = `<input class="form-check-input" type="${type}" name="${inputName}" value="${index}" ${isChecked ? 'checked' : ''}>`;
            });
        }

        // Ajouter un gestionnaire d'événements pour le changement de type de question
        questionTypeSelect.addEventListener('change', toggleOptionsVisibility);

        // Ajouter un gestionnaire d'événements pour le bouton d'ajout d'option
        addOptionBtn.addEventListener('click', function() {
            const options = optionsList.querySelectorAll('.option-row');
            const newOption = createOptionRow(options.length);
            optionsList.appendChild(newOption);
        });

        // Initialiser l'affichage des options
        toggleOptionsVisibility();

        // Ajouter des gestionnaires d'événements pour les boutons de suppression existants
        document.querySelectorAll('.remove-option').forEach(button => {
            button.addEventListener('click', function() {
                if (questionTypeSelect.value !== 'true_false') {
                    this.closest('.option-row').remove();
                    updateOptionIndexes();
                }
            });
        });

        // Validation du formulaire
        document.getElementById('questionForm').addEventListener('submit', function(e) {
            const questionType = questionTypeSelect.value;

            if (questionType === 'multiple_choice' || questionType === 'single_choice' || questionType === 'true_false') {
                const options = document.querySelectorAll('input[name="options[]"]');
                const correctOptions = document.querySelectorAll('input[name="correct_options[]"]:checked, input[name="correct_options"]:checked');

                let hasEmptyOption = false;
                options.forEach(option => {
                    if (option.value.trim() === '') {
                        hasEmptyOption = true;
                    }
                });

                if (hasEmptyOption) {
                    e.preventDefault();
                    alert('Toutes les options de réponse doivent être remplies.');
                    return;
                }

                if (correctOptions.length === 0) {
                    e.preventDefault();
                    alert('Vous devez sélectionner au moins une option correcte.');
                    return;
                }
            }
        });
    });
</script>

<?php
// Helper functions
function getQuestionTypeLabel($type)
{
    switch ($type) {
        case 'multiple_choice':
            return 'Choix multiple';
        case 'single_choice':
            return 'Choix unique';
        case 'true_false':
            return 'Vrai/Faux';
        case 'short_answer':
            return 'Réponse courte';
        case 'essay':
            return 'Réponse longue';
        default:
            return ucfirst($type);
    }
}

function getDifficultyBadgeClass($difficulty)
{
    switch ($difficulty) {
        case 'easy':
            return 'success';
        case 'medium':
            return 'warning';
        case 'hard':
            return 'danger';
        default:
            return 'secondary';
    }
}

function getStatusBadgeClass($status)
{
    switch ($status) {
        case 'draft':
            return 'secondary';
        case 'published':
            return 'success';
        case 'scheduled':
            return 'info';
        case 'completed':
            return 'primary';
        default:
            return 'secondary';
    }
}
?>