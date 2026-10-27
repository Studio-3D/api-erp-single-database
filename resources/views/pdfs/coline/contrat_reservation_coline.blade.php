<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Contrat de Réservation - Coline</title>
    <style>
        @page {
            margin: 10px 15px;
        }

        body {
            font-family: 'Times New Roman', serif;
            font-size: 11px;
            color: #000;
            line-height: 1.5;
            margin: 0;
            padding: 0;
        }

        p {
            text-align: justify;
            margin: 4px 0;
        }

        .blade-h1 {
            font-size: 12px;
            font-family: 'Times New Roman', serif;
            text-decoration: underline;
            margin-top: 8px;
            margin-left: 5.6pt;
            font-weight: bold;
        }

        .blade-p-quitance {
            margin-top: 12px;
            text-align: center;
            font-size: 13px;
            font-family: 'Times New Roman', serif;
            text-decoration: underline;
            font-weight: bold;
            margin-bottom: 6px;
        }

        /* ─── HEADER avec ligne + logo ─── */
        .header-container {
            position: relative;
            margin-bottom: 30px;
            padding-top: 20px;
            height: 120px;
        }

        .header-container hr {
            border: none;
            border-top: 1px solid black;
            margin: 0;
            position: absolute;
            top: 55px;
            left: 0;
            right: 0;
        }

        .header-container img {
            position: absolute;
            top: 0;
            left: 50px;
            height: 110px;
            width: 70px;
            object-fit: contain;
            background: white;
            padding: 0 5px;
        }

        /* ─── Signatures ─── */
        .signature-container {
            margin-top: 30px;
            width: 100%;
        }

        .signature-date {
            text-align: right;
            font-weight: bold;
            margin: 0;
            padding-right: 50px;
        }

        .signature-date-value {
            text-align: right;
            font-weight: bold;
            margin: 0;
            padding-right: 280px;
        }

        .signature-block {
            margin-top: 20px;
        }

        .signature-line {
            height: 60px;
        }

        strong {
            font-weight: bold;
        }

        u {
            text-decoration: underline;
        }
    </style>
</head>
<body>

{{-- ═══════════ LOGO + LIGNE ═══════════ --}}
<div class="header-container">
    <hr />
    @if(!empty($logoBase64))
        <img src="{{ $logoBase64 }}" alt="Logo" />
    @endif
</div>

{{-- ═══════════ TITRE ═══════════ --}}
<p class="blade-p-quitance">
    <u><strong>CONTRAT DE RESERVATION</strong></u>
</p>

<p style="margin-top:1px;">&nbsp;</p>

{{-- ═══════════ LES SOUSSIGNES ═══════════ --}}
<div class="blade-h1"><u>LES SOUSSIGNES</u>:</div>
<p>
    1/LA SOCIETE <strong>« {{ $societe['raison_social'] ?? '' }} »</strong>,
    société à responsabilité limitée de droit Marocain,
    au capital social de <strong>{{ $societe['capital'] ?? '' }} de dirhams</strong>,
    ayant son siège social à <strong>{{ $societe['adresse'] ?? '' }}</strong>,
    immatriculée au registre du commerce de Casablanca sous n° <strong>{{ $societe['rc'] ?? '' }}</strong>
    et dont le numéro de l'identifiant fiscal est le n° <strong>{{ $societe['if'] ?? '' }}</strong>.
</p>

<p class="blade-p-quitance">
    <u><strong>«LE RESERVANT D'UNE PART»</strong></u>
</p>

<p>
    2/Monsieur ou Madame
    @php $clients = $reservation['aquereurs'] ?? []; @endphp
    @if(count($clients) > 0)
        @foreach($clients as $idx => $aq)
            @php $cl = $aq['client'] ?? []; @endphp
            <strong>{{ $cl['nom'] ?? '' }} {{ $cl['prenom'] ?? '' }}</strong>
            à <strong>{{ $cl['adresse'] ?? 'Adresse' }}</strong>
            titulaire de la carte d'identité nationale
            <strong>n° {{ $cl['cin'] ?? '—' }}</strong>
            @if(!$loop->last) / @endif
        @endforeach
    @else
        <strong>Monsieur ou Madame ........................................</strong>
    @endif
    .
