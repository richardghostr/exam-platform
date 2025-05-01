<?php
include_once '../includes/config.php';
include_once '../includes/db.php';
include_once '../includes/functions.php';

if (!function_exists('redirect')) {
    function redirect($url) {
        header("Location: $url");
        exit;
    }
}
include_once '../includes/auth.php';

if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    redirect('index.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']) ? true : false;

    if (empty($username) || empty($password)) {
        $error = 'Veuillez remplir tous les champs.';
    } else {
        $user = authenticate_user($username, $password, $remember);
        if ($user && $user['role'] === 'admin') {
            redirect('index.php');
        } else {
            $error = 'Identifiants incorrects ou vous n\'avez pas les droits d\'administration.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <meta charset="UTF-8">
    <title>Connexion Admin - ExamSafe</title>
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #1e1f31, #2c2e4a);
            color: #ffffff;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .admin-login-container {
            width: 100%;
            max-width: 420px;
            background-color: #2c2e4a;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.4);
        }

        .admin-login-header {
            text-align: center;
            margin-bottom: 20px;
        }

        .admin-login-logo {
            height: 60px;
            margin-bottom: 10px;
        }

        .admin-login-header h1 {
            font-size: 1.5rem;
            margin: 10px 0;
            color: #ffffff;
        }

        .admin-login-header p {
            color: #bbb;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 6px;
            color: #ccc;
        }

        .input-icon-wrapper {
            position: relative;
        }

        .input-icon-wrapper i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #888;
        }

        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 10px 12px 10px 36px;
            border: none;
            border-radius: 8px;
            background-color: #1f2033;
            color: #eee;
            outline: none;
            font-size: 15px;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #ccc;
        }

        .btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 8px;
            background-color: #4c51bf;
            color: #fff;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .btn:hover {
            background-color: #5a63e0;
        }

        .alert {
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 15px;
        }

        .alert-danger {
            background-color: #ff4c4c;
            color: white;
        }

        .alert-success {
            background-color: #28a745;
            color: white;
        }

        .admin-login-footer {
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
            font-size: 14px;
        }

        .admin-login-footer a {
            color: #7c83ff;
            text-decoration: none;
        }

        .admin-login-footer a:hover {
            text-decoration: underline;
        }
    </style>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</head>
<body>
    <div class="admin-login-container">
        <div class="admin-login-header">
            <img src="../assets/images/logo.png" alt="ExamSafe Logo" class="admin-login-logo" height="260px" width="60px">
            <h1 style="margin-top: -15px;">Administration ExamSafe</h1>
            <p>Connectez-vous au panneau d'administration</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="admin-login-form">
            <div class="form-group">
                <label for="username">Nom d'utilisateur ou Email</label>
                <div class="input-icon-wrapper">
                    <i class="fas fa-user"></i>
                    <input type="text" id="username" name="username" placeholder="Entrez votre nom d'utilisateur ou email" required style="width: 90%;">
                </div>
            </div>

            <div class="form-group">
                <label for="password">Mot de passe</label>
                <div class="input-icon-wrapper">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="password" name="password" placeholder="Entrez votre mot de passe" required style="width: 90%;">
                </div>
            </div>

            <div class="form-group remember-me">
                <input type="checkbox" id="remember" name="remember">
                <label for="remember">Se souvenir de moi</label>
            </div>

            <button type="submit" class="btn">Se connecter</button>
        </form>

        <div class="admin-login-footer">
            <a href="../index.php">Retour au site</a>
            <a href="../forgot-password.php">Mot de passe oublié ?</a>
        </div>
    </div>
</body>
</html>
