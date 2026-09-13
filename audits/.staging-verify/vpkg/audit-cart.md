### Prior-audit section
### cart
Bugs:
- `DatabaseStorage:683-724` HIGH — CAS insert `first()` + bare `insert()`, no 23000 retry on `(owner_scope,identifier,instance)` unique.
- `MigrateGuestCartToUserAction:107-125,184-214` HIGH — putItems/putConditions/putMetadata + markMerged + forget, no txn → duplicates → double-checkout.
- `CartModel:161-165` MEDIUM — `markAsConverted()` no version/lock, no already-converted guard.
- `Condition:227,404-411` MEDIUM (was HIGH "undocumented") — `"+5"`=5¢ vs `"+5.00"`=500¢ IS in docblock `:399-403`; unit-cliff concern, not undocumented.
- `ApplyStoredCondition:64-118` MEDIUM-HIGH (was HIGH) — `applyCustom()` no allowlist/range on `name/type/target/value/order` (rules `factory_keys` allowlisted `:78-81`).
- `DatabaseStorage:352-373` MEDIUM — `swapIdentifier()` unconditional target wipe; `has()` outside txn.
Security:
- `NormalizedCartSynchronizer:135-210` pattern note LOW (was MEDIUM exploit) — child `where(cart_id)->delete()/upsert()` unscoped, but parent resolved `forOwner(:51)` + `deleteNormalizedCart` re-scopes `:126-163`; not exploitable as stated. Harden with explicit scope.
- Abandoned-cart raw deletes safe only via owner iteration — `ClearAbandonedCartsCommand:90-94,311+` iterates tuples + `withOwner` per batch.
Performance:
- 3+ round-trips per cart MEDIUM (narrowed) — true for snapshot path (`save` + items `upsert:210` + conditions `upsert:277`); primary `carts` storage single-row JSON.
- CAS spin no backoff MEDIUM — `handleCasConflict:732-745` throws, no retry.
- DONE (2026-09-13, §8 item 8) — Missing `(identifier,instance,version)` composite LOW. Fixed: kept additive (`2026_09_12_162447_add_cas_lookup_index_to_carts_table.php`).

### Prior-audit fix-first rows
| 34 | cart | `Storage/DatabaseStorage.php:683-724` | CAS insert race, no duplicate-key retry | HIGH |
| 35 | cart | `Actions/MigrateGuestCartToUserAction.php:107-125,184-214` | Non-atomic migration → duplicated carts | HIGH |
| — | cart | `CheckoutService:51-71` gap | No owner assertion on `startCheckout` (MEDIUM, kept near queue) | MEDIUM |

### Migration-batch rows (§8, code may already be fixed)
| 8 | cart `(identifier, instance, version)` composite (§4) | Kept additive (`2026_09_12_162447_*`) | `packages/cart/docs/08-storage.md` |
