<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use App\Models\WebhookEvent;
use App\Events\NotificationEvent;
use Illuminate\Support\Facades\Config;
use App\Models\Societe;

class WhatsAppBusinessController extends Controller
{

public function webhook_whatsapp_business(Request $request)
{
      $mode = $request->query('hub_mode', $request->query('hub.mode'));
        $verifyToken = $request->query('hub_verify_token', $request->query('hub.verify_token'));
        $challenge = $request->query('hub_challenge', $request->query('hub.challenge'));
     
    // Alternative method if Input facade doesn't work:
    // $mode = $request->input('hub.mode');
    // $verifyToken = $request->input('hub.verify_token');
    // $challenge = $request->input('hub.challenge');

    Log::warning("token", ['verifyToken' => $verifyToken]);

    if ($mode === 'subscribe') {
        // Your existing verification logic...
        $fallback = env('WHATSAPP_WEBHOOK_VERIFY_TOKEN');
        if ($fallback && hash_equals($fallback, (string) $verifyToken)) {
            Log::info("WhatsApp webhook verification successful via fallback env token");
            return response($challenge, 200);
        }

        // ... rest of your verification logic
    }

    // Rest of your webhook handling...
}
    /* WhatsApp Business API Webhook (Meta/Facebook format)
    public function webhook_whatsapp_business(Request $request)
    {
        // Handle webhook verification (required by Meta)
        // Meta sends GET with query params: hub.mode, hub.verify_token, hub.challenge
        $mode = $request->query('hub_mode', $request->query('hub.mode'));
        $verifyToken = $request->query('hub_verify_token', $request->query('hub.verify_token'));
        $challenge = $request->query('hub_challenge', $request->query('hub.challenge'));
            Log::warning("token", ['verifyToken' => $verifyToken]);

        if ($mode === 'subscribe') {
            // First: environment fallback (fast path, same as Facebook/Instagram pattern)
            $fallback = env('WHATSAPP_WEBHOOK_VERIFY_TOKEN');
            if ($fallback && hash_equals($fallback, (string) $verifyToken)) {
                Log::info("WhatsApp webhook verification successful via fallback env token");
                return response($challenge, 200)->header('Content-Type', 'text/plain');
            }

            // Then: check against all configured webhook tokens across tenants
            $validTokens = $this->getAllWhatsAppWebhookTokens();
            if (in_array($verifyToken, $validTokens)) {
                Log::info("WhatsApp webhook verification successful via DB token");
                return response($challenge, 200)->header('Content-Type', 'text/plain');
            }

            Log::warning("Invalid WhatsApp webhook verify token", ['provided' => $verifyToken]);
            return response('Forbidden', 403);
        }

        // Handle incoming messages
        Log::info('WhatsApp Business Webhook Received:', $request->all());

        $entries = $request->input('entry', []);

        foreach ($entries as $entry) {
            // Prefer the standard location: value.metadata.phone_number_id
            $phoneNumberId = $entry['changes'][0]['value']['metadata']['phone_number_id']
                ?? $entry['id']
                ?? null;

            if (!$phoneNumberId) {
                Log::warning('No phone number ID found in webhook entry', ['entry' => $entry]);
                continue;
            }

            $societeId = $this->findSocieteByPhoneNumberId($phoneNumberId);

            if (!$societeId) {
                Log::warning("No société configuration found for phone number ID: {$phoneNumberId}");
                continue;
            }

            DatabaseHelper::Config($societeId);
            Config::set('broadcasting.default', 'pusher_3');

            // Check if webhooks are enabled for this configuration
            $webhookEnabled = $this->isWhatsAppWebhookEnabledForPhone($phoneNumberId);

            if (!$webhookEnabled) {
                Log::info("Webhook received but webhooks are disabled for société {$societeId}");
                continue;
            }

            $changes = $entry['changes'] ?? [];

            foreach ($changes as $change) {
                $this->processWhatsAppWebhookChange($change, $societeId);
            }
        }

        return response()->json(['status' => 'success']);
    }*/

