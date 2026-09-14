<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Growth;

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Growth\Enums\ExperimentStatus;
use AIArmada\Growth\Models\Assignment;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use AIArmada\Growth\Support\AnonymousSubjectKey;
use AIArmada\Growth\Support\ExperimentAssignmentResolver;
use AIArmada\Signals\Models\SignalSession;
use AIArmada\Signals\Models\TrackedProperty;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

function anonymousKeyOwner(): User
{
    return User::query()->create([
        'name' => 'Anonymous Key Owner ' . Str::random(6),
        'email' => 'anonymous-key-' . Str::lower(Str::random(8)) . '@example.com',
        'password' => 'secret',
    ]);
}

describe('Anonymous subject key canonicalization', function (): void {
    beforeEach(function (): void {
        config()->set('growth.features.owner.enabled', true);
        config()->set('signals.owner.enabled', true);
    });

    it('inlines short identifiers and hashes long ones', function (): void {
        $longIdentifier = str_repeat('a', 300);

        expect(AnonymousSubjectKey::make('cart-1'))->toBe('anonymous:cart-1')
            ->and(AnonymousSubjectKey::make($longIdentifier))
            ->toBe('anonymous:sha256:' . hash('sha256', $longIdentifier))
            ->and(AnonymousSubjectKey::make('   '))->toBeNull();
    });

    it('attributes assignments stored under hashed keys back to long identifiers', function (): void {
        $owner = anonymousKeyOwner();
        $longIdentifier = str_repeat('b', 300);
        $subjectKey = AnonymousSubjectKey::make($longIdentifier);

        expect($subjectKey)->toStartWith('anonymous:sha256:');

        $experiment = OwnerContext::withOwner($owner, function (): Experiment {
            $trackedProperty = TrackedProperty::query()->create([
                'name' => 'Anonymous Key Property ' . Str::random(6),
                'slug' => 'anonymous-key-' . Str::lower(Str::random(8)),
                'write_key' => Str::random(40),
                'type' => 'website',
                'timezone' => 'UTC',
                'currency' => 'MYR',
                'is_active' => true,
            ]);

            /** @var Experiment $experiment */
            $experiment = Experiment::factory()->create([
                'tracked_property_id' => $trackedProperty->getKey(),
                'status' => ExperimentStatus::Active,
            ]);

            Variant::factory()->create([
                'experiment_id' => $experiment->getKey(),
                'code' => 'A',
                'name' => 'Control',
                'traffic_percentage' => 100,
                'position' => 1,
                'is_control' => true,
            ]);

            return $experiment->fresh(['variants', 'trackedProperty']) ?? $experiment;
        });

        $assignment = OwnerContext::withOwner($owner, fn (): Assignment => Assignment::query()->create([
            'experiment_id' => $experiment->getKey(),
            'variant_id' => $experiment->variants->first()->getKey(),
            'subject_key' => $subjectKey,
            'bucket' => 0,
            'assigned_at' => CarbonImmutable::now(),
            'first_exposed_at' => CarbonImmutable::now(),
            'last_seen_at' => CarbonImmutable::now(),
        ]));

        $source = new SignalSession;
        $source->forceFill(['cart_id' => $longIdentifier]);

        $resolved = OwnerContext::withOwner(
            $owner,
            fn () => app(ExperimentAssignmentResolver::class)->resolve($source, $experiment->trackedProperty)
        );

        expect($resolved->pluck('id')->all())->toContain($assignment->getKey());
    });
});
