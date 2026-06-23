<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>CONTRAT DE VENTE</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', 'Arial', sans-serif;
            font-size: 11px;
            line-height: 1.5;
            padding: 30px;
            background-color: #fff;
        }
        .container {
            width: 100%;
            margin: 0 auto;
        }

        /* Header with table */
        .header-table {
            width: 100%;
            margin-bottom: 20px;
            border-collapse: collapse;
        }
        .header-table td {
            border: none;
            padding: 0;
            vertical-align: top;
        }
        .logo-cell {
            width: 70px;
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
            text-align: right;
        }
        .company-info {
            text-align: right;
            font-size: 9px;
            line-height: 1.4;
        }
        .company-name {
            font-weight: bold;
            font-size: 11px;
            margin-bottom: 3px;
        }

        .title-section {
            text-align: center;
            margin-bottom: 20px;
        }
        .title {
            font-size: 18px;
            font-weight: bold;
            color: #4F46E5;
            margin-bottom: 8px;
            text-decoration: underline;
        }
        .title-divider {
            height: 2px;
            background-color: #A5B4FC;
            margin-top: 8px;
            margin-left: auto;
            margin-right: auto;
            width: 80%;
        }

        .section-title {
            font-size: 14px;
            font-weight: bold;
            color: #4F46E5;
            margin-bottom: 12px;
            margin-top: 15px;
            border-bottom: 1px solid #E5E7EB;
            padding-bottom: 6px;
        }

        .paragraph {
            font-size: 10px;
            line-height: 1.5;
            margin-bottom: 10px;
            text-align: justify;
        }

        .bold {
            font-weight: bold;
        }

        .client-info-container {
            background-color: #F3F4F6;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 12px;
            border-left: 4px solid #3B82F6;
        }

        .client-info-container2 {
            background-color: #F0F7FF;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 12px;
            border-left: 4px solid #5A5FE0;
        }

        .badge {
            width: 24px;
            height: 24px;
            background-color: #4F46E5;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 8px;
        }
        .badge-text {
            margin-left: 8px;
            color: white;
            font-size: 12px;
            font-weight: bold;
        }
        .partie-title {
            font-size: 12px;
            font-weight: bold;
            color: #4F46E5;
        }
        .row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        .label {
            font-size: 10px;
            font-weight: bold;
            width: 40%;
            color: #444444;
        }
        .financial-value {
            font-size: 10px;
            width: 60%;
            font-weight: bold;
            color: #4F46E5;
        }

        /* SIGNATURE SECTION - SAME ROW */
        .signature-container {
            width: 100%;
            margin-top: 70px;
        }
        .signature-table {
            width: 100%;
            border-collapse: collapse;
        }
        .signature-table td {
            width: 50%;
            vertical-align: bottom;
            padding: 0;
        }
        .signature-left {
            text-align: center;
            padding-right: 20px;
        }
        .signature-right {
            text-align: center;
            padding-left: 20px;
        }
        .signature-line {
            border-top: 1px solid #000000;
            padding-top: 10px;
            min-height: 60px;
        }
        .signature-table td {
            height: 80px;
        }

        .flex-row {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
        }

        .meta-info {
            text-align: right;
        }
        .meta-row {
            margin-bottom: 4px;
        }
        .meta-label {
            font-size: 9px;
            color: #6B7280;
        }
        .meta-value {
            font-size: 10px;
            font-weight: bold;
            color: #111827;
        }
    </style>
