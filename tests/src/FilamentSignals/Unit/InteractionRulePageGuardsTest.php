<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentSignals\FilamentSignalsTestCase;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentSignals\Pages\ConversionFunnelReport;
use AIArmada\FilamentSignals\Pages\LiveActivityReport;
use AIArmada\FilamentSignals\Pages\SignalsDashboard;
use AIArmada\FilamentSignals\Resources\SignalInteractionRuleResource\Pages\CreateSignalInteractionRule;
use AIArmada\FilamentSignals\Resources\SignalInteractionRuleResource\Pages\EditSignalInteractionRule;
use AIArmada\FilamentSignals\Resources\SignalInteractionRuleResource\Pages\ListSignalInteractionRules;
use AIArmada\Signals\Models\SignalInteractionRule;
use Filament\Panel;
use Filament\PanelRegistry;
use Illuminate\Database\QueryException;

uses(FilamentSignalsTestCase::class);

it('keeps report pages and the dashboard behind a view permission', function (): void {
    app(PanelRegistry::class)->register(Panel::make()->default()->id('admin')->path('admin')->login());

    config()->set('filament-signals.features.live_activity', true);
    config()->set('filament-signals.features.conversion_funnel', true);
    config()->set('filament-signals.features.dashboard', true);

    expect(LiveActivityReport::canAccess())->toBeFalse()
        ->and(ConversionFunnelReport::canAccess())->toBeFalse()
        ->and(SignalsDashboard::canAccess())->toBeFalse()
        ->and(LiveActivityReport::shouldRegisterNavigation())->toBeFalse()
        ->and(SignalsDashboard::shouldRegisterNavigation())->toBeFalse();
});

it('revalidates the tracked property on rule create and edit pages', function (): void {
    expect((new ReflectionMethod(CreateSignalInteractionRule::class, 'mutateFormDataBeforeCreate'))->getDeclaringClass()->getName())
        ->toBe(CreateSignalInteractionRule::class)
        ->and((new ReflectionMethod(EditSignalInteractionRule::class, 'mutateFormDataBeforeSave'))->getDeclaringClass()->getName())
        ->toBe(EditSignalInteractionRule::class);
});

it('authorizes bulk scan actions with the create policy', function (): void {
    app(PanelRegistry::class)->register(Panel::make()->default()->id('admin')->path('admin')->login());

    $method = new ReflectionMethod(ListSignalInteractionRules::class, 'getHeaderActions');
    $actions = $method->invoke(app(ListSignalInteractionRules::class));

    $byName = collect($actions)->mapWithKeys(
        static fn ($action): array => [$action->getName() => $action]
    );

    foreach (['createFromPreview', 'scanPage', 'rescanRoute'] as $name) {
        expect($byName->has($name))->toBeTrue("missing header action {$name}")
            ->and($byName[$name]->isAuthorized())->toBeFalse("{$name} is unexpectedly authorized for guests");
    }
});

it('allows the same slug in different owner scopes', function (): void {
    $attributes = [
        'name' => 'Shared Slug',
        'slug' => 'shared-slug',
        'trigger_type' => 'click',
        'event_name' => 'shared',
        'sort_order' => 1,
        'is_active' => false,
    ];

    $global = OwnerContext::withOwner(null, fn (): SignalInteractionRule => SignalInteractionRule::query()->create($attributes));
    $owned = OwnerContext::withOwner(
        User::factory()->create(),
        fn (): SignalInteractionRule => SignalInteractionRule::query()->create($attributes)
    );

    expect($global->getAttribute('owner_scope'))
        ->not->toBe($owned->getAttribute('owner_scope'));

    expect(fn (): SignalInteractionRule => OwnerContext::withOwner(
        null,
        fn (): SignalInteractionRule => SignalInteractionRule::query()->create($attributes)
    ))->toThrow(QueryException::class);
});

it('derives a fresh slug when the base slug is taken', function (): void {
    SignalInteractionRule::query()->create([
        'name' => 'Existing',
        'slug' => 'track-signup',
        'trigger_type' => 'click',
        'event_name' => 'signup',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $method = new ReflectionMethod(ListSignalInteractionRules::class, 'createRuleWithUniqueSlug');
    $rule = $method->invoke(app(ListSignalInteractionRules::class), [
        'name' => 'Track signup',
        'trigger_type' => 'click',
        'event_name' => 'signup',
        'sort_order' => 2,
        'is_active' => false,
    ], 'track-signup');

    expect($rule)->toBeInstanceOf(SignalInteractionRule::class)
        ->and($rule->slug)->toBe('track-signup-2');
});
