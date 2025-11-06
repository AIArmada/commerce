# AIArmada Cart Core Package

This package contains the core implementation that powers **AIArmada Cart**. It is published as `aiarmada/cart` on Packagist and intended for Laravel 12 applications seeking a feature-rich shopping cart engine.

## Highlights

- 📦 **Pluggable storage** – session, cache, and database drivers that implement a shared interface.
- 🛒 **Composable cart architecture** – traits manage items, totals, metadata, and conditions.
- 🔁 **Multi-instance by default** – support any number of cart buckets per user or session.
- 🧮 **Accurate calculations** – Akaunting\Money integration for precise totals and rounding.
- 📈 **Observability hooks** – metrics, conflict tracking, and per-operation performance measurements.

## Documentation

The full project documentation lives in the repository root under [`docs/`](../../docs/index.md). Start with:

- [Getting Started](../../docs/getting-started.md)
- [Cart Operations](../../docs/cart-operations.md)
- [Configuration Reference](../../docs/configuration.md)
- [Conditions & Discounts](../../docs/conditions.md)

## JSON vs JSONB (PostgreSQL)

Migrations default to portable `json` columns. To opt into `jsonb` on a fresh install, set one of the following BEFORE running migrations:

```env
COMMERCE_JSON_COLUMN_TYPE=jsonb
# or per-package override
CART_JSON_COLUMN_TYPE=jsonb
```

Or run the interactive setup:

```bash
php artisan commerce:configure-database
```

When using PostgreSQL + `jsonb`, GIN indexes are created automatically on `items`, `conditions`, and `metadata`.

## Local Development

Clone the monorepo and install dependencies:

```bash
composer install
vendor/bin/pest
```

When editing code:

- Keep strict types and typed signatures.
- Honour `StorageInterface` when extending storage implementations.
- Update or add tests for behavioural changes.
- Format using `vendor/bin/pint --dirty`.

## Testing

Run the unit suite for the core package:

```bash
vendor/bin/pest tests/Unit
```

Use Pest filters (`--group`, `--filter`) to target specific areas.

## Contributing

Please review the [Laravel Cart Coding Guidelines](../../.ai/guidelines/cart.blade.php) before submitting PRs. Contributions from the community are welcome—issues, bug reports, and feature suggestions keep the project healthy.

## License

AIArmada Cart is released under the [MIT license](../../LICENSE).
