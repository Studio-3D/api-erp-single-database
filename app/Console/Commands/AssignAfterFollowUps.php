<?php

namespace App\Console\Commands;

use App\Http\Helpers\CommercialAssignmentLeadResourceOneHelper;
use App\Http\Helpers\DatabaseHelper;
use App\Models\Conversation;
use App\Models\Prospect;
use App\Models\Societe;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AssignAfterFollowUps extends Command
{
    /**
     * Affecte automatiquement les prospects qui :
     *  - ont reçu 2 relances (follow_up_count >= 2)
     *  - n'ont pas répondu (last_client_message_at < last_bot_message_at)
     *  - ont un dernier statut '0' (Nouveau) ou '18' (En cours)
     *  - ne sont pas encore affectés (commercial_affecte = NULL)
     */
    protected $signature = 'prospects:assign-after-followups
                            {--delay-after-followup2=2 : Délai minimum après la 2e relance (heures)}
                            {--dry-run : Simulation}';

    protected $description = 'Affecte les prospects non affectés qui n\'ont pas répondu après 2 relances';

    public function handle(): int
    {
        $delayHours = (int) $this->option('delay-after-followup2');
        $dryRun = (bool) $this->option('dry-run');

        Log::info('[CRON] Début affectation après 2 relances', [
            'delay_after_followup2_hours' => $delayHours,
            'dry_run'                     => $dryRun,
        ]);

        $societes = Societe::all();
        if ($societes->isEmpty()) {
            Log::warning('[CRON] Aucune société trouvée');
            return Command::SUCCESS;
        }

        $totalAssigned = 0;
        $totalFailed = 0;

        foreach ($societes as $societe) {
            try {
                // Configurer la connexion temp
                $databaseName = env('DB_DATABASE');
                $connection = DatabaseHelper::Connection_database($databaseName);
                config(['database.connections.temp' => $connection]);
                DB::purge('temp');
                DB::reconnect('temp');

                // Récupérer les conversations éligibles
                $cutoff = Carbon::now()->subHours($delayHours);

                $conversations = Conversation::on('temp')
                    ->where('follow_up_count', '>=', 2)
                    ->whereNotNull('follow_up_sent_at')
                    ->where('follow_up_sent_at', '<=', $cutoff)
                    ->where(function ($query) {
                        $query->whereNull('last_client_message_at')
                            ->orWhereColumn('last_client_message_at', '<', 'follow_up_sent_at');
                    })
                    ->whereNotNull('prospect_id')
                    ->get();

                if ($conversations->isEmpty()) {
                    Log::info("[CRON] Aucune conversation éligible pour société #{$societe->id}");
                    continue;
                }

                Log::info("[CRON] {$conversations->count()} conversation(s) éligible(s) pour société #{$societe->id}");
                $this->info("📋 Société #{$societe->id} : {$conversations->count()} prospect(s) à affecter");

                foreach ($conversations as $conversation) {
                    $prospectId = $conversation->prospect_id;
                    $projetId = $conversation->projet_id;

                    // Vérifier que le prospect existe et n'est pas déjà affecté
                    $prospect = Prospect::on('temp')->find($prospectId);
                    if (!$prospect) {
                        continue;
                    }
                    if (!empty($prospect->commercial_affecte)) {
                        continue;
                    }

                    // Vérifier le dernier statut (doit être 0 ou 18)
                    $lastStatut = DB::connection('temp')
                        ->table('statut_prospects')
                        ->where('prospect_id', $prospectId)
                        ->whereNull('deleted_at')
                        ->orderBy('id', 'desc')
                        ->value('statut');
                    //'0',
                    if (!in_array((string) $lastStatut, ['18'], true)) {
                        Log::info("[CRON] Prospect #{$prospectId} — dernier statut {$lastStatut}, pas d'affectation");
                        continue;
                    }

                    if ($dryRun) {
                        $this->line("🟡 [DRY-RUN] Prospect #{$prospectId} ({$prospect->telephone}) serait affecté");
                        continue;
                    }

                    // Affecter
                    try {
                        $systemUser = User::on('temp')
                            ->where('role', 1)
                            ->whereNull('deleted_at')
                            ->first();

                        $result = CommercialAssignmentLeadResourceOneHelper::assignProspect(
                            $prospectId,
                            $projetId,
                            'temp',
                            $systemUser?->id,
                            'CronAfterFollowUps'
                        );

                      if (!empty($result['success'])) {
                        $totalAssigned++;
                        $commercialId = $result['commercial_id'];

                        $this->line("✅ Prospect #{$prospectId} → Commercial #{$commercialId}");

                        // ════════════════════════════════════════════════════════════
                        // ✅ 1. Créer le statut "Affecté" (6) dans statut_prospects
                        // ════════════════════════════════════════════════════════════
                        try {
                            DB::connection('temp')->table('statut_prospects')->insert([
                                'prospect_id'                 => $prospectId,
                                'statut'                      => '6',
                                'date_traitement'             => now()->toDateString(),
                                'user_id_traite'              => $systemUser?->id,
                                'commentaire'                 => 'Prospect affecté automatiquement après 2 relances sans réponse',
                                'type_traitement_rdv_relance' => 0,
                                'created_at'                  => now(),
                                'updated_at'                  => now(),
                            ]);

                            Log::info('[CRON] Statut Affecté (6) créé', [
                                'prospect_id'   => $prospectId,
                                'commercial_id' => $commercialId,
                            ]);
                        } catch (\Throwable $e) {
                            Log::error('[CRON] Erreur création statut Affecté', [
                                'prospect_id' => $prospectId,
                                'error'       => $e->getMessage(),
                            ]);
                        }

                        // ════════════════════════════════════════════════════════════
                        // ✅ 2. Créer une notification pour le commercial
                        // ════════════════════════════════════════════════════════════
                        try {
                            $prospect = \App\Models\Prospect::on('temp')->find($prospectId);
                            $prospectNom = $prospect
                                ? trim(($prospect->nom ?? '') . ' ' . ($prospect->prenom ?? ''))
                                : null;
                            if ($prospectNom === '' || $prospectNom === null) {
                                $prospectNom = $prospect->telephone ?? ('#' . $prospectId);
                            }

                            $notification = new \App\Models\Notification();
                            $notification->setConnection('temp');
                            $notification->date = now();
                            $notification->type = 53; // Affectation automatique
                            $notification->description_type = mb_substr(
                                "📋 *PROSPECT AFFECTÉ (après 2 relances)*\n\n"
                                . "👤 Prospect : {$prospectNom}\n"
                                . "📞 Téléphone : " . ($prospect->telephone ?? ' ') . "\n"
                                . "💬 Statut : Pas de réponse après 2 relances\n",
                                0, 255, 'UTF-8'
                            );
                            $notification->lien = "/crm/prospects/{$prospectId}";
                            $notification->role = 3;
                            $notification->user_id = $commercialId;
                            $notification->projet_id = $projetId;
                            $notification->prospect_id = $prospectId;
                            $notification->save();

                            \Illuminate\Support\Facades\Config::set('broadcasting.default', 'pusher_notify');
                            broadcast(new \App\Events\NotificationEvent($notification->id));

                            Log::info('[CRON] Notification créée pour le commercial', [
                                'notification_id' => $notification->id,
                                'commercial_id'   => $commercialId,
                                'prospect_id'     => $prospectId,
                            ]);
                        } catch (\Throwable $e) {
                            Log::error('[CRON] Erreur création notification', [
                                'prospect_id' => $prospectId,
                                'error'       => $e->getMessage(),
                            ]);
                        }

                        Log::info('[CRON] Prospect affecté après 2 relances', [
                            'prospect_id'   => $prospectId,
                            'commercial_id' => $commercialId,
                        ]);
                    } else {
                        $totalFailed++;
                        $this->warn("⚠️ Prospect #{$prospectId} : " . ($result['message'] ?? 'échec'));
                    }
                    } catch (\Throwable $e) {
                        $totalFailed++;
                        Log::error("[CRON] Exception affectation prospect #{$prospectId}", [
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

            } catch (\Exception $e) {
                Log::error("[CRON] Erreur société #{$societe->id}: " . $e->getMessage());
            }
        }

        $this->info("═══════════════════════════════════════");
        $this->info("✅ Affectés : {$totalAssigned}");
        $this->info("⚠️ Échecs  : {$totalFailed}");
        $this->info("═══════════════════════════════════════");

        return Command::SUCCESS;
    }
}
