---
title: Configuration
---

```php
// config/filament-seating.php
return [
    'navigation' => [
        'group' => 'Venue',
    ],
    'resources' => [
        'enabled' => [
            'seat_map' => true,
        ],
        'navigation_sort' => [
            'seat_map' => 1,
        ],
    ],
];
```
