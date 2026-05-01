# Filament Docs

Filament admin integration for `aiarmada/docs`.

## What it provides

- `DocResource` for documents
- `DocTemplateResource` for templates
- `DocSequenceResource` for numbering sequences
- `DocEmailTemplateResource` for email templates
- `AgingReportPage` and `PendingApprovalsPage`
- Dashboard widgets for stats, recent documents, revenue, status breakdown, and quick actions
- Secure download routing for generated PDFs

## Installation

```bash
composer require aiarmada/filament-docs
```

Register the plugin in your Filament panel:

```php
use AIArmada\FilamentDocs\FilamentDocsPlugin;
use Filament\Panel;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentDocsPlugin::make()
                ->navigationGroup('Billing'),
        ]);
}
```

Optional config publish:

```bash
php artisan vendor:publish --tag=filament-docs-config
```

## Configuration

`config/filament-docs.php` contains:

- `navigation.group`
- `features.auto_generate_pdf`
- `resources.navigation_sort.*`

`features.auto_generate_pdf` is used by the create page when no explicit `generate_pdf` value is submitted.

The fluent plugin API supports:

- `navigationGroup()`
- `docResource()`
- `docTemplateResource()`
- `docSequenceResource()`
- `docEmailTemplateResource()`
- `agingReportEnabled()`
- `pendingApprovalsEnabled()`
- `docStatsWidgetEnabled()`
- `quickActionsWidgetEnabled()`
- `recentDocumentsWidgetEnabled()`
- `revenueChartWidgetEnabled()`
- `statusBreakdownWidgetEnabled()`

## Email template triggers

The email template resource supports these triggers:

- `send`
- `due_soon`
- `reminder`
- `overdue`
- `paid`
- `created`

## Owner scoping

When `docs.owner.enabled` is on, the package applies owner checks to:

- resource queries
- widgets and report pages
- download routes
- record mutation actions

## Documentation

See `packages/filament-docs/docs/` for package docs:

- `00-overview.md`
- `01-installation.md`
- `02-resources.md`
- `03-configuration.md`
- `04-pages-widgets.md`
- `05-customization.md`
- `99-troubleshooting.md`
