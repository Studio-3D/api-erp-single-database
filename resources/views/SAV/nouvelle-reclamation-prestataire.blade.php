<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouvelle Réclamation Assignée</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f6f9;
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
            background: linear-gradient(135deg, #3498db, #2980b9);
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
        .reclamation-card {
            background: #fff;
            border: 2px solid #e3f2fd;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .reclamation-id {
            font-size: 24px;
            font-weight: bold;
            color: #2980b9;
            margin: 10px 0;
            text-align: center;
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
            transition: background 0.3s ease;
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
        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin: 15px 0;
        }
        .detail-item {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .detail-label {
            font-weight: bold;
            color: #7f8c8d;
            display: block;
            margin-bottom: 5px;
        }
        .detail-value {
            color: #2c3e50;
            font-size: 14px;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            background: #2ecc71;
            color: white;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 10px;
        }
        .problème-section {
            background: #fef5e7;
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
        }
        .contact-info {
            background: #e8f6f3;
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
        }
        .deadline {
            background: #ffebee;
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
            border-left: 4px solid #e74c3c;
        }
        .section-title {
            color: #3498db;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
            margin-top: 25px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔧 Nouvelle Réclamation Assignée</h1>
        </div>

        <div class="content">
            <h2>Bonjour {{ $prestataire['nom'] }} {{ $prestataire['prenom'] }} !</h2>

            <div class="urgent">
                <strong>📋 Une nouvelle réclamation vous a été assignée</strong>
            </div>

            <p>Vous avez été sélectionné pour traiter une réclamation importante. Voici les détails :</p>

            <div class="reclamation-card">
                <div class="reclamation-id">
                    Réclamation #{{ $reclamation['id'] ?? '' }}
                </div>

                <div class="details-grid">
                    <div class="detail-item">
                        <span class="detail-label">Client :</span>
                        <span class="detail-value">{{ $client['nom'] }} {{ $client['prenom'] ?? '' }}</span>
                    </div>

                    <div class="detail-item">
                        <span class="detail-label">Téléphone :</span>
                        <span class="detail-value">{{ $client['telephone'] ?? '' }}</span>
                    </div>

                    <div class="detail-item">
                        <span class="detail-label">Bien concerné :</span>
                        <span class="detail-value">{{ $bien['reference'] ?? '' }}</span>
                    </div>



                    <div class="detail-item">
                        <span class="detail-label">Type de service :</span>
                        <span class="detail-value">{{ $service['nom'] ?? '' }}</span>
                    </div>

                    <div class="detail-item">
                        <span class="detail-label">Date de réclamation :</span>
                        <span class="detail-value">{{ $reclamation['date_reclamation'] ?? date('d/m/Y') }}</span>
                    </div>
                </div>
            </div>

            <h3 class="section-title">📍 Emplacement du problème</h3>
            <div class="info-card">
                <p><strong>Zone concernée :</strong><br>
                {{ $reclamation['emplacements'] ?? 'Non spécifié' }}</p>
            </div>

            <h3 class="section-title">🔍 Description des problèmes</h3>
            <div class="problème-section">
                <p>{{ $reclamation['problemes'] ?? 'Description non disponible' }}</p>
            </div>

            <div class="contact-info">
                <h3>📞 Contact client</h3>
                <p><strong>Email :</strong> {{ $client['email'] ?? 'Non disponible' }}</p>
                <p><strong>Téléphone :</strong> {{ $client['telephone'] ?? 'Non disponible' }}</p>
                <p><strong>Adresse :</strong> {{ $client['adresse'] ?? 'Non disponible' }}</p>
            </div>

            <div class="deadline">
                <h3>⏰ Intervention demandée</h3>
                <p><strong>Date d'intervention prévue :</strong><br>
                {{ $reclamation['date_intervention'] ?? 'À planifier' }}</p>
                <p>Merci de confirmer cette date ou de proposer une alternative au client.</p>
            </div>

            <h3 class="section-title">📋 Actions à effectuer</h3>
            <ul>
                <li>Contacter le client pour confirmer l'intervention</li>
                <li>Visiter le site pour évaluer la situation</li>
                <li>Fournir un devis si nécessaire</li>
                <li>Planifier l'intervention</li>
                <li>Mettre à jour le statut de la réclamation</li>
            </ul>

            <p>Vous pouvez accéder à cette réclamation et mettre à jour son statut en cliquant sur le bouton ci-dessous :</p>

            <a href="{{ $reclamationLink }}" class="btn">
                📋 Accéder à la Réclamation
            </a>

            <div class="urgent" style="background: #e8f6f3;">
                <strong>💡 Informations importantes :</strong><br>
                N'oubliez pas de mettre à jour le statut de la réclamation après chaque étape.<br>
                Votre réactivité est essentielle pour la satisfaction client.
            </div>

            <p>Pour toute question, contactez notre service SAV au 01 23 45 67 89 ou par email à sav@immobilier.com</p>

            <p><strong>L'équipe Immobilier Immo - Service Après-Vente</strong></p>
        </div>

        <div class="footer">
            &copy; {{ date('Y') }} Immobilier Immo - Tous droits réservés<br>
            <small>Cet email a été généré automatiquement. Merci de ne pas y répondre directement.<br>
            Pour répondre au client, utilisez ses coordonnées ci-dessus.</small>
        </div>
    </div>
</body>
</html>
