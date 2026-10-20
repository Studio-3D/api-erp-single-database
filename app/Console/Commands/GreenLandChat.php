<?php

namespace App\Console\Commands;

use App\Services\AgentFinalService;
use App\Services\GreenLand\TwilioWhatsApp;
use Illuminate\Console\Command;

/**
 * Discuter avec l'agent virtuel dans le terminal, sans WhatsApp ni CRM : idéal pour tester
 * une modification des textes, des réglages ou des informations du projet avant la mise en ligne.
 *
 *   php artisan greenland:chat
 *   php artisan greenland:chat --phone=+33612345678 --name="Sara"   (prospect en France)
 *
 * Rien n'est enregistré en base et aucun message n'est envoyé.
 */
class GreenLandChat extends Command
{
    protected $signature = 'greenland:chat
        {--phone=+212600000000 : numéro WhatsApp simulé du prospect}
        {--name= : nom WhatsApp simulé}';

    protected $description = 'Tester l’agent virtuel GreenLand dans le terminal (aucun message envoyé).';

    public function handle(): int
    {
        $initial = array_filter(['whatsapp_phone' => (string) $this->option('phone'), 'profile_name' => $this->option('name')]);
        $state = $initial;
        $history = [];

        $this->info('Écrivez comme un prospect. Commandes : /bouton <id> · /fiche · /etat · /rappels · /reset · /quitter');
        $this->line('Les boutons s’affichent avec leur identifiant entre crochets : tapez /bouton gl:… pour en toucher un (ou écrivez son titre).');

        while (true) {
            $line = trim((string) $this->ask('Vous'));
            if ($line === '') {
                continue;
            }

            if (in_array($line, ['/quitter', '/q', '/exit'], true)) {
                return self::SUCCESS;
            }
            if ($line === '/reset') {
                [$state, $history] = [$initial, []];
                $this->info('Nouvelle conversation.');
                continue;
            }
            if ($line === '/etat') {
                $this->line(json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                continue;
            }
            if ($line === '/fiche') {
                $this->line(json_encode((new AgentFinalService($state))->getLeadSnapshot(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                continue;
            }
            if ($line === '/rappels') {
                $agent = new AgentFinalService($state);
                $reminders = $agent->dueReminders();
                $this->line($reminders === []
                    ? '(aucun rappel dû ; prochain : ' . ($agent->nextReminderAt() ?? 'aucun') . ')'
                    : implode("\n", array_map(static fn (array $r): string => '🤖 ' . $r['text'], $reminders)));
                $state = $agent->getConversationState();
                continue;
            }

            $button = null;
            $message = $line;
            if (preg_match('/^\/bouton\s+(\S+)(?:\s+(.*))?$/u', $line, $match) === 1) {
                $button = $match[1];
                $message = $match[2] ?? $match[1];
            }

            $result = (new AgentFinalService($state))->reply($message, $history, $button);
            $state = $result['state'];
            $history[] = ['role' => 'user', 'content' => $message];
            if ($result['message'] !== '') {
                $history[] = ['role' => 'assistant', 'content' => $result['message']];
            }

            $this->show($result);
        }
    }

    /** Affiche les messages tels qu'ils partiraient sur WhatsApp (même découpage que l'envoi Twilio). */
    private function show(array $result): void
    {
        if (!empty($result['silent'])) {
            $this->warn('(agent en pause : aucune réponse)');
        }

        $preview = new TwilioWhatsApp(new \stdClass(), 'AC', 'token', '+212000000000');
        foreach ($preview->plan($result) as $item) {
            switch ($item['kind']) {
                case 'text':
                case 'voice':
                    $this->line('🤖 ' . $item['text']);
                    break;
                case 'image':
                case 'video':
                case 'document':
                    $this->line(['image' => '🖼️ ', 'video' => '🎬 ', 'document' => '📄 '][$item['kind']] . ' ' . $item['url'] . (!empty($item['caption']) ? "\n   " . $item['caption'] : ''));
                    break;
                case 'location':
                    $this->line('📍 ' . $item['name'] . ' (' . $item['latitude'] . ', ' . $item['longitude'] . ')');
                    break;
                case 'question':
                    $this->line('🤖 ' . $item['body']);
                    foreach ((array) ($item['interactive']['buttons'] ?? []) as $button) {
                        $this->line('   [' . $button['id'] . '] ' . $button['title']);
                    }
                    foreach ((array) ($item['interactive']['sections'] ?? []) as $section) {
                        foreach ((array) ($section['rows'] ?? []) as $row) {
                            $this->line('   [' . $row['id'] . '] ' . $row['title'] . (isset($row['description']) ? ' – ' . $row['description'] : ''));
                        }
                    }
                    break;
            }
        }

        foreach ((array) ($result['actions'] ?? []) as $action) {
            switch ($action['type'] ?? null) {
                case 'notify_commercial':
                    $this->comment('   📣 Notification CRM : ' . ($action['payload']['event'] ?? '') . (isset($action['payload']['update']) ? ' (' . $action['payload']['update'] . ')' : '') . ' – score ' . ($action['payload']['lead_score'] ?? 0) . '/100');
                    break;
                case 'unanswered_question':
                    $this->comment('   ❓ Question sans réponse : ' . ($action['question'] ?? ''));
                    break;
                case 'data_deletion_request':
                    $this->comment('   🗑️  Demande de suppression des données');
                    break;
            }
        }
    }
}
