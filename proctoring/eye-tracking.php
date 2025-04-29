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
    <title>Suivi Oculaire - ExamSafe</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</head>
<body>
    <!-- En-tête -->
    <?php include '../includes/header.php'; ?>

    <!-- Section Hero -->
    <section class="about-hero">
        <div class="container">
            <div class="about-content">
                <h1>Suivi Oculaire</h1>
                <p>Notre technologie de suivi oculaire avancée détecte les comportements suspects pendant les examens, offrant un niveau de sécurité supplémentaire pour garantir l'intégrité académique.</p>
            </div>
        </div>
    </section>

    <!-- Section Description -->
    <section class="features">
        <div class="container">
            <div class="section-header">
                <h2>Comment fonctionne notre suivi oculaire</h2>
                <p>Une technologie précise pour détecter les comportements suspects</p>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; align-items: center; margin-bottom: 3rem;">
                <div>
                    <img src="../assets/images/eye-tracking.jpg" alt="Démonstration de suivi oculaire" style="width: 100%; border-radius: var(--border-radius); box-shadow: var(--box-shadow);">
                </div>
                <div>
                    <h3>Détection des mouvements oculaires</h3>
                    <p>Notre système analyse en temps réel la direction du regard et les mouvements des yeux, détectant les schémas suspects comme des regards répétés hors de l'écran.</p>
                    
                    <h3>Analyse comportementale</h3>
                    <p>En plus de suivre la direction du regard, notre technologie analyse des modèles comportementaux comme la fréquence des clignements, les dilatations pupillaires et d'autres indicateurs subtils de stress ou de tricherie potentielle.</p>
                    
                    <h3>Signalement intelligent</h3>
                    <p>Le système ne signale que les comportements réellement suspects, réduisant les faux positifs et permettant aux surveillants de se concentrer sur les incidents significatifs.</p>
                </div>
            </div>
            
            <div class="section-header">
                <h2>Caractéristiques principales</h2>
            </div>
            
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-low-vision"></i>
                    </div>
                    <h3>Détection hors écran</h3>
                    <p>Identifie quand l'étudiant regarde ailleurs que sur son écran, potentiellement pour consulter des notes ou d'autres ressources non autorisées.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <h3>Analyse de fréquence</h3>
                    <p>Mesure la fréquence et la durée des regards hors écran, distinguant les comportements naturels des motifs suspects.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-lightbulb"></i>
                    </div>
                    <h3>Détection des conditions</h3>
                    <p>Adapte sa sensibilité aux conditions d'éclairage et à la qualité de la caméra pour maintenir la précision dans différents environnements.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-brain"></i>
                    </div>
                    <h3>Apprentissage adaptatif</h3>
                    <p>S'améliore au fil du temps en apprenant des motifs comportementaux spécifiques à chaque utilisateur pour une détection plus précise.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Section Technique -->
    <section class="how-it-works">
        <div class="container">
            <div class="section-header">
                <h2>Aspects techniques</h2>
                <p>Notre technologie de suivi oculaire combine plusieurs approches innovantes</p>
            </div>
            
            <div class="steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <h3>Détection des yeux</h3>
                        <p>Nos algorithmes localisent précisément les yeux dans le flux vidéo, même lorsque l'utilisateur porte des lunettes ou se trouve dans des conditions d'éclairage variables.</p>
                    </div>
                </div>
                
                <div class="step">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <h3>Analyse cornéenne</h3>
                        <p>Le système calcule l'orientation des yeux en analysant la position relative de la cornée et d'autres marqueurs oculaires.</p>
                    </div>
                </div>
                
                <div class="step">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <h3>Corrélation tête-œil</h3>
                        <p>Notre système analyse conjointement la position de la tête et l'orientation des yeux pour déterminer avec précision où l'utilisateur regarde, même lors de mouvements naturels.</p>
                    </div>
                </div>
                
                <div class="step">
                    <div class="step-number">4</div>
                    <div class="step-content">
                        <h3>Détection des anomalies</h3>
                        <p>Les algorithmes d'apprentissage machine identifient les modèles de regard anormaux qui pourraient indiquer une tentative de triche ou de consultation de ressources non autorisées.</p>
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
                <p>Tout ce que vous devez savoir sur notre technologie de suivi oculaire</p>
            </div>
            
            <div class="faq-container">
                <div class="faq-item">
                    <div class="faq-question">
                        Le suivi oculaire fonctionne-t-il avec des webcams standard ?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Oui, notre technologie est conçue pour fonctionner avec des webcams standard présentes sur la plupart des ordinateurs portables et moniteurs. Pour des résultats optimaux, une résolution minimale de 720p est recommandée, mais le système peut s'adapter à des résolutions inférieures.</p>
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question">
                        Le système fonctionne-t-il pour les personnes portant des lunettes ?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Oui, notre système est conçu pour fonctionner avec des personnes portant des lunettes, y compris des lunettes à forte correction et des lunettes de soleil légèrement teintées. Cependant, des verres très teintés ou réfléchissants peuvent réduire la précision du suivi.</p>
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question">
                        Comment gérez-vous les faux positifs ?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Notre système utilise des algorithmes d'apprentissage machine qui distinguent les comportements normaux des comportements suspects. Par exemple, quelques regards rapides hors écran sont considérés comme normaux, tandis que des regards fréquents et prolongés dans une direction particulière déclencheront une alerte. Les administrateurs peuvent également ajuster les seuils de sensibilité.</p>
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question">
                        Cette technologie est-elle accessible aux personnes ayant des troubles visuels ?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Nous avons conçu notre système pour être aussi inclusif que possible. Pour les personnes ayant des troubles visuels spécifiques qui pourraient affecter le suivi oculaire, des accommodements individuels et des méthodes de surveillance alternatives peuvent être configurés par l'administrateur. Nous recommandons de contacter notre équipe de support pour discuter des options spécifiques.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Section CTA -->
    <section class="cta">
        <div class="container">
            <div class="cta-content">
                <h2>Sécurisez vos examens en ligne</h2>
                <p>Découvrez comment notre technologie de suivi oculaire peut garantir l'intégrité de vos évaluations.</p>
                <div class="hero-buttons">
                    <a href="../register.php" class="btn btn-primary btn-lg">Essayer ExamSafe</a>
                    <a href="../contact.php" class="btn btn-outline" style="background-color: transparent; color: white; border-color: white;">Demander une démo</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Pied de page -->
    <?php include '../includes/footer.php'; ?>

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
