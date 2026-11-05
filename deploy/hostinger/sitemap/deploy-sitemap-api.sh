set -eu
umask 077
stage="$HOME/elvarqo/deployments/20261105-sitemap-v1"
backup="$HOME/elvarqo/backups/20261105-sitemap-api-v1"
release="$HOME/elvarqo/releases/20261101-stage01/backend"
php=/opt/alt/php85/usr/bin/php
test -f "$stage/unpacked/backend/app/Http/Controllers/CatalogueSitemapController.php"
test ! -e "$release/app/Http/Controllers/CatalogueSitemapController.php"
test ! -e "$backup"
/opt/alt/alt-nodejs24/root/bin/node --input-type=module - "$stage" "$release" <<'JS'
import fs from 'node:fs';import crypto from 'node:crypto';
const [stage,release]=process.argv.slice(2);
const expected=JSON.parse(fs.readFileSync(stage+'/unpacked/baselines.json','utf8'))['backend/routes/catalogue.php'];
const actual=crypto.createHash('sha256').update(fs.readFileSync(release+'/routes/catalogue.php','utf8').replaceAll('\r\n','\n')).digest('hex');
if(expected!==actual)throw Error('Live backend routes changed since review');
console.log('Backend routes match reviewed baseline.');
JS
"$php" -l "$stage/unpacked/backend/app/Http/Controllers/CatalogueSitemapController.php"
"$php" -l "$stage/unpacked/backend/routes/catalogue.php"
mkdir -m 700 "$backup"
cp "$release/routes/catalogue.php" "$backup/catalogue.php"
success=0
cleanup() {
 result=$?
 if test "$success" = 0; then
  cp "$backup/catalogue.php" "$release/routes/catalogue.php"
  cd "$release"; "$php" artisan route:cache
  printf 'Restored previous routes after failed API verification.\n'
 fi
 exit "$result"
}
trap cleanup EXIT
for file in app/Http/Controllers/CatalogueSitemapController.php routes/catalogue.php; do
 cp "$stage/unpacked/backend/$file" "$release/$file.next"
 chmod 644 "$release/$file.next"
 mv "$release/$file.next" "$release/$file"
done
cd "$release"
"$php" artisan route:cache
curl --fail --silent --show-error --max-time 40 'https://api.elvarqo.uk/api/v1/catalogue/sitemap' -o "$stage/sitemap-api-live.json"
/opt/alt/alt-nodejs24/root/bin/node --input-type=module - "$stage" <<'JS'
import fs from 'node:fs';
const data=JSON.parse(fs.readFileSync(process.argv[2]+'/sitemap-api-live.json','utf8')).data;
if(!Array.isArray(data.products)||!Array.isArray(data.groups)||data.page!==1)throw Error('Invalid live feed');
if(data.products.some(p=>Object.keys(p).some(k=>!['slug','lastmod'].includes(k))))throw Error('Unexpected product fields');
console.log('PASS live public sitemap API: '+data.products.length+' product slugs; '+data.groups.length+' useful groups; no private fields.');
JS
curl --fail --silent --show-error --max-time 30 https://api.elvarqo.uk/api/v1/health -o "$stage/api-health.json"
success=1
printf 'PASS additive read-only API deployed. No migrations or catalogue writes.\n'
