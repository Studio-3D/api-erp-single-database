<?php

namespace App\Services;

use App\Models\Bien;
use App\Models\Projet;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AgentFinalService
{
    private $apiKey;
    private $model = 'gpt-4o-mini';
    private $excelData = [];
    private $responses = [];
    private $conversationState = [];
    private $lang = 'fr';

    private $typesDisponibles = ['F3', 'F4'];
    private $typesIndisponibles = ['villa', 'villas', 'dar', 'maison', 'studio', 'duplex', 'f2', 'magasin', 'local'];
    private $villesDisponibles = ['casablanca', 'casa'];
    private $villesIndisponibles = [
        'meknes', 'meknès', 'errachidia', 'tanger', 'tétouan', 'oujda', 'nador',
        'fès', 'fes', 'marrakech', 'rabat', 'kenitra', 'settat', 'khouribga',
        'beni mellal', 'agadir', 'essaouira', 'safi', 'el jadida', 'mohammedia',
        'temara', 'salé', 'skhirat', 'bouznika', 'berrechid', 'benslimane',
        'khemisset', 'tiflet', 'sidi kacem', 'sidi slimane'
    ];

    public function __construct()
    {
        $this->apiKey = env('OPENROUTER_API_KEY');
        $this->loadExcelData();
        $this->loadResponses();
    }

    private function loadExcelData()
    {
        $this->excelData = [
            'description' => 'GreenLand est un groupe résidentiel fermé et sécurisé.',
            'localisation' => 'SIDI MESSOUD, Casablanca, entre Californie et la ville verte',
            'ville' => 'Casablanca',
            'contact_agents' => 'Mr Oussama 212660446758 - Mr Maghraoui 212660446758',
            'date_livraison' => 'mars 2027',
            'details_bien' => 'Six immeubles en R+4, patio central, parking souterrain, ascenseur OTIS.',
            'equipements' => 'Deux terrains de Padel - Salle de sport - Patio Paysager',
            'superficies' => 'Appartements de 83 à 130 m².',
            'typologie' => "F3 : 2 Chambres + Salon + 2 salles de bains de 83 m² à 123 m²\nF4 : 3 Chambres + Salon + 2 salles de bains de 97 m² à 130 m²",
            'prix' => '14 000 à 16 500 DH/m².',
            'horaires' => '7j/7, de 10h à 18h',
            'balcon' => 'Certains appartements disposent de balcons.',
            'vue' => 'Les appartements offrent une vue sur le patio paysager et les espaces verts du projet.',
        ];
    }

    private function loadResponses()
    {
        $this->responses = [
            // ========== SALUTATIONS ==========
            'salutation_fr' => "Bonjour ! Bienvenue chez TRACIMO ! 😊\n\nSuper ! Pour vous trouver le bien idéal, quelle ville vous intéresse et quel est votre budget approximatif ?",
            'salutation_en' => "Hello ! Welcome to TRACIMO ! 😊\n\nGreat ! To find your ideal property, which city are you interested in and what is your approximate budget ?",
            'salutation_darija' => "Salamo ! Bienvenue chez TRACIMO ! 😊\n\nSuper ! Pour vous trouver le bien idéal, quelle ville vous intéresse et quel est votre budget approximatif ?",

            // ========== VILLES NON DISPONIBLES ==========
            'ville_non_disponible_fr' => "Nous n'avons pas de projets à :ville pour le moment. Notre projet GreenLand est situé à Casablanca (SIDI MESSOUD). 😊\n\nSouhaitez-vous des informations sur le projet GreenLand à Casablanca ?",
            'ville_non_disponible_en' => "We don't have any projects in :ville at the moment. Our GreenLand project is located in Casablanca (SIDI MESSOUD). 😊\n\nWould you like information about the GreenLand project in Casablanca ?",
            'ville_non_disponible_darija' => "Ma3ndna walou projets f :ville. Projet GreenLand kayn ghir f Casablanca (SIDI MESSOUD). 😊\n\nWash tbaghi t3ref 3la projet GreenLand f Casablanca ?",

            // ========== VUE ==========
            'vue_fr' => 'Pour la vue, elle donne sur le patio paysager et les espaces verts 🌿. Si vous êtes intéressé, je peux vous proposer une visite 😊.',
            'vue_en' => 'The view overlooks the landscaped patio and green spaces 🌿. If you are interested, I can offer you a visit 😊.',
            'vue_darija' => 'La vue t3ti 3la patio paysager w les espaces verts 🌿. Ila kenti interessé, n9der n3tiwk visite 😊.',

            // ========== PRIX ==========
            'prix_default_fr' => 'Le prix au m² est entre 14 000 et 16 500 DH/m². 😊',
            'prix_default_en' => 'The price per m² is between 14,000 and 16,500 DH/m². 😊',
            'prix_default_darija' => 'Taman f lmètre m² mabin 14 000 u 16 500 DH/m². 😊',

            'prix_f3_fr' => 'F3 : 83 à 123 m² → à partir de 14 000 DH/m². 😊',
            'prix_f3_en' => 'F3 : 83 to 123 m² → starting from 14,000 DH/m². 😊',
            'prix_f3_darija' => 'F3 : 83 à 123 m² → mn 14 000 DH/m². 😊',

            'prix_f4_fr' => 'F4 : 97 à 130 m² → à partir de 14 000 DH/m². 😊',
            'prix_f4_en' => 'F4 : 97 to 130 m² → starting from 14,000 DH/m². 😊',
            'prix_f4_darija' => 'F4 : 97 à 130 m² → mn 14 000 DH/m². 😊',

            // ========== PROJET ==========
            'projet_description_fr' => "Le projet GreenLand à Casablanca est exclusivement dédié aux appartements. Nous avons de superbes typologies F3 et F4 à vous proposer.\n\nLes appartements F3 disposent de 2 chambres et vont de 83 à 123 m², tandis que les F4 offrent 3 chambres, avec des superficies allant de 97 à 130 m².\n\nSi cela vous intéresse, je serais ravi de vous organiser une visite pour découvrir ces magnifiques appartements. Qu'en pensez-vous ?",
            'projet_description_en' => "The GreenLand project in Casablanca is exclusively dedicated to apartments. We have wonderful F3 and F4 typologies to offer you.\n\nF3 apartments have 2 bedrooms and range from 83 to 123 m², while F4 apartments offer 3 bedrooms, with areas ranging from 97 to 130 m².\n\nIf you are interested, I would be happy to arrange a visit for you to discover these magnificent apartments. What do you think ?",
            'projet_description_darija' => "Projet GreenLand f Casablanca hiya projet d'appartements. 3ndna typologies F3 u F4.\n\nF3 : 2 chambres u 83 à 123 m².\nF4 : 3 chambres u 97 à 130 m².\n\nIla kenti interessé, n9der n3tiwk visite bach tchof les appartements. Wash tbaghi ?",

            // ========== VISITE ==========
            'visite_fr' => "Parfait ! Je vais organiser une visite pour vous au projet GreenLand à Casablanca. 🏠\n\nDonnez-moi votre nom et une date qui vous convient, je vous réserve la visite tout de suite ! 😊",
            'visite_en' => "Perfect ! I will arrange a visit for you to the GreenLand project in Casablanca. 🏠\n\nGive me your name and a date that works for you, I will book the visit right away ! 😊",
            'visite_darija' => "Mzyan ! N9der n3tiwk visite f projet GreenLand f Casablanca. 🏠\n\n3tini smiytek u tari li tbaghi, n7addo lk visite ! 😊",

            // ========== VISITE AVEC NOM ==========
            'visite_nom_fr' => "Parfait ! Je vais réserver la visite pour :nom le :date. 🏠\n\nUn conseiller vous contactera pour confirmer les détails.\n\nÀ bientôt ! 😊",
            'visite_nom_en' => "Perfect ! I will book the visit for :nom on :date. 🏠\n\nAn advisor will contact you to confirm the details.\n\nSee you soon ! 😊",
            'visite_nom_darija' => "Mzyan ! N7addo lk visite f :date 3la smiytek :nom. 🏠\n\nWa7d mn l'équipe ghadi ycontactik bach yconfirmi les détails.\n\nNta li fih l'command ! 😊",

            // ========== BALCON ==========
            'balcon_fr' => 'Certains appartements du projet GreenLand disposent de balcons. 😊',
            'balcon_en' => 'Some apartments in the GreenLand project have balconies. 😊',
            'balcon_darija' => 'Ba9i des appartements f projet GreenLand 3ndhom balcons. 😊',

            // ========== PROXIMITÉS ==========
            'proximite_fr' => 'GreenLand est bien situé, proche des commodités. 😊',
            'proximite_en' => 'GreenLand is well located, close to amenities. 😊',
            'proximite_darija' => 'GreenLand mzyan, qrib l les commodités. 😊',

            // ========== DISPONIBILITÉ ==========
            'disponibilite_fr' => 'Oui, il reste des appartements disponibles dans le projet GreenLand. Pour les disponibilités exactes, je vous mets en contact avec notre équipe. 😊',
            'disponibilite_en' => 'Yes, there are still apartments available in the GreenLand project. For exact availabilities, I will put you in touch with our team. 😊',
            'disponibilite_darija' => 'Ah, ba9i des appartements disponibles f projet GreenLand. Pour les disponibilités exactes, n3tiwk contact m3a l\'équipe. 😊',

            // ========== TYPES INDISPONIBLES ==========
            'type_indisponible_fr' => 'GreenLand est un projet d\'appartements. Pas de :type. Typologies : F3 et F4. 😊',
            'type_indisponible_en' => 'GreenLand is an apartment project. No :type. Typologies : F3 and F4. 😊',
            'type_indisponible_darija' => 'GreenLand hiya projet d\'appartements. Ma3ndnach :type. Typologies : F3 u F4. 😊',

            // ========== IDENTITÉ ==========
            'identite_fr' => 'Je suis Karim, conseiller immobilier chez TRACIMO. Je suis là pour vous aider à trouver le bien idéal. 😊',
            'identite_en' => 'I am Karim, a real estate advisor at TRACIMO. I am here to help you find your ideal property. 😊',
            'identite_darija' => 'Ana Karim, conseiller immobilier f TRACIMO. Ana hna bach n3awnek tchof l\'bien idéal. 😊',
        ];
    }

    /**
 * 🔥 VÉRIFIER SI LE CLIENT DEMANDE UN TYPE SPÉCIFIQUE (F3, F4)
 */
private function checkSpecificType($message)
{
    $msg = strtolower(trim($message));

    // Nettoyer le message pour ne garder que F3 ou F4
    if (strpos($msg, 'f3') !== false || strpos($msg, 'f3') === 0) {
        return 'f3';
    }
    if (strpos($msg, 'f4') !== false || strpos($msg, 'f4') === 0) {
        return 'f4';
    }
    // "F3" avec espace ou ponctuation
    if (preg_match('/\bf3\b/', $msg)) {
        return 'f3';
    }
    if (preg_match('/\bf4\b/', $msg)) {
        return 'f4';
    }

    return null;
}

/**
 * 🔥 RÉPONSE POUR UN TYPE SPÉCIFIQUE
 */
private function getTypeResponse($type, $lang)
{
    if ($type === 'f3') {
        if ($lang === 'fr') {
            return "Parfait ! Le F3 est un excellent choix. 🏠\n\n" .
                   "📐 Superficie : 83 à 123 m²\n" .
                   "🛏️ 2 chambres + Salon + 2 salles de bains\n" .
                   "💰 Prix au m² : 14 000 à 16 500 DH/m²\n" .
                   "📍 Projet GreenLand - Casablanca (SIDI MESSOUD)\n\n" .
                   "Souhaitez-vous organiser une visite pour découvrir le F3 ? 😊";
        } elseif ($lang === 'en') {
            return "Perfect ! The F3 is an excellent choice. 🏠\n\n" .
                   "📐 Area : 83 to 123 m²\n" .
                   "🛏️ 2 bedrooms + Living room + 2 bathrooms\n" .
                   "💰 Price per m² : 14,000 to 16,500 DH/m²\n" .
                   "📍 GreenLand project - Casablanca (SIDI MESSOUD)\n\n" .
                   "Would you like to arrange a visit to discover the F3 ? 😊";
        } else {
            return "Mzyan ! F3 choix mzyan. 🏠\n\n" .
                   "📐 Superficie : 83 à 123 m²\n" .
                   "🛏️ 2 chambres + Salon + 2 salles de bains\n" .
                   "💰 Taman f lmètre m² : 14 000 à 16 500 DH/m²\n" .
                   "📍 Projet GreenLand - Casablanca (SIDI MESSOUD)\n\n" .
                   "Wash tbaghi tzour l'F3 ? 😊";
        }
    }

    if ($type === 'f4') {
        if ($lang === 'fr') {
            return "Parfait ! Le F4 est un excellent choix. 🏠\n\n" .
                   "📐 Superficie : 97 à 130 m²\n" .
                   "🛏️ 3 chambres + Salon + 2 salles de bains\n" .
                   "💰 Prix au m² : 14 000 à 16 500 DH/m²\n" .
                   "📍 Projet GreenLand - Casablanca (SIDI MESSOUD)\n\n" .
                   "Souhaitez-vous organiser une visite pour découvrir le F4 ? 😊";
        } elseif ($lang === 'en') {
            return "Perfect ! The F4 is an excellent choice. 🏠\n\n" .
                   "📐 Area : 97 to 130 m²\n" .
                   "🛏️ 3 bedrooms + Living room + 2 bathrooms\n" .
                   "💰 Price per m² : 14,000 to 16,500 DH/m²\n" .
                   "📍 GreenLand project - Casablanca (SIDI MESSOUD)\n\n" .
                   "Would you like to arrange a visit to discover the F4 ? 😊";
        } else {
            return "Mzyan ! F4 choix mzyan. 🏠\n\n" .
                   "📐 Superficie : 97 à 130 m²\n" .
                   "🛏️ 3 chambres + Salon + 2 salles de bains\n" .
                   "💰 Taman f lmètre m² : 14 000 à 16 500 DH/m²\n" .
                   "📍 Projet GreenLand - Casablanca (SIDI MESSOUD)\n\n" .
                   "Wash tbaghi tzour l'F4 ? 😊";
        }
    }

    return null;
}

    /**
     * 🔥 DÉTECTER LA LANGUE (AVEC MÉMOIRE)
     */
    private function detectLanguage($message, $sessionId)
    {
        $message = strtolower(trim($message));

        // 🔥 Si le message est très court, garder la langue précédente
        if (strlen($message) < 4) {
            // Vérifier si on a une langue stockée pour cette session
            if (isset($this->conversationState[$sessionId]['lang'])) {
                return $this->conversationState[$sessionId]['lang'];
            }
            return $this->lang;
        }

        $frKeywords = ['bonjour', 'salut', 'merci', 'comment', 'vous', 'je', 'tu', 'oui', 'non', 'd\'accord', 'parfait', 'combien', 'prix', 'appartement', 'projet', 'visite', 'moi', 'toi', 'français', 'francais', 'budget', 'villa', 'maison', 'appart', 'chambre', 'superficie', 'surface', 'étage', 'etage', 'terrasse', 'parking', 'livraison', 'construction', 'promoteur', 'demain', 'date', 'nom', 'semaine', 'mois', 'année'];
        $enKeywords = ['hello', 'hi', 'thank', 'thanks', 'you', 'i', 'yes', 'no', 'ok', 'okay', 'price', 'apartment', 'project', 'visit', 'good', 'great', 'help', 'me', 'my', 'english', 'budget', 'villa', 'bedroom', 'floor', 'terrace', 'parking', 'delivery', 'tomorrow', 'date', 'name', 'week', 'month', 'year'];
        $darijaKeywords = ['salam', 'slm', 'labas', 'kifash', 'nta', 'nti', 'ach', 'ash', 'wash', 'kayn', 'mzyan', '3la', 'chhal', 'taman', 'bien', 'dar', 'chri', 'baghi', 'tbaghi', 'nchri', 'nzour', 'safi', 'hna', 'bzf', 'chwiya', 'mchit', 'jiti', 'bghit', 'nchof', 'tchof', 'n9der', 't9der', 'ghda', 'lghda', 'smia', 'tari'];

        $frScore = 0;
        $enScore = 0;
        $darijaScore = 0;

        foreach ($frKeywords as $word) {
            if (strpos($message, $word) !== false) $frScore++;
        }
        foreach ($enKeywords as $word) {
            if (strpos($message, $word) !== false) $enScore++;
        }
        foreach ($darijaKeywords as $word) {
            if (strpos($message, $word) !== false) $darijaScore++;
        }

        // 🔥 Détection claire
        if (strpos($message, 'bonjour') !== false || strpos($message, 'salut') !== false) {
            $this->conversationState[$sessionId]['lang'] = 'fr';
            return 'fr';
        }
        if (strpos($message, 'hello') !== false || strpos($message, 'hi') !== false) {
            $this->conversationState[$sessionId]['lang'] = 'en';
            return 'en';
        }
        if (strpos($message, 'salam') !== false || strpos($message, 'slm') !== false) {
            $this->conversationState[$sessionId]['lang'] = 'darija';
            return 'darija';
        }

        // 🔥 Mots spécifiques
        if (strpos($message, 'safi') !== false || strpos($message, 'hna') !== false || strpos($message, 'bzf') !== false) {
            $this->conversationState[$sessionId]['lang'] = 'darija';
            return 'darija';
        }
        if (strpos($message, 'nta') !== false || strpos($message, 'nti') !== false) {
            $this->conversationState[$sessionId]['lang'] = 'darija';
            return 'darija';
        }

        // 🔥 Mots français spécifiques
        if (strpos($message, 'budget') !== false || strpos($message, 'villa') !== false) {
            $this->conversationState[$sessionId]['lang'] = 'fr';
            return 'fr';
        }
        if (strpos($message, 'demain') !== false || strpos($message, 'date') !== false || strpos($message, 'nom') !== false) {
            $this->conversationState[$sessionId]['lang'] = 'fr';
            return 'fr';
        }

        // 🔥 Comparer les scores
        if ($frScore >= $enScore && $frScore >= $darijaScore) {
            $this->conversationState[$sessionId]['lang'] = 'fr';
            return 'fr';
        }
        if ($enScore >= $frScore && $enScore >= $darijaScore) {
            $this->conversationState[$sessionId]['lang'] = 'en';
            return 'en';
        }
        if ($darijaScore >= $frScore && $darijaScore >= $enScore) {
            $this->conversationState[$sessionId]['lang'] = 'darija';
            return 'darija';
        }

        // 🔥 Garder la langue précédente de la session
        if (isset($this->conversationState[$sessionId]['lang'])) {
            return $this->conversationState[$sessionId]['lang'];
        }

        return $this->lang;
    }

    private function getResponse($key, $default = null, $replace = null, $replace2 = null)
    {
        if (!isset($this->lang) || $this->lang === null) {
            $this->lang = 'fr';
        }

        $langKey = $key . '_' . $this->lang;

        if (isset($this->responses[$langKey])) {
            $response = $this->responses[$langKey];
            if ($replace) {
                $response = str_replace(':ville', $replace, $response);
            }
            if ($replace2) {
                $response = str_replace(':nom', $replace2['nom'] ?? '', $response);
                $response = str_replace(':date', $replace2['date'] ?? '', $response);
            }
            return $response;
        }

        $fallbackKey = $key . '_fr';
        if (isset($this->responses[$fallbackKey])) {
            return $this->responses[$fallbackKey];
        }

        return $default ?? "Je suis Karim, votre conseiller immobilier. Comment puis-je vous aider ? 😊";
    }
/**
 * 🔥 VÉRIFIER SI LE CLIENT ACCEPTE (d'accord, oui, ok, etc.)
 */
private function isAcceptance($message)
{
    $msg = strtolower(trim($message));
    $acceptWords = ['daccord', 'd\'accord', 'accord', 'ok', 'oui', 'yes', 'yep', 'mzyan', 'tayeb', 'safi', 'inchalah'];

    foreach ($acceptWords as $word) {
        if (strpos($msg, $word) !== false) {
            return true;
        }
    }
    return false;
}

/**
 * 🔥 VÉRIFIER SI LE CLIENT ACCEPTE (d'accord, oui, ok, etc.)
 */


/**
 * 🔥 VÉRIFIER SI LE CLIENT DONNE SON NOM ET UNE DATE
 */
private function hasNameAndDate($message)
{
    $hasName = preg_match('/[a-zA-Z]{3,}/', $message);
    $hasDate = preg_match('/demain|aujourd\'hui|ce soir|ce week-end|lundi|mardi|mercredi|jeudi|vendredi|samedi|dimanche|(\d{1,2}\/\d{1,2})/', $message);
    return $hasName && $hasDate;
}

/**
 * 🔥 RÉPONSE QUAND LE CLIENT ACCEPTE LA VISITE
 */
private function getAcceptanceResponse($lang)
{
    if ($lang === 'fr') {
        return "Super ! Je suis ravi que vous soyez intéressé par le F4. 🏠\n\n" .
               "Pour réserver votre visite au projet GreenLand à Casablanca, je vais avoir besoin de :\n" .
               "• Votre nom complet\n" .
               "• La date qui vous convient (ex: demain, lundi, 15/08)\n\n" .
               "Donnez-moi ces informations et je vous réserve la visite tout de suite ! 😊";
    } elseif ($lang === 'en') {
        return "Great ! I'm glad you're interested in the F4. 🏠\n\n" .
               "To book your visit to the GreenLand project in Casablanca, I need :\n" .
               "• Your full name\n" .
               "• The date that works for you (e.g., tomorrow, Monday, 15/08)\n\n" .
               "Give me this information and I will book the visit right away ! 😊";
    } else {
        return "Mzyan ! Ana farhan belli kenti interessé f F4. 🏠\n\n" .
               "Bach n7addo lk visite f projet GreenLand f Casablanca, khsni :\n" .
               "• Smiytek kamla\n" .
               "• Tari li tbaghi (matalan: lghda, l'tnin, 15/08)\n\n" .
               "3tini had l'm9adate u n7addo lk visite tout de suite ! 😊";
    }
}

/**
 * 🔥 STOCKER LE TYPE CHOISI PAR LE CLIENT
 */
private function storeType($type, $sessionId)
{
    $this->conversationState[$sessionId]['last_type'] = $type;
}
/**
 * 🔥 RÉPONSE QUAND LE CLIENT ACCEPTE LA VISITE
 */

    public function processMessage($message, $sessionId)
    {
        try {
            $cleanMessage = $this->cleanMessage($message);

            // 🔥 Détecter la langue avec la session
            $this->lang = $this->detectLanguage($cleanMessage, $sessionId);

            // Vérifier la ville
            $cityCheck = $this->checkCityAvailability($cleanMessage);
            if ($cityCheck) {
                return $cityCheck;
            }
            // 🔥 ÉTAPE: VÉRIFIER SI LE CLIENT DEMANDE UN TYPE SPÉCIFIQUE (F3 ou F4)
            $type = $this->checkSpecificType($cleanMessage);
            if ($type) {
                return $this->getTypeResponse($type, $this->lang);
            }
            // 🔥 ÉTAPE: VÉRIFIER SI LE CLIENT ACCEPTE LA VISITE
            if ($this->isAcceptance($cleanMessage)) {
                // Vérifier si on a déjà parlé du type (F3/F4)
                $hasType = isset($this->conversationState[$sessionId]['last_type']);
                if ($hasType) {
                    return $this->getAcceptanceResponse($this->lang);
                }
                // Sinon, redemander le type
                if ($this->lang === 'fr') {
                    return "Super ! Pour vous proposer la meilleure visite, quel type d'appartement vous intéresse (F3 ou F4) ? 😊";
                } elseif ($this->lang === 'en') {
                    return "Great ! To offer you the best visit, what type of apartment are you interested in (F3 or F4) ? 😊";
                } else {
                    return "Mzyan ! Bach n3tiwk l'visite l'7san, chno type d'appartement li tbaghi (F3 wla F4) ? 😊";
                }
            }

            // Salutation
            if ($this->isSalutation($cleanMessage)) {
                return $this->getResponse('salutation');
            }

            // 🔥 Vérifier si le client donne son nom et une date pour la visite
            if ($this->hasNameAndDate($cleanMessage)) {
                $name = $this->extractName($message);
                $date = $this->extractDate($message);
                return $this->getResponse('visite_nom', null, null, ['nom' => $name, 'date' => $date]);
            }

            // Ville donnée par le client
            if ($this->hasGivenCity($cleanMessage, $sessionId)) {
                $city = $this->storeCity($cleanMessage, $sessionId);
                if ($city && !in_array($city, $this->villesDisponibles)) {
                    $villeNom = ucfirst($city);
                    return $this->getResponse('ville_non_disponible', null, $villeNom);
                }
                $cityDisplay = ucfirst($city);
                if ($this->lang === 'fr') {
                    return "Parfait, " . $cityDisplay . " c'est un excellent choix !\n\nQuel est votre budget et vous cherchez quel type de bien (F2, F3, F4) ? 😊";
                } elseif ($this->lang === 'en') {
                    return "Perfect, " . $cityDisplay . " is an excellent choice !\n\nWhat is your budget and what type of property are you looking for (F2, F3, F4) ? 😊";
                } else {
                    return "Mzyan ! " . $cityDisplay . " choix mzyan !\n\nChhal l'budget dyalk u ach type de bien (F2, F3, F4) ? 😊";
                }
            }

            // Budget
            if ($this->hasGivenBudget($cleanMessage)) {
                $this->storeBudget($cleanMessage, $sessionId);
                return $this->getResponse('projet_description');
            }

            // Vue
            $vueResponse = $this->checkVueQuestion($cleanMessage);
            if ($vueResponse) {
                return $vueResponse;
            }

            // Types indisponibles
            $typeResponse = $this->checkUnavailableTypes($cleanMessage);
            if ($typeResponse) {
                return $typeResponse;
            }

            // Infos projet
            if (strpos($cleanMessage, 'greenland') !== false || strpos($cleanMessage, 'projet') !== false || strpos($cleanMessage, 'project') !== false) {
                return $this->getResponse('projet_description');
            }

            // Prix
            $priceResponse = $this->checkPriceQuestion($cleanMessage);
            if ($priceResponse) {
                return $priceResponse;
            }

            // Balcons
            $balconResponse = $this->checkBalconQuestion($cleanMessage);
            if ($balconResponse) {
                return $balconResponse;
            }

            // Proximités
            $proximiteResponse = $this->checkProximiteQuestion($cleanMessage);
            if ($proximiteResponse) {
                return $proximiteResponse;
            }

            // Identité
            if (strpos($cleanMessage, 'commercial') !== false || strpos($cleanMessage, 'nta') !== false || strpos($cleanMessage, 'you') !== false) {
                return $this->getResponse('identite');
            }

            // Disponibilité
            if (strpos($cleanMessage, 'disponible') !== false || strpos($cleanMessage, 'kayn') !== false || strpos($cleanMessage, 'available') !== false) {
                return $this->getResponse('disponibilite');
            }

            // Visite
            if (strpos($cleanMessage, 'visite') !== false || strpos($cleanMessage, 'visit') !== false || strpos($cleanMessage, 'nzour') !== false || strpos($cleanMessage, 'nchof') !== false) {
                return $this->getResponse('visite');
            }

            // IA
            $prompt = $this->buildPrompt($message);
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => 'http://localhost:8000',
                'X-Title' => 'Agent Immobilier',
            ])->timeout(30)->post('https://openrouter.ai/api/v1/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $this->getSystemPrompt()],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.7,
                'max_tokens' => 500
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $content = $data['choices'][0]['message']['content'] ?? null;
                if (!empty($content)) {
                    return $this->cleanResponse($content);
                }
            }

            return $this->getFallbackResponse($cleanMessage);

        } catch (\Exception $e) {
            Log::error('Erreur: ' . $e->getMessage());
            return $this->getResponse('identite');
        }
    }

    /**
     * 🔥 VÉRIFIER SI LE CLIENT DONNE SON NOM ET UNE DATE
     */


    private function extractName($message)
    {
        preg_match('/[a-zA-Z]{3,}/', $message, $matches);
        return $matches[0] ?? 'Client';
    }

    private function extractDate($message)
    {
        if (strpos($message, 'demain') !== false) return 'demain';
        if (strpos($message, 'aujourd\'hui') !== false) return 'aujourd\'hui';
        if (strpos($message, 'ce soir') !== false) return 'ce soir';
        if (strpos($message, 'ce week-end') !== false) return 'ce week-end';
        preg_match('/(\d{1,2}\/\d{1,2})/', $message, $matches);
        return $matches[0] ?? 'bientôt';
    }

    private function checkCityAvailability($message)
    {
        $allCities = array_merge($this->villesDisponibles, $this->villesIndisponibles);
        foreach ($allCities as $ville) {
            if (strpos($message, $ville) !== false) {
                if (!in_array($ville, $this->villesDisponibles)) {
                    $villeNom = ucfirst($ville);
                    return $this->getResponse('ville_non_disponible', null, $villeNom);
                }
                return null;
            }
        }
        return null;
    }

    private function isSalutation($message)
    {
        $salutations = ['salam', 'bonjour', 'salut', 'hello', 'hi', 'slm'];
        foreach ($salutations as $salutation) {
            if (strpos($message, $salutation) !== false) {
                return true;
            }
        }
        return false;
    }

    private function hasGivenCity($message, $sessionId)
    {
        $allCities = array_merge($this->villesDisponibles, $this->villesIndisponibles);
        foreach ($allCities as $city) {
            if (strpos($message, $city) !== false) {
                if (!isset($this->conversationState[$sessionId]['city'])) {
                    return true;
                }
            }
        }
        return false;
    }

    private function storeCity($message, $sessionId)
    {
        $allCities = array_merge($this->villesDisponibles, $this->villesIndisponibles);
        foreach ($allCities as $city) {
            if (strpos($message, $city) !== false) {
                $this->conversationState[$sessionId]['city'] = $city;
                return $city;
            }
        }
        return null;
    }

    private function hasGivenBudget($message)
    {
        return preg_match('/\d+/', $message) === 1;
    }

    private function storeBudget($message, $sessionId)
    {
        preg_match('/\d+/', $message, $matches);
        if (!empty($matches)) {
            $this->conversationState[$sessionId]['budget'] = $matches[0];
        }
    }

    private function cleanMessage($message)
    {
        return strtolower(trim($message));
    }

    private function cleanResponse($response)
    {
        $salutations = ['Salam !', 'Salamo !', 'Bonjour !', 'Salut !', 'Hello !', 'Hi !', 'Bonjour', 'Salam', 'Salut', 'Hello', 'Hi'];
        foreach ($salutations as $salutation) {
            if (strpos($response, $salutation) === 0) {
                $response = trim(substr($response, strlen($salutation)));
                $response = ltrim($response, ' !😊');
                if (strpos($response, '😊') === false) {
                    $response .= ' 😊';
                }
                break;
            }
        }
        return $response;
    }

    private function checkVueQuestion($message)
    {
        $vueKeywords = ['3layach', 'vue', 'view', 'paysage', 'patio'];
        foreach ($vueKeywords as $keyword) {
            if (strpos($message, $keyword) !== false) {
                return $this->getResponse('vue');
            }
        }
        return null;
    }

    private function checkPriceQuestion($message)
    {
        $priceKeywords = ['prix', 'taman', 'price', 'budget', 'tarif', 'combien', 'chhal', 'how much'];
        foreach ($priceKeywords as $keyword) {
            if (strpos($message, $keyword) !== false) {
                if (strpos($message, 'f3') !== false) {
                    return $this->getResponse('prix_f3');
                }
                if (strpos($message, 'f4') !== false) {
                    return $this->getResponse('prix_f4');
                }
                return $this->getResponse('prix_default');
            }
        }
        return null;
    }

    private function checkBalconQuestion($message)
    {
        $balconKeywords = ['balcon', 'balcony', 'terrasse', 'balcons'];
        foreach ($balconKeywords as $keyword) {
            if (strpos($message, $keyword) !== false) {
                return $this->getResponse('balcon');
            }
        }
        return null;
    }

    private function checkProximiteQuestion($message)
    {
        $proximiteKeywords = ['supermarche', 'mosquee', 'hmam', 'hammam', 'ecole', 'école', 'commerces', 'magasin', 'school', 'mosque', 'shop'];
        foreach ($proximiteKeywords as $keyword) {
            if (strpos($message, $keyword) !== false) {
                return $this->getResponse('proximite');
            }
        }
        return null;
    }

    private function checkUnavailableTypes($message)
    {
        foreach ($this->typesIndisponibles as $type) {
            if (strpos($message, $type) !== false) {
                return $this->getResponse('type_indisponible', null, $type);
            }
        }
        return null;
    }

    private function getFallbackResponse($message)
    {
        if ($this->isSalutation($message)) {
            return $this->getResponse('salutation');
        }

        if ($this->lang === 'fr') {
            return "Je suis Karim, votre conseiller immobilier chez TRACIMO. 🏠\n\nPour vous aider à trouver le bien idéal, dites-moi :\n• Quelle ville vous intéresse ?\n• Quel est votre budget approximatif ?\n• Quel type de bien cherchez-vous (F3 ou F4) ?\n\nJe suis là pour vous guider ! 😊";
        } elseif ($this->lang === 'en') {
            return "I am Karim, your real estate advisor at TRACIMO. 🏠\n\nTo help you find the ideal property, tell me :\n• Which city are you interested in ?\n• What is your approximate budget ?\n• What type of property are you looking for (F3 or F4) ?\n\nI am here to guide you ! 😊";
        } else {
            return "Ana Karim, conseiller immobilier f TRACIMO. 🏠\n\nBach n3awnek tchof l'bien idéal, gol lia :\n• Chno l'ville li tbaghi ?\n• Chhal l'budget dyalk ?\n• Chno type de bien li tbaghi (F3 wla F4) ?\n\nAna hna bach n3awnek ! 😊";
        }
    }

    private function getSystemPrompt()
    {
        $lang = $this->lang;
        if ($lang === 'fr') {
            return "Tu es Karim, un conseiller immobilier marocain professionnel et chaleureux chez TRACIMO. Parle en français. Le projet GreenLand est à Casablanca uniquement. Pose des questions pour orienter le client (ville, budget, type). Utilise 😊.";
        } elseif ($lang === 'en') {
            return "You are Karim, a professional and warm Moroccan real estate advisor at TRACIMO. Speak in English. The GreenLand project is only in Casablanca. Ask questions to guide the client (city, budget, type). Use 😊.";
        } else {
            return "Tu es Karim, un conseiller immobilier marocain professionnel et chaleureux chez TRACIMO. Parle en darija. Le projet GreenLand est à Casablanca uniquement. Pose des questions pour orienter le client (ville, budget, type). Utilise 😊.";
        }
    }

    private function buildPrompt($message)
    {
        $lang = $this->lang;
        $prompt = "🏢 **PROJET GREENLAND - CASABLANCA**\n\n";

        $prompt .= "**Type :** Projet d'appartements UNIQUEMENT\n\n";
        $prompt .= "**Typologies :** F3 et F4 uniquement\n\n";
        $prompt .= "**Prix au m² :** 14 000 à 16 500 DH/m²\n\n";
        $prompt .= "**Superficies :** F3 (83-123 m²), F4 (97-130 m²)\n\n";
        $prompt .= "**Vue :** Sur le patio paysager et les espaces verts 🌿\n\n";
        $prompt .= "**Balcons :** Certains appartements en ont\n\n";
        $prompt .= "**Livraison :** Mars 2027\n\n";
        $prompt .= "**Équipements :** Padel, Salle de sport, Patio paysager\n\n";
        $prompt .= "**Localisation :** Casablanca (SIDI MESSOUD) UNIQUEMENT\n\n";

        $prompt .= "---\n";
        $prompt .= "**QUESTION DU CLIENT :** " . $message . "\n\n";
        $prompt .= "**INSTRUCTIONS :** Réponds en " . ($lang === 'fr' ? 'français' : ($lang === 'en' ? 'anglais' : 'darija')) . ". Pose des questions comme Sakani. Utilise 😊.\n\n";
        $prompt .= "**TA RÉPONSE :**";

        return $prompt;
    }
}
