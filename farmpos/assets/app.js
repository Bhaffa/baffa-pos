/* ═══════════════════════════════════════════════════════
   Baffa Precision Agri-Tech — App JavaScript
   ═══════════════════════════════════════════════════════ */

// ─── FLASH AUTO DISMISS ───────────────────────────────────
document.querySelectorAll('.flash').forEach(el => {
  setTimeout(() => el.style.opacity = '0', 4000);
  setTimeout(() => el.remove(), 4400);
});

// ─── MODAL HELPERS ───────────────────────────────────────
window.openModal  = id => document.getElementById(id)?.classList.add('open');
window.closeModal = id => document.getElementById(id)?.classList.remove('open');

// Close modal on overlay click
document.querySelectorAll('.modal-overlay').forEach(ov => {
  ov.addEventListener('click', e => { if (e.target === ov) ov.classList.remove('open'); });
});

// Close on Escape
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') document.querySelectorAll('.modal-overlay.open').forEach(m => m.classList.remove('open'));
});

// ─── CONFIRM DIALOGS ─────────────────────────────────────
document.querySelectorAll('[data-confirm]').forEach(el => {
  el.addEventListener('click', e => {
    if (!confirm(el.dataset.confirm || 'Are you sure?')) e.preventDefault();
  });
});

// ─── SORTABLE TABLE HEADERS ──────────────────────────────
document.querySelectorAll('thead th[data-sort]').forEach(th => {
  th.style.cursor = 'pointer';
  th.addEventListener('click', () => {
    const table = th.closest('table');
    const tbody = table.querySelector('tbody');
    const idx   = +th.dataset.sort;
    const asc   = th.dataset.dir !== 'asc';
    th.dataset.dir = asc ? 'asc' : 'desc';
    const rows = [...tbody.querySelectorAll('tr')];
    rows.sort((a, b) => {
      const av = a.cells[idx]?.innerText.replace(/[₦,]/g,'') || '';
      const bv = b.cells[idx]?.innerText.replace(/[₦,]/g,'') || '';
      const an = parseFloat(av), bn = parseFloat(bv);
      if (!isNaN(an) && !isNaN(bn)) return asc ? an-bn : bn-an;
      return asc ? av.localeCompare(bv) : bv.localeCompare(av);
    });
    rows.forEach(r => tbody.appendChild(r));
  });
});

// ─── LIVE TABLE SEARCH ────────────────────────────────────
const searchInput = document.getElementById('tableSearch');
if (searchInput) {
  searchInput.addEventListener('input', () => {
    const q = searchInput.value.toLowerCase();
    document.querySelectorAll('tbody tr').forEach(tr => {
      tr.style.display = tr.innerText.toLowerCase().includes(q) ? '' : 'none';
    });
  });
}

// ─── CSV EXPORT ──────────────────────────────────────────
window.exportCSV = (tableId, filename = 'export.csv') => {
  const table = document.getElementById(tableId);
  if (!table) return;
  const rows = [...table.querySelectorAll('tr')];
  const csv  = rows.map(r =>
    [...r.querySelectorAll('th,td')]
      .map(c => '"' + c.innerText.replace(/"/g,'""') + '"')
      .join(',')
  ).join('\n');
  const a = document.createElement('a');
  a.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
  a.download = filename;
  a.click();
};

// ─── NUMBER FORMAT ────────────────────────────────────────
window.fmt = (n, sym = '₦') => sym + parseFloat(n||0).toLocaleString('en-NG', {minimumFractionDigits:2, maximumFractionDigits:2});

// ─── PRINT RECEIPT ───────────────────────────────────────
window.printReceipt = id => {
  const el = document.getElementById(id);
  if (!el) return;
  const w = window.open('', '_blank', 'width=380,height=600');
  w.document.write('<html><head><title>Receipt</title><style>body{margin:0;padding:16px;font-family:monospace;font-size:12px}@media print{@page{margin:0}}</style></head><body>' + el.innerHTML + '</body></html>');
  w.document.close();
  setTimeout(() => { w.print(); w.close(); }, 300);
};

// ─── KEYBOARD SHORTCUT (POS) ─────────────────────────────
document.addEventListener('keydown', e => {
  if (e.altKey) {
    if (e.key === 'p' || e.key === 'P') { e.preventDefault(); document.getElementById('pos-search')?.focus(); }
    if (e.key === 'c' || e.key === 'C') { e.preventDefault(); document.querySelector('.btn-checkout')?.click(); }
    if (e.key === 'h' || e.key === 'H') { e.preventDefault(); document.querySelector('.btn-hold')?.click(); }
  }
});
