<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation de Rendez-vous</title>
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
            background: #3498db;
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
            <h1>🎯Rendez-vous @if($prospectName) Tracimo @else Greenland @endif</h1>
        </div>

        <div class="content">
            <h2>Bonjour {{ $name }} !</h2>
            @if($prospectName)
             <p>Un nouveau rendez-vous a été programmé avec un prospect :</p>
            @else
             <p>Nous sommes ravis de vous confirmer le rendez-vous suivant :</p>
            @endif
            <div class="info-card">
                <p><strong>Projet :</strong> {{ $projet ?? 'Non spécifié' }}</p>
                @if($bien)
                <p><strong>Bien concerné :</strong> {{ $bien }}</p>
                @endif
                @if($prospectName)
                <p><strong>Prospect :</strong> {{ $prospectName }}</p>
                @endif
                 @if($prospectName)
                    @if($tel ?? false)
                    <p><strong>📞 Téléphone :</strong> {{ $tel }}</p>
                     @endif
                @endif
                <p><strong>Date du rendez-vous :</strong> {{ $rdv }}</p>
            </div>
          @if($prospectName)

            <p>Merci de préparer ce rendez-vous et d'accueillir le prospect dans les meilleures conditions.</p>
            <p>N'oubliez pas de confirmer votre disponibilité si ce n'est pas déjà fait.</p>
            @else
             <p>Nous vous attendons avec impatience pour échanger sur ce projet qui correspond parfaitement à vos attentes.</p>
                <p>Pour toute modification ou question, n'hésitez pas à nous contacter.</p>
            @endif
        </div>

        <div class="footer">
            &copy; {{ date('Y') }}  @if($prospectName) Tracimo @else Greenland @endif  - Votre partenaire de confiance<br>
        </div>
    </div>
</body>
</html>
