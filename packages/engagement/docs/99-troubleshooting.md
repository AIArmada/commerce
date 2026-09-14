---
title: Troubleshooting
---

## Common Issues

### Duplicate follows/bookmarks/responses

The service prevents duplicate active records for the same actor/subject pair. If you see unexpected results, check for previous `unfollowed`, `removed`, or `cancelled` records that may need reactivating. The package transitions statuses — it never deletes rows.

Concurrent follow, bookmark, response, reaction, subscription, and
collection-item requests are protected by the identity unique indexes (folded
into the package's create migrations) and transaction-level row locking. Make
sure the package migrations have been run when deploying this behavior.

> [!WARNING]
> Identity unique indexes include the nullable `owner_type`/`owner_id` columns.
> MySQL treats `NULL` as distinct in unique indexes, so duplicate **global**
> (ownerless) rows are still possible on MySQL for counters, follows,
> bookmarks, responses, reactions, and subscriptions; PostgreSQL and SQLite get a
> partial unique index for the global-owner case on counters. Keep engagement
> rows owner-assigned (the default) to stay inside the enforced path.

### Reminders not sending

Ensure the console command is scheduled in your kernel:

```php
Schedule::command('engagement:send-due-reminders')->everyMinute();
```

Check:
- The reminder's `remind_at` is in the past or within the processing window
- The notification channels are configured (`engagement.reminder.default_channels`)
- The recipient model implements `Illuminate\Notifications\Notifiable`

The due-reminder command locks each candidate while dispatching it. An overlapping
worker may therefore skip a reminder that another worker has already claimed;
this is expected. A reminder already marked `sent` or `failed` is not transitioned
again by `markSent` or `markFailed`.

Offset reminders (`offset_minutes`) resolve `remind_at` at creation time from
`Remindable::reminderAnchorTime()` minus the offset, so they always carry an
absolute instant. An offset without `anchor_type`, an unresolvable anchor, or a
call that passes both `remind_at` and `offset_minutes` throws an
`InvalidArgumentException`. Reminders created before this behavior with
`remind_at = NULL` never become due; re-create them through `setReminder`.

A reminder whose notification class (or the configured default) is not listed in
`engagement.notifications.allowed` fails with the reason recorded on the row;
add the class to the allowlist to deliver it.

### Subscriptions not matching

Ensure the matching command is scheduled:

```php
Schedule::command('engagement:match-subscriptions')->hourly();
```

Check that the subscription criteria (`criteria` JSON column) matches the data passed to `matchingSubscriptions()`.

The match command only feeds `subject_type`/`subject_id` (plus the subject's
`HasSubscriptionMatchContext` pairs) into criteria matching. Criteria that
reference raw model attributes never match; implement
`HasSubscriptionMatchContext` on the subject to expose the attributes you match
on.

### Events adapter rejects an event model

The engagement manager requires `CanInteract` on actors and the matching subject marker contract. Add the contract to the host event model (and the corresponding public trait when needed), or keep event attendance flows in the events package. Publication events are intentionally not auto-forwarded to engagement subscriptions.

### Share URL generation failing

Ensure the subject model has a `shareUrl()` method (or implements `Shareable`). The `Event` model includes a `shareUrl()` method that uses the configured `events.shares.route_name` route. Configure the route name in `config/events.php` or via the `EVENTS_SHARE_ROUTE` env var if your app uses a different route name.

For custom URL generation, bind your own `ShareUrlGenerator` implementation.

### Traits not finding expected methods

The engagement traits add methods to your models at runtime. If a method is not found, verify:
- The trait is imported correctly (`use AIArmada\Engagement\Traits\CanFollow`)
- The model uses the trait in its class definition

### Model class overrides not applied

If you've configured custom model classes in `config/engagement.php` under the `models` key and they're not being used, check that you're resolving models through the contracts (e.g., `EngagementManager`) rather than instantiating them directly. The contracts use config-resolved model classes.
