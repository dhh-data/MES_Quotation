<?php
/**
 * Riwayat quotation: ringkasan status, filter, tabel (kartu di ponsel), paginasi.
 *
 * Variabel: $quotations, $pagination (page, pages, total, per_page),
 * $filters (q, status, date_from, date_to), $summary, $statuses.
 */
$page     = (int) $pagination['page'];
$pages    = (int) $pagination['pages'];
$total    = (int) $pagination['total'];
$hasFilter = implode('', array_map('strval', $filters)) !== '';

/** Query string untuk tautan paginasi, mempertahankan filter. */
$pageUrl = static function (int $p) use ($filters): string {
    $query = array_filter($filters, static fn ($v) => $v !== '' && $v !== null);
    if ($p > 1) {
        $query['page'] = $p;
    }

    return url('/quotations') . ($query ? '?' . http_build_query($query) : '');
};

/**
 * Tombol aksi satu baris: Lihat, Ubah, Cetak, Hapus.
 *
 * @param array<string, mixed> $row
 */
$actions = static function (array $row, string $cls) : void {
    $id = (int) $row['id'];
    ?>
    <a href="<?= e(url("/quotations/{$id}")) ?>" class="btn btn-secondary <?= $cls ?>">Lihat</a>
    <a href="<?= e(url("/quotations/{$id}/edit")) ?>" class="btn btn-secondary <?= $cls ?>">Ubah</a>
    <a href="<?= e(url("/quotations/{$id}/print")) ?>" target="_blank" rel="noopener" class="btn btn-secondary <?= $cls ?>">Cetak</a>
    <button type="button" class="btn btn-danger <?= $cls ?>"
            data-delete-action="<?= e(url("/quotations/{$id}/delete")) ?>"
            data-delete-label="<?= e($row['quotation_number']) ?>">Hapus</button>
    <?php
};
?>
<div class="mb-6 flex flex-wrap items-end justify-between gap-3">
  <div>
    <h1 class="text-2xl font-extrabold tracking-tight">Riwayat Quotation</h1>
    <p class="mt-1 text-sm text-moss-600 dark:text-moss-300"><?= e(number_format($total, 0, ',', '.')) ?> quotation<?= $hasFilter ? ' sesuai filter' : ' tersimpan' ?>.</p>
  </div>
  <a href="<?= e(url('/quotations/create')) ?>" class="btn btn-primary">
    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
    Quotation baru
  </a>
</div>

<!-- Ringkasan status (klik untuk menyaring) -->
<section class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4" aria-label="Ringkasan status">
  <?php foreach ($statuses as $s): ?>
    <?php $info = $summary[$s] ?? ['count' => 0, 'total' => 0.0]; $on = ($filters['status'] ?? '') === $s; ?>
    <a href="<?= e(url('/quotations?' . http_build_query(['status' => $s]))) ?>"
       class="card block p-4 transition hover:border-mes-leaf focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-mes-leaf dark:hover:border-mes-lime<?= $on ? ' ring-2 ring-mes-leaf dark:ring-mes-lime' : '' ?>"<?= $on ? ' aria-current="true"' : '' ?>>
      <?= status_badge($s) ?>
      <p class="mt-3 text-2xl font-extrabold tabular-nums"><?= e(number_format((int) $info['count'], 0, ',', '.')) ?></p>
      <p class="text-xs tabular-nums text-moss-500 dark:text-moss-400">IDR <?= e(format_idr($info['total'])) ?></p>
    </a>
  <?php endforeach; ?>
</section>

<!-- Filter -->
<form method="get" action="<?= e(url('/quotations')) ?>" class="card mb-6 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_auto] lg:items-end" role="search">
  <div>
    <label for="f-q" class="field-label">Cari</label>
    <input id="f-q" name="q" type="search" value="<?= e($filters['q'] ?? '') ?>" class="input" placeholder="Nomor atau nama pelanggan">
  </div>
  <div>
    <label for="f-status" class="field-label">Status</label>
    <select id="f-status" name="status" class="input">
      <option value="">Semua</option>
      <?php foreach ($statuses as $s): ?>
        <option value="<?= e($s) ?>"<?= ($filters['status'] ?? '') === $s ? ' selected' : '' ?>><?= e($s) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label for="f-from" class="field-label">Dari tanggal</label>
    <input id="f-from" name="date_from" type="date" value="<?= e($filters['date_from'] ?? '') ?>" class="input">
  </div>
  <div>
    <label for="f-to" class="field-label">Sampai tanggal</label>
    <input id="f-to" name="date_to" type="date" value="<?= e($filters['date_to'] ?? '') ?>" class="input">
  </div>
  <div class="flex gap-2 sm:col-span-2 lg:col-span-1">
    <button type="submit" class="btn btn-primary flex-1 lg:flex-none">Terapkan</button>
    <?php if ($hasFilter): ?><a href="<?= e(url('/quotations')) ?>" class="btn btn-secondary">Reset</a><?php endif; ?>
  </div>
</form>

<?php if (!$quotations): ?>
  <div class="card px-6 py-14 text-center">
    <p class="text-lg font-bold"><?= $hasFilter ? 'Tidak ada quotation yang cocok' : 'Belum ada quotation' ?></p>
    <p class="mx-auto mt-1 max-w-md text-sm text-moss-600 dark:text-moss-300">
      <?= $hasFilter ? 'Ubah kata kunci, status, atau rentang tanggal, lalu coba lagi.' : 'Quotation yang Anda simpan akan muncul di sini beserta statusnya.' ?>
    </p>
    <a href="<?= e($hasFilter ? url('/quotations') : url('/quotations/create')) ?>" class="btn btn-primary mt-5"><?= $hasFilter ? 'Hapus filter' : 'Buat quotation pertama' ?></a>
  </div>
