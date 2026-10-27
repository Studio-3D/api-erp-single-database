<?php

declare(strict_types=1);

namespace App\Services\GreenLand;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envoi des réponses de l'assistant sur WhatsApp avec Twilio, dans l'ordre de 'parts' :
 *
 *  - texte (découpé à 1 600 caractères, limite de Twilio) ;
 *  - photos, plans 3D, vidéo, brochure PDF (mediaUrl) ;
 *  - localisation : vraie épingle WhatsApp (PersistentAction « geo: »), lien Google Maps en secours ;
 *  - question avec boutons (3 au plus) ou liste (10 lignes au plus) : modèles de contenu Twilio
 *    (Content API) créés automatiquement une seule fois, avec des variables, puis réutilisés ;
 *    en secours, la question et ses choix partent en texte ;
 *  - réponse vocale (si activée) : fichier déposé par $publicFile puis envoyé en mediaUrl ;
 *  - « en train d'écrire… » et coches bleues (Typing Indicator API de Twilio).
 *
 * Chaque message envoyé est confié à $archive, pour l'enregistrer dans whatsapp_messages (CRM).
 */
final class TwilioWhatsApp
{
    private const BODY_MAX = 1600;
    private const INTERACTIVE_BODY_MAX = 1024;
    private const CONTENT_URL = 'https://content.twilio.com/v1/Content';
    private const TYPING_URL = 'https://messaging.twilio.com/v3/Indicators/Typing.json';
    private const CONTENT_CACHE_SECONDS = 30 * 24 * 3600;
    private const MEDIA_KINDS = ['image', 'video', 'document', 'voice'];

    public const DEFAULTS = [
        'pause_between_messages_ms' => 400,
        'pause_after_media_ms' => 1500,
        'typing_indicator' => true,
        'native_location' => true,
        'interactive' => true,
        'voice_also_text' => false,
        // Identifiants HX… de modèles de contenu déjà créés (sinon créés automatiquement) : 'buttons_2' => 'HX…', 'list_5' => 'HX…'
        'content_sids' => [],
    ];

    private array $options;

    /**
     * @param object        $twilio     client Twilio (\Twilio\Rest\Client)
     * @param string        $from       numéro WhatsApp de l'assistant, tel qu'enregistré dans whatsapp_configurations
     * @param \Closure|null $archive    fn(array $message): void — enregistre un message envoyé (CRM)
     * @param \Closure|null $publicFile fn(string $bytes, string $mime, string $filename): ?string — URL publique d'un fichier
     * @param \Closure|null $sleep      fn(int $milliseconds): void — pause entre deux messages
     */
    public function __construct(
        private readonly object $twilio,
        private readonly string $accountSid,
        private readonly string $authToken,
        private readonly string $from,
        private readonly ?\Closure $archive = null,
        private readonly ?\Closure $publicFile = null,
        private readonly ?VoiceSynthesizer $voice = null,
        array $options = [],
        private readonly ?\Closure $sleep = null,
    ) {
        $this->options = array_replace(self::DEFAULTS, (array) Settings::get('twilio', []), $options);
    }

    /**
     * Envoie la réponse complète de l'assistant (résultat de reply(), replyToVoice()…).
     *
     * @return int nombre de messages envoyés
     */
    public function sendResult(string $to, array $result): int
    {
        if (!empty($result['silent'])) {
            return 0;
        }

        $sent = 0;
        $previous = null;
        foreach ($this->plan($result) as $item) {
            if ($previous !== null) {
                $this->pause(in_array($previous['kind'], self::MEDIA_KINDS, true)
                    ? (int) $this->options['pause_after_media_ms']
                    : (int) $this->options['pause_between_messages_ms']);
            }
            $sent += $this->deliver($to, $item, (string) ($result['language'] ?? 'fr'));
            $previous = $item;
        }

        return $sent;
    }

