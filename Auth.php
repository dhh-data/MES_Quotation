<?php
/**
 * Autentikasi dan otorisasi berbasis session.
 *
 *  - Password disimpan sebagai hash bcrypt (password_hash/password_verify).
 *  - Pembatasan percobaan gagal per (username + IP) dan per IP.
 *  - Waktu tunggu: tidak aktif (idle) dan batas mutlak sejak login.
 *  - Session diganti ID-nya saat login, ganti password, dan logout.
 *  - Data pengguna dibaca ulang dari database di setiap request, sehingga akun
 *    yang dinonaktifkan atau diubah role-nya langsung berlaku.
 *  - Router memanggil Auth::guard() sebelum setiap controller dijalankan.
 *
 * @package App\Core
 */

declare(strict_types=1);

namespace App\Core;

use App\Controllers\AuthController;
use App\Controllers\SettingsController;
use App\Models\UserModel;

final class Auth
{
    /** Hash dummy agar waktu respons sama untuk username yang tidak ada. */
    private const DUMMY_HASH = '$2y$10$aCc3n5xUEGz1GoWlJqy./ezSquurjY2sTjwCrpp1BUE5BYxWmXhAe';

    /** Controller yang hanya boleh dibuka role tertentu. */
    private const ROLE_RULES = [
        SettingsController::class => ['admin'],
    ];

    /** @var array{idle_minutes: int, absolute_hours: int, max_attempts: int, max_ip_attempts: int, window_minutes: int, min_password: int} */
    private static array $cfg = [
        'idle_minutes'    => 30,
        'absolute_hours'  => 8,
        'max_attempts'    => 5,
        'max_ip_attempts' => 20,
        'window_minutes'  => 15,
        'min_password'    => 10,
    ];

    /** @var array<string, mixed>|null */
    private static ?array $user = null;
    private static bool $loaded = false;

    /**
     * @param array<string, int> $cfg Bagian 'auth' dari app_config.php
     */
    public static function configure(array $cfg): void
    {
        self::$cfg    = array_merge(self::$cfg, array_intersect_key($cfg, self::$cfg));
        self::$user   = null;
        self::$loaded = false;
    }

    public static function config(string $key): int
    {
        return (int) (self::$cfg[$key] ?? 0);
    }

    // ------------------------------------------------------------------
    // Status login
    // ------------------------------------------------------------------

