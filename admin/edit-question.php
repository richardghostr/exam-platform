<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Vérifier si l'utilisateur est connecté et est un administrateur
if (!isLoggedIn() || !isAdmin()) {
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
$sql = "SELECT q.*, e.title as exam_title, e.id as exam_id 
        FROM questions q 
        JOIN exams e ON q.exam_id = e.id 
        WHERE q.id = ?";
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
            
            <?php displayFlashMessages(); ?>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-edit me-2"></i>Modifier la question
                        </h5>
                        <span class="badge bg-primary">
                            Examen : <?php echo htmlspecialchars($question['exam_title']); ?>
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <form action="" method="post" enctype="multipart/form-data" id="questionForm">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="question_text" class="form-label">Texte de la question <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="question_text" name="question_text" rows="4" required><?php echo htmlspecialchars($question['question_text']); ?></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="question_type" class="form-label">Type de question <span class="text-danger">*</span></label>
                                            <select class="form-select" id="question_type" name="question_type" required>
                                                <?php while ($type = $questionTypes->fetch_assoc()): ?>
                                                    <option value="<?php echo $type['id']; ?>" <?php echo ($question['question_type_id'] === $type['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($type['name']); ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="points" class="form-label">Points <span class="text-danger">*</span></label>
                                            <input type="number" class="form-control" id="points" name="points" min="1" value="<?php echo $question['points']; ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="difficulty" class="form-label">Difficulté</label>
                                            <select class="form-select" id="difficulty" name="difficulty">
                                                <option value="easy" <?php echo ($question['difficulty'] === 'easy') ? 'selected' : ''; ?>>Facile</option>
                                                <option value="medium" <?php echo ($question['difficulty'] === 'medium') ? 'selected' : ''; ?>>Moyenne</option>
                                                <option value="hard" <?php echo ($question['difficulty'] === 'hard') ? 'selected' : ''; ?>>Difficile</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Champ caché pour l'ID de l'examen -->
                                <input type="hidden" name="exam_id" value="<?php echo $question['exam_id']; ?>">
                                
                                <!-- Section pour les options de QCM -->
                                <div id="mcq_options" class="question-type-section" <?php echo ($question['type'] !== QUESTION_MCQ) ? 'style="display: none;"' : ''; ?>>
                                    <h5 class="mt-4 mb-3">Options de réponse</h5>
                                    
                                    <div class="options-container">
                                        <?php 
                                        if (!empty($options) && $question['type'] === QUESTION_MCQ): 
                                            foreach ($options as $index => $option):
                                        ?>
                                            <div class="option-row mb-2">
                                                <div class="input-group">
                                                    <div class="input-group-text">
                                                        <input type="checkbox" name="correct_options[]" value="<?php echo $index; ?>" <?php echo ($option['is_correct'] == 1) ? 'checked' : ''; ?>>
                                                    </div>
                                                    <input type="text" class="form-control" name="options[]" value="<?php echo htmlspecialchars($option['option_text']); ?>" placeholder="Option de réponse">
                                                    <button type="button" class="btn btn-outline-danger remove-option">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        <?php 
                                            endforeach; 
                                        else:
                                            // Afficher des options vides si c'est un nouveau QCM
                                            for ($i = 0; $i < 4; $i++):
                                        ?>
                                            <div class="option-row mb-2">
                                                <div class="input-group">
                                                    <div class="input-group-text">
                                                        <input type="checkbox" name="correct_options[]" value="<?php echo $i; ?>">
                                                    </div>
                                                    <input type="text" class="form-control" name="options[]" placeholder="Option de réponse">
                                                    <button type="button" class="btn btn-outline-danger remove-option">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        <?php 
                                            endfor;
                                        endif; 
                                        ?>
                                    </div>
                                    
                                    <button type="button" class="btn btn-outline-primary mt-2" id="add_option">
                                        <i class="fas fa-plus me-1"></i> Ajouter une option
                                    </button>
                                </div>
                                
                                <!-- Section pour les questions vrai/faux -->
                                <div id="true_false_options" class="question-type-section" <?php echo ($question['type'] !== QUESTION_TRUE_FALSE) ? 'style="display: none;"' : ''; ?>>
                                    <h5 class="mt-4 mb-3">Réponse correcte</h5>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="correct_answer" id="true_option" value="true" <?php echo ($question['correct_answer'] === 'true') ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="true_option">Vrai</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="correct_answer" id="false_option" value="false" <?php echo ($question['correct_answer'] === 'false') ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="false_option">Faux</label>
                                    </div>
                                </div>
                                
                                <!-- Section pour les questions à réponse courte -->
                                <div id="short_answer_options" class="question-type-section" <?php echo ($question['type'] !== QUESTION_SHORT_ANSWER) ? 'style="display: none;"' : ''; ?>>
                                    <h5 class="mt-4 mb-3">Réponse correcte</h5>
                                    <div class="mb-3">
                                        <input type="text" class="form-control" name="correct_answer" value="<?php echo htmlspecialchars($question['correct_answer']); ?>" placeholder="Réponse attendue">
                                        <div class="form-text">Entrez la réponse exacte attendue. Les réponses des étudiants seront comparées à celle-ci.</div>
                                    </div>
                                </div>
                                
                                <!-- Section pour les questions à développement -->
                                <div id="essay_options" class="question-type-section" <?php echo ($question['type'] !== QUESTION_ESSAY) ? 'style="display: none;"' : ''; ?>>
                                    <h5 class="mt-4 mb-3">Critères d'évaluation</h5>
                                    <div class="mb-3">
                                        <textarea class="form-control" name="grading_criteria" rows="3" placeholder="Critères d'évaluation pour cette question"><?php echo htmlspecialchars($question['grading_criteria'] ?? ''); ?></textarea>
                                        <div class="form-text">Ces critères seront utilisés pour noter les réponses des étudiants.</div>
                                    </div>
                                </div>
                                
                                <!-- Section pour les questions d'appariement -->
                                <div id="matching_options" class="question-type-section" <?php echo ($question['type'] !== QUESTION_MATCHING) ? 'style="display: none;"' : ''; ?>>
                                    <h5 class="mt-4 mb-3">Éléments à apparier</h5>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <h6>Éléments</h6>
                                            <div class="options-container">
                                                <?php 
                                                if (!empty($options) && $question['type'] === QUESTION_MATCHING): 
                                                    foreach ($options as $index => $option):
                                                ?>
                                                    <div class="option-row mb-2">
                                                        <div class="input-group">
                                                            <input type="text" class="form-control" name="options[]" value="<?php echo htmlspecialchars($option['option_text']); ?>" placeholder="Élément">
                                                            <button type="button" class="btn btn-outline-danger remove-option">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                <?php 
                                                    endforeach; 
                                                else:
                                                    // Afficher des options vides
                                                    for ($i = 0; $i < 4; $i++):
                                                ?>
                                                    <div class="option-row mb-2">
                                                        <div class="input-group">
                                                            <input type="text" class="form-control" name="options[]" placeholder="Élément">
                                                            <button type="button" class="btn btn-outline-danger remove-option">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                <?php 
                                                    endfor;
                                                endif; 
                                                ?>
                                            </div>
                                            <button type="button" class="btn btn-outline-primary mt-2" id="add_matching_option">
                                                <i class="fas fa-plus me-1"></i> Ajouter un élément
                                            </button>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <h6>Correspondances</h6>
                                            <div class="matches-container">
                                                <?php 
                                                // Récupérer les correspondances si elles existent
                                                $matches = [];
                                                if ($question['type'] === QUESTION_MATCHING) {
                                                    $matchesSql = "SELECT * FROM question_matches WHERE question_id = ? ORDER BY match_order ASC";
                                                    $matchesStmt = $conn->prepare($matchesSql);
                                                    $matchesStmt->bind_param("i", $questionId);
                                                    $matchesStmt->execute();
                                                    $matchesResult = $matchesStmt->get_result();
                                                    
                                                    while ($match = $matchesResult->fetch_assoc()) {
                                                        $matches[] = $match;
                                                    }
                                                }
                                                
                                                if (!empty($matches)): 
                                                    foreach ($matches as $index => $match):
                                                ?>
                                                    <div class="match-row mb-2">
                                                        <div class="input-group">
                                                            <input type="text" class="form-control" name="matches[]" value="<?php echo htmlspecialchars($match['match_text']); ?>" placeholder="Correspondance">
                                                            <button type="button" class="btn btn-outline-danger remove-match">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                <?php 
                                                    endforeach; 
                                                else:
                                                    // Afficher des correspondances vides
                                                    for ($i = 0; $i < 4; $i++):
                                                ?>
                                                    <div class="match-row mb-2">
                                                        <div class="input-group">
                                                            <input type="text" class="form-control" name="matches[]" placeholder="Correspondance">
                                                            <button type="button" class="btn btn-outline-danger remove-match">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                <?php 
                                                    endfor;
                                                endif; 
                                                ?>
                                            </div>
                                            <button type="button" class="btn btn-outline-primary mt-2" id="add_match">
                                                <i class="fas fa-plus me-1"></i> Ajouter une correspondance
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Associations correctes</label>
                                        <div class="form-text mb-2">Utilisez la section ci-dessous pour définir les associations correctes entre les éléments et les correspondances.</div>
                                        
                                        <div id="associations_container">
                                            <?php
                                            // Récupérer les associations si elles existent
                                            $associations = [];
                                            if ($question['type'] === QUESTION_MATCHING) {
                                                $assocSql = "SELECT * FROM question_associations WHERE question_id = ?";
                                                $assocStmt = $conn->prepare($assocSql);
                                                $assocStmt->bind_param("i", $questionId);
                                                $assocStmt->execute();
                                                $assocResult = $assocStmt->get_result();
                                                
                                                while ($assoc = $assocResult->fetch_assoc()) {
                                                    $associations[] = $assoc;
                                                }
                                            }
                                            
                                            if (!empty($associations)):
                                                foreach ($associations as $index => $assoc):
                                            ?>
                                                <div class="association-row mb-2">
                                                    <div class="input-group">
                                                        <select class="form-select" name="association_options[]">
                                                            <?php foreach ($options as $opt): ?>
                                                                <option value="<?php echo $opt['id']; ?>" <?php echo ($assoc['option_id'] == $opt['id']) ? 'selected' : ''; ?>>
                                                                    <?php echo htmlspecialchars($opt['option_text']); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <span class="input-group-text"><i class="fas fa-arrow-right"></i></span>
                                                        <select class="form-select" name="association_matches[]">
                                                            <?php foreach ($matches as $match): ?>
                                                                <option value="<?php echo $match['id']; ?>" <?php echo ($assoc['match_id'] == $match['id']) ? 'selected' : ''; ?>>
                                                                    <?php echo htmlspecialchars($match['match_text']); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <button type="button" class="btn btn-outline-danger remove-association">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            <?php 
                                                endforeach;
                                            endif; 
                                            ?>
                                        </div>
                                        
                                        <button type="button" class="btn btn-outline-primary mt-2" id="add_association">
                                            <i class="fas fa-plus me-1"></i> Ajouter une association
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="mb-3 mt-4">
                                    <label for="explanation" class="form-label">Explication (facultatif)</label>
                                    <textarea class="form-control" id="explanation" name="explanation" rows="3" placeholder="Explication de la réponse correcte"><?php echo htmlspecialchars($question['explanation']); ?></textarea>
                                    <div class="form-text">Cette explication sera montrée aux étudiants après l'examen.</div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h5 class="mb-0">Image (facultatif)</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if (!empty($question['image_path'])): ?>
                                            <div class="current-image mb-3">
                                                <img src="<?php echo '../' . $question['image_path']; ?>" alt="Image de la question" class="img-fluid mb-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="remove_image" name="remove_image">
                                                    <label class="form-check-label" for="remove_image">
                                                        Supprimer cette image
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="mb-3">
                                            <label for="question_image" class="form-label">Télécharger une image</label>
                                            <input class="form-control" type="file" id="question_image" name="question_image" accept="image/*">
                                            <div class="form-text">Formats acceptés : JPG, JPEG, PNG, GIF. Taille max : 5 MB.</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h5 class="mb-0">Aperçu</h5>
                                    </div>
                                    <div class="card-body">
                                        <div id="question_preview">
                                            <p class="fw-bold">Question :</p>
                                            <p id="preview_text"><?php echo htmlspecialchars($question['question_text']); ?></p>
                                            
                                            <?php if (!empty($question['image_path'])): ?>
                                                <div class="text-center my-3">
                                                    <img src="<?php echo '../' . $question['image_path']; ?>" alt="Image de la question" class="img-fluid" style="max-height: 200px;">
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div id="preview_options" class="mt-3">
                                                <!-- Les options seront générées dynamiquement par JavaScript -->
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0">Paramètres avancés</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label for="tags" class="form-label">Tags</label>
                                            <input type="text" class="form-control" id="tags" name="tags" value="<?php echo htmlspecialchars($question['tags'] ?? ''); ?>" placeholder="ex: algèbre, équations">
                                            <div class="form-text">Séparez les tags par des virgules.</div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="time_limit" class="form-label">Temps recommandé (secondes)</label>
                                            <input type="number" class="form-control" id="time_limit" name="time_limit" value="<?php echo $question['time_limit'] ?? 60; ?>" min="0">
                                            <div class="form-text">Temps recommandé pour répondre à cette question (0 = pas de limite).</div>
                                        </div>
                                        
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="shuffle_options" name="shuffle_options" <?php echo ($question['shuffle_options'] ?? 0) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="shuffle_options">Mélanger les options</label>
                                        </div>
                                        
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="partial_credit" name="partial_credit" <?php echo ($question['partial_credit'] ?? 0) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="partial_credit">Crédit partiel</label>
                                            <div class="form-text">Permet d'attribuer des points partiels pour les réponses partiellement correctes.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between mt-4">
                            <a href="view-exam.php?id=<?php echo $question['exam_id']; ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i> Annuler
                            </a>
                            <div>
                                <button type="submit" name="save_draft" class="btn btn-primary me-2">
                                    <i class="fas fa-save me-1"></i> Enregistrer les modifications
                                </button>
                                <a href="preview-question.php?id=<?php echo $questionId; ?>" class="btn btn-outline-info" target="_blank">
                                    <i class="fas fa-eye me-1"></i> Prévisualiser
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialisation de l'éditeur TinyMCE
    if (typeof tinymce !== 'undefined') {
        tinymce.init({
            selector: '#question_text',
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
            content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; font-size: 16px; }',
            setup: function(editor) {
                editor.on('change', function() {
                    updatePreview();
                });
            }
        });
    }
    
    // Gestion du changement de type de question
    const questionTypeSelect = document.getElementById('question_type');
    const questionTypeSections = document.querySelectorAll('.question-type-section');
    
    questionTypeSelect.addEventListener('change', function() {
        // Cacher toutes les sections
        questionTypeSections.forEach(section => {
            section.style.display = 'none';
        });
        
        // Afficher la section correspondante au type sélectionné
        const selectedType = this.value;
        const sectionToShow = document.getElementById(selectedType + '_options');
        if (sectionToShow) {
            sectionToShow.style.display = 'block';
        }
        
        updatePreview();
    });
    
    // Ajouter une option pour les QCM
    document.getElementById('add_option').addEventListener('click', function() {
        const optionsContainer = document.querySelector('#mcq_options .options-container');
        const optionCount = optionsContainer.querySelectorAll('.option-row').length;
        
        const optionRow = document.createElement('div');
        optionRow.className = 'option-row mb-2';
        optionRow.innerHTML = `
            <div class="input-group">
                <div class="input-group-text">
                    <input type="checkbox" name="correct_options[]" value="${optionCount}">
                </div>
                <input type="text" class="form-control" name="options[]" placeholder="Option de réponse">
                <button type="button" class="btn btn-outline-danger remove-option">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        optionsContainer.appendChild(optionRow);
        
        // Ajouter l'événement de suppression
        optionRow.querySelector('.remove-option').addEventListener('click', function() {
            optionRow.remove();
            updatePreview();
        });
        
        updatePreview();
    });
    
    // Supprimer une option
    document.querySelectorAll('.remove-option').forEach(button => {
        button.addEventListener('click', function() {
            this.closest('.option-row').remove();
            updatePreview();
        });
    });
    
    // Ajouter un élément pour les questions d'appariement
    document.getElementById('add_matching_option').addEventListener('click', function() {
        const optionsContainer = document.querySelector('#matching_options .options-container');
        
        const optionRow = document.createElement('div');
        optionRow.className = 'option-row mb-2';
        optionRow.innerHTML = `
            <div class="input-group">
                <input type="text" class="form-control" name="options[]" placeholder="Élément">
                <button type="button" class="btn btn-outline-danger remove-option">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        optionsContainer.appendChild(optionRow);
        
        // Ajouter l'événement de suppression
        optionRow.querySelector('.remove-option').addEventListener('click', function() {
            optionRow.remove();
        });
    });
    
    // Ajouter une correspondance pour les questions d'appariement
    document.getElementById('add_match').addEventListener('click', function() {
        const matchesContainer = document.querySelector('#matching_options .matches-container');
        
        const matchRow = document.createElement('div');
        matchRow.className = 'match-row mb-2';
        matchRow.innerHTML = `
            <div class="input-group">
                <input type="text" class="form-control" name="matches[]" placeholder="Correspondance">
                <button type="button" class="btn btn-outline-danger remove-match">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        matchesContainer.appendChild(matchRow);
        
        // Ajouter l'événement de suppression
        matchRow.querySelector('.remove-match').addEventListener('click', function() {
            matchRow.remove();
        });
    });
    
    // Supprimer une correspondance
    document.querySelectorAll('.remove-match').forEach(button => {
        button.addEventListener('click', function() {
            this.closest('.match-row').remove();
        });
    });
    
    // Fonction pour mettre à jour l'aperçu
    function updatePreview() {
        const questionText = document.getElementById('question_text').value;
        const questionType = document.getElementById('question_type').value;
        
        // Mettre à jour le texte de la question
        document.getElementById('preview_text').textContent = questionText;
        
        // Mettre à jour les options en fonction du type de question
        const previewOptions = document.getElementById('preview_options');
        previewOptions.innerHTML = '';
        
        if (questionType === 'mcq') {
            const options = document.querySelectorAll('#mcq_options .option-row input[name="options[]"]');
            const correctOptions = Array.from(document.querySelectorAll('#mcq_options .option-row input[name="correct_options[]"]:checked')).map(cb => cb.value);
            
            if (options.length > 0) {
                const optionsList = document.createElement('div');
                optionsList.className = 'list-group';
                
                options.forEach((option, index) => {
                    if (option.value.trim() !== '') {
                        const optionItem = document.createElement('div');
                        optionItem.className = 'list-group-item';
                        
                        const isCorrect = correctOptions.includes(index.toString());
                        if (isCorrect) {
                            optionItem.classList.add('list-group-item-success');
                        }
                        
                        optionItem.innerHTML = `
                            <div class="form-check">
                                <input class="form-check-input" type="radio" disabled ${isCorrect ? 'checked' : ''}>
                                <label class="form-check-label">${option.value}</label>
                            </div>
                        `;
                        
                        optionsList.appendChild(optionItem);
                    }
                });
                
                previewOptions.appendChild(optionsList);
            }
        } else if (questionType === 'true_false') {
            const correctAnswer = document.querySelector('input[name="correct_answer"]:checked')?.value;
            
            const optionsList = document.createElement('div');
            optionsList.className = 'list-group';
            
            const trueOption = document.createElement('div');
            trueOption.className = 'list-group-item';
            if (correctAnswer === 'true') {
                trueOption.classList.add('list-group-item-success');
            }
            trueOption.innerHTML = `
                <div class="form-check">
                    <input class="form-check-input" type="radio" disabled ${correctAnswer === 'true' ? 'checked' : ''}>
                    <label class="form-check-label">Vrai</label>
                </div>
            `;
            
            const falseOption = document.createElement('div');
            falseOption.className = 'list-group-item';
            if (correctAnswer === 'false') {
                falseOption.classList.add('list-group-item-success');
            }
            falseOption.innerHTML = `
                <div class="form-check">
                    <input class="form-check-input" type="radio" disabled ${correctAnswer === 'false' ? 'checked' : ''}>
                    <label class="form-check-label">Faux</label>
                </div>
            `;
            
            optionsList.appendChild(trueOption);
            optionsList.appendChild(falseOption);
            previewOptions.appendChild(optionsList);
        } else if (questionType === 'short_answer') {
            const correctAnswer = document.querySelector('#short_answer_options input[name="correct_answer"]').value;
            
            const answerBox = document.createElement('div');
            answerBox.className = 'card';
            answerBox.innerHTML = `
                <div class="card-body">
                    <p class="mb-2"><strong>Réponse attendue :</strong></p>
                    <p class="mb-0">${correctAnswer || '(Aucune réponse définie)'}</p>
                </div>
            `;
            
            previewOptions.appendChild(answerBox);
        } else if (questionType === 'essay') {
            const answerBox = document.createElement('div');
            answerBox.className = 'card';
            answerBox.innerHTML = `
                <div class="card-body">
                    <p class="mb-0"><em>Cette question nécessite une réponse développée qui sera notée manuellement.</em></p>
                </div>
            `;
            
            previewOptions.appendChild(answerBox);
        }
    }
    
    // Initialiser l'aperçu au chargement de la page
    updatePreview();
    
    // Validation du formulaire avant soumission
    document.getElementById('questionForm').addEventListener('submit', function(event) {
        const questionType = document.getElementById('question_type').value;
        
        if (questionType === 'mcq') {
            // Vérifier qu'au moins une option est définie
            const options = document.querySelectorAll('#mcq_options .option-row input[name="options[]"]');
            let hasOptions = false;
            
            options.forEach(option => {
                if (option.value.trim() !== '') {
                    hasOptions = true;
                }
            });
            
            if (!hasOptions) {
                event.preventDefault();
                alert('Veuillez ajouter au moins une option de réponse pour cette question à choix multiple.');
                return;
            }
            
            // Vérifier qu'au moins une option est marquée comme correcte
            const correctOptions = document.querySelectorAll('#mcq_options .option-row input[name="correct_options[]"]:checked');
            if (correctOptions.length === 0) {
                event.preventDefault();
                alert('Veuillez sélectionner au moins une option correcte pour cette question à choix multiple.');
                return;
            }
        } else if (questionType === 'true_false') {
            // Vérifier qu'une réponse est sélectionnée
            const correctAnswer = document.querySelector('input[name="correct_answer"]:checked');
            if (!correctAnswer) {
                event.preventDefault();
                alert('Veuillez sélectionner la réponse correcte (Vrai ou Faux).');
                return;
            }
        } else if (questionType === 'short_answer') {
            // Vérifier qu'une réponse est définie
            const correctAnswer = document.querySelector('#short_answer_options input[name="correct_answer"]').value;
            if (correctAnswer.trim() === '') {
                event.preventDefault();
                alert('Veuillez définir la réponse attendue pour cette question à réponse courte.');
                return;
            }
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
