<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Attestation de Vente</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #2a2c3e;
            margin: 0;
            padding: 40px;
            font-size: 10px;
        }
        .container {
            border: 1px solid #E5E7EB;
            border-radius: 8px;
            padding: 20px;
        }
        .company-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 20px;
            gap: 20px;
        }
        .left-section {
            display: flex;
            align-items: flex-start;
            flex: 2;
            gap: 12px;
        }
        .logo-container {
            width: 50px;
            height: 50px;
            margin-top: 4px;
        }
        .logo {
            width: 50px;
            height: 50px;
            object-fit: contain;
        }
        .company-info {
            flex: 1;
        }
        .company-name {
            font-size: 14px;
            font-weight: bold;
            color: #111827;
            margin-bottom: 4px;
        }
        .company-detail-text {
            font-size: 9px;
            color: #6B7280;
            margin-bottom: 2px;
            line-height: 1.3;
        }
        .meta-info {
            text-align: right;
        }
        .meta-row {
            margin-bottom: 6px;
        }
        .meta-label {
            font-size: 10px;
            color: #6B7280;
            display: inline-block;
            width: 40px;
        }
        .meta-value {
            font-size: 10px;
            font-weight: bold;
            color: #111827;
        }
        .title-section {
            text-align: center;
            margin-bottom: 24px;
        }
        .title {
            font-size: 20px;
            font-weight: bold;
            color: #4F46E5;
            margin-bottom: 8px;
        }
        .title-divider {
            height: 2px;
            background-color: #A5B4FC;
            margin-top: 8px;
            width: 80%;
            margin-left: auto;
            margin-right: auto;
        }
        .section-title {
            font-size: 14px;
            font-weight: bold;
            color: #4F46E5;
            margin-bottom: 12px;
            border-bottom: 1px solid #E5E7EB;
            padding-bottom: 6px;
        }
        .paragraph {
            font-size: 10px;
            line-height: 1.5;
            margin-bottom: 10px;
            text-align: justify;
        }
        .bold-text {
            font-weight: bold;
        }
        .client-info-container {
            background-color: #F3F4F6;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 12px;
            border-left: 4px solid #3B82F6;
        }
        .article-title {
            font-size: 12px;
            font-weight: bold;
            text-decoration: underline;
            margin-bottom: 8px;
        }
        .signature-container {
            display: flex;
            flex-direction: row;
            justify-content: space-between;
            margin-top: 40px;
            gap: 20px;
        }
        .signature-box {
            flex: 1;
        }
        .signature-label {
            font-size: 10px;
            font-weight: bold;
            margin-bottom: 8px;
            color: #2a2c3e;
        }
        .signature-placeholder {
            height: 80px;
            border: 1px solid #D1D5DB;
            border-radius: 4px;
            background-color: #F9FAFB;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Company Header -->
        <div class="company-header">
            <div class="left-section">
                @if($logoBase64)
                    <div class="logo-container">
                        <img src="{{ $logoBase64 }}" class="logo" alt="Logo">
                    </div>
                @endif
                <div class="company-info">
                    <div class="company-name">{{ $societe['raison_sociale'] ?? '' }}</div>
                    <div class="company-detail-text">
                        Adresse: {{ $societe['adresse'] ?? 'Adresse non disponible' }}
                    </div>
                    @if(!empty($societe['tel']))
                        <div class="company-detail-text">Tél: {{ $societe['tel'] }}</div>
                    @endif
                    @if(!empty($societe['email']))
                        <div class="company-detail-text">Email: {{ $societe['email'] }}</div>
                    @endif
                    @if(!empty($societe['rc']))
                        <div class="company-detail-text">RC: {{ $societe['rc'] }}</div>
                    @endif
                    @if(!empty($societe['ice']))
                        <div class="company-detail-text">ICE: {{ $societe['ice'] }}</div>
                    @endif
                </div>
            </div>
            <div class="meta-info">
                <div class="meta-row">
                    <span class="meta-label">N°:</span>
                    <span class="meta-value">{{ $num_recu }}</span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">Date:</span>
                    <span class="meta-value">{{ $currentDate }}</span>
                </div>
            </div>
        </div>

        <!-- Title -->
        <div class="title-section">
            <div class="title">ATTESTATION DE VENTE</div>
            <div class="title-divider"></div>
        </div>

        <!-- Document Content -->
        <!-- LES SOUSSIGNES -->
        <div>
            <div class="section-title">LES SOUSSIGNES</div>
            <div class="paragraph">
                La Societé « <span class="bold-text">{{ $societe['raison_sociale'] ?? '' }}</span> »,
                société à responsabilité limitée de droit Marocain, au capital social de 100.000,00 de dirhams,
                ayant son siège social à {{ $societe['adresse'] ?? '' }}, immatriculée au registre du commerce
                de Casablanca sous n° {{ $societe['rc'] ?? '' }} et dont le numéro de l'identifiant fiscal est le n° {{ $societe['ice'] ?? '' }}.
            </div>
        </div>

        <!-- Client Info -->
        <div style="margin-top: 15px;">
            <div class="section-title">LE RESERVANT D'UNE PART</div>
            @foreach($clients as $client)
            <div class="client-info-container">
                <div class="paragraph">
                    <span class="bold-text">
                        {{ $formatCivilite($client['client']['civilite'] ?? '') }} {{ $client['client']['nom'] ?? '' }} {{ $client['client']['prenom'] ?? '' }}
                    </span>
                    , titulaire de la carte d'identité nationale n° {{ $client['client']['cin'] ?? '' }}
                    @if(!empty($client['client']['adresse']))
                    , domicilié à {{ $client['client']['adresse'] }}, {{ $client['client']['ville'] ?? '' }}
                    @endif.
                </div>
            </div>
            @endforeach
        </div>

        <!-- Article 1 -->
        <div style="margin-top: 15px;">
            <div class="section-title">LE RESERVATAIRE D'AUTRE PART</div>
            <div class="article-title">Article 1 : OBJET</div>
            <div class="paragraph">
                Le réservant, s'engage à réserver, en s'obligeant à toutes les garanties ordinaires de fait et de droits les plus étendus en pareille matière ; Au réservataire qui s'engage d'acquérir, le bien immobilier dont la désignation suit
            </div>
        </div>

        <!-- Article 2 -->
        <div style="margin-top: 15px;">
            <div class="article-title">Article 2 : Désignation</div>
            <div class="paragraph">
                Le bien est un {{ $reservationDetails['bien']['type'] ?? '' }}
                <span class="bold-text">n° {{ $reservationDetails['bien']['numero'] ?? '' }}</span>
                sous le nom : <span class="bold-text">{{ $reservationDetails['bien']['propriete_dite_bien'] ?? '' }}</span>.
                à distraire des propriétés dénommées, objet du titre foncier mère numéro
                {{ $reservationDetails['bien']['projet']['titre_foncier'] ?? '' }}
                Ce Bien sera situé au
                <span class="bold-text">
                    @php
                        $etage = $reservationDetails['bien']['niveau'] ?? 0;
                        if($etage == 0) echo "RDC";
                        elseif($etage == 1) echo "1er étage";
                        elseif($etage == 2) echo "2ème étage";
                        elseif($etage == 3) echo "3ème étage";
                        else echo "{$etage}ème étage";
                    @endphp
                </span>
                , D'une superficie approximative de
                <span class="bold-text">{{ number_format(floatval($reservationDetails['bien']['superficie_habitable'] ?? 0), 2) }} m² </span>
                @if(floatval($reservationDetails['bien']['superficie_balcon'] ?? 0) > 0)
                , un balcon d'une superficie approximative de
                <span class="bold-text">{{ number_format(floatval($reservationDetails['bien']['superficie_balcon']), 2) }} m²</span>
                @endif
            </div>

            @if(floatval($reservationDetails['bien']['superficie_terrasse'] ?? 0) > 0)
            <div class="paragraph">
                Et une terrasse d'une superficie approximative de
                <span class="bold-text">{{ number_format(floatval($reservationDetails['bien']['superficie_terrasse']), 2) }} m²</span>
            </div>
            @endif

            @if(!empty($reservationDetails['bien']['composition_bien']) && count($reservationDetails['bien']['composition_bien']) > 0)
            <div class="paragraph">
                Il sera composé de :
                @php
                    $composition = $reservationDetails['bien']['composition_bien'];
                    $summedComposition = [];

                    foreach($composition as $comp) {
                        foreach($comp as $key => $value) {
                            if($value > 0) {
                                if(!isset($summedComposition[$key])) {
                                    $summedComposition[$key] = 0;
                                }
                                $summedComposition[$key] += intval($value);
                            }
                        }
                    }

                    $parts = [];
                    if(isset($summedComposition['nbre_halls']) && $summedComposition['nbre_halls'] > 0) {
                        $parts[] = $summedComposition['nbre_halls'] . ' hall' . ($summedComposition['nbre_halls'] > 1 ? 's' : '');
                    }
                    if(isset($summedComposition['nbre_salons']) && $summedComposition['nbre_salons'] > 0) {
                        $parts[] = $summedComposition['nbre_salons'] . ' salon' . ($summedComposition['nbre_salons'] > 1 ? 's' : '');
                    }
                    if(isset($summedComposition['nbre_chambres']) && $summedComposition['nbre_chambres'] > 0) {
                        $parts[] = $summedComposition['nbre_chambres'] . ' chambre' . ($summedComposition['nbre_chambres'] > 1 ? 's' : '');
                    }
                     if(isset($summedComposition['nbre_kitchenette']) && $summedComposition['nbre_kitchenette'] > 0) {
                        $parts[] = $summedComposition['nbre_kitchenette'] . ' kitchenette' . ($summedComposition['nbre_kitchenette'] > 1 ? 's' : '');
                    }
                     if(isset($summedComposition['nbre_sejour']) && $summedComposition['nbre_sejour'] > 0) {
                        $parts[] = $summedComposition['nbre_sejour'] . ' sejour' . ($summedComposition['nbre_sejour'] > 1 ? 's' : '');
                    }
                    if(isset($summedComposition['nbre_cuisines']) && $summedComposition['nbre_cuisines'] > 0) {
                        $parts[] = $summedComposition['nbre_cuisines'] . ' cuisine' . ($summedComposition['nbre_cuisines'] > 1 ? 's' : '');
                    }
                    if(isset($summedComposition['nbre_sdb']) && $summedComposition['nbre_sdb'] > 0) {
                        $parts[] = $summedComposition['nbre_sdb'] . ' salle' . ($summedComposition['nbre_sdb'] > 1 ? 's' : '') . ' de bain';
                    }
                    if(isset($summedComposition['nbre_balcons']) && $summedComposition['nbre_balcons'] > 0) {
                        $parts[] = $summedComposition['nbre_balcons'] . ' balcon' . ($summedComposition['nbre_balcons'] > 1 ? 's' : '');
                    }
                    if(isset($summedComposition['nbre_buanderies']) && $summedComposition['nbre_buanderies'] > 0) {
                        $parts[] = $summedComposition['nbre_buanderies'] . ' buanderie';
                    }
                    if(isset($summedComposition['nbre_placards']) && $summedComposition['nbre_placards'] > 0) {
                        $parts[] = $summedComposition['nbre_placards'] . ' placard' . ($summedComposition['nbre_placards'] > 1 ? 's' : '');
                    }
                    if(isset($summedComposition['nbre_receptions']) && $summedComposition['nbre_receptions'] > 0) {
                        $parts[] = $summedComposition['nbre_receptions'] . ' réception' . ($summedComposition['nbre_receptions'] > 1 ? 's' : '');
                    }

                    $text = implode(', ', $parts);
                    $lastCommaPos = strrpos($text, ', ');
                    if($lastCommaPos !== false) {
                        $text = substr($text, 0, $lastCommaPos) . ' et ' . substr($text, $lastCommaPos + 2);
                    }
                    echo $text;
                @endphp
            </div>
            @endif

            @if(!empty($reservationDetails['bien']['nb_parking']))
            <div class="paragraph">
                Et {{ $reservationDetails['bien']['nb_parking'] }} place(s) de parking au sous-sol
            </div>
            @endif

            @if(!empty($reservationDetails['bien']['nb_box']))
            <div class="paragraph">
                Et {{ $reservationDetails['bien']['nb_box'] }} Box
            </div>
            @endif
        </div>

        <!-- Article 3 -->
        <div style="margin-top: 15px;">
            <div class="article-title">Article 3 : Prix</div>
            <div class="paragraph">
                Le présent contrat de réservation est consenti et accepté moyennant le prix ci-après détaillé :<br/>
                * Soit un prix global estimatif de la somme
                <span class="bold-text">{{ number_format(floatval($reservationDetails['prix'] ?? 0), 2) }} DHS</span><br/>
                Sur lequel prix de vente, le réservataire a versé à titre d'acompte à valoir sur le prix de vente d'une valeur de
                <span class="bold-text">{{ number_format(floatval($sum_avances_valides), 2) }} DHS</span><br/>
                * Le reliquat soit la somme de
                <span class="bold-text">{{ number_format(floatval($reservationDetails['prix'] ?? 0) - floatval($sum_avances_valides), 2) }} DHS</span>
                sera réglée le jour de la réalisation de la vente définitive.
            </div>
        </div>

        <!-- Article 4 -->
        <div style="margin-top: 15px;">
            <div class="article-title">Article 4 : Compromis</div>
            <div class="paragraph">
                Il est énoncé que le client a signé le compromis en
                <span class="bold-text">{{ isset($form['date_sign_client']) ? date('d/m/Y', strtotime($form['date_sign_client'])) : '' }}</span>
                et le Maitre d'Ouvrage en
                <span class="bold-text">{{ isset($form['date_sign_mo']) ? date('d/m/Y', strtotime($form['date_sign_mo'])) : '' }}</span>
                et enregistré en
                <span class="bold-text">{{ isset($form['date_enreg']) ? date('d/m/Y', strtotime($form['date_enreg'])) : '' }}</span>
                avec une durée d'échéance du
                @php
                    $duree = $form['duree_echeance'] ?? '';
                    if($duree == "3") echo "3 Mois";
                    elseif($duree == "6") echo "6 Mois";
                    elseif($duree == "12") echo "12 Mois";
                    else echo $duree;
                @endphp
                correspondant le
                <span class="bold-text">{{ isset($form['date_echeance']) ? date('d/m/Y', strtotime($form['date_echeance'])) : '' }}</span>.
            </div>
        </div>

        @if(!empty($form['commentaire']))
        <div class="paragraph">
            <span class="bold-text">Commentaire:</span> {{ $form['commentaire'] }}
        </div>
        @endif

        <!-- Signatures -->
        <div class="signature-container">
            <div class="signature-box">
                <div class="signature-label">Signature Client :</div>
                <div class="signature-placeholder"></div>
            </div>
            <div class="signature-box">
                <div class="signature-label">Signature Responsable:</div>
                <div class="signature-placeholder"></div>
            </div>
        </div>
    </div>
</body>
</html>
