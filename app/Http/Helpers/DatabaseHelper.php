<?php

namespace App\Http\Helpers;

use App\Http\Helpers\Bien_Helper;
use App\Http\Helpers\NotificationHelper;
use App\Mail\ScheduledEmail;
use App\Models\Avance;
use App\Models\Bien;
use App\Models\Notification;
use App\Models\Proposition;
use App\Models\Relance_Rdv_Visite;
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
    public function createNewClientDatabase($raison_sociale, $societe_id)
    {

        $databaseName = 'Erp_' . $raison_sociale . '_' . $societe_id;

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

        $migration = $this->runMigrations($connection);

        if ($migration === true) {
            return response()->json(['message' => 'Database created and migrations ran successfully.']);
        } else {
            return response()->json(['message' => 'Error running migrations.']);
        }
    }

    public function runMigrations($connection)
    {
        config(['database.connections.temp' => $connection]);

        $migration = Artisan::call('migrate', [
            '--database' => 'temp',
            '--path' => 'database/migrations/migrations_societe',
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
            $DatabaseName = 'Erp_' . $societe->raison_sociale_concatene . '_' . $societe_id;
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
        $databaseName = 'Erp_' . $database->raison_sociale_concatene . '_' . $database->id;

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
                            Bien_Helper::libererBien($bien->id, 'console', null);
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
        $databaseName = 'Erp_' . $database->raison_sociale_concatene . '_' . $database->id;

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

    public static function deleteWebhookTable($databases)
    {
        foreach ($databases as $database) {
            $databaseName = 'Erp_' . $database->raison_sociale_concatene . '_' . $database->id;

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
            $databaseName = 'Erp_' . $database->raison_sociale_concatene . '_' . $database->id;

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

    public static function envoyer_email_rdv_rlc($databases)
    {
        foreach ($databases as $database) {
            $databaseName = 'Erp_' . $database->raison_sociale_concatene . '_' . $database->id;

            // Configurer la connexion à la base de données temporaire
            $connection = DatabaseHelper::Connection_database($databaseName);
            config(['database.connections.temp' => $connection]);
            DB::connection('temp')->setDatabaseName($connection['database']);
            DB::reconnect('temp');

            if (Schema::connection('temp')->hasTable('relances_rdv_visites')) {
                $today = Carbon::now()->toDateString();

                // Récupérer les relances et RDVs pour les prospects
                $relances = Relance_Rdv_Visite::on('temp')
                    ->with(['visite.prospect'])
                    ->whereDate('date_relance', $today)
                    ->where('type', 1)
                    ->get();

                $rdvs = Relance_Rdv_Visite::on('temp')
                    ->with(['visite.prospect'])
                    ->whereDate('rdv', $today)
                    ->where('type', 2)
                    ->get();

                $prospectNotifications = $relances->merge($rdvs);

                // Récupérer les relances et RDVs pour les utilisateurs
                $relanceUserIds = Relance_Rdv_Visite::on('temp')
                    ->where('type', 1)
                    ->whereDate('date_relance', $today)
                    ->pluck('user_id');

                $rdvUserIds = Relance_Rdv_Visite::on('temp')
                    ->where('type', 2)
                    ->whereDate('rdv', $today)
                    ->pluck('user_id');

                $userIds = $relanceUserIds->merge($rdvUserIds)->unique();
                $users = User::on('temp')->whereIn('id', $userIds)->get();

                // Envoi des emails aux prospects
                foreach ($prospectNotifications as $notification) {
                    $prospectEmail = $notification->visite->prospect->email ?? null;

                    if ($prospectEmail) {
                        try {
                            $emailType = $notification->type; // 1: Relance, 2: RDV
                            Mail::to($prospectEmail)->send(new ScheduledEmail(4, $notification));

                            $logMessage = $emailType === 1
                                ? "Email de relance envoyé à {$prospectEmail} (Prospect)"
                                : "Email de RDV envoyé à {$prospectEmail} (Prospect)";
                            Log::info($logMessage . " dans la base de données {$databaseName}");
                        } catch (\Exception $e) {
                            Log::error("Échec de l'envoi de l'email au prospect {$prospectEmail}: " . $e->getMessage());
                        }
                    } else {
                        Log::warning("Aucun email associé au prospect pour la visite ID {$notification->visite_id} dans la base de données {$databaseName}");
                    }
                }

                // Envoi des emails aux utilisateurs
                foreach ($users as $user) {
                    try {
                        $relanceExists = $relanceUserIds->contains($user->id);
                        $rdvExists = $rdvUserIds->contains($user->id);

                        if ($relanceExists) {
                            Mail::to($user->email)->send(new ScheduledEmail(1, $user));
                            Log::info("Email de relance envoyé à {$user->email} (Utilisateur) dans la base de données {$databaseName}");
                        }

                        if ($rdvExists) {
                            Mail::to($user->email)->send(new ScheduledEmail(2, $user));
                            Log::info("Email de RDV envoyé à {$user->email} (Utilisateur) dans la base de données {$databaseName}");
                        }
                    } catch (\Exception $e) {
                        Log::error("Échec de l'envoi de l'email à l'utilisateur {$user->email}: " . $e->getMessage());
                    }
                }

                Log::info('Processus de relance et notification terminé pour la base de données ' . $databaseName);
            } else {
                Log::warning('La table relances_rdv_visites est absente dans la base de données ' . $databaseName);
            }
        }
    }


    public static function envoyer_email_echeance($databases)
    {
        foreach ($databases as $database) {
            $databaseName = 'Erp_' . $database->raison_sociale_concatene . '_' . $database->id;

            // Switch to the temporary database
            $connection = DatabaseHelper::Connection_database($databaseName);
            config(['database.connections.temp' => $connection]);
            DB::connection('temp')->setDatabaseName($connection['database']);
            DB::reconnect('temp');

            if (Schema::connection('temp')->hasTable('relances_rdv_visites')) {
                $today = Carbon::now()->toDateString();

                // Récupérer les avances avec leurs relations
                $echeances = Avance::on('temp')
                    ->whereDate('echeance', $today)
                    ->with(['reservation.aquereurs.client'])
                    ->get();

                if ($echeances->isEmpty()) {
                    Log::info("Aucune échéance trouvée pour la base de données {$databaseName}");
                    continue;
                }

                // **Envoi d'emails aux utilisateurs (users)**
                $userIds = $echeances->pluck('user_id')->unique(); // Extraire les user_ids associés
                if ($userIds->isNotEmpty()) {
                    $users = User::on('temp')->whereIn('id', $userIds)->get();

                    foreach ($users as $user) {
                        try {
                            Mail::to($user->email)->send(new ScheduledEmail(3, $user));
                            Log::info("Email envoyé à l'utilisateur {$user->email} dans la base de données {$databaseName}");
                        } catch (\Exception $e) {
                            Log::error("Échec de l'envoi de l'email à l'utilisateur {$user->email}: " . $e->getMessage());
                        }
                    }
                } else {
                    Log::info("Aucun utilisateur associé aux avances pour la base de données {$databaseName}");
                }

                // **Envoi d'emails aux clients (clients)**
                foreach ($echeances as $avance) {
                    if (!$avance->reservation || !$avance->reservation->aquereurs) {
                        Log::warning("Aucune réservation ou acquéreurs pour l'avance ID {$avance->id} dans la base de données {$databaseName}");
                        continue;
                    }

                    foreach ($avance->reservation->aquereurs as $aquereur) {
                        $client = $aquereur->client;

                        if ($client && !empty($client->email)) {
                            try {
                                Mail::to($client->email)->send(new ScheduledEmail(4, $avance));
                                Log::info("Email envoyé au client {$client->email} (Client ID: {$client->id}) dans la base de données {$databaseName}");
                            } catch (\Exception $e) {
                                Log::error("Échec de l'envoi de l'email au client {$client->email}: " . $e->getMessage());
                            }
                        } else {
                            Log::warning("Aucun email pour le client ID {$aquereur->client_id} dans la réservation ID {$avance->reservation->id}");
                        }
                    }
                }

                Log::info("Processus d'envoi des emails terminé pour la base de données {$databaseName}");
            }
        }
    }


    public static function envoyer_whatsapp_rdv_rlc($databases)
    {
        foreach ($databases as $database) {
            $databaseName = 'Erp_' . $database->raison_sociale_concatene . '_' . $database->id;

            // Configurer la connexion à la base de données temporaire
            $connection = DatabaseHelper::Connection_database($databaseName);
            config(['database.connections.temp' => $connection]);
            DB::connection('temp')->setDatabaseName($connection['database']);
            DB::reconnect('temp');

            if (Schema::connection('temp')->hasTable('relances_rdv_visites')) {
                $tomorrow = Carbon::tomorrow()->toDateString();
                $today = Carbon::now()->toDateString();
                log::info('tom'.$tomorrow);
               // Récupérer les relances pour les users
                $relances = Relance_Rdv_Visite::on('temp')
                    ->with(['user','visite.prospect'])
                    ->whereDate('date_relance', $today)
                    ->where('type', 1)
                    ->get();
                    log::info('count'.count($relances));
                //send msg to commercial pou relancer prospect

                    if(count($relances)>0){
                        foreach ($relances as $relance) {
                            $user = $relance->user;
                            if($user->phone!=null){
                                // Assuming the relationship exist
                                $prospect = $relance->visite->prospect->nom .' '.$relance->visite->prospect->prenom; // Adjust field name as needed
                                $phone_prospect=$relance->visite->prospect->telephone;
                                $message = "Bonjour, nous vous rappelons que vous avez une relance à effectuer pour le prospect $prospect. le Numéro telephone est :$phone_prospect";
                                DatabaseHelper::sendWhatsAppMessage($user->phone, $message);
                                Log::info(' message de relance whtsap send to prospect'.$user->phone);

                            }
                         }
                    }
                   // Récupérer les relances pour les prospects
                $rdvs = Relance_Rdv_Visite::on('temp')
                    ->with(['visite.prospect','user','visite.projet','visite.bien'])
                    ->whereDate('rdv', $tomorrow)
                    ->where('type', 2)
                    ->get();


                if(count($rdvs)>0){
                        foreach ($rdvs as $rdv) {
                            $prospect = $rdv->visite->prospect;
                            if($prospect!=null){
                                // Assuming the relationship exists
                                $phone = $prospect->telephone; // Adjust field name as needed
                                $heure = Carbon::parse($rdv->rdv)->format('H:i'); // Extracts only the time
                                $user=$rdv->user->name. ' '.$rdv->user->prenom;
                                $projet=$rdv->visite->projet->nom;
                                $bien=$rdv->visite->bien->propriete_dite_bien;
                                $message ="Madame/Monsieur, nous vous rappelons que vous avez un rendez-vous de suivi programmée demain à $heure avec le Commercial $user pour le Projet :$projet conecernant le bien $bien. Nous restons à votre disposition pour toute information complémentaire.";
                                DatabaseHelper::sendWhatsAppMessage($phone, $message);
                                Log::info(' message de rdv whtsap send to prospect'.$prospect->telephone. ' w rdv id ==>'.$rdv->id);

                            }
                    }
                }

                //SEND Msg to user for reminde prosect (table statut_prospects)
                        // Récupérer les relances pour les users
                        $rappel_prospects_users = StatutProspect::on('temp')
                        ->with(['user','prospect'])
                        ->whereDate('date_reppel', $today)
                        ->get();
                        log::info('count'.count($rappel_prospects_users));
                        if(count($rappel_prospects_users)>0){
                            foreach ($rappel_prospects_users as $rel) {
                                $user = $rel->user;
                                if($user->phone!=null){
                                    // Assuming the relationship exist
                                    $prospect = $rappel_prospects_users->prospect->nom .' '.$rappel_prospects_users->prospect->prenom; // Adjust field name as needed
                                    $phone_prospect=$rappel_prospects_users->prospect->telephone;
                                    $message = "Bonjour, nous vous rappelons que vous avez une relance à effectuer pour le prospect $prospect. le Numéro telephone est :$phone_prospect";
                                    DatabaseHelper::sendWhatsAppMessage($user->phone, $message);
                                    Log::info(' message de relance whtsap send to prospect'.$user->phone);

                                }
                             }
                        }


                Log::info('Processus de relance et notification terminé pour la base de données ' . $databaseName);
            } else {
                Log::warning('La table relances_rdv_visites est absente dans la base de données ' . $databaseName);
            }
        }
    }


    public static function sendWhatsAppMessage($phone, $message)
    {
        $instanceId =env('INSTANCE_ID_ULTRA_MSG');  // Replace with your instance ID
        $token = env('TOKEN_ULTRA_MSG');
        // Replace with your WhatsApp API provider details
        $response = Http::timeout(60)->post("https://api.ultramsg.com/$instanceId/messages/chat", [
            'to'   => $phone,
            'body' => $message,
            'token' => $token
        ]);

        Log::info("WhatsApp message sent to $phone: " . $response->body());
    }

    public static function import_fichiers($databases)
    {

        foreach ($databases as $database) {
            $databaseName = 'Erp_' . $database->raison_sociale_concatene . '_' . $database->id;

            // Switch to the temporary database
            $connection = DatabaseHelper::Connection_database($databaseName);
            config(['database.connections.temp' => $connection]);
            DB::connection('temp')->setDatabaseName($connection['database']);
            DB::reconnect('temp');

            //

            if (Schema::connection('temp')->hasTable('imports')) {
                $imports=Import::on('temp')->where('statut','0')->get();
                \Log::info("import des fichiers  du base de donne'. $databaseName.");

                foreach($imports as $imp){
                    $store=0;
                    $projet = Projet::on('temp')->findOrfail($imp->projet_id);
                    if($projet->nbre_tranches>0 && $projet->nbre_blocs>0 && $projet->nbre_immeubles>0){
                        \Log::info("enter in projet '. $imp->projet_id.");
                        ImportExcelHelper::ImportStockByProjet(null,$imp->data,$imp->projet_id,1);
                        $store=1;
                    }elseif($projet->nbre_tranches==0 && $projet->nbre_blocs==0 && $projet->nbre_immeubles==0){
                        ImportExcelHelper::ImportStockByProjetWithoutTrancheAndBlocAndImmeuble(null,$imp->data,$imp->projet_id,1);
                        $store=1;

                    }elseif($projet->nbre_blocs==0 && $projet->nbre_tranches==0 && $projet->nbre_immeubles>0){
                        ImportExcelHelper::ImportStockByProjetWithoutTrancheAndBloc(null,$imp->data,$imp->projet_id,1);
                        $store=1;
                    }
                    elseif($projet->nbre_tranches==0 && $projet->nbre_immeubles==0 && $projet->nbre_blocs>0){
                        ImportExcelHelper::ImportStockByProjetWithoutTrancheAndImmeuble(null,$imp->data,$imp->projet_id,1);
                        $store=1;
                    }
                    elseif($projet->nbre_tranches==0 && $projet->nbre_blocs>0 && $projet->nbre_immeubles>0){
                        ImportExcelHelper::ImportStockByProjetWithoutTranche(null,$imp->data,$imp->projet_id,1);
                        $store=1;
                    }
                    elseif($projet->nbre_blocs==0 && $projet->nbre_immeubles==0 && $projet->nbre_tranches>0){
                        ImportExcelHelper::ImportStockByProjetWithoutBlocAndImmeuble(null,$imp->data,$imp->projet_id,1);
                        $store=1;
                    }
                    elseif($projet->nbre_blocs==0 && $projet->nbre_tranches>0 && $projet->nbre_immeubles>0){
                        ImportExcelHelper::ImportStockByProjetWithoutBloc(null,$imp->data,$imp->projet_id,1);
                        $store=1;
                    }
                    elseif($projet->nbre_immeubles==0 && $projet->nbre_tranches>0 && $projet->nbre_blocs>0){
                        ImportExcelHelper::ImportStockByProjetWithoutImmeuble(null,$imp->data,$imp->projet_id,1);
                        $store=1;
                    }

                    if($store==1){
                        $imp->setConnection('temp');
                        //$imp->statut='1';
                        //$imp->save();
                        \Log::info("sort projet_id '. $imp->projet_id.");
                        Config::set('broadcasting.default', 'pusher_3');
                        $data_notif = [
                            'lien' => '/projets/show/' . $imp->projet_id,
                            'date' => Carbon::now(),
                            'type' => 29,
                            'description' => 'Fichier des Biens Importé ',
                            'user_id' => $imp->user->user_id_origin,
                            'projet_id' => $imp->projet_id,

                        ];
                        $notif_helper = new NotificationHelper();
                        $req=new \Illuminate\Http\Request();
                        $notif_helper->storeNotification($req->merge($data_notif));
                    }
                }
            }


        }
    }


    public static function liberer_bien_pre_reserve($databases)
    {
        Config::set('broadcasting.default', 'pusher_3');

        foreach ($databases as $database) {
            $databaseName = 'Erp_' . $database->raison_sociale_concatene . '_' . $database->id;

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
                            Bien_Helper::libererBien($bien->id, 'console', null);
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
            $databaseName = 'Erp_' . $database->raison_sociale_concatene . '_' . $database->id;
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
