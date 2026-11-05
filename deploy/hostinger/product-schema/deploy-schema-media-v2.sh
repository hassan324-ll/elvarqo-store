set -eu
umask 077
stage="$HOME/elvarqo/deployments/20261105-product-schema-v1"
backup="$HOME/elvarqo/backups/20261105-product-schema-media-v2"
release="$HOME/elvarqo/releases/20261101-stage01/backend"
web="$HOME/domains/api.elvarqo.uk/public_html"
file=app/Http/Controllers/PublicCatalogueController.php
test ! -e "$backup"
/opt/alt/alt-nodejs24/root/bin/node --input-type=module - "$stage" "$release" <<'JS'
import fs from 'node:fs';import crypto from 'node:crypto';
const [stage,release]=process.argv.slice(2);const file='app/Http/Controllers/PublicCatalogueController.php';
const expected=JSON.parse(fs.readFileSync(stage+'/unpacked/baselines.json','utf8'))['backend/'+file];
if(crypto.createHash('sha256').update(fs.readFileSync(release+'/'+file,'utf8').replaceAll('\r\n','\n')).digest('hex')!==expected)throw Error('Backend baseline changed');
JS
/opt/alt/php85/usr/bin/php -l "$stage/unpacked/backend/$file"
mkdir -m 700 "$backup"
cp "$release/$file" "$backup/PublicCatalogueController.php"
cp "$web/robots.txt" "$backup/robots.txt"
cp "$web/index.php" "$backup/index.php"
success=0
cleanup() {
 result=$?
 if test "$success" = 0; then
  cp "$backup/PublicCatalogueController.php" "$release/$file"
  cp "$backup/robots.txt" "$web/robots.txt";chmod 644 "$web/robots.txt"
  cp "$backup/index.php" "$web/index.php";chmod 644 "$web/index.php"
  printf 'Restored image controller and robots after failed checks.\n'
 fi
 exit "$result"
}
trap cleanup EXIT
export ELVARQO_API_WEB="$web"
/opt/alt/alt-nodejs24/root/bin/node --input-type=module <<'JS'
import fs from 'node:fs';
const p=process.env.ELVARQO_API_WEB+'/index.php';const s=fs.readFileSync(p,'utf8');
const before="header('X-Robots-Tag: noindex, nofollow');";
if(s.split(before).length!==2)throw Error('Unexpected API entry point robots directive');
const after = "$publicImageRequest = preg_match('#^/api/v1/catalogue/media/[a-f0-9-]{36}/(?:640|1280|1920)$#i', parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '') === 1;\nif (! $publicImageRequest) {\n    header('X-Robots-Tag: noindex, nofollow');\n}";
fs.writeFileSync(p+'.next',s.replace(before,after));fs.chmodSync(p+'.next',0o644);fs.renameSync(p+'.next',p);
JS
/opt/alt/php85/usr/bin/php -l "$web/index.php"
cp "$stage/unpacked/backend/$file" "$release/$file.next";chmod 644 "$release/$file.next";mv "$release/$file.next" "$release/$file"
cp "$stage/unpacked/deploy/hostinger/api-robots.txt" "$web/robots.txt.next";chmod 644 "$web/robots.txt.next";mv "$web/robots.txt.next" "$web/robots.txt"
curl --fail --silent --show-error --max-time 40 https://api.elvarqo.uk/api/v1/catalogue/products/womens-roll-neck-knitted-jumper -o "$stage/live-product.json"
image=$(/opt/alt/alt-nodejs24/root/bin/node -p "JSON.parse(require('fs').readFileSync('$stage/live-product.json','utf8')).data.product.media[0].url")
curl --fail --silent --show-error --max-time 40 -I "$image" -o "$stage/live-image-headers.txt"
grep -qi '^x-robots-tag: index, *follow' "$stage/live-image-headers.txt"
if grep -qi '^x-robots-tag:.*noindex' "$stage/live-image-headers.txt"; then exit 1; fi
curl --fail --silent --show-error --max-time 30 -I https://api.elvarqo.uk/api/v1/health -o "$stage/live-api-headers.txt"
grep -qi '^x-robots-tag:.*noindex' "$stage/live-api-headers.txt"
success=1
printf 'PASS live published product image is indexable; API noindex remains, robots allow only public media. No business data changed.\n'
