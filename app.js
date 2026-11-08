/**
 * MES Quotation: perilaku umum antarmuka.
 * Tema gelap/terang, menu samping di ponsel, tutup pesan, dialog hapus,
 * dan pratinjau file gambar sebelum diunggah.
 */
(() => {
  'use strict';

  const root = document.documentElement;

  // --- Tema gelap/terang (kelas awal dipasang oleh skrip kecil di <head>) ---
  const themeBtn = document.getElementById('theme-toggle');
  if (themeBtn) {
    themeBtn.setAttribute('aria-pressed', String(root.classList.contains('dark')));
    themeBtn.addEventListener('click', () => {
      const dark = root.classList.toggle('dark');
      themeBtn.setAttribute('aria-pressed', String(dark));
      try { localStorage.setItem('mes-theme', dark ? 'dark' : 'light'); } catch (e) { /* penyimpanan diblokir */ }
    });
  }

  // --- Menu samping (laci di layar kecil) ---
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebar-overlay');
  const toggles = document.querySelectorAll('[data-sidebar-toggle]');
  const setSidebar = (open) => {
    if (!sidebar || !overlay) return;
    sidebar.classList.toggle('-translate-x-full', !open);
    overlay.classList.toggle('hidden', !open);
    toggles.forEach((b) => b.setAttribute('aria-expanded', String(open)));
  };
  toggles.forEach((b) => b.addEventListener('click', () => {
    setSidebar(sidebar.classList.contains('-translate-x-full'));
  }));
  overlay?.addEventListener('click', () => setSidebar(false));
  sidebar?.addEventListener('click', (e) => {
    if (e.target.closest('a')) setSidebar(false);
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') setSidebar(false);
  });

  // --- Tampilkan/sembunyikan password ---
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-toggle-password]');
    if (!btn) return;
    const input = document.getElementById(btn.dataset.togglePassword);
    if (!input) return;
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.textContent = show ? 'Sembunyikan' : 'Tampilkan';
    btn.setAttribute('aria-pressed', String(show));
  });

  // --- Tutup pesan notifikasi ---
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-dismiss]');
    if (btn) btn.closest('[data-alert]')?.remove();
  });

  // --- Dialog konfirmasi hapus (form POST + token CSRF ada di footer.php) ---
  const dlg = document.getElementById('delete-dialog');
  if (dlg && typeof dlg.showModal === 'function') {
    const form = dlg.querySelector('form');
    const label = dlg.querySelector('[data-delete-label]');
    document.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-delete-action]');
      if (!btn) return;
      form.action = btn.dataset.deleteAction;
      label.textContent = btn.dataset.deleteLabel || '';
      dlg.showModal();
      dlg.querySelector('[data-dialog-cancel]')?.focus();
    });
    dlg.addEventListener('click', (e) => {
      if (e.target === dlg || e.target.closest('[data-dialog-cancel]')) dlg.close();
    });
  }

  // --- Pratinjau gambar unggahan (hanya PNG/JPG, maksimal 2 MB; server tetap memeriksa ulang) ---
  document.querySelectorAll('input[type="file"][data-preview]').forEach((input) => {
    const img = document.getElementById(input.dataset.preview);
    const msg = document.getElementById(input.dataset.message);
    const check = document.getElementById(input.dataset.remove);
    let url = null;

    input.addEventListener('change', () => {
      const file = input.files && input.files[0];
      if (msg) msg.textContent = '';
      if (!file) return;

      let problem = '';
      if (!/^image\/(png|jpeg)$/.test(file.type)) problem = 'File harus berupa gambar PNG atau JPG.';
      else if (file.size > 2 * 1024 * 1024) problem = 'Ukuran file maksimal 2 MB.';

      if (problem) {
        input.value = '';
        if (msg) msg.textContent = problem;
        return;
      }
      if (url) URL.revokeObjectURL(url);
      url = URL.createObjectURL(file);
      if (img) img.src = url;
      if (check) check.checked = false;
    });
  });
})();
