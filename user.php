#!/usr/bin/env php
<?php
/**
 * Pengelolaan akun lewat terminal (hanya CLI).
 *
 *   php bin/user.php list
 *   php bin/user.php create <username> <admin|finance> "<Nama lengkap>"
 *   php bin/user.php passwd <username>      (password sementara, wajib diganti saat login)
 *   php bin/user.php disable <username>
 *   php bin/user.php enable <username>
 *   php bin/user.php unlock <username>      (hapus catatan login gagal)
 *
 * Password dibaca dari prompt tersembunyi, atau dari satu baris STDIN bila
 * input bukan terminal (mis. `echo "..." | php bin/user.php passwd budi`).
 * Password tidak pernah diterima sebagai argumen agar tidak tersimpan di
 * riwayat shell.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('ROOT_PATH', dirname(__DIR__));
define('PUBLIC_PATH', ROOT_PATH . '/public');
require ROOT_PATH . '/src/Core/autoload.php';

use App\Config\Database;
use App\Core\ConfigException;
use App\Models\UserModel;

$config = require ROOT_PATH . '/src/Config/app_config.php';
Database::configure($config['db']);
$minLength = (int) $config['auth']['min_password'];

function out(string $line): void
{
    fwrite(STDOUT, $line . PHP_EOL);
}

function fail(string $line, int $code = 1): never
{
    fwrite(STDERR, 'Galat: ' . $line . PHP_EOL);
    exit($code);
}

function askPassword(string $prompt, int $min): string
{
    if (function_exists('posix_isatty') && posix_isatty(STDIN)) {
        fwrite(STDERR, $prompt);
        shell_exec('stty -echo');
        $line = fgets(STDIN);
        shell_exec('stty echo');
        fwrite(STDERR, PHP_EOL);
    } else {
        $line = fgets(STDIN);
    }
    $password = rtrim((string) $line, "\r\n");

    if (mb_strlen($password) < $min || strlen($password) > 72
        || preg_match('/\p{L}/u', $password) !== 1 || preg_match('/\d/', $password) !== 1) {
        fail("Password harus {$min}-72 karakter dan memuat huruf serta angka.");
    }

    return $password;
}

$command = $argv[1] ?? '';
$name    = strtolower($argv[2] ?? '');

try {
    $users = new UserModel();

    switch ($command) {
        case 'list':
            foreach ($users->all() as $u) {
                out(sprintf(
                    '%-20s %-8s %-9s %s%s',
                    $u['username'],
                    $u['role'],
                    (int) $u['is_active'] === 1 ? 'aktif' : 'nonaktif',
                    $u['full_name'],
                    (int) $u['must_change_password'] === 1 ? '  [wajib ganti password]' : ''
                ));
            }
            break;

        case 'create':
            $role     = $argv[3] ?? '';
            $fullName = $argv[4] ?? '';
            if ($name === '' || $role === '' || $fullName === '') {
                fail('Pemakaian: php bin/user.php create <username> <admin|finance> "<Nama lengkap>"');
            }
            $password = askPassword('Password sementara: ', $minLength);
            $id       = $users->create($name, $fullName, $role, $password, true);
            out("Akun '{$name}' dibuat (id {$id}, role {$role}). Pengguna wajib mengganti password saat login pertama.");
            break;

        case 'passwd':
        case 'disable':
        case 'enable':
        case 'unlock':
            if ($name === '') {
                fail("Pemakaian: php bin/user.php {$command} <username>");
            }
            $user = $users->findByUsername($name);
            if ($user === null) {
                fail("Username '{$name}' tidak ditemukan.");
            }
            $id = (int) $user['id'];

            if ($command === 'passwd') {
                $users->updatePassword($id, askPassword('Password sementara baru: ', $minLength), true);
                $users->clearFailuresForUser($name);
                out("Password '{$name}' diganti. Pengguna wajib menggantinya saat login berikutnya.");
            } elseif ($command === 'unlock') {
                out('Catatan gagal dihapus: ' . $users->clearFailuresForUser($name));
            } else {
                $users->setActive($id, $command === 'enable');
                out("Akun '{$name}' " . ($command === 'enable' ? 'diaktifkan.' : 'dinonaktifkan.'));
            }
            break;

        default:
            out('Perintah: list | create <username> <admin|finance> "<Nama>" | passwd <username> | disable <username> | enable <username> | unlock <username>');
            exit($command === '' ? 0 : 1);
    }
} catch (InvalidArgumentException | ConfigException $e) {
    fail($e->getMessage());
} catch (Throwable $e) {
    error_log('[MES] ' . $e->getMessage());
    fail('Operasi gagal. Periksa koneksi database dan konfigurasi .env.', 2);
}
