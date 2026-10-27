<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Helpers\RoleHelper;
use App\Models\Conversation;
use App\Services\AgentFinalService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;


use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use App\Http\Helpers\NotificationHelper;
use App\Http\Helpers\DatabaseHelper;
use App\Models\WebhookEvent;
use App\Models\Notification;
use App\Models\User;
use App\Events\NotificationEvent;
use App\Enum\TypeNotificationEnum;
use App\Enum\RoleEnum;
use Carbon\Carbon;
use App\Models\StatutProspect;
use App\Models\Prospect;
use Illuminate\Support\Facades\Config;
use App\Events\NewWhatsAppMessageEvent;
use Twilio\Rest\Client as ClientTwilio;
use Illuminate\Support\Facades\Auth;
use App\Http\Helpers\FichierHelper;
use App\Models\Societe;
use App\Services\GreenLand\LeadSummarizer;
use App\Services\GreenLand\Settings as GreenLandSettings;
use App\Services\GreenLand\Transcriber;
use App\Services\GreenLand\TwilioWhatsApp;
use App\Services\GreenLand\VoiceSynthesizer;
use App\Http\Helpers\CommercialAssignmentHelper;
/**
 * Contrôleur WhatsApp Business (Twilio) + agent virtuel GreenLand.
 *
 * Corrections apportées par rapport à la version précédente :
 *  - anti-doublon sur le MessageSid (les réessais Twilio créaient deux réponses) ;
 *  - le texte de l'agent part avant les médias ;
 *  - nom et numéro WhatsApp transmis au service comme valeurs de repli
 *    (l'agent ne les redemande plus, sans écraser ce que le prospect a donné) ;
 *  - prospect_id transmis à la notification commerciale ;
 *  - notification d'affectation envoyée une seule fois ;
 *  - recherche du prospect limitée au projet, avec orWhere groupé ;
 *  - gras WhatsApp (*texte*) et légendes des médias.
 *
 * Agent virtuel v3 :
 *  - vocaux transcrits (darija comprise) : l'agent répond dans la langue du prospect,
 *    le CRM affiche la transcription ;
 *  - photos lues par l'agent (capture de pub, plan…), position partagée (distance + itinéraire) ;
 *  - boutons et listes WhatsApp (modèles de contenu Twilio créés automatiquement),
 *    vraie épingle de localisation, « en train d'écrire… » ;
 *  - notifications CRM détaillées (lead chaud, visite réservée, urgence, mise à jour)
 *    avec score, résumé IA de la conversation et questions restées sans réponse ;
 *  - un commercial répond depuis le CRM : l'agent se met en retrait (greenland.human_takeover_hours).
 *
 * Signatures publiques inchangées ; deux méthodes publiques ajoutées (facultatives) :
 * getAgentStatus() et toggleAgent().
 */
class WhatsAppBusinessController extends Controller
{
    /* =====================================================================
     |  AGENT VIRTUEL
     * ===================================================================== */

