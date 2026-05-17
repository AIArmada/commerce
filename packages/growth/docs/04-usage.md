---
title: Usage
---

# Usage

## Create an experiment

Experiments are tied to a Signals `TrackedProperty` and should be created inside an explicit owner context when owner scoping is enabled.

```php
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use AIArmada\Signals\Models\TrackedProperty;

$experiment = OwnerContext::withOwner($store, function () use ($trackedProperty): Experiment {
    /** @var Experiment $experiment */
    $experiment = Experiment::query()->create([
        'tracked_property_id' => $trackedProperty->getKey(),
        'name' => 'Pricing Page Test',
        'slug' => 'pricing-page-test',
        'module_type' => 'pricing_test',
        'status' => 'active',
    ]);

    Variant::query()->create([
        'experiment_id' => $experiment->getKey(),
        'code' => 'A',
        'name' => 'Control',
        'traffic_percentage' => 50,
        'position' => 1,
        'is_control' => true,
        'is_active' => true,
    ]);

    Variant::query()->create([
        'experiment_id' => $experiment->getKey(),
        'code' => 'B',
        'name' => 'Higher Anchoring',
        'traffic_percentage' => 50,
        'position' => 2,
        'is_control' => false,
        'is_active' => true,
    ]);

    return $experiment->fresh(['variants']) ?? $experiment;
});
```

## Use module presets

Resolve preset defaults when you need to seed UI state or custom creation flows:

```php
use AIArmada\Growth\Actions\ResolveExperimentPreset;

$preset = app(ResolveExperimentPreset::class)->handle('funnel_test');

// ['module_type' => 'funnel_test', 'goal_event_name' => 'order.paid', ...]
```

When `growth.features.preset_modules.enabled` is `true`, blank module-specific fields are also hydrated from the preset when the experiment is created.

If `module_type` is blank, Growth falls back to `growth.defaults.module_type`. If you pass a custom unsupported module value, Growth preserves that explicit module type and only applies the generic goal and winner defaults.

## Resolve a sticky assignment

Use `ResolveExperimentAssignment` to allocate a subject to a variant.

```php
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Growth\Actions\ResolveExperimentAssignment;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Signals\Models\SignalIdentity;
use AIArmada\Signals\Models\SignalSession;

$assignment = OwnerContext::withOwner($store, function () use ($experiment, $identity, $session) {
    return app(ResolveExperimentAssignment::class)->handle(
        $experiment,
        $identity,
        $session,
    );
});
```

You can also resolve against an anonymous identifier:

```php
$assignment = OwnerContext::withOwner($store, function () use ($experiment) {
    return app(ResolveExperimentAssignment::class)->handle(
        $experiment,
        anonymousId: 'cart-123',
    );
});
```

### Important assignment rules

- the experiment must be accessible in the current owner scope
- the experiment must be `active`
- any provided `SignalIdentity` or `SignalSession` must belong to the **same tracked property** as the experiment
- assignments stay sticky for the same subject

## Project experiment context into Signals event properties

Use `ProjectExperimentContextIntoSignalProperties` before recording checkout or order signals so downstream analytics can attribute revenue back to the assigned variant.

```php
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Growth\Actions\ProjectExperimentContextIntoSignalProperties;

$properties = OwnerContext::withOwner($store, function () use ($order, $trackedProperty): array {
    return app(ProjectExperimentContextIntoSignalProperties::class)->handle(
        $order,
        $trackedProperty,
        [
            'order_id' => $order->getKey(),
        ],
    );
});
```

The action adds keys such as:

- `experiment_id`
- `experiment_slug`
- `variant_id`
- `variant_code`
- `assignment_id`
- `module_type`
- `experiment_contexts`

## Aggregate experiment results

Use `AggregateExperimentMetrics` to compute revenue, conversion, and winner metrics for an experiment.

```php
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Growth\Actions\AggregateExperimentMetrics;

$results = OwnerContext::withOwner($store, function () use ($experiment): array {
    return app(AggregateExperimentMetrics::class)->handle($experiment);
});
```

Example response shape:

```php
[
    'experiment_id' => '...',
    'currency' => 'MYR',
    'winner_metric' => 'revenue_per_visitor',
    'winner_variant_id' => '...',
    'totals' => [
        'assignments' => 128,
        'checkout_starts' => 67,
        'purchases' => 21,
        'refunds' => 1,
        'revenue_minor' => 499000,
    ],
    'variants' => [
        // per-variant metrics
    ],
]
```

If an experiment has no assignments yet, or it still lacks qualifying data for the configured winner metric, `winner_variant_id` is `null`. This lets UIs render a pending state instead of inventing a winner too early.

## Query models directly

```php
use AIArmada\Growth\Models\Assignment;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;

$experiments = Experiment::query()
    ->forOwner()
    ->with(['variants', 'trackedProperty'])
    ->latest()
    ->get();

$activeVariants = Variant::query()
    ->forOwner()
    ->active()
    ->orderBy('position')
    ->get();

$assignments = Assignment::query()
    ->forOwner()
    ->latest('assigned_at')
    ->get();
```

## Optional Filament admin UI

If `aiarmada/filament-growth` is installed, you get:

- experiment and variant resources
- a results page
- dashboard widgets for tracked revenue and recent winners

Register the plugin on your panel and keep the Growth package as the underlying domain layer. For the Filament-specific workflow, see the [`filament-growth` docs](../../filament-growth/docs/01-overview.md).