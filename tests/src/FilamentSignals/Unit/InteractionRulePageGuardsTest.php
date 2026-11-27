<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentSignals\FilamentSignalsTestCase;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentSignals\Features\Configuration\EntryPoints\Filament\Resources\SignalInteractionRuleResource\Pages\CreateSignalInteractionRule;
use AIArmada\FilamentSignals\Features\Configuration\EntryPoints\Filament\Resources\SignalInteractionRuleResource\Pages\EditSignalInteractionRule;
use AIArmada\FilamentSignals\Features\Configuration\EntryPoints\Filament\Resources\SignalInteractionRuleResource\Pages\ListSignalInteractionRules;
use AIArmada\FilamentSignals\Features\Configuration\Persistence\ScanPreviewStore;
use AIArmada\FilamentSignals\Features\Reporting\EntryPoints\Filament\Pages\ConversionFunnelReport;
use AIArmada\FilamentSignals\Features\Reporting\EntryPoints\Filament\Pages\LiveActivityReport;
use AIArmada\FilamentSignals\Features\Reporting\EntryPoints\Filament\Pages\SignalsDashboard;
use AIArmada\Signals\Features\Ingestion\Api\InteractionRuleAdministration;
use AIArmada\Signals\Features\Ingestion\Models\SignalInteractionRule;
use AIArmada\Signals\Features\Ingestion\Models\TrackedProperty;
use Filament\Panel;
use Filament\PanelRegistry;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

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

    $property = TrackedProperty::query()->create([
        'name' => 'Website',
        'slug' => 'website',
        'type' => 'website',
    ]);

    $created = app(InteractionRuleAdministration::class)->createScanned(
        (string) $property->getKey(),
        'click',
        'signup',
        null,
        false,
        [['name' => 'Track signup']],
    );

    expect($created)->toBe(1)
        ->and(SignalInteractionRule::query()->where('slug', 'track-signup-2')->exists())->toBeTrue();
});

it('allocates scanned slugs in one pass and skips overlong selectors', function (): void {
    $owner = User::factory()->create();

    OwnerContext::withOwner($owner, function (): void {
        $property = TrackedProperty::query()->create([
            'name' => 'Scanned Property',
            'slug' => 'scanned-property',
            'write_key' => 'scanned-property-key',
        ]);

        $candidates = [];

        for ($index = 0; $index < 499; $index++) {
            $candidates[] = [
                'name' => 'Repeated Call To Action',
                'selector' => '#repeat-' . $index,
            ];
        }

        $candidates[] = [
            'name' => 'Overlong Selector',
            'selector' => '#' . str_repeat('a', 255),
        ];

        DB::enableQueryLog();

        try {
            $created = app(InteractionRuleAdministration::class)->createScanned(
                (string) $property->getKey(),
                'click',
                'cta.click',
                null,
                false,
                $candidates,
            );
            $queries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }

        $slugs = SignalInteractionRule::query()->orderBy('slug')->pluck('slug');

        expect($created)->toBe(499)
            ->and(count($queries))->toBeLessThan(2100)
            ->and(collect($queries)->filter(fn (array $query): bool => str_contains($query['query'], '"slug" like')))->toHaveCount(1)
            ->and($slugs)->toHaveCount(499)
            ->and($slugs->unique())->toHaveCount(499)
            ->and($slugs->contains('repeated-call-to-action-499'))->toBeTrue()
            ->and(SignalInteractionRule::query()->where('name', 'Overlong Selector')->exists())->toBeFalse();
    });
});

it('isolates scan previews by authenticated operator and owner', function (): void {
    $operatorA = User::factory()->create();
    $operatorB = User::factory()->create();
    $store = app(ScanPreviewStore::class);
    $candidates = [['name' => 'Signup']];
    $meta = ['source_label' => '/signup'];

    expect($store->get()['candidates'])->toBe([]);
    expect(fn () => $store->put($candidates, $meta))->toThrow(AuthenticationException::class);

    $this->actingAs($operatorA);

    OwnerContext::withOwner($operatorA, function () use ($store, $candidates, $meta): void {
        $store->put($candidates, $meta);
        expect($store->get())->toBe(['candidates' => $candidates, 'meta' => $meta]);
    });

    OwnerContext::withOwner($operatorB, fn () => expect($store->get()['candidates'])->toBe([]));

    $this->actingAs($operatorB);
    OwnerContext::withOwner($operatorA, fn () => expect($store->get()['candidates'])->toBe([]));

    $this->actingAs($operatorA);
    OwnerContext::withOwner($operatorA, function () use ($store, $candidates): void {
        expect($store->get()['candidates'])->toBe($candidates);
        $store->forget();
        expect($store->get()['candidates'])->toBe([]);
    });
});
