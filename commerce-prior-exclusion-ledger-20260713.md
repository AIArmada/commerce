# Prior Handoff Exclusion Ledger — 2026-07-13

The following root causes are intentionally absent from the new task queue. A superficially different symptom is also excluded when its intended fix belongs to one of these prior workstreams.

## PX-01 — Verification environment setup

Missing PHP DOM/XML, SQLite/PDO SQLite, Composer/Pest/PHPStan baseline. Already owned by prior ENV/GOV work.

## PX-02 — Repository rule hierarchy discrepancy

Missing `.ai/rules/index.md` versus existing `.ai/guidelines`. Already owned by prior governance work.

## PX-03 — Duplicate Inventory commands from Orders

Payment and cancellation can emit duplicate inventory deduction/release commands. Already owned by prior BUG-INV work.

## PX-04 — Checkout step graph

Caller-visible step sequencing, registry/contributor seam, Events checkout contributor. Already owned by prior C01 work.

## PX-05 — Order intake

Atomic typed Order creation and durable intake idempotency. Already owned by prior C02 work.

## PX-06 — Inventory checkout commitment

Reservation/commit/release lifecycle and Checkout integration. Already owned by prior C03 work.

## PX-07 — Owner access consolidation

Shared owner-policy/deletion-test candidate. Already owned by prior C04 work.

## PX-08 — Promotion/Voucher stacking and commitment

Dead stacking registrar, combined cap, Voucher/Promotion commitment lifecycle. Already owned by prior C05 work.

## PX-09 — Shipment/J&T remote operations

Shipment creation/cancellation uncertainty, idempotency, carrier adapter. Already owned by prior C06 work.

## PX-10 — Checkout finalization

Early/duplicated completion, free-order swallowed failure, shared CreateOrderStep integration. Already owned by prior C07 work.

## PX-11 — Cross-package contract reviews

Order/Inventory/Discount pre- and post-implementation compatibility gates. Already owned by prior CTR tasks.

## PX-12 — Prior tracker and documentation governance

Agent ownership, exact scopes, same-pass documentation, integrated QC. Already owned by prior tracker.

## Operational rule

Run `validate-commerce-full-audit-tracker.py commerce-full-audit-tracker-20260713.yaml --prior-tracker architecture-execution-tracker-20260712.yaml` before claiming work. A new finding may be distinct while still touching a file owned by a prior task. The new YAML records explicit `prior_dependencies` for every detected static overlap; those prior tasks must be done and merged before the new task can be claimed.