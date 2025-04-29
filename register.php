<?php
// Inclure les fichiers de configuration et fonctions
include_once 'includes/config.php';
include_once 'includes/functions.php';

// Initialiser les variables
$error = '';
$success = '';
$formData = [
    'username' => '',
    'email' => '',
    'full_name' => '',
    'role' => 'student'
];

// Traiter le formulaire d'inscription
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer les données du formulaire
    $formData['username'] = trim($_POST['username']);
    $formData['email'] = trim($_POST['email']);
    $formData['full_name'] = trim($_POST['full_name']);
    $formData['role'] = isset($_POST['role']) ? $_POST['role'] : 'student';
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($formData['username']) || empty($formData['email']) || empty($formData['full_name']) || empty($password) || empty($confirm_password)) {
        $error = 'Veuillez remplir tous les champs.';
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Veuillez entrer une adresse email valide.';
    } elseif ($password !== $confirm_password) {
        $error = 'Les mots de passe ne correspondent pas.';
    } elseif (strlen($password) < 8) {
        $error = 'Le mot de passe doit contenir au moins 8 caractères.';
    } else {
        // Connexion à la base de données
        include_once 'includes/db.php';
        
        // Vérifier si le nom d'utilisateur ou l'email existe déjà
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $formData['username'], $formData['email']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = 'Ce nom d\'utilisateur ou cette adresse email est déjà utilisé(e).';
        } else {
            // Hacher le mot de passe
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insérer le nouvel utilisateur
            $insertStmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, role) VALUES (?, ?, ?, ?, ?)");
            $insertStmt->bind_param("sssss", $formData['username'], $formData['email'], $hashed_password, $formData['full_name'], $formData['role']);
            
            if ($insertStmt->execute()) {
                $success = 'Votre compte a été créé avec succès. Vous pouvez maintenant vous connecter.';
                // Réinitialiser le formulaire
                $formData = [
                    'username' => '',
                    'email' => '',
                    'full_name' => '',
                    'role' => 'student'
                ];
            } else {
                $error = 'Une erreur est survenue lors de la création de votre compte. Veuillez réessayer.';
            }
            
            $insertStmt->close();
        }
        
        $stmt->close();
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - ExamSafe</title>
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

    <!-- Section Inscription -->
    <section class="auth-section">
        <div class="container">
            <div class="auth-container">
                <div class="auth-form-container">
                    <h2>Créer un compte</h2>
                    <p>Rejoignez ExamSafe pour des examens en ligne sécurisés</p>
                    
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger">
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success">
                            <?php echo $success; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form action="register.php" method="post" class="auth-form">
                        <div class="form-group">
                            <label for="full_name">Nom complet</label>
                            <div class="input-icon-wrapper">
                                <i class="fas fa-user"></i>
                                <input style="padding-left: 45px;" type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($formData['full_name']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="username">Nom d'utilisateur</label>
                            <div class="input-icon-wrapper">
                                <i class="fas fa-user-tag"></i>
                                <input style="padding-left: 45px;" type="text" id="username" name="username" value="<?php echo htmlspecialchars($formData['username']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Adresse email</label>
                            <div class="input-icon-wrapper">
                                <i class="fas fa-envelope"></i>
                                <input style="padding-left: 45px;" type="email" id="email" name="email" value="<?php echo htmlspecialchars($formData['email']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Mot de passe</label>
                            <div class="input-icon-wrapper">
                                <i class="fas fa-lock"></i>
                                <input style="padding-left: 45px;" type="password" id="password" name="password" required>
                                <button  type="button" class="password-toggle" aria-label="Afficher/Masquer le mot de passe">
                                    <i style="margin-left: -30px;" class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="password-strength">
                                <div class="strength-meter">
                                    <div class="strength-meter-fill" data-strength="0"></div>
                                </div>
                                <div class="strength-text">Force du mot de passe: <span>Faible</span></div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirmer le mot de passe</label>
                            <div class="input-icon-wrapper">
                                <i class="fas fa-lock"></i>
                                <input style="padding-left: 45px;" type="password" id="confirm_password" name="confirm_password" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Type de compte</label>
                            <div class="role-selector">
                                <div class="role-option <?php echo $formData['role'] === 'student' ? 'selected' : ''; ?>">
                                    <input type="radio" id="role_student" name="role" value="student" <?php echo $formData['role'] === 'student' ? 'checked' : ''; ?>>
                                    <label for="role_student">
                                        <i class="fas fa-user-graduate"></i>
                                        <span>Étudiant</span>
                                    </label>
                                </div>
                                <div class="role-option <?php echo $formData['role'] === 'teacher' ? 'selected' : ''; ?>">
                                    <input type="radio" id="role_teacher" name="role" value="teacher" <?php echo $formData['role'] === 'teacher' ? 'checked' : ''; ?>>
                                    <label for="role_teacher">
                                        <i class="fas fa-chalkboard-teacher"></i>
                                        <span>Enseignant</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group terms">
                            <input type="checkbox" id="terms" name="terms" required>
                            <label for="terms">J'accepte les <a href="terms.php">conditions d'utilisation</a> et la <a href="privacy.php">politique de confidentialité</a></label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-block">Créer un compte</button>
                    </form>
                    
                    <div class="auth-separator">
                        <span>ou</span>
                    </div>
                    
                    <div class="social-auth">
                        <button class="btn btn-outline btn-social">
                            <i class="fab fa-google"></i> S'inscrire avec Google
                        </button>
                        <button class="btn btn-outline btn-social">
                            <i class="fab fa-microsoft"></i> S'inscrire avec Microsoft
                        </button>
                    </div>
                    
                    <div class="auth-footer">
                        <p>Vous avez déjà un compte? <a href="login.php">Se connecter</a></p>
                    </div>
                </div>
                
                <div class="auth-info">
                    <div class="auth-info-content">
                        <h2>Pourquoi choisir ExamSafe?</h2>
                        <p>ExamSafe révolutionne l'évaluation à distance grâce à une technologie de surveillance automatisée basée sur l'intelligence artificielle.</p>
                        
                        <div class="auth-features">
                            <div class="auth-feature">
                                <i class="fas fa-shield-alt"></i>
                                <div>
                                    <h3>Intégrité académique</h3>
                                    <p>Notre technologie de surveillance garantit l'authenticité des résultats d'examen.</p>
                                </div>
                            </div>
                            <div class="auth-feature">
                                <i class="fas fa-laptop"></i>
                                <div>
                                    <h3>Flexibilité totale</h3>
                                    <p>Passez vos examens n'importe où, n'importe quand, en toute sécurité.</p>
                                </div>
                            </div>
                            <div class="auth-feature">
                                <i class="fas fa-chart-pie"></i>
                                <div>
                                    <h3>Analyses avancées</h3>
                                    <p>Obtenez des insights détaillés sur les performances et le comportement.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Pied de page -->
    <?php include 'includes/footer.php'; ?>

    <!-- Scripts JS -->
    <script src="assets/js/main.js"></script>
    <script>
        // Script pour afficher/masquer le mot de passe
        document.addEventListener('DOMContentLoaded', function() {
            const passwordToggle = document.querySelector('.password-toggle');
            const passwordInput = document.querySelector('#password');
            
            if (passwordToggle && passwordInput) {
                passwordToggle.addEventListener('click', function() {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    
                    // Changer l'icône
                    const icon = this.querySelector('i');
                    icon.classList.toggle('fa-eye');
                    icon.classList.toggle('fa-eye-slash');
                });
            }
            
            // Vérification de la force du mot de passe
            const strengthMeter = document.querySelector('.strength-meter-fill');
            const strengthText = document.querySelector('.strength-text span');
            
            if (passwordInput && strengthMeter && strengthText) {
                passwordInput.addEventListener('input', function() {
                    const val = passwordInput.value;
                    let strength = 0;
                    
                    if (val.length >= 8) strength += 1;
                    if (val.match(/[a-z]/) && val.match(/[A-Z]/)) strength += 1;
                    if (val.match(/\d/)) strength += 1;
                    if (val.match(/[^a-zA-Z\d]/)) strength += 1;
                    
                    // Mettre à jour l'indicateur
                    strengthMeter.setAttribute('data-strength', strength);
                    
                    // Mettre à jour le texte
                    switch (strength) {
                        case 0:
                            strengthText.textContent = 'Faible';
                            break;
                        case 1:
                            strengthText.textContent = 'Faible';
                            break;
                        case 2:
                            strengthText.textContent = 'Moyen';
                            break;
                        case 3:
                            strengthText.textContent = 'Fort';
                            break;
                        case 4:
                            strengthText.textContent = 'Très fort';
                            break;
                    }
                });
            }
            
            // Sélection du type de compte
            const roleOptions = document.querySelectorAll('.role-option');
            
            roleOptions.forEach(option => {
                option.addEventListener('click', function() {
                    // Désélectionner toutes les options
                    roleOptions.forEach(opt => opt.classList.remove('selected'));
                    
                    // Sélectionner l'option cliquée
                    this.classList.add('selected');
                    
                    // Cocher le radio button
                    const radio = this.querySelector('input[type="radio"]');
                    radio.checked = true;
                });
            });
        });
    </script>
</body>
</html>
