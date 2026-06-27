# Repository Inventory

**Total packages discovered:** 58 (57 under `packages/` + 1 under `demo/`)

**Workspace mechanism:** Composer PSR-4 autoload with `path` repository entries only for `authz`, `commerce-support`, `communications`, `filament-communications`, and `membership`. The remaining packages are resolved through autoload mapping and monorepo-builder configuration.

**All packages are PHP libraries for the AIArmada Commerce ecosystem, targeting Laravel with Filament v5 admin panels.**

---

## Package list

### Domain core packages

| # | Directory | Composer name | Namespace | Version | Declared in repos? |
|---|-----------|---------------|-----------|---------|--------------------|
| 1 | addressing | aiarmada/addressing | AIArmada\Addressing | none | No |
| 2 | affiliate-network | aiarmada/affiliate-network | AIArmada\AffiliateNetwork | none | No |
| 3 | affiliates | aiarmada/affiliates | AIArmada\Affiliates | none | No |
| 4 | authz | aiarmada/authz | AIArmada\Authz | none | Yes |
| 5 | cart | aiarmada/cart | AIArmada\Cart | none | No |
| 6 | cashier | aiarmada/cashier | AIArmada\Cashier | none | No |
| 7 | cashier-chip | aiarmada/cashier-chip | AIArmada\CashierChip | none | No |
| 8 | checkout | aiarmada/checkout | AIArmada\Checkout | none | No |
| 9 | chip | aiarmada/chip | AIArmada\Chip | none | No |
| 10 | commerce-support | aiarmada/commerce-support | AIArmada\CommerceSupport | none | Yes |
| 11 | communications | aiarmada/communications | AIArmada\Communications | none | Yes |
| 12 | contacting | aiarmada/contacting | AIArmada\Contacting | none | No |
| 13 | csuite | aiarmada/commerce | — | none | No |
| 14 | customers | aiarmada/customers | AIArmada\Customers | none | No |
| 15 | docs | aiarmada/docs | AIArmada\Docs | none | No |
| 16 | engagement | aiarmada/engagement | AIArmada\Engagement | none | No |
| 17 | events | aiarmada/events | AIArmada\Events | none | No |
| 18 | feedback | aiarmada/feedback | AIArmada\Feedback | none | No |
| 19 | growth | aiarmada/growth | AIArmada\Growth | none | No |
| 20 | inventory | aiarmada/inventory | AIArmada\Inventory | none | No |
| 21 | jnt | aiarmada/jnt | AIArmada\Jnt | none | No |
| 22 | membership | aiarmada/membership | AIArmada\Membership | none | Yes |
| 23 | moderation | aiarmada/moderation | AIArmada\Moderation | none | No |
| 24 | orders | aiarmada/orders | AIArmada\Orders | none | No |
| 25 | pricing | aiarmada/pricing | AIArmada\Pricing | none | No |
| 26 | products | aiarmada/products | AIArmada\Products | none | No |
| 27 | promotions | aiarmada/promotions | AIArmada\Promotions | none | No |
| 28 | references | aiarmada/references | AIArmada\References | none | No |
| 29 | shipping | aiarmada/shipping | AIArmada\Shipping | none | No |
| 30 | signals | aiarmada/signals | AIArmada\Signals | none | No |
| 31 | tax | aiarmada/tax | AIArmada\Tax | none | No |
| 32 | vouchers | aiarmada/vouchers | AIArmada\Vouchers | none | No |

### Filament adapter packages

