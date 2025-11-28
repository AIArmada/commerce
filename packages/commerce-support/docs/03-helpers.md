# Helpers & Traits

Commerce Support provides helper functions and traits for common tasks.

## Helper Functions

### commerce_json_column_type()

Resolve JSON column type for migrations. Supports PostgreSQL JSONB for better performance.

```php
// In migrations
Schema::create('carts', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->{commerce_json_column_type()}('metadata');
    $table->{commerce_json_column_type()}('items');
});
```

### Per-Package Override

Each package can have its own JSON column type:

```php
// Uses VOUCHERS_JSON_COLUMN_TYPE env var if set
$table->{commerce_json_column_type('vouchers')}('conditions');
```

### Environment Variables

| Variable | Description |
|----------|-------------|
| `COMMERCE_JSON_COLUMN_TYPE` | Global setting (`json` or `jsonb`) |
| `{PACKAGE}_JSON_COLUMN_TYPE` | Per-package override (e.g., `VOUCHERS_JSON_COLUMN_TYPE`) |

### Fallback Chain

1. Package-specific env var (e.g., `VOUCHERS_JSON_COLUMN_TYPE`)
2. Global env var (`COMMERCE_JSON_COLUMN_TYPE`)
3. Default: `json`

### PostgreSQL JSONB

For PostgreSQL users, set in `.env`:

```env
COMMERCE_JSON_COLUMN_TYPE=jsonb
```

Benefits of JSONB:
- Faster querying with GIN indexes
- Smaller storage footprint
- Native operators for containment, existence checks

## Traits

### ValidatesConfiguration

Runtime configuration validation for service providers.

```php
use AIArmada\CommerceSupport\Traits\ValidatesConfiguration;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class MyServiceProvider extends PackageServiceProvider
{
    use ValidatesConfiguration;

    public function boot(): void
    {
        parent::boot();

        // Validate required config keys exist
        $this->validateConfiguration('chip', ['api_key', 'brand_id']);
    }
}
```

### Behavior

- **Production**: Throws `RuntimeException` if required keys are missing
- **Non-Production**: Skips validation unless `{config}.validate_config` is `true`

### Example Config

```php
// config/chip.php
return [
    'validate_config' => env('CHIP_VALIDATE_CONFIG', false),
    'api_key' => env('CHIP_API_KEY'),
    'brand_id' => env('CHIP_BRAND_ID'),
];
```

### Error Message

When validation fails:

```
RuntimeException: Required configuration key [chip.api_key] is not set.
Please publish the configuration file with: php artisan vendor:publish --tag=chip-config
```

## Setup Command

Interactive setup wizard for configuring Commerce packages.

```bash
php artisan commerce:setup
```

### Options

```bash
php artisan commerce:setup --force
```

- `--force`: Overwrite existing environment variables

### Configures

1. **CHIP Payment Gateway**
   - Brand ID
   - Secret Key
   - Webhook URL
   - Mode (sandbox/production)

2. **J&T Express Shipping**
   - API Key
   - API URL
   - Customer Code

3. **Database Settings**
   - JSON column type (PostgreSQL JSONB support)

### Example Session

```
AIArmada Commerce Setup Wizard

? Configure CHIP payment gateway? Yes
? CHIP Brand ID: your-brand-id
? CHIP Secret Key: ********
? CHIP Webhook URL: https://example.com/webhooks/chip
? Set CHIP mode? Yes
? Use production mode? No

? Configure J&T Express shipping? No

? Configure Commerce database settings? Yes
? Are you using PostgreSQL? Yes
? Use JSONB instead of JSON? Yes

Added CHIP_BRAND_ID
Added CHIP_SECRET_KEY
Added CHIP_WEBHOOK_URL
Added CHIP_MODE
Added COMMERCE_JSON_COLUMN_TYPE

Commerce configuration completed successfully!
Remember to run: php artisan migrate
```
