<?php

declare(strict_types=1);

use AIArmada\FilamentCommerceSupport\FilamentCommerceSupportPlugin;
use AIArmada\FilamentCommerceSupport\Pages\ManageCommerceNavigation;
use AIArmada\FilamentCommerceSupport\Pages\ManageExchangeRates;
use AIArmada\FilamentCommerceSupport\Resources\CurrencyResource;
use AIArmada\FilamentCommerceSupport\Resources\LanguageResource;
use AIArmada\FilamentCommerceSupport\Resources\TimezoneResource;
use AIArmada\FilamentCommerceSupport\Settings\CommerceNavigationSettings;
use AIArmada\FilamentCommerceSupport\Support\NavigationConfigurator;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelSettings\SettingsRepositories\DatabaseSettingsRepository;

beforeEach(function (): void {
    NavigationConfigurator::reset();
    config()->set('filament-commerce-support.navigation.enabled', true);
    config()->set('commerce-support.filament.navigation', [
        'enabled' => true,
        'groups' => [],
        'packages' => [],
        'items' => [],
    ]);
});

function navmgrSection(string $key, array $items = [], array $overrides = []): array
{
    return array_merge([
        'group_key' => $key,
        'label' => $key === '' ? 'Ungrouped' : $key,
        'icon' => '',
        'sort' => 0,
        'collapsible' => true,
        'collapsed' => false,
        'hidden' => false,
        'items' => $items,
    ], $overrides);
}

function navmgrItem(string $component, array $overrides = []): array
{
    return array_merge([
        'component' => $component,
        'hidden' => false,
        'label' => '',
        'sort' => 1,
        'parent_item' => '',
    ], $overrides);
}

function navmgrMockSettings(array $groups, array $overrides, bool $expectSave = true): CommerceNavigationSettings
{
    $settings = mock(CommerceNavigationSettings::class, function ($mock) use ($groups, $overrides): void {
        $mock->groups = $groups;
        $mock->overrides = $overrides;
    });

    if ($expectSave) {
        $settings->shouldReceive('save')->once()->andReturnSelf();
    } else {
        $settings->shouldReceive('save')->never();
    }

    app()->instance(CommerceNavigationSettings::class, $settings);

    return $settings;
}

function navmgrPage(array $sidebar): ManageCommerceNavigation
{
    $page = new ManageCommerceNavigation('navmgr-test');
    $page->data = ['sidebar' => $sidebar];

    return $page;
}

function navmgrConfigureSettingsRepository(): void
{
    // Mirror production wiring: the testbench app does not load the
    // spatie/laravel-settings config, so resolveSettings() cannot reach the
    // database repository without these keys.
    config()->set('settings.default_repository', 'database');
    config()->set('settings.repositories.database', [
        'type' => DatabaseSettingsRepository::class,
        'model' => null,
        'table' => 'settings',
        'connection' => 'testing',
    ]);
}

// Shallow per-entry merge permanently dropped file-level keys.
it('deep-merges item overrides per entry instead of shadowing file keys', function (): void {
    config()->set('commerce-support.filament.navigation.items', [
        'Navmgr\\FakeResource' => ['group' => 'Catalog', 'sort' => 5, 'label' => 'Foo'],
    ]);

    navmgrMockSettings([], ['Navmgr\\FakeResource' => ['sort' => 9]], false);

    NavigationConfigurator::apply();

    expect(config('commerce-support.filament.navigation.items.Navmgr\\FakeResource'))->toBe([
        'group' => 'Catalog',
        'sort' => 9,
        'label' => 'Foo',
    ]);
});

// Same defect on the groups side.
it('deep-merges group overrides per entry instead of shadowing file keys', function (): void {
    config()->set('commerce-support.filament.navigation.groups', [
        'Catalog' => ['label' => 'Catalog', 'icon' => 'heroicon-o-tag', 'sort' => 1],
    ]);

    navmgrMockSettings(['Catalog' => ['sort' => 2]], [], false);

    NavigationConfigurator::apply();

    expect(config('commerce-support.filament.navigation.groups.Catalog'))->toBe([
        'label' => 'Catalog',
        'icon' => 'heroicon-o-tag',
        'sort' => 2,
    ]);
});