| # | Directory | Composer name | Namespace | Version | Declared in repos? |
|---|-----------|---------------|-----------|---------|--------------------|
| 33 | filament-addressing | aiarmada/filament-addressing | AIArmada\FilamentAddressing | none | No |
| 34 | filament-affiliate-network | aiarmada/filament-affiliate-network | AIArmada\FilamentAffiliateNetwork | none | No |
| 35 | filament-affiliates | aiarmada/filament-affiliates | AIArmada\FilamentAffiliates | none | No |
| 36 | filament-authz | aiarmada/filament-authz | AIArmada\FilamentAuthz | none | No |
| 37 | filament-cart | aiarmada/filament-cart | AIArmada\FilamentCart | none | No |
| 38 | filament-cashier | aiarmada/filament-cashier | AIArmada\FilamentCashier | none | No |
| 39 | filament-cashier-chip | aiarmada/filament-cashier-chip | AIArmada\FilamentCashierChip | none | No |
| 40 | filament-chip | aiarmada/filament-chip | AIArmada\FilamentChip | none | No |
| 41 | filament-commerce-support | aiarmada/filament-commerce-support | AIArmada\FilamentCommerceSupport | none | No |
| 42 | filament-communications | aiarmada/filament-communications | AIArmada\Filament\Communications | none | Yes |
| 43 | filament-contacting | aiarmada/filament-contacting | AIArmada\FilamentContacting | none | No |
| 44 | filament-customers | aiarmada/filament-customers | AIArmada\FilamentCustomers | none | No |
| 45 | filament-docs | aiarmada/filament-docs | AIArmada\FilamentDocs | none | No |
| 46 | filament-engagement | aiarmada/filament-engagement | AIArmada\FilamentEngagement | none | No |
| 47 | filament-events | aiarmada/filament-events | AIArmada\FilamentEvents | none | No |
| 48 | filament-feedback | aiarmada/filament-feedback | AIArmada\FilamentFeedback | none | No |
| 49 | filament-growth | aiarmada/filament-growth | AIArmada\FilamentGrowth | none | No |
| 50 | filament-inventory | aiarmada/filament-inventory | AIArmada\FilamentInventory | none | No |
| 51 | filament-jnt | aiarmada/filament-jnt | AIArmada\FilamentJnt | none | No |
| 52 | filament-orders | aiarmada/filament-orders | AIArmada\FilamentOrders | none | No |
| 53 | filament-pricing | aiarmada/filament-pricing | AIArmada\FilamentPricing | none | No |
| 54 | filament-products | aiarmada/filament-products | AIArmada\FilamentProducts | none | No |
| 55 | filament-promotions | aiarmada/filament-promotions | AIArmada\FilamentPromotions | none | No |
| 56 | filament-shipping | aiarmada/filament-shipping | AIArmada\FilamentShipping | none | No |
| 57 | filament-signals | aiarmada/filament-signals | AIArmada\FilamentSignals | none | No |
| 58 | filament-tax | aiarmada/filament-tax | AIArmada\FilamentTax | none | No |
| 59 | filament-vouchers | aiarmada/filament-vouchers | AIArmada\FilamentVouchers | none | No |

### Demo

| # | Directory | Composer name | Version |
|---|-----------|---------------|---------|
| 60 | demo | aiarmada/commerce-demo | none |

---

## Notable observations

- **None of the 58 packages have a declared version** (all show "none" or `*`). This means all are implicitly `dev-*` and treated as unstable.
- **Only 5 packages** are declared as Composer path repositories (`authz`, `commerce-support`, `communications`, `filament-communications`, `membership`). The remaining 52 packages are resolved via PSR-4 autoload mapping in the root `composer.json` only — they are not individually discoverable as Composer packages without the monorepo-builder.
- **Namespace inconsistency:** `filament-communications` uses `AIArmada\Filament\Communications` (3-segment) while all other filament-* packages use `AIArmada\FilamentXxx` (2-segment like `AIArmada\FilamentCart`).
- The root package name is `aiarmada/commerce` with description "A powerful collection of commerce components for Laravel".
- `csuite/composer.json` has name `aiarmada/commerce` — same as root. This is likely a mistake or placeholder.
- All packages target PHP ^8.4.
- All filament-* packages depend on Filament v5 APIs.
- The monorepo relies on `symplify/monorepo-builder` for release coordination.
- Tests are stored in `tests/src/` (not in individual packages), mapped via PSR-4 per package.

## Undeclared packages

52 packages are not individually declared as Composer path repositories. They function as internal libraries within the monorepo's autoloading but are not independently installable without monorepo-builder publishing.
