# Orders Audit — DONE (2026-09-08)

## Verdict

The order-management package (`orders` + `filament-orders`) has passed
full review and implementation. The cart bridge is typed with explicit
money mapping, services inject collaborators behind owner asserts, all
six models carry policies, docs flow through one pipeline, carriers
resolve behind a contract, deletes are transactional and guarded,
addresses delegate to the canonical normalizer, and real coverage
(345 tests) pins it — with zero rated findings remaining.

## What was done

- **Typed cart bridge:** `Cart | CartManagerInterface` inputs,
  explicit subtotal/discount/shipping/tax/grand-total mapping,
  nullable session id, owner-guarded customer, no `session()` in
  domain — see `code-fixes-record.md`.
- **DI + owner enforcement:** constructor injection everywhere;
  `AssertsOrderOwnerBoundary` on all mutations; 6/6 policies
  registered; owner default aligned — see `code-fixes-record.md`.
- **Doc pipeline:** `BuildsOrderPdf` deleted into `BuildsOrderDocs`;
  render-only generators — see `code-fixes-record.md`.
- **Carriers:** bound-handler resolution with manual fallback;
  hardcoded J&T reads deleted — see `code-fixes-record.md`.
- **Delete safety:** transactional cascade + paid/final guard with
  force override; cancel/refund documented over delete — see
  `code-fixes-record.md`.
- **Address + config:** canonical delegation with fallback; status
  shadow deleted (Spatie machine sole source); operator strings
  env-overridable — see `code-fixes-record.md`.
- **Routes/widgets/listeners:** invoice throttle + sandbox audit;
  single OwnerCache aggregate (15s TTL); queued inventory bridges;
  conditional health check — see `code-fixes-record.md`.
- **Testing:** pre-existing 287-test suite kept green; transition,
  intake, refund, mapping, and invoice matrices added — see
  `code-fixes-record.md`.
- Suites: Orders 323 passed (725 assertions), FilamentOrders 22
  passed (65 assertions); PHPStan level 6 clean. No migration
  required (NULL-unsafe uniques explicitly deferred).

## Residual notes

- Brand-string *defaults* retained behind env keys (overridable,
  not generic) — recorded as documented, not as debranded.
- Caller updates in `checkout`/`filament-shipping` are a separate
  follow-up (logged dependencies, applied post-conversion).
- NULL-unsafe intake/payment uniques hardened via partial unique
  indexes (`2026_09_11_000002`, guarded/idempotent).

If any residual grows teeth, re-open it as a finding. Full finding history
lives in `migration-record.md`, `code-fixes-record.md`, and git history.
