---
title: Troubleshooting
---

## Common Issues

### Duplicate follows/bookmarks/responses

The service prevents duplicate active records for the same actor/subject pair. If you see unexpected results, check for previous `unfollowed`, `removed`, or `cancelled` records that may need reactivating. The package transitions statuses — it never deletes rows.

### Reminders not sending

Ensure the console command is scheduled in your kernel:

```php
Schedule::command('engagement:send-due-reminders')->everyMinute();
```

Check:
- The reminder's `remind_at` is in the past or within the processing window
- The notification channels are configured (`engagement.reminder.default_channels`)
- The recipient model implements `Illuminate\Notifications\Notifiable`

### Subscriptions not matching

Ensure the matching command is scheduled:

```php
Schedule::command('engagement:match-subscriptions')->hourly();
```

Check that the subscription criteria (`criteria` JSON column) matches the data passed to `matchingSubscriptions()`.

### Events integration not working

Verify both `aiarmada/events` and `aiarmada/engagement` are installed. The adapter auto-detects via `class_exists()` in the service provider. No manual configuration is needed.

### Share URL generation failing

Ensure the subject model has a `shareUrl()` method (or implements `Shareable`). The `Event` model includes a `shareUrl()` method that uses the configured `events.shares.route_name` route. Configure the route name in `config/events.php` or via the `EVENTS_SHARE_ROUTE` env var if your app uses a different route name.

For custom URL generation, bind your own `ShareUrlGenerator` implementation.

### Traits not finding expected methods

The engagement traits add methods to your models at runtime. If a method is not found, verify:
- The trait is imported correctly (`use AIArmada\Engagement\Traits\CanFollow`)
- The model uses the trait in its class definition

### Model class overrides not applied

If you've configured custom model classes in `config/engagement.php` under the `models` key and they're not being used, check that you're resolving models through the contracts (e.g., `EngagementManager`) rather than instantiating them directly. The contracts use config-resolved model classes.
