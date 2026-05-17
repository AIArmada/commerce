---
title: Troubleshooting
---

# Troubleshooting

## "Growth experiment is not accessible in the current owner scope"

**Cause:** You are resolving assignments or mutating records outside the correct owner context.

**Fix:**

1. Ensure your application binds `OwnerResolverInterface` correctly for request-driven flows.
2. For jobs, commands, and service code, enter the owner context explicitly.
3. Use `OwnerContext::withOwner(null, ...)` only for intentional global records.

```php
use AIArmada\CommerceSupport\Support\OwnerContext;

OwnerContext::withOwner($store, function (): void {
    // Safe owner-scoped growth operations
});
```

## "Signal identity/session must belong to the same tracked property as the experiment"

**Cause:** The identity or session you passed to `ResolveExperimentAssignment` was created for a different Signals tracked property.

**Fix:**

- resolve assignments with a `SignalIdentity` or `SignalSession` from the same tracked property as the experiment
- if you only have an anonymous browser or cart token, use the `anonymousId` argument instead

## "Explicit global owner context is required to resolve assignments for global growth experiments"

**Cause:** You tried to resolve an assignment for a persisted global experiment while owner scoping is enabled, but the current context was not explicitly global.

**Fix:** Wrap the assignment resolution in explicit global context:

```php
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Growth\Actions\ResolveExperimentAssignment;

OwnerContext::withOwner(null, function () use ($experiment): void {
    app(ResolveExperimentAssignment::class)->handle($experiment, anonymousId: 'cart-123');
});
```

## An experiment shows no winner yet

**Cause:** The experiment has no assignments yet, or there is not enough qualifying attributed activity for the configured winner metric.

**Expected behavior:** `AggregateExperimentMetrics` returns `winner_variant_id = null` until the experiment has both traffic and qualifying data for the configured winner metric.

This is intentional. The Filament UI renders a pending state instead of inventing a winner.

## Preset settings did not appear on a new experiment

**Cause:** One of these is usually true:

- `growth.features.preset_modules.enabled` is `false`
- the experiment already provided explicit values for the preset-driven fields
- your UI did not prefill fields before save, even though the model still applied defaults on creation

**Fix:**

- keep preset modules enabled
- leave preset-driven fields blank if you want automatic defaults
- call `ResolveExperimentPreset` explicitly in custom form flows

## Projected experiment context is missing from Signals events

**Cause:** Enrichment only works when:

- `growth.integrations.signals.enabled` is `true`
- the source model exposes a matching `customer_id`, `cart_id`, or `metadata.cart_id` value that can be resolved back to existing assignments
- the tracked property passed to the enricher matches the assignment's experiment tracked property

Double-check the order or checkout payload and the assignment rows being created.

## Deleting a tracked property or experiment leaves related data behind

**Cause:** Growth uses application-level cascades, not database cascades.

**Fix:** Delete through Eloquent so model hooks run:

```php
$experiment->delete();
$trackedProperty->delete();
```

Avoid raw query builder deletes for owner-scoped domain models.

## Metrics look inflated

**Cause:** Purchase counts and conversion rates answer different questions:

- `purchases` is the raw count of purchase events
- `conversion_rate` is normalized against assigned visitors and deduplicated by assignment when attribution data is available

If your custom Signals events omit experiment context or assignment ids, fix the enrichment flow first.

## More help

- Review [configuration](./03-configuration.md)
- Review [usage](./04-usage.md)
- Check your Signals tracked property setup
- Inspect application logs for owner-scoping or validation exceptions