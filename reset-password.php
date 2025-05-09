<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;

// 1. Vérification du token
$token = $_GET['token'] ?? null;
if (!$token) {
    header('Location: forgot-password.php?error=invalid_token');
    exit();
}


// 3. Vérification du token en base
$current_time = date('Y-m-d H:i:s');
$stmt = $conn->prepare("SELECT id, email, reset_token_expires FROM users WHERE reset_token = ? AND reset_token_expires > ?");
$stmt->bind_param('ss', $token, $current_time);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    header('Location: forgot-password.php?error=expired_or_invalid_token');
    exit();
}

// 4. Traitement du formulaire de nouveau mot de passe
$error = null;
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($new_password) || strlen($new_password) < 8) {
        $error = "Le mot de passe doit contenir au moins 8 caractères";
    } elseif ($new_password !== $confirm_password) {
        $error = "Les mots de passe ne correspondent pas";
    } else {
        // Hashage du nouveau mot de passe
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Mise à jour en base
        $update_stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?");
        $update_stmt->bind_param('si', $hashed_password, $user['id']);
        
        if ($update_stmt->execute()) {
            $success = true;
            
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
                
                $mail->setFrom(FROM_EMAIL, FROM_NAME);
                $mail->addAddress($user['email']);
                
                $mail->isHTML(true);
                $mail->Subject = 'Votre mot de passe a été modifié';
                $mail->Body    = "
                    <h2>Confirmation de réinitialisation de mot de passe</h2>
                    <p>Bonjour,</p>
                    <p>Votre mot de passe sur " . SITE_NAME . " a bien été modifié.</p>
                    <p>Si vous n'avez pas effectué cette modification, veuillez contacter immédiatement notre support.</p>
                    <p>Cordialement,<br>L'équipe " . SITE_NAME . "</p>
                ";
                
                $mail->send();
            } catch (Exception $e) {
                error_log("Erreur envoi email confirmation: " . $mail->ErrorInfo);
            }
        } else {
            $error = "Une erreur est survenue lors de la mise à jour du mot de passe";
        }
        $update_stmt->close();
    }
}

// 5. Affichage
$pageTitle = "Réinitialisation du mot de passe";
include 'includes/header.php';
?>

<div class="container mt-5" style="margin-top: 160px;margin-bottom: 50px;">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><?= htmlspecialchars($pageTitle) ?></h4>
                </div>
                
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> Votre mot de passe a été mis à jour avec succès!
                            <p class="mt-3">Vous pouvez maintenant vous <a href="login.php">connecter</a> avec votre nouveau mot de passe.</p>
                        </div>
                    <?php else: ?>
                        <form method="POST">
                            <div class="form-group">
                                <label for="password">Nouveau mot de passe</label>
                                <input type="password" class="form-control" id="password" name="password" required 
                                       minlength="8" placeholder="Minimum 8 caractères">
                                <small class="form-text text-muted">Utilisez un mot de passe fort et unique.</small>
                            </div>
                            
                            <div class="form-group mt-3">
                                <label for="confirm_password">Confirmez le nouveau mot de passe</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required
                                       minlength="8" placeholder="Retapez votre mot de passe">
                            </div>
                            
                            <div class="form-group mt-4">
                                <button type="submit" class="btn btn-primary btn-block" style="padding: 20px;">
                                    <i class="fas fa-save"></i> Enregistrer le nouveau mot de passe
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
                
                <?php if (!$success): ?>
                <div class="card-footer text-center">
                    <a href="login.php" class="btn btn-outline btn-social">
                        <i class="fas fa-arrow-left"></i> Retour à la connexion
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$conn->close();
include 'includes/footer.php';?>

<style>/* ===== GENERAL STYLES ===== */
:root {
  --primary-color: #2563eb;
  --primary-hover: #1d4ed8;
  --secondary-color: #6b7280;
  --light-color: #f9fafb;
  --dark-color: #1f2937;
  --danger-color: #ef4444;
  --success-color: #10b981;
  --border-radius: 0.375rem;
  --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

body {
  font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
  background-color: #f3f4f6;
  color: var(--dark-color);
  line-height: 1.6;
}

.container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 20px;
}

/* ===== CARD STYLES ===== */
.auth-card {
  background-color: white;
  border-radius: var(--border-radius);
  box-shadow: var(--shadow);
  overflow: hidden;
  max-width: 500px;
  margin: 2rem auto;
}

.auth-header {
  background-color: var(--primary-color);
  color: white;
  padding: 1.5rem;
  text-align: center;
}

.auth-header h2 {
  margin: 0;
  font-size: 1.5rem;
}

.auth-body {
  padding: 2rem;
}

/* ===== FORM STYLES ===== */
.form-group {
  margin-bottom: 1.5rem;
}

.form-group label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
  color: var(--dark-color);
}

.form-control {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid #d1d5db;
  border-radius: var(--border-radius);
  font-size: 1rem;
  transition: border-color 0.3s;
}

.form-control:focus {
  outline: none;
  border-color: var(--primary-color);
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

/* ===== BUTTON STYLES ===== */
.btn {
  display: inline-block;
  padding: 0.75rem 1.5rem;
  border-radius: var(--border-radius);
  font-weight: 500;
  text-align: center;
  cursor: pointer;
  transition: all 0.3s;
  border: none;
}

.btn-primary {
  background-color: var(--primary-color);
  color: white;
}

.btn-primary:hover {
  background-color: var(--primary-hover);
}

.btn-block {
  display: block;
  width: 100%;
}

/* ===== ALERT STYLES ===== */
.alert {
  padding: 1rem;
  border-radius: var(--border-radius);
  margin-bottom: 1.5rem;
}

.alert-danger {
  background-color: rgba(239, 68, 68, 0.1);
  border-left: 4px solid var(--danger-color);
  color: var(--danger-color);
}

.alert-success {
  background-color: rgba(16, 185, 129, 0.1);
  border-left: 4px solid var(--success-color);
  color: var(--success-color);
}

/* ===== LINK STYLES ===== */
.text-link {
  color: var(--primary-color);
  text-decoration: none;
  font-weight: 500;
}

.text-link:hover {
  text-decoration: underline;
}

.text-center {
  text-align: center;
}

/* ===== UTILITY STYLES ===== */
.mt-3 { margin-top: 1rem; }
.mt-4 { margin-top: 1.5rem; }
.mt-5 { margin-top: 2rem; }

.small {
  font-size: 0.875rem;
}

.text-muted {
  color: var(--secondary-color);
}

/* ===== ICON STYLES ===== */
.icon {
  margin-right: 0.5rem;
}

/* ===== RESPONSIVE ADJUSTMENTS ===== */
@media (max-width: 576px) {
  .auth-card {
    margin: 1rem;
  }
  
  .auth-body {
    padding: 1.5rem;
  }
}

/* ===== SPECIFIC PAGE ENHANCEMENTS ===== */
/* Forgot Password Page */
.forgot-password-info {
  color: var(--secondary-color);
  margin-bottom: 2rem;
  font-size: 0.95rem;
}

/* Reset Password Page */
.password-requirements {
  font-size: 0.875rem;
  color: var(--secondary-color);
  margin-top: 0.5rem;
}

.password-requirements ul {
  padding-left: 1.25rem;
  margin-top: 0.5rem;
}

.password-match {
  display: none;
  color: var(--success-color);
  font-size: 0.875rem;
  margin-top: 0.25rem;
}

/* ===== ANIMATIONS ===== */
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

.auth-card {
  animation: fadeIn 0.5s ease-out;
}</style><link rel="stylesheet" href="assets/css/style.css">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">