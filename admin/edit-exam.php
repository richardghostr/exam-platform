<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
// Vérifier si l'ID de l'examen est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: manage-exams.php');
    exit;
}

$exam_id = $_GET['id'];
$error_message = '';
$success_message = '';

// Récupérer les détails de l'examen
$stmt = $conn->prepare("SELECT * FROM exams WHERE id = ?");
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<script>alert('Examen non trouvé'); window.location.href='manage-exams.php';</script>";
    exit;
}

$exam = $result->fetch_assoc();

// Récupérer la liste des cours
$courses_result = $conn->query("SELECT id, course_name FROM courses ORDER BY course_name");

// Traitement du formulaire de mise à jour
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $course_id = $_POST['course_id'];
    $duration = $_POST['duration'];
    $start_time = $_POST['start_date'] . ' ' . $_POST['start_time'];
    $end_time = $_POST['end_date'] . ' ' . $_POST['end_time'];
    $passing_percentage = $_POST['passing_percentage'];
    $total_points = $_POST['total_points'];
    $status = $_POST['status'];
    $updated_by = $_SESSION['user_id'];

    // Validation des données
    if (
        empty($title) || empty($description) || empty($course_id) || empty($duration) ||
        empty($start_time) || empty($end_time) || empty($passing_percentage) || empty($total_points)
    ) {
        $error_message = "Tous les champs sont obligatoires.";
    } else {
        // Mise à jour de l'examen
        $update_stmt = $conn->prepare("UPDATE exams SET 
                                      title = ?, 
                                      description = ?, 
                                      course_id = ?, 
                                      duration = ?, 
                                      start_time = ?, 
                                      end_time = ?, 
                                      passing_percentage = ?, 
                                      total_points = ?, 
                                      status = ?, 
                                      updated_by = ?, 
                                      updated_at = NOW() 
                                      WHERE id = ?");

        $update_stmt->bind_param(
            "ssiissdiiii",
            $title,
            $description,
            $course_id,
            $duration,
            $start_time,
            $end_time,
            $passing_percentage,
            $total_points,
            $status,
            $updated_by,
            $exam_id
        );

        if ($update_stmt->execute()) {
            $success_message = "L'examen a été mis à jour avec succès.";

            // Récupérer les données mises à jour
            $stmt->execute();
            $result = $stmt->get_result();
            $exam = $result->fetch_assoc();
        } else {
            $error_message = "Erreur lors de la mise à jour de l'examen: " . $conn->error;
        }
    }
}
// Inclure l'en-tête
include 'includes/header.php';
?>

<div class="card mb-20">
    <div class="content-header">
        <div class="container-fluid">
            <div style="margin-top: 20px;margin-left:20px">
                <div class="page-path">
                    <a href="index.php">Dashboard</a>
                    <span class="separator">/</span>
                    <a href="manage-exams.php">Examens</a>
                    <span class="separator">/</span>
                    <span>Modifier l'examen</span>
                </div>
                <h1 class="page-title">Modifier l'examen</h1>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    <h5><i class="icon fas fa-ban"></i> Erreur!</h5>
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    <h5><i class="icon fas fa-check"></i> Succès!</h5>
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-12">
                    <div class="card card-primary">
                        <div class="card-header">
                            <h3 class="card-title">Informations de l'examen</h3>
                        </div>
                        <form method="post" action="">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="title">Titre de l'examen</label>
                                            <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($exam['title']); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="description">Description</label>
                                            <textarea class="form-control" id="description" name="description" rows="3" required><?php echo htmlspecialchars($exam['description']); ?></textarea>
                                        </div>
                                        <div class="form-group">
                                            <label for="course_id">Cours</label>
                                            <select class="form-control" id="course_id" name="course_id" required>
                                                <option value="">Sélectionner un cours</option>
                                                <?php while ($course = $courses_result->fetch_assoc()): ?>
                                                    <option value="<?php echo $course['id']; ?>" <?php echo ($course['id'] == $exam['course_id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($course['course_name']); ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="duration">Durée (minutes)</label>
                                            <input type="number" class="form-control" id="duration" name="duration" value="<?php echo htmlspecialchars($exam['duration']); ?>" min="1" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="start_date">Date de début</label>
                                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo date('Y-m-d', strtotime($exam['start_time'])); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="start_time">Heure de début</label>
                                            <input type="time" class="form-control" id="start_time" name="start_time" value="<?php echo date('H:i', strtotime($exam['start_time'])); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="end_date">Date de fin</label>
                                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo date('Y-m-d', strtotime($exam['end_time'])); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="end_time">Heure de fin</label>
                                            <input type="time" class="form-control" id="end_time" name="end_time" value="<?php echo date('H:i', strtotime($exam['end_time'])); ?>" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="passing_percentage">Pourcentage de réussite</label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" id="passing_percentage" name="passing_percentage" value="<?php echo htmlspecialchars($exam['passing_percentage']); ?>" min="0" max="100" required>
                                                <div class="input-group-append">
                                                    <span class="input-group-text">%</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="total_points">Points totaux</label>
                                            <input type="number" class="form-control" id="total_points" name="total_points" value="<?php echo htmlspecialchars($exam['total_points']); ?>" min="1" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="status">Statut</label>
                                            <select class="form-control" id="status" name="status" required>
                                                <option value="0" <?php echo ($exam['status'] == 0) ? 'selected' : ''; ?>>Brouillon</option>
                                                <option value="1" <?php echo ($exam['status'] == 1) ? 'selected' : ''; ?>>Publié</option>
                                                <option value="2" <?php echo ($exam['status'] == 2) ? 'selected' : ''; ?>>Terminé</option>
                                                <option value="3" <?php echo ($exam['status'] == 3) ? 'selected' : ''; ?>>Archivé</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer">
                                <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                                <a href="view-exam.php?id=<?php echo $exam_id; ?>" class="btn btn-default">Annuler</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
    $(document).ready(function() {
        // Validation des dates
        $('form').on('submit', function(e) {
            const startDate = new Date($('#start_date').val() + 'T' + $('#start_time').val());
            const endDate = new Date($('#end_date').val() + 'T' + $('#end_time').val());

            if (endDate <= startDate) {
                e.preventDefault();
                alert('La date de fin doit être postérieure à la date de début.');
                return false;
            }

            return true;
        });
    });
</script>

<?php
include 'includes/footer.php';
?>