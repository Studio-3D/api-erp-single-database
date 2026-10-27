<?php

declare(strict_types=1);

namespace App\Services\GreenLand;

/**
 * Lecture des réglages (config/greenland.php et config/services.php), avec repli sur .env
 * et sur des valeurs par défaut : aucun réglage manquant ne doit faire planter l'assistant.
 */
final class Settings
{
    /** config('greenland.<clé>') ; les tableaux associatifs sont complétés par les valeurs par défaut. */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::config('greenland.' . $key);

        if (is_array($default) && is_array($value) && !array_is_list($default)) {
            return array_replace_recursive($default, $value);
        }

        return $value ?? $default;
    }

    public static function config(string $key): mixed
    {
        try {
            return function_exists('config') ? config($key) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Valeur texte de config/services.php, sinon de .env. */
    public static function secret(string $configKey, string $envKey): ?string
    {
        $value = self::config($configKey);
        if ($value === null || $value === '') {
            try {
                $value = function_exists('env') ? env($envKey) : null;
            } catch (\Throwable) {
                $value = null;
            }
        }

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    public static function openRouterKey(): ?string
    {
        return self::secret('services.openrouter.key', 'OPENROUTER_API_KEY');
    }

    public static function openAiKey(): ?string
    {
        return self::secret('services.openai.key', 'OPENAI_API_KEY');
    }

    public static function elevenLabsKey(): ?string
    {
        return self::secret('services.elevenlabs.key', 'ELEVENLABS_API_KEY');
    }

    /** Fuseau horaire du Maroc (config greenland.timezone, par défaut Africa/Casablanca). */
    public static function timezone(): string
    {
        $name = self::get('timezone');
        if (is_string($name) && trim($name) !== '') {
            try {
                new \DateTimeZone(trim($name));
                return trim($name);
            } catch (\Throwable) {
            }
        }

        return 'Africa/Casablanca';
    }

    /** Numéro au format WhatsApp (chiffres seulement, indicatif compris) : « +212 6 61… » → « 212661… ». */
    public static function waNumber(?string $phone): string
    {
        $digits = (string) preg_replace('/\D+/', '', (string) $phone);
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0') && strlen($digits) === 10) {
            $digits = '212' . substr($digits, 1); // numéro marocain écrit 06…
        }

        return $digits;
    }

    /** Numéros de l'équipe autorisés aux commandes #… : administrateurs et commerciaux, au format WhatsApp. */
    public static function adminNumbers(): array
    {
        $phones = (array) self::get('admins', []);
        foreach ((array) self::get('commercials', []) as $commercial) {
            if (is_array($commercial) && !empty($commercial['phone'])) {
                $phones[] = $commercial['phone'];
            }
        }

        return array_values(array_unique(array_filter(array_map(
            static fn ($phone): string => self::waNumber(is_scalar($phone) ? (string) $phone : ''),
            $phones
        ))));
    }
}
