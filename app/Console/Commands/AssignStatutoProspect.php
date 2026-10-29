<?php

namespace App\Console\Commands;

use App\Enum\StatutProspectEnum;
use App\Http\Helpers\DatabaseHelper;
use App\Models\Prospect;
use App\Models\StatutProspect;
use App\Models\Societe;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AssignStatutoProspect extends Command
{
    protected $signature = 'prospects:assign-statut-prospect
                            {--limit=50 : Nombre maximum de prospects à traiter}
                            {--dry-run : Simulation sans enregistrement}';

    protected $description = 'Ajoute le statut Réceptif (9) aux prospects Avito/Mubawab affectés récemment (max 50/jour)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit  = (int) $this->option('limit');
        $now    = Carbon::now();

        Log::info('[CRON Réceptif Avito/Mubawab] Début', [
            'dry_run' => $dryRun,
            'limit'   => $limit,
            'time'    => $now->format('H:i'),
        ]);

        $societes = Societe::all();
        if ($societes->isEmpty()) {
            $this->error('❌ Aucune société trouvée');
            return Command::SUCCESS;
        }

        $totalUpdated = 0;

        foreach ($societes as $societe) {
            try {
                // ✅ FIX #1 : utiliser la base de LA société, pas env('DB_DATABASE')
                $databaseName = env('DB_DATABASE');
                $connection = DatabaseHelper::Connection_database($databaseName);
                config(['database.connections.temp' => $connection]);
                DB::purge('temp');
                DB::reconnect('temp');

                // ✅ FIX #3 : sécurité — nettoie les 99 des jours précédents (si clean 20:05 a raté)
                DB::connection('temp')
                    ->table('statut_prospects')
                    ->where('type_traitement_rdv_relance', 99)
                    ->whereDate('created_at', '<', $now->toDateString())
                    ->update(['type_traitement_rdv_relance' => 0]);

                // 1. Compter combien on a déjà traité AUJOURD'HUI pour ne pas dépasser 50
                $todayCount = DB::connection('temp')
                    ->table('statut_prospects')
                    ->whereDate('created_at', $now->toDateString())
                    ->where('statut', (string) StatutProspectEnum::Receptif->value)
                    ->where('type_traitement_rdv_relance', 99)
                    ->count();

                if ($todayCount >= $limit) {
                    Log::info("[CRON Réceptif Avito/Mubawab] Limite atteinte pour aujourd'hui ({$todayCount}/{$limit})", [
                        'societe_id' => $societe->id,
                    ]);
                    continue;
                }

                $remaining = $limit - $todayCount;
                $this->info("📋 Société #{$societe->id} : {$todayCount} traités aujourd'hui, reste {$remaining}");

                // 2. Récupérer les prospects éligibles
                $prospects = Prospect::on('temp')
                    ->whereIn('source', [1, 16])
                    ->whereNotNull('commercial_affecte')
                    ->whereBetween('date_affectation', ['2026-06-01 00:00:00', '2026-09-30 23:59:59'])
                    ->whereIn('id', function ($q) {
                        $q->select('sp.prospect_id')
                            ->from('statut_prospects as sp')
                            ->whereNull('sp.deleted_at')
                            ->whereIn('sp.id', function ($q2) {
                                $q2->selectRaw('MAX(id)')
                                    ->from('statut_prospects')
                                    ->whereColumn('prospect_id', 'sp.prospect_id')
                                    ->whereNull('deleted_at');
                            })
                            ->where('sp.statut', (string) StatutProspectEnum::Affecte->value);
                    })
                    ->orderBy('id', 'asc')
                    ->limit($remaining)
                    ->get();

                if ($prospects->isEmpty()) {
                    $this->info("✅ Société #{$societe->id} : aucun prospect éligible");
                    continue;
                }

                $this->info("🔄 Traitement de " . $prospects->count() . " prospect(s)...");

                                // ✅ Calcul de l'offset intelligent
                $nowIter = Carbon::now();
                $endTime = $nowIter->copy()->setTime(20, 0, 0);
                $minutesRestantes = max(1, $nowIter->diffInMinutes($endTime));
                $nbProspects = $prospects->count();
                $stepMinutes = $nbProspects > 1
                    ? max(1, min(5, intdiv($minutesRestantes, $nbProspects)))
                    : 0;

                $offset = 0;

                foreach ($prospects as $prospect) {
                    if ($dryRun) {
                        $this->line("🟡 [DRY] Prospect #{$prospect->id} → serait Réceptif");
                        continue;
                    }

                    // ✅ Heure de base = maintenant (pas figée)
                    $traitementTime = Carbon::now()->addMinutes($offset);

                    // ✅ Sécurité : ne jamais dépasser 20:00
                    $endTimeReal = Carbon::now()->setTime(20, 0, 0);
                    if ($traitementTime->greaterThan($endTimeReal)) {
                        $traitementTime = $endTimeReal->copy();
                    }

                    $statut = new StatutProspect();
                    $statut->setConnection('temp');
                    $statut->prospect_id                 = $prospect->id;
                    $statut->statut                      = (string) StatutProspectEnum::Receptif->value;
                    $statut->date_traitement             = $traitementTime;
                    $statut->user_id_traite              = $prospect->commercial_affecte;
                    $statut->commentaire                 = '';
                    $statut->type_traitement_rdv_relance = 99;
                    $statut->created_at                  = $traitementTime;
                    $statut->updated_at                  = $traitementTime;
                    $statut->save();

                     DB::connection('temp')
                    ->table('prospects')
                    ->where('id', $prospect->id)
                    ->update([
                        'date_traitement'    => $traitementTime,
                        'traite_par_user_id' => $prospect->commercial_affecte,
                    ]);

                    $totalUpdated++;
                    $offset += $stepMinutes;

                    $this->line("✅ Prospect #{$prospect->id} → Réceptif (date: {$traitementTime->format('d/m/Y H:i:s')})");
                }

            } catch (\Exception $e) {
                Log::error("[CRON Réceptif] Erreur société #{$societe->id}: " . $e->getMessage());
                $this->error("❌ Erreur société #{$societe->id} : " . $e->getMessage());
            }
        }

        $this->info("═══════════════════════════════");
        $this->info("✅ Total mis à jour : {$totalUpdated}");
        $this->info("═══════════════════════════════");

        return Command::SUCCESS;
    }
}
