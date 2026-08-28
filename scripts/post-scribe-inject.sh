#!/usr/bin/env bash
# ============================================================
# post-scribe-inject.sh — perbaikan kecil untuk output apidocs
# ------------------------------------------------------------
# Scribe v4 (tema default) TIDAK punya klik-untuk-collapse pada
# heading menu; buka/tutup hanya oleh scrollspy (semua subheader
# grup aktif selalu dibuka). Script ini menambahkan:
#
#   1. Toggle collapse (accordion) — klik heading grup (api/v1)
#      ATAU heading subgroup (System, Settings, ...) untuk
#      expand/collapse; MutationObserver melawan scrollspy;
#      state tersimpan di localStorage.
#   2. Ikon caret (▸/▾) di heading yang bisa di-collapse.
#   3. Module bahasa 'http' untuk highlight.js.
#
# PAKAI: jalankan SETELAH setiap scribe:generate:
#   bash scripts/post-scribe-inject.sh
# (idempoten per bagian — aman dijalankan berkali-kali)
# ============================================================
set -euo pipefail

HTML=/www/wwwroot/apidocs.wasnaker.lan/public/index.html
MARK_TOGGLE='wasnaker-sidebar-toggle-v3'
MARK_HLJS='wasnaker-hljs-http'

if [ ! -f "$HTML" ]; then
  echo "ERROR: $HTML tidak ada — jalankan scribe:generate dulu." >&2
  exit 1
fi

python3 - "$HTML" "$MARK_TOGGLE" "$MARK_HLJS" <<'PYEOF'
import re
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

# --- 2. toggle collapse (v2: grup + subgroup) ---
if mark_toggle not in html:
    # buang blok versi lama (v1/v2 script + style) bila ada —
    # kalau tidak, handler dobel -> toggle dua kali = no-op
    html = re.sub(r'\n<script id="wasnaker-sidebar-toggle[^"]*">.*?</script>\n', '\n', html, flags=re.S)
    html = re.sub(r'\n<style id="wasnaker-sidebar-toggle[^"]*">.*?</style>\n', '\n', html, flags=re.S)

    css = """
<style id="wasnaker-sidebar-toggle-css">
#toc a.wasnaker-caret::before { content: '\\25B8  '; font-size: .8em; }
#toc a.wasnaker-caret.wasnaker-open::before { content: '\\25BE  '; }
</style>
"""

    toggle_script = """
<script id="wasnaker-sidebar-toggle-v3">
// Accordion sidebar: klik heading grup (level-1) atau subgroup (level-2)
// untuk expand/collapse. Scrollspy tema selalu membuka subheader grup aktif;
// MutationObserver + localStorage menjaga state collapse.
(function () {
  var KEY = 'wasnaker-collapsed-groups-v2';
  var collapsed = new Set();
  try { collapsed = new Set(JSON.parse(localStorage.getItem(KEY) || '[]')); } catch (e) {}

  function keyFor(sub) { return sub.parentElement.id + '::' + sub.id; }

  function bind(link, sub) {
    if (!link || !sub) return;
    link.classList.add('wasnaker-caret');
    link.style.cursor = 'pointer';
    link.addEventListener('click', function (e) {
      e.preventDefault();
      var k = keyFor(sub);
      var willCollapse = sub.classList.contains('visible');
      sub.classList.toggle('visible', !willCollapse);
      link.classList.toggle('wasnaker-open', !willCollapse);
      if (willCollapse) collapsed.add(k); else collapsed.delete(k);
      try { localStorage.setItem(KEY, JSON.stringify(Array.from(collapsed))); } catch (err) {}
    });
    // terapkan state tersimpan + sinkronkan caret dengan state aktual
    if (collapsed.has(keyFor(sub))) {
      sub.classList.remove('visible');
      link.classList.remove('wasnaker-open');
    } else {
      link.classList.toggle('wasnaker-open', sub.classList.contains('visible'));
    }
  }

  // level-1: grup (api/v1, ...)
  document.querySelectorAll('#toc .tocify-header').forEach(function (h) {
    bind(h.querySelector(':scope > li > a'), h.querySelector(':scope > .tocify-subheader'));
  });

  // level-2: subgroup (System, Settings, ...) — ul subheader adalah sibling
  document.querySelectorAll('#toc li.tocify-item.level-2').forEach(function (li) {
    var sub = li.nextElementSibling;
    if (sub && sub.classList.contains('tocify-subheader')) {
      bind(li.querySelector(':scope > a'), sub);
    }
  });

  // lawan scrollspy: hapus .visible yang ditambahkan tema untuk item collapsed,
  // dan sinkronkan caret (wasnaker-open) dengan state subheader aktual
  var observer = new MutationObserver(function (mutations) {
    mutations.forEach(function (m) {
      if (m.type !== 'attributes' || m.attributeName !== 'class') return;
      var sub = m.target;
      if (!sub.classList.contains('tocify-subheader')) return;
      var link = sub.previousElementSibling && sub.previousElementSibling.classList.contains('level-2')
        ? sub.previousElementSibling.querySelector('a')
        : sub.parentElement.querySelector(':scope > li > a');
      if (!link) return;
      if (collapsed.has(keyFor(sub))) {
        if (sub.classList.contains('visible')) {
          sub.classList.remove('visible');
          link.classList.remove('wasnaker-open');
        }
      } else {
        link.classList.toggle('wasnaker-open', sub.classList.contains('visible'));
      }
    });
  });
  document.querySelectorAll('#toc .tocify-subheader').forEach(function (sub) {
    observer.observe(sub, { attributes: true, attributeFilter: ['class'] });
  });
})();
</script>
"""
    html = html.replace('</body>', css + toggle_script + '\n</body>')
    changed = True
    print('inject: accordion v3 (grup + subgroup) + caret ditambahkan')

if not changed:
    print('inject: semua sudah ada, skip.')

open(path, 'w').write(html)
PYEOF
