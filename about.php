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
    <title>À propos - ExamSafe</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</head>
<body>
    <!-- En-tête -->
    <?php include 'includes/header.php'; ?>

    <!-- Section À propos -->
    <section class="about-hero">
        <div class="container">
            <div class="about-content">
                <h1>À propos d'ExamSafe</h1>
                <p>ExamSafe est une plateforme d'examens en ligne innovante qui révolutionne la façon dont les établissements d'enseignement et les entreprises organisent leurs évaluations à distance.</p>
                <p>Fondée en 2023 par une équipe d'experts en éducation et en technologie, notre mission est de fournir une solution sécurisée, fiable et facile à utiliser pour les examens en ligne, tout en garantissant l'intégrité académique grâce à notre technologie de surveillance automatisée par intelligence artificielle.</p>
                <p>Nous croyons fermement que l'éducation à distance ne devrait pas compromettre la qualité et la fiabilité des évaluations. C'est pourquoi nous avons développé une plateforme qui combine des fonctionnalités avancées de surveillance avec une expérience utilisateur intuitive, tant pour les enseignants que pour les étudiants.</p>
            </div>
        </div>
    </section>

    <!-- Section Notre mission -->
    <section class="features">
        <div class="container">
            <div class="section-header">
                <h2>Notre mission</h2>
                <p>Transformer l'évaluation à distance en garantissant l'intégrité académique</p>
            </div>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3>Sécurité</h3>
                    <p>Garantir l'intégrité des examens grâce à une technologie de surveillance avancée qui détecte et prévient les tentatives de fraude.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-universal-access"></i>
                    </div>
                    <h3>Accessibilité</h3>
                    <p>Rendre l'éducation et l'évaluation accessibles à tous, partout dans le monde, en éliminant les barrières géographiques.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3>Innovation</h3>
                    <p>Repousser constamment les limites de la technologie pour améliorer l'expérience d'évaluation en ligne pour tous les utilisateurs.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Section Notre équipe -->
    <section class="team-section">
        <div class="container">
            <div class="section-header">
                <h2>Notre équipe</h2>
                <p>Des experts passionnés par l'éducation et la technologie</p>
            </div>
            <div class="team-grid">
                <div class="team-member">
                    <div class="team-member-image">
                        <img src="assets/images/team-1.jpg" alt="Sophie Martin">
                    </div>
                    <h3>Sophie Martin</h3>
                    <p>Fondatrice & CEO</p>
                    <p>Ancienne professeure d'université avec plus de 15 ans d'expérience dans l'éducation numérique.</p>
                    <div class="team-member-social">
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                    </div>
                </div>
                <div class="team-member">
                    <div class="team-member-image">
                        <img src="assets/images/team-2.jpg" alt="Thomas Dubois">
                    </div>
                    <h3>Thomas Dubois</h3>
                    <p>CTO</p>
                    <p>Expert en intelligence artificielle et en sécurité informatique avec une passion pour l'EdTech.</p>
                    <div class="team-member-social">
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#"><i class="fab fa-github"></i></a>
                    </div>
                </div>
                <div class="team-member">
                    <div class="team-member-image">
                        <img src="assets/images/team-3.jpg" alt="Emma Leclerc">
                    </div>
                    <h3>Emma Leclerc</h3>
                    <p>Directrice Produit</p>
                    <p>Spécialiste en expérience utilisateur avec une formation en sciences de l'éducation.</p>
                    <div class="team-member-social">
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#"><i class="fab fa-dribbble"></i></a>
                    </div>
                </div>
                <div class="team-member">
                    <div class="team-member-image">
                        <img src="assets/images/team-4.jpg" alt="Alexandre Chen">
                    </div>
                    <h3>Alexandre Chen</h3>
                    <p>Responsable IA</p>
                    <p>Docteur en intelligence artificielle spécialisé dans la vision par ordinateur et l'analyse comportementale.</p>
                    <div class="team-member-social">
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#"><i class="fab fa-github"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Section Nos valeurs -->
    <section class="how-it-works">
        <div class="container">
            <div class="section-header">
                <h2>Nos valeurs</h2>
                <p>Les principes qui guident nos actions et nos décisions</p>
            </div>
            <div class="steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <h3>Intégrité</h3>
                        <p>Nous croyons en l'honnêteté et la transparence dans tout ce que nous faisons. Notre plateforme est conçue pour promouvoir l'intégrité académique et garantir des évaluations équitables pour tous.</p>
                    </div>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <h3>Innovation</h3>
                        <p>Nous sommes constamment à la recherche de nouvelles façons d'améliorer notre plateforme et d'intégrer les dernières avancées technologiques pour offrir la meilleure expérience possible à nos utilisateurs.</p>
                    </div>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <h3>Confidentialité</h3>
                        <p>Nous prenons très au sérieux la protection des données personnelles et nous nous engageons à respecter les normes les plus strictes en matière de confidentialité et de sécurité des données.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Section Partenaires -->
    <section class="features">
        <div class="container">
            <div class="section-header">
                <h2>Nos partenaires</h2>
                <p>Ils nous font confiance pour leurs évaluations en ligne</p>
            </div>
            <div class="partners-grid">
                <div class="partner">
                    <img src="assets/images/partner-1.png" alt="Université de Paris">
                </div>
                <div class="partner">
                    <img src="assets/images/partner-2.png" alt="École Polytechnique">
                </div>
                <div class="partner">
                    <img src="assets/images/partner-3.png" alt="HEC Paris">
                </div>
                <div class="partner">
                    <img src="assets/images/partner-4.png" alt="Sciences Po">
                </div>
                <div class="partner">
                    <img src="assets/images/partner-5.png" alt="ESSEC Business School">
                </div>
                <div class="partner">
                    <img src="assets/images/partner-6.png" alt="Ministère de l'Éducation Nationale">
                </div>
            </div>
        </div>
    </section>

    <!-- Section CTA -->
    <section class="cta">
        <div class="container">
            <div class="cta-content">
                <h2>Rejoignez la révolution de l'évaluation en ligne</h2>
                <p>Découvrez comment ExamSafe peut transformer vos examens à distance et garantir l'intégrité académique.</p>
                <a href="contact.php" class="btn btn-primary btn-lg">Contactez-nous</a>
            </div>
        </div>
    </section>

    <!-- Pied de page -->
    <?php include 'includes/footer.php'; ?>

    <!-- Scripts JS -->
    <script src="assets/js/main.js"></script>
</body>
</html>
