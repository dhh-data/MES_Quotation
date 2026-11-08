<?php
/**
 * Model quotation: tabel `quotations` dan `quotation_items`.
 *
 * - create()/update() berjalan dalam satu transaksi (beginTransaction,
 *   commit, rollBack): header, item, dan (opsional) pelanggan baru
 *   tersimpan semuanya atau tidak sama sekali.
 * - Total dihitung ulang di server oleh calculate(); angka dari browser
 *   tidak pernah dipercaya.
 * - Semua query memakai prepared statement.
 *
 * @package App\Models
 */

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use PDO;
use PDOException;
use Throwable;

final class QuotationModel
{
    public const STATUSES      = ['Draft', 'Sent', 'Accepted', 'Rejected'];
    public const NUMBER_PREFIX = 'SQ';

    /** Percobaan ulang bila nomor otomatis bentrok dengan permintaan lain yang bersamaan. */
    private const MAX_NUMBER_RETRIES = 3;

    private PDO $pdo;
    private CustomerModel $customers;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo       = $pdo ?? Database::pdo();
        $this->customers = new CustomerModel($this->pdo);
    }

    // ------------------------------------------------------------------
    // Kalkulasi
    // ------------------------------------------------------------------

    /**
     * Menghitung total tiap item, subtotal, PPN, dan total akhir.
     *
     * @param list<array{item_description: string, quantity: float|int|string, unit?: string, unit_price: float|int|string}> $items
     *
     * @return array{items: list<array<string, mixed>>, subtotal: float, tax_percentage: float, tax_amount: float, total_amount: float}
     */
    public static function calculate(array $items, float $taxPercentage = 0.0): array
    {
        $rows     = [];
        $subtotal = 0.0;

        foreach ($items as $item) {
            $qty   = round((float) $item['quantity'], 2);
            $price = round((float) $item['unit_price'], 2);
            $line  = round($qty * $price, 2);

            $rows[] = [
                'item_description' => (string) $item['item_description'],
                'quantity'         => $qty,
                'unit'             => (string) ($item['unit'] ?? 'PCS'),
                'unit_price'       => $price,
                'total_price'      => $line,
            ];
            $subtotal += $line;
        }

        $taxPercentage = max(0.0, min(100.0, $taxPercentage));
        $subtotal      = round($subtotal, 2);
        $tax           = round($subtotal * $taxPercentage / 100, 2);

        return [
            'items'          => $rows,
            'subtotal'       => $subtotal,
            'tax_percentage' => $taxPercentage,
            'tax_amount'     => $tax,
            'total_amount'   => round($subtotal + $tax, 2),
        ];
    }

    /**
     * Memperkirakan tarif PPN (persen) dari quotation yang tersimpan,
     * karena tabel hanya menyimpan nominal PPN.
     *
     * @param array<string, mixed> $quotation Baris quotation (subtotal, tax_amount)
     */
    public static function inferTaxPercentage(array $quotation): float
    {
        $subtotal = (float) ($quotation['subtotal'] ?? 0);
        $tax      = (float) ($quotation['tax_amount'] ?? 0);

        return $subtotal > 0 ? round($tax / $subtotal * 100, 2) : 0.0;
    }

    // ------------------------------------------------------------------
    // Create / update / delete
    // ------------------------------------------------------------------

    /**
     * Membuat quotation beserta item-itemnya secara transaksional.
     *
     * Bila 'quotation_number' kosong, nomor SQ-YY-MM-NNN dibuat otomatis
     * (dan dicoba ulang bila bentrok). Bila $newCustomer diisi, pelanggan itu
     * dibuat di transaksi yang sama sehingga tidak ada pelanggan yatim bila
     * penyimpanan quotation gagal.
     *
     * @param array{quotation_number?: string, customer_id?: int, quotation_date: string, valid_until: string, notes?: ?string, status?: string} $header
     * @param list<array<string, mixed>> $items       Lihat calculate()
     * @param array<string, mixed>|null  $newCustomer Data untuk CustomerModel::create()
     *
     * @return int ID quotation baru
     *
     * @throws DomainException Nomor manual sudah dipakai.
     * @throws InvalidArgumentException Data tidak valid.
     */
    public function create(array $header, array $items, float $taxPercentage = 0.0, ?array $newCustomer = null): int
    {
        $this->assertHeader($header, $items, $newCustomer);

        $calc     = self::calculate($items, $taxPercentage);
        $manual   = trim((string) ($header['quotation_number'] ?? ''));
        $attempts = $manual === '' ? self::MAX_NUMBER_RETRIES : 1;

        for ($try = 1; ; $try++) {
            $owns = !$this->pdo->inTransaction(); // ikut transaksi luar bila sudah ada
            if ($owns) {
                $this->pdo->beginTransaction();
            }

            try {
                $customerId = $newCustomer !== null
                    ? $this->customers->create($newCustomer)
                    : (int) $header['customer_id'];
                $number = $manual !== '' ? $manual : $this->generateNumber((string) $header['quotation_date']);

                $stmt = $this->pdo->prepare(
                    'INSERT INTO quotations
                        (quotation_number, customer_id, quotation_date, valid_until,
                         subtotal, tax_amount, total_amount, notes, status)
                     VALUES
                        (:number, :customer_id, :quotation_date, :valid_until,
                         :subtotal, :tax_amount, :total_amount, :notes, :status)'
                );
                $stmt->execute([
                    'number'         => $number,
                    'customer_id'    => $customerId,
                    'quotation_date' => $header['quotation_date'],
                    'valid_until'    => $header['valid_until'],
                    'subtotal'       => $calc['subtotal'],
                    'tax_amount'     => $calc['tax_amount'],
                    'total_amount'   => $calc['total_amount'],
                    'notes'          => $this->nullable($header['notes'] ?? null),
                    'status'         => $header['status'] ?? 'Draft',
                ]);
                $id = (int) $this->pdo->lastInsertId();

                $this->insertItems($id, $calc['items']);

                if ($owns) {
                    $this->pdo->commit();
                }

                return $id;
            } catch (PDOException $e) {
                $this->rollBackIfOwned($owns);
                if (self::isDuplicateKey($e)) {
                    if ($try < $attempts) {
                        continue;
                    }
                    throw new DomainException('Nomor quotation sudah digunakan.', 0, $e);
                }
                throw $e;
            } catch (Throwable $e) {
                $this->rollBackIfOwned($owns);
                throw $e;
            }
        }
    }

    /**
     * Memperbarui quotation: header diganti dan seluruh item ditulis ulang
     * dalam satu transaksi.
     *
     * @param array<string, mixed>       $header Wajib memuat quotation_number
     * @param list<array<string, mixed>> $items
     * @param array<string, mixed>|null  $newCustomer
     *
     * @return bool false bila quotation tidak ditemukan
     *
     * @throws DomainException Nomor sudah dipakai quotation lain.
     */
    public function update(int $id, array $header, array $items, float $taxPercentage = 0.0, ?array $newCustomer = null): bool
    {
        $this->assertHeader($header, $items, $newCustomer);
        $number = trim((string) ($header['quotation_number'] ?? ''));
        if ($number === '') {
            throw new InvalidArgumentException('Nomor quotation wajib diisi.');
        }

        $calc = self::calculate($items, $taxPercentage);
        $owns = !$this->pdo->inTransaction();
        if ($owns) {
            $this->pdo->beginTransaction();
        }

        try {
            $lock = $this->pdo->prepare('SELECT id FROM quotations WHERE id = :id FOR UPDATE');
            $lock->execute(['id' => $id]);
            if ($lock->fetchColumn() === false) {
                $this->rollBackIfOwned($owns);

                return false;
            }

            $customerId = $newCustomer !== null
                ? $this->customers->create($newCustomer)
                : (int) $header['customer_id'];

            $stmt = $this->pdo->prepare(
                'UPDATE quotations
                    SET quotation_number = :number, customer_id = :customer_id,
                        quotation_date = :quotation_date, valid_until = :valid_until,
                        subtotal = :subtotal, tax_amount = :tax_amount, total_amount = :total_amount,
                        notes = :notes, status = :status
                  WHERE id = :id'
            );
            $stmt->execute([
                'number'         => $number,
                'customer_id'    => $customerId,
                'quotation_date' => $header['quotation_date'],
                'valid_until'    => $header['valid_until'],
                'subtotal'       => $calc['subtotal'],
                'tax_amount'     => $calc['tax_amount'],
                'total_amount'   => $calc['total_amount'],
                'notes'          => $this->nullable($header['notes'] ?? null),
                'status'         => $header['status'] ?? 'Draft',
                'id'             => $id,
            ]);

            $del = $this->pdo->prepare('DELETE FROM quotation_items WHERE quotation_id = :id');
            $del->execute(['id' => $id]);
            $this->insertItems($id, $calc['items']);

            if ($owns) {
                $this->pdo->commit();
            }

            return true;
        } catch (PDOException $e) {
            $this->rollBackIfOwned($owns);
            if (self::isDuplicateKey($e)) {
                throw new DomainException('Nomor quotation sudah digunakan.', 0, $e);
            }
            throw $e;
        } catch (Throwable $e) {
            $this->rollBackIfOwned($owns);
            throw $e;
        }
    }

    /**
     * Mengubah status quotation.
     *
     * @throws InvalidArgumentException Status di luar daftar STATUSES.
     */
    public function updateStatus(int $id, string $status): bool
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('Status tidak valid.');
        }
        $stmt = $this->pdo->prepare('UPDATE quotations SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $id]);

        return $this->exists($id);
    }

    /**
     * Menghapus quotation. Item ikut terhapus lewat ON DELETE CASCADE.
     *
     * @return bool false bila ID tidak ada
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM quotations WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    // ------------------------------------------------------------------
    // Query
    // ------------------------------------------------------------------

    public function exists(int $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM quotations WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Header quotation beserta data pelanggan (kolom customer_*).
     *
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT q.*,
                    c.name         AS customer_name,
                    c.company_name AS customer_company,
                    c.email        AS customer_email,
                    c.phone        AS customer_phone,
                    c.address      AS customer_address
               FROM quotations q
               JOIN customers c ON c.id = q.customer_id
              WHERE q.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findItems(int $quotationId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, quotation_id, item_description, quantity, unit, unit_price, total_price
               FROM quotation_items
              WHERE quotation_id = :id
              ORDER BY id ASC'
        );
        $stmt->execute(['id' => $quotationId]);

        return $stmt->fetchAll();
    }

    /**
     * Data lengkap untuk pratinjau, cetak, dan PDF.
     *
     * @return array{quotation: array<string, mixed>, items: list<array<string, mixed>>, totals: array{subtotal: float, tax_percentage: float, tax_amount: float, total_amount: float}}|null
     */
    public function getDocument(int $id): ?array
    {
        $quotation = $this->find($id);
        if ($quotation === null) {
            return null;
        }

        return [
            'quotation' => $quotation,
            'items'     => $this->findItems($id),
            'totals'    => [
                'subtotal'       => (float) $quotation['subtotal'],
                'tax_percentage' => self::inferTaxPercentage($quotation),
                'tax_amount'     => (float) $quotation['tax_amount'],
                'total_amount'   => (float) $quotation['total_amount'],
            ],
        ];
    }

    /**
     * Riwayat quotation dengan filter dan paginasi.
     *
     * Filter: q (nomor/pelanggan), status, date_from, date_to (Y-m-d).
     * Filter yang tidak valid diabaikan.
     *
     * @param array<string, mixed> $filters
     *
     * @return array{rows: list<array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public function getHistory(array $filters = [], int $page = 1, int $perPage = 15): array
    {
        $where  = [];
        $params = [];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $where[] = '(q.quotation_number LIKE :q1 OR c.name LIKE :q2 OR c.company_name LIKE :q3)';
            $params[':q1'] = $params[':q2'] = $params[':q3'] = $like;
        }
        $status = (string) ($filters['status'] ?? '');
        if (in_array($status, self::STATUSES, true)) {
            $where[] = 'q.status = :status';
            $params[':status'] = $status;
        }
        foreach (['date_from' => '>=', 'date_to' => '<='] as $key => $operator) {
            $value = (string) ($filters[$key] ?? '');
            if (self::isDate($value)) {
                $where[] = "q.quotation_date {$operator} :{$key}";
                $params[':' . $key] = $value;
            }
        }
        $clause = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

        $count = $this->pdo->prepare(
            "SELECT COUNT(*) FROM quotations q JOIN customers c ON c.id = q.customer_id {$clause}"
        );
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $perPage = max(1, min($perPage, 100));
        $pages   = max(1, (int) ceil($total / $perPage));
        $page    = max(1, min($page, $pages));

        $stmt = $this->pdo->prepare(
            "SELECT q.id, q.quotation_number, q.quotation_date, q.valid_until,
                    q.subtotal, q.tax_amount, q.total_amount, q.status, q.created_at,
                    c.name AS customer_name, c.company_name AS customer_company,
                    (SELECT COUNT(*) FROM quotation_items i WHERE i.quotation_id = q.id) AS item_count
               FROM quotations q
               JOIN customers c ON c.id = q.customer_id
               {$clause}
              ORDER BY q.quotation_date DESC, q.id DESC
              LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'rows'     => $stmt->fetchAll(),
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => $pages,
        ];
    }

    /**
     * Jumlah dan nilai quotation per status.
     *
     * @return array<string, array{count: int, total: float}> Semua status selalu ada
     */
    public function statusSummary(): array
    {
        $summary = [];
        foreach (self::STATUSES as $status) {
            $summary[$status] = ['count' => 0, 'total' => 0.0];
        }

        $rows = $this->pdo->query(
            'SELECT status, COUNT(*) AS cnt, COALESCE(SUM(total_amount), 0) AS total FROM quotations GROUP BY status'
        )->fetchAll();
        foreach ($rows as $row) {
            $summary[$row['status']] = ['count' => (int) $row['cnt'], 'total' => (float) $row['total']];
        }

        return $summary;
    }

    /**
     * Nomor berikutnya berformat SQ-YY-MM-NNN untuk bulan dari $date.
     * Di dalam transaksi, baris dikunci (FOR UPDATE) agar dua permintaan
     * bersamaan tidak mendapat nomor yang sama.
     *
     * @param string $date Format Y-m-d
     */
    public function generateNumber(string $date): string
    {
        if (!self::isDate($date)) {
            throw new InvalidArgumentException('Tanggal quotation tidak valid.');
        }

        $base = sprintf('%s-%s-%s-', self::NUMBER_PREFIX, substr($date, 2, 2), substr($date, 5, 2));
        $sql  = 'SELECT MAX(CAST(SUBSTRING(quotation_number, :start) AS UNSIGNED))
                   FROM quotations WHERE quotation_number LIKE :pattern';
        if ($this->pdo->inTransaction()) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':start', strlen($base) + 1, PDO::PARAM_INT);
        $stmt->bindValue(':pattern', $base . '%');
        $stmt->execute();

        return $base . str_pad((string) (((int) $stmt->fetchColumn()) + 1), 3, '0', STR_PAD_LEFT);
    }

    // ------------------------------------------------------------------
    // Internal
    // ------------------------------------------------------------------

    /**
     * @param list<array<string, mixed>> $rows Hasil calculate()['items']
     */
    private function insertItems(int $quotationId, array $rows): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO quotation_items (quotation_id, item_description, quantity, unit, unit_price, total_price)
             VALUES (:quotation_id, :description, :quantity, :unit, :unit_price, :total_price)'
        );
        foreach ($rows as $row) {
            $stmt->execute([
                'quotation_id' => $quotationId,
                'description'  => $row['item_description'],
                'quantity'     => $row['quantity'],
                'unit'         => $row['unit'],
                'unit_price'   => $row['unit_price'],
                'total_price'  => $row['total_price'],
            ]);
        }
    }

    /**
     * @param array<string, mixed>       $header
     * @param list<array<string, mixed>> $items
     * @param array<string, mixed>|null  $newCustomer
     */
    private function assertHeader(array $header, array $items, ?array $newCustomer): void
    {
        if ($items === []) {
            throw new InvalidArgumentException('Quotation minimal memiliki satu item.');
        }
        if ($newCustomer === null && (int) ($header['customer_id'] ?? 0) < 1) {
            throw new InvalidArgumentException('Pelanggan wajib dipilih atau diisi.');
        }
        if (!self::isDate((string) ($header['quotation_date'] ?? '')) || !self::isDate((string) ($header['valid_until'] ?? ''))) {
            throw new InvalidArgumentException('Tanggal quotation atau masa berlaku tidak valid.');
        }
        if (isset($header['status']) && !in_array($header['status'], self::STATUSES, true)) {
            throw new InvalidArgumentException('Status tidak valid.');
        }
    }

    private function rollBackIfOwned(bool $owns): void
    {
        if ($owns && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    private static function isDuplicateKey(PDOException $e): bool
    {
        return (int) ($e->errorInfo[1] ?? 0) === 1062;
    }

    private static function isDate(string $value): bool
    {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $d !== false && $d->format('Y-m-d') === $value;
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
