---
title: Usage
---

# Usage

This guide covers the resources, pages, and widgets registered by `FilamentAffiliatesPlugin`.

The plugin always registers `AffiliateResource`, then conditionally adds other resources and pages based on `filament-affiliates.features.admin.*` and whether commission tracking is enabled in the core affiliates package.

## Registered Resources

- `AffiliateResource` — always registered
- `AffiliateConversionResource` — when `features.admin.conversions` is enabled
- `AffiliatePayoutResource` — when `features.admin.payouts` is enabled and commission tracking is on
- `AffiliateProgramResource` — when `features.admin.programs` is enabled and commission tracking is on
- `AffiliateFraudSignalResource` — when `features.admin.fraud_monitoring` is enabled

## Registered Pages

- `FraudReviewPage` — when fraud monitoring is enabled
- `PayoutBatchPage` — when payouts are enabled
- `ReportsPage` — when reports are enabled

## Registered Widgets

- `AffiliateStatsWidget`
- `PerformanceOverviewWidget`
- `RealTimeActivityWidget`
- `FraudAlertWidget` when fraud monitoring is enabled
- `PayoutQueueWidget` when payouts are enabled
- `NetworkVisualizationWidget` when network visualization is enabled

## AffiliateResource

Manage affiliate accounts with owner-aware queries when affiliate owner mode is enabled.

### List View

- Code
- Name
- Status
- Commission
- Parent affiliate
- Updated timestamp

The table includes a status filter and these row actions:

- View
- Edit
- Delete

The resource also exposes the `ConversionsRelationManager` on the record page.

### Customization

Extend the resource to customize:

```php
namespace App\Filament\Resources;

use AIArmada\FilamentAffiliates\Resources\AffiliateResource as BaseResource;

class AffiliateResource extends BaseResource
{
| View | Open the affiliate record |
| Edit | Update the affiliate profile |
| Delete | Remove the affiliate record |
    {
        return static::getModel()::pending()->count() ?: null;
    }
Manage affiliate conversions and their commission lifecycle.
    public static function form(Form $form): Form
This resource is feature-gated by `filament-affiliates.features.admin.conversions`.
        return parent::form($form)
Typical workflows include reviewing conversion records, inspecting references and amounts, and updating conversion status from Filament actions when the operator has permission to approve affiliate activity.
    }
## AffiliatePayoutResource
```
Manage affiliate payout records when commission tracking and payout admin features are enabled.
```php
The payout resource works alongside `PayoutBatchPage`, which is registered only when payouts are enabled.

## AffiliateProgramResource
            Affiliate::class,
Manage affiliate programs and related commission structures when commission tracking is enabled.
            includeGlobal: (bool) config('affiliates.owner.include_global', false),
## AffiliateFraudSignalResource

Review fraud signals from the standard resource screens when fraud monitoring is enabled.

For queue-style operator workflows, pair it with `FraudReviewPage`.
|--------|-------------|
## FraudReviewPage
| Suspend | Suspend active affiliate |
`FraudReviewPage` is the focused review queue for detected fraud signals.

Verified actions include:

- Approve — dismiss the signal as a false positive
- Confirm Fraud — mark the signal as confirmed and optionally reject the linked conversion
- View
- Bulk approve
- Bulk confirm fraud
- Bulk fraud review
- Status
The page is owner-scoped through the affiliate relationship and only shows signals in the detected state.
Tables\Filters\SelectFilter::make('status')
## Feature Flags

Admin registration is driven by `config('filament-affiliates.features.admin')`:
    ->options(ConversionType::class),

use AIArmada\FilamentAffiliates\FilamentAffiliatesPlugin;
                    ->numeric()
FilamentAffiliatesPlugin::make();
```

When `affiliates.features.commission_tracking.enabled` is `false`, payout and program admin surfaces are suppressed even if their Filament feature flags remain `true`.

## Overriding Registration

Register the plugin in your panel as usual:

```php
use AIArmada\FilamentAffiliates\FilamentAffiliatesPlugin;
| Manager | Description |
|---------|-------------|
| TiersRelationManager | Manage program tiers |
    return $panel
        ->plugins([
            FilamentAffiliatesPlugin::make(),
        ]);

Review and manage fraud alerts.

### Severity Levels

| Level | Color | Description |
|-------|-------|-------------|
| Low | Gray | Minor anomaly |
| Medium | Yellow | Suspicious pattern |
| High | Orange | Likely fraud |
| Critical | Red | Confirmed fraud |

### Actions

```php
use AIArmada\Affiliates\Enums\FraudSignalStatus;
use AIArmada\FilamentAffiliates\Actions\UpdateAffiliateFraudSignalStatus;

Tables\Actions\Action::make('dismiss')
    ->label('Dismiss')
    ->action(fn (FraudSignal $record) => UpdateAffiliateFraudSignalStatus::run($record, FraudSignalStatus::Dismissed)),

Tables\Actions\Action::make('confirm')
    ->label('Confirm Fraud')
    ->color('danger')
    ->requiresConfirmation()
    ->action(fn (FraudSignal $record) => UpdateAffiliateFraudSignalStatus::run($record, FraudSignalStatus::Confirmed)),

Tables\Actions\Action::make('block_affiliate')
    ->label('Block Affiliate')
    ->color('danger')
    ->requiresConfirmation()
    ->action(fn (FraudSignal $record) => $record->affiliate->suspend()),
```

## Overriding Resources

Register custom resources in your panel:

```php
use App\Filament\Resources\AffiliateResource;
use AIArmada\FilamentAffiliates\FilamentAffiliatesPlugin;

FilamentAffiliatesPlugin::make()
    ->resources([
        AffiliateResource::class,
        // Use default for others...
    ]);
```

## Feature-Gated Registration

The plugin resolves resources from `filament-affiliates.features.admin`:

- `conversions` controls `AffiliateConversionResource`
- `payouts` controls `AffiliatePayoutResource`
- `programs` controls `AffiliateProgramResource`
- `fraud_monitoring` controls `AffiliateFraudSignalResource`

When `affiliates.features.commission_tracking.enabled` is false, payout/program resources are automatically disabled.

## Adding Custom Columns

Extend table columns in your resource:

```php
public static function table(Table $table): Table
{
    return parent::table($table)
        ->columns([
            ...parent::getTableColumns(),
            Tables\Columns\TextColumn::make('custom_field'),
        ]);
}
```
