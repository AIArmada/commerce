---
title: Usage
---

# Usage

## Resources

### Event series

`EventSeriesResource` manages reusable series/grouping records.

Features:

- name + slug management
- active toggle
- metadata editing
- event counts in the table and infolist

### Events

`EventResource` manages the reusable event definitions.

Features:

- series relationship
- status management
- summary + description fields
- default timezone and duration
- optional product link
- occurrences relation manager on the event detail pages

### Occurrences

`OccurrenceResource` manages scheduled runs of an event.

Features:

- linked event and venue
- capacity management
- starts/ends scheduling
- registration open/close windows
- check-in open/close windows
- optional product + variant links
- registrations relation manager on occurrence pages
- filters for status, event, venue, and date range

### Venues

`VenueResource` manages venue/contact/location details.

Features:

- slug generation
- contact name, email, phone
- address fields
- timezone
- occurrence counts

### Registrations

`RegistrationResource` manages attendees and linked commerce data.

Features:

- registration codes
- participant name/email/phone
- occurrence relationship
- linked order, order item, purchaser customer, and participant customer
- lifecycle fields such as `checked_in_at` and `cancelled_at`
- quick actions for check-in and cancellation

## Resource actions

### Registration check-in

The registration table and record pages expose a `Check In` action that calls the core `RegistrationService` with Filament as the source.

That action only succeeds when the registration is currently `confirmed` and the linked occurrence is inside its configured check-in window.

### Registration cancellation

The registration table and record pages expose a `Cancel` action with a reason field, which delegates to the core `RegistrationService`.

The cancellation reason is stored in the registration metadata by the core package.

## Owner-aware admin queries

All resources apply `OwnerUiScope::apply(..., includeGlobal: false)` to their main queries and to related record selectors. In practice that means:

- tables only show records visible to the current owner
- relationship selects only list records visible to the current owner
- navigation badges are computed from owner-scoped queries

## Example panel registration

```php
use AIArmada\FilamentEvents\FilamentEventsPlugin;

->plugins([
    FilamentEventsPlugin::make(),
])
```

Once the plugin is registered, the package adds the full event admin surface to that panel automatically.

## Related domain docs

For model creation, fulfillment, and programmatic registration workflows, see the core [`aiarmada/events` usage guide](../../events/docs/04-usage.md).
