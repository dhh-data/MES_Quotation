<?php
/**
 * Layout: penutup area konten, footer aplikasi, dialog hapus bersama, dan skrip umum.
 * Menutup elemen yang dibuka header.php dan sidebar.php.
 */
?>
</main>

<footer class="border-t border-moss-200 px-4 py-4 text-xs text-moss-500 dark:border-moss-800 dark:text-moss-400 sm:px-6 lg:px-8 print:hidden">
  <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-2">
    <p>&copy; <?= e(date('Y')) ?> <?= e(APP_NAME) ?>. Sistem penawaran harga internal.</p>
    <p>Zona waktu <?= e(date_default_timezone_get()) ?></p>
  </div>
</footer>
</div><!-- /konten -->
</div><!-- /lg:flex -->

<dialog id="delete-dialog" aria-labelledby="delete-title"
        class="w-[calc(100%-2rem)] max-w-md rounded-xl border border-moss-200 bg-white p-0 text-moss-900 shadow-xl backdrop:bg-moss-950/60 dark:border-moss-700 dark:bg-moss-900 dark:text-moss-100 print:hidden">
  <form method="post" action="" class="p-6">
    <?= csrf_field() ?>
    <h2 id="delete-title" class="text-lg font-bold">Hapus quotation?</h2>
    <p class="mt-2 text-sm text-moss-600 dark:text-moss-300">
      <strong data-delete-label class="font-mono"></strong> beserta seluruh itemnya akan dihapus permanen dan tidak bisa dikembalikan.
    </p>
    <div class="mt-6 flex justify-end gap-2">
      <button type="button" class="btn btn-secondary" data-dialog-cancel>Batal</button>
      <button type="submit" class="btn btn-danger-solid">Hapus permanen</button>
    </div>
  </form>
</dialog>

<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
