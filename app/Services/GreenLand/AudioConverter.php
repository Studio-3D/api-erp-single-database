<?php

declare(strict_types=1);

namespace App\Services\GreenLand;

use Illuminate\Support\Facades\Log;

/**
 * Conversion audio avec ffmpeg (facultatif) :
 *  - vocal WhatsApp (OGG/Opus) → MP3, accepté par tous les modèles de transcription ;
 *  - voix de synthèse (MP3) → OGG/Opus, le seul format affiché comme « message vocal » par WhatsApp.
 * Sans ffmpeg, l'assistant fonctionne quand même (formats d'origine, ou audio simple au lieu d'un vocal).
 */
final class AudioConverter
{
    private const TIMEOUT_SECONDS = 30;

    private static ?string $binary = null;

    public static function available(): bool
    {
        return self::binary() !== null;
    }

    /** OGG/Opus mono, léger (≈ 3 Ko par seconde) : affiché comme un vocal par WhatsApp. */
    public static function toOggOpus(string $audio, string $fromExtension = 'mp3'): ?string
    {
        // PCM brut (16 bits, 24 kHz, mono) : format de certaines voix de synthèse, à décrire à ffmpeg.
        $input = $fromExtension === 'pcm' ? ['-f', 's16le', '-ar', '24000', '-ac', '1'] : [];

        return self::run($audio, $fromExtension, 'ogg', ['-vn', '-ac', '1', '-ar', '48000', '-c:a', 'libopus', '-b:a', '24k', '-application', 'voip'], $input);
    }

    /** MP3 mono 16 kHz : idéal pour la transcription (petit et universel). */
    public static function toMp3(string $audio, string $fromExtension = 'ogg'): ?string
    {
        return self::run($audio, $fromExtension, 'mp3', ['-vn', '-ac', '1', '-ar', '16000', '-c:a', 'libmp3lame', '-b:a', '32k']);
    }

    /** Remet à zéro la détection (tests). */
    public static function reset(?string $binary = null): void
    {
        self::$binary = $binary;
    }

    private static function binary(): ?string
    {
        if (self::$binary !== null) {
            return self::$binary === '' ? null : self::$binary;
        }

        $configured = Settings::get('ffmpeg_path');
        if (is_string($configured) && $configured !== '') {
            return self::$binary = is_executable($configured) ? $configured : '';
        }

        foreach (explode(PATH_SEPARATOR, (string) getenv('PATH')) as $dir) {
            $candidate = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . (PHP_OS_FAMILY === 'Windows' ? 'ffmpeg.exe' : 'ffmpeg');
            if ($dir !== '' && is_file($candidate) && is_executable($candidate)) {
                return self::$binary = $candidate;
            }
        }
        foreach (['/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg', '/opt/homebrew/bin/ffmpeg'] as $candidate) {
            if (is_file($candidate) && is_executable($candidate)) {
                return self::$binary = $candidate;
            }
        }

        self::$binary = '';

        return null;
    }

    private static function run(string $audio, string $fromExtension, string $toExtension, array $options, array $inputOptions = []): ?string
    {
        $binary = self::binary();
        if ($binary === null || $audio === '' || !function_exists('proc_open')) {
            return null;
        }

        $dir = rtrim(sys_get_temp_dir(), '/\\');
        $id = bin2hex(random_bytes(6));
        $input = $dir . DIRECTORY_SEPARATOR . 'greenland-in-' . $id . '.' . preg_replace('/[^a-z0-9]/', '', strtolower($fromExtension));
        $output = $dir . DIRECTORY_SEPARATOR . 'greenland-out-' . $id . '.' . $toExtension;

        try {
            if (file_put_contents($input, $audio) === false) {
                return null;
            }

            $command = array_merge([$binary, '-hide_banner', '-loglevel', 'error', '-nostdin', '-y'], $inputOptions, ['-i', $input], $options, [$output]);
            $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            if (!is_resource($process)) {
                return null;
            }

            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);
            $errors = '';
            $deadline = microtime(true) + self::TIMEOUT_SECONDS;
            do {
                $errors .= (string) stream_get_contents($pipes[2]);
                stream_get_contents($pipes[1]);
                $status = proc_get_status($process);
                if (!$status['running']) {
                    break;
                }
                usleep(20_000);
            } while (microtime(true) < $deadline);

            if ($status['running']) {
                proc_terminate($process, 9);
                Log::warning('GreenLand : conversion audio trop longue, abandonnée.');
            }
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = $status['running'] ? -1 : (int) $status['exitcode'];
            proc_close($process);

            if ($exitCode !== 0 || !is_file($output) || filesize($output) === 0) {
                Log::warning('GreenLand : conversion audio impossible.', ['error' => mb_substr(trim($errors), 0, 300)]);
                return null;
            }

            $converted = file_get_contents($output);

            return $converted === false || $converted === '' ? null : $converted;
        } catch (\Throwable $e) {
            Log::warning('GreenLand : conversion audio impossible.', ['error' => $e->getMessage()]);
            return null;
        } finally {
            @unlink($input);
            @unlink($output);
        }
    }
}
