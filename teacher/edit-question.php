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

// Récupérer l'ID de la question
$questionId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($questionId <= 0) {
    setFlashMessage('danger', 'ID de question invalide.');
    header('Location: questions.php');
    exit();
}

// Récupérer les informations de la question
$sql = " SELECT 
q.*, 
e.title AS exam_title, 
e.id AS exam_id, 
qo.option_text, 
qo.is_correct
FROM 
questions q
JOIN 
exams e ON q.exam_id = e.id
LEFT JOIN 
question_options qo ON qo.question_id = q.id
WHERE 
q.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $questionId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    setFlashMessage('danger', 'Question non trouvée.');
    header('Location: questions.php');
    exit();
}

$question = $result->fetch_assoc();

// Récupérer les options de la question (pour les QCM)
$options = [];
if ($question['type'] === QUESTION_MCQ || $question['type'] === QUESTION_MATCHING) {
    $optionsSql = "SELECT * FROM question_options WHERE question_id = ? ORDER BY option_order ASC";
    $optionsStmt = $conn->prepare($optionsSql);
    $optionsStmt->bind_param("i", $questionId);
    $optionsStmt->execute();
    $optionsResult = $optionsStmt->get_result();

    while ($option = $optionsResult->fetch_assoc()) {
        $options[] = $option;
    }
}

// Récupérer tous les types de questions
$questionTypes = $conn->query("SELECT * FROM question_types ORDER BY name ASC");

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer les données du formulaire
    $questionText = $_POST['question_text'];
    $questionType = $_POST['question_type'];
    $points = intval($_POST['points']);
    $examId = intval($_POST['exam_id']);
    $correctAnswer = isset($_POST['correct_answer']) ? $_POST['correct_answer'] : '';
    $explanation = $_POST['explanation'] ?? '';
    $difficulty = $_POST['difficulty'];

    // Validation des données
    $errors = [];

    if (empty($questionText)) {
        $errors[] = "Le texte de la question est requis.";
    }

    if ($points <= 0) {
        $errors[] = "Le nombre de points doit être supérieur à zéro.";
    }

    // Si pas d'erreurs, mettre à jour la question
    if (empty($errors)) {
        // Commencer une transaction
        $conn->begin_transaction();

        try {
            // Mettre à jour la question
            $updateSql = "UPDATE questions SET 
                          question_text = ?, 
                          type = ?, 
                          points = ?, 
                          exam_id = ?, 
                          correct_answer = ?, 
                          explanation = ?,
                          difficulty = ?,
                          updated_at = NOW() 
                          WHERE id = ?";

            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("ssiisssi", $questionText, $questionType, $points, $examId, $correctAnswer, $explanation, $difficulty, $questionId);
            $updateStmt->execute();

            // Si c'est un QCM ou une question d'appariement, gérer les options
            if ($questionType === QUESTION_MCQ || $questionType === QUESTION_MATCHING) {
                // Supprimer les anciennes options
                $conn->query("DELETE FROM question_options WHERE question_id = $questionId");

                // Ajouter les nouvelles options
                if (isset($_POST['options']) && is_array($_POST['options'])) {
                    $optionOrder = 1;
                    $insertOptionSql = "INSERT INTO question_options (question_id, option_text, is_correct, option_order) VALUES (?, ?, ?, ?)";
                    $insertOptionStmt = $conn->prepare($insertOptionSql);

                    foreach ($_POST['options'] as $index => $optionText) {
                        if (!empty($optionText)) {
                            $isCorrect = isset($_POST['correct_options']) && in_array($index, $_POST['correct_options']) ? 1 : 0;
                            $insertOptionStmt->bind_param("isii", $questionId, $optionText, $isCorrect, $optionOrder);
                            $insertOptionStmt->execute();
                            $optionOrder++;
                        }
                    }
                }
            }

            // Si c'est une question d'appariement, gérer les correspondances
            if ($questionType === QUESTION_MATCHING && isset($_POST['matches']) && is_array($_POST['matches'])) {
                $matchOrder = 1;
                $insertMatchSql = "INSERT INTO question_matches (question_id, match_text, match_order) VALUES (?, ?, ?)";
                $insertMatchStmt = $conn->prepare($insertMatchSql);

                foreach ($_POST['matches'] as $matchText) {
                    if (!empty($matchText)) {
                        $insertMatchStmt->bind_param("isi", $questionId, $matchText, $matchOrder);
                        $insertMatchStmt->execute();
                        $matchOrder++;
                    }
                }
            }

            // Gérer le téléchargement d'image si présent
            if (isset($_FILES['question_image']) && $_FILES['question_image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = '../uploads/questions/';

                // Créer le répertoire s'il n'existe pas
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $fileName = $questionId . '_' . time() . '_' . basename($_FILES['question_image']['name']);
                $targetFile = $uploadDir . $fileName;

                // Vérifier le type de fichier
                $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
                if (in_array($imageFileType, ['jpg', 'jpeg', 'png', 'gif'])) {
                    if (move_uploaded_file($_FILES['question_image']['tmp_name'], $targetFile)) {
                        // Mettre à jour le chemin de l'image dans la base de données
                        $imagePath = 'uploads/questions/' . $fileName;
                        $conn->query("UPDATE questions SET image_path = '$imagePath' WHERE id = $questionId");
                    } else {
                        $errors[] = "Erreur lors du téléchargement de l'image.";
                    }
                } else {
                    $errors[] = "Seuls les fichiers JPG, JPEG, PNG et GIF sont autorisés.";
                }
            }

            // Valider la transaction
            $conn->commit();

            setFlashMessage('success', 'Question mise à jour avec succès.');
            header("Location: view-exam.php?id={$examId}");
            exit();
        } catch (Exception $e) {
            // Annuler la transaction en cas d'erreur
            $conn->rollback();
            $errors[] = "Erreur lors de la mise à jour de la question : " . $e->getMessage();
        }
    }
}