// Corrupted non-array settings entries must not fatal apply().
it('skips non-array settings entries during apply', function (): void {
    config()->set('commerce-support.filament.navigation.items', [
        'Navmgr\\FakeResource' => ['group' => 'Catalog', 'sort' => 5],
    ]);
    config()->set('commerce-support.filament.navigation.groups', [
        'Catalog' => ['label' => 'Catalog'],
    ]);

    navmgrMockSettings(
        ['Broken' => 42, 'Catalog' => ['sort' => 3]],
        ['Navmgr\\FakeResource' => 'junk'],
        false,
    );

    NavigationConfigurator::apply();

    $items = config('commerce-support.filament.navigation.items');
    $groups = config('commerce-support.filament.navigation.groups');

    expect($items['Navmgr\\FakeResource'])->toBe(['group' => 'Catalog', 'sort' => 5])
        ->and($groups)->not->toHaveKey('Broken')
        ->and($groups['Catalog'])->toBe(['label' => 'Catalog', 'sort' => 3]);
});

// Captured statics never refresh in-process without reset().
it('recaptures file config after reset', function (): void {
    config()->set('commerce-support.filament.navigation.groups', ['A' => ['label' => 'A']]);
    navmgrMockSettings([], [], false);

    NavigationConfigurator::apply();

    config()->set('commerce-support.filament.navigation.groups', ['B' => ['label' => 'B']]);
    NavigationConfigurator::apply();

    expect(NavigationConfigurator::getOriginalGroupConfig())->toHaveKey('A');

    NavigationConfigurator::reset();
    NavigationConfigurator::apply();

    expect(NavigationConfigurator::getOriginalGroupConfig())->toBe(['B' => ['label' => 'B']]);
});

// Saved items carry the group label after a rename; bucketing must
// match by key OR label or items detach into Ungrouped on the next mount.
it('buckets renamed-label items into their group instead of ungrouped', function (): void {
    $page = new ManageCommerceNavigation('navmgr-test');
    $method = new ReflectionMethod(ManageCommerceNavigation::class, 'buildSidebarForForm');

    $sections = $method->invoke(
        $page,
        ['Catalog' => ['label' => 'Shop', 'icon' => '', 'sort' => 0, 'collapsible' => true, 'collapsed' => false, 'hidden' => false]],
        ['Navmgr\\FakeResource' => ['group' => 'Shop', 'sort' => 1, 'label' => '', 'hidden' => false, 'parent_item' => '']],
    );

    expect($sections)->toHaveCount(1)
        ->and($sections[0]['group_key'])->toBe('Catalog')
        ->and($sections[0]['items'])->toHaveCount(1)
        ->and($sections[0]['items'][0]['component'])->toBe('Navmgr\\FakeResource');
});

// End-to-end: a renamed save round-trips without detaching items.
it('keeps renamed items attached across a save and rebuild', function (): void {
    $settings = navmgrMockSettings(
        [],
        ['Navmgr\\FakeResource' => ['group' => 'Shop', 'sort' => 1]],
    );

    navmgrPage([
        navmgrSection('Catalog', [navmgrItem('Navmgr\\FakeResource')], ['label' => 'Shop']),
    ])->save();

    expect($settings->overrides['Navmgr\\FakeResource']['group'])->toBe('Shop');

    $page = new ManageCommerceNavigation('navmgr-test');
    $method = new ReflectionMethod(ManageCommerceNavigation::class, 'buildSidebarForForm');
    $sections = $method->invoke($page, $settings->groups, $settings->overrides);

    $catalog = collect($sections)->firstWhere('group_key', 'Catalog');

    expect($catalog)->not->toBeNull()
        ->and(collect($catalog['items'])->firstWhere('component', 'Navmgr\\FakeResource'))->not->toBeNull();
});

// Typed sort values must be honored instead of silently discarded.
it('honors explicitly typed sort values on save', function (): void {
    $settings = navmgrMockSettings(
        ['Catalog' => ['label' => 'Catalog', 'sort' => 7]],
        ['Navmgr\\FakeResource' => ['group' => 'Catalog', 'sort' => 5]],
    );

    navmgrPage([
        navmgrSection('Catalog', [navmgrItem('Navmgr\\FakeResource', ['sort' => 42])], ['label' => 'Catalog', 'sort' => 3]),
    ])->save();

    expect($settings->overrides['Navmgr\\FakeResource']['sort'])->toBe(42)
        ->and($settings->groups['Catalog']['sort'])->toBe(3);
});

// Untouched sort inputs follow drag position so reordering works.
it('falls back to positional sorts for untouched inputs', function (): void {
    $settings = navmgrMockSettings(
        [],
        ['Navmgr\\FakeResource' => ['group' => 'Catalog', 'sort' => 5]],
    );

    navmgrPage([
        navmgrSection('Catalog', [navmgrItem('Navmgr\\FakeResource', ['sort' => 5])]),
    ])->save();

    // Submitted sort equals the mounted sort, so drag position (index 0 + 1) wins.
    expect($settings->overrides['Navmgr\\FakeResource']['sort'])->toBe(1);
});

