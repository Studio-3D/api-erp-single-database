<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demande de Validation de Réservation</title>
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
            background: linear-gradient(135deg, #2c3e50, #3498db);
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
            background: #f8f9fa;
            border-left: 4px solid #3498db;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
        }
        .btn {
            display: inline-block;
            margin: 20px 0;
            padding: 12px 30px;
            background: #3498db;
            color: #ffffff;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
            text-align: center;
        }
        .btn:hover {
            background: #2980b9;
        }
        .footer {
            background: #2c3e50;
            color: #ffffff;
            padding: 20px;
            text-align: center;
            font-size: 14px;
        }
        .urgent {
            background: #ffeaa7;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            margin: 20px 0;
            border-left: 4px solid #f39c12;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 Demande de Validation</h1>
        </div>

        <div class="content">
            <h2>Bonjour {{ $adminName }} !</h2>

            <div class="urgent">
                <strong>Une nouvelle réservation nécessite votre validation</strong>
            </div>

            <p>Une demande de validation a été soumise pour la réservation suivante :</p>

            <div class="info-card">
                <p><strong>Code Réservation :</strong> {{ $reservationCode }}</p>
                <p><strong>Date de création :</strong> {{ $dateCreation }}</p>
                <p><strong>Créée par :</strong> {{ $createdBy }}</p>
            </div>

            <p>Merci de procéder à la validation de cette réservation en cliquant sur le lien ci-dessous :</p>

            <a href="{{ $validationLink }}" class="btn">
                🔍 Valider la Réservation
            </a>

            <p>Ce lien vous dirigera directement vers la page de détail de la réservation où vous pourrez :</p>
            <ul>
                <li>Vérifier les informations de la réservation</li>
                <li>Consulter les détails du client</li>
                <li>Valider ou rejeter la réservation</li>
            </ul>

            <p><strong>L'équipe Immobilier</strong></p>
        </div>

        <div class="footer">
            &copy; {{ date('Y') }} Immobilier Immo - Tous droits réservés<br>
            <small>Cet email a été généré automatiquement, merci de ne pas y répondre.</small>
        </div>
    </div>
</body>
</html>
