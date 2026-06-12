---
title: Troubleshooting
---

## Common Issues

### Resources not appearing in navigation

Check that the resource is enabled in `config/filament-engagement.php` under `resources.enabled`. Resources with a key set to `false` are not registered.

### Follow/bookmark/reaction actions not working

Reusable actions call the core `EngagementManager` contract. Ensure:
- The authenticated user model uses the appropriate trait (`CanFollow`, `CanBookmark`, `CanReact`, etc.)
- The target model uses the matching trait (`HasFollowers`, `HasBookmarks`, `HasReactions`, etc.)

### Relation managers returning no data

Relation managers rely on Eloquent relationships defined on the parent model. Ensure the parent model uses the correct trait (e.g., `HasFollowers` for `follows` relationship).

### "Action not visible" in table

Actions like Follow/Bookmark/React are context-aware. If the current user is already following the record, the Follow action may hide itself in favor of an Unfollow action. Check the action's `visible()` logic.

### Plugin not loading

Ensure the plugin is registered in your panel configuration:

```php
->plugins([
    FilamentEngagementPlugin::make(),
])
```

### Engagement overview widget showing zero counts

Ensure the core `aiarmada/engagement` migrations have been published and run. The widget queries the actual database tables.
