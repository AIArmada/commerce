---
title: Troubleshooting
---

# Troubleshooting

## Resources not appearing in navigation

1. Verify `filament-communications` config is published
2. Check `navigation.group` config value matches your panel's navigation groups
3. Clear Filament cache: `php artisan filament:cache`

## Owner scoping

All queries use `getEloquentQuery()` with owner-aware scoping. If data appears incorrect:

1. Verify `communications.features.owner` configuration
2. Check the current owner context

## Missing resources

Ensure both `aiarmada/communications` and `aiarmada/filament-communications` are installed. The service provider for the core package must be registered.
