<?php
/**
 * Pratinjau lembar quotation A4.
 *
 * Tiga mode, satu markup lembar:
 *  - Tampilan aplikasi (dengan layout): bilah alat, lembar diskalakan di layar.
 *  - $printMode: dokumen mandiri untuk /quotations/{id}/print, dialog cetak otomatis.
 *  - $pdfMode  : dokumen mandiri untuk DomPDF; CSS disisipkan inline dan gambar
 *                berupa data URI.
 *
 * Variabel: $quotation, $items, $totals, $settings, $say, $logoSrc,
 * $signatureSrc, $statuses, $pdfMode, $printMode.
 */
$q        = $quotation;
$st       = is_array($settings ?? null) ? $settings : [];
$pdf      = !empty($pdfMode);
$print    = !empty($printMode);
$solo     = $pdf || $print;
$id       = (int) $q['id'];
$number   = (string) $q['quotation_number'];

$logo     = $logoSrc ?: uploaded_image_src('assets/img/logo-mes.png', $pdf);
$sigImg   = $signatureSrc ?? null;

$coName   = (string) ($st['company_name'] ?? APP_NAME);
$coAddr   = trim((string) ($st['company_address'] ?? ''));
$coPhone  = trim((string) ($st['company_phone'] ?? ''));
$coEmail  = trim((string) ($st['company_email'] ?? ''));

$custCo   = trim((string) ($q['customer_company'] ?? ''));
$custName = trim((string) ($q['customer_name'] ?? ''));
$custAddr = trim((string) ($q['customer_address'] ?? ''));
$billTo   = $custCo !== '' ? $custCo : $custName;

$qty      = static fn ($v): string => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
$taxPct   = (float) ($totals['tax_percentage'] ?? 0);
$taxAmt   = (float) ($totals['tax_amount'] ?? 0);
$hasTax   = $taxAmt > 0;
$notes    = trim((string) ($q['notes'] ?? ''));
$lastIdx  = count($items) - 1;

if ($solo): ?><!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Quotation <?= e($number) ?></title>
<?php if ($pdf): ?>
<style><?= file_get_contents(PUBLIC_PATH . '/assets/css/sheet.css') ?></style>
<?php else: ?>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&amp;display=swap">
<link rel="stylesheet" href="<?= e(asset('css/tailwind.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/sheet.css')) ?>">
<?php endif; ?>
</head>
<body<?= $print ? ' class="bg-moss-100 print:bg-white" data-autoprint="1"' : '' ?>>
<?php if ($print): ?>
<div class="mx-auto flex max-w-[210mm] flex-wrap items-center justify-between gap-3 px-4 py-4 print:hidden">
  <a href="<?= e(url('/quotations/' . $id)) ?>" class="btn btn-secondary">Kembali</a>
  <button type="button" class="btn btn-primary" data-print>Cetak / Simpan sebagai PDF</button>
