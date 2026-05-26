<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>REÇU</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', 'Arial', sans-serif;
            font-size: 11px;
            line-height: 1.3;
            padding: 15px;
            background-color: #fff;
        }
        .container {
            max-width: 100%;
            margin: 0 auto;
        }

        /* ========== HEADER: Logo + Company info on SAME ROW using TABLE (PDF FRIENDLY) ========== */
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

        .title-center {
            text-align: center;
            margin-bottom: 8px;
        }
        .title-center h1 {
            font-size: 18px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .title-center p {
            font-size: 14px;
            font-weight: 600;
            margin-top: 2px;
        }
        .border-top {
            border-top: 1px solid #000;
            margin: 8px 0;
        }
        .section {
            margin-bottom: 10px;
        }
        .section-title {
            font-weight: bold;
            font-size: 12px;
            text-decoration: underline;
            margin-bottom: 4px;
        }
        .ml-4 {
            margin-left: 16px;
        }
        .ml-5 {
            margin-left: 20px;
        }
        .mt-2 {
            margin-top: 4px;
        }
        .mt-8 {
            margin-top: 15px;
        }
        .font-medium {
            font-weight: 500;
        }
        .font-bold {
            font-weight: bold;
        }
        .italic {
            font-style: italic;
        }
        .underline {
            text-decoration: underline;
        }
        .flex {
            display: table;
            width: 100%;
        }
        .justify-between {
            display: table;
            width: 100%;
        }
        .items-end {
            vertical-align: bottom;
        }
        .text-right {
            text-align: right;
        }
        .mb-1 {
            margin-bottom: 3px;
        }
        .mb-2 {
            margin-bottom: 5px;
        }
        .mb-4 {
            margin-bottom: 8px;
        }
        .ml-2 {
            margin-left: 5px;
        }
        .mr-2 {
            margin-right: 5px;
        }
        .space-y-1 > * + * {
            margin-top: 2px;
        }
        p {
            margin-bottom: 3px;
        }
        .compact-list p {
            margin-bottom: 2px;
        }

        /* Signature section using table for left/right alignment */
        .signature-table {
            display: table;
            width: 100%;
            margin-top: 15px;
        }
        .signature-left {
            display: table-cell;
            width: 50%;
            vertical-align: bottom;
        }
        .signature-right {
            display: table-cell;
            width: 50%;
            text-align: right;
            vertical-align: bottom;
        }

        /* Checkbox style for payment modes */
        .checkbox-item {
            margin-bottom: 8px;
        }
        .checkbox-box {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #000;
            margin-right: 12px;
            vertical-align: middle;
            position: relative;
            background-color: #fff;
        }
        .checkbox-box.checked {
            background-color: #2563eb;
            border-color: #2563eb;
        }
        .checkbox-box.checked::after {
            content: "✓";
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-size: 11px;
            font-weight: bold;
        }
        .checkbox-label {
            font-weight: 500;
            margin-right: 5px;
        }
        .checkbox-detail {
            font-weight: 500;
            margin-left: 5px;
        }

        @media print {
            body { padding: 0.2in; }
            .checkbox-box.checked { background-color: #2563eb !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="container">

        <!-- ========== HEADER: Logo left + Company info right on SAME ROW using TABLE (PDF compatible) ========== -->
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
                        <div class="company-name">{{ $raison_social ?: 'Société' }}</div>
                        @if(!empty($tel))
                            <div>Tél: {{ $tel }}</div>
                        @endif
                        @if(!empty($email))
                            <div>Email: {{ $email }}</div>
                        @endif
                        @if(!empty($adresse))
                            <div>Adresse: {{ $adresse }}</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Titre REÇU au centre -->
        <div class="title-center">
            <h1>REÇU </h1>
            <p>N° {{ $num_recu }}</p>
        </div>

        <div class="border-top"></div>

        <!-- LE SOUSSIGNÉ -->
        <div class="section">
            <h3 class="section-title">LE SOUSSIGNÉ:</h3>
            <p class="mb-2">
                La société <strong>«{{ $raison_social ?: ' ' }}»</strong>,
                à responsabilité limitée de droit Marocain, au capital social de
                <strong>{{ number_format($capital, 0, ',', ' ') }}</strong> de dirhams, ayant son siège
                social à <strong>{{ $adresse ?: ' ' }}</strong>, immatriculée au
                registre du commerce sous n°
                <strong>{{ $registre_commerce ?: ' ' }}</strong> et dont le numéro
                de l'identifiant fiscal est le n°
                <strong>{{ $id_fiscal ?: ' ' }}</strong>.
            </p>
        </div>

        <!-- Déclare avoir reçu de -->
        <div class="section">
            <h3 class="section-title">Déclare avoir reçu de :</h3>
            <div class="ml-4">
                <p><span class="font-medium">Nom du réservataire :</span> {{ $clientNames ?: ' ' }}</p>
                <p><span class="font-medium">CIN :</span> {{ $clientCINs ?: ' ' }}</p>
                <p><span class="font-medium">Adresse :</span> {{ $clientAddresses ?: ' ' }}</p>
            </div>
        </div>

        <!-- La somme de -->
        <div class="section">
            <h3 class="section-title">La somme de :</h3>
            <div class="ml-4">
                <p><span class="font-medium">Montant en chiffres :</span> {{ number_format($montant, 2, ',', ' ') }} MAD</p>
                <p><span class="font-medium">Montant en lettres :</span> {{ $montantEnLettres }}</p>
            </div>
        </div>

        <!-- Mode de paiement avec cases à cocher stylisées -->
        <div class="section">
            <h3 class="section-title">Mode de paiement :</h3>
            <div class="ml-4">
                <!-- Espèces -->
                <div class="checkbox-item">
                    <span class="checkbox-box {{ $mode_paiement == 1 ? 'checked' : '' }}"></span>
                    <span class="checkbox-label">Espèces</span>
                </div>

                <!-- Chèque / Chèque de banque / Chèque certifié -->
                <div class="checkbox-item">
                    <span class="checkbox-box {{ in_array($mode_paiement, [2,3,4]) ? 'checked' : '' }}"></span>
                    <span class="checkbox-label">
                        @if($mode_paiement == 2) Chèque
                        @elseif($mode_paiement == 3) Chèque de banque
                        @elseif($mode_paiement == 4) Chèque certifié
                        @else Chèque
                        @endif
                    </span>
                    @if(in_array($mode_paiement, [2,3,4]) && !empty($numero_paiement))
                        <span class="checkbox-detail">n° : {{ $numero_paiement }}</span>
                    @endif
                    @if(in_array($mode_paiement, [2,3,4]) && !empty($banque))
                        <span class="checkbox-detail">Banque : {{ $banque }}</span>
                    @endif
                </div>

                <!-- Virement bancaire -->
                <div class="checkbox-item">
                    <span class="checkbox-box {{ $mode_paiement == 5 ? 'checked' : '' }}"></span>
                    <span class="checkbox-label">Virement bancaire</span>
                    @if($mode_paiement == 5 && !empty($numero_paiement))
                        <span class="checkbox-detail">(référence : {{ $numero_paiement }})</span>
                    @endif
                </div>

                <!-- Versement -->
                <div class="checkbox-item">
                    <span class="checkbox-box {{ $mode_paiement == 6 ? 'checked' : '' }}"></span>
                    <span class="checkbox-label">Versement</span>
                    @if($mode_paiement == 6 && !empty($numero_paiement))
                        <span class="checkbox-detail">n° : {{ $numero_paiement }}</span>
                    @endif
                </div>
            </div>
        </div>

        <!-- Objet du paiement -->
        <div class="section">
            <h3 class="section-title">Objet du paiement :</h3>
            <div class="ml-4">
                <p class="mb-2">Acompte relatif à la réservation du bien suivant :</p>
                <div class="ml-5 space-y-1">
                    <p><span class="font-medium">- Propriété dite bien :</span> {{ $bien['propriete_dite_bien'] ?: ' ' }}</p>
                    <p><span class="font-medium">- {{ $bien['type'] ?: 'Appartement' }} n° :</span> {{ $bien['bien_numero'] ?: ' ' }}</p>
                    <p><span class="font-medium">- Résidence :</span> {{ $bien['projet'] ?: ' ' }}</p>
                    <p><span class="font-medium">- Adresse du bien :</span> {{ $bien['adresse_projet'] ?: ' ' }}</p>
                    @if(!empty($bien['tranche']))
                        <p><span class="font-medium">- Tranche :</span> {{ $bien['tranche'] }}</p>
                    @endif
                    @if(!empty($bien['bloc']))
                        <p><span class="font-medium">- Bloc :</span> {{ $bien['bloc'] }}</p>
                    @endif
                    @if(!empty($bien['immeuble']))
                        <p><span class="font-medium">- Immeuble :</span> {{ $bien['immeuble'] }}</p>
                    @endif
                    <p><span class="font-medium">- Superficie :</span> {{ $bien['superficie_habitable'] ?: ' ' }} m²</p>
                    <p><span class="font-medium">- Prix de vente convenu :</span> {{ number_format($bien['prix'] ?? 0, 2, ',', ' ') }} DH</p>
                    <p><span class="font-medium">- Titre Foncier :</span> {{ $bien['titre_foncier'] ?: ' ' }}</p>
                    @if(!empty($bien['isParkingAvailable']) && $bien['isParkingAvailable'])
                        <p><span class="font-medium">- Parking :</span> Oui</p>
                        <p><span class="font-medium">- Superficie parking :</span> {{ $bien['superficie_parking'] ?: ' ' }} m²</p>
                        <p><span class="font-medium">- Prix parking :</span> {{ number_format($bien['prix_parking'] ?? 0, 2, ',', ' ') }} DH</p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Référence du contrat de réservation -->
        <div class="section">
            <h3 class="section-title">Référence du contrat de réservation :</h3>
            <div class="ml-4">
                <p>
                    <span class="font-medium">N° :</span> {{ $code_reservation ?: ' ' }}
                    <span class="ml-2 font-medium">en date du :</span> {{ $currentDate }}
                </p>
            </div>
        </div>

        <!-- Observation -->
        <div class="section">
            <h3 class="section-title">Observation :</h3>
            <div class="ml-4">
                <p class="italic">
                    Ce montant sera imputé sur le prix de vente définitif conformément au contrat de réservation signé entre les parties.
                </p>
            </div>
        </div>

        <!-- Signature using table layout for PDF compatibility -->
        <div class="signature-table">
            <div class="signature-left">
                <p class="font-medium">Fait à : Casablanca, Le : {{ $currentDate }}</p>
            </div>
            <div class="signature-right">
                <p class="font-bold underline mb-2">Signature du vendeur</p>
                <p class="font-bold mt-4">Société : {{ $raison_social ?: ' ' }}</p>
            </div>
        </div>

    </div>
</body>
</html>
