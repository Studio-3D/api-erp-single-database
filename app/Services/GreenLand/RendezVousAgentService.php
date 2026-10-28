<?php

declare(strict_types=1);

namespace App\Services\GreenLand;

use App\Enum\StatutProspectEnum;
use App\Events\NotificationEvent;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Enregistrement d'un rendez-vous fixé par l'agent virtuel WhatsApp.
 *
 * Table cible : `rendez_vous_agent` (connexion 'temp').
 *
 * Règles d'affectation :
 *  - RDV "appel"  : commercial lead_resource = 1 (réaffectation si nécessaire)
 *  - RDV "visite" : commercial lead_resource = 2 (réaffectation si nécessaire,
 *                   mais garde si le prospect est déjà chez un lead_resource=2)
 *
 * Nettoyage des notifications :
 *  - En cas de réaffectation, les ANCIENNES notifications du prospect
 *    (créées au round-robin) sont SUPPRIMÉES, puis une NOUVELLE notification
 *    est créée pour le commercial final.
 *
 * Autres règles :
 *  - Statuts prospect : 5 (Nouveau_appel) → 6 (Affecte) → 3 (Rappel)
 *  - Idempotent : un seul RDV "confirme" par (prospect, projet, type)
 *  - Annulation : update = cancelled / opt_out / data_deletion
 */
class RendezVousAgentService
{
    private const TABLE        = 'rendez_vous_agent';
    private const SOURCE_AGENT = 'agent_virtuel';

