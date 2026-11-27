## AIArmada Pricing

Use the Pricing package as the owner of price lists, prices, tier rules and price calculation. Use the public `PriceCalculatorInterface` to quote items, and treat its result DTOs as immutable. Format money at the presentation boundary with `PriceResultFormatter`.

Use `CreatePriceList`, `UpdatePriceList`, `DeletePriceList`, `SavePrice` and `SavePriceTier` for writes. Use `EnsurePriceListBySlug` and `EnsureListPrice` for idempotent provisioning. All mutations must run in the authorized owner context; never bypass owner scopes to expose or alter another tenant's pricing. Global operations require explicit global context when owner scoping is enabled.

Price amounts are integer minor units. Share an effective timestamp and currency when calculating a batch; inactive lists and prices must not participate in resolution.
