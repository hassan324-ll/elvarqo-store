# Hostinger deployment status — 2026-11-02

## Public applications

- Storefront: https://elvarqo.uk — managed Next.js / Node 24 application. Home and Women collection HTTPS responses, canonical URLs and API connectivity verified. Search indexing remains disabled.
- Admin: https://admin.elvarqo.uk — static React/Vite dashboard. HTTPS, assets, SPA fallback, sign-in rendering, exact API origin and storefront links verified. Existing owner login and authenticator acceptance still require merchant verification.
- API: https://api.elvarqo.uk/api/v1/health — Laravel / PHP 8.5.12 / Hostinger MySQL-compatible database / GD. Health, navigation, products, imported category image, CORS, secure staff session and unauthenticated rejection verified.

The database preserves 75 imported catalogue groups and the existing staff account and supporting records. Public navigation currently contains six active collections and 60 categories. No demo products were inserted. The local SQLite database and local environment remain unchanged. Subsequent online admin edits are stored in the hosting database and do not automatically synchronize back to local SQLite. Do not re-import the original snapshot over the live database.

## Server layout and rollback

- Laravel release: /home/u688505577/elvarqo/releases/20261101-stage01/backend
- API public entry: /home/u688505577/domains/api.elvarqo.uk/public_html/index.php — boots the private release; secrets and vendor files are outside public_html.
- Admin assets: /home/u688505577/domains/admin.elvarqo.uk/public_html
- Private backups: /home/u688505577/elvarqo/backups
- Private maintenance log: /home/u688505577/elvarqo/logs/hosting-tick.log

Before replacing API/admin public files, their previous contents were archived privately. A further backup precedes the admin storefront-link correction. Old admin hashed assets were retained for cached pages and rollback. Restore public files from the matching backup only after inspecting the intended target. Database rollback requires a separate current database backup; do not assume an old SQLite snapshot includes subsequent online changes. Original encrypted fields require the existing APP_KEY; never regenerate it on the imported database.

## Build configuration

Storefront Hostinger source packages use next.config.mjs, build command `next build --webpack`, and `experimental: { cpus: 2 }`. Server glibc 2.28 cannot load the Next 16.4.2 native SWC requiring 2.29. Webpack with Next's WASM fallback built successfully on the actual host. The first 24-worker attempt hit EAGAIN; two workers passed. Do not revert to the original failing TypeScript-config/Turbopack ZIP. Dependency versions and lockfile were unchanged by this packaging adaptation.

Managed storefront variables:

```dotenv
NEXT_PUBLIC_API_URL=https://api.elvarqo.uk/api/v1
CATALOGUE_API_URL=https://api.elvarqo.uk/api/v1
COMMERCE_API_URL=https://api.elvarqo.uk/api/v1
NEXT_PUBLIC_SITE_URL=https://elvarqo.uk
STOREFRONT_ORIGIN=https://elvarqo.uk
STOREFRONT_INDEXING=false
```

Redeploy the managed Node application after variable changes. Merchant completed this and public canonical links were verified.

Admin build variables:

```dotenv
VITE_API_URL=https://api.elvarqo.uk/api/v1
VITE_STOREFRONT_URL=https://elvarqo.uk
```

The admin's three storefront links now use VITE_STOREFRONT_URL with a localhost default for local development. Source changes are limited to Workspace.tsx, auth/StaffAccess.tsx, lib/site.ts and the secret-free .env.example. Formatting, lint and isolated TypeScript/Vite build passed. Static package includes SPA rewrite rules and noindex headers/robots. No private environment file, source database or API secret is part of the static package.

## Remaining hPanel cron setup

SSH does not expose `crontab`; no scheduled entry was installed. One manual `hosting:tick` succeeded on the host, and a second invocation while holding its file lock skipped correctly. In the API website's hPanel Advanced → Cron Jobs, use a custom command on the every-minute schedule (`* * * * *`, UTC):

```sh
/opt/alt/php85/usr/bin/php /home/u688505577/elvarqo/releases/20261101-stage01/backend/artisan hosting:tick >> /home/u688505577/elvarqo/logs/hosting-tick.log 2>&1
```

Inspect existing cron jobs first. Use only one application scheduler, not schedule:run, schedule:work, queue:listen or a parallel permanent queue worker. This bounded command holds an overlap lock, processes at most 20 jobs, and uses a cooperative time budget. PHP 8.5 CLI supports pcntl. Verify hPanel saves the entry and that a new log entry appears; rotate private logs periodically.

## Merchant acceptance and launch gates

1. Sign in at https://admin.elvarqo.uk using the existing staff account and its authenticator. Keep password, MFA seed and recovery codes private.
2. Confirm existing collections/categories and images. Add a genuine draft product, upload images and verify its saved details. Publishing and stock changes should use the merchant's actual data.
3. Confirm the published product and category images appear on the storefront; review desktop/mobile navigation and stock display.
4. Configure and verify the minute cron, hosting backups and restoration procedure.
5. Complete approved live tax/delivery/returns rules, verified Wise receiving details and real payment reconciliation, external transactional email, shipping/postage and refund processes before enabling checkout.

Wise/Stripe remain disabled. Email remains private local/log copies. Existing courier/refund and policy sandbox limitations remain. Current public availability is for browsing and admin verification, not readiness to take paid orders. Search indexing remains disabled until launch acceptance is complete.
