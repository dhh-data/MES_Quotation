<?php
/**
 * Koneksi database MySQL berbasis PDO (pola Singleton).
 *
 * Penggunaan di Model:
 *
 *   $pdo  = \App\Config\Database::getInstance()->getConnection();
 *   $stmt = $pdo->prepare('SELECT * FROM quotations WHERE id = :id');
 *   $stmt->execute(['id' => $id]);
 *   $row  = $stmt->fetch();            // array asosiatif (FETCH_ASSOC)
 *
 * Koneksi dibuat malas (lazy): baru dibuka saat pertama kali dibutuhkan.
 *
 * @package App\Config
 */

declare(strict_types=1);

namespace App\Config;

use App\Core\ConfigException;
use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    /** Instance tunggal. */
    private static ?self $instance = null;

    /** Konfigurasi koneksi yang diberikan lewat configure(). */
    private static ?array $config = null;

    private PDO $pdo;

    /**
     * @param array<string, mixed> $config Bagian 'db' dari app_config.php
     *
     * @throws RuntimeException Bila koneksi gagal (tanpa membocorkan kredensial).
     */
    private function __construct(array $config)
    {
        $charset   = (string) ($config['charset'] ?? 'utf8mb4');
        $collation = (string) ($config['collation'] ?? 'utf8mb4_unicode_ci');

        // Production tidak boleh memakai akun root, password kosong, atau password contoh.
        if (!empty($config['require_secure'])) {
            $user = strtolower((string) ($config['user'] ?? ''));
            $pass = (string) ($config['pass'] ?? '');
            if ($user === '' || $user === 'root') {
                throw new ConfigException('Konfigurasi database tidak aman: DB_USER tidak boleh kosong atau root di production. Buat akun khusus (lihat database/app_user.example.sql).');
            }
            if ($pass === '' || stripos($pass, 'GANTI') === 0) {
                throw new ConfigException('Konfigurasi database tidak aman: DB_PASS kosong atau masih berisi contoh. Isi dengan password yang kuat di file .env.');
            }
        }

        // Nilai ini disisipkan ke perintah SET NAMES, jadi hanya karakter aman yang diizinkan.
        if (!preg_match('/^[a-z0-9_]+$/', $charset) || !preg_match('/^[a-z0-9_]+$/', $collation)) {
            throw new RuntimeException('Konfigurasi charset/collation database tidak valid.');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            (string) ($config['host'] ?? '127.0.0.1'),
            (int) ($config['port'] ?? 3306),
            (string) ($config['name'] ?? ''),
            $charset
        );

        try {
            $this->pdo = new PDO($dsn, (string) ($config['user'] ?? ''), (string) ($config['pass'] ?? ''), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // error SQL dilempar sebagai exception
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // hasil query berupa array asosiatif
                PDO::ATTR_EMULATE_PREPARES   => false,                  // prepared statement asli dari server
            ]);
            $this->pdo->exec("SET NAMES {$charset} COLLATE {$collation}");
        } catch (PDOException $e) {
            // Pesan generik: DSN dan password tidak boleh ikut ke layar pengguna.
            throw new RuntimeException('Koneksi ke database gagal. Periksa konfigurasi DB_* dan status server MySQL.', 0, $e);
        }
    }

    /**
     * Menyimpan konfigurasi koneksi. Dipanggil sekali dari public/index.php.
     *
     * @param array<string, mixed> $config Bagian 'db' dari app_config.php
     */
    public static function configure(array $config): void
    {
        self::$config = $config;
    }

    /**
     * Mengambil instance tunggal. Bila configure() belum dipanggil,
     * konfigurasi dibaca langsung dari app_config.php.
     *
     * @throws RuntimeException
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            if (self::$config === null) {
                /** @var array{db: array<string, mixed>} $all */
                $all          = require __DIR__ . '/app_config.php';
                self::$config = $all['db'];
            }
            self::$instance = new self(self::$config);
        }

        return self::$instance;
    }

    /**
     * Objek PDO yang siap dipakai.
     */
    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    /**
     * Jalan pintas: Database::pdo() setara getInstance()->getConnection().
     */
    public static function pdo(): PDO
    {
        return self::getInstance()->getConnection();
    }

    /** Singleton tidak boleh diklon. */
    private function __clone()
    {
    }

    /** Singleton tidak boleh dibuat ulang lewat unserialize. */
    public function __wakeup(): void
    {
        throw new RuntimeException('Singleton tidak dapat di-unserialize.');
    }
}
