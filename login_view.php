<?php
/**
 * Halaman masuk. Variabel: $flash (error, info, success, old[username]).
 */
$old = is_array($flash['old'] ?? null) ? $flash['old'] : [];
require ROOT_PATH . '/src/Views/layouts/auth_top.php';
?>
  <form method="post" action="<?= e(url('/login')) ?>" class="card space-y-4 p-6" autocomplete="on">
    <?= csrf_field() ?>
    <div>
      <h1 class="text-xl font-extrabold tracking-tight">Masuk</h1>
      <p class="mt-1 text-sm text-moss-600 dark:text-moss-300">Gunakan akun yang diberikan administrator.</p>
    </div>
    <div>
      <label for="username" class="field-label">Username</label>
      <input id="username" name="username" type="text" required maxlength="50" autocomplete="username"
             autocapitalize="none" spellcheck="false" autofocus value="<?= e($old['username'] ?? '') ?>" class="input">
    </div>
    <div>
      <label for="password" class="field-label">Password</label>
      <div class="relative">
        <input id="password" name="password" type="password" required maxlength="1024" autocomplete="current-password" class="input pr-20">
        <button type="button" data-toggle-password="password" aria-controls="password" aria-pressed="false"
                class="absolute inset-y-0 right-0 px-3 text-xs font-semibold text-mes-green hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-mes-leaf dark:text-mes-lime">Tampilkan</button>
      </div>
    </div>
    <button type="submit" class="btn btn-primary w-full">Masuk</button>
  </form>
  <p class="mt-4 text-center text-xs text-moss-500 dark:text-moss-400">Lupa password? Hubungi administrator sistem.</p>
<?php require ROOT_PATH . '/src/Views/layouts/auth_bottom.php'; ?>
