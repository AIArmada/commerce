---
title: Usage Guide
---

## Managing Seat Maps

Navigate to **Venue → Seat Maps** in the Filament admin panel to create, edit, and view seat maps.

### Creating a Seat Map

1. Click **New Seat Map**
2. Enter a name and slug (required, unique per owner)
3. Set the version (minimum 1) and status
4. Save the map, then add sections and seats via the domain API (`AIArmada\Seating`)

> **Note:** Visual seat map editing and the occupancy viewer are planned but not yet available in the admin panel.

### Managing seat maps on host resources

`AIArmada\FilamentSeating\RelationManagers\SeatMapsRelationManager` manages seat maps
on any host resource whose model has a `seatMaps` relationship. Register it from the
host resource's `getRelations()` (or through the host package's relation-manager
config seam, such as `filament-events.resources.event_relation_managers`):

```php
use AIArmada\FilamentSeating\RelationManagers\SeatMapsRelationManager;

public static function getRelations(): array
{
    return [SeatMapsRelationManager::class];
}
```

The form and table reuse the seat-map resource definitions. Edit and delete actions
revalidate the record against the current owner scope.

## Authorization

`SeatMapResource` gates access through `FilamentPermission` abilities under the
`seat-map.*` prefix: `viewAny`, `view`, `create`, `update`, and `delete`.
The overview widget and resource queries scope to the resolved owner and fail
closed without an owner context.
