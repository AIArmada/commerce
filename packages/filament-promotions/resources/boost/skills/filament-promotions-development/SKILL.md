---
name: filament-promotions-development
description: Develop and integrate filament promotions using owner-safe domain operations.
---

# filament promotions development

Use FilamentPromotionsPlugin for promotion administration. Delegate campaign creation, editing, deletion, deactivation and voucher issuance to Promotions domain actions. Enforce promotion permissions, voucher.create for issuance, and exact owner scope. UI money conversion must preserve integer domain values. Never write another package's tables directly.

## Verification

Run the package architecture checks and affected monorepo Pest, PHPStan and Pint checks. Migrate callers directly and delete superseded code; do not introduce compatibility aliases or backfill migrations.
