<?php
// Inclure les fichiers de configuration et fonctions
include_once '../includes/config.php';
include_once '../includes/functions.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surveillance d'Écran - ExamSafe</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <!-- En-tête -->
    <?php include 'header.php'; ?>

    <!-- Section Hero -->
    <section class="about-hero">
        <div class="container">
            <div class="about-content" style="text-align: center;">
                <h1>Surveillance d'Écran</h1>
                <p>Notre technologie de surveillance d'écran détecte les applications non autorisées, les changements d'onglet et autres activités suspectes pendant les examens, garantissant l'intégrité académique sans compromettre l'expérience utilisateur.</p>
            </div>
        </div>
    </section>

    <!-- Section Description -->
    <section class="features">
        <div class="container">
            <div class="section-header">
                <h2>Comment fonctionne notre surveillance d'écran</h2>
                <p>Une approche équilibrée entre sécurité et respect de la vie privée</p>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; align-items: center; margin-bottom: 3rem;">
                <div>
                    <img src="../assets/images/screen-monitor.jpg" alt="Démonstration de surveillance d'écran" style="width: 100%; ">
                </div>
                <div>
                    <h3>Détection des applications</h3>
                    <p>Notre système surveille les applications et processus actifs pendant l'examen, détectant l'ouverture ou l'utilisation de logiciels non autorisés.</p>
                    
                    <h3>Suivi des changements d'onglet</h3>
                    <p>La technologie identifie quand un étudiant quitte l'onglet de l'examen pour consulter d'autres sites web ou ressources, enregistrant la durée et la fréquence de ces actions.</p>
                    
                    <h3>Captures d'écran intelligentes</h3>
                    <p>Le système peut prendre des captures d'écran uniquement lors de comportements suspects, équilibrant ainsi la sécurité et la confidentialité.</p>
                </div>
            </div>
            
            <div class="section-header">
                <h2>Caractéristiques principales</h2>
            </div>
            
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-desktop"></i>
                    </div>
                    <h3>Mode verrouillé</h3>
                    <p>Option permettant de restreindre l'accès à d'autres applications et sites web pendant la durée de l'examen.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <h3>Journal d'activité</h3>
                    <p>Enregistre un historique détaillé des actions de l'étudiant, incluant les changements d'onglet et les applications utilisées.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-camera"></i>
                    </div>
                    <h3>Captures contextuelles</h3>
                    <p>Prend des captures d'écran uniquement lors d'événements suspects, accompagnées d'informations contextuelles pour faciliter la vérification.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-ban"></i>
                    </div>
                    <h3>Liste noire personnalisable</h3>
                    <p>Permet aux administrateurs de définir quelles applications et sites web sont considérés comme non autorisés pendant les examens.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Section Technique -->
    <section class="how-it-works">
        <div class="container">
            <div class="section-header">
                <h2>Aspects techniques</h2>
                <p>Notre surveillance d'écran utilise plusieurs méthodes complémentaires</p>
            </div>
            
            <div class="steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <h3>Analyse des processus</h3>
                        <p>Le système surveille les processus actifs sur l'ordinateur de l'étudiant, détectant le lancement d'applications non autorisées ou de navigateurs secondaires.</p>
                    </div>
                </div>
                
                <div class="step">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <h3>Surveillance du focus</h3>
                        <p>La technologie détecte quand l'application d'examen perd le focus, indiquant que l'utilisateur interagit avec un autre programme ou fenêtre.</p>
                    </div>
                </div>
                
                <div class="step">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <h3>Détection de capture</h3>
                        <p>Le système peut identifier les tentatives de capture d'écran par des outils externes, protégeant ainsi le contenu de l'examen contre la copie non autorisée.</p>
                    </div>
                </div>
                
                <div class="step">
                    <div class="step-number">4</div>
                    <div class="step-content">
                        <h3>Analyse comportementale</h3>
                        <p>Au-delà de la simple détection, notre système analyse les motifs d'utilisation pour distinguer les actions accidentelles des tentatives délibérées de triche.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Section FAQ -->
    <section class="pricing-faq">
        <div class="container">
            <div class="section-header">
                <h2>Questions fréquentes</h2>
                <p>Tout ce que vous devez savoir sur notre technologie de surveillance d'écran</p>
            </div>
            
            <div class="faq-container">
                <div class="faq-item">
                    <div class="faq-question">
                        Le système fonctionne-t-il sur tous les navigateurs et systèmes d'exploitation ?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Notre système est compatible avec tous les principaux navigateurs (Chrome, Firefox, Safari, Edge) et systèmes d'exploitation (Windows, macOS, Linux). Certaines fonctionnalités avancées comme le mode verrouillé peuvent nécessiter l'installation d'une extension de navigateur ou d'une application légère, mais les fonctionnalités de base fonctionnent dans tous les environnements.</p>
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question">
                        Les étudiants peuvent-ils utiliser des ressources autorisées pendant l'examen ?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Absolument. Les administrateurs peuvent configurer des "listes blanches" d'applications et sites web auxquels les étudiants sont autorisés à accéder pendant l'examen. Par exemple, vous pouvez autoriser l'accès à une calculatrice, à un éditeur de texte spécifique ou à certaines ressources en ligne tout en bloquant l'accès à d'autres.</p>
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question">
                        Comment le système gère-t-il les configurations multi-écrans ?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Notre système détecte automatiquement les configurations multi-écrans. Selon les paramètres définis par l'administrateur, le système peut soit surveiller tous les écrans connectés, soit exiger que l'étudiant utilise uniquement un écran pendant l'examen. En cas d'écrans multiples, des captures peuvent être prises de tous les écrans pour assurer une surveillance complète.</p>
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question">
                        Que se passe-t-il en cas de perte de connexion internet ?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Notre système est conçu pour gérer les interruptions de connexion. Si la connexion est perdue, le système continue de surveiller localement les activités de l'écran. Une fois la connexion rétablie, les journaux et captures d'écran sont synchronisés avec le serveur. Pour les interruptions prolongées, des politiques configurables permettent aux administrateurs de définir comment les incidents doivent être gérés.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Section CTA -->
    <section class="cta">
        <div class="container" style="display: flex;justify-content: center; gap: 20px;flex-direction: column;align-items: center;">
            <div class="cta-content">
                <h2>Assurez l'intégrité de vos examens en ligne</h2>
                <p>Découvrez comment notre technologie de surveillance d'écran peut transformer vos évaluations à distance.</p>
                <div class="hero-buttons" style="display: flex;justify-content: center; gap: 20px;align-items: center;">
                    <a href="../register.php" class="btn btn-primary btn-lg">Essayer ExamSafe</a>
                    <a href="../contact.php" class="btn btn-outline" style="background-color: transparent; color: white; border-color: white;">Demander une démo</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Pied de page -->
    <?php include 'footer.php'; ?>

    <!-- Scripts JS -->
    <script src="../assets/js/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Gestion des FAQ
            const faqItems = document.querySelectorAll('.faq-item');
            
            faqItems.forEach(item => {
                const question = item.querySelector('.faq-question');
                
                question.addEventListener('click', () => {
                    item.classList.toggle('active');
                });
            });
        });
    </script>
</body>
</html>
