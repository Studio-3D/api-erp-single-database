<?php

namespace App\Http\Controllers\Facebook_Instagram;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Config;
use Carbon\Carbon;
use App\Models\Prospect;
use App\Models\Notification;
use App\Events\NotificationEvent;
use App\Models\Societe;
use App\Models\StatutProspect;
use App\Models\WebhookEvent;
use App\Models\Source;
use App\Models\User;
use App\Enum\StatutProspectEnum;
use App\Http\Helpers\NotificationHelper;



class FacebookAdWebhookController extends Controller
{
    /**
 * ✅ Récupérer le token d'accès pour une page spécifique
 */
private function getAccessTokenForPage($pageId)
{
    try {
        // ✅ Configurer la connexion temp
        $this->configureTempConnection();

        if (Schema::connection('temp')->hasTable('facebook_configurations')) {
            $config = DB::connection('temp')
                ->table('facebook_configurations')
                ->where('page_fcb_id', $pageId)
                ->whereNull('deleted_at')
                ->first();

            if ($config && !empty($config->acces_token_page)) {
                Log::info('✅ Access token found for page', [
                    'page_id' => $pageId,
                    'token_preview' => substr($config->acces_token_page, 0, 20) . '...'
                ]);
                return $config->acces_token_page;
            }
        }

        Log::error('❌ No access token found for page: ' . $pageId);
        return null;

    } catch (\Exception $e) {
        Log::error('Error getting access token for page: ' . $e->getMessage());
        return null;
    }
}
    /**
     * Vérification du webhook (GET)
     */
    /**
 * Vérification du webhook (GET)
 */
public function verify(Request $request)
{
    $hub_challenge = $request->input('hub_challenge');
    $hub_verify_token = $request->input('hub_verify_token');

    // ✅ Get the page ID from the request (if provided)
    $pageId = $request->input('page_id') ?? $request->input('id') ?? null;

    Log::info('=========================================');
    Log::info('🔍 FACEBOOK WEBHOOK VERIFICATION');
    Log::info('=========================================');
    Log::info('📥 Request received:', [
        'method' => $request->method(),
        'url' => $request->fullUrl(),
        'query' => $request->all(),
        'page_id' => $pageId
    ]);

    try {
        // ✅ Configure temp connection
        $this->configureTempConnection();

        // ✅ Get the verify token from database
        $verify_token = $this->getVerifyTokenFromConfig($pageId);

        Log::info('🔑 Token from config: ' . ($verify_token ? 'Found' : 'Not found'));
        Log::info('🔑 Received token: ' . $hub_verify_token);

        if ($verify_token && $hub_verify_token === $verify_token) {
            Log::info('✅ Webhook verify success');
            Log::info('=========================================');
            return response($hub_challenge, 200)->header('Content-Type', 'text/plain');
        }

        Log::warning('❌ Webhook verify failed');
        Log::info('=========================================');
        return response('Invalid verification token', 403);

    } catch (\Exception $e) {
        Log::error('❌ Error during webhook verification: ' . $e->getMessage());
        Log::error('Trace: ' . $e->getTraceAsString());
        return response('Verification error', 500);
    }
}

/**
 * ✅ Get the verify token from Facebook configuration
 */
private function getVerifyTokenFromConfig($pageId = null)
{
    try {
        $this->configureTempConnection();

        // ✅ Check if table exists
        if (!Schema::connection('temp')->hasTable('facebook_configurations')) {
            Log::warning('⚠️ Table facebook_configurations not found');
            return null;
        }

        $query = DB::connection('temp')
            ->table('facebook_configurations')
            ->whereNull('deleted_at');

        // ✅ If page ID is provided, try to get specific config
        if ($pageId) {
            $query->where('page_fcb_id', $pageId);
        }

        $config = $query->first();

        if ($config) {
            Log::info('✅ Found configuration', [
                'page_fcb_id' => $config->page_fcb_id ?? 'N/A',
                'has_webhook_verify_token' => !empty($config->webhook_verify_token)
            ]);

            // ✅ Return the webhook_verify_token from the config
            if (!empty($config->webhook_verify_token)) {
                return $config->webhook_verify_token;
            }

            // ✅ Fallback: check if there's a general verify token column
            if (!empty($config->verify_token)) {
                return $config->verify_token;
            }
        }

        // ✅ If no config found for specific page, get any active config
        if (!$config && !$pageId) {
            $anyConfig = DB::connection('temp')
                ->table('facebook_configurations')
                ->whereNull('deleted_at')
                ->first();

            if ($anyConfig && !empty($anyConfig->webhook_verify_token)) {
                Log::info('✅ Using any configuration token');
                return $anyConfig->webhook_verify_token;
            }
        }

        Log::warning('⚠️ No verify token found in database configuration');
        return null;

    } catch (\Exception $e) {
        Log::error('Error getting verify token from config: ' . $e->getMessage());
        return null;
    }
}

    /**
     * Traitement des données du webhook (POST)
     */
   public function handle(Request $request)
{
    /*Log::info('=========================================');
    Log::info('📨 WEBHOOK RECEIVED');
    Log::info('=========================================');
    Log::info('📦 Full Payload:', $request->all());*/

    $data = $request->all();

    if (!isset($data['entry']) || empty($data['entry'])) {
        Log::warning('⚠️ No entry found in webhook');
        return response('No entry found', 200);
    }

    foreach ($data['entry'] as $index => $entry) {
        $page_id = $entry['id'] ?? 'unknown';
        Log::info("📄 Entry #{$index} - Page ID: {$page_id}");

        // ✅ Détecter la société pour cette page
        $societeId = $this->findSocieteByPageId($page_id);
        if ($societeId) {
            Log::info('🏢 Société detected', ['societe_id' => $societeId]);
        }

        // ✅ Gérer les messages Facebook Messenger
        if (isset($entry['messaging'])) {
            foreach ($entry['messaging'] as $messaging) {
                Log::info('📩 Facebook messaging event detected');
                $projet_id = $this->getProjetIdFromPageId($page_id);
                $this->handleFacebookMessages($messaging, $societeId, $page_id, $projet_id);
            }
        }

        // ✅ Gérer les changements (feed, leadgen, etc.)
        if (isset($entry['changes'])) {
            foreach ($entry['changes'] as $changeIndex => $change) {
                $field = $change['field'] ?? 'unknown';
                Log::info("  🔄 Change #{$changeIndex} - Field: {$field}");

                switch ($field) {
                    case 'leadgen':
                        $this->handleLeadGen($change, $page_id);
                        break;
                   /* case 'feed':
                        $this->handleFeedComment($change);
                        break;*/
                    default:
                        Log::info("  ℹ️ Unhandled field: {$field}", $change);
                        break;
                }
            }
        }
    }

    Log::info('✅ Webhook processed successfully');
    Log::info('=========================================');

    return response('Webhook processed successfully', 200);
}

