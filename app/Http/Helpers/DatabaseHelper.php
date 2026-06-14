<?php

namespace App\Http\Helpers;

use App\Http\Helpers\Bien_Helper;
use App\Http\Helpers\NotificationHelper;
use App\Mail\ScheduledEmail;
use App\Models\Avance;
use App\Models\Bien;
use App\Models\Notification;
use App\Models\Proposition;
use App\Models\Visite;
use App\Models\Relance_Rdv_Visite;
use App\Models\Relance_Rdv_Appel;
use App\Models\TraitementAppel;
use App\Models\Rendez_vous;


use App\Models\Societe;
use App\Models\User;
use Carbon\Carbon;
use App\Models\Import;
use App\Models\Projet;
use App\Models\StatutProspect;
use App\Models\WebhookEvent;
use App\Models\CreneauxOccupes;

use Illuminate\Support\Facades\Http;
use App\Http\Helpers\ImportExcelHelper;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config; // Mail à envoyer
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class DatabaseHelper
{


    // ✅ Déclaration des propriétés statiques (obligatoire)
    public static $TEMPLATE_ECHEANCE_PAIEMENT;
    public static $TEMPLATE_RAPPEL_RDV_PROSPECT;

    public function __construct()
    {
        self::$TEMPLATE_ECHEANCE_PAIEMENT = env('TEMPLATE_ECHEANCE_PAIEMENT', null);
        self::$TEMPLATE_RAPPEL_RDV_PROSPECT = env('TEMPLATE_RAPPEL_RDV_PROSPECT', null);

        if (empty(self::$TEMPLATE_ECHEANCE_PAIEMENT)) {
            Log::warning("TEMPLATE_ECHEANCE_PAIEMENT non configuré dans .env");
        }
        if (empty(self::$TEMPLATE_RAPPEL_RDV_PROSPECT)) {
            Log::warning("TEMPLATE_RAPPEL_RDV_PROSPECT non configuré dans .env");
        }
    }
/**
 * Vérifie si un template WhatsApp est correctement configuré
 */
private static function isTemplateConfigured($templateSid, $templateName)
{
    if (empty($templateSid)) {
        Log::warning("Template WhatsApp '{$templateName}' non configuré - Envoi ignoré");
        return false;
    }
    return true;
}
    public function createNewClientDatabase($raison_sociale, $societe_id)
    {
        $databaseName = env('DB_DATABASE');
       // $databaseName = 'Erp_' . $raison_sociale . '_' . $societe_id;
          //  $databaseName =  env('DB_DATABASE');
        if ($this->databaseExists($databaseName)) {
            return response()->json(['message' => 'Database already exists.']);
        }

        DB::statement("CREATE DATABASE IF NOT EXISTS $databaseName");

        $connection = [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => $databaseName,
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ];

       // Run migrations
    $migration = $this->runMigrations($connection);

    if ($migration === true) {
        // Run seeders after successful migrations
        $seeder = $this->runSeeders($connection);

        if ($seeder === true) {
            return response()->json(['message' => 'Database created, migrations and seeders ran successfully.']);
        } else {
            return response()->json(['message' => 'Migrations ran successfully but error running seeders.']);
        }
    } else {
        return response()->json(['message' => 'Error running migrations.']);
    }
}

public function runSeeders($connection)
{
    config(['database.connections.temp' => $connection]);

    // Run the specific seeders
    $seeder1 = Artisan::call('db:seed', [
        '--database' => 'temp',
        '--class' => 'Database\Seeders\ServicesPrestatairesSeeder',
        '--force' => true,
    ]);

    $seeder2 = Artisan::call('db:seed', [
        '--database' => 'temp',
        '--class' => 'Database\Seeders\SourceSeeder',
        '--force' => true,
    ]);

    $seeder3 = Artisan::call('db:seed', [
        '--database' => 'temp',
        '--class' => 'Database\Seeders\TypeFreinSeeder',
        '--force' => true,
    ]);

    // NEW: Run SyncSuperAdminToSocieteDatabasesSeeder to insert superadmin
    $seeder4 = Artisan::call('db:seed', [
        '--database' => 'temp',
        '--class' => 'Database\Seeders\SyncSuperAdminToSocieteDatabasesSeeder',
        '--force' => true,
    ]);

    config(['database.connections.temp' => null]);

    // Check if all seeders ran successfully (return 0 means success)
    return ($seeder1 === 0 && $seeder2 === 0 && $seeder3 === 0 && $seeder4 === 0);
}
    public function runMigrations($connection)
    {
        config(['database.connections.temp' => $connection]);

       $migration = Artisan::call('migrate', [
            '--database' => 'temp',
            '--path' => 'database/migrations/migrations_societe',
            '--force' => true,
        ]);

        config(['database.connections.temp' => null]);

        return $migration === 0;
    }

    public function renameDatabase($oldDatabaseName, $newDatabaseName)
    {

        DB::statement("CREATE DATABASE $newDatabaseName");

        $tables = DB::select("SHOW TABLES FROM $oldDatabaseName");

        foreach ($tables as $table) {
            $tableName = reset($table);

            DB::statement("CREATE TABLE $newDatabaseName.$tableName LIKE $oldDatabaseName.$tableName");
            DB::statement("INSERT INTO $newDatabaseName.$tableName SELECT * FROM $oldDatabaseName.$tableName");
        }

        DB::statement("DROP DATABASE $oldDatabaseName");
    }

    public static function Config($societe_id = null)
    {
        try {
            // If no societe_id provided, try to get from authenticated user
            if ($societe_id === null) {
                $user = Auth::user();
                if (!$user) {
                    Log::warning('DatabaseHelper::Config called without societe_id and no authenticated user');
                    throw new \Exception('No societe_id provided and no authenticated user found');
                }
                $societe_id = $user->societe_id;
            }

            if (!$societe_id) {
                throw new \Exception('Invalid societe_id provided to DatabaseHelper::Config');
            }

            Log::info("DatabaseHelper::Config called with societe_id: {$societe_id}");

            $societe = Societe::findOrfail($societe_id);
            //$DatabaseName = 'Erp_' . $societe->raison_sociale_concatene . '_' . $societe_id;
            $DatabaseName = env('DB_DATABASE');
            $connection = DatabaseHelper::Connection_database($DatabaseName);
            config(['database.connections.temp' => $connection]);
        } catch (\Exception $e) {
            Log::error('DatabaseHelper::Config error: ' . $e->getMessage());
            throw $e;
        }
    }

    // public static function Config($societe_id = null)
    // {
    //     if (!$societe_id) {
    //         $societe_id = Auth::guard('api')->user()->societe_id;
    //     }
    //     $societe = Societe::findOrfail($societe_id);
    //     $DatabaseName = 'Erp_' . $societe->raison_sociale . '_' . $societe_id;
    //     $connection = DatabaseHelper::Connection_database($DatabaseName);
    //     config(['database.connections.temp' => $connection]);
    // }
    public static function Connection_database($databaseName)
    {
        return [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => $databaseName,
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ];
    }

   public static function deletePropositionTable($databases)
    {
        foreach ($databases as $database) {
           // $databaseName = 'Erp_' . $database->raison_sociale_concatene . '_' . $database->id;

    $databaseName =  env('DB_DATABASE');
            try {
                // Établir la connexion
                $connection = DatabaseHelper::Connection_database($databaseName);
                config(['database.connections.temp' => $connection]);
                DB::connection('temp')->setDatabaseName($connection['database']);
                DB::reconnect('temp');

                // Vérifier l'existence de la table une seule fois
                if (!Schema::connection('temp')->hasTable('propositions')) {
                    \Log::info("Table 'propositions' does not exist in $databaseName.");
                    continue;
                }

                // Récupérer les utilisateurs une seule fois
                $notConnectedUsers = DB::table('users')->where('is_connected', 0)->pluck('id');
                $connectedUsers = DB::table('users')->where('is_connected', 1)->pluck('id');

                // Fusionner les deux traitements similaires
                $allUsers = [
                    'not_connected' => $notConnectedUsers,
                    'connected' => $connectedUsers
                ];

                foreach ($allUsers as $type => $users) {
                    if ($users->isEmpty()) {
                        continue;
                    }

                    $propositions = Proposition::on('temp')
                        ->when($type == 'connected', function ($query) {
                            return $query->select('id', 'created_at', 'bien_id');
                        })
                        ->whereIn('user_id', $users)
                        ->with(['bien' => function($query) {
                            $query->select('id', 'etat', 'created_at');
                        }])
                        ->get();

                    foreach ($propositions as $prop) {
                        $bien = $prop->bien;
                        if ($bien && $bien->etat == 'ENCOURS_DE_PROPOSITION') {
                            $expiryTime = Carbon::parse($bien->created_at)->addMinutes(30);
                            if ($expiryTime->isPast()) {
                                Bien_Helper::libererBien($bien->id, 'console', null,false);
                                \Log::info("Bien proposé updated==>.".$bien->id);

                            }
                        }
                        $prop->forceDelete();
                    }

                    \Log::info("Deleted propositions for {$type} users in $databaseName.");
                }
            } catch (\Exception $e) {
                \Log::error("Error processing database $databaseName: " . $e->getMessage());
            }
        }
    }

  public static function deleteCreneauPropose($databases)
    {
        foreach ($databases as $database) {
          //  $databaseName = 'Erp_' . $database->raison_sociale_concatene . '_' . $database->id;
                $databaseName =  env('DB_DATABASE');
            try {
                // Établir la connexion
                $connection = DatabaseHelper::Connection_database($databaseName);
                config(['database.connections.temp' => $connection]);
                DB::connection('temp')->setDatabaseName($connection['database']);
                DB::reconnect('temp');

                // Vérifier l'existence de la table une seule fois
                if (!Schema::connection('temp')->hasTable('creneaux_occupes')) {
                    \Log::info("Table creneaux_occupes does not exist in $databaseName.");
                    continue;
                }

                $creneaux = CreneauxOccupes::on('temp')->get();
                    foreach ($creneaux as $prop) {
                            $expiryTime = Carbon::parse($prop->created_at)->addMinutes(3);
                            if ($expiryTime->isPast() && $prop->type==0) {
                                $prop->forceDelete();
                                \Log::info("creneay proposé deleted.");
                            }
                    }
            } catch (\Exception $e) {
                \Log::error("Error processing database $databaseName: " . $e->getMessage());
            }
        }
    }


    public static function annuler_rdv_automatique($databases)
{
    foreach ($databases as $database) {
        //$databaseName = 'Erp_' . $database->raison_sociale_concatene . '_' . $database->id;
            $databaseName =  env('DB_DATABASE');
        try {
            // Établir la connexion
            $connection = DatabaseHelper::Connection_database($databaseName);
            config(['database.connections.temp' => $connection]);
            DB::connection('temp')->setDatabaseName($connection['database']);
            DB::reconnect('temp');

            // Vérifier l'existence de la table
            if (!Schema::connection('temp')->hasTable('rendez_vous')) {
                \Log::info("Table rendez_vous does not exist in $databaseName.");
                continue;
            }

            // Récupérer les RDV en attente (statut = 1) dont la date est passée de plus d'une heure
            $now = now();

            $rdvs = Rendez_vous::on('temp')
                ->where('statut', '1') // en attente
                ->whereNotNull('rdv') // vérifier que rdv n'est pas null
                ->where('rdv', '<=', $now->subHour()) // rdv date plus ancienne qu'il y a une heure
                ->get();

            foreach ($rdvs as $rdv) {
                // Vérifier si plus d'une heure s'est écoulée depuis la date du RDV
                $rdvDateTime = \Carbon\Carbon::parse($rdv->rdv);
                $hoursDifference = $rdvDateTime->diffInHours($now);

                if ($hoursDifference >= 1) {
                    // Mettre à jour le statut à 4 (annulé automatique)
                    $rdv->statut = '4';
                    $rdv->save();

                    \Log::info("RDV ID {$rdv->id} dans $databaseName annulé automatiquement. RDV date: {$rdv->rdv}, Heure actuelle: {$now}");
                }
            }

            \Log::info("Processed $databaseName: " . $rdvs->count() . " RDVs annulés automatiquement.");

        } catch (\Exception $e) {
            \Log::error("Error processing database $databaseName: " . $e->getMessage());
        }
    }
}

    public static function deleteWebhookTable($databases)
    {
        foreach ($databases as $database) {
           // $databaseName = 'Erp_' . $database->raison_sociale_concatene . '_' . $database->id;
            $databaseName =  env('DB_DATABASE');
            // Switch to the temporary database
            $connection = DatabaseHelper::Connection_database($databaseName);
            config(['database.connections.temp' => $connection]);
            DB::connection('temp')->setDatabaseName($connection['database']);
            DB::reconnect('temp');

            //
            if (Schema::connection('temp')->hasTable('webhook_events')) {
                    $webhook_events = WebhookEvent::on('temp')->withTrashed() // id  from  users from mother db
                        ->get();
                    foreach ($webhook_events as $web) {
                        $web->forceDelete();
                    }

                    \Log::info("Deleted webhook_events for not connected users in $databaseName.");


            } else {
                \log::info("Table webhook_events' does not exist in $databaseName.");
            }
        }
    }

    public static function destroy_notif($databases)
    {
        foreach ($databases as $database) {
            //$databaseName = 'Erp_' . $database->raison_sociale_concatene . '_' . $database->id;
                $databaseName =  env('DB_DATABASE');
            // Switch to the temporary database
            $connection = DatabaseHelper::Connection_database($databaseName);
            config(['database.connections.temp' => $connection]);
            DB::connection('temp')->setDatabaseName($connection['database']);
            DB::reconnect('temp');

            //
            if (Schema::connection('temp')->hasTable('notifications')) {
                $date_15 = \Carbon\Carbon::today()->subDays(15);
                $Notifiations = Notification::on('temp')
                    ->whereDate('created_at', '<=', $date_15)
                    ->onlyTrashed()
                    ->get();
                if (($Notifiations->count()) > 0) {
                    foreach ($Notifiations as $nt) {
                        $nt->forceDelete();
                    }
                }
            }
        }
    }




    /**
 * Send WhatsApp template message using Twilio with database configuration
 */
public static function sendWhatsAppTemplate($phone, $templateSid, $variables = [], $projetId = null)
{
    // Vérification que le template SID n'est pas vide
    if (empty($templateSid)) {
        Log::warning("Template SID vide - Envoi WhatsApp ignoré pour {$phone}");
        return false;
    }
    try {

         // Log des variables avant envoi
        Log::info("Preparing WhatsApp template", [
            'template_sid' => $templateSid,
            'phone' => $phone,
            'variables_raw' => $variables
        ]);

        // Nettoyer les variables
        $cleanedVariables = [];
        foreach ($variables as $key => $value) {
            // S'assurer que la valeur n'est pas null et est une chaîne
            $cleanedValue = $value ?? '';
            // Limiter la longueur (WhatsApp limite à 1024 caractères)
            $cleanedValue = substr($cleanedValue, 0, 1024);
            $cleanedVariables[$key] = $cleanedValue;
        }

        // Prepare variables as JSON
        $contentVariables = json_encode($cleanedVariables);

        Log::info("WhatsApp variables JSON", ['json' => $contentVariables]);
        // Clean phone number
        $phone = preg_replace('/\s+/', '', $phone);

        if (preg_match('/^0(\d{9})$/', $phone, $matches)) {
            $phone = '+212' . $matches[1];
        }

        if (!preg_match('/^\+\d{10,15}$/', $phone) && !preg_match('/^0\d{9}$/', $phone)) {
            $phone = '+212' . ltrim($phone, '0');
        }

        // Get WhatsApp configuration
        $whatsappConfig = null;

        if ($projetId) {
            $whatsappConfig = \DB::connection('temp')->table('whatsapp_configurations')
                ->where('projet_id', $projetId)
                ->whereNull('deleted_at')
                ->first();
        }

        if (!$whatsappConfig) {
            $whatsappConfig = \DB::connection('temp')->table('whatsapp_configurations')
                ->whereNull('deleted_at')
                ->first();
        }

        if (!$whatsappConfig) {
            Log::warning("No WhatsApp configuration found");
            return false;
        }

        // Prepare variables as JSON
        $contentVariables = json_encode($variables);

        // Twilio API call
        $url = "https://api.twilio.com/2010-04-01/Accounts/" . $whatsappConfig->account_sid . "/Messages.json";

        $postData = [
            'To' => "whatsapp:" . $phone,
            'From' => "whatsapp:" . $whatsappConfig->phone_number_id,
            'ContentSid' => $templateSid,
            'ContentVariables' => $contentVariables
        ];

        $auth = base64_encode($whatsappConfig->account_sid . ":" . $whatsappConfig->access_token);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . $auth,
            'Content-Type: application/x-www-form-urlencoded'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $responseData = json_decode($response, true);

        if ($httpCode == 201 || $httpCode == 200) {
            Log::info("✅ WhatsApp template sent to {$phone}, SID: " . ($responseData['sid'] ?? 'unknown'));
            return true;
        } else {
            Log::error("❌ WhatsApp template failed: " . ($responseData['message'] ?? $response));
            return false;
        }

    } catch (\Exception $e) {
        Log::error("❌ WhatsApp template exception: " . $e->getMessage());
        return false;
    }
}

public static function envoyer_whatsap_email_rdv_rlc($databases)
{
    foreach ($databases as $database) {
        //$databaseName = 'Erp_' . $database->raison_sociale_concatene . '_' . $database->id;
$databaseName =  env('DB_DATABASE');
        // Configurer la connexion à la base de données temporaire
        $connection = DatabaseHelper::Connection_database($databaseName);
        config(['database.connections.temp' => $connection]);
        DB::connection('temp')->setDatabaseName($connection['database']);
        DB::reconnect('temp');

        $today = Carbon::now()->toDateString();
            Log::info('todat is ' . $today);

        // Traitement pour les visites
        self::traiterRelancesRdvVisites($databaseName, $today);

        // Traitement pour les appels
        self::traiterRelancesRdvAppels($databaseName, $today);
    }
}

private static function traiterRelancesRdvVisites($databaseName, $today)
{
    if (!Schema::connection('temp')->hasTable('relances_rdv_visites')) {
        Log::warning('La table relances_rdv_visites est absente dans la base de données ' . $databaseName);
        return;
    }

    // Récupérer toutes les relances et RDVs en une seule requête
    $relancesRdvs = Relance_Rdv_Visite::on('temp')
        ->where(function($query) use ($today) {
            $query->where(function($q) use ($today) {
                $q->where('type', 1)->where('type_traitement', 0)->whereDate('date_relance', $today);
            })->orWhere(function($q) use ($today) {
                $q->where('type', 2)->where('type_traitement', 0)->whereDate('rdv', $today);
            });
        })
        ->get();

    if ($relancesRdvs->isEmpty()) {
        return;
    }

    // Séparer les IDs
    $relanceVisiteIds = $relancesRdvs->where('type', 1)->pluck('visite_id');
    $rdvVisiteIds = $relancesRdvs->where('type', 2)->pluck('visite_id');
    $relanceUserIds = $relancesRdvs->where('type', 1)->pluck('user_id');
    $rdvUserIds = $relancesRdvs->where('type', 2)->pluck('user_id');

    // Récupérer toutes les visites concernées en une seule requête
    $visites = Visite::on('temp')
        ->with(['prospect', 'projet', 'bien'])
        ->where('etat', 1)
        ->whereIn('id', $relanceVisiteIds->merge($rdvVisiteIds)->unique())
        ->get();

    // Grouper les visites par prospect
    $prospectsData = [];
    foreach ($visites as $visite) {
        if ($visite->prospect) {
            $prospectId = $visite->prospect->id;
            if (!isset($prospectsData[$prospectId])) {
                $prospectsData[$prospectId] = [
                    'prospect' => $visite->prospect,
                    'visites' => [],
                    'hasRelance' => false,
                    'hasRdv' => false
                ];
            }
            $prospectsData[$prospectId]['visites'][] = $visite;
            $prospectsData[$prospectId]['hasRelance'] = $prospectsData[$prospectId]['hasRelance'] || $relanceVisiteIds->contains($visite->id);
            $prospectsData[$prospectId]['hasRdv'] = $prospectsData[$prospectId]['hasRdv'] || $rdvVisiteIds->contains($visite->id);
        }
    }

    // Envoi des emails aux prospects
    foreach ($prospectsData as $data) {
        self::envoyerEmail_whtsap_ProspectVisite($data, $databaseName);
    }

    // Envoi des emails aux utilisateurs
    $userIds = $relanceUserIds->merge($rdvUserIds)->unique();
    if ($userIds->isNotEmpty()) {
        $users = User::on('temp')
            ->with(['visite' => function($query) use ($relanceVisiteIds, $rdvVisiteIds) {
                $query->with(['projet', 'bien', 'prospect'])
                    ->whereIn('id', $relanceVisiteIds->merge($rdvVisiteIds))
                    ->where('etat', 1);
            }])
            ->whereIn('id', $userIds)
            ->get();

        foreach ($users as $user) {
            self::envoyerEmailUserVisite($user, $relanceUserIds, $rdvUserIds, $databaseName);
        }
    }

    Log::info('Processus de relance visite terminé pour la base de données ' . $databaseName);
}

private static function traiterRelancesRdvAppels($databaseName, $today)
{
    if (!Schema::connection('temp')->hasTable('relances_rdvs_appels')) {
        Log::warning('La table relances_rdv_appels est absente dans la base de données ' . $databaseName);
        return;
    }

    // Récupérer toutes les relances et RDVs en une seule requête
    $relancesRdvs = Relance_Rdv_Appel::on('temp')
        ->with('traite_appel.appel.prospect')
        ->where(function($query) use ($today) {
            $query->where(function($q) use ($today) {
                $q->where('type', 1)->where('type_traitement', 0)->whereDate('date_relance', $today);
            })->orWhere(function($q) use ($today) {
                $q->where('type', 2)->where('type_traitement', 0)->whereDate('rdv', $today);
            });
        })
        ->get();

    if ($relancesRdvs->isEmpty()) {
        return;
    }

    // Séparer les IDs
    $relanceAppelIds = $relancesRdvs->where('type', 1)->pluck('traite_appel_id');
    $rdvAppelIds = $relancesRdvs->where('type', 2)->pluck('traite_appel_id');
    $relanceUserIds = $relancesRdvs->where('type', 1)->pluck('traite_appel.user_id');
    $rdvUserIds = $relancesRdvs->where('type', 2)->pluck('traite_appel.user_id');

    // Récupérer tous les traitements d'appel concernés en une seule requête
   $traitementsAppels = TraitementAppel::on('temp')
    ->with(['appel.prospect', 'appel.projet', 'rdv']) // Ajoutez 'rdv' ici
    ->whereIn('id', $relanceAppelIds->merge($rdvAppelIds)->unique())
    ->get();

    // Grouper par prospect
    $prospectsData = [];
    foreach ($traitementsAppels as $traitement) {
        if ($traitement->appel && $traitement->appel->prospect) {
            $prospectId = $traitement->appel->prospect->id;
            if (!isset($prospectsData[$prospectId])) {
                $prospectsData[$prospectId] = [
                    'prospect' => $traitement->appel->prospect,
                    'traitements' => [],
                    'hasRelance' => false,
                    'hasRdv' => false
                ];
            }
            $prospectsData[$prospectId]['traitements'][] = $traitement;
            $prospectsData[$prospectId]['hasRelance'] = $prospectsData[$prospectId]['hasRelance'] || $relanceAppelIds->contains($traitement->id);
            $prospectsData[$prospectId]['hasRdv'] = $prospectsData[$prospectId]['hasRdv'] || $rdvAppelIds->contains($traitement->id);
        }
    }

    // Envoi des emails aux prospects
    foreach ($prospectsData as $data) {
        self::envoyerEmail_whasap_ProspectAppel($data, $databaseName);
    }

    // Envoi des emails aux utilisateurs
    $userIds = $relanceUserIds->merge($rdvUserIds)->unique();
    if ($userIds->isNotEmpty()) {
        $users = User::on('temp')->whereIn('id', $userIds)->get();

        // Précharger les traitements d'appel pour tous les utilisateurs
        $traitementsUsers = TraitementAppel::on('temp')
            ->with(['appel.projet', 'appel.prospect'])
            ->whereIn('user_id', $userIds)
            ->whereIn('id', $relanceAppelIds->merge($rdvAppelIds))
            ->get()
            ->groupBy('user_id');

        foreach ($users as $user) {
            self::envoyerEmail_whatsap_UserAppel($user, $traitementsUsers->get($user->id, []), $relanceUserIds, $rdvUserIds, $databaseName);
        }
    }

    Log::info('Processus de relance appel terminé pour la base de données ' . $databaseName);
}

// Méthodes helper pour l'envoi d'emails
private static function envoyerEmail_whtsap_ProspectVisite($data, $databaseName)
{
    $prospect = $data['prospect'];

    try {
         if ($prospect->email) {
            $projet = $data['visites'][0]->projet->nom ?? null;
            $bien = $data['visites'][0]->bien->propriete_dite_bien ?? null;
// Construction du numéro de téléphone complet
            $telephone = null;
            if ($prospect->telephone) {
                $telephone = $prospect->telephone;
                if ($prospect->telephone_num2) {
                    $telephone .= ' / ' . $prospect->telephone_num2;
                }
            } elseif ($prospect->telephone_num2) {
                $telephone = $prospect->telephone_num2;
            }
        /* if ($data['hasRelance']) {
                Mail::to($prospect->email)->send(new ScheduledEmail(1, $prospect, $projet, $bien, null, null, 'visite',null));
                Log::info("Email de relance visite envoyé à {$prospect->email} (Prospect) dans la base de données {$databaseName}");
            }*/

           if ($data['hasRdv']) {
                // Récupérer la date du RDV
                $rdvDate = null;
                if (isset($data['visites'][0]->rdv_relation->rdv)) {
                    $rdvDate = $data['visites'][0]->rdv_relation->rdv;
                }

                Mail::to($prospect->email)->send(new ScheduledEmail(
                    2, $prospect, $projet, $bien, null, null, 'visite', $telephone, $rdvDate
                ));
                Log::info("Email de RDV visite envoyé à {$prospect->email} (Prospect) dans la base de données {$databaseName}");
            }
         }
            // Send WhatsApp messages if telephone numbers exist
            self::sendWhatsAppToProspect($prospect, $data, $projet, $databaseName);

    } catch (\Exception $e) {
        Log::error("Échec de l'envoi de l'email au prospect {$prospect->email}: " . $e->getMessage());
          // Send WhatsApp messages if telephone numbers exist
        self::sendWhatsAppToProspect($prospect, $data, $projet, $databaseName);
    }
}

private static function envoyerEmailUserVisite($user, $relanceUserIds, $rdvUserIds, $databaseName)
{
    try {
        $visite = $user->visite->first();
        if (!$visite) return;

        $projet = $visite->projet->nom ?? null;
        $bien = $visite->bien->propriete_dite_bien ?? null;
        $prospectName = $visite->prospect->nom.' '.$visite->prospect->prenom ?? null;
        $prospectPhone =  $visite->prospect->telephone ?? null;
        $prospectPhone2 =  $visite->prospect->telephone_num2 ?? null;

         // Construction du téléphone du prospect pour le commercial
        $prospectPhone = null;
        if ($visite->prospect->telephone) {
            $prospectPhone = $visite->prospect->telephone;
            if ($visite->prospect->telephone_num2) {
                $prospectPhone .= ' / ' . $visite->prospect->telephone_num2;
            }
        } elseif ($visite->prospect->telephone_num2) {
            $prospectPhone = $visite->prospect->telephone_num2;
        }

        if ($relanceUserIds->contains($user->id)) {
            Mail::to($user->email)->send(new ScheduledEmail(1, $user, $projet, $bien, $prospectName, null, 'visite',$prospectPhone));
            Log::info("Email de relance visite envoyé à {$user->email} (Utilisateur) dans la base de données {$databaseName}");
        }

        // Dans envoyerEmailUserVisite
            if ($rdvUserIds->contains($user->id)) {
                // Récupérer la date du RDV
                $rdvDate = null;
                if ($visite->rdv_relation && $visite->rdv_relation->rdv) {
                    $rdvDate = $visite->rdv_relation->rdv;
                }

                Mail::to($user->email)->send(new ScheduledEmail(
                    2, $user, $projet, $bien, $prospectName, null, 'visite', $prospectPhone, $rdvDate
                ));
                Log::info("Email de RDV visite envoyé à {$user->email} (Utilisateur) dans la base de données {$databaseName}");
            }
      //  self::sendWhatsAppToUser($user, $relanceUserIds, $rdvUserIds, $projet, $prospectName, $prospectPhone, $prospectPhone2, $databaseName);

    } catch (\Exception $e) {
        Log::error("Échec de l'envoi de l'email à l'utilisateur {$user->email}: " . $e->getMessage());
       //  self::sendWhatsAppToUser($user, $relanceUserIds, $rdvUserIds, $projet, $prospectName, $prospectPhone, $prospectPhone2, $databaseName);

    }
}

private static function envoyerEmail_whasap_ProspectAppel($data, $databaseName)
{
    $prospect = $data['prospect'];

    try {
        $projet = $data['traitements'][0]->appel->projet->nom ?? null;

        // Récupérer la date RDV depuis la relation rdv() du TraitementAppel
        $rdvDate = null;
        if (isset($data['traitements'][0])) {
            $traitement = $data['traitements'][0];
            // Utiliser la relation rdv() que vous avez définie
            if ($traitement->rdv && $traitement->rdv->rdv) {
                $rdvDate = $traitement->rdv->rdv;
                Log::info("RDV date found for appel via relation: {$rdvDate}");
            } else {
                Log::warning("No rdv relation found for traitement_id: {$traitement->id}");
            }
        }

        $telephone = null;
        if ($prospect->telephone) {
            $telephone = $prospect->telephone;
            if ($prospect->telephone_num2) {
                $telephone .= ' / ' . $prospect->telephone_num2;
            }
        } elseif ($prospect->telephone_num2) {
            $telephone = $prospect->telephone_num2;
        }

        // Send emails if email exists
        if ($prospect->email) {
           if ($data['hasRdv']) {
            // Récupérer la date RDV
            $rdvDate = null;
            if (isset($data['traitements'][0]) && $data['traitements'][0]->rdv) {
                $rdvDate = $data['traitements'][0]->rdv->rdv;
            }

            Mail::to($prospect->email)->send(new ScheduledEmail(
                2, $prospect, $projet, null, null, null, 'appel', $telephone, $rdvDate
            ));
            Log::info("Email de RDV appel envoyé à {$prospect->email} (Prospect) dans la base de données {$databaseName}");
        }
        } else {
            Log::warning("Aucun email associé au prospect ID {$prospect->id} dans la base de données {$databaseName}");
        }

        // Send WhatsApp messages if we have a valid RDV date
        if ($rdvDate) {
            // Passer la date RDV directement à sendWhatsAppToProspect
            self::sendWhatsAppToProspect($prospect, $data, $projet, $databaseName, $rdvDate);
        } else {
            Log::warning("No RDV date found for prospect {$prospect->id}, skipping WhatsApp");
        }

    } catch (\Exception $e) {
        Log::error("Échec de l'envoi de l'email au prospect appel {$prospect->email}: " . $e->getMessage());
    }
}

private static function sendWhatsAppToProspect($prospect, $data, $projet, $databaseName, $rdvDateString = null)
{
    // Vérifier si le template est configuré
    if (!self::isTemplateConfigured(self::$TEMPLATE_RAPPEL_RDV_PROSPECT, 'TEMPLATE_RAPPEL_RDV_PROSPECT')) {
        return;
    }
    // Vérifier d'abord si c'est un RDV et si la date existe
    $hasValidRdv = false;
    $rdvDateFormatted = '';

    if ($data['hasRdv']) {
        // Utiliser la date passée en paramètre si disponible
        if ($rdvDateString) {
            $rdvDate = $rdvDateString;
        }
        // Pour les visites
        elseif (isset($data['visites'][0]->rdv_relation->rdv)) {
            $rdvDate = $data['visites'][0]->rdv_relation->rdv;
        }
        else {
            Log::warning("No RDV date provided for prospect {$prospect->id}");
            return;
        }

        if ($rdvDate && !empty($rdvDate) && is_string($rdvDate)) {
            $hasValidRdv = true;
            try {
                $rdvDateFormatted = Carbon::parse($rdvDate)->format('d/m/Y H:i');
                Log::info("RDV date parsed successfully: {$rdvDateFormatted}");
            } catch (\Exception $e) {
                Log::error("Erreur de parsing de la date RDV: " . $e->getMessage());
                return;
            }
        } else {
            Log::warning("RDV date is invalid for prospect {$prospect->id}");
            return;
        }
    } else {
        // Pas de RDV, ne pas envoyer WhatsApp
        Log::info("No RDV for prospect {$prospect->id}, skipping WhatsApp");
        return;
    }

    if (!$hasValidRdv) {
        Log::warning("No valid RDV date found for prospect {$prospect->id}");
        return;
    }

    // Préparer les variables communes
    $nomProspect = trim($prospect->nom . ' ' . $prospect->prenom);
    $projetClean = $projet ?? '';

    $variables = [
        "1" => $nomProspect,
        "2" => $rdvDateFormatted,
        "3" => $projetClean
    ];

    Log::info("WhatsApp variables being sent", ['variables' => $variables]);

    // Envoyer au numéro principal
    if ($prospect->telephone && !empty($prospect->telephone)) {
        try {
            DatabaseHelper::sendWhatsAppTemplate(
                $prospect->telephone,
                self::$TEMPLATE_RAPPEL_RDV_PROSPECT,
                $variables
            );
            Log::info("WhatsApp template RDV envoyé au numéro principal {$prospect->telephone} (Prospect) dans {$databaseName}");
        } catch (\Exception $e) {
            Log::error("Échec de l'envoi WhatsApp au numéro principal {$prospect->telephone}: " . $e->getMessage());
        }
    }

    // Envoyer au second numéro si différent du principal
    if ($prospect->telephone_num2 && !empty($prospect->telephone_num2) && $prospect->telephone_num2 != $prospect->telephone) {
        try {
            DatabaseHelper::sendWhatsAppTemplate(
                $prospect->telephone_num2,
                self::$TEMPLATE_RAPPEL_RDV_PROSPECT,
                $variables
            );
            Log::info("WhatsApp template RDV envoyé au second numéro {$prospect->telephone_num2} (Prospect) dans {$databaseName}");
        } catch (\Exception $e) {
            Log::error("Échec de l'envoi WhatsApp au second numéro {$prospect->telephone_num2}: " . $e->getMessage());
        }
    }
}

private static function envoyerEmail_whatsap_UserAppel($user, $traitements, $relanceUserIds, $rdvUserIds, $databaseName)
{
    try {
        $traitement = $traitements->first();
        if (!$traitement) return;

        $projet = $traitement->appel->projet->nom ?? null;
        $prospectName = $traitement->appel->prospect->nom.' '.$traitement->appel->prospect->prenom ?? null;
        $prospectPhone = $traitement->appel->prospect->telephone ?? null;
        $prospectPhone2 = $traitement->appel->prospect->telephone_num2 ?? null;

        // Construction du téléphone du prospect pour le commercial
        $prospectPhone = null;
        $prospect = $traitement->appel->prospect;
        if ($prospect->telephone) {
            $prospectPhone = $prospect->telephone;
            if ($prospect->telephone_num2) {
                $prospectPhone .= ' / ' . $prospect->telephone_num2;
            }
        } elseif ($prospect->telephone_num2) {
            $prospectPhone = $prospect->telephone_num2;
        }
        // Send emails if email exists
        if ($user->email) {
            if ($relanceUserIds->contains($user->id)) {
                Mail::to($user->email)->send(new ScheduledEmail(1, $user, $projet, null, $prospectName, null, 'appel', $prospectPhone));
                Log::info("Email de relance appel envoyé à {$user->email} (Utilisateur) dans la base de données {$databaseName}");
            }

           // Dans envoyerEmail_whatsap_UserAppel
            if ($rdvUserIds->contains($user->id)) {
                // Récupérer la date RDV
                $rdvDate = null;
                if ($traitement->rdv && $traitement->rdv->rdv) {
                    $rdvDate = $traitement->rdv->rdv;
                }

                Mail::to($user->email)->send(new ScheduledEmail(
                    2, $user, $projet, null, $prospectName, null, 'appel', $prospectPhone, $rdvDate
                ));
                Log::info("Email de RDV appel envoyé à {$user->email} (Utilisateur) dans la base de données {$databaseName}");
            }
        }

        // Send WhatsApp messages to user if phone number exists
       // self::sendWhatsAppToUser($user, $relanceUserIds, $rdvUserIds, $projet, $prospectName, $prospectPhone, $prospectPhone2, $databaseName);

    } catch (\Exception $e) {
        Log::error("Échec de l'envoi de l'email appel à l'utilisateur {$user->email}: " . $e->getMessage());
        // Continue with WhatsApp even if email fails
       // self::sendWhatsAppToUser($user, $relanceUserIds, $rdvUserIds, $projet, $prospectName, $prospectPhone, $prospectPhone2, $databaseName);
    }
}

/*private static function sendWhatsAppToUser($user, $relanceUserIds, $rdvUserIds, $projet, $prospectName, $prospectPhone, $prospectPhone2, $databaseName)
{
    if (!$user->phone) {
        Log::warning("Aucun numéro de téléphone associé à l'utilisateur ID {$user->id} dans la base de données {$databaseName}");
        return;
    }

    try {
        if ($relanceUserIds->contains($user->id)) {
            $message = "Bonjour {$user->name} {$user->prenom}, vous avez une relance à effectuer pour le prospect {$prospectName}.";
            if ($prospectPhone) {
                $message .= " Numéro téléphone: {$prospectPhone}";
                if ($prospectPhone2 && $prospectPhone2 != $prospectPhone) {
                    $message .= " / {$prospectPhone2}";
                }
            }
            DatabaseHelper::sendWhatsAppMessage($user->phone, $message);
            Log::info("WhatsApp de relance appel envoyé à l'utilisateur {$user->phone} dans la base de données {$databaseName}");
        }

        if ($rdvUserIds->contains($user->id)) {
            $rdvDate = $rdvUserIds->first() ? optional(Relance_Rdv_Appel::on('temp')->where('user_id', $user->id)->where('type', 2)->first())->rdv : null;
            $rdvDateFormatted = $rdvDate ? Carbon::parse($rdvDate)->format('d/m/Y H:i') : '';

            $message = "Bonjour {$user->name} {$user->prenom}, vous avez un rendez-vous prévu le {$rdvDateFormatted} avec le prospect {$prospectName} pour le projet {$projet}.";
            if ($prospectPhone) {
                $message .= " Numéro du prospect: {$prospectPhone}";
                if ($prospectPhone2 && $prospectPhone2 != $prospectPhone) {
                    $message .= " / {$prospectPhone2}";
                }
            }
            DatabaseHelper::sendWhatsAppMessage($user->phone, $message);
            Log::info("WhatsApp de RDV appel envoyé à l'utilisateur {$user->phone} dans la base de données {$databaseName}");
        }
    } catch (\Exception $e) {
        Log::error("Échec de l'envoi WhatsApp à l'utilisateur {$user->phone}: " . $e->getMessage());
    }
}*/


public static function envoyer_email_whatsapp_echeance($databases)
{
    foreach ($databases as $database) {
       // $databaseName = 'Erp_' . $database->raison_sociale_concatene . '_' . $database->id;
$databaseName =  env('DB_DATABASE');
        // Switch to the temporary database
        $connection = DatabaseHelper::Connection_database($databaseName);
        config(['database.connections.temp' => $connection]);
        DB::connection('temp')->setDatabaseName($connection['database']);
        DB::reconnect('temp');

        if (Schema::connection('temp')->hasTable('avances')) {
            $today = Carbon::now()->toDateString();

            // Récupérer les avances avec leurs relations
            $echeances = Avance::on('temp')
                ->whereDate('echeance', $today)
                ->whereHas('reservation', function($query) {
                    $query->where('etat', 1);
                })
                ->with(['reservation.aquereurs.client.prospect', 'reservation.projet', 'reservation.bien', 'user'])
                ->get();

            if ($echeances->isEmpty()) {
                Log::info("Aucune échéance trouvée pour la base de données {$databaseName}");
                continue;
            }

            // ========== ENVOI WHATSAPP AUX CLIENTS ==========
            foreach ($echeances as $avance) {
                if (!$avance->reservation || !$avance->reservation->aquereurs) {
                    continue;
                }

                foreach ($avance->reservation->aquereurs as $aquereur) {
                    $client = $aquereur->client;

                    if (!$client) {
                        continue;
                    }

                    // Récupérer les téléphones (priorité client, puis prospect)
                    $telephone1 = self::getClientTelephone1($client);
                    $telephone2 = self::getClientTelephone2($client);

                    if ($telephone1 || $telephone2) {
                        $projet = $avance->reservation->projet->nom ?? '';
                        $bien = $avance->reservation->bien->propriete_dite_bien ?? '';
                        $montant = number_format($avance->montant, 2, ',', ' ');
                        $echeance = Carbon::parse($avance->echeance)->format('d/m/Y');
                        $clientName = self::getClientName($client);

                        // Variables pour le template WhatsApp
                        $variables = [
                            "1" => $clientName,
                            "2" => $montant,
                            "3" => $echeance,
                            "4" => $projet,
                            "5" => $bien
                        ];
                            // Vérifier si le template est configuré
                            if (!self::isTemplateConfigured(self::$TEMPLATE_ECHEANCE_PAIEMENT, 'TEMPLATE_ECHEANCE_PAIEMENT')) {
                                Log::info("WhatsApp échéance non envoyé - Template non configuré dans .env");
                            } else {

                                    // Envoyer au numéro principal
                                    if ($telephone1 && !empty($telephone1)) {

                                        try {
                                            DatabaseHelper::sendWhatsAppTemplate(
                                                $telephone1,
                                                self::$TEMPLATE_ECHEANCE_PAIEMENT,
                                                $variables,
                                                $avance->reservation->projet_id ?? null
                                            );
                                            Log::info("WhatsApp échéance envoyé à {$telephone1} (Client) dans {$databaseName}");
                                        } catch (\Exception $e) {
                                            Log::error("Échec de l'envoi WhatsApp à {$telephone1}: " . $e->getMessage());
                                        }

                                    }
                                    // Envoyer au second numéro si différent
                                    if ($telephone2 && !empty($telephone2) && $telephone2 != $telephone1) {

                                            try {
                                            DatabaseHelper::sendWhatsAppTemplate(
                                                $telephone2,
                                                self::$TEMPLATE_ECHEANCE_PAIEMENT,
                                                $variables,
                                                $avance->reservation->projet_id ?? null
                                            );
                                            Log::info("WhatsApp échéance envoyé au second numéro {$telephone2} dans {$databaseName}");
                                            } catch (\Exception $e) {
                                                Log::error("Échec de l'envoi WhatsApp à {$telephone1}: " . $e->getMessage());
                                            }

                                    }

                            }
                    }
                    else {
                        Log::warning("Aucun téléphone trouvé pour le client ID {$client->id}");
                    }
                }
            }

            // **Envoi d'emails aux utilisateurs (users)**
            $userIds = $echeances->pluck('user_id')->unique();
            if ($userIds->isNotEmpty()) {
                $users = User::on('temp')->whereIn('id', $userIds)->get();

                foreach ($users as $user) {
                    try {
                        $userEcheances = $echeances->where('user_id', $user->id);

                        foreach ($userEcheances as $avance) {
                            $projet = $avance->reservation->projet->nom ?? null;
                            $bien = $avance->reservation->bien->propriete_dite_bien ?? null;
                            $clientName = self::getClientName($avance->reservation->aquereurs->first()->client);

                            Mail::to($user->email)->send(new ScheduledEmail(3, $user, $projet, $bien, $clientName, $avance));
                            Log::info("Email d'échéance envoyé à l'utilisateur {$user->email} dans la base de données {$databaseName}");
                        }
                    } catch (\Exception $e) {
                        Log::error("Échec de l'envoi de l'email à l'utilisateur {$user->email}: " . $e->getMessage());
                    }
                }
            }

            // **Envoi d'emails aux clients**
            foreach ($echeances as $avance) {
                if (!$avance->reservation || !$avance->reservation->aquereurs) {
                    Log::warning("Aucune réservation ou acquéreurs pour l'avance ID {$avance->id}");
                    continue;
                }

                foreach ($avance->reservation->aquereurs as $aquereur) {
                    $client = $aquereur->client;

                    if (!$client) {
                        Log::warning("Client introuvable pour l'aquéreur ID {$aquereur->id}");
                        continue;
                    }

                    // Récupérer l'email (priorité client, puis prospect)
                    $emailTo = self::getClientEmail($client);

                    if ($emailTo) {
                        try {
                            $projet = $avance->reservation->projet->nom ?? null;
                            $bien = $avance->reservation->bien->propriete_dite_bien ?? null;

                            Mail::to($emailTo)->send(new ScheduledEmail(4, $client, $projet, $bien, null, $avance));
                            Log::info("Email d'échéance envoyé à {$emailTo} (Client ID: {$client->id}) dans {$databaseName}");

                        } catch (\Exception $e) {
                            Log::error("Échec de l'envoi de l'email à {$emailTo}: " . $e->getMessage());
                        }
                    } else {
                        Log::warning("Aucun email pour le client ID {$client->id}");
                    }
                }
            }

            Log::info("Processus d'envoi des emails et WhatsApp terminé pour la base de données {$databaseName}");
        }
    }
}

/**
 * Récupérer le téléphone principal du client (priorité client, puis prospect)
 */
private static function getClientTelephone1($client)
{
    if (!$client) {
        return null;
    }

    // Priorité au téléphone du client
    if (!empty($client->telephone_num1)) {
        return $client->telephone_num1;
    }

    // Sinon, téléphone du prospect associé
    if ($client->prospect && !empty($client->prospect->telephone)) {
        return $client->prospect->telephone;
    }

    return null;
}

/**
 * Récupérer le téléphone secondaire du client (priorité client, puis prospect)
 */
private static function getClientTelephone2($client)
{
    if (!$client) {
        return null;
    }

    // Priorité au second téléphone du client
    if (!empty($client->telephone_num2)) {
        return $client->telephone_num2;
    }

    // Sinon, second téléphone du prospect associé
    if ($client->prospect && !empty($client->prospect->telephone_num2)) {
        return $client->prospect->telephone_num2;
    }

    return null;
}

/**
 * Récupérer l'email du client (priorité client, puis prospect)
 */
private static function getClientEmail($client)
{
    if (!$client) {
        return null;
    }

    // Priorité à l'email du client
    if (!empty($client->email)) {
        return $client->email;
    }

    // Sinon, email du prospect associé
    if ($client->prospect && !empty($client->prospect->email)) {
        return $client->prospect->email;
    }

    return null;
}

/**
 * Récupérer le nom complet du client (priorité client, puis prospect)
 */
private static function getClientName($client)
{
    if (!$client) {
        return 'Client';
    }

    // Priorité au nom du client
    if (!empty($client->nom) && !empty($client->prenom)) {
        return trim($client->nom . ' ' . $client->prenom);
    }

    if (!empty($client->nom)) {
        return $client->nom;
    }

    // Sinon, nom du prospect associé
    if ($client->prospect) {
        if (!empty($client->prospect->nom) && !empty($client->prospect->prenom)) {
            return trim($client->prospect->nom . ' ' . $client->prospect->prenom);
        }
        if (!empty($client->prospect->nom)) {
            return $client->prospect->nom;
        }
    }

    return 'Client';
}




    public static function sendImportEmail($imp, $to_email)
    {
        if($to_email != null) {
            if($imp->user_id==0){
                 $superadmin = \DB::connection('mysql') // Use your main connection name
                    ->table('users')
                    ->where('role', 1) // Superadmin role
                    ->first();

                $name=$superadmin->name . ' ' . $superadmin->prenom;
            }else{
                                $name= $imp->user->name . ' ' . $imp->user->prenom;

            }
            // Préparer les données pour l'email
            $emailData = [
                'adminName' =>$name,
                'fichier' => $imp->fichier,
                'link_import' => env('FRONTEND_URL').'/histo-importation/'.$imp->id,
                'dateCreation' => $imp->created_at,
                'statut' => $imp->statut,
            ];

            // Ajouter les détails d'erreur si statut = 3
            if($imp->message_echou) {
                $errorDetails = json_decode($imp->message_echou, true);
                if($errorDetails) {
                    $emailData['message_echou'] = $errorDetails;
                    $emailData['total_lignes'] = $errorDetails['total_lignes'] ?? 0;
                }
            }

            Mail::send('emails.message_import', $emailData, function ($message) use ($to_email, $imp) {
                $message->to($to_email)
                    ->subject('Résultat importation fichier : ' . $imp->created_at->format('d/m/Y H:i'));
                $message->from(env('MAIL_USERNAME'), 'Tracimo ');
            });

            \Log::info("Email de résultat d'importation envoyé à: {$to_email}");
        }
    }
    public static function import_fichiers($databases)
        {
            foreach ($databases as $database) {
                //$databaseName = 'Erp_' . $database->raison_sociale_concatene . '_' . $database->id;
$databaseName =  env('DB_DATABASE');
                $connection = DatabaseHelper::Connection_database($databaseName);
                config(['database.connections.temp' => $connection]);
                DB::connection('temp')->setDatabaseName($connection['database']);
                DB::reconnect('temp');

                if (Schema::connection('temp')->hasTable('imports')) {
                    $imports = Import::on('temp')->whereIn('type',['0','3'])->where('statut', '0')->get();
                    \Log::info("import des fichiers de la base de données '{$databaseName}'");

                    foreach ($imports as $imp) {
                        $store = 0;
                        $importResult = null;

                        if ($imp->statut == 2 || $imp->statut == 3) {
                            \Log::info("Skipping import {$imp->id} - already processed");
                            continue;
                        }


                            // Fallback to a default admin email
                            $to_email = $imp->user->email;


                        if ($imp->statut != '1') {
                            $imp->statut = '1';
                            $imp->save();
                        }

                        try {
                            $projet = Projet::on('temp')->findOrFail($imp->projet_id);
                                if($imp->type==0){
                                            // Appeler la méthode d'import avec l'ID de l'import
                                        if ($projet->nbre_blocs > 0 && $projet->nbre_immeubles > 0) {
                                            $importResult = ImportExcelHelper::ImportStockByProjetWithoutTranche(
                                                null, $imp->data, $imp->projet_id, 1, $imp->id
                                            );
                                            $store = 1;
                                        } elseif ($projet->nbre_immeubles == 0 && $projet->nbre_blocs > 0) {
                                            $importResult = ImportExcelHelper::ImportStockByProjetWithoutTrancheAndImmeuble(
                                                null, $imp->data, $imp->projet_id, 1, $imp->id
                                            );
                                            $store = 1;
                                        } elseif ($projet->nbre_blocs == 0 && $projet->nbre_immeubles > 0) {
                                            $importResult = ImportExcelHelper::ImportStockByProjetWithoutTrancheAndBloc(
                                                null, $imp->data, $imp->projet_id, 1, $imp->id
                                            );
                                            $store = 1;
                                        } elseif ($projet->nbre_blocs == 0 && $projet->nbre_immeubles == 0) {
                                            $importResult = ImportExcelHelper::ImportStockByProjetWithoutTrancheAndBlocAndImmeuble(
                                                null, $imp->data, $imp->projet_id, 1, $imp->id
                                            );
                                            $store = 1;
                                        }

                                }elseif($imp->type==3){

                                            $importResult = ImportExcelHelper::Import_Prospect($imp->data, $imp->projet_id, $imp->id);
                                            $store = 1;
                                }

                            if ($store == 1 && $importResult) {
                                $imp->refresh();


                                \Log::info("Import {$imp->id} completed: {$importResult['success']} success, {$importResult['errors']} errors");

                                Config::set('broadcasting.default', 'pusher_notify');
                                $imp->load('user');
                                    // Déterminer le type d'import pour le message
                                    $importTypeLabel = ($imp->type == 3) ? "des Prospects" : "des Biens";
                                    $successMessage = ($imp->type == 3)
                                        ? "Fichier des Prospects importé avec succès"
                                        : "Fichier des Biens importé avec succès";
                                    $errorMessage = ($imp->type == 3)
                                        ? "Import des Prospects terminé avec {$importResult['errors']} erreur(s) - Vérifiez les détails"
                                        : "Import des Biens terminé avec {$importResult['errors']} erreur(s) - Vérifiez les détails";

                                    $data_notif = [
                                        'lien' => '/histo-importation/' . $imp->id,
                                        'date' => Carbon::now(),
                                        'type' => $importResult['errors'] > 0 ? 35 : 29,
                                        'description' => $importResult['errors'] > 0
                                            ? $errorMessage
                                            : $successMessage,
                                        'user_id' => $imp->user ? $imp->user->user_id_origin : null ,
                                        'projet_id' => $imp->projet_id,
                                    ];


                                $notif_helper = new NotificationHelper();
                                $req = new \Illuminate\Http\Request();
                                $notif_helper->storeNotification($req->merge($data_notif));

                                if ($to_email) {
                                    self::sendImportEmail($imp, $to_email);
                                }
                            }

                        } catch (\Exception $e) {
                            $imp->statut = '3';
                            $imp->message_echou = $e->getMessage();
                            $imp->date_echou = now();
                            $imp->save();
                            \Log::error("Import failed for projet {$imp->projet_id}: " . $e->getMessage());

                            // Envoyer notification d'échec
                            Config::set('broadcasting.default', 'pusher_notify');
                            $imp->load('user');

                            $data_notif = [
                                'lien' => '/histo-importation/' . $imp->id,
                                'date' => Carbon::now(),
                                'type' => 35,
                                'description' => 'Échec d\'importation du fichier',
                                'user_id' => $imp->user ? $imp->user->user_id_origin :null ,
                                'projet_id' => $imp->projet_id,
                            ];
                            $notif_helper = new NotificationHelper();
                            $req = new \Illuminate\Http\Request();
                            $notif_helper->storeNotification($req->merge($data_notif));

                            if ($to_email) {
                                self::sendImportEmail($imp, $to_email);
                            }
                        }
                    }
                }
            }
        }


    /*********en Masse*********** */

    public static function edit_biens_titre_foncier_en_masse($databases)
        {
            foreach ($databases as $database) {
                //$databaseName = 'Erp_' . $database->raison_sociale_concatene . '_' . $database->id;
$databaseName =  env('DB_DATABASE');
                // Switch to the temporary database
                $connection = DatabaseHelper::Connection_database($databaseName);
                config(['database.connections.temp' => $connection]);
                DB::connection('temp')->setDatabaseName($connection['database']);
                DB::reconnect('temp');

                if (Schema::connection('temp')->hasTable('imports')) {
                    $imports = Import::on('temp')->whereIn('type',['1','2'])->where('statut','0')->with('user')->get();
                    \Log::info("import des fichiers en masse du base de donne  '. $databaseName.");

                    foreach($imports as $imp) {


                            // Fallback to a default admin email
                            $to_email = $imp->user->email;



                        // Set import status to "en_cours" (1) only if it's not already
                        if($imp->statut != '1') {
                            $imp->statut = '1';
                            $imp->save();
                        }

                        try {
                            $projet = Projet::on('temp')->findOrfail($imp->projet_id);
                            $result = ImportExcelHelper::ImportEdit_biens_edit_titre_foncier_EnMasse($imp->data, $imp->projet_id,$imp->type);
                            // Update import with results from importerDonnees_masse
                            $imp = Import::on('temp')->find($imp->id); // Refresh the import object

                            if(isset($result['error_count']) && $result['error_count'] > 0) {
                                // Import completed with errors - set status to "echoue" (3)
                                $imp->statut = '3';
                                $imp->message_echou = json_encode([
                                    'total_lignes' => $result['total'] ?? 0,
                                    'lignes_reussies' => $result['success_count'] ?? 0,
                                    'lignes_echouees' => $result['error_count'] ?? 0,
                                    'erreurs' => $result['errors'] ?? []
                                ]);
                                $imp->ligne_echou = $result['error_count'] ?? 0;
                                $imp->date_echou = now();

                                // Log partial success
                                \Log::info("Import partially completed for projet_id: {$imp->projet_id}, import_id: {$imp->id}, success: {$result['success_count']}, errors: {$result['error_count']}");
                            } else {
                                // Import completed successfully
                                $imp->statut = '2';
                                \Log::info("Import completed successfully for projet_id: {$imp->projet_id}, import_id: {$imp->id}");
                            }

                            $imp->save();

                            // Send notification
                            Config::set('broadcasting.default', 'pusher_notify');
                            $imp->load('user');

                            if($imp->statut == '2') {
                                // Completely successful import
                                $data_notif = [
                                    'lien' => '/histo-importation/' . $imp->id,
                                    'date' => Carbon::now(),
                                    'type' => 29,
                                    'description' => $imp->type==1?'Fichier des Biens en masse importé avec succès':'Titres fonciers en masse importé avec succès',
                                    'user_id' => $imp->user ? $imp->user->user_id_origin:null ,
                                    'projet_id' => $imp->projet_id,
                                ];
                            } elseif($imp->statut == '3') {
                                // Import with errors
                                $data_notif = [
                                    'lien' => '/histo-importation/' . $imp->id,
                                    'date' => Carbon::now(),
                                    'type' => 35,
                                    'description' => 'Import terminé avec des erreurs - Vérifiez les détails',
                                    'user_id' => $imp->user ? $imp->user->user_id_origin :null ,
                                    'projet_id' => $imp->projet_id,
                                ];
                            }

                            if(isset($data_notif)) {
                                $notif_helper = new NotificationHelper();
                                $req = new \Illuminate\Http\Request();
                                $notif_helper->storeNotification($req->merge($data_notif));
                            }

                            // Send email
                            if($to_email != null) {
                                self::sendImportEmail($imp, $to_email);
                            }

                        } catch (\Exception $e) {
                            // If import failed completely
                            $imp = Import::on('temp')->find($imp->id); // Refresh
                            $imp->statut = '3';
                            $imp->message_echou = 'une erreur s\'est produite veuillez relancer votre import';
                            $imp->date_echou = now();
                            $imp->save();

                            \Log::error("Import failed for projet {$imp->projet_id}: " . $e->getMessage());

                            // Send notification for failed import
                            Config::set('broadcasting.default', 'pusher_notify');
                            $imp->load('user');

                            $data_notif = [
                                'lien' => '/histo-importation/' . $imp->id,
                                'date' => Carbon::now(),
                                'type' => 35,
                                'description' => 'Échec d\'importation du fichier',
                                'user_id' => $imp->user ? $imp->user->user_id_origin : null,
                                'projet_id' => $imp->projet_id,
                            ];

                            $notif_helper = new NotificationHelper();
                            $req = new \Illuminate\Http\Request();
                            $notif_helper->storeNotification($req->merge($data_notif));

                            if($to_email != null) {
                                self::sendImportEmail($imp, $to_email);
                            }
                        }
                    }
                }
            }
        }

        public static function liberer_bien_pre_reserve($databases)
        {
            Config::set('broadcasting.default', 'pusher_notify');

            foreach ($databases as $database) {
               // $databaseName = 'Erp_' . $database->raison_sociale_concatene . '_' . $database->id;
$databaseName =  env('DB_DATABASE');
                // Switch to the temporary database
                $connection = DatabaseHelper::Connection_database($databaseName);
                config(['database.connections.temp' => $connection]);
                DB::connection('temp')->setDatabaseName($connection['database']);
                DB::reconnect('temp');

                //
                if (Schema::connection('temp')->hasTable('biens')) {
                    $cur_date = Carbon::now();
                    $biens = Bien::on('temp')->where('etat', 'PRE_RESERVATION')->get();
                    foreach ($biens as $bien) {
                        if ($bien->last_pre_reservation != null) {
                            $diff_in_days = Carbon::parse($bien->last_pre_reservation->date_pre_reserve)->diffInDays($cur_date);
                            if ($diff_in_days >= $bien->projet->limite_annulation_reservation + $bien->projet->prolongation_reservation) {
                                //if diff>=3 libere bien
                                Bien_Helper::libererBien($bien->id, 'console', null,false);
                            } else if ($diff_in_days == $bien->projet->limite_annulation_reservation) {

                                //if diff==2 notif to commercial
                                if ($bien->last_pre_reservation->visite_id != null) {
                                    $data_notif = [
                                        'lien' => '/visites/show/' . $bien->last_pre_reservation->visite->origin_id,
                                        'date' => Carbon::now(),
                                        'type' => 4,
                                        'description' => 'Régler situation du bien pre reservé',
                                        'user_id' => $bien->last_pre_reservation->visite->user->user_id_origin,
                                        'visite_id' => $bien->last_pre_reservation->visite->id,
                                        'prospect_id' => $bien->last_pre_reservation->visite->prospect_id,
                                        'projet_id' => $bien->last_pre_reservation->visite->projet_id,

                                    ];
                                    $notif_helper = new NotificationHelper();
                                    $req=new \Illuminate\Http\Request();
                                    $notif_helper->storeNotification($req->merge($data_notif));

                                }
                                /* else{
                            //appel_id!=null notification au detail appel ==>pas ecnours

                            }*/

                            }
                        }
                    }
                }
            }
        }

        public static function Deletedatabase($databases)
        {
            foreach ($databases as $database) {
               // $databaseName = 'Erp_' . $database->raison_sociale_concatene . '_' . $database->id;
               $databaseName =  env('DB_DATABASE');
                DB::statement("DROP DATABASE IF EXISTS `$databaseName`");
                DB::table('users')->where('societe_id', $database->id)->delete();
                DB::table('societes')->where('id', $database->id)->delete();
            }
        }

        public function databaseExists($databaseName)
        {
            $query = "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$databaseName'";
            $database = DB::select($query);

            return count($database) > 0;
        }
    }
