<?php
/**
 * Model pengaturan: profil perusahaan, path logo, path tanda tangan, dan
 * tarif PPN default. Tabel `settings` dipakai sebagai satu baris aktif
 * (baris dengan ID terkecil); bila kosong, baris bawaan dibuat otomatis.
 *
 * @package App\Models
 */

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

final class SettingsModel
{
    /** Kolom teks yang boleh diubah lewat update(). */
    private const EDITABLE = [
        'company_name',
        'company_address',
        'company_phone',
        'company_email',
        'tax_percentage_default',
    ];

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::pdo();
    }

    /**
     * Profil perusahaan aktif.
     *
     * @return array<string, mixed>
     */
    public function get(): array
    {
        $row = $this->fetchRow();
        if ($row === null) {
            $this->pdo->prepare(
                'INSERT INTO settings (company_name, company_logo, tax_percentage_default)
                 VALUES (:name, :logo, :tax)'
            )->execute(['name' => 'Mirai Energy Supply', 'logo' => 'assets/img/logo-mes.png', 'tax' => '11.00']);
            $row = $this->fetchRow();
        }

        /** @var array<string, mixed> $row */
        return $row;
    }

    /**
     * Tarif PPN default (persen).
     */
    public function taxDefault(): float
    {
        return (float) $this->get()['tax_percentage_default'];
    }

    /**
     * Memperbarui data teks profil perusahaan. Kunci di luar daftar
     * EDITABLE diabaikan.
     *
     * @param array<string, mixed> $data
     */
    public function update(array $data): bool
    {
        $id     = (int) $this->get()['id'];
        $sets   = [];
        $params = ['id' => $id];

        foreach (self::EDITABLE as $column) {
            if (!array_key_exists($column, $data)) {
                continue;
            }
            $value = $data[$column];
            if (is_string($value)) {
                $value = trim($value);
            }
            $sets[]           = "{$column} = :{$column}";
            $params[$column]  = ($value === '' && $column !== 'company_name') ? null : $value;
        }
        if ($sets === []) {
            return false;
        }

        $stmt = $this->pdo->prepare('UPDATE settings SET ' . implode(', ', $sets) . ' WHERE id = :id');

        return $stmt->execute($params);
    }

    /**
     * Menyimpan path logo (relatif terhadap public/), atau null untuk menghapus.
     */
    public function setLogo(?string $relativePath): bool
    {
        return $this->setPath('company_logo', $relativePath);
    }

    /**
     * Menyimpan path tanda tangan (relatif terhadap public/), atau null untuk menghapus.
     */
    public function setSignature(?string $relativePath): bool
    {
        return $this->setPath('company_signature', $relativePath);
    }

    // ------------------------------------------------------------------

    private function setPath(string $column, ?string $path): bool
    {
        // $column hanya berasal dari dua pemanggil di atas, bukan dari input pengguna.
        $stmt = $this->pdo->prepare("UPDATE settings SET {$column} = :path WHERE id = :id");

        return $stmt->execute(['path' => $path, 'id' => (int) $this->get()['id']]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchRow(): ?array
    {
        $row = $this->pdo->query('SELECT * FROM settings ORDER BY id ASC LIMIT 1')->fetch();

        return $row === false ? null : $row;
    }
}