    /**
     * ✅ Configurer la connexion temp pour la base de données
     * (Copié depuis l'ancien contrôleur)
     */
    private function configureTempConnection()
    {
        try {
            $databaseName = env('DB_DATABASE');

            $baseConfig = config('database.connections.mysql');
            $baseConfig['database'] = $databaseName;

            config(['database.connections.temp' => $baseConfig]);

            DB::purge('temp');
            DB::reconnect('temp');

            Log::info('✅ Temp connection configured successfully');
            return true;

        } catch (\Exception $e) {
            Log::error('❌ Failed to configure temp connection: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * ✅ Configurer la base de données pour une société spécifique
     * (Copié depuis l'ancien contrôleur)
     */
    private function configureDatabaseForSociete($societeId)
    {
        try {
            $databaseName = env('DB_DATABASE');

            Log::info("Configuring database connection for société {$societeId}: {$databaseName}");

            $baseConfig = config('database.connections.mysql');
            $baseConfig['database'] = $databaseName;

            config(['database.connections.temp' => $baseConfig]);

            DB::purge('temp');
            DB::reconnect('temp');

            $actualDbName = DB::connection('temp')->getDatabaseName();
            Log::info("Successfully connected to database: {$actualDbName}");

        } catch (\Exception $e) {
            Log::error("Error configuring database for société {$societeId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * ✅ Trouver la société par page ID
     * (Copié depuis l'ancien contrôleur)
     */
    private function findSocieteByPageId($pageId)
    {
        try {
            // ✅ Configurer la connexion temp d'abord
            $this->configureTempConnection();

            $societes = Societe::all();
            Log::info("Searching for page ID: {$pageId} across " . $societes->count() . " sociétés");

            foreach ($societes as $societe) {
                try {
                    $this->configureDatabaseForSociete($societe->id);

                    if (Schema::connection('temp')->hasTable('facebook_configurations')) {
                        $facebookMatch = DB::connection('temp')
                            ->table('facebook_configurations')
                            ->where('page_fcb_id', $pageId)
                            ->whereNull('deleted_at')
                            ->exists();

                        if ($facebookMatch) {
                            Log::info("MATCH FOUND! Page ID '{$pageId}' found in société {$societe->id}");
                            return $societe->id;
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning("Error checking société {$societe->id}: " . $e->getMessage());
                    continue;
                }
            }

            Log::warning("No société found for page ID: {$pageId}");
            return null;

        } catch (\Exception $e) {
            Log::error("Error finding société for page ID {$pageId}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * ✅ Récupérer le projet_id à partir du page_id
     * (Version améliorée avec la logique de l'ancien contrôleur)
     */
/**
 * ✅ Récupérer le projet_id à partir du page_id
 */
private function getProjetIdFromPageId($pageId)
{
    try {
        // ✅ Configurer la connexion temp
        $this->configureTempConnection();

        if (!$pageId) {
            Log::warning('⚠️ No page ID provided');
            return 2; // Fallback
        }

        if (!Schema::connection('temp')->hasTable('facebook_configurations')) {
            Log::warning('Table facebook_configurations not found in temp connection');

            // ✅ Fallback: essayer de trouver dans la base principale
            if (Schema::hasTable('facebook_configurations')) {
                $config = DB::table('facebook_configurations')
                    ->where('page_fcb_id', $pageId)
                    ->whereNull('deleted_at')
                    ->first();

                if ($config) {
                    Log::info('✅ Project found in main database', [
                        'page_id' => $pageId,
                        'projet_id' => $config->projet_id
                    ]);
                    return $config->projet_id;
                }
            }

            // ✅ Fallback: projet_id 2 (votre configuration actuelle)
            return 2;
        }

        $config = DB::connection('temp')
            ->table('facebook_configurations')
            ->where('page_fcb_id', $pageId)
            ->whereNull('deleted_at')
            ->first();

        if ($config) {
            Log::info('✅ Project found for page ID', [
                'page_id' => $pageId,
                'projet_id' => $config->projet_id,
                'page_fcb_id' => $config->page_fcb_id
            ]);
            return $config->projet_id;
        }

        // ✅ Si page non trouvée, essayer de trouver par le page_id complet (format: pageId_postId)
        if (strpos($pageId, '_') !== false) {
            $parts = explode('_', $pageId);
            $cleanPageId = $parts[0];

            $config = DB::connection('temp')
                ->table('facebook_configurations')
                ->where('page_fcb_id', $cleanPageId)
                ->whereNull('deleted_at')
                ->first();

            if ($config) {
                Log::info('✅ Project found for extracted page ID', [
                    'page_id' => $pageId,
                    'clean_page_id' => $cleanPageId,
                    'projet_id' => $config->projet_id
                ]);
                return $config->projet_id;
            }
        }

        Log::warning('⚠️ No project found for page ID, using default: ' . $pageId);
        return 2;

    } catch (\Exception $e) {
        Log::error('Error getting project ID: ' . $e->getMessage());
        return 2;
    }
}
    /**
 * ✅ Récupérer le projet_id à partir du page_id
 */

    /**
     * Traiter un nouveau lead - Version avec stockage
     */
   private function handleLeadGen($change, $pageId = null)
    {
    try {
        // ✅ Configurer la connexion temp
        $this->configureTempConnection();

        $lead_id = $change['value']['leadgen_id'] ?? null;

        if (!$lead_id) {
            Log::warning('⚠️ Lead ID not found in webhook');
            return;
        }

        Log::info('=========================================');
        Log::info('🎯 LEAD RECEIVED');
        Log::info('=========================================');
        Log::info('📌 Lead ID: ' . $lead_id);
        Log::info('📌 Page ID: ' . $pageId);

        // ✅ Récupérer le token d'accès pour cette page spécifique
        $access_token = $this->getAccessTokenForPage($pageId);

        if (!$access_token) {
            Log::error('❌ No access token available for page: ' . $pageId);
            return;
        }

        // Récupérer les détails du lead
        $url = "https://graph.facebook.com/v22.0/{$lead_id}";
        $url .= "?fields=id,created_time,field_data,ad_id,ad_name,form_id";

        Log::info('🔗 API Call: ' . $url);

        $response = Http::withToken($access_token)->get($url);
            if ($response->successful()) {
                $leadData = $response->json();

                Log::info('✅ LEAD DETAILS RETRIEVED:');
                Log::info(json_encode($leadData, JSON_PRETTY_PRINT));

                // Extraire tous les champs du formulaire
                $field_data = $leadData['field_data'] ?? [];
                $lead_info = [];

                foreach ($field_data as $field) {
                    $field_name = $field['name'] ?? 'unknown';
                    $field_value = $field['values'][0] ?? 'empty';
                    Log::info("  - {$field_name}: {$field_value}");
                    $lead_info[$field_name] = $field_value;
                }

                // ✅ Récupérer le projet_id
                $projet_id = $this->getProjetIdFromPageId($pageId);

                // Extraire les informations du lead
                $full_name = $lead_info['full_name'] ?? null;
                $email = $lead_info['email'] ?? null;
                $phone = $lead_info['phone_number'] ?? null;
                $type_bien = $lead_info['quel_type_de_bien_vous_intéresse_?'] ?? null;
                $budget = $lead_info['votre_budget_approximatif_?'] ?? null;
                $residence = $lead_info['vous_êtes_:'] ?? null;

                // ✅ Vérifier si le prospect existe déjà par email ou téléphone
                $existingProspect = $this->findExistingProspect($email, $phone, $projet_id);

                if ($existingProspect) {
                    Log::info('📝 Updating existing prospect', ['id' => $existingProspect->id]);
                    $prospect = $this->updateProspect($existingProspect, [
                        'full_name' => $full_name,
                        'email' => $email,
                        'phone' => $phone,
                        'type_bien' => $type_bien,
                        'budget' => $budget,
                        'residence' => $residence,
                        'facebook_lead_id' => $leadData['id'],
                        'ad_id' => $leadData['ad_id'] ?? null,
                        'ad_name' => $leadData['ad_name'] ?? null,
                        'form_id' => $leadData['form_id'] ?? null,
                    ]);

                } else {
                    Log::info('📝 Creating new prospect');
                    $prospect = $this->createProspect([
                        'full_name' => $full_name,
                        'email' => $email,
                        'phone' => $phone,
                        'type_bien' => $type_bien,
                        'budget' => $budget,
                        'residence' => $residence,
                        'facebook_lead_id' => $leadData['id'],
                        'ad_id' => $leadData['ad_id'] ?? null,
                        'ad_name' => $leadData['ad_name'] ?? null,
                        'form_id' => $leadData['form_id'] ?? null,
                        'projet_id' => $projet_id,
                        'origin' => 'facebook',
                    ]);
                    $this->createProspectStatus($prospect->id, $projet_id);
                }



                // Log des informations clés
                Log::info('=========================================');
                Log::info('📋 RÉSUMÉ DU LEAD:');
                Log::info('=========================================');
                Log::info('👤 Nom complet: ' . ($full_name ?? 'Non fourni'));
                Log::info('📧 Email: ' . ($email ?? 'Non fourni'));
                Log::info('📞 Téléphone: ' . ($phone ?? 'Non fourni'));
                Log::info('🏠 Type de bien: ' . ($type_bien ?? 'Non fourni'));
                Log::info('💰 Budget: ' . ($budget ?? 'Non fourni'));
                Log::info('📍 Résidence: ' . ($residence ?? 'Non fourni'));
                Log::info('🆔 Ad ID: ' . ($leadData['ad_id'] ?? 'Non fourni'));
                Log::info('📢 Ad Name: ' . ($leadData['ad_name'] ?? 'Non fourni'));
                Log::info('📝 Form ID: ' . ($leadData['form_id'] ?? 'Non fourni'));

                // ✅ Créer une notification pour les commerciaux
                //$this->createLeadNotification($prospect->id,$full_name, $email, $phone, $projet_id);

                Log::info('✅ Lead processed and saved successfully');

            } else {
                Log::error('❌ Failed to fetch lead details');
                Log::error('Status: ' . $response->status());
                Log::error('Response: ' . $response->body());
            }

            Log::info('=========================================');

        } catch (\Exception $e) {
            Log::error('❌ Error processing lead');
            Log::error('Message: ' . $e->getMessage());
            Log::error('Trace: ' . $e->getTraceAsString());
        }
    }

    /**
     * ✅ Créer un nouveau prospect avec toutes les données
     */
    private function createProspect($data)
    {
        try {
            // ✅ Configurer la connexion temp
            $this->configureTempConnection();
         // Get source ID - using whereIn for better readability
            $sourceId = null;
            $source = Source::on('temp')
                ->whereIn('source', ['Facebook', 'facebook'])
                ->first();

            if ($source) {
                $sourceId = $source->id;
            }
            $prospect = new Prospect();
            $prospect->setConnection('temp');

            // Champs obligatoires
            $prospect->nom = $data['full_name'] ?? 'Prospect Facebook';
            $prospect->projet_id = $data['projet_id'];
            $prospect->origin = 'facebook';  // ✅ Correspond à l'enum de la table

            // Champs optionnels
            $prospect->prenom = $data['prenom'] ?? null;
            $prospect->email = $data['email'] ?? null;
            $prospect->telephone = $data['phone'] ?? null;
            $prospect->telephone_num2 = $data['phone2'] ?? null;
            $prospect->message = $data['message'] ?? null;
            $prospect->ville = $data['ville'] ?? null;

            // Champs Facebook spécifiques
            $prospect->facebook_id = $data['facebook_id'] ?? null;
            $prospect->facebook_lead_id = $data['facebook_lead_id'] ?? null;
            $prospect->facebook_ad_id = $data['ad_id'] ?? null;
            $prospect->facebook_ad_name = $data['ad_name'] ?? null;
            $prospect->facebook_form_id = $data['form_id'] ?? null;

            // Champs personnalisés
            if (isset($data['type_bien'])) {
                $prospect->type_bien = $data['type_bien'];
            }
            if (isset($data['budget'])) {
                $prospect->budget = $data['budget'];
            }
            if (isset($data['residence'])) {
                $prospect->residence = $data['residence'];
            }


            // Statut par défaut
            $prospect->source = $sourceId;
            $prospect->notifie = 0;

            // Date de création
            $prospect->created_at = now();
            $prospect->updated_at = now();

            $prospect->save();

            Log::info('✅ Prospect created successfully', [
                'id' => $prospect->id,
                'nom' => $prospect->nom,
                'origin' => $prospect->origin
            ]);

            return $prospect;

        } catch (\Exception $e) {
            Log::error('Error creating prospect: ' . $e->getMessage());
            Log::error('Trace: ' . $e->getTraceAsString());
            return null;
        }
    }
private function createProspectStatus($prospectId, $projetId)
{
    try {
        // ✅ Configurer la connexion temp
        $this->configureTempConnection();

        // Vérifier si un statut existe déjà pour ce prospect
        $existingStatus = StatutProspect::on('temp')
            ->where('prospect_id', $prospectId)
            ->whereNull('deleted_at')
            ->first();

        if ($existingStatus) {
            Log::info('⚠️ Status already exists for prospect', ['prospect_id' => $prospectId]);
            return $existingStatus;
        }

        /* Créer un nouveau statut "En attente" (statut = '0')
        $statutProspect = new StatutProspect();
        $statutProspect->setConnection('temp');
        $statutProspect->prospect_id = $prospectId;
        $statutProspect->statut = '0'; // ✅ En attente
        $statutProspect->date_traitement = Carbon::now();
        $statutProspect->user_id_traite = null;
        $statutProspect->commentaire = 'Prospect créé par Facebook Ads';
        $statutProspect->type_traitement_rdv_relance = 0; // En attente
        $statutProspect->created_at = now();
        $statutProspect->updated_at = now();
        $statutProspect->save();

        Log::info('✅ Statut prospect created successfully', [
            'prospect_id' => $prospectId,
            'statut' => '0 (En attente)',
            'statut_id' => $statutProspect->id,
            'commentaire' => 'Prospect créé par Facebook Ads'
        ]);*/

        // ✅ AUTOMATIC ASSIGNMENT: Affecter le prospect au commercial avec le moins de prospects
        $assignmentResult = $this->autoAssignSingleProspect($prospectId, $projetId);

        Log::info('📊 Auto-assignment result', [
            'prospect_id' => $prospectId,
            'success' => $assignmentResult
        ]);

        return $statutProspect;

    } catch (\Exception $e) {
        Log::error('Error creating prospect status: ' . $e->getMessage());
        Log::error('Trace: ' . $e->getTraceAsString());
        return null;
    }
}

/**
 * Auto-assign a single prospect to the commercial with the least prospects
 */
/**
 * Auto-assign a single prospect to the commercial with the least prospects
 */
/**
 * Auto-assign a single prospect to the commercial with the least prospects
 */
/**
 * Auto-assign a single prospect to the commercial with the least prospects
 */
/**
 * Auto-assign a single prospect to the commercial with the least prospects
 */
private function autoAssignSingleProspect($prospectId, $projetId)
{
    try {
        Log::info('🔄 Starting auto-assignment for prospect', [
            'prospect_id' => $prospectId,
            'projet_id' => $projetId
        ]);

        // ✅ Configurer la connexion temp
        $this->configureTempConnection();

        // Get prospect
        $prospect = Prospect::on('temp')->find($prospectId);
        if (!$prospect) {
            Log::error('❌ Prospect not found for auto-assignment', ['prospect_id' => $prospectId]);
            return false;
        }

        // Get all active commercials (role = 3) for this project
        $commercials = User::on('temp')
            ->where(function($query) use ($projetId) {
                $query->whereHas('projets', function($q) use ($projetId) {
                    $q->where('projet_id', $projetId);
                });
            })
            ->where('role', 3)
            ->where('is_actif', 1)
            ->orderBy('id')
            ->whereNull('deleted_at')
            ->get();

        if ($commercials->isEmpty()) {
            Log::warning('⚠️ No active commercials found', ['projet_id' => $projetId]);
            return false;
        }

        // ============================================
        // ✅ LOGIQUE SIMPLIFIÉE: SEULEMENT last_affected
        // ============================================

        // If only one commercial, assign to them
        if ($commercials->count() === 1) {
            $targetCommercial = $commercials->first();
            Log::info('✅ Only one commercial found', [
                'commercial_id' => $targetCommercial->id,
                'name' => $targetCommercial->name . ' ' . $targetCommercial->prenom
            ]);
        } else {
            // ✅ Step 1: Find commercial with last_affected = 0
            $targetCommercial = null;

            foreach ($commercials as $commercial) {
                if ($commercial->last_affected == 0) {
                    $targetCommercial = $commercial;
                    Log::info('✅ Found commercial with last_affected = 0', [
                        'commercial_id' => $commercial->id,
                        'name' => $commercial->name . ' ' . $commercial->prenom
                    ]);
                    break;
                }
            }

            // ✅ Step 2: If all have last_affected = 1, take the first one
            if (!$targetCommercial) {
                $targetCommercial = $commercials->first();
                Log::info('🔄 All commercials have last_affected = 1, taking first', [
                    'commercial_id' => $targetCommercial->id,
                    'name' => $targetCommercial->name . ' ' . $targetCommercial->prenom
                ]);
            }
        }

        // Get system user (Admin)
        $systemUser = User::on('temp')->where('role', 1)->first();

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

            Log::info('✅ Prospect updated with commercial', [
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
            $statutProspect->commentaire = 'Prospect affecté automatiquement après création via Facebook Ads';
            $statutProspect->type_traitement_rdv_relance = 0;
            $statutProspect->created_at = now();
            $statutProspect->updated_at = now();
            $statutProspect->save();

            Log::info('✅ Status "Affecté" created', [
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

            Log::info('✅ Commercial counters updated', [
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
                ->update(['last_affected' => 0]);

            Log::info('✅ Reset last_affected = 0 for other commercials');

            // ✅ 6. COMMIT transaction
            DB::connection('temp')->commit();

            Log::info('✅ Transaction committed successfully');

            // ✅ 7. Send notification (OUTSIDE transaction)
            $this->sendAffectationNotification($newCommercialId, $prospectId, $projetId);

            // ✅ 8. Verify data was saved
            $verifyProspect = Prospect::on('temp')->find($prospectId);
            $verifyStatus = StatutProspect::on('temp')
                ->where('prospect_id', $prospectId)
                ->orderBy('id', 'desc')
                ->first();
            $verifyUser = User::on('temp')->find($newCommercialId);

            Log::info('🔍 Verification after commit', [
                'prospect_commercial_id' => $verifyProspect ? $verifyProspect->commercial_affecte : null,
                'status_id' => $verifyStatus ? $verifyStatus->id : null,
                'status_statut' => $verifyStatus ? $verifyStatus->statut : null,
                'user_nb_prospects' => $verifyUser ? $verifyUser->nb_prospects : null,
                'user_last_affected' => $verifyUser ? $verifyUser->last_affected : null
            ]);

            Log::info('✅ Auto-assignment completed successfully', [
                'prospect_id' => $prospectId,
                'commercial_id' => $newCommercialId,
                'commercial_name' => $targetCommercial->name . ' ' . $targetCommercial->prenom
            ]);

            return true;

        } catch (\Exception $e) {
            DB::connection('temp')->rollBack();
            Log::error('❌ Auto-assignment transaction failed: ' . $e->getMessage());
            Log::error('Trace: ' . $e->getTraceAsString());
            return false;
        }

    } catch (\Exception $e) {
        Log::error('❌ Auto-assignment failed: ' . $e->getMessage());
        Log::error('Trace: ' . $e->getTraceAsString());
        return false;
    }
}
/**
 * Send notification to the assigned commercial
 */
/**
 * Send notification to the assigned commercial
 */
private function sendAffectationNotification($commercialId, $prospectId, $projetId)
{
    try {
        $this->configureTempConnection();

        // ✅ Get user with user_id_origin
        $commercial = User::on('temp')->find($commercialId);

        if (!$commercial) {
            Log::warning('Commercial not found for notification', ['commercial_id' => $commercialId]);
            return;
        }

        // ✅ Get prospect for better description
        $prospect = Prospect::on('temp')->find($prospectId);
        $prospectName = $prospect ? trim(($prospect->nom ?? '') . ' ' . ($prospect->prenom ?? '')) : 'Nouveau prospect';
        if (empty($prospectName)) {
            $prospectName = 'Nouveau prospect';
        }

        // ✅ Prepare notification data
        $data_notif = [
            'lien'        => '/crm/prospects/' . $prospectId,
            'date'        => Carbon::now(),
            'type'        => 53, // Prospect assignment type
            'user_id'     => $commercial->user_id_origin, // Use user_id_origin
            'description' => "Un nouveau prospect '{$prospectName}' vous a été affecté via Facebook Ads",
            'projet_id'   => $projetId,
            'prospect_id' => $prospectId,
        ];

        // ✅ Store notification
        $notif_helper = new NotificationHelper();
        $notif_helper->storeNotification(new Request($data_notif));

        // ✅ Broadcast event (wrap in try-catch to avoid breaking the flow)
        try {
            Config::set('broadcasting.default', 'pusher_notify');
            broadcast(new NotificationEvent($commercial->user_id_origin));
        } catch (\Exception $e) {
            Log::warning('Broadcast failed but notification was stored: ' . $e->getMessage());
        }

        Log::info('✅ Affectation notification sent', [
            'commercial_id' => $commercialId,
            'prospect_id' => $prospectId,
            'user_id_origin' => $commercial->user_id_origin
        ]);

    } catch (\Exception $e) {
        Log::error('Erreur envoi notification affectation: ' . $e->getMessage());
        Log::error('Trace: ' . $e->getTraceAsString());
    }
}


    /**
     * Trouver un prospect existant par email ou téléphone
     */
     private function findExistingProspect($email, $phone, $projet_id)
    {
        try {
            // ✅ Configurer la connexion temp
            $this->configureTempConnection();

            if (empty($email) && empty($phone)) {
                return null;
            }

            $query = Prospect::on('temp')
                ->where('projet_id', $projet_id)
                ->whereNull('deleted_at');

            if ($email) {
                $query->where('email', $email);
            }

            if ($phone) {
                $query->orWhere('telephone', $phone);
                $query->orWhere('telephone_num2', $phone);
            }

            return $query->first();

        } catch (\Exception $e) {
            Log::error('Error finding existing prospect: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Créer un nouveau prospect
     */


    /**
     * Mettre à jour un prospect existant
     */
   private function updateProspect($prospect, $data)
    {
        try {
            // ✅ Configurer la connexion temp
            $this->configureTempConnection();

            if (isset($data['full_name']) && !empty($data['full_name'])) {
                $prospect->nom = $data['full_name'];
            }
            if (isset($data['email']) && !empty($data['email'])) {
                $prospect->email = $data['email'];
            }
            if (isset($data['phone']) && !empty($data['phone'])) {
                $prospect->telephone = $data['phone'];
            }
            if (isset($data['phone2']) && !empty($data['phone2'])) {
                $prospect->telephone_num2 = $data['phone2'];
            }
            if (isset($data['message']) && !empty($data['message'])) {
                $prospect->message = $data['message'];
            }
            if (isset($data['ville']) && !empty($data['ville'])) {
                $prospect->ville = $data['ville'];
            }

            // Champs personnalisés
            if (isset($data['type_bien'])) {
                $prospect->type_bien = $data['type_bien'];
            }
            if (isset($data['budget'])) {
                $prospect->budget = $data['budget'];
            }
            if (isset($data['residence'])) {
                $prospect->residence = $data['residence'];
            }

            // Mettre à jour les champs Facebook
            if (isset($data['facebook_lead_id'])) {
                $prospect->facebook_lead_id = $data['facebook_lead_id'];
            }
            if (isset($data['ad_id'])) {
                $prospect->facebook_ad_id = $data['ad_id'];
            }
            if (isset($data['ad_name'])) {
                $prospect->facebook_ad_name = $data['ad_name'];
            }
            if (isset($data['form_id'])) {
                $prospect->facebook_form_id = $data['form_id'];
            }

            $prospect->updated_at = now();
            $prospect->save();

            Log::info('✅ Prospect updated successfully', ['id' => $prospect->id]);

            return $prospect;

        } catch (\Exception $e) {
            Log::error('Error updating prospect: ' . $e->getMessage());
            return null;
        }
    }


    /**
     * Créer une notification pour les commerciaux
     */
     private function createLeadNotification($prospect_id,$full_name, $email, $phone, $projet_id)
    {
        try {
            // ✅ Configurer la connexion temp
            $this->configureTempConnection();
            //📋 Nouveau lead Facebook Ads
            $description = "";
            if ($full_name) {
                $description .= "{$full_name}";
            }
             /* if ($email) {
                $description .= " (Email: {$email})";
            }*/
            if ($phone) {
                $description .= " (Tél: {$phone})";
            }

            $notification = new Notification();
            $notification->setConnection('temp');

            $notification->date = now()->format('Y-m-d H:i:s');
            $notification->type =99; // Type pour leads Facebook Ads
            $notification->description_type = $description;
            $notification->lien = '/crm/prospects/'.$prospect_id;
            $notification->role = \App\Enum\RoleEnum::ADMIN->value;
            $notification->projet_id = $projet_id;
            $notification->save();

            // Broadcast la notification
            Config::set('broadcasting.default', 'pusher_notify');
            broadcast(new NotificationEvent($notification->id));

            Log::info('✅ Lead notification created', ['notification_id' => $notification->id]);

        } catch (\Exception $e) {
            Log::error('Error creating lead notification: ' . $e->getMessage());
        }
    }


    /**
     * Traiter un commentaire - Logging only
     */
   /**
     * Traiter un commentaire - Version complète avec création de prospect
     */
    private function handleFeedComment($change)
    {
        try {
            $value = $change['value'] ?? [];
            $item = $value['item'] ?? null;
            $verb = $value['verb'] ?? null;

            // ✅ Ignorer les suppressions
            if ($verb === 'remove') {
                Log::info('Ignoring removal event');
                return;
            }

            // ✅ Récupérer le projet_id
            $pageId = $value['post_id'] ?? null;
            if ($pageId) {
                // Extraire le page_id du post_id (format: pageId_postId)
                $pageIdParts = explode('_', $pageId);
                $pageId = $pageIdParts[0] ?? null;
            }
            $projet_id = $this->getProjetIdFromPageId($pageId);

            switch ($item) {
                case 'reaction':
                    $this->handleFacebookReaction($value, $projet_id);
                    break;

                case 'comment':
                    $this->handleFacebookComment($value, $projet_id);
                    break;

                case 'post':
                    $this->handleFacebookPost($value, $projet_id);
                    break;

                default:
                    Log::info('Unknown feed item type:', ['item' => $item, 'data' => $value]);
                    break;
            }

        } catch (\Exception $e) {
            Log::error('❌ Error handling feed event: ' . $e->getMessage());
            Log::error('Trace: ' . $e->getTraceAsString());
        }
    }

    /**
     * ✅ Gérer une réaction Facebook (like, love, wow, etc.)
     */
    private function handleFacebookReaction($data, $projet_id)
    {
        Log::info('👍 New Reaction on Facebook Page:', $data);

        try {
            // Extraire les informations de la réaction
            $reactionType = $data['reaction_type'] ?? 'unknown';
            $userName = $data['from']['name'] ?? 'Utilisateur inconnu';
            $userId = $data['from']['id'] ?? null;
            $postId = $data['post_id'] ?? null;
            $verb = $data['verb'] ?? 'add';

            /* ✅ Créer ou mettre à jour le prospect
            if ($userId && $projet_id) {
                $this->createOrUpdateProspectFromFacebook([
                    'name' => $userName,
                    'facebook_id' => $userId,
                    'projet_id' => $projet_id,
                    'source' => 'facebook_reaction',
                    'interaction_type' => 'reaction',
                    'post_id' => $postId,
                ]);
            }*/

            // ✅ Créer une notification uniquement pour les nouvelles réactions
            if ($verb === 'add') {
                $description = match($reactionType) {
                    'like' => "👍 {$userName} a aimé votre publication Facebook",
                    'love' => "❤️ {$userName} adore votre publication Facebook",
                    'wow' => "😮 {$userName} trouve votre publication Facebook impressionnante",
                    'haha' => "😂 {$userName} trouve votre publication Facebook amusante",
                    'sad' => "😢 {$userName} trouve votre publication Facebook triste",
                    'angry' => "😡 {$userName} est en colère contre votre publication Facebook",
                    'care' => "🤗 {$userName} se soucie de votre publication Facebook",
                    default => "{$userName} a réagi à votre publication Facebook"
                };

                $postLink = $postId ? "https://www.facebook.com/{$postId}" : null;
                $this->createFacebookNotification($description, $postLink, \App\Enum\TypeNotificationEnum::FacebookReaction->value, $projet_id);
            }

        } catch (\Exception $e) {
            Log::error('Error handling Facebook reaction: ' . $e->getMessage());
        }
    }

    /**
     * ✅ Gérer un commentaire Facebook
     */
    private function handleFacebookComment($data, $projet_id)
    {
        Log::info('💬 New Comment on Facebook Page:', $data);

        try {
            // Extraire les informations du commentaire
            $userName = $data['from']['name'] ?? 'Utilisateur inconnu';
            $userId = $data['from']['id'] ?? null;
            $message = $data['message'] ?? '';
            $postId = $data['post_id'] ?? null;
            $commentId = $data['comment_id'] ?? null;

            /* ✅ Créer ou mettre à jour le prospect
            if ($userId && $projet_id) {
                $this->createOrUpdateProspectFromFacebook([
                    'name' => $userName,
                    'facebook_id' => $userId,
                    'projet_id' => $projet_id,
                    'source' => 'facebook_comment',
                    'interaction_type' => 'comment',
                    'post_id' => $postId,
                    'message' => $message,
                ]);
            }*/

            // ✅ Créer une notification
            $description = "💬 {$userName} a commenté votre publication Facebook";
            if (!empty($message)) {
                $description .= ": " . (strlen($message) > 50 ? substr($message, 0, 50) . '...' : $message);
            }

            $link = null;
            if ($postId && $commentId) {
                $link = "https://www.facebook.com/{$postId}?comment_id={$commentId}";
            } elseif ($postId) {
                $link = "https://www.facebook.com/{$postId}";
            }

            $this->createFacebookNotification($description, $link, \App\Enum\TypeNotificationEnum::FacebookComment->value, $projet_id);

        } catch (\Exception $e) {
            Log::error('Error handling Facebook comment: ' . $e->getMessage());
        }
    }

    /**
     * ✅ Gérer un nouveau post Facebook
     */
    private function handleFacebookPost($data, $projet_id)
    {
        Log::info('📝 New Post on Facebook Page:', $data);

        try {
            if (isset($data['from']['name'])) {
                $userName = $data['from']['name'];
                $userId = $data['from']['id'] ?? null;
                $message = $data['message'] ?? '';
                $postId = $data['post_id'] ?? null;

                /*✅ Créer ou mettre à jour le prospect
                if ($userId && $projet_id) {
                    $this->createOrUpdateProspectFromFacebook([
                        'name' => $userName,
                        'facebook_id' => $userId,
                        'projet_id' => $projet_id,
                        'source' => 'facebook_post',
                        'interaction_type' => 'post',
                        'post_id' => $postId,
                        'message' => $message,
                    ]);
                }*/

                // ✅ Créer une notification
                $description = !empty($message)
                    ? "📝 {$userName} a publié sur votre page : " . substr($message, 0, 50) . (strlen($message) > 50 ? '...' : '')
                    : "📝 {$userName} a publié sur votre page Facebook";

                $postLink = $postId ? "https://www.facebook.com/{$postId}" : null;
                $this->createFacebookNotification($description, $postLink, \App\Enum\TypeNotificationEnum::FacebookPublication->value, $projet_id);
            }

        } catch (\Exception $e) {
            Log::error('Error handling Facebook post: ' . $e->getMessage());
        }
    }

    /*** ✅ Créer ou mettre à jour un prospect à partir d'une interaction Facebook

    private function createOrUpdateProspectFromFacebook($data)
    {
        try {
            $this->configureTempConnection();

            $name = $data['name'] ?? 'Utilisateur Facebook';
            $facebookId = $data['facebook_id'] ?? null;
            $projet_id = $data['projet_id'] ?? null;

            if (!$facebookId || !$projet_id) {
                Log::warning('Missing facebook_id or projet_id for prospect creation');
                return null;
            }

            // Vérifier si le prospect existe déjà via facebook_id
            $prospect = Prospect::on('temp')
                ->where('facebook_id', $facebookId)
                ->where('projet_id', $projet_id)
                ->whereNull('deleted_at')
                ->first();

            if (!$prospect) {
                // Créer un nouveau prospect
                $prospect = new Prospect();
                $prospect->setConnection('temp');
                $prospect->nom = $name;
                $prospect->facebook_id = $facebookId;
                $prospect->projet_id = $projet_id;
                $prospect->origin = 'facebook';
                $prospect->notifie = 0;
                $prospect->created_at = now();
                $prospect->updated_at = now();

                if (isset($data['message'])) {
                    $prospect->message = $data['message'];
                }
                if (isset($data['post_id'])) {
                    $prospect->post_id = $data['post_id'];
                }
                if (isset($data['interaction_type'])) {
                    $prospect->interaction_type = $data['interaction_type'];
                }

                $prospect->save();

                // ✅ Créer le statut du prospect
                $this->createProspectStatus($prospect->id, $projet_id);

                Log::info('✅ New prospect created from Facebook interaction', [
                    'prospect_id' => $prospect->id,
                    'name' => $name,
                    'facebook_id' => $facebookId,
                    'interaction_type' => $data['interaction_type'] ?? 'unknown'
                ]);

                return $prospect;
            }

            // Mettre à jour le prospect existant
            if (isset($data['message']) && empty($prospect->message)) {
                $prospect->message = $data['message'];
            }
            if (isset($data['post_id']) && empty($prospect->post_id)) {
                $prospect->post_id = $data['post_id'];
            }
            if (isset($data['interaction_type'])) {
                $prospect->interaction_type = $data['interaction_type'];
            }
            $prospect->updated_at = now();
            $prospect->save();

            Log::info('✅ Prospect updated from Facebook interaction', [
                'prospect_id' => $prospect->id,
                'name' => $name,
                'facebook_id' => $facebookId
            ]);

            return $prospect;

        } catch (\Exception $e) {
            Log::error('Error creating/updating prospect from Facebook: ' . $e->getMessage());
            return null;
        }
    } */

    /**
     * ✅ Créer une notification Facebook
     */
    private function createFacebookNotification($description, $link = null, $type = null, $projet_id)
    {
        try {
            // ✅ Configurer la connexion temp
            $this->configureTempConnection();

            // Vérifier si une notification similaire existe dans les 5 dernières minutes
            $existingNotification = Notification::on('temp')
                ->where('type', $type)
                ->where('lien', $link)
                ->where('description_type', $description)
                ->where('created_at', '>=', Carbon::now()->subMinutes(5))
                ->first();

            if ($existingNotification) {
                Log::info('⚠️ Duplicate notification detected, skipping creation', [
                    'existing_id' => $existingNotification->id,
                    'type' => $type,
                    'link' => $link
                ]);
                return;
            }

            // Créer la notification
            $notification = new Notification();
            $notification->setConnection('temp');

            $notification->date = now()->format('Y-m-d H:i:s');
            $notification->type = $type;
            $notification->description_type = $description;
            $notification->lien = $link ?? 'https://www.facebook.com';
            $notification->role = \App\Enum\RoleEnum::ADMIN->value ?? 3;
            $notification->projet_id = $projet_id;
            $notification->save();

            // Broadcast la notification
            Config::set('broadcasting.default', 'pusher_notify');
            broadcast(new NotificationEvent($notification->id));

            Log::info('✅ Facebook notification created successfully', [
                'notification_id' => $notification->id,
                'type' => $type,
                'description' => $description
            ]);

        } catch (\Exception $e) {
            Log::error('Error creating Facebook notification: ' . $e->getMessage());
        }
    }

    // ============================================
    // HANDLE FACEBOOK MESSAGING
    // ============================================


    // ============================================
// HANDLE FACEBOOK MESSAGING
// ============================================

/**
 * ✅ Gérer les messages Facebook Messenger
 * (Copié depuis l'ancien contrôleur)
 */
/**
 * ✅ Gérer les messages Facebook Messenger
 */
private function handleFacebookMessages($messaging, $societeId = null, $pageId = null, $projet_id)
{
    Log::info('Processing Facebook direct message:', ['messaging' => $messaging]);

    try {
        // ✅ 1. Détecter la société à partir du page_id
        if (!$societeId && $pageId) {
            $societeId = $this->findSocieteByPageId($pageId);
            Log::info('🔍 Société detected for page', [
                'page_id' => $pageId,
                'societe_id' => $societeId
            ]);
        }

        // ✅ 2. Configurer la connexion pour cette société
        if ($societeId) {
            $this->configureDatabaseForSociete($societeId);
        } else {
            // Fallback: configurer la connexion temp par défaut
            $this->configureTempConnection();
        }

        // ✅ 3. Store webhook event avec la bonne connexion
        $web = new WebhookEvent();
        $web->setConnection('temp');
        $web->platform = 'facebook';
        $web->type = 'facebook_messaging';
        $web->data = $messaging;
        if ($pageId) {
            $web->page_id = $pageId;
        }
        $web->save();

        broadcast(new NotificationEvent(0));

        $senderId = $messaging['sender']['id'] ?? null;
        $message = $messaging['message']['text'] ?? '';
        $timestamp = $messaging['timestamp'] ?? null;
        $messageId = $messaging['mid'] ?? null;

        // Get sender name using Graph API
        $senderName = 'Utilisateur inconnu';
        if ($senderId && $pageId) {
            $senderName = $this->getFacebookUserName($senderId, $pageId);
        }

        Log::info('Extracted Facebook message details:', [
            'senderName' => $senderName,
            'senderId' => $senderId,
            'message' => $message,
            'messageId' => $messageId,
            'timestamp' => $timestamp
        ]);

        // Check if message contains a phone number
        $phoneNumber = $this->extractPhoneNumber($message);

        if ($senderId && $pageId) {
            if ($phoneNumber) {
                // Validate phone number format
                $validationResult = $this->validatePhoneNumber($phoneNumber);

                if (!$validationResult['is_valid']) {
                    $errorMessage = $validationResult['error_message'] ??
                        "❌ Format de numéro de téléphone invalide. Veuillez fournir un numéro valide (ex: 06XXXXXXXX ou +2126XXXXXXXX).";
                    $this->sendFacebookMessageFromPage($senderId, $errorMessage, $pageId);
                    Log::warning("Invalid phone number format from user", [
                        'sender_id' => $senderId,
                        'phone_number' => $phoneNumber
                    ]);
                    return;
                }

                $normalizedPhone = $validationResult['normalized'] ?? $this->normalizePhoneNumberInternational($phoneNumber, $validationResult['country'] ?? null);

                // Check if phone number already exists
                $Duplicate_Prospect = $this->isPhoneNumberDuplicate($normalizedPhone, $senderId, $projet_id);
                if ($Duplicate_Prospect != null) {
                    Log::info("Duplicate phone number detected", [
                        'sender_id' => $senderId,
                        'phone_number' => $phoneNumber,
                        'normalized' => $normalizedPhone,
                        'prospect_id' => $Duplicate_Prospect->id
                    ]);

                    $duplicateMessage = "⚠️ Ce numéro de téléphone est déjà associé à un autre compte. Veuillez fournir un numéro différent.";
                    $this->sendFacebookMessageFromPage($senderId, $duplicateMessage, $pageId);

                    $notif_helper = new NotificationHelper();
                    $req = new \Illuminate\Http\Request();
                    $notif_helper->storeNotification($req->merge([
                        'lien' => '/crm/prospects/' . $Duplicate_Prospect->id,
                        'date' => Carbon::now(),
                        'type' => 102,
                        'description' => 'Le Prospect ' . $Duplicate_Prospect->nom . ' vous a contacté sur Facebook',
                        'user_id' => null,
                        'role' => \App\Enum\RoleEnum::ADMIN->value,
                        'prospect_id' => $Duplicate_Prospect->id,
                        'projet_id' => $projet_id,
                    ]));
                    return;
                }

                // Update prospect with phone number
                $updateSuccess = $this->updateProspectWithPhoneNumber($senderName, $normalizedPhone, $societeId, $projet_id, $senderId, $message);

                if ($updateSuccess) {
                    $confirmationMessage = "✅ Merci ! Votre numéro de téléphone {$normalizedPhone} a été enregistré avec succès.";
                    $this->sendFacebookMessageFromPage($senderId, $confirmationMessage, $pageId);
                } else {
                    $errorMessage = "❌ Désolé, une erreur s'est produite. Veuillez réessayer.";
                    $this->sendFacebookMessageFromPage($senderId, $errorMessage, $pageId);
                }

            } else {
                // No phone number in message - ask for it
                if ($this->isFirstMessageFromUser($senderId)) {
                    $welcomeMessage = "Bonjour {$senderName} ! 👋\n\n" .
                        "Merci de nous avoir contactés. Pourriez-vous nous partager votre numéro de téléphone ?\n\n" .
                        "**Formats acceptés** :\n" .
                        "• **Maroc** : 06XXXXXXXX (10) ou +2126XXXXXXXX (13)\n" .
                        "• **France** : 01XXXXXXXX (10) ou +331XXXXXXXX (12)\n" .
                        "• **International** : +CodePays Numéro (9-15 chiffres)";

                    $messageSent = $this->sendFacebookMessageFromPage($senderId, $welcomeMessage, $pageId);

                    if ($messageSent) {
                        $this->markAsAskedForPhone($senderId);

                        $description = "Le prospect {$senderName} n'a pas fourni son numéro de téléphone sur Facebook.";
                        $notification = new Notification();
                        $notification->setConnection('temp');
                        $notification->date = now()->format('Y-m-d H:i:s');
                        $notification->type = \App\Enum\TypeNotificationEnum::FacebookMessage->value;
                        $notification->description_type = $description;
                        $notification->lien = "https://www.facebook.com/$pageId";
                        $notification->role = \App\Enum\RoleEnum::ADMIN->value;
                        $notification->projet_id = $projet_id;
                        $notification->save();

                        Config::set('broadcasting.default', 'pusher_notify');
                        broadcast(new NotificationEvent($notification->id));
                        Log::info("Asked user {$senderId} for phone number");
                    }
                }
            }
        }

        // Create notification for new message
        $description = "Nouveau message Facebook de {$senderName}";
        if (!empty($message)) {
            $description .= ": " . (strlen($message) > 50 ? substr($message, 0, 50) . '...' : $message);
        }

        $link = $senderId ? "https://www.facebook.com/{$pageId}" : null;
        $this->createFacebookNotification($description, $link, \App\Enum\TypeNotificationEnum::FacebookMessage->value, $projet_id);

        Log::info('Facebook message processing completed');

    } catch (\Exception $e) {
        Log::error('Error handling Facebook message: ' . $e->getMessage());
        Log::error('Stack trace: ' . $e->getTraceAsString());
    }
}

// ============================================
// FONCTIONS AUXILIAIRES
// ============================================

/**
 * ✅ Envoyer un message Facebook depuis la page
 */
private function sendFacebookMessageFromPage($recipientId, $message, $pageId)
{
    try {
        $accessToken = $this->getAccessTokenForPage($pageId);

        if (!$accessToken) {
            Log::error("No access token found for page ID: {$pageId}");
            return false;
        }

        $client = new Client(['timeout' => 30.0]);

        $response = $client->post("https://graph.facebook.com/v24.0/{$pageId}/messages", [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'recipient' => ['id' => $recipientId],
                'message' => ['text' => $message],
                'messaging_type' => 'RESPONSE'
            ],
            'query' => ['access_token' => $accessToken]
        ]);

        $responseBody = json_decode($response->getBody(), true);

        if (isset($responseBody['message_id'])) {
            Log::info("Facebook message sent successfully", [
                'page_id' => $pageId,
                'recipient_id' => $recipientId,
                'message_id' => $responseBody['message_id']
            ]);
            return true;
        } else {
            Log::error("Failed to send Facebook message", $responseBody);
            return false;
        }

    } catch (\Exception $e) {
        Log::error("Error sending Facebook message: " . $e->getMessage());
        return false;
    }
}

/**
 * ✅ Récupérer le nom d'un utilisateur Facebook
 */
private function getFacebookUserName($senderId, $pageId)
{
    try {
        $accessToken = $this->getAccessTokenForPage($pageId);

        if (!$accessToken) {
            return 'Utilisateur Facebook';
        }

        $response = Http::timeout(30)->get(
            "https://graph.facebook.com/v22.0/{$senderId}",
            [
                'fields' => 'name,first_name,last_name',
                'access_token' => $accessToken
            ]
        );

        if ($response->successful()) {
            $userData = $response->json();
            return $userData['name'] ?? $userData['first_name'] ?? 'Utilisateur Facebook';
        } else {
            return 'Utilisateur Facebook';
        }

    } catch (\Exception $e) {
        Log::error("Error getting Facebook user name: " . $e->getMessage());
        return 'Utilisateur Facebook';
    }
}

/**
 * ✅ Extraire un numéro de téléphone d'un message
 */
private function extractPhoneNumber($message)
{
    $patterns = [
        '/\+\d{10,14}\b/',
        '/00\d{10,14}\b/',
        '/(?:\+|00)?212[5-7]\d{8}\b/',
        '/(?:\+|00)?33[1-9]\d{8}\b/',
        '/(?:\+|00)?90[2-9]\d{9}\b/',
        '/(?:\+|00)?213[5-9]\d{8}\b/',
        '/0[5-7]\d{8}\b/',
        '/0[1-9](\d{2}){4}\b/',
        '/\b\d{9,15}\b/',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $message, $matches)) {
            $potentialNumber = $matches[0];
            $cleaned = preg_replace('/[^\d+]/', '', $potentialNumber);
            $digitCount = strlen(preg_replace('/[^0-9]/', '', $cleaned));
            if ($digitCount >= 9 && $digitCount <= 15) {
                return $cleaned;
            }
        }
    }
    return null;
}

/**
 * ✅ Valider un numéro de téléphone
 */
private function validatePhoneNumber($phoneNumber)
{
    $cleaned = preg_replace('/[^\d+]/', '', $phoneNumber);
    $digitCount = strlen(preg_replace('/[^0-9]/', '', $cleaned));

    if ($digitCount < 9) {
        return [
            'is_valid' => false,
            'error_message' => "❌ Numéro trop court! Minimum 9 chiffres. Vous avez fourni {$digitCount} chiffres."
        ];
    }

    if ($digitCount > 15) {
        return [
            'is_valid' => false,
            'error_message' => "❌ Numéro trop long!"
        ];
    }

    $countryPatterns = [
        'MA' => [
            'patterns' => ['/^(?:\+212|00212|212|0)([5-7]\d{8})$/', '/^[5-7]\d{8}$/'],
            'name' => 'Maroc'
        ],
        'FR' => [
            'patterns' => ['/^(?:\+33|0033|33|0)([1-9]\d{8})$/', '/^0[1-9](\d{2}){4}$/'],
            'name' => 'France'
        ],
        'TR' => [
            'patterns' => ['/^(?:\+90|0090|90|0)([2-9]\d{9})$/', '/^0[2-9]\d{9}$/'],
            'name' => 'Turquie'
        ],
        'DZ' => [
            'patterns' => ['/^(?:\+213|00213|213|0)([5-9]\d{8})$/', '/^0[5-9]\d{8}$/'],
            'name' => 'Algérie'
        ],
    ];

    foreach ($countryPatterns as $countryCode => $countryInfo) {
        foreach ($countryInfo['patterns'] as $pattern) {
            if (preg_match($pattern, $cleaned)) {
                $normalized = $this->normalizePhoneNumberInternational($phoneNumber, $countryCode);
                return [
                    'is_valid' => true,
                    'normalized' => $normalized,
                    'country' => $countryCode,
                    'country_name' => $countryInfo['name']
                ];
            }
        }
    }

    // Fallback: accepter si c'est un format international générique
    if (preg_match('/^\+\d{10,14}$/', $cleaned) || preg_match('/^\d{9,15}$/', $cleaned)) {
        return [
            'is_valid' => true,
            'normalized' => $this->normalizePhoneNumberInternational($cleaned, null),
            'country' => 'INTERNATIONAL',
            'country_name' => 'International'
        ];
    }

    return [
        'is_valid' => false,
        'error_message' => "❌ Format invalide. Exemples: 06XXXXXXXX, +2126XXXXXXXX"
    ];
}

/**
 * ✅ Normaliser un numéro de téléphone en format international
 */
private function normalizePhoneNumberInternational($phoneNumber, $countryCode = null)
{
    $cleaned = preg_replace('/[^\d+]/', '', $phoneNumber);

    if ($countryCode) {
        switch ($countryCode) {
            case 'MA':
                if (str_starts_with($cleaned, '0') && strlen($cleaned) === 10) {
                    return '+212' . substr($cleaned, 1);
                } elseif (str_starts_with($cleaned, '00212')) {
                    return '+' . substr($cleaned, 2);
                } elseif (str_starts_with($cleaned, '212')) {
                    return '+' . $cleaned;
                } elseif (strlen($cleaned) === 9 && in_array($cleaned[0], ['5', '6', '7'])) {
                    return '+212' . $cleaned;
                }
                break;
            case 'FR':
                if (str_starts_with($cleaned, '0') && strlen($cleaned) === 10) {
                    return '+33' . substr($cleaned, 1);
                } elseif (str_starts_with($cleaned, '0033')) {
                    return '+' . substr($cleaned, 2);
                } elseif (str_starts_with($cleaned, '33') && strlen($cleaned) === 11) {
                    return '+' . $cleaned;
                }
                break;
            case 'TR':
                if (str_starts_with($cleaned, '0') && strlen($cleaned) === 11) {
                    return '+90' . substr($cleaned, 1);
                }
                break;
            case 'DZ':
                if (str_starts_with($cleaned, '0') && strlen($cleaned) === 10) {
                    return '+213' . substr($cleaned, 1);
                }
                break;
        }
    }

    if (str_starts_with($cleaned, '00')) {
        return '+' . substr($cleaned, 2);
    } elseif (!str_starts_with($cleaned, '+') && !str_starts_with($cleaned, '00')) {
        return '+' . $cleaned;
    }

    return $cleaned;
}

/**
 * ✅ Vérifier si un numéro de téléphone est en double
 */
private function isPhoneNumberDuplicate($phoneNumber, $currentSenderId, $projet_id)
{
    try {
        $normalizedPhone = $phoneNumber;
        if (!str_starts_with($phoneNumber, '+')) {
            $validationResult = $this->validatePhoneNumber($phoneNumber);
            if ($validationResult['is_valid']) {
                $normalizedPhone = $validationResult['normalized'] ?? $phoneNumber;
            }
        }

        $phoneDigits = preg_replace('/[^0-9]/', '', $normalizedPhone);

        $existingProspect = Prospect::on('temp')
            ->where('projet_id', $projet_id)
            ->where('telephone', '!=', '')
            ->whereNotNull('telephone')
            ->where(function ($query) use ($normalizedPhone, $phoneDigits) {
                $query->where('telephone', $normalizedPhone)
                    ->orWhere('telephone', 'LIKE', '%' . substr($normalizedPhone, 1) . '%')
                    ->orWhere('telephone', 'LIKE', '%' . substr($phoneDigits, -9) . '%')
                    ->orWhere('telephone_num2', 'like', '%' . substr($phoneDigits, -9) . '%');
            })
            ->first();

        return $existingProspect;

    } catch (\Exception $e) {
        Log::error("Error checking duplicate phone: " . $e->getMessage());
        return null;
    }
}

/**
 * ✅ Mettre à jour un prospect avec un numéro de téléphone
 */
private function updateProspectWithPhoneNumber($senderName, $phoneNumber, $societeId, $projet_id, $senderId, $message)
{
    try {
        $normalizedPhone = $phoneNumber;
        if (!str_starts_with($phoneNumber, '+')) {
            $validationResult = $this->validatePhoneNumber($phoneNumber);
            if ($validationResult['is_valid']) {
                $normalizedPhone = $validationResult['normalized'] ?? $phoneNumber;
            }
        }

        $prospect = Prospect::on('temp')
            ->where('nom', $senderName)
            ->where('projet_id', $projet_id)
            ->first();

        if ($prospect) {
            $prospect->telephone = $normalizedPhone;
            $prospect->facebook_id = $senderId;
            $prospect->save();

            Log::info("Prospect phone number updated", [
                'prospect_id' => $prospect->id,
                'phone_number' => $normalizedPhone
            ]);
        } else {
               // Création d'un nouveau prospect
            $sourceId = null;
            $source = Source::on('temp')
                ->whereIn('source', ['Facebook', 'facebook'])
                ->first();

            if ($source) {
                $sourceId = $source->id;
            }
            // Créer un nouveau prospect
            $prospect = new Prospect();
            $prospect->setConnection('temp');
            $prospect->nom = $senderName;
            $prospect->telephone = $normalizedPhone;
            $prospect->facebook_id = $senderId;
            $prospect->projet_id = $projet_id;
            $prospect->origin = 'facebook';
            $prospect->source = $sourceId;
            $prospect->notifie = 0;
            $prospect->message = $message;
            $prospect->created_at = now();
            $prospect->updated_at = now();
            $prospect->save();

            $this->createProspectStatus($prospect->id, $projet_id);

            Log::info("New prospect created with phone number", [
                'prospect_id' => $prospect->id,
                'name' => $senderName,
                'phone_number' => $normalizedPhone
            ]);
        }

        return true;

    } catch (\Exception $e) {
        Log::error("Error updating prospect with phone: " . $e->getMessage());
        return false;
    }
}

/**
 * ✅ Marquer qu'on a demandé le numéro de téléphone
 */
private function markAsAskedForPhone($senderId)
{
    try {
        cache()->put("asked_phone_{$senderId}", true, 1440); // 24h
        Log::info("Marked user {$senderId} as asked for phone number");
    } catch (\Exception $e) {
        Log::error("Error marking asked for phone: " . $e->getMessage());
    }
}

/**
 * ✅ Vérifier si c'est le premier message de l'utilisateur
 */
private function isFirstMessageFromUser($senderId)
{
    try {
        $prospect = Prospect::on('temp')
            ->where('facebook_id', $senderId)
            ->orWhere(function ($query) use ($senderId) {
                $query->where('telephone', 'LIKE', '%' . substr($senderId, -6) . '%');
            })
            ->first();

        return !$prospect || empty($prospect->telephone);

    } catch (\Exception $e) {
        Log::error("Error checking first message: " . $e->getMessage());
        return true; // Par sécurité, on suppose que c'est le premier message
    }
}
}
