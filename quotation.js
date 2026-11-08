/**
 * MES Quotation: formulir quotation interaktif.
 *  - Tambah/hapus baris item secara langsung.
 *  - Sub total per baris, PPN, total, dan terbilang dihitung seketika.
 *  - Memilih pelanggan tersimpan atau mengisi pelanggan baru.
 *  - Tombol cepat masa berlaku (+7/+14/+30 hari).
 *
 * Angka di layar hanya perkiraan: server selalu menghitung ulang total
 * dari jumlah dan harga satuan sebelum menyimpan.
 */
(() => {
  'use strict';

  const form = document.getElementById('quotation-form');
  if (!form) return;

  const rowsEl = document.getElementById('item-rows');
  const tpl = document.getElementById('item-template');
  const taxInput = document.getElementById('tax_percentage');

  // ------------------------------------------------------------------
  // Angka: aturan baca sama dengan Controller::decimal() di server
  // ------------------------------------------------------------------
  const nf0 = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
  const nf2 = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const fmt = (n) => (Math.abs(n - Math.round(n)) < 0.005 ? nf0.format(Math.round(n)) : nf2.format(n));
  const round2 = (n) => Math.round((n + Number.EPSILON) * 100) / 100;

  /** "1.500.000,50" -> 1500000.5, "1500.5" -> 1500.5, tidak valid -> null */
  function parseDecimal(raw) {
    let s = String(raw ?? '').replace(/rp|idr/gi, '').replace(/\s+/g, '');
    if (s === '') return null;
    if (/^\d{1,3}(\.\d{3})+,\d+$|^\d{1,3}(\.\d{3}){2,}$/.test(s)) s = s.replace(/\./g, '').replace(',', '.');
    else if (/^\d+,\d+$/.test(s)) s = s.replace(',', '.');
    else if (!/^\d+(\.\d+)?$/.test(s)) return null;
    const v = Number(s);
    return Number.isFinite(v) && v < 1e13 ? v : null;
  }

  // ------------------------------------------------------------------
  // Terbilang bahasa Indonesia
  // ------------------------------------------------------------------
  const ONES = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];
  function words(n) {
    if (n < 12) return ONES[n];
    if (n < 20) return `${words(n - 10)} belas`;
    if (n < 100) return `${words(Math.floor(n / 10))} puluh${n % 10 ? ` ${words(n % 10)}` : ''}`;
    if (n < 200) return `seratus${n - 100 ? ` ${words(n - 100)}` : ''}`;
    if (n < 1e3) return `${words(Math.floor(n / 100))} ratus${n % 100 ? ` ${words(n % 100)}` : ''}`;
    if (n < 2e3) return `seribu${n - 1e3 ? ` ${words(n - 1e3)}` : ''}`;
    const units = [[1e12, 'triliun'], [1e9, 'miliar'], [1e6, 'juta'], [1e3, 'ribu']];
    for (const [size, name] of units) {
      if (n >= size) {
        const rest = n % size;
        return `${words(Math.floor(n / size))} ${name}${rest ? ` ${words(rest)}` : ''}`;
      }
    }
    return String(n);
  }
  function terbilang(n) {
    n = Math.round(n);
    if (!Number.isFinite(n) || n === 0) return 'Nol rupiah';
    if (n >= 1e15) return '';
    const text = `${words(n)} rupiah`;
    return text.charAt(0).toUpperCase() + text.slice(1);
  }

  // ------------------------------------------------------------------
  // Baris item
  // ------------------------------------------------------------------
  const rows = () => Array.from(rowsEl.querySelectorAll('[data-item-row]'));

  function reindex() {
    const list = rows();
    list.forEach((row, i) => {
      row.querySelectorAll('[name]').forEach((el) => {
        el.name = el.name.replace(/^items\[[^\]]*\]/, `items[${i}]`);
      });
      row.querySelectorAll('[data-row-no]').forEach((n) => { n.textContent = String(i + 1); });
    });
    rowsEl.querySelectorAll('[data-remove-row]').forEach((b) => { b.disabled = list.length === 1; });
  }

  function addRow(focus = true) {
    const row = tpl.content.firstElementChild.cloneNode(true);
    rowsEl.appendChild(row);
    reindex();
    recalc();
    if (focus) row.querySelector('[data-field="description"]').focus();
    return row;
  }

  function removeRow(row) {
    if (rows().length <= 1) return;
    row.remove();
    reindex();
    recalc();
  }

  /** Peringatan bila "1.500" dibaca sebagai 1,5 (titik = desimal) padahal mungkin maksudnya 1500. */
  function ambiguityHint(input) {
    const hint = input.closest('[data-cell]')?.querySelector('[data-ambig]');
    if (!hint) return;
    const raw = input.value.trim();
    if (/^\d{1,3}\.\d{3}$/.test(raw)) {
      hint.textContent = `Dibaca ${fmt(parseDecimal(raw))}. Ketik ${raw.replace('.', '')} bila maksud Anda ribuan.`;
      hint.hidden = false;
    } else {
      hint.hidden = true;
    }
  }

  // ------------------------------------------------------------------
  // Perhitungan
  // ------------------------------------------------------------------
  function recalc() {
    let subtotal = 0;
    rows().forEach((row) => {
      const qty = round2(parseDecimal(row.querySelector('[data-field="quantity"]').value) ?? 0);
      const price = round2(parseDecimal(row.querySelector('[data-field="unit_price"]').value) ?? 0);
      const line = round2(qty * price);
      subtotal += line;
      row.querySelector('[data-line-total]').textContent = line > 0 ? fmt(line) : '0';
    });
    subtotal = round2(subtotal);

    let pct = parseDecimal(taxInput.value);
    if (pct === null || pct < 0 || pct > 100) pct = 0;
    const tax = round2((subtotal * pct) / 100);
    const total = round2(subtotal + tax);

    document.querySelectorAll('[data-subtotal]').forEach((el) => { el.textContent = fmt(subtotal); });
    document.querySelectorAll('[data-tax]').forEach((el) => { el.textContent = fmt(tax); });
    document.querySelectorAll('[data-total]').forEach((el) => { el.textContent = fmt(total); });
    document.querySelectorAll('[data-say]').forEach((el) => { el.textContent = terbilang(total); });
  }

  rowsEl.addEventListener('input', (e) => {
    const t = e.target;
    if (t.matches('[data-field="quantity"], [data-field="unit_price"]')) ambiguityHint(t);
    if (t.classList.contains('input-error')) {
      t.classList.remove('input-error');
      t.closest('[data-cell]')?.querySelector('[data-server-error]')?.remove();
    }
    recalc();
  });

  rowsEl.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-remove-row]');
    if (btn) removeRow(btn.closest('[data-item-row]'));
  });

  // Enter di kolom harga: pindah ke baris berikutnya (atau tambah baris) tanpa mengirim form.
  rowsEl.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter' || !e.target.matches('input[data-field]')) return;
    e.preventDefault();
    const list = rows();
    const row = e.target.closest('[data-item-row]');
    const next = list[list.indexOf(row) + 1];
    if (e.target.matches('[data-field="unit_price"]')) {
      (next ?? addRow(false)).querySelector('[data-field="description"]').focus();
    }
  });

  document.getElementById('add-row').addEventListener('click', () => addRow());
  taxInput.addEventListener('input', recalc);

  // ------------------------------------------------------------------
  // Pelanggan
  // ------------------------------------------------------------------
  const select = document.getElementById('customer_id');
  const newBox = document.getElementById('customer-new');
  const existingBox = document.getElementById('customer-existing');
  let customers = [];
  try { customers = JSON.parse(document.getElementById('customers-data').textContent || '[]'); } catch (e) { customers = []; }
  const byId = new Map(customers.map((c) => [String(c.id), c]));

  function syncCustomer() {
    const c = byId.get(select.value);
    newBox.hidden = Boolean(c);
    existingBox.hidden = !c;
    if (!c) return;
    const set = (key, value) => {
      const el = existingBox.querySelector(`[data-c="${key}"]`);
      if (!el) return;
      el.textContent = value || '-';
    };
    set('company', c.company_name || c.name);
    set('name', c.company_name ? c.name : '');
    set('address', c.address);
    set('email', c.email);
    set('phone', c.phone);
  }
  select.addEventListener('change', syncCustomer);
  syncCustomer();

  // ------------------------------------------------------------------
  // Tanggal: tombol cepat masa berlaku
  // ------------------------------------------------------------------
  const dateInput = document.getElementById('quotation_date');
  const validInput = document.getElementById('valid_until');
  const toISO = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  const fromISO = (s) => {
    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s);
    return m ? new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3])) : null;
  };
  document.querySelectorAll('[data-valid-days]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const d = fromISO(dateInput.value) ?? new Date();
      d.setDate(d.getDate() + Number(btn.dataset.validDays));
      validInput.value = toISO(d);
      validInput.classList.remove('input-error');
    });
  });

  // ------------------------------------------------------------------
  // Kirim form: indeks baris dirapikan, tombol dikunci agar tidak terkirim dua kali
  // ------------------------------------------------------------------
  const submitBtn = form.querySelector('[data-submit]');
  const submitLabel = submitBtn ? submitBtn.textContent : '';
  form.addEventListener('submit', () => {
    reindex();
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = 'Menyimpan...';
    }
  });
  window.addEventListener('pageshow', (e) => {
    if (e.persisted && submitBtn) {
      submitBtn.disabled = false;
      submitBtn.textContent = submitLabel;
    }
  });

  rows().forEach((row) => row.querySelectorAll('[data-field="quantity"], [data-field="unit_price"]').forEach(ambiguityHint));
  reindex();
  recalc();
})();
