<?php
// Inclure les fichiers de configuration et fonctions
include_once 'includes/config.php';
include_once 'includes/functions.php';

// Initialiser les variables
$error = '';
$username = '';

// Traiter le formulaire de connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer les données du formulaire
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    // Validation simple
    if (empty($username) || empty($password)) {
        $error = 'Veuillez remplir tous les champs.';
    } else {
        // Ici, vous implémenteriez la vérification des identifiants dans la base de données
        // Pour cet exemple, nous utilisons une vérification simplifiée
        
        // Connexion à la base de données
        include_once 'includes/db.php';
        
        // Préparer la requête pour éviter les injections SQL
        $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Vérifier le mot de passe
            if (password_verify($password, $user['password'])) {
                // Mot de passe correct, créer une session
                session_start();
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                
                // Mettre à jour la dernière connexion
                $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $updateStmt->bind_param("i", $user['id']);
                $updateStmt->execute();
                
                // Rediriger vers la page appropriée selon le rôle
                switch ($user['role']) {
                    case 'teacher':
                        header('Location: teacher/index.php');
                        break;
                    case 'student':
                        header('Location: student/index.php');
                        break;
                    default:
                        header('Location: index.php');
                }
                exit;
            } else {
                $error = 'Identifiants incorrects.';
            }
        } else {
            $error = 'Identifiants incorrects.';
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
    <title>Connexion - ExamSafe</title>
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

    <!-- Section Connexion -->
    <section class="auth-section">
        <div class="container">
            <div class="auth-container">
                <div class="auth-form-container">
                    <h2>Connexion</h2>
                    <p>Accédez à votre compte ExamSafe</p>
                    
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger">
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form action="login.php" method="post" class="auth-form">
                        <div class="form-group">
                            <label for="username">Nom d'utilisateur ou Email</label>
                            <div class="input-icon-wrapper">
                                <i class="fas fa-user"></i>
                                <input style="padding-left: 45px;" type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Mot de passe</label>
                            <div class="input-icon-wrapper">
                                <i class="fas fa-lock"></i>
                                <input style="padding-left: 45px;" type="password" id="password" name="password" required>
                                <button type="button" class="password-toggle" aria-label="Afficher/Masquer le mot de passe">
                                    <i style="margin-left: -30px;" class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-options">
                            <div class="remember-me">
                                <input type="checkbox" id="remember" name="remember">
                                <label for="remember">Se souvenir de moi</label>
                            </div>
                            <a href="forgot-password.php" class="forgot-password">Mot de passe oublié?</a>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-block">Se connecter</button>
                    </form>
                    
                    <div class="auth-separator">
                        <span>ou</span>
                    </div>
                    
                    <div class="social-auth">
                        <button class="btn btn-outline btn-social">
                            <i class="fab fa-google"></i> Continuer avec Google
                        </button>
                        <button class="btn btn-outline btn-social">
                            <i class="fab fa-microsoft"></i> Continuer avec Microsoft
                        </button>
                    </div>
                    
                    <div class="auth-footer">
                        <p>Vous n'avez pas de compte? <a href="register.php">S'inscrire</a></p>
                    </div>
                </div>
                
                <div class="auth-info">
                    <div class="auth-info-content">
                        <h2>Bienvenue sur ExamSafe</h2>
                        <p>La plateforme d'examens en ligne la plus sécurisée avec surveillance automatisée par intelligence artificielle.</p>
                        
                        <div class="auth-features">
                            <div class="auth-feature">
                                <i class="fas fa-shield-alt"></i>
                                <div>
                                    <h3>Sécurité maximale</h3>
                                    <p>Surveillance automatisée par IA pour garantir l'intégrité des examens.</p>
                                </div>
                            </div>
                            <div class="auth-feature">
                                <i class="fas fa-chart-line"></i>
                                <div>
                                    <h3>Analyses détaillées</h3>
                                    <p>Rapports complets sur les performances et les comportements.</p>
                                </div>
                            </div>
                            <div class="auth-feature">
                                <i class="fas fa-graduation-cap"></i>
                                <div>
                                    <h3>Expérience optimale</h3>
                                    <p>Interface intuitive pour les étudiants et les enseignants.</p>
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
        });
    </script>
</body>
</html>
