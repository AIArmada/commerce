---
title: Troubleshooting
---

# Troubleshooting

## Migrations not publishing

Ensure the CommunicationsServiceProvider is registered:

```php
// config/app.php or composer.json extra.laravel.providers
AIArmada\Communications\CommunicationsServiceProvider::class,
```

Then publish:

```bash
php artisan vendor:publish --provider="AIArmada\Communications\CommunicationsServiceProvider" --tag="communications-migrations"
```

## Notifications not being captured

1. Verify `communications.features.native_capture` is `true`
2. Check that `RecordNativeNotificationSending` and `RecordNativeNotificationSent` listeners are registered
3. Confirm the notification is sent through Laravel's `Notification::send()` or `$notifiable->notify()`

## Owner scoping issues

If queries return unexpected results:

1. Verify `communications.features.owner.enabled` matches your tenancy model
2. Check that `OwnerContext::withOwner()` wraps the operation
3. For raw `DB::table()` queries, apply `OwnerQuery::applyToQueryBuilder()`

## PHPStan errors

If you encounter PHPStan errors related to dynamic properties on the trait (`HasCommunicationContext`), ensure your notification class either uses the trait or extends `BaseCommunicationNotification`.
