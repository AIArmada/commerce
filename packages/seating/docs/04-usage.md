---
title: Seating Usage
---

## Creating a Seat Map

```php
use AIArmada\Seating\Models\SeatMap;
use AIArmada\Seating\Models\SeatSection;
use AIArmada\Seating\Models\Seat;

$map = SeatMap::query()->create([
    'name' => 'Main Hall',
    'slug' => 'main-hall',
    'version' => 1,
]);

$section = SeatSection::query()->create([
    'seat_map_id' => $map->id,
    'code' => 'A',
    'name' => 'Orchestra',
    'sort_order' => 1,
]);

Seat::query()->create([
    'seat_section_id' => $section->id,
    'row_label' => 'A',
    'row_number' => 1,
    'column_number' => 1,
    'seat_label' => '1',
    'status' => 'available',
    'category' => 'standard',
]);
```

## Allocating Seats

```php
use AIArmada\Seating\Contracts\SeatAllocatorInterface;

$results = app(SeatAllocatorInterface::class)->allocate(
    map: $map,
    quantity: 2,
    heldByType: 'cart',
    heldById: $cartId,
    reference: 'checkout-' . $checkoutId,
    categoryPreferences: ['vip'],
);
```

Each result is an `AllocationResult` DTO with `seatId`, `sectionCode`, `rowLabel`, `seatLabel`, and `category`.

## Livewire Component

```blade
<livewire:seating.seat-map :seatMapId="$seatMapId" :selectable="true" :showLegend="true" />
```

### Events

- `seat-picked` — dispatched when a seat is selected
- `seat-deselected` — dispatched when a seat is deselected
- `selection-cleared` — dispatched when all selections are cleared

### Properties

| Property      | Type    | Default | Description                              |
|---------------|---------|---------|------------------------------------------|
| `seatMapId`   | string  | null    | The seat map UUID                         |
| `selectable`  | bool    | true    | Allow seat selection                      |
| `showLegend`  | bool    | true    | Show the status legend                    |
| `category`    | string  | null    | Filter seats by category                  |
| `picked`      | array   | []      | Currently selected seat IDs (livewire)    |

## Console Command

```bash
php artisan seating:release-expired-holds
```

Options:

- `--chunk=500` — chunk size for batch deletion

## Custom Allocator

Implement `SeatAllocatorInterface` and bind it in the container:

```php
use AIArmada\Seating\Contracts\SeatAllocatorInterface;

app()->bind(SeatAllocatorInterface::class, MyCustomAllocator::class);
```
