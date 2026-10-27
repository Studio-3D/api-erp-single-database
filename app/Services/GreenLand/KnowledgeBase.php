<?php

declare(strict_types=1);

namespace App\Services\GreenLand;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * FAQ : réponses ajoutées par l'équipe (commande WhatsApp « #faq question = réponse » si elle est installée)
 * ou dans config/greenland.php ('faq' => [['question' => '…', 'answer' => '…'], …]).
 *
 * L'IA reçoit ces réponses avec les données du projet ; sans IA, find() retrouve la réponse
 * la plus proche d'une question posée par un prospect.
 */
final class KnowledgeBase
{
    private const CACHE_KEY = 'greenland_knowledge_base';
    private const MAX_ENTRIES = 200;
    private const MIN_SCORE = 0.6;

    /** Mots trop courants pour distinguer deux questions. */
    private const STOP_WORDS = [
        'est', 'ce', 'que', 'qu', 'qui', 'quoi', 'il', 'elle', 'ils', 'y', 'a', 't', 'quel', 'quelle', 'quels', 'quelles', 'les', 'des', 'une', 'un', 'le', 'la', 'du', 'de', 'au', 'aux',
        'pour', 'par', 'avec', 'dans', 'sur', 'sous', 'vous', 'nous', 'je', 'il', 'elle', 'on', 'mon', 'ma', 'mes', 'votre', 'vos', 'notre',
        'avez', 'avoir', 'etre', 'sont', 'fait', 'faire', 'peut', 'peux', 'pouvez', 'comment', 'combien', 'pourquoi', 'quand', 'ou', 'y', 'a', 'il',
        'pas', 'plus', 'tres', 'bien', 'aussi', 'svp', 'stp', 'merci', 'bonjour', 'bonsoir', 'salut', 'oui', 'non', 'et', 'en', 'se', 'sa', 'son', 'ses',
        'projet', 'residence', 'greenland', 'appartement', 'appartements',
        'the', 'and', 'what', 'how', 'is', 'are', 'do', 'does', 'you', 'have', 'there', 'can', 'could', 'which', 'when', 'where', 'why', 'it', 'of', 'to', 'in', 'for', 'please',
        'wach', 'kayn', 'kayna', 'dyal', 'dial', 'chno', 'chnou', 'fin', 'kifach', 'chhal', 'wakha', 'salam', 'f', 'l', 'w',
        'هل', 'ما', 'ماذا', 'كيف', 'متى', 'اين', 'في', 'من', 'على', 'الى', 'عن', 'واش', 'شنو', 'فين',
    ];

    /** @return list<array{id:string, question:string, answer:string, keywords:list<string>, added_by:?string, added_at:?string}> */
    public static function all(): array
    {
        $entries = [];

        try {
            foreach ((array) (function_exists('config') ? (config('greenland.faq') ?? []) : []) as $item) {
                if (is_array($item) && is_string($item['question'] ?? null) && is_string($item['answer'] ?? null)) {
                    $entries[] = self::entry($item['question'], $item['answer'], 'config', null);
                }
            }
        } catch (\Throwable) {
            // Configuration absente : seules les réponses apprises sont utilisées.
        }

        return array_merge($entries, self::learned());
    }

    /** Réponse apprise la plus proche de la question, ou null si aucune n'est assez proche. */
    public static function find(string $question): ?array
    {
        $words = self::keywords($question);
        if ($words === []) {
            return null;
        }

        $best = null;
        $bestScore = 0.0;
        foreach (self::all() as $entry) {
            $keywords = $entry['keywords'];
            if ($keywords === []) {
                continue;
            }

            $common = count(array_intersect($words, $keywords));
            $needed = min(2, count($keywords));
            if ($common < $needed) {
                continue;
            }

            $score = $common / min(count($words), count($keywords));
            if ($score >= self::MIN_SCORE && $score > $bestScore) {
                $best = $entry;
                $bestScore = $score;
            }
        }

        return $best;
    }

    /** Ajoute (ou remplace, si la même question existe déjà) une réponse apprise. */
    public static function add(string $question, string $answer, ?string $author = null): array
    {
        $entry = self::entry($question, $answer, $author, date(DATE_ATOM));
        $entries = array_values(array_filter(self::learned(), static fn (array $e): bool => $e['id'] !== $entry['id']));
        $entries[] = $entry;

        self::save(array_slice($entries, -self::MAX_ENTRIES));

        return $entry;
    }

