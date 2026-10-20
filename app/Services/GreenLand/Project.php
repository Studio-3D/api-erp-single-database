<?php

declare(strict_types=1);

namespace App\Services\GreenLand;

/**
 * INFORMATIONS DU PROJET GREENLAND — le fichier à modifier pour mettre à jour ce que dit l'assistant.
 *
 * - Ne changez que les textes et les valeurs, jamais les clés (les mots avant « => »).
 * - Une valeur à null signifie « pas encore disponible » : l'assistant le dit honnêtement.
 * - Vous pouvez aussi surcharger n'importe quelle clé depuis config/greenland.php (clé 'project').
 * - Après une modification, lancez « php tests/run.php » pour vérifier que tout fonctionne.
 */
final class Project
{
    public const DATA = [
        'name' => 'GreenLand',
        'city' => 'Casablanca',

        // Prénom de la conseillère virtuelle (ex. 'Salma'). null = elle se présente sans prénom.
        'assistant_name' => null,

        'description' => 'GreenLand est une résidence fermée et sécurisée, dans un environnement calme et proche des commodités essentielles.',
        'location' => 'Sidi Messoud, entre Californie et la Ville Verte, à proximité immédiate de l’entrée de l’autoroute A3, à côté de la British International School.',
        'localisation' => 'Sidi Messoud, entre Californie et la Ville Verte, à proximité immédiate de l’entrée de l’autoroute A3, à côté de la British International School.',
        'adresse' => 'Sidi Messoud, entre Californie et la Ville Verte, à proximité immédiate de l’entrée de l’autoroute A3.',
        'delivery' => 'Mars 2027',
        'date_livraison' => 'Mars 2027',
        'etat' => 'Le projet est construit et entre dans ses dernières étapes de finition.',
        'details_bien' => 'Six immeubles en R+4, patio central, parking souterrain et ascenseurs OTIS.',
        'opening_hours' => '7j/7, de 10h à 18h',
        'horaires' => '7j/7, de 10h à 18h',
        'commercialisation' => 'Vente uniquement (pas de location).',
        'points_forts' => [
            'À côté de la British International School',
            'À proximité de l’entrée de l’autoroute A3',
        ],
        'features' => [
            'Six immeubles en R+4',
            'Un patio central paysager',
            'Deux terrains de padel',
            'Une salle de sport',
            'Un parking souterrain',
            'Des ascenseurs OTIS',
        ],
        'equipements' => [
            'Deux terrains de padel',
            'Une salle de sport',
            'Un patio paysager',
            'Parking souterrain',
            'Ascenseurs OTIS',
        ],
        'types' => [
            'F3' => '2 chambres, salon et 2 salles de bains — de 83 à 123 m²',
            'F4' => '3 chambres, salon et 2 salles de bains — de 97 à 130 m²',
        ],
        'typologies' => [
            'F3' => ['composition' => '2 chambres + salon + 2 salles de bains', 'surface' => '83 à 123 m²', 'chambres' => 2, 'surface_min' => 83, 'surface_max' => 123],
            'F4' => ['composition' => '3 chambres + salon + 2 salles de bains', 'surface' => '97 à 130 m²', 'chambres' => 3, 'surface_min' => 97, 'surface_max' => 130],
        ],
        'superficies' => 'Les appartements vont de 83 à 130 m².',

        // Ne jamais exposer de prix précis par appartement au prospect.
        'price_from_per_m2' => 14500,

        /*
         * FAQ — À FAIRE VALIDER PAR LE PROMOTEUR.
         * Tant qu'une réponse vaut null, l'assistant dit qu'il n'a pas encore l'information et
         * enregistre la question. Les réponses de la FAQ (config greenland.faq) s'ajoutent à celles-ci.
         */
        'faq' => [
            'acces' => ['question' => 'Comment accède-t-on au projet aujourd’hui ?', 'reponse' => null],
            'ecoles' => ['question' => 'Y a-t-il des écoles à proximité ?', 'reponse' => 'Le projet se trouve à côté de la British International School.'],
            'titre_foncier' => ['question' => 'Le projet dispose-t-il d’un titre foncier ?', 'reponse' => null],
            'syndic' => ['question' => 'Quels sont les frais de syndic ?', 'reponse' => null],
            'parking' => ['question' => 'La place de parking est-elle comprise dans le prix ?', 'reponse' => null],
            'finitions' => ['question' => 'Quelles sont les finitions (matériaux, marques) ?', 'reponse' => null],
            'mre' => ['question' => 'Peut-on acheter depuis l’étranger (MRE) ?', 'reponse' => null],
            'frais' => ['question' => 'Quels frais s’ajoutent au prix (notaire, enregistrement) ?', 'reponse' => null],
        ],

        /* Tranches de budget proposées en liste WhatsApp (valeur = montant retenu, en DH). Titres : 24 caractères maximum. */
        'budget_ranges' => [
            'lt_1_2' => ['value' => 1_000_000, 'fr' => 'Moins de 1,2 M DH', 'darija' => 'Ta7t 120 mlyoun', 'ar' => 'أقل من 1,2 مليون درهم', 'en' => 'Under 1.2M MAD'],
            '1_2_1_5' => ['value' => 1_350_000, 'fr' => '1,2 à 1,5 M DH', 'darija' => '120 - 150 mlyoun', 'ar' => 'من 1,2 إلى 1,5 مليون', 'en' => '1.2 to 1.5M MAD'],
            '1_5_1_8' => ['value' => 1_650_000, 'fr' => '1,5 à 1,8 M DH', 'darija' => '150 - 180 mlyoun', 'ar' => 'من 1,5 إلى 1,8 مليون', 'en' => '1.5 to 1.8M MAD'],
            '1_8_2_2' => ['value' => 2_000_000, 'fr' => '1,8 à 2,2 M DH', 'darija' => '180 - 220 mlyoun', 'ar' => 'من 1,8 إلى 2,2 مليون', 'en' => '1.8 to 2.2M MAD'],
            'gt_2_2' => ['value' => 2_400_000, 'fr' => 'Plus de 2,2 M DH', 'darija' => 'Ktar men 220 mlyoun', 'ar' => 'أكثر من 2,2 مليون درهم', 'en' => 'Over 2.2M MAD'],
            'unknown' => ['value' => null, 'fr' => 'Je ne sais pas encore', 'darija' => 'Mazal ma 3reftch', 'ar' => 'لم أحدد بعد', 'en' => 'Not sure yet'],
        ],

        /* Textes du projet dans les quatre langues (réponses de secours, boutons, messages du code). */
        'i18n' => [
            'fr' => [
                'description' => 'GreenLand est une résidence fermée et sécurisée de six immeubles en R+4, avec un patio central paysager, un parking souterrain, des ascenseurs OTIS, deux terrains de padel et une salle de sport.',
                'location' => 'GreenLand se situe à Sidi Messoud, entre Californie et la Ville Verte, près de l’entrée de l’autoroute A3 et à côté de la British International School.',
                'etat' => 'Le projet est construit et entre dans ses dernières étapes de finition.',
                'livraison' => 'mars 2027',
                'horaires' => '7j/7, de 10h à 18h',
                'equipements' => 'deux terrains de padel, une salle de sport, un patio paysager, un parking souterrain et des ascenseurs OTIS',
                'commercialisation' => 'Les appartements de GreenLand sont proposés à la vente uniquement, pas à la location.',
                'typologies' => [
                    'F3' => '2 chambres, un salon et 2 salles de bains, de 83 à 123 m²',
                    'F4' => '3 chambres, un salon et 2 salles de bains, de 97 à 130 m²',
                ],
            ],
            'darija' => [
                'description' => 'GreenLand résidence msdouda w m2emmna, fiha 6 dyal les immeubles R+4, patio f lwost, parking ta7t l2ard, ascenseurs OTIS, jouj terrains dyal padel w salle de sport.',
                'location' => 'GreenLand kayna f Sidi Messoud, bin Californie w la Ville Verte, 9rib men l’entrée dyal l’autoroute A3 w 7da British International School.',
                'etat' => 'Lmachrou3 mbni w rah f akhir les étapes dyal les finitions.',
                'livraison' => 'mars 2027',
                'horaires' => '7/7, men 10h tal 18h',
                'equipements' => 'jouj terrains dyal padel, salle de sport, patio, parking ta7t l2ard w ascenseurs OTIS',
                'commercialisation' => 'Les appartements dyal GreenLand kaytbaa3o ghir l bi3, machi l kra.',
                'typologies' => [
                    'F3' => '2 byout n3as, salon w 2 salles de bains, men 83 tal 123 m²',
                    'F4' => '3 byout n3as, salon w 2 salles de bains, men 97 tal 130 m²',
                ],
            ],
            'ar' => [
                'description' => 'GreenLand إقامة مغلقة ومؤمنة تضم ست عمارات من صنف R+4، مع فناء مركزي مشجر وموقف سيارات تحت أرضي ومصاعد OTIS وملعبين للبادل وقاعة رياضة.',
                'location' => 'يقع GreenLand في سيدي مسعود، بين كاليفورنيا والمدينة الخضراء، قرب مدخل الطريق السيار A3 وبجوار British International School.',
                'etat' => 'المشروع مبني ويدخل آخر مراحل التشطيب.',
                'livraison' => 'مارس 2027',
                'horaires' => 'طيلة أيام الأسبوع، من 10h إلى 18h',
                'equipements' => 'ملعبين للبادل وقاعة رياضة وفناء مشجر وموقف سيارات تحت أرضي ومصاعد OTIS',
                'commercialisation' => 'شقق GreenLand معروضة للبيع فقط، وليس للكراء.',
                'typologies' => [
                    'F3' => 'غرفتي نوم وصالونا وحمامين، بمساحة من 83 إلى 123 م²',
                    'F4' => '3 غرف نوم وصالونا وحمامين، بمساحة من 97 إلى 130 م²',
                ],
            ],
            'en' => [
                'description' => 'GreenLand is a gated, secure residence of six R+4 buildings, with a landscaped central patio, underground parking, OTIS lifts, two padel courts and a gym.',
                'location' => 'GreenLand is in Sidi Messoud, between Californie and the Ville Verte, near the A3 motorway entrance and next to the British International School.',
                'etat' => 'The project is built and in its final finishing stages.',
                'livraison' => 'March 2027',
                'horaires' => 'every day, from 10:00 to 18:00',
                'equipements' => 'two padel courts, a gym, a landscaped patio, underground parking and OTIS lifts',
                'commercialisation' => 'GreenLand apartments are for sale only, not for rent.',
                'typologies' => [
                    'F3' => '2 bedrooms, a living room and 2 bathrooms, from 83 to 123 m²',
                    'F4' => '3 bedrooms, a living room and 2 bathrooms, from 97 to 130 m²',
                ],
            ],
        ],

        'resources' => [
            'maps_url' => 'https://www.google.com/maps/search/G94G%2BPC%2C%20Casablanca%2C%20Maroc/@33.50707888208982,-7.623671181499958,17z?hl=fr',
            'latitude' => 33.50707888208982,
            'longitude' => -7.623671181499958,
            'location_label' => 'GreenLand – Sidi Messoud',
            'location_address' => 'Sidi Messoud, Casablanca',
            'virtual_tour_url' => 'https://my.matterport.com/show/?m=8upgYS8NGex',

            // À renseigner : URL https d'une vidéo .mp4 du projet (par exemple une vidéo de la pub Meta).
            'video_url' => null,

            // À renseigner : URL https de la brochure PDF du projet.
            'brochure_url' => null,
            'brochure_filename' => 'GreenLand-brochure.pdf',

            // Plans 3D par typologie : envoyés avec la description de la typologie (null = pas encore disponible).
            'floor_plan_urls' => [
                'F3' => 'https://vrstudio3d.com/greenland/media/f3.jpeg',
                'F4' => 'https://vrstudio3d.com/greenland/media/f4.jpeg',
            ],
            'photo_urls' => [
                'https://vrstudio3d.com/greenland/media/1.jpeg',
                'https://vrstudio3d.com/greenland/media/2.jpeg',
                'https://vrstudio3d.com/greenland/media/3.jpeg',
                'https://vrstudio3d.com/greenland/media/4.jpeg',
                'https://vrstudio3d.com/greenland/media/5.jpeg',
                'https://vrstudio3d.com/greenland/media/6.jpeg',
                'https://vrstudio3d.com/greenland/media/7.jpeg',
                'https://vrstudio3d.com/greenland/media/8.jpeg',
            ],
        ],
    ];
}
