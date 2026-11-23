#!/bin/sh
# Rigenera assets/js/popup.min.js da popup.js (da lanciare dopo ogni modifica a popup.js, prima del commit).
# Usa esbuild (npx esbuild). Controlla che il file minificato resti sotto i 5 KB.
set -e
cd "$(dirname "$0")/.."
ESBUILD=${ESBUILD:-"npx --yes esbuild@0.28.2"}
$ESBUILD assets/js/popup.js --minify --target=es2017 --legal-comments=none --outfile=assets/js/popup.min.js --log-level=warning
size=$(wc -c < assets/js/popup.min.js | tr -d ' ')
gz=$(gzip -9c assets/js/popup.min.js | wc -c | tr -d ' ')
echo "popup.min.js: $size byte (gzip $gz)"
[ "$size" -lt 5120 ] || { echo "ATTENZIONE: popup.min.js supera i 5 KB" >&2; exit 1; }
