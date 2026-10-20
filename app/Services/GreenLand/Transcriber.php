<?php

declare(strict_types=1);

namespace App\Services\GreenLand;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Transcription des messages vocaux (darija comprise).
 *
 * Par défaut, la clé OpenRouter de l'assistant suffit :
 *  1. mode « llm » : un modèle multimodal (Gemini Flash) écoute le vocal et le transcrit,
 *     la darija en lettres latines comme sur WhatsApp, avec la langue parlée ;
 *  2. repli « stt » : modèle de transcription (gpt-4o-transcribe) si le premier échoue.
 * Autre fournisseur possible : 'openai' (clé OPENAI_API_KEY).
 *
 * Réglages : config('greenland.transcription') — voir config/greenland.php.
 */
final class Transcriber
{
    private const OPENROUTER_CHAT_URL = 'https://openrouter.ai/api/v1/chat/completions';
    private const OPENROUTER_STT_URL = 'https://openrouter.ai/api/v1/audio/transcriptions';
    private const OPENAI_STT_URL = 'https://api.openai.com/v1/audio/transcriptions';

    public const DEFAULTS = [
        'enabled' => true,
        'provider' => 'openrouter',               // openrouter | openai
        'mode' => 'llm',                          // llm (meilleur en darija) | stt
        'llm_model' => 'google/gemini-3.1-flash-lite',
        'stt_model' => 'openai/gpt-4o-transcribe',
        'openai_model' => 'gpt-4o-transcribe',
        'fallback_to_stt' => true,
        'convert_with_ffmpeg' => true,            // OGG → MP3 avant l'envoi au modèle multimodal
        'max_bytes' => 16 * 1024 * 1024,
        'timeout' => 45,
    ];

    private const LANGUAGES = ['fr', 'darija', 'ar', 'en'];

    private array $settings;

    public function __construct(?array $settings = null)
    {
        $this->settings = $settings !== null
            ? array_replace(self::DEFAULTS, $settings)
            : (array) Settings::get('transcription', self::DEFAULTS);
    }

    /**
     * @return array{text:string, language:?string, provider:string, model:string}|null
     *         null si le vocal est vide, inaudible ou si aucun fournisseur ne répond
     */
    public function transcribe(string $audio, string $mimeType = 'audio/ogg'): ?array
    {
        if (!$this->settings['enabled'] || $audio === '') {
            return null;
        }
        if (strlen($audio) > (int) $this->settings['max_bytes']) {
            Log::info('GreenLand : vocal trop volumineux pour être transcrit.', ['bytes' => strlen($audio)]);
            return null;
        }

        $format = self::formatFromMime($mimeType);

        if ($this->settings['provider'] === 'openai') {
            return $this->viaOpenAi($audio, $format);
        }

        $key = Settings::openRouterKey();
        if ($key === null) {
            Log::warning('GreenLand : transcription impossible, clé OpenRouter absente.');
            return null;
        }

        if ($this->settings['mode'] !== 'stt') {
            $result = $this->viaLanguageModel($audio, $format, $key);
            if ($result !== false) {
                return $result; // transcription, ou null si le vocal est inaudible
            }
            if (!$this->settings['fallback_to_stt']) {
                return null;
            }
        }

        return $this->viaSpeechToText($audio, $format, $key);
    }

    /** Format attendu par les API à partir du type MIME (« audio/ogg; codecs=opus » → « ogg »). */
    public static function formatFromMime(string $mimeType): string
    {
        $mime = strtolower(trim(explode(';', $mimeType)[0]));

        return match ($mime) {
            'audio/mpeg', 'audio/mp3' => 'mp3',
            'audio/mp4', 'audio/m4a', 'audio/x-m4a' => 'm4a',
            'audio/aac' => 'aac',
            'audio/amr' => 'amr',
            'audio/wav', 'audio/x-wav', 'audio/wave' => 'wav',
            'audio/webm' => 'webm',
            'audio/flac' => 'flac',
            default => 'ogg',
        };
    }

