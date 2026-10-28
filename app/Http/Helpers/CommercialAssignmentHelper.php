<?php

namespace App\Http\Helpers;

use App\Models\User;
use App\Models\Prospect;
use App\Models\StatutProspect;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CommercialAssignmentHelper
{
    /**
     * Affecter un prospect à un commercial selon le cycle 50% prioritaire.
     *
     * @param  int       $prospectId   ID du prospect (table prospects)
     * @param  int       $projetId     ID du projet
     * @param  string    $connexion    Connexion DB (par défaut 'temp')
     * @param  int|null  $systemUserId ID du system user (affecte_par_admin_id)
     * @param  string    $sourceLabel  Label pour logs (ex: 'LandingPage', 'Facebook')
     * @return array{success: bool, commercial_id?: int, commercial?: User, message?: string}
     */
    public static function assignProspect(
        int $prospectId,
        int $projetId,
        string $connexion = 'temp',
        ?int $systemUserId = null,
        string $sourceLabel = 'Unknown'
    ): array {
        try {
            Log::info("🔄 [{$sourceLabel}] Starting assignment", [
                'prospect_id' => $prospectId,
                'projet_id'   => $projetId,
            ]);

            // ============================================================
            // 1️⃣ Récupérer le prospect
            // ============================================================
            $prospect = Prospect::on($connexion)->find($prospectId);
            if (!$prospect) {
                Log::error("❌ [{$sourceLabel}] Prospect not found", ['prospect_id' => $prospectId]);
                return ['success' => false, 'message' => 'Prospect not found'];
            }

            // ============================================================
            // 2️⃣ Récupérer les commerciaux éligibles (filtre pivot explicite)
            // ============================================================
            $commercials = self::getEligibleCommercials($projetId, $connexion);

            Log::info("👥 [{$sourceLabel}] Commerciaux éligibles", [
                'count'         => $commercials->count(),
                'ids'           => $commercials->pluck('id')->toArray(),
                'nb_prospects'  => $commercials->pluck('nb_prospects', 'id')->toArray(),
                'lead_resource' => $commercials->pluck('lead_resource', 'id')->toArray(),
            ]);

            if ($commercials->isEmpty()) {
                Log::warning("⚠️ [{$sourceLabel}] No active commercials", ['projet_id' => $projetId]);
                return ['success' => false, 'message' => 'No active commercials'];
            }

            // ============================================================
            // 3️⃣ Choisir le commercial cible
            // ============================================================
            $targetCommercial = self::pickTargetCommercial($commercials, $sourceLabel);

            if (!$targetCommercial) {
                Log::error("❌ [{$sourceLabel}] Failed to pick commercial");
                return ['success' => false, 'message' => 'Pick failed'];
            }

            // ============================================================
            // 4️⃣ Transaction : mise à jour prospect + compteurs
            // ============================================================
            DB::connection($connexion)->beginTransaction();

            try {
                $systemUser = $systemUserId
                    ? User::on($connexion)->find($systemUserId)
                    : User::on($connexion)->where('role', 1)->whereNull('deleted_at')->first();

                // 4a. Mettre à jour le prospect
                $prospect->commercial_affecte   = $targetCommercial->id;
                $prospect->affecte_par_admin_id = $systemUser?->id ?? $targetCommercial->id;
                $prospect->date_affectation     = Carbon::now();
                $prospect->save();

                // 4b. Incrément atomique + last_affected = 1
                User::on($connexion)
                    ->where('id', $targetCommercial->id)
                    ->increment('nb_prospects');

                User::on($connexion)
                    ->where('id', $targetCommercial->id)
                    ->update(['last_affected' => 1]);

                // 4c. Reset last_affected pour les autres
                User::on($connexion)
                    ->where('id', '!=', $targetCommercial->id)
                    ->where('role', 3)
                    ->where('is_actif', 1)
                    ->whereNull('deleted_at')
                    ->update(['last_affected' => 0]);

                DB::connection($connexion)->commit();

                Log::info("✅ [{$sourceLabel}] Prospect assigned", [
                    'prospect_id'   => $prospectId,
                    'commercial_id' => $targetCommercial->id,
                    'commercial'    => $targetCommercial->name . ' ' . $targetCommercial->prenom,
                    'lead_resource' => $targetCommercial->lead_resource ?? 0,
                ]);

                return [
                    'success'       => true,
                    'commercial_id' => $targetCommercial->id,
                    'commercial'    => $targetCommercial,
                ];

            } catch (\Exception $e) {
                DB::connection($connexion)->rollBack();
                Log::error("❌ [{$sourceLabel}] Transaction failed: " . $e->getMessage());
                Log::error($e->getTraceAsString());
                return ['success' => false, 'message' => $e->getMessage()];
            }

        } catch (\Exception $e) {
            Log::error("❌ [{$sourceLabel}] Assignment failed: " . $e->getMessage());
            Log::error($e->getTraceAsString());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Récupérer les commerciaux éligibles pour un projet.
     * ⚠️ Filtre EXPLICITE sur user_projets.deleted_at (le fix).
     */
    public static function getEligibleCommercials(int $projetId, string $connexion = 'temp')
    {
        return User::on($connexion)
            ->whereIn('id', function ($q) use ($projetId) {
                $q->select('user_id')
                  ->from('user_projets')
                  ->where('projet_id', $projetId)
                  ->whereNull('deleted_at');   // ← LE FIX
            })
            ->where('role', 3)
            ->where('is_actif', 1)
            ->whereNull('users.deleted_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * Choisir le commercial cible selon le cycle.
     */
    private static function pickTargetCommercial($commercials, string $sourceLabel): ?User
    {
        // Cas 1 : un seul commercial
        if ($commercials->count() === 1) {
            Log::info("✅ [{$sourceLabel}] Only one commercial", [
                'commercial_id' => $commercials->first()->id,
            ]);
            return $commercials->first();
        }

        // Cas 2 : plusieurs → cycle
        $cycle = self::buildCycle($commercials, $sourceLabel);

        if (empty($cycle)) {
            Log::warning("⚠️ [{$sourceLabel}] Empty cycle, fallback to first");
            return $commercials->first();
        }

        $totalAssigned = $commercials->sum('nb_prospects');
        $cycleLength   = count($cycle);
        $index         = $totalAssigned % $cycleLength;
        $targetId      = $cycle[$index];

        $target = $commercials->firstWhere('id', $targetId);
        if (!$target) {
            Log::error("❌ [{$sourceLabel}] Target not found in collection", [
                'target_id' => $targetId,
                'cycle'     => $cycle,
            ]);
            return $commercials->first();
        }

        Log::info("✅ [{$sourceLabel}] Cycle-based pick", [
            'total_assigned' => $totalAssigned,
            'cycle_length'   => $cycleLength,
            'index'          => $index,
            'cycle'          => $cycle,
            'target_id'      => $targetId,
            'target_name'    => $target->name . ' ' . $target->prenom,
            'lead_resource'  => $target->lead_resource ?? 0,
        ]);

        return $target;
    }

    /**
     * Construire le cycle 50% global.
     *
     * Règle : total slots prioritaires = total slots normaux (= nb_normaux).
     * Les P prioritaires se partagent N slots équitablement.
     */
   public static function buildCycle($commercials, string $sourceLabel = 'Unknown'): array
{
    // ✅ Prioritaire : lead_resource = 1
    $prioritaires = $commercials->where('lead_resource', 1)->values();

    // ✅ Normaux : lead_resource = 0 OU 2
    $normaux = $commercials->whereIn('lead_resource', [0, 2])->values();

    // Aucun prioritaire OU aucun normal → round-robin pur
    if ($prioritaires->isEmpty() || $normaux->isEmpty()) {
        $cycle = $commercials->pluck('id')->toArray();
        Log::info("🔁 [{$sourceLabel}] Round-robin cycle (no priority mix)", [
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
        if ($slots === 0) $slots = 1;   // sécurité cas P > N
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

    Log::info("🔁 [{$sourceLabel}] Cycle 50% global", [
        'nb_prio'        => $nbPrioritaires,
        'nb_normaux'     => $nbNormaux,
        'slots_par_prio' => $slotsParPrio,
        'cycle'          => $cycle,
        'cycle_length'   => count($cycle),
        'ratio_prio'     => $ratioPrio . '%',
        'normaux_rule'   => 'lead_resource IN (0, 2)',   // ← trace pour debug
    ]);

    return $cycle;
}
}
