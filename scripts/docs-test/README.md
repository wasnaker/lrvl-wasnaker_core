# Tes fungsional accordion sidebar apidocs

Tes jsdom untuk `scripts/post-scribe-inject.sh` (toggle collapse grup + subgroup,
caret, observer vs scrollspy, persistensi localStorage).

Cara pakai (setelah regenerate + inject):

    cd scripts/docs-test
    npm install          # sekali saja (butuh jsdom)
    npm test

Harapan: 15/15 PASS. Menarik ulang index.html asli dari
/www/wwwroot/apidocs.wasnaker.lan/public/index.html.
