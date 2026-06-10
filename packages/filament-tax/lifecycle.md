# Filament Tax Lifecycle

## 1. Installation

```bash
composer require aiarmada/filament-tax
```

The package auto-discovers via Laravel's package discovery (`FilamentTaxServiceProvider`). Requires PHP 8.4+, Filament v5.6.5+, and the `aiarmada/tax` domain package.

Suggestions (not required):
- `aiarmada/customers` — enables Customer/CustomerGroup entity types in exemptions.
- `filament/spatie-laravel-settings-plugin` — required for the `ManageTaxSettings` page.

No migrations ship with this package (persistence lives in `aiarmada/tax`).

## 2. Configuration

Publish the config:

```bash
php artisan vendor:publish --tag=filament-tax-config
```

`config/filament-tax.php` exposes:

| Key | Default | Purpose |
|-----|---------|---------|
| `features.zones` | `true` | Tax Zone resource |
| `features.classes` | `true` | Tax Class resource |
| `features.rates` | `true` | Tax Rate resource |
| `features.exemptions` | `true` | Tax Exemption resource |
| `features.widgets` | `true` | Dashboard widgets |
| `features.settings_page` | `true` | ManageTaxSettings page |
| `certificates.disk` | `local` (env: `TAX_CERTIFICATES_DISK`) | Storage disk for exemption certificates |
| `certificates.directory` | `tax-exemptions` | Directory for certificate files |

## 3. Initialization

### Panel Registration

```php
use AIArmada\FilamentTax\FilamentTaxPlugin;

$panel->plugin(
    FilamentTaxPlugin::make()
        ->zones()
        ->classes()
        ->rates()
        ->exemptions()
        ->widgets()
        ->settingsPage()
);
```

Plugin-level settings take precedence over config-level `features` keys.

On `register()`, the plugin registers:
- Resources: `TaxZoneResource` (sort 1), `TaxClassResource` (sort 2), `TaxRateResource` (sort 2), `TaxExemptionResource` (sort 4) — all under the "Tax" navigation group.
- Widgets: `TaxStatsWidget` (sort 1), `ExpiringExemptionsWidget` (sort 2), `ZoneCoverageWidget` (sort 3).
- Pages: `ManageTaxSettings` — under "Settings" group (sort 11), gated by `tax.settings.manage`.

**Navigation consistency gap**: `TaxClassResource` and `TaxRateResource` share sort order 2, so sidebar order depends on registration order.

### Authorization

| Permission | Gated Actions |
|---|---|
| `tax.zones.delete` | Bulk delete zones |
| `tax.classes.delete` | Bulk delete classes |
| `tax.rates.update` | Bulk activate/deactivate rates |
| `tax.rates.delete` | Bulk delete rates |
| `tax.exemptions.download` | Download certificate |
| `tax.exemptions.approve` | Approve exemptions |
| `tax.exemptions.reject` | Reject exemptions |
| `tax.exemptions.renew` | Renew exemptions |
| `tax.exemptions.delete` | Delete exemptions |
| `tax.settings.manage` | Save tax settings |

### Multitenancy (Owner Scoping)

Every resource and relation manager applies `TaxOwnerScope::applyToOwnedQuery()` to `getEloquentQuery()`. All bulk write actions use `OwnerWriteGuard::findOrFailForOwner()`. `CreateTaxExemption` and `EditTaxExemption` additionally validate the exemptable entity (Customer/CustomerGroup) belongs to the current owner.

## 4. Operation

### Resources

#### TaxZoneResource
- **Model**: `AIArmada\Tax\Models\TaxZone`
- **Pages**: List, Create, View, Edit
- **Relation Managers**: `RatesRelationManager`
- **Form** (`TaxZoneForm`): Zone Details (name, code unique, type country/state/postcode, description), Geographic Matching (countries, states, postcodes — TagsInput), Settings sidebar (is_active, is_default, priority).
- **Table** (`TaxZonesTable`): Columns for name+code, type badge, countries, rates_count, priority, is_default, is_active. Filters for type and is_active. Bulk delete with OwnerWriteGuard.

#### TaxClassResource
- **Model**: `AIArmada\Tax\Models\TaxClass`
- **Pages**: List, Create, Edit (no View page)
- **Form** (`TaxClassForm`): Name (auto-generates slug on blur), slug (unique), description, is_default, is_active, position. Table is reorderable on position.
- **Table** (`TaxClassesTable`): Columns for name, slug, description (toggleable), is_default, is_active, position. Filter for is_active. Bulk delete with OwnerWriteGuard.