    /**
     * Messages à envoyer, dans l'ordre. Le texte et la question qui le suit partent en un seul message
     * (avec ses boutons). Après des médias, une question sans boutons devient la légende du dernier média :
     * elle arrive ainsi forcément après lui.
     *
     * @return list<array{kind:string}>
     */
    public function plan(array $result): array
    {
        $parts = $result['parts'] ?? null;
        if (!is_array($parts)) {
            // Résultat d'une ancienne version : texte complet, puis question.
            $parts = [];
            $text = (string) ($result['body'] ?? $result['message'] ?? '');
            if (trim($text) !== '') {
                $parts[] = ['type' => 'text', 'text' => $text];
            }
            if (!empty($result['question'])) {
                $parts[] = ['type' => 'question', 'text' => (string) $result['question'], 'interactive' => $result['interactive'] ?? null];
            }
        }

        $plan = [];
        $buffer = [];
        $flush = static function () use (&$plan, &$buffer): void {
            if ($buffer !== []) {
                $plan[] = ['kind' => 'text', 'text' => implode("\n\n", $buffer)];
                $buffer = [];
            }
        };

        foreach ($parts as $part) {
            switch ((string) ($part['type'] ?? '')) {
                case 'text':
                    $text = trim((string) ($part['text'] ?? ''));
                    if ($text === '') {
                        break;
                    }
                    if (!empty($part['speak']) && $this->voice !== null && $this->publicFile !== null) {
                        $flush();
                        $plan[] = ['kind' => 'voice', 'text' => $text, 'speak_text' => (string) ($part['speak_text'] ?? $text)];
                        break;
                    }
                    $buffer[] = $text;
                    break;

                case 'image':
                case 'video':
                case 'document':
                    if (trim((string) ($part['url'] ?? '')) === '') {
                        break;
                    }
                    $flush();
                    $plan[] = ['kind' => $part['type'], 'url' => (string) $part['url'], 'caption' => $part['caption'] ?? null];
                    break;

                case 'location':
                    $flush();
                    $plan[] = [
                        'kind' => 'location',
                        'latitude' => (float) $part['latitude'],
                        'longitude' => (float) $part['longitude'],
                        'name' => (string) ($part['name'] ?? ''),
                        'address' => $part['address'] ?? null,
                        'maps_url' => $part['maps_url'] ?? null,
                    ];
                    break;

                case 'cta':
                    // Bouton-lien : envoyé en texte, WhatsApp rend le lien cliquable.
                    $buffer[] = trim(trim((string) ($part['body'] ?? '')) . "\n" . (string) ($part['url'] ?? ''));
                    break;

                case 'question':
                    $question = trim((string) ($part['text'] ?? ''));
                    if ($question === '') {
                        break;
                    }
                    $interactive = $this->options['interactive'] && is_array($part['interactive'] ?? null) ? $part['interactive'] : null;
                    $last = $plan === [] ? null : $plan[array_key_last($plan)];

                    if ($interactive === null && $buffer === [] && $last !== null && in_array($last['kind'], ['image', 'video', 'document'], true)) {
                        $caption = trim((string) ($last['caption'] ?? ''));
                        $plan[array_key_last($plan)]['caption'] = $caption !== '' ? $caption . "\n\n" . $question : $question;
                        break;
                    }

                    $body = implode("\n\n", array_merge($buffer, [$question]));
                    if ($interactive !== null && mb_strlen($body, 'UTF-8') > self::INTERACTIVE_BODY_MAX) {
                        $flush();
                        $body = $question;
                    }
                    $buffer = [];
                    $plan[] = ['kind' => 'question', 'body' => $body, 'interactive' => $interactive];
                    break;
            }
        }
        $flush();

        return $plan;
    }

