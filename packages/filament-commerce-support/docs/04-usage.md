---
title: Usage
---

## Managing Navigation

Navigate to **Settings → Navigation** in your Filament panel.

The authenticated panel user must be authorized for the configured
`filament-commerce-support.navigation.permission` Gate ability.

## Managing Exchange Rates

Navigate to **Settings → Exchange Rates** in your Filament panel
(gated by `filament-commerce-support.exchange_rates.enabled` and the
`exchange_rates.permission` Gate ability).

Edit the base currency and the current rates table (units per one base
unit, reporting only — never money movement). Use **Snapshot current
rates** to store today's table as a dated history entry so historical
reports never shift when rates move. Values persist in the
`commerce-exchange-rates` settings group; the provider falls back to
`commerce-support.currency.exchange_rates` config when settings are
unmigrated.

### Groups

Define navigation groups with:

| Field | Description |
|-------|-------------|
| Group Key | Unique identifier (e.g., `catalog`, `operations`) |
| Label | Display name (e.g., "Catalog", "Operations") |
| Icon | Heroicon name (e.g., `heroicon-o-shopping-bag`) |
| Sort Order | Sort priority (lower = first) |
| Collapsible | Whether the group can be collapsed |
| Collapsed by Default | Initial state |

Groups are rendered in sort order. Drag to reorder, or type an explicit
sort value (typed values win; untouched inputs follow drag position).

Items left without a group appear under **Ungrouped Items**. Typing a group
key into that section adopts its items into the new group. The key
`__ungrouped__` is reserved and rejected by validation.

Adding the same component twice keeps the first occurrence and warns about
the duplicate. Overrides for currently unregistered components are kept and
labelled `[Unregistered]` so disabling a package never loses its settings.

### Item Overrides

Override navigation settings for any registered resource or page by its fully qualified class name:

| Field | Description |
|-------|-------------|
| Component Class | FQCN (e.g., `AIArmada\FilamentProducts\Resources\ProductResource`) |
| Hidden | Hide from navigation |
| Group | Reassign to a different group |
| Sort Order | Sort position within the group |
| Parent Item | Nest under this parent item label |

> Overrides take effect immediately after saving. No cache clear required.

## Disabling without uninstalling

```php
// config/filament-commerce-support.php
'navigation' => [
    'enabled' => false,
],
```

The settings page is removed from the panel and overrides are not applied.

## Auditing related state changes

`AIArmada\FilamentCommerceSupport\Concerns\AuditsRelatedStateChanges` audits
related-state edits from Livewire pages. Capture a snapshot before the edit and audit
the differences after it:

```php
use AIArmada\FilamentCommerceSupport\Concerns\AuditsRelatedStateChanges;
use App\Models\Event; // your auditable model
use Illuminate\Database\Eloquent\Model;

class ManageEventSchedule
{
    use AuditsRelatedStateChanges;

    protected function getRelatedAuditSnapshot(Model $record): array
    {
        return ['occurrences' => $record->occurrences()->pluck('starts_at', 'id')->all()];
    }

    public function save(Event $event): void
    {
        $this->captureRelatedAuditSnapshot($event);
        // ... mutate related rows ...
        $this->auditRelatedStateChanges($event, 'schedule_updated');
    }
}
```

Records that are not auditable, or snapshots without changes, record nothing. The
snapshot shape is page-defined; the model needs `HasCommerceAudit` on a model
implementing owen-it's `Auditable` contract.
