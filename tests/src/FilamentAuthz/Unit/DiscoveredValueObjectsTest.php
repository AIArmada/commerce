<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\FilamentAuthz\Unit;

use AIArmada\FilamentAuthz\ValueObjects\DiscoveredPage;
use AIArmada\FilamentAuthz\ValueObjects\DiscoveredResource;
use AIArmada\FilamentAuthz\ValueObjects\DiscoveredWidget;

describe('DiscoveredPage', function (): void {
    describe('constructor', function (): void {
        it('creates instance with required fqcn', function (): void {
            $page = new DiscoveredPage(fqcn: 'App\\Filament\\Pages\\Dashboard');

            expect($page->fqcn)->toBe('App\\Filament\\Pages\\Dashboard')
                ->and($page->title)->toBeNull()
                ->and($page->slug)->toBeNull()
                ->and($page->cluster)->toBeNull()
                ->and($page->permissions)->toBe([])
                ->and($page->metadata)->toBe([])
                ->and($page->panel)->toBeNull();
        });

        it('creates instance with all properties', function (): void {
            $page = new DiscoveredPage(
                fqcn: 'App\\Filament\\Pages\\Settings',
                title: 'Application Settings',
                slug: 'settings',
                cluster: 'Admin',
                permissions: ['settings.view', 'settings.edit'],
                metadata: ['icon' => 'heroicon-o-cog'],
                panel: 'admin'
            );

            expect($page->fqcn)->toBe('App\\Filament\\Pages\\Settings')
                ->and($page->title)->toBe('Application Settings')
                ->and($page->slug)->toBe('settings')
                ->and($page->cluster)->toBe('Admin')
                ->and($page->permissions)->toBe(['settings.view', 'settings.edit'])
                ->and($page->metadata)->toBe(['icon' => 'heroicon-o-cog'])
                ->and($page->panel)->toBe('admin');
        });
    });

    describe('getPermissionKey', function (): void {
        it('uses slug when provided', function (): void {
            $page = new DiscoveredPage(
                fqcn: 'App\\Filament\\Pages\\Dashboard',
                slug: 'main-dashboard'
            );

            expect($page->getPermissionKey())->toBe('page.main-dashboard');
        });

        it('derives from class basename when slug not provided', function (): void {
            $page = new DiscoveredPage(fqcn: 'App\\Filament\\Pages\\UserSettings');

            expect($page->getPermissionKey())->toBe('page.user-settings');
        });
    });

    describe('getBasename', function (): void {
        it('returns class basename', function (): void {
            $page = new DiscoveredPage(fqcn: 'App\\Filament\\Pages\\Dashboard');

            expect($page->getBasename())->toBe('Dashboard');
        });

        it('handles deep namespaces', function (): void {
            $page = new DiscoveredPage(fqcn: 'App\\Filament\\Admin\\Pages\\Settings\\GeneralSettings');

            expect($page->getBasename())->toBe('GeneralSettings');
        });
    });

    describe('toArray', function (): void {
        it('converts to array with all properties', function (): void {
            $page = new DiscoveredPage(
                fqcn: 'App\\Filament\\Pages\\Dashboard',
                title: 'Dashboard',
                slug: 'dashboard',
                cluster: 'Main',
                permissions: ['dashboard.view'],
                panel: 'admin',
                metadata: ['sortOrder' => 1]
            );

            $array = $page->toArray();

            expect($array)->toBe([
                'fqcn' => 'App\\Filament\\Pages\\Dashboard',
                'basename' => 'Dashboard',
                'title' => 'Dashboard',
                'slug' => 'dashboard',
                'cluster' => 'Main',
                'permission' => 'page.dashboard',
                'permissions' => ['dashboard.view'],
                'panel' => 'admin',
                'metadata' => ['sortOrder' => 1],
            ]);
        });

        it('includes derived values when optional fields null', function (): void {
            $page = new DiscoveredPage(fqcn: 'App\\Filament\\Pages\\UserProfile');

            $array = $page->toArray();

            expect($array['basename'])->toBe('UserProfile')
                ->and($array['permission'])->toBe('page.user-profile')
                ->and($array['title'])->toBeNull();
        });
    });
});

