---
title: Settings Page
---

# Settings Page

The plugin includes a settings page for configuring global tax behavior without code changes.

## Enabling the Settings Page

```php
FilamentTaxPlugin::make()
    ->settingsPage(true);
```

Or via configuration:

```php
// config/filament-tax.php
'features' => [
    'settings_page' => true,
],
```

## Navigation

The settings page appears at:
- **Path:** derived from the page class — `/admin/manage-tax-settings` in the default `admin` panel
- **Navigation:** `Settings` > `Tax Settings` (group from `filament-tax.navigation.settings_group`, sort from `filament-tax.pages.navigation_sort.settings`)
- **Icon:** `heroicon-o-receipt-percent`

## Settings Overview

The page manages the `TaxSettings` class from the base tax package using Spatie Laravel Settings.
Defaults: 6% SST, prices exclusive of tax, tax on shipping **off**, SST Number label.

```
┌─────────────────────────────────────────────────────────────┐
│ Tax Settings                                                 │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│ ☑ Enable Tax Calculation                                    │
│   Enable or disable tax calculations globally.               │
│                                                              │
│ Default Tax Rate (%)   [6___________]                        │
│   Default tax rate percentage (e.g., 6 for 6%).              │
│                                                              │
│ Default Tax Name       [SST___________]                      │
│   Tax name displayed on invoices (e.g., SST, GST, VAT).     │
│                                                              │
│ ☐ Prices Include Tax                                        │
│   Enable if your prices already include tax.                 │
│                                                              │
│ ☑ Tax Based on Shipping Address                             │
│   Calculate tax based on shipping address (vs billing).      │
│                                                              │
│ ☑ Digital Goods Taxable                                     │
│   Apply tax to digital/downloadable products.                │
│                                                              │
│ ☐ Shipping Taxable                                          │
│   Apply tax to shipping charges.                             │
│                                                              │
│ Tax ID Label         [SST Number ▾]                          │
│   Label for customer tax identification numbers.            │
│                                                              │
│ ☐ Validate Tax IDs                                          │
│   Validate customer tax IDs (requires integration).          │
│                                                              │
│ ☐ Require Exemption Certificate                             │
│   Require certificate for B2B tax exemptions.                │
│                                                              │
│                                             [Save]            │
└─────────────────────────────────────────────────────────────┘
```

## Available Settings

