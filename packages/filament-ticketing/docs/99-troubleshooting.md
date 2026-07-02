---
title: Troubleshooting
---

# Troubleshooting

## Common Issues

### Ticketable Type Not Showing in Dropdown

**Problem**: The ticketable type dropdown is empty when creating a ticket type.

**Solution**: Register the ticketable model class. Either via service provider:

```php
use AIArmada\FilamentTicketing\Support\TicketableTypeRegistry;

public function boot(): void
{
    app(TicketableTypeRegistry::class)->register(\App\Models\Workshop::class);
}
```

Or via config:

```php
// config/filament-ticketing.php
'ticketable_types' => [
    \App\Models\Workshop::class,
],
```

Also verify the model implements `AIArmada\Ticketing\Contracts\TicketableInterface`.

### Ticketable Record Select Empty

**Problem**: After selecting a ticketable type (e.g., Workshop), the specific record dropdown is empty.

**Solution**: Check that:

1. Records exist for that model
2. Owner context is set correctly (if owner mode is enabled)
3. The model in the dropdown matches the selected type

### Pass Resource Empty

**Problem**: Pass list shows no passes.

**Solution**:

1. Check the resource is enabled:

```php
// config/filament-ticketing.php
'resources' => [
    'enabled' => [
        'pass' => true,
    ],
],
```

2. Verify owner context — if owner mode is enabled, the list scopes to the current owner:

```php
use AIArmada\CommerceSupport\Support\OwnerContext;

// Ensure owner is resolved
$owner = OwnerContext::resolve();
```

3. Make sure passes exist for the current owner's scope.

### Navigation Group Not Showing

**Problem**: Ticketing navigation group doesn't appear in the panel.

**Solution**:

1. Verify the plugin is registered:

```php
use AIArmada\FilamentTicketing\FilamentTicketingPlugin;

public function panel(Panel $panel): Panel
{
    return $panel->plugins([
        FilamentTicketingPlugin::make(),
    ]);
}
```

2. Check at least one resource is enabled:

```php
'resources' => [
    'enabled' => [
        'ticket_type' => true,
    ],
],
```

3. Run `php artisan filament:assets` to rebuild the Filament assets.

### State Transition Action Missing

**Problem**: A pass state transition button (e.g., "Activate") is not visible.

**Solution**:

1. Verify the pass is in the correct previous state
2. Check the user has the required policy permissions
3. Check owner context — the pass must belong to the current owner scope

### "Action not available" on Pass

**Problem**: A state transition action is listed but throws "action not available".

**Solution**: This means the pass's current state does not allow that transition. Verify the pass's current state:

```bash
php artisan tinker --execute '$pass = \AIArmada\Ticketing\Models\Pass::find("pass-uuid"); echo $pass->state->getValue();'
```

Allowed transitions are documented in the [Usage Guide](04-usage.md#pass-state-transitions).

### Permission Denied

**Problem**: Users see "This action is unauthorized."

**Solution**: Check Laravel policies for the ticketing models:

```bash
php artisan route:list --name=filament.*ticket*
```

Ensure the authenticated user has the correct role or permission in your application's authorization system.

### Plugin Not Loading in Panel

**Problem**: The plugin resources don't show up at all.

**Solution**:

1. Check the plugin is registered in the correct panel config
2. Verify the package is installed: `composer show aiarmada/filament-ticketing`
3. Check for missing dependencies: `composer show aiarmada/ticketing`
4. Rebuild Filament assets: `php artisan filament:assets`

### Config Not Taking Effect

**Problem**: Changes to `config/filament-ticketing.php` don't take effect.

**Solution**:

1. Clear the config cache: `php artisan config:clear`
2. If running Octane, restart workers: `php artisan octane:reload`
3. Verify you're editing the published config, not the package default

### Wrong Navigation Group

**Problem**: Resources appear under the wrong navigation group.

**Solution**: Override the group in config:

```php
// config/filament-ticketing.php
'navigation' => [
    'group' => 'Events', // Instead of "Ticketing"
],
```

The `CommerceNavigation` engine from `commerce-support` supports runtime overrides if configured.

## Debug Mode

Enable detailed logging:

```php
// config/logging.php
'channels' => [
    'filament-ticketing' => [
        'driver' => 'daily',
        'path' => storage_path('logs/filament-ticketing.log'),
        'level' => 'debug',
    ],
],
```

## Getting Help

1. **Check Configuration**: Review `config/filament-ticketing.php`
2. **Enable Debug Mode**: Set `APP_DEBUG=true` in `.env`
3. **Check Logs**: Review `storage/logs/laravel.log`
4. **Test in Isolation**: Create a minimal Filament panel setup
5. **Check Package Versions**: `composer show aiarmada/filament-ticketing aiarmada/ticketing`

## Reporting Issues

When reporting issues, include:

- Laravel version
- PHP version
- PHPStan issue (if applicable)
- Package versions (`composer show`)
- Configuration (sanitized)
- Error message with stack trace
- Steps to reproduce
- Expected vs actual behavior

## Common Gotchas

### Ticketable Type Registration
- **Always** register ticketable models before creating ticket types in the admin
- **Use** either the service provider or config, not both (to avoid duplication)
- **Validate** the model implements `TicketableInterface`

### Resource Visibility
- **Hidden** resources still process requests if accessed directly — disable in config
- **Disabled** resources are not registered with the panel navigation

### Owner Scoping
- **Always** validate owner context in multi-tenant mode
- **Never** trust dropdown options without server-side ID revalidation
- **Use** explicit owner context for cross-tenant operations

### State Actions
- **Terminal** states cannot transition — create a new pass if needed
- **Reasons** are required for most state transitions — always provide context
- **Transfers** are logged immutably — no deletion of transfer records

## Read next

- [Configuration](03-configuration.md) — Review configuration options
- [Usage](04-usage.md) — Review usage patterns