</head>
<body>
    <div class="container">

        <!-- HEADER -->
        <table class="header-table">
            <tr>
                <td class="logo-cell">
                    <div class="logo-container">
                        @if($logoBase64)
                            <img src="{{ $logoBase64 }}" alt="Logo">
                        @endif
                    </div>
                </td>
                <td class="company-cell">
                    <div class="company-info">
                        <div class="company-name">{{ $societe['raison_sociale'] ?? 'Société' }}</div>
                        @if(!empty($societe['adresse']))
                            <div>Adresse: {{ $societe['adresse'] }}</div>
                        @endif
                        @if(!empty($societe['tel']))
                            <div>Tél: {{ $societe['tel'] }}</div>
                        @endif
                        @if(!empty($societe['email']))
                            <div>Email: {{ $societe['email'] }}</div>
                        @endif
                        @if(!empty($societe['rc']))
                            <div>RC: {{ $societe['rc'] }}</div>
                        @endif
                        @if(!empty($societe['ice']))
                            <div>ICE: {{ $societe['ice'] }}</div>
                        @endif
                    </div>
                    <div class="meta-info">
                        <div class="meta-row">
                            <span class="meta-label">N°:</span>
                            <span class="meta-value"> {{ $num_recu }}</span>
                        </div>
                        <div class="meta-row">
                            <span class="meta-label">Date:</span>
                            <span class="meta-value"> {{ $currentDate }}</span>
                        </div>
                    </div>
                </td>
            </tr>
        </table>

        <!-- TITLE -->
        <div class="title-section">
            <div class="title">CONTRAT DE VENTE</div>
            <div class="title-divider"></div>
        </div>

        <!-- LES PARTIES -->
        <div>
            <div class="section-title">LES PARTIES</div>

            <!-- Vendeur -->
            <div class="client-info-container2">
                <div class="flex-row">
                    <div class="badge">
                        <span class="badge-text">V</span>
                    </div>
                    <span class="partie-title">Vendeur</span>
                </div>
                <div class="paragraph">
                    {{ $societe['raison_sociale'] ?? 'Société' }}, société à responsabilité limitée de
                    droit Marocain, au capital social de 100.000,00 de dirhams, ayant
                    son siège social à {{ $societe['adresse'] ?? 'Adresse non disponible' }}, immatriculée au registre du commerce
                    sous n° {{ $societe['rc'] ?? 'Non renseigné' }} et dont le numéro de l'identifiant fiscal est le n° {{ $societe['ice'] ?? 'Non renseigné' }}.
                </div>
            </div>

            <!-- Acheteur -->
            <div class="client-info-container">
                <div class="flex-row">
                    <div class="badge">
                        <span class="badge-text">A</span>
                    </div>
                    <span class="partie-title">Acheteur(s)</span>
                </div>
                @php
                    $aquereurs = $reservation['aquereurs'] ?? [];
                @endphp
                @if(count($aquereurs) > 0)
                    @foreach($aquereurs as $aquereur)
                        @php
                            $client = $aquereur['client'] ?? [];
                        @endphp
                        <div style="margin-bottom: 10px;">
                            <div class="paragraph bold">
                                {{ $formatCivilite($client['civilite'] ?? '') }} {{ $client['nom'] ?? '' }} {{ $client['prenom'] ?? '' }}
                            </div>
                            <div class="paragraph">
                                CIN: {{ $client['cin'] ?? 'Non renseigné' }}
                                @if(!empty($client['adresse']))
                                    , domicilié à {{ $client['adresse'] }}, {{ $client['ville'] ?? '' }}
                                @endif
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="paragraph">Aucun acheteur renseigné</div>
                @endif
            </div>
        </div>

        <!-- DÉTAILS DU BIEN -->
        <div style="margin-top: 15px;">
            <div class="section-title">DÉTAILS DU BIEN</div>
            <div class="client-info-container">
                @php
                    $bien = $reservation['bien'] ?? [];
                    $niveau = $bien['niveau'] ?? null;
                    $niveauText = '';
                    if ($niveau == 0) $niveauText = 'RDC';
                    elseif ($niveau == 1) $niveauText = '1er étage';
                    elseif ($niveau > 1) $niveauText = $niveau . 'ème étage';
                    else $niveauText = 'niveau non spécifié';
                @endphp
                <div class="paragraph">
                    Ce bien @php
    $bienObj = $reservation['bien'] ?? [];
    $bienNom = '';
    if ($bienObj) {
        $parts = [];
        if (!empty($bienObj['tranche']['nom'])) $parts[] = $bienObj['tranche']['nom'];
        if (!empty($bienObj['bloc']['nom'])) $parts[] = $bienObj['bloc']['nom'];
        if (!empty($bienObj['immeuble']['nom'])) $parts[] = $bienObj['immeuble']['nom'];
        if (!empty($bienObj['propriete_dite_bien'])) $parts[] = $bienObj['propriete_dite_bien'];
        $bienNom = implode(' - ', $parts);
    }
