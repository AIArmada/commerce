---
name: promotions-development
description: Develop and integrate promotions using owner-safe domain operations.
---

# promotions development

Promotions owns automatic campaigns and promotion targets. Use `AIArmada\Promotions\Runtime\PromotionServiceInterface` for applicability and the concrete CreatePromotion, UpdatePromotion, DeactivatePromotion and DeletePromotion actions for administration. Fixed discounts use integer minor units; promotion percentage values are whole percentages. Voucher issuance converts these into voucher basis points and enforces owner context. Fulfillment must count each order's promotion usage once; order metadata writes belong to Orders. Never directly increment usage from UI or checkout callbacks.

## Verification

Run the package architecture checks and affected monorepo Pest, PHPStan and Pint checks. Migrate callers directly and delete superseded code; do not introduce compatibility aliases or backfill migrations.
