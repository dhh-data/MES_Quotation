<?php
/**
 * Controller cetak dan PDF untuk lembar quotation A4.
 *
 * Dua jalur, otomatis dipilih:
 *  1. DomPDF (composer require dompdf/dompdf) bila terpasang: GET
 *     /quotations/{id}/pdf menghasilkan file PDF yang diunduh dan disimpan
 *     di storage/outputs/generated_pdfs sebagai cache. Nama file cache memuat
 *     hash isi dokumen, jadi PDF otomatis dibuat ulang saat data berubah.
 *  2. Cetak bawaan browser bila DomPDF belum terpasang: GET
 *     /quotations/{id}/print menampilkan lembar A4 tanpa menu dan membuka
 *     dialog cetak (pilih "Save as PDF").
 *
 * View quotation_preview_view menerima $pdfMode (true saat DomPDF: gunakan
 * tata letak tabel, gambar berupa data URI) dan $printMode (true saat cetak
 * browser: panggil window.print()).
 *
 * @package App\Controllers
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\QuotationModel;
use App\Models\SettingsModel;
use Throwable;

final class PdfController extends Controller
{
    /** Naikkan nilainya setiap kali tampilan lembar berubah, agar cache lama tidak dipakai. */
    private const TEMPLATE_VERSION = '1';

    private QuotationModel $quotations;
    private SettingsModel $settings;

    public function __construct()
    {
        $this->quotations = new QuotationModel();
        $this->settings   = new SettingsModel();
    }

    /**
     * GET /quotations/{id}/pdf : unduh PDF (DomPDF) atau arahkan ke cetak browser.
     */
    public function download(int $id): void
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            $this->flash('info', 'DomPDF belum terpasang, memakai cetak browser. Pilih "Save as PDF" di dialog cetak.');
            $this->redirect("/quotations/{$id}/print");
        }

        $data   = $this->documentData($id, true, false);
        $number = (string) $data['quotation']['quotation_number'];
        $safe   = $this->safeName($number);

        $dir = ROOT_PATH . '/storage/outputs/generated_pdfs';
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Folder cache PDF tidak bisa dibuat.');
        }

        // Hash memuat data, tampilan lembar (waktu ubah View dan CSS), dan logo/tanda tangan
        // yang dipakai, sehingga PDF lama tidak dipakai lagi setelah salah satunya berubah.
        $hash = substr(sha1((string) json_encode([
            $data['quotation'],
            $data['items'],
            $data['settings'],
            self::TEMPLATE_VERSION,
            @filemtime(ROOT_PATH . '/src/Views/quotation_preview_view.php'),
            @filemtime(PUBLIC_PATH . '/assets/css/sheet.css'),
            md5((string) $data['logoSrc']),
            md5((string) $data['signatureSrc']),
        ])), 0, 10);
        $file = "{$dir}/{$safe}_{$hash}.pdf";

        if (!is_file($file)) {
            $pdf = $this->renderPdf($data);
            if (file_put_contents($file, $pdf, LOCK_EX) === false) {
                throw new \RuntimeException('PDF gagal disimpan.');
            }
            foreach (glob("{$dir}/{$safe}_*.pdf") ?: [] as $stale) { // buang versi lama
                if ($stale !== $file) {
                    @unlink($stale);
                }
            }
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="Quotation-' . $safe . '.pdf"');
        header('Content-Length: ' . filesize($file));
        header('X-Content-Type-Options: nosniff');
        readfile($file);
        exit;
    }

    /**
     * GET /quotations/{id}/print : lembar A4 siap cetak (dialog cetak otomatis).
     */
    public function printPage(int $id): void
    {
        $data = $this->documentData($id, false, true);
        $this->view('quotation_preview_view', $data, false);
    }

    // ------------------------------------------------------------------

    /**
     * Menyusun data View lembar quotation.
     *
     * @return array<string, mixed>
     */
    private function documentData(int $id, bool $pdfMode, bool $printMode): array
    {
        $doc = $this->quotations->getDocument($id);
        if ($doc === null) {
            $this->abort(404, 'Quotation tidak ditemukan.');
        }

        $settings = $this->settings->get();

        return $doc + [
            'pageTitle'    => 'Quotation ' . $doc['quotation']['quotation_number'],
            'settings'     => $settings,
            'say'          => terbilang($doc['totals']['total_amount']),
            'logoSrc'      => uploaded_image_src($settings['company_logo'] ?? null, $pdfMode),
            'signatureSrc' => uploaded_image_src($settings['company_signature'] ?? null, $pdfMode),
            'pdfMode'      => $pdfMode,
            'printMode'    => $printMode,
        ];
    }

    /**
     * Merender HTML lembar menjadi biner PDF dengan DomPDF.
     *
     * @param array<string, mixed> $data
     */
    private function renderPdf(array $data): string
    {
        try {
            $html = $this->render('quotation_preview_view', $data, false);

            $options = new \Dompdf\Options();
            $options->set('isRemoteEnabled', false);      // tidak mengambil sumber dari internet
            $options->set('isHtml5ParserEnabled', true);
            $options->set('chroot', ROOT_PATH);
            $options->set('defaultFont', 'DejaVu Sans');

            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            return $dompdf->output();
        } catch (Throwable $e) {
            throw new \RuntimeException('Gagal membuat PDF: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Nama file aman dari nomor quotation.
     */
    private function safeName(string $number): string
    {
        $safe = trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $number), '-.');

        return $safe === '' ? 'quotation' : $safe;
    }
}
