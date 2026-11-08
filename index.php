<?php
/**
 * Front controller MES Quotation: satu-satunya pintu masuk aplikasi.
 *
 * Urutan kerja:
 *  1. Mendefinisikan konstanta path dan memuat autoloader.
 *  2. Memuat konfigurasi, mengatur error handling dan zona waktu.
 *  3. Memulai session dengan cookie yang diperkeras.
 *  4. Mendaftarkan rute, lalu Router->dispatch().
 *
 * Document root web server harus menunjuk ke folder public/ ini, sehingga
 * src/, database/, storage/ dan vendor/ tidak bisa diakses dari browser.
 *
 * @package MesQuotation
 */

declare(strict_types=1);

use App\Config\Database;
use App\Core\Auth;
use App\Core\ConfigException;
use App\Core\Router;

if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit('MES Quotation membutuhkan PHP 8.1 atau lebih baru.');
}

// ---------------------------------------------------------------------
// 1. Path dan autoloader
// ---------------------------------------------------------------------
define('ROOT_PATH', dirname(__DIR__));
define('PUBLIC_PATH', __DIR__);

// Composer (mis. DomPDF) bila ada, lalu autoloader PSR-4 App\ -> src/.
require ROOT_PATH . '/src/Core/autoload.php';

// ---------------------------------------------------------------------
// 2. Konfigurasi, error handling, zona waktu
// ---------------------------------------------------------------------
/** @var array{app: array<string, mixed>, db: array<string, mixed>, paths: array<string, string>} $config */
$config = require ROOT_PATH . '/src/Config/app_config.php';
$debug  = (bool) $config['app']['debug'];

define('BASE_URL', (string) $config['app']['base_url']);
define('APP_NAME', (string) $config['app']['name']);

// Fungsi bantu global untuk Controller dan View: e(), url(), asset(), csrf_field(), terbilang(), dll.
require ROOT_PATH . '/src/Core/helpers.php';

error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
date_default_timezone_set((string) $config['app']['timezone']);

// Buffer output supaya redirect dan header tetap bisa dikirim setelah view mulai dirender.
ob_start();

set_exception_handler(static function (Throwable $e) use ($debug): void {
    error_log(sprintf('[MES] %s: %s in %s:%d', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()));

    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }

    if ($e instanceof ConfigException) { // pesan konfigurasi tidak memuat rahasia
        echo '<!doctype html><meta charset="utf-8"><title>Konfigurasi bermasalah</title><h1>Konfigurasi bermasalah</h1><p>'
            . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
        return;
    }

    echo $debug
        ? '<pre>' . htmlspecialchars((string) $e, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>'
        : '<!doctype html><meta charset="utf-8"><title>Terjadi kesalahan</title>'
          . '<h1>Terjadi kesalahan pada server.</h1><p>Silakan coba lagi nanti.</p>';
});

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

Database::configure($config['db']); // koneksi baru dibuka saat Model pertama kali memakainya
Auth::configure($config['auth']);

// ---------------------------------------------------------------------
// 3. Session
// ---------------------------------------------------------------------
$basePath = (string) $config['app']['base_path'];

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.gc_maxlifetime', (string) max(3600, (int) $config['auth']['absolute_hours'] * 3600));
session_name('MES_SESSID');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => $basePath === '' ? '/' : $basePath . '/',
    'secure'   => str_starts_with(BASE_URL, 'https://'),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// Token CSRF untuk form POST; controller membandingkannya dengan hash_equals().
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ---------------------------------------------------------------------
// 4. Rute
// ---------------------------------------------------------------------
$router = new Router($basePath, 'App\\Controllers');
$router->setGuard([Auth::class, 'guard']); // semua rute butuh login kecuali /login dan /logout

// Autentikasi
$router->get('/login', 'AuthController@loginForm');
$router->post('/login', 'AuthController@login');
$router->post('/logout', 'AuthController@logout');
$router->get('/password', 'AuthController@passwordForm');
$router->post('/password', 'AuthController@passwordUpdate');

// Quotation
$router->get('/', 'QuotationController@create');
$router->get('/quotations', 'QuotationController@index');
$router->get('/quotations/create', 'QuotationController@create');
$router->post('/quotations/store', 'QuotationController@store');
$router->get('/quotations/{id}', 'QuotationController@preview');
$router->get('/quotations/{id}/edit', 'QuotationController@edit');
$router->post('/quotations/{id}/update', 'QuotationController@update');
$router->post('/quotations/{id}/status', 'QuotationController@updateStatus');
$router->post('/quotations/{id}/delete', 'QuotationController@delete');

// PDF
$router->get('/quotations/{id}/pdf', 'PdfController@download');      // DomPDF bila terpasang
$router->get('/quotations/{id}/print', 'PdfController@printPage');   // cetak bawaan browser

// Pengaturan perusahaan (hanya role admin)
$router->get('/settings', 'SettingsController@index');
$router->post('/settings', 'SettingsController@update');

$router->dispatch();

ob_end_flush();
