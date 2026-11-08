<?php
/**
 * Layout: menu navigasi samping, pembuka area konten, dan pesan flash.
 *
 * Variabel: $activeMenu (create | list | settings), $flash (success, error, info).
 * Di layar kecil menu berupa laci yang dibuka tombol di navbar (app.js).
 */
$active = (string) ($activeMenu ?? '');
$nav = [
    'create'   => ['Buat Quotation Baru', '/quotations/create', 'M12 5v14M5 12h14'],
    'list'     => ['Riwayat Quotation', '/quotations', 'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01'],
    'settings' => ['Pengaturan Perusahaan', '/settings', 'M4 21V5a1 1 0 011-1h8a1 1 0 011 1v16M14 9h5a1 1 0 011 1v11M3 21h18M8 8h2M8 12h2M8 16h2'],
];
if (!\App\Core\Auth::isAdmin()) {
    unset($nav['settings']); // Pengaturan perusahaan hanya untuk admin
}
$alerts = [
    'success' => ['border-green-300 bg-green-50 text-green-900 dark:border-green-800 dark:bg-green-950/60 dark:text-green-100', 'alert'],
    'error'   => ['border-red-300 bg-red-50 text-red-900 dark:border-red-800 dark:bg-red-950/60 dark:text-red-100', 'alert'],
    'info'    => ['border-sky-300 bg-sky-50 text-sky-900 dark:border-sky-800 dark:bg-sky-950/60 dark:text-sky-100', 'status'],
];
?>
<div id="sidebar-overlay" class="fixed inset-0 z-40 hidden bg-moss-950/50 lg:hidden print:hidden" aria-hidden="true"></div>

<aside id="sidebar" aria-label="Menu utama"
       class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col border-r border-moss-200 bg-white px-3 pb-4 pt-4 transition-transform duration-200 dark:border-moss-800 dark:bg-moss-900
              lg:sticky lg:top-14 lg:z-20 lg:h-[calc(100vh-3.5rem)] lg:shrink-0 lg:translate-x-0 print:hidden">
  <div class="mb-3 flex items-center justify-between px-3 lg:hidden">
    <span class="text-sm font-extrabold text-mes-forest dark:text-mes-lime"><?= e(APP_NAME) ?></span>
    <button type="button" class="btn-icon" data-sidebar-toggle aria-label="Tutup menu">
      <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
    </button>
  </div>

  <p class="px-3 pb-2 text-xs font-bold uppercase tracking-wider text-moss-500 dark:text-moss-400">Modul</p>
  <nav class="flex flex-col gap-1">
    <?php foreach ($nav as $key => [$label, $path, $icon]): ?>
      <a href="<?= e(url($path)) ?>" class="nav-link"<?= $active === $key ? ' aria-current="page"' : '' ?>>
        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="<?= e($icon) ?>"/></svg>
        <?= e($label) ?>
      </a>
    <?php endforeach; ?>
  </nav>

  <div class="mt-auto rounded-lg bg-moss-100 p-3 text-xs leading-relaxed text-moss-600 dark:bg-moss-800 dark:text-moss-300">
    Total quotation dihitung ulang oleh server saat disimpan, jadi angka di dokumen selalu konsisten.
  </div>
</aside>

<div class="flex min-h-[calc(100vh-3.5rem)] min-w-0 flex-1 flex-col print:block print:min-h-0">
<main id="main" class="mx-auto w-full min-w-0 max-w-6xl flex-1 px-4 py-6 sm:px-6 lg:px-8 print:max-w-none print:p-0">
<?php foreach ($alerts as $type => [$classes, $role]): ?>
  <?php if (!empty($flash[$type]) && is_string($flash[$type])): ?>
    <div role="<?= $role ?>" data-alert class="mb-5 flex items-start gap-3 rounded-lg border px-4 py-3 text-sm font-medium print:hidden <?= $classes ?>">
      <p class="min-w-0 flex-1"><?= e($flash[$type]) ?></p>
      <button type="button" data-dismiss class="-m-1 rounded p-1 opacity-70 hover:opacity-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-current" aria-label="Tutup pesan">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
      </button>
    </div>
  <?php endif; ?>
<?php endforeach; ?>
