<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once'includes/db.php';

// Rediriger si l'utilisateur est déjà connecté
if (isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$pageTitle = "Mot de passe oublié";
$success = false;
$error = null;


// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = clean($_POST['email']);
    
    // Vérifier si l'email existe
    $stmt = $conn->prepare("SELECT id, first_name, email FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if ($user) {
        // Générer un token sécurisé
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Mettre à jour l'utilisateur avec le token
        $stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?");
        $stmt->bind_param('ssi', $token, $expires, $user['id']);
        
        if ($stmt->execute()) {
            // Préparer l'email
            $resetLink = SITE_URL . 'reset-password.php?token=' . $token;
            $subject = "Réinitialisation de votre mot de passe";
            $message = "Bonjour " . $user['first_name'] . ",\n\n";
            $message .= "Pour réinitialiser votre mot de passe, cliquez sur le lien suivant :\n";
            $message .= $resetLink . "\n\n";
            $message .= "Ce lien expirera dans 1 heure.\n";
            $message .= "Si vous n'avez pas demandé cette réinitialisation, ignorez simplement cet email.\n\n";
            $message .= "Cordialement,\nL'équipe " . SITE_NAME;
            
            // Envoyer l'email
            $headers = "From: " . EMAIL_FROM;
            if (mail($user['email'], $subject, $message, $headers)) {
                $success = true;
            } else {
                $error = "Une erreur est survenue lors de l'envoi de l'email.";
            }
        } else {
            $error = "Une erreur est survenue lors de la génération du lien de réinitialisation.";
        }
        $stmt->close();
    } else {
        $error = "Aucun compte trouvé avec cette adresse email.";
    }
}

include 'includes/header.php';
?>

<div class="breadcrumb">
    <div class="container">
        <ul>
            <li><a href="index.php">Accueil</a></li>
            <li><a href="login.php">Connexion</a></li>
            <li>Mot de passe oublié</li>
        </ul>
    </div>
</div>

<section class="login-page">
    <div class="container">
        <h1 class="page-title">Réinitialisation du mot de passe</h1>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                Un email avec les instructions de réinitialisation a été envoyé à <?= htmlspecialchars($email) ?>.
                <p>Si vous ne voyez pas l'email, vérifiez votre dossier spam.</p>
            </div>
        <?php else: ?>
            <div class="login-form-container">
                <form action="forgot-password.php" method="POST" class="login-form">
                    <div class="form-group">
                        <label for="email">Adresse email</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Envoyer les instructions</button>
                    </div>
                    
                    <div class="form-footer">
                        <p><a href="login.php">Retour à la connexion</a></p>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</section>
