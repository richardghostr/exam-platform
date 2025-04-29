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
    <title>ExamSafe - Plateforme d'examens en ligne avec surveillance automatisée</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <!-- En-tête -->
    <header class="header">
        <div class="container">
            <div class="logo">
                <a href="index.php">
                    <h1>ExamSafe</h1>
                </a>
            </div>
            <nav class="main-nav">
                <ul>
                    <li><a href="index.php" class="active">Accueil</a></li>
                    <li><a href="features.php">Fonctionnalités</a></li>
                    <li><a href="pricing.php">Tarifs</a></li>
                    <li><a href="about.php">À propos</a></li>
                    <li><a href="contact.php">Contact</a></li>
                </ul>
            </nav>
            <div class="auth-buttons">
                <a href="login.php" class="btn btn-outline">Connexion</a>
                <a href="register.php" class="btn btn-primary">Inscription</a>
            </div>
            <div class="mobile-menu-toggle">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>
    </header>

    <!-- Section Hero -->
    <section class="hero">
        <div class="container">
            <div class="hero-content">
                <h1>Examens en ligne sécurisés avec surveillance intelligente</h1>
                <p>ExamSafe révolutionne l'évaluation à distance grâce à une technologie de surveillance automatisée basée sur l'intelligence artificielle.</p>
                <div class="hero-buttons">
                    <a href="register.php" class="btn btn-primary btn-lg">Commencer maintenant</a>
                    <a href="#demo" class="btn btn-outline btn-lg">Voir la démo</a>
                </div>
            </div>
            <div class="hero-image">
                <img src="assets\images\hero-illustration.png" alt="Illustration de la plateforme d'examen">
            </div>
        </div>
    </section>

    <!-- Section Caractéristiques -->
    <section class="features" id="features">
        <div class="container">
            <div class="section-header">
                <h2>Fonctionnalités principales</h2>
                <p>Notre plateforme combine sécurité, facilité d'utilisation et technologies avancées</p>
            </div>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <h3>Vérification d'identité</h3>
                    <p>Reconnaissance faciale avancée pour confirmer l'identité des candidats avant et pendant l'examen.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-eye"></i>
                    </div>
                    <h3>Suivi du regard</h3>
                    <p>Détection des comportements suspects grâce à l'analyse des mouvements oculaires.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-microphone-alt"></i>
                    </div>
                    <h3>Surveillance audio</h3>
                    <p>Détection des conversations et bruits suspects dans l'environnement du candidat.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-desktop"></i>
                    </div>
                    <h3>Verrouillage de navigateur</h3>
                    <p>Empêche l'accès à d'autres applications ou sites web pendant l'examen.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <h3>Rapports détaillés</h3>
                    <p>Analyses complètes des performances et des incidents pour chaque examen.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h3>Notation automatique</h3>
                    <p>Évaluation instantanée des questions à choix multiples et assistance pour les questions ouvertes.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Section Comment ça marche -->
    <section class="how-it-works" id="how-it-works">
        <div class="container">
            <div class="section-header">
                <h2>Comment ça marche</h2>
                <p>Un processus simple en trois étapes pour des examens sécurisés</p>
            </div>
            <div class="steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <h3>Création de l'examen</h3>
                        <p>Les enseignants créent facilement des examens avec différents types de questions et paramètres de sécurité.</p>
                    </div>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <h3>Vérification et surveillance</h3>
                        <p>Les étudiants s'identifient via reconnaissance faciale et sont surveillés automatiquement pendant l'examen.</p>
                    </div>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <h3>Évaluation et résultats</h3>
                        <p>Notation automatique et rapports détaillés disponibles immédiatement après l'examen.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Section Témoignages -->
    <section class="testimonials">
        <div class="container">
            <div class="section-header">
                <h2>Ce que disent nos utilisateurs</h2>
                <p>Des établissements d'enseignement du monde entier font confiance à ExamSafe</p>
            </div>
            <div class="testimonials-slider">
                <div class="testimonial">
                    <div class="testimonial-content">
                        <p>"ExamSafe a transformé notre façon d'évaluer les étudiants à distance. La surveillance automatisée nous donne une confiance totale dans l'intégrité des examens."</p>
                    </div>
                    <div class="testimonial-author">
                        <img src="assets/images/testimonial-1.jpg" alt="Portrait de Dr. Martin">
                        <div class="author-info">
                            <h4>Dr. Sophie Martin</h4>
                            <p>Université de Paris</p>
                        </div>
                    </div>
                </div>
                <div class="testimonial">
                    <div class="testimonial-content">
                        <p>"L'interface intuitive et les fonctionnalités avancées de surveillance ont considérablement réduit les cas de fraude tout en simplifiant notre processus d'évaluation."</p>
                    </div>
                    <div class="testimonial-author">
                        <img src="assets/images/testimonial-2.jpg" alt="Portrait de Prof. Chen">
                        <div class="author-info">
                            <h4>Prof. Wei Chen</h4>
                            <p>Institut Technologique de Shanghai</p>
                        </div>
                    </div>
                </div>
                <div class="testimonial">
                    <div class="testimonial-content">
                        <p>"Nos étudiants apprécient la flexibilité des examens à distance, et nous apprécions la sécurité et la fiabilité qu'offre ExamSafe."</p>
                    </div>
                    <div class="testimonial-author">
                        <img src="assets/images/testimonial-3.jpg" alt="Portrait de Dr. Johnson">
                        <div class="author-info">
                            <h4>Dr. Emily Johnson</h4>
                            <p>Université de Toronto</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Section Tarifs -->
    <section class="pricing" id="pricing">
        <div class="container">
            <div class="section-header">
                <h2>Nos formules</h2>
                <p>Des solutions adaptées à tous les besoins et budgets</p>
            </div>
            <div class="pricing-plans">
                <div class="pricing-plan">
                    <div class="plan-header">
                        <h3>Basique</h3>
                        <div class="plan-price">
                            <span class="currency">€</span>
                            <span class="amount">29</span>
                            <span class="period">/mois</span>
                        </div>
                        <p>Idéal pour les petits établissements</p>
                    </div>
                    <div class="plan-features">
                        <ul>
                            <li><i class="fas fa-check"></i> Jusqu'à 50 examens/mois</li>
                            <li><i class="fas fa-check"></i> Surveillance de base</li>
                            <li><i class="fas fa-check"></i> Rapports standards</li>
                            <li><i class="fas fa-check"></i> Support par email</li>
                            <li class="not-included"><i class="fas fa-times"></i> Reconnaissance faciale avancée</li>
                            <li class="not-included"><i class="fas fa-times"></i> Suivi du regard</li>
                            <li class="not-included"><i class="fas fa-times"></i> API d'intégration</li>
                        </ul>
                    </div>
                    <div class="plan-footer">
                        <a href="register.php?plan=basic" class="btn btn-outline btn-block">Choisir ce plan</a>
                    </div>
                </div>
                <div class="pricing-plan featured">
                    <div class="plan-badge">Populaire</div>
                    <div class="plan-header">
                        <h3>Pro</h3>
                        <div class="plan-price">
                            <span class="currency">€</span>
                            <span class="amount">79</span>
                            <span class="period">/mois</span>
                        </div>
                        <p>Pour les établissements de taille moyenne</p>
                    </div>
                    <div class="plan-features">
                        <ul>
                            <li><i class="fas fa-check"></i> Examens illimités</li>
                            <li><i class="fas fa-check"></i> Surveillance complète</li>
                            <li><i class="fas fa-check"></i> Reconnaissance faciale</li>
                            <li><i class="fas fa-check"></i> Suivi du regard</li>
                            <li><i class="fas fa-check"></i> Rapports avancés</li>
                            <li><i class="fas fa-check"></i> Support prioritaire</li>
                            <li class="not-included"><i class="fas fa-times"></i> API d'intégration</li>
                        </ul>
                    </div>
                    <div class="plan-footer">
                        <a href="register.php?plan=pro" class="btn btn-primary btn-block">Choisir ce plan</a>
                    </div>
                </div>
                <div class="pricing-plan">
                    <div class="plan-header">
                        <h3>Entreprise</h3>
                        <div class="plan-price">
                            <span class="currency">€</span>
                            <span class="amount">199</span>
                            <span class="period">/mois</span>
                        </div>
                        <p>Pour les grandes institutions</p>
                    </div>
                    <div class="plan-features">
                        <ul>
                            <li><i class="fas fa-check"></i> Examens illimités</li>
                            <li><i class="fas fa-check"></i> Surveillance premium</li>
                            <li><i class="fas fa-check"></i> Toutes les fonctionnalités</li>
                            <li><i class="fas fa-check"></i> API d'intégration</li>
                            <li><i class="fas fa-check"></i> Personnalisation avancée</li>
                            <li><i class="fas fa-check"></i> Support dédié 24/7</li>
                            <li><i class="fas fa-check"></i> Formation et onboarding</li>
                        </ul>
                    </div>
                    <div class="plan-footer">
                        <a href="register.php?plan=enterprise" class="btn btn-outline btn-block">Choisir ce plan</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Section CTA -->
    <section class="cta">
        <div class="container">
            <div class="cta-content">
                <h2>Prêt à révolutionner vos examens en ligne?</h2>
                <p>Rejoignez des milliers d'établissements qui font confiance à ExamSafe pour leurs évaluations à distance.</p>
                <a href="register.php" class="btn btn-primary btn-lg">Commencer gratuitement</a>
            </div>
        </div>
    </section>

    <!-- Pied de page -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-col">
                    <div class="footer-logo">
                        <h2>ExamSafe</h2>
                    </div>
                    <p>La solution d'examen en ligne la plus sécurisée avec surveillance automatisée par intelligence artificielle.</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
                <div class="footer-col">
                    <h4>Liens rapides</h4>
                    <ul>
                        <li><a href="index.php">Accueil</a></li>
                        <li><a href="features.php">Fonctionnalités</a></li>
                        <li><a href="pricing.php">Tarifs</a></li>
                        <li><a href="about.php">À propos</a></li>
                        <li><a href="contact.php">Contact</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>Légal</h4>
                    <ul>
                        <li><a href="terms.php">Conditions d'utilisation</a></li>
                        <li><a href="privacy.php">Politique de confidentialité</a></li>
                        <li><a href="cookies.php">Politique de cookies</a></li>
                        <li><a href="gdpr.php">Conformité RGPD</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>Contact</h4>
                    <ul class="contact-info">
                        <li><i class="fas fa-map-marker-alt"></i> 123 Avenue de l'Innovation, 75001 Paris</li>
                        <li><i class="fas fa-phone"></i> +33 1 23 45 67 89</li>
                        <li><i class="fas fa-envelope"></i> contact@examsafe.com</li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> ExamSafe. Tous droits réservés.</p>
            </div>
        </div>
    </footer>

    <!-- Scripts JS -->
    <script src="assets/js/main.js"></script>
</body>
</html>