it('resolves sorts with clamping and positional defaults', function (): void {
    $page = new ManageCommerceNavigation('navmgr-test');
    $method = new ReflectionMethod(ManageCommerceNavigation::class, 'resolveSort');

    expect($method->invoke($page, 5, 1, 0))->toBe(5)
        ->and($method->invoke($page, 1, 1, 0))->toBe(0)
        ->and($method->invoke($page, null, 1, 2))->toBe(2)
        ->and($method->invoke($page, 'abc', 1, 2))->toBe(2)
        ->and($method->invoke($page, 99999, null, 0))->toBe(9999)
        ->and($method->invoke($page, -3, null, 0))->toBe(0);
});

// The ungrouped section uses an empty key; the legacy sentinel is
// rejected as a real group key so it can never swallow a group.
it('uses an empty key for the ungrouped section', function (): void {
    $page = new ManageCommerceNavigation('navmgr-test');
    $method = new ReflectionMethod(ManageCommerceNavigation::class, 'buildSidebarForForm');

    $sections = $method->invoke($page, [], ['Navmgr\\FakeResource' => ['group' => '']]);

    expect($sections)->toHaveCount(1)
        ->and($sections[0]['group_key'])->toBe('')
        ->and($sections[0]['sort'])->toBe(0);
});

// save() must run form validation: unknown components are rejected
// and nothing is persisted.
it('rejects unlisted components instead of persisting them', function (): void {
    navmgrMockSettings([], [], false);

    $page = navmgrPage([
        navmgrSection('Catalog', [navmgrItem('Navmgr\\EvilClass')]),
    ]);

    expect(fn () => $page->save())->toThrow(ValidationException::class);
});

// The reserved ungrouped sentinel is rejected as a group key.
it('rejects the reserved ungrouped key as a group key', function (): void {
    navmgrMockSettings([], [], false);

    $page = navmgrPage([
        navmgrSection('__ungrouped__', [], ['label' => 'Sneaky']),
    ]);

    expect(fn () => $page->save())->toThrow(ValidationException::class);
});

// Icon values must be heroicon names (or blank); bad values render
// broken navigation for every admin.
it('rejects non-heroicon icon values', function (): void {
    navmgrMockSettings([], [], false);

    $page = navmgrPage([
        navmgrSection('Catalog', [], ['icon' => 'not-an-icon!']),
    ]);

    expect(fn () => $page->save())->toThrow(ValidationException::class);
});

it('accepts valid heroicon icon values', function (): void {
    $settings = navmgrMockSettings([], []);

    navmgrPage([
        navmgrSection('Catalog', [], ['icon' => 'heroicon-o-shopping-bag']),
    ])->save();

    expect($settings->groups['Catalog']['icon'])->toBe('heroicon-o-shopping-bag');
});

// Previously stored (now unregistered) components must keep
// round-tripping instead of failing validation and blocking every save.
it('lets previously stored components round-trip through validation', function (): void {
    $settings = navmgrMockSettings(
        [],
        ['Navmgr\\StaleResource' => ['group' => 'Catalog', 'sort' => 1]],
    );

    navmgrPage([
        navmgrSection('Catalog', [navmgrItem('Navmgr\\StaleResource')]),
    ])->save();

    expect($settings->overrides)->toHaveKey('Navmgr\\StaleResource');
});

// A duplicate component in two groups keeps the first occurrence
// instead of silently last-winning.
it('keeps the first occurrence of duplicate components', function (): void {
    $settings = navmgrMockSettings(
        [],
        ['Navmgr\\FakeResource' => ['group' => 'Catalog', 'sort' => 1]],
    );

    navmgrPage([
        navmgrSection('Catalog', [navmgrItem('Navmgr\\FakeResource')]),
        navmgrSection('Shop', [navmgrItem('Navmgr\\FakeResource')]),
    ])->save();

    expect($settings->overrides['Navmgr\\FakeResource']['group'])->toBe('Catalog');
});

// Repeaters are bounded so settings payloads cannot bloat.
it('rejects oversized sidebars beyond the repeater limits', function (): void {
    navmgrMockSettings([], [], false);

    $sections = [];
    for ($i = 0; $i < 101; $i++) {
        $sections[] = navmgrSection("Group{$i}");
    }

    expect(fn () => navmgrPage($sections)->save())->toThrow(ValidationException::class);
});

