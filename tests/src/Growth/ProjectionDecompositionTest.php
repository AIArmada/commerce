<?php

declare(strict_types=1);

use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Growth\Actions\BuildExperimentSignalProperties;
use AIArmada\Growth\Actions\ProjectExperimentContextIntoSignalProperties;
use AIArmada\Growth\Models\Assignment;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use AIArmada\Growth\Support\ExperimentAssignmentResolver;
use AIArmada\Growth\Support\ExperimentContextMerger;
use AIArmada\Signals\Models\SignalIdentity;
use AIArmada\Signals\Models\TrackedProperty;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * @return array{owner: User, trackedProperty: TrackedProperty, experiment: Experiment, variant: Variant, identity: SignalIdentity, assignment: Assignment, source: CheckoutSession}
 */
function growthProjectionDecompositionFixture(): array
{
    /** @var User $owner */
    $owner = User::query()->create([
        'name' => 'Growth Decomposition Owner ' . Str::random(6),
        'email' => 'growth-decomposition-' . Str::lower(Str::random(8)) . '@example.com',
        'password' => 'secret',
    ]);

    $trackedProperty = OwnerContext::withOwner($owner, fn (): TrackedProperty => TrackedProperty::query()->create([
        'name' => 'Growth Decomposition Property ' . Str::random(6),
        'slug' => 'growth-decomposition-' . Str::lower(Str::random(8)),
        'write_key' => Str::random(40),
        'type' => 'website',
        'timezone' => 'UTC',
        'currency' => 'MYR',
        'is_active' => true,
    ]));

    [$experiment, $variant] = OwnerContext::withOwner($owner, function () use ($trackedProperty): array {
        /** @var Experiment $experiment */
        $experiment = Experiment::factory()->create([
            'tracked_property_id' => $trackedProperty->getKey(),
            'name' => 'Growth Decomposition Experiment',
            'slug' => 'growth-decomposition-experiment-' . Str::lower(Str::random(6)),
            'module_type' => 'sales_page_test',
            'status' => 'active',
        ]);

        /** @var Variant $variant */
        $variant = Variant::factory()->create([
            'experiment_id' => $experiment->getKey(),
            'code' => 'A',
            'name' => 'Variant A',
            'position' => 1,
            'traffic_percentage' => 100,
            'is_control' => true,
        ]);

        return [$experiment, $variant];
    });

    $identity = OwnerContext::withOwner($owner, fn (): SignalIdentity => SignalIdentity::query()->create([
        'tracked_property_id' => $trackedProperty->getKey(),
        'external_id' => 'customer-growth-decomposition',
        'anonymous_id' => 'cart-growth-decomposition',
        'email' => 'growth-decomposition-customer@example.com',
    ]));

    $assignment = OwnerContext::withOwner($owner, fn (): Assignment => Assignment::query()->create([
        'experiment_id' => $experiment->getKey(),
        'variant_id' => $variant->getKey(),
        'signal_identity_id' => $identity->getKey(),
        'subject_key' => 'anonymous:cart-growth-decomposition',
        'bucket' => 0,
        'assigned_at' => now(),
        'first_exposed_at' => now(),
        'last_seen_at' => now(),
    ]));

    $source = OwnerContext::withOwner($owner, fn (): CheckoutSession => CheckoutSession::query()->create([
        'cart_id' => 'cart-growth-decomposition',
        'customer_id' => 'customer-growth-decomposition',
        'grand_total' => 29900,
        'currency' => 'MYR',
    ]));

    return compact('owner', 'trackedProperty', 'experiment', 'variant', 'identity', 'assignment', 'source');
}

it('resolves the assignment used by projection on the shared fixture', function (): void {
    $fixture = growthProjectionDecompositionFixture();

    /** @var Collection<int, Assignment> $assignments */
    $assignments = OwnerContext::withOwner(
        $fixture['owner'],
        fn (): Collection => app(ExperimentAssignmentResolver::class)->resolve(
            $fixture['source'],
            $fixture['trackedProperty'],
        ),
    );

    $projected = OwnerContext::withOwner(
        $fixture['owner'],
        fn (): array => app(ProjectExperimentContextIntoSignalProperties::class)->handle(
            $fixture['source'],
            $fixture['trackedProperty'],
        ),
    );

    $expectedContext = OwnerContext::withOwner(
        $fixture['owner'],
        fn (): array => app(BuildExperimentSignalProperties::class)->contextForAssignment($assignments->firstOrFail()),
    );

    expect($assignments->modelKeys())->toBe([$fixture['assignment']->getKey()])
        ->and($projected['experiment_contexts'][0])->toEqual($expectedContext);
});

it('merges the projected context into the same output on the shared fixture', function (): void {
    $fixture = growthProjectionDecompositionFixture();

    $projected = OwnerContext::withOwner(
        $fixture['owner'],
        fn (): array => app(ProjectExperimentContextIntoSignalProperties::class)->handle(
            $fixture['source'],
            $fixture['trackedProperty'],
        ),
    );

    $merged = OwnerContext::withOwner(
        $fixture['owner'],
        fn (): array => app(ExperimentContextMerger::class)->merge(
            $fixture['source'],
            $projected,
        ),
    );

    expect($merged)->toBe($projected);
});
