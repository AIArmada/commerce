<?php

declare(strict_types=1);

require_once __DIR__ . '/PresentationTestSupport.php';

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Growth\Actions\ResolveExperimentAssignment;
use AIArmada\Growth\Facades\Growth;
use AIArmada\Growth\Livewire\Concerns\InteractsWithExperimentContext;
use AIArmada\Growth\Support\ExperimentContextManager;

it('returns null when no experiment context is available on the request', function (): void {
    expect(experiment())->toBeNull()
        ->and(Growth::variantCode())->toBeNull()
        ->and(Growth::experimentSlug())->toBeNull();
});

it('exposes experiment context through the helper facade and livewire concern', function (): void {
    $owner = growthPresentationCreateOwner();
    $experiment = growthPresentationCreateExperiment($owner);
    $assignment = OwnerContext::withOwner($owner, fn () => app(ResolveExperimentAssignment::class)->handle($experiment, anonymousId: 'presentation-helper-subject'));
    $manager = app(ExperimentContextManager::class);

    $manager->store(request(), $experiment, $assignment);

    $context = experiment();
    $consumer = new class
    {
        use InteractsWithExperimentContext;
    };

    expect($context)->not->toBeNull()
        ->and($context?->slug)->toBe($experiment->slug)
        ->and($context?->assignmentId())->toBe((string) $assignment->getKey())
        ->and(Growth::variantCode())->toBe($context?->variantCode())
        ->and(Growth::assignmentId())->toBe((string) $assignment->getKey())
        ->and($consumer->experimentContext()?->experiment->is($experiment))->toBeTrue()
        ->and($consumer->isExperimentVariant($context?->variantCode() ?? ''))->toBeTrue()
        ->and($consumer->experimentSlug())->toBe($experiment->slug)
        ->and($consumer->isExperimentControl())->toBe($context?->isControl() ?? false);
});
