# AIArmada Filament Vouchers Plugin

A comprehensive Filament v5 plugin that provides a futuristic admin experience for managing vouchers powered by the `aiarmada/vouchers` package. It ships with rich resources, usage analytics, manual redemption workflows, and optional deep links to the Filament Cart plugin.

[![Packagist](https://img.shields.io/packagist/v/aiarmada/filament-vouchers.svg?style=flat-square)](https://packagist.org/packages/aiarmada/filament-vouchers)
[![Tests](https://img.shields.io/github/actions/workflow/status/aiarmada/filament-vouchers/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/aiarmada/filament-vouchers/actions)

---

## Features

- 🎟️ **Voucher Management** – Create, edit, activate, and manage vouchers with rich UI
- 📊 **Usage Analytics** – Track redemptions, discount totals, and usage patterns
- 👥 **Multi-Staff Support** – Owner resolution for marketplace and SaaS scenarios
- 🏪 **Multi-Store Support** – Store-specific vouchers for marketplace platforms
- 🔄 **Manual Redemption** – Admin-initiated redemption workflow
- 🛒 **Cart Integration** – Optional deep links to cart snapshots (when filament-cart installed)
- 📈 **Dashboard Widget** – Real-time statistics and metrics
- ⚡ **Bulk Operations** – Activate, deactivate, expire multiple vouchers at once
- 🔐 **Authorization** – Policy-based access control for granular permissions
- 🎯 **Condition Targeting** – Configure scope/phase/application via preset or DSL for each voucher

---

## Requirements

- PHP ^8.4
- Laravel ^12.0
- Filament ^5.0
- aiarmada/vouchers ^0.1

---

## Installation

```bash
composer require aiarmada/filament-vouchers
```

Register the plugin inside your Filament panel provider:

```php
use AIArmada\FilamentVouchers\FilamentVouchers;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...existing configuration
        ->plugins([
            FilamentVouchers::make(),
        ]);
}
```

### Optional Configuration

Publish the configuration to customize navigation, owner selectors, and defaults:

```bash
php artisan vendor:publish --tag=filament-vouchers-config
```

---

## Resources

### 1. Voucher Resource

**Location:** Commerce → Vouchers

Comprehensive voucher management interface:

**Table Columns:**
- Code (with copy action and QR code icon)
- Type badge (fixed, percentage, free_shipping)
- Discount value/amount
- Status badge (active, expired, used_up, inactive)
- Usage stats (X / Y uses, or ∞ for unlimited)
- Valid from/until dates
- Owner (when multi-staff/store enabled)
- Created date

**Filters:**
- Type (fixed discount, percentage discount, free shipping)
- Status (active, expired, used_up, inactive)
- Date range (valid from, valid until, created)
- Discount amount range
- Has usage limit toggle
- Owner (multi-staff scenarios)

**Actions:**
- **Create** – Wizard with validation and preview
- **Edit** – Update details, dates, limits
- **View** – Comprehensive infolist with usage history
- **Activate** – Enable voucher for redemption
- **Deactivate** – Temporarily disable voucher
- **Expire** – Mark voucher as expired
- **Redeem Manually** – Admin-initiated redemption
- **Generate QR Code** – For POS integration
- **Duplicate** – Create similar voucher with new code

**Bulk Actions:**
- Activate selected vouchers
- Deactivate selected vouchers
- Expire selected vouchers
- Export to CSV/Excel
- Delete selected vouchers

**Search:** Code, description, metadata (JSON searchable)

### 2. Voucher Usage Resource

**Location:** Commerce → Voucher Usage

Track redemption history and analytics:

**Table Columns:**
- Voucher code (with link to voucher)
- Redeemer (user email/name)
- Discount amount applied
- Cart reference (with link when filament-cart installed)
- Redeemed at timestamp
- IP address (for fraud detection)
- Status (success, failed, reversed)

**Filters:**
- Voucher code
- Redeemer
- Date range (redeemed at)
- Discount amount range
- Status

**Actions:**
- View usage details
- Reverse redemption (admin only)
- Link to cart (when filament-cart installed)
- View redeemer profile

**Search:** Voucher code, redeemer, cart reference

---

## Dashboard Widget

### Voucher Statistics Overview

Real-time metrics at a glance:

- Total vouchers count
- Active vouchers count
- Total redemptions today/this week/this month
- Total discount amount given
- Average discount per redemption
- Expiring soon count (within 7 days)
- Most used voucher codes

**Widget Configuration:**
```php
FilamentVouchers::make()
    ->widgets([
        VoucherStatsWidget::class,
    ])
    ->widgetOptions([
        'expiring_soon_days' => 7,
        'show_trending' => true,
        'refresh_interval' => '60s',
    ]);
```

---

## Creating Vouchers

### Via UI (Wizard)

The create form uses a multi-step wizard:

**Step 1: Basic Information**
- Code (auto-generated or custom)
- Description
- Type (fixed, percentage, free_shipping)
- Value/amount

**Step 2: Condition Targeting**
- Choose from presets (cart subtotal, grand total, shipments, payments, items) or author a custom DSL such as `cart@cart_subtotal/aggregate`
- Preview the generated scope/phase/application metadata that will be stored alongside the voucher
- Advanced users can drop directly into the DSL field; clearing it reverts to the default cart-subtotal targeting

**Step 3: Usage Limits**
- Total usage limit (optional)
- Per-user usage limit (optional)
- Minimum cart total requirement (optional)

**Step 4: Dates & Ownership**
- Valid from date (optional)
- Valid until date (optional)
- Owner (multi-staff/store selector)

**Step 5: Preview & Confirm**
- Review all details
- See calculated discount preview
- Confirm creation

### Via Code

```php
use AIArmada\Vouchers\Facades\Voucher;

$voucher = Voucher::create([
    'code' => 'WELCOME2025',
    'type' => 'percentage',
    'value' => '20', // 20%
    'description' => 'Welcome discount for new customers',
    'usage_limit' => 100,
    'user_usage_limit' => 1,
    'min_total' => 5000, // RM50.00 minimum
    'valid_from' => now(),
    'valid_until' => now()->addDays(30),
]);
```

---

## Manual Redemption Workflow

Admins can redeem vouchers on behalf of customers:

1. Navigate to voucher detail view
2. Click "Redeem Manually" action
3. Fill redemption form:
   - Redeemer (user selector or email)
   - Cart reference (optional)
   - Override amount (optional)
   - Notes (internal)
4. Confirm redemption
5. System validates and creates redemption record

**Validation Rules:**
- Voucher must be active
- Must not exceed usage limits
- Must be within valid date range
- Cart total must meet minimum requirement (if applicable)

---

## Multi-Staff/Multi-Store Support

Configure owner resolution in `config/filament-vouchers.php`:

```php
return [
    'ownership' => [
        'enabled' => true,
        
        // Owner model (e.g., User, Staff, Store)
        'owner_model' => \App\Models\User::class,
        
        // Owner foreign key column
        'owner_column' => 'owner_id',
        
        // Owner display field
        'owner_display_field' => 'name',
        
        // Owner selector type (select, search, radio)
        'owner_selector_type' => 'search',
        
        // Restrict owners to authenticated user
        'restrict_to_auth_user' => false,
        
        // Owner label
        'owner_label' => 'Staff Member',
    ],
];
```

**Use Cases:**
- **Marketplace:** Each store creates their own vouchers
- **SaaS:** Each tenant manages vouchers independently
- **Multi-Brand:** Different brands with separate voucher pools
- **Affiliate:** Track which affiliate created the voucher

---

## Filament Cart Integration

When `aiarmada/filament-cart` is installed, the plugin automatically adds:

- Cart reference column in usage table with clickable link
- "View Cart" action in usage detail view
- Cart snapshot deep linking
- Cross-package navigation breadcrumbs

No additional configuration required—integration is automatic!

---

## Configuration

Full configuration options in `config/filament-vouchers.php`:

```php
return [
    // Navigation settings
    'navigation' => [
        'group' => 'Commerce',
        'sort' => 20,
        'voucher_icon' => 'heroicon-o-ticket',
        'usage_icon' => 'heroicon-o-chart-bar',
    ],
    
    // Resource configuration
    'resources' => [
        'voucher' => [
            'enabled' => true,
            'label' => 'Voucher',
            'plural_label' => 'Vouchers',
        ],
        'usage' => [
            'enabled' => true,
            'label' => 'Voucher Usage',
            'plural_label' => 'Voucher Usage',
        ],
    ],
    
    // Code generation
    'code_generation' => [
        'prefix' => '',
        'length' => 8,
        'uppercase' => true,
        'exclude_similar' => true, // No 0/O, 1/I/l
    ],
    
    // Validation
    'validation' => [
        'code_unique' => true,
        'code_regex' => '/^[A-Z0-9-]+$/',
        'min_code_length' => 4,
        'max_code_length' => 20,
    ],
    
    // Widget
    'widget' => [
        'enabled' => true,
        'expiring_soon_days' => 7,
        'show_trending' => true,
        'refresh_interval' => '60s',
    ],
    
    // Cart integration
    'cart_integration' => [
        'enabled' => true, // Auto-detects filament-cart
        'show_cart_links' => true,
    ],
];
```

---

## Validation Rules

The plugin includes comprehensive validation:

**Code Validation:**
- Must be unique
- Length between 4-20 characters
- Alphanumeric with optional dashes
- Case-insensitive matching
- No similar characters when enabled (0/O, 1/I/l)

**Amount Validation:**
- Fixed discount: Must not exceed cart total
- Percentage discount: 0-100%
- Free shipping: No amount validation

**Date Validation:**
- Valid from must be before valid until
- Cannot create voucher that's already expired
- Expiry date must be in the future

**Usage Validation:**
- Total usage limit must be positive
- Per-user limit must be ≤ total limit
- Minimum cart total must be positive

---

## Authorization

Define policies for granular permission control:

```php
// app/Policies/VoucherPolicy.php
class VoucherPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_vouchers');
    }
    
    public function create(User $user): bool
    {
        return $user->can('create_vouchers');
    }
    
    public function update(User $user, Voucher $voucher): bool
    {
        // Staff can only edit their own vouchers
        if ($user->hasRole('staff')) {
            return $voucher->owner_id === $user->id;
        }
        
        return $user->can('update_vouchers');
    }
    
    public function delete(User $user, Voucher $voucher): bool
    {
        // Cannot delete vouchers with redemptions
        if ($voucher->redemptions()->exists()) {
            return false;
        }
        
        return $user->can('delete_vouchers');
    }
    
    public function redeemManually(User $user, Voucher $voucher): bool
    {
        return $user->can('redeem_vouchers_manually');
    }
}
```

Register in `AuthServiceProvider`:

```php
protected $policies = [
    \AIArmada\Vouchers\Models\Voucher::class => VoucherPolicy::class,
];
```

---

## Testing

The plugin includes comprehensive tests using Pest v4:

```bash
# Run all tests
vendor/bin/pest

# Run specific test suite
vendor/bin/pest --filter=VoucherResource

# Run with parallel execution
vendor/bin/pest --parallel

# Run with coverage
vendor/bin/pest --coverage
```

**Test Coverage:**
- Voucher resource CRUD ✅
- Usage resource operations ✅
- Manual redemption workflow ✅
- Bulk actions (activate, deactivate, expire) ✅
- Dashboard widget calculations ✅
- Multi-staff/store scenarios ✅
- Cart integration ✅
- Authorization policies ⚠️ (partial)
- QR code generation ⚠️ (partial)

---

## Troubleshooting

### Vouchers not appearing

1. Verify aiarmada/vouchers is configured
2. Check database tables: `vouchers`, `voucher_redemptions`
3. Verify user has `view_vouchers` permission
4. Check ownership filters (multi-staff mode)

### Manual redemption fails

1. Check voucher status (must be active)
2. Verify usage limits not exceeded
3. Check date range (must be within valid period)
4. Verify minimum cart total met
5. Check Laravel logs for validation errors

### Cart links not working

1. Ensure aiarmada/filament-cart is installed
2. Verify cart integration enabled in config
3. Check cart reference stored in redemption
4. Verify user has cart viewing permissions

### Code generation issues

1. Check `code_generation` config settings
2. Verify uniqueness validation is working
3. Check database for duplicate codes
4. Review `code_regex` pattern

---

## Extending Resources

Create custom resources by extending base classes:

```php
// app/Filament/Resources/CustomVoucherResource.php
namespace App\Filament\Resources;

use AIArmada\FilamentVouchers\Resources\VoucherResource as BaseVoucherResource;
use Filament\Tables;

class CustomVoucherResource extends BaseVoucherResource
{
    // Add custom columns
    public static function table(Table $table): Table
    {
        return parent::table($table)
            ->columns([
                ...parent::getColumns(),
                Tables\Columns\TextColumn::make('metadata.campaign')
                    ->label('Campaign')
                    ->badge()
                    ->searchable(),
            ]);
    }
    
    // Add custom actions
    public static function getActions(): array
    {
        return [
            ...parent::getActions(),
            Tables\Actions\Action::make('send_email')
                ->label('Email to Customers')
                ->icon('heroicon-o-envelope')
                ->action(fn ($record) => /* send logic */),
        ];
    }
}
```

---

## Quality Gates

Before submitting PRs:

```bash
vendor/bin/pint --dirty    # Format code
vendor/bin/pest --parallel # Run tests
vendor/bin/phpstan analyse # Static analysis
```

---

## Contributing

Pull requests are welcome! Please:

1. Open an issue describing the enhancement or bug
2. Keep documentation alongside code changes
3. Include tests for new features
4. Follow existing code style

---

## Security

If you discover security vulnerabilities, please email security@aiarmada.com instead of using the issue tracker.

---

## Credits

- [AIArmada Team](https://aiarmada.com)
- [All Contributors](https://github.com/aiarmada/commerce/contributors)

---

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md) for details.
