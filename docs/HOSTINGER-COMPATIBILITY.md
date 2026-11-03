# Hostinger shared-host compatibility

Architecture recorded 2026-11-01 before implementation. User supplied Hostinger support reply: MySQL triggers generally supported but no database/user yet; PHP proc_open disabled; GD/Imagick enabled; minute cron allowed with bounded workers. Account-level grants and deployed tests remain pending. No remote configuration or DNS changes authorized by this document alone.

Keep Laravel and MySQL/MariaDB. Add a PHP GD image adapter alongside the existing Sharp adapter, selected explicitly by MEDIA_IMAGE_DRIVER=gd on Hostinger; existing local Sharp configuration remains intact. Keep still JPEG/PNG/WebP validation, 8MB/24MP caps, EXIF orientation, alpha, metadata stripping and non-enlarging 640/1280/1920 WebP outputs. Category minimum remains 64px, product minimum 200px. Reject animated PNG/WebP and corrupt/truncated input with meaningful errors. Bound GD memory allocation before decode, and do not invoke child processes in the GD path.

Add hosting:tick, a single CLI entry point that acquires an OS file lock, invokes existing maintenance commands in-process (not schedule:run, which launches child processes), then drains a limited number of database queue jobs with a bounded time budget. Same lock protects the complete tick; concurrent invocations exit without running maintenance or worker. Current jobs write private mail copies; no external mail or real payments. A max-time budget is cooperative; individual job timeouts and host limits still need deployed verification.

Current dependency lock requires PHP >=8.4.1. Hostinger PHP8.3 must be upgraded for both API web requests and cron/CLI, using the actual executable path confirmed by hosting support. Do not bypass Composer platform requirements or rewrite the backend.

Validate with an isolated portable GD-enabled PHP runtime (no system PHP changes), disabled process-execution functions for native upload/tick acceptance, existing Sharp regressions, image orientation/transparency/security cases, lock/queue behavior, and original DB/env preservation. Staging still requires an empty database/user with real trigger grants and secure deployment access.

## Configuration to apply on staging

Your supplied Hostinger reply removes the need for a VPS solely to launch an image worker: use the native GD adapter instead. Trigger support is still provisional until the actual newly created database user's migrations succeed. There was no database assigned to the domain at the time of the reply.

1. In hPanel, set the API site's PHP version to **8.4.1+ or 8.5**, and enable GD with JPEG/PNG/WebP. The existing Composer lock requires >=8.4.1, so PHP8.3 is insufficient. Confirm the cron/SSH PHP executable is also the required version; changing the website PHP version may not change the CLI binary.
2. Create a new, empty staging MySQL database/user. Keep its password in the private server .env; do not send it through chat or source control. Do not reuse a populated database.
3. Use the private backend configuration from examples/backend-hostinger.env.example. Set MEDIA_IMAGE_DRIVER=gd, QUEUE_CONNECTION=database and WISE_ENABLED=false. Keep APP_KEY from the source when importing encrypted accounts/receipts. Sharp/media npm dependencies are unnecessary on the deployed Laravel API in GD mode; the separately deployed Next.js app still needs its own Node dependencies.
4. Deploy Laravel outside the public document root; serve only its public/ directory. The support example placing artisan and application source inside public_html is not our deployment layout. If hPanel cannot point directly at public/, the public entry point must reference a sibling private application directory. Review the exact paths during deployment.
5. Apply migrations, preflight and import using MYSQL-MIGRATION.md. If trigger creation is denied, stop and ask Hostinger to enable the required grant. Never remove audit/stock/order protections to make the import pass. Copy private product-media files separately; category images are database-backed.
6. Run php artisan hosting:check using the correct PHP binary. It reports capabilities without printing credentials; the storage check creates and immediately removes two tiny private probe files, and database checks only read metadata. A successful local report is not evidence that the deployed account has passed. Then verify real category/product image uploads through the deployed API.

After private configuration changes, run php artisan config:clear. Rebuild frontend deployments when their public API URL changes. The local developer .env still uses the existing Sharp/SQLite setup; it was not switched or overwritten.

## One cron entry; no permanent worker or subprocess scheduler

Use hPanel's every-minute UTC schedule:

```cron
* * * * *
```

Command template (replace every placeholder with verified server paths; create the private log directory first):

```sh
/ABSOLUTE/PATH/TO/PHP84/php /home/ACCOUNT/domains/api-staging.elvarqo.uk/elvarqo-backend/artisan hosting:tick >> /home/ACCOUNT/private-cron-logs/elvarqo.log 2>&1
```

