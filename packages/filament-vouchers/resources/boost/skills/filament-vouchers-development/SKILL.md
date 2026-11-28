---
name: filament-vouchers-development
description: Develop and integrate filament vouchers using owner-safe domain operations.
---

# filament vouchers development

Use the FilamentVouchersPlugin for voucher administration. Delegate mutations to registered Vouchers runtime interfaces, enforce voucher permissions and scope reads to the current owner. Do not hardcode host application models. Stacking and targeting configuration pages reflect application configuration; change configuration through its canonical config files, not through an unsaved form. Use integer minor units and basis points at domain boundaries; convert display amounts only at the UI edge.

## Verification

Run the package architecture checks and affected monorepo Pest, PHPStan and Pint checks. Migrate callers directly and delete superseded code; do not introduce compatibility aliases or backfill migrations.
