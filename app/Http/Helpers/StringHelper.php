<?php

namespace App\Http\Helpers;
class StringHelper
{
    /**
     * Normalize a string by converting special characters to normal letters
     *
     * @param string $string
     * @param bool $keepSpaces Whether to keep spaces or remove them
     * @return string
     */
    public static function normalize($string, $keepSpaces = true)
    {
        if (empty($string)) {
            return '';
        }

        $charactersMap = [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ý' => 'y', 'ÿ' => 'y',
            'ñ' => 'n', 'ç' => 'c',
            'œ' => 'oe', 'æ' => 'ae',
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A',
            'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O',
            'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ý' => 'Y',
            'Ñ' => 'N', 'Ç' => 'C',
            'Œ' => 'OE', 'Æ' => 'AE',
        ];

        $normalized = strtr($string, $charactersMap);

        if (function_exists('iconv')) {
            $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
            if ($transliterated !== false) {
                $normalized = $transliterated;
            }
        }

        // Remove any remaining non-alphanumeric characters except spaces and hyphens if needed
        if (!$keepSpaces) {
            $normalized = preg_replace('/[^a-zA-Z0-9]/', '', $normalized);
        }

        return $normalized;
    }

    /**
     * Create a slug from a string (normalize + replace spaces)
     *
     * @param string $string
     * @param string $separator
     * @return string
     */
    public static function slugify($string, $separator = '-')
    {
        $normalized = self::normalize($string, false);
        $normalized = strtolower($normalized);
        $normalized = preg_replace('/[^a-z0-9]/', $separator, $normalized);
        $normalized = preg_replace('/' . preg_quote($separator, '/') . '+/', $separator, $normalized);
        $normalized = trim($normalized, $separator);

        return $normalized;
    }
}
