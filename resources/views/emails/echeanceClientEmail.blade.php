<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rappel d'Échéance</title>
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
            background: linear-gradient(135deg, #9b59b6, #8e44ad);
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
            background: #f8f5ff;
            border-left: 4px solid #9b59b6;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
        }
        .amount {
            font-size: 24px;
            font-weight: bold;
            color: #27ae60;
            text-align: center;
            margin: 15px 0;
        }
        .urgent {
            background: #ffeaa7;
            padding: 10px;
            border-radius: 5px;
            text-align: center;
            margin: 15px 0;
        }
        .footer {
            background: #2c3e50;
            color: #ffffff;
            padding: 20px;
            text-align: center;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>💰 Rappel d'Échéance</h1>
        </div>

        <div class="content">
            <h2>Bonjour {{ $name }} !</h2>

            <div class="urgent">
                <strong>📋 Votre échéance approche</strong>
            </div>

            <p>Nous vous rappelons votre prochaine échéance pour votre projet immobilier :</p>

            <div class="info-card">
                <p><strong>Projet :</strong> {{ $projet ?? 'Non spécifié' }}</p>
                @if($bien)
                <p><strong>Bien concerné :</strong> {{ $bien }}</p>
                @endif
                <p><strong>Date d'échéance :</strong> {{ $echeance ? \Carbon\Carbon::parse($echeance)->format('d/m/Y') : $date }}</p>

                @if($montant)
                <div class="amount">
                    Montant dû : {{ number_format($montant, 2, ',', ' ') }} €
                </div>
                @endif
            </div>

            <p>Nous vous remercions pour votre confiance et restons à votre disposition pour toute question concernant cette échéance.</p>

            <p><strong>L'équipe Immobilier</strong></p>
        </div>

        <div class="footer">
            &copy; {{ date('Y') }} Immobilier - Votre partenaire de confiance<br>
            <small>Contact : <a href="mailto:support@immobilier.com" style="color: #3498db; text-decoration: underline;">support@immobilier.com</a></small>
        </div>
    </div>
</body>
</html>
