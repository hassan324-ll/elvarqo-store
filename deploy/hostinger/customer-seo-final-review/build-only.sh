#!/bin/sh
# PRIVATE BUILD ONLY. No active files, symlinks, settings or database writes.
# Transfer/build permission is required before running this on Hostinger.
set -eu
umask 077
stage="$HOME/elvarqo/deployments/20261105-customer-seo-final-review-v2"
test "$#" = 1 || { printf 'Usage: sh build-only.sh /absolute/path/storefront-hostinger-review-source.zip\n'; exit 1; }
archive=$1
case "$archive" in /*) ;; *) printf 'Archive path must be absolute\n'; exit 1;; esac
printf '%s  %s\n' 'b5cab9c1ba967324b9655439a4102c192a77b02f45a55f7e73bb0faa2be97aa3' "$archive" | sha256sum -c -
test ! -e "$stage" || { printf 'Private build directory already exists; review before choosing a new path.\n'; exit 1; }
command -v unzip >/dev/null
mkdir -m 700 "$stage" "$stage/source"
unzip -q "$archive" -d "$stage/source"
cd "$stage/source"
test -f next.config.mjs && test ! -f next.config.ts
test ! -e .env && test ! -e .env.local && test ! -e node_modules
export PATH="/opt/alt/alt-nodejs24/root/bin:$PATH"
node -e "if(process.versions.node.split('.')[0]!=='24')throw Error('Node24 required')"
export NEXT_PUBLIC_API_URL=https://api.elvarqo.uk/api/v1
export CATALOGUE_API_URL=https://api.elvarqo.uk/api/v1
export COMMERCE_API_URL=https://api.elvarqo.uk/api/v1
export NEXT_PUBLIC_SITE_URL=https://elvarqo.uk
export STOREFRONT_ORIGIN=https://elvarqo.uk
export STOREFRONT_INDEXING=true
export NEXT_TELEMETRY_DISABLED=1
# Existing shared-host native SWC cannot load against its old glibc; webpack can use WASM fallback.
# Do not suppress native errors by changing production libraries or installing system packages.
npm ci --include=dev --no-audit --no-fund > "$stage/build.log" 2>&1
npm run build >> "$stage/build.log" 2>&1
test -f .next/standalone/server.js
mkdir -m 700 "$stage/artifact" "$stage/artifact/nodejs"
cp -a .next/standalone/. "$stage/artifact/nodejs/"
cp -a public "$stage/artifact/nodejs/public"
cp -a .next/static "$stage/artifact/nodejs/.next/static"
cp production-indexing.cjs "$stage/artifact/nodejs/production-indexing.cjs"
mkdir "$stage/artifact/nodejs/tmp"
find "$stage/artifact" -name '.env*' -print > "$stage/env-files.txt"
test ! -s "$stage/env-files.txt"
tar -czf "$stage/customer-seo-linux-standalone.tar.gz" -C "$stage/artifact" nodejs
sha256sum "$stage/customer-seo-linux-standalone.tar.gz" > "$stage/customer-seo-linux-standalone.sha256"
printf 'Private Linux build complete; NOT activated, deployed or tested on the public site.\n'
cat "$stage/customer-seo-linux-standalone.sha256"
tail -30 "$stage/build.log"