describe('DiscoveredWidget', function (): void {
    describe('constructor', function (): void {
        it('creates instance with required fqcn', function (): void {
            $widget = new DiscoveredWidget(fqcn: 'App\\Filament\\Widgets\\StatsOverview');

            expect($widget->fqcn)->toBe('App\\Filament\\Widgets\\StatsOverview')
                ->and($widget->name)->toBeNull()
                ->and($widget->type)->toBeNull()
                ->and($widget->permissions)->toBe([])
                ->and($widget->metadata)->toBe([])
                ->and($widget->panel)->toBeNull();
        });

        it('creates instance with all properties', function (): void {
            $widget = new DiscoveredWidget(
                fqcn: 'App\\Filament\\Widgets\\SalesChart',
                name: 'sales_chart',
                type: 'chart',
                permissions: ['charts.view'],
                metadata: ['isChart' => true],
                panel: 'admin'
            );

            expect($widget->fqcn)->toBe('App\\Filament\\Widgets\\SalesChart')
                ->and($widget->name)->toBe('sales_chart')
                ->and($widget->type)->toBe('chart')
                ->and($widget->permissions)->toBe(['charts.view'])
                ->and($widget->metadata)->toBe(['isChart' => true])
                ->and($widget->panel)->toBe('admin');
        });
    });

    describe('getPermissionKey', function (): void {
        it('uses name when provided', function (): void {
            $widget = new DiscoveredWidget(
                fqcn: 'App\\Filament\\Widgets\\Stats',
                name: 'overview_stats'
            );

            expect($widget->getPermissionKey())->toBe('widget.overview_stats');
        });

        it('derives from class basename when name not provided', function (): void {
            $widget = new DiscoveredWidget(fqcn: 'App\\Filament\\Widgets\\StatsOverview');

            expect($widget->getPermissionKey())->toBe('widget.stats_overview');
        });
    });

    describe('getBasename', function (): void {
        it('returns class basename', function (): void {
            $widget = new DiscoveredWidget(fqcn: 'App\\Filament\\Widgets\\RecentOrders');

            expect($widget->getBasename())->toBe('RecentOrders');
        });
    });

    describe('isChart', function (): void {
        it('returns true when metadata indicates chart', function (): void {
            $widget = new DiscoveredWidget(
                fqcn: 'App\\Filament\\Widgets\\SalesChart',
                metadata: ['isChart' => true]
            );

            expect($widget->isChart())->toBeTrue();
        });

        it('returns false when not a chart', function (): void {
            $widget = new DiscoveredWidget(fqcn: 'App\\Filament\\Widgets\\Stats');

            expect($widget->isChart())->toBeFalse();
        });

        it('returns false when metadata missing isChart key', function (): void {
            $widget = new DiscoveredWidget(
                fqcn: 'App\\Filament\\Widgets\\Stats',
                metadata: ['other' => 'value']
            );

            expect($widget->isChart())->toBeFalse();
        });
    });

    describe('isStats', function (): void {
        it('returns true when metadata indicates stats', function (): void {
            $widget = new DiscoveredWidget(
                fqcn: 'App\\Filament\\Widgets\\StatsOverview',
                metadata: ['isStats' => true]
            );

            expect($widget->isStats())->toBeTrue();
        });

        it('returns false when not stats', function (): void {
            $widget = new DiscoveredWidget(fqcn: 'App\\Filament\\Widgets\\Table');

            expect($widget->isStats())->toBeFalse();
        });
    });

    describe('toArray', function (): void {
        it('converts to array with all properties', function (): void {
            $widget = new DiscoveredWidget(
                fqcn: 'App\\Filament\\Widgets\\SalesChart',
                name: 'sales',
                type: 'chart',
                permissions: ['widget.sales'],
                metadata: ['isChart' => true],
                panel: 'admin'
            );

            $array = $widget->toArray();

            expect($array)->toBe([
                'fqcn' => 'App\\Filament\\Widgets\\SalesChart',
                'basename' => 'SalesChart',
                'name' => 'sales',
                'type' => 'chart',
                'permission' => 'widget.sales',
                'permissions' => ['widget.sales'],
                'panel' => 'admin',
                'metadata' => ['isChart' => true],
            ]);
        });

        it('uses basename as name when name is null', function (): void {
            $widget = new DiscoveredWidget(fqcn: 'App\\Filament\\Widgets\\OrdersWidget');

            $array = $widget->toArray();

            expect($array['name'])->toBe('OrdersWidget');
        });
    });
});

