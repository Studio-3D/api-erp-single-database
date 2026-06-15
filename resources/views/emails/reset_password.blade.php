<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Réinitialisation de votre mot de passe</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 500px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            background-color: #231651;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 10px 10px 0 0;
            margin: -30px -30px 20px -30px;
        }
        .header img {
            max-width: 120px;
            height: auto;
            display: block;
            margin: 0 auto;
        }
        .step {
            font-weight: bold;
            margin: 10px 0;
        }
        .warning {
            background: #FEF3C7;
            padding: 10px;
            border-left: 4px solid #F59E0B;
            margin: 15px 0;
        }
        hr {
            margin: 20px 0;
            border: none;
            border-top: 1px solid #eee;
        }
        .btn {
            background-color: #231651;
            color: #FFFFFF;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 6px;
            display: inline-block;
            font-weight: bold;
            font-size: 14px;
        }
        .btn:hover {
            background-color: #1a0f3d;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            @php
                // Try to embed if it's an email (when $message object exists)
                if (isset($message) && method_exists($message, 'embed')) {
                    $logo = $message->embed(public_path('docs/logo/tracimo_blue.png'));
                } else {
                    // Fallback to asset URL
                    $logo = asset('docs/logo/tracimo_blue.png');
                }
            @endphp
            <img src="{{ $logo }}" alt="Tracimo Logo">
        </div>

        <h2 style="color: #231651; margin-top: 0;">🔐 Réinitialisation de votre mot de passe</h2>

        <p><strong>Bonjour,</strong></p>

        <p>Vous avez demandé à réinitialiser votre mot de passe. Cliquez sur le bouton ci-dessous :</p>

        <div style="text-align: center; margin: 25px 0;">
            <a href="{{ $resetUrl }}" class="btn" style="background-color: #231651; color: #FFFFFF; padding: 12px 30px; text-decoration: none; border-radius: 6px; display: inline-block; font-weight: bold; font-size: 14px;">Réinitialiser mon mot de passe</a>
        </div>

        <div class="warning">
            <strong>📌 Note :</strong> Ce lien expirera dans une heure et ne pourra être utilisé qu'une seule fois.
        </div>

        <p><strong>📝 Procédure :</strong></p>
        <ul>
            <li><strong>1.</strong> Cliquez sur le bouton ci-dessus</li>
            <li><strong>2.</strong> Saisissez votre <strong>nouveau mot de passe</strong></li>
            <li><strong>3.</strong> <strong>Confirmez</strong> votre nouveau mot de passe</li>
            <li><strong>4.</strong> Cliquez sur <strong>"Envoyer"</strong></li>
        </ul>

        <hr>

        <p style="color: #6B7280; font-size: 12px;">
            Si vous n'êtes pas à l'origine de cette demande, ignorez simplement cet email.
        </p>

        <p><strong>L'équipe Tracimo</strong></p>
    </div>
</body>
</html>
