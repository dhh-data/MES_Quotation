<?php
/**
 * Ganti password. Variabel: $forced (wajib ganti setelah login pertama),
 * $minLength, $flash (errors[...]).
 */
$errors = is_array($flash['errors'] ?? null) ? $flash['errors'] : [];
$err = static fn (string $k): string => isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
$bad = static fn (string $k): string => isset($errors[$k]) ? ' input-error' : '';
require ROOT_PATH . '/src/Views/layouts/auth_top.php';
?>
  <form method="post" action="<?= e(url('/password')) ?>" class="card space-y-4 p-6" autocomplete="off">
    <?= csrf_field() ?>
    <div>
      <h1 class="text-xl font-extrabold tracking-tight">Ubah password</h1>
      <p class="mt-1 text-sm text-moss-600 dark:text-moss-300">
        <?= $forced ? 'Password Anda masih sementara. Buat password baru sebelum melanjutkan.' : 'Gunakan password yang tidak dipakai di layanan lain.' ?>
      </p>
    </div>
    <div>
      <label for="current_password" class="field-label">Password saat ini</label>
      <input id="current_password" name="current_password" type="password" required maxlength="1024" autocomplete="current-password" class="input<?= $bad('current_password') ?>">
      <?= $err('current_password') ?>
    </div>
    <div>
      <label for="new_password" class="field-label">Password baru</label>
      <input id="new_password" name="new_password" type="password" required maxlength="72" minlength="<?= (int) $minLength ?>" autocomplete="new-password" class="input<?= $bad('new_password') ?>">
      <?= $err('new_password') ?>
      <p class="field-hint">Minimal <?= (int) $minLength ?> karakter, memuat huruf dan angka, tidak memuat username.</p>
    </div>
    <div>
      <label for="confirm_password" class="field-label">Ulangi password baru</label>
      <input id="confirm_password" name="confirm_password" type="password" required maxlength="72" autocomplete="new-password" class="input<?= $bad('confirm_password') ?>">
      <?= $err('confirm_password') ?>
    </div>
    <div class="flex gap-2">
      <?php if (!$forced): ?><a href="<?= e(url('/quotations')) ?>" class="btn btn-secondary">Batal</a><?php endif; ?>
      <button type="submit" class="btn btn-primary flex-1">Simpan password</button>
    </div>
  </form>
  <?php if ($forced): ?>
    <form method="post" action="<?= e(url('/logout')) ?>" class="mt-4 text-center">
      <?= csrf_field() ?>
      <button type="submit" class="text-xs font-semibold text-moss-500 hover:underline dark:text-moss-400">Keluar</button>
    </form>
  <?php endif; ?>
<?php require ROOT_PATH . '/src/Views/layouts/auth_bottom.php'; ?>
