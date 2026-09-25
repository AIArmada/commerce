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
- `AffiliateUplineResource` (`features.admin.upline_visualization`)
- `AffiliateFraudSignalResource` (`features.admin.fraud_monitoring`)

## Admin pages

- `FraudReviewPage` (`features.admin.fraud_monitoring`)
- `PayoutBatchPage` (`features.admin.payouts`)
- `ManageAffiliatePayoutSettings` (`features.admin.payouts`)
- `PerformanceBonusesPage` (`features.admin.payouts`)
- `ManageAffiliateBonusSettings` (`features.admin.commission_management`)
- `ReportsPage` (`features.admin.reports`)

### Performance bonuses

The Performance Bonuses page previews the calculated bonuses for a month (counts and minor-unit totals by currency) and awards them as approved conversions. The table below lists awarded bonus conversions as an audit trail. The same flow runs headless via `affiliates:award-bonuses --dry-run` / `affiliates:award-bonuses --month=YYYY-MM [--type=...]`.

### Bonus settings

The Bonus Settings page edits all four performance-bonus programs (top performer, recruitment, consistency, growth) in the `affiliate-bonuses` settings group. Values resolve settings-first with `affiliates.bonuses` config fallback when settings are unmigrated. Performance-bonus rule types are excluded from per-program commission rules because bonuses run globally.

### Payout settings

The Payout Settings page edits the global payout minimum and the per-currency minimum map (minor units) in the `affiliate-payouts` settings group. Values resolve settings-first with `affiliates.payouts` config fallback when settings are unmigrated.

### Reports page currencies

The reports page never blends money across currencies. Summary totals convert to `affiliates.currency.default` only when conversions span currencies; when an exchange rate is missing the total shows `—` with a "Missing exchange rate" hint and the Totals-by-Currency table carries the exact legs. Top-affiliates and trend tables print one row per currency with an explicit Currency column.

## Widgets

### Always registered

- `AffiliateStatsWidget`
- `PerformanceOverviewWidget`
- `RealTimeActivityWidget`

### Feature-gated widgets

- `FraudAlertWidget` (`features.admin.fraud_monitoring`)
- `PayoutQueueWidget` (`features.admin.payouts`)
- `UplineVisualizationWidget` (`features.admin.upline_visualization`)

## Commission tracking gate behavior

When `affiliates.features.commission_tracking.enabled` is `false`, these admin features are suppressed even if enabled in `filament-affiliates` config:

- payouts
- programs
- commission management

## Conversions table

`AffiliateConversionResource` lists conversions with affiliate, reference,
commission, and status columns, plus two provenance columns:

- **Origin** — where the conversion was recorded (`storefront`,
  `marketplace`, `network`, …).
- **Source Ref** — the upstream pointer (network link code, etc.).

Row actions approve, reject, mark paid, and **reverse**. Reverse asks for
a reason and routes through `ReverseAffiliateConversion`: the original is
marked reversed and a negated companion conversion posts, so readers that
sum posted rows stay correct. All mutations authorize through the
conversion policy (`affiliate_conversion.update` / `affiliate.approve`)
and re-resolve the record in owner scope.

## Owner scope and write safety

Resource/page filters are not authorization by themselves.

Write paths should:

- authorize via policy/ability checks,
- re-resolve submitted IDs server-side in owner scope,
- fail validation when IDs are out-of-scope.

Recent hardened write paths include support-ticket, link/program membership, and commission-promotion affiliate targeting flows.

Payout rejection on `PayoutBatchPage` routes through the domain payout-status action and releases reserved funds; affiliate ownership on the affiliate form is resolved server-side from the linked user (submitted owner tuples are never trusted); affiliate pickers search lazily within the current owner scope.

`ManageAffiliateCommissionSettings` requires the `affiliates.commission.update` or `affiliate.update` ability. Rates are validated as percentages between 0 and 100, and the level count is capped by `affiliates.upline.max_depth`.

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
