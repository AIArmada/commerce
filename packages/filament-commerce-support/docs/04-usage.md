---
title: Usage
---

## Managing Navigation

Navigate to **Settings → Navigation** in your Filament panel.

The authenticated panel user must be authorized for the configured
`filament-commerce-support.navigation.permission` Gate ability.

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