</p>

<p class="blade-p-quitance">
    <u><strong>«LE RESERVATAIRE» D'AUTRE PART</strong></u>
</p>

<p>Lesquels ont convenu de conclure le présent contrat de réservation</p>

{{-- ═══════════ ARTICLE 1 : OBJET ═══════════ --}}
<div class="blade-h1"><u>ARTICLE 1</u>:<u>OBJET</u></div>
<p>
    Le réservant, s'engage à réserver, en s'obligeant à toutes les garanties ordinaires de fait et de droits les plus étendus en pareille matière ;
    <br />
    Au réservataire qui s'engage d'acquérir, le bien immobilier dont la désignation suit :
</p>

{{-- ═══════════ ARTICLE 2 : DESIGNATION ═══════════ --}}
<div class="blade-h1"><u>ARTICLE 2</u>:<u>DESIGNATION</u></div>
<p>
    Un <strong>{{ $reservation['type_bien'] ?? '' }}</strong>
    n° <strong>{{ $reservation['numero_bien'] ?? '—' }}</strong>
    GH <strong>{{ $reservation['bloc_nom'] ?? '' }}</strong>
    IMM <strong>{{ $reservation['immeuble_nom'] ?? '' }}</strong>.
    En copropriété sis à {{ $reservation['localisation'] ?? '' }}
    à distraire des propriétés dénommées :
    <br />
    - « » objet du titre foncier mère numéro <strong>{{ $reservation['titre_foncier'] ?? '' }}</strong>
</p>

<p>
    Cet {{ $reservation['type_bien'] ?? '' }} sera situé au
    <strong>{{ $reservation['niveau_label'] ?? '' }}</strong>,
    d'une superficie approximative de <strong>{{ $reservation['superficie_approximative'] ?? 0 }} m²</strong>
    dont un balcon et buanderie d'une superficie approximative de
    <strong>{{ $reservation['superficie_balcon'] ?? 0 }} m²</strong>
    Et une terrasse d'une superficie approximative de
    <strong>{{ $reservation['superficie_terrasse'] ?? 0 }} m²</strong>.
    Il sera composé de :
    <strong>{{ $reservation['nbre_hall'] ?? 0 }}</strong> hall,
    <strong>{{ $reservation['nbre_salon'] ?? 0 }}</strong> salon,
    <strong>{{ $reservation['nbre_chambre'] ?? 0 }}</strong> chambres,
    <strong>{{ $reservation['nbre_cuisine'] ?? 0 }}</strong> cuisine,
    <strong>{{ $reservation['nbre_sdb'] ?? 0 }}</strong> salles de bain,
    <strong>{{ $reservation['nbre_balcon'] ?? 0 }}</strong> balcon,
    <strong>{{ $reservation['nbre_buanderie'] ?? 0 }}</strong> buanderie,
    <strong>{{ $reservation['nbre_placard'] ?? 0 }}</strong> placard Et
    <strong>{{ $reservation['nombre_parking'] ?? 0 }}</strong> place(s) de parking au sous-sol ET
    <strong>{{ $reservation['nombre_box'] ?? 0 }}</strong> Box.
</p>

<p>
    Le réservataire déclare, dès à présent, accepter l'emplacement de la place de parking qui sera affectée par les services fonciers des opérations topographiques à la fraction divise de
    @php
        $type = $reservation['type_bien'] ?? '';
        $typeLabel = $type === 'Appartement' ? "l'appartement" : ($type === 'Studio' ? 'Studio' : 'Magasin');
    @endphp
    {{ $typeLabel }} réservé.
    <br />
    Tel au surplus que ledit appartement existera avec toutes ses aisances et dépendances sans aucune exception ni réserve et sans qu'il soit besoin d'en faire une plus ample désignation, «le réservataire» déclarant parfaitement connaître le bien immobilier objet des présentes pour avoir visité le chantier et constaté l'état d'avancement des travaux préalablement à la signature des présentes.
    <br />
    Le réservataire reconnaît être informé que la superficie du bien objet des présentes n'est mentionnée qu'approximativement et que celle réelle et définitive apparaîtra après l'accomplissement par les services fonciers des opérations topographiques.
