<?php
/**
 * Controller pengaturan: profil perusahaan, logo, dan tanda tangan digital.
 *
 * Keamanan unggahan (hanya PNG/JPG):
 *  - Tipe file ditentukan dari ISI file (finfo + getimagesize), bukan dari
 *    nama atau Content-Type kiriman browser.
 *  - Ukuran maksimal 2 MB dan dimensi maksimal 4000 x 4000 piksel.
 *  - Nama file dibuat acak; ekstensi diambil dari tipe terdeteksi.
 *  - Bila ekstensi GD tersedia, gambar di-encode ulang sehingga data
 *    tambahan di dalam file (mis. kode tersembunyi) ikut terbuang.
 *  - File lama hanya dihapus bila berada di folder unggahan.
 *
 * Data yang dikirim ke settings_view:
 *  settings, errors, logoSrc, signatureSrc
 *
 * @package App\Controllers
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\SettingsModel;
use finfo;
use RuntimeException;

final class SettingsController extends Controller
{
    /** Folder unggahan, relatif terhadap public/. */
    private const UPLOAD_DIR = 'assets/img/signature';

    private const MAX_BYTES      = 2 * 1024 * 1024;
    private const MAX_DIMENSION  = 4000;

    /** MIME terdeteksi -> ekstensi file. */
    private const ALLOWED = ['image/png' => 'png', 'image/jpeg' => 'jpg'];

    private SettingsModel $settings;

    public function __construct()
    {
        $this->settings = new SettingsModel();
    }

    /**
     * GET /settings
     */
    public function index(): void
    {
        $flash    = $this->consumeFlash();
        $settings = $this->settings->get();

        if (is_array($flash['old'] ?? null)) {
            foreach (['company_name', 'company_address', 'company_phone', 'company_email', 'tax_percentage_default'] as $key) {
                if (isset($flash['old'][$key]) && is_string($flash['old'][$key])) {
                    $settings[$key] = $flash['old'][$key];
                }
            }
        }

        $this->view('settings_view', [
            'pageTitle'    => 'Pengaturan',
            'activeMenu'   => 'settings',
            'settings'     => $settings,
            'errors'       => is_array($flash['errors'] ?? null) ? $flash['errors'] : [],
            'logoSrc'      => uploaded_image_src($settings['company_logo'] ?? null),
            'signatureSrc' => uploaded_image_src($settings['company_signature'] ?? null),
            'flash'        => $flash,
        ]);
    }

    /**
     * POST /settings : simpan profil dan (opsional) logo serta tanda tangan.
     */
    public function update(): void
    {
        $this->verifyCsrf();
        $current = $this->settings->get();
        $errors  = [];

        // --- Teks profil ---
        $name    = $this->post('company_name');
        $address = $this->post('company_address');
        $phone   = $this->post('company_phone');
        $email   = $this->post('company_email');
        $taxRaw  = $this->post('tax_percentage_default');

        if ($name === '' || mb_strlen($name) > 150) {
            $errors['company_name'] = 'Nama perusahaan wajib diisi (maksimal 150 karakter).';
        }
        if (mb_strlen($address) > 2000) {
            $errors['company_address'] = 'Alamat maksimal 2000 karakter.';
        }
        if (mb_strlen($phone) > 30) {
            $errors['company_phone'] = 'Telepon maksimal 30 karakter.';
        }
        if ($email !== '' && (mb_strlen($email) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            $errors['company_email'] = 'Format email tidak valid.';
        }
        $tax = $taxRaw === '' ? null : $this->decimal($taxRaw);
        if ($tax === null || $tax < 0 || $tax > 100) {
            $errors['tax_percentage_default'] = 'Tarif PPN harus antara 0 dan 100.';
        }

        // --- Unggahan (disimpan dulu; dibatalkan bila ada galat lain) ---
        $newFiles = [];
        foreach ([
            'company_logo'      => ['logo', 'remove_logo'],
            'company_signature' => ['signature', 'remove_signature'],
        ] as $field => [$prefix, $removeKey]) {
            try {
                $stored = $this->storeImage($field, $prefix);
                if ($stored !== null) {
                    $newFiles[$field] = $stored;
                }
            } catch (RuntimeException $e) {
                $errors[$field] = $e->getMessage();
            }
        }

        if ($errors !== []) {
            foreach ($newFiles as $path) {
                $this->deleteStoredFile($path);
            }
            $old = $_POST;
            unset($old['csrf_token']);
            $this->flash('errors', $errors);
            $this->flash('old', $old);
            $this->flash('error', 'Periksa kembali isian yang ditandai.');
            $this->redirect('/settings');
        }

        // --- Simpan ---
        $this->settings->update([
            'company_name'           => $name,
            'company_address'        => $address,
            'company_phone'          => $phone,
            'company_email'          => $email,
            'tax_percentage_default' => $tax,
        ]);

        foreach ([
            'company_logo'      => ['setLogo', 'remove_logo'],
            'company_signature' => ['setSignature', 'remove_signature'],
        ] as $field => [$setter, $removeKey]) {
            $old = (string) ($current[$field] ?? '');

            if (isset($newFiles[$field])) {
                $this->settings->{$setter}($newFiles[$field]);
                $this->deleteStoredFile($old);
            } elseif ($this->post($removeKey) === '1') {
                $this->settings->{$setter}(null);
                $this->deleteStoredFile($old);
            }
        }

        $this->flash('success', 'Pengaturan tersimpan.');
        $this->redirect('/settings');
    }

    // ------------------------------------------------------------------
    // Unggahan
    // ------------------------------------------------------------------

    /**
     * Memvalidasi dan menyimpan satu file gambar unggahan.
     *
     * @return string|null Path relatif terhadap public/, atau null bila tidak ada file diunggah
     *
     * @throws RuntimeException Pesan galat siap tampil bila file ditolak.
     */
    private function storeImage(string $field, string $prefix): ?string
    {
        $file = $_FILES[$field] ?? null;
        if (!is_array($file) || !isset($file['error'])) {
            return null;
        }
        if (is_array($file['error'])) {
            throw new RuntimeException('Unggahan tidak valid.');
        }
        if ($file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException(match ($file['error']) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Ukuran file melebihi batas server.',
                UPLOAD_ERR_PARTIAL                        => 'File hanya terunggah sebagian. Coba lagi.',
                default                                   => 'Unggahan gagal. Coba lagi.',
            });
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if (!is_uploaded_file($tmp)) {
            throw new RuntimeException('Unggahan tidak valid.');
        }
        if ((int) ($file['size'] ?? 0) > self::MAX_BYTES || (int) filesize($tmp) > self::MAX_BYTES) {
            throw new RuntimeException('Ukuran file maksimal 2 MB.');
        }

        // Tipe dari isi file, bukan dari nama atau header klien.
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
        $info = @getimagesize($tmp);
        if ($mime === false || !isset(self::ALLOWED[$mime]) || $info === false
            || !in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)) {
            throw new RuntimeException('File harus berupa gambar PNG atau JPG.');
        }
        if ($info[0] < 1 || $info[1] < 1 || $info[0] > self::MAX_DIMENSION || $info[1] > self::MAX_DIMENSION) {
            throw new RuntimeException('Dimensi gambar maksimal ' . self::MAX_DIMENSION . ' x ' . self::MAX_DIMENSION . ' piksel.');
        }

        $dir = PUBLIC_PATH . '/' . self::UPLOAD_DIR;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Folder unggahan tidak bisa dibuat.');
        }

        $name = $prefix . '-' . bin2hex(random_bytes(12)) . '.' . self::ALLOWED[$mime];
        $dest = $dir . '/' . $name;

        if (!$this->reencode($tmp, $dest, $mime) && !move_uploaded_file($tmp, $dest)) {
            throw new RuntimeException('File gagal disimpan di server.');
        }
        @chmod($dest, 0644);

        return self::UPLOAD_DIR . '/' . $name;
    }

    /**
     * Menulis ulang gambar lewat GD agar hanya piksel yang tersisa.
     *
     * @return bool false bila GD tidak ada atau gambar tidak bisa didekode
     */
    private function reencode(string $source, string $dest, string $mime): bool
    {
        if (!function_exists('imagecreatefromstring')) {
            return false;
        }
        $image = @imagecreatefromstring((string) file_get_contents($source));
        if ($image === false) {
            return false;
        }

        if ($mime === 'image/png') {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $ok = imagepng($image, $dest, 6);
        } else {
            $ok = imagejpeg($image, $dest, 90);
        }
        imagedestroy($image);

        return $ok;
    }

    /**
     * Menghapus file unggahan lama. Hanya path di folder unggahan dengan
     * nama aman yang boleh dihapus; logo bawaan dan path lain tidak disentuh.
     */
    private function deleteStoredFile(string $relativePath): void
    {
        $pattern = '#^' . preg_quote(self::UPLOAD_DIR, '#') . '/[A-Za-z0-9_-]+\.(?:png|jpg)$#';
        if ($relativePath === '' || preg_match($pattern, $relativePath) !== 1) {
            return;
        }
        $full = PUBLIC_PATH . '/' . $relativePath;
        if (is_file($full)) {
            @unlink($full);
        }
    }
}
