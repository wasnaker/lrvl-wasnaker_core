// Tes fungsional accordion sidebar apidocs v2 (inject post-scribe)
// Script tema (CDN) tidak dimuat di jsdom — perilaku tema (hashChange
// menambah .visible) disimulasikan manual.
const { JSDOM } = require('jsdom');
const fs = require('fs');

const html = fs.readFileSync('/www/wwwroot/apidocs.wasnaker.lan/public/index.html', 'utf8');
const dom = new JSDOM(html, { runScripts: 'dangerously', url: 'http://apidocs.wasnaker.lan/' });

function result(name, ok, extra = '') {
  console.log(`${ok ? 'PASS' : 'FAIL'} | ${name}${extra ? ' | ' + extra : ''}`);
  if (!ok) process.exitCode = 1;
}

setTimeout(() => {
  const doc = dom.window.document;

  // --- level-1: grup api/v1 ---
  const link1 = doc.querySelector('#tocify-header-apiv1 > li > a');
  const sub1 = doc.querySelector('#tocify-subheader-apiv1');
  result('L1: selektor grup', !!link1 && !!sub1, `link="${link1 && link1.textContent.trim()}"`);
  result('L1: caret ada', link1 && link1.classList.contains('wasnaker-caret'));

  sub1.classList.add('visible'); // tema membuka grup
  setTimeout(() => {
    result('L1: caret open saat visible', link1.classList.contains('wasnaker-open'));

    link1.dispatchEvent(new dom.window.MouseEvent('click', { bubbles: true, cancelable: true }));
    result('L1: klik -> collapsed', !sub1.classList.contains('visible'));
    result('L1: caret tertutup', !link1.classList.contains('wasnaker-open'));

    link1.dispatchEvent(new dom.window.MouseEvent('click', { bubbles: true, cancelable: true }));
    result('L1: klik -> expanded lagi', sub1.classList.contains('visible'));

    // --- level-2: subgroup System ---
    const li2 = doc.querySelector('li.tocify-item.level-2[data-unique="apiv1-system"]');
    const link2 = li2.querySelector(':scope > a');
    const sub2 = li2.nextElementSibling;
    result('L2: selektor subgroup System', !!link2 && !!sub2 && sub2.classList.contains('tocify-subheader'), sub2 && sub2.id);
    result('L2: caret ada', link2.classList.contains('wasnaker-caret'));

    sub2.classList.add('visible'); // tema membuka subgroup
    setTimeout(() => {
      result('L2: caret open saat visible', link2.classList.contains('wasnaker-open'));

      link2.dispatchEvent(new dom.window.MouseEvent('click', { bubbles: true, cancelable: true }));
      result('L2: klik -> endpoint System collapsed', !sub2.classList.contains('visible'));
      result('L2: caret tertutup', !link2.classList.contains('wasnaker-open'));

      // --- observer melawan scrollspy (level-2) ---
      sub2.classList.add('visible'); // scrollspy mencoba membuka lagi
      setTimeout(() => {
        result('L2: observer lawan scrollspy (tetap collapsed)', !sub2.classList.contains('visible'));

        // --- persistensi ---
        const saved = dom.window.localStorage.getItem('wasnaker-collapsed-groups-v2') || '[]';
        result('state tersimpan (key v2)', saved.includes('tocify-subheader-apiv1::tocify-subheader-apiv1-system'), saved);

        // --- reload: state collapse diterapkan ---
        const dom2 = new JSDOM(html, { runScripts: 'dangerously', url: 'http://apidocs.wasnaker.lan/' });
        dom2.window.localStorage.setItem('wasnaker-collapsed-groups-v2',
          JSON.stringify(['tocify-header-apiv1::tocify-subheader-apiv1']));
        setTimeout(() => {
          const s1 = dom2.window.document.querySelector('#tocify-subheader-apiv1');
          const l1 = dom2.window.document.querySelector('#tocify-header-apiv1 > li > a');
          result('reload: grup collapsed tetap collapsed', !s1.classList.contains('visible'));
          result('reload: caret tidak open', !l1.classList.contains('wasnaker-open'));
          console.log(process.exitCode === 1 ? 'ADA KEGAGALAN' : 'SEMUA TES LULUS');
        }, 300);
      }, 200);
    }, 200);
  }, 200);
}, 800);
