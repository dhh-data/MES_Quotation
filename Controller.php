<?php
/**
 * Controller dasar: render View, flash message, redirect, CSRF, dan
 * pembacaan input yang aman. Semua controller aplikasi mewarisi kelas ini.
 *
 * Konvensi View (src/Views/<nama>.php):
 *  - Variabel dari array $data tersedia langsung di View, ditambah $flash
 *    (pesan sekali tampil) dan $pageTitle.
 *  - View dibungkus layouts/header, layouts/sidebar, layouts/footer kecuali
 *    $layout = false (untuk lembar A4 cetak/PDF).
 *
 * @package App\Core
 */

declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

abstract class Controller
{
    // ------------------------------------------------------------------
    // View
    // ------------------------------------------------------------------

    /**
     * Merender View langsung ke output.
     *
     * @param array<string, mixed> $data
     */
    protected function view(string $view, array $data = [], bool $layout = true): void
    {
        echo $this->render($view, $data, $layout);
    }

    /**
     * Merender View menjadi string (dipakai juga untuk PDF).
     *
     * @param array<string, mixed> $data
     *
     * @throws InvalidArgumentException Nama view tidak valid.
     * @throws RuntimeException         File view tidak ditemukan.
     */
    protected function render(string $view, array $data = [], bool $layout = true): string
    {
        $data += ['flash' => $this->consumeFlash(), 'pageTitle' => APP_NAME];

        ob_start();
        try {
            if ($layout) {
                $this->includeView('layouts/header', $data);
                $this->includeView('layouts/sidebar', $data);
            }
            $this->includeView($view, $data);
            if ($layout) {
                $this->includeView('layouts/footer', $data);
            }

            return (string) ob_get_clean();
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function includeView(string $view, array $data): void
    {
        // Hanya huruf kecil, angka, underscore, dan "/" antar-folder: tidak ada celah path traversal.
        if (!preg_match('#^[a-z0-9_]+(?:/[a-z0-9_]+)*$#', $view)) {
            throw new InvalidArgumentException("Nama view tidak valid: {$view}");
        }
        $file = ROOT_PATH . '/src/Views/' . $view . '.php';
        if (!is_file($file)) {
            throw new RuntimeException("View tidak ditemukan: {$view}");
        }

        (static function (string $__file, array $__data): void {
            extract($__data, EXTR_SKIP);
            require $__file;
        })($file, $data);
    }

    // ------------------------------------------------------------------
    // Respons
    // ------------------------------------------------------------------

    /**
     * Redirect ke rute internal (selalu diawali "/"), lalu menghentikan eksekusi.
     */
    protected function redirect(string $path, int $status = 302): never
    {
        $path = preg_replace('/[\r\n]+/', '', $path) ?? '/';
        if ($path === '' || $path[0] !== '/' || str_starts_with($path, '//')) {
            $path = '/'; // cegah open redirect dan header injection
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Location: ' . BASE_URL . $path, true, $status);
        exit;
    }

    /**
     * Mengirim halaman galat sederhana lalu menghentikan eksekusi.
     */
    protected function abort(int $status, string $message = ''): never
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');

        $m = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . "<title>{$status}</title><body style=\"font-family:system-ui,sans-serif;max-width:32rem;margin:15vh auto;padding:0 1rem\">"
            . "<h1 style=\"color:#0b6a3b\">{$status}</h1><p>{$m}</p><p><a href=\"" . htmlspecialchars(BASE_URL . '/', ENT_QUOTES) . '">Kembali</a></p>';
        exit;
    }

    // ------------------------------------------------------------------
    // Flash message (disimpan di session, tampil sekali)
    // ------------------------------------------------------------------

    /**
     * Menyimpan nilai flash untuk request berikutnya.
     * Kunci yang dipakai: success, error, errors, old.
     */
    protected function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    /**
     * Mengambil semua flash lalu menghapusnya.
     *
     * @return array<string, mixed>
     */
    protected function consumeFlash(): array
    {
        $flash = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);

        return is_array($flash) ? $flash : [];
    }

    // ------------------------------------------------------------------
    // Keamanan dan input
    // ------------------------------------------------------------------

    /**
     * Memeriksa token CSRF pada request POST; menghentikan dengan 403 bila salah.
     */
    protected function verifyCsrf(): void
    {
        $sent    = $_POST['csrf_token'] ?? '';
        $session = $_SESSION['csrf_token'] ?? '';

        if (!is_string($sent) || !is_string($session) || $session === '' || !hash_equals($session, $sent)) {
            $this->abort(403, 'Token keamanan tidak valid atau sudah kedaluwarsa. Muat ulang halaman lalu coba lagi.');
        }
    }

    /**
     * Nilai POST bertipe string (di-trim); array atau nilai kosong menjadi "".
     */
    protected function post(string $key): string
    {
        $value = $_POST[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    }

    /**
     * Nilai query string bertipe string (di-trim).
     */
    protected function query(string $key): string
    {
        $value = $_GET[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    }

    /**
     * Membaca angka dari input pengguna. Titik dianggap desimal ("1500.5");
     * format Indonesia dikenali bila memuat koma desimal atau dua kelompok
     * ribuan ("1.500.000" atau "1.500,50"). Mengembalikan null bila tidak valid.
     */
    protected function decimal(string $raw): ?float
    {
        $s = str_replace(' ', '', trim(str_ireplace(['Rp', 'IDR'], '', $raw)));
        if ($s === '') {
            return null;
        }

        if (preg_match('/^\d{1,3}(?:\.\d{3})+,\d+$|^\d{1,3}(?:\.\d{3}){2,}$/', $s) === 1) {
            $s = str_replace(['.', ','], ['', '.'], $s);
        } elseif (preg_match('/^\d+,\d+$/', $s) === 1) {
            $s = str_replace(',', '.', $s);
        } elseif (preg_match('/^\d+(?:\.\d+)?$/', $s) !== 1) {
            return null;
        }

        $value = (float) $s;

        return is_finite($value) && $value < 1e13 ? $value : null;
    }

    /**
     * Tanggal Y-m-d yang valid, atau null.
     */
    protected function validDate(string $value): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return ($date !== false && $date->format('Y-m-d') === $value) ? $date : null;
    }
}
