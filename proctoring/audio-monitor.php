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
    <title>Surveillance Audio - ExamSafe</title>
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
                <h1>Surveillance Audio</h1>
                <p>Notre technologie de surveillance audio détecte les conversations et les bruits suspects pendant les examens, ajoutant une couche supplémentaire de sécurité à votre processus d'évaluation.</p>
            </div>
        </div>
    </section>

    <!-- Section Description -->
    <section class="features">
        <div class="container">
            <div class="section-header">
                <h2>Comment fonctionne notre surveillance audio</h2>
                <p>Une analyse intelligente pour détecter les anomalies sonores</p>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; align-items: center; margin-bottom: 3rem;">
                <div>
                    <h3>Analyse des sons ambiants</h3>
                    <p>Notre système enregistre et analyse en temps réel l'environnement sonore de l'étudiant, identifiant les bruits anormaux comme les conversations ou les dictées.</p>
                    
                    <h3>Détection de la parole</h3>
                    <p>Grâce à des algorithmes avancés de reconnaissance vocale, notre technologie peut distinguer la parole humaine des bruits de fond, détectant ainsi les conversations potentielles avec des tiers.</p>
                    
                    <h3>Filtrage intelligent</h3>
                    <p>Le système est capable de filtrer les bruits ambiants normaux (clics de souris, frappe au clavier) pour se concentrer uniquement sur les sons qui pourraient indiquer une tentative de triche.</p>
                </div>
                <div>
                    <img src="../assets/images/audio-monitor.jpg" alt="Démonstration de surveillance audio" style="width: 100%; ">
                </div>
            </div>
            
            <div class="section-header">
                <h2>Caractéristiques principales</h2>
            </div>
            
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-microphone-alt"></i>
                    </div>
                    <h3>Détection de conversations</h3>
                    <p>Identifie les voix humaines et les dialogues qui pourraient indiquer une communication avec des tiers pendant l'examen.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-volume-up"></i>
                    </div>
                    <h3>Analyse du niveau sonore</h3>
                    <p>Surveille les changements soudains de volume qui pourraient indiquer l'arrivée d'une autre personne ou un événement suspect.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-filter"></i>
                    </div>
                    <h3>Filtrage adaptatif</h3>
                    <p>S'adapte à l'environnement sonore normal de chaque étudiant pour minimiser les faux positifs tout en maintenant une détection efficace.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-file-audio"></i>
                    </div>
                    <h3>Enregistrement sélectif</h3>
                    <p>Enregistre uniquement les séquences audio suspectes pour vérification, préservant ainsi la vie privée tout en fournissant des preuves si nécessaire.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Section Technique -->
    <section class="how-it-works">
        <div class="container">
            <div class="section-header">
                <h2>Aspects techniques</h2>
                <p>Notre technologie de surveillance audio utilise plusieurs couches d'analyse</p>
            </div>
            
            <div class="steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <h3>Capture audio</h3>
                        <p>Le système capture en continu le flux audio via le microphone de l'utilisateur, en s'assurant de la qualité et de la clarté du signal.</p>
                    </div>
                </div>
                
                <div class="step">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <h3>Analyse spectrale</h3>
                        <p>Les algorithmes décomposent le signal audio en différentes fréquences pour identifier des modèles caractéristiques de la parole humaine et d'autres sons pertinents.</p>
                    </div>
                </div>
                
                <div class="step">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <h3>Détection de mots-clés</h3>
                        <p>Pour certains examens, le système peut être configuré pour détecter des mots ou phrases spécifiques liés au contenu de l'examen, signalant des tentatives potentielles de triche.</p>
                    </div>
                </div>
                
                <div class="step">
                    <div class="step-number">4</div>
                    <div class="step-content">
                        <h3>Analyse contextuelle</h3>
                        <p>Le système évalue le contexte des sons détectés, en tenant compte du moment où ils se produisent par rapport aux questions d'examen et aux actions de l'utilisateur.</p>
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
                <p>Tout ce que vous devez savoir sur notre technologie de surveillance audio</p>
            </div>
            
            <div class="faq-container">
                <div class="faq-item">
                    <div class="faq-question">
                        Comment gérez-vous les environnements bruyants ?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Notre système est conçu pour s'adapter à différents niveaux de bruit ambiant. Au début de l'examen, le système établit une référence du niveau sonore normal de l'environnement de l'étudiant. Il filtre ensuite les bruits constants ou prévisibles pour se concentrer sur les anomalies sonores. Dans les environnements particulièrement bruyants, les administrateurs peuvent ajuster les seuils de sensibilité.</p>
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question">
                        La surveillance audio respecte-t-elle la vie privée des étudiants ?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Absolument. Notre système n'enregistre pas l'intégralité des sessions d'examen, mais uniquement de courts extraits lorsqu'un comportement suspect est détecté. Les étudiants sont toujours informés avant l'examen que la surveillance audio sera activée, et ils doivent donner leur consentement explicite. Toutes les données audio sont cryptées et ne sont conservées que pour la durée nécessaire à la vérification des incidents signalés.</p>
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question">
                        Quels types de sons sont considérés comme suspects ?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Notre système est principalement configuré pour détecter les conversations (indiquant une possible collaboration), les voix récitant des réponses (suggérant un partage d'informations), et certains motifs de bruit qui pourraient indiquer l'utilisation de dispositifs non autorisés. Le système est également capable d'apprendre et de s'adapter aux nouveaux modèles de triche au fil du temps.</p>
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question">
                        Les problèmes techniques avec le microphone affecteront-ils l'examen ?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Non, notre système est conçu pour gérer les problèmes techniques de manière gracieuse. Si un problème de microphone est détecté, le système en informera l'étudiant et le surveillant, mais permettra généralement à l'examen de se poursuivre. Dans les cas où la surveillance audio est une exigence absolue, des politiques spécifiques peuvent être configurées par l'administrateur.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Section CTA -->
    <section class="cta">
        <div class="container" style="display: flex;justify-content: center; gap: 20px;flex-direction: column;align-items: center;">
            <div class="cta-content">
                <h2>Renforcez la sécurité de vos examens</h2>
                <p>Découvrez comment notre technologie de surveillance audio peut prévenir la triche et garantir l'équité des évaluations.</p>
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
