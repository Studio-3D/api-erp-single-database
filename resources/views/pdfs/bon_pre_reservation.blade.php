<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>REÇU DE PRÉ-RÉSERVATION</title>
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

        /* Header */
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
            margin-bottom: 20px;
            text-decoration: underline;
        }
        .title-number {
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 30px;
        }
        .content {
            margin-bottom: 30px;
            text-align: justify;
        }
        .content p {
            margin-bottom: 15px;
        }

        /* ========== SIGNATURE SECTION: FORCED SIDE BY SIDE ========== */
        .signature-container {
            width: 100%;
            margin-top: 80px;
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
        /* Force table cells to have equal height */
        .signature-table td {
            height: 80px;
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
        <div class="title">REÇU DE PRÉ-RÉSERVATION</div>
        <div class="title-number">N° {{ $code_pre_reservation }}</div>

        <!-- CONTENT -->
        <div class="content">
            <p>
                La société <strong>{{ $societe['raison_sociale'] ?? ' ' }}</strong>, confirme la pré-réservation du bien immobilier suivant :
            </p>
            <p>
                Le bien identifié sous la référence <strong>{{ $propriete_dite_bien ?: 'B' . $code_pre_reservation }}</strong> est situé
                <strong>{{ $getNiveauText($niveau) }}</strong>,
                d'une superficie de <strong>{{ $superficie ?: '0' }}</strong> m² et d'orientation <strong>{{ $getOrientationFullName($orientation) }}</strong>.
                Ce bien est proposé au prix de <strong>{{ $formatCurrency($prix) }}</strong>.
                @if(!empty($rdv))
                    Un rendez-vous a été fixé pour le <strong>{{ \Carbon\Carbon::parse($rdv)->format('d/m/Y') }}</strong> afin de finaliser cette réservation.
                @endif
            </p>
            <p>
                Ce reçu atteste de l'engagement du client à procéder à la réservation définitive du bien selon les modalités convenues entre les parties.
            </p>
        </div>

        <!-- ========== SIGNATURE SECTION: SIMPLE TABLE WITH TWO CELLS ========== -->
        <div class="signature-container">
            <table class="signature-table">
                <tr>
                    <td class="signature-left">
                            Signature du Client<br>
                            CIN / Passeport
                    </td>
                    <td class="signature-right">
                            Signature de la Société<br>
                            <strong>{{ $societe['raison_sociale'] ?? 'ste_sup_admin' }}</strong><br>
                    </td>
                </tr>
            </table>
        </div>

        <!-- FOOTER -->
        <div class="footer">
            Fait à ............, le {{ $currentDate }}
        </div>

    </div>
</body>
</html>
