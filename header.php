<?php
/**
 * Layout: pembuka dokumen HTML dan navbar atas.
 *
 * Variabel: $pageTitle, $settings (opsional; bila tidak dikirim controller,
 * profil perusahaan dimuat di sini untuk informasi singkat di navbar).
 *
 * Susunan layout (dibuka di sini, ditutup di footer.php):
 *   header.php  : <html><body> + navbar + <div class="lg:flex">
 *   sidebar.php : <aside> + <div konten><main> + pesan flash
 *   footer.php  : </main><footer></div></div> + dialog hapus + skrip
 */
if (!isset($settings) || !is_array($settings)) {
    try {
        $settings = (new \App\Models\SettingsModel())->get();
    } catch (\Throwable $e) {
        $settings = [];
    }
}
$authUser = \App\Core\Auth::user();
$brandName = (string) ($settings['company_name'] ?? APP_NAME);
$brandLogo = uploaded_image_src($settings['company_logo'] ?? null) ?? uploaded_image_src('assets/img/logo-mes.png');
$brandContact = array_values(array_filter([
    (string) ($settings['company_phone'] ?? ''),
    (string) ($settings['company_email'] ?? ''),
]));
?><!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<title><?= e($pageTitle ?? APP_NAME) ?> | <?= e(APP_NAME) ?></title>
<link rel="icon" type="image/png" href="<?= e(asset('img/logo-mes.png')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@500&amp;family=Plus+Jakarta+Sans:wght@400;500;600;700;800&amp;display=swap">
<link rel="stylesheet" href="<?= e(asset('css/tailwind.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/sheet.css')) ?>">
<script>
/* Terapkan tema tersimpan sebelum halaman digambar agar tidak berkedip. */
(function () {
  try {
    var t = localStorage.getItem('mes-theme');
    if (t === 'dark' || (t !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
      document.documentElement.classList.add('dark');
    }
  } catch (e) {}
})();
</script>
</head>
<body class="min-h-screen bg-moss-50 text-moss-900 antialiased dark:bg-moss-950 dark:text-moss-100 print:bg-white">
<a href="#main" class="sr-only z-50 rounded-lg bg-mes-forest px-4 py-2 text-sm font-semibold text-white focus:not-sr-only focus:fixed focus:left-4 focus:top-4 print:hidden">Lewati ke konten</a>

<header class="sticky top-0 z-30 h-14 border-b border-moss-200 bg-white/90 backdrop-blur dark:border-moss-800 dark:bg-moss-900/90 print:hidden">
  <div class="flex h-full items-center gap-3 px-4 sm:px-6">
    <button type="button" class="btn-icon lg:hidden" data-sidebar-toggle aria-controls="sidebar" aria-expanded="false" aria-label="Buka menu">
      <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
    </button>

    <a href="<?= e(url('/quotations/create')) ?>" class="flex min-w-0 items-center gap-2.5 rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-mes-leaf">
      <?php if ($brandLogo): ?><img src="<?= e($brandLogo) ?>" alt="" class="h-8 w-auto"><?php endif; ?>
      <span class="truncate text-sm font-extrabold tracking-wide text-mes-forest dark:text-mes-lime"><?= e(APP_NAME) ?></span>
    </a>

    <div class="ml-auto flex items-center gap-3">
      <div class="hidden max-w-xs text-right leading-tight md:block">
        <p class="truncate text-sm font-semibold"><?= e($brandName) ?></p>
        <?php if ($brandContact): ?>
          <p class="truncate text-xs text-moss-500 dark:text-moss-400"><?= e(implode('  |  ', $brandContact)) ?></p>
        <?php endif; ?>
      </div>
      <button type="button" id="theme-toggle" class="btn-icon" aria-label="Ganti tema gelap atau terang" aria-pressed="false" title="Ganti tema">
        <svg class="h-5 w-5 dark:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z"/></svg>
        <svg class="hidden h-5 w-5 dark:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
      </button>
      <?php if ($authUser): ?>
        <div class="flex items-center gap-1.5 border-l border-moss-200 pl-3 dark:border-moss-700">
          <div class="hidden text-right leading-tight sm:block">
            <p class="max-w-[10rem] truncate text-sm font-semibold"><?= e($authUser['full_name']) ?></p>
            <p class="text-[11px] font-bold uppercase tracking-wider text-mes-green dark:text-mes-lime"><?= e($authUser['role']) ?></p>
          </div>
          <a href="<?= e(url('/password')) ?>" class="btn-icon" aria-label="Ubah password" title="Ubah password">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="8" cy="15" r="4"/><path d="M10.8 12.2L20 3M17 6l3 3M14 9l2 2"/></svg>
          </a>
          <form method="post" action="<?= e(url('/logout')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn-icon" aria-label="Keluar" title="Keluar">
              <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
            </button>
          </form>
        </div>
      <?php endif; ?>
    </div>
  </div>
  <div class="absolute inset-x-0 -bottom-[3px] h-[3px] bg-gradient-to-r from-mes-forest via-mes-leaf to-mes-yellow" aria-hidden="true"></div>
</header>

<div class="lg:flex print:block">
