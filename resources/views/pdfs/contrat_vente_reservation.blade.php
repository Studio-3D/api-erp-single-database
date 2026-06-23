{{-- resources/views/pdfs/contrat_vente.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Contrat de Réservation</title>
    <style>
        /* Reset and Base */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', 'Arial', sans-serif;
            font-size: 10px;
            color: #1a1a1a;
            line-height: 1.5;
            padding: 30px 40px;
            background: white;
        }

        /* Container */
        .contract-container {
            max-width: 100%;
            margin: 0 auto;
        }

        /* Colors */
        .text-primary { color: #0d4a35; }
        .text-gold { color: #b8973a; }
        .border-primary { border-color: #0d4a35; }
        .border-gold { border-color: #b8973a; }

        /* Typography */
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .font-bold { font-weight: bold; }
        .font-semibold { font-weight: 600; }
        .italic { font-style: italic; }
        .uppercase { text-transform: uppercase; }
        .tracking-wide { letter-spacing: 1px; }

        /* Spacing */
        .mb-1 { margin-bottom: 4px; }
        .mb-2 { margin-bottom: 8px; }
        .mb-3 { margin-bottom: 12px; }
        .mb-4 { margin-bottom: 16px; }
        .mb-6 { margin-bottom: 24px; }
        .mt-1 { margin-top: 4px; }
        .mt-2 { margin-top: 8px; }
        .mt-3 { margin-top: 12px; }
        .mt-4 { margin-top: 16px; }
        .mt-6 { margin-top: 24px; }
        .pl-4 { padding-left: 16px; }
        .pl-5 { padding-left: 20px; }
        .pt-4 { padding-top: 16px; }
        .pb-2 { padding-bottom: 8px; }
        .pb-3 { padding-bottom: 12px; }

        /* Borders */
        .border-b-2 { border-bottom: 2px solid; }
        .border-b { border-bottom: 1px solid; }
        .border-t-2 { border-top: 2px solid; }
        .border { border: 1px solid #d1d5db; }
        .border-collapse { border-collapse: collapse; }

        /* Header with logo */
        .header-container {
            display: table;
            width: 100%;
            border-bottom: 2px solid #0d4a35;
            padding-bottom: 8px;
            margin-bottom: 16px;
        }

        .header-container .header-left {
            display: table-cell;
            width: 20%;
            vertical-align: middle;
        }
        .header-container .header-center {
            display: table-cell;
            width: 60%;
            text-align: center;
            vertical-align: middle;
        }
        .header-container .header-right {
            display: table-cell;
            width: 20%;
            text-align: right;
            vertical-align: middle;
        }

        .header-title {
            font-size: 20px;
            font-weight: bold;
            color: #0d4a35;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-top: 8px;
            margin-bottom: 4px;
        }

        .header-subtitle {
            font-size: 10px;
            color: #4a4a4a;
            font-weight: 600;
            margin-top: 4px;
            margin-bottom: 6px;
            padding-bottom: 8px;
            border-bottom: 2px solid #b8973a;
        }

        .header-logo {
            max-height: 50px;
            max-width: 80px;
        }

        .header-greenland {
            max-height: 50px;
            max-width: 100px;
        }

        /* Article Headers */
        .article-title {
            font-size: 13px;
            font-weight: bold;
            color: #0d4a35;
            border-bottom: 1px solid #0d4a35;
            padding-bottom: 4px;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .article-subtitle {
            font-size: 10px;
            font-weight: 600;
            color: #b8973a;
            margin-bottom: 4px;
        }

        /* Tables */
        .table {
            width: 100%;
            font-size: 9px;
            border-collapse: collapse;
            margin: 6px 0;
        }

        .table thead th {
            background-color: #0d4a35;
            color: white;
            padding: 3px 6px;
            text-align: left;
            font-size: 8px;
            border: 1px solid #0d4a35;
        }

        .table tbody td {
            border: 1px solid #d1d5db;
            padding: 3px 6px;
        }

        .table .row-total {
            background-color: #f3f4f6;
            font-weight: bold;
        }

        .table .row-total td {
            color: #0d4a35;
        }

        .table .text-right {
            text-align: right;
        }
        .table .text-center {
            text-align: center;
        }

        /* Signature Section */
        .signature-box {
            height: 50px;
            border-bottom: 1px solid #000;
            margin-bottom: 4px;
        }

        .signature-label {
            font-size: 10px;
            font-weight: bold;
            color: #0d4a35;
        }

        /* Footer */
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 7px;
            color: #6b7280;
            border-top: 1px solid #d1d5db;
            padding-top: 8px;
        }

        /* Lists */
        .list-disc {
            list-style-type: disc;
            padding-left: 16px;
        }
        .list-disc li {
            margin-bottom: 2px;
        }

        /* Misc */
        .bg-gray-100 { background-color: #f3f4f6; }
        .text-gray-600 { color: #4b5563; }
        .text-gray-700 { color: #374151; }
        .text-gray-800 { color: #1f2937; }
        .text-xs { font-size: 7px; }
        .text-sm { font-size: 8.5px; }
        .text-lg { font-size: 12px; }

        .page-break {
            page-break-after: always;
        }

        /* Responsive grid for signatures */
        .signature-grid {
            display: table;
            width: 100%;
            margin-top: 10px;
        }
        .signature-grid .col {
            display: table-cell;
            width: 50%;
            text-align: center;
            padding: 0 8px;
        }

        /* Beneficiary info */
        .beneficiary-block {
            margin-top: 4px;
        }
        .beneficiary-block p {
            margin-bottom: 2px;
        }

        /* Client info styling */
        .client-info p {
            margin-bottom: 2px;
        }

        /* Dotted placeholder */
        .dotted-placeholder {
            color: #9ca3af;
            font-weight: normal;
        }

        /* Page number */
        .page-number {
            text-align: center;
            font-size: 8px;
            color: #6b7280;
            margin-top: 10px;
        }

        /* Green Land badge */
        .greenland-badge {
            font-size: 14px;
            font-weight: bold;
            color: #0d4a35;
            text-transform: uppercase;
            letter-spacing: 3px;
            background: #e8f5e9;
            padding: 4px 12px;
            border-radius: 4px;
            display: inline-block;
            border: 1px solid #b8973a;
        }
    </style>
</head>
<body>
<div class="contract-container">

    {{-- ==================== HEADER ==================== --}}
    <div class="header-container" >
        {{-- Left: Company Logo --}}
        <div class="header-left">
            @if($logoBase64)
                <img src="{{ $logoBase64 }}" alt="Logo IMOZINE" class="header-logo">
            @endif
        </div>

        {{-- Center: Project Name --}}
        <div class="header-center">
            <div class="greenland-badge">Projet Green Land</div>
        </div>

        {{-- Right: Green Land Image --}}
        <div class="header-right">
            @php
                $greenLandBase64 = null;
                // Try to load green_ang.png from logos folder
                if (isset($societe['raison_sociale_concatene']) && isset($societe['id'])) {
                    $greenLandPath = $societe['raison_sociale_concatene'] . '_' . $societe['id'] . '/logos/green_land.png';
                    $fileContent = null;

                    if (app()->environment('production')) {
                        if (Storage::disk('s3')->exists($greenLandPath)) {
                            $fileContent = Storage::disk('s3')->get($greenLandPath);
                        }
                    } else {
                        $localPath = public_path('docs/' . $greenLandPath);
                        if (file_exists($localPath)) {
                            $fileContent = file_get_contents($localPath);
                        }
                    }

                    if ($fileContent !== null) {
                        $greenLandBase64 = 'data:image/png;base64,' . base64_encode($fileContent);
                    }
                }
            @endphp
            @if($greenLandBase64)
                <img src="{{ $greenLandBase64 }}" alt="Green Land" class="header-greenland">
            @endif
        </div>
    </div>

  {{-- Header Title & Subtitle --}}
<div style="text-align: center; margin-top: 20px; margin-bottom: 20px;">
    <div class="header-title">CONTRAT DE RÉSERVATION</div>
    <div class="header-subtitle" style="border-bottom: 2px solid #b8973a; padding-bottom: 8px; display: inline-block;">
        Dossier : {{ $reservation['code_reservation'] ?? '' }} | N° : {{ $num_recu ?? '' }} | Identifiant bien : {{ $bien['tranche']['nom'] ?? 'Tranche' }}/{{ $bien['bloc']['nom'] ?? 'GH' }}/{{ $bien['immeuble']['nom'] ?? 'IMM' }}/{{ $bien['niveau'] ?? 'ETAGE' }}/{{ $bien['numero'] ?? 'APT' }}
    </div>
</div>

    {{-- ==================== ENTRE LES SOUSSIGNÉS ==================== --}}
    <div class="mb-3">
        <h2 class="article-title">ENTRE LES SOUSSIGNÉS</h2>

        {{-- VENDEUR --}}
        <div class="pl-4 mb-2">
            <p class="text-sm text-gray-800">
                La société « <strong class="text-primary">IMOZINE</strong> », Société à responsabilité limitée,
                au capital social de 141.050.000 dirhams,
                dont le siège social est au 13 Angle Rue de Rome et Rue de Varsovie, Résidence Amina, 1er Etage Appt N° 01, Casablanca,
                représentée par Mr Driss MOUSS et Mr Othmane MOUSS, dûment habilités aux fins des présentes,
            </p>
            <p class="text-sm italic mt-1 text-gray-700">
                Ci-après désigné <strong class="text-primary">« LE VENDEUR »</strong>, D'UNE PART
            </p>
        </div>

        {{-- BÉNÉFICIAIRE --}}
        <div class="pl-4">
            <p class="text-sm font-semibold text-gray-800">Et :</p>
            @if(isset($reservation['aquereurs']) && is_array($reservation['aquereurs']) && count($reservation['aquereurs']) > 0)
                @foreach($reservation['aquereurs'] as $aquereur)
                    @php $client = $aquereur['client'] ?? []; @endphp
                    <div class="beneficiary-block mt-1">
                        <p class="text-sm font-semibold text-gray-800">
                            {{ $formatCivilite($client['civilite'] ?? '') }} / Madame :
                            <span class="font-normal">{{ $client['nom'] ?? '' }} {{ $client['prenom'] ?? '' }}</span>
                        </p>
                        <p class="text-sm text-gray-700">CIN / Passeport n° : <span class="font-semibold">{{ $client['cin'] ?? '  ' }}</span></p>
                        <p class="text-sm text-gray-700">De nationalité : <span class="font-semibold">{{ $client['nationalite'] ?? '  ' }}</span></p>
                        <p class="text-sm text-gray-700">Né(e) le : <span class="font-semibold">{{ isset($client['date_naissance']) ? $formatDate($client['date_naissance']) : ' ' }}</span></p>
                        <p class="text-sm text-gray-700">Demeurant à : <span class="font-semibold">{{ $client['adresse'] ?? ' ' }}{{ isset($client['ville']) ? ', ' . $client['ville'] : '' }}</span></p>
                        <p class="text-sm text-gray-700">
                            Téléphone / Email :
                            <span class="font-semibold">
                                @if(!empty($client['telephone_num1']))
                                    {{ $client['telephone_num1'] }}
                                @elseif(!empty($client['telephone']))
                                    {{ $client['telephone'] }}
                                @endif
                                @if(!empty($client['telephone_num2']))
                                    / {{ $client['telephone_num2'] }}
                                @endif
                                @if(!empty($client['email']))
                                    / {{ $client['email'] }}
                                @endif
                            </span>
                        </p>
                    </div>
                @endforeach
            @else
                <div class="beneficiary-block mt-1">
                    <p class="text-sm font-semibold text-gray-800">Monsieur / Madame : <span class="dotted-placeholder">'  '</span></p>
                    <p class="text-sm text-gray-700">CIN / Passeport n° : <span class="dotted-placeholder">'  '</span></p>
                    <p class="text-sm text-gray-700">De nationalité : <span class="dotted-placeholder">'  '</span></p>
                    <p class="text-sm text-gray-700">Né(e) le : <span class="dotted-placeholder">'  '</span></p>
                    <p class="text-sm text-gray-700">Demeurant à : <span class="dotted-placeholder">'  '</span></p>
                    <p class="text-sm text-gray-700">Téléphone / Email : <span class="dotted-placeholder">'  '</span></p>
                </div>
            @endif
            <p class="text-sm italic mt-2 text-gray-700">
                Ci-après désigné <strong class="text-primary">« LE BÉNÉFICIAIRE »</strong>, D'AUTRE PART
            </p>
            <p class="text-sm mt-2 text-gray-700">
                Le VENDEUR et le BÉNÉFICIAIRE sont ci-après dénommés individuellement une « Partie » et collectivement les « Parties ».
            </p>
        </div>
    </div>

    {{-- ==================== PRÉAMBULE ==================== --}}
    <div class="mb-3">
        <h2 class="article-title">PRÉAMBULE</h2>
        <div class="pl-4">
            <p class="text-sm text-gray-800">
                Sur le terrain objet du titre foncier n° T26886/D, le Vendeur développe l'ensemble immobilier
                dénommé  <strong class="text-primary">« Projet Green Land »</strong>, situé à Ain Chock, Casablanca. Le titre foncier fera l'objet d'un
                éclatement comportant une série de fractions divises. Le règlement de copropriété en cours
                d'élaboration sera déposé à la conservation foncière et communiqué au BÉNÉFICIAIRE avant la
                signature de l'acte d'acquisition, auquel il s'imposera dès cette signature.
            </p>
        </div>
    </div>

    {{-- ==================== ARTICLE 1 – OBJET ==================== --}}
    <div class="mb-3">
        <h2 class="article-title">ARTICLE 1 – OBJET</h2>
        <div class="pl-4">
            <p class="text-sm mb-1 text-gray-800">Le VENDEUR réserve au BÉNÉFICIAIRE, dans le cadre du Projet Green Land, le bien immobilier ci-après désigné :</p>
            <div class="mt-1">
                <p class="text-sm text-gray-800"><span class="font-semibold">Localisation :</span> Ain Chock — Projet Green Land, Casablanca</p>
                <p class="text-sm text-gray-800"><span class="font-semibold">Identifiant :</span> {{ $bien['tranche']['nom'] ?? 'Tranche' }}/{{ $bien['bloc']['nom'] ?? 'GH' }}/{{ $bien['immeuble']['nom'] ?? 'IMM' }}/{{ $bien['niveau'] ?? 'ETAGE' }}/{{ $bien['numero'] ?? 'APPARTEMENT' }}</p>
                <p class="text-sm text-gray-800"><span class="font-semibold">Superficie approximative :</span> {{ $surfaceVendable ?? ' ' }} m²</p>
                <p class="text-xs text-gray-600 mt-1 italic">
                    La superficie est susceptible de connaître une variation une fois les titres fonciers des fractions divises établis, conformément à l'article 49 de la loi 18-00 relative au statut de la copropriété des immeubles bâtis.
                </p>
            </div>
        </div>
    </div>

    {{-- ==================== ARTICLE 2 – PRIX ==================== --}}
    <div class="mb-3">
        <h2 class="article-title">ARTICLE 2 – PRIX, MODALITÉS ET CONDITIONS DE PAIEMENT</h2>

        {{-- 2.1 Composition du prix --}}
        <div class="pl-4 mb-2">
            <h3 class="article-subtitle">2.1 Composition du prix global de réservation</h3>
            <p class="text-sm text-gray-700 mb-1">Le prix global de la présente réservation se compose des éléments suivants :</p>

            <table class="table">
                <thead>
                    <tr>
                        <th style="width:45%;">Désignation</th>
                        <th style="width:25%;" class="text-right">Surface (m²)</th>
                        <th style="width:30%;" class="text-right">Prix (DH TTC)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Prix de l'appartement</td>
                        <td class="text-right">{{ $surfaceVendable ?? '' }} m²</td>
                        <td class="text-right font-semibold">{{ $apartmentPrice > 0 ? $formatCurrency($apartmentPrice) : 'DH' }}</td>
                    </tr>
                    <tr>
                        <td>Prix du garage</td>
                        <td class="text-right">{{ $surfaceParking > 0 ? $surfaceParking . ' m²' : '—' }}</td>
                        <td class="text-right">{{ $parkingTotal > 0 ? $formatCurrency($parkingTotal) : 'DH' }}</td>
                    </tr>
                    <tr>
                        <td>Prix du box</td>
                        <td class="text-right">{{ $surfaceBox > 0 ? $surfaceBox . ' m²' : '—' }}</td>
                        <td class="text-right">{{ $boxTotal > 0 ? $formatCurrency($boxTotal) : 'DH' }}</td>
                    </tr>
                    <tr class="row-total">
                        <td><strong>PRIX TOTAL DE RÉSERVATION</strong></td>
                        <td class="text-right">—</td>
                        <td class="text-right text-primary"><strong>{{ $totalPrice >= 0 ? $formatCurrency($totalPrice) : 'DH' }}</strong></td>
                    </tr>
                </tbody>
            </table>

            <p class="text-xs text-gray-600 mt-1 italic">
                La révision du prix selon la superficie réelle (à la hausse ou à la baisse) s'applique <strong>uniquement au prix de l'appartement.</strong>
                La superficie définitive retenue sera celle exprimée par le certificat de propriété, le règlement de copropriété ou une attestation délivrée par un géomètre agréé.
                Le prix du garage et du box demeurent fixes et forfaitaires. Cette disposition est expressément et irrévocablement acceptée par les deux Parties.
            </p>
        </div>

        {{-- 2.2 Échéancier de paiement --}}
        <div class="pl-4">
            <h3 class="article-subtitle">2.2 Échéancier de paiement lié à l'avancement des travaux</h3>
            <p class="text-sm text-gray-700 mb-1">Le prix global est payable selon l'échéancier ci-après, attesté à chaque stade par le bureau de contrôle technique :</p>

            <table class="table">
                <thead>
                    <tr>
                        <th style="width:8%;" class="text-center">#</th>
                        <th style="width:50%;">Stade d'avancement des travaux</th>
                        <th style="width:17%;" class="text-center">% Prix</th>
                        <th style="width:25%;" class="text-right">Montant (DH TTC)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($paymentSchedule as $schedule)
                    <tr>
                        <td class="text-center">{{ $schedule['step'] }}</td>
                        <td>{{ $schedule['label'] }}</td>
                        <td class="text-center">{{ $schedule['percentage'] }} %</td>
                        <td class="text-right">{{ $totalPrice > 0 ? $formatCurrency($schedule['amount']) : 'DH' }}</td>
                    </tr>
                    @endforeach
                    <tr class="row-total">
                        <td class="text-center"></td>
                        <td><strong>TOTAL</strong></td>
                        <td class="text-center"><strong>100 %</strong></td>
                        <td class="text-right text-primary"><strong>{{ $totalPrice > 0 ? $formatCurrency($totalPrice) : 'DH' }}</strong></td>
                    </tr>
                </tbody>
            </table>

            <p class="text-xs text-gray-600 mt-1 italic">
                Chaque appel de fonds sera conditionné à la présentation d'une attestation du bureau de contrôle technique certifiant l'achèvement du stade concerné.
                Les paiements s'effectueront exclusivement par chèque certifié ou virement bancaire — aucun paiement en espèces ne sera accepté.
            </p>
        </div>
    </div>

    {{-- ==================== ARTICLE 3 – CLAUSE RÉSOLUTOIRE ==================== --}}
    <div class="mb-3">
        <h2 class="article-title">ARTICLE 3 – CLAUSE RÉSOLUTOIRE</h2>
        <div class="pl-4">
            <p class="text-sm text-gray-800">
                En cas de non-paiement de l'une quelconque des sommes indiquées à l'article 2 à son échéance,
                et un mois après l'envoi par lettre recommandée avec accusé de réception d'une mise en demeure
                restée sans effet, le BÉNÉFICIAIRE sera de plein droit redevable d'une <strong>indemnité de retard fixée
                à 10 % du solde dû.</strong>
            </p>
            <p class="text-sm mt-2 text-gray-800">
                À défaut de paiement de l'acompte et de l'indemnité de retard dans le délai de 15 jours suivant
                l'expiration du délai d'un mois imparti par la mise en demeure, la présente réservation sera,
                le BÉNÉFICIAIRE y consent expressément, nulle et non avenue. Le VENDEUR aura droit à une
                indemnité équivalente à <strong>10 % du prix total de réservation.</strong> Les acomptes versés seront
                remboursés sous déduction de cette indemnité dans un délai de deux (2) mois.
            </p>
        </div>
    </div>

    {{-- ==================== ARTICLE 4 – DÉSISTEMENT ==================== --}}
    <div class="mb-3">
        <h2 class="article-title">ARTICLE 4 – DÉSISTEMENT</h2>
        <div class="pl-4">
            <p class="text-sm text-gray-800">
                Au cas où le BÉNÉFICIAIRE se désiste de la présente réservation, le VENDEUR aura droit à une
                indemnité équivalente à <strong>10 % du prix total de réservation</strong> indiqué à l'article 2. Les acomptes versés
                seront remboursés sous déduction de cette indemnité dans un délai ne dépassant pas trois (3)
                mois à compter de la date de notification du désistement.
            </p>
        </div>
    </div>

    {{-- ==================== ARTICLE 5 – CONDITIONS SUSPENSIVES ==================== --}}
    <div class="mb-3">
        <h2 class="article-title">ARTICLE 5 – CONDITIONS SUSPENSIVES</h2>
        <div class="pl-4">
            <p class="text-sm mb-1 text-gray-800">La réalisation définitive de la vente est subordonnée aux conditions suspensives suivantes :</p>
            <ul class="list-disc text-sm text-gray-800">
                <li>Le paiement par le BÉNÉFICIAIRE de l'intégralité du prix de réservation ainsi que des droits, frais, timbres et taxes de l'acte de vente définitif ;</li>
                <li>L'achèvement des constructions et l'obtention du permis d'habiter ou du certificat de conformité par le VENDEUR ;</li>
                <li>L'éclatement du titre foncier parcellaire par le VENDEUR.</li>
            </ul>
        </div>
    </div>

    {{-- ==================== ARTICLE 6 – CONTRAT DÉFINITIF DE VENTE ==================== --}}
    <div class="mb-3">
        <h2 class="article-title">ARTICLE 6 – CONTRAT DÉFINITIF DE VENTE</h2>
        <div class="pl-4">
            <p class="text-sm text-gray-800">
                Le VENDEUR soumettra le contrat définitif de vente au BÉNÉFICIAIRE dès achèvement des travaux.
                Les travaux devraient être achevés au plus tard le : <span class="font-semibold">……………………………</span>, sauf cas de force majeure.
            </p>
            <p class="text-sm mt-2 text-gray-800">
                Le BÉNÉFICIAIRE s'engage à se présenter auprès du notaire dans un délai de 15 jours suivant
                la réunion de l'ensemble des conditions suspensives visées à l'article 5. En cas d'abstention
                dans ce délai, la clause résolutoire de l'article 3 s'appliquera de plein droit.
            </p>
        </div>
    </div>

    {{-- ==================== ARTICLE 7 – INTERDICTION DE CÉDER SES DROITS ==================== --}}
    <div class="mb-3">
        <h2 class="article-title">ARTICLE 7 – INTERDICTION DE CÉDER SES DROITS</h2>
        <div class="pl-4">
            <p class="text-sm text-gray-800">
                Il est expressément convenu que le BÉNÉFICIAIRE s'interdit de céder les droits qu'il tient
                des présentes à une tierce personne, sans l'accord exprès et préalable du VENDEUR.
            </p>
        </div>
    </div>

    {{-- ==================== ARTICLE 8 – NOTIFICATIONS ==================== --}}
    <div class="mb-3">
        <h2 class="article-title">ARTICLE 8 – NOTIFICATIONS</h2>
        <div class="pl-4">
            <p class="text-sm text-gray-800">
                Toute notification sera considérée comme valable dès lors qu'elle est envoyée par courriel,
                puis confirmée le jour même par lettre recommandée avec accusé de réception, aux adresses suivantes:
            </p>
            <div class="mt-1">
                <div class="pl-4">
                    <p class="text-sm font-semibold text-gray-800">Au Vendeur :</p>
                    <p class="text-sm text-gray-700" style="border-bottom: 2px solid #b8973a; padding-bottom: 4px;">
                        À Mr. Le Directeur Général de la Ste. IMOZINE SARL,
                        13 Angle Rue de Rome et Rue Varsovie, Résidence Amina, 1er Etage, Appart N°1, Casablanca
                    </p>
                </div>
                <div class="pl-4 mt-1">
                    <p class="text-sm font-semibold text-gray-800">Au Bénéficiaire :</p>
                    @if(isset($reservation['aquereurs']) && is_array($reservation['aquereurs']) && count($reservation['aquereurs']) > 0)
                        @foreach($reservation['aquereurs'] as $aquereur)
                            @php $client = $aquereur['client'] ?? []; @endphp
                            <p class="text-sm text-gray-700" style="border-bottom: 2px solid #b8973a; padding-bottom: 4px;">
                                {{ $client['nom'] ?? '' }} {{ $client['prenom'] ?? '' }},
                                {{ $client['adresse'] ?? 'Adresse' }}{{ isset($client['ville']) ? ', ' . $client['ville'] : '' }}
                            </p>
                        @endforeach
                    @else
                        <p class="text-sm text-gray-700" style="border-bottom: 2px solid #b8973a; padding-bottom: 4px;"></p>
                    @endif
                </div>
            </div>
            <p class="text-sm mt-1 italic text-gray-600">
                En cas de changement d'adresse, le BÉNÉFICIAIRE s'engage à en informer le VENDEUR par lettre
                recommandée avec accusé de réception dans un délai de 15 jours. À défaut, toute notification
                envoyée à l'ancienne adresse sera réputée valablement effectuée.
            </p>
        </div>
    </div>

    {{-- ==================== ARTICLE 9 – ENTRÉE EN JOUISSANCE ==================== --}}
    <div class="mb-3">
        <h2 class="article-title">ARTICLE 9 – ENTRÉE EN JOUISSANCE</h2>
        <div class="pl-4">
            <p class="text-sm text-gray-800">
                Le BÉNÉFICIAIRE aura la jouissance du bien à compter de la signature du contrat de vente définitif,
                libre de toute occupation ou location. La prise de possession sera constatée par un procès-verbal
                contradictoire signé par les deux Parties.
            </p>
        </div>
    </div>

    {{-- ==================== ARTICLE 10 – FRAIS ==================== --}}
    <div class="mb-3">
        <h2 class="article-title">ARTICLE 10 – FRAIS</h2>
        <div class="pl-4">
            <p class="text-sm text-gray-800">
                Tous les frais, droits et honoraires liés au présent acte et à ses suites — y compris les droits
                d'enregistrement et d'inscription à la conservation foncière de l'acte de vente définitif — sont
                à la charge exclusive du BÉNÉFICIAIRE.
            </p>
        </div>
    </div>

    {{-- ==================== ARTICLE 11 – PROTECTION DES DONNÉES ==================== --}}
    <div class="mb-3">
        <h2 class="article-title">ARTICLE 11 – PROTECTION DES DONNÉES À CARACTÈRE PERSONNEL</h2>
        <div class="pl-4">
            <p class="text-sm text-gray-800">
                En application de la loi n° 09-08 relative à la protection des personnes physiques à l'égard du
                traitement des données à caractère personnel, le BÉNÉFICIAIRE consent à ce que la société IMOZINE
                collecte et traite ses données personnelles aux fins exclusives de la gestion de son dossier
                d'acquisition. Ces données pourront être communiquées aux sous-traitants, héritiers, ayants droit
                et mandataires habilités. Le BÉNÉFICIAIRE dispose d'un droit d'accès, de rectification et
                d'opposition en adressant une demande écrite au VENDEUR.
            </p>
        </div>
    </div>

    {{-- ==================== ARTICLE 12 – ÉLECTION DE DOMICILE ==================== --}}
    <div class="mb-3">
        <h2 class="article-title">ARTICLE 12 – ÉLECTION DE DOMICILE</h2>
        <div class="pl-4">
            <p class="text-sm text-gray-800">
                Les Parties font élection de domicile en leurs adresses sus-indiquées.
            </p>
        </div>
    </div>

    {{-- ==================== ARTICLE 13 – COMPÉTENCE JURIDICTIONNELLE ==================== --}}
    <div class="mb-3">
        <h2 class="article-title">ARTICLE 13 – COMPÉTENCE JURIDICTIONNELLE</h2>
        <div class="pl-4">
            <p class="text-sm text-gray-800">
                Les tribunaux de Casablanca seront seuls compétents pour connaître tout litige qui pourrait
                surgir à l'occasion de l'exécution des présentes et de leurs suites.
            </p>
        </div>
    </div>


    {{-- ==================== SIGNATURES ==================== --}}
    <div class="mt-4 pt-3 border-t-2 border-primary">
        <div class="text-center mb-3">
            <p class="text-sm font-semibold text-gray-800">
                Fait à Casablanca, le {{ isset($date_enreg) ? $formatDate($date_enreg) : '………………' }}
            </p>
        </div>

        <div class="signature-grid">
            {{-- Vendeur --}}
            <div class="col">
                <div class="signature-box"></div>
                <p class="signature-label">LE VENDEUR</p>
                <p class="text-sm font-semibold text-gray-800">Société IMOZINE SARL</p>
                <p class="text-xs text-gray-500 mt-1">(Cachet et signature)</p>
            </div>

            {{-- Bénéficiaire --}}
            <div class="col">
                <div class="signature-box"></div>
                <p class="signature-label">LE BÉNÉFICIAIRE</p>
                @if(isset($reservation['aquereurs']) && is_array($reservation['aquereurs']) && count($reservation['aquereurs']) > 0)
                    @foreach($reservation['aquereurs'] as $aquereur)
                        @php $client = $aquereur['client'] ?? []; @endphp
                        <p class="text-sm font-semibold text-gray-800">
                            M. / Mme {{ $client['nom'] ?? '' }} {{ $client['prenom'] ?? '' }}
                        </p>
                    @endforeach
                @else
                    <p class="text-sm font-semibold text-gray-800">M. / Mme …………………………</p>
                @endif
                <p class="text-xs text-gray-500 mt-1">(Signature précédée de « Lu et Approuvé »)</p>
            </div>
        </div>

        {{-- Footer --}}
        <div class="footer">
            IMOZINE SARL — Contrat de Réservation — Projet Green Land — Ain Chock, Casablanca
        </div>

    </div>

</div>
</body>
</html>
