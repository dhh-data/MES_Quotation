<?php
/**
 * Model pelanggan/klien: CRUD tabel `customers`.
 *
 * Semua query memakai prepared statement. Nilai opsional yang kosong
 * disimpan sebagai NULL.
 *
 * @package App\Models
 */

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use DomainException;
use InvalidArgumentException;
use PDO;
use PDOException;

final class CustomerModel
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::pdo();
    }

    /**
     * Daftar pelanggan, urut nama perusahaan lalu nama PIC.
     *
     * @return list<array<string, mixed>>
     */
    public function all(string $search = '', int $limit = 100, int $offset = 0): array
    {
        [$where, $params] = $this->searchClause($search);

        $stmt = $this->pdo->prepare(
            "SELECT id, name, company_name, email, phone, address, created_at
               FROM customers {$where}
              ORDER BY COALESCE(company_name, name) ASC, name ASC
              LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', max(1, min($limit, 500)), PDO::PARAM_INT);
        $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Jumlah pelanggan (dengan filter pencarian yang sama seperti all()).
     */
    public function count(string $search = ''): int
    {
        [$where, $params] = $this->searchClause($search);

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM customers {$where}");
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, company_name, email, phone, address, created_at FROM customers WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Menambah pelanggan baru.
     *
     * @param array{name: string, company_name?: ?string, email?: ?string, phone?: ?string, address?: ?string} $data
     *
     * @return int ID pelanggan baru
     *
     * @throws InvalidArgumentException Bila nama kosong.
     */
    public function create(array $data): int
    {
        $row = $this->clean($data);

        $stmt = $this->pdo->prepare(
            'INSERT INTO customers (name, company_name, email, phone, address)
             VALUES (:name, :company_name, :email, :phone, :address)'
        );
        $stmt->execute($row);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Memperbarui pelanggan.
     *
     * @param array<string, mixed> $data
     *
     * @return bool true bila baris ditemukan (meski isinya tidak berubah)
     */
    public function update(int $id, array $data): bool
    {
        if ($this->find($id) === null) {
            return false;
        }

        $row       = $this->clean($data);
        $row['id'] = $id;

        $stmt = $this->pdo->prepare(
            'UPDATE customers
                SET name = :name, company_name = :company_name, email = :email,
                    phone = :phone, address = :address
              WHERE id = :id'
        );
        $stmt->execute($row);

        return true;
    }

    /**
     * Menghapus pelanggan.
     *
     * @return bool false bila ID tidak ada
     *
     * @throws DomainException Bila pelanggan masih punya quotation (FK RESTRICT).
     */
    public function delete(int $id): bool
    {
        try {
            $stmt = $this->pdo->prepare('DELETE FROM customers WHERE id = :id');
            $stmt->execute(['id' => $id]);
        } catch (PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1451) {
                throw new DomainException('Pelanggan masih memiliki quotation dan tidak bisa dihapus.', 0, $e);
            }
            throw $e;
        }

        return $stmt->rowCount() > 0;
    }

    /**
     * Jumlah quotation milik pelanggan.
     */
    public function countQuotations(int $id): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM quotations WHERE customer_id = :id');
        $stmt->execute(['id' => $id]);

        return (int) $stmt->fetchColumn();
    }

    // ------------------------------------------------------------------

    /**
     * @return array{0: string, 1: array<string, string>} [klausa WHERE, parameter]
     */
    private function searchClause(string $search): array
    {
        $search = trim($search);
        if ($search === '') {
            return ['', []];
        }
        $like = '%' . addcslashes($search, '%_\\') . '%';

        return [
            'WHERE name LIKE :s1 OR company_name LIKE :s2 OR email LIKE :s3',
            [':s1' => $like, ':s2' => $like, ':s3' => $like],
        ];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{name: string, company_name: ?string, email: ?string, phone: ?string, address: ?string}
     */
    private function clean(array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('Nama pelanggan wajib diisi.');
        }

        $nullable = static function (mixed $value): ?string {
            $value = trim((string) $value);

            return $value === '' ? null : $value;
        };

        return [
            'name'         => $name,
            'company_name' => $nullable($data['company_name'] ?? null),
            'email'        => $nullable($data['email'] ?? null),
            'phone'        => $nullable($data['phone'] ?? null),
            'address'      => $nullable($data['address'] ?? null),
        ];
    }
}