    /** Coches bleues et « en train d'écrire… » pendant que l'assistant prépare sa réponse (25 s au plus). */
    public function typing(?string $inboundMessageSid): void
    {
        if (!$this->options['typing_indicator'] || !is_string($inboundMessageSid) || preg_match('/^(SM|MM)[0-9a-fA-F]{32}$/', $inboundMessageSid) !== 1) {
            return;
        }

        try {
            foreach (['WHATSAPP', 'whatsapp'] as $channel) {
                $response = Http::timeout(4)
                    ->withBasicAuth($this->accountSid, $this->authToken)
                    ->acceptJson()
                    ->post(self::TYPING_URL, ['channel' => $channel, 'messageId' => $inboundMessageSid]);
                if ($response->status() !== 400) {
                    return;
                }
            }
        } catch (\Throwable $e) {
            Log::debug('GreenLand : indicateur de saisie non envoyé.', ['error' => $e->getMessage()]);
        }
    }

    public function sendText(string $to, string $text, string $type = 'agent_response'): bool
    {
        $ok = false;
        foreach (self::split(trim($text)) as $chunk) {
            $sid = $this->create($to, ['body' => $chunk]);
            if ($sid === null) {
                return $ok;
            }
            $this->archiveMessage(['message' => $chunk, 'message_sid' => $sid, 'message_type' => $type]);
            $ok = true;
        }

        return $ok;
    }

    public function sendMedia(string $to, string $url, ?string $caption = null, string $type = 'media'): bool
    {
        if (trim($url) === '') {
            return false;
        }

        $params = ['mediaUrl' => [$url]];
        if ($caption !== null && trim($caption) !== '') {
            $params['body'] = mb_substr(trim($caption), 0, self::BODY_MAX, 'UTF-8');
        }

        $sid = $this->create($to, $params);
        if ($sid === null) {
            return false;
        }
        $this->archiveMessage([
            'message' => $params['body'] ?? ($type === 'agent_voice' ? '🎤 Réponse vocale' : '📎 Média'),
            'message_sid' => $sid,
            'message_type' => $type,
            'media_url' => $url,
        ]);

        return true;
    }

    /** Épingle de localisation WhatsApp (nom + adresse) ; si Twilio la refuse, lien Google Maps. */
    public function sendLocation(string $to, float $latitude, float $longitude, string $name, ?string $address = null, ?string $mapsUrl = null): bool
    {
        $name = trim($name) !== '' ? trim($name) : 'GreenLand';
        $label = trim((string) $address) !== '' ? trim((string) $address) : $name;

        if ($this->options['native_location']) {
            $sid = $this->create($to, [
                'body' => $name,
                'persistentAction' => ['geo:' . $latitude . ',' . $longitude . '|' . str_replace('|', ' ', $label)],
            ], false);
            if ($sid !== null) {
                $this->archiveMessage(['message' => '📍 ' . $name . ($label !== $name ? ' – ' . $label : ''), 'message_sid' => $sid, 'message_type' => 'location']);
                return true;
            }
        }

        $url = $mapsUrl ?: 'https://www.google.com/maps/search/?api=1&query=' . $latitude . '%2C' . $longitude;

        return $this->sendText($to, "📍 *{$name}*\n{$url}", 'location');
    }

