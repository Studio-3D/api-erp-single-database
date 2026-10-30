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
                            {--limit=100 : Max par jour}
                            {--per-run=100 : Max par exécution}
                            {--dry-run : Simulation}';

    protected $description = 'Ajoute le statut Réceptif (9) aux prospects avec dernier statut 0/5/6 (max 50/jour, date = hier, heures aléatoires)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit  = (int) $this->option('limit');
        $perRun = (int) $this->option('per-run');
        $now    = Carbon::now();

        // ✅ Date de traitement = HIER ->subDay()
        $date = $now->copy();

        Log::info('[CRON Réceptif Avito/Mubawab] Début', [
            'dry_run'    => $dryRun,
            'limit'      => $limit,
            'per_run'    => $perRun,
            'date_cible' =>  $date->toDateString(),
        ]);

        $societes = Societe::all();
        if ($societes->isEmpty()) {
            $this->error('❌ Aucune société trouvée');
            return Command::SUCCESS;
        }

        $totalUpdated = 0;

        foreach ($societes as $societe) {
            try {
                $databaseName = env('DB_DATABASE');
                $connection   = DatabaseHelper::Connection_database($databaseName);
                config(['database.connections.temp' => $connection]);
                DB::purge('temp');
                DB::reconnect('temp');

                // ✅ Sécurité : nettoie les 99 des jours précédents
                DB::connection('temp')
                    ->table('statut_prospects')
                    ->where('type_traitement_rdv_relance', 99)
                    ->whereDate('created_at', '<',  $date->toDateString())
                    ->update(['type_traitement_rdv_relance' => 0]);

                // 1. Compter combien on a déjà traité HIER
                $todayCount = DB::connection('temp')
                    ->table('statut_prospects')
                    ->whereDate('date_traitement',  $date->toDateString())
                    ->whereIn('statut', ['2', '9', '11'])
                    ->where('type_traitement_rdv_relance', 99)
                    ->count();

                if ($todayCount >= $limit) {
                    Log::info("[CRON Réceptif] Limite atteinte pour hier ({$todayCount}/{$limit})", [
                        'societe_id' => $societe->id,
                    ]);
                    continue;
                }

                $remaining = min($limit - $todayCount, $perRun);
                $this->info("📋 Société #{$societe->id} : {$todayCount} traités hier, reste {$remaining}");

                // 2. Récupérer les prospects éligibles (dernier statut 0, 5 ou 6)
                $prospects = Prospect::on('temp')
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
                            ->whereIn('sp.statut', ['0', '5', '6']);
                    })
                    ->orderBy('id', 'asc')
                    ->limit($remaining)
                    ->get();

                if ($prospects->isEmpty()) {
                    $this->info("✅ Société #{$societe->id} : aucun prospect éligible");
                    continue;
                }

                $this->info("🔄 Traitement de " . $prospects->count() . " prospect(s)...");


// ✅ Générer des heures ALÉATOIRES entre 10:00 et 19:59
//    + garantir un écart minimum de 3 min entre chaque prospect
//    + compenser l'offset des 9 (75% × 3 min) pour ne pas dépasser 20:00
$heuresAleatoires = [];
$nbProspects      = $prospects->count();

// Nombre de 9 estimés (75%)
$nb9Estimes   = (int) round($nbProspects * 0.75);
$offsetTotal  = $nb9Estimes * 3;                    // minutes ajoutées par les 9
$minutesDispo = 1199 - 600 - $offsetTotal;          // plage restante après offset
$ecartMoyen   = $nbProspects > 0
    ? max(3, intdiv($minutesDispo, $nbProspects))   // écart minimum 3 min
    : 3;

$minuteCourante = 600; // 10:00
for ($i = 0; $i < $nbProspects; $i++) {
    // Écart aléatoire autour de l'écart moyen (± 2 min)
    $ecart = rand(max(1, $ecartMoyen - 2), $ecartMoyen + 2);
    $minuteCourante += $ecart;

    // Sécurité : ne pas dépasser 1199 (19:59) AVANT offset
    if ($minuteCourante > 1199 - $offsetTotal) {
        $minuteCourante = 1199 - $offsetTotal;
    }

    $heuresAleatoires[] =  $date->copy()->setTime(
        intdiv($minuteCourante, 60),
        $minuteCourante % 60,
        rand(0, 59)
    );
}
                $index        = 0;
                $offsetCumule = 0; // ✅ minutes à ajouter (cumul des +3min des statuts 9)

                foreach ($prospects as $prospect) {
                    // ✅ Statut aléatoire : 2 (20%), 9 (75%), 11 (5%)
                    $random = rand(1, 100);
                    if ($random <= 20) {
                        $statutChoisi = '2';
                    } elseif ($random <= 95) {
                        $statutChoisi = '9';
                    } else {
                        $statutChoisi = '11';
                    }

                    // ✅ Heure = heure aléatoire + offset cumulé
                    $traitementTime = $heuresAleatoires[$index]->copy()->addMinutes($offsetCumule);

                    // ✅ Plafond 20:00
                    $endTimeReal =  $date->copy()->setTime(20, 0, 0);
                    if ($traitementTime->greaterThan($endTimeReal)) {
                        $traitementTime = $endTimeReal->copy();
                    }

                    if ($dryRun) {
                        $this->line("🟡 [DRY] Prospect #{$prospect->id} → serait Statut {$statutChoisi} (date: {$traitementTime->format('d/m/Y H:i:s')})");

                        // ✅ Si statut 9, décaler les suivants
                        if ($statutChoisi === '9') {
                            $offsetCumule += 3;
                        }

                        $index++;
                        continue;
                    }

                    // ✅ Création du statut
                    $statut = new StatutProspect();
                    $statut->setConnection('temp');
                    $statut->prospect_id                 = $prospect->id;
                    $statut->statut                      = $statutChoisi;
                    $statut->date_traitement             = $traitementTime;
                    $statut->user_id_traite              = $prospect->commercial_affecte;
                    $statut->commentaire                 = '';
                    $statut->type_traitement_rdv_relance = 99;
                    $statut->created_at                  = $traitementTime; // ✅ aujourd'hui
                    $statut->updated_at                  = $traitementTime; // ✅ aujourd'hui
                    $statut->save();

                    // ✅ Mise à jour du prospect
                    DB::connection('temp')
                        ->table('prospects')
                        ->where('id', $prospect->id)
                        ->update([
                            'date_traitement'    => $traitementTime,
                            'traite_par_user_id' => $prospect->commercial_affecte,
                        ]);

                    // ✅ Si statut 9, décaler les suivants de +3 min
                    if ($statutChoisi === '9') {
                        $offsetCumule += 3;
                    }

                    $totalUpdated++;
                    $index++;

                    $this->line("✅ Prospect #{$prospect->id} → Statut {$statutChoisi} (date: {$traitementTime->format('d/m/Y H:i:s')})");
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
