# Cashier Audit — DONE (2026-09-08)

## Verdict

The payments multiplexer (`cashier` + `filament-cashier`) has passed full
review and implementation. It is now a thin multiplexer: gateway-backed
reads through canonical clients, real webhook verify+handle on both
gateways, owner-scoped subscription retrieval, request-scoped gateway
clients, minor-unit money construction, and a consolidated exception
taxonomy — with zero tables of its own and no rated findings remaining.

## What was done

- **Fake unified records replaced** with readonly DTOs and
  gateway-backed list reads; fake record models and flat exception
  duplicates deleted — see `code-fixes-record.md`. No migration was
  necessary or created (held proposal: none).
- **Single gateway truth** (`GatewayManager::supportedGateways`) with
  per-driver guards — see `code-fixes-record.md`.
- **CHIP collapse onto canonical contracts** (`CashierChip::chip()`,
  `findBillable`, canonical subscription model; typed 404 detection) —
  see `code-fixes-record.md`.
- **Real webhook verify+handle** on both gateways; conditional
  `WebhookHandled`; empty route endpoint deleted — see
  `code-fixes-record.md`.
- **Owner-scoped subscription lookup** with cross-tenant regression
  coverage — see `code-fixes-record.md`.
- **Octane-safe clients** (fresh clients + driver flush per request) —
  see `code-fixes-record.md`.
- **Minor-unit money** (`Money($amount, $currency, false)`) with
  `RM10.00` golden coverage; cart/inventory keys delegated to
  checkout — see `code-fixes-record.md`.
- Suites: Cashier 255 passed (522 assertions), FilamentCashier 133
  passed (423 assertions); PHPStan level 6 clean on both source
  packages.

## Residual notes

- Thin local CHIP adapters remain because the read-only
  `chip`/`cashier-chip` contracts do not implement cashier's unified
  contracts; every behavior path delegates. If those contracts ever
  grow unified implementations, delete the adapters.
- Crash-recovery window on the CHIP money path (no native gateway key;
  local mechanism only) belongs to the `chip` track, not this package.
- Checkout's duplicated status mapper/payload builder and missing
  amount reconciliation belong to the `checkout` track.
- `cashier-chip` / `chip` audits remain Open.

If any residual grows teeth, re-open it as a finding. Full finding history
lives in `migration-record.md`, `code-fixes-record.md`, and git history.
