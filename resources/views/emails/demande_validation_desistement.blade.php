<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validation de Désistement</title>
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
            background: #fdf2f2;
            border-left: 4px solid #e74c3c;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
        }
        .warning-card {
            background: #fff5f5;
            border: 2px solid #e74c3c;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            text-align: center;
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
            text-align: center;
        }
        .btn:hover {
            background: #c0392b;
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
        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin: 15px 0;
        }
        .detail-item {
            padding: 8px 0;
        }
        .motif-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚫 Validation de Désistement</h1>
        </div>

        <div class="content">
            <h2>Bonjour {{ $adminName }} !</h2>

            <div class="warning-card">
                <strong>⚠️ ATTENTION : Une demande de désistement nécessite votre validation</strong>
            </div>

            <p>Un désistement a été demandé pour la réservation suivante :</p>

            <div class="info-card">
                <div class="details-grid">

                    <div class="detail-item">
                        <strong>Réservation :</strong><br>
                        {{ $reservationCode }}
                    </div>
                    <div class="detail-item">
                        <strong>Projet :</strong><br>
                        {{ $projetName }}
                    </div>
                    <div class="detail-item">
                        <strong>Date :</strong><br>
                        {{ $dateCreation }}
                    </div>
                </div>



                <p><strong>Demandé par :</strong> {{ $createdBy }}</p>
            </div>

            <p>Merci de procéder à la validation de ce désistement en cliquant sur le lien ci-dessous :</p>

            <a href="{{ $validationLink }}" class="btn">
                🔍 Examiner le Désistement
            </a>

            <p>Ce lien vous dirigera directement vers la page de détail du désistement où vous pourrez :</p>
            <ul>
                <li>Consulter les détails complets du désistement</li>
                <li>Vérifier le motif fourni</li>
                <li>Examiner les implications financières</li>
                <li>Valider ou rejeter la demande de désistement</li>
                <li>Gérer les éventuels remboursements</li>
            </ul>

            <div class="urgent">
                <strong>💡 Important :</strong> Cette action est irréversible une fois validée.
                Veuillez vérifier attentivement toutes les informations avant validation.
            </div>

            <p><strong>L'équipe Tracimo </strong></p>
        </div>

        <div class="footer">
            &copy; {{ date('Y') }} Tracimo  - Tous droits réservés<br>
            <small>Cet email a été généré automatiquement, merci de ne pas y répondre.</small>
        </div>
    </div>
</body>
</html>
