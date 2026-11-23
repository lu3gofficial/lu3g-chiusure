#!/bin/sh
# Crea dist/lu3g-chiusure.zip (cartella lu3g-chiusure/ all'interno), il pacchetto che WordPress installa e aggiorna.
# Esclude i file che servono solo allo sviluppo (git, script, automazioni).
set -e
cd "$(dirname "$0")/.."
rm -rf dist && mkdir -p dist/lu3g-chiusure
cp -R lu3g-chiusure.php readme.txt uninstall.php includes assets languages dist/lu3g-chiusure/
(cd dist && zip -rq lu3g-chiusure.zip lu3g-chiusure -x '*.DS_Store' -x '*/.gitkeep')
rm -rf dist/lu3g-chiusure
echo "dist/lu3g-chiusure.zip"
