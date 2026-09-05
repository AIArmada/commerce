---
title: Cart Context
package: cart
status: current
surface: domain
family: checkout-flow
keywords:
  - cart
  - basket
  - cart-items
  - conditions
  - abandonment
  - migration
---

# Cart Context

## Snapshot
- Composer: `aiarmada/cart`
- Role: Cart persistence: items, conditions, metadata, migration on login, owner-aware storage.
- Triggers: cart, basket, cart-items, conditions, abandonment, migration
- Search first: `src/Models, src/Services, src/Actions, config, docs`
- Related: `filament-cart`, `checkout`, `signals`, `vouchers`
- Paired: `filament-cart` (Filament admin adapter)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../filament-cart/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Owns models, actions, services, events, calculations, and persistence rules.
- If admin UI changes too, audit `filament-cart`.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Cart CRUD, totals/conditions, guest→user migration.
- Skip when: Checkout orchestration or order lifecycle — see checkout/orders.
- Owner/security: Owner-aware (Condition, HasCartOwner); migrate guest carts on login.

## Key surfaces
- Models: `CartItem`, `CartModel`, `Condition`
- Actions/Services: `Actions/MigrateCartOnLoginAction`, `Actions/MigrateGuestCartToUserAction`, `Services/BuiltInRulesFactory`, `Services/CartConditionResolver`, `Services/CartFactory`, `Services/CartMergeStrategyRegistry`, `Services/CartMigrationService`, `Services/RulePresets`
- Config `cart.php`: `database`, `json_column_type`, `table`, `conditions_table`, `ttl`, `lock_for_update`, `money`, `default_currency`, `rounding_mode`, `empty_cart_behavior`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: `05-conditions.md`, `06-dynamic-conditions.md`, `07-events.md`, `08-storage.md`, `09-multi-tenancy.md`, `10-api-reference.md`