it('rejects oversized item lists beyond the repeater limits', function (): void {
    $stored = [];
    for ($i = 0; $i < 201; $i++) {
        $stored["Navmgr\\Item{$i}"] = ['group' => 'Catalog', 'sort' => $i];
    }
    navmgrMockSettings([], $stored, false);

    $items = [];
    for ($i = 0; $i < 201; $i++) {
        $items[] = navmgrItem("Navmgr\\Item{$i}");
    }

    expect(fn () => navmgrPage([navmgrSection('Catalog', $items)])->save())->toThrow(ValidationException::class);
});

// Reference-data resources must be registered; Filament discovery
// covers app paths only, so the package UI was unreachable out of the box.
it('registers reference-data resources when navigation is enabled', function (): void {
    navmgrMockSettings([], [], false);

    $panel = Mockery::mock(Panel::class);
    $panel->shouldReceive('navigation')->once()->with(Mockery::type(Closure::class))->andReturnSelf();
    $panel->shouldReceive('pages')->once()->with([ManageCommerceNavigation::class, ManageExchangeRates::class])->andReturnSelf();
    $panel->shouldReceive('resources')->once()->with([
        CurrencyResource::class,
        LanguageResource::class,
        TimezoneResource::class,
    ])->andReturnSelf();

    (new FilamentCommerceSupportPlugin(app()))->register($panel);

    expect($panel)->toBeInstanceOf(Panel::class);
});

it('registers no pages or resources when navigation is disabled', function (): void {
    config()->set('filament-commerce-support.navigation.enabled', false);
    config()->set('filament-commerce-support.exchange_rates.enabled', false);
    navmgrMockSettings([], [], false);

    $panel = Mockery::mock(Panel::class);
    $panel->shouldReceive('navigation')->once()->with(Mockery::type(Closure::class))->andReturnSelf();
    $panel->shouldNotReceive('pages', 'resources');

    (new FilamentCommerceSupportPlugin(app()))->register($panel);

    expect($panel)->toBeInstanceOf(Panel::class);
});

it('registers only the navigation page when exchange rates are disabled', function (): void {
    config()->set('filament-commerce-support.exchange_rates.enabled', false);
    navmgrMockSettings([], [], false);

    $panel = Mockery::mock(Panel::class);
    $panel->shouldReceive('navigation')->once()->with(Mockery::type(Closure::class))->andReturnSelf();
    $panel->shouldReceive('pages')->once()->with([ManageCommerceNavigation::class])->andReturnSelf();
    $panel->shouldReceive('resources')->once()->andReturnSelf();

    (new FilamentCommerceSupportPlugin(app()))->register($panel);

    expect($panel)->toBeInstanceOf(Panel::class);
});

it('registers only the rates page when navigation is disabled', function (): void {
    config()->set('filament-commerce-support.navigation.enabled', false);
    navmgrMockSettings([], [], false);

    $panel = Mockery::mock(Panel::class);
    $panel->shouldReceive('navigation')->once()->with(Mockery::type(Closure::class))->andReturnSelf();
    $panel->shouldReceive('pages')->once()->with([ManageExchangeRates::class])->andReturnSelf();
    $panel->shouldNotReceive('resources');

    (new FilamentCommerceSupportPlugin(app()))->register($panel);

    expect($panel)->toBeInstanceOf(Panel::class);
});

it('honors per-resource enabled flags', function (): void {
    config()->set('filament-commerce-support.resources.languages.enabled', false);
    navmgrMockSettings([], [], false);

    $panel = Mockery::mock(Panel::class);
    $panel->shouldReceive('navigation')->once()->with(Mockery::type(Closure::class))->andReturnSelf();
    $panel->shouldReceive('pages')->once()->andReturnSelf();
    $panel->shouldReceive('resources')->once()->with([
        CurrencyResource::class,
        TimezoneResource::class,
    ])->andReturnSelf();

    (new FilamentCommerceSupportPlugin(app()))->register($panel);

    expect($panel)->toBeInstanceOf(Panel::class);
});

// Group-field visibility must read the sibling group_key, not each
// field's own state.
it('reads sibling group_key for group-field visibility', function (): void {
    $source = file_get_contents(__DIR__ . '/../../../packages/filament-commerce-support/src/Pages/ManageCommerceNavigation.php');

    expect(mb_substr_count($source, 'isUngroupedSection($get)'))->toBeGreaterThanOrEqual(6)
        ->and($source)->not->toContain('fn (?string $state)');
});

