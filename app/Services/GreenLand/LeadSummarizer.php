<?php

declare(strict_types=1);

namespace App\Services\GreenLand;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Résumé de la conversation pour le commercial (2 à 3 phrases, en français), rédigé par l'IA :
 * ce que cherche le prospect, ses questions, ses objections et ce qu'il attend du rappel.
 */
final class LeadSummarizer
{
    private const URL = 'https://openrouter.ai/api/v1/chat/completions';
    private const MESSAGES_KEPT = 24;

    public function summarize(array $history, array $lead = []): ?string
    {
        $key = Settings::openRouterKey();
        $lines = [];
        foreach (array_slice($history, -self::MESSAGES_KEPT) as $item) {
            $content = trim((string) ($item['content'] ?? ''));
            if ($content !== '') {
                $lines[] = (($item['role'] ?? '') === 'assistant' ? 'Assistant' : 'Prospect') . ' : ' . mb_substr($content, 0, 500, 'UTF-8');
            }
        }
        if ($key === null || $lines === []) {
            return null;
        }

        $facts = array_filter([
            'typologie' => $lead['property_type'] ?? null,
            'budget' => $lead['budget'] ?? ($lead['budget_range'] ?? null),
            'projet' => $lead['purpose'] ?? null,
            'delai' => $lead['timeline'] ?? null,
            'financement' => $lead['financing'] ?? null,
        ], static fn ($v): bool => $v !== null && $v !== '');

        $model = Settings::secret('services.openrouter.model', 'OPENROUTER_MODEL') ?? 'openai/gpt-4o-mini';
        if (!str_contains($model, '/')) {
            $model = 'openai/' . $model;
        }

        try {
            $response = Http::timeout(20)
                ->withToken($key)
                ->withHeaders(['X-Title' => 'GreenLand WhatsApp Agent'])
                ->acceptJson()
                ->post(self::URL, [
                    'model' => $model,
                    'temperature' => 0.2,
                    'max_tokens' => 220,
                    'messages' => [
                        ['role' => 'system', 'content' => 'Tu prépares un commercial immobilier (projet GreenLand, Casablanca) avant qu’il rappelle un prospect. '
                            . 'Résume la conversation WhatsApp en 2 ou 3 phrases courtes, en français, sur une seule ligne : ce que cherche le prospect, '
                            . 'ses questions ou objections importantes, son niveau d’urgence et ce qu’il attend de l’appel. '
                            . 'N’invente rien, pas de formule de politesse, pas de numéro de téléphone.'],
                        ['role' => 'user', 'content' => ($facts !== [] ? 'Faits connus : ' . json_encode($facts, JSON_UNESCAPED_UNICODE) . "\n\n" : '') . implode("\n", $lines)],
                    ],
                ]);

            $text = $response->successful() ? $response->json('choices.0.message.content') : null;
            if (!is_string($text) || trim($text) === '') {
                return null;
            }

            return mb_substr(trim((string) preg_replace('/\s+/u', ' ', $text)), 0, 600, 'UTF-8');
        } catch (\Throwable $e) {
            Log::info('GreenLand : résumé du lead impossible.', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
