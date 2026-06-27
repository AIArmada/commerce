<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\Filament\CommerceNavigation;
use AIArmada\CommerceSupport\Support\Filament\CommerceNavigationPlugin;
use Filament\Navigation\NavigationItem;

beforeEach(function (): void {
    config()->set('commerce-support.filament.navigation', [
        'enabled' => true,
        'groups' => [],
        'packages' => [],
        'items' => [],
    ]);
});

it('applies class navigation visibility group parent and sort overrides', function (): void {
    config()->set('commerce-support.filament.navigation.items', [
        CommerceNavigationFixtureResource::class => [
            'visible' => false,
            'group' => 'Operations',
            'parent_item' => 'Catalog',
            'sort' => 25,
        ],
    ]);

    $item = NavigationItem::make('Products')
        ->group('Catalog')
        ->sort(10)
        ->url('/products');

    $configured = CommerceNavigation::configureNavigationItem($item, CommerceNavigationFixtureResource::class);

    expect($configured->isVisible())->toBeFalse()
        ->and($configured->getGroup())->toBe('Operations')
        ->and($configured->getParentItem())->toBe('Catalog')
        ->and($configured->getSort())->toBe(25);
});

it('applies package navigation overrides without making inaccessible items visible', function (): void {
    config()->set('commerce-support.filament.navigation.packages', [
        'filament-products' => [
            'group' => 'Catalog',
            'items' => [
                'products' => [
                    'visible' => true,
                    'sort' => 5,
                ],
                'categories' => [
                    'hidden' => true,
                ],
            ],
        ],
    ]);

    expect(CommerceNavigation::group(CommerceNavigationFixtureResource::class, 'Default', 'filament-products', 'products'))->toBe('Catalog')
        ->and(CommerceNavigation::sort(CommerceNavigationFixtureResource::class, 10, 'filament-products', 'products'))->toBe(5)
        ->and(CommerceNavigation::visible(CommerceNavigationFixtureResource::class, false, 'filament-products', 'products'))->toBeFalse()
        ->and(CommerceNavigation::visible(CommerceNavigationFixtureResource::class, true, 'filament-products', 'categories'))->toBeFalse();
});

it('builds configured navigation groups in configured order', function (): void {
    config()->set('commerce-support.filament.navigation.groups', [
        'Catalog' => [
            'label' => 'Catalog',
            'sort' => 20,
        ],
        'Operations' => [
            'label' => 'Operations',
            'sort' => 10,
            'collapsed' => true,
        ],
    ]);

    $groups = array_values(CommerceNavigation::groups());
    $groupLabels = array_map(
        static fn ($group): ?string => $group->getLabel(),
        $groups,
    );

    expect($groupLabels)->toContain('Operations', 'Catalog');

    $opsIndex = array_search('Operations', $groupLabels, true);
    expect($opsIndex)->not->toBeFalse();
    $catIndex = array_search('Catalog', $groupLabels, true);
    expect($catIndex)->not->toBeFalse();
    expect($opsIndex)->toBeLessThan($catIndex);

    $operationGroup = $groups[$opsIndex];
    expect($operationGroup->isCollapsed())->toBeTrue();
});

it('configures panels with commerce navigation groups and builder', function (): void {
    config()->set('commerce-support.filament.navigation.groups', [
        'Catalog' => [
            'label' => 'Catalog',
        ],
    ]);

    $panel = new CommerceNavigationPanelFixture;

    CommerceNavigation::configurePanel($panel);

    expect($panel->builder)->toBeInstanceOf(Closure::class);
});

it('exposes a filament plugin for panel registration', function (): void {
    expect(CommerceNavigationPlugin::make()->getId())->toBe('commerce-navigation');
});

final class CommerceNavigationFixtureResource {}

final class CommerceNavigationPanelFixture
{
    /**
     * @var array<int, mixed>
     */
    public array $groups = [];

    public ?Closure $builder = null;

    /**
     * @param  array<int, mixed>  $groups
     */
    public function navigationGroups(array $groups): static
    {
        $this->groups = $groups;

        return $this;
    }

    public function navigation(Closure $builder): static
    {
        $this->builder = $builder;

        return $this;
    }
}
