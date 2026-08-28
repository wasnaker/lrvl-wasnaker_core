#!/usr/bin/env bash
# ============================================================
# post-scribe-inject.sh — inject toggle collapse grup sidebar
# ------------------------------------------------------------
# Scribe v4 (tema default) TIDAK punya klik-untuk-collapse pada
# heading grup; buka/tutup hanya oleh scrollspy (grup aktif selalu
# terbuka). Dengan satu grup top-level (api/v1) sidebar terkesan
# "permanen terbuka".
#
# Script ini menambahkan JS kecil ke index.html hasil
# `php artisan scribe:generate --force` sehingga heading grup
# (level-1) bisa diklik untuk expand/collapse, dan state collapse
# dipertahankan walau scrollspy mencoba membukanya lagi.
#
# PAKAI: jalankan SETELAH setiap scribe:generate:
#   bash scripts/post-scribe-inject.sh
# (idempoten — aman dijalankan berkali-kali)
# ============================================================
set -euo pipefail

HTML=/www/wwwroot/apidocs.wasnaker.lan/public/index.html
MARK='wasnaker-sidebar-toggle'

if [ ! -f "$HTML" ]; then
  echo "ERROR: $HTML tidak ada — jalankan scribe:generate dulu." >&2
  exit 1
fi

if grep -q "$MARK" "$HTML"; then
  echo "inject: sudah ada ($MARK), skip."
  exit 0
fi

python3 - "$HTML" <<'PYEOF'
import sys

path = sys.argv[1]
html = open(path).read()

script = """
<script id="wasnaker-sidebar-toggle">
// Klik heading grup (level-1) untuk expand/collapse subheader-nya.
// Scribe v4 default hanya scrollspy (grup aktif selalu terbuka);
// MutationObserver menjaga state collapse walau tema menambah .visible lagi.
(function () {
  var KEY = 'wasnaker-collapsed-groups';
  var collapsed = new Set();
  try { collapsed = new Set(JSON.parse(localStorage.getItem(KEY) || '[]')); } catch (e) {}

  function toggle(h, sub, force) {
    var isVisible = sub.classList.contains('visible');
    var willCollapse = force !== undefined ? !force : isVisible;
    sub.classList.toggle('visible', !willCollapse);
    if (willCollapse) collapsed.add(h.id); else collapsed.delete(h.id);
    try { localStorage.setItem(KEY, JSON.stringify(Array.from(collapsed))); } catch (e) {}
  }

  document.querySelectorAll('#toc .tocify-header').forEach(function (h) {
    var link = h.querySelector(':scope > li > a');
    var sub = h.querySelector(':scope > .tocify-subheader');
    if (!link || !sub) return;
    link.style.cursor = 'pointer';
    link.addEventListener('click', function (e) {
      e.preventDefault();
      toggle(h, sub);
    });
    // terapkan state tersimpan (mis. grup di-collapse sesi lalu)
    if (collapsed.has(h.id)) { sub.classList.remove('visible'); }
  });

  // lawan scrollspy: hapus .visible yang ditambahkan tema untuk grup collapsed
  var observer = new MutationObserver(function (mutations) {
    mutations.forEach(function (m) {
      if (m.type !== 'attributes' || m.attributeName !== 'class') return;
      var sub = m.target;
      var header = sub.parentElement;
      if (header && collapsed.has(header.id) && sub.classList.contains('visible')) {
        sub.classList.remove('visible');
      }
    });
  });
  document.querySelectorAll('#toc .tocify-subheader').forEach(function (sub) {
    observer.observe(sub, { attributes: true, attributeFilter: ['class'] });
  });
})();
</script>
"""

if '</body>' in html:
    html = html.replace('</body>', script + '\n</body>')
else:
    html += script

open(path, 'w').write(html)
print('inject: ok — toggle collapse grup ditambahkan ke index.html')
PYEOF
