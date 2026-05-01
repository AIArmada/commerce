---
title: Configuration
---

# Configuration

## Plugin Configuration (Fluent API)

Configure the plugin directly in your panel provider:

```php
use AIArmada\FilamentDocs\FilamentDocsPlugin;
use Filament\Panel;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentDocsPlugin::make()
                ->navigationGroup('Billing')
                ->docResource(\App\Filament\Resources\DocResource::class)
                ->docTemplateResource(\App\Filament\Resources\DocTemplateResource::class)
                ->docSequenceResource(\App\Filament\Resources\DocSequenceResource::class)
                ->docEmailTemplateResource(\App\Filament\Resources\DocEmailTemplateResource::class)
                ->agingReportEnabled(false)
                ->pendingApprovalsEnabled(false)
                ->docStatsWidgetEnabled(false)
                ->quickActionsWidgetEnabled(false)
                ->recentDocumentsWidgetEnabled(false)
                ->revenueChartWidgetEnabled(false)
                ->statusBreakdownWidgetEnabled(false),
        ]);
}
```

## Available Configuration Methods

| Method | Description | Default |
|--------|-------------|---------|
| `navigationGroup(string)` | Set the navigation group | `'Documents'` from config |
| `docResource(string)` | Use a custom `DocResource` class | `DocResource::class` |
| `docTemplateResource(string)` | Use a custom `DocTemplateResource` class | `DocTemplateResource::class` |
| `docSequenceResource(string)` | Use a custom `DocSequenceResource` class | `DocSequenceResource::class` |
| `docEmailTemplateResource(string)` | Use a custom `DocEmailTemplateResource` class | `DocEmailTemplateResource::class` |
| `agingReportEnabled(bool)` | Enable/disable Aging Report | `true` |
| `pendingApprovalsEnabled(bool)` | Enable/disable Pending Approvals | `true` |
| `docStatsWidgetEnabled(bool)` | Enable/disable stats widget | `true` |
| `quickActionsWidgetEnabled(bool)` | Enable/disable quick actions widget | `true` |
| `recentDocumentsWidgetEnabled(bool)` | Enable/disable recent documents widget | `true` |
| `revenueChartWidgetEnabled(bool)` | Enable/disable revenue chart widget | `true` |
| `statusBreakdownWidgetEnabled(bool)` | Enable/disable status breakdown widget | `true` |

## Publishing Configuration

```bash
php artisan vendor:publish --tag=filament-docs-config
```

Creates `config/filament-docs.php`.

## Configuration File

```php
<?php

declare(strict_types=1);

return [
    'navigation' => [
        'group' => 'Documents',
    ],

    'features' => [
        'auto_generate_pdf' => true,
    ],

    'resources' => [
        'navigation_sort' => [
            'docs' => 10,
            'doc_templates' => 20,
            'pending_approvals' => 15,
            'sequences' => 90,
            'email_templates' => 91,
            'aging_report' => 100,
        ],
    ],
];
```

## Notes

- `features.auto_generate_pdf` is used by the create page when `generate_pdf` is not explicitly submitted.
- Resource and page ordering comes from `resources.navigation_sort.*`.
- Base document/PDF/storage settings still live in `config/docs.php`.
