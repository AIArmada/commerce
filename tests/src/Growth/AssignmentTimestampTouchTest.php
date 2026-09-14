<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Growth;

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Growth\Enums\ExperimentStatus;
use AIArmada\Growth\Models\Assignment;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use AIArmada\Signals\Models\SignalIdentity;
use AIArmada\Signals\Models\TrackedProperty;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

function timestampTouchOwner(): User
{
    return User::query()->create([
        'name' => 'Timestamp Touch Owner ' . Str::random(6),
        'email' => 'timestamp-touch-' . Str::lower(Str::random(8)) . '@example.com',
        'password' => 'secret',
    ]);
}

function timestampTouchAssignment(User $owner): Assignment
{
    return OwnerContext::withOwner($owner, function (): Assignment {
        $trackedProperty = TrackedProperty::query()->create([
            'name' => 'Timestamp Touch Property ' . Str::random(6),
            'slug' => 'timestamp-touch-' . Str::lower(Str::random(8)),
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

        $variant = Variant::factory()->create([
            'experiment_id' => $experiment->getKey(),
            'code' => 'A',
            'name' => 'Control',
            'traffic_percentage' => 100,
            'position' => 1,
            'is_control' => true,
        ]);

        return Assignment::query()->create([
            'experiment_id' => $experiment->getKey(),
            'variant_id' => $variant->getKey(),
            'subject_key' => 'identity:touch-1',
            'bucket' => 0,
            'assigned_at' => CarbonImmutable::now(),
            'first_exposed_at' => CarbonImmutable::now(),
            'last_seen_at' => CarbonImmutable::now(),
        ]);
    });
}

function countSelectsDuring(callable $callback): int
{
    $selects = 0;

    DB::listen(function ($query) use (&$selects): void {
        if (str_starts_with(mb_ltrim($query->sql), 'select')) {
            $selects++;
        }
    });

    $callback();

    return $selects;
}

describe('Assignment timestamp touches', function (): void {
    beforeEach(function (): void {
        config()->set('growth.features.owner.enabled', true);
        config()->set('signals.owner.enabled', true);
    });

    it('saves timestamp-only touches without revalidation queries', function (): void {
        $owner = timestampTouchOwner();
        $assignment = timestampTouchAssignment($owner);

        $selects = OwnerContext::withOwner($owner, fn (): int => countSelectsDuring(function () use ($assignment): void {
            $assignment->last_seen_at = CarbonImmutable::now()->addMinute();
            $assignment->save();
        }));

        expect($selects)->toBe(0);
    });

    it('still revalidates when identity linkage changes', function (): void {
        $owner = timestampTouchOwner();
        $assignment = timestampTouchAssignment($owner);

        $identity = OwnerContext::withOwner($owner, function () use ($assignment): SignalIdentity {
            $experiment = $assignment->experiment;

            return SignalIdentity::query()->create([
                'tracked_property_id' => $experiment->tracked_property_id,
                'external_id' => 'customer-touch-' . Str::lower(Str::random(8)),
            ]);
        });

        $selects = OwnerContext::withOwner($owner, fn (): int => countSelectsDuring(function () use ($assignment, $identity): void {
            $assignment->signal_identity_id = $identity->getKey();
            $assignment->save();
        }));

        expect($selects)->toBeGreaterThan(0);
    });

    it('still rejects experiment and variant reassignment', function (): void {
        $owner = timestampTouchOwner();
        $assignment = timestampTouchAssignment($owner);

        expect(fn (): mixed => OwnerContext::withOwner($owner, function () use ($assignment): mixed {
            $assignment->experiment_id = (string) Str::uuid();

            return $assignment->save();
        }))->toThrow(InvalidArgumentException::class);
    });
});
