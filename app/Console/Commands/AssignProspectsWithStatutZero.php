<?php

namespace App\Console\Commands;

use App\Http\Helpers\DatabaseHelper;
use App\Models\Notification;
use App\Models\Societe;
use App\Models\User;
use App\Events\NotificationEvent;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
/*affecter les prospect autmaique  pour les prospest avec last statut 0*/
class AssignProspectsWithStatutZero extends Command
{
    protected $signature = 'prospects:assign-statut-zero
                            {--dry-run : Simulation sans affectation}
                            {--limit=100 : Nombre maximum de prospects à traiter}';

    protected $description = 'Affecte les prospects dont le dernier statut est 0 (En attente)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit  = (int) $this->option('limit');

        Log::info('[CRON Statut 0] Début affectation', [
            'dry_run' => $dryRun,
            'limit'   => $limit,
        ]);

        $societes = Societe::all();
        if ($societes->isEmpty()) {
            Log::warning('[CRON Statut 0] Aucune société trouvée');
            $this->error('❌ Aucune société trouvée');
            return Command::SUCCESS;
        }

        $totalAssigned = 0;
        $totalFailed   = 0;
        $totalSkipped  = 0;

        foreach ($societes as $societe) {
            try {
                $databaseName = env('DB_DATABASE');
                $connection = DatabaseHelper::Connection_database($databaseName);
                config(['database.connections.temp' => $connection]);
                DB::purge('temp');
                DB::reconnect('temp');

                Log::info("[CRON Statut 0] Connexion configurée pour société #{$societe->id}");

                // ✅ Prospects éligibles (DB direct)
                $prospects = DB::connection('temp')
                    ->table('prospects')
                    ->whereNull('commercial_affecte')
                    ->whereNull('deleted_at')
                    ->whereIn('id', function ($subQuery) {
                        $subQuery->select('sp.prospect_id')
                            ->from('statut_prospects as sp')
                            ->whereNull('sp.deleted_at')
                            ->whereIn('sp.id', function ($inner) {
                                $inner->selectRaw('MAX(sp2.id)')
                                    ->from('statut_prospects as sp2')
                                    ->whereColumn('sp2.prospect_id', 'sp.prospect_id')
                                    ->whereNull('sp2.deleted_at');
                            })
                            ->where('sp.statut', '0');
                    })
                    ->orderBy('id', 'asc')
                    ->limit($limit)
                    ->get(['id', 'telephone', 'nom', 'prenom', 'projet_id']);

                if ($prospects->isEmpty()) {
                    Log::info("[CRON Statut 0] Aucun prospect éligible pour société #{$societe->id}");
                    $this->info("✅ Société #{$societe->id} : aucun prospect à affecter");
                    continue;
                }

                Log::info("[CRON Statut 0] {$prospects->count()} prospect(s) éligible(s) pour société #{$societe->id}");
                $this->info("📋 Société #{$societe->id} : {$prospects->count()} prospect(s) à affecter");

                foreach ($prospects as $prospect) {
                    $prospectId = (int) $prospect->id;
                    $projetId   = (int) $prospect->projet_id;

                    if (!$projetId) {
                        Log::warning("[CRON Statut 0] Prospect #{$prospectId} sans projet_id — ignoré");
                        $totalSkipped++;
                        continue;
                    }

                    if ($dryRun) {
                        $this->line("🟡 [DRY-RUN] Prospect #{$prospectId} ({$prospect->telephone}) serait affecté");
                        continue;
                    }

                    try {
                        // ═══════════════════════════════════════════════════════
                        // 🎯 SÉLECTION DU COMMERCIAL
                        //     (logique 50% prioritaire, identique au helper)
                        // ═══════════════════════════════════════════════════════
                        $targetCommercialId = $this->pickTargetCommercialWithCycle($projetId);

                        if ($targetCommercialId === null) {
                            $totalFailed++;
                            Log::warning("[CRON Statut 0] Aucun commercial disponible", [
                                'prospect_id' => $prospectId,
                                'projet_id'   => $projetId,
                            ]);
                            $this->warn("⚠️ Prospect #{$prospectId} : aucun commercial disponible");
                            continue;
                        }

                        // ✅ Récupérer le system user (admin)
                        $systemUser = DB::connection('temp')
                            ->table('users')
                            ->where('role', 1)
                            ->whereNull('deleted_at')
                            ->first();

                        // ═══════════════════════════════════════════════════════
                        // 🎯 AFFECTATION EN DB DIRECTE
                        // ═══════════════════════════════════════════════════════
                        DB::connection('temp')->table('prospects')
                            ->where('id', $prospectId)
                            ->update([
                                'commercial_affecte'   => $targetCommercialId,
                                'affecte_par_admin_id' => $systemUser->id ?? $targetCommercialId,
                                'date_affectation'     => Carbon::now(),
                                'updated_at'           => Carbon::now(),
                            ]);

                        // ✅ Incrément + reset last_affected
                        DB::connection('temp')->table('users')
                            ->where('id', $targetCommercialId)
                            ->increment('nb_prospects');

                        DB::connection('temp')->table('users')
                            ->where('id', $targetCommercialId)
                            ->update(['last_affected' => 1]);

                        DB::connection('temp')->table('users')
                            ->where('id', '!=', $targetCommercialId)
                            ->where('role', 3)
                            ->where('is_actif', 1)
                            ->whereNull('deleted_at')
                            ->update(['last_affected' => 0]);

                        $totalAssigned++;
                        $this->line("✅ Prospect #{$prospectId} → Commercial #{$targetCommercialId}");

                        // ✅ 1. Créer le statut "Affecté" (6)
                        try {
                            DB::connection('temp')->table('statut_prospects')->insert([
                                'prospect_id'                 => $prospectId,
                                'statut'                      => '6',
                                'date_traitement'             => now()->toDateString(),
                                'user_id_traite'              => $systemUser->id ?? null,
                                'commentaire'                 => 'Prospect affecté automatiquement (dernier statut = 0)',
                                'type_traitement_rdv_relance' => 0,
                                'created_at'                  => now(),
                                'updated_at'                  => now(),
                            ]);

                            Log::info('[CRON Statut 0] Statut Affecté (6) créé', [
                                'prospect_id'   => $prospectId,
                                'commercial_id' => $targetCommercialId,
                            ]);
                        } catch (\Throwable $e) {
                            Log::error('[CRON Statut 0] Erreur création statut Affecté', [
                                'prospect_id' => $prospectId,
                                'error'       => $e->getMessage(),
                            ]);
                        }

                        // ✅ 2. Créer une notification
                        try {
                            $prospectNom = trim(($prospect->nom ?? '') . ' ' . ($prospect->prenom ?? ''));
                            if ($prospectNom === '') {
                                $prospectNom = $prospect->telephone ?? ('#' . $prospectId);
                            }

                            $notification = new Notification();
                            $notification->setConnection('temp');
                            $notification->date = now();
                            $notification->type = 53;
                            $notification->description_type = mb_substr(
                                "📋 *NOUVEAU PROSPECT AFFECTÉ*\n\n"
                                . "👤 Prospect : {$prospectNom}\n"
                                . "📞 Téléphone : " . ($prospect->telephone ?? 'N/A') . "\n"
                                . "💬 Motif : Statut = En attente (0)\n"
                                . "🏢 Projet ID : {$projetId}",
                                0, 255, 'UTF-8'
                            );
                            $notification->lien = "/crm/prospects/{$prospectId}";
                            $notification->role = 3;
                            $notification->user_id = $targetCommercialId;
                            $notification->projet_id = $projetId;
                            $notification->prospect_id = $prospectId;
                            $notification->save();

                            Config::set('broadcasting.default', 'pusher_notify');
                            broadcast(new NotificationEvent($notification->id));

                            Log::info('[CRON Statut 0] Notification créée', [
                                'notification_id' => $notification->id,
                                'commercial_id'   => $targetCommercialId,
                                'prospect_id'     => $prospectId,
                            ]);
                        } catch (\Throwable $e) {
                            Log::error('[CRON Statut 0] Erreur création notification', [
                                'prospect_id' => $prospectId,
                                'error'       => $e->getMessage(),
                            ]);
                        }

                    } catch (\Throwable $e) {
                        $totalFailed++;
                        Log::error("[CRON Statut 0] Exception affectation prospect #{$prospectId}", [
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                        $this->error("❌ Prospect #{$prospectId} : " . $e->getMessage());
                    }
                }

            } catch (\Exception $e) {
                Log::error("[CRON Statut 0] Erreur société #{$societe->id}: " . $e->getMessage());
                $this->error("❌ Société #{$societe->id} : " . $e->getMessage());
            }
        }

        $this->info("═══════════════════════════════════════");
        $this->info("✅ Affectés : {$totalAssigned}");
        $this->info("⚠️ Échecs  : {$totalFailed}");
        $this->info("⏭️ Ignorés : {$totalSkipped}");
        $this->info("═══════════════════════════════════════");

        Log::info('[CRON Statut 0] Fin affectation', [
            'assigned' => $totalAssigned,
            'failed'   => $totalFailed,
            'skipped'  => $totalSkipped,
        ]);

        return Command::SUCCESS;
    }

    /* =====================================================================
     |  🎯 LOGIQUE DU HELPER RECOPIÉE ICI (cycle 50% prioritaire + normaux)
     * ===================================================================== */

    /**
     * Sélectionne le commercial cible en appliquant la logique 50% du helper
     * (sans charger le Prospect en Eloquent)
     */
    private function pickTargetCommercialWithCycle(int $projetId): ?int
    {
        // 1. Récupérer les commerciaux éligibles pour le projet
        $commercials = $this->getEligibleCommercials($projetId);

        if ($commercials->isEmpty()) {
            Log::warning("[CRON Statut 0] Aucun commercial éligible", ['projet_id' => $projetId]);
            return null;
        }

        Log::info("[CRON Statut 0] Commerciaux éligibles", [
            'count'         => $commercials->count(),
            'ids'           => $commercials->pluck('id')->toArray(),
            'lead_resource' => $commercials->pluck('lead_resource', 'id')->toArray(),
            'nb_prospects'  => $commercials->pluck('nb_prospects', 'id')->toArray(),
        ]);

        // 2. Un seul commercial → direct
        if ($commercials->count() === 1) {
            return (int) $commercials->first()->id;
        }

        // 3. Construire le cycle 50% (prioritaires + normaux)
        $cycle = $this->buildCycle($commercials);

        if (empty($cycle)) {
            Log::warning("[CRON Statut 0] Cycle vide, fallback au premier");
            return (int) $commercials->first()->id;
        }

        // 4. Choisir selon le cycle
        $totalAssigned = $commercials->sum('nb_prospects');
        $cycleLength   = count($cycle);
        $index         = $totalAssigned % $cycleLength;
        $targetId      = $cycle[$index];

        // Vérifier que le commercial existe dans la collection
        $target = $commercials->firstWhere('id', $targetId);

        if (!$target) {
            Log::error("[CRON Statut 0] Target introuvable dans la collection", [
                'target_id' => $targetId,
                'cycle'     => $cycle,
            ]);
            return (int) $commercials->first()->id;
        }

        Log::info("[CRON Statut 0] Cycle-based pick", [
            'total_assigned' => $totalAssigned,
            'cycle_length'   => $cycleLength,
            'index'          => $index,
            'cycle'          => $cycle,
            'target_id'      => $targetId,
            'target_name'    => $target->name . ' ' . $target->prenom,
            'lead_resource'  => $target->lead_resource ?? 0,
        ]);

        return (int) $targetId;
    }

    /**
     * Récupère les commerciaux éligibles pour un projet
     * (copie de CommercialAssignmentHelper::getEligibleCommercials, mais avec DB::table pour éviter Eloquent)
     */
    private function getEligibleCommercials(int $projetId)
    {
        // ⚠️ On utilise User::on() car User N'A PAS de $with problématique.
        //    Mais si un jour User a un $with, il faudra aussi bypasser.
        return User::on('temp')
            ->whereIn('id', function ($q) use ($projetId) {
                $q->select('user_id')
                  ->from('user_projets')
                  ->where('projet_id', $projetId)
                  ->whereNull('deleted_at');
            })
            ->where('role', 3)
            ->where('is_actif', 1)
            ->whereNull('users.deleted_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * Construit le cycle 50% (copie de CommercialAssignmentHelper::buildCycle)
     * - Prioritaire : lead_resource = 1
     * - Normaux     : lead_resource = 0
     * - Ratio      : 50% prioritaire / 50% normal
     */
    private function buildCycle($commercials): array
    {
        $prioritaires = $commercials->where('lead_resource', 1)->values();
        $normaux      = $commercials->where('lead_resource', 0)->values();

        // Aucun prioritaire OU aucun normal → round-robin pur
        if ($prioritaires->isEmpty() || $normaux->isEmpty()) {
            $cycle = $commercials->pluck('id')->toArray();
            Log::info('[CRON Statut 0] Round-robin cycle (pas de mix prioritaire)', [
                'cycle' => $cycle,
            ]);
            return $cycle;
        }

        $nbPrioritaires = $prioritaires->count();
        $nbNormaux      = $normaux->count();

        // 50% global : total slots prio = nbNormaux
        $base  = intdiv($nbNormaux, $nbPrioritaires);
        $reste = $nbNormaux % $nbPrioritaires;

        $cycle = [];
        $slotsParPrio = [];

        foreach ($prioritaires as $idx => $c) {
            $slots = $base + ($idx < $reste ? 1 : 0);
            if ($slots === 0) $slots = 1;
            $slotsParPrio[$c->id] = $slots;
            for ($i = 0; $i < $slots; $i++) {
                $cycle[] = $c->id;
            }
        }

        foreach ($normaux as $c) {
            $cycle[] = $c->id;
            $slotsParPrio[$c->id] = 1;
        }

        $ratioPrio = round(
            (count($cycle) - $nbNormaux) / count($cycle) * 100,
            1
        );

        Log::info('[CRON Statut 0] Cycle 50% global', [
            'nb_prio'        => $nbPrioritaires,
            'nb_normaux'     => $nbNormaux,
            'slots_par_prio' => $slotsParPrio,
            'cycle'          => $cycle,
            'cycle_length'   => count($cycle),
            'ratio_prio'     => $ratioPrio . '%',
        ]);

        return $cycle;
    }
}