`TaxSettings` is cast from spatie/laravel-settings. Property types are declared on the
class; defaults come from the `tax` package's settings migration (see
[Settings storage](#settings-storage)).

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `enabled` | bool | `true` | Master switch for tax calculation |
| `defaultTaxRate` | float | `6.0` | Default tax rate **percentage** (`6` = 6%), 0–100 |
| `defaultTaxName` | string | `'SST'` | Tax name shown on invoices |
| `pricesIncludeTax` | bool | `false` | Whether catalog prices include tax |
| `taxBasedOnShippingAddress` | bool | `true` | Resolve tax from shipping instead of billing address |
| `digitalGoodsTaxable` | bool | `true` | Apply tax to digital/downloadable products |
| `shippingTaxable` | bool | `false` | Apply tax to shipping |
| `taxIdLabel` | string | `'SST Number'` | Label for customer tax IDs |
| `validateTaxIds` | bool | `false` | Validate customer tax IDs |
| `requireExemptionCertificate` | bool | `false` | Require a certificate for B2B exemptions |

Defaults come from `packages/tax/database/settings/2026_06_13_000003_create_tax_settings.php`.
Three of them differ from the `??` fallbacks in `ManageTaxSettings::mount()` — `defaultTaxRate`
(`6.0` vs `0.0`), `defaultTaxName` (`'SST'` vs `'Tax'`), and `taxIdLabel` (`'SST Number'` vs
`'Tax ID'`). Read the value rather than assuming either one.

> **warning**
> `defaultTaxRate` is a percentage, not basis points. `TaxRate::$rate` is the basis-point
> column (`600` = 6%); a 6% default here is `6`, not `600`. Do not mix the two in money math.

## Implementation

`ManageTaxSettings` is a `final class ... extends Page` (not `SettingsPage`) that
resolves `TaxSettings` from the container in `mount()` and writes it back in `save()`.
Filament v5 schemas live in `Filament\Schemas\Schema`:

```php
namespace AIArmada\FilamentTax\Pages;

use AIArmada\Tax\Settings\TaxSettings;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

final class ManageTaxSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-receipt-percent';
    
    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return config('filament-tax.navigation.settings_group');
    }
    
    public static function getNavigationSort(): ?int
    {
        $sort = config('filament-tax.pages.navigation_sort.settings');
        
        return is_numeric($sort) ? (int) $sort : null;
    }
    
    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Toggle::make('enabled')
                    ->label(__('Enable Tax Calculation'))
                    ->helperText(__('Enable or disable tax calculations globally.')),
                    
                TextInput::make('defaultTaxRate')
                    ->label(__('Default Tax Rate'))
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->suffix('%')
                    ->required()
                    ->helperText(__('Default tax rate percentage (e.g., 6 for 6%).')),
                    
                TextInput::make('defaultTaxName')
                    ->label(__('Default Tax Name'))
                    ->required()
                    ->helperText(__('Tax name displayed on invoices (e.g., SST, GST, VAT).')),
                    
                Toggle::make('pricesIncludeTax')
                    ->label(__('Prices Include Tax')),
                    
                Toggle::make('taxBasedOnShippingAddress')
                    ->label(__('Tax Based on Shipping Address')),
                    
                Toggle::make('digitalGoodsTaxable')
                    ->label(__('Digital Goods Taxable')),
                    
                Toggle::make('shippingTaxable')
                    ->label(__('Shipping Taxable')),
                    
                Select::make('taxIdLabel')
                    ->label(__('Tax ID Label'))
                    ->options([
                        'VAT Number' => 'VAT Number',
                        'GST Number' => 'GST Number',
                        'SST Number' => 'SST Number',
                        'Tax ID' => 'Tax ID',
                    ])
                    ->required(),
                    
                Toggle::make('validateTaxIds')
                    ->label(__('Validate Tax IDs')),
                    
                Toggle::make('requireExemptionCertificate')
                    ->label(__('Require Exemption Certificate')),
            ])
            ->statePath('data');
    }
}
```

> **warning**
> Never set `protected static ?string $navigationGroup`. It blocks the `CommerceNavigation`
> runtime override engine, which reads a resource's config default and then merges
> `commerce-support.filament.navigation.items.{FQCN}` on top. Always implement
> `getNavigationGroup()` and read `config()` inside it.

## Blade View

The page uses the package view `filament-tax::pages.manage-tax-settings`. Publish it
with:

```bash
php artisan vendor:publish --tag=filament-tax-views
```

which writes `resources/views/vendor/filament-tax/pages/manage-tax-settings.blade.php`.

## Authorization

The page requires the `tax.settings.manage` ability, enforced two ways:
`ManageTaxSettings::authzPermission()` returns `'tax.settings.manage'` (used by the
`HasPageAuthz` concern), and `save()` re-checks `auth()->user()?->can('tax.settings.manage')`
before writing, sending an "Unauthorized" notification otherwise.

To use a different gate:

```php
// AuthServiceProvider
Gate::define('tax.settings.manage', function ($user) {
    return $user->hasRole('admin');
});
```

## Settings storage

The `tax` package ships its spatie settings migrations in
`packages/tax/database/settings` and appends that directory to
`settings.migrations_paths` in `TaxServiceProvider::registerSettingsMigrationPath()`,
so `php artisan migrate` picks them up with no copying step. If you want them in the
application's own `database/settings`, publish them:

```bash
php artisan vendor:publish --tag=tax-settings
```

## Extending Settings

To add more settings fields:

### 1. Extend the Settings Class

`TaxSettings` is not `final` and its group is already `tax`, so a subclass only needs
the new properties:

```php
namespace App\Settings;

use AIArmada\Tax\Settings\TaxSettings as BaseTaxSettings;

class TaxSettings extends BaseTaxSettings
{
    public bool $autoDetectZone = true;
    
    public ?string $fallbackZoneId = null;
}
```

> **warning**
> Register the subclass in the container so the page and your code resolve the same
> class, otherwise spatie reads the base class and the extra properties stay unset:
>
> ```php
> $this->app->singleton(\App\Settings\TaxSettings::class);
> ```

### 2. Create Migration

```bash
php artisan make:settings-migration AddAutoDetectToTaxSettings
```

```php
use Spatie\LaravelSettings\Migrations\SettingsMigration;

class AddAutoDetectToTaxSettings extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('tax.autoDetectZone', true);
        $this->migrator->add('tax.fallbackZoneId', null);
    }
}
```

### 3. Create a Custom Settings Page

`AIArmada\FilamentTax\Pages\ManageTaxSettings` is `final`, so write a standalone
`Page` that reads and writes the same settings class:

```php
namespace App\Filament\Pages;

use AIArmada\Tax\Models\TaxZone;
use App\Settings\TaxSettings;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Pages\Page;
use UnitEnum;

class ManageTaxSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';
    
    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return config('filament-tax.navigation.settings_group');
    }
    
    protected string $view = 'filament.pages.manage-tax-settings';
    
    public ?array $data = [];
    
    public function mount(): void
    {
        $settings = app(TaxSettings::class);
        
        $this->data = [
            'autoDetectZone' => $settings->autoDetectZone,
            'fallbackZoneId' => $settings->fallbackZoneId,
        ];
        
        $this->getSchema('form')?->fill($this->data);
    }
    
    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Zone Detection')
                    ->schema([
                        Toggle::make('autoDetectZone')
                            ->label('Auto-detect Tax Zone'),
                        
                        Select::make('fallbackZoneId')
                            ->label('Fallback Zone')
                            ->options(TaxZone::pluck('name', 'id')->all()),
                    ]),
            ])
            ->statePath('data');
    }
}
```

### 4. Register in Panel

```php
use App\Filament\Pages\ManageTaxSettings;

public function panel(Panel $panel): Panel
{
    return $panel
        ->pages([
            ManageTaxSettings::class,
        ])
        ->plugins([
            FilamentTaxPlugin::make()
                ->settingsPage(false), // Disable built-in page
        ]);
}
```

## Reading Settings in Code

Access settings anywhere in your application:

```php
use AIArmada\Tax\Settings\TaxSettings;

$settings = app(TaxSettings::class);

if ($settings->enabled) {
    // Tax is enabled
}

if ($settings->pricesIncludeTax) {
    // Extract tax from price
} else {
    // Add tax to price
}

if ($settings->shippingTaxable) {
    // Calculate tax on shipping
}
```

## Settings Caching

Spatie Laravel Settings caches settings by default. Clear cache after changing settings programmatically:

```bash
php artisan cache:clear
```

Or in code:

```php
use Spatie\LaravelSettings\SettingsCache;

app(SettingsCache::class)->clear();
```