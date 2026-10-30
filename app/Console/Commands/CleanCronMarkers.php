<?php

namespace App\Console\Commands;

use App\Http\Helpers\DatabaseHelper;
use App\Models\Societe;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanCronMarkers extends Command
{
    protected $signature = 'prospects:clean-cron-markers';
    protected $description = 'Remet à NULL le marqueur 99 posé par le cron Réceptif';

   public function handle(): int
{
    $hier = Carbon::now()->subDay()->toDateString();   // ✅ hier
    $total = 0;

    foreach (Societe::all() as $societe) {
        try {
            $databaseName = env('DB_DATABASE');
            $connection   = DatabaseHelper::Connection_database($databaseName);
            config(['database.connections.temp' => $connection]);
            DB::purge('temp');
            DB::reconnect('temp');

            $avantHier = Carbon::now()->subDays(2)->toDateString();   // 28/10

            // ✅ Nettoie les 99 créés HIER
            $updated = DB::connection('temp')
                ->table('statut_prospects')
                ->whereDate('created_at', $avantHier)          // ✅ hier
                ->where('type_traitement_rdv_relance', 99)
                ->update(['type_traitement_rdv_relance' => null]);

            $total += $updated;
            Log::info("[CleanCron] Société #{$societe->id} : {$updated} ligne(s) nettoyée(s)");
        } catch (\Exception $e) {
            Log::error("[CleanCron] Erreur société #{$societe->id}: " . $e->getMessage());
        }
    }

    $this->info("✅ {$total} ligne(s) nettoyée(s)");
    return Command::SUCCESS;
}
}
