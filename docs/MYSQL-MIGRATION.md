# MySQL / MariaDB migration preparation

Architecture recorded 2026-10-31 before implementation. Keep Laravel and the existing SQLite development database. Hostinger Web/Cloud uses MariaDB through its MySQL interface. Test using the installed XAMPP MariaDB 10.4.32 engine on a separate loopback port (3317), isolated data directory, and disposable schemas. This is compatibility evidence, not validation of the unknown remote server version or its privileges.

Preserve IDs, passwords, encrypted MFA, group hierarchy, images and stock/order history. Copy a consistent SQLite backup, never a running file by ordinary file copy. Retain APP_KEY privately: encrypted values cannot be decrypted with a replacement key. The destination must have current migrations and no business records; import is transactional, refuses populated targets, verifies normalized record hashes, and leaves source untouched. Do not export private data or keys into public_html or source control.

Audit/stock immutability must not be weakened. Existing MySQL audit/stock triggers remain required. Add missing commerce/fulfilment/after-sales immutability protections for MySQL/MariaDB. Migration must fail if the destination account cannot install them. Hostinger's SQL import instructions exclude triggers; remote trigger privileges are therefore a staging blocker until checked, not justification to silently omit protections.

Run the same feature suite against MariaDB, with separate coverage of fresh/rollback migrations, immutable SQL evidence, import preservation, and independent-process last-unit checkout races. Keep current SQLite suite passing. No production credentials, DNS changes, live transfers, external email or deployment in this task.

Staging also needs PHP/extensions, Node24/Sharp child-process execution, HTTPS/private storage, origin/cookie configuration and bounded scheduled queue execution verified on the exact plan. No assumptions that successful local database tests prove all hosting capabilities.

Sources checked:
- https://www.hostinger.com/support/1583226-which-database-management-system-is-used-at-hostinger/
- https://www.hostinger.com/support/1864324-how-to-upload-and-set-up-your-database-at-hostinger/

## 2026-11-01 shared-host update

The merchant supplied Hostinger's confirmation that proc_open is disabled, GD/Imagick are available and minute cron is supported. The API now has a native GD adapter and an in-process bounded hosting:tick command; **GD replaces the previous PHP-to-Node requirement**. See HOSTINGER-COMPATIBILITY.md for current image/cron configuration. Set PHP >=8.4.1 for the existing dependency lock; support's PHP8.3 configuration is insufficient. Actual database trigger grants and remote staging remain unverified.

## Local configuration and repeatable tests

The existing backend/.env remains SQLite. No business database or nameserver was switched. The source is not PostgreSQL. A schema migration and data transfer are different operations; do not import SQLite SQL directly through phpMyAdmin.

Use an isolated MariaDB/MySQL service (XAMPP is sufficient locally) and create a **disposable** schema named elvarqo_test_suite with a test-only user. The PHPUnit profile deliberately wipes its selected test schema and rejects ordinary business database names. Run in a fresh PowerShell terminal from backend:

```powershell
$env:MYSQL_TEST_HOST = '127.0.0.1'
$env:MYSQL_TEST_PORT = '3306'
$env:MYSQL_TEST_DATABASE = 'elvarqo_test_suite'
$env:MYSQL_TEST_USERNAME = 'your_local_test_user'
# Set MYSQL_TEST_PASSWORD privately if your test user requires one.
php vendor/phpunit/phpunit/phpunit -c phpunit.mysql.xml
```

Do not use a production database or real credentials in this test profile. This task used port 3317 and separate elvarqo_compat_* schemas, with an isolated XAMPP MariaDB data directory. No existing XAMPP data/service was changed. The older MariaDB 10.4 test instance needs explicit_defaults_for_timestamp enabled at startup; verify the real Hostinger version/settings separately. Oracle MySQL has not been run locally; the added CI matrix targets MySQL 8.4 and MariaDB 11.4 and remains unexecuted until CI runs.

Normal SQLite regression remains:

```powershell
php vendor/phpunit/phpunit/phpunit
php vendor/bin/pint --test
```

## Preserving and copying data

Create a new private backup directory outside the repository and public web root. With the current SQLite backend configuration, this command makes a consistent online backup and refuses to overwrite a file:

```powershell
php artisan database:snapshot-sqlite "C:\PrivateBackups\elvarqo-before-mysql.sqlite"
```

Keep the existing backend/.env (especially APP_KEY) and backend/storage/app/private/catalogue backed up privately too. Category thumbnails are in the database; product photo derivatives are separate private files. The import does not copy media files, .env, or APP_KEY. Never place backups, Laravel source/private storage or .env in public_html.

On a **separate offline destination application copy**, install dependencies, preserve APP_KEY privately, set APP_ENV=staging (or local for rehearsal), WISE_ENABLED=false and MySQL settings. The secret-free starting template is examples/backend-hostinger.env.example; it must be completed privately and its placeholder domains are planned staging addresses, not deployed services.

```dotenv
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=the_new_destination_database
DB_USERNAME=the_destination_user
DB_PASSWORD=your_private_password
DB_TIMEZONE=+00:00
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci
```

Run from the destination backend; these commands must not target the existing populated database:

```powershell
php artisan config:clear
php artisan migrate --force
php artisan database:import-sqlite "C:\PrivateBackups\elvarqo-before-mysql.sqlite"
# Review preflight counts. Keep traffic and workers stopped, then:
php artisan database:import-sqlite "C:\PrivateBackups\elvarqo-before-mysql.sqlite" --execute
```

On Linux substitute the absolute private server path to the backup. The SQLite PDO extension is needed for this one-time import in addition to pdo_mysql. Do not use migrate:fresh, reset or rollback against your populated source or destination.

