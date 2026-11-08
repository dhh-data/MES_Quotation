<?php
/**
 * Pembaca file .env sederhana.
 *
 * Format yang didukung:
 *   KEY=nilai
 *   KEY="nilai dengan spasi dan \n"      (escape: \n \r \t \" \\)
 *   KEY='nilai literal'
 *   KEY=nilai   # komentar di akhir baris
 *   export KEY=nilai
 *
 * Aturan prioritas: environment variable asli server (getenv) SELALU menang
 * atas isi file .env. Dengan begitu produksi bisa memakai secret dari server
 * atau container tanpa file di disk. File .env tidak mengubah environment
 * proses (tanpa putenv), jadi tidak ikut bocor ke proses anak.
 *
 * @package App\Core
 */

declare(strict_types=1);

namespace App\Core;

final class Env
{
    /** @var array<string, string> */
    private static array $values = [];

    /**
     * Memuat file .env bila ada. Aman dipanggil berulang.
     *
     * @throws ConfigException Bila file ada tetapi tidak bisa dibaca.
     */
    public static function load(string $file): void
    {
        if (!is_file($file)) {
            return;
        }
        $content = @file_get_contents($file);
        if ($content === false) {
            throw new ConfigException('File .env ada tetapi tidak bisa dibaca. Periksa izin file.');
        }

        // Peringatan (bukan galat) bila file dapat dibaca semua pengguna server.
        $perms = @fileperms($file);
        if ($perms !== false && ($perms & 0004) !== 0) {
            error_log('[MES] Peringatan: .env dapat dibaca semua pengguna. Jalankan: chmod 640 .env');
        }

        self::$values = self::parse($content) + self::$values;
    }

    /**
     * Mengurai isi .env menjadi array.
     *
     * @return array<string, string>
     */
    public static function parse(string $content): array
    {
        $out = [];
        // Buang BOM dan samakan akhir baris.
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;

        foreach (preg_split('/\r\n|\n|\r/', $content) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (str_starts_with($line, 'export ')) {
                $line = ltrim(substr($line, 7));
            }
            $eq = strpos($line, '=');
            if ($eq === false) {
                continue;
            }
            $key = rtrim(substr($line, 0, $eq));
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key) !== 1) {
                continue;
            }
            $out[$key] = self::parseValue(ltrim(substr($line, $eq + 1)));
        }

        return $out;
    }

    /**
     * Nilai konfigurasi: environment asli dulu, lalu file .env, lalu bawaan.
     * Nilai kosong dianggap tidak diisi.
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        $real = getenv($key);
        if ($real !== false && $real !== '') {
            return $real;
        }
        $file = self::$values[$key] ?? '';

        return $file !== '' ? $file : $default;
    }

    /**
     * Nilai boolean: 1, true, on, yes.
     */
    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);
        if ($value === null) {
            return $default;
        }

        return in_array(strtolower($value), ['1', 'true', 'on', 'yes'], true);
    }

    /**
     * Nilai integer dengan batas bawah dan atas.
     */
    public static function int(string $key, int $default, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): int
    {
        $value = self::get($key);
        $n     = ($value !== null && preg_match('/^-?\d+$/', $value) === 1) ? (int) $value : $default;

        return max($min, min($max, $n));
    }

    /** Mengosongkan nilai yang dimuat (untuk pengujian). */
    public static function reset(): void
    {
        self::$values = [];
    }

    private static function parseValue(string $raw): string
    {
        if ($raw === '') {
            return '';
        }

        $quote = $raw[0];
        if ($quote === '"' || $quote === "'") {
            $len = strlen($raw);
            $buf = '';
            for ($i = 1; $i < $len; $i++) {
                $ch = $raw[$i];
                if ($quote === '"' && $ch === '\\' && $i + 1 < $len) {
                    $next = $raw[++$i];
                    $buf .= match ($next) {
                        'n'     => "\n",
                        'r'     => "\r",
                        't'     => "\t",
                        '"'     => '"',
                        '\\'    => '\\',
                        default => '\\' . $next,
                    };
                    continue;
                }
                if ($ch === $quote) {
                    return $buf; // sisa setelah tanda kutip penutup (komentar) diabaikan
                }
                $buf .= $ch;
            }

            return $buf; // tanda kutip tidak ditutup: ambil apa adanya
        }

        // Tanpa kutip: komentar dimulai oleh " #".
        $hash = preg_match('/\s#/', $raw, $m, PREG_OFFSET_CAPTURE);
        if ($hash === 1) {
            $raw = substr($raw, 0, $m[0][1]);
        }

        return rtrim($raw);
    }
}