Do **not** blindly use /usr/bin/php; verify its version. Do not run schedule:run, schedule:work, queue:listen or an additional continuous queue:work alongside this shared-host cron. Laravel's normal scheduled command runner can create child processes, which this account disables. The new hosting:tick invokes the maintenance commands and queue worker within the same PHP process instead.

The whole tick holds one OS file lock at storage/framework/hosting-tick.lock. A second overlapping tick exits without processing work; do not delete the active lock file to bypass it. It expires eligible Wise/manual/exchange reservations, queues private notifications and drains at most 20 jobs with a worker max-time of up to 30 seconds, inside a cooperative 45-second tick budget. Existing queued work is drained before adding another outbox batch, avoiding repeated notification enqueue growth. Failed jobs follow the existing retry policy and remain inspectable with php artisan queue:failed. Logs must remain private and need rotation/monitoring.

The queue max-time is checked between jobs; it does not forcibly terminate a single long job. The --timeout=20 limit requires pcntl signal support. If unavailable on the host, rely on verified host/PHP execution limits and bounded job operations; do not claim the tick has a hard 45-second wall-clock guarantee. Confirm remote duration and that overlapping cron calls skip before enabling it on a live database. The file lock is for one shared-host API deployment; multiple independent application hosts would need a distributed lock.

## What this does not enable

The current mail jobs continue writing private .eml/log copies. No external email is configured by this change. Wise receiving details remain pending and checkout disabled. Tax/delivery policies and courier/refund integrations retain their existing launch blockers. After-sales test-only mutations remain disabled outside local/testing. Staging must be private and non-indexable, with outbound mail/payments disabled and copied accounts protected. Nameservers and live DNS are unchanged.

References:
- https://www.php.net/manual/en/function.imagewebp.php — GD output must be verified because a successful return alone may not prove a file was written.
- https://www.hostinger.com/support/1575755-how-to-change-the-php-version-of-your-hostinger-hosting-plan/ — PHP version selection in hPanel.

## Verification completed 2026-11-01

- Full GD/SQLite backend suite on portable official PHP8.4.27: **177 tests / 1,565 assertions passed**.
- Full GD/MariaDB 10.4.32 suite on isolated loopback3317: **185 tests / 1,620 assertions passed**, including existing migration/import/concurrency coverage.
- Targeted native image/API and hosting tick acceptance with proc_open, exec, shell_exec, system, passthru and popen disabled: **10 tests / 82 assertions passed**. These tests use no Node subprocess, including the actual category/product upload routes with a deliberately unusable Node path.
- Existing Herd PHP8.5 Sharp regression: **177 tests / 1,499 assertions passed, six GD-only tests skipped** because that runtime has no GD. The original local Sharp path remains available.
- Native checks cover JPEG/PNG/WebP, non-enlarged 640/1280/1920 derivatives, category thumbnails, alpha, metadata removal, all eight JPEG EXIF orientations, animated PNG/WebP and truncation rejection, minimum dimensions and dynamic memory budget. The earlier Downloads/06_Knitwear.webp is no longer present, so that exact original was not retested in this task.
- Tick checks cover repeated runs, lock contention, at most20 processed jobs, retaining remaining work, not duplicating outbox batches when backlog exists and rejecting a non-database queue configuration.
- hosting:check passes all eight capability checks on the local GD/MariaDB copy with subprocess functions disabled. On Windows the initial read-only is_writable directory flag was misleading; the final diagnostic tests actual temporary writes and cleans them up. All34 existing evidence triggers remain present. This is not a remote Hostinger result.
- Full PHP Pint check passed. No frontend changes, so frontend builds were not rerun for this backend-only task. CI workflows now request GD; remote CI has not been run.
- Preservation comparison: **60 source tables and the existing backend/.env unchanged**. No production credentials, real transfers, external emails, provider accounts, Hostinger settings or DNS/nameservers were changed. The installed Herd/XAMPP PHP binaries/settings were untouched; a checksum-verified portable official PHP build was used only for QA. Isolated MariaDB was stopped after verification.

Remaining before staging: select PHP>=8.4.1/GD on a separate API PHP site, create the empty staging database/user, verify actual trigger grants via migrations, supply secure deployment access and apply the staging configuration. Test uploads and cron on that actual account. Keep Wise disabled until separately configured and verified.