    /**
     * Point d'entrée unique.
     */
 public function enregistrer(array $payload, int $projetId, ?int $prospectId): ?int
{
    if ($prospectId === null || $prospectId <= 0) {
        Log::warning('[RDV Agent] Prospect introuvable — RDV ignoré.', ['projet_id' => $projetId]);
        return null;
    }

    // 1. Garde-fou : ne créer le RDV que si la demande est RÉELLEMENT transmise
    if (!$this->isHandoffCompleted($payload)) {
        Log::info('[RDV Agent] RDV pas encore confirmé — ignoré.', [
            'prospect_id' => $prospectId,
            'event'       => $payload['event'] ?? null,
            'status'      => $payload['status'] ?? null,
        ]);
        return null;
    }

    try {
        // 2. Annulation
        if ($this->isCancellation($payload)) {
            $this->annulerRdvs($prospectId, $projetId, (string) $payload['update']);
            Log::info('[RDV Agent] RDVs annulés.', [
                'prospect_id' => $prospectId,
                'update'      => $payload['update'],
            ]);
            return null;
        }

        // 3. Type de RDV
        $type = $this->resolveType($payload);
        if ($type === null) {
            Log::info('[RDV Agent] Aucun RDV à enregistrer.', [
                'prospect_id' => $prospectId,
                'event'       => $payload['event'] ?? null,
            ]);
            return null;
        }

        // 4. Date
        $dateRdv = $this->resolveDateRdv($payload, $type);

        // 5. Commercial cible
        $commercialId = $this->resolveCommercialId($type);
        if ($commercialId === null) {
            Log::error('[RDV Agent] Aucun commercial disponible pour ce type.', [
                'prospect_id' => $prospectId,
                'type'        => $type,
            ]);
            return null;
        }

        $typeRdv     = $type === 'appel' ? 1 : 2;
        $commentaire = $this->buildCommentaire($payload, $type);

        // 6. Transaction
        $rdvId = DB::connection('temp')->transaction(function () use (
            $prospectId, $projetId, $commercialId, $type, $typeRdv, $dateRdv, $payload, $commentaire
        ) {
            // 6a. Idempotence
            $existant = DB::connection('temp')->table(self::TABLE)
                ->where('prospect_id', $prospectId)
                ->where('projet_id', $projetId)
                ->where('type', $type)
                ->where('status', 'confirme')
                ->whereNull('deleted_at')
                ->first();

            if ($existant) {
                DB::connection('temp')->table(self::TABLE)
                    ->where('id', $existant->id)
                    ->update([
                        'commercial_id' => $commercialId,
                        'date_rdv'      => $dateRdv,
                        'creneau_label' => $payload['callback_slot_label'] ?? $existant->creneau_label,
                        'commentaire'   => $commentaire,
                        'source'        => self::SOURCE_AGENT,
                        'updated_at'    => now(),
                    ]);

                Log::info('[RDV Agent] RDV mis à jour.', [
                    'rdv_id'        => $existant->id,
                    'prospect_id'   => $prospectId,
                    'type'          => $type,
                    'commercial_id' => $commercialId,
                ]);

                $rdvId = (int) $existant->id;
            } else {
                $rdvId = DB::connection('temp')->table(self::TABLE)->insertGetId([
                    'prospect_id'   => $prospectId,
                    'projet_id'     => $projetId,
                    'commercial_id' => $commercialId,
                    'type'          => $type,
                    'type_rdv'      => $typeRdv,
                    'date_rdv'      => $dateRdv,
                    'creneau_label' => $payload['callback_slot_label'] ?? null,
                    'source'        => self::SOURCE_AGENT,
                    'commentaire'   => $commentaire,
                    'status'        => 'confirme',
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);

                Log::info('[RDV Agent] RDV créé.', [
                    'rdv_id'        => $rdvId,
                    'prospect_id'   => $prospectId,
                    'type'          => $type,
                    'commercial_id' => $commercialId,
                ]);
            }

            // 6b. Réaffectation + nettoyage notifications
            $this->affecterProspectAuCommercialDuRdv($prospectId, $commercialId, $type);

            // ✅ 6c. Statut UNIQUE selon le type (PAS de Rappel pour une visite)
            if ($type === 'appel') {
                // APPEL → Rappel (3)
                $this->ajouterStatut(
                    $prospectId,
                    StatutProspectEnum::Rappel->value,   // ← 3
                    $type,
                    $rdvId,
                    $dateRdv,
                    'RDV appel fixé (rappel programmé)'
                );
            } elseif ($type === 'visite') {
                // VISITE → Converti_en_visite (4)
                $this->ajouterStatut(
                    $prospectId,
                    StatutProspectEnum::Converti_en_visite->value,   // ← 4
                    $type,
                    $rdvId,
                    $dateRdv,
                    'Visite programmée (RDV visite confirmé)'
                );
            }

            return (int) $rdvId;
        });

        // 7. Notification RDV finale
        $this->notifierCommercialRdv($rdvId, $prospectId, $projetId, $commercialId, $type, $dateRdv);

        return $rdvId;

    } catch (\Throwable $e) {
        Log::error('[RDV Agent] Échec enregistrement RDV.', [
            'prospect_id' => $prospectId,
            'projet_id'   => $projetId,
            'error'       => $e->getMessage(),
            'trace'       => $e->getTraceAsString(),
        ]);
        return null;
    }
}

    /* =====================================================================
     |  Helpers
     * ===================================================================== */

    private function isHandoffCompleted(array $payload): bool
    {
        $event  = $payload['event'] ?? null;
        $status = $payload['status'] ?? null;
        $update = $payload['update'] ?? null;

        if (in_array($event, ['handoff', 'visit_booked', 'escalation'], true)) {
            return true;
        }
        if ($event === 'update' && in_array($update, ['schedule', 'visit_rescheduled', 'visit_confirmed_by_prospect'], true)) {
            return true;
        }
        if ($status === 'callback_or_visit_requested') {
            return true;
        }

        return false;
    }

    private function isCancellation(array $payload): bool
    {
        return in_array($payload['update'] ?? null, [
            'cancelled',
            'visit_cancelled',
            'opt_out',
            'opt_out_visit_cancelled',
            'data_deletion',
        ], true);
    }

    private function annulerRdvs(int $prospectId, int $projetId, string $motif): void
    {
        DB::connection('temp')->table(self::TABLE)
            ->where('prospect_id', $prospectId)
            ->where('projet_id', $projetId)
            ->where('status', 'confirme')
            ->whereNull('deleted_at')
            ->update([
                'status'      => 'annule',
                'commentaire' => DB::raw("CONCAT(COALESCE(commentaire, ''), ' | Annulé : " . addslashes($motif) . "')"),
                'updated_at'  => now(),
            ]);
    }

    private function resolveType(array $payload): ?string
    {
        if (!empty($payload['visit']['start'])) {
            return 'visite';
        }
        if (!empty($payload['requested_visit'])) {
            return 'visite';
        }
        if (!empty($payload['requested_callback'])) {
            return 'appel';
        }

        $event = $payload['event'] ?? null;
        if (in_array($event, ['visit_booked', 'visit_confirmed_by_prospect', 'visit_rescheduled'], true)) {
            return 'visite';
        }
        if (in_array($event, ['handoff', 'hot_lead', 'escalation'], true)) {
            return 'appel';
        }

        return null;
    }

    private function resolveDateRdv(array $payload, string $type): ?string
    {
        if (!empty($payload['visit']['start']) && is_string($payload['visit']['start'])) {
            try {
                return Carbon::parse($payload['visit']['start'])
                    ->setTimezone('Africa/Casablanca')
                    ->format('Y-m-d H:i:s');
            } catch (\Throwable) {
            }
        }

        $date = $payload['callback_date'] ?? null;
        $time = $payload['callback_time'] ?? null;

        if (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
            if (is_string($time) && preg_match('/^\d{2}:\d{2}$/', $time) === 1) {
                return $date . ' ' . $time . ':00';
            }
            return $date . ' 00:00:00';
        }

        return null;
    }

    /**
     * ✅ Commercial cible :
     *  - appel  → lead_resource = 1
     *  - visite → lead_resource = 2
     */
    private function resolveCommercialId(string $type): ?int
    {
        $expected = $type === 'appel' ? 1 : 2;

        $user = DB::connection('temp')
            ->table('users')
            ->where('lead_resource', $expected)
            ->where('role', 3)
            ->where('is_actif', 1)
            ->whereNull('deleted_at')
            ->orderBy('last_affected')
            ->orderBy('id')
            ->first();

        return $user ? (int) $user->id : null;
    }

    /**
     * ✅ Réaffectation conditionnelle + nettoyage notifications.
     *
     * APPEL :
     *   - Si l'ancien commercial est déjà lead_resource=1 → garder (rien à faire)
     *   - Sinon réaffecter au commercial lead_resource=1 + supprimer anciennes notifs
     *
     * VISITE :
     *   - Si l'ancien commercial est déjà lead_resource=2 → garder (rien à faire)
     *   - Sinon réaffecter au commercial lead_resource=2 + supprimer anciennes notifs
     */
   private function affecterProspectAuCommercialDuRdv(int $prospectId, int $commercialId, string $type): void
{
    $prospect = DB::connection('temp')->table('prospects')
        ->where('id', $prospectId)
        ->whereNull('deleted_at')
        ->first();

    if (!$prospect) {
        Log::warning('[RDV Agent] Prospect introuvable.', ['prospect_id' => $prospectId]);
        return;
    }

    $ancienCommercialId = $prospect->commercial_affecte !== null ? (int) $prospect->commercial_affecte : null;

    // Récupérer le lead_resource de l'ancien commercial
    $ancienLeadResource = null;
    if ($ancienCommercialId !== null) {
        $ancienLeadResource = DB::connection('temp')
            ->table('users')
            ->where('id', $ancienCommercialId)
            ->value('lead_resource');
    }

    $targetLeadResource = $type === 'appel' ? 1 : 2;

    // ✅ CAS 1 : prospect déjà chez un commercial du bon lead_resource → GARDER
    if ($ancienCommercialId !== null && (int) $ancienLeadResource === $targetLeadResource) {
        Log::info('[RDV Agent] Prospect déjà chez le bon commercial — pas de réaffectation.', [
            'prospect_id'   => $prospectId,
            'commercial_id' => $ancienCommercialId,
            'lead_resource' => $targetLeadResource,
            'type'          => $type,
        ]);
        // Pas de statut à ajouter : le contrôleur a déjà mis 6 (Affecte)
        return;
    }

    // ✅ CAS 2 : réaffectation nécessaire
    Log::info('[RDV Agent] Réaffectation du prospect.', [
        'prospect_id'          => $prospectId,
        'ancien_commercial'    => $ancienCommercialId,
        'ancien_lead_resource' => $ancienLeadResource,
        'nouveau_commercial'   => $commercialId,
        'type'                 => $type,
    ]);



    // ✅ Statut Reaffecte (13) — au lieu de 5
    $this->ajouterStatut(
        $prospectId,
        StatutProspectEnum::Reaffecte->value,   // ← 13
        $type,
        null,
        null,
          "Réaffectation automatique vers lead resource convenable"
        //({$type} → commercial lead_resource={$targetLeadResource})
    );

    // Affecter
    DB::connection('temp')->table('prospects')
        ->where('id', $prospectId)
        ->update([
            'commercial_affecte' => $commercialId,
            'date_affectation'   => now(),
            'updated_at'         => now(),
        ]);

    // Incrémenter le nouveau
    DB::connection('temp')->table('users')
        ->where('id', $commercialId)
        ->update([
            'nb_prospects'  => DB::raw('COALESCE(nb_prospects, 0) + 1'),
            'last_affected' => 1,
            'updated_at'    => now(),
        ]);

    // Décrémenter l'ancien
    if ($ancienCommercialId !== null && $ancienCommercialId !== $commercialId) {
        DB::connection('temp')->table('users')
            ->where('id', $ancienCommercialId)
            ->where('nb_prospects', '>', 0)
            ->decrement('nb_prospects');
    }

    // ✅ Supprimer les ANCIENNES notifications du prospect (créées au round-robin)
    $deleted = DB::connection('temp')->table('notifications')
        ->where('prospect_id', $prospectId)
        ->whereNull('deleted_at')
        ->delete();

    Log::info('[RDV Agent] Anciennes notifications supprimées.', [
        'prospect_id' => $prospectId,
        'deleted'     => $deleted,
    ]);

    // ❌ PAS de statut 6 ici : le contrôleur l'a déjà ajouté

    Log::info('[RDV Agent] Réaffectation terminée.', [
        'prospect_id'        => $prospectId,
        'ancien_commercial'  => $ancienCommercialId,
        'nouveau_commercial' => $commercialId,
    ]);
}

    private function ajouterStatut(
        int $prospectId,
        int $statutCode,
        string $typeRdv,
        ?int $rdvId = null,
        ?string $dateRdv = null,
        ?string $commentaire = null
    ): void {
        $commentaire = $commentaire ?: (
            $typeRdv === 'visite'
                ? 'RDV visite fixé par l\'agent virtuel'
                : 'RDV appel fixé par l\'agent virtuel'
        );

        $typeTraitement = $typeRdv === 'visite' ? 2 : 1;

        $row = [
            'prospect_id'                 => $prospectId,
            'statut'                      => (string) $statutCode,
            'date_traitement'             => now()->toDateString(),
            'user_id_traite'              => null,
            'commentaire'                 => mb_substr($commentaire, 0, 255, 'UTF-8'),
            'type_traitement_rdv_relance' => $typeTraitement,
            'date_traitement_rdv_relance' => now(),
            'user_id_traite_rdv_relance'  => null,
            'created_at'                  => now(),
            'updated_at'                  => now(),
        ];

       /* if ($rdvId !== null) {
            if ($typeRdv === 'visite') {
                $row['visite_id'] = $rdvId;
            } else {
                $row['appel_id'] = $rdvId;
            }
        }*/

        if ($dateRdv !== null) {
            $row['rdv'] = $dateRdv;
        }

        DB::connection('temp')->table('statut_prospects')->insert($row);
    }

    /**
     * ✅ Notification RDV finale au commercial concerné.
     */
    private function notifierCommercialRdv(
        int $rdvId,
        int $prospectId,
        int $projetId,
        int $commercialId,
        string $type,
        ?string $dateRdv
    ): void {
        try {
            $prospect = DB::connection('temp')->table('prospects')
                ->where('id', $prospectId)
                ->first(['nom', 'prenom', 'telephone']);

            $prospectNom = $prospect
                ? trim(($prospect->nom ?? '') . ' ' . ($prospect->prenom ?? ''))
                : null;
            if ($prospectNom === '' || $prospectNom === null) {
                $prospectNom = $prospect->telephone ?? ('#' . $prospectId);
            }

            $dateFormatee = $dateRdv !== null
                ? Carbon::parse($dateRdv)->locale('fr')->isoFormat('dddd D MMMM [à] HH[h]mm')
                : 'Dès que possible';

            $titre = $type === 'visite' ? '📅 *NOUVEAU RDV VISITE*' : '📞 *NOUVEAU RDV APPEL*';

            $description = $titre . "\n\n"
                . '👤 Prospect : ' . $prospectNom . "\n"
                . '📞 Téléphone : ' . ($prospect->telephone ?? 'N/A') . "\n"
                . '🕒 Date : ' . $dateFormatee . "\n"
                . '🔗 RDV #' . $rdvId;

            $notification = new Notification();
            $notification->setConnection('temp');
            $notification->date = now();
            $notification->type = 50;
            $notification->description_type = mb_substr($description, 0, 255, 'UTF-8');
            $notification->lien = '/crm/prospects/' . $prospectId;
            $notification->role = 3;
            $notification->user_id = $commercialId;
            $notification->projet_id = $projetId;
            $notification->prospect_id = $prospectId;
            $notification->save();

            Config::set('broadcasting.default', 'pusher_notify');
            broadcast(new NotificationEvent($notification->id));

            Log::info('[RDV Agent] Notification RDV envoyée au commercial.', [
                'notification_id' => $notification->id,
                'commercial_id'   => $commercialId,
                'rdv_id'          => $rdvId,
                'type'            => $type,
            ]);
        } catch (\Throwable $e) {
            Log::error('[RDV Agent] Erreur notification RDV.', [
                'error'         => $e->getMessage(),
                'rdv_id'        => $rdvId,
                'commercial_id' => $commercialId,
            ]);
        }
    }

    private function buildCommentaire(array $payload, string $type): string
    {
        $parts = [$type === 'visite' ? 'RDV visite (agent virtuel)' : 'RDV appel (agent virtuel)'];

        if (!empty($payload['callback_slot_label'])) {
            $parts[] = 'Créneau : ' . $payload['callback_slot_label'];
        }
        if (!empty($payload['visit']['start'])) {
            $parts[] = 'Visite : ' . $payload['visit']['start'];
        }
        if (!empty($payload['name'])) {
            $parts[] = 'Nom : ' . $payload['name'];
        }
        if (!empty($payload['lead_score'])) {
            $parts[] = 'Score : ' . $payload['lead_score'] . '/100';
        }

        return mb_substr(implode(' | ', $parts), 0, 1000, 'UTF-8');
    }
}