#### TaxRateResource
- **Model**: `AIArmada\Tax\Models\TaxRate`
- **Pages**: List, Create, View, Edit
- **Form** (`TaxRateForm`): Rate Details (zone_id owner-scoped select, name, tax_class, rate displayed as % stored as basis points), Application Rules (is_compound, is_shipping, priority), Status sidebar (is_active, description).
- **Table** (`TaxRatesTable`): Columns for zone name badge, rate name, tax class (color-coded), rate (formatted from basis points), is_compound, is_shipping, priority, is_active, created_at. Filters for zone (scoped), class, is_active, is_compound. Bulk actions: Activate, Deactivate, Delete (all OwnerWriteGuard-gated).

**Table filter gap**: Tax rates have bulk activate/deactivate toggle actions but no `is_active` ternary filter on the table (unlike other resources that filter by active status).

#### TaxExemptionResource
- **Model**: `AIArmada\Tax\Models\TaxExemption`
- **Pages**: List, Create, View, Edit
- **Form** (`TaxExemptionForm`): Customer Information (exemptable_type dynamic: Customer/CustomerGroup/User, exemptable_id searchable owner-scoped, tax_zone_id optional), Certificate Details (certificate_number unique, document_path file upload pdf/image max 5MB, reason), Validity Period (starts_at, expires_at optional for permanent), Status sidebar (status enum select, status_info, verified_info), Internal Notes (rejection_reason).
- **Exemptable types**: Customer if `aiarmada/customers` installed; CustomerGroup if installed; falls back to `App\Models\User`.
- **Write guards**: `CreateTaxExemption` and `EditTaxExemption` revalidate exemptable entity's owner in `beforeCreate()`/`beforeSave()`.
- **Table** (`TaxExemptionsTable`): Columns for exemptable name+type, certificate_number (copyable), zone badge, status badge, valid-from, expires (color/icon-coded by expiry). Filters for zone (scoped), status, expiring-soon, expired.
- **Row actions** (grouped): View, Edit, Download Certificate, Approve (pending only), Renew (with date picker), Delete.
- **Bulk actions**: Approve, Reject, Delete (all OwnerWriteGuard-gated).

**Action gap**: The "Renew" action is only available as a row action with a date picker, but bulk renewal is not available. When all exemptions in a zone need extension, operators must renew them individually.

### Widgets

- **TaxStatsWidget** (`StatsOverviewWidget`, 30s polling): 4 stats — Active Tax Zones, Configured Rates, Tax Classes, Active Exemptions. All scoped via TaxOwnerScope.
- **ExpiringExemptionsWidget** (`TableWidget`, full width): Exemptions expiring within 30 days. Paginated.
- **ZoneCoverageWidget** (custom Blade view, full width): Active zones with rates, countries, priority. All owner-scoped.

### Settings Page

`ManageTaxSettings` reads/writes `AIArmada\Tax\Settings\TaxSettings`. Fields: enabled (toggle), defaultTaxRate, defaultTaxName, pricesIncludeTax (toggle), taxBasedOnShippingAddress (toggle), digitalGoodsTaxable (toggle), shippingTaxable (toggle), taxIdLabel (select), validateTaxIds (toggle), requireExemptionCertificate (toggle). Authorization: `tax.settings.manage` permission checked at page-level and in `save()`.

### Certificate Downloads

`DownloadTaxExemptionCertificateAction` verifies the exemption record exists within owner scope, `document_path` is non-null, path starts with allowed directory prefix, file exists on configured disk. Returns `StreamedResponse` on success.

## 5. Extension

### Per-Panel Feature Control

Disable individual resources/widgets per panel via the plugin API:

```php
FilamentTaxPlugin::make()
    ->rates(false)
    ->exemptions(false)
    ->widgets(false)
    ->settingsPage(false);
```

### Custom Forms and Tables

All resource forms and tables are extracted into dedicated classes (`Schemas/` and `Tables/`). Override via Filament's standard resource extension points:

```php
TaxZoneResource::form(fn (Schema $schema) => TaxZoneForm::configure($schema));
TaxZoneResource::table(fn (Table $table) => TaxZonesTable::configure($table));
```
