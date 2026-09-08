<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Growth\Enums\ExperimentStatus;
use AIArmada\Growth\Models\Experiment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

function growthTransitionOwner(): User
{
    return User::query()->create([
        'name' => 'Growth Transition Owner ' . Str::random(6),
        'email' => 'growth-transition-' . Str::lower(Str::random(8)) . '@example.com',
        'password' => 'secret',
    ]);
}

it('centralizes experiment status transitions and lifecycle timestamps', function (): void {
    $owner = growthTransitionOwner();

    $experiment = OwnerContext::withOwner($owner, fn (): Experiment => Experiment::factory()->create([
        'status' => ExperimentStatus::Draft,
        'started_at' => null,
        'ended_at' => null,
        'paused_at' => null,
        'concluded_at' => null,
        'archived_at' => null,
    ]));

    OwnerContext::withOwner($owner, function () use ($experiment): void {
        $experiment->transitionTo(ExperimentStatus::Active)->save();

        expect($experiment->refresh()->status)->toBe(ExperimentStatus::Active)
            ->and($experiment->started_at)->toBeInstanceOf(CarbonImmutable::class)
            ->and($experiment->paused_at)->toBeNull();

        $startedAt = $experiment->started_at;

        $experiment->transitionTo(ExperimentStatus::Paused)->save();

        expect($experiment->refresh()->status)->toBe(ExperimentStatus::Paused)
            ->and($experiment->paused_at)->toBeInstanceOf(CarbonImmutable::class);

        $experiment->transitionTo(ExperimentStatus::Active)->save();

        expect($experiment->refresh()->status)->toBe(ExperimentStatus::Active)
            ->and($experiment->started_at?->equalTo($startedAt))->toBeTrue()
            ->and($experiment->paused_at)->toBeNull();

        $experiment->transitionTo(ExperimentStatus::Concluded)->save();

        expect($experiment->refresh()->status)->toBe(ExperimentStatus::Concluded)
            ->and($experiment->concluded_at)->toBeInstanceOf(CarbonImmutable::class)
            ->and($experiment->ended_at)->toBeInstanceOf(CarbonImmutable::class)
            ->and($experiment->paused_at)->toBeNull();

        $endedAt = $experiment->ended_at;
        $experiment->transitionTo(ExperimentStatus::Archived)->save();
        $archivedAt = $experiment->refresh()->archived_at;

        $experiment->transitionTo(ExperimentStatus::Archived)->save();

        expect($experiment->refresh()->status)->toBe(ExperimentStatus::Archived)
            ->and($archivedAt)->toBeInstanceOf(CarbonImmutable::class)
            ->and($experiment->ended_at?->equalTo($endedAt))->toBeTrue()
            ->and($experiment->archived_at?->equalTo($archivedAt))->toBeTrue();
    });
});
