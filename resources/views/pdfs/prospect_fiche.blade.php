<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>FICHE PROSPECT</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', 'Arial', sans-serif;
            font-size: 11px;
            line-height: 1.4;
            padding: 15px;
            background-color: #fff;
        }
        .container {
            max-width: 100%;
            margin: 0 auto;
        }
        /* Header */
        .header-row {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }
        .logo-container {
            display: table-cell;
            width: 80px;
            vertical-align: top;
        }
        .logo-container img {
            width: 80px;
            height: auto;
        }
        .company-info {
            display: table-cell;
            text-align: right;
            font-size: 10px;
            vertical-align: top;
        }
        .company-name {
            font-weight: bold;
            font-size: 11px;
            margin-bottom: 5px;
        }
        .title {
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 15px;
            text-decoration: underline;
        }
        .badge {
            background-color: #3498DB;
            color: white;
            padding: 8px;
            text-align: center;
            margin-bottom: 25px;
        }
        .badge-text {
            font-weight: bold;
        }
        .section {
            margin-bottom: 25px;
            page-break-inside: avoid;
        }
        .section-title {
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 12px;
            margin-top: 8px;
            color: #2C3E50;
            border-bottom: 2px solid #3498DB;
            padding-bottom: 4px;
        }

        /* ========== TWO COLUMNS USING DISPLAY: TABLE (PDF FRIENDLY) ========== */
        /* This works perfectly in Dompdf, wkhtmltopdf, TCPDF */
        .two-columns-table {
            display: table;
            width: 100%;
            margin-bottom: 10px;
        }
        .table-row {
            display: table-row;
        }
        .table-col-left {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-right: 20px;
        }
        .table-col-right {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-left: 10px;
        }

        /* Individual info line */
        .info-line {
            display: table;
            width: 100%;
            margin-bottom: 12px;
        }
        .info-label {
            display: table-cell;
            width: 130px;
            font-size: 10px;
            font-weight: bold;
            color: #2c3e50;
            vertical-align: top;
        }
        .info-value {
            display: table-cell;
            font-size: 11px;
            color: #1a252f;
            word-break: break-word;
            vertical-align: top;
        }
        .info-value.empty {
            color: #7f8c8d;
            font-style: italic;
        }

        /* Personal info - two columns grid (50/50) */
        .personal-grid {
            display: table;
            width: 100%;
        }
        .personal-row {
            display: table-row;
        }
        .personal-cell {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-bottom: 12px;
        }
        .personal-item {
            display: table;
            width: 100%;
            padding-right: 15px;
        }
        .personal-item .info-label {
            width: 135px;
        }
        .personal-item .info-value {
            display: table-cell;
        }

        /* Table styles for visits and calls */
        .data-table {
            width: 100%;
            margin-top: 10px;
            margin-bottom: 15px;
            border-collapse: collapse;
        }
        .data-table th {
            background-color: #34495E;
            color: white;
            font-size: 9px;
            font-weight: bold;
            padding: 8px 6px;
            text-align: left;
            border: 1px solid #2C3E50;
        }
        .data-table td {
            font-size: 9px;
            padding: 8px 6px;
            border: 1px solid #dee2e6;
            color: #2c3e50;
        }
        .data-table tr:nth-child(even) {
            background-color: #F8F9FA;
        }

        .empty-table-msg {
            background: #f9f9fc;
            padding: 12px;
            text-align: center;
            font-size: 10px;
            color: #6c757d;
            border: 1px dashed #ced4da;
        }

        .message-box {
            margin-top: 20px;
            padding: 10px;
            background-color: #F0F7FF;
            border-radius: 5px;
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

        @media print {
            body { padding: 0.2in; }
            .badge { background-color: #3498DB !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .data-table th { background-color: #34495E !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header-row">
            <div class="logo-container">
                @if($logoBase64)
                    <img src="{{ $logoBase64 }}" alt="Logo">
                @endif
            </div>
            <div class="company-info">
                <div class="company-name">{{ $societe['raison_sociale'] ?? ' ' }}</div>
                @if(!empty($societe['tel']))
                    <div>Tél: {{ $societe['tel'] }}</div>
                @endif
                @if(!empty($societe['email']))
                    <div>Email: {{ $societe['email'] }}</div>
                @endif
                @if(!empty($societe['adresse']))
                    <div>Adresse: {{ $societe['adresse'] }}</div>
                @endif
            </div>
        </div>

        <!-- Title -->
        <div class="title">FICHE D'INFORMATION PROSPECT</div>

        <div class="badge">
            <div class="badge-text">Document de suivi commercial confidentiel</div>
        </div>

        <!-- ========== SECTION INFORMATIONS PERSONNELLES ========= -->
        <div class="section">
            <div class="section-title">INFORMATIONS PERSONNELLES</div>
            <div class="personal-grid">
                <div class="personal-row">
                    <div class="personal-cell">
                        <div class="personal-item">
                            <div class="info-label">Nom complet</div>
                            <div class="info-value">{{ trim(($prospect['nom'] ?? '') . ' ' . ($prospect['prenom'] ?? '')) ?: ' ' }}</div>
                        </div>
                    </div>
                    <div class="personal-cell">
                        <div class="personal-item">
                            <div class="info-label">CIN / Passeport</div>
                            <div class="info-value">{{ $prospect['cin'] ?? ' ' }}</div>
                        </div>
                    </div>
                </div>
                <div class="personal-row">
                    <div class="personal-cell">
                        <div class="personal-item">
                            <div class="info-label">Téléphone principal</div>
                            <div class="info-value">{{ $prospect['telephone'] ?? ' ' }}</div>
                        </div>
                    </div>
                    <div class="personal-cell">
                        <div class="personal-item">
                            <div class="info-label">Téléphone secondaire</div>
                            <div class="info-value">
                                @if(!empty($prospect['telephone_num2']) && $prospect['telephone_num2'] != 'null')
                                    {{ $prospect['telephone_num2'] }}

                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                <div class="personal-row">
                    <div class="personal-cell">
                        <div class="personal-item">
                            <div class="info-label">Adresse email</div>
                            <div class="info-value {{ empty($prospect['email']) ? 'empty' : '' }}">{{ $prospect['email'] ?? ' ' }}</div>
                        </div>
                    </div>
                    <div class="personal-cell">
                        <div class="personal-item">
                            <div class="info-label">Accepte d'être contacté</div>
                            <div class="info-value">{{ ($prospect['notifie'] ?? 0) == 1 ? 'Oui' : 'Non' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========== SECTION ORIGINE & AFFECTATION - TWO SIDE BY SIDE COLUMNS ========= -->
        <!-- Using display: table / table-cell for maximum PDF compatibility -->
        <div class="section">
            <div class="section-title">ORIGINE & AFFECTATION</div>
            <div class="two-columns-table">
                <div class="table-row">
                    <!-- LEFT COLUMN: Origin & Source -->
                    <div class="table-col-left">
                        <div class="info-line">
                            <div class="info-label">Source / Origine</div>
                            <div class="info-value">{{ $prospect['origin'] ?? 'visite' }}</div>
                        </div>
                        <div class="info-line">
                            <div class="info-label">Provenance</div>
                            <div class="info-value">
                                @if(!empty($prospect['partenaire_id']))
                                    Partenaire : {{ $prospect['partenaire']['description'] ?? '' }}
                                @elseif(!empty($prospect['source']['source']))
                                    {{ $prospect['source']['source'] }}
                                @else
                                    Avito
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT COLUMN: Commercial assignment -->
                    <div class="table-col-right">
                        <div class="info-line">
                            <div class="info-label">Commercial assigné</div>
                            <div class="info-value {{ empty($prospect['affecte_par_admin']) ? 'empty' : '' }}">
                                @if(!empty($prospect['affecte_par_admin']))
                                    {{ trim(($prospect['affecte_par_admin']['name'] ?? '') . ' ' . ($prospect['affecte_par_admin']['prenom'] ?? '')) }}
                                @else
                                    Aucun commercial
                                @endif
                            </div>
                        </div>
                        <div class="info-line">
                            <div class="info-label">Date d'assignation</div>
                            <div class="info-value {{ empty($prospect['date_affectation']) ? 'empty' : '' }}">
                                @if(!empty($prospect['date_affectation']))
                                    {{ \Carbon\Carbon::parse($prospect['date_affectation'])->format('d/m/Y H:i') }}
                                @else
                                    Non assigné
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========== VISITES RÉALISÉES ========= -->
        @if(count($visites) > 0)
        <div class="section">
            <div class="section-title">VISITES RÉALISÉES ({{ count($visites) }} visite(s))</div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Responsable</th>
                        <th>Intérêt</th>
                        <th>Statut</th>
                        <th>Bien immobilier</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($visites as $visite)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($visite['date'])->format('d/m/Y H:i') }}</td>
                        <td>{{ trim(($visite['nom_cc'] ?? '') . ' ' . ($visite['prenom_cc'] ?? '')) ?: ' ' }}</td>
                        <td>{{ $formatInteret($visite['interet'] ?? null) ?: ' ' }}</td>
                        <td>{{ $formatStatutVisite($visite['statut'] ?? null) ?: ($visite['statut_label'] ?? ' ') }}</td>
                        <td>{{ $getBienInfo($visite['bien'] ?? null) ?: ($visite['bien_id'] ?? ' ') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="section">
            <div class="section-title">VISITES RÉALISÉES</div>
            <div class="empty-table-msg">Aucune visite enregistrée pour ce prospect.</div>
        </div>
        @endif

        <!-- ========== HISTORIQUE APPELS ========= -->
        @if(count($appels) > 0)
        <div class="section">
            <div class="section-title">HISTORIQUE DES ÉCHANGES TÉLÉPHONIQUES ({{ count($appels) }} appel(s))</div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Commercial</th>
                        <th>Type</th>
                        <th>Niveau d'intérêt</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($appels as $appel)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($appel['date'])->format('d/m/Y H:i') }}</td>
                        <td>{{ trim(($appel['user']['name'] ?? '') . ' ' . ($appel['user']['prenom'] ?? '')) ?: '-' }}</td>
                        <td>{{ ($appel['type_appel'] ?? '') == 1 ? 'Entrant' : 'Sortant' }}</td>
                        <td>{{ $formatInteret($appel['interet'] ?? null) ?: '-' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="section">
            <div class="section-title">HISTORIQUE DES ÉCHANGES TÉLÉPHONIQUES</div>
            <div class="empty-table-msg">Aucun appel répertorié.</div>
        </div>
        @endif

        <!-- Message professionnel -->
        <div class="message-box">
            <div class="message-text">
                Ce document fait office de suivi commercial. Toute information contenue dans ce document
                est confidentielle et réservée à l'usage interne de {{ $societe['raison_sociale'] ?? ' ' }}.
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            Document généré le {{ $currentDate ?? date('d/m/Y H:i') }} — {{ $societe['raison_sociale'] ?? ' ' }} —
            Tous droits réservés {{ date('Y') }}
        </div>
    </div>
</body>
</html>
