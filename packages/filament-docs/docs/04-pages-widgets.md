---
title: Pages & Widgets
---

# Pages & Widgets

## Pages

### Aging Report Page

Shows outstanding documents grouped into aging buckets:

- Current
- 1-30 days
- 31-60 days
- 61-90 days
- 90+ days

It is registered inside the active Filament panel, so the final URL depends on your panel path.

### Pending Approvals Page

Shows approvals assigned to the current user.

Supported interactions:

- approve with comments
- reject with comments
- filter by document type

## Widgets

All shipped widgets apply owner scoping through `DocsOwnerScope`.

### DocStatsWidget

Displays totals for:

- all documents
- draft documents
- pending documents
- paid documents
- overdue documents

### QuickActionsWidget

Provides shortcuts for:

- new invoice
- new quotation
- new credit note
- new receipt
- new delivery note
- new proforma invoice
- aging report

### RecentDocumentsWidget

Displays a compact recent-documents table with:

- document number
- type
- customer
- total
- status
- issue date

### RevenueChartWidget

Displays a line chart of paid revenue over the last 30 days.

### StatusBreakdownWidget

Displays a doughnut chart of document status distribution.

## Widget Placement

To add widgets to your dashboard:

```php
<?php

namespace App\Providers;

use AIArmada\FilamentDocs\Widgets\DocStatsWidget;
use AIArmada\FilamentDocs\Widgets\RecentDocumentsWidget;
use AIArmada\FilamentDocs\Widgets\RevenueChartWidget;
use Filament\Panel;
use Filament\PanelProvider;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->widgets([
                DocStatsWidget::class,
                RevenueChartWidget::class,
                RecentDocumentsWidget::class,
            ]);
    }
}
```

If you build custom widgets around docs data, apply the same owner-scoping rules as the built-in widgets and pages.
