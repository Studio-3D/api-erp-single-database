<?php

namespace App\Http\Helpers;

use App\Models\Societe;
use App\Models\Bien;
use App\Models\Proposition;
use App\Models\Frein;
use App\Models\Notification;
use App\Models\PreReservation;
use App\Http\Helpers\Bien_Helper;
use App\Http\Helpers\NotificationHelper;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;

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
                  $propositions= Proposition::on('temp')
                        ->whereIn('user_id', $notConnectedUsers)  // id  from  users from mother db
                        ->get();
                       foreach($propositions as $prop){
                        $bien=Bien::on('temp')->findorfail($prop->bien_id);
                        if($bien->etat=='ENCOURS_DE_PROPOSITION'){
                            Bien_Helper::libererBien($bien->id,'console',null);
                        }
                        $prop->forceDelete();
                       }

                    \Log::info("Deleted propositions for not connected users in $databaseName.");
                }
                if ($connectedUsers->isNotEmpty()) {
                    $propositions=Proposition::on('temp')
                    ->select('id', 'created_at','bien_id')
                    ->whereIn('user_id', $connectedUsers)
                    ->whereNotIn('id', function ($query) use ($connectedUsers) {  $query->select(DB::raw('MAX(id)'))->from('propositions')->whereIn('user_id', $connectedUsers)
                    ->groupBy('user_id'); })->get();
                        foreach($propositions as $prop){
                        $bien=Bien::on('temp')->findorfail($prop->bien_id);
                        if($bien->etat=='ENCOURS_DE_PROPOSITION'){
                            Bien_Helper::libererBien($bien->id,'console',null);
                        }
                        $prop->forceDelete();
                       }
                    \Log::info("Deleted older propositions for connected users in $databaseName.");

                }
            } else {
                \Log::info("Table 'propositions' does not exist in $databaseName.");
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
                    $biens=Bien::on('temp')->where('etat','PRE_RESERVATION')->get();
                    foreach($biens as $bien){
                        if($bien->last_pre_reservation!=null){
                            $diff_in_days =Carbon::parse($bien->last_pre_reservation->date_pre_reserve)->diffInDays($cur_date);
                            if ($diff_in_days >= $bien->projet->limite_annulation_reservation+$bien->projet->prolongation_reservation) {
                                //if diff>=3 libere bien
                                Bien_Helper::libererBien($bien->id,'console',null);
                            }
                            else if($diff_in_days == $bien->projet->limite_annulation_reservation){

                                //if diff==2 notif to commercial
                                if($bien->last_pre_reservation->visite_id!=null){
                                    NotificationHelper::storeNotification(
                                        '/visites/show/'.$bien->last_pre_reservation->visite->origin_id,Carbon::now(),4,'Régler situation du bien pre reservé',$bien->last_pre_reservation->visite->user->user_id_origin,$bien->last_pre_reservation->visite->id,$bien->last_pre_reservation->visite->prospect_id,$bien->last_pre_reservation->visite->projet_id
                                    );
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
