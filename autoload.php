<?php
/**
 * Autoloader bersama untuk front controller (public/index.php) dan perintah
 * CLI (bin/user.php): Composer bila ada, lalu PSR-4 App\ -> src/.
 *
 * Memerlukan konstanta ROOT_PATH sudah didefinisikan.
 */

declare(strict_types=1);

$composerAutoload = ROOT_PATH . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require $composerAutoload;
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    // Hanya huruf, angka, underscore, dan pemisah namespace: menutup celah path traversal.
    if (preg_match('/^[A-Za-z0-9_]+(?:\\\\[A-Za-z0-9_]+)*$/', $relative) !== 1) {
        return;
    }

    $file = ROOT_PATH . '/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