</p>

<p class="blade-p-quitance">
    <u><strong>Ci après dénommé « L'Immeuble ou le Bien»</strong></u>
</p>

{{-- ═══════════ ARTICLE 3 : PRIX ═══════════ --}}
<div class="blade-h1"><u>ARTICLE 3 : PRIX</u>:</div>
<p>
    Le présent contrat de réservation est consenti et accepté moyennant le prix ci-après détaillé :
    <br />
    * <strong>Pour la partie construite avec buanderie et balcon et terrasse</strong>,
    au prix principal et définitif de
    <strong>{{ number_format($totalPrice, 0, ',', ' ') }} ({{ $prixTotalLettre }} DHS).</strong>
    Soit un prix unitaire de
    <strong>{{ number_format($prixUnitaire, 0, ',', ' ') }} ({{ $prixUnitaireLettre }} DHS)</strong>
    <br />
    @if($prixParking > 0)
        * <strong>Pour le Parking</strong> au prix forfaitaire et définitif de
        <strong>{{ number_format($prixParking, 0, ',', ' ') }} ({{ $prixParkingLettre }} DHS)</strong>
        <br />
    @endif
    * Soit un prix global estimatif de la somme
    <strong>{{ number_format($totalPrice, 0, ',', ' ') }} ({{ $prixGlobalLettre }} DHS)</strong>
    <br />
    Sur lequel prix de vente, le réservataire a versé à titre d'acompte à valoir sur le prix de vente d'une valeur de
    <strong>{{ number_format($totalAvance, 0, ',', ' ') }} ({{ $avanceLettre }} DHS)</strong>.
</p>

<p class="blade-p-quitance">
    <u><strong>DONT QUITTANCE D'AUTANT</strong></u>
</p>

<p>
    Le reliquat soit la somme de
    <strong>{{ number_format($reliquat, 0, ',', ' ') }} ({{ $reliquatLettre }} DHS)</strong>
    sera réglée le jour de la réalisation de la vente définitive.
</p>

{{-- ═══════════ ARTICLE 4 : VARIATION DU PRIX ═══════════ --}}
<div class="blade-h1"><u>ARTICLE 4 : VARIATION DU PRIX PAR RAPPORT A LA SUPERFICIE DEFINITIVE DE L'APPARTEMENT :</u>:</div>
<p>
    Il est énoncé, que la surface de la propriété objet des présentes n'est fixée qu'approximativement, elle ne sera arrêtée définitivement qu'après achèvement des opérations topographiques dans leur globalité et établissement du titre foncier parcellaire.
    <br />
    La superficie définitive qui servira à la révision du prix de vente est celle objet du certificat de propriété. Et en cas de différence entre la superficie mentionnée sur le présent acte de réservation et la superficie mentionnée sur le certificat de propriété du bien objet des présentes, le prix de vente ci-dessus arrêté sera revu à la hausse ou à la baisse.
    <br />
    Etant précisé que le prix du parking est un prix forfaitaire non révisable, seule la différence en superficie de l'appartement avec balcon et buanderie et terrasse qui sera tenue en compte pour la révision du prix.
</p>

{{-- ═══════════ ARTICLE 5 : DESISTEMENT ═══════════ --}}
<div class="blade-h1"><u>ARTICLE 5 : DESISTEMENT</u>:</div>
<p>
    En cas de résolution du contrat de réservation comme en cas de désistement du réservataire, la Société Nord Afrique Immobilier aura droit à une indemnité équivalente à 40 000,00 dhs que le réservataire accepte et s'engage à régler.
    <br />
    Le montant de cette indemnité sera retenu sur la somme versée par le réservataire à titre d'apport personnel ou recouvré auprès de celui-ci. Le remboursement de l'avance déjà réglée diminuée de l'équivalent de 40 000,00 dhs, interviendra un mois à compter de la date de dépôt de la demande de désistement par Mr ou Madame
    @foreach($clients as $idx => $aq)
        @php $cl = $aq['client'] ?? []; @endphp
        <strong>{{ $cl['nom'] ?? '' }} {{ $cl['prenom'] ?? '' }}</strong>
        @if(!$loop->last) / @endif
    @endforeach
    réservataire.
