<?php

namespace App\Http\Helpers;

use App\Http\Helpers\Bien_Helper;
use App\Http\Helpers\NotificationHelper;
use App\Mail\ScheduledEmail;
use App\Models\Avance;
use App\Models\Bien;
use App\Models\Notification;
use App\Models\Proposition;
use App\Models\Relance_Rdv_visite;
use App\Models\Societe;
use App\Models\User;
use Carbon\Carbon;
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
        if (!$societe_id) {
            $societe_id = Auth::guard('api')->user()->societe_id;
        }
        $societe = Societe::findOrfail($societe_id);
        $DatabaseName = 'Erp_' . $societe->raison_sociale_concatene . '_' . $societe_id;
        $connection = DatabaseHelper::Connection_database($DatabaseName);
        config(['database.connections.temp' => $connection]);
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

            // Switch to the temporary database
            $connection = DatabaseHelper::Connection_database($databaseName);
            config(['database.connections.temp' => $connection]);
            DB::connection('temp')->setDatabaseName($connection['database']);
            DB::reconnect('temp');
            // Retrieve users from the mother database
            $notConnectedUsers = DB::table('users')->where('is_connected', 0)->pluck('id');
            $connectedUsers = DB::table('users')->where('is_connected', 1)->pluck('id');

            //
            if (Schema::connection('temp')->hasTable('propositions')) {
                if ($notConnectedUsers->isNotEmpty()) {
                    $propositions = Proposition::on('temp')
                        ->whereIn('user_id', $notConnectedUsers) // id  from  users from mother db
                        ->get();
                    foreach ($propositions as $prop) {
                        $bien = Bien::on('temp')->findorfail($prop->bien_id);
                        if ($bien->etat == 'ENCOURS_DE_PROPOSITION') {
                            Bien_Helper::libererBien($bien->id, 'console', null);
                        }
                        $prop->forceDelete();
                    }

                    \Log::info("Deleted propositions for not connected users in $databaseName.");
                }
                if ($connectedUsers->isNotEmpty()) {
                    $propositions = Proposition::on('temp')
                        ->select('id', 'created_at', 'bien_id')
                        ->whereIn('user_id', $connectedUsers)
                        ->whereNotIn('id', function ($query) use ($connectedUsers) {$query->select(DB::raw('MAX(id)'))->from('propositions')->whereIn('user_id', $connectedUsers)
                                ->groupBy('user_id');})->get();
                    foreach ($propositions as $prop) {
                        $bien = Bien::on('temp')->findorfail($prop->bien_id);
                        if ($bien->etat == 'ENCOURS_DE_PROPOSITION') {
                            Bien_Helper::libererBien($bien->id, 'console', null);
                        }
                        $prop->forceDelete();
                    }
                    \Log::info("Deleted older propositions for connected users in $databaseName.");

                }
            } else {
                \log::info("Table 'propositions' does not exist in $databaseName.");
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
                $relances = Relance_Rdv_visite::on('temp')
                    ->with(['visite.prospect'])
                    ->whereDate('date_relance', $today)
                    ->where('type', 1)
                    ->get();

                $rdvs = Relance_Rdv_visite::on('temp')
                    ->with(['visite.prospect'])
                    ->whereDate('rdv', $today)
                    ->where('type', 2)
                    ->get();

                $prospectNotifications = $relances->merge($rdvs);

                // Récupérer les relances et RDVs pour les utilisateurs
                $relanceUserIds = Relance_Rdv_visite::on('temp')
                    ->where('type', 1)
                    ->whereDate('date_relance', $today)
                    ->pluck('user_id');

                $rdvUserIds = Relance_Rdv_visite::on('temp')
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
                                $notif_helper->storeNotification($request->merge($data_notif));

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
