<?php
/**
 * Formulir quotation (buat baru dan ubah).
 *
 * Variabel: $mode (create|edit), $action, $quotationId, $form, $items, $errors,
 * $customers, $settings, $suggestedNumber, $statuses.
 *
 * Nama field mengikuti QuotationController::collect():
 *   quotation_number, status, quotation_date, valid_until, customer_id,
 *   customer_name/company/email/phone/address, tax_percentage, notes,
 *   items[i][description|quantity|unit|unit_price]
 */
$isEdit = ($mode ?? 'create') === 'edit';
$errors = is_array($errors ?? null) ? $errors : [];

$err = static function (string $key) use ($errors): string {
    return isset($errors[$key]) ? '<p class="field-error" data-server-error>' . e($errors[$key]) . '</p>' : '';
};
$invalid = static fn (string $key): string => isset($errors[$key]) ? ' input-error' : '';

$customersJson = json_encode(array_map(static fn (array $c): array => [
    'id'           => (int) $c['id'],
    'name'         => (string) $c['name'],
    'company_name' => (string) ($c['company_name'] ?? ''),
    'email'        => (string) ($c['email'] ?? ''),
    'phone'        => (string) ($c['phone'] ?? ''),
    'address'      => (string) ($c['address'] ?? ''),
], $customers ?? []), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);

/**
 * Satu baris item. Dipakai untuk baris dari server dan untuk <template> baris baru.
 *
 * @param array<string, string> $it
 */
$renderRow = static function (string|int $i, array $it) use ($err, $invalid): void {
    $k = static fn (string $f): string => "items.{$i}.{$f}";
    ?>
    <div data-item-row class="grid grid-cols-2 gap-x-3 gap-y-3 rounded-lg border border-moss-200 bg-moss-50/60 p-3 dark:border-moss-800 dark:bg-moss-950/40
                              md:grid-cols-[2rem_minmax(0,1fr)_5.5rem_5.5rem_9rem_9rem_2.25rem] md:items-start md:border-0 md:bg-transparent md:p-0 md:dark:bg-transparent">
      <div class="col-span-2 flex items-center justify-between md:hidden">
        <span class="text-xs font-bold uppercase tracking-wide text-moss-600 dark:text-moss-300">Item <span data-row-no><?= is_int($i) ? $i + 1 : '' ?></span></span>
        <button type="button" data-remove-row class="btn btn-ghost btn-sm" aria-label="Hapus item ini">Hapus</button>
      </div>

      <span class="hidden pt-2 text-center font-mono text-sm text-moss-500 md:block" data-row-no><?= is_int($i) ? $i + 1 : '' ?></span>

      <label class="col-span-2 block md:col-span-1" data-cell>
        <span class="field-label md:sr-only">Deskripsi</span>
        <textarea name="items[<?= e($i) ?>][description]" data-field="description" rows="2" maxlength="500" class="input resize-y<?= $invalid($k('description')) ?>"
                  placeholder="Nama barang atau jasa"><?= e($it['description'] ?? '') ?></textarea>
        <?= $err($k('description')) ?>
      </label>

      <label class="block" data-cell>
        <span class="field-label md:sr-only">Jumlah</span>
        <input type="text" inputmode="decimal" autocomplete="off" name="items[<?= e($i) ?>][quantity]" data-field="quantity" value="<?= e($it['quantity'] ?? '') ?>"
               class="input text-right tabular-nums<?= $invalid($k('quantity')) ?>" placeholder="1">
        <?= $err($k('quantity')) ?>
        <p class="field-hint text-amber-700 dark:text-amber-300" data-ambig hidden></p>
      </label>

      <label class="block" data-cell>
        <span class="field-label md:sr-only">Satuan</span>
        <input type="text" list="unit-options" autocomplete="off" maxlength="20" name="items[<?= e($i) ?>][unit]" value="<?= e($it['unit'] ?? 'PCS') ?>"
               class="input uppercase<?= $invalid($k('unit')) ?>" placeholder="PCS">
        <?= $err($k('unit')) ?>
      </label>

      <label class="block" data-cell>
        <span class="field-label md:sr-only">Harga satuan (IDR)</span>
        <input type="text" inputmode="decimal" autocomplete="off" name="items[<?= e($i) ?>][unit_price]" data-field="unit_price" value="<?= e($it['unit_price'] ?? '') ?>"
               class="input text-right tabular-nums<?= $invalid($k('unit_price')) ?>" placeholder="0">
        <?= $err($k('unit_price')) ?>
        <p class="field-hint text-amber-700 dark:text-amber-300" data-ambig hidden></p>
      </label>

      <div>
        <span class="field-label md:sr-only">Sub total (IDR)</span>
        <p class="rounded-lg bg-moss-100 px-3 py-2 text-right text-sm font-semibold tabular-nums dark:bg-moss-800" data-line-total>0</p>
      </div>

      <button type="button" data-remove-row class="btn-icon hidden text-red-700 hover:bg-red-50 hover:text-red-800 disabled:pointer-events-none disabled:opacity-30 dark:text-red-300 dark:hover:bg-red-950 md:inline-flex" aria-label="Hapus item ini">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 7h14M10 7V5h4v2M7 7l1 12h8l1-12M10 11v5M14 11v5"/></svg>
      </button>
    </div>
    <?php
};
?>
<div class="mb-6 flex flex-wrap items-end justify-between gap-3">
  <div>
    <h1 class="text-2xl font-extrabold tracking-tight"><?= $isEdit ? 'Ubah Quotation' : 'Buat Quotation Baru' ?></h1>
    <p class="mt-1 text-sm text-moss-600 dark:text-moss-300">
      <?= $isEdit ? 'Perubahan menimpa data quotation dan seluruh itemnya.' : 'Isi data di bawah. Total dan terbilang terhitung otomatis.' ?>
    </p>
  </div>
  <a href="<?= e(url($isEdit ? '/quotations/' . (int) $quotationId : '/quotations')) ?>" class="btn btn-secondary">Batal</a>