    /** @return array|null|false false = échec technique (le repli peut être tenté) */
    private function viaLanguageModel(string $audio, string $format, string $key): array|null|false
    {
        [$audio, $format] = $this->prepareForLanguageModel($audio, $format);

        $payload = [
            'model' => (string) $this->settings['llm_model'],
            'temperature' => 0,
            'max_tokens' => 700,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => self::prompt()],
                ['role' => 'user', 'content' => [
                    ['type' => 'text', 'text' => 'Transcris ce message vocal.'],
                    ['type' => 'input_audio', 'input_audio' => ['data' => base64_encode($audio), 'format' => $format]],
                ]],
            ],
        ];

        try {
            $response = Http::timeout((int) $this->settings['timeout'])
                ->withToken($key)
                ->withHeaders(['X-Title' => 'GreenLand WhatsApp Agent'])
                ->acceptJson()
                ->post(self::OPENROUTER_CHAT_URL, $payload);

            if (!$response->successful()) {
                Log::warning('GreenLand : transcription (modèle multimodal) refusée.', ['status' => $response->status(), 'body' => mb_substr((string) $response->body(), 0, 300)]);
                return false;
            }

            $content = $response->json('choices.0.message.content');
            $data = is_string($content) ? self::decodeJson($content) : (is_array($content) ? $content : null);
            if (!is_array($data)) {
                return false;
            }

            $text = trim((string) ($data['transcription'] ?? $data['text'] ?? ''));
            $audible = !array_key_exists('audible', $data) || $data['audible'] === true || $data['audible'] === 'true' || $data['audible'] === 1;
            if ($text === '' || !$audible) {
                return null;
            }

            $language = is_string($data['langue'] ?? null) ? strtolower(trim($data['langue'])) : null;

            return [
                'text' => mb_substr($text, 0, 2000, 'UTF-8'),
                'language' => in_array($language, self::LANGUAGES, true) ? $language : null,
                'provider' => 'openrouter',
                'model' => (string) $this->settings['llm_model'],
            ];
        } catch (\Throwable $e) {
            Log::warning('GreenLand : transcription (modèle multimodal) impossible.', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function viaSpeechToText(string $audio, string $format, string $key): ?array
    {
        [$audio, $format] = $this->prepareForSpeechToText($audio, $format);

        try {
            $response = Http::timeout((int) $this->settings['timeout'])
                ->withToken($key)
                ->withHeaders(['X-Title' => 'GreenLand WhatsApp Agent'])
                ->acceptJson()
                ->post(self::OPENROUTER_STT_URL, [
                    'model' => (string) $this->settings['stt_model'],
                    'input_audio' => ['data' => base64_encode($audio), 'format' => $format],
                ]);

            if (!$response->successful()) {
                Log::warning('GreenLand : transcription refusée.', ['status' => $response->status(), 'body' => mb_substr((string) $response->body(), 0, 300)]);
                return null;
            }

            return $this->sttResult((string) $response->json('text'), 'openrouter', (string) $this->settings['stt_model']);
        } catch (\Throwable $e) {
            Log::warning('GreenLand : transcription impossible.', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function viaOpenAi(string $audio, string $format): ?array
    {
        $key = Settings::openAiKey();
        if ($key === null) {
            Log::warning('GreenLand : transcription impossible, clé OpenAI absente.');
            return null;
        }

        [$audio, $format] = $this->prepareForSpeechToText($audio, $format);

        try {
            $response = Http::timeout((int) $this->settings['timeout'])
                ->withToken($key)
                ->attach('file', $audio, 'vocal.' . $format)
                ->post(self::OPENAI_STT_URL, ['model' => (string) $this->settings['openai_model'], 'response_format' => 'json']);

            if (!$response->successful()) {
                Log::warning('GreenLand : transcription OpenAI refusée.', ['status' => $response->status(), 'body' => mb_substr((string) $response->body(), 0, 300)]);
                return null;
            }

            return $this->sttResult((string) $response->json('text'), 'openai', (string) $this->settings['openai_model']);
        } catch (\Throwable $e) {
            Log::warning('GreenLand : transcription OpenAI impossible.', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function sttResult(string $text, string $provider, string $model): ?array
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        // Les modèles de transcription ne donnent pas la langue : l'assistant la déduit du texte.
        return ['text' => mb_substr($text, 0, 2000, 'UTF-8'), 'language' => null, 'provider' => $provider, 'model' => $model];
    }

    /** Le modèle multimodal reçoit du MP3 (format le plus sûr) quand ffmpeg est disponible. */
    private function prepareForLanguageModel(string $audio, string $format): array
    {
        if ($this->settings['convert_with_ffmpeg'] && !in_array($format, ['mp3', 'wav'], true)) {
            $mp3 = AudioConverter::toMp3($audio, $format);
            if ($mp3 !== null) {
                return [$mp3, 'mp3'];
            }
        }

        return [$audio, $format];
    }

    /** Les modèles de transcription lisent l'OGG ; l'AMR (vieux téléphones) doit être converti. */
    private function prepareForSpeechToText(string $audio, string $format): array
    {
        if ($format === 'amr') {
            $mp3 = AudioConverter::toMp3($audio, 'amr');
            if ($mp3 !== null) {
                return [$mp3, 'mp3'];
            }
        }

        return [$audio, $format];
    }

    private static function decodeJson(string $content): ?array
    {
        $content = trim($content);
        $content = (string) preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $content);
        $data = json_decode($content, true);
        if (!is_array($data) && preg_match('/\{.*\}/s', $content, $match) === 1) {
            $data = json_decode($match[0], true);
        }

        return is_array($data) ? $data : null;
    }

    private static function prompt(): string
    {
        return <<<'PROMPT'
Tu es un service de transcription. Tu reçois le message vocal WhatsApp d'un prospect immobilier au Maroc (projet GreenLand à Casablanca). Transcris EXACTEMENT ce qui est dit : ne réponds pas, ne résume pas, ne traduis pas, n'ajoute rien.

Règles :
- Darija marocaine : écris-la en lettres latines, comme les Marocains l'écrivent sur WhatsApp (ex. « salam, bghit nchouf chi appartement, chhal taman dyal F3 ? »). Garde les mots français tels qu'ils sont prononcés.
- Arabe classique : en lettres arabes. Français et anglais : orthographe normale, avec la ponctuation.
- Chiffres en chiffres (« 2 chambres », « 15h », « 06 61 23 45 67 ») ; garde les unités telles qu'elles sont dites (« 120 mlyoun », « 1,5 million », « 900 000 dirhams ») sans les convertir.
- Noms propres tels qu'ils sont entendus.
- Si l'audio est vide, inaudible ou ne contient que du bruit : "audible": false et "transcription": "".

Réponds uniquement avec ce JSON :
{"transcription": "...", "langue": "fr|darija|ar|en|autre", "audible": true}
PROMPT;
    }
}
