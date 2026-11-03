# Payment provider decision

Updated 2026-10-28. The merchant approved Wise bank-transfer checkout with manual staff receipt confirmation, replacing Stripe for new checkouts. This supersedes provider choices in earlier phase documents and supplied plans.

Implementation is complete. New checkout uses PAYMENT_PROVIDER=wise_bank_transfer. The merchant requested receiving details remain pending, so WISE_ENABLED=false and checkout is disabled in development until verified GBP details are added locally. No real transfer has been verified.

Customers receive an order-specific amount, reference and deadline. Only an authenticated, MFA-verified owner with finance.confirm can record an exact settled Wise receipt. The application does not call Wise, generate payment links or automatically verify receipts. No API token is required. Card, Apple Pay and Google Pay are not offered by this checkout.

Historical Stripe sandbox orders retain their adapter; Wise orders cannot be settled by Stripe callbacks. The local refund simulator rejects Wise-funded orders. Real refunds and exchange top-up collection remain deferred. Tax/delivery policies, external email and courier integrations retain their existing test/local limitations.

Configuration, acceptance steps and verification evidence: [Wise checkout](WISE-CHECKOUT.md). Phase 9 has not started.
