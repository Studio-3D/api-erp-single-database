<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validation de Pénalité</title>
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
            background: linear-gradient(135deg, #d35400, #e67e22);
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
            background: #fef9f3;
            border-left: 4px solid #e67e22;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
        }
        .penalite-card {
            background: #fff5f5;
            border: 2px solid #e74c3c;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            text-align: center;
        }
        .montant {
            font-size: 28px;
            font-weight: bold;
            color: #e74c3c;
            margin: 10px 0;
        }
        .btn {
            display: inline-block;
            margin: 20px 0;
            padding: 12px 30px;
            background: #e67e22;
            color: #ffffff;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
            text-align: center;
        }
        .btn:hover {
            background: #d35400;
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
        .type-badge {
            display: inline-block;
            padding: 4px 12px;
            background: #e67e22;
            color: white;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚖️ Validation de Pénalité</h1>
        </div>

        <div class="content">
            <h2>Bonjour {{ $adminName }} !</h2>

            <div class="urgent">
                <strong>💰 Une pénalité financière nécessite votre validation</strong>
            </div>

            <p>Une pénalité a été appliquée suite à un désistement et nécessite votre validation :</p>

            <div class="info-card">
                <div class="details-grid">
                    <div class="detail-item">
                        <strong>Code Pénalité :</strong><br>
                        {{ $penaliteCode }}
                    </div>

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



                <p><strong>Proposée par :</strong> {{ $createdBy }}</p>
            </div>

            <div class="penalite-card">
                <h3>Montant de la Pénalité</h3>
                <div class="montant">
                    {{ $montantPenalite }}
                </div>
                <p>Ce montant sera appliqué au client suite à votre validation</p>
            </div>

            <p>Merci de procéder à la validation de cette pénalité en cliquant sur le lien ci-dessous :</p>

            <a href="{{ $validationLink }}" class="btn">
                ✅ Valider la Pénalité
            </a>

            <p>Ce lien vous dirigera directement vers la page de détail de la pénalité où vous pourrez :</p>
            <ul>
                <li>Vérifier le calcul de la pénalité</li>
                <li>Consulter les détails du désistement</li>
                <li>Examiner la conformité avec la politique de pénalités</li>
                <li>Valider ou ajuster le montant de la pénalité</li>
                <li>Générer les documents de pénalité</li>
            </ul>

            <div class="urgent">
                <strong>📋 Important :</strong> Vérifiez que le montant de la pénalité est conforme
                au contrat et à la politique de l'entreprise avant validation.
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
