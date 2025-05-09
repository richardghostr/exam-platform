<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialisation de mot de passe</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { color: #2c3e50; text-align: center; }
        .button { 
            display: inline-block; 
            padding: 12px 24px; 
            background-color: #3498db; 
            color: white !important; 
            text-decoration: none; 
            border-radius: 4px; 
            font-weight: bold;
            margin: 20px 0;
        }
        .footer { margin-top: 30px; font-size: 12px; color: #7f8c8d; text-align: center; }
        .code { 
            background: #f8f9fa; 
            padding: 10px; 
            border-radius: 4px; 
            font-family: monospace;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Réinitialisation de mot de passe</h2>
        </div>
        
        <p>Bonjour <?= htmlspecialchars($user['first_name']) ?>,</p>
        
        <p>Vous avez demandé à réinitialiser votre mot de passe sur <strong><?= SITE_NAME ?></strong>.</p>
        
        <p style="text-align: center;">
            <a href="<?= $resetLink ?>" class="button">
                Réinitialiser mon mot de passe
            </a>
        </p>
        
        <p>Ou copiez ce lien dans votre navigateur :</p>
        <div class="code"><?= $resetLink ?></div>
        
        <p><em>Ce lien expirera le <?= date('d/m/Y à H:i', strtotime($expires)) ?>.</em></p>
        
        <p>Si vous n'avez pas fait cette demande, veuillez ignorer cet email.</p>
        
        <div class="footer">
            <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
            <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?>. Tous droits réservés.</p>
        </div>
    </div>
</body>
</html>