### Prior-audit section
### products (`aiarmada/products`)
Bugs:
- 10 models `owner_type/id` fillable MEDIUM (was CRITICAL) — fact true, but `Product::booted` creating/updating + `Variant::creating` throw on cross-tenant; defense-in-depth.
- `Models/Variant.php` HIGH — no `updating` guard; `product_id` reassignable post-create.
- `Models/Product.php:813-820` MEDIUM — `deleting` `each(delete)` + detaches, no txn/chunk.
- `UpdateProductStatus.php:65-68` LOW — `transitionToDraft` leaves stale `published_at`.
Security:
- `Variant` no `updating` guard HIGH (above) — security impact: `product_id/owner_*` reassignment post-create.
- Filament `ProductResource:40-44` LOW — `Product::query()->forOwner()` bypasses `parent::getEloquentQuery()`; use `OwnerUiScope::apply(parent::...)`.
Performance:
- Variant cascade 1k+ deletes MEDIUM — chunk or queue.
- `Collection:453` / `Category:229` strip-then-refilter LOW (was MEDIUM) — correctly re-scoped; style risk only.
