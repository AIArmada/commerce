# Audit Report: `cart`

**Package:** `aiarmada/cart`  
**Surface:** domain  
**Family:** checkout-flow  
**Audited:** 2026-06-27 — Commit `7d1dc95fa`  

---

## Purpose

Cart foundation: persistence (database-backed), items, conditions, metadata, dynamic conditions, login migration, event lifecycle, and owner-aware scoping.

## Architecture — PASS

Complex, well-structured package with clear separation:
- **Core:** `Cart` (value object with 10 traits), `CartManager` (scoped singleton via `app('cart')`)
- **Models (3):** `CartModel`, `CartItem` (readonly DTO), `Condition` (reusable condition config)
- **Storage:** `StorageInterface` → `DatabaseStorage`, `InMemoryStorage` (testing)
- **Conditions subsystem:** `CartCondition`, `ConditionProviderRegistry`, `ConditionPipeline`, `ConditionTarget`, dynamic conditions, condition type handlers
- **Services (6):** `CartConditionResolver`, `CartFactory`, `CartMergeStrategyRegistry`, `CartMigrationService`, `BuiltInRulesFactory`, `RulePresets`
- **Contracts (8):** `BuyableInterface`, `CartManagerInterface`, `CartMergeStrategyInterface`, `CartValidatorInterface`, `ConditionProviderInterface`, `RulesFactoryInterface`, etc.
- **Events (16):** Item add/remove/update, condition add/remove, metadata add/clear/remove, cart create/destroy/clear/merge
- **Traits (10):** Manage items, conditions, metadata, buyables, storage, instances, totals, events, dynamic conditions, lazy pipeline
- **Actions (2):** `MigrateGuestCartToUserAction`, `MigrateCartOnLoginAction`
- **Other:** 6 exceptions, 2 enums, 2 collections, 2 listeners, 1 command

## Implementation Quality — PASS

- UUID PKs, config-driven table names
- `CartItem` is a `readonly class` — good DTO practice, immutable after creation
- Money in minor units (price as `int` cents)
- Optimistic locking via `version` column
- Owner scoping with `nullableUuidMorphs('owner')`
- GIN indexes for JSONB on PostgreSQL, composite indexes for MySQL
- `$withinTransaction = false` on migration (safe index creation)
- Octane-safe: `ConditionPresets::restoreOctaneDefaults()` on `RequestReceived`, `rememberOctaneDefaults()` on boot
- Config validation at boot (`ValidatesConfiguration` trait)
- `CartManager::forOwner()` creates scoped storage for admin operations
- `Cart::setIdentifier()` is immutable — returns new Cart instance
- Event system with enable/disable toggle

## Security — PASS

- Owner scoping with `nullableUuidMorphs('owner')` and `HasOwner` trait
- `CartManager::resolveIdentifier()` falls back auth→session, throws on neither
- `Limits` config: max items (1000), max quantity (10000), max data size (1MB), max string length (255)
- Empty cart behavior configurable: `destroy`, `clear`, `preserve`
- Merge strategies configurable via `CartMergeStrategyRegistry`

## Tests — PASS

**67+ test files** under `tests/src/Cart/Feature/` (22 files) and `tests/src/Cart/Unit/` (45+ files) covering:
- Core operations (add, remove, update, clear, destroy, instances)
- Conditions (static, dynamic, providers, pipeline, targets, shipping)
- Metadata (add, batch, remove, clear)
- Migration (guest→user, login migration, swap)
- Events (all 16 event types)
- Database storage (persistence, locking, TTL)
- Models (CartModel, CartItem, Condition, CartCondition)
- Traits (all 10 trait files)
- Contracts, helpers, services, exceptions
- Performance indexes migration

## Documentation — PASS

11 doc files covering overview, installation, configuration, usage, conditions, dynamic conditions, events, storage, multi-tenancy, API reference, and troubleshooting. Well-structured and complete.

## Issues Found

**Minor:**
1. `CartManager::forOwner()` uses `@phpstan-ignore-next-line` (line 164) — the `new static()` call could be clearer about its intent.
2. Empty `src/Models/Concerns/` directory — scaffolding not cleaned.
3. `Cart::__construct()` has 9 optional constructor parameters with inline `app()` fallbacks — makes testing slightly harder (though InMemoryStorage exists).

## Final Status

**Ready.** High-quality package with thorough testing, good architecture, solid security, and complete documentation.

## Summary

| Category | Result |
|----------|--------|
| Purpose | Clear |
| Architecture | Well-structured, extensible condition system |
| Implementation | Solid, immutable CartItem, DB indexing |
| Security | Owner scoping + limits + merge guard |
| Tests | 67+ files, excellent coverage |
| Docs | 11 files, thorough |
