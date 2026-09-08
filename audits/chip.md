# Chip Audit — DONE (2026-09-08)

## Verdict

The CHIP gateway package (`chip` + `filament-chip`) has passed full
review and implementation. The configured `Webhook` subclass is the
single `webhook_calls` system of record, purchase amounts are reconciled
end to end with explicit currency and integer quantities, status mapping
is canonical with webhook-event precedence, checkout/customer/doc
sprawl is removed leaving typed payload events, and the collect service
fronts the `*Api` facades — with zero rated findings remaining except
the explicitly held crash-recovery window below.

## What was done

- **Single webhook writer** (configured `Webhook` subclass as SOR,
  owner scoping, vendor migration frozen, `WebhookLogger` deleted) —
  see `code-fixes-record.md`.
- **Amount-trust chain closed** (explicit currency, int quantities,
  product-currency checks, line-item and checkout-total
  reconciliation, response validation) — see `code-fixes-record.md`.
- **Canonical status mapping** with webhook-event precedence; both CHIP
  consumers delegate — see `code-fixes-record.md`.
- **Sprawl removed** (checkout customer/document bridges, listeners,
  support classes deleted; typed `WebhookReceived`/`PurchaseEvent`
  contract retained; generic `ChipCustomerDirectory` kept) — see
  `code-fixes-record.md`.
- Suites: Chip 1049 passed (2723 assertions, 4 skipped),
  FilamentChip 17 passed (73 assertions); PHPStan level 6 clean on
  both source packages.

## Residual notes

- **Crash recovery CLOSED (2026-09-08, was held):** durable
  `PurchaseIdempotencyLedger` (`chip/src/Support/
  PurchaseIdempotencyLedger.php:45`) reserves before the remote post
  and replays after it. Precise semantics (independent re-verification):
  crash between post and record leaves a pending reservation and the
  retry fails closed demanding reconciliation — no double-charge, but
  not transparent replay either. Mutation legs carry deterministic
  operation keys with locking + cached responses (`PurchasesApi.php:238`);
  explicit-blank keys throw at every entrypoint including raw `create()`.
  Null-cache construction bypasses mutation protection, but the
  container always injects a real cache — accepted residual, not a live
  path. All 10 adversarial proofs green; see `code-fixes-record.md`.
- Checkout-side amount assertions and event/status precedence (logged
  as dependencies) were implemented in the same pass —
  `CashierProcessor` fail-closed evidence, `CreateOrderStep` blocking
  reconciliation, paid-wins callback policy; see `checkout.md`
  post-track note. Token TTL (A-7) and CHIP-cluster deletion (A-5)
  remain checkout-track work.
- Checkout/docs/customer subscribers for the new event contract remain
  a logged dependency — no docs/customer files were touched here.

If any residual grows teeth, re-open it as a finding. Full finding history
lives in `migration-record.md`, `code-fixes-record.md`, and git history.
