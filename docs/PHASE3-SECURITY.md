# Phase 3 architecture and security contract
24 October 2026. User authorizes Phase 3 only. Reviewed plan pp.9â€“10 and design A01/A27/A28; role/audit management interfaces remain Phase 10.

Staff identities, password broker and session storage are physically separate from the existing customer User model/provider. Laravel's staff session guard is authenticated only after mandatory TOTP enrollment/verification. No public staff registration. An interactive CLI bootstraps the owner with a hidden password prompt; no default passwords or pre-created personal accounts.

Browser authentication uses an HttpOnly, SameSite=Strict staff cookie scoped to /api/v1/staff. Production uses Secure cookies; local HTTP is limited to loopback development. Staff requests require the exact configured admin origin when Origin is present, explicit credentialed CORS, and a CSRF token on every unsafe request (including same-origin requests). Tokens are obtained from the staff CSRF endpoint and kept in memory; no bearer tokens or credentials in browser storage. The public health endpoint retains its previous non-credentialed CORS behavior.

Laravel server sessions have a 30-minute idle limit and 8-hour absolute lifetime. Password reset, role change and CLI MFA recovery increment a server-side account version and revoke all staff sessions. Pending MFA challenges expire in five minutes and are invalidated by account-version changes. Session IDs rotate at password acceptance and final MFA authentication; logout destroys the server session and rotates the CSRF token.

Google2FA 9 implements RFC 6238 TOTP. Secrets are encrypted with APP_KEY; accepted time steps are stored under a row lock to prevent replay. A one-step clock drift window is allowed. Ten random 128-bit recovery codes are shown once, stored as SHA-256 hashes, consumed atomically, and expire after one year. Regeneration requires password plus a fresh second factor and invalidates old codes. Protect APP_KEY and keep server/authenticator clocks synchronized.

Named permissions cover catalogue, fulfilment, customer visibility, content, finance, staff, integrations and audit. Owner/operations/support/finance roles are seeded by migration. Laravel independently enforces every protected capability/audit route. Capability endpoints assert authorization only; no fake finance/order/product operations are implemented. Future write endpoints must reuse permission enforcement.

Audit records contain only actor ID, event/action, target identifier, result, generated request ID, keyed IP hash and UTC timestamp. No credentials, TOTP seeds, recovery codes or reset tokens are accepted into audit payloads. The model rejects changes/deletion; SQLite, MySQL and PostgreSQL migrations install update/delete guards. DB administrators can still alter their database; deployment must use a restricted runtime DB account and protected backups for operational immutability.

Password recovery uses Laravel's separate staff password broker with 30-minute hashed, single-use tokens and neutral responses. Password reset preserves MFA, invalidates sessions, and does not sign the user in. In local log-mail mode, staff reset messages go to a PRIVATE sandbox .eml file instead of application logs, so reset bearer URLs do not leak into logs. SMTP/provider configuration is required before real delivery; no external email is sent during local tests.

Login, MFA, reset and sensitive security changes have IP/identity rate limits. Unsafe requests enforce Origin and CSRF. The JSON error envelope distinguishes 401, 403, 419, 422 and 429 without stack traces. API responses and reset UI use no-store/no-referrer. Reset parameters are removed from the address bar immediately.

References: https://laravel.com/framework/docs/authentication ; https://laravel.com/framework/docs/passwords ; https://github.com/antonioribeiro/google2fa . Production release, deployment, full staff management and customer accounts are outside this phase.

Verification: SQLite migration applied; 26 tests / 176 assertions and 17 real-browser grouped checks pass. MySQL/PostgreSQL trigger branches remain untested. See STAFF-ACCESS.md and PROGRESS.md for commands and limits. Google2FA 9.0.0 and constant_time_encoding 3.1.3 are locked.
