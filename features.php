<?php
// Inclure les fichiers de configuration et fonctions
include_once 'includes/config.php';
include_once 'includes/functions.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fonctionnalités - ExamSafe</title>
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

    <!-- Section Hero -->
    <section class="about-hero">
        <div class="container">
            <div class="about-content">
                <h1>Nos Fonctionnalités</h1>
                <p>ExamSafe offre une solution complète pour l'organisation et la surveillance d'examens en ligne, garantissant l'intégrité académique et une expérience utilisateur optimale pour les étudiants et les enseignants.</p>
            </div>
        </div>
    </section>

    <!-- Section Fonctionnalités Principales -->
    <section class="features">
        <div class="container">
            <div class="section-header">
                <h2>Fonctionnalités Principales</h2>
                <p>Notre plateforme combine technologie avancée et facilité d'utilisation</p>
            </div>
            
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <h3>Surveillance IA</h3>
                    <p>Surveillance automatisée par intelligence artificielle pour détecter les comportements suspects pendant les examens.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <h3>Types d'examens variés</h3>
                    <p>Support pour QCM, questions ouvertes, exercices de programmation, et bien plus encore.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <h3>Analyses détaillées</h3>
                    <p>Statistiques avancées sur les performances et le comportement des étudiants.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-lock"></i>
                    </div>
                    <h3>Sécurité maximale</h3>
                    <p>Chiffrement des données et mesures anti-triche avancées pour une intégrité académique garantie.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <h3>Compatible tous appareils</h3>
                    <p>Utilisez notre plateforme sur ordinateur, tablette ou smartphone sans perte de fonctionnalités.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-sync-alt"></i>
                    </div>
                    <h3>Synchronisation en temps réel</h3>
                    <p>Les réponses sont sauvegardées automatiquement et synchronisées en temps réel.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Section Surveillance -->
    <section class="how-it-works">
        <div class="container">
            <div class="section-header">
                <h2>Technologie de Surveillance Avancée</h2>
                <p>Notre système de surveillance automatisée utilise plusieurs technologies pour garantir l'intégrité des examens</p>
            </div>
            
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-user"></i>
                    </div>
                    <h3>Reconnaissance faciale</h3>
                    <p>Vérification d'identité et détection de présence continue pendant toute la durée de l'examen.</p>
                    <a href="proctoring/face-recognition.php" class="btn btn-outline">En savoir plus</a>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-eye"></i>
                    </div>
                    <h3>Suivi oculaire</h3>
                    <p>Détecte les mouvements oculaires suspects et les regards prolongés hors de l'écran.</p>
                    <a href="proctoring/eye-tracking.php" class="btn btn-outline">En savoir plus</a>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-microphone"></i>
                    </div>
                    <h3>Surveillance audio</h3>
                    <p>Analyse les sons ambiants pour détecter les conversations ou bruits suspects.</p>
                    <a href="proctoring/audio-monitor.php" class="btn btn-outline">En savoir plus</a>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-desktop"></i>
                    </div>
                    <h3>Surveillance d'écran</h3>
                    <p>Détecte les changements d'onglet, les captures d'écran et les applications non autorisées.</p>
                    <a href="proctoring/screen-monitor.php" class="btn btn-outline">En savoir plus</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Section Administration -->
    <section class="features">
        <div class="container">
            <div class="section-header">
                <h2>Pour les Administrateurs et Enseignants</h2>
                <p>Des outils puissants pour une gestion efficace des examens</p>
            </div>
            
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-edit"></i>
                    </div>
                    <h3>Création d'examens intuitive</h3>
                    <p>Interface drag-and-drop pour créer des examens professionnels en quelques minutes.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-database"></i>
                    </div>
                    <h3>Banque de questions</h3>
                    <p>Créez et réutilisez des questions organisées par catégories et niveaux de difficulté.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3>Planification avancée</h3>
                    <p>Programmez vos examens avec des plages horaires, durées et conditions d'accès personnalisées.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-robot"></i>
                    </div>
                    <h3>Correction automatisée</h3>
                    <p>Correction immédiate pour les QCM et assistance IA pour l'évaluation des questions ouvertes.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-flag"></i>
                    </div>
                    <h3>Signalement d'incidents</h3>
                    <p>Rapports détaillés sur les comportements suspects avec preuves horodatées.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-file-export"></i>
                    </div>
                    <h3>Export des résultats</h3>
                    <p>Exportez les notes et statistiques dans différents formats (Excel, CSV, PDF).</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Section Pour les Étudiants -->
    <section class="how-it-works">
        <div class="container">
            <div class="section-header">
                <h2>Pour les Étudiants</h2>
                <p>Une expérience d'examen fluide et sans stress</p>
            </div>
            
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-laptop-house"></i>
                    </div>
                    <h3>Passez vos examens de n'importe où</h3>
                    <p>Flexibilité maximale : passez vos examens depuis votre domicile ou n'importe quel endroit calme.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-hand-paper"></i>
                    </div>
                    <h3>Interface intuitive</h3>
                    <p>Navigation simple et ergonomique pour une concentration maximale sur le contenu de l'examen.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-save"></i>
                    </div>
                    <h3>Sauvegarde automatique</h3>
                    <p>Vos réponses sont sauvegardées automatiquement. Aucune perte de données en cas de problème technique.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-check-double"></i>
                    </div>
                    <h3>Résultats immédiats</h3>
                    <p>Pour les examens à correction automatique, obtenez vos résultats dès la soumission.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Section CTA -->
    <section class="cta">
        <div class="container">
            <div class="cta-content" style="display: flex;justify-content: center; gap: 20px;flex-direction: column;align-items: center;">
                <h2>Prêt à révolutionner vos examens en ligne ?</h2>
                <p>Rejoignez des centaines d'établissements qui font confiance à ExamSafe pour leurs évaluations à distance.</p>
                <div class="hero-buttons" >
                    <a href="register.php" class="btn btn-primary btn-lg">Créer un compte</a>
                    <a href="contact.php" class="btn btn-outline" style="background-color: transparent; color: white; border-color: white;">Demander une démo</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Pied de page -->
    <?php include 'includes/footer.php'; ?>

    <!-- Scripts JS -->
    <script src="assets/js/main.js"></script>
</body>
</html>
