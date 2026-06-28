<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>FICHE CLIENT</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', 'Arial', sans-serif;
            font-size: 10px;
            line-height: 1.3;
            padding: 15px;
            background-color: #fff;
        }
        .container {
            max-width: 100%;
            margin: 0 auto;
        }

        /* ========== HEADER: Logo + Company info on SAME ROW using TABLE (PDF FRIENDLY - like fiche prospect) ========== */
        .header-table {
            display: table;
            width: 100%;
            margin-bottom: 15px;
        }
        .header-row {
            display: table-row;
        }
        .logo-cell {
            display: table-cell;
            width: 70px;
            vertical-align: top;
        }
        .logo-container {
            width: 70px;
            height: 70px;
        }
        .logo-container img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .company-cell {
            display: table-cell;
            text-align: right;
            vertical-align: top;
        }
        .company-info {
            text-align: right;
            font-size: 9px;
            line-height: 1.4;
        }
        .company-name {
            font-weight: bold;
            font-size: 10px;
            margin-bottom: 3px;
        }

        .title {
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 10px;
            text-decoration: underline;
        }
        .badge {
            background-color: #2C3E50;
            color: white;
            padding: 6px;
            text-align: center;
            margin-bottom: 20px;
        }
        .section {
            margin-bottom: 20px;
            page-break-inside: avoid;
        }
        .section-title {
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 10px;
            margin-top: 10px;
            color: #2C3E50;
            border-bottom: 2px solid #3498DB;
            padding-bottom: 3px;
        }

        /* ========== TWO COLUMNS USING TABLE (PDF FRIENDLY - NO FLEXBOX) ========== */
        .two-columns-table {
            display: table;
            width: 100%;
            margin-bottom: 5px;
        }
        .two-columns-row {
            display: table-row;
        }
        .col-left {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-right: 20px;
        }
        .col-right {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-left: 20px;
            border-left: 1px solid #E0E0E0;
        }

        /* Individual info line using table */
        .info-line {
            display: table;
            width: 100%;
            margin-bottom: 10px;
        }
        .info-label {
            display: table-cell;
            width: 110px;
            font-size: 9px;
            font-weight: bold;
            color: #666;
            vertical-align: top;
            padding-bottom: 2px;
        }
        .info-value {
            display: table-cell;
            font-size: 10px;
            color: #2C3E50;
            vertical-align: top;
            word-break: break-word;
        }
        .info-value.empty {
            color: #999;
            font-style: italic;
        }

        /* Table styles for reservations and visits */
        .data-table {
            width: 100%;
            margin-top: 10px;
            border-collapse: collapse;
        }
        .data-table th {
            background-color: #34495E;
            color: white;
            font-size: 8px;
            font-weight: bold;
            padding: 6px 4px;
            text-align: left;
            border: 1px solid #2C3E50;
        }
        .data-table td {
            font-size: 8px;
            padding: 5px 4px;
            border: 1px solid #E4E4E4;
            color: #34495E;
        }
        .data-table tr:nth-child(even) {
            background-color: #F8F9FA;
        }

        .message-box {
            margin-top: 15px;
            padding: 8px;
            background-color: #F0F7FF;
        }
        .message-text {
            font-size: 9px;
            color: #2C3E50;
            font-style: italic;
            text-align: center;
        }
        .footer {
            text-align: center;
            font-size: 8px;
            margin-top: 20px;
            color: #7F8C8D;
            padding-top: 8px;
            border-top: 1px solid #E4E4E4;
        }

        /* Badge styles */
        .badge-statut {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 8px;
            font-weight: bold;
        }
        .status-valid { background-color: #27AE60; color: white; }
        .status-rejected { background-color: #E74C3C; color: white; }
        .status-pending { background-color: #F39C12; color: white; }
        .interesse { color: #27AE60; font-weight: bold; }
        .vendu { color: #27AE60; font-weight: bold; }

        /* Empty message */
        .empty-message {
            background: #f9f9fc;
            padding: 10px;
            text-align: center;
            font-size: 9px;
            color: #6c757d;
            border: 1px dashed #ced4da;
        }

        @media print {
            body { padding: 0.2in; }
            .badge { background-color: #2C3E50 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .data-table th { background-color: #34495E !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .message-box { background-color: #F0F7FF !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .col-right { border-left: 1px solid #E0E0E0; }
        }
    </style>
</head>
<body>
    <div class="container">

        <!-- ========== HEADER: Logo left + Company info right on SAME ROW (TABLE based - PDF compatible, like fiche prospect) ========== -->
        <div class="header-table">
            <div class="header-row">
                <div class="logo-cell">
                    <div class="logo-container">
                        @if($logoBase64)
                            <img src="{{ $logoBase64 }}" alt="Logo">
                        @endif
                    </div>
                </div>
                <div class="company-cell">
                    <div class="company-info">
                        <div class="company-name">{{ $societe['raison_sociale'] ?? 'Société' }}</div>
                        @if(!empty($societe['tel']))
                            <div>Tél: {{ $societe['tel'] }}</div>
                        @endif
                        @if(!empty($societe['email']))
                            <div>Email: {{ $societe['email'] }}</div>
                        @endif
                        @if(!empty($societe['adresse']))
                            <div>Adresse:{{ $societe['adresse'] }}</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Title -->
        <div class="title">FICHE CLIENT</div>

        <div class="badge">
            <div style="font-weight: bold; font-size: 9px;">Document confidentiel - Suivi client</div>
        </div>

        <!-- ========== SECTION 1: INFORMATIONS PERSONNELLES - Two columns using TABLE (no flexbox) ========== -->
        <div class="section">
            <div class="section-title">INFORMATIONS PERSONNELLES</div>
            <div class="two-columns-table">
                <div class="two-columns-row">
                    <!-- LEFT COLUMN -->
                    <div class="col-left">
                        <div class="info-line">
                            <div class="info-label">Code Client</div>
                            <div class="info-value">{{ $client['code_client'] ?? '' }}</div>
                        </div>
                        <div class="info-line">
                            <div class="info-label">Nom complet</div>
                            <div class="info-value">{{ trim(($client['nom'] ?? '') . ' ' . ($client['prenom'] ?? '')) ?: '' }}</div>
                        </div>
                        <div class="info-line">
                            <div class="info-label">CIN</div>
                            <div class="info-value">{{ $client['cin'] ?? '' }}</div>
                        </div>
                        <div class="info-line">
                            <div class="info-label">Téléphone principal</div>
                            <div class="info-value">{{ $client['telephone_num1'] ?? '' }}</div>
                        </div>
                        <div class="info-line">
                            <div class="info-label">Téléphone secondaire</div>
                            <div class="info-value {{ empty($client['telephone_num2']) ? 'empty' : '' }}">{{ $client['telephone_num2'] ?? '' }}</div>
                        </div>
                        <div class="info-line">
                            <div class="info-label">Email</div>
                            <div class="info-value {{ empty($client['email']) ? 'empty' : '' }}">{{ $client['email'] ?? '' }}</div>
                        </div>
                    </div>
                    <!-- RIGHT COLUMN -->
                    <div class="col-right">
                        <div class="info-line">
                            <div class="info-label">Civilité</div>
                            <div class="info-value">{{ $formatCivilite($client['civilite'] ?? null) ?: '' }}</div>
                        </div>
                        <div class="info-line">
                            <div class="info-label">Pays / Ville</div>
                            <div class="info-value">
                                @if(!empty($client['pays']) || !empty($client['ville']))
                                    {{ $client['pays'] ?? '' }}@if(!empty($client['pays']) && !empty($client['ville'])) / @endif{{ $client['ville'] ?? '' }}
                                @else

                                @endif
                            </div>
                        </div>
                        <div class="info-line">
                            <div class="info-label">Adresse</div>
                            <div class="info-value {{ empty($client['adresse']) ? 'empty' : '' }}">{{ $client['adresse'] ?? '' }}</div>
                        </div>
                        <div class="info-line">
                            <div class="info-label">Nationalité</div>
                            <div class="info-value {{ empty($client['nationalite']) ? 'empty' : '' }}">{{ $client['nationalite'] ?? '' }}</div>
                        </div>
                        <div class="info-line">
                            <div class="info-label">Profession</div>
                            <div class="info-value {{ empty($client['profession']) ? 'empty' : '' }}">{{ $client['profession'] ?? '' }}</div>
                        </div>
                        <!--div class="info-line">
                            <div class="info-label">Accepte d'être contacté</div>
                            <div class="info-value">{{ ($client['notifie'] ?? 0) == 1 ? 'Oui' : 'Non' }}</div>
                        </div> -->
                    </div>
                </div>
            </div>
        </div>

        <!-- ========== SECTION 2: DÉTAILS COMPLÉMENTAIRES - Two columns using TABLE (no flexbox) ========== -->
        <div class="section">
            <div class="section-title">DÉTAILS COMPLÉMENTAIRES</div>
            <div class="two-columns-table">
                <div class="two-columns-row">
                    <!-- LEFT COLUMN -->
                    <div class="col-left">
                        <div class="info-line">
                            <div class="info-label">Date de naissance</div>
                            <div class="info-value">{{ $formatDate($client['date_naissance'] ?? null) ?: '' }}</div>
                        </div>
                        <div class="info-line">
                            <div class="info-label">Lieu de naissance</div>
                            <div class="info-value {{ empty($client['lieu_naissance']) ? 'empty' : '' }}">{{ $client['lieu_naissance'] ?? '' }}</div>
                        </div>
                        <div class="info-line">
                            <div class="info-label">Situation familiale</div>
                            <div class="info-value">{{ $getSituationLabel($client['situation_familliale'] ?? null) ?: '' }}</div>
                        </div>
                        @if(!empty($client['nom_mari']))
                        <div class="info-line">
                            <div class="info-label">Nom du mari</div>
                            <div class="info-value">{{ $client['nom_mari'] }}</div>
                        </div>
                        @endif
                        @if(!empty($client['lieu_mariage']))
                        <div class="info-line">
                            <div class="info-label">Lieu de mariage</div>
                            <div class="info-value">{{ $client['lieu_mariage'] }}</div>
                        </div>
                        @endif
                        @if(!empty($client['date_mariage']))
                        <div class="info-line">
                            <div class="info-label">Date de mariage</div>
                            <div class="info-value">{{ $formatDate($client['date_mariage']) }}</div>
                        </div>
                        @endif
                    </div>
                    <!-- RIGHT COLUMN -->
                    <div class="col-right">
                        @if(!empty($client['nom_pere']))
                        <div class="info-line">
                            <div class="info-label">Nom du père</div>
                            <div class="info-value">{{ $client['nom_pere'] }}</div>
                        </div>
                        @endif
                        @if(!empty($client['nom_mere']))
                        <div class="info-line">
                            <div class="info-label">Nom de la mère</div>
                            <div class="info-value">{{ $client['nom_mere'] }}</div>
                        </div>
                        @endif
                        @if(!empty($client['partenaire']['description']))
                        <div class="info-line">
                            <div class="info-label">Partenaire</div>
                            <div class="info-value">{{ $client['partenaire']['description'] }}</div>
                        </div>
                        @endif
                        @if(!empty($client['prospect']['origin']))
                        <div class="info-line">
                            <div class="info-label">Origine prospect</div>
                            <div class="info-value">{{ $client['prospect']['origin'] }}</div>
                        </div>
                        @endif
                        @if(empty($client['nom_pere']) && empty($client['nom_mere']) && empty($client['partenaire']['description']) && empty($client['prospect']['origin']))
                        <div class="info-line">
                            <div class="info-label"></div>
                            <div class="info-value empty">Aucune information complémentaire</div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- ========== SECTION 3: RÉSERVATIONS ========== -->
        @if(count($reservations) > 0)
        <div class="section">
            <div class="section-title">RÉSERVATIONS ({{ count($reservations) }})</div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Date</th>
                        <th>Bien</th>
                        <th>Prix</th>
                        <th>Avance</th>
                        <th>Reste</th>
                        <th>Financement</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($reservations as $row)
                    <tr>
                        <td>{{ $row['code_reservation'] ?? '' }}</td>
                        <td>{{ $formatDate($row['date_reservation'] ?? null) }}</td>
                        <td>{{ $getBienInfo($row['bien'] ?? null) ?: '-' }}</td>
                        <td>{{ $formatCurrency($row['prix'] ?? 0) }}</td>
                        <td>{{ $formatCurrency($row['avances_sum_montant'] ?? 0) }}</td>
                        <td>{{ $formatCurrency(($row['prix'] ?? 0) - ($row['avances_sum_montant'] ?? 0)) }}</td>
                        <td>{{ $formatFinancement($row['mode_financement'] ?? null) }}</td>
                        <td>
                            <span class="badge-statut status-{{ $formatStatutReservation($row['statut'] ?? null) == 'Validé' ? 'valid' : ($formatStatutReservation($row['statut'] ?? null) == 'Refusé' ? 'rejected' : 'pending') }}">
                                {{ $formatStatutReservation($row['statut'] ?? null) }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="section">
            <div class="section-title">RÉSERVATIONS</div>
            <div class="empty-message">Aucune réservation enregistrée pour ce client.</div>
        </div>
        @endif

        <!-- ========== SECTION 4: HISTORIQUE DES VISITES ========== -->
        @if(count($visites) > 0)
        <div class="section">
            <div class="section-title">HISTORIQUE DES VISITES ({{ count($visites) }})</div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Responsable</th>
                        <th>Intérêt</th>
                        <th>Statut</th>
                        <th>Bien</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($visites as $row)
                    <tr>
                        <td>{{ $formatDate($row['date'] ?? null) }}</td>
                        <td>{{ trim(($row['nom_cc'] ?? '') . ' ' . ($row['prenom_cc'] ?? '')) ?: '-' }}</td>
                        <td class="{{ $formatInteret($row['interet'] ?? null) == 'Intéressé' ? 'interesse' : '' }}">
                            {{ $formatInteret($row['interet'] ?? null) ?: '-' }}
                        </td>
                        <td class="{{ $formatStatutVisite($row['statut'] ?? null) == 'Vendu' ? 'vendu' : '' }}">
                            {{ $formatStatutVisite($row['statut'] ?? null) ?: '-' }}
                        </td>
                        <td>{{ $getBienInfo($row['bien'] ?? null) ?: '-' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="section">
            <div class="section-title">HISTORIQUE DES VISITES</div>
            <div class="empty-message">Aucune visite enregistrée pour ce client.</div>
        </div>
        @endif

        <!-- Message professionnel -->
        <div class="message-box">
            <div class="message-text">
                Ce document récapitule l'ensemble des informations client, réservations et visites.
                Toute modification doit être signalée au service commercial.
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            Document généré le {{ $currentDate }} — {{ $societe['raison_sociale'] ?? 'Société' }} —
            Tous droits réservés {{ date('Y') }}
        </div>
    </div>
</body>
</html>