</div>

<form id="quotation-form" method="post" action="<?= e($action) ?>" class="space-y-6" autocomplete="off">
  <?= csrf_field() ?>

  <?php if (isset($errors['items'])): ?>
    <div role="alert" class="rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm font-medium text-red-900 dark:border-red-800 dark:bg-red-950/60 dark:text-red-100"><?= e($errors['items']) ?></div>
  <?php endif; ?>

  <!-- Dokumen -->
  <section class="card p-5" aria-labelledby="sec-doc">
    <h2 id="sec-doc" class="section-title">Dokumen</h2>
    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <div class="sm:col-span-2">
        <label for="quotation_number" class="field-label">No. Quotation</label>
        <input id="quotation_number" name="quotation_number" type="text" maxlength="30" value="<?= e($form['quotation_number'] ?? '') ?>"
               class="input font-mono<?= $invalid('quotation_number') ?>"
               placeholder="<?= $isEdit ? '' : 'Otomatis: ' . e($suggestedNumber ?? '') ?>" <?= $isEdit ? 'required' : '' ?>>
        <?= $err('quotation_number') ?>
        <?php if (!$isEdit): ?><p class="field-hint">Kosongkan agar nomor dibuat otomatis (SQ-TH-BL-001).</p><?php endif; ?>
      </div>
      <div class="sm:col-span-2">
        <label for="status" class="field-label">Status</label>
        <select id="status" name="status" class="input<?= $invalid('status') ?>">
          <?php foreach ($statuses as $s): ?>
            <option value="<?= e($s) ?>"<?= ($form['status'] ?? 'Draft') === $s ? ' selected' : '' ?>><?= e($s) ?></option>
          <?php endforeach; ?>
        </select>
        <?= $err('status') ?>
      </div>
      <div>
        <label for="quotation_date" class="field-label">Tanggal Quotation</label>
        <input id="quotation_date" name="quotation_date" type="date" required value="<?= e($form['quotation_date'] ?? '') ?>" class="input<?= $invalid('quotation_date') ?>">
        <?= $err('quotation_date') ?>
      </div>
      <div>
        <label for="valid_until" class="field-label">Berlaku Sampai</label>
        <input id="valid_until" name="valid_until" type="date" required value="<?= e($form['valid_until'] ?? '') ?>" class="input<?= $invalid('valid_until') ?>">
        <?= $err('valid_until') ?>
        <div class="mt-2 flex flex-wrap gap-1.5" role="group" aria-label="Isi masa berlaku cepat">
          <?php foreach ([7, 14, 30] as $days): ?>
            <button type="button" data-valid-days="<?= $days ?>" class="btn btn-secondary btn-sm">+<?= $days ?> hari</button>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>

  <!-- Pelanggan -->
  <section class="card p-5" aria-labelledby="sec-customer">
    <h2 id="sec-customer" class="section-title">Pelanggan</h2>
    <div class="mt-4 grid gap-4 lg:grid-cols-2">
      <div class="lg:col-span-2">
        <label for="customer_id" class="field-label">Pilih pelanggan tersimpan</label>
        <select id="customer_id" name="customer_id" class="input<?= $invalid('customer_id') ?>">
          <option value="">+ Pelanggan baru (isi di bawah)</option>
          <?php foreach ($customers as $c): ?>
            <option value="<?= (int) $c['id'] ?>"<?= (string) ($form['customer_id'] ?? '') === (string) $c['id'] ? ' selected' : '' ?>>
              <?= e(($c['company_name'] ?? '') !== '' && $c['company_name'] !== null ? $c['company_name'] . ' - ' . $c['name'] : $c['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?= $err('customer_id') ?>
      </div>

      <!-- Ringkasan pelanggan tersimpan -->
      <div id="customer-existing" class="rounded-lg bg-moss-100 p-4 text-sm dark:bg-moss-800 lg:col-span-2" hidden>
        <p class="font-bold" data-c="company"></p>
        <p class="text-moss-600 dark:text-moss-300">Quotation For: <span data-c="name"></span></p>
        <p class="mt-2 whitespace-pre-line" data-c="address"></p>
        <p class="mt-2 text-moss-600 dark:text-moss-300"><span data-c="email"></span> &middot; <span data-c="phone"></span></p>
      </div>

      <!-- Pelanggan baru -->
      <div id="customer-new" class="grid gap-4 lg:col-span-2 lg:grid-cols-2">
        <div>
          <label for="customer_company" class="field-label">Perusahaan (Company)</label>
          <input id="customer_company" name="customer_company" type="text" maxlength="150" value="<?= e($form['customer_company'] ?? '') ?>" class="input<?= $invalid('customer_company') ?>" placeholder="CV. Contoh Makmur">
          <?= $err('customer_company') ?>
        </div>
        <div>
          <label for="customer_name" class="field-label">Penerima (Quotation For)</label>
          <input id="customer_name" name="customer_name" type="text" maxlength="120" value="<?= e($form['customer_name'] ?? '') ?>" class="input<?= $invalid('customer_name') ?>" placeholder="Nama PIC">
          <?= $err('customer_name') ?>
        </div>
        <div class="lg:col-span-2">
          <label for="customer_address" class="field-label">Alamat</label>
          <textarea id="customer_address" name="customer_address" rows="3" maxlength="2000" class="input<?= $invalid('customer_address') ?>" placeholder="Satu baris per alamat"><?= e($form['customer_address'] ?? '') ?></textarea>
          <?= $err('customer_address') ?>
        </div>
        <div>
          <label for="customer_email" class="field-label">Email</label>
          <input id="customer_email" name="customer_email" type="email" maxlength="120" value="<?= e($form['customer_email'] ?? '') ?>" class="input<?= $invalid('customer_email') ?>" placeholder="pembelian@contoh.co.id">
          <?= $err('customer_email') ?>
        </div>
        <div>
          <label for="customer_phone" class="field-label">Telepon</label>
          <input id="customer_phone" name="customer_phone" type="tel" maxlength="30" value="<?= e($form['customer_phone'] ?? '') ?>" class="input<?= $invalid('customer_phone') ?>" placeholder="+62 ...">
          <?= $err('customer_phone') ?>
        </div>
      </div>
    </div>
  </section>

  <!-- Item -->
  <section class="card p-5" aria-labelledby="sec-items">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <h2 id="sec-items" class="section-title">Rincian Barang / Jasa</h2>
      <p class="text-xs text-moss-500 dark:text-moss-400">Desimal pakai koma (2,5). Titik hanya untuk ribuan penuh (1.500.000).</p>
    </div>

    <div class="mt-4 hidden grid-cols-[2rem_minmax(0,1fr)_5.5rem_5.5rem_9rem_9rem_2.25rem] gap-x-3 px-0 text-xs font-bold uppercase tracking-wide text-moss-600 dark:text-moss-300 md:grid" aria-hidden="true">
      <span class="text-center">No</span><span>Deskripsi</span><span class="text-right">Jumlah</span><span>Satuan</span><span class="text-right">Harga (IDR)</span><span class="text-right">Sub total</span><span></span>
    </div>

    <div id="item-rows" class="mt-2 space-y-3">
      <?php foreach ($items as $i => $it): ?>
        <?php $renderRow((int) $i, $it); ?>
      <?php endforeach; ?>
    </div>

    <template id="item-template"><?php $renderRow('__i__', ['quantity' => '1', 'unit' => 'PCS']); ?></template>
    <datalist id="unit-options">
      <?php foreach (['PCS', 'UNIT', 'SET', 'ROLL', 'BATANG', 'LOT', 'M', 'KG', 'LITER', 'JASA'] as $u): ?><option value="<?= e($u) ?>"><?php endforeach; ?>
    </datalist>

    <div class="mt-4">
      <button type="button" id="add-row" class="btn btn-secondary">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
        Tambah item
      </button>
    </div>
  </section>

  <!-- Catatan dan total -->
  <section class="grid gap-6 lg:grid-cols-2" aria-label="Catatan dan total">
    <div class="card p-5">
      <label for="notes" class="section-title">Catatan dan Syarat</label>
      <textarea id="notes" name="notes" rows="6" maxlength="5000" class="input mt-3<?= $invalid('notes') ?>"
                placeholder="Payment Terms: C.O.D&#10;Delivery: Free&#10;Lead Time: 2 minggu setelah PO"><?= e($form['notes'] ?? '') ?></textarea>
      <?= $err('notes') ?>
      <p class="field-hint">Tercetak di lembar quotation. Tulis satu syarat per baris.</p>
    </div>

    <div class="card p-5">
      <h2 class="section-title">Ringkasan</h2>
      <dl class="mt-4 space-y-3 text-sm">
        <div class="flex items-center justify-between gap-3">
          <dt class="text-moss-600 dark:text-moss-300">Sub total</dt>
          <dd class="font-semibold tabular-nums" data-subtotal>0</dd>
        </div>
        <div class="flex items-center justify-between gap-3">
          <dt class="flex items-center gap-2 text-moss-600 dark:text-moss-300">
            <label for="tax_percentage">PPN</label>
            <input id="tax_percentage" name="tax_percentage" type="text" inputmode="decimal" value="<?= e($form['tax_percentage'] ?? '11') ?>"
                   class="input w-20 py-1 text-right tabular-nums<?= $invalid('tax_percentage') ?>" aria-describedby="tax-help">
            <span aria-hidden="true">%</span>
          </dt>
          <dd class="font-semibold tabular-nums" data-tax>0</dd>
        </div>
        <?= $err('tax_percentage') ?>
        <p id="tax-help" class="field-hint !mt-0">Isi 0 bila harga tidak dikenai PPN.</p>
        <div class="flex items-baseline justify-between gap-3 border-t border-moss-200 pt-3 dark:border-moss-700">
          <dt class="font-bold">Total (IDR)</dt>
          <dd class="text-xl font-extrabold tabular-nums text-mes-forest dark:text-mes-lime" data-total>0</dd>
        </div>
      </dl>
      <p class="mt-3 text-xs italic text-moss-600 dark:text-moss-300" data-say aria-live="polite"></p>
    </div>
  </section>

  <!-- Bilah aksi -->
  <div class="sticky bottom-0 z-10 -mx-4 border-t border-moss-200 bg-white/95 px-4 py-3 backdrop-blur dark:border-moss-800 dark:bg-moss-900/95 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-3">
      <p class="text-sm"><span class="text-moss-600 dark:text-moss-300">Total</span>
        <strong class="ml-1 text-lg tabular-nums text-mes-forest dark:text-mes-lime" data-total>0</strong></p>
      <div class="flex gap-2">
        <a href="<?= e(url($isEdit ? '/quotations/' . (int) $quotationId : '/quotations')) ?>" class="btn btn-secondary">Batal</a>
        <button type="submit" class="btn btn-primary" data-submit><?= $isEdit ? 'Simpan perubahan' : 'Simpan quotation' ?></button>
      </div>
    </div>
  </div>
</form>

<script type="application/json" id="customers-data"><?= $customersJson ?></script>
<script src="<?= e(asset('js/quotation.js')) ?>" defer></script>