// Missing settings storage degrades to in-memory defaults instead of
// 500ing, and reads never write.
it('falls back to empty settings when rows are missing', function (): void {
    navmgrConfigureSettingsRepository();
    app()->forgetInstance(CommerceNavigationSettings::class);

    $page = new ManageCommerceNavigation('navmgr-test');
    $method = new ReflectionMethod(ManageCommerceNavigation::class, 'resolveSettings');
    $settings = $method->invoke($page);

    expect($settings)->toBeInstanceOf(CommerceNavigationSettings::class)
        ->and($settings->groups)->toBe([])
        ->and($settings->overrides)->toBe([]);
});

it('falls back to empty settings when the table is missing', function (): void {
    navmgrConfigureSettingsRepository();
    Schema::dropIfExists('settings');
    app()->forgetInstance(CommerceNavigationSettings::class);

    $page = new ManageCommerceNavigation('navmgr-test');
    $method = new ReflectionMethod(ManageCommerceNavigation::class, 'resolveSettings');
    $settings = $method->invoke($page);

    expect($settings)->toBeInstanceOf(CommerceNavigationSettings::class)
        ->and($settings->groups)->toBe([])
        ->and($settings->overrides)->toBe([]);
});

it('mounts with defaults when settings storage is unavailable', function (): void {
    navmgrConfigureSettingsRepository();
    Schema::dropIfExists('settings');
    app()->forgetInstance(CommerceNavigationSettings::class);

    $page = new ManageCommerceNavigation('navmgr-test');
    $page->mount();

    expect($page->data)->toHaveKey('sidebar')
        ->and($page->data['sidebar'])->toBeArray();
});

it('reports storage failures on save instead of throwing', function (): void {
    config()->set('commerce-support.filament.navigation.items', [
        'Navmgr\\Kept' => ['group' => 'Catalog'],
    ]);

    $settings = mock(CommerceNavigationSettings::class, function ($mock): void {
        $mock->groups = [];
        $mock->overrides = [];
        $mock->shouldReceive('save')->once()->andThrow(
            new QueryException('testing', 'insert into settings', [], new Exception('db gone'))
        );
    });
    app()->instance(CommerceNavigationSettings::class, $settings);

    navmgrPage([])->save();

    // apply() was skipped: the failed save changed nothing at runtime.
    expect(config('commerce-support.filament.navigation.items'))->toBe([
        'Navmgr\\Kept' => ['group' => 'Catalog'],
    ]);
});

// Static methods must never be invoked on unregistered persisted
// class names.
it('never calls navigation methods on unregistered classes', function (): void {
    $page = new ManageCommerceNavigation('navmgr-test');
    $method = new ReflectionMethod(ManageCommerceNavigation::class, 'normalizeOverrideForSidebar');

    $row = $method->invoke($page, NavmgrLabelBombFixture::class, [], 0);

    expect($row['label'])->toBe('NavmgrLabelBombFixture')
        ->and($row['component'])->toBe(NavmgrLabelBombFixture::class);
});

// Timezone sort falls back to 100 like its siblings.
it('defaults the timezone navigation sort to 100', function (): void {
    config()->set('filament-commerce-support.navigation', ['enabled' => true]);

    expect(TimezoneResource::getNavigationSort())->toBe(100);
});

// Corrupted sidebar entries must not fatal the manager page.
it('tolerates non-array sidebar entries when building the form', function (): void {
    $page = new ManageCommerceNavigation('navmgr-test');
    $method = new ReflectionMethod(ManageCommerceNavigation::class, 'buildSidebarForForm');

    $sections = $method->invoke($page, ['Catalog' => 'junk'], ['Navmgr\\FakeResource' => 'junk', 7 => ['group' => 'x']]);

    expect($sections)->toBeArray();
});

final class NavmgrLabelBombFixture
{
    public static function getNavigationLabel(): string
    {
        throw new RuntimeException('must not be called');
    }
}

// [NEW] Components may declare enum navigation groups
// (`string | UnitEnum | null`); the options list must not fatal on them.
it('tolerates enum navigation groups in component options', function (): void {
    navmgrMockSettings([], [], false);

    Filament::getCurrentOrDefaultPanel()->pages([NavmgrEnumGroupPageFixture::class]);

    $page = new ManageCommerceNavigation('navmgr-test');
    $method = new ReflectionMethod(ManageCommerceNavigation::class, 'getComponentOptions');
    $options = $method->invoke($page);

    expect($options[NavmgrEnumGroupPageFixture::class])->toContain('[Shop]');
});

enum NavmgrGroupFixture
{
    case Shop;
}

final class NavmgrEnumGroupPageFixture extends Page
{
    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return NavmgrGroupFixture::Shop;
    }
}
