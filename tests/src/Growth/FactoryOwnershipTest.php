<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Growth\Models\Assignment;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

function growthFactoryOwner(): User
{
    return User::query()->create([
        'name' => 'Growth Factory Owner ' . Str::random(6),
        'email' => 'growth-factory-' . Str::lower(Str::random(8)) . '@example.com',
        'password' => 'secret',
    ]);
}

it('growth factories honor the current owner context by default', function (): void {
    $owner = growthFactoryOwner();

    [$experiment, $variant, $assignment] = OwnerContext::withOwner($owner, function (): array {
        /** @var Experiment $experiment */
        $experiment = Experiment::factory()->create();
        /** @var Variant $variant */
        $variant = Variant::factory()->create();
        /** @var Assignment $assignment */
        $assignment = Assignment::factory()->create();

        return [$experiment->fresh('trackedProperty'), $variant->fresh('experiment'), $assignment->fresh(['experiment', 'variant'])];
    });

    expect($experiment->belongsToOwner($owner))->toBeTrue()
        ->and($experiment->trackedProperty->belongsToOwner($owner))->toBeTrue()
        ->and($variant->belongsToOwner($owner))->toBeTrue()
        ->and($variant->experiment->belongsToOwner($owner))->toBeTrue()
        ->and($assignment->belongsToOwner($owner))->toBeTrue()
        ->and($assignment->experiment->belongsToOwner($owner))->toBeTrue()
        ->and($assignment->variant->belongsToOwner($owner))->toBeTrue();
});

it('growth factories support explicit global states', function (): void {
    [$experiment, $variant, $assignment] = OwnerContext::withOwner(null, function (): array {
        /** @var Experiment $experiment */
        $experiment = Experiment::factory()->global()->create();
        /** @var Variant $variant */
        $variant = Variant::factory()->global()->create();
        /** @var Assignment $assignment */
        $assignment = Assignment::factory()->global()->create();

        return [$experiment->fresh('trackedProperty'), $variant->fresh('experiment'), $assignment->fresh(['experiment', 'variant'])];
    });

    expect($experiment->isGlobal())->toBeTrue()
        ->and($experiment->trackedProperty->isGlobal())->toBeTrue()
        ->and($variant->isGlobal())->toBeTrue()
        ->and($variant->experiment->isGlobal())->toBeTrue()
        ->and($assignment->isGlobal())->toBeTrue()
        ->and($assignment->experiment->isGlobal())->toBeTrue()
        ->and($assignment->variant->isGlobal())->toBeTrue();
});

it('growth experiment factory definitions use immutable started_at values', function (): void {
    $startedAt = Experiment::factory()->definition()['started_at'];

    expect($startedAt)->toBeInstanceOf(CarbonImmutable::class);
});

it('growth models do not mass assign owner tuples', function (): void {
    $models = [
        new Experiment,
        new Variant,
        new Assignment,
    ];

    foreach ($models as $model) {
        $model->fill([
            'owner_type' => User::class,
            'owner_id' => 'owner-123',
        ]);

        expect($model->owner_type)->toBeNull()
            ->and($model->owner_id)->toBeNull();
    }
});