$pageTitle = "Modifier la question";
include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">


        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><?php echo $pageTitle; ?></h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="view-exam.php?id=<?php echo $question['exam_id']; ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Retour à l'examen
                    </a>
                </div>
            </div>

            <!-- Afficher les messages d'erreur -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Afficher les messages flash -->
            <?php displayFlashMessages(); ?>

            <div class="card mb-4">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">Modifier la question</h5>
                            <small class="text-muted">Examen: <?php echo htmlspecialchars($question['exam_title']); ?></small>
                        </div>
                        <span class="badge bg-primary"><?php echo htmlspecialchars($question['question_type_name']); ?></span>
                    </div>
                </div>
                <div class="card-body">
                    <form id="editQuestionForm" method="post" action="">
                        <div class="mb-3">
                            <label for="question_text" class="form-label">Texte de la question <span class="text-danger">*</span></label>
                            <textarea class="form-control rich-editor" id="question_text" name="question_text" rows="4" required><?php echo htmlspecialchars($question['question_text']); ?></textarea>
                            <small class="form-text text-muted">Vous pouvez utiliser le formatage pour améliorer la présentation de votre question.</small>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="question_type_id" class="form-label">Type de question <span class="text-danger">*</span></label>
                                <select class="form-select" id="question_type_id" name="question_type_id" required>
                                    <?php foreach ($questionTypes as $type): ?>
                                        <option value="<?php echo $type['id']; ?>" <?php echo ($type['id'] == $question['question_type_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($type['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="points" class="form-label">Points <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="points" name="points" min="0.5" step="0.5" value="<?php echo $question['points']; ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label for="difficulty" class="form-label">Difficulté</label>
                                <select class="form-select" id="difficulty" name="difficulty">
                                    <option value="Facile" <?php echo ($question['difficulty'] == 'Facile') ? 'selected' : ''; ?>>Facile</option>
                                    <option value="Moyen" <?php echo ($question['difficulty'] == 'Moyen') ? 'selected' : ''; ?>>Moyen</option>
                                    <option value="Difficile" <?php echo ($question['difficulty'] == 'Difficile') ? 'selected' : ''; ?>>Difficile</option>
                                </select>
                            </div>
                        </div>

                        <!-- Options pour QCM -->
                        <div id="mcq-options" class="question-type-options <?php echo ($question['question_type_id'] == 1) ? '' : 'd-none'; ?>">
                            <h5 class="mb-3">Options de réponse</h5>
                            <div id="options-container">
                                <?php if (!empty($options)): ?>
                                    <?php foreach ($options as $index => $option): ?>
                                        <div class="option-row mb-2 d-flex align-items-center">
                                            <div class="form-check me-2">
                                                <input class="form-check-input" type="radio" name="correct_option" value="<?php echo $index; ?>" <?php echo ($option['is_correct']) ? 'checked' : ''; ?>>
                                            </div>
                                            <input type="text" class="form-control" name="options[<?php echo $index; ?>]" value="<?php echo htmlspecialchars($option['option_text']); ?>" placeholder="Option de réponse">
                                            <button type="button" class="btn btn-outline-danger ms-2 remove-option"><i class="fas fa-times"></i></button>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="option-row mb-2 d-flex align-items-center">
                                        <div class="form-check me-2">
                                            <input class="form-check-input" type="radio" name="correct_option" value="0">
                                        </div>
                                        <input type="text" class="form-control" name="options[0]" placeholder="Option de réponse">
                                        <button type="button" class="btn btn-outline-danger ms-2 remove-option"><i class="fas fa-times"></i></button>
                                    </div>
                                    <div class="option-row mb-2 d-flex align-items-center">
                                        <div class="form-check me-2">
                                            <input class="form-check-input" type="radio" name="correct_option" value="1">
                                        </div>
                                        <input type="text" class="form-control" name="options[1]" placeholder="Option de réponse">
                                        <button type="button" class="btn btn-outline-danger ms-2 remove-option"><i class="fas fa-times"></i></button>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <button type="button" id="add-option" class="btn btn-outline-primary mt-2">
                                <i class="fas fa-plus me-1"></i> Ajouter une option
                            </button>
                        </div>

                        <!-- Options pour Vrai/Faux -->
                        <div id="true-false-options" class="question-type-options <?php echo ($question['question_type_id'] == 2) ? '' : 'd-none'; ?>">
                            <h5 class="mb-3">Réponse correcte</h5>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="correct_option" id="true-option" value="0" <?php echo ($question['correct_answer'] == '0') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="true-option">Vrai</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="correct_option" id="false-option" value="1" <?php echo ($question['correct_answer'] == '1') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="false-option">Faux</label>
                            </div>
                        </div>

                        <!-- Options pour Réponse courte -->
                        <div id="short-answer-options" class="question-type-options <?php echo ($question['question_type_id'] == 3) ? '' : 'd-none'; ?>">
                            <h5 class="mb-3">Réponse correcte</h5>
                            <div class="mb-3">
                                <input type="text" class="form-control" name="correct_answer" value="<?php echo htmlspecialchars($question['correct_answer']); ?>" placeholder="Entrez la réponse correcte">
                                <small class="form-text text-muted">La réponse de l'étudiant doit correspondre exactement à cette réponse pour être considérée comme correcte.</small>
                            </div>
                        </div>

                        <!-- Options pour Réponse longue/essai -->
                        <div id="essay-options" class="question-type-options <?php echo ($question['question_type_id'] == 4) ? '' : 'd-none'; ?>">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i> Les réponses à cette question devront être notées manuellement.
                            </div>
                            <div class="mb-3">
                                <label for="grading_criteria" class="form-label">Critères de notation (facultatif)</label>
                                <textarea class="form-control" id="grading_criteria" name="grading_criteria" rows="3" placeholder="Décrivez les critères de notation pour cette question..."><?php echo isset($question['grading_criteria']) ? htmlspecialchars($question['grading_criteria']) : ''; ?></textarea>
                                <small class="form-text text-muted">Ces critères vous aideront lors de la notation manuelle.</small>
                            </div>
                        </div>

                        <!-- Options pour Correspondance -->
                        <div id="matching-options" class="question-type-options <?php echo ($question['question_type_id'] == 5) ? '' : 'd-none'; ?>">
                            <h5 class="mb-3">Paires de correspondance</h5>
                            <div id="matching-container">
                                <?php
                                $matchPairs = [];
                                if ($question['question_type_id'] == 5 && !empty($question['correct_answer'])) {
                                    $matchPairs = json_decode($question['correct_answer'], true);
                                }

                                if (!empty($matchPairs)):
                                    $index = 0;
                                    foreach ($matchPairs as $item => $answer):
                                ?>
                                        <div class="match-row mb-2 row">
                                            <div class="col-md-5">
                                                <input type="text" class="form-control" name="match_items[<?php echo $index; ?>]" value="<?php echo htmlspecialchars($item); ?>" placeholder="Élément">
                                            </div>
                                            <div class="col-md-5">
                                                <input type="text" class="form-control" name="match_answers[<?php echo $index; ?>]" value="<?php echo htmlspecialchars($answer); ?>" placeholder="Correspondance">
                                            </div>
                                            <div class="col-md-2">
                                                <button type="button" class="btn btn-outline-danger remove-match"><i class="fas fa-times"></i></button>
                                            </div>
                                        </div>
                                    <?php
                                        $index++;
                                    endforeach;
                                else:
                                    ?>
                                    <div class="match-row mb-2 row">
                                        <div class="col-md-5">
                                            <input type="text" class="form-control" name="match_items[0]" placeholder="Élément">
                                        </div>
                                        <div class="col-md-5">
                                            <input type="text" class="form-control" name="match_answers[0]" placeholder="Correspondance">
                                        </div>
                                        <div class="col-md-2">
                                            <button type="button" class="btn btn-outline-danger remove-match"><i class="fas fa-times"></i></button>
                                        </div>
                                    </div>
                                    <div class="match-row mb-2 row">
                                        <div class="col-md-5">
                                            <input type="text" class="form-control" name="match_items[1]" placeholder="Élément">
                                        </div>
                                        <div class="col-md-5">
                                            <input type="text" class="form-control" name="match_answers[1]" placeholder="Correspondance">
                                        </div>
                                        <div class="col-md-2">
                                            <button type="button" class="btn btn-outline-danger remove-match"><i class="fas fa-times"></i></button>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <button type="button" id="add-match" class="btn btn-outline-primary mt-2">
                                <i class="fas fa-plus me-1"></i> Ajouter une paire
                            </button>
                        </div>

                        <div class="mt-4 d-flex justify-content-between">
                            <a href="view-exam.php?id=<?php echo $question['exam_id']; ?>" class="btn btn-outline-secondary">Annuler</a>
                            <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Aperçu de la question -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Aperçu de la question</h5>
                </div>
                <div class="card-body">
                    <div id="question-preview" class="p-3 border rounded">
                        <div class="question-text mb-3"></div>
                        <div class="question-options"></div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Tiny MCE pour l'éditeur de texte riche -->
<script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/5/tinymce.min.js" referrerpolicy="origin"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialisation de TinyMCE
        tinymce.init({
            selector: '.rich-editor',
            height: 200,
            menubar: false,
            plugins: [
                'advlist autolink lists link image charmap print preview anchor',
                'searchreplace visualblocks code fullscreen',
                'insertdatetime media table paste code help wordcount'
            ],
            toolbar: 'undo redo | formatselect | ' +
                'bold italic backcolor | alignleft aligncenter ' +
                'alignright alignjustify | bullist numlist outdent indent | ' +
                'removeformat | help',
            setup: function(editor) {
                editor.on('Change', function(e) {
                    updatePreview();
                });
            }
        });

        // Gestion du type de question
        const questionTypeSelect = document.getElementById('question_type_id');
        const questionTypeOptions = document.querySelectorAll('.question-type-options');

        questionTypeSelect.addEventListener('change', function() {
            // Cacher toutes les options
            questionTypeOptions.forEach(option => {
                option.classList.add('d-none');
            });

            // Afficher les options correspondantes au type sélectionné
            const selectedType = this.value;
            if (selectedType == 1) { // QCM
                document.getElementById('mcq-options').classList.remove('d-none');
            } else if (selectedType == 2) { // Vrai/Faux
                document.getElementById('true-false-options').classList.remove('d-none');
            } else if (selectedType == 3) { // Réponse courte
                document.getElementById('short-answer-options').classList.remove('d-none');
            } else if (selectedType == 4) { // Réponse longue/essai
                document.getElementById('essay-options').classList.remove('d-none');
            } else if (selectedType == 5) { // Correspondance
                document.getElementById('matching-options').classList.remove('d-none');
            }

            updatePreview();
        });

        // Ajouter une option pour QCM
        const addOptionBtn = document.getElementById('add-option');
        const optionsContainer = document.getElementById('options-container');

        addOptionBtn.addEventListener('click', function() {
            const optionRows = optionsContainer.querySelectorAll('.option-row');
            const newIndex = optionRows.length;

            const newOptionRow = document.createElement('div');
            newOptionRow.className = 'option-row mb-2 d-flex align-items-center';
            newOptionRow.innerHTML = `
            <div class="form-check me-2">
                <input class="form-check-input" type="radio" name="correct_option" value="${newIndex}">
            </div>
            <input type="text" class="form-control" name="options[${newIndex}]" placeholder="Option de réponse">
            <button type="button" class="btn btn-outline-danger ms-2 remove-option"><i class="fas fa-times"></i></button>
        `;

            optionsContainer.appendChild(newOptionRow);

            // Ajouter l'événement pour supprimer l'option
            newOptionRow.querySelector('.remove-option').addEventListener('click', function() {
                removeOption(this);
            });

            // Mettre à jour l'aperçu
            updatePreview();
        });

        // Supprimer une option pour QCM
        document.querySelectorAll('.remove-option').forEach(button => {
            button.addEventListener('click', function() {
                removeOption(this);
            });
        });

        function removeOption(button) {
            const optionRows = optionsContainer.querySelectorAll('.option-row');
            if (optionRows.length > 2) {
                const optionRow = button.closest('.option-row');
                optionRow.remove();

                // Réindexer les options restantes
                const remainingOptions = optionsContainer.querySelectorAll('.option-row');
                remainingOptions.forEach((row, index) => {
                    row.querySelector('input[type="radio"]').value = index;
                    row.querySelector('input[type="text"]').name = `options[${index}]`;
                });

                // Mettre à jour l'aperçu
                updatePreview();
            } else {
                alert('Vous devez avoir au moins 2 options de réponse.');
            }
        }

        // Ajouter une paire pour Correspondance
        const addMatchBtn = document.getElementById('add-match');
        const matchingContainer = document.getElementById('matching-container');

        addMatchBtn.addEventListener('click', function() {
            const matchRows = matchingContainer.querySelectorAll('.match-row');
            const newIndex = matchRows.length;

            const newMatchRow = document.createElement('div');
            newMatchRow.className = 'match-row mb-2 row';
            newMatchRow.innerHTML = `
            <div class="col-md-5">
                <input type="text" class="form-control" name="match_items[${newIndex}]" placeholder="Élément">
            </div>
            <div class="col-md-5">
                <input type="text" class="form-control" name="match_answers[${newIndex}]" placeholder="Correspondance">
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-outline-danger remove-match"><i class="fas fa-times"></i></button>
            </div>
        `;

            matchingContainer.appendChild(newMatchRow);

            // Ajouter l'événement pour supprimer la paire
            newMatchRow.querySelector('.remove-match').addEventListener('click', function() {
                removeMatch(this);
            });

            // Mettre à jour l'aperçu
            updatePreview();
        });

        // Supprimer une paire pour Correspondance
        document.querySelectorAll('.remove-match').forEach(button => {
            button.addEventListener('click', function() {
                removeMatch(this);
            });
        });

        function removeMatch(button) {
            const matchRows = matchingContainer.querySelectorAll('.match-row');
            if (matchRows.length > 2) {
                const matchRow = button.closest('.match-row');
                matchRow.remove();

                // Réindexer les paires restantes
                const remainingMatches = matchingContainer.querySelectorAll('.match-row');
                remainingMatches.forEach((row, index) => {
                    row.querySelector('input[name^="match_items"]').name = `match_items[${index}]`;
                    row.querySelector('input[name^="match_answers"]').name = `match_answers[${index}]`;
                });

                // Mettre à jour l'aperçu
                updatePreview();
            } else {
                alert('Vous devez avoir au moins 2 paires de correspondance.');
            }
        }

        // Fonction pour mettre à jour l'aperçu de la question
        function updatePreview() {
            const questionText = tinymce.get('question_text').getContent();
            const questionType = document.getElementById('question_type_id').value;

            // Mettre à jour le texte de la question
            document.querySelector('#question-preview .question-text').innerHTML = questionText;

            // Mettre à jour les options selon le type de question
            const optionsContainer = document.querySelector('#question-preview .question-options');
            optionsContainer.innerHTML = '';

            if (questionType == 1) { // QCM
                const options = document.querySelectorAll('#options-container .option-row');
                const optionsList = document.createElement('div');
                optionsList.className = 'options-list';

                options.forEach((option, index) => {
                    const optionText = option.querySelector('input[type="text"]').value || `Option ${index + 1}`;
                    const isChecked = option.querySelector('input[type="radio"]').checked;

                    const optionItem = document.createElement('div');
                    optionItem.className = 'form-check mb-2';
                    optionItem.innerHTML = `
                    <input class="form-check-input" type="radio" ${isChecked ? 'checked' : ''} disabled>
                    <label class="form-check-label">${optionText}</label>
                `;

                    optionsList.appendChild(optionItem);
                });

                optionsContainer.appendChild(optionsList);
            } else if (questionType == 2) { // Vrai/Faux
                const trueChecked = document.getElementById('true-option')?.checked;
                const falseChecked = document.getElementById('false-option')?.checked;

                const optionsList = document.createElement('div');
                optionsList.className = 'options-list';
                optionsList.innerHTML = `
                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" ${trueChecked ? 'checked' : ''} disabled>
                    <label class="form-check-label">Vrai</label>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" ${falseChecked ? 'checked' : ''} disabled>
                    <label class="form-check-label">Faux</label>
                </div>
            `;

                optionsContainer.appendChild(optionsList);
            } else if (questionType == 3) { // Réponse courte
                const answerInput = document.createElement('div');
                answerInput.className = 'mb-3';
                answerInput.innerHTML = `
                <input type="text" class="form-control" placeholder="Votre réponse ici" disabled>
            `;

                optionsContainer.appendChild(answerInput);
            } else if (questionType == 4) { // Réponse longue/essai
                const answerTextarea = document.createElement('div');
                answerTextarea.className = 'mb-3';
                answerTextarea.innerHTML = `
                <textarea class="form-control" rows="3" placeholder="Votre réponse ici" disabled></textarea>
            `;

                optionsContainer.appendChild(answerTextarea);
            } else if (questionType == 5) { // Correspondance
                const matchItems = document.querySelectorAll('input[name^="match_items"]');
                const matchAnswers = document.querySelectorAll('input[name^="match_answers"]');

                if (matchItems.length > 0 && matchAnswers.length > 0) {
                    const matchingTable = document.createElement('table');
                    matchingTable.className = 'table table-bordered';

                    const tableHead = document.createElement('thead');
                    tableHead.innerHTML = `
                    <tr>
                        <th>Élément</th>
                        <th>Correspondance</th>
                    </tr>
                `;

                    const tableBody = document.createElement('tbody');

                    for (let i = 0; i < matchItems.length; i++) {
                        const itemText = matchItems[i].value || `Élément ${i + 1}`;
                        const answerText = matchAnswers[i].value || `Correspondance ${i + 1}`;

                        const tableRow = document.createElement('tr');
                        tableRow.innerHTML = `
                        <td>${itemText}</td>
                        <td>${answerText}</td>
                    `;

                        tableBody.appendChild(tableRow);
                    }

                    matchingTable.appendChild(tableHead);
                    matchingTable.appendChild(tableBody);
                    optionsContainer.appendChild(matchingTable);
                }
            }
        }

        // Initialiser l'aperçu au chargement de la page
        setTimeout(updatePreview, 1000); // Délai pour laisser TinyMCE s'initialiser
    });
</script>