describe('DiscoveredResource', function (): void {
    it('builds permission keys from model basename', function (): void {
        $resource = new DiscoveredResource(
            fqcn: 'App\\Filament\\Resources\\OrderResource',
            model: 'App\\Models\\Order',
            permissions: ['viewAny', 'view', 'create'],
            metadata: [],
            panel: 'admin',
        );

        expect($resource->toPermissionKeys())->toBe([
            'order.viewAny',
            'order.view',
            'order.create',
        ]);
    });

    it('supports custom permission separator', function (): void {
        $resource = new DiscoveredResource(
            fqcn: 'App\\Filament\\Resources\\OrderResource',
            model: 'App\\Models\\Order',
            permissions: ['viewAny', 'delete'],
            metadata: [],
        );

        expect($resource->toPermissionKeys(':'))->toBe([
            'order:viewAny',
            'order:delete',
        ]);
    });

    it('derives the policy class name from the model namespace', function (): void {
        $resource = new DiscoveredResource(
            fqcn: 'App\\Filament\\Resources\\OrderResource',
            model: 'App\\Models\\Order',
            permissions: [],
            metadata: [],
        );

        expect($resource->getPolicyClass())->toBe('App\\Policies\\OrderPolicy');
    });

    it('detects when an existing policy class exists', function (): void {
        if (! class_exists('App\\Policies\\OrderPolicy')) {
            eval('namespace App\\Policies; class OrderPolicy {}');
        }

        $resource = new DiscoveredResource(
            fqcn: 'App\\Filament\\Resources\\OrderResource',
            model: 'App\\Models\\Order',
            permissions: [],
            metadata: [],
        );

        expect($resource->hasExistingPolicy())->toBeTrue();
    });

    it('converts to array including derived values', function (): void {
        $resource = new DiscoveredResource(
            fqcn: 'App\\Filament\\Resources\\OrderResource',
            model: 'App\\Models\\Order',
            permissions: ['viewAny', 'view'],
            metadata: ['icon' => 'heroicon-o-shopping-bag'],
            panel: 'admin',
            navigationGroup: 'Sales',
            navigationLabel: 'Orders',
            slug: 'orders',
            cluster: 'Commerce',
        );

        $array = $resource->toArray();

        expect($array['fqcn'])->toBe('App\\Filament\\Resources\\OrderResource')
            ->and($array['model'])->toBe('App\\Models\\Order')
            ->and($array['model_basename'])->toBe('Order')
            ->and($array['permissions'])->toBe(['viewAny', 'view'])
            ->and($array['permission_keys'])->toBe(['order.viewAny', 'order.view'])
            ->and($array['panel'])->toBe('admin')
            ->and($array['navigation_group'])->toBe('Sales')
            ->and($array['navigation_label'])->toBe('Orders')
            ->and($array['slug'])->toBe('orders')
            ->and($array['cluster'])->toBe('Commerce')
            ->and($array['policy_class'])->toBe('App\\Policies\\OrderPolicy')
            ->and($array['metadata'])->toBe(['icon' => 'heroicon-o-shopping-bag']);
    });
});
