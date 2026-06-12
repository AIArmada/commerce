---
title: Usage
---

## Managing Navigation

Navigate to **Settings → Navigation** in your Filament panel.

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

Groups are rendered in sort order. Drag to reorder.

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

The settings page remains accessible but overrides are not applied.
