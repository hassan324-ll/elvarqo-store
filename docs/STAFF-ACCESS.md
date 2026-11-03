# Staff access — Phase 3

Project location: `C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO`. Use three terminals for backend, storefront and admin as described in README. SQLite needs no XAMPP service.

## Upgrade and create your owner account

Preserve your existing environment files, APP_KEY and database. In PowerShell:

```powershell
cd "C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO\backend"
composer install
php artisan migrate
php artisan staff:create YOUR_EMAIL --name="Your Name" --role=owner
```

Replace YOUR_EMAIL with your real work address. Enter and confirm your own password at the hidden prompts: at least 14 characters including uppercase, lowercase, number and symbol. No personal staff account or default password was created during development.

## Start all three applications

```powershell
# Terminal 1
cd "C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO\backend"
php artisan serve --host=localhost --port=8000 --tries=1
```

```powershell
# Terminal 2
cd "C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO\storefront"
npm run dev
```

```powershell
# Terminal 3
cd "C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO\admin"
npm run dev
```

Use `npm.cmd` if PowerShell blocks npm scripts. Existing dependencies remain installed; use `npm ci` inside each frontend for a fresh install. Visit http://localhost:5173, sign in, add the displayed setup key to a time-based authenticator and enter its six-digit code. Store the ten recovery codes privately before continuing. The key and codes are sensitive. No QR service receives the seed. Confirm your device clock is synchronized.

Visit http://localhost:5173/?view=security for your identity, permissions, recovery-code replacement and (with audit.view) real security events. Business dashboards remain labelled demonstrations. Admin design reference and health diagnostics now require staff authentication. Storefront http://localhost:3000 and health http://localhost:8000/api/v1/health remain public.

## Test locally

1. Open a private browser window: admin must show sign-in. Incorrect credentials give neutral feedback. Password acceptance alone must lead to the mandatory authenticator step.
2. Complete enrollment, save recovery codes, then sign out. Sign in using a recovery code; that same code must fail on its next use.
3. On Your security, replace recovery codes using your password and a fresh authenticator code. Old codes must fail. A code already accepted in the current 30-second step cannot be reused; wait for the next code.
4. Sign out and reload: private pages remain inaccessible. Idle sessions expire after 30 minutes, absolute lifetime eight hours.
5. Create a separate support account with `php artisan staff:create SUPPORT_EMAIL --name="Support" --role=support`. Enroll it, then confirm Payments and Settings are hidden and `?view=payments` is restricted. Backend automated checks also verify direct HTTP permission denials.
6. Use Forgot password. In local MAIL_MAILER=log mode, inspect only your private sandbox mailbox:

```powershell
cd "C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO\backend"
Get-ChildItem storage/app/private/staff-mail -Filter *.eml | Sort-Object LastWriteTime -Descending | Select-Object -First 1 | Get-Content
```

Open the reset link privately. Links expire in 30 minutes and work once. Reset revokes sessions and still requires MFA on next sign-in. No email is actually delivered in this local mode; staff messages are kept out of application logs.

## Recovery and configuration

If every second factor is lost, an authorized local operator can run `php artisan staff:recover-mfa YOUR_EMAIL` after independently checking identity. Confirm the prompt. This revokes sessions and factors; the existing password is still required and enrollment starts again. Change a role using `php artisan staff:set-role YOUR_EMAIL support`; changes revoke sessions and the last active owner is protected. Full staff/role management UI is reserved for Phase 10.

The backend example defines STAFF_ADMIN_ORIGIN=http://localhost:5173, STAFF_IDLE_SECONDS=1800 and STAFF_ABSOLUTE_SECONDS=28800. Both frontend examples use http://localhost:8000/api/v1. Use localhost consistently. Do not overwrite existing .env files. Back up APP_KEY with the encrypted database; regenerating it loses access to encrypted authenticator seeds. Production requires HTTPS, secure cookie configuration, a restricted database account, backups and a real mail transport; no production deployment is included.

## Repeatable verification

```powershell
cd backend
composer validate --strict
composer format:check
composer test
cd ../storefront
npm run check
cd ../admin
npm run check
cd ..
npm install --prefix .qa --no-save playwright
# Windows browser suite uses installed Microsoft Edge.
# Linux/macOS: node .qa/node_modules/playwright/cli.js install chromium
node shared/tests/staff-security.cjs
```

Run the final commands from the ELVARQO root. The staff browser suite starts its own services on 8005/5180, creates an isolated database with random test credentials under ignored .qa/phase3, and writes redacted evidence to docs/verification/phase3. Those two ports must be free. It never seeds the development database. Delete the ignored test run directories when no longer needed; they contain private test credentials. The older Phase 2 acceptance.cjs admin checks predate the authentication gate and are historical; use the new suite for current admin acceptance. Manual physical authenticator enrollment and accessibility checks on your devices still complement automation.
