<?php

namespace App\Http\Controllers\WhatsApp;
use App\Http\Helpers\RoleHelper;
use App\Models\Conversation; // 🔥 AJOUTER CETTE LIGNE

use App\Services\AgentFinalService; // 🔥 AJOUTER CETTE LIGNE
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
use App\Events\NewWhatsAppMessageEvent;  // AJOUTER CETTE LIGNE
use Twilio\Rest\Client as ClientTwilio;  // ← AJOUTER CETTE LIGNE ICI
use Illuminate\Support\Facades\Auth;
use App\Http\Helpers\FichierHelper;  // AJOUTER CETTE LIGNE
use App\Models\Societe;

class WhatsAppBusinessController extends Controller
{
     /**
 * 🔥 TRAITER LE MESSAGE AVEC L'AGENT VIRTUEL
 */


private function processWithAgent($message, $from, $sessionId, $projetId,$prospectId = null)
{
    try {
        Log::info("🤖 Agent virtuel: Traitement du message de {$from}", [
            'message' => $message,
            'session_id' => $sessionId,
            'prospect_id' => $prospectId,  // ✅ Ajouté
        ]);

        // 🔥 UTILISER LA MÊME MÉTHODE QUE DANS AgentController
        // Conversation::getOrCreate() est une méthode statique du modèle
      $conversation = Conversation::getOrCreate($sessionId, [
        'phone_number' => $from,          // ✅ AJOUTER
        'projet_id' => $projetId,          // ✅ AJOUTER
         'prospect_id' => $prospectId||null,  // ✅ Plus NULL !           // ✅ AJOUTER
        'user_ip' => 'whatsapp',
        'user_agent' => 'WhatsApp Business',
    ]);
        if (!$conversation) {
            // Fallback: créer l'agent sans historique
            $agent = new AgentFinalService();
            $response = $agent->processMessage($message, $sessionId);
            return $response;
        }

        // 🔥 Récupérer l'état et l'historique
        $state = $conversation->state ?? [];
        $history = $conversation->history ?? [];

        // 🔥 Créer l'agent avec l'état sauvegardé
        $agent = new AgentFinalService($state);

        // 🔥 Appeler reply avec l'historique
        $result = $agent->reply($message, $history);

        // 🔥 Récupérer la réponse
        $response = $result['message'] ?? "Je n'ai pas pu traiter votre demande. 😊";

        // 🔥 Récupérer le nouvel état
        $newState = $result['state'] ?? $agent->getConversationState();

        // 🔥 Mettre à jour l'historique
        $history[] = [
            'role' => 'user',
            'content' => $message,
            'timestamp' => now()->toDateTimeString()
        ];

        $history[] = [
            'role' => 'assistant',
            'content' => $response,
            'timestamp' => now()->toDateTimeString()
        ];

        // 🔥 Sauvegarder en base de données (comme dans AgentController)
        $conversation->updateConversation($newState, $history);
        if ($prospectId) {
            $this->assignProspectIfInterested($prospectId, $projetId, $newState);
        }
        Log::info("🤖 Agent virtuel: Réponse générée et sauvegardée", [
            'response' => $response,
            'history_count' => count($history)
        ]);

        return $response;

    } catch (\Exception $e) {
        Log::error("❌ Erreur agent virtuel: " . $e->getMessage(), [
            'trace' => $e->getTraceAsString()
        ]);

        // Fallback: répondre sans historique
        $agent = new AgentFinalService();
        return $agent->processMessage($message, $sessionId);
    }
}
     /**
     * 🔥 VÉRIFIER SI LE NUMÉRO EXISTE DÉJÀ DANS WHATSAPP_MESSAGES
     */
/**
 * 🔥 VÉRIFIER SI LA CONVERSATION EXISTE ET SI ELLE EST TERMINÉE
 *
 * @return array [
 *   'exists' => bool,           // La conversation existe ?
 *   'is_completed' => bool,      // La visite est complète (nom + date) ?
 * ]
 */
private function isExistingConversation($projetId, $phoneNumber)
{
    try {
        $sessionId = $this->getSessionId($phoneNumber, $projetId);

        // ✅ Récupérer la conversation
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

        // ✅ Récupérer l'état de la conversation
        $state = $conversation->state ?? [];

        // ✅ Vérifier si la conversation est TERMINÉE (visite complète)
        $hasName = !empty($state['name']);
        $hasDate = !empty($state['appointment_date']);
        $visitAccepted = !empty($state['visit_accepted']);
        $isCompleted = !empty($state['completed']);

        // ✅ La conversation est "terminée" si :
        //    - Nom + Date renseignés  ET
        //    - Visite acceptée  ET
        //    - Statut completed = true
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
     * 🔥 ENVOYER UNE RÉPONSE WHATSAPP DEPUIS L'AGENT
     */
    private function sendAgentResponse($to, $message, $config, $projetId, $sessionId)
    {
        try {
            // Récupérer la configuration Twilio
            $twilio = new \Twilio\Rest\Client($config->account_sid, $config->access_token);

            // Envoyer le message
            $sentMessage = $twilio->messages->create(
                "whatsapp:" . $to,
                [
                    'from' => "whatsapp:" . $config->phone_number_id,
                    'body' => $message
                ]
            );

            // Stocker le message envoyé
            DB::connection('temp')->table('whatsapp_messages')->insert([
                'projet_id' => $projetId,
                'from_number' => $config->phone_number_id,
                'to_number' => $to,
                'message' => $message,
                'message_sid' => $sentMessage->sid,
                'profile_name' => 'Agent Virtuel Karim',
                'status' => 'sent',
                'message_type' => 'agent_response',
                'created_at' => now(),
                'updated_at' => now()
            ]);

             // ✅ MARQUER LE MESSAGE BOT (programme le follow-up)
            $conversation = \App\Models\Conversation::where('session_id', $sessionId)->first();
            if ($conversation) {
                $conversation->markBotMessage(); // ✅ Programme le follow-up
            }

            Log::info("✅ Réponse de l'agent envoyée + follow-up programmé", [
                'session_id' => $sessionId,
            ]);

            Log::info("✅ Réponse de l'agent envoyée à {$to}");

            return true;

        } catch (\Exception $e) {
            Log::error("❌ Erreur envoi réponse agent: " . $e->getMessage());
            return false;
        }
    }
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
                'updated_at' => now()
            ]);

