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
    <title>Reconnaissance Faciale - ExamSafe</title>
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
                <h1>Reconnaissance Faciale</h1>
                <p>Notre technologie de reconnaissance faciale assure l'identité de l'étudiant tout au long de l'examen, garantissant ainsi l'intégrité académique sans compromettre la vie privée.</p>
            </div>
        </div>
    </section>

    <!-- Section Description -->
    <section class="features">
        <div class="container">
            <div class="section-header">
                <h2>Comment fonctionne notre reconnaissance faciale</h2>
                <p>Une technologie avancée et respectueuse de la vie privée</p>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; align-items: center; margin-bottom: 3rem;">
                <div>
                    <h3>Vérification d'identité</h3>
                    <p>Avant le début de l'examen, notre système capture une image de référence de l'étudiant et la compare avec sa photo d'identité enregistrée. Cette étape garantit que la bonne personne commence l'examen.</p>
                    
                    <h3>Surveillance continue</h3>
                    <p>Pendant toute la durée de l'examen, notre système analyse régulièrement le visage de l'étudiant pour s'assurer qu'il s'agit toujours de la même personne et qu'elle reste présente devant l'écran.</p>
                    
                    <h3>Détection de présence multiple</h3>
                    <p>Notre technologie peut détecter la présence de plusieurs visages dans le champ de la caméra, signalant immédiatement une potentielle tentative de triche.</p>
                </div>
                <div>
                    <img src="../assets/images/face-recognition.jpg" alt="Démonstration de reconnaissance faciale" style="width: 100%; border-radius: var(--border-radius); box-shadow: var(--box-shadow);">
                </div>
            </div>
            
            <div class="section-header">
                <h2>Caractéristiques principales</h2>
            </div>
            
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-fingerprint"></i>
                    </div>
                    <h3>Algorithmes avancés</h3>
                    <p>Nos algorithmes d'apprentissage profond peuvent identifier avec précision un individu même dans différentes conditions d'éclairage ou avec des changements mineurs (lunettes, coiffure).</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3>Respect de la vie privée</h3>
                    <p>Les données biométriques sont traitées localement et ne sont jamais stockées de manière permanente, garantissant la conformité au RGPD et autres réglementations.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3>Rapports détaillés</h3>
                    <p>Les superviseurs reçoivent des rapports horodatés avec captures d'écran en cas d'incidents, facilitant la vérification et les décisions.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3>Analyse temporelle</h3>
                    <p>Notre système détecte les absences prolongées et calcule le temps total pendant lequel l'étudiant était effectivement présent devant son écran.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Section Technique -->
    <section class="how-it-works">
        <div class="container">
            <div class="section-header">
                <h2>Aspects techniques</h2>
                <p>Notre technologie de reconnaissance faciale intègre plusieurs couches de sécurité</p>
            </div>
            
            <div class="steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <h3>Détection du visage</h3>
                        <p>Nos algorithmes localisent d'abord précisément le visage dans l'image, quelle que soit l'orientation ou la distance de la caméra.</p>
                    </div>
                </div>
                
                <div class="step">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <h3>Extraction des caractéristiques</h3>
                        <p>Le système identifie plus de 128 points caractéristiques uniques du visage, créant une "empreinte faciale" numérique.</p>
                    </div>
                </div>
                
                <div class="step">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <h3>Comparaison biométrique</h3>
                        <p>Cette empreinte est comparée à l'image de référence pour confirmer l'identité avec un niveau de confiance statistique élevé.</p>
                    </div>
                </div>
                
                <div class="step">
                    <div class="step-number">4</div>
                    <div class="step-content">
                        <h3>Surveillance continue</h3>
                        <p>Des vérifications périodiques sont effectuées tout au long de l'examen, combinées à la détection d'autres facteurs comme l'orientation de la tête et les mouvements oculaires.</p>
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
                <p>Tout ce que vous devez savoir sur notre technologie de reconnaissance faciale</p>
            </div>
            
            <div class="faq-container">
                <div class="faq-item">
                    <div class="faq-question">
                        La reconnaissance faciale fonctionne-t-elle avec tous les types de caméras ?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Oui, notre système est conçu pour fonctionner avec la plupart des webcams intégrées et externes. Une caméra standard d'ordinateur portable ou de bureau est généralement suffisante. Cependant, une qualité d'image et un éclairage acceptables sont nécessaires pour des résultats optimaux.</p>
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question">
                        Que se passe-t-il si le visage de l'étudiant disparaît momentanément ?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Notre système est tolérant aux disparitions momentanées (quelques secondes) qui peuvent survenir naturellement. Cependant, des absences plus longues ou répétées déclenchent des alertes et sont signalées dans le rapport final. Les seuils de tolérance peuvent être ajustés par l'administrateur.</p>
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question">
                        La reconnaissance faciale est-elle conforme au RGPD ?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Oui, notre solution est entièrement conforme au RGPD et autres réglementations sur la protection des données. Les données biométriques sont traitées avec le consentement explicite de l'utilisateur, uniquement aux fins spécifiées, et ne sont pas conservées plus longtemps que nécessaire. Nous proposons également des options de traitement local où les données ne quittent jamais l'appareil de l'utilisateur.</p>
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question">
                        Le système peut-il être trompé par une photo ou une vidéo ?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Notre technologie intègre une détection de vivacité (liveness detection) qui peut différencier un vrai visage d'une photo ou d'une vidéo. Le système analyse des micro-mouvements naturels, la réflexion de la lumière sur la peau, et d'autres facteurs qui ne peuvent pas être facilement simulés.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Section CTA -->
    <section class="cta">
        <div class="container">
            <div class="cta-content">
                <h2>Prêt à renforcer l'intégrité de vos examens ?</h2>
                <p>Découvrez comment notre technologie de reconnaissance faciale peut transformer vos évaluations en ligne.</p>
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
