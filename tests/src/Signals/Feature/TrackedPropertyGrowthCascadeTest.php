<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Signals\Models\TrackedProperty;
use Illuminate\Support\Facades\Schema;

uses(SignalsTestCase::class);

it('deletes a property cleanly when growth tables are absent, even after they existed', function (): void {
    /** @var User $owner */
    $owner = User::query()->create([
        'name' => 'Growth Cascade Owner',
        'email' => 'growth-cascade-owner@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    // Prime: growth tables exist (as in suites that run growth migrations),
    // so a delete observes the growth cascade path.
    $this->loadMigrationsFrom(dirname(__DIR__, 4) . '/packages/growth/database/migrations');
    $this->artisan('migrate');

    $growthTable = (new Experiment)->getTable();

    expect(Schema::hasTable($growthTable))->toBeTrue();

    TrackedProperty::query()->create([
        'name' => 'Priming Property',
        'slug' => 'priming-property',
        'write_key' => 'priming-property-key',
    ])->delete();

    // Schema rebuilt without growth (a fresh signals-only worker state).
    Schema::dropIfExists($growthTable);

    $property = TrackedProperty::query()->create([
        'name' => 'Growthless Property',
        'slug' => 'growthless-property',
        'write_key' => 'growthless-property-key',
    ]);

    $property->delete();

    expect(TrackedProperty::query()->whereKey($property->getKey())->exists())->toBeFalse();
});
