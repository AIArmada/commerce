# aiarmada/rewards

Configurable reward programs for Laravel: recognition, points, badges, cash payouts, and benefit grants with audited entitlements and immutable ledgers.

- Programs are versioned, enrollable, and tenant-scoped through the program boundary.
- Awards move held → approved → fulfilled with frozen policy/consent/source snapshots.
- Money is integer minor units plus an explicit currency; budgets enforce funded ≥ reserved + spent.
- Payout and coupon rails are host-bound contracts with fail-closed manual defaults.

See [CONTEXT.md](CONTEXT.md) and [docs/01-overview.md](docs/01-overview.md) to start, then [docs/04-usage.md](docs/04-usage.md) for the action-level flows.
