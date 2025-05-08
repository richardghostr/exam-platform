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
    <title>Tarifs - ExamSafe</title>
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

    <!-- Section Pricing Header -->
    <section class="pricing-header">
        <div class="container">
            <h1>Nos tarifs</h1>
            <p>Des formules adaptées à tous les besoins, de la petite classe à l'université complète.</p>
            
            <div class="pricing-toggle">
                <span>Mensuel</span>
                <div class="toggle" id="pricing-toggle"></div>
                <span>Annuel</span>
            </div>
        </div>
    </section>

    <!-- Section Plans -->
    <section class="pricing" style="margin-top: -170px;">
        <div class="container">
            <div class="pricing-plans" id="pricing-plans">
                <!-- Plan Standard -->
                <div class="pricing-plan">
                    <div class="plan-header">
                        <h3>Standard</h3>
                        <p>Idéal pour les petites classes et cours individuels</p>
                    </div>
                    <div class="plan-price" style="margin-left: 20px;">
                        <div class="monthly">
                            <span class="currency">fcfa</span>
                            <span class="amount">19012</span>
                            <span class="period">/mois</span>
                        </div>
                        <div class="annual" style="display: none;">
                            <span class="currency">fcfa</span>
                            <span class="amount">196019</span>
                            <span class="period">/an</span>
                        </div>
                    </div>
                    <div class="plan-features">
                        <ul>
                            <li><i class="fas fa-check"></i> Jusqu'à 50 étudiants</li>
                            <li><i class="fas fa-check"></i> Reconnaissance faciale</li>
                            <li><i class="fas fa-check"></i> QCM et questions ouvertes</li>
                            <li><i class="fas fa-check"></i> Correction automatique des QCM</li>
                            <li class="not-included"><i class="fas fa-times"></i> Suivi oculaire</li>
                            <li class="not-included"><i class="fas fa-times"></i> Surveillance audio</li>
                            <li class="not-included"><i class="fas fa-times"></i> API et intégrations</li>
                        </ul>
                        <div class="plan-footer">
                            <a href="register.php" class="btn btn-outline btn-block">Commencer</a>
                        </div>
                    </div>
                </div>
                
                <!-- Plan Pro -->
                <div class="pricing-plan featured">
                    <div class="plan-badge">Populaire</div>
                    <div class="plan-header">
                        <h3>Pro</h3>
                        <p>Pour les écoles et les départements universitaires</p>
                    </div>
                    <div class="plan-price" style="margin-left: 20px;">
                        <div class="monthly">
                            <span class="currency">fcfa</span>
                            <span class="amount">64903</span>
                            <span class="period">/mois</span>
                        </div>
                        <div class="annual" style="display: none;">
                            <span class="currency">fcfa</span>
                            <span class="amount">654926</span>
                            <span class="period">/an</span>
                        </div>
                    </div>
                    <div class="plan-features">
                        <ul>
                            <li><i class="fas fa-check"></i> Jusqu'à 500 étudiants</li>
                            <li><i class="fas fa-check"></i> Reconnaissance faciale</li>
                            <li><i class="fas fa-check"></i> Suivi oculaire</li>
                            <li><i class="fas fa-check"></i> Surveillance audio</li>
                            <li><i class="fas fa-check"></i> Tous types d'examens</li>
                            <li><i class="fas fa-check"></i> Banque de questions</li>
                            <li class="not-included"><i class="fas fa-times"></i> API et intégrations</li>
                        </ul>
                        <div class="plan-footer">
                            <a href="register.php" class="btn btn-primary btn-block">Commencer</a>
                        </div>
                    </div>
                </div>
                
                <!-- Plan Entreprise -->
                <div class="pricing-plan">
                    <div class="plan-header">
                        <h3>Entreprise</h3>
                        <p>Pour les grandes institutions et universités</p>
                    </div>
                    <div class="plan-price" style="margin-left: 20px;">
                        <div class="monthly">
                            <span class="currency">fcfa</span>
                            <span class="amount">196019</span>
                            <span class="period">/mois</span>
                        </div>
                        <div class="annual" style="display: none;">
                            <span class="currency">fcfa</span>
                            <span class="amount">1966090</span>
                            <span class="period">/an</span>
                        </div>
                    </div>
                    <div class="plan-features">
                        <ul>
                            <li><i class="fas fa-check"></i> Étudiants illimités</li>
                            <li><i class="fas fa-check"></i> Toutes les fonctionnalités</li>
                            <li><i class="fas fa-check"></i> API complète</li>
                            <li><i class="fas fa-check"></i> Intégrations LMS</li>
                            <li><i class="fas fa-check"></i> Support dédié</li>
                            <li><i class="fas fa-check"></i> Personnalisations</li>
                            <li><i class="fas fa-check"></i> Formation et onboarding</li>
                        </ul>
                        <div class="plan-footer">
                            <a href="contact.php" class="btn btn-outline btn-block">Contacter les ventes</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Section Comparaison -->
    <section class="section">
        <div class="container">
            <div class="section-header">
                <h2>Comparaison détaillée des fonctionnalités</h2>
                <p>Trouvez la formule qui correspond parfaitement à vos besoins</p>
            </div>
            
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; margin: 2rem 0;">
                    <thead>
                        <tr>
                            <th style="text-align: left; padding: 1rem; border-bottom: 2px solid var(--border-color);">Fonctionnalité</th>
                            <th style="text-align: center; padding: 1rem; border-bottom: 2px solid var(--border-color);">Standard</th>
                            <th style="text-align: center; padding: 1rem; border-bottom: 2px solid var(--border-color); background-color: rgba(67, 97, 238, 0.05);">Pro</th>
                            <th style="text-align: center; padding: 1rem; border-bottom: 2px solid var(--border-color);">Entreprise</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="text-align: left; padding: 1rem; border-bottom: 1px solid var(--border-color);">Nombre d'étudiants</td>
                            <td style="text-align: center; padding: 1rem; border-bottom: 1px solid var(--border-color);">50</td>
                            <td style="text-align: center; padding: 1rem; border-bottom: 1px solid var(--border-color); background-color: rgba(67,97,238,0.05);">500</td>
                            <td style="text-align: center; padding: 1rem; border-bottom: 1px solid var(--border-color);">Illimité</td>
                        </tr>
                        <tr>
                            <td style="text-align: left; padding: 1rem; border-bottom: 1px solid var(--border-color);">Reconnaissance faciale</td>
                            <td style="text-align: center; padding: 1rem; border-bottom: 1px solid var(--border-color);"><i class="fas fa-check" style="color: var(--success-color);"></i></td>
                            <td style="text-align: center; padding: 1rem; border-bottom: 1px solid var(--border-color); background-color: rgba(67,97,238,0.05);"><i class="fas fa-check" style="color: var(--success-color);"></i></td>
                            <td style="text-align: center; padding: 1rem; border-bottom: 1px solid var(--border-color);"><i class="fas fa-check" style="color: var(--success-color);"></i></td>
                        </tr>
                        <tr>
                            <td style="text-align: left; padding: 1rem; border-bottom: 1px solid var(--border-color);">Suivi oculaire</td>
                            <td style="text-align: center; padding: 1rem; border-bottom: 1px solid var(--border-color);"><i class="fas fa-times" style="color: var(--danger-color);"></i></td>
                            <td style="text-align: center; padding: 1rem; border-bottom: 1px solid var(--border-color); background-color: rgba(67,97,238,0.05);"><i class="fas fa-check" style="color: var(--success-color);"></i></td>
                            <td style="text-align: center; padding: 1rem; border-bottom: 1px solid var(--border-color);"><i class="fas fa-check" style="color: var(--success-color);"></i></td>
                        </tr>
                        <tr>
                            <td style="text-align: left; padding: 1rem; border-bottom: 1px solid var(--border-color);">Surveillance audio</td>
                            <td style="text-align: center; padding: 1rem; border-bottom: 1px solid var(--border-color);"><i class="fas fa-times" style="color: var(--danger-color);"></i></td>
                            <td style="text-align: center; padding: 1rem; border-bottom: 1px solid var(--border-color); background-color: rgba(67,97,238,0.05);"><i class="fas fa-check" style="color: var(--success-color);"></i></td>
                            <td style="text-align: center; padding: 1rem; border-bottom: 1px solid var(--border-color);"><i class="fas fa-check" style="color: var(--success-color);"></i></td>
                        </tr>
                        <tr>
                            <td style="text-align: left; padding: 1rem; border-bottom: 1px solid var(--border-color);">Surveillance d'écran</td>
                            <td style="text-align: center; padding: 1rem; border-bottom: 1px solid var(--border-color);"><i class="fas fa-check" style="color: var(--success-color);"></i></td>
                            <td style="text-align: center; padding: 1rem; border-bottom: 1px solid var(--border-color); background-color: rgba(67,97,238,0.05);"><i class="fas fa-check" style="color: var(--success-color);"></i></td>
                            <td style="text-align: center; padding: 1rem; border-bottom: 1px solid var(--border-color);"><i class="fas fa-check" style="color: var(--success-color);"></i></td>
                        </tr>
                        <tr>
                            <td style="text-align: left; padding: 1rem; border-bottom: 1px solid var(--border-color);">Banque de questions</td>
                            <td style="text-align: center; padding: 1rem; border-bottom: 1px solid var(--border-color);">Limitée</td>
                            <td style="text-align: center; padding: 1rem; border-bottom: 1px solid var(--border-color); background-color: rgba(67,97,238,0.05);">Complète</td>
                            <td style="text-align: center; padding: 1rem; border-bottom: 1px solid var(--border-color);">Illimitée</td>
                        </tr>
                        <tr>
                            <td style="text-align: left; padding: 1rem; border-bottom: 1px solid var(--border-color);">API et intégrations</td>
                            <td style="text-align: center; padding: 1rem; border-bottom: 1px solid var(--border-color);"><i class="fas fa-times" style="color: var(--danger-color);"></i></td>
                            <td style="text-align: center; padding: 1rem; border-bottom: 1px solid var(--border-color); background-color: rgba(67,97,238,0.05);"><i class="fas fa-times" style="color: var(--danger-color);"></i></td>
                            <td style="text-align: center; padding: 1rem; border-bottom: 1px solid var(--border-color);"><i class="fas fa-check" style="color: var(--success-color);"></i></td>
                        </tr>
                        <tr>
                            <td style="text-align: left; padding: 1rem; border-bottom: 1px solid var(--border-color);">Support</td>
                            <td style="text-align: center; padding: 1rem; border-bottom: 1px solid var(--border-color);">Email</td>
                            <td style="text-align: center; padding: 1rem; border-bottom: 1px solid var(--border-color); background-color: rgba(67,97,238,0.05);">Email et chat</td>
                            <td style="text-align: center; padding: 1rem; border-bottom: 1px solid var(--border-color);">Dédié</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- Section FAQ -->
    <section class="pricing-faq">
        <div class="container">
            <div class="section-header">
                <h2>Questions fréquentes</h2>
                <p>Réponses aux questions courantes sur nos tarifs et fonctionnalités</p>
            </div>
            
            <div class="faq-container">
                <div class="faq-item">
                    <div class="faq-question">
                        Puis-je changer de formule à tout moment ?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Oui, vous pouvez passer d'une formule à une autre à tout moment. Si vous passez à une formule supérieure, la différence sera calculée au prorata. Si vous passez à une formule inférieure, le changement prendra effet à la fin de votre période de facturation en cours.</p>
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question">
                        Y a-t-il des frais cachés ?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Non, tous nos prix sont transparents. Il n'y a pas de frais cachés ni de coûts supplémentaires. Vous payez uniquement pour la formule que vous choisissez.</p>
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question">
                        Proposez-vous une remise pour les établissements éducatifs ?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Oui, nous offrons des remises spéciales pour les écoles, universités et institutions éducatives. Contactez notre équipe commerciale pour en savoir plus sur notre programme éducatif.</p>
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question">
                        Puis-je essayer ExamSafe avant de m'abonner ?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Absolument ! Nous proposons une période d'essai gratuite de 14 jours pour toutes nos formules. Aucune carte de crédit n'est requise pour l'essai. Vous pourrez tester toutes les fonctionnalités de la formule de votre choix.</p>
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question">
                        Comment fonctionne la facturation annuelle ?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>La facturation annuelle vous permet d'économiser environ 15% par rapport au paiement mensuel. Le montant total est facturé en une seule fois au début de votre période d'abonnement.</p>
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question">
                        Que se passe-t-il si je dépasse le nombre d'étudiants autorisé ?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Si vous approchez de la limite d'étudiants de votre formule, nous vous avertirons pour que vous puissiez passer à une formule supérieure si nécessaire. Nous offrons une marge de tolérance de 10% avant d'imposer des restrictions.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Section CTA -->
    <section class="cta">
        <div class="container">
            <div class="cta-content">
                <h2>Vous avez des besoins spécifiques ?</h2>
                <p>Contactez notre équipe commerciale pour discuter de solutions personnalisées adaptées à vos exigences particulières.</p>
                <a href="contact.php" class="btn btn-primary btn-lg">Nous contacter</a>
            </div>
        </div>
    </section>

    <!-- Pied de page -->
    <?php include 'includes/footer.php'; ?>

    <!-- Scripts JS -->
    <script src="assets/js/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle entre tarification mensuelle et annuelle
            const toggleBtn = document.getElementById('pricing-toggle');
            const pricingPlans = document.getElementById('pricing-plans');
            const monthlyPrices = document.querySelectorAll('.monthly');
            const annualPrices = document.querySelectorAll('.annual');
            
            toggleBtn.addEventListener('click', function() {
                this.classList.toggle('annual');
                
                if (this.classList.contains('annual')) {
                    monthlyPrices.forEach(price => price.style.display = 'none');
                    annualPrices.forEach(price => price.style.display = 'block');
                } else {
                    monthlyPrices.forEach(price => price.style.display = 'block');
                    annualPrices.forEach(price => price.style.display = 'none');
                }
            });
            
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
