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

        // Switch to the temporary database
        $connection = DatabaseHelper::Connection_database($databaseName);
        config(['database.connections.temp' => $connection]);
        DB::connection('temp')->setDatabaseName($connection['database']);
        DB::reconnect('temp');

        if (Schema::connection('temp')->hasTable('relances_rdv_visites')) {
            $today = Carbon::now()->toDateString();

            // Récupération des utilisateurs avec leur type (1: relance, 2: rdv)
            $relances = Relance_Rdv_visite::on('temp')
                ->where('type', 1)
                ->whereDate('date_relance', $today)
                ->pluck('user_id');

            $rdvs = Relance_Rdv_visite::on('temp')
                ->where('type', 2)
                ->whereDate('rdv', $today)
                ->pluck('user_id');

            $userIds = $relances->merge($rdvs)->unique();

            if ($userIds->isEmpty()) {
                Log::info('Aucun utilisateur à relancer ou notifier aujourd\'hui pour la base de données ' . $databaseName);
                continue;
            }

            // Récupérer les utilisateurs concernés
            $users = User::on('temp')->whereIn('id', $userIds)->get();

            foreach ($users as $user) {
                try {
                    // Vérification du type et envoi de l'email
                    $relanceExists = $relances->contains($user->id);
                    $rdvExists = $rdvs->contains($user->id);

                    if ($relanceExists) {
                        Mail::to($user->email)->send(new ScheduledEmail(1, $user));
                        Log::info("Email de relance envoyé à {$user->email} dans la base de données {$databaseName}");
                    }

                    if ($rdvExists) {
                        Mail::to($user->email)->send(new ScheduledEmail(2, $user));
                        Log::info("Email de rdv envoyé à {$user->email} dans la base de données {$databaseName}");
                    }

                } catch (\Exception $e) {
                    // Log en cas d'échec
                    Log::error("Échec de l'envoi de l'email à {$user->email}: " . $e->getMessage());
                }
            }

            Log::info('Processus de relance et notification terminé pour la base de données ' . $databaseName);
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

            //
            if (Schema::connection('temp')->hasTable('relances_rdv_visites')) {
                $today = Carbon::now()->toDateString();
                $userIds = Avance::on('temp')->whereDate('echeance', $today)->pluck('user_id');

                if ($userIds->isEmpty()) {
                    Log::info(message: 'Aucun date de relance aujourd\'hui pour la base de données ' . $databaseName);
                    continue;
                }

                // Récupérer les utilisateurs dans la table Users
                $users = User::on('temp')->whereIn('id', $userIds)->get();

                foreach ($users as $user) {
                    try {
                        // Envoi de l'email
                        Mail::to($user->email)->send(new ScheduledEmail(3, $user));
                        Log::info("Email envoyé à {$user->email} dans la base de données {$databaseName}");
                    } catch (\Exception $e) {
                        // Log en cas d'échec
                        Log::error(message: "Échec de l'envoi de l'email à {$user->email}: " . $e->getMessage());
                    }
                }

                Log::info('Processus de relance terminé pour la base de données ' . $databaseName);
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
