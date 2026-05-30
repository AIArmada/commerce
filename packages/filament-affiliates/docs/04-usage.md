---
title: Usage
---

# Usage

This package registers resources, pages, and widgets through `FilamentAffiliatesPlugin`.

Registration is config-driven:

- `filament-affiliates.features.admin.*` controls admin surfaces.
- `affiliates.features.commission_tracking.enabled` can force-disable some admin features.

## Admin resources

### Always registered

- `AffiliateResource`

### Feature-gated resources

- `AffiliateConversionResource` (`features.admin.conversions`)
- `AffiliatePayoutResource` (`features.admin.payouts` + commission tracking enabled)
- `AffiliateProgramResource` (`features.admin.programs` + commission tracking enabled)
- `AffiliateCommissionTemplateResource` (`features.admin.commission_management` + commission tracking enabled)
- `AffiliateLinkResource` (`features.admin.links`)
- `AffiliateTouchpointResource` (`features.admin.attribution`)
- `AffiliateRankResource` (`features.admin.ranks`)
- `AffiliateRankHistoryResource` (`features.admin.ranks`)
- `AffiliateSupportTicketResource` (`features.admin.support_compliance`)
- `AffiliateTaxDocumentResource` (`features.admin.support_compliance`)
- `AffiliateNetworkResource` (`features.admin.network_visualization`)
- `AffiliateFraudSignalResource` (`features.admin.fraud_monitoring`)

## Admin pages

- `FraudReviewPage` (`features.admin.fraud_monitoring`)
- `PayoutBatchPage` (`features.admin.payouts`)
- `ReportsPage` (`features.admin.reports`)

## Widgets

### Always registered

- `AffiliateStatsWidget`
- `PerformanceOverviewWidget`
- `RealTimeActivityWidget`

### Feature-gated widgets

- `FraudAlertWidget` (`features.admin.fraud_monitoring`)
- `PayoutQueueWidget` (`features.admin.payouts`)
- `NetworkVisualizationWidget` (`features.admin.network_visualization`)

## Commission tracking gate behavior

When `affiliates.features.commission_tracking.enabled` is `false`, these admin features are suppressed even if enabled in `filament-affiliates` config:

- payouts
- programs
- commission management

## Owner scope and write safety

Resource/page filters are not authorization by themselves.

Write paths should:

- authorize via policy/ability checks,
- re-resolve submitted IDs server-side in owner scope,
- fail validation when IDs are out-of-scope.

Recent hardened write paths include support-ticket, link/program membership, and commission-promotion affiliate targeting flows.

## Plugin registration

Register in your panel provider:

```php
use AIArmada\FilamentAffiliates\FilamentAffiliatesPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentAffiliatesPlugin::make(),
        ]);
}
```

## Related docs

- [Configuration](03-configuration.md)
- [Widgets](05-widgets.md)
- [Portal](06-portal.md)
