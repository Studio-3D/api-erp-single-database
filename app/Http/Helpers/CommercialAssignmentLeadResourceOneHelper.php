<?php

namespace App\Http\Helpers;

use App\Models\User;
use App\Models\Prospect;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Helper d'affectation dédié à l'agent virtuel WhatsApp.
 *
 * ✅ Round-robin UNIQUEMENT sur les commerciaux avec lead_resource = 1.
 * ✅ Ne touche PAS à CommercialAssignmentHelper (utilisé ailleurs).
 * ✅ Ne supprime rien d'existant.
 */
class CommercialAssignmentLeadResourceOneHelper
{
    /**
     * Affecter un prospect à un commercial lead_resource=1 (round-robin).
     *
     * @param  int       $prospectId
     * @param  int       $projetId
     * @param  string    $connexion
     * @param  int|null  $systemUserId
     * @param  string    $sourceLabel
     * @return array{success: bool, commercial_id?: int, commercial?: User, message?: string}
     */
    public static function assignProspect(
        int $prospectId,
        int $projetId,
        string $connexion = 'temp',
        ?int $systemUserId = null,
        string $sourceLabel = 'WhatsApp'
    ): array {
        try {
            Log::info("[{$sourceLabel}] 🔄 Starting lead_resource=1 assignment", [
                'prospect_id' => $prospectId,
                'projet_id'   => $projetId,
            ]);

            // 1. Prospect
            $prospect = Prospect::on($connexion)->find($prospectId);
            if (!$prospect) {
                Log::error("[{$sourceLabel}] ❌ Prospect not found", ['prospect_id' => $prospectId]);
                return ['success' => false, 'message' => 'Prospect not found'];
            }

            // 2. Si déjà affecté → ne pas réaffecter
            if (!empty($prospect->commercial_affecte)) {
                Log::info("[{$sourceLabel}] ℹ️ Prospect already assigned", [
                    'prospect_id'   => $prospectId,
                    'commercial_id' => $prospect->commercial_affecte,
                ]);
                return [
                    'success'       => true,
                    'commercial_id' => (int) $prospect->commercial_affecte,
                    'message'       => 'Already assigned',
                ];
            }

            // 3. Commerciaux éligibles avec lead_resource = 1
            $commercials = self::getEligibleCommercialsLeadResourceOne($projetId, $connexion);

            Log::info("[{$sourceLabel}] 👥 Commerciaux lead_resource=1 éligibles", [
                'count'         => $commercials->count(),
                'ids'           => $commercials->pluck('id')->toArray(),
                'nb_prospects'  => $commercials->pluck('nb_prospects', 'id')->toArray(),
            ]);

            if ($commercials->isEmpty()) {
                Log::warning("[{$sourceLabel}] ⚠️ Aucun commercial lead_resource=1 actif", [
                    'projet_id' => $projetId,
                ]);
                return ['success' => false, 'message' => 'No active commercials with lead_resource=1'];
            }

            // 4. Choisir le commercial cible (round-robin sur lead_resource=1)
            $targetCommercial = self::pickTargetCommercial($commercials, $sourceLabel);

            if (!$targetCommercial) {
                Log::error("[{$sourceLabel}] ❌ Failed to pick commercial");
                return ['success' => false, 'message' => 'Pick failed'];
            }

            // 5. Transaction
            DB::connection($connexion)->beginTransaction();

            try {
                $systemUser = $systemUserId
                    ? User::on($connexion)->find($systemUserId)
                    : User::on($connexion)->where('role', 1)->whereNull('deleted_at')->first();

                // 5a. Mettre à jour le prospect
                $prospect->commercial_affecte   = $targetCommercial->id;
                $prospect->affecte_par_admin_id = $systemUser?->id ?? $targetCommercial->id;
                $prospect->date_affectation     = Carbon::now();
                $prospect->save();

                // 5b. Incrément atomique + last_affected = 1
                User::on($connexion)
                    ->where('id', $targetCommercial->id)
                    ->increment('nb_prospects');

                User::on($connexion)
                    ->where('id', $targetCommercial->id)
                    ->update(['last_affected' => 1]);

                // 5c. Reset last_affected pour les autres lead_resource=1
                User::on($connexion)
                    ->where('id', '!=', $targetCommercial->id)
                    ->where('lead_resource', 1)
                    ->where('role', 3)
                    ->where('is_actif', 1)
                    ->whereNull('deleted_at')
                    ->update(['last_affected' => 0]);

                DB::connection($connexion)->commit();

                Log::info("[{$sourceLabel}] ✅ Prospect assigned (lead_resource=1)", [
                    'prospect_id'   => $prospectId,
                    'commercial_id' => $targetCommercial->id,
                    'commercial'    => $targetCommercial->name . ' ' . $targetCommercial->prenom,
                ]);

                return [
                    'success'       => true,
                    'commercial_id' => $targetCommercial->id,
                    'commercial'    => $targetCommercial,
                ];

            } catch (\Exception $e) {
                DB::connection($connexion)->rollBack();
                Log::error("[{$sourceLabel}] ❌ Transaction failed: " . $e->getMessage());
                return ['success' => false, 'message' => $e->getMessage()];
            }

        } catch (\Exception $e) {
            Log::error("[{$sourceLabel}] ❌ Assignment failed: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Commerciaux éligibles avec lead_resource = 1.
     */
    public static function getEligibleCommercialsLeadResourceOne(int $projetId, string $connexion = 'temp')
    {
        return User::on($connexion)
            ->whereIn('id', function ($q) use ($projetId) {
                $q->select('user_id')
                  ->from('user_projets')
                  ->where('projet_id', $projetId)
                  ->whereNull('deleted_at');
            })
            ->where('role', 3)
            ->where('lead_resource', 1)     // ✅ FILTRE CLÉ
            ->where('is_actif', 1)
            ->whereNull('users.deleted_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * Choisir le commercial cible (round-robin simple sur lead_resource=1).
     */
    private static function pickTargetCommercial($commercials, string $sourceLabel): ?User
    {
        // Cas 1 : un seul commercial lead_resource=1
        if ($commercials->count() === 1) {
            Log::info("[{$sourceLabel}] ✅ Un seul commercial lead_resource=1", [
                'commercial_id' => $commercials->first()->id,
            ]);
            return $commercials->first();
        }

        // Cas 2 : plusieurs → round-robin basé sur le total affecté
        $totalAssigned = $commercials->sum('nb_prospects');
        $cycleLength   = $commercials->count();
        $index         = $totalAssigned % $cycleLength;
        $target        = $commercials->values()[$index] ?? $commercials->first();

        Log::info("[{$sourceLabel}] ✅ Round-robin lead_resource=1", [
            'total_assigned' => $totalAssigned,
            'cycle_length'   => $cycleLength,
            'index'          => $index,
            'target_id'      => $target->id,
            'target_name'    => $target->name . ' ' . $target->prenom,
        ]);

        return $target;
    }
}