<?php else: ?>

  <!-- Tabel (layar menengah ke atas) -->
  <div class="card hidden overflow-hidden md:block">
    <div class="overflow-x-auto">
      <table class="w-full min-w-[56rem] text-left text-sm">
        <caption class="sr-only">Daftar quotation</caption>
        <thead class="border-b border-moss-200 bg-moss-100 text-xs font-bold uppercase tracking-wide text-moss-600 dark:border-moss-700 dark:bg-moss-800 dark:text-moss-300">
          <tr>
            <th scope="col" class="px-4 py-3">No. Quotation</th>
            <th scope="col" class="px-4 py-3">Pelanggan</th>
            <th scope="col" class="px-4 py-3">Tanggal</th>
            <th scope="col" class="px-4 py-3">Berlaku s/d</th>
            <th scope="col" class="px-4 py-3 text-right">Total (IDR)</th>
            <th scope="col" class="px-4 py-3">Status</th>
            <th scope="col" class="px-4 py-3 text-right">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-moss-100 dark:divide-moss-800">
          <?php foreach ($quotations as $row): ?>
            <tr class="hover:bg-moss-50 dark:hover:bg-moss-800/50">
              <td class="whitespace-nowrap px-4 py-3 font-mono text-[13px] font-medium">
                <a href="<?= e(url('/quotations/' . (int) $row['id'])) ?>" class="text-mes-green hover:underline dark:text-mes-lime"><?= e($row['quotation_number']) ?></a>
              </td>
              <td class="px-4 py-3">
                <p class="font-semibold"><?= e($row['customer_company'] ?: $row['customer_name']) ?></p>
                <?php if ($row['customer_company']): ?><p class="text-xs text-moss-500 dark:text-moss-400"><?= e($row['customer_name']) ?></p><?php endif; ?>
              </td>
              <td class="whitespace-nowrap px-4 py-3"><?= e(date_id($row['quotation_date'])) ?></td>
              <td class="whitespace-nowrap px-4 py-3"><?= e(date_id($row['valid_until'])) ?></td>
              <td class="whitespace-nowrap px-4 py-3 text-right font-semibold tabular-nums"><?= e(format_idr($row['total_amount'])) ?></td>
              <td class="px-4 py-3"><?= status_badge((string) $row['status']) ?></td>
              <td class="px-4 py-3"><div class="flex justify-end gap-1.5"><?php $actions($row, 'btn-sm'); ?></div></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Kartu (ponsel) -->
  <ul class="space-y-3 md:hidden">
    <?php foreach ($quotations as $row): ?>
      <li class="card p-4">
        <div class="flex items-start justify-between gap-3">
          <a href="<?= e(url('/quotations/' . (int) $row['id'])) ?>" class="font-mono text-sm font-medium text-mes-green hover:underline dark:text-mes-lime"><?= e($row['quotation_number']) ?></a>
          <?= status_badge((string) $row['status']) ?>
        </div>
        <p class="mt-2 font-semibold"><?= e($row['customer_company'] ?: $row['customer_name']) ?></p>
        <p class="text-xs text-moss-500 dark:text-moss-400"><?= e(date_id($row['quotation_date'])) ?> &middot; berlaku s/d <?= e(date_id($row['valid_until'])) ?></p>
        <p class="mt-2 text-lg font-extrabold tabular-nums">IDR <?= e(format_idr($row['total_amount'])) ?></p>
        <div class="mt-3 grid grid-cols-4 gap-1.5"><?php $actions($row, 'btn-sm !px-1'); ?></div>
      </li>
    <?php endforeach; ?>
  </ul>

  <?php if ($pages > 1): ?>
    <nav class="mt-6 flex flex-wrap items-center justify-between gap-3" aria-label="Halaman">
      <p class="text-sm text-moss-600 dark:text-moss-300">Halaman <?= $page ?> dari <?= $pages ?></p>
      <ul class="flex flex-wrap gap-1.5">
        <li><a href="<?= e($pageUrl(max(1, $page - 1))) ?>" class="btn btn-secondary btn-sm<?= $page <= 1 ? ' pointer-events-none opacity-40' : '' ?>"<?= $page <= 1 ? ' aria-disabled="true" tabindex="-1"' : '' ?>>Sebelumnya</a></li>
        <?php for ($p = max(1, $page - 2); $p <= min($pages, $page + 2); $p++): ?>
          <li><a href="<?= e($pageUrl($p)) ?>" class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-secondary' ?>"<?= $p === $page ? ' aria-current="page"' : '' ?>><?= $p ?></a></li>
        <?php endfor; ?>
        <li><a href="<?= e($pageUrl(min($pages, $page + 1))) ?>" class="btn btn-secondary btn-sm<?= $page >= $pages ? ' pointer-events-none opacity-40' : '' ?>"<?= $page >= $pages ? ' aria-disabled="true" tabindex="-1"' : '' ?>>Berikutnya</a></li>
      </ul>
    </nav>
  <?php endif; ?>
<?php endif; ?>