</div>
<?php endif; ?>
<?php else: ?>
<div class="mb-6 print:hidden">
  <nav aria-label="Navigasi halaman" class="text-sm text-moss-600 dark:text-moss-300">
    <a href="<?= e(url('/quotations')) ?>" class="font-semibold text-mes-green hover:underline dark:text-mes-lime">Riwayat Quotation</a>
    <span aria-hidden="true"> / </span><span class="font-mono"><?= e($number) ?></span>
  </nav>

  <div class="mt-3 flex flex-wrap items-center gap-3">
    <h1 class="text-2xl font-extrabold tracking-tight">Quotation <span class="font-mono text-xl"><?= e($number) ?></span></h1>
    <?= status_badge((string) $q['status']) ?>
    <div class="ml-auto flex flex-wrap gap-2">
      <a href="<?= e(url("/quotations/{$id}/edit")) ?>" class="btn btn-secondary">Ubah</a>
      <button type="button" class="btn btn-secondary" data-print>Cetak</button>
      <a href="<?= e(url("/quotations/{$id}/pdf")) ?>" class="btn btn-primary">Unduh PDF</a>
    </div>
  </div>

  <div class="card mt-4 flex flex-wrap items-end gap-3 p-3">
    <form method="post" action="<?= e(url("/quotations/{$id}/status")) ?>" class="flex flex-wrap items-end gap-2">
      <?= csrf_field() ?>
      <div>
        <label for="status" class="field-label">Ubah status</label>
        <select id="status" name="status" class="input w-40 py-1.5">
          <?php foreach ($statuses as $s): ?>
            <option value="<?= e($s) ?>"<?= $q['status'] === $s ? ' selected' : '' ?>><?= e($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-secondary">Terapkan</button>
    </form>
    <p class="min-w-0 flex-1 text-xs text-moss-500 dark:text-moss-400">
      Untuk file PDF, pilih <em>Unduh PDF</em>. Bila DomPDF belum terpasang, halaman cetak terbuka dan Anda bisa memilih "Save as PDF".
    </p>
    <button type="button" class="btn btn-danger"
            data-delete-action="<?= e(url("/quotations/{$id}/delete")) ?>" data-delete-label="<?= e($number) ?>">Hapus</button>
  </div>
</div>
<?php endif; ?>

<?php /* Pembungkus layar (overflow tersembunyi + skala) dilewati di PDF karena memotong footer. */ ?>
<div<?= $pdf ? '' : ' class="sheet-stage" data-sheet-stage' ?>>
<div<?= $pdf ? '' : ' class="sheet-scale" data-sheet-scale' ?>>
<article class="sheet<?= $pdf ? ' is-pdf' : ' sheet-shadow' ?>" aria-label="Lembar quotation <?= e($number) ?>">
  <div class="s-body">

    <!-- Kop surat -->
    <table class="s-head">
      <tr>
        <?php if ($logo): ?><td class="s-logo"><img src="<?= e($logo) ?>" alt="Logo <?= e($coName) ?>"></td><?php endif; ?>
        <td>
          <div class="s-name"><?= e($coName) ?></div>
          <?php if ($coAddr !== ''): ?><div class="s-addr"><?= nl2br(e($coAddr)) ?></div><?php endif; ?>
        </td>
        <td class="s-contact">
          <?php if ($coPhone !== ''): ?>Telp. <?= e($coPhone) ?><br><?php endif; ?>
          <?php if ($coEmail !== ''): ?><?= e($coEmail) ?><?php endif; ?>
        </td>
      </tr>
    </table>
    <table class="s-rule" role="presentation">
      <tr>
        <td style="width:34%;background:#05472f">&nbsp;</td>
        <td style="width:24%;background:#177a41">&nbsp;</td>
        <td style="width:22%;background:#b4d000">&nbsp;</td>
        <td style="width:20%;background:#f7e100">&nbsp;</td>
      </tr>
    </table>

    <!-- Pelanggan dan data dokumen -->
    <table class="s-two">
      <tr>
        <td class="l">
          <div class="s-label">Company</div>
          <div class="s-band"><?= e($billTo) ?><b><?= $custAddr !== '' ? nl2br(e($custAddr)) : '&nbsp;' ?></b></div>
          <div class="s-sub">Quotation For</div>
          <div class="s-band"><?= e($custName !== '' ? $custName : $billTo) ?></div>
        </td>
        <td class="r">
          <div class="s-title">Sales Quotation</div>
          <div class="s-band">
            <table class="s-meta">
              <tr><td class="k">Quotation No.</td><td>: <?= e($number) ?></td></tr>
              <tr><td class="k">Quotation Date</td><td>: <?= e(date_id($q['quotation_date'])) ?></td></tr>
              <tr><td class="k">Valid Until</td><td>: <b style="display:inline"><?= e(date_id($q['valid_until'])) ?></b></td></tr>
              <tr><td class="k">Currency</td><td>: IDR (Rupiah)</td></tr>
            </table>
          </div>
        </td>
      </tr>
    </table>

    <!-- Rincian -->
    <table class="s-items">
      <thead>
        <tr>
          <th class="ctr" style="width:7%">No.</th>
          <th>Description</th>
          <th class="num" style="width:13%">Quantity</th>
          <th class="num" style="width:19%">Unit Price (IDR)</th>
          <th class="num" style="width:20%">Sub Total (IDR)</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $n => $it): ?>
          <tr<?= $n === $lastIdx ? ' class="last"' : '' ?>>
            <td class="ctr"><?= $n + 1 ?></td>
            <td><?= nl2br(e($it['item_description'])) ?></td>
            <td class="num"><?= e($qty($it['quantity'])) ?> <?= e($it['unit']) ?></td>
            <td class="num"><?= e(format_idr($it['unit_price'])) ?></td>
            <td class="num"><?= e(format_idr($it['total_price'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <!-- Ringkasan -->
    <table class="s-sum">
      <tr>
        <td class="l">
          <div class="s-say"><b>Say :</b> <?= e($say) ?></div>
          <?php if (!$hasTax): ?><div class="s-pricenote">Price above excluded PPN</div><?php endif; ?>
        </td>
        <td>
          <table class="s-totals">
            <?php if ($hasTax): ?>
              <tr><td>Sub Total</td><td class="num"><?= e(format_idr($totals['subtotal'])) ?></td></tr>
              <tr><td>PPN <?= e($qty($taxPct)) ?>%</td><td class="num"><?= e(format_idr($taxAmt)) ?></td></tr>
            <?php endif; ?>
            <tr class="grand"><td>Total (IDR)</td><td class="num"><?= e(format_idr($totals['total_amount'])) ?></td></tr>
          </table>
        </td>
      </tr>
    </table>

    <?php if ($notes !== ''): ?>
      <div class="s-notes"><b>Notes / Terms &amp; Condition</b><?= nl2br(e($notes)) ?></div>
    <?php endif; ?>

    <!-- Tanda tangan -->
    <table class="s-sign">
      <tr>
        <td>
          <div>Customer Acceptance,</div>
          <div class="space"></div>
          <div class="line">&nbsp;</div>
          <div class="role">Name, signature &amp; company stamp</div>
        </td>
        <td>
          <div>Best Regards,</div>
          <div class="space"><?php if ($sigImg): ?><img src="<?= e($sigImg) ?>" alt="Tanda tangan <?= e($coName) ?>"><?php endif; ?></div>
          <div class="line"><?= e($coName) ?></div>
          <div class="role">Authorized signature</div>
        </td>
      </tr>
    </table>
  </div>

  <!-- Footer lembar -->
  <div class="s-foot">
    <table>
      <tr>
        <td class="nm"><?= e($coName) ?></td>
        <td><?php if ($coAddr !== ''): ?><span class="lbl">ADDRESS</span><?= nl2br(e($coAddr)) ?><?php else: ?>&nbsp;<?php endif; ?></td>
        <?php if ($coPhone !== '' || $coEmail !== ''): ?>
          <td class="tab"><?= e($coPhone) ?><?= ($coPhone !== '' && $coEmail !== '') ? '<br>' : '' ?><?= e($coEmail) ?></td>
        <?php endif; ?>
      </tr>
    </table>
  </div>
</article>
</div>
</div>

<?php if ($solo): ?>
<?php if ($print): ?><script src="<?= e(asset('js/pdf.js')) ?>"></script><?php endif; ?>
</body>
</html>
<?php else: ?>
<script src="<?= e(asset('js/pdf.js')) ?>" defer></script>
<?php endif; ?>
