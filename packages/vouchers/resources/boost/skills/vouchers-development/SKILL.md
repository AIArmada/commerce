---
name: vouchers-development
description: Develop and integrate vouchers using owner-safe domain operations.
---

# vouchers development

Lifecycle owns vouchers, usage records and lookup caching. Wallet owns wallet records; CartIntegration owns reservation caching and cart integration. Use `AIArmada\Vouchers\Runtime\VoucherServiceInterface` or the Voucher facade for business operations. Use the Lifecycle `Api\VoucherBatchIssuer` for batch issuance and the registered Runtime interfaces for other lifecycle and wallet mutations; do not import private feature actions. `Data\VoucherData` is an immutable snapshot with `Data\VoucherType` and `Data\VoucherStatus` enums. Use canonical snake_case array keys, integer minor units for money, and integer basis points for percentages. Resolve owner context explicitly for mutations. Usage recording enforces assignee, currency, validity, manual redemption permission, usage limits and idempotency. Never treat reservation cache entries as committed usage. Usage history returns a list of immutable `Data\VoucherUsageData` snapshots, newest first, with a default limit of 100 and a maximum of 1,000. It does not return Eloquent models or lazy-load redeemers.


Cart condition extensions are declared in the CartIntegration feature manifest and registered through the package extension registry. Dynamic voucher rule metadata must contain both `voucher_code` and `voucher_id`; use the validated voucher snapshot as the canonical identity. Never re-resolve an already selected voucher by code alone. Checkout currency must match voucher currency, and explicitly malformed eligibility rules fail closed.

## Verification

Run the package architecture checks and affected monorepo Pest, PHPStan and Pint checks. Migrate callers directly and delete superseded code; do not introduce compatibility aliases or backfill migrations.