    /**
     * 🤖 TRAITER LE MESSAGE AVEC L'AGENT VIRTUEL
     *
     * $input : le texte reçu (string), ou la description du message reçu (voir buildAgentInput()) :
     *   ['type' => 'text', 'text' => '…', 'interactive_id' => 'gl:…']           texte, bouton ou ligne de liste
     *   ['type' => 'audio', 'transcript' => '…', 'language' => 'darija']       vocal transcrit (ou non : transcript null)
     *   ['type' => 'image', 'bytes' => …, 'mime' => 'image/jpeg', 'caption' => '…']
     *   ['type' => 'location', 'latitude' => …, 'longitude' => …, 'label' => …, 'address' => …]
     *   ['type' => 'video'|'document', 'caption' => '…']
     *   + 'referral' => [...] : publicité « Click to WhatsApp » d'origine
     *
     * Renvoie le résultat complet de l'agent (parts, actions, state, silent…) + 'notify_commercial' et 'history'.
     */
    private function processWithAgent($input, $from, $sessionId, $projetId, $prospectId = null, $profileName = null)
    {
        $input = is_array($input) ? $input : ['type' => 'text', 'text' => (string) $input];
        $type = (string) ($input['type'] ?? 'text');
        $message = $this->agentHistoryText($input);

        try {
            Log::info("🤖 Agent virtuel: Traitement du message de {$from}", [
                'type' => $type,
                'message' => $message,
                'interactive_id' => $input['interactive_id'] ?? null,
                'session_id' => $sessionId,
                'prospect_id' => $prospectId,
            ]);

            // 🔥 Récupérer ou créer la conversation
            $conversation = Conversation::getOrCreate($sessionId, [
                'phone_number' => $from,
                'projet_id' => $projetId,
                'prospect_id' => $prospectId ?: null,
                'user_ip' => 'whatsapp',
                'user_agent' => 'WhatsApp Business',
            ]);

            // 🔥 État et historique persistés
            $state = $conversation ? ($conversation->state ?? []) : [];
            $history = $conversation ? ($conversation->history ?? []) : [];

            // ════════════════════════════════════════════════════════════
            // 👤 CONTACT CONNU : valeurs de repli pour l'agent
            //    (jamais 'name'/'phone' directement, sinon on écrase ce que
            //     le prospect a communiqué dans la conversation)
            // ════════════════════════════════════════════════════════════
            $state['whatsapp_phone'] = $from;
            $state['profile_name'] = $this->resolveProspectName($prospectId, $profileName);

            if ($prospectId) {
                $state['prospect_id'] = $prospectId;

                // ✅ Commercial affecté dans le CRM : l'agent l'annonce par son prénom
                //    (« Ahmed, votre conseiller, vous appellera… »), sinon « un conseiller »
                $commercial = $this->assignedCommercialFor($prospectId);
                if ($commercial) {
                    $state['assigned_commercial'] = $commercial;
                }
            }

            // 🔥 Créer l'agent avec l'état sauvegardé
            $agent = new AgentFinalService($state);

            // ✅ Prospect venu d'une publicité « Click to WhatsApp » : la pub est jointe au lead
            if (!empty($input['referral']) && is_array($input['referral'])) {
                $agent->setReferral($input['referral']);
            }

            // ✅ État AVANT la réponse : sert à détecter les transitions (qualification, demande de conseiller)
            $previousState = $state;

            // 🔥 Réponse adaptée au type de message reçu
            switch ($type) {
                case 'audio':
                    // Vocal transcrit : traité comme un texte, dans la langue détectée (darija, arabe…).
                    // Sans transcription : l'agent invite poliment le prospect à écrire, dans sa langue.
                    $result = trim((string) ($input['transcript'] ?? '')) !== ''
                        ? $agent->replyToMedia('audio', $history, null, (string) $input['transcript'], $input['language'] ?? null)
                        : $agent->replyToMedia('audio', $history);
                    break;

                case 'image':
                    // Photo lue par l'IA (capture de la pub, plan, question écrite…) ; pièces d'identité jamais lues.
                    $result = !empty($input['bytes'])
                        ? $agent->replyToImage((string) $input['bytes'], (string) ($input['mime'] ?? 'image/jpeg'), $input['caption'] ?? null, $history)
                        : $agent->replyToMedia('image', $history, $input['caption'] ?? null);
                    break;

                case 'location':
                    // Position partagée : distance jusqu'au projet et lien d'itinéraire.
                    $result = $agent->replyToLocation((float) $input['latitude'], (float) $input['longitude'], $input['label'] ?? null, $input['address'] ?? null, $history);
                    break;

                case 'video':
                case 'document':
                    $result = $agent->replyToMedia($type, $history, $input['caption'] ?? null);
                    break;

                default:
                    // Texte, ou bouton / ligne de liste touché (identifiant « gl:… »)
                    $result = $agent->reply((string) ($input['text'] ?? ''), $history, $input['interactive_id'] ?? null);
            }

            $response = (string) ($result['message'] ?? '');
            $newState = $result['state'] ?? $agent->getConversationState();
            $actions = $result['actions'] ?? [];

            // 🔥 Mettre à jour l'historique et sauvegarder
            if ($conversation) {
                $history[] = [
                    'role' => 'user',
                    'content' => $message,
                    'timestamp' => now()->toDateTimeString(),
                ];

                // ✅ Agent en pause (un commercial a la main) : aucune réponse à enregistrer
                if ($response !== '') {
                    $history[] = [
                        'role' => 'assistant',
                        'content' => $response,
                        'timestamp' => now()->toDateTimeString(),
                    ];
                }

                // ✅ Borne la taille stockée en base (l'agent n'exploite que les derniers échanges)
                $history = array_slice($history, -40);

                $conversation->updateConversation($newState, $history);
            }

            if ($prospectId) {
                $this->assignProspectIfInterested($prospectId, $projetId, $newState);
                $this->syncProspectFromAgent($prospectId, $newState, $from);
            }

            Log::info("🤖 Agent virtuel: Réponse générée et sauvegardée", [
                'response' => $response,
                'silent' => !empty($result['silent']),
                'history_count' => count($history),
                'actions' => array_column($actions, 'type'),
                'stage' => $newState['conversation_stage'] ?? null,
                'lead_score' => $newState['lead_score'] ?? null,
            ]);

           return array_merge($result, [
                'message' => $response,
                // ✅ lues directement depuis le résultat de l'agent (plus de variables locales)
                'message_before_media' => $result['message_before_media'] ?? $response,
                'message_after_media'  => $result['message_after_media'] ?? null,
                'actions' => $actions,
                'state' => $newState,
                'pending_contact' => $result['pending_contact'] ?? [],
                'notify_commercial' => $this->shouldNotifyCommercial($previousState, $newState),
                'history' => $history,
            ]);

        } catch (\Throwable $e) {
            Log::error("❌ Erreur agent virtuel: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            // ✅ Pas de repli sur processMessage() : il repart d'une mémoire vide
            //    et produit des réponses incohérentes (questions déjà posées, etc.).
            return [
                'message' => '',
                'parts' => [],
                'silent' => true,
                'actions' => [],
                'state' => [],
                'pending_contact' => [],
                'notify_commercial' => false,
                'history' => [],
            ];
        }
    }

    /**
     * 📝 TEXTE DU MESSAGE REÇU, pour l'historique de l'agent et les journaux
     */
    private function agentHistoryText(array $input)
    {
        $caption = trim((string) ($input['caption'] ?? ''));

        switch ($input['type'] ?? 'text') {
            case 'audio':
                $transcript = trim((string) ($input['transcript'] ?? ''));
                return $transcript !== '' ? '🎤 ' . $transcript : '[message vocal]';
            case 'image':
                return '[photo]' . ($caption !== '' ? ' ' . $caption : '');
            case 'location':
                return '[position partagée]';
            case 'video':
                return '[vidéo]' . ($caption !== '' ? ' ' . $caption : '');
            case 'document':
                return '[document]' . ($caption !== '' ? ' ' . $caption : '');
            default:
                return (string) ($input['text'] ?? '');
        }
    }

    /**
     * 🧩 DESCRIPTION DU MESSAGE REÇU, pour l'agent
     */
    private function buildAgentInput(Request $request, $body, $interactiveId, $mediaType, $mediaContentType, $downloadedMedia, $transcription, array $referral)
    {
        $input = ['referral' => $referral];
        $body = trim((string) $body);
        $latitude = $request->input('Latitude');
        $longitude = $request->input('Longitude');

        if (is_numeric($latitude) && is_numeric($longitude)) {
            return $input + [
                'type' => 'location',
                'latitude' => (float) $latitude,
                'longitude' => (float) $longitude,
                'label' => $request->input('Label'),
                'address' => $request->input('Address'),
            ];
        }

        if ($mediaType === 'audio' && $body === '') {
            return $input + [
                'type' => 'audio',
                'transcript' => $transcription['text'] ?? null,
                'language' => $transcription['language'] ?? null,
            ];
        }

        if ($mediaType === 'image') {
            return $input + [
                'type' => 'image',
                'bytes' => $downloadedMedia['bytes'] ?? null,
                'mime' => $downloadedMedia['mime'] ?? $mediaContentType,
                'caption' => $body !== '' ? $body : null,
            ];
        }

        if (in_array($mediaType, ['video', 'pdf', 'document'], true)) {
            return $input + [
                'type' => $mediaType === 'video' ? 'video' : 'document',
                'caption' => $body !== '' ? $body : null,
            ];
        }

        return $input + [
            'type' => 'text',
            'text' => $body,
            'interactive_id' => $interactiveId,
        ];
    }

    /**
     * 🔘 IDENTIFIANT DU BOUTON OU DE LA LIGNE DE LISTE TOUCHÉ
     * Seuls les identifiants de l'agent (« gl:… ») sont retenus. Sans identifiant,
     * l'agent reconnaît le bouton à son titre (Body).
     */
    private function interactiveIdFromRequest(Request $request)
    {
        foreach (['ButtonPayload', 'ListId'] as $field) {
            $value = trim((string) $request->input($field, ''));
            if (str_starts_with($value, 'gl:')) {
                return $value;
            }
        }

        $data = $request->input('InteractiveData');
        if (is_array($data)) {
            $data = json_encode($data);
        }
        if (is_string($data) && preg_match('/"(gl:[^"\\\\]{1,190})"/', $data, $match) === 1) {
            return $match[1];
        }

        return null;
    }

    /**
     * 📣 PUBLICITÉ « CLICK TO WHATSAPP » D'ORIGINE (paramètres Referral* de Twilio)
     */
    private function referralFromRequest(Request $request)
    {
        $fields = [
            'source_url' => 'ReferralSourceUrl',
            'source_id' => 'ReferralSourceId',
            'source_type' => 'ReferralSourceType',
            'headline' => 'ReferralHeadline',
            'body' => 'ReferralBody',
            'media_type' => 'ReferralMediaContentType',
            'ctwa_clid' => 'ReferralCtwaClid',
        ];

        $referral = [];
        foreach ($fields as $key => $field) {
            $value = trim((string) $request->input($field, ''));
            if ($value !== '') {
                $referral[$key] = $value;
            }
        }

        return $referral;
    }

    /**
     * 👔 COMMERCIAL AFFECTÉ DANS LE CRM (prénom annoncé au prospect par l'agent)
     */
    private function assignedCommercialFor($prospectId)
    {
        try {
            $prospect = Prospect::on('temp')->find($prospectId);
            if (!$prospect || empty($prospect->commercial_affecte)) {
                return null;
            }

            $user = User::on('temp')->find($prospect->commercial_affecte);
            if (!$user) {
                return null;
            }

            $name = trim(trim((string) ($user->prenom ?? '')) . ' ' . trim((string) ($user->name ?? '')));

            return $name === '' ? null : [
                'name' => $name,
                'phone' => null,
                'email' => $user->email ?? null,
                'crm_user_id' => $user->id,
            ];
        } catch (\Throwable $e) {
            Log::warning("⚠️ Commercial affecté introuvable: " . $e->getMessage());
            return null;
        }
    }

    /**
     * ⏸️ L'AGENT EST-IL EN PAUSE POUR CETTE CONVERSATION ? (un commercial a repris la main)
     */
    private function isAgentPaused($sessionId)
    {
        try {
            $conversation = Conversation::where('session_id', $sessionId)->first();

            return $conversation ? (new AgentFinalService($conversation->state ?? []))->isBotPaused() : false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * 🎤 TRANSCRIRE UN MESSAGE VOCAL (darija, arabe, français, anglais)
     * Modèle et fournisseur : config/greenland.php ('transcription').
     *
     * @return array|null ['text' => '…', 'language' => 'fr'|'darija'|'ar'|'en'|null, …]
     */
    private function transcribeVoiceMessage($audioBytes, $mimeType)
    {
        try {
            $transcription = (new Transcriber())->transcribe((string) $audioBytes, (string) ($mimeType ?: 'audio/ogg'));
            $text = is_array($transcription) ? trim((string) ($transcription['text'] ?? '')) : '';

            if ($text === '') {
                return null;
            }

            Log::info("✅ Vocal transcrit", [
                'text' => $text,
                'language' => $transcription['language'] ?? null,
                'model' => $transcription['model'] ?? null,
            ]);

            return $transcription;
        } catch (\Throwable $e) {
            Log::error("❌ Erreur transcription: " . $e->getMessage());
            return null;
        }
    }

    /**
     * 🔔 LE COMMERCIAL DOIT-IL ÊTRE NOTIFIÉ ?
     *
     * Uniquement sur une transition :
     *   1. le prospect vient d'être qualifié (typologie + budget cohérents) ;
     *   2. le prospect vient de demander un conseiller (rappel ou visite).
     *
     * Les messages suivants ne déclenchent plus rien : le commercial n'est pas
     * notifié à chaque phrase du prospect.
     */
    private function shouldNotifyCommercial(array $previousState, array $newState)
    {
        $flag = function (array $state, array $keys) {
            foreach ($keys as $key) {
                if (!empty($state[$key])) {
                    return true;
                }
            }
            return false;
        };

        $advisorKeys = ['handoff_requested', 'wants_callback', 'wants_visit', 'commercial_contact_requested', 'visit_requested'];

        $becameQualified = empty($previousState['lead_qualified']) && !empty($newState['lead_qualified']);
        $askedForAdvisor = !$flag($previousState, $advisorKeys) && $flag($newState, $advisorKeys);

        if ($becameQualified || $askedForAdvisor) {
            Log::info("🔔 Notification commercial justifiée", [
                'lead_qualifie' => $becameQualified,
                'demande_conseiller' => $askedForAdvisor,
            ]);

            return true;
        }

        return false;
    }

    /**
     * 👤 NOM DU PROSPECT : fiche CRM en priorité, sinon nom du profil WhatsApp
     */
    private function resolveProspectName($prospectId, $profileName = null)
    {
        if ($prospectId) {
            $prospect = Prospect::on('temp')->find($prospectId);

            if ($prospect) {
                $nom = trim($prospect->nom ?? '');
                $prenom = trim($prospect->prenom ?? '');
                $fullName = trim($nom . ' ' . $prenom);

                if ($fullName !== '') {
                    Log::info("👤 Nom du prospect récupéré", [
                        'prospect_id' => $prospectId,
                        'full_name' => $fullName,
                    ]);

                    return $fullName;
                }
            }
        }

        $profileName = trim((string) $profileName);

        return ($profileName === '' || $profileName === 'Utilisateur WhatsApp') ? null : $profileName;
    }

    /**
     * 🔄 REPORTER DANS LE CRM CE QUE L'AGENT A COLLECTÉ
     * (nom communiqué dans la conversation, numéro de rappel différent du WhatsApp)
     */
    private function syncProspectFromAgent($prospectId, array $state, $whatsappNumber)
    {
        try {
            $prospect = Prospect::on('temp')->find($prospectId);
            if (!$prospect) {
                return;
            }

            $changed = false;

            // ✅ Nom donné par le prospect, si la fiche est vide
            if (empty($prospect->nom) && !empty($state['name'])) {
                $parts = preg_split('/\s+/', trim($state['name']));
                $prospect->nom = array_shift($parts);

                if (empty($prospect->prenom) && !empty($parts)) {
                    $prospect->prenom = implode(' ', $parts);
                }

                $changed = true;
            }

            // ✅ Numéro de rappel différent du numéro WhatsApp → second téléphone
            $callbackPhone = $state['phone'] ?? null;
            $digits = function ($value) {
                return preg_replace('/\D+/', '', (string) $value);
            };

            if ($callbackPhone
                && $digits($callbackPhone) !== $digits($whatsappNumber)
                && $digits($callbackPhone) !== $digits($prospect->telephone)
                && empty($prospect->telephone_num2)) {
                $prospect->telephone_num2 = $callbackPhone;
                $changed = true;
            }

            if ($changed) {
                $prospect->save();
                Log::info("✅ Fiche prospect mise à jour depuis l'agent", [
                    'prospect_id' => $prospectId,
                ]);
            }
        } catch (\Exception $e) {
            Log::error("❌ Erreur synchronisation prospect: " . $e->getMessage());
        }
    }

    /**
     * 🛡️ ANTI-DOUBLON
     * Twilio réessaie le webhook si la réponse dépasse ~15 s : sans ce contrôle,
     * le même message est traité deux fois et le prospect reçoit deux réponses.
     */
    private function isDuplicateWebhook($messageSid)
    {
        if (empty($messageSid)) {
            return false;
        }

        try {
            return DB::connection('temp')
                ->table('whatsapp_messages')
                ->where('message_sid', $messageSid)
                ->exists();
        } catch (\Exception $e) {
            Log::warning("⚠️ Vérification doublon impossible: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 🔥 VÉRIFIER SI LA CONVERSATION EXISTE ET SI ELLE EST TERMINÉE
     *
     * @return array ['exists' => bool, 'is_completed' => bool]
     *
     * NOTE : 'appointment_date' n'est pas renseigné par l'agent actuel, la condition
     * n'est donc jamais vraie et l'agent répond en continu (comportement voulu
     * aujourd'hui). Pour que le commercial prenne le relais après la transmission
     * d'un lead, remplacer $isFullyCompleted par : !empty($state['handoff_notified']).
     */
    private function isExistingConversation($projetId, $phoneNumber)
    {
        try {
            $sessionId = $this->getSessionId($phoneNumber, $projetId);

            $conversation = \App\Models\Conversation::where('session_id', $sessionId)->first();

            if (!$conversation) {
                Log::info("📊 Aucune conversation existante: {$phoneNumber}", [
                    'session_id' => $sessionId,
                ]);

                return [
                    'exists' => false,
                    'is_completed' => false,
                ];
            }

            $state = $conversation->state ?? [];

            $hasName = !empty($state['name']);
            $hasDate = !empty($state['appointment_date']);
            $visitAccepted = !empty($state['visit_accepted']);
            $isCompleted = !empty($state['completed']);

            $isFullyCompleted = $hasName && $hasDate && $visitAccepted && $isCompleted;

            Log::info("📊 Conversation existante analysée: {$phoneNumber}", [
                'session_id' => $sessionId,
                'conversation_id' => $conversation->id,
                'prospect_id' => $conversation->prospect_id,
                'has_name' => $hasName,
                'has_date' => $hasDate,
                'visit_accepted' => $visitAccepted,
                'is_completed' => $isCompleted,
                'is_fully_completed' => $isFullyCompleted,
            ]);

            return [
                'exists' => true,
                'is_completed' => $isFullyCompleted,
            ];

        } catch (\Exception $e) {
            Log::error("❌ Erreur vérification conversation: " . $e->getMessage());

            return [
                'exists' => false,
                'is_completed' => false,
            ];
        }
    }

    /**
     * 🔥 EXTRAIRE LE SESSION ID DU NUMÉRO
     */
    private function getSessionId($phoneNumber, $projetId)
    {
        return 'whatsapp_' . $projetId . '_' . preg_replace('/[^0-9]/', '', $phoneNumber);
    }

    /**
     * 🔥 ENVOYER LA RÉPONSE DE L'AGENT (texte, photos, plans, vidéo, brochure, localisation, boutons)
     *
     * ✅ L'ordre est fixé par l'agent : le TEXTE part en premier, les médias ensuite, puis la
     *    question avec ses boutons. Après des photos, une question sans bouton devient la légende
     *    de la dernière photo : elle s'affiche forcément après elle.
     * ✅ Chaque message envoyé est archivé dans whatsapp_messages (messagerie du CRM).
     */
    private function sendAgentResponse($to, array $agentResult, $config, $projetId, $sessionId, $prospectId = null, $societe = null)
    {
        try {
            // ✅ Agent en pause : un commercial a repris la conversation, rien n'est envoyé
            if (!empty($agentResult['silent'])) {
                Log::info("🤫 Agent en pause : aucune réponse automatique", ['session_id' => $sessionId]);
                return false;
            }

            $sent = $this->twilioSender($config, $projetId, $to, $prospectId, $societe)->sendResult($to, $agentResult);

            // ✅ MARQUER LE MESSAGE BOT (programme le follow-up)
            if ($sent > 0) {
                $conversation = \App\Models\Conversation::where('session_id', $sessionId)->first();
                if ($conversation) {
                    $conversation->markBotMessage();
                }
            }

            Log::info("✅ Réponse de l'agent envoyée + follow-up programmé", [
                'session_id' => $sessionId,
                'to' => $to,
                'messages_envoyes' => $sent,
            ]);

            return $sent > 0;

        } catch (\Throwable $e) {
            Log::error("❌ Erreur envoi réponse agent: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 📤 EXPÉDITEUR TWILIO DE L'AGENT
     * Envoie les messages (texte, médias, épingle de localisation, boutons, listes, vocal)
     * et archive chacun dans whatsapp_messages.
     */
    private function twilioSender($config, $projetId, $to, $prospectId = null, $societe = null)
    {
        $twilio = new \Twilio\Rest\Client($config->account_sid, $config->access_token);

        $archive = function (array $message) use ($config, $projetId, $to, $prospectId) {
            DB::connection('temp')->table('whatsapp_messages')->insert([
                'projet_id' => $projetId,
                'from_number' => $config->phone_number_id,
                'to_number' => $to,
                'message' => $message['message'] ?? '',
                'message_sid' => $message['message_sid'] ?? null,
                'profile_name' => 'Agent Virtuel Karim',
                'status' => 'sent',
                'message_type' => $message['message_type'] ?? 'agent_response',
                'media_url' => $message['media_url'] ?? null,
                'prospect_id' => $prospectId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        };

        // ✅ Réponses vocales (config greenland.voice_replies) : fichier audio publié comme les autres médias
        $publicFile = null;
        $voice = null;
        if ($societe && config('greenland.voice_replies.enabled', false)) {
            $voice = new VoiceSynthesizer();
            $publicFile = function (string $bytes, string $mime, string $filename) use ($societe) {
                return $this->storePublicFile($bytes, $mime, $filename, $societe);
            };
        }

        return new TwilioWhatsApp(
            $twilio,
            (string) $config->account_sid,
            (string) $config->access_token,
            (string) $config->phone_number_id,
            $archive,
            $publicFile,
            $voice
        );
    }

    /**
     * 💾 DÉPOSER UN FICHIER PRODUIT PAR L'AGENT (réponse vocale) ET RENVOYER SON URL PUBLIQUE
     */
    private function storePublicFile($bytes, $mime, $filename, $societe, $doss = 'whatsapp_media')
    {
        $tempPath = null;

        try {
            $fileName = time() . '_' . Str::random(6) . '_' . $filename;
            $tempPath = tempnam(sys_get_temp_dir(), 'greenland_');
            file_put_contents($tempPath, $bytes);

            $file = new \Illuminate\Http\UploadedFile($tempPath, $fileName, $mime, null, true);
            FichierHelper::ajouter_fichier($file, $societe->raison_sociale_concatene, $societe->id, $doss, $fileName);

            return FichierHelper::get_file_url($societe->raison_sociale_concatene, $societe->id, $doss, $fileName);
        } catch (\Throwable $e) {
            Log::warning("⚠️ Fichier de l'agent non publié: " . $e->getMessage());
            return null;
        } finally {
            if ($tempPath && file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    /**
     * 🔥 EXÉCUTER UNE ACTION DE L'AGENT DESTINÉE À L'ÉQUIPE
     * (photos, plans, localisation et boutons sont déjà partis avec sendAgentResponse)
     */
    private function executeAgentAction(array $action, $to, $config, $projetId, $prospectId = null, array $history = [])
    {
        try {
            $type = $action['type'] ?? null;

            switch ($type) {
                case 'notify_commercial':
                    // ✅ 1. ENREGISTRER LE RDV EN PREMIER (data métier prioritaire)
                    try {
                        (new \App\Services\GreenLand\RendezVousAgentService())
                            ->enregistrer(
                                $action['payload'] ?? [],
                                (int) $projetId,
                                $prospectId ? (int) $prospectId : null
                            );
                    } catch (\Throwable $e) {
                        Log::error('[RDV Agent] Erreur enregistrement depuis executeAgentAction', [
                            'prospect_id' => $prospectId,
                            'projet_id'   => $projetId,
                            'error'       => $e->getMessage(),
                        ]);
                    }

                    // ✅ 2. NOTIFIER LE COMMERCIAL (secondaire — ne bloque pas le RDV)
                    try {
                        $this->notifyCommercialFromAgent(
                            $action['payload'] ?? [],
                            $projetId,
                            $prospectId,
                            $history
                        );
                    } catch (\Throwable $e) {
                        Log::error('[RDV Agent] Erreur notification commercial', [
                            'prospect_id' => $prospectId,
                            'error'       => $e->getMessage(),
                        ]);
                    }
                    break;

                // ✅ 2. DEMANDE DE SUPPRESSION DES DONNÉES (loi 09-08)
                case 'data_deletion_request':
                    $this->notifyDataDeletionRequest($action, $to, $projetId, $prospectId);
                    break;

                // ✅ 3. QUESTION RESTÉE SANS RÉPONSE : à ajouter à la FAQ (config/greenland.php → 'faq')
                case 'unanswered_question':
                    Log::info("❓ Question sans réponse de l'agent", [
                        'question' => $action['question'] ?? null,
                        'from' => $to,
                        'prospect_id' => $prospectId,
                    ]);
                    break;

                // ✅ 4. MESSAGE À TRANSMETTRE : déjà visible dans la messagerie WhatsApp du CRM
                case 'forward_to_commercial':
                    break;

                // ✅ Envois au prospect : déjà faits par sendAgentResponse
                case 'send_media':
                case 'send_document':
                case 'send_location':
                case 'send_cta':
                case 'send_call_button':
                    break;

                default:
                    Log::warning("⚠️ Action inconnue: {$type}");
                    break;
            }
        } catch (\Throwable $e) {
            Log::error("❌ Erreur exécution action: " . $e->getMessage());
        }
    }

    /**
     * 🔥 NOTIFIER LE COMMERCIAL (depuis l'agent)
     *
     * Un titre par événement de l'agent : lead qualifié, lead chaud, demande de rappel ou de visite,
     * visite réservée, urgence (prospect mécontent ou qui demande un humain), mise à jour
     * (nouveau créneau, annulation…). La fiche contient le score, la qualification complète,
     * un résumé de la conversation rédigé par l'IA et les questions restées sans réponse.
     */
    private function notifyCommercialFromAgent(array $payload, $projetId, $prospectId = null, array $history = [])
    {
        try {
            // ✅ prospect_id : paramètre du contrôleur, sinon champ conservé dans l'état
            $prospectId = $prospectId ?: ($payload['prospect_id'] ?? null);
            $event = (string) ($payload['event'] ?? 'lead');

            // ✅ La demande de suppression des données a sa propre notification (data_deletion_request)
            if (($payload['update'] ?? null) === 'data_deletion') {
                return;
            }

            Log::info("📤 Notification commercial depuis l'agent", [
                'event' => $event,
                'update' => $payload['update'] ?? null,
                'score' => $payload['lead_score'] ?? null,
                'prospect_id' => $prospectId,
            ]);

            // ✅ Trouver le commercial affecté
            // ✅ Affectation garantie avant la notification (tour de rôle via last_affected) :
            //    sans commercial affecté, la notification partait avec user_id = null.
            $assignedCommercialId = $this->ensureProspectAssigned($prospectId, $projetId);

            // ✅ Résumé de la conversation rédigé par l'IA, pour les événements qui précèdent un appel
            $aiSummary = null;
            if (in_array($event, ['hot_lead', 'handoff', 'visit_booked', 'escalation'], true)
                && config('greenland.crm_notifications.ai_summary', true)
                && !empty($history)) {
                $aiSummary = (new LeadSummarizer())->summarize($history, $payload);
            }

            // ✅ Créer la notification
            $notification = new Notification();
            $notification->setConnection('temp');
            $notification->date = now();
            $notification->type = 50; // Demande de rappel / lead via agent
            $notification->description_type = $this->agentNotificationText($payload, $aiSummary);
            $notification->lien = $prospectId ? "/crm/prospects/" . $prospectId : "/prospects";
            $notification->role = 3;
            $notification->user_id = $assignedCommercialId ?: null;
            $notification->projet_id = $projetId;
            $notification->prospect_id = $prospectId;
            $notification->save();

            Config::set('broadcasting.default', 'pusher_notify');
            broadcast(new NotificationEvent($notification->id));

            Log::info("✅ Notification commercial créée", [
                'notification_id' => $notification->id,
                'event' => $event,
                'commercial_id' => $assignedCommercialId,
                'prospect_id' => $prospectId,
            ]);
        } catch (\Throwable $e) {
            Log::error("❌ Erreur notification commercial: " . $e->getMessage());
        }
    }

    /**
     * 🏷️ TITRE DE LA NOTIFICATION selon l'événement de l'agent
     */
    private function agentNotificationTitle(array $payload)
    {
        $updates = [
            'schedule' => 'nouveau créneau de rappel',
            'phone' => 'nouveau numéro de rappel',
            'schedule+phone' => 'nouveau créneau et nouveau numéro',
            'cancelled' => 'demande de rappel annulée',
            'visit_cancelled' => 'visite annulée',
            'visit_rescheduled' => 'visite déplacée',
            'visit_confirmed_by_prospect' => 'visite confirmée par le prospect',
            'visit_requested' => 'le prospect demande aussi une visite',
            'visit_booked' => 'visite réservée',
            'escalation' => 'le prospect demande à être rappelé rapidement',
            'opt_out' => 'le prospect ne souhaite plus être contacté',
            'opt_out_visit_cancelled' => 'le prospect ne souhaite plus être contacté (visite annulée)',
        ];

        switch ((string) ($payload['event'] ?? 'lead')) {
            case 'escalation':
                return ($payload['escalation_reason'] ?? null) === 'demande_humain'
                    ? "🚨 *URGENT – LE PROSPECT VEUT PARLER À UN CONSEILLER*"
                    : "🚨 *URGENT – PROSPECT MÉCONTENT*";
            case 'hot_lead':
                return "🔥 *LEAD CHAUD (" . (int) ($payload['lead_score'] ?? 0) . "/100)*";
            case 'visit_booked':
                return "📅 *VISITE RÉSERVÉE*";
            case 'handoff':
                return !empty($payload['requested_visit']) && empty($payload['requested_callback'])
                    ? "📅 *DEMANDE DE VISITE*"
                    : "📞 *DEMANDE DE RAPPEL*";
            case 'update':
                $update = (string) ($payload['update'] ?? '');
                $label = $updates[$update] ?? ($update !== '' ? $update : 'changement');
                return (str_contains($update, 'cancel') || str_starts_with($update, 'opt_out') ? "❌" : "🔄") . " *MISE À JOUR : " . mb_strtoupper($label, 'UTF-8') . "*";
            default:
                return "🎯 *LEAD QUALIFIÉ (agent)*";
        }
    }

    /**
     * 📋 FICHE DU PROSPECT dans la notification
     */
    private function agentNotificationText(array $payload, $aiSummary = null)
    {
        $value = function ($key) use ($payload) {
            $v = $payload[$key] ?? null;
            return is_scalar($v) && trim((string) $v) !== '' ? trim((string) $v) : null;
        };

        $timelines = ['immediat' => 'Dès que possible', 'trois_six_mois' => 'Dans 3 à 6 mois', 'plus_tard' => 'Plus de 6 mois', 'renseignement' => 'Se renseigne'];
        $financings = ['comptant' => 'Comptant', 'credit' => 'Crédit', 'mixte' => 'Apport + crédit', 'indecis' => 'À définir'];
        $temperatures = ['chaud' => 'chaud 🔥', 'tiede' => 'tiède', 'froid' => 'froid'];

        $budget = $value('budget')
            ? number_format((int) $payload['budget'], 0, ',', ' ') . ' DH'
            : ($value('budget_range') ?: 'Non précisé');

        $visit = is_array($payload['visit'] ?? null) && !empty($payload['visit']['start'])
            && in_array($payload['visit']['status'] ?? 'confirmed', ['confirmed', 'rescheduling'], true)
            ? $this->frenchDateTime((string) $payload['visit']['start'])
            : null;

        $lines = [
            $this->agentNotificationTitle($payload),
            '',
            "👤 Nom: " . ($value('name') ?: ($value('whatsapp_name') ?: 'Non fourni')),
            "📞 Téléphone: " . ($value('phone') ?: 'Non fourni'),
            "📊 Score: " . (int) ($payload['lead_score'] ?? 0) . "/100 (" . ($temperatures[$payload['lead_temperature'] ?? ''] ?? 'froid') . ")",
            "🏠 Type: " . ($value('property_type') ?: 'Non précisé'),
            "🎯 Projet: " . ($value('purpose') ? ucfirst($value('purpose')) : 'Non précisé'),
            "💰 Budget: " . $budget,
            "⏳ Délai d'achat: " . ($timelines[$payload['timeline'] ?? ''] ?? 'Non précisé'),
            "🏦 Financement: " . ($financings[$payload['financing'] ?? ''] ?? 'Non précisé'),
        ];

        if (!empty($payload['is_mre'])) {
            $lines[] = "🌍 Vit à l'étranger" . ($value('prospect_country') && $payload['prospect_country'] !== 'Maroc' ? ' (' . $payload['prospect_country'] . ')' : '');
        }
        if ($visit) {
            $lines[] = "📅 Visite: " . $visit;
        } elseif (!empty($payload['requested_callback']) || ($payload['status'] ?? '') === 'callback_or_visit_requested') {
            $lines[] = "🕒 Créneau souhaité: " . ($value('callback_slot_label') ?: 'Dès que possible')
                . ($value('callback_time_prospect_local') ? ' — heure du prospect : ' . $payload['callback_time_prospect_local'] : '');
        }
        if (!empty($payload['ad']['headline']) || !empty($payload['ad']['source_id'])) {
            $lines[] = "📣 Publicité: " . ($payload['ad']['headline'] ?? $payload['ad']['source_id']);
        }

        $summary = $aiSummary ?: $value('summary');
        if ($summary) {
            $lines[] = '';
            $lines[] = "🧠 Résumé: " . $summary;
        }
        if (!empty($payload['unanswered_questions'])) {
            $lines[] = "❓ Questions sans réponse: " . implode(' | ', array_slice((array) $payload['unanswered_questions'], -3));
        }
        if ($value('last_voice_transcript')) {
            $lines[] = "🎤 Dernier vocal: « " . mb_substr($payload['last_voice_transcript'], 0, 200, 'UTF-8') . " »";
        }
        $lines[] = "📝 Dernier message: " . ($value('last_message') ?: '');

       // return implode("\n", $lines);
        // ✅ La colonne notifications.description_type est VARCHAR(255) : on tronque
        return mb_substr(implode("\n", $lines), 0, 255, 'UTF-8');
    }

    /**
     * 🗓️ « jeudi 22/10 à 15h » (heure du Maroc)
     */
    private function frenchDateTime($iso)
    {
        try {
            $date = Carbon::parse($iso)->setTimezone(GreenLandSettings::timezone());
            $days = [1 => 'lundi', 2 => 'mardi', 3 => 'mercredi', 4 => 'jeudi', 5 => 'vendredi', 6 => 'samedi', 7 => 'dimanche'];

            return $days[(int) $date->format('N')] . ' ' . $date->format('d/m') . ' à ' . $date->format('G') . 'h' . ($date->format('i') !== '00' ? $date->format('i') : '');
        } catch (\Throwable $e) {
            return (string) $iso;
        }
    }

    /**
     * 🗑️ DEMANDE DE SUPPRESSION DES DONNÉES (loi 09-08) : notification à traiter dans le CRM
     * L'agent a déjà arrêté les relances et annulé le rendez-vous éventuel.
     */
    private function notifyDataDeletionRequest(array $action, $from, $projetId, $prospectId = null)
    {
        try {
            $commercialId = null;
            if ($prospectId) {
                $prospect = Prospect::on('temp')->find($prospectId);
                $commercialId = $prospect->commercial_affecte ?? null;
            }

            $notification = new Notification();
            $notification->setConnection('temp');
            $notification->date = now();
            $notification->type = 50;
            $notification->description_type = "🗑️ *DEMANDE DE SUPPRESSION DES DONNÉES (loi 09-08)*\n\n"
                . "👤 Nom: " . ($action['name'] ?? 'Non fourni') . "\n"
                . "📞 Téléphone: " . $from . "\n"
                . "L'agent a arrêté les relances et annulé le rendez-vous éventuel. "
                . "Supprimez ou anonymisez la fiche du prospect et ses messages dans le CRM.";
            $notification->lien = $prospectId ? "/crm/prospects/" . $prospectId : "/prospects";
            $notification->role = 3;
            $notification->user_id = $commercialId ?: null;
            $notification->projet_id = $projetId;
            $notification->prospect_id = $prospectId;
            $notification->save();

            Config::set('broadcasting.default', 'pusher_notify');
            broadcast(new NotificationEvent($notification->id));

            Log::warning("🗑️ Demande de suppression des données", ['from' => $from, 'prospect_id' => $prospectId]);
        } catch (\Throwable $e) {
            Log::error("❌ Erreur notification suppression des données: " . $e->getMessage());
        }
    }

    /**
     * 👥 COMMERCIAUX ÉLIGIBLES POUR UN PROJET
     *
     * Requête directe sur la connexion 'temp' via la table pivot (user_projets).
     * La relation Eloquent whereHas('projets') cherchait la table sur la connexion
     * par défaut : elle levait une exception et aucun commercial n'était trouvé.
     */
    private function getProjectCommercials($projetId)
{
    try {
        // ✅ Utiliser le helper (même source de vérité)
        $commercials = CommercialAssignmentHelper::getEligibleCommercials($projetId, 'temp');

        if ($commercials->isNotEmpty()) {
            Log::info("👥 [WhatsApp] Commerciaux du projet", [
                'projet_id' => $projetId,
                'count'     => $commercials->count(),
                'ids'       => $commercials->pluck('id')->toArray(),
            ]);
            return $commercials;
        }

        // Fallback : tous les commerciaux actifs (si aucun rattachement projet)
        $fallback = User::on('temp')
            ->where('role', 3)
            ->where('is_actif', 1)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get();

        Log::warning("⚠️ [WhatsApp] Aucun commercial lié au projet, fallback sur tous", [
            'projet_id' => $projetId,
            'count'     => $fallback->count(),
        ]);

        return $fallback;

    } catch (\Exception $e) {
        Log::error("❌ [WhatsApp] Erreur getProjectCommercials: " . $e->getMessage());
        return collect();
    }
}

    /**
     * 🎯 GARANTIR QU'UN COMMERCIAL EST AFFECTÉ AVANT D'ENVOYER UNE NOTIFICATION
     * Sans cela, la notification part avec user_id = null et n'atteint personne.
     */
    private function ensureProspectAssigned($prospectId, $projetId)
    {
        if (!$prospectId) {
            return null;
        }

        try {
            $prospect = Prospect::on('temp')->find($prospectId);
            if ($prospect && !empty($prospect->commercial_affecte)) {
                return $prospect->commercial_affecte;
            }

            // Le prospect n'est pas encore affecté : affectation à tour de rôle
            $assignedId = $this->autoAssignSingleProspect($prospectId, $projetId);

            if ($assignedId) {
                return $assignedId;
            }

            // Dernier recours : le premier commercial actif, pour que la notification ait un destinataire
            $fallback = $this->getProjectCommercials($projetId)->first();

            Log::warning("⚠️ Affectation impossible, notification envoyée au premier commercial", [
                'prospect_id' => $prospectId,
                'projet_id' => $projetId,
                'commercial_id' => $fallback->id ?? null,
            ]);

            return $fallback->id ?? null;
        } catch (\Exception $e) {
            Log::error("❌ Erreur affectation avant notification: " . $e->getMessage());
            return null;
        }
    }

    /* =====================================================================
     |  MESSAGES
     * ===================================================================== */

    /**
     * Marquer les messages comme lus pour une conversation spécifique
     */
    public function markMessagesAsRead(Request $request, $projetId, $phoneNumber)
    {
        try {
            DatabaseHelper::Config();

            // Vérifier si la table existe
            if (!Schema::connection('temp')->hasTable('whatsapp_messages')) {
                return response()->json(['success' => false, 'message' => 'Table non trouvée'], 404);
            }

            // Mettre à jour les messages non lus
            $updated = DB::connection('temp')
                ->table('whatsapp_messages')
                ->where('projet_id', $projetId)
                ->where('from_number', $phoneNumber)
                ->whereNull('read_at')
                ->where('status', 'received')
                ->update([
                    'read_at' => now(),
                    'status' => 'read',
                    'updated_at' => now(),
                ]);

            Log::info("✅ Messages marqués comme lus pour le numéro: {$phoneNumber}, projet: {$projetId}, {$updated} message(s) mis à jour");

            return response()->json([
                'success' => true,
                'updated_count' => $updated,
                'message' => "{$updated} message(s) marqué(s) comme lu(s)",
            ]);

        } catch (\Exception $e) {
            Log::error("❌ Erreur lors du marquage des messages comme lus: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Configurer la base de données pour une société spécifique
     */
    private function configureDatabaseForSociete($societeId)
    {
        try {
            $societe = \App\Models\Societe::find($societeId);
            if (!$societe) {
                throw new \Exception("Société non trouvée: {$societeId}");
            }

            $databaseName = env('DB_DATABASE');
            // $databaseName = 'Erp_' . $societe->raison_sociale_concatene . '_' . $societe->id;
            $connection = DatabaseHelper::Connection_database($databaseName);
            config(['database.connections.temp' => $connection]);
            DB::purge('temp');
            DB::reconnect('temp');

            Log::info("Database configured for société: {$societe->id} - {$databaseName}");

        } catch (\Exception $e) {
            Log::error("Error configuring database: " . $e->getMessage());
            throw $e;
        }
    }

    /* =====================================================================
     |  AFFECTATION DES PROSPECTS
     * ===================================================================== */

    /**
     * 🔥 AUTO-AFFECTATION D'UN PROSPECT À UN COMMERCIAL
     * Logique circulaire basée sur last_affected
     *
     * @param int $prospectId ID du prospect à affecter
     * @param int $projetId ID du projet
     * @return int|false ID du commercial affecté ou false si échec
     */
    /*private function autoAssignSingleProspect($prospectId, $projetId)
    {
        try {
            Log::info('🔄 Début de l\'auto-affectation pour le prospect', [
                'prospect_id' => $prospectId,
                'projet_id' => $projetId,
            ]);

            // ✅ La connexion temp est déjà configurée dans le webhook
            // On n'appelle PAS DatabaseHelper::Config() ici

            // Get prospect
            $prospect = Prospect::on('temp')->find($prospectId);
            if (!$prospect) {
                Log::error('❌ Prospect non trouvé pour l\'auto-affectation', ['prospect_id' => $prospectId]);
                return false;
            }

            // ✅ Vérifier si le prospect a déjà un commercial affecté
            if (!empty($prospect->commercial_affecte)) {
                Log::info('ℹ️ Prospect déjà affecté', [
                    'prospect_id' => $prospectId,
                    'commercial_id' => $prospect->commercial_affecte,
                ]);
                return $prospect->commercial_affecte;
            }

            // ✅ Commerciaux du projet (table pivot, sans relation Eloquent inter-connexions)
            $commercials = $this->getProjectCommercials($projetId);

            if ($commercials->isEmpty()) {
                Log::error('❌ Aucun commercial actif trouvé', ['projet_id' => $projetId]);
                return false;
            }

            // ============================================
            // ✅ LOGIQUE CIRCULAIRE basée sur last_affected
            // ============================================

            // If only one commercial, assign to them
            if ($commercials->count() === 1) {
                $targetCommercial = $commercials->first();
                Log::info('✅ Un seul commercial trouvé', [
                    'commercial_id' => $targetCommercial->id,
                    'name' => $targetCommercial->name . ' ' . $targetCommercial->prenom,
                ]);
            } else {
                // ✅ CHECK: Are all commercials have last_affected = 1?
                $allHaveLastAffected = true;
                foreach ($commercials as $commercial) {
                    if ($commercial->last_affected == 0) {
                        $allHaveLastAffected = false;
                        break;
                    }
                }

                // ✅ If all have last_affected = 1, reset ALL to 0
                if ($allHaveLastAffected) {
                    Log::info('🔄 Tous les commerciaux ont last_affected = 1, reset de tous à 0');
                    User::on('temp')
                        ->where('role', 3)
                        ->where('is_actif', 1)
                        ->whereNull('deleted_at')
                        ->update(['last_affected' => 0]);

                    // Refresh the collection
                    $commercials = $this->getProjectCommercials($projetId);

                    Log::info('✅ Tous les commerciaux réinitialisés à last_affected = 0');
                }

                // ✅ Step 1: Find commercial with last_affected = 0
                $targetCommercial = null;

                foreach ($commercials as $commercial) {
                    if ($commercial->last_affected == 0) {
                        $targetCommercial = $commercial;
                        Log::info('✅ Commercial trouvé avec last_affected = 0', [
                            'commercial_id' => $commercial->id,
                            'name' => $commercial->name . ' ' . $commercial->prenom,
                        ]);
                        break;
                    }
                }

                // ✅ Step 2: If all have last_affected = 1, take the first one
                if (!$targetCommercial) {
                    $targetCommercial = $commercials->first();
                    Log::info('🔄 Tous les commerciaux ont last_affected = 1, prise du premier', [
                        'commercial_id' => $targetCommercial->id,
                        'name' => $targetCommercial->name . ' ' . $targetCommercial->prenom,
                    ]);
                }
            }

            // Get system user (Admin)
            $systemUser = User::on('temp')
                ->where('role', 1) // ROLE_ADMIN
                ->whereNull('deleted_at')
                ->first();

            // ✅ Start transaction
            DB::connection('temp')->beginTransaction();

            try {
                $newCommercialId = $targetCommercial->id;

                // ✅ 1. Update prospect assignment
                $prospect->commercial_affecte = $newCommercialId;
                if ($systemUser) {
                    $prospect->affecte_par_admin_id = $systemUser->id;
                }
                $prospect->date_affectation = Carbon::now();
                $prospect->save();

                Log::info('✅ Prospect mis à jour avec le commercial', [
                    'prospect_id' => $prospectId,
                    'commercial_id' => $newCommercialId,
                ]);

                // ✅ 2. Create "Affecte" status for the prospect
                $statutProspect = new StatutProspect();
                $statutProspect->setConnection('temp');
                $statutProspect->prospect_id = $prospectId;
                $statutProspect->statut = '6'; // Affecté
                $statutProspect->date_traitement = Carbon::now();
                $statutProspect->user_id_traite = $systemUser ? $systemUser->id : null;
                $statutProspect->commentaire = 'Prospect affecté automatiquement après création via WhatsApp';
                $statutProspect->type_traitement_rdv_relance = 0;
                $statutProspect->created_at = now();
                $statutProspect->updated_at = now();
                $statutProspect->save();

                Log::info('✅ Statut "Affecté" créé', [
                    'prospect_id' => $prospectId,
                    'statut_id' => $statutProspect->id,
                ]);

                // ✅ 3. Update nb_prospects counter
                $commercialUser = $targetCommercial;
                $oldCount = $commercialUser->nb_prospects ?? 0;
                $commercialUser->nb_prospects = $oldCount + 1;

                // ✅ 4. Set last_affected = 1 for the selected commercial
                $commercialUser->last_affected = 1;
                $commercialUser->save();

                Log::info('✅ Compteurs du commercial mis à jour', [
                    'commercial_id' => $newCommercialId,
                    'old_count' => $oldCount,
                    'new_count' => $oldCount + 1,
                    'last_affected' => 1,
                ]);

                // ✅ 5. Reset last_affected = 0 for all other commercials
                User::on('temp')
                    ->where('id', '!=', $newCommercialId)
                    ->where('role', 3)
                    ->where('is_actif', 1)
                    ->whereNull('deleted_at')
                    ->update(['last_affected' => 0]);

                Log::info('✅ Reset last_affected = 0 pour les autres commerciaux');

                // ✅ 6. COMMIT transaction
                DB::connection('temp')->commit();

                Log::info('✅ Transaction validée avec succès');

                // ✅ 7. Send notification (OUTSIDE transaction) — désactivée
                // $this->sendAffectationNotification($newCommercialId, $prospectId, $projetId);

                // ✅ 8. Verify data was saved
                $verifyProspect = Prospect::on('temp')->find($prospectId);
                $verifyStatus = StatutProspect::on('temp')
                    ->where('prospect_id', $prospectId)
                    ->orderBy('id', 'desc')
                    ->first();
                $verifyUser = User::on('temp')
                    ->where('id', $newCommercialId)
                    ->whereNull('deleted_at')
                    ->first();

                Log::info('🔍 Vérification après commit', [
                    'prospect_commercial_id' => $verifyProspect ? $verifyProspect->commercial_affecte : null,
                    'status_id' => $verifyStatus ? $verifyStatus->id : null,
                    'status_statut' => $verifyStatus ? $verifyStatus->statut : null,
                    'user_nb_prospects' => $verifyUser ? $verifyUser->nb_prospects : null,
                    'user_last_affected' => $verifyUser ? $verifyUser->last_affected : null,
                ]);

                Log::info('✅ Auto-affectation terminée avec succès', [
                    'prospect_id' => $prospectId,
                    'commercial_id' => $newCommercialId,
                    'commercial_name' => $targetCommercial->name . ' ' . $targetCommercial->prenom,
                ]);

                return $newCommercialId;

            } catch (\Exception $e) {
                DB::connection('temp')->rollBack();
                Log::error('❌ Transaction d\'auto-affectation échouée: ' . $e->getMessage());
                Log::error('Trace: ' . $e->getTraceAsString());
                return false;
            }

        } catch (\Exception $e) {
            Log::error('❌ Auto-affectation échouée: ' . $e->getMessage());
            Log::error('Trace: ' . $e->getTraceAsString());
            return false;
        }
    }*/
        /**
 * 🔥 AUTO-AFFECTATION D'UN PROSPECT À UN COMMERCIAL
 * Utilise CommercialAssignmentHelper (cycle 50% global + filtre pivot).
 *
 * @param int $prospectId ID du prospect à affecter
 * @param int $projetId ID du projet
 * @return int|false ID du commercial affecté ou false si échec
 */
private function autoAssignSingleProspect($prospectId, $projetId)
{
    try {
        Log::info('🔄 [WhatsApp] Début auto-affectation', [
            'prospect_id' => $prospectId,
            'projet_id'   => $projetId,
        ]);

        // ✅ Récupérer le prospect
        $prospect = Prospect::on('temp')->find($prospectId);
        if (!$prospect) {
            Log::error('❌ [WhatsApp] Prospect non trouvé', ['prospect_id' => $prospectId]);
            return false;
        }

        // ✅ Déjà affecté ? → ne rien faire
        if (!empty($prospect->commercial_affecte)) {
            Log::info('ℹ️ [WhatsApp] Prospect déjà affecté', [
                'prospect_id'   => $prospectId,
                'commercial_id' => $prospect->commercial_affecte,
            ]);
            return $prospect->commercial_affecte;
        }

        // ✅ Récupérer le system user (admin)
        $systemUser = User::on('temp')
            ->where('role', 1)
            ->whereNull('deleted_at')
            ->first();

        /* ✅ Déléguer au helper (transaction + cycle + incrément + reset)
        $result = CommercialAssignmentHelper::assignProspect(
            $prospectId,
            $projetId,
            'temp',
            $systemUser?->id,
            'WhatsApp'
        );*/
        // ✅ Déléguer au helper DÉDIÉ (round-robin sur lead_resource=1 uniquement)
            $result = \App\Http\Helpers\CommercialAssignmentLeadResourceOneHelper::assignProspect(
                $prospectId,
                $projetId,
                'temp',
                $systemUser?->id,
                'WhatsApp'
            );

        if (!$result['success']) {
            Log::warning('⚠️ [WhatsApp] Affectation échouée', [
                'prospect_id' => $prospectId,
                'message'     => $result['message'] ?? 'unknown',
            ]);
            return false;
        }

        $assignedCommercialId = $result['commercial_id'];

        // ✅ Créer le statut "Affecté" (= 6) — comme avant
        $statutProspect = new StatutProspect();
        $statutProspect->setConnection('temp');
        $statutProspect->prospect_id                 = $prospectId;
        $statutProspect->statut                      = '6';
        $statutProspect->date_traitement             = Carbon::now();
        $statutProspect->user_id_traite              = $systemUser?->id;
        $statutProspect->commentaire                 = 'Prospect affecté automatiquement après création via WhatsApp';
        $statutProspect->type_traitement_rdv_relance = 0;
        $statutProspect->save();

        Log::info('✅ [WhatsApp] Affectation terminée', [
            'prospect_id'   => $prospectId,
            'commercial_id' => $assignedCommercialId,
            'commercial'    => $result['commercial']->name ?? null,
        ]);

        return $assignedCommercialId;

    } catch (\Exception $e) {
        Log::error('❌ [WhatsApp] Auto-affectation échouée: ' . $e->getMessage());
        Log::error('Trace: ' . $e->getTraceAsString());
        return false;
    }
}

    /**
     * 🔥 AFFECTER LE PROSPECT DÈS QU'IL MONTRE UN INTÉRÊT CONCRET
     *
     * Conditions d'affectation :
     *   - Type choisi (F3 ou F4)   OU
     *   - Budget donné              OU
     *   - Visite demandée           OU
     *   - Visite acceptée
     */
    private function assignProspectIfInterested($prospectId, $projetId, array $state)
    {
        try {
            // ✅ Vérifier si le prospect existe
            $prospect = Prospect::on('temp')->find($prospectId);
            if (!$prospect) {
                Log::warning("⚠️ Prospect introuvable", ['prospect_id' => $prospectId]);
                return false;
            }

            // ✅ Vérifier si déjà affecté
            if (!empty($prospect->commercial_affecte)) {
                Log::info("ℹ️ Prospect déjà affecté", [
                    'prospect_id' => $prospectId,
                    'commercial_id' => $prospect->commercial_affecte,
                ]);
                return $prospect->commercial_affecte;
            }

            // ════════════════════════════════════════════════════════════
            // 🎯 VÉRIFIER SI LE CLIENT MONTRE UN INTÉRÊT CONCRET
            // ════════════════════════════════════════════════════════════

            // Critère 1 : Type choisi (F3 ou F4)
            $hasType = !empty($state['property_type'])
                       && in_array(strtoupper($state['property_type']), ['F3', 'F4']);

            // Critère 2 : Budget donné (saisi par le prospect ou déduit par l'agent)
            $hasBudget = !empty($state['budget']);

            // Critère 3 : Visite demandée, acceptée ou réservée
            $wantsVisit = !empty($state['wants_visit'])
                          || !empty($state['visit_requested'])
                          || !empty($state['visit_accepted'])
                          || !empty($state['visit_booking']);

            // Critère 4 : Rappel demandé, demande transmise ou lead qualifié
            $wantsCallback = !empty($state['wants_callback'])
                             || !empty($state['commercial_contact_requested'])
                             || !empty($state['handoff_requested'])
                             || !empty($state['lead_qualified']);

            // ✅ Mêmes critères que la notification côté agent : au moins UN suffit (OU)
            $hasConcreteInterest = $hasType || $hasBudget || $wantsVisit || $wantsCallback;

            Log::info("🔍 Vérification intérêt prospect", [
                'prospect_id' => $prospectId,
                'has_type' => $hasType,
                'property_type' => $state['property_type'] ?? null,
                'has_budget' => $hasBudget,
                'budget' => $state['budget'] ?? null,
                'wants_visit' => $wantsVisit,
                'wants_callback' => $wantsCallback,
                'has_concrete_interest' => $hasConcreteInterest,
            ]);

            // ❌ Pas d'intérêt concret → NE PAS affecter
            if (!$hasConcreteInterest) {
                Log::info("⏸️ Affectation NON déclenchée - Pas d'intérêt concret", [
                    'prospect_id' => $prospectId,
                ]);
                return false;
            }

            // ════════════════════════════════════════════════════════════
            // 🔥 INTÉRÊT CONCRET → AFFECTER
            // ════════════════════════════════════════════════════════════
            Log::info("🎯 Intérêt concret détecté - Affectation déclenchée", [
                'prospect_id' => $prospectId,
                'projet_id' => $projetId,
                'property_type' => $state['property_type'] ?? null,
                'budget' => $state['budget'] ?? null,
                'wants_visit' => $wantsVisit,
                'wants_callback' => $wantsCallback,
            ]);

            // 🔥 Appeler l'auto-affectation
            // ✅ sendAffectationNotification() est déjà appelée à l'intérieur :
            //    le second appel créait une notification en double, il a été retiré.
            $assignedCommercialId = $this->autoAssignSingleProspect($prospectId, $projetId);

            if ($assignedCommercialId) {
                Log::info("✅ Prospect affecté avec succès", [
                    'prospect_id' => $prospectId,
                    'commercial_id' => $assignedCommercialId,
                ]);

                return $assignedCommercialId;
            }

            Log::warning("⚠️ Aucun commercial disponible", [
                'prospect_id' => $prospectId,
            ]);

            return false;

        } catch (\Exception $e) {
            Log::error("❌ Erreur affectation intérêt: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /* =====================================================================
     |  WEBHOOK
     * ===================================================================== */

    /**
     * 🔥 WEBHOOK TWILIO AVEC AGENT VIRTUEL
     */
    public function webhook_whatsapp_business(Request $request)
    {
        try {
            Log::info('📩 Message reçu de Twilio', $request->all());

            // ✅ Vocal + IA + photos peuvent dépasser 30 s : la réponse part jusqu'au bout,
            //    même si Twilio ferme la connexion avant la fin du traitement.
            if (function_exists('set_time_limit')) {
                @set_time_limit(120);
            }
            if (function_exists('ignore_user_abort')) {
                @ignore_user_abort(true);
            }

            // Nettoyer les numéros
            $to = ltrim(str_replace('whatsapp:', '', $request->input('To')), '+');
            $number = str_replace('whatsapp:', '', $request->input('From'));
            $from = '+' . ltrim($number, '+');
            $body = $request->input('Body');
            $messageSid = $request->input('MessageSid');
            $profileName = $request->input('ProfileName', 'Utilisateur WhatsApp');

            // ✅ Bouton ou ligne de liste touché (identifiant « gl:… » des boutons de l'agent)
            $interactiveId = $this->interactiveIdFromRequest($request);

            // ✅ Position partagée par le prospect
            $isLocation = is_numeric($request->input('Latitude')) && is_numeric($request->input('Longitude'));

            // ✅ Prospect venu d'une publicité « Click to WhatsApp »
            $referral = $this->referralFromRequest($request);

            // ✅ Récupérer les informations sur le média
            $numMedia = $request->input('NumMedia', 0);
            $mediaUrl = null;
            $mediaContentType = null;
            $mediaType = null;

            if ($numMedia > 0) {
                $mediaUrl0 = $request->input('MediaUrl0');
                $mediaContentType0 = $request->input('MediaContentType0');

                if ($mediaUrl0) {
                    $mediaUrl = $mediaUrl0;
                    $mediaContentType = $mediaContentType0;

                    if (str_contains($mediaContentType, 'image/')) {
                        $mediaType = 'image';
                    } elseif (str_contains($mediaContentType, 'video/')) {
                        $mediaType = 'video';
                    } elseif ($mediaContentType === 'application/pdf') {
                        $mediaType = 'pdf';
                    } elseif (str_contains($mediaContentType, 'audio/')) {
                        $mediaType = 'audio';
                    } else {
                        $mediaType = 'document';
                    }

                    Log::info("📎 Média reçu: {$mediaType} - {$mediaContentType}");
                    Log::info("📎 Media URL: {$mediaUrl}");
                }
            }

            // ========== PARCOURIR TOUTES LES SOCIÉTÉS ==========
            $societes = \App\Models\Societe::all();

            $foundConfig = null;
            $foundDatabaseName = null;
            $foundSociete = null;

            foreach ($societes as $societe) {
                $databaseName = env('DB_DATABASE');

                try {
                    $connection = DatabaseHelper::Connection_database($databaseName);
                    config(['database.connections.temp_search' => $connection]);
                    DB::connection('temp_search')->setDatabaseName($connection['database']);
                    DB::reconnect('temp_search');

                    if (Schema::connection('temp_search')->hasTable('whatsapp_configurations')) {
                        $config = DB::connection('temp_search')
                            ->table('whatsapp_configurations')
                            ->where('phone_number_id', $to)
                            ->whereNull('deleted_at')
                            ->first();

                        if ($config) {
                            $foundConfig = $config;
                            $foundDatabaseName = $databaseName;
                            $foundSociete = $societe;
                            Log::info("✅ Configuration trouvée dans: " . $databaseName);
                            break;
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning("Erreur dans société {$societe->id}: " . $e->getMessage());
                    continue;
                }
            }

            if (config()->has('database.connections.temp_search')) {
                DB::purge('temp_search');
                config(['database.connections.temp_search' => null]);
            }

            if (!$foundConfig) {
                Log::warning("❌ Aucune configuration trouvée pour le numéro: {$to}");
                return response()->json(['status' => 'error', 'message' => 'Configuration not found'], 200);
            }

            $connection = DatabaseHelper::Connection_database($foundDatabaseName);
            config(['database.connections.temp' => $connection]);
            DB::connection('temp')->setDatabaseName($connection['database']);
            DB::reconnect('temp');

            // ════════════════════════════════════════════════════════════
            // 🛡️ ANTI-DOUBLON : Twilio réessaie si la réponse tarde
            // ════════════════════════════════════════════════════════════
            if ($this->isDuplicateWebhook($messageSid)) {
                Log::info("↩️ Message déjà traité, ignoré: {$messageSid}");
                return response()->json(['status' => 'duplicate']);
            }

            // ════════════════════════════════════════════════════════════
            // ✍️ « EN TRAIN D'ÉCRIRE… » + COCHES BLEUES pendant que l'agent prépare
            //    sa réponse (sauf si un commercial a repris la conversation)
            // ════════════════════════════════════════════════════════════
            $sessionId = $this->getSessionId($from, $foundConfig->projet_id);
            $conversationCheck = $this->isExistingConversation($foundConfig->projet_id, $from);

            if (!$conversationCheck['is_completed'] && !$this->isAgentPaused($sessionId)) {
                $this->twilioSender($foundConfig, $foundConfig->projet_id, $from)->typing($messageSid);
            }

            // ========== TROUVER OU CRÉER LE PROSPECT ==========
            // ✅ Filtre projet + orWhere groupé (sinon un prospect d'un autre projet
            //    pouvait être récupéré)
            $prospect = DB::connection('temp')
                ->table('prospects')
              //  ->where('projet_id', $foundConfig->projet_id)
                ->where(function ($query) use ($from) {
                    $query->where('telephone', $from)
                          ->orWhere('telephone_num2', $from);
                })->whereNull('deleted_at')
                ->first();

            $prospectId = null;
            $isNewProspect = false;
            $assignedCommercialId = null;

            if ($prospect) {
                $prospectId = $prospect->id;
                Log::info("📞 Prospect existant trouvé: {$from} (ID: {$prospectId})");

                // ✅ Récupérer le commercial déjà affecté si existant
                if (!empty($prospect->commercial_affecte)) {
                    $assignedCommercialId = $prospect->commercial_affecte;
                    Log::info("✅ Prospect déjà affecté au commercial ID: {$assignedCommercialId}");
                }
            } else {
                // Création d'un nouveau prospect
                $sourceId = null;
                if (Schema::connection('temp')->hasTable('sources')) {
                    $sourceId = DB::connection('temp')
                        ->table('sources')
                        ->where('source', 'WhatsApp')
                        ->orWhere('source', 'whatsapp')
                        ->value('id');
                }

                // 🔥 DÉCOUPER LE PROFILE NAME EN NOM + PRENOM
                $nom = '';
                $prenom = '';

                $profileNameClean = trim($profileName ?? '');

                if (!empty($profileNameClean)) {
                    $parts = preg_split('/\s+/', $profileNameClean);

                    if (count($parts) >= 2) {
                        // Ex: "Ahmed Benali" → nom = "Ahmed", prenom = "Benali"
                        // Ex: "Ahmed Ben Ali" → nom = "Ahmed", prenom = "Ben Ali"
                        $nom = array_shift($parts);
                        $prenom = implode(' ', $parts);
                    } else {
                        // Ex: "Ahmed" → nom = "Ahmed", prenom = ""
                        $nom = $profileNameClean;
                        $prenom = '';
                    }
                }

                // 🔥 FORMATER LE NOM (première lettre majuscule)
                if (!empty($nom)) {
                    if (strtoupper($nom) === $nom) {
                        $nom = ucwords(mb_strtolower($nom, 'UTF-8'));
                    } else {
                        $nom = ucfirst(mb_strtolower($nom, 'UTF-8'));
                    }
                }

                if (!empty($prenom)) {
                    if (strtoupper($prenom) === $prenom) {
                        $prenom = ucwords(mb_strtolower($prenom, 'UTF-8'));
                    } else {
                        $prenom = ucfirst(mb_strtolower($prenom, 'UTF-8'));
                    }
                }

                Log::info("👤 ProfileName découpé et formaté", [
                    'original' => $profileNameClean,
                    'nom' => $nom,
                    'prenom' => $prenom,
                ]);

                $prospectData = [
                    'telephone' => $from,
                    'telephone_num2' => null,
                    'nom' => $nom,
                    'prenom' => $prenom,
                    'email' => null,
                    'projet_id' => $foundConfig->projet_id,
                    'origin' => 'WhatsApp',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if ($sourceId) {
                    $prospectData['source'] = $sourceId;
                }

                $prospectId = DB::connection('temp')->table('prospects')->insertGetId($prospectData);
                $isNewProspect = true;

                $statutProspect = new StatutProspect();
                $statutProspect->setConnection('temp');
                $statutProspect->prospect_id = $prospectId;
                $statutProspect->statut = '0';
                $statutProspect->date_traitement = Carbon::now();
                $statutProspect->user_id_traite = null;
                $statutProspect->commentaire = 'Prospect créé par WhatsApp';
                $statutProspect->type_traitement_rdv_relance = 0;
                $statutProspect->created_at = now();
                $statutProspect->updated_at = now();
                $statutProspect->save();

                Log::info("✅ Nouveau prospect créé: {$from} (ID: {$prospectId})");
            }

            // ========== TÉLÉCHARGER ET STOCKER LE MÉDIA ==========
            $localMediaUrl = null;
            $downloadedMedia = null; // ✅ contenu du média, gardé pour l'agent (vocal, photo)
            if ($mediaUrl && $foundSociete) {
                $localMediaUrl = $this->downloadAndStoreMedia(
                    $mediaUrl,
                    $foundConfig,
                    $from,
                    $messageSid,
                    $foundSociete,
                    'whatsapp_media',
                    $downloadedMedia
                );

                if ($localMediaUrl) {
                    Log::info("✅ Média téléchargé et stocké: {$localMediaUrl}");
                } else {
                    Log::warning("⚠️ Échec du téléchargement du média, utilisation de l'URL Twilio");
                }
            }

            // ════════════════════════════════════════════════════════════════
            // 🎤 VOCAL : TRANSCRIPTION (darija comprise)
            //    Réussie : l'agent répond comme à un texte, dans la langue du prospect,
            //    et le CRM affiche la transcription.
            //    Échouée : l'agent invite poliment le prospect à écrire, dans sa langue.
            // ════════════════════════════════════════════════════════════════
            $isAudio = ($numMedia > 0) && ($mediaType === 'audio');
            $transcription = null;

            if (empty($body) && $isAudio) {
                Log::info("🎤 Message audio détecté (pas de texte) → transcription en cours", [
                    'from' => $from,
                    'media_url' => $mediaUrl,
                    'content_type' => $mediaContentType,
                ]);

                $transcription = !empty($downloadedMedia['bytes'])
                    ? $this->transcribeVoiceMessage($downloadedMedia['bytes'], $downloadedMedia['mime'] ?: $mediaContentType)
                    : null;

                if (!$transcription) {
                    Log::warning("⚠️ Transcription audio échouée : l'agent demandera au prospect d'écrire", ['from' => $from]);
                }
            } elseif (!empty($body)) {
                Log::info("💬 Message texte reçu", [
                    'from' => $from,
                    'body_length' => strlen($body),
                    'interactive_id' => $interactiveId,
                ]);
            } elseif ($numMedia > 0) {
                Log::info("📎 Média reçu (photo, vidéo ou document)", [
                    'from' => $from,
                    'media_type' => $mediaType,
                ]);
            } elseif ($isLocation) {
                Log::info("📍 Position reçue", [
                    'from' => $from,
                    'latitude' => $request->input('Latitude'),
                    'longitude' => $request->input('Longitude'),
                ]);
            }

            // ✅ Texte affiché dans la messagerie du CRM
            $crmText = $body;
            if ($transcription) {
                $crmText = '🎤 ' . $transcription['text'];
            } elseif ($isAudio && empty($body)) {
                $crmText = '🎤 Message vocal';
            } elseif ($isLocation && empty($body)) {
                $crmText = '📍 Position partagée : https://www.google.com/maps?q='
                    . $request->input('Latitude') . ',' . $request->input('Longitude');
            }

            // ========== STOCKER LE NOUVEAU MESSAGE ==========
            $messageData = [
                'projet_id' => $foundConfig->projet_id,
                'from_number' => $from,
                'to_number' => $to,
                // ✅ Transcription pour un vocal, lien Google Maps pour une position
                'message' => $crmText ?: ($mediaUrl ? '📎 Fichier joint' : ''),
                'message_sid' => $messageSid,
                'profile_name' => $profileName,
                'status' => 'received',
                'created_at' => now(),
                'updated_at' => now(),
                'media_url' => $localMediaUrl ?: $mediaUrl,
                'prospect_id' => $prospectId,
            ];

            $messageId = DB::connection('temp')->table('whatsapp_messages')->insertGetId($messageData);
            $messageData['id'] = $messageId;
            $messageData['created_at'] = $messageData['created_at']->toISOString();

            // ============================================================
            // 🤖 TRAITER AVEC L'AGENT VIRTUEL
            // ============================================================

            // ✅ Drapeaux de notification (voir plus bas)
            $shouldNotifyCommercial = false;
            $agentHandledMessage = false;

            // ✅ L'agent traite aussi les vocaux (même non transcrits), photos, documents,
            //    positions et boutons, pas seulement le texte
            $hasContent = trim((string) $body) !== '' || $numMedia > 0 || $isLocation || $interactiveId !== null;

            if ($hasContent) {
                // ✅ MARQUER LE MESSAGE CLIENT (annule le follow-up)
                $conversation = \App\Models\Conversation::where('session_id', $sessionId)->first();
                if ($conversation) {
                    $conversation->markClientMessage();
                    Log::info("📩 Message client marqué (follow-up annulé)", [
                        'session_id' => $sessionId,
                    ]);
                }

                // ✅ Conversation existante / terminée : vérifiée avant l'indicateur de saisie
                $isExisting = $conversationCheck['exists'];
                $isCompleted = $conversationCheck['is_completed'];

                Log::info("🔍 Vérification conversation", [
                    'phone' => $from,
                    'is_new_prospect' => $isNewProspect,
                    'is_existing_conversation' => $isExisting,
                    'is_conversation_completed' => $isCompleted,
                    'prospect_id' => $prospectId,
                ]);

                // ════════════════════════════════════════════════════════════
                // ✅ AGENT RÉPOND UNIQUEMENT SI :
                //    1. Nouveau prospect  OU
                //    2. Prospect sans conversation  OU
                //    3. Conversation NON terminée
                // ════════════════════════════════════════════════════════════
                $shouldAgentRespond = $isNewProspect || !$isExisting || !$isCompleted;

                if ($shouldAgentRespond) {
                    $reason = 'inconnu';
                    if ($isNewProspect) {
                        $reason = 'Nouveau prospect';
                    } elseif (!$isExisting) {
                        $reason = 'Prospect sans conversation';
                    } else {
                        $reason = 'Conversation non terminée';
                    }

                    Log::info("🤖 Agent virtuel DÉCLENCHÉ pour {$from}", [
                        'raison' => $reason,
                    ]);

                    // ✅ Message reçu décrit pour l'agent : texte, bouton, vocal transcrit, photo, position…
                    $agentInput = $this->buildAgentInput(
                        $request,
                        $body,
                        $interactiveId,
                        $mediaType,
                        $mediaContentType,
                        $downloadedMedia,
                        $transcription,
                        $referral
                    );

                    // ✅ Le nom du profil WhatsApp est transmis à l'agent
                    $agentResult = $this->processWithAgent(
                        $agentInput,
                        $from,
                        $sessionId,
                        $foundConfig->projet_id,
                        $prospectId,
                        $profileName
                    );

                    $agentActions = $agentResult['actions'] ?? [];

                    // ✅ Le commercial n'est notifié que sur une transition utile.
                    //    Si l'agent a déjà émis notify_commercial (notification détaillée
                    //    de type 53), on n'ajoute pas de notification générique.
                    $agentAlreadyNotified = false;
                    foreach ($agentActions as $agentAction) {
                        if (($agentAction['type'] ?? null) === 'notify_commercial') {
                            $agentAlreadyNotified = true;
                            break;
                        }
                    }

                    $shouldNotifyCommercial = !$agentAlreadyNotified && !empty($agentResult['notify_commercial']);
                    $agentHandledMessage = true;

                    // ✅ 1. Réponse au prospect : texte, photos, plans, localisation, boutons…
                    //    (rien si l'agent est en pause : un commercial a la main)
                    $this->sendAgentResponse(
                        $from,
                        $agentResult,
                        $foundConfig,
                        $foundConfig->projet_id,
                        $sessionId,
                        $prospectId,
                        $foundSociete
                    );

                    // ✅ 2. Actions pour l'équipe, APRÈS la réponse : le prospect n'attend pas
                    //    (notification CRM avec résumé IA, demande de suppression des données…)
                    foreach ($agentActions as $agentAction) {
                        $this->executeAgentAction(
                            $agentAction,
                            $from,
                            $foundConfig,
                            $foundConfig->projet_id,
                            $prospectId,
                            $agentResult['history'] ?? []
                        );
                    }

                    Log::info("✅ Message traité par l'agent pour {$from}", [
                        'silent' => !empty($agentResult['silent']),
                        'actions' => array_column($agentActions, 'type'),
                    ]);
                } else {
                    Log::info("⏹️ Agent NON déclenché pour {$from}", [
                        'raison' => 'Conversation terminée',
                        'prospect_id' => $prospectId,
                    ]);
                    Log::info("   → Le commercial prend le relais");
                }
            }

            // ========== BROADCAST ==========
            Config::set('broadcasting.default', 'pusher_whatsapp');
            try {
                broadcast(new NewWhatsAppMessageEvent($messageData, $foundConfig->projet_id, $from))->toOthers();
                Log::info("✅ Broadcast Pusher envoyé pour le message: {$messageSid}");
            } catch (\Exception $e) {
                Log::warning("⚠️ Erreur broadcast Pusher: " . $e->getMessage());
            }

            /* ========== WEBHOOK EVENT ==========
            $web = new WebhookEvent();
            $web->setConnection('temp');
            $web->platform = 'whatsapp';
            $web->type = 'whatsapp_message';
            $web->data = $request->all();
            $web->save();*/

            // ========== NOTIFICATION ==========
            // ✅ Le commercial n'est plus notifié à chaque message.
            //    Notification si : nouveau prospect, lead qualifié, demande de conseiller,
            //    ou message non traité par l'agent (le commercial a la main).
            $notifyCommercial = $isNewProspect || $shouldNotifyCommercial || !$agentHandledMessage;

            /* Notification générique désactivée : seule la notification détaillée de l'agent
               (notifyCommercialFromAgent) est envoyée au commercial.

            if ($notifyCommercial) {
                broadcast(new NotificationEvent(0));
                $this->createWhatsAppNotification(
                    $prospectId,
                    $from,
                    $profileName,
                    $body,
                    $foundConfig->projet_id,
                    $isNewProspect,
                    $assignedCommercialId
                );
            } else {
                Log::info("🔕 Notification commerciale ignorée (conversation en cours avec l'agent)", [
                    'prospect_id' => $prospectId,
                ]);
            }*/

            Log::info("✅ Message WhatsApp traité avec succès: {$messageSid}");

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            Log::error('❌ Erreur webhook WhatsApp: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['status' => 'error'], 200);
        }
    }

    /**
     * Télécharger et stocker le média sur le serveur
     *
     * $downloaded reçoit le contenu téléchargé (['bytes' => …, 'mime' => …]) : l'agent s'en sert
     * pour transcrire un vocal ou lire une photo, sans second téléchargement.
     */
    private function downloadAndStoreMedia($mediaUrl, $config, $from, $messageSid, $societe, $doss = 'whatsapp_media', &$downloaded = null)
    {
        try {
            Log::info("📥 Téléchargement du média depuis: {$mediaUrl}");

            // Récupérer le contenu du média
            $client = new \GuzzleHttp\Client();
            $response = $client->get($mediaUrl, [
                'auth' => [$config->account_sid, $config->access_token],
                'timeout' => 30,
            ]);

            $content = $response->getBody()->getContents();
            $contentType = $response->getHeader('Content-Type')[0] ?? 'application/octet-stream';

            // ✅ Contenu gardé pour l'agent (même si l'enregistrement ci-dessous échoue)
            $downloaded = ['bytes' => $content, 'mime' => $contentType];

            // Déterminer l'extension
            $extension = 'file';
            if (str_contains($contentType, 'image/')) {
                $extension = explode('/', $contentType)[1] ?? 'jpg';
            } elseif (str_contains($contentType, 'video/')) {
                $extension = explode('/', $contentType)[1] ?? 'mp4';
            } elseif ($contentType === 'application/pdf') {
                $extension = 'pdf';
            } elseif (str_contains($contentType, 'audio/')) {
                $extension = explode('/', $contentType)[1] ?? 'mp3';
            }

            // Générer un nom de fichier unique
            $originalName = $messageSid . '.' . $extension;
            $fileName = time() . '_' . $originalName;

            Log::info("📝 Création du fichier: {$fileName}");

            // ✅ Créer un fichier temporaire avec le contenu
            $tempPath = tempnam(sys_get_temp_dir(), 'whatsapp_media_');
            file_put_contents($tempPath, $content);

            // ✅ Créer un objet UploadedFile à partir du fichier temporaire
            $file = new \Illuminate\Http\UploadedFile(
                $tempPath,
                $fileName,
                $contentType,
                null,
                true
            );

            // Utiliser FichierHelper avec le fichier
            FichierHelper::ajouter_fichier(
                $file,
                $societe->raison_sociale_concatene,
                $societe->id,
                $doss,
                $fileName
            );

            // Supprimer le fichier temporaire
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }

            // Générer l'URL en utilisant FichierHelper
            $mediaUrl = FichierHelper::get_file_url(
                $societe->raison_sociale_concatene,
                $societe->id,
                $doss,
                $fileName
            );

            Log::info('📎 Media saved', [
                'societe' => $societe->raison_sociale_concatene,
                'societe_id' => $societe->id,
                'dossier' => $doss,
                'filename' => $fileName,
                'url' => $mediaUrl,
                'size' => strlen($content),
                'type' => $contentType,
            ]);

            return $mediaUrl;

        } catch (\Exception $e) {
            Log::error("❌ Erreur téléchargement média: " . $e->getMessage());
            Log::error("❌ Trace: " . $e->getTraceAsString());
            return null;
        }
    }

    /* =====================================================================
     |  NOTIFICATIONS
     * ===================================================================== */

    /* Notifications génériques désactivées : seule la notification détaillée
       de l'agent (notifyCommercialFromAgent) est envoyée au commercial.

    /**
     * Envoyer une notification d'affectation au commercial
     * /
    private function sendAffectationNotification($commercialId, $prospectId, $projetId)
    {
        try {
            $commercial = User::on('temp')->find($commercialId);
            $prospect = Prospect::on('temp')->find($prospectId);

            if (!$commercial || !$prospect) {
                Log::warning('⚠️ Impossible d\'envoyer la notification d\'affectation', [
                    'commercial_id' => $commercialId,
                    'prospect_id' => $prospectId,
                ]);
                return false;
            }

            $description = "📋 *NOUVEAU PROSPECT AFFECTÉ*\n\n";
            $description .= "👤 Prospect: " . ($prospect->nom ?? $prospect->telephone ?? 'Inconnu') . "\n";
            $description .= "📞 Téléphone: " . ($prospect->telephone ?? 'Non renseigné') . "\n";
            $description .= "📱 Source: WhatsApp\n";
            $description .= "🏢 Projet ID: {$projetId}\n\n";
            $description .= "✅ Affecté automatiquement à: " . $commercial->name . ' ' . $commercial->prenom;

            $link = "/prospects/edit/" . $prospectId;

            $notification = new Notification();
            $notification->setConnection('temp');
            $notification->date = now();
            $notification->type = 52; // Type: Affectation automatique
            $notification->description_type = $description;
            $notification->lien = $link;
            $notification->role = 3; // ADMIN_COMMERCIAL
            $notification->user_id = $commercialId; // ✅ Notification UNIQUEMENT pour le commercial affecté
            $notification->projet_id = $projetId;
            $notification->prospect_id = $prospectId;
            $notification->save();

            Config::set('broadcasting.default', 'pusher_notify');
            broadcast(new NotificationEvent($notification->id));

            Log::info('✅ Notification d\'affectation envoyée au commercial', [
                'commercial_id' => $commercialId,
                'notification_id' => $notification->id,
                'prospect_id' => $prospectId,
            ]);

            return $notification;

        } catch (\Exception $e) {
            Log::error('❌ Erreur envoi notification affectation: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Créer une notification pour les commerciaux
     * /
    private function createWhatsAppNotification($prospectId, $phoneNumber, $profileName, $message, $projetId, $isNewProspect = false, $assignedCommercialId = null)
    {
        try {
            if (!$assignedCommercialId) {
                $prospect = Prospect::on('temp')->find($prospectId);
                if ($prospect && !empty($prospect->commercial_affecte)) {
                    $assignedCommercialId = $prospect->commercial_affecte;
                }
            }

            if ($isNewProspect) {
                $description = "📱 *NOUVEAU CONTACT WHATSAPP*\n\n";
                $description .= "📞 Numéro: {$phoneNumber}\n";
                $description .= "👤 Nom: {$profileName}\n";
                $description .= "💬 Message: " . (strlen($message) > 100 ? substr($message, 0, 100) . '...' : $message);
                $type = 51;
            } else {
                $description = "💬 *NOUVEAU MESSAGE WHATSAPP*\n\n";
                $description .= "📞 De: {$phoneNumber}\n";
                $description .= "👤 Prospect: {$profileName}\n";
                $description .= "💬 Message: " . (strlen($message) > 100 ? substr($message, 0, 100) . '...' : $message);
                $type = 50;
            }

            $link = "/whatsapp-messenger?phone={$phoneNumber}&projet_id={$projetId}&prospect_id={$prospectId}";

            $notification = new Notification();
            $notification->setConnection('temp');
            $notification->date = now();
            $notification->type = $type;
            $notification->description_type = $description;
            $notification->lien = $link;
            $notification->role = 3; // ADMIN_COMMERCIAL

            if ($assignedCommercialId) {
                $notification->user_id = $assignedCommercialId;
                Log::info("🔔 Notification affectée au commercial ID: {$assignedCommercialId}");
            } else {
                $notification->user_id = null;
                Log::info("🔔 Notification sans affectation spécifique");
            }

            $notification->projet_id = $projetId;
            $notification->prospect_id = $prospectId;
            $notification->save();

            Config::set('broadcasting.default', 'pusher_notify');
            broadcast(new NotificationEvent($notification->id));

            Log::info("✅ Notification WhatsApp créée pour prospect {$prospectId}");

        } catch (\Exception $e) {
            Log::error("❌ Erreur création notification: " . $e->getMessage());
        }
    }


    */

    /* =====================================================================
     |  CONVERSATIONS (interface CRM)
     * ===================================================================== */

    /**
     * Récupérer les conversations WhatsApp
     */
    public function getConversations(Request $request, $projetId)
    {
        DatabaseHelper::Config();

        // Check if the whatsapp_messages table exists
        if (!Schema::connection('temp')->hasTable('whatsapp_messages')) {
            return response()->json([
                'conversations' => [],
            ]);
        }

        // Get the business WhatsApp number from configurations
        $whatsappConfig = DB::connection('temp')
            ->table('whatsapp_configurations')
            ->where('projet_id', $projetId)
            ->whereNull('deleted_at')
            ->first();

        if (!$whatsappConfig) {
            // No WhatsApp configuration found for this project
            return response()->json([
                'conversations' => [],
            ]);
        }

        $ourWhatsappNumber = $whatsappConfig->phone_number_id;

        // Get unique phone numbers from both from_number AND to_number (excluding our number)
        // ✅ Valeur passée en binding (plus d'interpolation directe dans le SQL)
        $phoneNumbers = DB::connection('temp')
            ->table('whatsapp_messages')
            ->where('projet_id', $projetId)
            ->selectRaw("DISTINCT CASE WHEN from_number = ? THEN to_number ELSE from_number END as phone_number", [$ourWhatsappNumber])
            ->where(function ($query) use ($ourWhatsappNumber) {
                $query->where('from_number', '!=', $ourWhatsappNumber)
                      ->orWhere('to_number', '!=', $ourWhatsappNumber);
            })
            ->get();

        $conversations = [];

        foreach ($phoneNumbers as $phone) {
            $phoneNumber = $phone->phone_number;
            if (empty($phoneNumber)) {
                continue;
            }

            // Get messages between the two parties
            $messages = DB::connection('temp')
                ->table('whatsapp_messages')
                ->where('projet_id', $projetId)
                ->where(function ($query) use ($phoneNumber, $ourWhatsappNumber) {
                    $query->where(function ($q) use ($phoneNumber, $ourWhatsappNumber) {
                        $q->where('from_number', $phoneNumber)
                          ->where('to_number', $ourWhatsappNumber);
                    })->orWhere(function ($q) use ($phoneNumber, $ourWhatsappNumber) {
                        $q->where('from_number', $ourWhatsappNumber)
                          ->where('to_number', $phoneNumber);
                    });
                })
                ->orderBy('created_at', 'asc')
                ->get();

            if ($messages->count() > 0) {
                $lastMessage = $messages->last();
                $unreadCount = $messages->where('status', 'received')
                                       ->whereNull('read_at')
                                       ->where('from_number', $phoneNumber)
                                       ->count();

                // Get prospect info
                $prospect = DB::connection('temp')
                    ->table('prospects')
                    ->where(function ($query) use ($phoneNumber) {
                        $query->where('telephone', $phoneNumber)
                              ->orWhere('telephone_num2', $phoneNumber);
                    })
                    ->first();

                $conversations[] = [
                    'phone_number' => $phoneNumber,
                    'profile_name' => $lastMessage->profile_name ?? $phoneNumber,
                    'prospect_id' => $prospect->id ?? null,
                    'prospect_nom' => $prospect->nom ?? $phoneNumber,
                    'last_message' => $lastMessage->message ?? null,
                    'last_message_date' => $lastMessage->created_at ?? now(),
                    'message_count' => $messages->count(),
                    'unread_count' => $unreadCount,
                    'last_message_from_me' => $lastMessage && $lastMessage->from_number == $ourWhatsappNumber,
                ];
            }
        }

        // Sort by last_message_date descending
        usort($conversations, function ($a, $b) {
            return strtotime($b['last_message_date']) - strtotime($a['last_message_date']);
        });

        return response()->json(['conversations' => array_values($conversations)]);
    }

    /**
     * Récupérer une conversation spécifique
     */
    public function getConversation(Request $request, $projetId, $phoneNumber)
    {
        DatabaseHelper::Config();

        // Get the business WhatsApp number from configurations
        $whatsappConfig = DB::connection('temp')
            ->table('whatsapp_configurations')
            ->where('projet_id', $projetId)
            ->whereNull('deleted_at')
            ->first();

        $ourWhatsappNumber = $whatsappConfig ? $whatsappConfig->phone_number_id : null;

        $messages = DB::connection('temp')
            ->table('whatsapp_messages')
            ->where('projet_id', $projetId)
            ->where(function ($query) use ($phoneNumber) {
                $query->where('from_number', $phoneNumber)
                      ->orWhere('to_number', $phoneNumber);
            })
            ->orderBy('created_at', 'asc')
            ->get();

        // If we have our business number, also show messages where we are the sender
        if ($ourWhatsappNumber && $messages->isEmpty()) {
            $messages = DB::connection('temp')
                ->table('whatsapp_messages')
                ->where('projet_id', $projetId)
                ->where(function ($query) use ($phoneNumber, $ourWhatsappNumber) {
                    $query->where(function ($q) use ($phoneNumber, $ourWhatsappNumber) {
                        $q->where('from_number', $ourWhatsappNumber)
                          ->where('to_number', $phoneNumber);
                    })->orWhere(function ($q) use ($phoneNumber, $ourWhatsappNumber) {
                        $q->where('from_number', $phoneNumber)
                          ->where('to_number', $ourWhatsappNumber);
                    });
                })
                ->orderBy('created_at', 'asc')
                ->get();
        }

        $prospect = DB::connection('temp')
            ->table('prospects')
            ->where(function ($query) use ($phoneNumber) {
                $query->where('telephone', $phoneNumber)
                      ->orWhere('telephone_num2', $phoneNumber);
            })
            ->first();

        return response()->json([
            'conversation' => [
                'phone_number' => $phoneNumber,
                'profile_name' => $messages->first()->profile_name ?? 'Client',
                'prospect_id' => $prospect->id ?? null,
                'prospect_nom' => $prospect->nom ?? null,
                'messages' => $messages,
            ],
        ]);
    }

    /**
     * Envoyer une réponse WhatsApp (depuis le CRM)
     */
    public function sendReply(Request $request, $projetId, $phoneNumber)
    {
        DatabaseHelper::Config();

        $request->validate([
            'message' => 'nullable|string|max:1600',
            'media_url' => 'nullable|string|url',
        ]);

        $messageText = $request->input('message', '');
        $mediaUrl = $request->input('media_url', null);

        // Check if we have a message or a file
        if (empty($messageText) && empty($mediaUrl)) {
            return response()->json(['error' => 'Un message ou un fichier est requis'], 400);
        }

        $config = DB::connection('temp')
            ->table('whatsapp_configurations')
            ->where('projet_id', $projetId)
            ->whereNull('deleted_at')
            ->first();

        if (!$config) {
            return response()->json(['error' => 'Configuration WhatsApp non trouvée'], 404);
        }

        $twilio = new ClientTwilio($config->account_sid, $config->access_token);
        $phoneNumber = str_starts_with($phoneNumber, '+') ? $phoneNumber : '+' . $phoneNumber;

        try {
            // ✅ Get the authenticated user
            $user = auth()->user();
            $userIdSent = $user ? $user->id : null;

            // If user is authenticated, get the user from temp database
            if ($userIdSent) {
                $userTemp = DB::connection('temp')
                    ->table('users')
                    ->where('user_id_origin', $userIdSent)
                    ->first();
                $userIdSent = $userTemp ? $userTemp->id : null;
            }

            // 🔍 Vérifier si le client a déjà envoyé un message (réponse)
            $lastClientReply = DB::connection('temp')
                ->table('whatsapp_messages')
                ->where('projet_id', $projetId)
                ->where('from_number', $phoneNumber)
                ->whereIn('status', ['received', 'read', 'delivered'])
                ->orderBy('created_at', 'desc')
                ->first();

            $canSendFreeForm = false;
            $hoursSinceLastReply = null;

            // ✅ Vérifier si session active (client a répondu dans les 24h)
            if ($lastClientReply) {
                $lastReplyTime = Carbon::parse($lastClientReply->created_at);
                // ✅ diffInHours() sur une date passée : on force la valeur absolue
                $hoursSinceLastReply = abs(Carbon::now()->diffInHours($lastReplyTime));

                if ($hoursSinceLastReply <= 24) {
                    $canSendFreeForm = true;
                    Log::info("✅ Session active - Dernière réponse il y a {$hoursSinceLastReply}h");
                } else {
                    Log::info("⏰ Plus de 24h depuis la dernière réponse ({$hoursSinceLastReply}h)");
                }
            } else {
                Log::info("📱 Premier message pour ce client");
            }

            // 🔢 COMPTER LE NOMBRE DE MESSAGES MARKETING DÉJÀ ENVOYÉS
            $marketingCount = DB::connection('temp')
                ->table('whatsapp_messages')
                ->where('projet_id', $projetId)
                ->where('to_number', $phoneNumber)
                ->where('message_type', 'marketing_template')
                ->count();

            Log::info("📊 Marketing messages sent to {$phoneNumber}: {$marketingCount}");

            // 📝 Décider du type de message à envoyer
            if ($canSendFreeForm) {
                // ✅ Session active → Message NORMAL
                Log::info("💬 Envoi d'un message normal (session active)");

                $messageParams = [
                    'from' => "whatsapp:" . $config->phone_number_id,
                    'body' => $messageText ?: 'Fichier joint',
                ];

                // ✅ Add media URL if present (free-form can also send media)
                if ($mediaUrl) {
                    $messageParams['mediaUrl'] = [$mediaUrl];
                    Log::info("📎 Media URL added to free-form message: {$mediaUrl}");
                }

                $sentMessage = $twilio->messages->create(
                    "whatsapp:" . $phoneNumber,
                    $messageParams
                );

                // Stocker le message normal
                $messageId = DB::connection('temp')->table('whatsapp_messages')->insertGetId([
                    'projet_id' => $projetId,
                    'from_number' => $config->phone_number_id,
                    'to_number' => $phoneNumber,
                    'message' => $messageText ?: 'Fichier joint',
                    'message_sid' => $sentMessage->sid,
                    'profile_name' => auth()->user()->name ?? 'Commercial',
                    'status' => 'sent',
                    'message_type' => 'free_form',
                    'media_url' => $mediaUrl,
                    'marketing_count' => $marketingCount,
                    'user_id_sent' => $userIdSent,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                Log::info("✅ Message normal envoyé à {$phoneNumber}, SID: " . $sentMessage->sid);

                // ✅ Le commercial a repris la conversation : l'agent se met en retrait
                $this->pauseAgentAfterHumanReply($projetId, $phoneNumber);

                return response()->json([
                    'success' => true,
                    'sid' => $sentMessage->sid,
                    'message_id' => $messageId,
                    'message_type' => 'free_form',
                    'session_active' => true,
                    'hours_since_last_reply' => $hoursSinceLastReply,
                    'has_media' => !empty($mediaUrl),
                    'marketing_count' => $marketingCount,
                    'user_id_sent' => $userIdSent,
                    'note' => "Message normal envoyé (client actif)",
                ]);

            } else {
                // ❌ Session inactive → Utiliser le TEMPLATE MARKETING
                Log::info("📋 Envoi du template marketing (session inactive)");

                // 🔥 Déterminer si on a un fichier
                $hasMedia = !empty($mediaUrl);

                // 🔥 TEMPLATE SIDs en fonction de la présence de média
                $templateSids = [];
                if ($hasMedia) {
                    // ✅ Utiliser le template avec fichier
                    $templateSids = [
                        env('WHATSAPP_BULK_1_FILE_TEMPLATE_SID', 'HX8926ddf7b251ef7f1259087f8bf36108'),
                    ];
                    Log::info("📎 Using FILE templates for message with media");
                } else {
                    // ✅ Utiliser le template sans fichier
                    $templateSids = [
                        env('WHATSAPP_BULK_1_TEMPLATE_SID', 'HX4b8042dbf576a937b303a72cee231b6f'),
                        env('WHATSAPP_BULK_2_TEMPLATE_SID', 'HXd4e66bdc7a945bcdd2dc4ed627ee3530'),
                        env('WHATSAPP_BULK_3_TEMPLATE_SID', 'HX9c03226d7fdabc08ec92548e1fee4fb9'),
                    ];
                    Log::info("📝 Using standard templates for message without media");
                }

                // Filter out null/empty values
                $templateSids = array_values(array_filter($templateSids));

                if (empty($templateSids)) {
                    Log::error("No template SIDs configured in .env");
                    return response()->json(['error' => 'Aucun template configuré'], 500);
                }

                // 🔥 DÉTERMINER LE TEMPLATE À UTILISER EN FONCTION DU NOMBRE DE TENTATIVES
                $templateIndex = min($marketingCount, count($templateSids) - 1);
                $selectedTemplateSid = $templateSids[$templateIndex] ?? $templateSids[0];

                Log::info("📋 Using template #" . ($templateIndex + 1) . " (Marketing count: {$marketingCount}) - Template SID: {$selectedTemplateSid}");

                // Vérifier que le template SID est valide
                if (empty($selectedTemplateSid) || $selectedTemplateSid === 'HX') {
                    throw new \Exception("Invalid template SID: {$selectedTemplateSid}");
                }

                // ✅ Construire les variables en fonction du type de template
                $templateVariables = [];
                if ($hasMedia) {
                    // ✅ Template avec 2 variables : message + media_url
                    $templateVariables = [
                        '1' => $messageText ?: 'Fichier joint',
                        '2' => $mediaUrl,
                    ];
                    Log::info("📎 Template with media: message='{$messageText}', media_url='{$mediaUrl}'");
                } else {
                    // ✅ Template avec 1 variable : message seulement
                    $templateVariables = [
                        '1' => $messageText,
                    ];
                    Log::info("📝 Template without media: message='{$messageText}'");
                }

                // Envoyer le template
                $sentMessage = $twilio->messages->create(
                    "whatsapp:" . $phoneNumber,
                    [
                        'from' => "whatsapp:" . $config->phone_number_id,
                        'contentSid' => $selectedTemplateSid,
                        'contentVariables' => json_encode($templateVariables),
                    ]
                );

                // Stocker le template avec toutes les informations
                $messageId = DB::connection('temp')->table('whatsapp_messages')->insertGetId([
                    'projet_id' => $projetId,
                    'from_number' => $config->phone_number_id,
                    'to_number' => $phoneNumber,
                    'message' => $messageText ?: 'Fichier joint',
                    'message_sid' => $sentMessage->sid,
                    'profile_name' => auth()->user()->name ?? 'Commercial',
                    'status' => 'sent',
                    'message_type' => 'marketing_template',
                    'media_url' => $mediaUrl,
                    'marketing_count' => $marketingCount,
                    'template_index' => $templateIndex + 1,
                    'template_sid' => $selectedTemplateSid,
                    'user_id_sent' => $userIdSent,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                Log::info("✅ Template #" . ($templateIndex + 1) . " envoyé à {$phoneNumber}, SID: " . $sentMessage->sid);

                // ✅ Le commercial relance lui-même le prospect : l'agent se met en retrait
                $this->pauseAgentAfterHumanReply($projetId, $phoneNumber);

                // Get template name for response
                $templateNames = $hasMedia ? [
                    'File Template 1',
                ] : [
                    'Template 1',
                    'Template 2',
                    'Template 3',
                ];

                return response()->json([
                    'success' => true,
                    'sid' => $sentMessage->sid,
                    'message_id' => $messageId,
                    'message_type' => 'marketing_template',
                    'template_sid' => $selectedTemplateSid,
                    'template_index' => $templateIndex + 1,
                    'template_name' => $templateNames[$templateIndex] ?? 'Template',
                    'has_media' => $hasMedia,
                    'media_url' => $mediaUrl,
                    'marketing_count' => $marketingCount,
                    'user_id_sent' => $userIdSent,
                    'session_active' => false,
                    'hours_since_last_reply' => $hoursSinceLastReply ?? 'Aucune réponse',
                    'note' => "Template #" . ($templateIndex + 1) . " envoyé (client inactif ou premier message)",
                    'display_message' => $messageText,
                ]);
            }

        } catch (\Exception $e) {
            Log::error("❌ Erreur envoi message: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /* =====================================================================
     |  AGENT VIRTUEL : REPRISE PAR UN COMMERCIAL
     * ===================================================================== */

    /**
     * ⏸️ UN COMMERCIAL RÉPOND DEPUIS LE CRM : l'agent se tait pendant N heures
     * (config greenland.human_takeover_hours, 12 par défaut ; 0 = l'agent continue de répondre).
     * Chaque nouvelle réponse du commercial prolonge la pause ; l'agent reprend seul ensuite.
     */
    private function pauseAgentAfterHumanReply($projetId, $phoneNumber)
    {
        $hours = (int) config('greenland.human_takeover_hours', 12);
        if ($hours <= 0) {
            return;
        }

        try {
            $conversation = Conversation::where('session_id', $this->getSessionId($phoneNumber, $projetId))->first();
            if (!$conversation) {
                return;
            }

            $agent = new AgentFinalService($conversation->state ?? []);
            $agent->pauseBot($hours);
            $conversation->updateConversation($agent->getConversationState(), $conversation->history ?? []);

            Log::info("⏸️ Agent en pause {$hours} h : un commercial a répondu", [
                'projet_id' => $projetId,
                'phone' => $phoneNumber,
            ]);
        } catch (\Throwable $e) {
            Log::warning("⚠️ Pause de l'agent impossible: " . $e->getMessage());
        }
    }

    /**
     * 🤖 ÉTAT DE L'AGENT POUR UN PROSPECT (pour l'écran de conversation du CRM)
     * Score, température, résumé, rendez-vous, agent en pause ou non.
     */
    public function getAgentStatus(Request $request, $projetId, $phoneNumber)
    {
        DatabaseHelper::Config();

        $phoneNumber = str_starts_with($phoneNumber, '+') ? $phoneNumber : '+' . $phoneNumber;
        $conversation = Conversation::where('session_id', $this->getSessionId($phoneNumber, $projetId))->first();

        if (!$conversation) {
            return response()->json(['exists' => false]);
        }

        $agent = new AgentFinalService($conversation->state ?? []);
        $lead = $agent->getLeadSnapshot();
        $state = $agent->getConversationState();

        return response()->json([
            'exists' => true,
            'agent_paused' => $agent->isBotPaused(),
            'paused_until' => $state['bot_paused_until'] ?? null,
            'lead_score' => $lead['lead_score'] ?? 0,
            'lead_temperature' => $lead['lead_temperature'] ?? null,
            'summary' => $lead['summary'] ?? null,
            'visit' => $lead['visit'] ?? null,
            'callback_slot_label' => $lead['callback_slot_label'] ?? null,
            'unanswered_questions' => $lead['unanswered_questions'] ?? [],
            'language' => $lead['language'] ?? null,
            'stage' => $state['conversation_stage'] ?? null,
        ]);
    }

    /**
     * ⏯️ METTRE EN PAUSE OU RELANCER L'AGENT POUR UN PROSPECT (bouton du CRM)
     * POST action=pause [hours=N, sans durée : jusqu'à la reprise] | action=resume
     */
    public function toggleAgent(Request $request, $projetId, $phoneNumber)
    {
        DatabaseHelper::Config();

        $phoneNumber = str_starts_with($phoneNumber, '+') ? $phoneNumber : '+' . $phoneNumber;
        $conversation = Conversation::where('session_id', $this->getSessionId($phoneNumber, $projetId))->first();

        if (!$conversation) {
            return response()->json(['error' => 'Conversation introuvable'], 404);
        }

        $agent = new AgentFinalService($conversation->state ?? []);
        if ($request->input('action') === 'resume') {
            $agent->resumeBot();
        } else {
            $hours = (int) $request->input('hours', 0);
            $agent->pauseBot($hours > 0 ? $hours : null);
        }

        $state = $agent->getConversationState();
        $conversation->updateConversation($state, $conversation->history ?? []);

        return response()->json([
            'success' => true,
            'agent_paused' => $agent->isBotPaused(),
            'paused_until' => $state['bot_paused_until'] ?? null,
        ]);
    }

    /* =====================================================================
     |  CONFIGURATION WHATSAPP
     * ===================================================================== */

    public function get_whatsapp_configurations()
    {
        if (RoleHelper::AdminSup() || RoleHelper::AgentAdmin()) {
            DatabaseHelper::Config();

            try {
                if (!Schema::connection('temp')->hasTable('whatsapp_configurations')) {
                    return response()->json(['configurations' => []], 200);
                }

                $configurations = DB::connection('temp')
                    ->table('whatsapp_configurations')
                    ->leftJoin('projets', 'whatsapp_configurations.projet_id', '=', 'projets.id')
                    ->select(
                        'whatsapp_configurations.*',
                        'projets.nom as projet_nom'
                    )
                    ->whereNull('whatsapp_configurations.deleted_at')
                    ->get();

                return response()->json(['configurations' => $configurations], 200);
            } catch (\Exception $e) {
                if (str_contains($e->getMessage(), "doesn't exist")) {
                    return response()->json(['configurations' => []], 200);
                }
                throw $e;
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function store_whatsapp_configuration(Request $request)
    {
        if (RoleHelper::AdminSup() || RoleHelper::AgentAdmin()) {
            DatabaseHelper::Config();

            try {
                // Check if table exists, create if not
                if (!Schema::connection('temp')->hasTable('whatsapp_configurations')) {
                    Schema::connection('temp')->create('whatsapp_configurations', function (Blueprint $table) {
                        $table->id();
                        $table->string('phone_number_id');
                        $table->longText('access_token');
                        $table->string('account_sid');
                        $table->unsignedBigInteger('projet_id');
                        $table->string('webhook_verify_token')->nullable();
                        $table->boolean('webhook_enabled')->default(false);
                        $table->json('webhook_subscriptions')->nullable();
                        $table->softDeletes();
                        $table->timestamps();

                        $table->foreign('projet_id')->references('id')->on('projets')->onDelete('cascade');
                    });
                }

                // Drop unique constraint if it exists (safety in case it was created earlier)
                try {
                    DB::connection('temp')->statement('ALTER TABLE whatsapp_configurations DROP INDEX unique_project_whatsapp_config');
                } catch (\Exception $e) {
                    // Ignore if index doesn't exist
                }

                // Insert new configuration (webhook explicitly disabled by default)
                $configId = DB::connection('temp')->table('whatsapp_configurations')->insertGetId([
                    'phone_number_id' => $request->phone_number_id,
                    'access_token' => $request->access_token,
                    'account_sid' => $request->account_sid,
                    'projet_id' => $request->projet_id,
                    'webhook_enabled' => false,
                    'webhook_verify_token' => null,
                    'webhook_subscriptions' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return response()->json([
                    'message' => 'Configuration WhatsApp enregistrée avec succès',
                    'configuration_id' => $configId,
                ], 200);
            } catch (\Exception $e) {
                return response()->json([
                    'error' => 'Erreur lors de l\'enregistrement: ' . $e->getMessage(),
                ], 500);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function get_whatsapp_webhooks()
    {
        if (RoleHelper::AdminSup() || RoleHelper::AgentAdmin()) {
            DatabaseHelper::Config();

            try {
                if (!Schema::connection('temp')->hasTable('whatsapp_configurations')) {
                    return response()->json(['webhooks' => []], 200);
                }

                $webhooks = DB::connection('temp')
                    ->table('whatsapp_configurations')
                    ->leftJoin('projets', 'whatsapp_configurations.projet_id', '=', 'projets.id')
                    ->select(
                        'whatsapp_configurations.id',
                        'whatsapp_configurations.phone_number_id',
                        'whatsapp_configurations.webhook_verify_token',
                        'whatsapp_configurations.webhook_enabled',
                        'whatsapp_configurations.webhook_subscriptions',
                        'whatsapp_configurations.created_at',
                        'whatsapp_configurations.updated_at',
                        'projets.nom as projet_nom',
                        DB::raw("CONCAT('" . (config('webhook.base_url') ?? config('app.url') ?? 'https://votre-domaine.com') . "/api/webhook_whatsapp_business') as webhook_url")
                    )
                    ->whereNotNull('whatsapp_configurations.webhook_verify_token')
                    ->whereNull('whatsapp_configurations.deleted_at')
                    ->get();

                return response()->json(['webhooks' => $webhooks], 200);
            } catch (\Exception $e) {
                if (str_contains($e->getMessage(), "doesn't exist")) {
                    return response()->json(['webhooks' => []], 200);
                }
                throw $e;
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function store_whatsapp_webhook_configuration(Request $request, $configId)
    {
        if (RoleHelper::AdminSup() || RoleHelper::AgentAdmin()) {
            DatabaseHelper::Config();

            try {
                // Verify configuration exists
                $config = DB::connection('temp')
                    ->table('whatsapp_configurations')
                    ->where('id', $configId)
                    ->whereNull('deleted_at')
                    ->first();

                if (!$config) {
                    return response()->json(['error' => 'Configuration WhatsApp non trouvée'], 404);
                }

                // Store webhook configuration but keep it DISABLED by default
                DB::connection('temp')
                    ->table('whatsapp_configurations')
                    ->where('id', $configId)
                    ->update([
                        'webhook_verify_token' => $request->webhook_verify_token,
                        'webhook_enabled' => false,
                        'webhook_subscriptions' => json_encode(['messages']),
                        'updated_at' => now(),
                    ]);

                return response()->json([
                    'message' => 'Webhook WhatsApp configuré avec succès',
                    'webhook_enabled' => false,
                ], 200);
            } catch (\Exception $e) {
                return response()->json(['error' => 'Erreur lors de la configuration: ' . $e->getMessage()], 500);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function delete_whatsapp_webhook($configId)
    {
        if (RoleHelper::AdminSup() || RoleHelper::AgentAdmin()) {
            DatabaseHelper::Config();

            try {
                $updated = DB::connection('temp')
                    ->table('whatsapp_configurations')
                    ->where('id', $configId)
                    ->update([
                        'webhook_verify_token' => null,
                        'webhook_enabled' => false,
                        'webhook_subscriptions' => null,
                        'updated_at' => now(),
                    ]);

                if ($updated) {
                    return response()->json(['message' => 'Webhook WhatsApp supprimé avec succès'], 200);
                } else {
                    return response()->json(['error' => 'Configuration non trouvée'], 404);
                }
            } catch (\Exception $e) {
                return response()->json(['error' => 'Erreur lors de la suppression: ' . $e->getMessage()], 500);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function toggle_whatsapp_webhook(Request $request, $configId)
    {
        if (RoleHelper::AdminSup() || RoleHelper::AgentAdmin()) {
            DatabaseHelper::Config();

            try {
                $config = DB::connection('temp')
                    ->table('whatsapp_configurations')
                    ->where('id', $configId)
                    ->whereNull('deleted_at')
                    ->first();

                if (!$config) {
                    return response()->json(['error' => 'Configuration WhatsApp non trouvée'], 404);
                }

                if (!$config->webhook_verify_token) {
                    return response()->json(['error' => 'Webhook non configuré'], 400);
                }

                $newStatus = $request->webhook_enabled;

                DB::connection('temp')
                    ->table('whatsapp_configurations')
                    ->where('id', $configId)
                    ->update([
                        'webhook_enabled' => $newStatus,
                        'updated_at' => now(),
                    ]);

                $statusText = $newStatus ? 'activé' : 'désactivé';

                return response()->json([
                    'message' => "Webhook WhatsApp {$statusText} avec succès",
                    'webhook_enabled' => $newStatus,
                ], 200);
            } catch (\Exception $e) {
                return response()->json(['error' => 'Erreur lors de la modification: ' . $e->getMessage()], 500);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function update_whatsapp_configuration(Request $request, $configId)
    {
        if (RoleHelper::AdminSup() || RoleHelper::AgentAdmin()) {
            DatabaseHelper::Config();

            try {
                // Validate required fields
                if (!$request->phone_number_id || !$request->access_token || !$request->projet_id || !$request->account_sid) {
                    return response()->json(['error' => 'Tous les champs obligatoires doivent être remplis'], 400);
                }

                // Check if configuration exists
                $exists = DB::connection('temp')
                    ->table('whatsapp_configurations')
                    ->where('id', $configId)
                    ->whereNull('deleted_at')
                    ->exists();

                if (!$exists) {
                    return response()->json(['error' => 'Configuration non trouvée'], 404);
                }

                // Update configuration
                $updated = DB::connection('temp')
                    ->table('whatsapp_configurations')
                    ->where('id', $configId)
                    ->update([
                        'phone_number_id' => $request->phone_number_id,
                        'access_token' => $request->access_token,
                        'account_sid' => $request->account_sid,
                        'projet_id' => $request->projet_id,
                        'updated_at' => now(),
                    ]);

                if ($updated) {
                    return response()->json(['message' => 'Configuration WhatsApp mise à jour avec succès'], 200);
                } else {
                    return response()->json(['error' => 'Aucune modification effectuée'], 400);
                }
            } catch (\Exception $e) {
                return response()->json(['error' => 'Erreur lors de la mise à jour: ' . $e->getMessage()], 500);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function delete_whatsapp_configuration($configId)
    {
        if (RoleHelper::AdminSup() || RoleHelper::AgentAdmin()) {
            DatabaseHelper::Config();

            try {
                $deleted = DB::connection('temp')
                    ->table('whatsapp_configurations')
                    ->where('id', $configId)
                    ->update([
                        'deleted_at' => now(),
                    ]);

                if ($deleted) {
                    return response()->json(['message' => 'Configuration WhatsApp supprimée avec succès'], 200);
                } else {
                    return response()->json(['error' => 'Configuration non trouvée'], 404);
                }
            } catch (\Exception $e) {
                return response()->json(['error' => 'Erreur lors de la suppression: ' . $e->getMessage()], 500);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /* =====================================================================
     |  UPLOAD
     * ===================================================================== */

    public function uploadMedia(Request $request)
    {
        try {
            DatabaseHelper::Config();

            // Get user and society information (same as uploadMedia_bulk_whatsap)
            $user = Auth::user();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->first();
            $societe = Societe::findOrFail($userAuth->societe_id);

            $request->validate([
                'file' => 'required|file|max:20480', // 20MB max
            ]);

            if ($request->hasFile('file')) {
                $file = $request->file('file');

                // Generate filename: time()_originalName (same as bulk_whatsap)
                $fileName = time() . '_' . $file->getClientOriginalName();
                $doss = 'bulk_whatsap_file';

                // Use FichierHelper exactly like in uploadMedia_bulk_whatsap
                FichierHelper::ajouter_fichier(
                    $file,
                    $societe->raison_sociale_concatene,
                    $societe->id,
                    $doss,
                    $fileName
                );

                // Generate URL using FichierHelper
                $mediaUrl = FichierHelper::get_file_url(
                    $societe->raison_sociale_concatene,
                    $societe->id,
                    $doss,
                    $fileName
                );

                // Log for debugging
                Log::info('WhatsApp Media Upload', [
                    'original_name' => $file->getClientOriginalName(),
                    'saved_as' => $fileName,
                    'type' => $request->input('type', 'auto'),
                    'url' => $mediaUrl,
                    'societe' => $societe->raison_sociale_concatene,
                    'dossier' => $doss,
                ]);

                return response()->json([
                    'success' => true,
                    'url' => $mediaUrl,
                    'type' => $request->input('type', 'auto'),
                    'filename' => $fileName,
                    'original_name' => $file->getClientOriginalName(),
                ]);
            }

            return response()->json(['error' => 'No file uploaded'], 400);

        } catch (\Exception $e) {
            Log::error('Upload error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
