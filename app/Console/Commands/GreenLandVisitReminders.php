<?php

namespace App\Console\Commands;

use App\Http\Helpers\DatabaseHelper;
use App\Models\Conversation;
use App\Services\AgentFinalService;
use App\Services\GreenLand\TwilioWhatsApp;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Rappels des visites réservées par l'agent virtuel GreenLand : la veille, puis 2 h avant
 * (config greenland.reminders), avec les boutons « Je confirme / Changer / Annuler ».
 *
 * Dans les 24 h qui suivent le dernier message du prospect : message libre avec boutons.
 * Au-delà, WhatsApp impose un modèle approuvé : GREENLAND_TEMPLATE_RAPPEL_VISITE_SID
 * (sans modèle, le rappel n'est pas envoyé et le journal le signale).
 *
 * Planification (app/Console/Kernel.php ou routes/console.php) :
 *   $schedule->command('greenland:visit-reminders')->everyFiveMinutes()->withoutOverlapping();
 */
class GreenLandVisitReminders extends Command
{
    protected $signature = 'greenland:visit-reminders {--dry-run : affiche les rappels sans les envoyer}';

    protected $description = 'Envoie les rappels de visite de l’agent virtuel GreenLand (WhatsApp via Twilio).';

    /** Configurations WhatsApp déjà lues, par projet. */
    private array $configs = [];

    public function handle(): int
    {
        $this->configureDatabase();

        $sent = 0;
        // Pré-filtre : seules les conversations qui ont un rappel de visite programmé
        Conversation::query()
            ->where('updated_at', '>=', now()->subDays(15))
            ->where(function ($query) {
                $query->where('state', 'like', '%visit_d1%')
                      ->orWhere('state', 'like', '%visit_h2%');
            })
            ->orderBy('id')
            ->chunkById(100, function ($conversations) use (&$sent) {
                foreach ($conversations as $conversation) {
                    $sent += $this->remind($conversation);
                }
            });

        $this->info("{$sent} rappel(s) de visite envoyé(s).");

        return self::SUCCESS;
    }

    /** @return int nombre de rappels envoyés pour cette conversation */
    private function remind(Conversation $conversation): int
    {
        try {
            $agent = new AgentFinalService($conversation->state ?? []);
            $reminders = $agent->dueReminders();
            if ($reminders === []) {
                return 0;
            }

            [$projetId, $phone] = $this->projetAndPhone($conversation);
            $config = $projetId ? $this->whatsappConfig($projetId) : null;
            if (!$config || !$phone) {
                Log::warning('GreenLand : rappel de visite impossible (configuration WhatsApp ou numéro introuvable).', ['conversation_id' => $conversation->id]);
                return 0;
            }

            $history = $conversation->history ?? [];
            $count = 0;

            foreach ($reminders as $reminder) {
                if ($this->option('dry-run')) {
                    $this->line("→ {$phone} : " . $reminder['text'] . ($reminder['requires_template'] ? '  [modèle]' : ''));
                    $count++;
                    continue;
                }

                if ($this->deliver($config, $projetId, $phone, $conversation->prospect_id ?? null, $reminder)) {
                    $history[] = [
                        'role' => 'assistant',
                        'content' => $reminder['text'],
                        'timestamp' => now()->toDateTimeString(),
                    ];
                    $count++;
                }
            }

            // Les rappels sont marqués comme envoyés dans l'état : jamais deux fois le même
            if (!$this->option('dry-run')) {
                $conversation->updateConversation($agent->getConversationState(), array_slice($history, -40));
            }

            return $count;
        } catch (\Throwable $e) {
            Log::error('GreenLand : rappel de visite en erreur.', ['conversation_id' => $conversation->id ?? null, 'error' => $e->getMessage()]);
            return 0;
        }
    }

    private function deliver($config, $projetId, string $phone, $prospectId, array $reminder): bool
    {
        $sender = new TwilioWhatsApp(
            new \Twilio\Rest\Client($config->account_sid, $config->access_token),
            (string) $config->account_sid,
            (string) $config->access_token,
            (string) $config->phone_number_id,
            function (array $message) use ($config, $projetId, $phone, $prospectId) {
                DB::connection('temp')->table('whatsapp_messages')->insert([
                    'projet_id' => $projetId,
                    'from_number' => $config->phone_number_id,
                    'to_number' => $phone,
                    'message' => $message['message'] ?? '',
                    'message_sid' => $message['message_sid'] ?? null,
                    'profile_name' => 'Agent Virtuel Karim',
                    'status' => 'sent',
                    'message_type' => 'agent_reminder',
                    'prospect_id' => $prospectId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        );

        // 1. Dans la fenêtre de 24 h : texte + boutons Confirmer / Changer / Annuler
        if (!$reminder['requires_template']) {
            return $sender->sendQuestion($phone, $reminder['text'], $reminder['interactive'], 'agent_reminder');
        }

        // 2. Hors fenêtre : modèle approuvé (jour, heure) avec les mêmes boutons
        $templates = (array) config('greenland.twilio.templates.visit_reminder', []);
        $language = (string) ($reminder['template']['language'] ?? 'fr');
        $contentSid = $templates[$language] ?? ($templates['fr'] ?? null);

        if (!is_string($contentSid) || !str_starts_with($contentSid, 'HX')) {
            Log::warning('GreenLand : rappel de visite non envoyé, aucun modèle approuvé configuré (GREENLAND_TEMPLATE_RAPPEL_VISITE_SID).', ['phone' => $phone]);
            return false;
        }

        return $sender->sendTemplate($phone, $contentSid, (array) ($reminder['template']['body_params'] ?? []), $reminder['text'], 'agent_reminder');
    }

    /** Projet et numéro du prospect : colonnes de la conversation, sinon session « whatsapp_{projet}_{numéro} ». */
    private function projetAndPhone(Conversation $conversation): array
    {
        $projetId = $conversation->projet_id ?? null;
        $phone = $conversation->phone_number ?? null;

        if ((!$projetId || !$phone) && preg_match('/^whatsapp_(\d+)_(\d+)$/', (string) $conversation->session_id, $match) === 1) {
            $projetId = $projetId ?: (int) $match[1];
            $phone = $phone ?: '+' . $match[2];
        }

        $phone = $phone ? '+' . ltrim((string) $phone, '+') : null;

        return [$projetId, $phone];
    }

    private function whatsappConfig($projetId)
    {
        if (!array_key_exists($projetId, $this->configs)) {
            $this->configs[$projetId] = DB::connection('temp')
                ->table('whatsapp_configurations')
                ->where('projet_id', $projetId)
                ->whereNull('deleted_at')
                ->first();
        }

        return $this->configs[$projetId];
    }

    /** Même connexion 'temp' que le webhook WhatsApp. */
    private function configureDatabase(): void
    {
        $database = env('DB_DATABASE') ?: config('database.connections.' . config('database.default') . '.database');
        $connection = DatabaseHelper::Connection_database($database);
        config(['database.connections.temp' => $connection]);
        DB::purge('temp');
        DB::reconnect('temp');
    }
}
