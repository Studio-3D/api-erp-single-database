<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Bienvenue chez Tracimo</title>
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
            background-color: #231651;
            color: white;
            padding: 20px 20px;
            text-align: center;
            border-radius: 10px 10px 0 0;
            margin: -20px -20px 20px -20px;
        }
        .header img {
            max-width: 120px;
            height: auto;
            display: block;
            margin: 0 auto 10px auto;
        }
        .header h1 {
            margin: 0;
            font-size: 20px;
            font-weight: bold;
        }
        .header p {
            margin: 5px 0 0 0;
            font-size: 13px;
            opacity: 0.9;
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
            background-color: #231651;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 14px;
        }
        .welcome-title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .subtitle {
            font-size: 13px;
            margin-bottom: 10px;
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
            <h1>Bienvenue chez Tracimo !</h1>
        </div>

        <p>Bonjour <strong>{{ $nom ?? '' }} {{ $prenom ?? '' }}</strong>,</p>

        <p>Nous avons le plaisir de vous informer que votre inscription en tant que <strong>{{ $role ?? 'Utilisateur' }}</strong> a bien été prise en compte. Vous pouvez désormais profiter de notre solution de gestion immobilière.</p>

        <p>Vous trouverez ci-dessous vos <strong>identifiants de connexion</strong>.</p>

        <div class="credentials">
            <p><strong>📧 Email :</strong> {{ $email ?? '' }}</p>
            <p><strong>🔑 Mot de passe temporaire :</strong> {{ $password ?? '' }}</p>
        </div>
        <table role="presentation" border="0" cellpadding="0" cellspacing="0" class="btn btn-primary">
            <tbody>
                <tr>
                    <td align="left">
                        <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                            <tbody>
                                <tr>
                                    <td>
                                        <a href="{{ config('app.frontend_url', 'https://tracimo.com') }}/login" target="_blank" style="display: inline-block; background-color: #231651; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px;">Se connecter</a>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </td>
                </tr>
            </tbody>
        </table>

        <p><strong>Important :</strong> Concernant le mot de passe, <strong>vous seul le connaissez</strong>. Nous vous recommandons de le modifier lors de votre première connexion pour des raisons de sécurité.</p>

        <p>Vous disposez à présent d'un compte <strong>{{ $role ?? 'Utilisateur' }}</strong> dans notre solution Tracimo.</p>

        <br>
        <p>Cordialement,</p>
        <p><strong>L'équipe Tracimo</strong></p>

        <div class="footer">
            <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
            <p>&copy; {{ date('Y') }} Tracimo - Tous droits réservés.</p>
        </div>
    </div>
</body>
</html>