@endphp
{{ $bienNom }} , immobilier est un {{ $bien['type_bien']['type'] ?? 'type non spécifié' }}, identifié par
                    le numéro <span class="bold">{{ $bien['numero'] ?? 'non renseigné' }}</span>.
                    Il est situé au <span class="bold">{{ $niveauText }}</span>
                    , d'une superficie habitable de <span class="bold">{{ $bien['superficie_habitable'] ?? '0' }} m²</span>.
                    @if(!empty($bien['superficie_balcon']) && $bien['superficie_balcon'] > 0)
                        Le bien comprend un balcon de {{ $bien['superficie_balcon'] }} m².
                    @endif
                    @if(!empty($bien['superficie_terrasse']) && $bien['superficie_terrasse'] > 0)
                        Il dispose également d'une terrasse de {{ $bien['superficie_terrasse'] }} m².
                    @endif
                </div>
                @if(!empty($bien['composition_bien']) && count($bien['composition_bien']) > 0)
                    <div class="paragraph" style="margin-top: 8px;">
                        La composition du bien comprend :
                        @php
                            $summedComposition = ['nbre_halls' => 0, 'nbre_salons' => 0, 'nbre_chambres' => 0, 'nbre_cuisines' => 0, 'nbre_sdb' => 0, 'nbre_balcons' => 0, 'nbre_buanderies' => 0, 'nbre_placards' => 0, 'nbre_receptions' => 0, 'nbre_kitchenette' => 0, 'nbre_sejour' => 0];
                            foreach ($bien['composition_bien'] as $comp) {
                                $summedComposition['nbre_halls'] += $comp['nbre_halls'] ?? 0;
                                $summedComposition['nbre_salons'] += $comp['nbre_salons'] ?? 0;
                                $summedComposition['nbre_chambres'] += $comp['nbre_chambres'] ?? 0;
                                $summedComposition['nbre_kitchenette'] += $comp['nbre_kitchenette'] ?? 0;
                                $summedComposition['nbre_sejour'] += $comp['nbre_sejour'] ?? 0;
                                $summedComposition['nbre_cuisines'] += $comp['nbre_cuisines'] ?? 0;
                                $summedComposition['nbre_sdb'] += $comp['nbre_sdb'] ?? 0;
                                $summedComposition['nbre_balcons'] += $comp['nbre_balcons'] ?? 0;
                                $summedComposition['nbre_buanderies'] += $comp['nbre_buanderies'] ?? 0;
                                $summedComposition['nbre_placards'] += $comp['nbre_placards'] ?? 0;
                                $summedComposition['nbre_receptions'] += $comp['nbre_receptions'] ?? 0;
                            }
                            $parts = [];
                            if ($summedComposition['nbre_halls'] > 0) $parts[] = $summedComposition['nbre_halls'] . ' hall' . ($summedComposition['nbre_halls'] > 1 ? 's' : '');
                            if ($summedComposition['nbre_salons'] > 0) $parts[] = $summedComposition['nbre_salons'] . ' salon' . ($summedComposition['nbre_salons'] > 1 ? 's' : '');
                            if ($summedComposition['nbre_chambres'] > 0) $parts[] = $summedComposition['nbre_chambres'] . ' chambre' . ($summedComposition['nbre_chambres'] > 1 ? 's' : '');
                            if ($summedComposition['nbre_kitchenette'] > 0) $parts[] = $summedComposition['nbre_kitchenette'] . ' kitchenette' . ($summedComposition['nbre_kitchenette'] > 1 ? 's' : '');
                            if ($summedComposition['nbre_sejour'] > 0) $parts[] = $summedComposition['nbre_sejour'] . ' sejour' . ($summedComposition['nbre_sejour'] > 1 ? 's' : '');
                            if ($summedComposition['nbre_cuisines'] > 0) $parts[] = $summedComposition['nbre_cuisines'] . ' cuisine' . ($summedComposition['nbre_cuisines'] > 1 ? 's' : '');
                            if ($summedComposition['nbre_sdb'] > 0) $parts[] = $summedComposition['nbre_sdb'] . ' salle' . ($summedComposition['nbre_sdb'] > 1 ? 's' : '') . ' de bain';
                            if ($summedComposition['nbre_balcons'] > 0) $parts[] = $summedComposition['nbre_balcons'] . ' balcon' . ($summedComposition['nbre_balcons'] > 1 ? 's' : '');
                            if ($summedComposition['nbre_buanderies'] > 0) $parts[] = $summedComposition['nbre_buanderies'] . ' buanderie' . ($summedComposition['nbre_buanderies'] > 1 ? 's' : '');
                            if ($summedComposition['nbre_placards'] > 0) $parts[] = $summedComposition['nbre_placards'] . ' placard' . ($summedComposition['nbre_placards'] > 1 ? 's' : '');
                            if ($summedComposition['nbre_receptions'] > 0) $parts[] = $summedComposition['nbre_receptions'] . ' réception' . ($summedComposition['nbre_receptions'] > 1 ? 's' : '');

                            $compositionText = implode(', ', $parts);
                            $lastComma = strrpos($compositionText, ', ');
                            if ($lastComma !== false) {
                                $compositionText = substr($compositionText, 0, $lastComma) . ' et ' . substr($compositionText, $lastComma + 2);
                            }
                        @endphp
                        {{ $compositionText ?: 'Non spécifiée' }}.
                        @if(!empty($bien['num_parking']))
                            Le bien dispose de {{ $bien['num_parking'] }} place{{ $bien['num_parking'] > 1 ? 's' : '' }} de parking au sous-sol.
                        @endif
                        @if(!empty($bien['num_box']))
                            Il comprend également un box numéro {{ $bien['num_box'] }}.
                        @endif
                    </div>
                @endif
            </div>
        </div>

        <!-- CONDITIONS FINANCIÈRES -->
        <div style="margin-top: 15px;">
            <div class="section-title">CONDITIONS FINANCIÈRES</div>
            <div class="client-info-container2">
                @php
                    $prix = $reservation['prix'] ?? 0;
                    $sumAvances = $sum_avances_valides ?? 0;
                @endphp
                <div class="row">
                    <span class="label">Prix global (DHS):</span>
                    <span class="financial-value">{{ number_format($prix, 0, ',', ' ') }} DH</span>
                </div>
                <div class="row">
                    <span class="label">Acompte versé (DHS):</span>
                    <span class="financial-value">{{ number_format($sumAvances, 0, ',', ' ') }} DH</span>
                </div>
                <div class="row">
                    <span class="label">Reste à payer (DHS):</span>
                    <span class="financial-value">{{ number_format($prix - $sumAvances, 0, ',', ' ') }} DH</span>
                </div>
            </div>
        </div>

        <!-- DATES DU CONTRAT -->
        <div style="margin-top: 15px;">
            <div class="section-title">DATES DU CONTRAT</div>
            <div class="client-info-container2">
                <div class="paragraph">
                    Il est énoncé que le client a signé le contrat en
                    <span class="bold"> {{ $formatDate($date_sign_client) ?: 'date non renseignée' }}</span>
                    et le Maitre d'Ouvrage en
                    <span class="bold"> {{ $formatDate($date_sign_mo) ?: 'date non renseignée' }}</span>
                    et enregistré en
                    <span class="bold"> {{ $formatDate($date_enreg) ?: 'date non renseignée' }}</span>.
                </div>
            </div>
        </div>

                     <div class="section-title">SIGNATURES</div>

        <!-- SIGNATURES - SAME ROW -->
        <div class="signature-container">
            <table class="signature-table">
                <tr>
                    <td class="signature-left">
                        <div class="signature-line">
                            Signature du Client
                        </div>
                    </td>
                    <td class="signature-right">
                        <div class="signature-line">
                            Signature du Responsable
                        </div>
                    </td>
                </tr>
            </table>
        </div>

    </div>
</body>
</html>
