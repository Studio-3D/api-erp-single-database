<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Urgent Échéance </title>
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
            background: linear-gradient(135deg, #f39c12, #e67e22);
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
            background: #fffaf0;
            border-left: 4px solid #f39c12;
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
            <h1>⚠️ Échéance Aujourd'hui</h1>
            <div class="today-badge">Paiement urgent</div>
        </div>

        <div class="content">
            <h2>Bonjour {{ $name }} !</h2>
                <p style="text-align: center; font-size: 16px;">
                    <strong class="urgent">🔔 PAIEMENT À EFFECTUER AUJOURD'HUI</strong>
                </p>
            <p>Une échéance importante pour le Bien suivant :</p>

            <div class="info-card">
               <p><strong>🏠 Projet :</strong> {{ $projet ?? 'Non spécifié' }}</p>

                @if($bien)
                <p><strong>📍 Bien concerné :</strong> {{ $bien }}</p>
                @endif

                @if($prospectName)
                <p><strong>👤 Client :</strong> {{ $prospectName }}</p>
                @endif

                <p><strong>📅 Date d'échéance :</strong> <span class="urgent">{{ $echeance ?? date('d/m/Y') }}</span> </p>

                @if($montant)
                <div class="amount">
                    💰 Montant à payer : {{ number_format($montant, 2, ',', ' ') }} MAD
                </div>
                @endif
            </div>

            <p>Merci de prendre les dispositions nécessaires pour le suivi de cette échéance.</p>

            <p>L'équipe Tracimo</p>
        </div>

        <div class="footer">
            &copy; {{ date('Y') }} Tracimo - Votre partenaire de confiance<br>
            <small>Contact : <a href="tracimo.admin@gmail.com" style="color: #3498db; text-decoration: underline;">tracimo.admin@gmail.com</a></small>
        </div>
    </div>
</body>
</html>