    /** Question avec boutons ou liste (modèle de contenu Twilio) ; en secours, la question et ses choix en texte. */
    public function sendQuestion(string $to, string $body, ?array $interactive, string $type = 'agent_question'): bool
    {
        $options = $interactive !== null ? self::optionsOf($interactive) : [];
        if ($options === []) {
            return $this->sendText($to, $body, $type);
        }

        $content = $this->contentFor($body, $interactive);
        if ($content !== null) {
            [$key, $definition, $variables] = $content;
            $contentSid = $this->contentSid($key, $definition);
            if ($contentSid !== null) {
                $messageSid = $this->create($to, [
                    'contentSid' => $contentSid,
                    'contentVariables' => json_encode($variables, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ], false);
                if ($messageSid !== null) {
                    $this->archiveMessage([
                        'message' => $body . "\n" . implode(' ', array_map(static fn (array $o): string => '[' . $o['title'] . ']', $options)),
                        'message_sid' => $messageSid,
                        'message_type' => $type,
                    ]);
                    return true;
                }
                // Modèle supprimé côté Twilio ? Il sera recréé au prochain envoi.
                $this->forgetContent($key);
            }
        }

        $lines = array_map(static fn (array $o): string => '▫️ ' . $o['title'], $options);

        return $this->sendText($to, $body . "\n\n" . implode("\n", $lines), $type);
    }

    /** Modèle approuvé (hors fenêtre de 24 h) : identifiant de contenu Twilio (HX…) et ses variables, dans l'ordre. */
    public function sendTemplate(string $to, string $contentSid, array $variables, string $archiveText, string $type = 'agent_template'): bool
    {
        $values = [];
        foreach (array_values($variables) as $index => $value) {
            $values[(string) ($index + 1)] = self::oneLine((string) $value);
        }

        $params = ['contentSid' => $contentSid];
        if ($values !== []) {
            $params['contentVariables'] = json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $sid = $this->create($to, $params);
        if ($sid === null) {
            return false;
        }
        $this->archiveMessage(['message' => $archiveText, 'message_sid' => $sid, 'message_type' => $type]);

        return true;
    }

    /** Variable de modèle approuvé : une seule ligne, sans tabulation ni longue suite d'espaces (règle WhatsApp). */
    public static function oneLine(string $text): string
    {
        $text = trim((string) preg_replace('/\s*[\r\n\t]+\s*/u', ' · ', trim($text)));
        $text = (string) preg_replace('/ {4,}/u', '   ', $text);

        return $text === '' ? '—' : $text;
    }

    /* ------------------------------------------------------------------ */

    private function deliver(string $to, array $item, string $language): int
    {
        switch ($item['kind']) {
            case 'text':
                return $this->sendText($to, $item['text']) ? 1 : 0;

            case 'voice':
                $voiceSent = $this->sendVoice($to, $item['speak_text'], $language);
                if (!$voiceSent || $this->options['voice_also_text']) {
                    return ($this->sendText($to, $item['text']) ? 1 : 0) + ($voiceSent ? 1 : 0);
                }
                return 1;

            case 'image':
            case 'video':
            case 'document':
                return $this->sendMedia($to, $item['url'], $item['caption'] ?? null) ? 1 : 0;

            case 'location':
                return $this->sendLocation($to, $item['latitude'], $item['longitude'], $item['name'], $item['address'] ?? null, $item['maps_url'] ?? null) ? 1 : 0;

            case 'question':
                return $this->sendQuestion($to, $item['body'], $item['interactive']) ? 1 : 0;
        }

        return 0;
    }

    private function sendVoice(string $to, string $text, string $language): bool
    {
        try {
            $audio = $this->voice?->synthesize($text, $language);
            $url = $audio !== null && $this->publicFile !== null ? ($this->publicFile)($audio['bytes'], $audio['mime'], $audio['filename']) : null;

            return is_string($url) && $url !== '' && $this->sendMedia($to, $url, null, 'agent_voice');
        } catch (\Throwable $e) {
            Log::warning('GreenLand : réponse vocale impossible, texte envoyé.', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Définition du modèle de contenu (toutes les valeurs en variables : un seul modèle par forme,
     * réutilisé pour toutes les questions) et les variables de cet envoi.
     *
     * @return array{0:string, 1:array, 2:array<string,string>}|null
     */
    private function contentFor(string $body, array $interactive): ?array
    {
        $body = mb_substr(trim($body), 0, self::INTERACTIVE_BODY_MAX, 'UTF-8');

        $buttons = array_slice((array) ($interactive['buttons'] ?? []), 0, 3);
        if (($interactive['type'] ?? null) === 'buttons' && $buttons !== []) {
            $count = count($buttons);
            $variables = ['1' => $body];
            $actions = [];
            foreach (array_values($buttons) as $i => $button) {
                $variables[(string) (2 + $i)] = mb_substr((string) $button['title'], 0, 20, 'UTF-8');
                $variables[(string) (2 + $count + $i)] = mb_substr((string) $button['id'], 0, 200, 'UTF-8');
                $actions[] = ['title' => '{{' . (2 + $i) . '}}', 'id' => '{{' . (2 + $count + $i) . '}}'];
            }

            ksort($variables, SORT_NATURAL);

            return ['buttons_' . $count, [
                'friendly_name' => 'greenland_boutons_' . $count,
                'language' => 'fr',
                'variables' => self::samples($variables),
                'types' => [
                    'twilio/quick-reply' => ['body' => '{{1}}', 'actions' => $actions],
                    'twilio/text' => ['body' => '{{1}}'],
                ],
            ], $variables];
        }

        $rows = [];
        $fallbackDescription = '';
        foreach ((array) ($interactive['sections'] ?? []) as $section) {
            $fallbackDescription = $fallbackDescription !== '' ? $fallbackDescription : trim((string) ($section['title'] ?? ''));
            foreach ((array) ($section['rows'] ?? []) as $row) {
                $rows[] = $row;
            }
        }
        $rows = array_slice($rows, 0, 10);
        if ($rows === []) {
            return null;
        }

        $count = count($rows);
        $button = trim((string) ($interactive['button'] ?? ''));
        $variables = ['1' => $body, '2' => mb_substr($button !== '' ? $button : 'Choisir', 0, 20, 'UTF-8')];
        $items = [];
        $next = 3;
        foreach ($rows as $row) {
            // La description est obligatoire chez Twilio : à défaut, le titre de la section (« Budget »…).
            $description = trim((string) ($row['description'] ?? ''));
            $description = $description !== '' ? $description : ($fallbackDescription !== '' ? $fallbackDescription : (string) $row['title']);

            $variables[(string) $next] = mb_substr((string) $row['title'], 0, 24, 'UTF-8');
            $variables[(string) ($next + 1)] = mb_substr((string) $row['id'], 0, 200, 'UTF-8');
            $variables[(string) ($next + 2)] = mb_substr($description, 0, 72, 'UTF-8');
            $items[] = ['item' => '{{' . $next . '}}', 'id' => '{{' . ($next + 1) . '}}', 'description' => '{{' . ($next + 2) . '}}'];
            $next += 3;
        }

        return ['list_' . $count, [
            'friendly_name' => 'greenland_liste_' . $count,
            'language' => 'fr',
            'variables' => self::samples($variables),
            'types' => [
                'twilio/list-picker' => ['body' => '{{1}}', 'button' => '{{2}}', 'items' => $items],
                'twilio/text' => ['body' => '{{1}}'],
            ],
        ], $variables];
    }

    /** Identifiant du modèle de contenu : configuré, déjà créé (cache), ou créé maintenant. */
    private function contentSid(string $key, array $definition): ?string
    {
        $configured = $this->options['content_sids'][$key] ?? null;
        if (is_string($configured) && str_starts_with($configured, 'HX')) {
            return $configured;
        }

        try {
            $cached = Cache::get($this->contentCacheKey($key));
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        } catch (\Throwable) {
            // Cache indisponible : le modèle est recréé.
        }

        try {
            $response = Http::timeout(15)
                ->withBasicAuth($this->accountSid, $this->authToken)
                ->acceptJson()
                ->post(self::CONTENT_URL, $definition);

            $sid = $response->successful() ? $response->json('sid') : null;
            if (!is_string($sid) || $sid === '') {
                Log::warning('GreenLand : modèle de contenu Twilio non créé, question envoyée en texte.', [
                    'key' => $key,
                    'status' => $response->status(),
                    'body' => mb_substr((string) $response->body(), 0, 300, 'UTF-8'),
                ]);
                return null;
            }

            try {
                Cache::put($this->contentCacheKey($key), $sid, self::CONTENT_CACHE_SECONDS);
            } catch (\Throwable) {
            }
            Log::info('GreenLand : modèle de contenu Twilio créé.', ['key' => $key, 'sid' => $sid]);

            return $sid;
        } catch (\Throwable $e) {
            Log::warning('GreenLand : Content API Twilio injoignable, question envoyée en texte.', ['key' => $key, 'error' => $e->getMessage()]);
            return null;
        }
    }

    private function forgetContent(string $key): void
    {
        try {
            Cache::forget($this->contentCacheKey($key));
        } catch (\Throwable) {
        }
    }

    private function contentCacheKey(string $key): string
    {
        return 'greenland_twilio_content:' . $this->accountSid . ':' . $key . ':v2';
    }

    /** Envoi Twilio ; renvoie le SID du message, ou null en cas d'échec (journalisé). */
    private function create(string $to, array $params, bool $logAsError = true): ?string
    {
        try {
            $message = $this->twilio->messages->create('whatsapp:' . $to, ['from' => 'whatsapp:' . $this->from] + $params);
            $sid = (string) ($message->sid ?? '');

            return $sid !== '' ? $sid : null;
        } catch (\Throwable $e) {
            if ($logAsError) {
                Log::error('GreenLand : envoi WhatsApp (Twilio) impossible.', ['to' => $to, 'error' => $e->getMessage()]);
            } else {
                Log::info('GreenLand : envoi enrichi refusé par Twilio, repli en texte.', ['to' => $to, 'error' => $e->getMessage()]);
            }
            return null;
        }
    }

    private function archiveMessage(array $message): void
    {
        if ($this->archive === null) {
            return;
        }

        try {
            ($this->archive)($message);
        } catch (\Throwable $e) {
            Log::warning('GreenLand : message envoyé non archivé.', ['error' => $e->getMessage()]);
        }
    }

    private function pause(int $milliseconds): void
    {
        if ($milliseconds <= 0) {
            return;
        }
        if ($this->sleep !== null) {
            ($this->sleep)($milliseconds);
            return;
        }
        usleep($milliseconds * 1000);
    }

    /** @return list<array{id:string, title:string}> */
    private static function optionsOf(array $interactive): array
    {
        $options = [];
        foreach ((array) ($interactive['buttons'] ?? []) as $button) {
            $options[] = ['id' => (string) ($button['id'] ?? ''), 'title' => (string) ($button['title'] ?? '')];
        }
        foreach ((array) ($interactive['sections'] ?? []) as $section) {
            foreach ((array) ($section['rows'] ?? []) as $row) {
                $options[] = ['id' => (string) ($row['id'] ?? ''), 'title' => (string) ($row['title'] ?? '')];
            }
        }

        return array_values(array_filter($options, static fn (array $o): bool => $o['id'] !== '' && $o['title'] !== ''));
    }

    /** Valeurs d'exemple exigées par la Content API (une par variable). */
    private static function samples(array $variables): array
    {
        $samples = [];
        foreach (array_keys($variables) as $key) {
            $samples[(string) $key] = 'Exemple ' . $key;
        }

        return $samples;
    }

    /** Twilio limite un message texte à 1 600 caractères : découpe entre deux paragraphes si possible. */
    private static function split(string $text): array
    {
        $chunks = [];
        while (mb_strlen($text, 'UTF-8') > self::BODY_MAX) {
            $part = mb_substr($text, 0, self::BODY_MAX, 'UTF-8');
            $cut = mb_strrpos($part, "\n\n", 0, 'UTF-8');
            if ($cut === false || $cut < self::BODY_MAX / 2) {
                $cut = mb_strrpos($part, ' ', 0, 'UTF-8');
                $cut = $cut === false || $cut < self::BODY_MAX / 2 ? self::BODY_MAX : $cut;
            }
            $chunks[] = rtrim(mb_substr($text, 0, $cut, 'UTF-8'));
            $text = ltrim(mb_substr($text, $cut, null, 'UTF-8'));
        }
        $chunks[] = $text;

        return array_values(array_filter($chunks, static fn (string $c): bool => trim($c) !== ''));
    }
}