    private function getAllWhatsAppWebhookTokens()
    {
        $tokens = [];

        try {
            // IDENTICAL base reset as Facebook controller
            config(['database.connections.temp' => config('database.connections.mysql')]);
            DB::purge('temp');
            DB::reconnect('temp');

            $societes = Societe::all();
            Log::info("Checking " . $societes->count() . " sociétés for WhatsApp tokens");

            foreach ($societes as $societe) {
                try {
                    // IDENTICAL switching logic
                    $this->configureDatabaseForSociete($societe);

                    if (Schema::connection('temp')->hasTable('whatsapp_configurations')) {
                        $waTokens = DB::connection('temp')
                            ->table('whatsapp_configurations')
                            ->whereNotNull('webhook_verify_token')
                            ->whereNull('deleted_at')
                            ->pluck('webhook_verify_token')
                            ->toArray();

                        $tokens = array_merge($tokens, $waTokens);
                    }
                } catch (\Exception $e) {
                    Log::warning("Error checking société {$societe->id} for WhatsApp tokens: " . $e->getMessage());
                    continue;
                }
            }

            $tokens = array_unique(array_filter($tokens));
            Log::info("Total unique WhatsApp tokens found: " . count($tokens));
            return $tokens;

        } catch (\Exception $e) {
            Log::error('Error getting WhatsApp webhook tokens: ' . $e->getMessage());
            return [];
        }
    }

