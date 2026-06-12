# 01 — Architecture Principles

## Goal

Build a generic Laravel Interactions package that stores user intent and preference toward any model, without coupling to a specific application domain.

It must support:

- Users following events, occurrences, speakers, masjids, organizations, topics, books, series, products, or any model.
- Users bookmarking/saving any model.
- Users organizing bookmarks into collections.
- Users responding to any model, such as `interested`, `going`, `maybe`, or `not_going` for event occurrences.
- Users reacting to any model, such as `like`, `love`, `useful`, `support`, `amin`.
- Users subscribing to rule-based interests such as “live online events”, “events near me”, “new recordings”, “events by followed speakers”.
- Users setting reminder preferences while actual delivery is handled by Laravel Notifications.

## What this package is not

This package is not Analytics.

Do not store:

```text
views
clicks
impressions
link opens
map opens
search logs
funnel metrics
page views
```

Those belong to the Analytics package.

This package is not Events.

Do not store:

```text
events
event_occurrences
event_sessions
event_registrations
event_passes
event_attendances
```

Those belong to the Events package.

This package is not Commerce.

Do not store:

```text
orders
payments
checkouts
invoices
refunds
paid subscription plans
```

Those belong to Checkout/Orders/Payments/Products packages.

This package is not Authorization.

Do not store:

```text
admin roles
permission grants
ownership rules
approval permissions
```

Those belong to Authorization or the domain package.

This package is not a Notifications package.

Do not build notification delivery infrastructure. Laravel Notifications are sufficient and should be extended by the host app. This package may create reminder/subscription preferences and dispatch application events that trigger Laravel Notifications.

## Interaction vs analytics

```text
User clicked event page
= Analytics

User clicked Going
= Interactions

User registered officially
= Events

User checked in at venue
= Events

User opened Waze link
= Analytics

User bookmarked an occurrence
= Interactions

User subscribed to live online events
= Interactions

Email notification was delivered
= Laravel Notifications / host app notification infrastructure
```

## Entity-based vs rule-based interest

### Follow

Use `follows` when the user follows a specific entity:

```text
Follow Ustaz Ahmad
Follow Masjid Al-Falah
Follow Event Series: Riyadhus Solihin
Follow Topic: Fiqh
```

### Subscription

Use `subscriptions` when the user subscribes to a rule/filter:

```text
Notify me about all live online events
Notify me when recordings are available
Notify me about Fiqh talks near me
Notify me about new events by followed speakers
Notify me about new events at followed masjids
```

## Response vs registration vs attendance

```text
responses
= intention / RSVP-like signal
= interested, going, maybe, not_going

event_registrations
= official signup / booking / ticket registration

event_attendances
= actual check-in / physical or online attendance truth
```

Never let `responses` replace `event_registrations` or `event_attendances`.

## Reminder vs notification delivery

```text
reminders
= user preference / scheduled intent
= remind me one hour before

Laravel Notification
= actual message delivery
= mail, database, broadcast, SMS, WhatsApp via host app channels
```

The Interactions package may own `reminders`, but not notification delivery logs.

## Generic polymorphism

Every interaction should support any model:

```text
actor_type / actor_id
subject_type / subject_id
```

Specific table examples:

```text
follower_type / follower_id
followable_type / followable_id

bookmarker_type / bookmarker_id
bookmarkable_type / bookmarkable_id

responder_type / responder_id
respondable_type / respondable_id
```

Do not create duplicate domain-specific tables such as:

```text
event_followers
speaker_followers
masjid_followers
saved_events
saved_speakers
saved_masjids
event_responses
```

## Lifecycle design

Use lifecycle timestamps where state changes matter:

```text
followed_at
muted_at
unfollowed_at
bookmarked_at
removed_at
responded_at
cancelled_at
changed_at
reacted_at
subscribed_at
unsubscribed_at
remind_at
sent_at
expired_at
archived_at
```

Use booleans only for stable flags:

```text
is_default
is_system
is_primary
is_public_counter_visible
```

## No database enforcement dependency

The package must not rely on database foreign keys or cascading deletes.

Instead, enforce integrity through:

- service methods,
- validation rules,
- unique indexes,
- state transition guards,
- policy checks,
- cleanup jobs,
- reconciliation commands,
- model event listeners where appropriate.

## Package integration strategy

The package must integrate through:

- contracts/interfaces,
- traits,
- service contracts,
- Null implementations,
- bridge adapters,
- `class_exists()` package detection,
- service provider bindings,
- Laravel events/listeners,
- hooks/callbacks,
- optional Filament resources.

It must not break if Events, Analytics, Commerce, Authorization, or domain packages are not installed.
