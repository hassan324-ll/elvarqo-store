# Wise bank-transfer checkout implementation

User approved manual Wise bank-transfer checkout on 2026-10-28. Receiving details remain pending by request. No API token, payment-link generation, card checkout, automatic bank reconciliation or live refund automation is part of this implementation.

## Contract

New checkouts use PAYMENT_PROVIDER=wise_bank_transfer. WISE_ENABLED and valid local account holder/sort-code/account-number configuration are required before order creation. Configuration defaults to disabled and contains no invented bank details. Each order stores its provider and encrypted receiving-instruction snapshot, so later edits cannot silently redirect existing payments. Historical Stripe orders retain their provider and can continue through the existing sandbox adapter; Wise orders never invoke it.

Checkout reuses immutable server pricing, guest bag capability, revision/quote checks, transaction locks and inventory reservation. Public reads/resume require the same owned bag. Awaiting bank transfer never implies paid. Payment window defaults to 24 hours; visible deadline and instructions tell the customer not to pay after expiry. Resume/maintenance expire unpaid Wise orders and release stock exactly once. Late transfers require separate staff review and a fresh stock check, rather than automatically confirming a released reservation.

Owner-only finance.confirm permission gates the new staff action (existing staff session/MFA/Origin/CSRF remain required). Staff checks the settled Wise receipt outside the app, then submits the exact GBP amount, observed order payment reference and unique Wise transaction ID, with explicit acknowledgement. Operation IDs and request fingerprints make exact retries safe; transaction IDs cannot fund two orders. Confirmation atomically commits reserved stock, marks the order paid/confirmed, clears its active bag and queues the existing private confirmation copy. Shipment preparation remains blocked until paid. This is staff-attested receipt, not an API verification claim.

The existing refund simulator is blocked for Wise-funded orders to prevent a simulated refund being mistaken for movement of real bank funds. Live Wise refunds remain manual and need separate reconciliation functionality. Delivery/tax rates and external email/carrier integrations remain their existing test-only arrangements.

## References checked

- https://wise.com/help/articles/2935927/how-do-i-receive-money-with-my-gbp-account-details — GBP receiving details and payment routes.
- https://docs.wise.com/guides/developer/auth-and-security/personal-api-token — limited personal-token access; this implementation makes no Wise API calls.

## Current status and local configuration

Implemented and verified on 2026-10-28. The merchant requested receiving details remain pending. Development checkout is therefore disabled: WISE_ENABLED=false and no receiving details have been invented. No real transfer has been verified.

When ready, edit the existing backend/.env locally (preserve APP_KEY and all existing settings):

```dotenv
PAYMENT_PROVIDER=wise_bank_transfer
WISE_ENABLED=false
WISE_ACCOUNT_HOLDER="Your exact verified GBP account holder name"
WISE_SORT_CODE=your-six-digit-sort-code
WISE_ACCOUNT_NUMBER=your-eight-digit-account-number
WISE_PAYMENT_WINDOW_HOURS=24
```

Replace the descriptive placeholders with the exact GBP receiving details from your Wise Business account. These values stay in ignored .env; do not commit them. No Wise API token is used. Leave WISE_ENABLED=false until the details have been checked. Set it to true only when you are ready to accept bank-transfer orders. This enables order instructions, not automatic receipt verification. Existing tax/delivery rates remain test-only, so this is not production launch approval.

In PowerShell, from the project folder:

```powershell
cd backend
php artisan migrate
php artisan config:clear
```

Restart the API and any running queue/scheduler processes after configuration changes. Run the normal applications in separate terminals as documented in README.md: backend on localhost:8000, storefront on localhost:3000 and admin on localhost:5173. XAMPP is unnecessary for SQLite.

Keep these additional backend terminals running:

```powershell
# Terminal 4, in backend
php artisan schedule:work
```

```powershell
# Terminal 5, in backend
php artisan queue:work --tries=3 --timeout=60
```

The scheduler runs commerce:wise-maintenance every minute. A one-off expiry check is also available:

```powershell
php artisan commerce:wise-maintenance
```

## Manual local acceptance

1. With receiving details pending, add a published, stocked product to a bag and open checkout. It should explain setup is pending and disable placing an order. No order or stock reservation is created.
2. Once verified receiving details are configured and enabled locally, complete a GBP/UK checkout. The confirmation page shows the exact amount, frozen receiving details, unique order payment reference and deadline. The order remains awaiting bank transfer and stock is reserved.
3. Sign into admin as the owner, complete MFA and open Orders. Open the unpaid Wise order. Check the actual settled receipt in Wise separately before using Confirm Wise payment received.
4. Enter the exact amount, reference and unique Wise transaction ID; acknowledge that you checked Wise. Mismatches are rejected. Successful confirmation marks paid, commits stock once and enables the existing fulfilment flow. Refreshing the customer's payment status shows staff-confirmed receipt. There is no customer self-confirmation button.
5. A support/operations/finance account without finance.confirm cannot confirm a Wise receipt. The permission is assigned to owner only by this migration.
6. Unpaid orders expire at their deadline. Resume or maintenance releases their stock once and hides payment instructions. If money was already sent, contact staff for separate review; do not pay twice. Late confirmation is rejected after reservation expiry.

Do not use production money to simulate a receipt. Automated coverage uses isolated databases and synthetic TEST ONLY bank details; these are not your receiving account. Real end-to-end transfer verification remains pending.

## Verification and remaining work

- Backend: 167 tests passed, 1,483 assertions, including 9 Wise tests. Run php artisan test from backend. PHPUnit explicitly selects the legacy provider for historical adapter tests; Wise tests select Wise explicitly.
- Backend formatting: PHP Pint passed. Both React apps passed formatting, lint, type checking and production builds.
- Seven isolated production-browser acceptance groups passed: disabled setup, awaiting instructions/reservation, guest isolation, mismatch rejection, exact owner confirmation, support restrictions and expiry/mobile layout. No browser page errors or real payments.
- Additive migration applied to the existing development database. A private pre-migration backup was made; 58 non-runtime business tables were verified unchanged. Owner receives only the added finance.confirm permission. No QA receipts were inserted into development.
- Receiving details and a real transfer test remain pending by merchant request. No external emails are sent: confirmation copies remain private local files processed by the existing queue. Real courier integration and approved live tax/delivery rates remain pending.
- Wise orders cannot use the local refund simulator. Real bank refunds require separate manual handling/reconciliation. Exchange top-up collection remains deferred; new exchange orders carry an explicit non-Stripe funding method.
- Phase 9 has not started. Earlier Phase 6 Stripe instructions describe historical sandbox work; this document supersedes their provider choice for new checkout.
