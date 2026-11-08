<?php
/**
 * Konfigurasi dasar aplikasi MES Quotation.
 *
 * File ini mengembalikan array konfigurasi (bukan kelas) agar bisa dimuat
 * sekali oleh public/index.php. Nilai sensitif dibaca dari file .env di root
 * proyek (salin dari .env.example; tidak masuk Git) atau dari environment
 * variable server, yang selalu diutamakan:
 *
 *   APP_ENV     production | development   (default: production)
 *   APP_DEBUG   1 | 0                       (default: 0)
 *   APP_URL     URL dasar eksplisit, mis. https://quotation.contoh.co.id
 *               (opsional; bila kosong dideteksi otomatis dari request)
 *   DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS
 *   AUTH_IDLE_MINUTES, AUTH_ABSOLUTE_HOURS, AUTH_MAX_ATTEMPTS,
 *   AUTH_MAX_IP_ATTEMPTS, AUTH_LOCKOUT_MINUTES, AUTH_MIN_PASSWORD
 *
 * @package App\Config
 */

declare(strict_types=1);

use App\Core\Env;

return (static function (): array {
    $root = dirname(__DIR__, 2);

    // File .env (di luar public/ dan tidak masuk Git). Environment asli server menang atas isinya.
    Env::load($root . '/.env');

    /** Membaca konfigurasi dengan nilai bawaan. */
    $env = static fn (string $key, ?string $default = null): ?string => Env::get($key, $default);

    // ---------------------------------------------------------------
    // Deteksi base URL otomatis
    // ---------------------------------------------------------------
    $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');

    // Header Host berasal dari klien, jadi hanya diterima bila formatnya valid.
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if (!preg_match('/^(?:[A-Za-z0-9.-]+|\[[0-9A-Fa-f:.]+\])(?::\d{1,5})?$/', $host)) {
        $host = (string) ($_SERVER['SERVER_NAME'] ?? 'localhost');
    }

    // Subfolder tempat index.php berada, mis. "/mes-quotation/public" atau "".
    $script   = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $basePath = rtrim(str_replace('\\', '/', dirname($script)), '/');
    if ($basePath === '.' || !preg_match('#^[A-Za-z0-9_\-./~%]*$#', $basePath)) {
        $basePath = '';
    }

    $explicitUrl = $env('APP_URL');
    if ($explicitUrl !== null) {
        $baseUrl  = rtrim($explicitUrl, '/');
        $urlPath  = parse_url($baseUrl, PHP_URL_PATH);
        $basePath = is_string($urlPath) ? rtrim($urlPath, '/') : '';
    } else {
        $baseUrl = ($https ? 'https' : 'http') . '://' . $host . $basePath;
    }

    // ---------------------------------------------------------------
    // Konfigurasi akhir
    // ---------------------------------------------------------------
    return [
        'app' => [
            'name'      => 'MES Quotation',
            'env'       => $env('APP_ENV', 'production'),
            'debug'     => in_array(strtolower((string) $env('APP_DEBUG', '0')), ['1', 'true', 'on'], true),
            'timezone'  => 'Asia/Jakarta',
            'base_url'  => $baseUrl,   // tanpa slash di akhir
            'base_path' => $basePath,  // bagian path dari base_url, dipakai Router
        ],
        'db' => [
            'host'      => $env('DB_HOST', '127.0.0.1'),
            'port'      => (int) $env('DB_PORT', '3306'),
            'name'      => $env('DB_NAME', 'mes_quotation'),
            'user'      => $env('DB_USER', 'root'),
            'pass'      => $env('DB_PASS', ''),
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            // Di production koneksi ditolak bila memakai root atau password kosong/contoh.
            'require_secure' => strtolower((string) $env('APP_ENV', 'production')) === 'production',
        ],
        'auth' => [
            'idle_minutes'    => Env::int('AUTH_IDLE_MINUTES', 30, 5, 1440),
            'absolute_hours'  => Env::int('AUTH_ABSOLUTE_HOURS', 8, 1, 72),
            'max_attempts'    => Env::int('AUTH_MAX_ATTEMPTS', 5, 3, 50),
            'max_ip_attempts' => Env::int('AUTH_MAX_IP_ATTEMPTS', 20, 5, 500),
            'window_minutes'  => Env::int('AUTH_LOCKOUT_MINUTES', 15, 1, 1440),
            'min_password'    => Env::int('AUTH_MIN_PASSWORD', 10, 8, 64),
        ],
        'paths' => [
            'root'      => $root,
            'public'    => $root . '/public',
            'storage'   => $root . '/storage',
            'pdf_cache' => $root . '/storage/outputs/generated_pdfs',
            'signature' => $root . '/public/assets/img/signature',
        ],
    ];
})();
