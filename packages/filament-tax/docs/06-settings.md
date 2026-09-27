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
- **Path:** `/admin/manage-tax-settings` (default slug)
- **Navigation:** Settings > Tax Settings
- **Icon:** `heroicon-o-receipt-percent`

## Settings Overview

The page manages the `TaxSettings` class from the base tax package using Spatie Laravel Settings.

```
┌─────────────────────────────────────────────────────────────┐
│ Tax Settings                                                 │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│ General Settings                                             │
│ ─────────────────────────────────────────────────────────── │
│                                                              │
│ ☑ Enable Tax Calculation                                    │
│   When enabled, taxes are calculated on orders               │
│                                                              │
│ Default Tax Rate (%)   [6.00___________]                    │
│   Used when no zone-specific rate is found                   │
│                                                              │
│ Default Tax Name       [SST_____________]                   │
│   Tax name displayed on invoices                             │
│                                                              │
│ ─────────────────────────────────────────────────────────── │
│ Price Configuration                                          │
│ ─────────────────────────────────────────────────────────── │
│                                                              │
│ ☐ Prices Include Tax                                        │
│   Enable if product prices already include tax               │
│                                                              │
│ ☑ Tax Based on Shipping Address                             │
│   Calculate tax based on shipping address                    │
│                                                              │
│ ☑ Digital Goods Taxable                                     │
│   Apply tax to digital products                              │
│                                                              │
│ ─────────────────────────────────────────────────────────── │
│ Shipping & Tax IDs                                           │
│ ─────────────────────────────────────────────────────────── │
│                                                              │
│ ☐ Shipping is Taxable                                       │
│   Apply tax to shipping charges                              │
│                                                              │
│ Tax ID Label           [SST Number        ▼]                │
│   Label for customer tax identification numbers              │
│                                                              │
│ ☐ Validate Tax IDs                                          │
│   Validate customer tax IDs                                  │
│                                                              │
│ ☐ Require Exemption Certificate                             │
│   Require certificate for B2B tax exemptions                 │
│                                                              │
│                                   [Cancel]  [Save Settings]  │
└─────────────────────────────────────────────────────────────┘
```

## Available Settings

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `enabled` | bool | `true` | Master switch for tax calculation |
| `defaultTaxRate` | float | `6.0` | Fallback rate as a percentage (e.g., 6 for 6%) |
| `defaultTaxName` | string | `'SST'` | Tax name on invoices |
| `pricesIncludeTax` | bool | `false` | Whether catalog prices include tax |
| `taxBasedOnShippingAddress` | bool | `true` | Use shipping address for zone |
| `digitalGoodsTaxable` | bool | `true` | Tax digital products |
| `shippingTaxable` | bool | `false` | Apply tax to shipping |
| `taxIdLabel` | string | `'SST Number'` | Label for tax ID field |
| `validateTaxIds` | bool | `false` | Validate customer tax IDs |
| `requireExemptionCertificate` | bool | `false` | Require certificate for exemptions |

## Implementation

The settings page extends Filament's settings page with custom form fields:

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
    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Toggle::make('enabled')
                    ->label('Enable Tax Calculation'),

                TextInput::make('defaultTaxRate')
                    ->label('Default Tax Rate')
                    ->numeric()
                    ->suffix('%'),

                TextInput::make('defaultTaxName')
                    ->label('Default Tax Name'),

                Toggle::make('pricesIncludeTax')
                    ->label('Prices Include Tax'),

                Toggle::make('taxBasedOnShippingAddress')
                    ->label('Tax Based on Shipping Address'),

                Toggle::make('digitalGoodsTaxable')
                    ->label('Digital Goods Taxable'),

                Toggle::make('shippingTaxable')
                    ->label('Shipping Taxable'),

                Select::make('taxIdLabel')
                    ->label('Tax ID Label')
                    ->options([
                        'VAT Number' => 'VAT Number',
                        'GST Number' => 'GST Number',
                        'SST Number' => 'SST Number',
                        'Tax ID' => 'Tax ID',
                    ]),

                Toggle::make('validateTaxIds')
                    ->label('Validate Tax IDs'),

                Toggle::make('requireExemptionCertificate')
                    ->label('Require Exemption Certificate'),
            ])
            ->statePath('data');
    }
}
```

## Blade View

The page uses a custom Blade view at `resources/views/pages/manage-tax-settings.blade.php`:

```blade
<x-filament-panels::page>
    <x-filament-panels::form wire:submit="save">
        {{ $this->form }}
        
        <x-filament-panels::form.actions
            :actions="$this->getCachedFormActions()"
            :full-width="$this->hasFullWidthFormActions()"
        />
    </x-filament-panels::form>
</x-filament-panels::page>
```

## Authorization

The settings page requires proper authorization:

### With filament-authz

```php
// Permissions checked:
// - tax.settings.view (to see page)
// - tax.settings.update (to save changes)
```

### Without filament-authz

Create a gate or use policies:

```php
// AuthServiceProvider
Gate::define('manage-tax-settings', function ($user) {
    return $user->hasRole('admin');
});
```

Then in the page:

```php
public static function canAccess(): bool
{
    return Gate::allows('manage-tax-settings');
}
```

## Extending Settings

To add more settings fields:

### 1. Extend the Settings Class

```php
namespace App\Settings;

use AIArmada\Tax\Settings\TaxSettings as BaseTaxSettings;

class TaxSettings extends BaseTaxSettings
{
    public bool $autoDetectZone = true;
    public string $fallbackZoneId = '';
    
    public static function group(): string
    {
        return 'tax';
    }
}
```

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
        $this->migrator->add('tax.fallbackZoneId', '');
    }
}
```

### 3. Create Custom Settings Page

```php
namespace App\Filament\Pages;

use AIArmada\FilamentTax\Pages\ManageTaxSettings as BaseSettingsPage;
use App\Settings\TaxSettings;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ManageTaxSettings extends BaseSettingsPage
{
    public function form(Schema $schema): Schema
    {
        $schema = parent::form($schema);

        return $schema->schema([
            ...$schema->getComponents(),

            Section::make('Zone Detection')
                ->schema([
                    Toggle::make('autoDetectZone')
                        ->label('Auto-detect Tax Zone')
                        ->helperText('Automatically detect zone from customer address'),
                        
                    Select::make('fallbackZoneId')
                        ->label('Fallback Zone')
                        ->options(TaxZone::pluck('name', 'id'))
                        ->helperText('Zone to use when detection fails'),
                ]),
        ]);
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
