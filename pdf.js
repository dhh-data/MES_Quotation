/**
 * MES Quotation: penanganan lembar A4 di layar dan cetak.
 *  - Menskalakan lembar 210 mm agar muat di layar yang lebih sempit.
 *  - Tombol [data-print] membuka dialog cetak browser ("Save as PDF" untuk berkas PDF).
 *  - Halaman /quotations/{id}/print (body[data-autoprint="1"]) langsung membuka dialog cetak.
 * Saat mencetak, skala dibuang oleh @media print di sheet.css.
 */
(() => {
  'use strict';

  const stage = document.querySelector('[data-sheet-stage]');
  if (stage) {
    const scaleEl = stage.querySelector('[data-sheet-scale]');
    const sheet = stage.querySelector('.sheet');

    const fit = () => {
      if (!scaleEl || !sheet) return;
      const natural = sheet.offsetWidth;
      const scale = Math.min(1, (stage.clientWidth - 4) / natural);
      if (scale < 1) {
        scaleEl.style.transform = `scale(${scale})`;
        stage.style.height = `${Math.ceil(sheet.offsetHeight * scale) + 4}px`;
      } else {
        scaleEl.style.transform = '';
        stage.style.height = '';
      }
    };

    fit();
    window.addEventListener('resize', fit);
    window.addEventListener('load', fit);
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(fit);
    if (window.ResizeObserver && stage.parentElement) new ResizeObserver(fit).observe(stage.parentElement);
  }

  document.addEventListener('click', (e) => {
    if (e.target.closest('[data-print]')) {
      e.preventDefault();
      window.print();
    }
  });

  if (document.body.dataset.autoprint === '1') {
    window.addEventListener('load', () => setTimeout(() => window.print(), 400));
  }
})();
