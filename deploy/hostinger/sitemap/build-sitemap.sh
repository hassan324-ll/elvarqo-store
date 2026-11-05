set -eu
umask 077
stage="$HOME/elvarqo/deployments/20261105-sitemap-v1"
base="$HOME/domains/elvarqo.uk/hbuilds"
printf '%s  %s\n' 'df87b3544dfb7d1648f865ea5b595201820541bc17bf6786265cd68f494c6c91' "$stage/package.tar.gz" | sha256sum -c -
test ! -e "$stage/unpacked"
mkdir -m 700 "$stage/unpacked"
tar -xzf "$stage/package.tar.gz" -C "$stage/unpacked"
export PATH="/opt/alt/alt-nodejs24/root/bin:$PATH"
node --input-type=module - "$stage" "$base" <<'JS'
import fs from 'node:fs';
import crypto from 'node:crypto';
const [stage,base]=process.argv.slice(2);
const baselines=JSON.parse(fs.readFileSync(stage+'/unpacked/baselines.json','utf8'));
for(const [name,hash] of Object.entries(baselines)){
 if(!name.startsWith('storefront/'))continue;
 const file=name.slice('storefront/'.length);
 if(hash===null){if(fs.existsSync(base+'/last-source/'+file))throw Error('New file already exists: '+file);continue;}
 const actual=crypto.createHash('sha256').update(fs.readFileSync(base+'/last-source/'+file)).digest('hex');
 if(actual!==hash)throw Error('Source baseline differs: '+file);
}
console.log('Sitemap storefront source baselines match; no live source changed.');
JS
cp -a "$base/last-source" "$stage/source"
cp -a "$stage/unpacked/storefront/." "$stage/source/"
cd "$stage/source"
node --input-type=module <<'JS'
import fs from 'node:fs';
const p='next.config.mjs';
const original=fs.readFileSync(p,'utf8');
if(!original.includes('const nextConfig = {'))throw Error('Unexpected build config');
fs.writeFileSync(p,original.replace('const nextConfig = {',"const nextConfig = { output: 'standalone',"));
JS
export NEXT_PUBLIC_API_URL=https://api.elvarqo.uk/api/v1
export CATALOGUE_API_URL=https://api.elvarqo.uk/api/v1
export COMMERCE_API_URL=https://api.elvarqo.uk/api/v1
export NEXT_PUBLIC_SITE_URL=https://elvarqo.uk
export STOREFRONT_ORIGIN=https://elvarqo.uk
export STOREFRONT_INDEXING=true
export NEXT_TELEMETRY_DISABLED=1
npm ci --include=dev --no-audit --no-fund > "$stage/build.log" 2>&1
npm run build >> "$stage/build.log" 2>&1
test -f .next/standalone/server.js
printf 'PASS isolated Hostinger storefront production build.\n'
tail -25 "$stage/build.log"
