<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Mise à jour de votre compte Tracimo </title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 10px;
        }
        .header {
            background-color: #4F46E5;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 10px 10px 0 0;
            margin: -20px -20px 20px -20px;
        }
        .credentials {
            background-color: #f5f5f5;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            font-size: 12px;
            color: #666;
            text-align: center;
        }
        .role-badge {
            display: inline-block;
            background-color: #4F46E5;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 14px;
        }
        .warning {
            background-color: #FEF3C7;
            border-left: 4px solid #F59E0B;
            padding: 10px 15px;
            margin: 15px 0;
            border-radius: 5px;
        }
        .info-box {
            background-color: #E0E7FF;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #4F46E5;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Tracimo </h2>
        </div>

        <p>Bonjour <strong>{{ $nom ?? '' }} {{ $prenom ?? '' }}</strong>,</p>

        <p>Les informations de votre compte ont été mises à jour.</p>

        <div class="info-box">
            @if($role_changed ?? false)
                <p><strong>📌 Votre nouveau rôle :</strong> <span class="role-badge">{{ $role ?? 'Utilisateur' }}</span></p>
            @endif

            @if($email_changed ?? false)
                <p><strong>📧 Votre nouvel email de connexion :</strong> {{ $email ?? '' }}</p>
            @endif
        </div>

        @if($password_changed ?? false)
            <div class="credentials">
                <p><strong>🔑 Votre nouveau mot de passe :</strong> {{ $password ?? '' }}</p>
            </div>

            <div class="warning">
                <p><strong>⚠️ Important :</strong> Nous vous recommandons de modifier ce mot de passe lors de votre prochaine connexion.</p>
            </div>
        @endif

        <div class="warning">
            <p><strong>🔒 Sécurité :</strong> Si vous n'êtes pas à l'origine de ces modifications, veuillez contacter immédiatement votre administrateur.</p>
        </div>

        <br>
        <p>Cordialement,</p>
        <p><strong>L'équipe Tracimo  </strong></p>

        <div class="footer">
            <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
            <p>&copy; {{ date('Y') }} Tracimo   - Tous droits réservés.</p>
        </div>
    </div>
</body>
</html>
