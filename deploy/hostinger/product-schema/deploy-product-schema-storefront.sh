set -eu
umask 077
stage="$HOME/elvarqo/deployments/20261105-product-schema-v1"
backup="$HOME/elvarqo/backups/20261105-product-schema-storefront-v1"
base="$HOME/domains/elvarqo.uk/hbuilds"
web="$HOME/domains/elvarqo.uk/public_html"
version=elvarqo-product-schema-20261105-v1
target="$base/versions/$version/nodejs"
test -f "$stage/source/.next/standalone/server.js"
test ! -e "$backup"
test ! -e "$base/versions/$version"
old=$(readlink "$base/current")
oldnode=$(readlink -f "$base/current/nodejs")
case "$old" in versions/*) ;; *) exit 1;; esac
mkdir -m 700 "$backup" "$backup/source" "$base/versions/$version"
printf '%s\n' "$old" > "$backup/previous-link.txt"
cp "$web/.htaccess" "$backup/main.htaccess"
cp "$base/config/.env" "$backup/runtime.env"
for file in src/app/products/[product]/page.tsx; do mkdir -p "$backup/source/$(dirname "$file")"; cp "$base/last-source/$file" "$backup/source/$file"; done
mkdir "$target"
cp -a "$stage/source/.next/standalone/." "$target/"
cp -a "$stage/source/public" "$target/public"
cp -a "$stage/source/.next/static" "$target/.next/static"
cp -an "$oldnode/.next/static/." "$target/.next/static/"
cp -a "$base/current/public_html" "$base/versions/$version/public_html"
if test -f "$base/current/.metadata.json"; then cp "$base/current/.metadata.json" "$base/versions/$version/.metadata.json"; fi
mkdir -p "$target/tmp"
cat > "$target/production-indexing.cjs" <<'JS'
// Production-only Passenger startup: parent hPanel environment can retain stale values.
process.env.STOREFRONT_INDEXING = 'true';
require('./server.js');
JS
export ELVARQO_WEB_ROOT="$web" ELVARQO_APP_TARGET="$target"
success=0
switched=0
stop_owned_app() {
 expected=$1
 for pid in $(pgrep -u "$(id -u)" -f 'next-server' || true); do
  cwd=$(readlink "/proc/$pid/cwd" || true)
  comm=$(cat "/proc/$pid/comm" 2>/dev/null || true)
  case "$comm" in next-server*) ;; *) continue;; esac
  if test "$cwd" = "$expected"; then printf 'Gracefully restarting owned storefront process %s\n' "$pid"; kill -TERM "$pid"; fi
 done
}
cleanup() {
 result=$?
 if test "$success" = 0 && test "$switched" = 1; then
  cp "$backup/runtime.env" "$base/config/.env"
  cp "$backup/main.htaccess" "$web/.htaccess"
  chmod 644 "$web/.htaccess"
  ln -s "$old" "$base/current.rollback-cards"
  mv -Tf "$base/current.rollback-cards" "$base/current"
  stop_owned_app "$target"
  cp -a "$backup/source/." "$base/last-source/"
  printf 'Restored previous storefront release/configuration after failed checks.\n'
 fi
 exit "$result"
}
trap cleanup EXIT
ln -s "versions/$version" "$base/current.cards-next"
mv -Tf "$base/current.cards-next" "$base/current"
switched=1
/opt/alt/alt-nodejs24/root/bin/node --input-type=module <<'JS'
import fs from 'node:fs';
const main=process.env.ELVARQO_WEB_ROOT+'/.htaccess';
const original=fs.readFileSync(main,'utf8');
if((original.match(/^PassengerAppRoot .+$/gm)||[]).length!==1)throw Error('Unexpected Passenger directives');
const next=original.replace(/^PassengerAppRoot .+$/m,'PassengerAppRoot '+process.env.ELVARQO_APP_TARGET).replace(/^PassengerStartupFile .+$/m,'PassengerStartupFile production-indexing.cjs');
fs.writeFileSync(main+'.next',next);fs.chmodSync(main+'.next',0o644);fs.renameSync(main+'.next',main);
const managed=process.env.ELVARQO_APP_TARGET.replace(/\/nodejs$/,'/public_html/.htaccess');
fs.writeFileSync(managed,next);fs.chmodSync(managed,0o644);
JS
touch "$target/tmp/restart.txt"
stop_owned_app "$oldnode"
curl --fail --silent --show-error --retry 3 --retry-delay 2 --max-time 50 https://elvarqo.uk/ -o "$stage/home-check.html"
if grep -Eqi '<meta[^>]+name="robots"[^>]+content="[^"]*(noindex|none)' "$stage/home-check.html"; then printf 'Homepage still excludes indexing; rolling back.\n'; exit 1; fi
curl --fail --silent --show-error --max-time 40 -D "$stage/home-headers.txt" https://elvarqo.uk/ -o "$stage/home-check.html"
if grep -Eqi '^x-robots-tag:.*(noindex|none)' "$stage/home-headers.txt"; then printf 'Homepage header excludes indexing; rolling back.\n'; exit 1; fi
grep -q 'compact-product-card' "$stage/home-check.html"
curl --fail --silent --show-error --max-time 40 https://elvarqo.uk/products/womens-roll-neck-knitted-jumper -o "$stage/product-check.html"
if grep -q 'unavailable-option' "$stage/product-check.html"; then printf 'Availability regression; rolling back.\n'; exit 1; fi
curl --fail --silent --show-error --max-time 40 -D "$stage/sitemap-live-headers.txt" https://elvarqo.uk/sitemap.xml -o "$stage/sitemap-live.xml"
grep -qi '^content-type: application/xml' "$stage/sitemap-live-headers.txt"
grep -q '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' "$stage/sitemap-live.xml"
if grep -qi '<html' "$stage/sitemap-live.xml"; then printf 'HTML fallback intercepted sitemap; rolling back.\n'; exit 1; fi
curl --fail --silent --show-error --max-time 40 https://elvarqo.uk/robots.txt -o "$stage/robots-live.txt"
grep -q 'Sitemap: https://elvarqo.uk/sitemap.xml' "$stage/robots-live.txt"
grep -q 'id="product-structured-data"' "$stage/product-check.html"
grep -q 'application/ld+json' "$stage/product-check.html"
cp -a "$stage/unpacked/storefront/." "$base/last-source/"
success=1
printf 'PASS initial product JSON-LD, sitemap, homepage indexing, compact cards and availability retained. No catalogue writes.\n'
