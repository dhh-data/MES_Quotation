<?php
/**
 * Controller quotation: daftar, buat, ubah, pratinjau, status, dan hapus.
 *
 * Alur form memakai pola Post/Redirect/Get: bila validasi gagal, isian lama
 * dan pesan galat disimpan sebagai flash lalu pengguna diarahkan kembali ke
 * form. Total (subtotal, PPN, total) selalu dihitung ulang di server.
 *
 * Data yang dikirim ke View:
 *  quotation_list_view   : quotations, pagination, filters, summary, statuses
 *  quotation_create_view : mode (create|edit), action, quotationId, form,
 *                          items, errors, customers, settings,
 *                          suggestedNumber, statuses
 *  quotation_preview_view: quotation, items, totals, settings, say,
 *                          logoSrc, signatureSrc, pdfMode, printMode
 *
 * @package App\Controllers
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\CustomerModel;
use App\Models\QuotationModel;
use App\Models\SettingsModel;
use DomainException;

final class QuotationController extends Controller
{
    private const PER_PAGE  = 15;
    private const MAX_ITEMS = 100;

    private QuotationModel $quotations;
    private CustomerModel $customers;
    private SettingsModel $settings;

    public function __construct()
    {
        $this->quotations = new QuotationModel();
        $this->customers  = new CustomerModel();
        $this->settings   = new SettingsModel();
    }

    // ------------------------------------------------------------------
    // Daftar dan pratinjau
    // ------------------------------------------------------------------

    /**
     * GET /quotations : riwayat dengan pencarian, filter status/tanggal, dan paginasi.
     */
    public function index(): void
    {
        $filters = [
            'q'         => $this->query('q'),
            'status'    => $this->query('status'),
            'date_from' => $this->query('date_from'),
            'date_to'   => $this->query('date_to'),
        ];
        $result = $this->quotations->getHistory($filters, max(1, (int) $this->query('page')), self::PER_PAGE);

        $this->view('quotation_list_view', [
            'pageTitle'  => 'Riwayat Quotation',
            'activeMenu' => 'list',
            'quotations' => $result['rows'],
            'pagination' => [
                'page'     => $result['page'],
                'pages'    => $result['pages'],
                'total'    => $result['total'],
                'per_page' => $result['per_page'],
            ],
            'filters'    => $filters,
            'summary'    => $this->quotations->statusSummary(),
            'statuses'   => QuotationModel::STATUSES,
        ]);
    }

    /**
     * GET /quotations/{id} : pratinjau lembar A4.
     */
    public function preview(int $id): void
    {
        $doc = $this->quotations->getDocument($id);
        if ($doc === null) {
            $this->abort(404, 'Quotation tidak ditemukan.');
        }

        $settings = $this->settings->get();
        $this->view('quotation_preview_view', $doc + [
            'pageTitle'     => 'Quotation ' . $doc['quotation']['quotation_number'],
            'activeMenu'    => 'list',
            'settings'      => $settings,
            'say'           => terbilang($doc['totals']['total_amount']),
            'logoSrc'       => uploaded_image_src($settings['company_logo'] ?? null),
            'signatureSrc'  => uploaded_image_src($settings['company_signature'] ?? null),
            'statuses'      => QuotationModel::STATUSES,
            'pdfMode'       => false,
            'printMode'     => false,
        ]);
    }

    // ------------------------------------------------------------------
    // Buat
    // ------------------------------------------------------------------

    /**
     * GET /quotations/create (dan "/") : form quotation baru.
     */
    public function create(): void
    {
        $flash    = $this->consumeFlash();
        $settings = $this->settings->get();
        $today    = date('Y-m-d');

        $form  = $this->defaultForm($settings);
        $items = [$this->blankItem()];
        if (!empty($flash['old']) && is_array($flash['old'])) {
            [$form, $items] = $this->mergeOld($flash['old'], $form);
        }

        $this->renderForm('create', url('/quotations/store'), null, $form, $items, $flash, $settings, $this->quotations->generateNumber($today));
    }

    /**
     * POST /quotations/store : validasi lalu simpan (transaksional).
     */
    public function store(): void
    {
        $this->verifyCsrf();
        $in = $this->collect(null);
        if ($in['errors'] !== []) {
            $this->backWithErrors('/quotations/create', $in['errors']);
        }

        try {
            $id = $this->quotations->create($in['data'], $in['items'], $in['tax'], $in['newCustomer']);
        } catch (DomainException $e) {
            $this->backWithErrors('/quotations/create', ['quotation_number' => $e->getMessage()]);
        }

        $number = (string) ($this->quotations->find($id)['quotation_number'] ?? '');
        $this->flash('success', "Quotation {$number} berhasil dibuat.");
        $this->redirect('/quotations/' . $id);
    }

    // ------------------------------------------------------------------
    // Ubah
    // ------------------------------------------------------------------

    /**
     * GET /quotations/{id}/edit : form yang sama dengan create, terisi data tersimpan.
     */
    public function edit(int $id): void
    {
        $doc = $this->quotations->getDocument($id);
        if ($doc === null) {
            $this->abort(404, 'Quotation tidak ditemukan.');
        }

        $flash    = $this->consumeFlash();
        $settings = $this->settings->get();
        $q        = $doc['quotation'];

        $form = $this->defaultForm($settings);
        foreach (['quotation_number', 'quotation_date', 'valid_until', 'notes', 'status', 'customer_id'] as $key) {
            $form[$key] = (string) ($q[$key] ?? '');
        }
        $form['tax_percentage'] = $this->plain($doc['totals']['tax_percentage']);

        $items = array_map(fn (array $row): array => [
            'description' => (string) $row['item_description'],
            'quantity'    => $this->plain((float) $row['quantity']),
            'unit'        => (string) $row['unit'],
            'unit_price'  => $this->plain((float) $row['unit_price']),
        ], $doc['items']);

        if (!empty($flash['old']) && is_array($flash['old'])) {
            [$form, $items] = $this->mergeOld($flash['old'], $form);
        }

        $this->renderForm('edit', url("/quotations/{$id}/update"), $id, $form, $items, $flash, $settings, '');
    }

    /**
     * POST /quotations/{id}/update
     */
    public function update(int $id): void
    {
        $this->verifyCsrf();
        if (!$this->quotations->exists($id)) {
            $this->abort(404, 'Quotation tidak ditemukan.');
        }

        $in = $this->collect($id);
        if ($in['errors'] !== []) {
            $this->backWithErrors("/quotations/{$id}/edit", $in['errors']);
        }

        try {
            $this->quotations->update($id, $in['data'], $in['items'], $in['tax'], $in['newCustomer']);
        } catch (DomainException $e) {
            $this->backWithErrors("/quotations/{$id}/edit", ['quotation_number' => $e->getMessage()]);
        }

        $this->flash('success', 'Perubahan quotation tersimpan.');
        $this->redirect('/quotations/' . $id);
    }

    /**
     * POST /quotations/{id}/status : ubah status (Draft/Sent/Accepted/Rejected).
     */
    public function updateStatus(int $id): void
    {
        $this->verifyCsrf();
        $status = $this->post('status');

        if (!in_array($status, QuotationModel::STATUSES, true)) {
            $this->flash('error', 'Status tidak valid.');
        } elseif (!$this->quotations->updateStatus($id, $status)) {
            $this->abort(404, 'Quotation tidak ditemukan.');
        } else {
            $this->flash('success', "Status diubah menjadi {$status}.");
        }
        $this->redirect('/quotations/' . $id);
    }

    // ------------------------------------------------------------------
    // Hapus
    // ------------------------------------------------------------------

    /**
     * POST /quotations/{id}/delete : item ikut terhapus (ON DELETE CASCADE).
     */
    public function delete(int $id): void
    {
        $this->verifyCsrf();
        $quotation = $this->quotations->find($id);
        if ($quotation === null) {
            $this->abort(404, 'Quotation tidak ditemukan.');
        }

        $this->quotations->delete($id);
        $this->flash('success', "Quotation {$quotation['quotation_number']} dihapus.");
        $this->redirect('/quotations');
    }

    // ------------------------------------------------------------------
    // Form: bantu
    // ------------------------------------------------------------------

    /**
     * Membaca dan memvalidasi input form (dipakai store dan update).
     *
     * @return array{data: array<string, mixed>, items: list<array<string, mixed>>, tax: float, newCustomer: ?array<string, mixed>, errors: array<string, string>}
     */
    private function collect(?int $currentId): array
    {
        $errors = [];

        // --- Dokumen ---
        $number = $this->post('quotation_number');
        if ($number !== '' && !preg_match('#^[A-Za-z0-9][A-Za-z0-9./-]{2,29}$#', $number)) {
            $errors['quotation_number'] = 'Nomor 3-30 karakter: huruf, angka, titik, garis miring, atau tanda hubung.';
        } elseif ($number === '' && $currentId !== null) {
            $errors['quotation_number'] = 'Nomor quotation wajib diisi.';
        }

        $dateRaw  = $this->post('quotation_date');
        $validRaw = $this->post('valid_until');
        $date     = $this->validDate($dateRaw);
        $valid    = $this->validDate($validRaw);
        if ($date === null) {
            $errors['quotation_date'] = 'Tanggal quotation tidak valid.';
        }
        if ($valid === null) {
            $errors['valid_until'] = 'Tanggal berlaku sampai tidak valid.';
        } elseif ($date !== null && $valid < $date) {
            $errors['valid_until'] = 'Berlaku sampai tidak boleh lebih awal dari tanggal quotation.';
        }

        $notes = $this->post('notes');
        if (mb_strlen($notes) > 5000) {
            $errors['notes'] = 'Catatan maksimal 5000 karakter.';
        }

        $status = $this->post('status') ?: 'Draft';
        if (!in_array($status, QuotationModel::STATUSES, true)) {
            $errors['status'] = 'Status tidak valid.';
        }

        // --- Pelanggan: pilih yang ada atau isi baru ---
        $customerId  = (int) $this->post('customer_id');
        $newCustomer = null;
        if ($customerId > 0) {
            if ($this->customers->find($customerId) === null) {
                $errors['customer_id'] = 'Pelanggan yang dipilih tidak ditemukan.';
            }
        } else {
            $customerId  = 0;
            $newCustomer = [
                'name'         => $this->post('customer_name'),
                'company_name' => $this->post('customer_company'),
                'email'        => $this->post('customer_email'),
                'phone'        => $this->post('customer_phone'),
                'address'      => $this->post('customer_address'),
            ];
            if ($newCustomer['name'] === '') {
                $errors['customer_name'] = 'Nama penerima (Quotation For) wajib diisi.';
            } elseif (mb_strlen($newCustomer['name']) > 120) {
                $errors['customer_name'] = 'Nama maksimal 120 karakter.';
            }
            if (mb_strlen($newCustomer['company_name']) > 150) {
                $errors['customer_company'] = 'Nama perusahaan maksimal 150 karakter.';
            }
            if ($newCustomer['email'] !== '' && (mb_strlen($newCustomer['email']) > 120 || !filter_var($newCustomer['email'], FILTER_VALIDATE_EMAIL))) {
                $errors['customer_email'] = 'Format email tidak valid.';
            }
            if (mb_strlen($newCustomer['phone']) > 30) {
                $errors['customer_phone'] = 'Telepon maksimal 30 karakter.';
            }
            if (mb_strlen($newCustomer['address']) > 2000) {
                $errors['customer_address'] = 'Alamat maksimal 2000 karakter.';
            }
        }

        // --- PPN ---
        $taxRaw = $this->post('tax_percentage');
        $tax    = $taxRaw === '' ? $this->settings->taxDefault() : $this->decimal($taxRaw);
        if ($tax === null || $tax < 0 || $tax > 100) {
            $errors['tax_percentage'] = 'Tarif PPN harus antara 0 dan 100.';
            $tax = 0.0;
        }

        // --- Item ---
        $items = [];
        $rows  = $_POST['items'] ?? [];
        $rows  = is_array($rows) ? $rows : [];
        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            $i     = (int) $index;
            $desc  = is_string($row['description'] ?? null) ? trim($row['description']) : '';
            $qtyR  = is_string($row['quantity'] ?? null) ? trim($row['quantity']) : '';
            $unit  = is_string($row['unit'] ?? null) ? trim($row['unit']) : '';
            $priceR = is_string($row['unit_price'] ?? null) ? trim($row['unit_price']) : '';

            if ($desc === '' && $qtyR === '' && $priceR === '') {
                continue; // baris kosong diabaikan
            }
            if (count($items) >= self::MAX_ITEMS) {
                $errors['items'] = 'Maksimal ' . self::MAX_ITEMS . ' item per quotation.';
                break;
            }

            if ($desc === '' || mb_strlen($desc) > 500) {
                $errors["items.{$i}.description"] = 'Deskripsi wajib diisi (maksimal 500 karakter).';
            }
            $qty = $this->decimal($qtyR);
            if ($qty === null || $qty <= 0 || $qty > 9999999999.99) {
                $errors["items.{$i}.quantity"] = 'Jumlah harus lebih dari 0.';
            }
            $price = $this->decimal($priceR);
            if ($price === null || $price < 0 || $price > 9999999999999.0) {
                $errors["items.{$i}.unit_price"] = 'Harga satuan tidak valid.';
            }
            if (mb_strlen($unit) > 20) {
                $errors["items.{$i}.unit"] = 'Satuan maksimal 20 karakter.';
            }

            $items[] = [
                'item_description' => $desc,
                'quantity'         => $qty ?? 0.0,
                'unit'             => $unit === '' ? 'PCS' : $unit,
                'unit_price'       => $price ?? 0.0,
            ];
        }
        if ($items === [] && !isset($errors['items'])) {
            $errors['items'] = 'Tambahkan minimal satu item.';
        }

        return [
            'data' => [
                'quotation_number' => $number,
                'customer_id'      => $customerId,
                'quotation_date'   => $dateRaw,
                'valid_until'      => $validRaw,
                'notes'            => $notes,
                'status'           => $status,
            ],
            'items'       => $items,
            'tax'         => (float) $tax,
            'newCustomer' => $newCustomer,
            'errors'      => $errors,
        ];
    }

    /**
     * Menyimpan galat dan isian lama lalu kembali ke form.
     *
     * @param array<string, string> $errors
     */
    private function backWithErrors(string $path, array $errors): never
    {
        $old = $_POST;
        unset($old['csrf_token']);

        $this->flash('errors', $errors);
        $this->flash('old', $old);
        $this->flash('error', 'Periksa kembali isian yang ditandai.');
        $this->redirect($path);
    }

    /**
     * @param array<string, mixed>  $form
     * @param list<array<string, string>> $items
     * @param array<string, mixed>  $flash
     * @param array<string, mixed>  $settings
     */
    private function renderForm(string $mode, string $action, ?int $id, array $form, array $items, array $flash, array $settings, string $suggested): void
    {
        $this->view('quotation_create_view', [
            'pageTitle'       => $mode === 'edit' ? 'Ubah Quotation' : 'Quotation Baru',
            'activeMenu'      => 'create',
            'mode'            => $mode,
            'action'          => $action,
            'quotationId'     => $id,
            'form'            => $form,
            'items'           => $items,
            'errors'          => is_array($flash['errors'] ?? null) ? $flash['errors'] : [],
            'customers'       => $this->customers->all('', 300),
            'settings'        => $settings,
            'suggestedNumber' => $suggested,
            'statuses'        => QuotationModel::STATUSES,
            'flash'           => $flash,
        ]);
    }

    /**
     * @param array<string, mixed> $settings
     *
     * @return array<string, string>
     */
    private function defaultForm(array $settings): array
    {
        return [
            'quotation_number'  => '',
            'quotation_date'    => date('Y-m-d'),
            'valid_until'       => date('Y-m-d', strtotime('+7 days')),
            'notes'             => '',
            'status'            => 'Draft',
            'customer_id'       => '',
            'customer_name'     => '',
            'customer_company'  => '',
            'customer_email'    => '',
            'customer_phone'    => '',
            'customer_address'  => '',
            'tax_percentage'    => $this->plain((float) ($settings['tax_percentage_default'] ?? 11)),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function blankItem(): array
    {
        return ['description' => '', 'quantity' => '1', 'unit' => 'PCS', 'unit_price' => ''];
    }

    /**
     * Menimpa nilai form dengan isian lama (setelah validasi gagal).
     *
     * @param array<string, mixed>  $old
     * @param array<string, string> $form
     *
     * @return array{0: array<string, string>, 1: list<array<string, string>>}
     */
    private function mergeOld(array $old, array $form): array
    {
        foreach (array_keys($form) as $key) {
            if (isset($old[$key]) && is_string($old[$key])) {
                $form[$key] = $old[$key];
            }
        }

        $items = [];
        foreach (is_array($old['items'] ?? null) ? $old['items'] : [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $items[] = [
                'description' => is_string($row['description'] ?? null) ? $row['description'] : '',
                'quantity'    => is_string($row['quantity'] ?? null) ? $row['quantity'] : '',
                'unit'        => is_string($row['unit'] ?? null) ? $row['unit'] : '',
                'unit_price'  => is_string($row['unit_price'] ?? null) ? $row['unit_price'] : '',
            ];
        }

        return [$form, $items === [] ? [$this->blankItem()] : $items];
    }

    /**
     * Angka tanpa nol di belakang koma, untuk nilai input form: 2.50 -> "2.5".
     */
    private function plain(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') ?: '0';
    }
}
