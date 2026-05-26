<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>REÇU DE PÉNALITÉ DE DÉSISTEMENT</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', 'Arial', sans-serif;
            font-size: 12px;
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
            margin-bottom: 30px;
            border-collapse: collapse;
        }
        .header-table td {
            border: none;
            padding: 0;
            vertical-align: top;
        }
        .logo-cell {
            width: 80px;
        }
        .logo-container {
            width: 80px;
            height: 80px;
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
            font-size: 10px;
            line-height: 1.4;
        }
        .company-name {
            font-weight: bold;
            font-size: 11px;
            margin-bottom: 3px;
        }

        .title {
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 8px;
            text-decoration: underline;
        }
        .subtitle {
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 30px;
        }

        /* Info Box */
        .info-box {
            background-color: #FFF3E0;
            padding: 12px;
            margin-bottom: 20px;
            border-left: 3px solid #E74C3C;
        }
        .info-row {
            margin-bottom: 8px;
        }
        .info-label {
            font-weight: bold;
            color: #34495E;
            display: inline-block;
            width: 35%;
        }
        .info-value {
            color: #2C3E50;
            display: inline-block;
            width: 64%;
        }

        .amount {
            font-size: 18px;
            font-weight: bold;
            color: #E74C3C;
            text-align: center;
            margin: 15px 0;
            background-color: #FDEBD0;
            padding: 10px;
        }
        .text {
            font-size: 11px;
            line-height: 1.5;
            text-align: justify;
            margin-bottom: 12px;
        }
        .bold {
            font-weight: bold;
        }

        /* ========== SIGNATURE SECTION: TABLE FOR SIDE BY SIDE ========== */
        .signature-container {
            width: 100%;
            margin-top: 50px;
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

        .footer {
            text-align: center;
            font-size: 9px;
            margin-top: 60px;
            color: #7F8C8D;
            padding-top: 8px;
            border-top: 1px solid #E4E4E4;
        }
    </style>
</head>
<body>
    <div class="container">

        <!-- HEADER with table -->
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
                 </td>
             </tr>
         </table>

        <!-- TITLE -->
        <div class="title">REÇU DE PÉNALITÉ DE DÉSISTEMENT</div>
        <div class="subtitle">N° {{ $num_recu }}</div>

        <!-- Info Box -->
        <div class="info-box">
            <div class="info-row">
                <span class="info-label">N° Dossier :</span>
                <span class="info-value">{{ $code_dossier }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Bien immobilier :</span>
                <span class="info-value">{{ $bienCompletNom ?: '' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Client(s) :</span>
                <span class="info-value">{{ $clientNames ?: '' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Mode de paiement :</span>
                <span class="info-value">
                    {{ $getModePaiementLabel($mode_paiement) }}
                    @if(!empty($numero_paiement) && $mode_paiement != 1)
                        - N°: {{ $numero_paiement }}
                    @endif
                </span>
            </div>
        </div>

        <!-- Montant -->
        <div class="amount">
            Montant de la pénalité : {{ $formatCurrency($montant_penalite) }}
        </div>

        <!-- Texte officiel -->
        <div class="text">
            Nous, <span class="bold">{{ $societe['raison_sociale'] ?? 'La société' }}</span>,
            attestons par la présente que {{ $estMultiplesClients ? 'les clients' : 'le client' }}
            <span class="bold"> {{ $clientNames }}</span> {{ $estMultiplesClients ? 'ont' : 'a' }} versé{{ !$estMultiplesClients ? 'e' : '' }}
            une pénalité de désistement d'un montant de
            <span class="bold">{{ $formatCurrency($montant_penalite) }}</span>.
        </div>

        <div class="text">
            Ce règlement a été effectué {{ $mode_paiement == 1 ? 'en espèces' : 'par ' . $getModePaiementLabel($mode_paiement) }}
            @if(!empty($numero_paiement) && $mode_paiement != 1)
                sous la référence n° {{ $numero_paiement }}
            @endif,
            concernant le bien immobilier désigné sous la référence
            <span class="bold">{{ $bienCompletNom ?: '' }}</span>.
        </div>

        <div class="text">
            La présente pénalité de désistement est versée suite à la décision du client
            de ne pas donner suite à la réservation du bien immobilier. Conformément aux
            conditions générales de vente et à la clause de réservation signée par les parties,
            ce montant reste définitivement acquis à la société à titre de dommages et intérêts.
        </div>

        <div class="text">
            Par le présent reçu, le client reconnaît avoir été informé des conditions
            de désistement et accepte que cette pénalité reste acquise à la société.
        </div>

        <div class="text">
            Fait à {{ $societe['ville'] ?? '............' }}, le {{ $currentDate }}
        </div>

        <!-- SIGNATURE SECTION with table (side by side) -->
        <div class="signature-container">
            <table class="signature-table">
                <tr>
                    <td class="signature-left">
                        <div class="signature-line">
                            Signature du Client<br>
                            <span style="font-size: 9px;">{{ $clientNames }}</span><br>
                            <span style="font-size: 8px;">CIN / Passeport</span>
                        </div>
                     </td>
                    <td class="signature-right">
                        <div class="signature-line">
                            Signature de la Société<br>
                            <strong>{{ $societe['raison_sociale'] ?? ' ' }}</strong><br>
                            Représentant légal
                        </div>
                     </td>
                 </tr>
             </table>
        </div>

        <!-- FOOTER -->
        <div class="footer">
            Document généré le {{ $currentDate }} — {{ $societe['raison_sociale'] ?? 'Société' }} —
            Tous droits réservés {{ date('Y') }}
        </div>

    </div>
</body>
</html>
