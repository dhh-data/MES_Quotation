<?php
/**
 * Pengaturan perusahaan: profil, kontak, PPN default, logo, dan tanda tangan digital.
 *
 * Variabel: $settings, $errors, $logoSrc, $signatureSrc.
 * Nama field mengikuti SettingsController::update():
 *   company_name, company_address, company_phone, company_email,
 *   tax_percentage_default, company_logo (file), company_signature (file),
 *   remove_logo, remove_signature.
 */
$errors = is_array($errors ?? null) ? $errors : [];
$err = static fn (string $k): string => isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
$bad = static fn (string $k): string => isset($errors[$k]) ? ' input-error' : '';
$val = static fn (string $k): string => e($settings[$k] ?? '');

$tax = (string) ($settings['tax_percentage_default'] ?? '11');
if (str_contains($tax, '.')) {
    $tax = rtrim(rtrim($tax, '0'), '.');
}

$defaultLogo = uploaded_image_src('assets/img/logo-mes.png');
$usingDefaultLogo = !$logoSrc;
?>
<div class="mb-6">
  <h1 class="text-2xl font-extrabold tracking-tight">Pengaturan Perusahaan</h1>
  <p class="mt-1 text-sm text-moss-600 dark:text-moss-300">Data ini tampil di kop surat dan footer setiap quotation.</p>
</div>

<form method="post" action="<?= e(url('/settings')) ?>" enctype="multipart/form-data" class="space-y-6" autocomplete="off">
  <?= csrf_field() ?>

  <section class="card p-5" aria-labelledby="sec-profile">
    <h2 id="sec-profile" class="section-title">Profil dan kontak</h2>
    <div class="mt-4 grid gap-4 sm:grid-cols-2">
      <div class="sm:col-span-2">
        <label for="company_name" class="field-label">Nama perusahaan</label>
        <input id="company_name" name="company_name" type="text" required maxlength="150" value="<?= $val('company_name') ?>" class="input<?= $bad('company_name') ?>">
        <?= $err('company_name') ?>
      </div>
      <div class="sm:col-span-2">
        <label for="company_address" class="field-label">Alamat</label>
        <textarea id="company_address" name="company_address" rows="3" maxlength="2000" class="input<?= $bad('company_address') ?>" placeholder="Satu baris per alamat"><?= $val('company_address') ?></textarea>
        <?= $err('company_address') ?>
      </div>
      <div>
        <label for="company_phone" class="field-label">Nomor telepon</label>
        <input id="company_phone" name="company_phone" type="tel" maxlength="30" value="<?= $val('company_phone') ?>" class="input<?= $bad('company_phone') ?>" placeholder="+62 21 ...">
        <?= $err('company_phone') ?>
      </div>
      <div>
        <label for="company_email" class="field-label">Email</label>
        <input id="company_email" name="company_email" type="email" maxlength="120" value="<?= $val('company_email') ?>" class="input<?= $bad('company_email') ?>" placeholder="finance@perusahaan.co.id">
        <?= $err('company_email') ?>
      </div>
    </div>
  </section>

  <section class="card p-5" aria-labelledby="sec-tax">
    <h2 id="sec-tax" class="section-title">Pajak</h2>
    <div class="mt-4 max-w-xs">
      <label for="tax_percentage_default" class="field-label">Tarif PPN bawaan (%)</label>
      <input id="tax_percentage_default" name="tax_percentage_default" type="text" inputmode="decimal" value="<?= e($tax) ?>" class="input text-right tabular-nums<?= $bad('tax_percentage_default') ?>">
      <?= $err('tax_percentage_default') ?>
      <p class="field-hint">Terisi otomatis di quotation baru. Bisa diubah per quotation.</p>
    </div>
  </section>

  <section class="card p-5" aria-labelledby="sec-images">
    <h2 id="sec-images" class="section-title">Logo dan tanda tangan digital</h2>
    <p class="mt-1 text-sm text-moss-600 dark:text-moss-300">Format PNG atau JPG, maksimal 2 MB, maksimal 4000 x 4000 piksel. Tanda tangan paling rapi bila PNG berlatar transparan.</p>

    <div class="mt-5 grid gap-6 md:grid-cols-2">
      <!-- Logo -->
      <div>
        <p class="field-label">Logo perusahaan</p>
        <div class="flex h-36 items-center justify-center rounded-lg border border-dashed border-moss-300 bg-white p-3 dark:border-moss-600">
          <img id="logo-preview" src="<?= e($logoSrc ?: $defaultLogo) ?>" alt="Logo saat ini" class="max-h-full max-w-full object-contain">
        </div>
        <?php if ($usingDefaultLogo): ?><p class="field-hint">Memakai logo bawaan MES.</p><?php endif; ?>
        <label for="company_logo" class="field-label mt-3">Unggah logo baru</label>
        <input id="company_logo" name="company_logo" type="file" accept="image/png,image/jpeg"
               data-preview="logo-preview" data-message="logo-msg" data-remove="remove_logo"
               class="input file:mr-3 file:rounded-md file:border-0 file:bg-moss-100 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-moss-800 dark:file:bg-moss-800 dark:file:text-moss-100">
        <p id="logo-msg" class="field-error" role="alert"></p>
        <?= $err('company_logo') ?>
        <?php if (!$usingDefaultLogo): ?>
          <label class="mt-3 flex items-center gap-2 text-sm">
            <input id="remove_logo" name="remove_logo" type="checkbox" value="1" class="h-4 w-4 rounded border-moss-300 text-mes-green focus:ring-mes-leaf">
            Hapus logo ini dan pakai logo bawaan MES
          </label>
        <?php endif; ?>
      </div>

      <!-- Tanda tangan -->
      <div>
        <p class="field-label">Tanda tangan digital</p>
        <div class="flex h-36 items-center justify-center rounded-lg border border-dashed border-moss-300 bg-white p-3 dark:border-moss-600">
          <?php if ($signatureSrc): ?>
            <img id="signature-preview" src="<?= e($signatureSrc) ?>" alt="Tanda tangan saat ini" class="max-h-full max-w-full object-contain">
          <?php else: ?>
            <img id="signature-preview" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==" alt="" class="max-h-full max-w-full object-contain">
          <?php endif; ?>
        </div>
        <?php if (!$signatureSrc): ?><p class="field-hint">Belum ada tanda tangan. Area tanda tangan di quotation akan kosong.</p><?php endif; ?>
        <label for="company_signature" class="field-label mt-3">Unggah tanda tangan baru</label>
        <input id="company_signature" name="company_signature" type="file" accept="image/png,image/jpeg"
               data-preview="signature-preview" data-message="signature-msg" data-remove="remove_signature"
               class="input file:mr-3 file:rounded-md file:border-0 file:bg-moss-100 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-moss-800 dark:file:bg-moss-800 dark:file:text-moss-100">
        <p id="signature-msg" class="field-error" role="alert"></p>
        <?= $err('company_signature') ?>
        <?php if ($signatureSrc): ?>
          <label class="mt-3 flex items-center gap-2 text-sm">
            <input id="remove_signature" name="remove_signature" type="checkbox" value="1" class="h-4 w-4 rounded border-moss-300 text-mes-green focus:ring-mes-leaf">
            Hapus tanda tangan ini
          </label>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <div class="flex justify-end gap-2">
    <a href="<?= e(url('/quotations')) ?>" class="btn btn-secondary">Batal</a>
    <button type="submit" class="btn btn-primary">Simpan pengaturan</button>
  </div>
</form>
