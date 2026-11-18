#!/bin/bash
# Vydanie novej verzie pluginu po zmene pages/dod-formular.json (termíny DOD z formulára).
# Použitie v klone repa NCN0004/knv-novy-web:  bash tools/release-dod.sh "Termíny DOD z formulára: +3"
set -e
cd "$(dirname "$0")/.."
N=${1:-"Termíny DOD z formulára"}
python3 -c "import json;d=json.load(open('pages/dod-formular.json'));assert isinstance(d['terminy'],list)"
OLD=$(grep -o "define( 'KNV_VERSION', '[0-9.]*'" knv-novy-web.php | grep -o "[0-9][0-9.]*")
NEW=$(python3 -c "v='$OLD'.split('.');v[-1]=str(int(v[-1])+1);print('.'.join(v))")
sed -i "s/$OLD/$NEW/g" knv-novy-web.php
command -v php >/dev/null && php -l knv-novy-web.php >/dev/null
git add -A
git -c user.name="Claude" -c user.email="noreply@anthropic.com" commit -qm "DOD z formulára – $NEW: $N

Co-Authored-By: Claude <noreply@anthropic.com>"
git push origin HEAD:main 2>&1 | tail -1
SHA=$(git rev-parse HEAD)
python3 -c "import json,sys;json.dump({'version':'$NEW','package':'https://github.com/NCN0004/knv-novy-web/archive/$SHA.zip','notes':sys.argv[1]},open('update.json','w'),ensure_ascii=False,indent=1)" "$N"
git add update.json
git -c user.name="Claude" -c user.email="noreply@anthropic.com" commit -qm "update.json: $NEW

Co-Authored-By: Claude <noreply@anthropic.com>"
git push origin HEAD:main 2>&1 | tail -1
echo "OK $NEW $SHA"
