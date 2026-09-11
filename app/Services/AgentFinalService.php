<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AgentFinalService
{
    /*
    |--------------------------------------------------------------------------
    | CONFIGURATION
    |--------------------------------------------------------------------------
    */
    private array $sessionHistory = [];

    private ?string $apiKey = null;

    private string $model = 'gpt-4o-mini';

    private bool $n8nEnabled = false;

    private ?string $n8nWebhookUrl = null;

    /*
    |--------------------------------------------------------------------------
    | DONNÉES OFFICIELLES GREENLAND
    |--------------------------------------------------------------------------
    */

    private array $data = [
        'projet' => [
            'nom' => 'GreenLand',
            'description' =>
                'GreenLand est un groupe résidentiel fermé et sécurisé qui bénéficie d\'un environnement calme et proche des commodités essentielles.',
            'localisation' =>
                'SIDI MESSOUD, entre Californie et la ville verte, à proximité immédiate de l\'entrée d\'autoroute A3.',
            'adresse' =>
                'Le projet est situé à SIDI MESSOUD, entre Californie et la ville verte, à proximité immédiate de l\'entrée d\'autoroute A3.',
            'etat' =>
                'Le projet est déjà construit et entre dans ses dernières étapes de finition. Une résidence concrète et tangible, dont la livraison approche — pour une acquisition en toute confiance.',
            'date_livraison' => 'Mars 2027',
            'details_bien' =>
                'Une architecture maîtrisée : six immeubles en R+4, un patio central propice à la convivialité, et un parking souterrain offrant un large nombre de places ainsi qu\'un accès pratique. Ascenseur OTIS pour un confort quotidien. Entrée soignée.',
            'equipements' => [
                'Deux terrains de Padel',
                'Une salle de sport',
                'Un patio paysager',
                'Parking souterrain',
                'Ascenseur OTIS',
            ],
            'superficies' => 'Les appartements vont de 83 à 130 m².',
            'typologies' => [
                'F3' => [
                    'composition' => '2 chambres + salon + 2 salles de bains',
                    'surface' => '83 m² à 123 m²',
                ],
                'F4' => [
                    'composition' => '3 chambres + salon + 2 salles de bains',
                    'surface' => '97 m² à 130 m²',
                ],
            ],
            'horaires' => '7j/7, de 10h à 18h.',
            'contact_agents' =>
                'Mr Oussama : 212660446758 / Mr Maghraoui : 212660446758',
        ],
    ];

    private array $priceRanges = [
        'F3' => [
            'min' => 1162000,
            'max' => 2029500,
            'min_formatted' => '1 162 000',
            'max_formatted' => '2 029 500',
            'surface' => '83 à 123 m²'
        ],
        'F4' => [
            'min' => 1358000,
            'max' => 2145000,
            'min_formatted' => '1 358 000',
            'max_formatted' => '2 145 000',
            'surface' => '97 à 130 m²'
        ]
    ];

    /*
    |--------------------------------------------------------------------------
    | ÉTAT DE CONVERSATION
    |--------------------------------------------------------------------------
    */

    private array $conversationState = [
        'first_message_done' => false,
        'initialized' => true,
        'greeting_done' => false,
        'out_of_project_handled' => false,
        'project' => 'GreenLand',
        'completed' => false,
        'visit_asked' => false,
        'last_invalid_time' => null,
        'conversation_stage' => 'greeting',
        'last_question' => null,
        'last_question_type' => null,
        'last_user_intent' => null,
        'last_message_was_confirmation' => false,
        'purpose' => null,
        'city' => null,
        'property_type' => null,
        'surface' => null,
        'budget' => null,
        'budget_invalid' => false,
        'budget_error' => null,
        'budget_given_by_user' => false,
        'payment_method' => null,
        'wants_visit' => false,
        'visit_requested' => false,
        'visit_accepted' => false,
        'name' => null,
        'phone' => null,
        'wants_contact' => false,
        'appointment_date' => null,
        'appointment_time' => null,
        'commercial_contact_requested' => false,
        'contact_sent' => false,
        'last_user_message' => null,
        'last_bot_message' => null,
    ];

    /*
    |--------------------------------------------------------------------------
    | CONTACT EN ATTENTE
    |--------------------------------------------------------------------------
    */

    private array $pendingContact = [];

    /*
    |--------------------------------------------------------------------------
    | CONSTRUCTEUR
    |--------------------------------------------------------------------------
    */

    public function __construct(array $savedState = [])
    {
        $this->apiKey = env('OPENROUTER_API_KEY');
        $this->model = env('OPENROUTER_MODEL', 'gpt-4o-mini');
        $this->n8nEnabled = filter_var(env('N8N_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
        $this->n8nWebhookUrl = env('N8N_WEBHOOK_URL');

        if (!empty($savedState)) {
            foreach ($savedState as $key => $value) {
                if ($value !== null && $value !== '') {
                    $this->conversationState[$key] = $value;
                }
            }
        }

        if (!empty($this->conversationState['budget']) && (int) $this->conversationState['budget'] > 0) {
            $this->conversationState['budget_given_by_user'] = true;
        }

        if (!empty($this->conversationState['property_type'])) {
            $type = strtoupper($this->conversationState['property_type']);
            if ($type === 'F3' || $type === 'F4') {
                $this->conversationState['property_type'] = $type;
            }
        }

        $this->buildPendingContact();

        Log::info('AgentFinalService construit', [
            'saved_state' => $savedState,
            'final_state' => $this->conversationState,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | PROJECT
    |--------------------------------------------------------------------------
    */

    private function getProject(): array
    {
        return $this->data['projet'];
    }

    private function getDataContext(): string
    {
        $project = $this->getProject();

        return implode("\n", [
            "PROJET : {$project['nom']}",
            "DESCRIPTION : {$project['description']}",
            "LOCALISATION : {$project['localisation']}",
            "ADRESSE : {$project['adresse']}",
            "ÉTAT : {$project['etat']}",
            "LIVRAISON : {$project['date_livraison']}",
            "DÉTAILS : {$project['details_bien']}",
            "SUPERFICIES : {$project['superficies']}",
            "HORAIRES : {$project['horaires']}",
            "CONTACTS : {$project['contact_agents']}",
            "F3 : {$project['typologies']['F3']['composition']} ; {$project['typologies']['F3']['surface']}",
            "F4 : {$project['typologies']['F4']['composition']} ; {$project['typologies']['F4']['surface']}",
            "ÉQUIPEMENTS : " . implode(' - ', $project['equipements']),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | 🔥 NOUVELLES MÉTHODES : appendQuestion / getSmartQuestion / getQuestion
    |--------------------------------------------------------------------------
    */

    /**
     * 🔥 AJOUTER UNE QUESTION POUR ATTIRER L'ATTENTION DU CLIENT
     * (Version INTELLIGENTE - ne pose pas de question si l'info est déjà donnée)
     */
    private function appendQuestion(string $response, string $context = 'default'): string
    {
        // ✅ Vérifier si la réponse se termine déjà par une question
        $trimmed = trim($response);
        if (preg_match('/[?؟]\s*[😊😉🙂]?\s*$/u', $trimmed)) {
            return $response;
        }

        // ✅ Récupérer l'état actuel de la conversation
        $state = $this->conversationState;
        $lang = $this->detectLanguage($this->conversationState['last_user_message'] ?? 'fr');

        // ✅ Vérifier ce qui est DÉJÀ connu
        $hasType = !empty($state['property_type']) && in_array(strtoupper($state['property_type']), ['F3', 'F4']);
        $hasBudget = !empty($state['budget']) && !empty($state['budget_given_by_user']);
        $hasVisit = !empty($state['visit_accepted']) || !empty($state['visit_requested']);
        $hasName = !empty($state['name']);
        $hasDate = !empty($state['appointment_date']);

        // ✅ Générer la bonne question selon le contexte ET l'état
        $question = $this->getSmartQuestion($lang, $context, [
            'has_type' => $hasType,
            'has_budget' => $hasBudget,
            'has_visit' => $hasVisit,
            'has_name' => $hasName,
            'has_date' => $hasDate,
        ]);

        // ✅ Si aucune question pertinente, ne rien ajouter
        if (empty($question)) {
            return $response;
        }

        return $response . $question;
    }

    /**
     * 🔥 RETOURNER LA QUESTION INTELLIGENTE
     */
    private function getSmartQuestion(string $lang, string $context, array $state): string
    {
        // ✅ ÉTAPE 1 : Si le client a TOUT (type + budget + visite + nom + date) → Confirmer
        if ($state['has_visit'] && $state['has_name'] && $state['has_date']) {
            return ''; // ✅ Plus rien à demander, la visite est confirmée
        }

        // ✅ ÉTAPE 2 : Si le client a visité mais pas de nom → Demander le nom
        if ($state['has_visit'] && !$state['has_name']) {
            return $this->getQuestion($lang, 'name');
        }

        // ✅ ÉTAPE 3 : Si le client a nom mais pas de date → Demander la date
        if ($state['has_name'] && !$state['has_date']) {
            return $this->getQuestion($lang, 'date');
        }

        // ✅ ÉTAPE 4 : Si le client a un type et budget → Proposer la visite
        if ($state['has_type'] && $state['has_budget'] && !$state['has_visit']) {
            return $this->getQuestion($lang, 'visit');
        }

        // ✅ ÉTAPE 5 : Si le client a un type mais pas de budget → Demander le budget
        if ($state['has_type'] && !$state['has_budget']) {
            return $this->getQuestion($lang, 'budget');
        }

        // ✅ ÉTAPE 6 : Si le client n'a pas de type → Demander le type
        if (!$state['has_type']) {
            return $this->getQuestion($lang, 'type');
        }

        // ✅ ÉTAPE 7 : Fallback générique
        return $this->getQuestion($lang, $context);
    }

    /**
     * 🔥 RETOURNER LA QUESTION SELON LE TYPE
     */
    private function getQuestion(string $lang, string $type): string
    {
        $questions = [
            'fr' => [
                'name'    => "\n\nPour réserver la visite, donnez-moi votre **nom complet** 😊",
                'date'    => "\n\nQuelle **date** vous conviendrait pour la visite ? 😊",
                'visit'   => "\n\nSouhaitez-vous organiser une visite ? 😊",
                'budget'  => "\n\nQuel est votre **budget approximatif** ? 😊",
                'type'    => "\n\nQuel type vous intéresse (**F3** ou **F4**) ? 😊",
                'default' => "\n\nQue souhaitez-vous savoir d'autre ? 😊",
            ],
            'en' => [
                'name'    => "\n\nTo book the visit, give me your **full name** 😊",
                'date'    => "\n\nWhat **date** would work for you for the visit? 😊",
                'visit'   => "\n\nWould you like to schedule a visit? 😊",
                'budget'  => "\n\nWhat is your **approximate budget**? 😊",
                'type'    => "\n\nWhich type are you interested in (**F3** or **F4**)? 😊",
                'default' => "\n\nWhat else would you like to know? 😊",
            ],
            'darija' => [
                'name'    => "\n\nBach n7jz lik visite, 3tini **smiytek kamla** 😊",
                'date'    => "\n\n**Ach mn nhar** mzyan lik l'visite ? 😊",
                'visit'   => "\n\nWach baghi t'planifier visite ? 😊",
                'budget'  => "\n\nChhal l'**budget dyalk** ? 😊",
                'type'    => "\n\nAch mn type m'7tam bik (**F3** wla **F4**) ? 😊",
                'default' => "\n\nAch baghi t3ref akhor ? 😊",
            ],
        ];

        return $questions[$lang][$type] ?? $questions[$lang]['default'];
    }

    /*
    |--------------------------------------------------------------------------
    | IA COMPRÉHENSION
    |--------------------------------------------------------------------------
    */

    private function understandWithAI(string $message): array
    {
        if (!$this->apiKey) {
            return $this->understandManually($message);
        }

        $prompt = "Tu es un assistant qui comprend le langage naturel des clients pour un projet immobilier appelé GreenLand.

Message du client: \"$message\"

Données du projet GreenLand:
- Projet: GreenLand, groupe résidentiel à SIDI MESSOUD
- Typologies: F3 (2 chambres + salon + 2 sdb, 83-123 m²), F4 (3 chambres + salon + 2 sdb, 97-130 m²)
- Équipements: Padel, Sport, Patio, Parking, Ascenseur
- Livraison: Mars 2027
- Horaires: 7j/7, 10h à 18h
- Contact: Mr Oussama/Mr Maghraoui : 212660446758

Instructions:
Analyse le message du client et détermine son INTENTION.

Réponds UNIQUEMENT au format JSON avec cette structure:
{
    \"intent\": \"localisation|prix|surface|equipement|livraison|description|contact|horaire|typologie|achat|budget|visite|salutation|inconnu\",
    \"value\": \"la valeur extraite si applicable\",
    \"confidence\": 0.9,
    \"response\": \"la réponse à donner au client (si déjà générée)\",
    \"explanation\": \"pourquoi tu as fait ce choix\"
}

Exemples:
- Message: \"livraison\" → {\"intent\":\"livraison\",\"value\":\"livraison\",\"confidence\":0.99,\"response\":\"\",\"explanation\":\"client demande la date de livraison\"}
- Message: \"surfaces\" → {\"intent\":\"surface\",\"value\":\"surface\",\"confidence\":0.99,\"response\":\"\",\"explanation\":\"client demande les surfaces\"}
- Message: \"chn akhor\" → {\"intent\":\"description\",\"value\":\"description\",\"confidence\":0.9,\"response\":\"\",\"explanation\":\"client demande plus d'informations sur le projet\"}
- Message: \"wch fih des apprtement l bi3\" → {\"intent\":\"typologie\",\"value\":\"typologie\",\"confidence\":0.95,\"response\":\"\",\"explanation\":\"client demande les typologies disponibles\"}
- Message: \"brit nchri appartement\" → {\"intent\":\"achat\",\"value\":\"achat\",\"confidence\":0.99,\"response\":\"\",\"explanation\":\"client veut acheter un appartement\"}
- Message: \"prix\" → {\"intent\":\"prix\",\"value\":\"prix\",\"confidence\":0.99,\"response\":\"\",\"explanation\":\"client demande les prix\"}
- Message: \"adresse\" → {\"intent\":\"localisation\",\"value\":\"adresse\",\"confidence\":0.99,\"response\":\"\",\"explanation\":\"client demande l'adresse\"}

IMPORTANT:
- Comprends le SENS, pas seulement les mots
- Si le client demande des informations sur le projet, retourne l'intention correspondante
- Si le client veut acheter, retourne \"achat\"
- Ne réponds JAMAIS avec les typologies par défaut si ce n'est pas approprié

Réponds UNIQUEMENT en JSON, sans autre texte.";

        try {
            $response = Http::timeout(15)
                ->withToken($this->apiKey)
                ->acceptJson()
                ->post(
                    'https://openrouter.ai/api/v1/chat/completions',
                    [
                        'model' => $this->model,
                        'messages' => [
                            ['role' => 'system', 'content' => 'Tu es un assistant qui comprend le langage naturel. Réponds UNIQUEMENT en JSON.'],
                            ['role' => 'user', 'content' => $prompt]
                        ],
                        'temperature' => 0.1,
                        'max_tokens' => 300,
                    ]
                );

            if ($response->successful()) {
                $json = $response->json();
                $content = $json['choices'][0]['message']['content'] ?? '';

                Log::info('📥 Réponse IA pour compréhension', ['content' => $content]);

                if (preg_match('/\{[^{}]*\}/', $content, $matches)) {
                    $result = json_decode($matches[0], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        Log::info('✅ IA a compris le message', [
                            'intent' => $result['intent'] ?? 'inconnu',
                            'confidence' => $result['confidence'] ?? 0,
                            'explanation' => $result['explanation'] ?? ''
                        ]);
                        return $result;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('❌ Erreur IA pour compréhension', ['error' => $e->getMessage()]);
        }

        return $this->understandManually($message);
    }

    private function understandManually(string $message): array
    {
        $lower = $this->normalize($message);

        $intentMap = [
            'localisation' => ['localisation', 'adresse', 'fin kayn', 'ou se trouve', 'sidi messoud'],
            'prix' => ['prix', 'chhal', 'combien', 'tarif', 'cout', 'coût'],
            'surface' => ['surface', 'superficie', 'metre', 'm2', 'm²', 'taille'],
            'equipement' => ['équipement', 'equipement', 'padel', 'sport', 'patio', 'parking', 'ascenseur'],
            'livraison' => ['livraison', 'date livraison', 'mars', 'delai', 'quand'],
            'description' => ['description', 'details', 'detail', 'info', 'infos'],
            'contact' => ['contact', 'téléphone', 'telephone', 'numero', 'appel', 'commercial'],
            'horaire' => ['horaire', 'horaires', 'ouverture', 'fermeture'],
            'typologie' => ['typologie', 'type', 'chambre', 'salon', 'f3', 'f4'],
            'achat' => ['acheter', 'achat', 'brit', 'bghit', 'nchri', 'nchry', 'appartement', 'apprt', 'logement'],
            'visite' => ['visite', 'visiter', 'nzour', 'nchouf', 'nchof'],
            'budget' => ['budget'],
            'salutation' => ['bonjour', 'salam', 'salut', 'hello', 'slm', 'marhba']
        ];

        foreach ($intentMap as $intent => $keywords) {
            foreach ($keywords as $keyword) {
                if (mb_stripos($lower, $keyword) !== false) {
                    if ($intent === 'salutation') {
                        return ['intent' => 'salutation', 'confidence' => 0.9];
                    }
                    return ['intent' => $intent, 'value' => $keyword, 'confidence' => 0.7];
                }
            }
        }

        return ['intent' => 'inconnu', 'confidence' => 0.3];
    }

    private function generateResponseFromIntent(array $intentResult): ?string
    {
        $intent = $intentResult['intent'] ?? 'inconnu';
        $lang = $this->detectLanguage($this->conversationState['last_user_message'] ?? 'fr');

        switch ($intent) {
            case 'localisation':
                $response = "📍 **Localisation GreenLand :**\n\n" .
                            "🏠 SIDI MESSOUD, entre Californie et la ville verte,\n" .
                            "🚗 À proximité immédiate de l'entrée d'autoroute A3.\n\n" .
                            "📌 Un emplacement stratégique alliant calme et accessibilité.";
                return $this->translateResponse($response, $lang);

            case 'prix':
                $response = "📊 **Prix GreenLand :**\n\n" .
                            "📐 **Typologies :**\n" .
                            "   • F3 (83 à 123 m²) " .
                            "   • F4 (97 à 130 m²) " .
                            "📌 Prix indicatifs selon étage, vue et orientation.";
                return $this->translateResponse($response, $lang);

            case 'surface':
                $response = "📐 **Surfaces GreenLand :**\n\n" .
                            "📐 **Typologies :**\n" .
                            "   • F3 : **83 à 123 m²** (2 chambres + salon + 2 salles de bains)\n" .
                            "   • F4 : **97 à 130 m²** (3 chambres + salon + 2 salles de bains)";
                return $this->translateResponse($response, $lang);

            case 'equipement':
                $response = "🏋️ **Équipements GreenLand :**\n\n" .
                            "🎾 Deux terrains de Padel\n" .
                            "🏋️ Une salle de sport\n" .
                            "🌿 Un patio paysager\n" .
                            "🅿️ Parking souterrain\n" .
                            "🛗 Ascenseur OTIS\n\n" .
                            "✨ Six immeubles en R+4, un patio central propice à la convivialité.";
                return $this->translateResponse($response, $lang);

            case 'livraison':
                $response = "📅 **Date de livraison GreenLand :**\n\n" .
                            "🏗️ Le projet est déjà construit et entre dans ses dernières étapes de finition.\n" .
                            "📆 Livraison prévue : **Mars 2027**\n\n" .
                            "✅ Une résidence concrète et tangible, dont la livraison approche.";
                return $this->translateResponse($response, $lang);

            case 'description':
                $response = "🏠 **Description GreenLand :**\n\n" .
                            "GreenLand est un groupe résidentiel fermé et sécurisé qui bénéficie d'un environnement calme et proche des commodités essentielles.\n\n" .
                            "🏗️ Six immeubles en R+4\n" .
                            "🌿 Un patio central propice à la convivialité\n" .
                            "🅿️ Parking souterrain\n" .
                            "🛗 Ascenseur OTIS\n\n" .
                            "📅 Livraison : Mars 2027\n" .
                            "📍 SIDI MESSOUD, entre Californie et la ville verte";
                return $this->translateResponse($response, $lang);

            case 'contact':
                $response = "📞 **Contacts GreenLand :**\n\n" .
                            "• Mr Oussama : 212660446758\n" .
                            "• Mr Maghraoui : 212660446758\n\n" .
                            "📧 Contactez-nous pour toute question ou visite.";
                return $this->translateResponse($response, $lang);

            case 'horaire':
                $response = "🕐 **Horaires GreenLand :**\n\n" .
                            "📅 7j/7, de 10h à 18h.\n\n" .
                            "📍 Visites sur rendez-vous.";
                return $this->translateResponse($response, $lang);

            case 'typologie':
                $response = "📐 **Typologies GreenLand :**\n\n" .
                            "📐 **F3 :**\n" .
                            "   • 2 chambres + salon + 2 salles de bains\n" .
                            "   • 83 à 123 m²\n\n" .
                            "📐 **F4 :**\n" .
                            "   • 3 chambres + salon + 2 salles de bains\n" .
                            "   • 97 à 130 m²\n\n" .
                            "Quel type vous intéresse ?";
                return $this->translateResponse($response, $lang);

            case 'achat':
                $type = $this->conversationState['property_type'] ?? null;
                if (empty($type)) {
                    $response = "📐 **Typologies GreenLand :**\n\n" .
                                "📐 **F3 :**\n" .
                                "   • 2 chambres + salon + 2 salles de bains\n" .
                                "   • 83 à 123 m²\n\n" .
                                "📐 **F4 :**\n" .
                                "   • 3 chambres + salon + 2 salles de bains\n" .
                                "   • 97 à 130 m²\n\n" .
                                "Quel type vous intéresse ?";
                    return $this->translateResponse($response, $lang);
                } else {
                    if (empty($this->conversationState['budget']) || !$this->conversationState['budget_given_by_user']) {
                        $response = "Parfait 😊 Vous êtes intéressé par un " . $type . ".\n\n" .
                                    "💰 Quel budget avez-vous prévu pour votre appartement ?";
                        return $this->translateResponse($response, $lang);
                    }
                }
                return null;

            case 'salutation':
                return $this->greetingResponse();

            case 'inconnu':
            default:
                return null;
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | DÉTECTION DE LANGUE
    |--------------------------------------------------------------------------
    */

    private function detectLanguage(string $message): string
    {
        $message = trim($message);

        $frenchWords = [
            'bonjour', 'salut', 'merci', 'svp', 'stp', 'd\'accord',
            'oui', 'non', 'je', 'tu', 'il', 'elle', 'nous', 'vous',
            'appartement', 'visite', 'budget', 'prix', 'surface',
            'localisation', 'adresse', 'équipement', 'livraison',
            'combien', 'quel', 'quelle', 'pourquoi', 'comment'
        ];

        $darijaWords = [
            'salam', 'slm', 'marhba', 'bghit', 'brit', 'wakha',
            'la', 'wach', 'chno', 'chhal', 'fin', 'kifach',
            'mzyan', 'saha', 'tayara', 'wah', 'hada', 'hadak',
            'nchouf', 'nchof', 'nzour', 'nvisiti', 'bghitch'
        ];

        $lower = mb_strtolower($message);
        $frenchCount = 0;
        $darijaCount = 0;

        foreach ($frenchWords as $word) {
            if (mb_stripos($lower, $word) !== false) {
                $frenchCount++;
            }
        }

        foreach ($darijaWords as $word) {
            if (mb_stripos($lower, $word) !== false) {
                $darijaCount++;
            }
        }

        if (preg_match('/^(bonjour|salut|hello|merci)/i', $message)) {
            return 'fr';
        }

        if (preg_match('/^(salam|slm|marhba|salamo)/i', $message)) {
            return 'darija';
        }

        return ($frenchCount >= $darijaCount) ? 'fr' : 'darija';
    }

    /*
    |--------------------------------------------------------------------------
    | TRADUCTION
    |--------------------------------------------------------------------------
    */

    private function translateResponse(string $response, string $lang): string
    {
        if ($lang === 'fr') {
            return $response;
        }

        $translations = [
            "Merci  J'ai bien noté votre recherche" => "Merci  9rit mzyan recherche dialek",
            "Souhaitez-vous que je transmette votre demande de visite" => "Wach bghiti n'envoyi demande dialek l'équipe",
            "Parfait  Pour transmettre votre demande de visite" => "Mzyan  Bach n'envoyi demande dialek l'équipe",
            "donnez-moi simplement votre nom" => "3tini ismek",
            "Quel numéro de téléphone peut-on utiliser" => "Chhal men raqm téléphone",
            "Quel jour vous conviendrait pour la visite" => "Ach mn jour mzyan lik",
            "Et à quelle heure vous conviendrait la visite" => "W ach mn sa3a mzyana lik",
            "Le bureau est ouvert de 10h à 18h" => "L'bureau mftou7 mn 10h l 18h",
            "J'ai bien enregistré votre demande de visite" => "Sijilt demande dialek",
            "Notre équipe vous contactera" => "L'équipe taycontacti m3ak",
            "Merci de nous avoir contactés" => "Merci 3la contact",
            "Votre demande de visite a déjà été enregistrée" => "Demande dialek t'ajouté",
            "Notre équipe vous contactera très prochainement" => "L'équipe taycontacti m3ak qrib",
            "Pour toute urgence" => "L'urgence",
            "Pas de problème, pas d'obligation" => "Machi mouchkil, machi wajib",
            "Si vous changez d'avis" => "Ila bghiti tbeddel ra'yek",
            "n'hésitez pas à nous contacter" => "ma t'khafch t'contactina",
            "Ou envoyez-nous un message" => "Wla sift lina message",
            "Merci et à bientôt" => "Merci w n'tla9aw",
            "Désolé  Le prix des appartements" => "  L'prix d'l'appartements",
            "est compris entre" => "mabin",
            "Pour un F3 de 83 m²" => "L'F3 dyal 83 m²",
            "à partir de" => "mn",
            "Pour un F4 de 97 m²" => "L'F4 dyal 97 m²",
            "c'est malheureusement insuffisant" => "machi mzyan",
            "Réviser votre budget" => "tbeddel budget dialek",
            "minimum" => "l'aghal",
            "Obtenir plus d'informations" => "t'khoud I'infos",
            "**Informations sur les prix" => "**I'infos 3la l'prix",
            "Prix au m²" => "Prix f m²",
            "Typologies" => "L'typologies",
            "Ces prix sont indicatifs" => "Hado l'prix ta9ribiyin",
            "peuvent varier selon" => "tayt'bedlo 7sab",
            "L'étage" => "L'étage",
            "La vue" => "L'vue",
            "L'orientation" => "L'orientation",
            "Quel budget avez-vous prévu" => "Chhal mn budget 3ndek",
            "**Informations sur les surfaces" => "**I'infos 3la l'surfaces",
            "Les prix varient entre" => "L'prix tayt'bedlo mabin",
            "Quel type vous intéresse" => "Ach mn type m'7tam bik",
            "F3 ou F4" => "F3 wla F4",
            "Très bien" => "Mzyan",
            "Vous recherchez plutôt" => "Kant9leb 3la",
            "Parfait" => "Mzyan",
            "Salam  Marhba bik m3a Greenland" => "Salam  Marhba bik m3a Greenland",
            "Bien sûr  Je peux vous renseigner sur GreenLand" => "Bien sûr  N9der n3tik I'infos 3la GreenLand",
            "Comment puis-je vous aider" => "Kifash n9der n3awnek",
            "Quel type vous intéresse" => "Ach mn type m'7tam bik",
            "Vous êtes intéressé par un" => "M'7tam b",
        ];

        foreach ($translations as $fr => $darija) {
            if (mb_stripos($response, $fr) !== false) {
                $response = str_ireplace($fr, $darija, $response);
            }
        }

        return $response;
    }

    /*
    |--------------------------------------------------------------------------
    | NORMALISATION
    |--------------------------------------------------------------------------
    */

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');

        return strtr($value, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a', 'å' => 'a',
            'ç' => 'c',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'ì' => 'i', 'í' => 'i',
            'ô' => 'o', 'ö' => 'o', 'ò' => 'o', 'ó' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u',
            'ÿ' => 'y',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | MONEY
    |--------------------------------------------------------------------------
    */

    private function parseMoney(string $value): ?int
    {
        $value = mb_strtolower(trim($value), 'UTF-8');

        $value = str_replace(
            ['dh', 'dhs', 'mad', 'dirhams', 'dirham', 'm', 'million'],
            '',
            $value
        );

        $value = trim($value);

        if (preg_match('/^([0-9][0-9\s.,]*)$/u', $value)) {
            $normalized = str_replace([' ', '.', ','], '', $value);
            if (is_numeric($normalized)) {
                return (int) $normalized;
            }
        }

        if (preg_match('/^([0-9]+(?:[.,][0-9]+)?)$/', $value, $matches)) {
            $number = str_replace(',', '.', $matches[1]);
            if (is_numeric($number)) {
                if ((float) $number < 100) {
                    return (int) round(((float) $number) * 1000000);
                }
                return (int) round((float) $number);
            }
        }

        if (preg_match('/^([0-9]+)$/', $value, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | EXTRACTION BUDGET
    |--------------------------------------------------------------------------
    */

    private function extractBudget(string $message): ?int
    {
        $message = trim($message);

        if (preg_match(
            '/(?:budget|j\'ai un budget|jai un budget|mon budget|budget dyali|budget diali)\s*(?:de|est|:)?\s*([0-9][0-9\s.,]*(?:\s*m)?)/iu',
            $message,
            $matches
        )) {
            return $this->parseMoney($matches[1]);
        }

        if (preg_match(
            '/^\s*([0-9][0-9\s.,]*)\s*(?:dh|dhs|mad)?\s*$/iu',
            $message,
            $matches
        )) {
            return $this->parseMoney($matches[1]);
        }

        if (preg_match(
            '/(?:ok|je vais|je veux|je change|nouveau|mon nouveau)\s*(?:de|:)?\s*([0-9][0-9\s.,]*)\s*(?:dh|dhs|mad)?/iu',
            $message,
            $matches
        )) {
            return $this->parseMoney($matches[1]);
        }

        if (preg_match(
            '/\b([0-9]{1,3}(?:\s[0-9]{3})*|[0-9]{4,9})\b/',
            $message,
            $matches
        )) {
            $amount = $this->parseMoney($matches[1]);
            if ($amount !== null && $amount > 10000) {
                return $amount;
            }
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | PHONE
    |--------------------------------------------------------------------------
    */

    private function extractPhone(string $message): ?string
    {
        $cleaned = preg_replace('/[^0-9+]/', '', $message);

        if (empty($cleaned)) {
            return null;
        }

        if (preg_match('/^\+212[0-9]{9}$/', $cleaned)) {
            return $cleaned;
        }

        if (preg_match('/^212[0-9]{9}$/', $cleaned)) {
            return '+' . $cleaned;
        }

        if (preg_match('/^0[0-9]{9}$/', $cleaned)) {
            return '+212' . substr($cleaned, 1);
        }

        if (preg_match('/^[5-7][0-9]{9}$/', $cleaned)) {
            return '+212' . $cleaned;
        }

        if (preg_match('/^[5-7][0-9]{8}$/', $cleaned)) {
            return '+212' . $cleaned;
        }

        if (preg_match('/^[0-9]{10}$/', $cleaned)) {
            if (preg_match('/^[5-7]/', $cleaned)) {
                return '+212' . $cleaned;
            }
            if (preg_match('/^0/', $cleaned)) {
                return '+212' . substr($cleaned, 1);
            }
        }

        if (preg_match('/^[0-9]{9}$/', $cleaned)) {
            if (preg_match('/^[5-7]/', $cleaned)) {
                return '+212' . $cleaned;
            }
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | TYPE EXTRACTION
    |--------------------------------------------------------------------------
    */

    private function extractPropertyType(string $message): ?string
    {
        $message = strtoupper($message);

        if (preg_match('/\bF3\b/', $message) ||
            preg_match('/\bF 3\b/', $message) ||
            preg_match('/\bF-3\b/', $message) ||
            strpos($message, 'F3') !== false) {
            return 'F3';
        }

        if (preg_match('/\bF4\b/', $message) ||
            preg_match('/\bF 4\b/', $message) ||
            preg_match('/\bF-4\b/', $message) ||
            strpos($message, 'F4') !== false) {
            return 'F4';
        }

        if (strpos($message, '3 CHAMBRES') !== false ||
            strpos($message, '3 CHAMBRE') !== false) {
            return 'F4';
        }

        if (strpos($message, '2 CHAMBRES') !== false ||
            strpos($message, '2 CHAMBRE') !== false) {
            return 'F3';
        }

        if (preg_match('/\bF[2-9]\b/', $message)) {
            return 'INVALID';
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | INTENT DETECTION
    |--------------------------------------------------------------------------
    */

    private function detectBuyIntent(string $message): bool
    {
        $lower = $this->normalize($message);

        $buyWords = [
            'acheter', 'achat', 'brit', 'bghit', 'nchri', 'nchry',
            'je veux', 'je cherche', 'kant9leb', 'kantlebb',
            'appartement', 'apprt', 'logement', 'dar',
            'visite', 'nzour', 'nchouf', 'nchof', 'visiter'
        ];

        foreach ($buyWords as $word) {
            if (mb_stripos($lower, $word) !== false) {
                return true;
            }
        }

        return false;
    }

    private function detectInfoIntent(string $message): bool
    {
        $lower = $this->normalize($message);

        $infoWords = [
            'localisation', 'adresse', 'description', 'details',
            'équipement', 'equipement', 'padel', 'sport', 'parking',
            'ascenseur', 'livraison', 'mars', 'delai', 'horaire',
            'contact', 'téléphone', 'numero', 'surface', 'superficie',
            'typologie', 'type', 'chambre', 'salon', 'etat', 'état',
            'prix', 'chhal', 'combien', 'tarif', 'cout'
        ];

        foreach ($infoWords as $word) {
            if (mb_stripos($lower, $word) !== false) {
                return true;
            }
        }

        return false;
    }

    private function getAvailableAmenities(): array
    {
        return [
            'padel', 'sport', 'patio', 'parking', 'ascenseur',
            'terrain de padel', 'salle de sport', 'patio paysager',
            'parking souterrain', 'ascenseur otis'
        ];
    }

    private function getAvailableTypes(): array
    {
        return ['f3', 'f4'];
    }

    private function detectOutOfProject(string $message): ?string
    {
        $lower = $this->normalize($message);

        $alwaysOutOfProject = [
            'villa', 'maison', 'riad', 'dar',
            'boutique', 'commerce', 'bureau', 'studio',
            'piscine', 'swimming pool', 'hamam', 'hammam',
            'restaurant', 'cafe', 'snack', 'bar', 'night club',
            'jardin', 'garden', 'parc', 'park', 'espace vert',
            'terrain de foot', 'football', 'basket', 'tennis',
            'gym', 'fitness', 'yoga', 'spa', 'sauna', 'jacuzzi',
            'cinema', 'salle de cinema', 'theatre',
            'supermarche', 'epicerie', 'magasin', 'shop',
            'pharmacie', 'banque', 'poste', 'bureau de poste',
            'ecole', 'college', 'lycee', 'universite', 'crèche',
            'mosquee', 'eglise', 'temple',
            'voiturier', 'concierge', 'gardien', 'security',
        ];

        $exceptions = [
            'localisation', 'localisation', 'localisé', 'localiser',
            'adresse', 'adresser',
            'prix', 'prix', 'chhal', 'combien',
            'surface', 'superficie', 'metre', 'm2', 'm²',
            'équipement', 'equipement', 'padel', 'sport', 'parking', 'ascenseur',
            'livraison', 'date livraison', 'mars', 'delai', 'quand',
            'description', 'details', 'detail', 'info', 'infos',
            'typologie', 'type', 'chambre', 'salon',
            'etat', 'état', 'construction', 'avancement',
            'horaire', 'ouverture', 'fermeture',
            'contact', 'téléphone', 'numero', 'appel', 'commercial'
        ];

        foreach ($alwaysOutOfProject as $word) {
            if (mb_stripos($lower, $word) !== false) {
                $isException = false;
                foreach ($exceptions as $exception) {
                    if (mb_stripos($lower, $exception) !== false) {
                        $isException = true;
                        break;
                    }
                }
                if ($isException) {
                    continue;
                }

                $available = $this->getAvailableAmenities();
                foreach ($available as $availableWord) {
                    if (mb_stripos($word, $availableWord) !== false ||
                        mb_stripos($availableWord, $word) !== false) {
                        return null;
                    }
                }
                return $word;
            }
        }

        return null;
    }

    private function defaultResponse(string $message): string
    {
        $lang = $this->detectLanguage($message);

        if ($lang === 'fr') {
            return "Je n'ai pas bien compris votre demande 😊\n\n" .
                   "Pourriez-vous reformuler votre question ?\n" .
                   "Je peux vous aider avec :\n" .
                   "   • Les typologies (F3 ou F4)\n" .
                   "   • Les prix\n" .
                   "   • La localisation\n" .
                   "   • Les équipements\n" .
                   "   • Les horaires\n" .
                   "   • Ou pour planifier une visite";
        } else {
            return "Ma fhemtch mzyan had l'talab dialek 😊\n\n" .
                   "Wach t9der t3tini question wa7da akhra ?\n" .
                   "N9der n3awnek f :\n" .
                   "   • L'typologies (F3 wla F4)\n" .
                   "   • L'prix\n" .
                   "   • L'localisation\n" .
                   "   • L'équipements\n" .
                   "   • L'horaires\n" .
                   "   • Wla bach t'planifier visite";
        }
    }

    private function outOfProjectResponse(string $requested): string
    {
        $lang = $this->detectLanguage($this->conversationState['last_user_message'] ?? 'fr');

        $translations = [
            'piscine' => [
                'fr' => "Je comprends votre intérêt pour une piscine.\n\n" .
                       "Malheureusement, GreenLand ne dispose pas de piscine actuellement.\n\n" .
                       " GreenLand est un **groupe résidentiel** avec des appartements F3 et F4.\n" .
                       "Équipements disponibles : Padel, Sport, Patio, Parking, Ascenseur.\n\n" .
                       "Souhaitez-vous plus d'informations sur les appartements ?",
                'darija' => "fhemt had l'envie dialek l'piscine.\n\n" .
                           "GreenLand ma3ndouch piscine.\n\n" .
                           "GreenLand howa **groupe résidentiel** fih appartements F3 ou F4.\n" .
                           "L'équipements li 3ndna : Padel, Sport, Patio, Parking, Ascenseur.\n\n" .
                           "Wach baghi t'khoud I'infos 3la l'appartements ?"
            ],
            'restaurant' => [
                'fr' => "Je comprends votre demande pour un restaurant.\n\n" .
                       "GreenLand ne dispose pas de restaurant sur place.\n\n" .
                       "GreenLand est un **groupe résidentiel** avec des appartements F3 et F4.\n" .
                       "Équipements disponibles : Padel, Sport, Patio, Parking, Ascenseur.\n" .
                       "Localisation : SIDI MESSOUD, proche des commodités.\n\n" .
                       "Souhaitez-vous plus d'informations sur les appartements ?",
                'darija' => "fhemt had l'talab dialek l'restaurant.\n\n" .
                           " GreenLand ma3ndouch restaurant.\n\n" .
                           "GreenLand howa **groupe résidentiel** fih appartements F3 ou F4.\n" .
                           "L'équipements li 3ndna : Padel, Sport, Patio, Parking, Ascenseur.\n" .
                           " Localisation : SIDI MESSOUD, qrib l'commodités.\n\n" .
                           "Wach baghi t'khoud I'infos 3la l'appartements ?"
            ],
            'jardin' => [
                'fr' => "Je comprends votre intérêt pour un jardin.\n\n" .
                       "GreenLand dispose d'un **patio paysager** mais pas de jardin individuel.\n\n" .
                       " GreenLand est un **groupe résidentiel** avec des appartements F3 et F4.\n" .
                       " Équipements disponibles : Padel, Sport, Patio, Parking, Ascenseur.\n\n" .
                       "Souhaitez-vous plus d'informations sur les appartements ?",
                'darija' => "  fhemt had l'envie dialek l'jardin.\n\n" .
                           "GreenLand 3ndo **patio paysager** walakin machi jardin individuel.\n\n" .
                           " GreenLand howa **groupe résidentiel** fih appartements F3 ou F4.\n" .
                           " L'équipements li 3ndna : Padel, Sport, Patio, Parking, Ascenseur.\n\n" .
                           "Wach baghi t'khoud I'infos 3la l'appartements ?"
            ]
        ];

        $default = [
            'fr' => "🏠 Je comprends votre demande.\n\n" .
                    "GreenLand est un **groupe résidentiel** composé uniquement d'appartements F3 et F4.\n\n" .
                    " **Typologies :**\n" .
                    "   • F3 : 2 chambres + salon + 2 salles de bains (83 à 123 m²)\n" .
                    "   • F4 : 3 chambres + salon + 2 salles de bains (97 à 130 m²)\n\n" .
                    " **Équipements disponibles :**\n" .
                    "   • Padel, Sport, Patio, Parking, Ascenseur\n\n" .
                    "Souhaitez-vous plus d'informations sur les appartements ?",
            'darija' => "🏠  fhemt had l'talab dialek.\n\n" .
                        "GreenLand howa **groupe résidentiel** fih ghir appartements F3 ou F4.\n\n" .
                        " **L'typologies :**\n" .
                        "   • F3 : 2 chambres + salon + 2 salles de bains (83 à 123 m²)\n" .
                        "   • F4 : 3 chambres + salon + 2 salles de bains (97 à 130 m²)\n\n" .
                        " **L'équipements li 3ndna :**\n" .
                        "   • Padel, Sport, Patio, Parking, Ascenseur\n\n" .
                        "Wach baghi t'khoud I'infos 3la l'appartements ?"
        ];

        $response = $translations[$requested][$lang] ?? $default[$lang] ?? $default['fr'];

        return $response;
    }

    /*
    |--------------------------------------------------------------------------
    | QUESTIONS GÉNÉRALES
    |--------------------------------------------------------------------------
    */

    private function handleGeneralQuestion(string $message): ?string
    {
        $lower = $this->normalize($message);
        $lang = $this->detectLanguage($message);

        if (mb_stripos($lower, 'information') !== false ||
            mb_stripos($lower, 'info') !== false ||
            mb_stripos($lower, 'demander') !== false ||
            mb_stripos($lower, 'demande') !== false ||
            mb_stripos($lower, 'renseignement') !== false ||
            mb_stripos($lower, 'savoir') !== false) {

            $response = "🏢 **PROJET GREENLAND - CASABLANCA**\n\n" .
                        "GreenLand est un **groupe résidentiel fermé et sécurisé** " .
                        "situé à **SIDI MESSOUD**, entre Californie et la ville verte, " .
                        "à proximité immédiate de l'entrée d'autoroute A3.\n\n" .
                        "📐 **Typologies disponibles :**\n" .
                        "   • **F3** : 2 chambres + salon + 2 salles de bains (83 à 123 m²)\n" .
                        "   • **F4** : 3 chambres + salon + 2 salles de bains (97 à 130 m²)\n\n" .
                        "🏋️ **Équipements :** Padel, Salle de sport, Patio paysager, " .
                        "Parking souterrain, Ascenseur OTIS\n\n" .
                        "📅 **Livraison :** Mars 2027\n" .
                        "**Quel type d'appartement vous intéresse (F3 ou F4) ?** 😊";

            return $this->translateResponse($response, $lang);
        }

        if (mb_stripos($lower, 'localisation') !== false ||
            mb_stripos($lower, 'adresse') !== false ||
            mb_stripos($lower, 'fin kayn') !== false ||
            mb_stripos($lower, 'ou se trouve') !== false) {

            $response = "📍 **Localisation GreenLand :**\n\n" .
                        "🏠 SIDI MESSOUD, entre Californie et la ville verte,\n" .
                        "🚗 À proximité immédiate de l'entrée d'autoroute A3.\n\n" .
                        "📌 Un emplacement stratégique alliant calme et accessibilité.";
            return $this->translateResponse($response, $lang);
        }

        if (mb_stripos($lower, 'équipement') !== false ||
            mb_stripos($lower, 'equipement') !== false ||
            mb_stripos($lower, 'padel') !== false ||
            mb_stripos($lower, 'sport') !== false ||
            mb_stripos($lower, 'parking') !== false ||
            mb_stripos($lower, 'ascenseur') !== false) {

            $response = "🏋️ **Équipements GreenLand :**\n\n" .
                        "🎾 Deux terrains de Padel\n" .
                        "🏋️ Une salle de sport\n" .
                        " Un patio paysager\n" .
                        "🅿️ Parking souterrain\n" .
                        "🛗 Ascenseur OTIS\n\n" .
                        "✨ Une architecture maîtrisée : six immeubles en R+4, un patio central propice à la convivialité.";
            return $this->translateResponse($response, $lang);
        }

        if (mb_stripos($lower, 'livraison') !== false ||
            mb_stripos($lower, 'date livraison') !== false ||
            mb_stripos($lower, 'mars') !== false ||
            mb_stripos($lower, 'delai') !== false ||
            mb_stripos($lower, 'quand') !== false) {

            $response = "📅 **Date de livraison GreenLand :**\n\n" .
                        " Le projet est déjà construit et entre dans ses dernières étapes de finition.\n" .
                        "📆 Livraison prévue : **Mars 2027**\n\n" .
                        "✅ Une résidence concrète et tangible, dont la livraison approche — pour une acquisition en toute confiance.";
            return $this->translateResponse($response, $lang);
        }

        if (mb_stripos($lower, 'description') !== false ||
            mb_stripos($lower, 'details') !== false ||
            mb_stripos($lower, 'detail') !== false ||
            mb_stripos($lower, 'info') !== false ||
            mb_stripos($lower, 'infos') !== false) {

            $response = "🏠 **Description GreenLand :**\n\n" .
                        "GreenLand est un groupe résidentiel fermé et sécurisé qui bénéficie d'un environnement calme et proche des commodités essentielles.\n\n" .
                        " Six immeubles en R+4\n" .
                        " Un patio central propice à la convivialité\n" .
                        "🅿️ Parking souterrain\n" .
                        "🛗 Ascenseur OTIS\n\n" .
                        "📅 Livraison : Mars 2027\n" .
                        "📍 SIDI MESSOUD, entre Californie et la ville verte";
            return $this->translateResponse($response, $lang);
        }

        if (mb_stripos($lower, 'contact') !== false ||
            mb_stripos($lower, 'téléphone') !== false ||
            mb_stripos($lower, 'numero') !== false ||
            mb_stripos($lower, 'appel') !== false ||
            mb_stripos($lower, 'commercial') !== false) {

            $response = "📞 **Contacts GreenLand :**\n\n" .
                        "• Mr Oussama : 212660446758\n" .
                        "• Mr Maghraoui : 212660446758\n\n" .
                        "📧 Contactez-nous pour toute question ou visite.";
            return $this->translateResponse($response, $lang);
        }

        if (mb_stripos($lower, 'horaire') !== false ||
            mb_stripos($lower, 'ouverture') !== false ||
            mb_stripos($lower, 'fermeture') !== false) {

            $response = "🕐 **Horaires GreenLand :**\n\n" .
                        "📅 7j/7, de 10h à 18h.\n\n" .
                        "📍 Visites sur rendez-vous.";
            return $this->translateResponse($response, $lang);
        }

        if (mb_stripos($lower, 'surface') !== false ||
            mb_stripos($lower, 'superficie') !== false ||
            mb_stripos($lower, 'metre') !== false ||
            mb_stripos($lower, 'm2') !== false ||
            mb_stripos($lower, 'm²') !== false ||
            mb_stripos($lower, 'taille') !== false) {

            $response = " **Surfaces GreenLand :**\n\n" .
                        " **Typologies :**\n" .
                        "   • F3 : **83 à 123 m²** (2 chambres + salon + 2 salles de bains)\n" .
                        "   • F4 : **97 à 130 m²** (3 chambres + salon + 2 salles de bains)\n\n" ;
            return $this->translateResponse($response, $lang);
        }

        if (mb_stripos($lower, 'typologie') !== false ||
            mb_stripos($lower, 'type') !== false ||
            mb_stripos($lower, 'f3') !== false ||
            mb_stripos($lower, 'f4') !== false ||
            mb_stripos($lower, 'chambre') !== false ||
            mb_stripos($lower, 'salon') !== false) {

            $response = " **Typologies GreenLand :**\n\n" .
                        " **F3 :**\n" .
                        "   • Composition : 2 chambres + salon + 2 salles de bains\n" .
                        "   • Surface : 83 à 123 m²\n\n" .
                        " **F4 :**\n" .
                        "   • Composition : 3 chambres + salon + 2 salles de bains\n" .
                        "   • Surface : 97 à 130 m²\n\n" .
                        "Quel type vous intéresse ? Nous pourrons ensuite discuter des prix.";
            return $this->translateResponse($response, $lang);
        }

        if (mb_stripos($lower, 'prix') !== false ||
            mb_stripos($lower, 'chhal') !== false ||
            mb_stripos($lower, 'combien') !== false ||
            mb_stripos($lower, 'tarif') !== false ||
            mb_stripos($lower, 'cout') !== false ||
            mb_stripos($lower, 'coût') !== false) {

            $response = "📊 **Prix GreenLand :**\n\n" .
                        " **Typologies :**\n" .
                        "   • F3 (83 à 123 m²) \n" .
                        "   • F4 (97 à 130 m²) \n\n" .
                        "📌 Prix indicatifs selon étage, vue et orientation.\n\n" .
                        "Quel type vous intéresse ?";
            return $this->translateResponse($response, $lang);
        }

        if (mb_stripos($lower, 'etat') !== false ||
            mb_stripos($lower, 'état') !== false ||
            mb_stripos($lower, 'construction') !== false ||
            mb_stripos($lower, 'avancement') !== false) {

            $response = " **État du projet GreenLand :**\n\n" .
                        "Le projet est déjà construit et entre dans ses dernières étapes de finition.\n\n" .
                        "✅ Une résidence concrète et tangible, dont la livraison approche — pour une acquisition en toute confiance.\n" .
                        "📅 Livraison prévue : **Mars 2027**";
            return $this->translateResponse($response, $lang);
        }

        return null;
    }

    private function handleQuestion(string $message): string
    {
        $lower = $this->normalize($message);
        $lang = $this->detectLanguage($message);

        $outOfProject = $this->detectOutOfProject($message);
        if ($outOfProject !== null) {
            return $this->outOfProjectResponse($outOfProject);
        }
        $generalResponse = $this->handleGeneralQuestion($message);
        if ($generalResponse !== null) {
            return $generalResponse;
        }

        $response = "Bien sûr  Je peux vous renseigner sur GreenLand :\n" .
                    "• 📍 Localisation : SIDI MESSOUD\n" .
                    "•  Typologies : F3 et F4\n" .
                    "•  Surfaces : 83 à 130 m²\n" .
                    "•  Équipements : Padel, Sport, Patio, Parking\n" .
                    "• 📅 Livraison : Mars 2027\n\n" .
                    "Que souhaitez-vous savoir exactement ?";

        return $this->translateResponse($response, $lang);
    }

    /*
    |--------------------------------------------------------------------------
    | GREETING
    |--------------------------------------------------------------------------
    */
    private function greetingResponse(): string
    {
        $this->conversationState['first_message_done'] = true;
        $this->conversationState['greeting_done'] = true;
        $this->conversationState['welcome_sent'] = true;
        $this->conversationState['conversation_stage'] = 'greeting';
        $this->conversationState['last_question_type'] = 'greeting';

        $lastMessage = $this->conversationState['last_user_message'] ?? 'salam';
        $lang = $this->detectLanguage($lastMessage);

        if ($lang === 'fr') {
            return "Bonjour 😊 Bienvenue chez Greenland. Comment puis-je vous aider ?";
        }

        return "Salam 😊 Marhba bik m3a Greenland. Comment puis-je vous aider ?";
    }

    /*
    |--------------------------------------------------------------------------
    | NAME
    |--------------------------------------------------------------------------
    */

    private function isValidName(string $candidate): bool
    {
        $candidate = trim($candidate);
        $length = mb_strlen($candidate);

        if ($length < 2 || $length > 60) {
            return false;
        }

        if (!preg_match('/^[a-zA-ZÀ-ÿ\s\-\'\.]+$/', $candidate)) {
            return false;
        }

        $normalized = $this->normalize($candidate);
        $negations = ['non', 'no', 'nan', 'la', 'laa', 'laaa', 'n', 'nn', 'nnn', 'nnnn'];
        if (in_array($normalized, $negations, true)) {
            return false;
        }

        $greetings = ['salam', 'slm', 'bonjour', 'salut', 'hello', 'marhba', 'marhaba', 'ahlan'];
        if (in_array($normalized, $greetings, true)) {
            return false;
        }

        $reserved = ['oui', 'ok', 'okay', 'daccord', 'yes', 'non', 'no', 'bonjour', 'salam', 'salut', 'slm', 'wakha', 'bghit', 'brit', 'la', 'laa', 'laaa'];
        if (in_array($normalized, $reserved, true)) {
            return false;
        }

        if (strpos($candidate, ' ') !== false) {
            $parts = explode(' ', $candidate);
            foreach ($parts as $part) {
                if (mb_strlen($part) < 2) {
                    return false;
                }
                if (!preg_match('/^[a-zA-ZÀ-ÿ\s\-\'\.]+$/', $part)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function formatName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/[^a-zA-ZÀ-ÿ\s\-\'\.]/', '', $name);

        if (strtoupper($name) === $name) {
            $name = ucfirst(mb_strtolower($name));
        }

        $name = preg_replace('/\s+/', ' ', $name);

        $parts = explode(' ', $name);
        $formatted = [];
        foreach ($parts as $part) {
            if (!empty($part) && mb_strlen($part) > 1) {
                $formatted[] = ucfirst(mb_strtolower($part));
            }
        }

        if (count($formatted) === 1) {
            return $formatted[0];
        }

        return implode(' ', $formatted);
    }

   private function extractNameWithAI(string $message): ?string
{
    if (!$this->apiKey) {
        return null;
    }

    $trimmed = trim($message);
    $lower = mb_strtolower($trimmed);

    // ✅ FILTRE : Si le message contient des mots-clés de demande d'info, ce n'est PAS un nom
    $infoKeywords = [
        'information', 'informations', 'info', 'infos',
        'demande', 'demander', 'savoir', 'connaitre', 'connaître',
        'donne', 'donner', '3tini', '3tina', 'a3tini',
        'sur', 'about', 'concernant', 'propos',
        'f3', 'f4', 'appartement', 'prix', 'surface',
        'localisation', 'adresse', 'equipement', 'équipement',
        'livraison', 'horaire', 'contact', 'projet'
    ];
    foreach ($infoKeywords as $kw) {
        if (mb_stripos($lower, $kw) !== false) {
            Log::info('⏭️ extractNameWithAI: message contient mot-clé info', ['message' => $message]);
            return null;
        }
    }

    // ✅ FILTRE : Si le message contient plus de 4 mots, ce n'est probablement pas un nom
    $wordCount = count(preg_split('/\s+/', $trimmed));
    if ($wordCount > 4) {
        Log::info('⏭️ extractNameWithAI: message trop long (>4 mots)', ['message' => $message]);
        return null;
    }

    // ✅ FILTRE : Si le message contient un chiffre, ignorer
    if (preg_match('/\d/', $trimmed)) {
        return null;
    }

    // ✅ FILTRE : Négations et réponses courtes
    $shortAnswers = [
        'oui', 'non', 'ok', 'okay', 'yes', 'no', 'nan', 'la', 'laa', 'laaa',
        'wakha', 'bghit', 'brit', 'wah', 'saha', 'mzyan', 'daccord', 'dac',
        'merci', 'svp', 'stp', 'bonjour', 'salam', 'salut', 'hello', 'slm',
        'ah', 'wa', 'walo', 'hada', 'hadak', 'tayara', 'wakh'
    ];
    if (in_array($lower, $shortAnswers, true)) {
        return null;
    }

    $prompt = "Tu es un assistant qui extrait les noms des messages.

Message: \"$message\"

Instructions IMPORTANTES:
1. Extrais le nom de la personne UNIQUEMENT si c'est clairement un nom.
2. Le nom peut être en français, en darija, ou dans n'importe quelle langue.
3. Le nom peut avoir des fautes d'orthographe (ex: FADAWI au lieu de FADWA).
4. Le nom peut être un prénom seul ou un nom complet (max 3 mots).
5. Si le message contient \"je m'appelle\", \"mon nom est\", \"ismi\", \"ism dyali\", le nom est après.
6. Si le message est juste un mot comme \"Ahmed\", \"Fatima\", \"Mohamed\", c'est un nom.
7. ⚠️ IGNORE les négations : \"non\", \"la\", \"mabghitch\"
8. ⚠️ IGNORE les salutations : \"salam\", \"bonjour\", \"salut\"
9. ⚠️ IGNORE les réponses : \"oui\", \"wakha\", \"bghit\", \"ok\", \"merci\"
10. ⚠️ IGNORE les numéros de téléphone, dates, heures
11. ⚠️ IGNORE les demandes d'information : \"3tini des informations sur f4\", \"je veux savoir\", \"info sur f3\"
12. ⚠️ IGNORE les phrases longues (> 4 mots) qui ne sont pas des noms

Réponds UNIQUEMENT au format JSON:
{\"name\": \"le nom extrait ou null\", \"confidence\": 0.9, \"explanation\": \"pourquoi\"}

Exemples:
- \"FADAWI\" → {\"name\": \"Fadawi\", \"confidence\": 0.95, \"explanation\": \"nom en majuscules\"}
- \"je m'appelle Ahmed\" → {\"name\": \"Ahmed\", \"confidence\": 0.99, \"explanation\": \"préfixe je m'appelle\"}
- \"mon nom est Fatima\" → {\"name\": \"Fatima\", \"confidence\": 0.99, \"explanation\": \"préfixe mon nom est\"}
- \"ismi Youssef\" → {\"name\": \"Youssef\", \"confidence\": 0.95, \"explanation\": \"préfixe ismi\"}
- \"salam\" → {\"name\": null, \"confidence\": 0.99, \"explanation\": \"salutation\"}
- \"non\" → {\"name\": null, \"confidence\": 0.99, \"explanation\": \"négation\"}
- \"oui\" → {\"name\": null, \"confidence\": 0.99, \"explanation\": \"réponse positive\"}
- \"06 96 63 38 82\" → {\"name\": null, \"confidence\": 0.99, \"explanation\": \"numéro de téléphone\"}
- \"3tini des informations sur f4\" → {\"name\": null, \"confidence\": 0.99, \"explanation\": \"demande d'information\"}
- \"je veux demander des informations\" → {\"name\": null, \"confidence\": 0.99, \"explanation\": \"demande d'information\"}
- \"Tini des informations sur\" → {\"name\": null, \"confidence\": 0.99, \"explanation\": \"demande d'information\"}

Réponds UNIQUEMENT en JSON, sans autre texte.";

    try {
        $response = Http::timeout(10)
            ->withToken($this->apiKey)
            ->acceptJson()
            ->post(
                'https://openrouter.ai/api/v1/chat/completions',
                [
                    'model' => $this->model,
                    'messages' => [
                        ['role' => 'system', 'content' => 'Tu extrais des noms. Réponds UNIQUEMENT en JSON.'],
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    'temperature' => 0.1,
                    'max_tokens' => 150,
                ]
            );

        if ($response->successful()) {
            $json = $response->json();
            $content = $json['choices'][0]['message']['content'] ?? '';

            if (preg_match('/\{[^{}]*\}/', $content, $matches)) {
                $result = json_decode($matches[0], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $name = $result['name'] ?? null;
                    $confidence = $result['confidence'] ?? 0;

                    Log::info('IA a extrait un nom', [
                        'name' => $name,
                        'confidence' => $confidence,
                        'explanation' => $result['explanation'] ?? '',
                        'original' => $message
                    ]);

                    // ✅ Vérifier que c'est un nom valide
                    if ($name !== null && $confidence > 0.7 && mb_strlen($name) >= 2 && mb_strlen($name) <= 30) {
                        // ✅ Filtrer les noms suspects
                        $nameLower = mb_strtolower($name);
                        $forbidden = ['tini', 'donne', 'information', 'informations', 'info', 'infos'];
                        foreach ($forbidden as $word) {
                            if (mb_stripos($nameLower, $word) !== false) {
                                return null;
                            }
                        }
                        return $this->formatName($name);
                    }
                }
            }
        }
    } catch (\Throwable $e) {
        Log::warning('Erreur IA pour extraire le nom', ['error' => $e->getMessage()]);
    }

    return null;
}

    private function extractName(string $message): ?string
    {
        $message = trim($message);

        if (empty($message)) {
            return null;
        }

        if ($this->apiKey) {
            $aiName = $this->extractNameWithAI($message);
            if ($aiName !== null) {
                Log::info('✅ Nom extrait par IA', ['name' => $aiName, 'original' => $message]);
                return $aiName;
            }
        }

        if (preg_match('/(?:mon nom|nom)\s*(?:est|:)?\s*([a-zA-ZÀ-ÿ][a-zA-ZÀ-ÿ\' -]{1,60})/iu', $message, $matches)) {
            $name = trim($matches[1]);
            if (mb_strlen($name) >= 2 && mb_strlen($name) <= 60) {
                if (!$this->isNegative($name) && !$this->isPositive($name)) {
                    return $this->formatName($name);
                }
            }
        }

        if (preg_match('/(?:je m\'appelle|je m’appelle|moi c’est|moi cest|ism dyali|ismi)\s*[:\-]?\s*([a-zA-ZÀ-ÿ][a-zA-ZÀ-ÿ\' -]{1,60})/iu', $message, $matches)) {
            $name = trim($matches[1]);
            if (mb_strlen($name) >= 2 && mb_strlen($name) <= 60) {
                if (!$this->isNegative($name) && !$this->isPositive($name)) {
                    return $this->formatName($name);
                }
            }
        }

        if (preg_match('/^\s*(?:MON\s+NOM\s+EST|NOM\s+EST|JE\s+M\'APPELLE)\s+([A-ZÀ-ÿ][A-ZÀ-ÿ\' -]{1,60})/iu', $message, $matches)) {
            $name = trim($matches[1]);
            if (mb_strlen($name) >= 2 && mb_strlen($name) <= 60) {
                if (!$this->isNegative($name) && !$this->isPositive($name)) {
                    return $this->formatName($name);
                }
            }
        }

        if ($this->conversationState['conversation_stage'] === 'visit_name') {
            $candidate = trim($message);

            if ($this->isExactNegation($candidate) || $this->isExactPositive($candidate)) {
                return null;
            }

            if (preg_match('/^[0-9+\s\-\.()]+$/', $candidate)) {
                return null;
            }

            if (preg_match('/^\d{1,2}[\/\-]\d{1,2}/', $candidate)) {
                return null;
            }

            if (preg_match('/^\d{1,2}h/', $candidate)) {
                return null;
            }

            if (preg_match('/^[a-zA-ZÀ-ÿ\s\-\'\.]+$/', $candidate) && mb_strlen($candidate) >= 2 && mb_strlen($candidate) <= 30) {
                $normalized = $this->normalize($candidate);
                $reserved = ['oui', 'ok', 'okay', 'daccord', 'yes', 'non', 'no', 'bonjour', 'salam', 'salut', 'slm', 'wakha', 'bghit', 'brit', 'la', 'laa', 'laaa'];
                if (!in_array($normalized, $reserved, true)) {
                    return $this->formatName($candidate);
                }
            }

            $aiName = $this->extractNameWithAI($message);
            if ($aiName !== null) {
                return $aiName;
            }

            if (mb_strlen($candidate) >= 2 && mb_strlen($candidate) <= 30) {
                if (!preg_match('/^[0-9+\s\-\.()]+$/', $candidate)) {
                    $normalized = $this->normalize($candidate);
                    $reserved = ['oui', 'ok', 'okay', 'daccord', 'yes', 'non', 'no', 'bonjour', 'salam', 'salut', 'slm', 'wakha', 'bghit', 'brit', 'la', 'laa', 'laaa'];
                    if (!in_array($normalized, $reserved, true)) {
                        return $this->formatName($candidate);
                    }
                }
            }
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | EXACT NEGATION / POSITIVE
    |--------------------------------------------------------------------------
    */

    private function isExactNegation(string $message): bool
    {
        $message = trim($message);
        $lower = mb_strtolower($message);

        $exactNegations = [
            'non', 'no', 'nan', 'la', 'laa', 'laaa', 'n', 'nn', 'nnn', 'nnnn',
            'non merci', 'non svp', 'pas', 'pas maintenant', 'plus tard',
            'la hadi', 'la bghitch', 'mabghitch', 'ma bghitch',
            'la chwiya', 'la hada', 'la hadik', 'la walo',
            'ma9blch', 'ma9belch', 'machi', 'machi hadi'
        ];

        if (in_array($lower, $exactNegations, true)) {
            return true;
        }

        $darijaNegations = ['la', 'laa', 'laaa', 'machi', 'mabghitch', 'ma bghitch'];
        foreach ($darijaNegations as $neg) {
            if ($lower === $neg) {
                return true;
            }
        }

        return false;
    }

    private function isExactPositive(string $message): bool
    {
        $message = trim($message);
        $lower = mb_strtolower($message);

        $exactPositives = [
            'oui', 'yes', 'ok', 'okay', 'daccord', 'dac', 'dak',
            'wakha', 'bghit', 'brit', 'wah', 'saha', 'mzyan',
            'tayara', 'wa', 'walo', 'walah', 'hay', 'haya'
        ];

        if (in_array($lower, $exactPositives, true)) {
            return true;
        }

        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | POSITIVE / NEGATIVE
    |--------------------------------------------------------------------------
    */

    private function isPositive(string $message): bool
    {
        $lower = $this->normalize($message);
        $lower = trim(preg_replace('/[.!]+$/u', '', $lower));

        $positive = [
            'oui', 'yes', 'ok', 'okay', 'daccord', 'd accord', 'dac', 'dak',
            'avec plaisir', 'bien sur', 'vas y', 'vasy', 'go', 'ye', 'ah oui',
            'oui bien sur', 'oui svp', 'oui stp', 'stp', 'svp', 'je veux',
            'je veux bien', 'je veux visiter', 'je veux une visite',
            'parfait', 'tres bien', 'tres bien',
            'brit', 'bghit', 'bghit nzour', 'bghit nvisiti', 'bghit nchouf',
            'ah', 'wah', 'wah bghit', 'wah hadi', 'hada', 'hadak',
            'wakha', 'wakha hadi', 'wakha bghit', 'nchouf', 'nchof',
            'visite', 'visiter', 'nzour', 'nvisiti', 'nchoufo',
            'wa', 'walo', 'walah', 'walah bghit',
            'saha', 'saha hadi', 'mzyan', 'mzyan hadi',
            'tayara', 'tayara hadi', 'tayara bghit',
            'ou', 'oui', 'wahdo', 'wahdo bghit',
            'hay', 'haya', 'hay bghit', 'hay nzour',
            'ela', 'ela bghit', 'ela nzour', 'ela hadi',
            'nchouf', 'nchof', 'nzour', 'nvisiti',
            'bghit nchouf', 'bghit nchof', 'bghit nzour',
            'wakh', 'wakha', 'wkha', 'wka', 'done'
        ];

        foreach ($positive as $word) {
            if ($lower === $word || mb_stripos($lower, $word) !== false) {
                return true;
            }
        }

        $positivePhrases = [
            'je veux une visite', 'je veux visiter',
            'bghit nzour', 'bghit nvisiti', 'bghit nchouf',
            'nchouf le projet', 'nchof le bien',
            'wah bghit', 'wakha hadi',
            'a quel moment', 'les horaires',
            'wakha nzour', 'wakha nvisiti'
        ];

        foreach ($positivePhrases as $phrase) {
            if (mb_stripos($lower, $phrase) !== false) {
                return true;
            }
        }

        return false;
    }

    private function isNegativeWithAI(string $message): bool
    {
        if (!$this->apiKey) {
            return $this->isNegative($message);
        }

        $message = trim($message);
        $lower = mb_strtolower($message);

        $obviousNegations = ['non', 'no', 'nan', 'la', 'laa', 'laaa', 'n', 'nn', 'nnn'];
        if (in_array($lower, $obviousNegations, true)) {
            return true;
        }

        $prompt = "Tu es un assistant qui analyse le sentiment des messages pour détecter les négations.

Message: \"$message\"

Instructions:
1. Analyse le SENS du message, pas seulement les mots.
2. Détermine si le message exprime un refus, un non, une négation.
3. Prends en compte le contexte et l'intention.
4. Exemples:
   - \"non je ne veux pas\" → négation
   - \"pas maintenant\" → négation
   - \"non merci\" → négation
   - \"je ne suis pas intéressé\" → négation
   - \"peut-être plus tard\" → négation
   - \"je veux visiter\" → POSITIF
   - \"oui je veux bien\" → POSITIF
   - \"d'accord\" → POSITIF
   - \"je vais réfléchir\" → négation
   - \"c'est trop cher pour moi\" → négation
   - \"j'ai un budget de 1 million\" → POSITIF
   - \"je cherche un F3\" → POSITIF

5. IMPORTANT: Un message qui donne une information n'est PAS une négation.

Réponds UNIQUEMENT au format JSON:
{\"is_negative\": true/false, \"confidence\": 0.9, \"explanation\": \"explication courte\"}

Réponds UNIQUEMENT en JSON, sans autre texte.";

        try {
            $response = Http::timeout(10)
                ->withToken($this->apiKey)
                ->acceptJson()
                ->post(
                    'https://openrouter.ai/api/v1/chat/completions',
                    [
                        'model' => $this->model,
                        'messages' => [
                            ['role' => 'system', 'content' => 'Tu es un assistant qui analyse les messages pour détecter les négations. Réponds UNIQUEMENT en JSON.'],
                            ['role' => 'user', 'content' => $prompt]
                        ],
                        'temperature' => 0.1,
                        'max_tokens' => 150,
                    ]
                );

            if ($response->successful()) {
                $json = $response->json();
                $content = $json['choices'][0]['message']['content'] ?? '';

                if (preg_match('/\{[^{}]*\}/', $content, $matches)) {
                    $result = json_decode($matches[0], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $isNegative = $result['is_negative'] ?? false;
                        $confidence = $result['confidence'] ?? 0;

                        Log::info('IA a détecté une négation', [
                            'message' => $message,
                            'is_negative' => $isNegative,
                            'confidence' => $confidence,
                            'explanation' => $result['explanation'] ?? ''
                        ]);

                        if ($confidence > 0.6) {
                            return $isNegative;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Erreur IA pour détecter la négation', ['error' => $e->getMessage()]);
        }

        return $this->isNegative($message);
    }

    private function isNegative(string $message): bool
    {
        if ($this->apiKey) {
            $aiResult = $this->isNegativeWithAI($message);
            if ($aiResult !== null) {
                return $aiResult;
            }
        }

        $lower = $this->normalize($message);
        $lower = trim(preg_replace('/[.!]+$/u', '', $lower));

        $original = trim($message);
        $lowerOriginal = mb_strtolower($original, 'UTF-8');

        $negative = [
            'non', 'no', 'nan', 'pas', 'pas maintenant', 'plus tard',
            'je ne veux pas', 'non merci', 'non svp',
            'sans', 'sans visite', 'pas de visite',
            'NON', 'Non', 'nON', 'nOn',
            'n', 'nn', 'nnn', 'nnnn',
            'la', 'laah', 'la hadi', 'la bghitch', 'mabghitch',
            'ma bghitch', 'mabritch', 'ma britch', 'la chwiya',
            'la hada', 'la hadik', 'la walo', 'ma9blch',
            'ma9belch', 'ma n9belch', 'ma n9balch', 'machi hadi',
            'machi hada', 'machi hadik', 'machi walo', 'la hado',
            'ma bghitch nzour', 'ma bghitch nvisiti', 'ma bghitch nchouf',
            'ma nzourch', 'ma nvisitich', 'ma nchoufch',
            'la wakha', 'wakha la', 'wakha bghit la',
            'salamo', 'salamo la', 'salamo bghit la',
            'ma9bel', 'ma9balch', 'machich', 'machich hadi',
            'laa', 'laaa', 'c est trop cher', 'trop cher',
            'je ne suis pas intéressé', 'pas intéressé',
            'je vais réfléchir', 'je réfléchis',
            'peut-être plus tard', 'plus tard'
        ];

        foreach ($negative as $word) {
            if ($lowerOriginal === $word ||
                mb_stripos($lowerOriginal, $word) !== false ||
                $lower === $word ||
                mb_stripos($lower, $word) !== false) {
                return true;
            }
        }

        if (preg_match('/^n+$/i', $original)) {
            return true;
        }

        if (preg_match('/^la+$/i', $original)) {
            return true;
        }

        if (preg_match('/^(NON|non|NoN|nOn)$/', $original)) {
            return true;
        }

        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | DATE EXTRACTION
    |--------------------------------------------------------------------------
    */

    private function calculateNextDayDate(int $targetDay): string
    {
        $today = date('N');
        $daysToAdd = ($targetDay - $today + 7) % 7;

        if ($daysToAdd === 0) {
            $daysToAdd = 7;
        }

        $date = date('d/m/Y', strtotime('+' . $daysToAdd . ' days'));
        Log::info('Date calculée', [
            'target_day' => $targetDay,
            'today' => $today,
            'days_to_add' => $daysToAdd,
            'date' => $date
        ]);

        return $date;
    }

    private function extractDateWithAI(string $message): ?string
{
    if (!$this->apiKey) {
        return null;
    }

    // ✅ FILTRE : Si le message ne contient AUCUN mot-clé de date, ne pas appeler l'IA
    $lower = $this->normalize($message);
    $dateKeywords = [
        'demain', 'demin', 'ghda', 'ghadda', 'apres', 'après',
        'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche',
        'semaine', 'week', 'weekend', 'mois', 'janvier', 'fevrier', 'mars',
        'avril', 'mai', 'juin', 'juillet', 'aout', 'septembre', 'octobre',
        'novembre', 'decembre', 'aujourd', 'today', 'lyoum',
        '/', '-', 'le ', 'dans '
    ];

    $hasDateKeyword = false;
    foreach ($dateKeywords as $kw) {
        if (mb_stripos($lower, $kw) !== false) {
            $hasDateKeyword = true;
            break;
        }
    }

    // ✅ Vérifier aussi si le message contient un chiffre (ex: 15, 12)
    if (!$hasDateKeyword && !preg_match('/\d/', $message)) {
        Log::info('⏭️ extractDateWithAI: aucun mot-clé de date détecté', ['message' => $message]);
        return null;
    }

    // ✅ NOUVEAU : Refuser les messages courts qui ne sont pas des dates
    $trimmed = trim($message);
    $negationsAndAnswers = [
        'oui', 'non', 'ok', 'okay', 'yes', 'no', 'nan', 'la', 'laa',
        'wakha', 'bghit', 'brit', 'wah', 'saha', 'mzyan', 'daccord',
        'merci', 'svp', 'stp', 'bonjour', 'salam', 'salut', 'hello',
        'ah', 'wa', 'walo', 'hada', 'hadak'
    ];
    if (in_array(mb_strtolower($trimmed), $negationsAndAnswers, true)) {
        Log::info('⏭️ extractDateWithAI: message ignoré (négation/réponse)', ['message' => $message]);
        return null;
    }

    // ✅ Si le message est trop court (< 3 caractères), ignorer
    if (mb_strlen($trimmed) < 3) {
        return null;
    }

    $today = date('d/m/Y');
    $tomorrow = date('d/m/Y', strtotime('+1 day'));
    $afterTomorrow = date('d/m/Y', strtotime('+2 days'));
    $nextWeek = date('N') === 1 ? date('d/m/Y', strtotime('+7 days')) : date('d/m/Y', strtotime('next monday'));

    $prompt = "Tu es un assistant qui comprend le langage naturel pour extraire des dates.

Message: \"$message\"

Aujourd'hui: $today
Demain: $tomorrow
Après-demain: $afterTomorrow
Lundi prochain: $nextWeek

Instructions IMPORTANTES:
1. Comprends le SENS du message, pas seulement les mots.
2. Le client peut écrire n'importe comment, avec des fautes d'orthographe.
3. Extrais la date mentionnée ou la date à laquelle le client fait référence.
4. ⚠️ SI LE MESSAGE NE CONTIENT AUCUNE DATE → retourne null
5. ⚠️ SI LE MESSAGE EST \"OUI\", \"NON\", \"OK\", \"MERCI\", \"BONJOUR\" → retourne null
6. ⚠️ NE PRENDS PAS la date d'aujourd'hui par défaut !

Réponds UNIQUEMENT au format JSON:
{\"date\": \"DD/MM/YYYY\" ou null, \"confidence\": 0.9, \"explanation\": \"explication courte\"}

Réponds UNIQUEMENT en JSON, sans autre texte.";

    try {
        $response = Http::timeout(15)
            ->withToken($this->apiKey)
            ->acceptJson()
            ->post(
                'https://openrouter.ai/api/v1/chat/completions',
                [
                    'model' => $this->model,
                    'messages' => [
                        ['role' => 'system', 'content' => 'Tu extrais des dates. Réponds UNIQUEMENT en JSON. Si pas de date → date: null'],
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    'temperature' => 0.1,
                    'max_tokens' => 200,
                ]
            );

        if ($response->successful()) {
            $json = $response->json();
            $content = $json['choices'][0]['message']['content'] ?? '';

            Log::info('📥 Réponse IA pour date', ['content' => $content]);

            if (preg_match('/\{[^{}]*\}/', $content, $matches)) {
                $result = json_decode($matches[0], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $date = $result['date'] ?? null;
                    $confidence = $result['confidence'] ?? 0;

                    // ✅ Si date est null ou vide → retourner null
                    if ($date === null || $date === '' || $date === 'null') {
                        return null;
                    }

                    if ($confidence > 0.7 && preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $date)) {
                        $parts = explode('/', $date);
                        if (count($parts) === 3 && checkdate((int)$parts[1], (int)$parts[0], (int)$parts[2])) {
                            return $date;
                        }
                    }
                }
            }
        }
    } catch (\Throwable $e) {
        Log::error('❌ Exception IA pour date', ['error' => $e->getMessage()]);
    }

    return null;
}

    private function extractDate(string $message): ?string
    {
        $lower = $this->normalize($message);
        $original = trim($message);

        Log::info('Extraction date - Début', ['message' => $message, 'lower' => $lower]);

        if ($this->apiKey) {
            $aiDate = $this->extractDateWithAI($message);
            if ($aiDate !== null) {
                Log::info('✅ Date extraite par IA', ['date' => $aiDate, 'original' => $message]);
                return $aiDate;
            }
        }

        $tomorrowVariants = [
            'demain', 'demin', 'demain matin', 'demain apres midi', 'demain soir',
            'ghda', 'ghadda', 'ghada', 'gheda', 'nhar ghda',
            'tomorrow', 'tomorow', 'tommorow',
            'le lendemain', 'lendemain', 'dem1', 'dem1n'
        ];
        foreach ($tomorrowVariants as $variant) {
            if (mb_stripos($lower, $variant) !== false) {
                $date = date('d/m/Y', strtotime('+1 day'));
                Log::info('📅 Date extraite: DEMAIN', ['date' => $date]);
                return $date;
            }
        }

        $afterTomorrowVariants = [
            'apres demain', 'après demain', 'apres-demain', 'après-demain',
            'apresdemain', 'aprèsdemain', 'ba3d ghda', 'ba3d ghadda',
            'day after tomorrow', 'after tomorrow',
            'apres demin', 'apres dmain', 'apres deman'
        ];
        foreach ($afterTomorrowVariants as $variant) {
            if (mb_stripos($lower, $variant) !== false) {
                $date = date('d/m/Y', strtotime('+2 days'));
                Log::info('📅 Date extraite: APRÈS-DEMAIN', ['date' => $date]);
                return $date;
            }
        }

        $todayVariants = [
            'aujourdhui', 'aujourd\'hui', 'today', 'lyoum', 'had lyoum',
            'ce jour', 'cette journee', 'cette journée', 'auj', 'ajd'
        ];
        foreach ($todayVariants as $variant) {
            if (mb_stripos($lower, $variant) !== false) {
                $date = date('d/m/Y');
                Log::info('📅 Date extraite: AUJOURD\'HUI', ['date' => $date]);
                return $date;
            }
        }

        $daysOfWeek = [
            'lundi' => 1, 'mardi' => 2, 'mercredi' => 3, 'jeudi' => 4,
            'vendredi' => 5, 'samedi' => 6, 'dimanche' => 7
        ];

        foreach ($daysOfWeek as $dayName => $dayNumber) {
            if (preg_match('/' . $dayName . '\s*(prochain|prochaine|prochains|prochaines)/iu', $lower)) {
                return $this->calculateNextDayDate($dayNumber);
            }
            if (preg_match('/\b' . $dayName . '\b/iu', $lower)) {
                return $this->calculateNextDayDate($dayNumber);
            }
        }

        if (preg_match('/\b(\d{1,2}[\/\-]\d{1,2}(?:[\/\-]\d{2,4})?)\b/', $message, $matches)) {
            $parts = preg_split('/[\/\-]/', $matches[1]);
            if (count($parts) === 3) {
                if ((int)$parts[0] <= 31 && (int)$parts[1] <= 12) {
                    return $matches[1];
                }
            }
            return $matches[1];
        }

        $nextWeekVariants = [
            'semaine prochaine', 'la semaine prochaine', 'next week',
            'la semaine qui vient', 'semaine a venir'
        ];
        foreach ($nextWeekVariants as $variant) {
            if (mb_stripos($lower, $variant) !== false) {
                $date = date('N') === 1 ? date('d/m/Y', strtotime('+7 days')) : date('d/m/Y', strtotime('next monday'));
                Log::info('📅 Date extraite: SEMAINE PROCHAINE', ['date' => $date]);
                return $date;
            }
        }

        $weekendVariants = [
            'week end', 'weekend', 'week-end',
            'ce week end', 'ce weekend', 'ce week-end',
            'le week end prochain', 'le weekend prochain',
            'this weekend', 'next weekend'
        ];
        foreach ($weekendVariants as $variant) {
            if (mb_stripos($lower, $variant) !== false) {
                $date = date('N') === 6 ? date('d/m/Y', strtotime('+7 days')) : date('d/m/Y', strtotime('next saturday'));
                Log::info('📅 Date extraite: WEEK-END', ['date' => $date]);
                return $date;
            }
        }

        $monthNames = [
            'janvier' => 1, 'jan' => 1, 'janv' => 1,
            'fevrier' => 2, 'février' => 2, 'fev' => 2, 'fév' => 2,
            'mars' => 3, 'mar' => 3,
            'avril' => 4, 'avr' => 4,
            'mai' => 5,
            'juin' => 6, 'jun' => 6,
            'juillet' => 7, 'jui' => 7, 'jul' => 7,
            'aout' => 8, 'août' => 8, 'aou' => 8, 'aoû' => 8,
            'septembre' => 9, 'sep' => 9,
            'octobre' => 10, 'oct' => 10,
            'novembre' => 11, 'nov' => 11,
            'decembre' => 12, 'décembre' => 12, 'dec' => 12, 'déc' => 12
        ];

        foreach ($monthNames as $monthName => $monthNumber) {
            if (preg_match('/\b(\d{1,2})\s*(' . $monthName . ')\b/i', $message, $matches)) {
                $day = (int) $matches[1];
                $month = $monthNumber;
                $year = date('Y');
                if ($month < date('m') || ($month == date('m') && $day < date('d'))) {
                    $year++;
                }
                return sprintf('%02d/%02d/%04d', $day, $month, $year);
            }
            if (preg_match('/\b(\d{1,2})\s*(' . $monthName . ')\s*(\d{4})\b/i', $message, $matches)) {
                $day = (int) $matches[1];
                $month = $monthNumber;
                $year = (int) $matches[3];
                return sprintf('%02d/%02d/%04d', $day, $month, $year);
            }
        }

        if (preg_match('/\b(\d{1,2})\/(\d{1,2})\b/', $message, $matches)) {
            $day = (int) $matches[1];
            $month = (int) $matches[2];
            if ($day <= 31 && $month <= 12) {
                $year = date('Y');
                if ($month < date('m') || ($month == date('m') && $day < date('d'))) {
                    $year++;
                }
                return sprintf('%02d/%02d/%04d', $day, $month, $year);
            }
        }

        if (preg_match('/\b(\d{1,2})-(\d{1,2})\b/', $message, $matches)) {
            $day = (int) $matches[1];
            $month = (int) $matches[2];
            if ($day <= 31 && $month <= 12) {
                $year = date('Y');
                if ($month < date('m') || ($month == date('m') && $day < date('d'))) {
                    $year++;
                }
                return sprintf('%02d/%02d/%04d', $day, $month, $year);
            }
        }

        if (preg_match('/dans\s*(\d+)\s*(jour|jours)/iu', $lower, $matches)) {
            $days = (int) $matches[1];
            if ($days > 0 && $days <= 30) {
                return date('d/m/Y', strtotime('+' . $days . ' days'));
            }
        }

        if (preg_match('/le\s*(\d{1,2})\s*(?:de ce mois|de ce mois-ci)?/i', $message, $matches)) {
            $day = (int) $matches[1];
            $month = date('m');
            $year = date('Y');
            if ($day < date('d')) {
                $month++;
                if ($month > 12) {
                    $month = 1;
                    $year++;
                }
            }
            return sprintf('%02d/%02d/%04d', $day, $month, $year);
        }

        Log::warning('❌ Aucune date valide trouvée', ['message' => $message]);
        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | GREETING DETECTION
    |--------------------------------------------------------------------------
    */

    private function isGreetingMessage(string $message): bool
    {
        $lower = $this->normalize($message);

        $greetings = [
            'salam', 'salam alaykom', 'salam 3alaykom', 'bonjour', 'bonsoir', 'salut', 'hello',
            'slm', 'salamo', 'salamou', 'salam 3likom', 'salam alikom', 'salam alykom',
            'marhba', 'marhaba', 'ahlan', 'salem', 'slem', 'slemo'
        ];

        foreach ($greetings as $greeting) {
            if (preg_match('/^' . preg_quote($greeting, '/') . '\b/iu', $lower)) {
                return true;
            }
        }

        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | QUALIFICATION RESPONSE
    |--------------------------------------------------------------------------
    */

    private function qualificationResponse(): ?string
    {
        $state = $this->conversationState;

        if (empty($state['property_type'])) {
            $this->conversationState['conversation_stage'] = 'property_type';
            $this->conversationState['last_question_type'] = 'property_type';
            $this->conversationState['last_question'] = 'property_type';

            $response = " **Typologies GreenLand :**\n\n" .
                        " **F3 :**\n" .
                        "   • Composition : 2 chambres + salon + 2 salles de bains\n" .
                        "   • Surface : 83 à 123 m²\n\n" .
                        " **F4 :**\n" .
                        "   • Composition : 3 chambres + salon + 2 salles de bains\n" .
                        "   • Surface : 97 à 130 m²\n\n" .
                        "Quel type vous intéresse ?";

            $lang = $this->detectLanguage($state['last_user_message'] ?? 'fr');
            return $this->translateResponse($response, $lang);
        }

        if (!$state['budget_given_by_user'] || empty($state['budget'])) {
            $this->conversationState['conversation_stage'] = 'budget';
            $this->conversationState['last_question_type'] = 'budget';
            $this->conversationState['last_question'] = 'budget';

            $response = "Parfait  Vous êtes intéressé par un " . $state['property_type'] . ".\n\n" .
                        "💰 Quel budget avez-vous prévu pour votre appartement ?";

            $lang = $this->detectLanguage($state['last_user_message'] ?? 'fr');
            return $this->translateResponse($response, $lang);
        }

        $type = $state['property_type'];
        $budget = (int) $state['budget'];

        $this->conversationState['conversation_stage'] = 'visit_offer';
        $this->conversationState['last_question_type'] = 'visit_offer';
        $this->conversationState['last_question'] = 'visit';

        $response = "Merci  J'ai bien noté votre recherche d'un " .
            $state['property_type'] .
            " avec un budget de " .
            number_format((int) $state['budget'], 0, ',', ' ') .
            " DH.\n\n" .
            " Le projet GreenLand est déjà construit et entre dans ses dernières étapes de finition.\n\n" .
            "📅 Livraison prévue : Mars 2027\n\n" .
            "Souhaitez-vous que je transmette votre demande de visite à notre équipe ?";

        $lang = $this->detectLanguage($state['last_user_message'] ?? 'fr');
        return $this->translateResponse($response, $lang);
    }

    /*
    |--------------------------------------------------------------------------
    | HANDLE VISIT RESPONSE
    |--------------------------------------------------------------------------
    */

    private function handleVisitResponse(): array
    {
        if ($this->conversationState['completed']) {
            $answer = "Merci de nous avoir contactés \n\n" .
                      "Votre demande de visite a déjà été enregistrée.\n" .
                      "Notre équipe vous contactera très prochainement.\n\n" .
                      "📞 Pour toute urgence : " . $this->data['projet']['contact_agents'];
            $this->conversationState['last_bot_message'] = $answer;
            return $this->response($answer);
        }

        if (empty($this->conversationState['name'])) {
            $this->conversationState['conversation_stage'] = 'visit_name';
            $this->conversationState['last_question_type'] = 'visit_name';

            $lastMessage = $this->conversationState['last_user_message'] ?? '';

            if ($this->isPositive($lastMessage)) {
                $answer = "Parfait  Pour transmettre votre demande de visite à notre équipe commerciale, donnez-moi simplement votre nom.";
                $this->conversationState['last_bot_message'] = $answer;
                return $this->response($this->appendQuestion($answer, 'name'));
            }

            if ($this->isNegative($lastMessage)) {
                return $this->handleVisitRefusal();
            }

            $name = $this->extractName($lastMessage);

            if ($name === null) {
                $candidate = trim($lastMessage);
                $length = mb_strlen($candidate);

                if ($length >= 2 && $length <= 30) {
                    if (!preg_match('/^[0-9+\s\-\.()]+$/', $candidate) &&
                        !preg_match('/^\d{1,2}[\/\-]\d{1,2}/', $candidate) &&
                        !preg_match('/^\d{1,2}h/', $candidate) &&
                        !$this->isNegative($candidate) && !$this->isPositive($candidate)) {
                        $greetings = ['salam', 'slm', 'bonjour', 'salut', 'hello', 'marhba'];
                        $normalized = $this->normalize($candidate);
                        if (!in_array($normalized, $greetings, true)) {
                            $name = ucfirst(mb_strtolower($candidate));
                        }
                    }
                }
            }

            if ($name !== null && mb_strlen($name) >= 2) {
                $this->conversationState['name'] = $name;
                $this->conversationState['conversation_stage'] = 'visit_date';
                $this->conversationState['last_question_type'] = 'visit_date';

                $answer = "Merci {$name} \n\n" .
                          "📅 Quel jour vous conviendrait pour la visite ?\n" .
                          "Exemples : lundi, demain, 15/12, lundi prochain...";

                $this->conversationState['last_bot_message'] = $answer;
                return $this->response($this->appendQuestion($answer, 'date'));
            }

            $answer = "Désolé  Je n'ai pas bien compris votre nom.\n\n" .
                      "Pourriez-vous me donner votre nom ?\n" .
                      "Exemples : Ahmed, Fatima, Mohamed...";
            $this->conversationState['last_bot_message'] = $answer;
            return $this->response($this->appendQuestion($answer, 'name'));
        }

        if (empty($this->conversationState['appointment_date'])) {
            $this->conversationState['conversation_stage'] = 'visit_date';
            $this->conversationState['last_question_type'] = 'visit_date';

            $lastMessage = $this->conversationState['last_user_message'] ?? '';
            $date = $this->extractDate($lastMessage);

            if ($date !== null) {
                $this->conversationState['appointment_date'] = $date;
                $this->conversationState['completed'] = true;
                $this->conversationState['contact_sent'] = true;
                $this->conversationState['conversation_stage'] = 'sent';
                $this->conversationState['appointment_time'] = null;

                $this->trySendContact();

                $answer = "✅ Parfait {$this->conversationState['name']} \n\n" .
                          "📅 J'ai bien enregistré votre visite pour le **" . $date . "**.\n\n" .
                          "🏠 Un de nos commerciaux vous contactera très prochainement pour confirmer les détails.\n\n" .
                          "📞 Pour toute question : " . $this->data['projet']['contact_agents'] . "\n\n" .
                          "🙏 Merci de nous avoir contactés et à bientôt !";
                $this->conversationState['last_bot_message'] = $answer;
                // ✅ ICI : has_visit + has_name + has_date = true → appendQuestion ne rajoute rien
                return $this->response($this->appendQuestion($answer, 'default'));
            }

            if ($this->isPositive($lastMessage)) {
                $answer = "📅 Quel jour vous conviendrait pour la visite ?\n" .
                          "Exemples : lundi, demain, 15/12, lundi prochain...";
                $this->conversationState['last_bot_message'] = $answer;
                return $this->response($this->appendQuestion($answer, 'date'));
            }

            if ($this->isNegative($lastMessage)) {
                return $this->handleVisitRefusal();
            }

            $answer = "Désolé  Je n'ai pas bien compris la date.\n\n" .
                      "Veuillez me donner une date valide, par exemple :\n" .
                      "• lundi, mardi, mercredi...\n" .
                      "• demain, après-demain\n" .
                      "• 15/12, 15-12, 15 décembre\n" .
                      "• lundi prochain, semaine prochaine";
            $this->conversationState['last_bot_message'] = $answer;
            return $this->response($this->appendQuestion($answer, 'date'));
        }

        $this->conversationState['conversation_stage'] = 'sent';
        $this->trySendContact();
        $this->conversationState['completed'] = true;

        $date = $this->conversationState['appointment_date'] ?? '';

        $answer = "✅ Parfait {$this->conversationState['name']} \n\n" .
                  "📅 Visite enregistrée pour le **" . $date . "**.\n\n" .
                  "🏠 Notre équipe vous contactera pour confirmer les détails.\n\n" .
                  "🙏 Merci de nous avoir contactés et à bientôt !";

        $this->conversationState['last_bot_message'] = $answer;
        return $this->response($this->appendQuestion($answer, 'default'));
    }

    /*
    |--------------------------------------------------------------------------
    | HANDLE VISIT REFUSAL
    |--------------------------------------------------------------------------
    */

    private function handleVisitRefusal(): array
    {
        $this->conversationState['visit_accepted'] = false;
        $this->conversationState['visit_requested'] = false;
        $this->conversationState['wants_visit'] = false;
        $this->conversationState['commercial_contact_requested'] = false;
        $this->conversationState['last_message_was_confirmation'] = false;
        $this->conversationState['conversation_stage'] = 'sent';
        $this->conversationState['last_question_type'] = null;
        $this->conversationState['completed'] = true;

        $answer = "Pas de problème, pas d'obligation ! \n\n" .
            "Si vous changez d'avis, n'hésitez pas à nous contacter au :\n" .
            $this->data['projet']['contact_agents'] . "\n\n" .
            "📧 Ou envoyez-nous un message, nous vous répondrons avec plaisir.\n\n" .
            "🙏 Merci et à bientôt !";

        $this->conversationState['last_bot_message'] = $answer;
        $this->buildPendingContact();
        return $this->response($this->appendQuestion($answer, 'default'));
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE CONVERSATION STATE
    |--------------------------------------------------------------------------
    */

    private function updateConversationState(string $message): void
    {
        $this->conversationState['last_user_message'] = $message;
        $lower = $this->normalize($message);

        Log::info('updateConversationState - Début', [
            'message' => $message,
            'last_question_type' => $this->conversationState['last_question_type'],
            'current_stage' => $this->conversationState['conversation_stage'],
        ]);

        $propertyType = $this->extractPropertyType($message);
        if ($propertyType !== null) {
            $this->conversationState['property_type'] = $propertyType;
            Log::info('Type extrait', ['property_type' => $propertyType]);
        }

        $amount = $this->extractBudget($message);

        if ($amount !== null) {
            $type = $this->conversationState['property_type'] ?? null;

            if ($amount < 100000) {
                $this->conversationState['budget'] = null;
                $this->conversationState['budget_given_by_user'] = false;
                $this->conversationState['budget_invalid'] = true;
                $this->conversationState['budget_error'] = 'budget_trop_bas';
                $this->conversationState['conversation_stage'] = 'budget';
                $this->conversationState['last_question_type'] = 'budget';

                $this->conversationState['last_bot_message'] =
                    "Désolé  Le budget de " . number_format($amount, 0, ',', ' ') . " DH est trop bas.\n\n" .
                    "Veuillez réviser votre budget.";

                $this->buildPendingContact();
                Log::info('updateConversationState - Budget trop bas');
                return;
            }

            if ($type !== null && $type !== 'INVALID') {
                $this->conversationState['budget'] = $amount;
                $this->conversationState['budget_given_by_user'] = true;
                $this->conversationState['budget_invalid'] = false;
                $this->conversationState['budget_error'] = null;

                $this->conversationState['conversation_stage'] = 'visit_offer';
                $this->conversationState['last_question_type'] = 'visit_offer';
                $this->conversationState['visit_asked'] = true;
                $this->conversationState['visit_requested'] = true;
                $this->conversationState['wants_visit'] = true;

                $this->conversationState['last_bot_message'] =
                    "Merci  J'ai bien noté votre recherche d'un " . $type . " avec un budget de " . number_format($amount, 0, ',', ' ') . " DH.\n\n" .
                    "💰 Ce budget correspond bien à la fourchette de prix d'un " . $type . " à GreenLand.\n\n" .
                    " Le projet GreenLand est déjà construit et entre dans ses dernières étapes de finition.\n\n" .
                    "📅 Livraison prévue : Mars 2027\n\n" .
                    "Souhaitez-vous que je transmette votre demande de visite à notre équipe ?";

                $this->buildPendingContact();
                Log::info('updateConversationState - Budget valide pour le type');
                return;
            }

            $this->conversationState['budget'] = $amount;
            $this->conversationState['budget_given_by_user'] = true;
            $this->conversationState['budget_invalid'] = false;
            $this->conversationState['budget_error'] = null;
            $this->conversationState['conversation_stage'] = 'property_type';
            $this->conversationState['last_question_type'] = 'property_type';

            $this->conversationState['last_bot_message'] =
                "Parfait  J'ai bien enregistré votre budget de " . number_format($amount, 0, ',', ' ') . " DH.\n\n" .
                " **Typologies GreenLand :**\n\n" .
                " **F3 :**\n" .
                "   • Composition : 2 chambres + salon + 2 salles de bains\n" .
                "   • Surface : 83 à 123 m²\n\n" .
                " **F4 :**\n" .
                "   • Composition : 3 chambres + salon + 2 salles de bains\n" .
                "   • Surface : 97 à 130 m²\n\n" .
                "Quel type vous intéresse ?";

            $this->buildPendingContact();
            Log::info('updateConversationState - Budget enregistré, type inconnu');
            return;
        }

        if (mb_stripos($lower, 'habiter') !== false ||
            mb_stripos($lower, 'habite') !== false ||
            mb_stripos($lower, 'vivre') !== false ||
            mb_stripos($lower, 'residence') !== false) {
            $this->conversationState['purpose'] = 'Résidence principale';
        }

        if (mb_stripos($lower, 'investir') !== false ||
            mb_stripos($lower, 'investissement') !== false) {
            $this->conversationState['purpose'] = 'Investissement';
        }

        if (mb_stripos($lower, 'cash') !== false ||
            mb_stripos($lower, 'comptant') !== false) {
            $this->conversationState['payment_method'] = 'Cash / Comptant';
        }

        if (mb_stripos($lower, 'credit') !== false ||
            mb_stripos($lower, 'banque') !== false ||
            mb_stripos($lower, 'bancaire') !== false) {
            $this->conversationState['payment_method'] = 'Crédit bancaire';
        }

        if (mb_stripos($lower, 'tranche') !== false ||
            mb_stripos($lower, 'echelonne') !== false) {
            $this->conversationState['payment_method'] = 'Paiement par tranche';
        }

        $name = $this->extractName($message);
        if ($name !== null) {
            $this->conversationState['name'] = $name;
            Log::info('Nom extrait', ['name' => $name]);
        }

        $date = $this->extractDate($message);

        if ($date !== null && $this->conversationState['visit_accepted']) {
            $this->conversationState['appointment_date'] = $date;
            $this->conversationState['conversation_stage'] = 'visit_time';
            $this->conversationState['last_question_type'] = 'visit_time';

            Log::info('Date extraite', [
                'date' => $date,
                'message' => $message
            ]);

            $this->buildPendingContact();
            return;
        }

        $visitWords = [
            'visite', 'visiter', 'nchouf', 'nchof', 'voir le projet',
            'voir le bien', 'voir appartement', 'nzour', 'nvisiti',
            'bghit nzour', 'bghit nvisiti', 'bghit nchouf'
        ];

        foreach ($visitWords as $word) {
            if (mb_stripos($lower, $word) !== false) {
                $this->conversationState['wants_visit'] = true;
                $this->conversationState['visit_requested'] = true;
                break;
            }
        }

        if ($this->conversationState['last_question_type'] === 'visit_offer') {
            Log::info('Traitement réponse visite', [
                'message' => $message,
                'is_positive' => $this->isPositive($message),
                'is_negative' => $this->isNegative($message),
            ]);

            if ($this->isPositive($message)) {
                $this->conversationState['visit_accepted'] = true;
                $this->conversationState['wants_visit'] = true;
                $this->conversationState['visit_requested'] = true;
                $this->conversationState['commercial_contact_requested'] = true;
                $this->conversationState['last_message_was_confirmation'] = true;
                $this->conversationState['conversation_stage'] = 'visit_name';
                $this->conversationState['last_question_type'] = 'visit_name';

                Log::info('✅ Visite ACCEPTÉE');
                $this->buildPendingContact();
                return;
            }

            if ($this->isNegative($message)) {
                $this->conversationState['visit_accepted'] = false;
                $this->conversationState['visit_requested'] = false;
                $this->conversationState['wants_visit'] = false;
                $this->conversationState['commercial_contact_requested'] = false;
                $this->conversationState['last_message_was_confirmation'] = false;
                $this->conversationState['conversation_stage'] = 'sent';
                $this->conversationState['last_question_type'] = null;

                Log::info('❌ Visite REFUSÉE');
                $this->buildPendingContact();
                return;
            }

            Log::info('⚠️ Réponse ambiguë pour la visite');
        }

        if ($this->conversationState['visit_accepted']) {
            if (empty($this->conversationState['name'])) {
                $this->conversationState['conversation_stage'] = 'visit_name';
                $this->conversationState['last_question_type'] = 'visit_name';
            } elseif (empty($this->conversationState['appointment_date'])) {
                $this->conversationState['conversation_stage'] = 'visit_date';
                $this->conversationState['last_question_type'] = 'visit_date';
            } else {
                $this->conversationState['conversation_stage'] = 'sent';
                $this->conversationState['last_question_type'] = null;
                $this->trySendContact();
            }
        }

        $this->buildPendingContact();

        Log::info('updateConversationState - Fin', [
            'stage' => $this->conversationState['conversation_stage'],
            'visit_accepted' => $this->conversationState['visit_accepted'],
            'last_question_type' => $this->conversationState['last_question_type'],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | BUILD PENDING CONTACT
    |--------------------------------------------------------------------------
    */

    private function buildPendingContact(): void
    {
        $this->pendingContact = [
            'name' => $this->conversationState['name'],
            'phone' => $this->conversationState['phone'],
            'project' => $this->conversationState['project'],
            'property_type' => $this->conversationState['property_type'],
            'budget' => $this->conversationState['budget'],
            'purpose' => $this->conversationState['purpose'],
            'payment_method' => $this->conversationState['payment_method'],
            'appointment_date' => $this->conversationState['appointment_date'],
            'appointment_time' => $this->conversationState['appointment_time'],
            'wants_visit' => $this->conversationState['wants_visit'],
            'visit_requested' => $this->conversationState['visit_requested'],
            'visit_accepted' => $this->conversationState['visit_accepted'],
            'wants_contact' => $this->conversationState['wants_contact'],
            'conversation_stage' => $this->conversationState['conversation_stage'],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | SEND CONTACT TO N8N
    |--------------------------------------------------------------------------
    */

    private function sendContactToN8n(array $payload): bool
    {
        if (!$this->n8nEnabled || !$this->n8nWebhookUrl) {
            Log::warning('N8N désactivé ou webhook manquant.');
            return false;
        }

        try {
            $response = Http::timeout(20)
                ->acceptJson()
                ->post($this->n8nWebhookUrl, $payload);

            if (!$response->successful()) {
                Log::error('N8N réponse invalide', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Erreur N8N', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | TRY SEND CONTACT
    |--------------------------------------------------------------------------
    */

    private function trySendContact(): void
    {
        if ($this->conversationState['contact_sent'] ||
            empty($this->conversationState['name']) ||
            !$this->conversationState['visit_accepted'] ||
            empty($this->conversationState['appointment_date'])) {
            return;
        }

        $payload = [
            'source' => 'sakani_agent',
            'lead_type' => 'visit_request',
            'project' => $this->conversationState['project'],
            'name' => $this->conversationState['name'],
            'phone' => $this->conversationState['phone'] ?? 'Non fourni',
            'property_type' => $this->conversationState['property_type'],
            'budget' => $this->conversationState['budget'],
            'purpose' => $this->conversationState['purpose'],
            'payment_method' => $this->conversationState['payment_method'],
            'appointment_date' => $this->conversationState['appointment_date'],
            'appointment_time' => $this->conversationState['appointment_time'],
            'wants_visit' => true,
            'visit_requested' => true,
            'visit_accepted' => true,
            'status' => 'new',
            'created_at' => now()->toIso8601String(),
        ];

        if ($this->sendContactToN8n($payload)) {
            $this->conversationState['contact_sent'] = true;
            $this->conversationState['conversation_stage'] = 'sent';
            $this->pendingContact = [];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SYSTEM PROMPT
    |--------------------------------------------------------------------------
    */

    private function getSystemPrompt(): string
    {
        $context = $this->getDataContext();
        $state = json_encode($this->conversationState, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return <<<PROMPT
Tu es le conseiller virtuel officiel de Greenland.
Tu représentes uniquement le projet GreenLand.

État actuel : {$state}
Données GreenLand : {$context}

Réponds de manière courte, chaleureuse et commerciale. Une seule question à la fois.
PROMPT;
    }

    /*
    |--------------------------------------------------------------------------
    | CALL AI
    |--------------------------------------------------------------------------
    */

    private function callAI(string $message, array $history = []): ?string
    {
        if (!$this->apiKey) {
            Log::error('OPENROUTER_API_KEY manquante');
            return null;
        }

        $messages = [
            ['role' => 'system', 'content' => $this->getSystemPrompt()],
        ];

        foreach ($history as $item) {
            if (!isset($item['role']) || !isset($item['content'])) {
                continue;
            }
            if (!in_array($item['role'], ['user', 'assistant'], true)) {
                continue;
            }
            $messages[] = ['role' => $item['role'], 'content' => $item['content']];
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        try {
            $response = Http::timeout(60)
                ->withToken($this->apiKey)
                ->acceptJson()
                ->post(
                    'https://openrouter.ai/api/v1/chat/completions',
                    [
                        'model' => $this->model,
                        'messages' => $messages,
                        'temperature' => 0.05,
                        'max_tokens' => 400,
                    ]
                );

            if (!$response->successful()) {
                Log::error('Erreur OpenRouter', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $json = $response->json();
            $answer = $json['choices'][0]['message']['content'] ?? null;

            if (!is_string($answer) || trim($answer) === '') {
                return null;
            }

            return trim($answer);
        } catch (\Throwable $e) {
            Log::error('Exception OpenRouter', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SECURITY
    |--------------------------------------------------------------------------
    */

    private function isSensitiveRequest(string $message): bool
    {
        $lower = $this->normalize($message);

        $patterns = [
            'prompt system', 'prompt systeme', 'system prompt',
            'tes instructions', 'instructions internes',
            'base de donnees', 'database', 'donne moi la base',
            'montre les api', 'show api', 'api key', 'cle api',
            'openrouter', 'n8n', 'ton code', 'montre ton code',
            'source code', 'php code', 'comment tu es programme',
            'variables env', '.env',
        ];

        foreach ($patterns as $pattern) {
            if (mb_stripos($lower, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    private function securityResponse(): string
    {
        return "Je suis le conseiller virtuel de Greenland  Je peux par contre vous aider avec les informations sur GreenLand.";
    }

    /*
    |--------------------------------------------------------------------------
    | LEAK PROTECTION
    |--------------------------------------------------------------------------
    */

    private function looksLikeInternalLeak(string $answer): bool
    {
        $patterns = [
            'OPENROUTER_API_KEY', 'N8N_WEBHOOK_URL', 'BEGIN PRIVATE KEY',
            'system prompt', 'system message', 'developer message',
            'api key', 'authorization: bearer', 'bearer sk-',
            'openrouter', 'n8n_webhook',
        ];

        foreach ($patterns as $pattern) {
            if (mb_stripos($answer, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
 * 🔥 Détecter les remerciements
 */
private function isThankYouMessage(string $message): bool
{
    $lower = $this->normalize(trim($message));

    $thanks = [
        'merci', 'merci beaucoup', 'merci bcp', 'mrc', 'mrci',
        'chokran', 'choukran', 'chokrane', 'saha', 'sahit', 'sahit a',
        'barak allah', 'barak allaho', 'allah ybarek', 'allah ybarak',
        'thank you', 'thanks', 'thx', 'top', 'parfait merci',
        'chokran bzaf', 'choukran bzaf'
    ];

    foreach ($thanks as $word) {
        if ($lower === $word || mb_stripos($lower, $word) !== false) {
            return true;
        }
    }

    return false;
}

/**
 * 🔥 Réponse aux remerciements
 */
private function thankYouResponse(string $message): string
{
    $lang = $this->detectLanguage($message);

    // ✅ Si la visite est déjà complétée → remercier + confirmer
    if ($this->conversationState['completed']) {
        if ($lang === 'fr') {
            return "Avec plaisir 😊\n\n" .
                   "✅ Votre demande de visite a bien été enregistrée.\n" .
                   "📞 Notre équipe vous contactera très prochainement.\n\n" .
                   "🙏 À bientôt !";
        }
        return "Bla jmil 😊\n\n" .
               "✅ Demande dialek t'sijilat mzyan.\n" .
               "📞 L'équipe taycontacti m3ak qrib.\n\n" .
               "🙏 Bslama !";
    }

    if ($lang === 'fr') {
        return "Avec plaisir 😊\n\n" .
               "N'hésitez pas à nous contacter si vous avez d'autres questions.\n\n" .
               "📞 " . $this->data['projet']['contact_agents'] . "\n\n" .
               "🙏 À bientôt !";
    }

    return "Bla jmil 😊\n\n" .
           "Ila 3ndek chi soual akhor, ma t'khafch t'contactina.\n\n" .
           "📞 " . $this->data['projet']['contact_agents'] . "\n\n" .
           "🙏 Bslama !";
}
    /*
    |--------------------------------------------------------------------------
    | MEANINGLESS MESSAGE
    |--------------------------------------------------------------------------
    */

    private function isMeaninglessMessage(string $message): bool
    {
        $message = trim($message);

        if (mb_strlen($message) <= 2) {
            $exceptions = [
                'ok', 'hi', 'yo', 'ah', 'wa', 'la', 'no', 'si', 'ça', 'ca',
                'merci', 'saha', 'wakha', 'bghit', 'brit', 'wah', 'non', 'oui',
                'yes', 'top', 'bien', 'parfait', 'mzyan', 'daccord'
            ];
                $lower = mb_strtolower($message);

            if (in_array($lower, $exceptions, true)) {
                return false;
            }

            if (preg_match('/^[a-zA-Z]{1,2}$/', $message)) {
                return true;
            }
        }

        if (preg_match('/^[a-zA-Z]{1,15}$/', $message) &&
            !preg_match('/^(bonjour|salam|salut|hello|ok|oui|non|yes|no|merci|svp|stp|ah|wa|la|f3|f4|adresse|localisation|prix|equipement|description|contact|horaire|surface|typologie|visite|nchri|bghit|brit|appartement|livraison|mars|delai|quand|etat|construction|avancement|horaires|ouverture|fermeture|commercial|telephone|numero|appel)$/i', $message)) {
            return true;
        }

        if (preg_match('/^([a-zA-Z])\1{2,}$/', $message)) {
            return true;
        }

        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | TYPE RESPONSE
    |--------------------------------------------------------------------------
    */

    private function getTypeResponse($type, $lang)
    {
        $projet = $this->data['projet'];
        $typologies = $projet['typologies'];

        $typeUpper = strtoupper($type);
        $info = $typologies[$typeUpper] ?? null;

        if ($lang === 'fr') {
            return "🏠 Parfait ! Le {$typeUpper} est un excellent choix.\n\n" .
                   "📐 **Superficie** : " . ($info['surface'] ?? '83 à 130 m²') . "\n" .
                   "🛏️ **Composition** : " . ($info['composition'] ?? '2 chambres + salon + 2 salles de bains') . "\n" .
                   "📍 **Projet GreenLand - Casablanca**";
        } else {
            return "🏠 Mzyan ! {$typeUpper} choix mzyan.\n\n" .
                   "📐 **Superficie** : " . ($info['surface'] ?? '83 à 130 m²') . "\n" .
                   "🛏️ **Composition** : " . ($info['composition'] ?? '2 chambres + salon + 2 salles de bains') . "\n" .
                   "📍 **Projet GreenLand - Casablanca**";
        }
    }

    /*
    |--------------------------------------------------------------------------
    | MAIN REPLY
    |--------------------------------------------------------------------------
    */

    public function reply(string $message, array $history = []): array
    {
        Log::info('=== AGENT REPLY ===', [
            'message' => $message,
            'history_count' => count($history),
            'current_state' => $this->conversationState,
        ]);

        $message = trim($message);

        if ($message === '') {
            return [
                'success' => false,
                'message' => 'Message vide.',
                'state' => $this->conversationState,
                'pending_contact' => $this->pendingContact,
            ];
        }

        // 🔥 IMPORTANT : enregistrer le message utilisateur AVANT tout
        $this->conversationState['last_user_message'] = $message;

        $this->hydrateFromHistory($history);

        $lower = $this->normalize($message);

        // ════════════════════════════════════════════════════════════════
        // 🔥 PRIORITÉ 1 : DÉTECTION DU TYPE F3/F4 (AVANT TOUT)
        // ════════════════════════════════════════════════════════════════
        $type = $this->extractPropertyType($message);
        if ($type === 'F3' || $type === 'F4') {
            $this->conversationState['property_type'] = $type;
            $this->conversationState['first_message_done'] = true;

            $response = $this->getTypeResponse(strtolower($type), $this->detectLanguage($message));
            $this->conversationState['last_bot_message'] = $response;
            $this->buildPendingContact();
            // ✅ has_type=true → appendQuestion demandera le budget
            return $this->response($this->appendQuestion($response, 'type'));
        }

        // ════════════════════════════════════════════════════════════════
        // 🔍 DÉTECTION HORS PROJET
        // ════════════════════════════════════════════════════════════════
        $outOfProject = $this->detectOutOfProject($message);
        if ($outOfProject !== null) {
            $response = $this->outOfProjectResponse($outOfProject);
            $this->conversationState['last_bot_message'] = $response;
            $this->buildPendingContact();
            return $this->response($this->appendQuestion($response, 'default'));
        }

        // ════════════════════════════════════════════════════════════════
        // 🚨 PRIORITÉ ABSOLUE: SI C'EST LE PREMIER MESSAGE
        // ════════════════════════════════════════════════════════════════
        if (!$this->conversationState['first_message_done']) {
            $this->conversationState['first_message_done'] = true;
            $this->conversationState['greeting_done'] = true;
            $this->conversationState['welcome_sent'] = true;

            $answer = $this->greetingResponse();
            $this->conversationState['last_bot_message'] = $answer;
            $this->buildPendingContact();
            // ✅ Pas de type → appendQuestion demandera le type
            return $this->response($answer);
        }

        // ════════════════════════════════════════════════════════════════
        // 🔍 VÉRIFIER SI C'EST UNE SALUTATION (après le premier message)
        // ════════════════════════════════════════════════════════════════
        if ($this->isGreetingMessage($message)) {
            $lang = $this->detectLanguage($message);

            if ($lang === 'fr') {
                $response = "Bonjour 😊 Bienvenue chez Greenland, Comment puis-je vous aider ?";
            } else {
                $response = "Salam 😊 Mrahba f  Greenland. Kifash n9der n3awnek ?";
            }

            $this->conversationState['last_bot_message'] = $response;
            $this->buildPendingContact();
                return $this->response($response);

            //return $this->response($this->appendQuestion($response, 'greeting'));
        }

        // ════════════════════════════════════════════════════════════════
        // 🔍 VÉRIFIER SI LE MESSAGE EST VIDE OU SANS SENS
        // ════════════════════════════════════════════════════════════════
        if ($this->isMeaninglessMessage($message)) {
            $lang = $this->detectLanguage($message);

            if ($lang === 'fr') {
                $response = "Je n'ai pas bien compris votre message 😊\n\n" .
                            "Pourriez-vous reformuler votre demande ?\n" .
                            "Exemples :\n" .
                            "   • F3 ou F4 ?\n" .
                            "   • Prix ?\n" .
                            "   • Localisation ?\n" .
                            "   .....";
            } else {
                $response = "Ma fhemtch mzyan had l'message dialek 😊\n\n" .
                            "Wach t9der t3tini message wa7ed akhor ?\n" .
                            "Mthal :\n" .
                            "   • F3 wla F4 ?\n" .
                            "   • Prix ?\n" .
                            "   • Localisation ?\n" .
                            "   .....";
            }

            $this->conversationState['last_bot_message'] = $response;
            $this->buildPendingContact();
            return $this->response($this->appendQuestion($response, 'default'));
        }

        // ════════════════════════════════════════════════════════════════
        // 🧠 UTILISER L'IA POUR COMPRENDRE LE SENS DU MESSAGE
        // ════════════════════════════════════════════════════════════════
        $intentResult = $this->understandWithAI($message);

        if (isset($intentResult['intent']) && $intentResult['intent'] !== 'inconnu' && $intentResult['confidence'] > 0.6) {
            if (!empty($intentResult['response'])) {
                $response = $intentResult['response'];
                $this->conversationState['last_bot_message'] = $response;
                $this->buildPendingContact();
                return $this->response($this->appendQuestion($response, 'default'));
            }

            $response = $this->generateResponseFromIntent($intentResult);
            if ($response !== null) {
                $this->conversationState['last_bot_message'] = $response;
                $this->buildPendingContact();
                return $this->response($this->appendQuestion($response, 'default'));
            }
        }

        // ════════════════════════════════════════════════════════════════
        // 📊 VÉRIFIER SI L'UTILISATEUR POSE UNE QUESTION
        // ════════════════════════════════════════════════════════════════
        $questionWords = [
            'localisation', 'adresse', 'fin kayn', 'ou se trouve',
            'prix', 'chhal', 'combien', 'tarif', 'cout', 'coût',
            'surface', 'superficie', 'metre', 'm2', 'm²', 'taille',
            'équipement', 'equipement', 'padel', 'sport', 'patio', 'parking', 'ascenseur',
            'livraison', 'date livraison', 'mars', 'delai', 'quand',
            'description', 'details', 'detail', 'info', 'infos',
            'typologie', 'type', 'chambre', 'salon',
            'etat', 'état', 'construction', 'avancement',
            'horaire', 'ouverture', 'fermeture',
            'contact', 'téléphone', 'numero', 'appel', 'commercial',
            'quoi', 'que', 'comment', 'pourquoi', 'est ce que'
        ];

        $isQuestion = false;
        foreach ($questionWords as $word) {
            if (mb_stripos($lower, $word) !== false) {
                $isQuestion = true;
                break;
            }
        }

        if (mb_stripos($lower, 'goli') !== false ||
            mb_stripos($lower, 'wach') !== false ||
            mb_stripos($lower, 'chno') !== false ||
            mb_stripos($lower, '?') !== false) {
            $isQuestion = true;
        }

        if ($isQuestion) {
            $response = $this->handleQuestion($message);
            $this->conversationState['last_bot_message'] = $response;
            $this->buildPendingContact();
            return $this->response($this->appendQuestion($response, 'default'));
        }

        // ════════════════════════════════════════════════════════════════
        // 🔍 VÉRIFIER SI LE MESSAGE CONTIENT UN BUDGET
        // ════════════════════════════════════════════════════════════════
        $amount = $this->extractBudget($message);

        if ($amount !== null) {
            if ($amount < 100000) {
                $this->conversationState['budget'] = null;
                $this->conversationState['budget_given_by_user'] = false;

                $lang = $this->detectLanguage($message);
                if ($lang === 'fr') {
                    $answer = "Désolé 😊 Le budget de " . number_format($amount, 0, ',', ' ') . " DH n'est pas valide.\n\n" .
                            "Veuillez entrer un budget valide (minimum 100 000 DH).";
                } else {
                    $answer = " 😊 L'budget dialek " . number_format($amount, 0, ',', ' ') . " DH machi valide.\n\n" .
                            "3tina budget valide (l'aghal 100 000 DH).";
                }

                $this->conversationState['last_bot_message'] = $answer;
                $this->buildPendingContact();
                return $this->response($this->appendQuestion($answer, 'budget'));
            }

            $this->conversationState['budget'] = $amount;
            $this->conversationState['budget_given_by_user'] = true;
            $this->conversationState['budget_invalid'] = false;
            $this->conversationState['budget_error'] = null;
            $this->conversationState['conversation_stage'] = 'visit_offer';
            $this->conversationState['last_question_type'] = 'visit_offer';
            $this->conversationState['visit_asked'] = true;
            $this->conversationState['visit_requested'] = true;
            $this->conversationState['wants_visit'] = true;

            $answer = $this->qualificationResponse();
            $this->conversationState['last_bot_message'] = $answer;
            $this->buildPendingContact();
            // ✅ has_budget + has_type → appendQuestion proposera la visite
            return $this->response($this->appendQuestion($answer, 'visit'));
        }

        // ════════════════════════════════════════════════════════════════
        // 🔍 DÉTECTION D'INTENTION - ACHAT OU INFORMATION
        // ════════════════════════════════════════════════════════════════
        if ($this->detectBuyIntent($message) || $this->isPositive($message)) {
            $type = $this->conversationState['property_type'] ?? null;

            if (!empty($type) && $type !== 'INVALID') {
                if (empty($this->conversationState['budget']) || !$this->conversationState['budget_given_by_user']) {
                    $this->conversationState['conversation_stage'] = 'budget';
                    $this->conversationState['last_question_type'] = 'budget';

                    $response = "Parfait 😊 Vous êtes intéressé par un " . $type . ".\n\n" .
                                "💰 Quel budget avez-vous prévu pour votre appartement ?";

                    $lang = $this->detectLanguage($message);
                    $this->conversationState['last_bot_message'] = $this->translateResponse($response, $lang);
                    $this->buildPendingContact();
                    // ✅ has_type mais pas budget → appendQuestion demandera le budget
                    return $this->response($this->appendQuestion($this->translateResponse($response, $lang), 'budget'));
                }
            }

            if (empty($type) || $type === 'INVALID') {
                $this->conversationState['conversation_stage'] = 'property_type';
                $this->conversationState['last_question_type'] = 'property_type';

                $lang = $this->detectLanguage($message);

                if ($lang === 'fr') {
                    $response = " **Typologies GreenLand :**\n\n" .
                                " **F3 :**\n" .
                                "   • 2 chambres + salon + 2 salles de bains\n" .
                                "   • 83 à 123 m²\n\n" .
                                " **F4 :**\n" .
                                "   • 3 chambres + salon + 2 salles de bains\n" .
                                "   • 97 à 130 m²\n\n" .
                                "Quel type vous intéresse ?";
                } else {
                    $response = " **L'typologies GreenLand :**\n\n" .
                                " **F3 :**\n" .
                                "   • 2 chambres + salon + 2 salles de bains\n" .
                                "   • 83 à 123 m²\n\n" .
                                " **F4 :**\n" .
                                "   • 3 chambres + salon + 2 salles de bains\n" .
                                "   • 97 à 130 m²\n\n" .
                                "Ach mn type m'7tam bik ?";
                }

                $this->conversationState['last_bot_message'] = $response;
                $this->buildPendingContact();
                // ✅ has_budget mais pas type → appendQuestion demandera le type
                return $this->response($this->appendQuestion($response, 'type'));
            }
        }

        // ════════════════════════════════════════════════════════════════
        // 🚫 GESTION DE LA NÉGATION POUR LA VISITE
        // ════════════════════════════════════════════════════════════════
        $lastQuestionType = $this->conversationState['last_question_type'] ?? null;

        if ($lastQuestionType === 'visit_offer' && $this->isNegative($message)) {
            return $this->handleVisitRefusal();
        }

        // Update state
        $this->updateConversationState($message);

        // ════════════════════════════════════════════════════════════════
        // 🛑 VÉRIFIER SI LA CONVERSATION EST TERMINÉE
        // ════════════════════════════════════════════════════════════════
        if ($this->conversationState['completed']) {
            $answer = "Merci de nous avoir contactés 😊\n\n" .
                      "✅ Votre demande de visite a déjà été enregistrée.\n" .
                      "📞 Notre équipe vous contactera très prochainement.\n\n" .
                      "📱 Pour toute urgence : " . $this->data['projet']['contact_agents'] . "\n\n" .
                      "🙏 À bientôt !";

            $this->conversationState['last_bot_message'] = $answer;
            $this->buildPendingContact();
            // ✅ Tout est complet → appendQuestion n'ajoutera rien
            return $this->response($this->appendQuestion($answer, 'default'));
        }

        // Security
        if ($this->isSensitiveRequest($message)) {
            return [
                'success' => true,
                'message' => $this->securityResponse(),
                'state' => $this->conversationState,
                'pending_contact' => $this->pendingContact,
            ];
        }

        $type = $this->conversationState['property_type'] ?? null;
        $budget = $this->conversationState['budget'] ?? null;
        $budgetGiven = $this->conversationState['budget_given_by_user'] ?? false;

        // ════════════════════════════════════════════════════════════════
        // 🚫 GESTION DES TYPOLOGIES INVALIDES
        // ════════════════════════════════════════════════════════════════
        if ($type === 'INVALID') {
            $this->conversationState['property_type'] = null;
            $this->conversationState['conversation_stage'] = 'property_type';
            $this->conversationState['last_question_type'] = 'property_type';

            $answer = "Désolé 😊 Les typologies disponibles dans notre projet GreenLand sont uniquement F3 et F4.\n\n" .
                      " Le F3 : 2 chambres + salon + 2 salles de bains (83 à 123 m²)\n" .
                      " Le F4 : 3 chambres + salon + 2 salles de bains (97 à 130 m²)\n\n" .
                      "Lequel de ces deux types vous intéresse ?";

            $this->conversationState['last_bot_message'] = $answer;
            $this->buildPendingContact();
            return $this->response($this->appendQuestion($answer, 'type'));
        }

        // ════════════════════════════════════════════════════════════════
        // GESTION DE LA VISITE
        // ════════════════════════════════════════════════════════════════

        if ($this->conversationState['visit_accepted']) {
            return $this->handleVisitResponse();
        }

        if ($this->conversationState['last_question_type'] === 'visit_offer') {
            if ($this->isPositive($message)) {
                $this->conversationState['visit_accepted'] = true;
                $this->conversationState['visit_requested'] = true;
                $this->conversationState['wants_visit'] = true;
                $this->conversationState['conversation_stage'] = 'visit_name';
                $this->conversationState['last_question_type'] = 'visit_name';

                $answer = "Parfait 😊 Pour transmettre votre demande de visite à notre équipe commerciale, donnez-moi simplement votre nom.";
                $this->conversationState['last_bot_message'] = $answer;
                $this->buildPendingContact();
                // ✅ has_visit mais pas name → appendQuestion demandera le nom
                return $this->response($this->appendQuestion($answer, 'name'));
            }

            if ($this->isNegative($message)) {
                return $this->handleVisitRefusal();
            }

            $answer = $this->qualificationResponse();
            $this->conversationState['last_bot_message'] = $answer;
            $this->buildPendingContact();
            return $this->response($this->appendQuestion($answer, 'visit'));
        }

        // ════════════════════════════════════════════════════════════════
        // QUALIFICATION - TYPE ET BUDGET
        // ════════════════════════════════════════════════════════════════

        if (!empty($type) && !empty($budget) && $budgetGiven) {
            if ($type !== 'F3' && $type !== 'F4') {
                $answer = "Désolé 😊 Les typologies disponibles sont F3 et F4. Souhaitez-vous en savoir plus sur ces deux types ?";
                $this->conversationState['property_type'] = null;
                $this->conversationState['last_bot_message'] = $answer;
                $this->buildPendingContact();
                return $this->response($this->appendQuestion($answer, 'type'));
            }

            $this->conversationState['conversation_stage'] = 'visit_offer';
            $this->conversationState['last_question_type'] = 'visit_offer';
            $this->conversationState['visit_asked'] = true;
            $this->conversationState['visit_requested'] = true;
            $this->conversationState['wants_visit'] = true;

            $answer = $this->qualificationResponse();
            $this->conversationState['last_bot_message'] = $answer;
            $this->buildPendingContact();
            return $this->response($this->appendQuestion($answer, 'visit'));
        }

        if (!empty($type) && empty($budget)) {
            $this->conversationState['conversation_stage'] = 'budget';
            $this->conversationState['last_question_type'] = 'budget';

            $response = "Parfait 😊 Vous êtes intéressé par un " . $type . ".\n\n" .
                        "💰 Quel budget avez-vous prévu pour votre appartement ?";

            $lang = $this->detectLanguage($message);
            $answer = $this->translateResponse($response, $lang);
            $this->conversationState['last_bot_message'] = $answer;
            $this->buildPendingContact();
            return $this->response($this->appendQuestion($answer, 'budget'));
        }

        if (empty($type)) {
            $this->conversationState['conversation_stage'] = 'property_type';
            $this->conversationState['last_question_type'] = 'property_type';

            $lang = $this->detectLanguage($message);

            if ($lang === 'fr') {
                $response = " **Typologies GreenLand :**\n\n" .
                            " **F3 :**\n" .
                            "   • 2 chambres + salon + 2 salles de bains\n" .
                            "   • 83 à 123 m²\n\n" .
                            " **F4 :**\n" .
                            "   • 3 chambres + salon + 2 salles de bains\n" .
                            "   • 97 à 130 m²\n\n" .
                            "Quel type vous intéresse ?";
            } else {
                $response = " **L'typologies GreenLand :**\n\n" .
                            " **F3 :**\n" .
                            "   • 2 chambres + salon + 2 salles de bains\n" .
                            "   • 83 à 123 m²\n\n" .
                            " **F4 :**\n" .
                            "   • 3 chambres + salon + 2 salles de bains\n" .
                            "   • 97 à 130 m²\n\n" .
                            "Ach mn type m'7tam bik ?";
            }
            $this->conversationState['last_bot_message'] = $response;
            $this->buildPendingContact();
            return $this->response($this->appendQuestion($response, 'type'));
        }

          // ════════════════════════════════════════════════════════════════
        // 🚨 PRIORITÉ 0 : VÉRIFIER SI C'EST UN REMERCIEMENT
        // ════════════════════════════════════════════════════════════════
        if ($this->isThankYouMessage($message)) {
            $answer = $this->thankYouResponse($message);
            $this->conversationState['last_bot_message'] = $answer;
            $this->buildPendingContact();
            return $this->response($answer);
        }
        // ════════════════════════════════════════════════════════════════
        // ⚠️ DERNIER RECOURS : MESSAGE NON RECONNU
        // ════════════════════════════════════════════════════════════════
        $answer = $this->defaultResponse($message);
        $this->conversationState['last_bot_message'] = $answer;
        $this->buildPendingContact();
        return $this->response($this->appendQuestion($answer, 'default'));
    }

    /*
    |--------------------------------------------------------------------------
    | HYDRATE FROM HISTORY
    |--------------------------------------------------------------------------
    */

    private function hydrateFromHistory(array $history): void
    {
        if (empty($history)) {
            return;
        }

        foreach ($history as $item) {
            if (!isset($item['role']) || !isset($item['content'])) {
                continue;
            }

            if ($item['role'] !== 'user') {
                continue;
            }

            $content = trim((string) $item['content']);
            if ($content === '') {
                continue;
            }

            $type = $this->extractPropertyType($content);
            if ($type !== null) {
                $this->conversationState['property_type'] = $type;
            }

            $budget = $this->extractBudget($content);
            if ($budget !== null) {
                $this->conversationState['budget'] = $budget;
                $this->conversationState['budget_given_by_user'] = true;
            }

            $phone = $this->extractPhone($content);
            if ($phone !== null) {
                $this->conversationState['phone'] = $phone;
                $this->conversationState['wants_contact'] = true;
            }

            if ($this->conversationState['visit_accepted']) {
                $name = $this->extractName($content);
                if ($name !== null) {
                    $this->conversationState['name'] = $name;
                }
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

    private function response(string $answer): array
    {
        $this->buildPendingContact();

        return [
            'success' => true,
            'message' => $answer,
            'state' => $this->conversationState,
            'pending_contact' => $this->pendingContact,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | GETTERS
    |--------------------------------------------------------------------------
    */

    public function getData(): array
    {
        return $this->data;
    }

    public function getConversationState(): array
    {
        return $this->conversationState;
    }

    public function getPendingContact(): array
    {
        return $this->pendingContact;
    }

    /*
    |--------------------------------------------------------------------------
    | SESSION HISTORY
    |--------------------------------------------------------------------------
    */

    private function getHistoryForSession(string $sessionId): array
    {
        return $this->sessionHistory[$sessionId] ?? [];
    }

    private function addToSessionHistory(string $sessionId, string $role, string $content): void
    {
        if (!isset($this->sessionHistory[$sessionId])) {
            $this->sessionHistory[$sessionId] = [];
        }

        $this->sessionHistory[$sessionId][] = [
            'role' => $role,
            'content' => $content,
            'timestamp' => now()->toDateTimeString()
        ];

        if (count($this->sessionHistory[$sessionId]) > 30) {
            $this->sessionHistory[$sessionId] = array_slice($this->sessionHistory[$sessionId], -30);
        }
    }

    public function processMessage(string $message, string $sessionId): string
    {
        try {
            Log::info('📩 AgentFinalService::processMessage', [
                'message' => $message,
                'session_id' => $sessionId
            ]);

            $history = $this->getHistoryForSession($sessionId);

            $result = $this->reply($message, $history);

            $response = $result['message'] ?? "Je n'ai pas pu traiter votre demande. 😊";

            $this->addToSessionHistory($sessionId, 'user', $message);
            $this->addToSessionHistory($sessionId, 'assistant', $response);

            Log::info('✅ AgentFinalService::processMessage terminé', [
                'session_id' => $sessionId,
                'history_count' => count($this->getHistoryForSession($sessionId))
            ]);

            return $response;

        } catch (\Exception $e) {
            Log::error('❌ Erreur processMessage: ' . $e->getMessage());
            return "Je suis désolé, une erreur s'est produite. Veuillez réessayer plus tard. 😊";
        }
    }
}
