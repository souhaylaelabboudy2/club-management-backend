<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f1f5f9;
            padding: 20px;
            line-height: 1.6;
        }
        
        .email-wrapper {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.1);
        }
        
        .email-header {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            padding: 40px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .email-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(239, 68, 68, 0.05) 100%);
            pointer-events: none;
        }
        
        .logo-container {
            position: relative;
            z-index: 1;
            margin-bottom: 20px;
        }
        
        .logo {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: white;
            text-decoration: none;
        }
        
        .logo-icon {
            width: 40px;
            height: 40px;
            background: #EF4444;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .logo-text {
            font-size: 24px;
            font-weight: bold;
            color: white;
        }
        
        .header-title {
            position: relative;
            z-index: 1;
            color: white;
            font-size: 28px;
            font-weight: 700;
            margin: 0;
        }
        
        .email-body {
            padding: 40px 30px;
            color: #475569;
            background: white;
        }
        
        .greeting {
            font-size: 18px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 20px;
        }
        
        .content-line {
            font-size: 15px;
            line-height: 1.8;
            margin-bottom: 20px;
            color: #64748b;
        }
        
        .cta-container {
            text-align: center;
            margin: 40px 0;
        }
        
        .cta-button {
            display: inline-block;
            padding: 14px 40px;
            background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }
        
        .cta-button:hover {
            background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%);
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
            transform: translateY(-2px);
        }
        
        .warning-box {
            background: #FEF2F2;
            border-left: 4px solid #EF4444;
            padding: 15px;
            margin: 30px 0;
            border-radius: 4px;
        }
        
        .warning-box p {
            font-size: 14px;
            color: #7F1D1D;
            margin: 0;
        }
        
        .footer {
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 30px;
            text-align: center;
            font-size: 13px;
            color: #94a3b8;
        }
        
        .footer-text {
            margin: 8px 0;
        }
        
        .divider {
            background: #e2e8f0;
            height: 1px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <!-- Header -->
        <div class="email-header">
            <div class="logo-container">
                <a href="#" class="logo">
                    <span class="logo-icon">🎯</span>
                    <span class="logo-text">Club Manager</span>
                </a>
            </div>
            <h1 class="header-title">Réinitialisation de mot de passe</h1>
        </div>

        <!-- Body -->
        <div class="email-body">
            <p class="greeting">Bonjour {{ $user->name }},</p>

            <p class="content-line">
                Vous avez demandé à réinitialiser votre mot de passe. Cliquez sur le bouton ci-dessous pour accéder à la page de réinitialisation.
            </p>

            <div class="cta-container">
                <a href="http://localhost:3000/reset-password/{{ $token }}" class="cta-button">
                    Réinitialiser mon mot de passe
                </a>
            </div>

            <p class="content-line">
                Ou copiez et collez l'adresse suivante dans votre navigateur :
                <br>
                <code style="background: #f1f5f9; padding: 4px 8px; border-radius: 4px; font-family: 'Courier New', monospace; font-size: 12px;">
                    http://localhost:3000/reset-password/{{ $token }}
                </code>
            </p>

            <div class="warning-box">
                <p>
                    ⏱️ <strong>Important :</strong> Ce lien expire dans <strong>15 minutes</strong>. Si vous ne l'utilisez pas d'ici là, vous devrez demander un nouveau lien.
                </p>
            </div>

            <div class="divider"></div>

            <p class="content-line">
                Si vous n'avez pas demandé cette réinitialisation, ignorez simplement ce message. Votre compte reste sécurisé.
            </p>

            <p class="content-line">
                Besoin d'aide ? Contactez notre support.
            </p>
        </div>

        <!-- Footer -->
        <div class="footer">
            <div class="footer-text">© {{ date('Y') }} Club Manager. Tous droits réservés.</div>
            <div class="footer-text">
                <a href="https://yourapp.com" style="color: #64748b; text-decoration: none;">Visitez notre site</a>
            </div>
        </div>
    </div>
</body>
</html>