        Log::info("✅ Messages marqués comme lus pour le numéro: {$phoneNumber}, projet: {$projetId}, {$updated} message(s) mis à jour");

        return response()->json([
            'success' => true,
            'updated_count' => $updated,
            'message' => "{$updated} message(s) marqué(s) comme lu(s)"
        ]);

    } catch (\Exception $e) {
        Log::error("❌ Erreur lors du marquage des messages comme lus: " . $e->getMessage());
        return response()->json([
            'success' => false,
            'error' => $e->getMessage()
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
          //  $databaseName = 'Erp_' . $societe->raison_sociale_concatene . '_' . $societe->id;
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
     /**
     * 🔥 VERSION MODIFIÉE DU WEBHOOK AVEC AGENT VIRTUEL
     * (Remplacer la fonction existante par celle-ci)
     */
    /**
 * 🔥 AUTO-AFFECTATION D'UN PROSPECT À UN COMMERCIAL
 * Logique circulaire basée sur last_affected
 *
 * @param int $prospectId ID du prospect à affecter
 * @param int $projetId ID du projet
 * @return int|false ID du commercial affecté ou false si échec
 */
/**
 * 🔥 AUTO-AFFECTATION D'UN PROSPECT À UN COMMERCIAL
 * Logique circulaire basée sur last_affected
 *
 * @param int $prospectId ID du prospect à affecter
 * @param int $projetId ID du projet
 * @return int|false ID du commercial affecté ou false si échec
 */
private function autoAssignSingleProspect($prospectId, $projetId)
{
    try {
        Log::info('🔄 Début de l\'auto-affectation pour le prospect', [
            'prospect_id' => $prospectId,
            'projet_id' => $projetId
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
                'commercial_id' => $prospect->commercial_affecte
            ]);
            return $prospect->commercial_affecte;
        }

        // Get all active commercials (role = 3) for this project
        $commercials = User::on('temp')
            ->where(function($query) use ($projetId) {
                $query->whereHas('projets', function($q) use ($projetId) {
                    $q->where('projet_id', $projetId);
                });
            })
            ->where('role', 3) // ROLE_COMMERCIAL
            ->where('is_actif', 1)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get();

        if ($commercials->isEmpty()) {
            Log::warning('⚠️ Aucun commercial actif trouvé', ['projet_id' => $projetId]);
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
                'name' => $targetCommercial->name . ' ' . $targetCommercial->prenom
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
                $commercials = User::on('temp')
                    ->where(function($query) use ($projetId) {
                        $query->whereHas('projets', function($q) use ($projetId) {
                            $q->where('projet_id', $projetId);
                        });
                    })
                    ->where('role', 3)
                    ->where('is_actif', 1)
                    ->whereNull('deleted_at')
                    ->orderBy('id')
                    ->get();

                Log::info('✅ Tous les commerciaux réinitialisés à last_affected = 0');
            }

            // ✅ Step 1: Find commercial with last_affected = 0
            $targetCommercial = null;

            foreach ($commercials as $commercial) {
                if ($commercial->last_affected == 0) {
                    $targetCommercial = $commercial;
                    Log::info('✅ Commercial trouvé avec last_affected = 0', [
                        'commercial_id' => $commercial->id,
                        'name' => $commercial->name . ' ' . $commercial->prenom
                    ]);
                    break;
                }
            }

            // ✅ Step 2: If all have last_affected = 1, take the first one
            if (!$targetCommercial) {
                $targetCommercial = $commercials->first();
                Log::info('🔄 Tous les commerciaux ont last_affected = 1, prise du premier', [
                    'commercial_id' => $targetCommercial->id,
                    'name' => $targetCommercial->name . ' ' . $targetCommercial->prenom
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
                'commercial_id' => $newCommercialId
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
                'statut_id' => $statutProspect->id
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
                'last_affected' => 1
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

            // ✅ 7. Send notification (OUTSIDE transaction)
            $this->sendAffectationNotification($newCommercialId, $prospectId, $projetId);

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
                'user_last_affected' => $verifyUser ? $verifyUser->last_affected : null
            ]);

            Log::info('✅ Auto-affectation terminée avec succès', [
                'prospect_id' => $prospectId,
                'commercial_id' => $newCommercialId,
                'commercial_name' => $targetCommercial->name . ' ' . $targetCommercial->prenom
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

        // Critère 2 : Budget donné
        $hasBudget = !empty($state['budget'])
                     && !empty($state['budget_given_by_user']);

        // Critère 3 : Visite demandée
        $wantsVisit = !empty($state['wants_visit'])
                      || !empty($state['visit_requested']);

        // Critère 4 : Visite acceptée
        $visitAccepted = !empty($state['visit_accepted']);

        // ✅ Intérêt concret = au moins UN de ces critères
        $hasConcreteInterest = $hasType || $hasBudget || $wantsVisit || $visitAccepted;

        Log::info("🔍 Vérification intérêt prospect", [
            'prospect_id' => $prospectId,
            'has_type' => $hasType,
            'property_type' => $state['property_type'] ?? null,
            'has_budget' => $hasBudget,
            'budget' => $state['budget'] ?? null,
            'wants_visit' => $wantsVisit,
            'visit_accepted' => $visitAccepted,
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
            'visit_accepted' => $visitAccepted,
        ]);

        // 🔥 Appeler l'auto-affectation
        $assignedCommercialId = $this->autoAssignSingleProspect($prospectId, $projetId);

        if ($assignedCommercialId) {
            Log::info("✅ Prospect affecté avec succès", [
                'prospect_id' => $prospectId,
                'commercial_id' => $assignedCommercialId,
            ]);

            // 🔥 Envoyer la notification d'affectation
            $this->sendAffectationNotification($assignedCommercialId, $prospectId, $projetId);

            return $assignedCommercialId;
        } else {
            Log::warning("⚠️ Aucun commercial disponible", [
                'prospect_id' => $prospectId,
            ]);
            return false;
        }

    } catch (\Exception $e) {
        Log::error("❌ Erreur affectation intérêt: " . $e->getMessage(), [
            'trace' => $e->getTraceAsString(),
        ]);
        return false;
    }
}
 public function webhook_whatsapp_business(Request $request)
    {
        try {
            Log::info('📩 Message reçu de Twilio', $request->all());

            // Nettoyer les numéros
            $to = ltrim(str_replace('whatsapp:', '', $request->input('To')), '+');
            $number = str_replace('whatsapp:', '', $request->input('From'));
            $from = '+' . ltrim($number, '+');
            $body = $request->input('Body');
            $messageSid = $request->input('MessageSid');
            $profileName = $request->input('ProfileName', 'Utilisateur WhatsApp');

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

            // ========== TROUVER OU CRÉER LE PROSPECT ==========
            $prospect = DB::connection('temp')
                ->table('prospects')
                ->where('telephone', $from)
                ->orWhere('telephone_num2', $from)
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

                $prospectData = [
                    'telephone' => $from,
                    'telephone_num2' => null,
                    'nom' => $profileName,
                    'prenom' => '',
                    'email' => null,
                    'projet_id' => $foundConfig->projet_id,
                    'origin' => 'WhatsApp',
                    'created_at' => now(),
                    'updated_at' => now()
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
            Log::info('🔄 Début de l\'auto-affectation pour le nouveau prospect', [
                'prospect_id' => $prospectId,
                'projet_id' => $foundConfig->projet_id
            ]);

           /* if ($isNewProspect) {

                $assignedCommercialId = $this->autoAssignSingleProspect($prospectId, $foundConfig->projet_id);

                if ($assignedCommercialId) {
                    Log::info('✅ Prospect affecté automatiquement', [
                        'prospect_id' => $prospectId,
                        'commercial_id' => $assignedCommercialId
                    ]);
                    // 🔥 NOTIFICATION 1: Envoyer au commercial affecté UNIQUEMENT
                $this->sendAffectationNotification($assignedCommercialId, $prospectId, $foundConfig->projet_id);
                } else {
                    Log::warning('⚠️ Aucun commercial disponible pour l\'affectation', [
                        'prospect_id' => $prospectId
                    ]);
                }
            }*/


            // ========== TÉLÉCHARGER ET STOCKER LE MÉDIA ==========
            $localMediaUrl = null;
            if ($mediaUrl && $foundSociete) {
                $localMediaUrl = $this->downloadAndStoreMedia(
                    $mediaUrl,
                    $foundConfig,
                    $from,
                    $messageSid,
                    $foundSociete
                );

                if ($localMediaUrl) {
                    Log::info("✅ Média téléchargé et stocké: {$localMediaUrl}");
                } else {
                    Log::warning("⚠️ Échec du téléchargement du média, utilisation de l'URL Twilio");
                }
            }

            // ========== STOCKER LE NOUVEAU MESSAGE ==========
            $messageData = [
                'projet_id' => $foundConfig->projet_id,
                'from_number' => $from,
                'to_number' => $to,
                'message' => $body ?: ($mediaUrl ? '📎 Fichier joint' : ''),
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
            // 🔥 NOUVEAU : TRAITER AVEC L'AGENT VIRTUEL
            // ============================================================

            // Vérifier si le message n'est pas vide
            if (!empty($body)) {
                // Générer un session ID
                $sessionId = $this->getSessionId($from, $foundConfig->projet_id);
                // ✅ NOUVEAU : MARQUER LE MESSAGE CLIENT (annule le follow-up)
                    $conversation = \App\Models\Conversation::where('session_id', $sessionId)->first();
                    if ($conversation) {
                        $conversation->markClientMessage();
                        Log::info("📩 Message client marqué (follow-up annulé)", [
                            'session_id' => $sessionId,
                        ]);
                    }
           // ✅ Vérifier si conversation existe ET si elle est complète
            $conversationCheck = $this->isExistingConversation($foundConfig->projet_id, $from);

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
            //    3. Conversation NON terminée (visite incomplète)
            // ════════════════════════════════════════════════════════════
                    // $agentResponse = $this->processWithAgent($body, $from, $sessionId,$foundConfig->projet_id,$prospectId);

        $shouldAgentRespond = $isNewProspect || !$isExisting || !$isCompleted;

        if ($shouldAgentRespond) {
                    $reason = 'inconnu';
                    if ($isNewProspect) {
                        $reason = 'Nouveau prospect';
                    } elseif (!$isExisting) {
                        $reason = 'Prospect sans conversation';
                    } else {
                        $reason = 'Conversation non terminée (visite incomplète)';
                    }

                    Log::info("🤖 Agent virtuel DÉCLENCHÉ pour {$from}", [
                        'raison' => $reason,
                    ]);

                    // Traiter avec l'agent
                    $agentResponse = $this->processWithAgent(
                        $body,
                        $from,
                        $sessionId,
                        $foundConfig->projet_id,
                        $prospectId
                    );

                    // Envoyer la réponse
                    if ($agentResponse) {
                        $this->sendAgentResponse(
                            $from,
                            $agentResponse,
                            $foundConfig,
                            $foundConfig->projet_id,
                            $sessionId
                        );
                        Log::info("✅ Réponse de l'agent envoyée à {$from}");
                    }
                } else {
                    Log::info("⏹️ Agent NON déclenché pour {$from}", [
                        'raison' => 'Visite complète (nom + date + acceptée)',
                        'prospect_id' => $prospectId,
                    ]);
                    Log::info("   → Le commercial prend le relais pour finaliser le RDV");
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

            /* ========== WEBOOK EVENT ==========
            $web = new WebhookEvent();
            $web->setConnection('temp');
            $web->platform = 'whatsapp';
            $web->type = 'whatsapp_message';
            $web->data = $request->all();
            $web->save();*/

            // ========== NOTIFICATION ==========
            broadcast(new NotificationEvent(0));
            $this->createWhatsAppNotification($prospectId, $from, $profileName, $body, $foundConfig->projet_id, $isNewProspect,$assignedCommercialId);

            Log::info("✅ Message WhatsApp traité avec succès: {$messageSid}");

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            Log::error('❌ Erreur webhook WhatsApp: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['status' => 'error'], 200);
        }
    }

/*public function webhook_whatsapp_business(Request $request)
{
    try {
        Log::info('Message reçu de Twilio', $request->all());

        // Nettoyer les numéros
        $to = ltrim(str_replace('whatsapp:', '', $request->input('To')), '+');
        $number = str_replace('whatsapp:', '', $request->input('From'));
        $from = '+' . ltrim($number, '+');
        $body = $request->input('Body');
        $messageSid = $request->input('MessageSid');
        $profileName = $request->input('ProfileName', 'Utilisateur WhatsApp');

        // ✅ FIX: Récupérer les informations sur le média CORRECTEMENT
        $numMedia = $request->input('NumMedia', 0);
        $mediaUrl = null;
        $mediaContentType = null;
        $mediaType = null;

        if ($numMedia > 0) {
            $mediaUrl0 ='https://phplaravel-1633242-6465298.cloudwaysapps.com/docs/logo/tracimo_blue.png';
            $mediaContentType0 = $request->input('MediaContentType0');

            if ($mediaUrl0) {
                $mediaUrl = $mediaUrl0;
                $mediaContentType = $mediaContentType0;

                // Déterminer le type de média
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

        // ========== TROUVER OU CRÉER LE PROSPECT ==========
        $prospect = DB::connection('temp')
            ->table('prospects')
            ->where('telephone', $from)
            ->orWhere('telephone_num2', $from)
            ->first();

        $prospectId = null;
        $isNewProspect = false;

        if ($prospect) {
            $prospectId = $prospect->id;
            Log::info("📞 Prospect existant trouvé: {$from} (ID: {$prospectId})");
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

            $prospectData = [
                'telephone' => $from,
                'telephone_num2' => null,
                'nom' => $profileName,
                'prenom' => '',
                'email' => null,
                'projet_id' => $foundConfig->projet_id,
                'origin' => 'WhatsApp',
                'created_at' => now(),
                'updated_at' => now()
            ];

            if ($sourceId) {
                $prospectData['source'] = $sourceId;
            }

            $prospectId = DB::connection('temp')->table('prospects')->insertGetId($prospectData);
            $isNewProspect = true;
            $statutProspect = new StatutProspect();
            $statutProspect->setConnection('temp');
            $statutProspect->prospect_id = $prospectId;
            $statutProspect->statut = '0'; // En attente
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
        if ($mediaUrl && $foundSociete) {
           $localMediaUrl = $this->downloadAndStoreMedia(
                $mediaUrl,
                $foundConfig,
                $from,
                $messageSid,
                $foundSociete
            );

            if ($localMediaUrl) {
                Log::info("✅ Média téléchargé et stocké: {$localMediaUrl}");
            } else {
                Log::warning("⚠️ Échec du téléchargement du média, utilisation de l'URL Twilio");
            }
        }

        // ========== STOCKER LE NOUVEAU MESSAGE ==========
        $messageData = [
            'projet_id' => $foundConfig->projet_id,
            'from_number' => $from,
            'to_number' => $to,
            'message' => $body ?: ($mediaUrl ? '📎 Fichier joint' : ''),
            'message_sid' => $messageSid,
            'profile_name' => $profileName,
            'status' => 'received',
            'created_at' => now(),
            'updated_at' => now(),
            'media_url' => $localMediaUrl ?: $mediaUrl, // Utiliser l'URL locale si disponible, sinon l'URL Twilio
            'prospect_id' => $prospectId,
        ];

        $messageId = DB::connection('temp')->table('whatsapp_messages')->insertGetId($messageData);
        $messageData['id'] = $messageId;
        $messageData['created_at'] = $messageData['created_at']->toISOString();

        // Broadcast...
        Config::set('broadcasting.default', 'pusher_whatsapp');
        try {
            broadcast(new NewWhatsAppMessageEvent($messageData, $foundConfig->projet_id, $from))->toOthers();
            Log::info("✅ Broadcast Pusher envoyé pour le message: {$messageSid}");
        } catch (\Exception $e) {
            Log::warning("⚠️ Erreur broadcast Pusher: " . $e->getMessage());
        }

        $web = new WebhookEvent();
        $web->setConnection('temp');
        $web->platform = 'whatsapp';
        $web->type = 'whatsapp_message';
        $web->data = $request->all();
        $web->save();

        broadcast(new NotificationEvent(0));

        $this->createWhatsAppNotification($prospectId, $from, $profileName, $body, $foundConfig->projet_id, $isNewProspect);

        Log::info("✅ Message WhatsApp traité avec succès: {$messageSid}");

        return response()->json(['status' => 'success']);

    } catch (\Exception $e) {
        Log::error('❌ Erreur webhook WhatsApp: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString()
        ]);
        return response()->json(['status' => 'error'], 200);
    }
}*/

/**
 * Télécharger et stocker le média sur le serveur
 */
private function downloadAndStoreMedia($mediaUrl, $config, $from, $messageSid, $societe, $doss = 'whatsapp_media')
{
    try {
        Log::info("📥 Téléchargement du média depuis: {$mediaUrl}");

        // Récupérer le contenu du média
        $client = new \GuzzleHttp\Client();
        $response = $client->get($mediaUrl, [
            'auth' => [$config->account_sid, $config->access_token]
        ]);

        $content = $response->getBody()->getContents();
        $contentType = $response->getHeader('Content-Type')[0] ?? 'application/octet-stream';

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

        // ✅ FIX: Créer un fichier temporaire avec le contenu
        $tempPath = tempnam(sys_get_temp_dir(), 'whatsapp_media_');
        file_put_contents($tempPath, $content);

        // ✅ FIX: Créer un objet UploadedFile à partir du fichier temporaire
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
            'type' => $contentType
        ]);

        return $mediaUrl;

    } catch (\Exception $e) {
        Log::error("❌ Erreur téléchargement média: " . $e->getMessage());
        Log::error("❌ Trace: " . $e->getTraceAsString());
        return null;
    }
}
/**
 * Envoyer une notification d'affectation au commercial
 */
private function sendAffectationNotification($commercialId, $prospectId, $projetId)
{
    try {
        $commercial = User::on('temp')->find($commercialId);
        $prospect = Prospect::on('temp')->find($prospectId);

        if (!$commercial || !$prospect) {
            Log::warning('⚠️ Impossible d\'envoyer la notification d\'affectation', [
                'commercial_id' => $commercialId,
                'prospect_id' => $prospectId
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
            'prospect_id' => $prospectId
        ]);

        return $notification;

    } catch (\Exception $e) {
        Log::error('❌ Erreur envoi notification affectation: ' . $e->getMessage());
        return false;
    }
}
/**
 * Créer une notification pour les commerciaux
 */
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

    /**
     * Créer une notification pour les commerciaux
     */

    /**
     * Récupérer les conversations WhatsApp
     */
    /**
 * Récupérer les conversations WhatsApp
 */
 /**
 * Récupérer les conversations WhatsApp
 */
public function getConversations(Request $request, $projetId)
{
    DatabaseHelper::Config();

    // Check if the whatsapp_messages table exists
    if (!Schema::connection('temp')->hasTable('whatsapp_messages')) {
        return response()->json([
            'conversations' => []
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
            'conversations' => []
        ]);
    }

    $ourWhatsappNumber = $whatsappConfig->phone_number_id;

    // Get unique phone numbers from both from_number AND to_number (excluding our number)
    $phoneNumbers = DB::connection('temp')
        ->table('whatsapp_messages')
        ->where('projet_id', $projetId)
        ->select(DB::raw("DISTINCT CASE
            WHEN from_number = '{$ourWhatsappNumber}' THEN to_number
            ELSE from_number
        END as phone_number"))
        ->where(function($query) use ($ourWhatsappNumber) {
            $query->where('from_number', '!=', $ourWhatsappNumber)
                  ->orWhere('to_number', '!=', $ourWhatsappNumber);
        })
        ->get();

    $conversations = [];

    foreach ($phoneNumbers as $phone) {
        $phoneNumber = $phone->phone_number;
        if (empty($phoneNumber)) continue;

        // Get messages between the two parties
        $messages = DB::connection('temp')
            ->table('whatsapp_messages')
            ->where('projet_id', $projetId)
            ->where(function($query) use ($phoneNumber, $ourWhatsappNumber) {
                $query->where(function($q) use ($phoneNumber, $ourWhatsappNumber) {
                    $q->where('from_number', $phoneNumber)
                      ->where('to_number', $ourWhatsappNumber);
                })->orWhere(function($q) use ($phoneNumber, $ourWhatsappNumber) {
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
                ->where('telephone', $phoneNumber)
                ->orWhere('telephone_num2', $phoneNumber)
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
                'last_message_from_me' => $lastMessage && $lastMessage->from_number == $ourWhatsappNumber
            ];
        }
    }

    // Sort by last_message_date descending
    usort($conversations, function($a, $b) {
        return strtotime($b['last_message_date']) - strtotime($a['last_message_date']);
    });

    return response()->json(['conversations' => array_values($conversations)]);
}

    /**
     * Récupérer une conversation spécifique
     */
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
        ->where(function($query) use ($phoneNumber) {
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
            ->where(function($query) use ($phoneNumber, $ourWhatsappNumber) {
                $query->where(function($q) use ($phoneNumber, $ourWhatsappNumber) {
                    $q->where('from_number', $ourWhatsappNumber)
                      ->where('to_number', $phoneNumber);
                })->orWhere(function($q) use ($phoneNumber, $ourWhatsappNumber) {
                    $q->where('from_number', $phoneNumber)
                      ->where('to_number', $ourWhatsappNumber);
                });
            })
            ->orderBy('created_at', 'asc')
            ->get();
    }

    $prospect = DB::connection('temp')
        ->table('prospects')
        ->where('telephone', $phoneNumber)
        ->orWhere('telephone_num2', $phoneNumber)
        ->first();

    return response()->json([
        'conversation' => [
            'phone_number' => $phoneNumber,
            'profile_name' => $messages->first()->profile_name ?? 'Client',
            'prospect_id' => $prospect->id ?? null,
            'prospect_nom' => $prospect->nom ?? null,
            'messages' => $messages
        ]
    ]);
}

   // Envoyer une réponse WhatsApp

public function sendReply(Request $request, $projetId, $phoneNumber)
{
    DatabaseHelper::Config();

    $request->validate([
        'message' => 'nullable|string|max:1600',
        'media_url' => 'nullable|string|url',
    ]);

    $messageText = $request->input('message', '');
    $mediaUrl = $request->input('media_url', null);
    // $mediaUrl = 'https://phplaravel-1633242-6465298.cloudwaysapps.com/docs/logo/tracimo_blue.png';

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
            $hoursSinceLastReply = Carbon::now()->diffInHours($lastReplyTime);

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
                'body' => $messageText ?: 'Fichier joint'
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
                'user_id_sent' => $userIdSent, // ✅ Added user_id_sent
                'created_at' => now(),
                'updated_at' => now()
            ]);

            Log::info("✅ Message normal envoyé à {$phoneNumber}, SID: " . $sentMessage->sid);

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
                'note' => "Message normal envoyé (client actif)"
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
            $templateSids = array_filter($templateSids);

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
                    'contentVariables' => json_encode($templateVariables)
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
                  'user_id_sent' => $userIdSent, // ✅ Added user_id_sent
                'created_at' => now(),
                'updated_at' => now()
            ]);

            Log::info("✅ Template #" . ($templateIndex + 1) . " envoyé à {$phoneNumber}, SID: " . $sentMessage->sid);

            // Get template name for response
            $templateNames = $hasMedia ? [
                'File Template 1',

            ] : [
                'Template 1',
                'Template 2',
                'Template 3'
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
                'display_message' => $messageText
            ]);
        }

    } catch (\Exception $e) {
        Log::error("❌ Erreur envoi message: " . $e->getMessage());
        return response()->json(['error' => $e->getMessage()], 500);
    }
}




    // Configuration management (following Facebook pattern)
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
        //Here
    public function store_whatsapp_configuration(Request $request)
    {
        if (RoleHelper::AdminSup()|| RoleHelper::AgentAdmin()) {
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
                    'webhook_enabled' => false, // Explicitly set to false
                    'webhook_verify_token' => null,
                    'webhook_subscriptions' => null,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                return response()->json([
                    'message' => 'Configuration WhatsApp enregistrée avec succès',
                    'configuration_id' => $configId
                ], 200);
            } catch (\Exception $e) {
                return response()->json([
                    'error' => 'Erreur lors de l\'enregistrement: ' . $e->getMessage()
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
                        'webhook_enabled' => false, // Explicitly set to false
                        'webhook_subscriptions' => json_encode(['messages']), // WhatsApp messages
                        'updated_at' => now()
                    ]);

                return response()->json([
                    'message' => 'Webhook WhatsApp configuré avec succès',
                    'webhook_enabled' => false // Return false to indicate it's disabled
                ], 200);
            } catch (\Exception $e) {
                return response()->json(['error' => 'Erreur lors de la configuration: ' . $e->getMessage()], 500);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
        //here
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
                        'updated_at' => now()
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
                        'updated_at' => now()
                    ]);

                $statusText = $newStatus ? 'activé' : 'désactivé';
                return response()->json([
                    'message' => "Webhook WhatsApp {$statusText} avec succès",
                    'webhook_enabled' => $newStatus
                ], 200);
            } catch (\Exception $e) {
                return response()->json(['error' => 'Erreur lors de la modification: ' . $e->getMessage()], 500);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
        //here
    public function update_whatsapp_configuration(Request $request, $configId)
    {
        if (RoleHelper::AdminSup() || RoleHelper::AgentAdmin()) {
            DatabaseHelper::Config();

            try {
                // Validate required fields
                if (!$request->phone_number_id || !$request->access_token || !$request->projet_id|| !$request->account_sid) {
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
                        'updated_at' => now()
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
        //here
    public function delete_whatsapp_configuration($configId)
    {
        if (RoleHelper::AdminSup()|| RoleHelper::AgentAdmin()) {
            DatabaseHelper::Config();

            try {
                $deleted = DB::connection('temp')
                    ->table('whatsapp_configurations')
                    ->where('id', $configId)
                    ->update([
                        'deleted_at' => now()
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
            // This stores the file and handles everything
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
                'dossier' => $doss
            ]);

            return response()->json([
                'success' => true,
                'url' => $mediaUrl,
                'type' => $request->input('type', 'auto'),
                'filename' => $fileName,
                'original_name' => $file->getClientOriginalName()
            ]);
        }

        return response()->json(['error' => 'No file uploaded'], 400);

    } catch (\Exception $e) {
        Log::error('Upload error: ' . $e->getMessage());
        return response()->json(['error' => $e->getMessage()], 500);
    }
}
}





