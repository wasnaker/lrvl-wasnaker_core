#!/usr/bin/env bash
# ============================================================
# post-scribe-inject.sh — perbaikan kecil untuk output apidocs
# ------------------------------------------------------------
# Scribe v4 (tema default) TIDAK punya klik-untuk-collapse pada
# heading grup; buka/tutup hanya oleh scrollspy (grup aktif selalu
# terbuka). Dengan satu grup top-level (api/v1) sidebar terkesan
# "permanen terbuka". Script ini menambahkan:
#
#   1. Toggle collapse grup (klik heading level-1) — JS inline
#      + MutationObserver melawan scrollspy + localStorage.
#   2. Module bahasa 'http' untuk highlight.js — blok contoh
#      respons (language-http) tidak ter-highlight tanpa ini.
#
# PAKAI: jalankan SETELAH setiap scribe:generate:
#   bash scripts/post-scribe-inject.sh
# (idempoten per bagian — aman dijalankan berkali-kali)
# ============================================================
set -euo pipefail

HTML=/www/wwwroot/apidocs.wasnaker.lan/public/index.html
MARK_TOGGLE='wasnaker-sidebar-toggle'
MARK_HLJS='wasnaker-hljs-http'

if [ ! -f "$HTML" ]; then
  echo "ERROR: $HTML tidak ada — jalankan scribe:generate dulu." >&2
  exit 1
fi

python3 - "$HTML" "$MARK_TOGGLE" "$MARK_HLJS" <<'PYEOF'
import sys

path, mark_toggle, mark_hljs = sys.argv[1], sys.argv[2], sys.argv[3]
html = open(path).read()
changed = False

# --- 1. module bahasa 'http' untuk highlight.js ---
if mark_hljs not in html:
    lang_script = (
        '\n<script id="wasnaker-hljs-http"'
        ' src="https://unpkg.com/@highlightjs/cdn-assets@11.6.0/languages/http.min.js"></script>'
    )
    html = html.replace('</body>', lang_script + '\n</body>')
    changed = True
    print('inject: module highlight.js bahasa http ditambahkan')

# --- 2. toggle collapse grup sidebar ---
if mark_toggle not in html:
    toggle_script = """
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
    html = html.replace('</body>', toggle_script + '\n</body>')
    changed = True
    print('inject: toggle collapse grup ditambahkan')

if not changed:
    print('inject: semua sudah ada, skip.')

open(path, 'w').write(html)
PYEOF
