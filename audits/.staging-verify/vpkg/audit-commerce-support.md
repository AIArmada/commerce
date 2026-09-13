### Prior-audit section
### commerce-support
Bugs:
- `src/Support/MoneyNormalizer.php:32` LOW (was MEDIUM) — `toDollars(int): float` display helper only; do not use for persist/calc.
- `src/Support/MoneyNormalizer.php:45` LOW — `format()` returns `formatCurrency()` typed `string`, no `false` check → TypeError on failure.
- `src/Actions/ProcessWebhookCallAction.php:65-69` LOW — failure `update(exception=(string)$e)` outside txn, unbounded (DB bloat / secret leak).
- `src/Models/Tag.php:10` LOW — no `getTable()`, breaks prefix contract.
Security:
- `src/Webhooks/CommerceSignatureValidator.php:71-93` LOW (was MEDIUM) — `hash_equals` + `hmac` correct; no timestamp/nonce/replay, no `sha256=` strip. Base-class standard.
- `src/Models/Report.php:47-56` MEDIUM — `status/reviewed_by_*/timestamps/internal_notes` fillable → forge reviewer + terminal timestamps.
Performance:
- `src/Support/OwnerBatchRunner.php:92-95` MEDIUM — `distinct()->get()` loads all owner tuples; chunk it.
- `src/Support/OwnerBatchRunner.php:113,138` LOW (was MEDIUM) — global `config(include_global)` flip per-run with `finally` restore; correct but Octane-fragile.
- `SeedLanguagesAction:34-52` LOW — per-row `where(code)->first()` + insert/update (seed-only, small N).
