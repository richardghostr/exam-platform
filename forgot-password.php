<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 1. Initialisation
if (isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$pageTitle = "Réinitialisation de mot de passe";
$success = false;
$error = null;




// 4. Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $conn->real_escape_string(clean($_POST['email']));

    // Vérification utilisateur
    $result = $conn->query("SELECT id, first_name, email FROM users WHERE email = '$email' LIMIT 1");

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();

        // Génération token sécurisé
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Mise à jour DB
        if ($conn->query("UPDATE users SET reset_token='$token', reset_token_expires='$expires' WHERE id={$user['id']}")) {

            // 5. Envoi email avec PHPMailer
            $mail = new PHPMailer(true);

            try {
                // Configuration SMTP
                $mail->isSMTP();
                $mail->Host       = SMTP_HOST;
                $mail->SMTPAuth   = true;
                $mail->Username   = SMTP_USER;
                $mail->Password   = SMTP_PASS;
                $mail->SMTPSecure = SMTP_SECURE;
                $mail->Port       = SMTP_PORT;
                $mail->CharSet    = 'UTF-8';

                // Expéditeur/Destinataire
                $mail->setFrom(FROM_EMAIL, FROM_NAME);
                $mail->addAddress($user['email'], $user['first_name']);

                // Contenu email
                $resetLink = SITE_URL . 'reset-password.php?token=' . $token;

                $mail->isHTML(true);
                $mail->Subject = SITE_NAME . ' - Réinitialisation de mot de passe';

                // Template HTML
                ob_start();
                include 'templates/email-reset-password.php';
                $mail->Body = ob_get_clean();

                // Version texte
                $mail->AltBody = "Bonjour {$user['first_name']},\n\n"
                    . "Cliquez ici pour réinitialiser votre mot de passe:\n"
                    . "$resetLink\n\n"
                    . "Lien valable 1 heure.\n\n"
                    . "Si vous n'avez pas fait cette demande, ignorez cet email.";

                $mail->send();
                $success = true;
            } catch (Exception $e) {
                $error = "Erreur d'envoi d'email. Veuillez réessayer.";
                error_log("SMTP Error: " . $mail->ErrorInfo);
                $conn->query("UPDATE users SET reset_token=NULL, reset_token_expires=NULL WHERE id={$user['id']}");
            }
        } else {
            $error = "Erreur base de données. Veuillez réessayer.";
        }
    } else {
        $error = "Aucun compte trouvé avec cette adresse email.";
    }
}


?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - ExamSafe</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>

<body>
    <?php // 6. Affichage
    include 'includes/header.php'; ?>


    <div class="container mt-5" style="margin-top: 160px;margin-bottom: 80px;">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><?= htmlspecialchars($pageTitle) ?></h4>
                    </div>

                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success" style="text-align:center">
                                <i class="fas fa-check-circle"></i> Email envoyé!
                                Un lien de réinitialisation a été envoyé à <strong>&nbsp; <?= htmlspecialchars($email) ?> &nbsp; .</strong>
                                Vérifiez vos spams si vous ne voyez pas l'email
                            </div>
                        <?php else: ?>
                            <form method="POST">
                                <div class="form-group">
                                    <label for="email">Votre adresse email</label>
                                    <input type="email" class="form-control" id="email" name="email" required
                                        placeholder="exemple@domaine.com">
                                </div>

                                <button type="submit" class="btn btn-primary btn-block" style="padding: 20px;">
                                    <i class="fas fa-paper-plane"></i> Envoyer les instructions
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
<br>
                    <div class="card-footer text-center" >
                        <a href="login.php" class="btn btn-outline btn-social">
                            <i class="fas fa-arrow-left"></i> Retour à la connexion
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php
    include 'includes/footer.php';
    ?>

</body>
</html>