    /**
     * Pengguna yang sedang login, atau null.
     *
     * @return array<string, mixed>|null
     */
    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$user;
        }
        self::$loaded = true;

        $auth = $_SESSION['auth'] ?? null;
        if (!is_array($auth) || !isset($auth['id'], $auth['login_at'], $auth['last_seen'])) {
            return self::$user = null;
        }

        $now = time();
        if ($now - (int) $auth['last_seen'] > self::$cfg['idle_minutes'] * 60) {
            self::expire('Sesi berakhir karena tidak ada aktivitas. Silakan masuk lagi.');
            return self::$user = null;
        }
        if ($now - (int) $auth['login_at'] > self::$cfg['absolute_hours'] * 3600) {
            self::expire('Sesi sudah mencapai batas waktu. Silakan masuk lagi.');
            return self::$user = null;
        }

        $row = (new UserModel())->findById((int) $auth['id']);
        if ($row === null || (int) $row['is_active'] !== 1) {
            self::expire('Akun tidak aktif. Hubungi administrator.');
            return self::$user = null;
        }

        $_SESSION['auth']['last_seen'] = $now;
        unset($row['password_hash']);

        return self::$user = $row;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isAdmin(): bool
    {
        return (self::user()['role'] ?? '') === 'admin';
    }

    public static function mustChangePassword(): bool
    {
        return (int) (self::user()['must_change_password'] ?? 0) === 1;
    }

    // ------------------------------------------------------------------
    // Login dan logout
    // ------------------------------------------------------------------

    /** Apakah pasangan username+IP (atau IP saja) sedang dikunci. */
    public static function isLocked(string $username, string $ip): bool
    {
        $model  = new UserModel();
        $window = self::$cfg['window_minutes'];

        return $model->countFailures($username, $ip, $window) >= self::$cfg['max_attempts']
            || $model->countIpFailures($ip, $window) >= self::$cfg['max_ip_attempts'];
    }

    /**
     * Memeriksa kredensial. Tidak membuat session; panggil login() bila berhasil.
     * Pesan galat sengaja sama untuk username salah, password salah, dan akun nonaktif.
     *
     * @return array{ok: bool, error?: string, user?: array<string, mixed>}
     */
    public static function attempt(string $username, string $password, string $ip): array
    {
        $username = strtolower(trim($username));
        $generic  = ['ok' => false, 'error' => 'Username atau password salah.'];

        if (self::isLocked($username, $ip)) {
            return ['ok' => false, 'error' => 'Terlalu banyak percobaan gagal. Coba lagi dalam ' . self::$cfg['window_minutes'] . ' menit.'];
        }

        $model = new UserModel();
        if ($username === '' || strlen($username) > 50 || $password === '' || strlen($password) > 1024) {
            $model->recordFailure($username, $ip);
            return $generic;
        }

        $user  = $model->findByUsername($username);
        $valid = password_verify($password, $user['password_hash'] ?? self::DUMMY_HASH);

        if ($user === null || !$valid || (int) $user['is_active'] !== 1) {
            $model->recordFailure($username, $ip);
            return $generic;
        }

        $model->clearFailures($username, $ip);
        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            $model->rehash((int) $user['id'], $password);
        }
        $model->touchLogin((int) $user['id']);
        unset($user['password_hash']);

        return ['ok' => true, 'user' => $user];
    }

    /**
     * Membuka session login untuk pengguna yang sudah terverifikasi.
     *
     * @param array<string, mixed> $user
     */
    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['auth']       = [
            'id'        => (int) $user['id'],
            'login_at'  => time(),
            'last_seen' => time(),
        ];
        self::$user   = null;
        self::$loaded = false;
    }

    /**
     * Mengganti ID session tanpa keluar (setelah ganti password).
     */
    public static function refresh(): void
    {
        session_regenerate_id(true);
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        self::$user   = null;
        self::$loaded = false;
    }

    public static function logout(): void
    {
        $flash = $_SESSION['_flash'] ?? null;
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        if (is_array($flash)) {
            $_SESSION['_flash'] = $flash;
        }
        self::$user   = null;
        self::$loaded = true;
    }

    private static function expire(string $message): void
    {
        unset($_SESSION['auth']);
        session_regenerate_id(true);
        $_SESSION['csrf_token']     = bin2hex(random_bytes(32));
        $_SESSION['_flash']['info'] = $message;
    }

    // ------------------------------------------------------------------
    // Penjaga rute
    // ------------------------------------------------------------------

    /**
     * Dipanggil Router sebelum controller berjalan. Mengalihkan atau
     * menghentikan request yang tidak berhak.
     */
    public static function guard(string $class, string $action, string $method, string $path): void
    {
        $isAuthController = $class === AuthController::class;

        // Halaman masuk terbuka untuk umum; logout harus selalu bisa dipakai.
        if ($isAuthController && in_array($action, ['loginForm', 'login', 'logout'], true)) {
            return;
        }

        $user = self::user();
        if ($user === null) {
            if ($method === 'GET' && !isset($_SESSION['_flash']['info'])) {
                $_SESSION['intended'] = $path;
            }
            self::redirect('/login');
        }

        // Akun dengan password sementara hanya boleh membuka halaman ganti password.
        if ((int) $user['must_change_password'] === 1 && !$isAuthController) {
            self::redirect('/password');
        }

        $allowed = self::ROLE_RULES[$class] ?? null;
        if ($allowed !== null && !in_array((string) $user['role'], $allowed, true)) {
            self::deny(403, 'Anda tidak memiliki izin untuk membuka halaman ini.');
        }
    }

    /**
     * Mengambil dan menghapus tujuan setelah login (hanya path internal yang aman).
     */
    public static function pullIntended(): string
    {
        $path = $_SESSION['intended'] ?? '';
        unset($_SESSION['intended']);

        if (!is_string($path) || $path === '' || $path[0] !== '/' || str_starts_with($path, '//')
            || str_contains($path, '\\') || preg_match('/[\x00-\x1F\x7F]/', $path) === 1
            || str_starts_with($path, '/login') || str_starts_with($path, '/logout') || str_starts_with($path, '/password')) {
            return '/quotations';
        }

        return $path;
    }

    private static function redirect(string $path): never
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Location: ' . BASE_URL . $path, true, 302);
        exit;
    }

    private static function deny(int $status, string $message): never
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        $m = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . "<title>{$status}</title><body style=\"font-family:system-ui,sans-serif;max-width:32rem;margin:15vh auto;padding:0 1rem\">"
            . "<h1 style=\"color:#0b6a3b\">{$status}</h1><p>{$m}</p><p><a href=\"" . htmlspecialchars(BASE_URL . '/quotations', ENT_QUOTES) . '">Kembali</a></p>';
        exit;
    }
}
