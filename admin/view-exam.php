<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
include 'includes/header.php';


// Vérifier si l'ID de l'examen est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: manage-exams.php');
    exit;
}

$exam_id = $_GET['id'];

// Récupérer les détails de l'examen
$stmt = $conn->prepare("SELECT e.*, c.course_name, u.full_name AS creator_name 
                        FROM exams e 
                        LEFT JOIN courses c ON e.course_id = c.id 
                        LEFT JOIN users u ON e.created_by = u.id 
                        WHERE e.id = ?");
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<script>alert('Examen non trouvé'); window.location.href='manage-exams.php';</script>";
    exit;
}

$exam = $result->fetch_assoc();

// Récupérer les questions de l'examen
$stmt = $conn->prepare("SELECT q.*, qt.type_name 
                        FROM questions q 
                        LEFT JOIN question_types qt ON q.question_type_id = qt.id 
                        WHERE q.exam_id = ?
                        ORDER BY q.question_order");
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$questions_result = $stmt->get_result();
?>

<div class="card mb-20">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Détails de l'examen</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="index.php">Accueil</a></li>
                        <li class="breadcrumb-item"><a href="manage-exams.php">Examens</a></li>
                        <li class="breadcrumb-item active">Détails de l'examen</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><?php echo htmlspecialchars($exam['title']); ?></h3>
                            <div class="card-tools">
                                <a href="edit-exam.php?id=<?php echo $exam_id; ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-edit"></i> Modifier l'examen
                                </a>
                                <a href="add-questions.php?exam_id=<?php echo $exam_id; ?>" class="btn btn-success btn-sm ml-2">
                                    <i class="fas fa-plus"></i> Ajouter des questions
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-bordered">
                                        <tr>
                                            <th style="width: 30%">Titre</th>
                                            <td><?php echo htmlspecialchars($exam['title']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Description</th>
                                            <td><?php echo htmlspecialchars($exam['description']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Cours</th>
                                            <td><?php echo htmlspecialchars($exam['course_name']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Durée</th>
                                            <td><?php echo htmlspecialchars($exam['duration']); ?> minutes</td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-bordered">
                                        <tr>
                                            <th style="width: 30%">Date de début</th>
                                            <td><?php echo date('d/m/Y H:i', strtotime($exam['start_time'])); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Date de fin</th>
                                            <td><?php echo date('d/m/Y H:i', strtotime($exam['end_time'])); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Points totaux</th>
                                            <td><?php echo htmlspecialchars($exam['total_points']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Créé par</th>
                                            <td><?php echo htmlspecialchars($exam['creator_name']); ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <div class="row mt-4">
                                <div class="col-md-12">
                                    <div class="card card-primary card-outline">
                                        <div class="card-header">
                                            <h3 class="card-title">Questions de l'examen</h3>
                                            <span class="badge badge-info float-right">
                                                <?php echo $questions_result->num_rows; ?> questions
                                            </span>
                                        </div>
                                        <div class="card-body p-0">
                                            <?php if ($questions_result->num_rows > 0): ?>
                                                <table class="table table-striped">
                                                    <thead>
                                                        <tr>
                                                            <th style="width: 5%">#</th>
                                                            <th style="width: 50%">Question</th>
                                                            <th style="width: 15%">Type</th>
                                                            <th style="width: 10%">Points</th>
                                                            <th style="width: 20%">Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php 
                                                        $counter = 1;
                                                        while ($question = $questions_result->fetch_assoc()): 
                                                        ?>
                                                            <tr>
                                                                <td><?php echo $counter++; ?></td>
                                                                <td><?php echo htmlspecialchars($question['question_text']); ?></td>
                                                                <td><?php echo htmlspecialchars($question['type_name']); ?></td>
                                                                <td><?php echo htmlspecialchars($question['points']); ?></td>
                                                                <td>
                                                                    <button type="button" class="btn btn-info btn-sm view-question" data-id="<?php echo $question['id']; ?>">
                                                                        <i class="fas fa-eye"></i>
                                                                    </button>
                                                                    <a href="edit-question.php?id=<?php echo $question['id']; ?>" class="btn btn-primary btn-sm">
                                                                        <i class="fas fa-edit"></i>
                                                                    </a>
                                                                    <button type="button" class="btn btn-danger btn-sm delete-question" data-id="<?php echo $question['id']; ?>">
                                                                        <i class="fas fa-trash"></i>
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                        <?php endwhile; ?>
                                                    </tbody>
                                                </table>
                                            <?php else: ?>
                                                <div class="alert alert-info m-3">
                                                    Aucune question n'a été ajoutée à cet examen.
                                                    <a href="add-questions.php?exam_id=<?php echo $exam_id; ?>" class="alert-link">
                                                        Ajouter des questions maintenant
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Modal pour afficher les détails d'une question -->
<div class="modal fade" id="questionModal" tabindex="-1" role="dialog" aria-labelledby="questionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="questionModalLabel">Détails de la question</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="questionDetails">
                <!-- Les détails de la question seront chargés ici via AJAX -->
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="sr-only">Chargement...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Afficher les détails d'une question
    $('.view-question').on('click', function() {
        const questionId = $(this).data('id');
        $('#questionModal').modal('show');
        
        // Ici, vous devriez charger les détails de la question via AJAX
        $('#questionDetails').html('<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="sr-only">Chargement...</span></div></div>');
        
        // Simulation de chargement AJAX (à remplacer par un vrai appel AJAX)
        setTimeout(function() {
            $.ajax({
                url: 'ajax/get_question_details.php',
                type: 'GET',
                data: { id: questionId },
                success: function(response) {
                    $('#questionDetails').html(response);
                },
                error: function() {
                    $('#questionDetails').html('<div class="alert alert-danger">Erreur lors du chargement des détails de la question.</div>');
                }
            });
        }, 500);
    });
    
    // Supprimer une question
    $('.delete-question').on('click', function() {
        if (confirm('Êtes-vous sûr de vouloir supprimer cette question ?')) {
            const questionId = $(this).data('id');
            
            // Ici, vous devriez envoyer une requête AJAX pour supprimer la question
            $.ajax({
                url: 'ajax/delete_question.php',
                type: 'POST',
                data: { id: questionId },
                success: function(response) {
                    if (response.success) {
                        alert('Question supprimée avec succès');
                        location.reload();
                    } else {
                        alert('Erreur lors de la suppression de la question: ' + response.message);
                    }
                },
                error: function() {
                    alert('Erreur lors de la communication avec le serveur');
                }
            });
        }
    });
});
</script>

<?php
include 'includes/footer.php';
?>
