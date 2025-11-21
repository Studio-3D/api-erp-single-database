<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rappel de Relance</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f8f9fa;
            color: #333333;
            line-height: 1.6;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #e74c3c, #e67e22);
            color: #ffffff;
            padding: 30px 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: bold;
        }
        .content {
            padding: 30px;
        }
        .content h2 {
            color: #2c3e50;
            margin-top: 0;
        }
        .info-card {
            background: #fff5f5;
            border-left: 4px solid #e74c3c;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .footer {
            background: #2c3e50;
            color: #ffffff;
            padding: 20px;
            text-align: center;
            font-size: 14px;
        }
        .btn {
            display: inline-block;
            margin: 20px 0;
            padding: 12px 30px;
            background: #e74c3c;
            color: #ffffff;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔔 Rappel Important</h1>
        </div>

        <div class="content">
            <h2>Bonjour {{ $name }} !</h2>

            <p>Ceci est un rappel concernant le suivi de votre projet immobilier :</p>

            <div class="info-card">
                <p><strong>Projet :</strong> {{ $projet ?? 'Non spécifié' }}</p>
                @if($bien)
                <p><strong>Bien concerné :</strong> {{ $bien }}</p>
                @endif
                @if($prospectName)
                <p><strong>Prospect :</strong> {{ $prospectName }}</p>
                @endif
                <p><strong>Date de relance :</strong> {{ $date }}</p>
            </div>

            <p>Nous vous remercions pour votre confiance et restons à votre disposition pour toute information complémentaire.</p>

            <p>L'équipe Immobilier</p>
        </div>

        <div class="footer">
            &copy; {{ date('Y') }} Immobilier - Votre partenaire de confiance<br>
            <small>Contact : <a href="mailto:support@immobilier.com" style="color: #3498db; text-decoration: underline;">support@immobilier.com</a></small>
        </div>
    </div>
</body>
</html>
