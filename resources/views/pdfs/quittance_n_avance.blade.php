<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Quittance</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', 'Arial', sans-serif;
            font-size: 11px;
            color: #1a1a1a;
            line-height: 1.6;
            padding: 30px 35px;
            background: white;
        }

        .container {
            max-width: 100%;
            margin: 0 auto;
        }

        .text-primary { color: #0d4a35; }

        .header-container {
            display: table;
            width: 100%;
            border-bottom: 2px solid #0d4a35;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }

        .header-left {
            display: table-cell;
            width: 20%;
            vertical-align: middle;
        }

        .header-center {
            display: table-cell;
            width: 60%;
            text-align: center;
            vertical-align: middle;
        }

        .header-right {
            display: table-cell;
            width: 20%;
            text-align: right;
            vertical-align: middle;
        }

        .header-logo {
            max-height: 60px;
            max-width: 80px;
        }

        .header-greenland {
            max-height: 50px;
            max-width: 100px;
        }

        .header-title {
            font-size: 22px;
            font-weight: bold;
            color: #0d4a35;
            text-transform: uppercase;
            letter-spacing: 3px;
        }

        .header-subtitle {
            font-size: 10px;
            color: #4a4a4a;
            font-weight: 600;
            margin-top: 4px;
        }

        .content {
            margin-top: 10px;
        }

        .recognition-text {
            font-size: 11px;
            text-align: justify;
            margin-bottom: 12px;
            padding: 8px 0;
        }

        .recognition-text strong {
            color: #0d4a35;
        }

        .client-info {
            padding-left: 20px;
            margin: 8px 0 12px 0;
        }

        .client-info p {
            margin-bottom: 2px;
        }

        .payment-item {
            margin-bottom: 6px;
            padding-left: 20px;
        }

        .amount-box {
            background: #f8f9fa;
            border-left: 4px solid #b8973a;
            padding: 10px 16px;
            margin: 4px 0 12px 20px;
        }

        .amount-row {
            display: table;
            width: 100%;
            margin: 4px 0;
        }

        .amount-label {
            display: table-cell;
            width: 100px;
            font-weight: 600;
            color: #4a4a4a;
        }

        .amount-value {
            display: table-cell;
            font-weight: bold;
            color: #0d4a35;
        }

        .signature-section {
            margin-top: 30px;
            display: table;
            width: 100%;
        }

        .signature-col {
            display: table-cell;
            width: 50%;
            text-align: center;
        }

        .signature-label {
            font-size: 11px;
            font-weight: bold;
            color: #0d4a35;
        }

        .signature-sub {
            font-size: 9px;
            color: #6b7280;
            margin-top: 2px;
        }

        .conditions {
            margin-top: 20px;
            padding-top: 12px;
        }

        .conditions-title {
            font-size: 10px;
            font-weight: bold;
            color: #0d4a35;
            margin-bottom: 6px;
        }

        .conditions-list {
            list-style-type: none;
            padding-left: 0;
            font-size: 9px;
            color: #4b5563;
        }

        .conditions-list li {
            padding: 2px 0;
            padding-left: 16px;
            position: relative;
        }

        .conditions-list li:before {
            content: "-";
            position: absolute;
            left: 0;
            color: #b8973a;
        }

        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        .pl-4 { padding-left: 16px; }
        .mt-2 { margin-top: 8px; }
        .mt-4 { margin-top: 16px; }
        .mb-2 { margin-bottom: 8px; }
    </style>
</head>
<body>
<div class="container">

    {{-- HEADER --}}
    <div class="header-container">
        <div class="header-left">
            @if($logoBase64)
                <img src="{{ $logoBase64 }}" alt="Logo IMOZINE" class="header-logo">
            @endif
        </div>
        <div class="header-center">
            <div class="header-title">QUITTANCE</div>
        </div>
        <div class="header-right">
            @if($greenLandBase64)
                <img src="{{ $greenLandBase64 }}" alt="Green Land" class="header-greenland">
            @else
                <div style="font-size: 10px; font-weight: bold; color: #0d4a35; border: 1px solid #b8973a; border-radius: 4px; padding: 4px 12px; display: inline-block; background: #e8f5e9;">
                    {{ $projet_nom ?? 'GreenLand' }}
                </div>
            @endif
        </div>
    </div>

    {{-- CONTENT --}}
    <div class="content">

        {{-- Recognition Text --}}
        <div class="recognition-text">
            La société <strong>« {{ $societe['raison_social'] ?? 'IMOZINE' }} »</strong>, Société à responsabilité limitée, au capital social de {{ $societe['capital'] ?? '141.050.000' }} dirhams, dont le siège social est au {{ $societe['adresse'] ?? '13 Angle Rue de Rome et Rue de Varsovie, Résidence Amina, 1er Etage Appt N° 01, Casablanca' }},
            <br><br>
            <strong>Reconnait avoir reçu de :</strong>
        </div>

        {{-- Client Information - LOOP THROUGH ALL CLIENTS --}}
        <div class="client-info">
            @if(isset($aquereurs) && count($aquereurs) > 0)
                @foreach($aquereurs as $aquereur)
                    @php $client = $aquereur['client'] ?? []; @endphp
                    <p>
                        <strong>{{ $getCivilite($client['civilite'] ?? '') }}</strong>
                        {{ $client['nom'] ?? '' }} {{ $client['prenom'] ?? '' }}
                        @if(!empty($client['cin']))
                            titulaire de la CIN n° <strong>{{ $client['cin'] }}</strong>
                        @endif
                        @if(!empty($client['adresse']) || !empty($client['ville']))
                            , domicilié à <strong>{{ $client['adresse'] ?? '' }}{{ isset($client['ville']) ? ', ' . $client['ville'] : '' }}</strong>
                        @endif
                    </p>
                @endforeach
            @else
                <p>M./Mme ..............................................</p>
            @endif
        </div>

        {{-- LOOP THROUGH ALL AVANCES --}}
        @foreach($allAvances as $index => $avance)
            @php
                $montant = $avance['montant'] ?? 0;
                $modePaiement = $avance['mode_paiement'] ?? null;
                $banqueNom = $avance['banque']['nom'] ?? '';
                $numeroPaiement = $avance['numero_paiement'] ?? '';
                $montantLettres = $avance['montant_par_lettre'] ?? '';

                $paymentDescription = '';

                if ($modePaiement == 1) {
                    $paymentDescription = 'Un paiement en espèces';
                } elseif ($modePaiement == 2) {
                    $paymentDescription = 'Un chèque';
                    if ($banqueNom) $paymentDescription .= ' domicilié à ' . $banqueNom;
                    if ($numeroPaiement) $paymentDescription .= ', n° ' . $numeroPaiement;
                } elseif ($modePaiement == 3) {
                    $paymentDescription = 'Un chèque ';
                    if ($banqueNom) $paymentDescription .= ' domicilié à ' . $banqueNom;
                    if ($numeroPaiement) $paymentDescription .= ', n° ' . $numeroPaiement;
                } elseif ($modePaiement == 4) {
                    $paymentDescription = 'Un chèque certifié';
                    if ($banqueNom) $paymentDescription .= ' domicilié à ' . $banqueNom;
                    if ($numeroPaiement) $paymentDescription .= ', n° ' . $numeroPaiement;
                } elseif ($modePaiement == 5) {
                    $paymentDescription = 'Un virement bancaire';
                    if ($banqueNom) $paymentDescription .= ' domicilié à ' . $banqueNom;
                    if ($numeroPaiement) $paymentDescription .= ', n° ' . $numeroPaiement;
                } elseif ($modePaiement == 6) {
                    $paymentDescription = 'Un versement';
                    if ($banqueNom) $paymentDescription .= ' domicilié à ' . $banqueNom;
                    if ($numeroPaiement) $paymentDescription .= ', n° ' . $numeroPaiement;
                } else {
                    $paymentDescription = 'Un paiement';
                }

                $paymentDescription .= ' d\'une somme principale de :';
            @endphp

            {{-- Payment Description --}}
            <div class="payment-item">
                <p style="font-weight: 600; color: #0d4a35; margin-bottom: 2px;">
                    {{ $paymentDescription }}
                </p>
            </div>

            {{-- Amount Box --}}
            <div class="amount-box">
                <div class="amount-row">
                    <span class="amount-label">En chiffre :</span>
                    <span class="amount-value">{{ number_format($montant, 0, ',', ' ') }} Dhs</span>
                </div>
                <div class="amount-row">
                    <span class="amount-label">En lettres :</span>
                    <span class="amount-value" style="text-transform: uppercase; font-size: 10px;">
                        {{ $montantLettres ?: '…………………………' }} Dhs
                    </span>
                </div>
            </div>
        @endforeach

        {{-- Property Description --}}
        <div style="margin: 12px 0 8px 0;">
            <p style="font-size: 10px;">
                En compte et à valoir sur le prix de vente du bien situé sur le projet
                <strong style="color: #0d4a35;">{{ $projet_nom ?? 'GreenLand' }}</strong>
                et dont la désignation est détaillée sur le contrat de réservation
                <strong>n°{{ $reservation['code_reservation'] ?? 'xxxxxxxx' }}</strong> :
            </p>
        </div>

        {{-- Signatures --}}
        <div class="signature-section">
            <div class="signature-col">
                <div style="height: 50px; margin-bottom: 4px;"></div>
                <div class="signature-label">Le Client</div>
                <div class="signature-sub">Signature</div>
            </div>
            <div class="signature-col">
                <div style="height: 50px; margin-bottom: 4px;"></div>
                <div class="signature-label">Agent Commercial</div>
                <div class="signature-sub">Signature</div>
            </div>
        </div>

        {{-- Conditions --}}
        <div class="conditions">
            <div class="conditions-title">(*) Conditions :</div>
            <ul class="conditions-list">
                <li>Le reliquat du montant de la vente doit être acquitté aux conditions de la promesse de vente ;</li>
                <li>En cas de résiliation de la vente 10% du montant du prix de vente sera acquise au vendeur ;</li>
                <li>En cas de résiliation, le montant restitué à l'acheteur ne peut en aucun être productif d'intérêt ;</li>
                <li>En cas de rejet de paiement du chèque, pour quelque motif que ce soit, la présente quittance ainsi que l'acte de réservation deviendront nuls et non avenus.</li>
            </ul>
        </div>

        {{-- Date at bottom --}}
        <div style="text-align: center; margin-top: 15px; padding-top: 10px;">
            <p style="font-size: 10px; font-weight: 600; color: #0d4a35;">
                Fait le {{ $currentDate }}
            </p>
        </div>
    </div>

</div>
</body>
</html>
