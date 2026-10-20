<?php

declare(strict_types=1);

namespace App\Services\GreenLand;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Lecture des photos envoyées par les prospects (capture de la publicité, plan, photo d'un appartement,
 * question écrite sur une image…) par un modèle de vision, via la clé OpenRouter de l'assistant.
 *
 * Les documents personnels (pièce d'identité, relevé bancaire, fiche de paie…) ne sont pas décrits :
 * ils sont classés « document_personnel » et transmis au conseiller sans être lus.
 */
final class ImageReader
{
    private const OPENROUTER_CHAT_URL = 'https://openrouter.ai/api/v1/chat/completions';

    public const DEFAULTS = [
        'enabled' => true,
        'model' => 'google/gemini-3.1-flash-lite',
        'max_bytes' => 5 * 1024 * 1024,
        'timeout' => 30,
    ];

    public const CATEGORIES = ['publicite', 'plan', 'appartement', 'localisation', 'capture_texte', 'document_personnel', 'autre'];

    /** Légendes qui annoncent un document personnel : il n'est même pas envoyé au modèle. */
    private const PRIVATE_CAPTION_PATTERN = '/(?:\bcin\b|carte\s+(?:d\s*)?identit|passeport|passport|relev[eé]\s+bancaire|bank\s+statement|fiche\s+de\s+paie|bulletin\s+de\s+salaire|payslip|attestation\s+de\s+salaire|rib\b|بطاقة\s*التعريف|جواز\s*السفر|كشف\s*الحساب)/iu';

    private array $settings;

    public function __construct(?array $settings = null)
    {
        $this->settings = $settings !== null
            ? array_replace(self::DEFAULTS, $settings)
            : (array) Settings::get('vision', self::DEFAULTS);
    }

    public static function captionLooksPrivate(?string $caption): bool
    {
        return $caption !== null && preg_match(self::PRIVATE_CAPTION_PATTERN, $caption) === 1;
    }

    /**
     * @return array{category:string, description:string, text:?string}|null null si l'image n'a pas pu être lue
     */
    public function read(string $image, string $mimeType = 'image/jpeg', ?string $caption = null): ?array
    {
        if (self::captionLooksPrivate($caption)) {
            return ['category' => 'document_personnel', 'description' => '', 'text' => null];
        }
        if (!$this->settings['enabled'] || $image === '' || strlen($image) > (int) $this->settings['max_bytes']) {
            return null;
        }

        $key = Settings::openRouterKey();
        if ($key === null) {
            return null;
        }

        $mime = strtolower(trim(explode(';', $mimeType)[0]));
        $mime = in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true) ? $mime : 'image/jpeg';

        $payload = [
            'model' => (string) $this->settings['model'],
            'temperature' => 0,
            'max_tokens' => 400,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => self::prompt()],
                ['role' => 'user', 'content' => [
                    ['type' => 'text', 'text' => $caption !== null && trim($caption) !== '' ? 'Légende du prospect : ' . trim($caption) : 'Pas de légende.'],
                    ['type' => 'image_url', 'image_url' => ['url' => 'data:' . $mime . ';base64,' . base64_encode($image)]],
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
                Log::warning('GreenLand : lecture d\'image refusée.', ['status' => $response->status(), 'body' => mb_substr((string) $response->body(), 0, 300)]);
                return null;
            }

            $content = $response->json('choices.0.message.content');
            $data = is_string($content) ? json_decode((string) preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($content)), true) : $content;
            if (!is_array($data)) {
                return null;
            }

            $category = is_string($data['categorie'] ?? null) ? strtolower(trim($data['categorie'])) : 'autre';
            $category = in_array($category, self::CATEGORIES, true) ? $category : 'autre';
            if ($category === 'document_personnel') {
                return ['category' => $category, 'description' => '', 'text' => null];
            }

            $description = trim((string) ($data['description'] ?? ''));
            $text = trim((string) ($data['texte'] ?? ''));

            return [
                'category' => $category,
                'description' => mb_substr($description, 0, 400, 'UTF-8'),
                'text' => $text !== '' ? mb_substr($text, 0, 400, 'UTF-8') : null,
            ];
        } catch (\Throwable $e) {
            Log::warning('GreenLand : lecture d\'image impossible.', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private static function prompt(): string
    {
        return <<<'PROMPT'
Tu aides la conseillère virtuelle de GreenLand, un projet immobilier neuf à Casablanca (Sidi Messoud). Un prospect vient d'envoyer cette image sur WhatsApp.

Décris-la en une ou deux phrases, en français, de façon utile pour lui répondre : capture d'une publicité GreenLand, plan d'appartement (typologie, nombre de chambres si visible), photo d'un intérieur ou d'une façade, carte ou itinéraire, capture d'écran d'une conversation ou d'une annonce, etc. Ne devine pas ce qui n'est pas visible.
Si l'image contient une question ou un texte important écrit par le prospect, recopie-le dans "texte".

Catégories possibles : publicite, plan, appartement, localisation, capture_texte, document_personnel, autre.
Pièce d'identité, passeport, permis, relevé bancaire, fiche de paie, contrat ou tout document personnel : "categorie": "document_personnel", "description": "" et "texte": "" — ne recopie AUCUNE donnée personnelle.

Réponds uniquement avec ce JSON :
{"categorie": "...", "description": "...", "texte": "..."}
PROMPT;
    }
}
