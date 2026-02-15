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
        
        .logo-text .highlight {
            color: #EF4444;
        }
        
        .header-title {
            position: relative;
            z-index: 1;
            color: white;
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }
        
        .header-subtitle {
            position: relative;
            z-index: 1;
            color: rgba(255, 255, 255, 0.8);
            font-size: 16px;
        }
        
        .email-body {
            padding: 40px 30px;
        }
        
        .greeting {
            font-size: 20px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 20px;
        }
        
        .message {
            color: #475569;
            font-size: 15px;
            margin-bottom: 25px;
            line-height: 1.7;
        }
        
        .credentials-card {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border: 2px solid #EF4444;
            border-radius: 12px;
            padding: 24px;
            margin: 30px 0;
        }
        
        .credentials-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 18px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 16px;
        }
        
        .credential-item {
            background: white;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid #e2e8f0;
        }
        
        .credential-item:last-child {
            margin-bottom: 0;
        }
        
        .credential-label {
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .credential-value {
            font-size: 15px;
            font-weight: 600;
            color: #1e293b;
            font-family: 'Courier New', monospace;
        }
        
        .warning-box {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border-left: 4px solid #f59e0b;
            padding: 16px 20px;
            border-radius: 8px;
            margin: 20px 0;
            display: flex;
            gap: 12px;
        }
        
        .warning-icon {
            font-size: 20px;
            flex-shrink: 0;
        }
        
        .warning-text {
            color: #92400e;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .warning-text strong {
            font-weight: 700;
        }
        
        .club-details {
            background: #f8fafc;
            border-radius: 12px;
            padding: 24px;
            margin: 25px 0;
        }
        
        .club-details-title {
            font-size: 18px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 16px;
        }
        
        .detail-row {
            display: flex;
            padding: 12px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            flex: 0 0 140px;
            font-weight: 600;
            color: #64748b;
            font-size: 14px;
        }
        
        .detail-value {
            flex: 1;
            color: #1e293b;
            font-size: 14px;
        }
        
        .cta-container {
            margin: 35px 0;
            text-align: center;
        }
        
        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%);
            color: white;
            padding: 14px 32px;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 15px;
            margin: 0 8px 12px;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }
        
        .cta-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
        }
        
        .cta-button.secondary {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            box-shadow: 0 4px 12px rgba(30, 41, 59, 0.3);
        }
        
        .cta-button.secondary:hover {
            box-shadow: 0 6px 20px rgba(30, 41, 59, 0.4);
        }
        
        .divider {
            height: 1px;
            background: linear-gradient(to right, transparent, #e2e8f0, transparent);
            margin: 30px 0;
        }
        
        .email-footer {
            background: #f8fafc;
            padding: 30px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
        }
        
        .footer-text {
            color: #64748b;
            font-size: 13px;
            line-height: 1.6;
            margin-bottom: 12px;
        }
        
        .footer-links {
            margin-top: 16px;
        }
        
        .footer-link {
            color: #EF4444;
            text-decoration: none;
            font-size: 13px;
            margin: 0 10px;
            font-weight: 600;
        }
        
        .social-links {
            margin-top: 20px;
        }
        
        .social-icon {
            display: inline-block;
            width: 36px;
            height: 36px;
            background: #1e293b;
            color: white;
            text-decoration: none;
            border-radius: 50%;
            line-height: 36px;
            margin: 0 6px;
            font-size: 16px;
        }
        
        @media only screen and (max-width: 600px) {
            .email-wrapper {
                border-radius: 0;
            }
            
            .email-header,
            .email-body,
            .email-footer {
                padding: 30px 20px;
            }
            
            .header-title {
                font-size: 26px;
            }
            
            .cta-button {
                display: block;
                margin: 0 0 12px 0;
            }
            
            .detail-row {
                flex-direction: column;
            }
            
            .detail-label {
                margin-bottom: 4px;
            }
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <!-- Header -->
        <div class="email-header">
            <div class="logo-container">
                <a href="{{ env('APP_URL') }}" class="logo">
                    <div class="logo-icon">🎓</div>
                    <span class="logo-text">Clu<span class="highlight">versity</span></span>
                </a>
            </div>
            <h1 class="header-title">Bienvenue ! 🎉</h1>
            <p class="header-subtitle">Vous faites maintenant partie de {{ $club->name }}</p>
        </div>
        
        <!-- Body -->
        <div class="email-body">
            <div class="greeting">
                Bonjour {{ $person->first_name }} {{ $person->last_name }},
            </div>
            
            <p class="message">
                Nous sommes ravis de vous accueillir dans notre club ! Vous faites désormais partie d'une communauté passionnée qui partage vos intérêts et ambitions.
            </p>
            
            @if($password)
            <!-- Credentials Card -->
            <div class="credentials-card">
                <div class="credentials-title">
                    🔐 Vos identifiants de connexion
                </div>
                
                <div class="credential-item">
                    <span class="credential-label">Email</span>
                    <span class="credential-value">{{ $person->email }}</span>
                </div>
                
                <div class="credential-item">
                    <span class="credential-label">Mot de passe</span>
                    <span class="credential-value">{{ $password }}</span>
                </div>
            </div>
            
            <!-- Warning -->
            <div class="warning-box">
                <span class="warning-icon">⚠️</span>
                <div class="warning-text">
                    <strong>Important :</strong> Pour votre sécurité, veuillez changer votre mot de passe lors de votre première connexion.
                </div>
            </div>
            @endif
            
            <!-- Club Details -->
            <div class="club-details">
                <div class="club-details-title">📋 Détails de votre adhésion</div>
                
                <div class="detail-row">
                    <span class="detail-label">Club</span>
                    <span class="detail-value">{{ $club->name }}</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Votre rôle</span>
                    <span class="detail-value">{{ ucfirst($role) }}</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Email enregistré</span>
                    <span class="detail-value">{{ $person->email }}</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Date d'adhésion</span>
                    <span class="detail-value">{{ now()->format('d/m/Y') }}</span>
                </div>
            </div>
            
            <p class="message">
                Vous pouvez maintenant accéder à tous les services, événements et ressources du club. Explorez, participez et profitez pleinement de cette expérience !
            </p>
            
            <!-- CTA Buttons -->
            <div class="cta-container">
                <a href="https://club-management-frontend-production-710d.up.railway.app/Login/login" class="cta-button">
                    🚀 Se connecter maintenant
                </a>
                <a href="https://club-management-frontend-production-710d.up.railway.app/clubs/{{ $club->id }}" class="cta-button secondary">
                    📍 Accéder au club
                </a>
            </div>
            
            <div class="divider"></div>
            
            <p class="message">
                Si vous avez des questions ou besoin d'aide, notre équipe est là pour vous accompagner.
            </p>
            
            <p class="message" style="margin-top: 25px;">
                Cordialement,<br>
                <strong>L'équipe {{ $club->name }}</strong>
            </p>
        </div>
        
        <!-- Footer -->
        <div class="email-footer">
            <p class="footer-text">
                Cet email a été envoyé à <strong>{{ $person->email }}</strong><br>
                Vous recevez cet email car vous avez été ajouté au club {{ $club->name }}.
            </p>
            
            <div class="footer-links">
                <a href="{{ env('APP_URL') }}" class="footer-link">Accueil</a>
                <a href="{{ env('APP_URL') }}/help" class="footer-link">Aide</a>
                <a href="{{ env('APP_URL') }}/contact" class="footer-link">Contact</a>
            </div>
            
            <p class="footer-text" style="margin-top: 20px; font-size: 12px; color: #94a3b8;">
                © {{ date('Y') }} Cluversity - Université Sidi Mohamed Ben Abdellah - EST Fès<br>
                Tous droits réservés
            </p>
        </div>
    </div>
</body>
</html>