    private function findSocieteByPhoneNumberId($phoneNumberId)
    {
        try {
            // Reset temp connection to base mysql before scanning tenants (mirrors token search)
            config(['database.connections.temp' => config('database.connections.mysql')]);
            DB::purge('temp');
            DB::reconnect('temp');

            $societes = Societe::all();

            foreach ($societes as $societe) {
                try {
                    // Switch to this société's tenant DB using the same logic as token search
                    $this->configureDatabaseForSociete($societe);

                    if (Schema::connection('temp')->hasTable('whatsapp_configurations')) {
                        $config = DB::connection('temp')
                            ->table('whatsapp_configurations')
                            ->where('phone_number_id', $phoneNumberId)
                            ->whereNull('deleted_at')
                            ->first();

                        if ($config) {
                            return $societe->id;
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning("Error checking société {$societe->id} for phone number: " . $e->getMessage());
                    continue;
                }
            }
        } catch (\Exception $e) {
            Log::error('Error finding société by phone number ID: ' . $e->getMessage());
        }

        return null;
    }

    private function isWhatsAppWebhookEnabledForPhone($phoneNumberId)
    {
        try {
            $config = DB::connection('temp')
                ->table('whatsapp_configurations')
                ->where('phone_number_id', $phoneNumberId)
                ->whereNull('deleted_at')
                ->first();

            return $config && $config->webhook_enabled;
        } catch (\Exception $e) {
            Log::error('Error checking WhatsApp webhook status: ' . $e->getMessage());
            return false;
        }
    }

    private function processWhatsAppWebhookChange($change, $societeId)
    {
        $value = $change['value'] ?? [];

        // Store webhook event
        try {
            $web = new WebhookEvent();
            $web->setConnection('temp');
            $web->platform = 'whatsapp';
            $web->type = 'whatsapp_message';
            $web->data = $change;
            $web->save();

            broadcast(new NotificationEvent(0));

            Log::info("WhatsApp webhook event saved successfully for société {$societeId}");

        } catch (\Exception $e) {
            Log::error("Error saving WhatsApp webhook event for société {$societeId}: " . $e->getMessage());
        }

        // Process incoming messages
        if (isset($value['messages'])) {
            $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;
            foreach ($value['messages'] as $message) {
                $contact = $value['contacts'][0] ?? [];
                $this->processIncomingWhatsAppMessage($message, $contact, $societeId, $phoneNumberId);
            }
        }
    }

    private function getProjetIdForPhoneNumber($phoneNumberId)
    {
        if (!$phoneNumberId) return null;
        try {
            $config = DB::connection('temp')
                ->table('whatsapp_configurations')
                ->where('phone_number_id', $phoneNumberId)
                ->whereNull('deleted_at')
                ->first();

            if (!$config) {
                return null;
            }

            // If multiple WhatsApp configs share the same projet_id, disable auto-linking
            $sameProjetCount = DB::connection('temp')
                ->table('whatsapp_configurations')
                ->where('projet_id', $config->projet_id)
                ->whereNull('deleted_at')
                ->count();

            if ($config->projet_id && $sameProjetCount > 1) {
                // Ambiguous projet mapping -> user should choose on prospect page
                return null;
            }

            return $config->projet_id;
        } catch (\Exception $e) {
            Log::error('Error retrieving projet_id for WhatsApp phone number: ' . $e->getMessage());
            return null;
        }
    }

    private function processIncomingWhatsAppMessage($message, $contact, $societeId, $phoneNumberId = null)
    {
        $from = $message['from'];
        $text = $message['text']['body'] ?? '';
        $name = $contact['profile']['name'] ?? 'Unknown';
        $messageId = $message['id'];

        Log::info("Processing WhatsApp message from: $from ($name) - Message: $text");

        // Resolve projet_id based on WhatsApp configuration for the phone number id
        $projetId = $this->getProjetIdForPhoneNumber($phoneNumberId);

        // Auto-register prospect with linked project
        $this->registerProspectFromWhatsApp($from, $text, $name, $societeId, $projetId);
    }

    private function registerProspectFromWhatsApp($phone, $message, $name, $societeId, $projetId = null)
    {
        try {
            // Check if prospect already exists
            $existingProspect = \App\Models\Prospect::on('temp')
                ->where('telephone', $phone)
                ->first();

            if (!$existingProspect) {
                \App\Http\Controllers\Api\V1\ProspectController::Store_WhatsApp(
                    $phoneNumberId,
                    $phone,
                    $message,
                    $name,
                    $societeId,
                    $projetId
                );
                Log::info("New prospect registered from WhatsApp: $phone");
            } else {
                Log::info("Existing prospect sent WhatsApp message: $phone");
            }
        } catch (\Exception $e) {
            Log::error("Error registering WhatsApp prospect: " . $e->getMessage());
        }
    }

    // Configuration management (following Facebook pattern)
    public function get_whatsapp_configurations()
    {
        if (RoleHelper::AdminSup()) {
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
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();

            try {
                // Check if table exists, create if not
                if (!Schema::connection('temp')->hasTable('whatsapp_configurations')) {
                    Schema::connection('temp')->create('whatsapp_configurations', function (Blueprint $table) {
                        $table->id();
                        $table->string('phone_number_id');
                        $table->longText('access_token');
                        $table->string('app_id')->nullable();
                        $table->string('app_secret')->nullable();
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
                    'app_id' => $request->app_id,
                    'app_secret' => $request->app_secret,
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
        if (RoleHelper::AdminSup()) {
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
        if (RoleHelper::AdminSup()) {
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

    public function delete_whatsapp_webhook($configId)
    {
        if (RoleHelper::AdminSup()) {
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
        if (RoleHelper::AdminSup()) {
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

    public function update_whatsapp_configuration(Request $request, $configId)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();

            try {
                // Validate required fields
                if (!$request->phone_number_id || !$request->access_token || !$request->projet_id) {
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
                        'app_id' => $request->app_id,
                        'app_secret' => $request->app_secret,
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

    public function delete_whatsapp_configuration($configId)
    {
        if (RoleHelper::AdminSup()) {
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

    /**
     * Configure the temp DB connection for a given société (mirrors Facebook controller)
     */
    private function configureDatabaseForSociete($societe)
    {
        try {
            $raison_sociale_concatene = $societe->raison_sociale_concatene ??
                str_replace(' ', '', $societe->raison_sociale ?? ('Societe' . $societe->id));
            $databaseName = 'Erp_' . $raison_sociale_concatene . '_' . $societe->id;

            $baseConfig = config('database.connections.mysql');
            $baseConfig['database'] = $databaseName;

            config(['database.connections.temp' => $baseConfig]);
            DB::purge('temp');
            DB::reconnect('temp');

            $actualDbName = DB::connection('temp')->getDatabaseName();
            if ($actualDbName !== $databaseName) {
                Log::warning("Database name mismatch! Expected: {$databaseName}, Actual: {$actualDbName}");
            }
        } catch (\Exception $e) {
            Log::error("Error configuring database for société {$societe->id}: " . $e->getMessage());
            throw $e;
        }
    }


    // Send WhatsApp message using Business API
    public function sendWhatsAppMessage($to, $message, $accessToken, $phoneNumberId)
    {
        try {
            $response = Http::withToken($accessToken)
                ->timeout(60)
                ->post("https://graph.facebook.com/v18.0/{$phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => $to,
                    'text' => ['body' => $message]
                ]);

            Log::info("WhatsApp Business message sent to $to: " . $response->body());
            return $response->json();
        } catch (\Exception $e) {
            Log::error("Error sending WhatsApp Business message: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
}

//1269697291014076?fields=name,phone_numbers,message_templates    827760573749958"