</p>

{{-- ═══════════ ARTICLE 6 : REALISATION DU CONTRAT ═══════════ --}}
<div class="blade-h1"><u>ARTICLE 6 : REALISATION DU CONTRAT DE VENTE DEFINITIF</u>:</div>
<p>
    L'établissement du compromis de vente ou du contrat définitif de vente interviendra après l'obtention du permis d'habiter ainsi que le paiement du solde du prix de vente. Dès l'accomplissement de ces conditions le réservant avisera le réservataire par lettre ou par acte extra judiciaire, pour signer conjointement le compromis de vente ou le contrat définitif de vente dans un délai ne dépassant pas 30 jours à compter de la réception de la lettre précitée.
    <br />
    Passé ce délai, le présent acte de réservation deviendra caduc et non avenu sans qu'il soit besoin d'aucune mesure judiciaire ou extrajudiciaire de notification à l'une ou à l'autre des parties.
</p>

{{-- ═══════════ ARTICLE 7 : CHARGES ET CONDITIONS ═══════════ --}}
<div class="blade-h1"><u>ARTICLE 7 : CHARGES ET CONDITIONS DE LA VENTE DEFINITIVE</u>:</div>
<p>
    La vente du bien objet du présent contrat de réservation, si elle se réalise, aura lieu dans le cadre de la vente d'immeubles bâtis soumis au statut de la copropriété réglementé par la loi n°18-00 telle qu'elle a été modifiée et complétée par la loi 106-12 et à celles stipulées dans le présent contrat.
    <br />
    En cas de réalisation, la vente aura lieu sous les charges et conditions suivantes que le réservataire devra exécuter et accomplir, savoir :
    <br />
    - Il exécutera toutes les dispositions du règlement de copropriété qui régira le bien objet des présentes ;
    <br />
    - Il acquittera à compter du jour de la signature de la vente définitive tous les impôts et charges de toute nature afférents audit bien.
    <br />
    - Il payera tous les frais et droits des présentes et de leur suite.
    <br />
    - Il supportera, à compter de l'entrée en jouissance sa quote-part aux charges de copropriété.
</p>

{{-- ═══════════ ARTICLE 8 : ELECTION DE DOMICILE ═══════════ --}}
<div class="blade-h1"><u>ARTICLE 8 : ELECTION DE DOMICILE</u>:</div>
<p>
    Pour l'exécution des présentes et de leurs suites, les Parties font élection de domicile en leur siège et demeure respectif sus indiqué.
</p>

{{-- ═══════════ ARTICLE 9 : DROIT APPLICABLE ═══════════ --}}
<div class="blade-h1"><u>ARTICLE 9 : DROIT APPLICABLE ET LITIGES</u>:</div>
<p>
    Le présent contrat est soumis au droit marocain. Tout différend découlant de l'interprétation ou de l'exécution du présent contrat de vente ou en relation avec lui et qui n'aura pas été résolu de manière amiable, sera soumis à la compétence exclusive du tribunal compétent en la matière.
</p>

<br />

{{-- ═══════════ SIGNATURES ═══════════ --}}
<div class="signature-container">
    <p class="signature-date">&nbsp;Fait et passé à Casablanca,</p>
    <p class="signature-date-value">Le {{ $dateReservation }}</p>
    <br /><br />

    <div class="blade-h1"><u>LE RESERVANT</u></div>
    <p><strong>NORD AFRIQUE IMMOBILIER</strong><br />Es qualité</p>
    <div class="signature-line"></div>

    <div class="blade-h1"><u>LE RESERVATAIRE</u></div>
    <p>
        Monsieur ou Madame
        @if(count($clients) > 0)
            @foreach($clients as $idx => $aq)
                @php $cl = $aq['client'] ?? []; @endphp
                <strong>{{ $cl['nom'] ?? '' }} {{ $cl['prenom'] ?? '' }}</strong>
                @if(!$loop->last) / @endif
            @endforeach
        @else
            <strong>..................................</strong>
        @endif
    </p>
    <div class="signature-line"></div>
</div>

</body>
</html>
