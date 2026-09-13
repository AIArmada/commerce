### Prior-audit section
### pricing
Bugs:
- Dual `is_active` + `deactivated_at` drift LOW — no single transition helper.
- No `amount>=0 / min_quantity>=1 / starts<=ends` validation MEDIUM.
Security: clean — owner auto-assign + cross-owner throw + `customer_id/segment_id` scoped `exists()`.
Performance: `PriceCalculator:86-205` MEDIUM — customer→segment→tier→promo→list fan-out (~100+ queries on 50-line cart, directionally); no batch preload. `clearOtherDefaults:342` bulk `update` GOOD.
