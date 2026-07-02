---
title: Troubleshooting
---

## Seat maps not appearing

Verify the plugin is registered in your panel provider:

```php
$panel->plugins([
    FilamentSeatingPlugin::make(),
]);
```

## Config changes not taking effect

Clear the config cache:

```bash
php artisan config:clear
```
