<?php

declare(strict_types=1);

namespace App\Services\GreenLand;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Réponses vocales : le texte de l'assistant est lu par une voix de synthèse quand le prospect
 * a lui-même envoyé un vocal (réglage greenland.voice_replies).
 *
 * WhatsApp n'affiche un « message vocal » que pour un fichier OGG/Opus (lecture directe jusqu'à 512 Ko) :
 *  - OpenAI (gpt-4o-mini-tts) le produit directement ;
 *  - OpenRouter et ElevenLabs produisent du MP3, converti par ffmpeg s'il est installé
 *    (sinon le MP3 part en audio simple).
 * La darija écrite en lettres latines est mal lue par les voix de synthèse : elle reste en texte par défaut.
 */
final class VoiceSynthesizer
{
    private const OPENAI_URL = 'https://api.openai.com/v1/audio/speech';
    private const OPENROUTER_URL = 'https://openrouter.ai/api/v1/audio/speech';
    private const ELEVENLABS_URL = 'https://api.elevenlabs.io/v1/text-to-speech/';
    private const MAX_VOICE_NOTE_BYTES = 512 * 1024;

    public const DEFAULTS = [
        'provider' => 'openai',                   // openai | openrouter | elevenlabs
        'max_chars' => 700,
        'timeout' => 30,
        'openai' => [
            'model' => 'gpt-4o-mini-tts',
            'voice' => 'coral',
            'instructions' => 'Voix féminine chaleureuse et professionnelle d’une conseillère immobilière marocaine. Débit naturel, ton souriant et rassurant.',
        ],
        'openrouter' => ['model' => 'x-ai/grok-voice-tts-1.0', 'voice' => 'eve', 'format' => 'mp3'], // mp3 | pcm
        'elevenlabs' => ['voice_id' => null, 'model' => 'eleven_multilingual_v2'],
    ];

    private array $settings;

    public function __construct(?array $settings = null)
    {
        $this->settings = $settings !== null
            ? array_replace_recursive(self::DEFAULTS, $settings)
            : (array) Settings::get('voice', self::DEFAULTS);
    }

    /**
     * @return array{bytes:string, mime:string, filename:string, voice:bool}|null
     *         voice = true : OGG/Opus à envoyer comme message vocal ; false : audio simple (MP3)
     */
    public function synthesize(string $text, string $language = 'fr'): ?array
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }
        $text = $this->shorten($text, (int) $this->settings['max_chars']);

        $audio = match ((string) $this->settings['provider']) {
            'openrouter' => $this->viaOpenRouter($text),
            'elevenlabs' => $this->viaElevenLabs($text),
            default => $this->viaOpenAi($text),
        };
        if ($audio === null || $audio === '') {
            return null;
        }

        if (!self::isOggOpus($audio)) {
            $isPcm = $this->settings['provider'] === 'openrouter' && ($this->settings['openrouter']['format'] ?? 'mp3') === 'pcm';
            $converted = AudioConverter::toOggOpus($audio, $isPcm ? 'pcm' : 'mp3');
            if ($converted === null) {
                // Sans ffmpeg : un MP3 part en audio simple ; du PCM brut ne peut pas être envoyé.
                return $isPcm ? null : ['bytes' => $audio, 'mime' => 'audio/mpeg', 'filename' => 'greenland.mp3', 'voice' => false];
            }
            $audio = $converted;
        }

        return ['bytes' => $audio, 'mime' => 'audio/ogg', 'filename' => 'greenland.ogg', 'voice' => strlen($audio) <= self::MAX_VOICE_NOTE_BYTES];
    }

    public static function isOggOpus(string $audio): bool
    {
        return str_starts_with($audio, 'OggS') && str_contains(substr($audio, 0, 512), 'OpusHead');
    }

    private function viaOpenAi(string $text): ?string
    {
        $key = Settings::openAiKey();
        if ($key === null) {
            Log::info('GreenLand : réponse vocale impossible, clé OpenAI absente (texte envoyé).');
            return null;
        }

        $config = (array) $this->settings['openai'];

        return $this->fetch(fn () => Http::timeout((int) $this->settings['timeout'])
            ->withToken($key)
            ->post(self::OPENAI_URL, array_filter([
                'model' => (string) $config['model'],
                'input' => $text,
                'voice' => (string) $config['voice'],
                'instructions' => $config['instructions'] ?? null,
                'response_format' => 'opus',
            ], static fn ($v): bool => $v !== null && $v !== '')));
    }

    private function viaOpenRouter(string $text): ?string
    {
        $key = Settings::openRouterKey();
        if ($key === null) {
            return null;
        }

        $config = (array) $this->settings['openrouter'];

        return $this->fetch(fn () => Http::timeout((int) $this->settings['timeout'])
            ->withToken($key)
            ->withHeaders(['X-Title' => 'GreenLand WhatsApp Agent'])
            ->post(self::OPENROUTER_URL, [
                'model' => (string) $config['model'],
                'input' => $text,
                'voice' => (string) $config['voice'],
                'response_format' => ($config['format'] ?? 'mp3') === 'pcm' ? 'pcm' : 'mp3',
            ]));
    }

    private function viaElevenLabs(string $text): ?string
    {
        $key = Settings::elevenLabsKey();
        $config = (array) $this->settings['elevenlabs'];
        $voiceId = (string) ($config['voice_id'] ?? '');
        if ($key === null || $voiceId === '') {
            Log::info('GreenLand : réponse vocale ElevenLabs impossible (clé ou voice_id manquant).');
            return null;
        }

        return $this->fetch(fn () => Http::timeout((int) $this->settings['timeout'])
            ->withHeaders(['xi-api-key' => $key, 'Accept' => 'audio/mpeg'])
            ->post(self::ELEVENLABS_URL . rawurlencode($voiceId) . '?output_format=mp3_44100_128', [
                'text' => $text,
                'model_id' => (string) $config['model'],
            ]));
    }

    private function fetch(callable $request): ?string
    {
        try {
            $response = $request();
            if (!$response->successful()) {
                Log::warning('GreenLand : synthèse vocale refusée.', ['status' => $response->status(), 'body' => mb_substr((string) $response->body(), 0, 300)]);
                return null;
            }

            $body = (string) $response->body();
            // Une erreur renvoyée en JSON avec un code 200 n'est pas un fichier audio.
            return $body !== '' && !str_starts_with(ltrim($body), '{') ? $body : null;
        } catch (\Throwable $e) {
            Log::warning('GreenLand : synthèse vocale impossible.', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /** Coupe proprement à la fin d'une phrase pour rester sous la limite d'un vocal court. */
    private function shorten(string $text, int $max): string
    {
        if ($max <= 0 || mb_strlen($text, 'UTF-8') <= $max) {
            return $text;
        }

        $cut = mb_substr($text, 0, $max, 'UTF-8');
        $last = max((int) mb_strrpos($cut, '. ', 0, 'UTF-8'), (int) mb_strrpos($cut, '? ', 0, 'UTF-8'), (int) mb_strrpos($cut, '! ', 0, 'UTF-8'));

        return $last > $max / 2 ? mb_substr($cut, 0, $last + 1, 'UTF-8') : $cut;
    }
}
