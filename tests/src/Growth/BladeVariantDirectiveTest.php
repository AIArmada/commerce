<?php

declare(strict_types=1);

require_once __DIR__ . '/PresentationTestSupport.php';

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Growth\Actions\ResolveExperimentAssignment;
use AIArmada\Growth\Support\ExperimentContextManager;
use Illuminate\Support\Facades\Blade;

it('renders the matching blade variant branch when directives are enabled', function (): void {
    config()->set('growth.features.blade_directives.enabled', true);

    $owner = growthPresentationCreateOwner();
    $experiment = growthPresentationCreateExperiment($owner);
    $assignment = OwnerContext::withOwner($owner, fn () => app(ResolveExperimentAssignment::class)->handle($experiment, anonymousId: 'blade-variant-subject'));

    app(ExperimentContextManager::class)->store(request(), $experiment, $assignment);

    $template = <<<'BLADE'
<section data-growth='enabled'>
@variant(strtolower('%s'))
A
@elsevariant('missing')
B
@else
C
@endvariant
</section>
BLADE;

    $output = Blade::render(sprintf($template, mb_strtoupper((string) $assignment->variant->code)), [], true);

    expect(trim($output))->toBe("<section data-growth='enabled'>\nA\n</section>");
});

it('falls back to the else branch when blade directives are disabled', function (): void {
    config()->set('growth.features.blade_directives.enabled', false);

    $owner = growthPresentationCreateOwner();
    $experiment = growthPresentationCreateExperiment($owner);
    $assignment = OwnerContext::withOwner($owner, fn () => app(ResolveExperimentAssignment::class)->handle($experiment, anonymousId: 'blade-disabled-subject'));

    app(ExperimentContextManager::class)->store(request(), $experiment, $assignment);

    $template = <<<'BLADE'
<section data-growth='disabled'>
@variant('%s')
A
@elsevariant('missing')
B
@else
C
@endvariant
</section>
BLADE;

    $output = Blade::render(sprintf($template, (string) $assignment->variant->code), [], true);

    expect(trim($output))->toBe("<section data-growth='disabled'>\nC\n</section>");
});

it('supports nested blade control flow inside variant blocks', function (): void {
    config()->set('growth.features.blade_directives.enabled', true);

    $owner = growthPresentationCreateOwner();
    $experiment = growthPresentationCreateExperiment($owner);
    $assignment = OwnerContext::withOwner($owner, fn () => app(ResolveExperimentAssignment::class)->handle($experiment, anonymousId: 'blade-nested-control-flow'));

    app(ExperimentContextManager::class)->store(request(), $experiment, $assignment);

    $template = <<<'BLADE'
<section data-growth='nested'>
@variant('%s')
@if (true)
NESTED_OK
@else
NESTED_FAIL
@endif
@else
OUTER_FAIL
@endvariant
</section>
BLADE;

    $output = Blade::render(sprintf($template, (string) $assignment->variant->code), [], true);

    expect(trim($output))->toContain('NESTED_OK')
        ->and($output)->not->toContain('NESTED_FAIL')
        ->and($output)->not->toContain('OUTER_FAIL');
});