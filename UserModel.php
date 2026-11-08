<?php
/**
 * Akses data pengguna dan catatan percobaan login gagal.
 * Seluruh query memakai prepared statement.
 *
 * @package App\Models
 */

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use InvalidArgumentException;
use PDO;

final class UserModel
{
    public const ROLES = ['admin', 'finance'];

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::pdo();
    }

    // ------------------------------------------------------------------
    // Pengguna
    // ------------------------------------------------------------------

    /** @return array<string, mixed>|null */
    public function findByUsername(string $username): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE username = :u LIMIT 1');
        $stmt->execute(['u' => strtolower($username)]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->db->query(
            'SELECT id, username, full_name, role, is_active, must_change_password, last_login_at
               FROM users ORDER BY username'
        )->fetchAll();
    }

    /**
     * Membuat pengguna baru. Password disimpan sebagai hash bcrypt.
     *
     * @throws InvalidArgumentException Bila data tidak valid atau username sudah ada.
     */
    public function create(string $username, string $fullName, string $role, string $password, bool $mustChange = true): int
    {
        $username = strtolower(trim($username));
        if (preg_match('/^[a-z0-9._-]{3,50}$/', $username) !== 1) {
            throw new InvalidArgumentException('Username harus 3-50 karakter: huruf kecil, angka, titik, garis bawah, atau strip.');
        }
        if (!in_array($role, self::ROLES, true)) {
            throw new InvalidArgumentException('Role harus salah satu dari: ' . implode(', ', self::ROLES) . '.');
        }
        $fullName = trim($fullName);
        if ($fullName === '' || mb_strlen($fullName) > 100) {
            throw new InvalidArgumentException('Nama lengkap wajib diisi (maksimal 100 karakter).');
        }
        if ($this->findByUsername($username) !== null) {
            throw new InvalidArgumentException("Username '{$username}' sudah dipakai.");
        }

        $stmt = $this->db->prepare(
            'INSERT INTO users (username, full_name, password_hash, role, must_change_password)
             VALUES (:u, :n, :h, :r, :m)'
        );
        $stmt->execute([
            'u' => $username,
            'n' => $fullName,
            'h' => password_hash($password, PASSWORD_DEFAULT),
            'r' => $role,
            'm' => $mustChange ? 1 : 0,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updatePassword(int $id, string $password, bool $mustChange = false): void
    {
        $stmt = $this->db->prepare(
            'UPDATE users SET password_hash = :h, must_change_password = :m, password_changed_at = NOW() WHERE id = :id'
        );
        $stmt->execute(['h' => password_hash($password, PASSWORD_DEFAULT), 'm' => $mustChange ? 1 : 0, 'id' => $id]);
    }

    /** Menyimpan hash baru tanpa mengubah status password (rehash otomatis). */
    public function rehash(int $id, string $password): void
    {
        $stmt = $this->db->prepare('UPDATE users SET password_hash = :h WHERE id = :id');
        $stmt->execute(['h' => password_hash($password, PASSWORD_DEFAULT), 'id' => $id]);
    }

    public function setActive(int $id, bool $active): void
    {
        $stmt = $this->db->prepare('UPDATE users SET is_active = :a WHERE id = :id');
        $stmt->execute(['a' => $active ? 1 : 0, 'id' => $id]);
    }

    public function touchLogin(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    // ------------------------------------------------------------------
    // Percobaan login gagal
    // ------------------------------------------------------------------

    public function recordFailure(string $username, string $ip): void
    {
        $stmt = $this->db->prepare('INSERT INTO login_attempts (username, ip_address) VALUES (:u, :ip)');
        $stmt->execute(['u' => substr(strtolower($username), 0, 50), 'ip' => substr($ip, 0, 45)]);

        // Bersih-bersih sesekali agar tabel tidak membesar.
        if (random_int(1, 50) === 1) {
            $this->db->exec('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 2 DAY)');
        }
    }

    /** Jumlah kegagalan pasangan username+IP dalam jendela waktu (menit). */
    public function countFailures(string $username, string $ip, int $windowMinutes): int
    {
        $window = max(1, $windowMinutes);
        $stmt   = $this->db->prepare(
            "SELECT COUNT(*) FROM login_attempts
              WHERE username = :u AND ip_address = :ip AND attempted_at >= (NOW() - INTERVAL {$window} MINUTE)"
        );
        $stmt->execute(['u' => substr(strtolower($username), 0, 50), 'ip' => substr($ip, 0, 45)]);

        return (int) $stmt->fetchColumn();
    }

    /** Jumlah kegagalan dari satu IP (semua username) dalam jendela waktu (menit). */
    public function countIpFailures(string $ip, int $windowMinutes): int
    {
        $window = max(1, $windowMinutes);
        $stmt   = $this->db->prepare(
            "SELECT COUNT(*) FROM login_attempts
              WHERE ip_address = :ip AND attempted_at >= (NOW() - INTERVAL {$window} MINUTE)"
        );
        $stmt->execute(['ip' => substr($ip, 0, 45)]);

        return (int) $stmt->fetchColumn();
    }

    /** Menghapus catatan gagal pasangan username+IP (setelah login berhasil). */
    public function clearFailures(string $username, string $ip): void
    {
        $stmt = $this->db->prepare('DELETE FROM login_attempts WHERE username = :u AND ip_address = :ip');
        $stmt->execute(['u' => substr(strtolower($username), 0, 50), 'ip' => substr($ip, 0, 45)]);
    }

    /** Membuka kunci semua IP untuk satu username (dipakai CLI). */
    public function clearFailuresForUser(string $username): int
    {
        $stmt = $this->db->prepare('DELETE FROM login_attempts WHERE username = :u');
        $stmt->execute(['u' => strtolower($username)]);

        return $stmt->rowCount();
    }
}
