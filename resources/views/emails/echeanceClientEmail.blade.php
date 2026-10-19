<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rappel Échéance - Paiement </title>
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
            background: linear-gradient(135deg, #e74c3c, #c0392b);
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
            background: #ffe5e5;
            border-left: 4px solid #e74c3c;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
        }
        .amount {
            font-size: 24px;
            font-weight: bold;
            color: #e74c3c;
            text-align: center;
            margin: 15px 0;
        }
        .warning-today {
            background: #fff3cd;
            border: 1px solid #ffecb5;
            color: #856404;
            padding: 12px;
            border-radius: 6px;
            text-align: center;
            margin-top: 15px;
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
            <h1>💰 Rappel Échéance</h1>
        </div>

        <div class="content">
            <h2>Bonjour {{ $name }} !</h2>

            <div style="background: #ffeaa7; padding: 10px; border-radius: 5px; text-align: center; margin: 15px 0;">
                <strong>📋 Votre échéance est due AUJOURD'HUI</strong>
            </div>

            <p>Nous vous rappelons votre échéance pour votre Bien :</p>

            <div class="info-card">
                <p><strong>🏠 Projet :</strong> {{ $projet ?? 'Non spécifié' }}</p>

                @if($bien)
                <p><strong>📍 Bien concerné :</strong> {{ $bien }}</p>
                @endif

                <p><strong>📅 Date d'échéance :</strong> <strong style="color: #e74c3c;">{{ $echeance ?? date('d/m/Y') }}</strong></p>

                @if($montant)
                <div class="amount">
                    Montant dû : {{ number_format($montant, 2, ',', ' ') }} MAD
                </div>
                @endif
            </div>

            <div class="warning-today">
                ⚠️ <strong>Ce paiement est dû aujourd'hui.</strong> Merci d'effectuer le règlement sans délai.
            </div>

            <p>Nous vous remercions pour votre confiance et restons à votre disposition pour toute question concernant cette échéance.</p>

            <p><strong>Cordialement,</strong><br>L'équipe {{ env('CLIENT_NOM') }} </p>
        </div>


        <div class="footer">
            &copy; {{ date('Y') }} {{ env('CLIENT_NOM') }} - Votre partenaire de confiance<br>
            <small>Contact : <a href="mailto:{{ env('MAIL_USERNAME') }}" style="color: #3498db; text-decoration: underline;">{{ env('MAIL_USERNAME') }}</a></small>
        </div>
    </div>
</body>
</html>
