<?php
/**
 * Pembuka halaman mandiri tanpa menu (login, ganti password wajib).
 * Variabel: $pageTitle, $flash.
 */
$alerts = [
    'success' => ['border-green-300 bg-green-50 text-green-900 dark:border-green-800 dark:bg-green-950/60 dark:text-green-100', 'status'],
    'error'   => ['border-red-300 bg-red-50 text-red-900 dark:border-red-800 dark:bg-red-950/60 dark:text-red-100', 'alert'],
    'info'    => ['border-sky-300 bg-sky-50 text-sky-900 dark:border-sky-800 dark:bg-sky-950/60 dark:text-sky-100', 'status'],
];
?><!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<meta name="robots" content="noindex, nofollow">
<title><?= e($pageTitle ?? APP_NAME) ?> | <?= e(APP_NAME) ?></title>
<link rel="icon" type="image/png" href="<?= e(asset('img/logo-mes.png')) ?>">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&amp;display=swap">
<link rel="stylesheet" href="<?= e(asset('css/tailwind.css')) ?>">
<script>
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
<body class="min-h-screen bg-moss-50 text-moss-900 antialiased dark:bg-moss-950 dark:text-moss-100">
<div class="h-1.5 bg-gradient-to-r from-mes-forest via-mes-leaf to-mes-yellow" aria-hidden="true"></div>
<main class="mx-auto flex min-h-[calc(100vh-6px)] w-full max-w-md flex-col justify-center px-4 py-10">
  <div class="mb-6 flex flex-col items-center text-center">
    <img src="<?= e(asset('img/logo-mes.png')) ?>" alt="" class="h-14 w-auto">
    <p class="mt-3 text-sm font-extrabold uppercase tracking-widest text-mes-forest dark:text-mes-lime"><?= e(APP_NAME) ?></p>
  </div>
<?php foreach ($alerts as $type => [$classes, $role]): ?>
  <?php if (!empty($flash[$type]) && is_string($flash[$type])): ?>
    <div role="<?= $role ?>" class="mb-4 rounded-lg border px-4 py-3 text-sm font-medium <?= $classes ?>"><?= e($flash[$type]) ?></div>
  <?php endif; ?>
<?php endforeach; ?>
