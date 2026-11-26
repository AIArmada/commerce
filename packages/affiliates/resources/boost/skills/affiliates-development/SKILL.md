---
name: affiliates-development
description: Develop and integrate the modular Laravel Dune Affiliates package, using owner-scoped feature APIs and canonical operations.
---

# Affiliates development

Use the modular Dune affiliate engine through feature APIs; never import another feature's internal actions, queries or models. Identity owns affiliate profiles, registration and credentials; Attribution owns tracking and touchpoints; Conversions owns conversion accounting and balances; Payouts owns settlement and payout lifecycle; Programs, Commissions, Upline, Fraud, Tax, Support, Reporting and Webhooks own their respective capabilities. Read the feature manifests for exact durable ownership.

- Resolve public affiliates with `AIArmada\\Affiliates\\Features\\Identity\\Api\\AffiliateDirectory`; `findByCode()` is owner scoped and `codeAvailable()` checks global case-insensitive uniqueness without returning foreign records.
- Public signup uses `RegistrationApi::register()`; commission terms are derived from configuration and sponsorship is resolved inside the transaction. Administrative changes use `AffiliateAdministration` and lifecycle APIs.
- Record cart conversions through `CartConversionRecording`; post fixed awards through `AwardPosting`. Never write conversion or balance rows directly from application code.
- Use `PayoutReconciliationQueries::settlementExport()` for settlement rows and their matching summary; do not resolve arbitrary payee relations in exports.
- Payout reconciliation and reversal honor active operation leases; provider I/O stays outside database transactions, and global affiliate accounting re-enters the canonical owner context (including explicit global).
- Amounts are integer minor units with an explicit ISO currency. Scope reads and mutations to the current owner; explicit global context is not equivalent to a missing owner. Do not bypass ownership or use cross-feature ORM relationships.
- Configure a local renderer for private tax worksheets. TINs must be masked and tax documents stored privately. No legacy aliases or backfills.

Verification from the commerce monorepo root:

```bash
composer --working-dir=packages/affiliates arch:check
vendor/bin/phpstan analyse packages/affiliates/src --level=6
vendor/bin/pest --parallel tests/src/Affiliates
```

Run Pint on changed PHP files and keep package architecture exceptions empty.
