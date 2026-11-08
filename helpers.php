<?php
/**
 * Fungsi bantu global untuk Controller dan View.
 *
 * Dimuat sekali oleh public/index.php. Semua fungsi dibungkus
 * function_exists() agar aman dimuat ulang.
 *
 * @package App\Core
 */

declare(strict_types=1);

if (!function_exists('e')) {
    /**
     * Escape HTML untuk output di View (cegah XSS).
     */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('url')) {
    /**
     * URL absolut ke sebuah rute, mis. url('/quotations/5').
     */
    function url(string $path = ''): string
    {
        return BASE_URL . ($path === '' ? '' : '/' . ltrim($path, '/'));
    }
}

if (!function_exists('asset')) {
    /**
     * URL aset statis di public/assets, mis. asset('css/tailwind.css').
     * Parameter ?v=<waktu ubah file> ditambahkan agar browser memuat ulang
     * file yang baru berubah.
     */
    function asset(string $path): string
    {
        $path = ltrim($path, '/');
        $file = PUBLIC_PATH . '/assets/' . $path;
        $ver  = (!str_contains($path, '..') && is_file($file)) ? '?v=' . filemtime($file) : '';

        return url('assets/' . $path) . $ver;
    }
}

if (!function_exists('status_badge')) {
    /**
     * Badge status quotation (warna plus titik dan teks, jadi tidak hanya
     * mengandalkan warna). Kelas Tailwind di sini ikut dipindai saat build CSS.
     */
    function status_badge(string $status): string
    {
        $map = [
            'Draft'    => ['bg-moss-100 text-moss-700 dark:bg-moss-800 dark:text-moss-200', 'bg-moss-500'],
            'Sent'     => ['bg-sky-100 text-sky-800 dark:bg-sky-900/50 dark:text-sky-200', 'bg-sky-500'],
            'Accepted' => ['bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-200', 'bg-green-600'],
            'Rejected' => ['bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-200', 'bg-red-600'],
        ];
        [$class, $dot] = $map[$status] ?? $map['Draft'];

        return '<span class="badge ' . $class . '"><span class="h-1.5 w-1.5 rounded-full ' . $dot
            . '" aria-hidden="true"></span>' . e($status) . '</span>';
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return (string) ($_SESSION['csrf_token'] ?? '');
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Input hidden berisi token CSRF untuk setiap form POST.
     */
    function csrf_field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('format_idr')) {
    /**
     * Format angka rupiah gaya Indonesia: 904170000 -> "904.170.000".
     * Pecahan hanya ditampilkan bila ada.
     */
    function format_idr(float|int|string $amount): string
    {
        $value = (float) $amount;
        $whole = abs($value - round($value)) < 0.005;

        return number_format($value, $whole ? 0 : 2, ',', '.');
    }
}

if (!function_exists('date_id')) {
    /**
     * Tanggal Y-m-d menjadi "02 Nov 2026" (singkatan bulan bahasa Indonesia).
     */
    function date_id(?string $ymd): string
    {
        if ($ymd === null || !preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $ymd, $m)) {
            return '';
        }
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des'];

        return $m[3] . ' ' . ($months[(int) $m[2] - 1] ?? '') . ' ' . $m[1];
    }
}

if (!function_exists('mes_terbilang_int')) {
    /**
     * Angka bulat positif menjadi kata bahasa Indonesia (tanpa kata "rupiah").
     */
    function mes_terbilang_int(int $n): string
    {
        static $ones = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];

        return match (true) {
            $n < 12            => $ones[$n],
            $n < 20            => mes_terbilang_int($n - 10) . ' belas',
            $n < 100           => mes_terbilang_int(intdiv($n, 10)) . ' puluh' . ($n % 10 ? ' ' . mes_terbilang_int($n % 10) : ''),
            $n < 200           => 'seratus' . ($n - 100 ? ' ' . mes_terbilang_int($n - 100) : ''),
            $n < 1000          => mes_terbilang_int(intdiv($n, 100)) . ' ratus' . ($n % 100 ? ' ' . mes_terbilang_int($n % 100) : ''),
            $n < 2000          => 'seribu' . ($n - 1000 ? ' ' . mes_terbilang_int($n - 1000) : ''),
            $n < 1_000_000     => mes_terbilang_int(intdiv($n, 1000)) . ' ribu' . ($n % 1000 ? ' ' . mes_terbilang_int($n % 1000) : ''),
            $n < 1_000_000_000 => mes_terbilang_int(intdiv($n, 1_000_000)) . ' juta' . ($n % 1_000_000 ? ' ' . mes_terbilang_int($n % 1_000_000) : ''),
            $n < 1_000_000_000_000 => mes_terbilang_int(intdiv($n, 1_000_000_000)) . ' miliar' . ($n % 1_000_000_000 ? ' ' . mes_terbilang_int($n % 1_000_000_000) : ''),
            default            => mes_terbilang_int(intdiv($n, 1_000_000_000_000)) . ' triliun' . ($n % 1_000_000_000_000 ? ' ' . mes_terbilang_int($n % 1_000_000_000_000) : ''),
        };
    }
}

if (!function_exists('terbilang')) {
    /**
     * Terbilang rupiah: 904170000 -> "Sembilan ratus empat juta seratus tujuh puluh ribu rupiah".
     */
    function terbilang(float|int $amount): string
    {
        $n = (int) round($amount);
        if ($n === 0) {
            return 'Nol rupiah';
        }
        $text = ($n < 0 ? 'minus ' : '') . mes_terbilang_int(abs($n)) . ' rupiah';

        return mb_strtoupper(mb_substr($text, 0, 1)) . mb_substr($text, 1);
    }
}

if (!function_exists('uploaded_image_src')) {
    /**
     * Sumber gambar yang aman untuk <img>: path relatif dari database
     * (mis. "assets/img/signature/logo-ab12.png") dicek polanya dan
     * keberadaan filenya di dalam public/.
     *
     * @param bool $embed true = data URI base64 (untuk PDF), false = URL biasa
     *
     * @return string|null null bila path kosong, tidak valid, atau file tidak ada
     */
    function uploaded_image_src(?string $relativePath, bool $embed = false): ?string
    {
        if ($relativePath === null || $relativePath === ''
            || !preg_match('#^assets/img/(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9._-]+\.(png|jpe?g)$#i', $relativePath, $m)
            || str_contains($relativePath, '..')) {
            return null;
        }

        $full = realpath(PUBLIC_PATH . '/' . $relativePath);
        $root = realpath(PUBLIC_PATH);
        if ($full === false || $root === false || !str_starts_with($full, $root . DIRECTORY_SEPARATOR) || !is_file($full)) {
            return null;
        }
        if (!$embed) {
            return url($relativePath);
        }

        $size = filesize($full);
        if ($size === false || $size > 5 * 1024 * 1024) {
            return null;
        }
        $mime = strtolower($m[1]) === 'png' ? 'image/png' : 'image/jpeg';

        return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($full));
    }
}
