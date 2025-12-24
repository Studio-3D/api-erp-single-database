<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Résultat de l'importation</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px;
            text-align: center;
            border-radius: 10px 10px 0 0;
            margin: -30px -30px 30px -30px;
        }
        .header h1 {
            color: white;
            margin: 0;
            font-size: 24px;
        }
        .status-success {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #c3e6cb;
            margin-bottom: 20px;
        }
        .status-warning {
            background-color: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #ffeaa7;
            margin-bottom: 20px;
        }
        .status-error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #f5c6cb;
            margin-bottom: 20px;
        }
        .info-card {
            background-color: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin: 20px 0;
            border-radius: 0 5px 5px 0;
        }
        .error-details {
            background-color: #fff5f5;
            border: 1px solid #fed7d7;
            border-radius: 5px;
            padding: 15px;
            margin: 15px 0;
        }
        .error-item {
            background-color: white;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            padding: 12px;
            margin: 10px 0;
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            color: #718096;
            font-size: 14px;
        }
        .stats {
            display: flex;
            justify-content: space-around;
            text-align: center;
            margin: 20px 0;
        }
        .stat-item {
            flex: 1;
            padding: 15px;
        }
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            display: block;
        }
        .stat-success { color: #38a169; }
        .stat-warning { color: #d69e2e; }
        .stat-error { color: #e53e3e; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        table th, table td {
            padding: 8px 12px;
            text-align: left;
            border: 1px solid #e2e8f0;
        }
        table th {
            background-color: #f7fafc;
            font-weight: bold;
        }

        /* New styles for the error table */
        .error-table-container {
            margin-top: 20px;
        }
        .error-table-header {
            font-size: 16px;
            font-weight: 600;
            color: #7c2d12;
            margin-bottom: 15px;
        }
        .error-table-wrapper {
            max-height: 384px;
            overflow-y: auto;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
        }
        .error-table {
            width: 100%;
            background-color: white;
            border-collapse: collapse;
            font-size: 14px;
        }
        .error-table thead {
            position: sticky;
            top: 0;
        }
        .error-table thead tr {
            background-color: #f8f9fa;
        }
        .error-table th, .error-table td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        .error-table th {
            color: #000000;
            font-weight: 600;
            border-bottom: 2px solid #e53e3e;
        }
        .error-table tbody tr:nth-child(even) {
            background-color: #f9fafb;
        }
        .error-table tbody tr:nth-child(odd) {
            background-color: white;
        }
        .error-line {
            font-weight: 500;
        }
        .error-message {
            color: #c53030;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Résultat de l'importation</h1>
        </div>

        <p>Bonjour <strong>{{ $adminName }}</strong>,</p>

        <div class="info-card">
            <strong>Fichier :</strong> {{ $fichier }}<br>
            <strong>Date de création :</strong> {{ $dateCreation->format('d/m/Y à H:i') }}<br>
            <strong>Lien de consultation :</strong> <a href="{{ $link_import }}">{{ $link_import }}</a>
        </div>

        @if($statut == '2')
            {{-- Import réussi --}}
            <div class="status-success">
                <h3>✅ Importation terminée avec succès</h3>
                <p>Toutes les données ont été importées correctement.</p>
            </div>

            <div class="stats">
                <div class="stat-item">
                    <span class="stat-number stat-success">{{ $total_lignes ?? 0 }}</span>
                    <span>Lignes importées</span>
                </div>
            </div>

        @elseif($statut == '3' && isset($message_echou) && is_array($message_echou))
            {{-- Import avec erreurs --}}
            <div class="status-warning">
                <h3>⚠️ Importation terminée avec des erreurs</h3>
                <p>L'importation s'est terminée mais certaines lignes n'ont pas pu être traitées.</p>
            </div>

            <div class="stats">
                <div class="stat-item">
                    <span class="stat-number stat-success">{{ $message_echou['lignes_reussies'] ?? 0 }}</span>
                    <span>Lignes réussies</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number stat-warning">{{ $message_echou['lignes_echouees'] ?? 0 }}</span>
                    <span>Lignes en erreur</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number">{{ $message_echou['total_lignes'] ?? 0 }}</span>
                    <span>Total lignes</span>
                </div>
            </div>

            @if(isset($message_echou['erreurs']) && count($message_echou['erreurs']) > 0)
                <div class="error-table-container">
                    <h4 class="error-table-header">Détails des erreurs :</h4>
                    <div class="">
                        <table class="error-table">
                            <thead>
                                <tr>
                                    <th>Ligne</th>
                                    <th>Erreur</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($message_echou['erreurs'] as $erreur)
                                    <tr>
                                        <td class="error-line">{{ $erreur['ligne'] }}</td>
                                        <td class="error-message">{{ $erreur['message'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

        @else
            {{-- Import échoué --}}
            <div class="status-error">
                <h3>❌ Échec de l'importation</h3>
                <p>L'importation a échoué. Veuillez vérifier votre fichier et réessayer.</p>
            </div>

            @if(isset($message_echou) && !is_array($message_echou))
                <div class="error-details">
                    <h4>Message d'erreur :</h4>
                    <p>{{ $message_echou }}</p>
                </div>
            @endif
        @endif

        <div style="text-align: center;">
            <a href="{{ $link_import }}" class="btn">Voir les détails de l'importation</a>
        </div>

        <div class="footer">
            <p>Cet email a été envoyé automatiquement par le système Immobilier Immo.</p>
            <p>Si vous n'êtes pas à l'origine de cette action, veuillez contacter l'administrateur.</p>
        </div>
    </div>
</body>
</html>