    /** Supprime une réponse par son identifiant (ou son numéro dans la liste, à partir de 1). */
    public static function remove(string $idOrNumber): bool
    {
        $entries = self::learned();
        $before = count($entries);

        if (ctype_digit($idOrNumber) && isset($entries[(int) $idOrNumber - 1])) {
            unset($entries[(int) $idOrNumber - 1]);
        } else {
            $entries = array_filter($entries, static fn (array $e): bool => $e['id'] !== $idOrNumber);
        }

        if (count($entries) === $before) {
            return false;
        }
        self::save(array_values($entries));

        return true;
    }

    /** Réponses ajoutées depuis WhatsApp (hors configuration). */
    public static function learned(): array
    {
        try {
            $stored = Cache::get(self::CACHE_KEY);
        } catch (\Throwable $e) {
            Log::warning('FAQ GreenLand illisible.', ['error' => $e->getMessage()]);
            return [];
        }

        return is_array($stored) ? array_values(array_filter($stored, 'is_array')) : [];
    }

    /** Mots significatifs d'une question (normalisés, sans mots outils). */
    public static function keywords(string $text): array
    {
        $text = self::normalize($text);
        $words = preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $words = array_filter($words, static fn (string $w): bool => mb_strlen($w, 'UTF-8') >= 2 && !in_array($w, self::STOP_WORDS, true));

        return array_values(array_unique(array_map([self::class, 'stem'], $words)));
    }

    private static function entry(string $question, string $answer, ?string $author, ?string $at): array
    {
        $question = trim($question);

        return [
            'id' => substr(sha1(self::normalize($question)), 0, 8),
            'question' => mb_substr($question, 0, 300, 'UTF-8'),
            'answer' => mb_substr(trim($answer), 0, 1000, 'UTF-8'),
            'keywords' => self::keywords($question),
            'added_by' => $author,
            'added_at' => $at,
        ];
    }

    private static function save(array $entries): void
    {
        try {
            Cache::forever(self::CACHE_KEY, $entries);
        } catch (\Throwable $e) {
            Log::warning('FAQ GreenLand non enregistrée.', ['error' => $e->getMessage()]);
        }
    }

    /** Mots de même sens ramenés à un seul (« près », « proche », « à côté » → proximite). */
    private const SYNONYMS = [
        'proximite' => ['pres', 'proche', 'proches', 'proximite', 'cote', 'autour', 'voisinage', 'environs', 'loin', 'distance', 'near', 'nearby', 'close', 'qrib', '9rib'],
        'prix' => ['prix', 'tarif', 'tarifs', 'cout', 'coute', 'combien', 'price', 'cost', 'taman', 'chhal'],
        'mosquee' => ['mosquee', 'mosque', 'masjid', 'jamaa', 'jame3', 'مسجد'],
        'ecole' => ['ecole', 'ecoles', 'school', 'schools', 'lycee', 'college', 'creche', 'madrasa', 'مدرسة'],
        'parking' => ['parking', 'parkings', 'garage', 'stationnement', 'box'],
        'piscine' => ['piscine', 'pool', 'swimming'],
        'syndic' => ['syndic', 'copropriete', 'charges'],
        'livraison' => ['livraison', 'livre', 'delivery', 'handover', 'cles'],
        'transport' => ['tram', 'tramway', 'bus', 'busway', 'transport', 'transports', 'metro'],
        'commerce' => ['commerce', 'commerces', 'magasin', 'magasins', 'supermarche', 'marjane', 'carrefour', 'shopping', 'mall'],
        'sante' => ['hopital', 'clinique', 'pharmacie', 'medecin', 'hospital', 'clinic'],
    ];

    /** Racine grossière : « parkings » et « parking » se rejoignent ; synonymes regroupés. */
    private static function stem(string $word): string
    {
        foreach (self::SYNONYMS as $canonical => $words) {
            if (in_array($word, $words, true)) {
                return $canonical;
            }
        }
        if (preg_match('/^\p{Latin}+$/u', $word) === 1 && mb_strlen($word, 'UTF-8') > 4) {
            return (string) preg_replace('/(?:es|s|x)$/u', '', $word);
        }

        return $word;
    }

    private static function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        if (class_exists(\Normalizer::class)) {
            $decomposed = \Normalizer::normalize($value, \Normalizer::FORM_D);
            if (is_string($decomposed)) {
                $value = (string) preg_replace('/\p{Mn}+/u', '', $decomposed);
            }
        }

        return trim((string) preg_replace("/[\\s'’‘\\-]+/u", ' ', $value));
    }
}
