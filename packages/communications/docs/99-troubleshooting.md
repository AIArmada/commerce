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

## Destination resolves to the wrong address

1. Check for an active `CommunicationDestination` on the recipient morph + channel (`status = active`). Primary and verified rows win.
2. If you expect only notifiable routing, confirm no destination row exists or rebind `DestinationResolver` to `NotifiableDestinationResolver`.
3. Confirm the notifiable implements `routeNotificationFor($channel)` or `routeNotificationFor{Driver}()` for the fallback path.

## Owner scoping issues

If queries return unexpected results:

1. Verify `communications.features.owner.enabled` matches your tenancy model
2. Check that `OwnerContext::withOwner()` wraps the operation
3. For raw `DB::table()` queries, apply `OwnerQuery::applyToQueryBuilder()`

## Inbox entries are missing

1. Confirm the recipient model uses `HasInbox`
2. Confirm `communications.features.owner.enabled` matches your owner context
3. Check that the inbox was created through `DispatchInboxNotificationAction` or `NotificationInboxService`
4. Verify the `communications.inbox-index` component is registered when you expect the Livewire screen

## Inbox pruning is not deleting rows

`communications:prune-inboxes` only removes archived entries older than the cutoff.

1. Ensure the rows have `archived_at` set
2. Check the `--before` date if you overrode it
3. Run the command without `--dry-run` to perform the delete

## PHPStan errors

If you encounter PHPStan errors related to dynamic properties on the trait (`HasCommunicationContext`), ensure your notification class either uses the trait or extends `BaseCommunicationNotification`.
