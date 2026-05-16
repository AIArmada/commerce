<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Growth\Models\Assignment;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use AIArmada\Signals\Models\TrackedProperty;
use Illuminate\Support\Str;

function growthCleanupOwner(): User
{
    return User::query()->create([
        'name' => 'Growth Cleanup Owner ' . Str::random(6),
        'email' => 'growth-cleanup-' . Str::lower(Str::random(8)) . '@example.com',
        'password' => 'secret',
    ]);
}

it('deletes growth experiments when their tracked property is deleted', function (): void {
    $owner = growthCleanupOwner();

    [$trackedProperty, $experiment, $variant, $assignment] = OwnerContext::withOwner($owner, function (): array {
        $trackedProperty = TrackedProperty::query()->create([
            'name' => 'Cleanup Property',
            'slug' => 'cleanup-property-' . Str::lower(Str::random(6)),
            'write_key' => Str::random(40),
            'type' => 'website',
            'timezone' => 'UTC',
            'currency' => 'MYR',
            'is_active' => true,
        ]);

        /** @var Experiment $experiment */
        $experiment = Experiment::factory()->create([
            'tracked_property_id' => $trackedProperty->getKey(),
        ]);

        /** @var Variant $variant */
        $variant = Variant::factory()->create([
            'experiment_id' => $experiment->getKey(),
        ]);

        /** @var Assignment $assignment */
        $assignment = Assignment::factory()->create([
            'experiment_id' => $experiment->getKey(),
            'variant_id' => $variant->getKey(),
        ]);

        return [$trackedProperty, $experiment, $variant, $assignment];
    });

    OwnerContext::withOwner($owner, fn (): bool => $trackedProperty->delete());

    expect(Experiment::query()->withoutOwnerScope()->whereKey($experiment->getKey())->exists())->toBeFalse()
        ->and(Variant::query()->withoutOwnerScope()->whereKey($variant->getKey())->exists())->toBeFalse()
        ->and(Assignment::query()->withoutOwnerScope()->whereKey($assignment->getKey())->exists())->toBeFalse();
});
