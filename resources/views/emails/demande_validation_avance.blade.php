<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validation d'Avance</title>
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
            background: linear-gradient(135deg, #27ae60, #2ecc71);
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
            background: #f0f9f4;
            border-left: 4px solid #27ae60;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
        }
        .amount-card {
            background: #e8f6ef;
            border: 2px solid #27ae60;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            text-align: center;
        }
        .amount {
            font-size: 28px;
            font-weight: bold;
            color: #27ae60;
            margin: 10px 0;
        }
        .btn {
            display: inline-block;
            margin: 20px 0;
            padding: 12px 30px;
            background: #27ae60;
            color: #ffffff;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
            text-align: center;
        }
        .btn:hover {
            background: #219653;
        }
        .footer {
            background: #2c3e50;
            color: #ffffff;
            padding: 20px;
            text-align: center;
            font-size: 14px;
        }
        .urgent {
            background: #d5f4e6;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            margin: 20px 0;
            border-left: 4px solid #27ae60;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>💰 Validation d'Avance</h1>
        </div>

        <div class="content">
            <h2>Bonjour {{ $adminName }} !</h2>

            <div class="urgent">
                <strong>Une nouvelle avance nécessite votre validation</strong>
            </div>

            <p>Un paiement a été enregistré et nécessite votre validation pour la réservation suivante :</p>

            <div class="info-card">
                <div class="details-grid">
                    <div class="detail-item">
                        <strong>Réservation :</strong><br>
                        {{ $reservationCode }}
                    </div>
                    <div class="detail-item">
                        <strong>N° Avance :</strong><br>
                        {{ $avanceNumero }}
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
            </div>

            <div class="amount-card">
                <h3>Montant de l'avance</h3>
                <div class="amount">
                    {{ $montantAvance }} MAD
                </div>
                <p>Enregistré par : <strong>{{ $createdBy }}</strong></p>
            </div>

            <p>Merci de procéder à la validation de cette avance en cliquant sur le lien ci-dessous :</p>

            <a href="{{ $validationLink }}" class="btn">
                ✅ Valider l'Avance
            </a>

            <p>Ce lien vous dirigera directement vers la page de détail de la réservation où vous pourrez :</p>
            <ul>
                <li>Vérifier les détails du paiement</li>
                <li>Consulter l'historique des avances</li>
                <li>Valider ou rejeter cette avance</li>
                <li>Générer les documents nécessaires</li>
            </ul>

            <p><strong>L'équipe Tracimo </strong></p>
        </div>

        <div class="footer">
            &copy; {{ date('Y') }} Tracimo  - Tous droits réservés<br>
            <small>Cet email a été généré automatiquement, merci de ne pas y répondre.</small>
        </div>
    </div>
</body>
</html>