The command refuses populated business tables, schema mismatches, non-InnoDB tables, missing evidence triggers and incompatible encryption keys. It copies all business rows with original IDs and verifies semantic JSON/record hashes and all declared foreign keys before commit. No trigger is disabled. Foreign-key checks are temporarily disabled on the isolated import connection to handle category self-references, validated explicitly before commit and restored afterwards. Insert/JSON/uniqueness failures roll back all data writes. Sessions, cache and queue jobs are deliberately excluded; migration history belongs to the destination. Database-backed notification/outbox records are copied, so keep workers stopped on a staging clone until outbound behavior and copied notification handling are reviewed.

Do not run a staging clone publicly with copied customer/account data. Restrict all three staging hosts, disable external mail and real payments, and exclude staging from indexing. Freeze source writes and take a final fresh snapshot for a real cutover; the rehearsal snapshot is not a live synchronization tool. Retain the source for rollback. After destination takes new orders, switching back requires reconciliation of those orders rather than simply restoring an old snapshot.

## Hostinger staging gates and deployment order

Staging has not been deployed. Nameservers and live DNS are unchanged. Confirm the following on the actual Unlimited account first:

1. MySQL/MariaDB version and InnoDB; database user can create BEFORE UPDATE/DELETE triggers using SIGNAL SQLSTATE. Hostinger's published SQL-import guide excludes triggers. Do not strip trigger protections or mark this check complete without account-level evidence. If unavailable, choose a compatible database/hosting service or separately review a different evidence architecture.
2. PHP 8.4+ for the current lockfile/dev checks, required extensions including pdo_mysql, pdo_sqlite/sqlite3 for transfer, mbstring, fileinfo, OpenSSL, DOM/XML and curl; Composer install and artisan access over SSH.
3. Node24 for the separate Next.js deployment; GD JPEG/PNG/WebP on the Laravel API with MEDIA_IMAGE_DRIVER=gd. Verify a real upload through Laravel. PHP no longer needs to launch Node on this deployment path.
4. Minute cron support is confirmed by the merchant-supplied support reply. Use the new in-process hosting:tick command, not schedule:run/work. Verify the PHP binary version, host execution limits and remote overlap/retry behavior; see HOSTINGER-COMPATIBILITY.md.
5. Existing domain/email DNS inventory and private staging hostnames. Keep Namecheap nameservers; only add reviewed staging records after destinations are provisioned. Do not replace existing MX/TXT records.

Then provision an empty staging database and three restricted staging sites. Upload Laravel outside the public document root (only public/ is served), deploy the Next.js server and built admin assets, complete private environment values/origins/HTTPS, migrate and preflight/import the snapshot. Copy private product media and verify permissions. Leave Wise and external mail disabled. Test health/database, owner login/MFA, hierarchy/images, product editing/publishing, stock, guest isolation and cron execution on staging. Synthetic payment/fulfilment fixtures must remain isolated and cannot claim a real bank transfer or postage. Live tax/delivery policies, email, courier and refunds retain the separately documented launch blockers.

No actual Hostinger access or credentials were supplied during this local task. Host privilege/runtime confirmation and secure deployment access are needed to proceed to remote staging.

## Verification completed 2026-10-31

- Existing SQLite feature suite: **167 tests / 1,483 assertions passed**.
- Complete MariaDB profile: **174 tests / 1,534 assertions passed**, including two independent-process races and five transfer tests. After adding the final JSON object-versus-array rewrite regression and strengthening canonical verification, all **six affected transfer tests / 40 assertions passed**. Eight MySQL-specific tests are now included in the repeatable profile.
- Fresh migrations and full rollback paths exercised on isolated MariaDB 10.4.32. Fixed one generated index name over MySQL's 64-character limit and foreign-key/index removal ordering in return-unit rollback. Existing database migrations are not rerun or reset on the development database.
- Added 30 MySQL evidence triggers (15 tables, UPDATE/DELETE), retaining the existing four staff audit/stock triggers. The MariaDB-specific Laravel driver is also handled by the additive migration; the locally exercised connection used Laravel's mysql driver.
- Import tests cover identity/password hashes/MFA, category hierarchy and image bytes, paid order/receipt/stock preservation, excluded sessions, auto-increment continuity, populated-target rejection, wrong key, invalid JSON rollback, missing evidence trigger refusal and silent JSON shape rewrite detection.
- Both concurrent checkout attempts cannot reserve the last unit; identical simultaneous owner receipt confirmations produce one stock settlement and one outbox entry.
- Actual development snapshot rehearsal imported 75 groups, one staff account and the existing evidence/recovery records into the private copy. No source products existed at snapshot time. All 59 imported business table row hashes and declared foreign keys passed. SQLite migration history and runtime tables are not copied.
- Preservation comparison: **60 source tables unchanged** (business tables plus migrations); existing backend/.env byte-for-byte unchanged. Category thumbnails were verified through copied database rows; no private product photo files were modified. PHP Pint passed.
- Laravel health, public navigation, product list and saved WebP category image reads returned 200 from the migrated copy; storefront CORS origin was retained. Frontend source did not change, so prior frontend build results are not presented as a newly executed build in this task.
- No real payment, external mail, Hostinger deployment, DNS or nameserver changes. The isolated MariaDB process was stopped after verification. Private snapshots/config backups and real-account copy remain outside deliverables in the task work directory.
- Attempted read-only inspection of the signed-in Hostinger Chrome dashboard using the computer-use skill. The tool stopped because it could not verify the current browser URL confidently enough to enforce policy. No further UI actions were attempted, and no hosting settings were changed. Account-level trigger/PHP/cron checks and secure deployment access remain outstanding.
