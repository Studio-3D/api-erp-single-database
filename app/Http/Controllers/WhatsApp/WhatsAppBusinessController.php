<?php

namespace App\Http\Controllers\WhatsApp;
use App\Http\Helpers\RoleHelper;

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
use Illuminate\Support\Facades\Config;
use App\Events\NewWhatsAppMessageEvent;  // AJOUTER CETTE LIGNE
use Twilio\Rest\Client as ClientTwilio;  // ← AJOUTER CETTE LIGNE ICI

class WhatsAppBusinessController extends Controller
{
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

            $databaseName = 'Erp_' . $societe->raison_sociale_concatene . '_' . $societe->id;
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

  public function webhook_whatsapp_business(Request $request)
{
    try {
        Log::info('Message reçu de Twilio', $request->all());

        // Nettoyer les numéros
        $to = ltrim(str_replace('whatsapp:', '', $request->input('To')), '+');
        $from = ltrim(str_replace('whatsapp:', '', $request->input('From')), '+');
        $body = $request->input('Body');
        $messageSid = $request->input('MessageSid');
        $profileName = $request->input('ProfileName', 'Utilisateur WhatsApp');

        Log::info("Recherche configuration pour numéro: {$to}");

        // ========== PARCOURIR TOUTES LES SOCIÉTÉS ==========
        $societes = \App\Models\Societe::all();

        $foundConfig = null;
        $foundDatabaseName = null;
        $foundSocieteId = null;

        foreach ($societes as $societe) {
            $databaseName = 'Erp_' . $societe->raison_sociale_concatene . '_' . $societe->id;

            Log::info("Recherche dans la base: " . $databaseName);

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
                        $foundSocieteId = $societe->id;
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

        Log::info("✅ Connecté à la base: " . $foundDatabaseName);

        if (!Schema::connection('temp')->hasTable('whatsapp_messages')) {
            Schema::connection('temp')->create('whatsapp_messages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('projet_id');
                $table->string('from_number');
                $table->string('to_number');
                $table->text('message');
                $table->string('message_sid')->unique();
                $table->string('profile_name')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->string('status')->default('received');
                $table->timestamps();
            });
            Log::info("Table whatsapp_messages créée");
        }

        $existingMessage = DB::connection('temp')
            ->table('whatsapp_messages')
            ->where('message_sid', $messageSid)
            ->first();

        if ($existingMessage) {
            Log::info("Message déjà traité: {$messageSid}");
            return response()->json(['status' => 'already_processed']);
        }

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

            // ========== 🔥 NOUVEAU : Marquer nos messages envoyés à ce prospect comme LUS ==========
            $ourMessages = DB::connection('temp')
                ->table('whatsapp_messages')
                 ->where('from_number',$foundConfig->phone_number_id)  // Notre numéro fixe
                ->where('projet_id', $foundConfig->projet_id)
                ->where('to_number', $from)
                ->whereIn('status', ['sent', 'delivered'])
                ->get();

            if ($ourMessages->count() > 0) {
                DB::connection('temp')
                    ->table('whatsapp_messages')
                    ->where('projet_id', $foundConfig->projet_id)
                    ->where('to_number', $from)
                     ->where('from_number', $foundConfig->phone_number_id)
                    ->whereIn('status', ['sent', 'delivered'])
                    ->update([
                        'status' => 'read',
                        'read_at' => now(),
                        'updated_at' => now()
                    ]);

                foreach ($ourMessages as $msg) {
                    try {
                        $messageData = (array)$msg;
                        $messageData['status'] = 'read';
                        $messageData['read_at'] = now()->toISOString();
                        broadcast(new NewWhatsAppMessageEvent($messageData, $foundConfig->projet_id, $from))->toOthers();
                        Log::info("📡 Broadcast read pour message envoyé {$msg->id}");
                    } catch (\Exception $e) {
                        Log::warning("⚠️ Erreur broadcast: " . $e->getMessage());
                    }
                }
            }

            // ========== Marquer les messages précédents du prospect comme lus ==========
            $unreadMessages = DB::connection('temp')
                ->table('whatsapp_messages')
                ->where('projet_id', $foundConfig->projet_id)
                ->where('from_number', $from)
                ->where(function($q) {
                    $q->where('status', 'delivered')
                      ->orWhere(function($sub) {
                          $sub->where('status', 'received')
                              ->whereNull('read_at');
                      });
                })
                ->get();

            if ($unreadMessages->count() > 0) {
                DB::connection('temp')
                    ->table('whatsapp_messages')
                    ->where('projet_id', $foundConfig->projet_id)
                    ->where('from_number', $from)
                    ->where(function($q) {
                        $q->where('status', 'delivered')
                          ->orWhere(function($sub) {
                              $sub->where('status', 'received')
                                  ->whereNull('read_at');
                          });
                    })
                    ->update([
                        'status' => 'read',
                        'read_at' => now(),
                        'updated_at' => now()
                    ]);

                foreach ($unreadMessages as $msg) {
                    try {
                        $messageData = (array)$msg;
                        $messageData['status'] = 'read';
                        $messageData['read_at'] = now()->toISOString();
                        broadcast(new NewWhatsAppMessageEvent($messageData, $foundConfig->projet_id, $from))->toOthers();
                        Log::info("📡 Broadcast read pour message {$msg->id}");
                    } catch (\Exception $e) {
                        Log::warning("⚠️ Erreur broadcast read: " . $e->getMessage());
                    }
                }
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
            Log::info("✅ Nouveau prospect créé: {$from} (ID: {$prospectId})");
        }

        // ========== STOCKER LE NOUVEAU MESSAGE ==========
        $messageData = [
            'projet_id' => $foundConfig->projet_id,
            'from_number' => $from,
            'to_number' => $to,
            'message' => $body,
            'message_sid' => $messageSid,
            'profile_name' => $profileName,
            'status' => 'received',
            'created_at' => now(),
            'updated_at' => now()
        ];

        $messageId = DB::connection('temp')->table('whatsapp_messages')->insertGetId($messageData);
        $messageData['id'] = $messageId;
        $messageData['created_at'] = $messageData['created_at']->toISOString();

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
}
/**
 * Créer une notification pour les commerciaux
 */
private function createWhatsAppNotification($prospectId, $phoneNumber, $profileName, $message, $projetId, $isNewProspect = false)
{
    try {
        if ($isNewProspect) {
            $description = "📱 *NOUVEAU CONTACT WHATSAPP*\n\n";
            $description .= "📞 Numéro: {$phoneNumber}\n";
            $description .= "👤 Nom: {$profileName}\n";
            $description .= "💬 Message: " . (strlen($message) > 100 ? substr($message, 0, 100) . '...' : $message);
            $type = 51;
        } else {
            $description = "💬 *NOUVEAU MESSAGE WHATSAPP*\n\n";
            $description .= "📞 De: {$phoneNumber}\n";
            $description .= "👤 Client: {$profileName}\n";
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
        $notification->role = 2; // ADMIN_COMMERCIAL
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
public function getConversations(Request $request, $projetId)
{
    DatabaseHelper::Config();

    // Récupérer tous les messages groupés par expéditeur
    $conversations = DB::connection('temp')
        ->table('whatsapp_messages')
        ->where('projet_id', $projetId)
        ->select(
            'from_number as phone_number',
            'profile_name',
            DB::raw('MAX(created_at) as last_message_date'),
            DB::raw('COUNT(*) as message_count'),
            DB::raw('SUM(CASE WHEN status = "received" AND read_at IS NULL THEN 1 ELSE 0 END) as unread_count')
        )
        ->groupBy('from_number', 'profile_name')
        ->orderBy('last_message_date', 'desc')
        ->get()
        ->map(function ($conv) use ($projetId) {
            // Récupérer le dernier message
            $lastMessage = DB::connection('temp')
                ->table('whatsapp_messages')
                ->where('projet_id', $projetId)
                ->where('from_number', $conv->phone_number)
                ->orderBy('created_at', 'desc')
                ->first();

            // Récupérer le prospect associé
            $prospect = DB::connection('temp')
                ->table('prospects')
                ->where('telephone', $conv->phone_number)
                ->orWhere('telephone_num2', $conv->phone_number)
                ->first();

            return [
                'phone_number' => $conv->phone_number,
                'profile_name' => $conv->profile_name,
                'prospect_id' => $prospect->id ?? null,
                'prospect_nom' => $prospect->nom ?? $conv->profile_name,
                'last_message' => $lastMessage->message ?? null,
                'last_message_date' => $conv->last_message_date,
                'message_count' => $conv->message_count,
                'unread_count' => (int) $conv->unread_count,  // ← Ajout du compteur non lu
                'last_message_from_me' => $lastMessage && $lastMessage->from_number == $conv->phone_number
            ];
        });

    return response()->json(['conversations' => $conversations]);
}

    /**
     * Récupérer une conversation spécifique
     */
    public function getConversation(Request $request, $projetId, $phoneNumber)
    {
        DatabaseHelper::Config();

        $messages = DB::connection('temp')
            ->table('whatsapp_messages')
            ->where('projet_id', $projetId)
            ->where(function($query) use ($phoneNumber) {
                $query->where('from_number', $phoneNumber)
                      ->orWhere('to_number', $phoneNumber);
            })
            ->orderBy('created_at', 'asc')
            ->get();

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

    /**
     * Envoyer une réponse WhatsApp
     */
public function sendReply(Request $request, $projetId, $phoneNumber)
{
    DatabaseHelper::Config();

    $request->validate([
        'message' => 'nullable|string|max:1600',
        'media_url' => 'nullable|url',
        'media_type' => 'nullable|string|in:image,audio'
    ]);

    $messageText = $request->input('message', '');
    $mediaUrl = $request->input('media_url');
    $mediaType = $request->input('media_type', 'image');

    if (empty($messageText) && empty($mediaUrl)) {
        return response()->json(['error' => 'Un message ou un média est requis'], 400);
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

    try {
        // 🔥 CORRECTION: Construire le message correctement
        if (!empty($mediaUrl)) {
            // Envoi avec média (image ou audio)
            $sentMessage = $twilio->messages->create(
                "whatsapp:" . $phoneNumber,
                [
                    'from' => "whatsapp:" . $config->phone_number_id,
                    'mediaUrl' => [$mediaUrl],
                    'body' => $messageText ?: null
                ]
            );
        } else {
            // Envoi texte seulement
            $sentMessage = $twilio->messages->create(
                "whatsapp:" . $phoneNumber,
                [
                    'from' => "whatsapp:" . $config->phone_number_id,
                    'body' => $messageText
                ]
            );
        }

        $messageId = DB::connection('temp')->table('whatsapp_messages')->insertGetId([
            'projet_id' => $projetId,
            'from_number' => $config->phone_number_id,
            'to_number' => $phoneNumber,
            'message' => $messageText ?: ($mediaType === 'audio' ? '🎤 Message vocal' : '📷 Image'),
            'message_sid' => $sentMessage->sid,
            'profile_name' => auth()->user()->name ?? 'Commercial',
            'status' => 'sent',
            'media_url' => $mediaUrl,
            'media_type' => $mediaType,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        Log::info("Message envoyé à {$phoneNumber}, SID: " . $sentMessage->sid);

        return response()->json([
            'success' => true,
            'sid' => $sentMessage->sid,
            'message_id' => $messageId
        ]);

    } catch (\Exception $e) {
        Log::error("Erreur envoi message: " . $e->getMessage());
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

        $request->validate([
            'file' => 'required|file|max:10240', // 10MB max
            'type' => 'required|in:image,audio'
        ]);

        $file = $request->file('file');
        $type = $request->input('type');

        $folder = $type === 'image' ? 'whatsapp_images' : 'whatsapp_audios';
        $path = $file->store($folder, 'public');
        $url = asset('storage/' . $path);

        return response()->json([
            'success' => true,
            'url' => $url,
            'type' => $type,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize()
        ]);

    } catch (\Exception $e) {
        Log::error('Upload error: ' . $e->getMessage());
        return response()->json(['error' => $e->getMessage()], 500);
    }
}

}





