<?php
// Inclure les fichiers de configuration et fonctions
include_once 'includes/config.php';
include_once 'includes/functions.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Initialiser les variables
$success = false;
$error = '';
$form_data = [
    'name' => '',
    'email' => '',
    'subject' => '',
    'message' => ''
];

// Traiter le formulaire de contact
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer les données du formulaire
    $form_data['name'] = isset($_POST['name']) ? clean_input($_POST['name']) : '';
    $form_data['email'] = isset($_POST['email']) ? clean_input($_POST['email']) : '';
    $form_data['subject'] = isset($_POST['subject']) ? clean_input($_POST['subject']) : '';
    $form_data['message'] = isset($_POST['message']) ? clean_input($_POST['message']) : '';

    // Validation
    if (empty($form_data['name'])) {
        $error = 'Veuillez entrer votre nom.';
    } elseif (empty($form_data['email'])) {
        $error = 'Veuillez entrer votre adresse email.';
    } elseif (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Veuillez entrer une adresse email valide.';
    } elseif (empty($form_data['subject'])) {
        $error = 'Veuillez entrer un sujet.';
    } elseif (empty($form_data['message'])) {
        $error = 'Veuillez entrer votre message.';
    } else {

        // Envoyer un email de confirmation
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = SMTP_SECURE;
            $mail->Port       = SMTP_PORT;

            $mail->setFrom($form_data['email'], FROM_NAME);
            $mail->addAddress(FROM_EMAIL);
            // Envoyer l'email
            $mail->isHTML(true);
            $mail->Subject = 'Contact ExamSafe: ' . $form_data['subject'];
            $mail->Body = '
            <html>
            <head>
                <title>Nouveau message de contact</title>
            </head>
            <body>
                <h2>Nouveau message de contact</h2>
                <p><strong>Nom:</strong> ' . htmlspecialchars($form_data['name']) . '</p>
                <p><strong>Email:</strong> ' . htmlspecialchars($form_data['email']) . '</p>
                <p><strong>Sujet:</strong> ' . htmlspecialchars($form_data['subject']) . '</p>
                <p><strong>Message:</strong></p>
                <p>' . nl2br(htmlspecialchars($form_data['message'])) . '</p>
            </body>
            </html>
        ';

            $mail->send();
            $success = 'Merci de nous avoir contacté ! Nous allons vous répondre sous peu.';
        } catch (Exception $e) {
            $error = 'Une erreur est survenue lors de l\'envoi de votre message. Veuillez réessayer.';
            // Réinitialiser le formulaire
            $form_data = [
                'name' => '',
                'email' => '',
                'subject' => '',
                'message' => ''
            ];
        }
        // $headers = [
        //     'From' => $form_data['name'] . ' <' . $form_data['email'] . '>',
        //     'Reply-To' => $form_data['email']
        // ];
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact - ExamSafe</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>

<body>
    <!-- En-tête -->
    <?php include 'includes/header.php'; ?>

    <!-- Section Contact -->
    <section class="about-hero">
        <div class="container">
            <div class="about-content" style="text-align: center;">
                <h1>Contactez-nous</h1>
                <p>Vous avez des questions sur ExamSafe ? Vous souhaitez organiser une démonstration ou discuter de vos besoins spécifiques ? N'hésitez pas à nous contacter. Notre équipe est là pour vous aider.</p>
            </div>
        </div>
    </section>

    <!-- Formulaire de contact -->
    <section class="features" style="margin-top: -30px;" >
        <div class="container" >
            <div class="section-header" style="text-align: center;width:100%">
                <h2>Envoyez-nous un message</h2>
                <p>Nous vous répondrons dans les plus brefs délais</p>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success" style="text-align: center;width:100%">
                    <i class="fas fa-check-circle"></i>
                    Votre message a été envoyé avec succès. Nous vous répondrons dans les plus brefs délais.
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <div class="contact-form">
                <form action="contact.php" method="post">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Nom complet</label>
                            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($form_data['name']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($form_data['email']); ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="subject">Sujet</label>
                        <input type="text" id="subject" name="subject" value="<?php echo htmlspecialchars($form_data['subject']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="message">Message</label>
                        <textarea id="message" name="message" required><?php echo htmlspecialchars($form_data['message']); ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">Envoyer le message</button>
                </form>
            </div>

            <div class="contact-info">
                <div class="section-header">
                    <h2>Informations de contact</h2>
                    <p>Vous préférez nous contacter directement ?</p>
                </div>

                <div class="features-grid">
                    <div class="contact-info-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <div class="contact-info-content">
                            <h3>Adresse</h3>
                            <p>123 Avenue des Examens<br>Douala, Cameroun</p>
                        </div>
                    </div>

                    <div class="contact-info-item">
                        <i class="fas fa-envelope"></i>
                        <div class="contact-info-content">
                            <h3>Email</h3>
                            <p>contact@examsafe.com<br>support@examsafe.com</p>
                        </div>
                    </div>

                    <div class="contact-info-item">
                        <i class="fas fa-phone-alt"></i>
                        <div class="contact-info-content">
                            <h3>Téléphone</h3>
                            <p>+237 6 23 45 67 89<br>+237 6 98 76 54 32</p>
                        </div>
                    </div>

                    <div class="contact-info-item">
                        <i class="fas fa-clock"></i>
                        <div class="contact-info-content">
                            <h3>Horaires</h3>
                            <p>Lundi - Vendredi: 9h - 18h<br>Samedi: 9h - 13h</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Section carte -->
    <section class="section">
        <div class="container">
            <div class="section-header">
                <h2>Nous trouver</h2>
                <p>Rendez-nous visite dans nos locaux</p>
            </div>

            <div style="width: 100%; height: 400px; border-radius: var(--border-radius); overflow: hidden;">
                <!-- Intégrez ici votre carte Google Maps ou OpenStreetMap -->
                <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d127482.8610673536!2d9.6859644!3d4.0510564!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x1061126c54d5c89b%3A0x9ae59a89850dad6e!2sDouala%2C%20Cameroun!5e0!3m2!1sfr!2sfr!4v1620637891673!5m2!1sfr!2sfr" width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
            </div>
        </div>
    </section>
    <br><br>

    <!-- Pied de page -->
    <?php include 'includes/footer.php'; ?>

    <!-- Scripts JS -->
    <script src="assets/js/main.js"></script>
</body>

</html>