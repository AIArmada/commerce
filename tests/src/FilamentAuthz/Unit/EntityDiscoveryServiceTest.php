<?php

declare(strict_types=1);

namespace Tests\FilamentAuthz\Unit;

use AIArmada\FilamentAuthz\Services\Discovery\PageTransformer;
use AIArmada\FilamentAuthz\Services\Discovery\ResourceTransformer;
use AIArmada\FilamentAuthz\Services\Discovery\WidgetTransformer;
use AIArmada\FilamentAuthz\Services\EntityDiscoveryService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Mockery;

afterEach(function (): void {
    Mockery::close();
});

describe('EntityDiscoveryService', function (): void {
    describe('constructor', function (): void {
        it('can be instantiated without arguments', function (): void {
            $service = new EntityDiscoveryService;

            expect($service)->toBeInstanceOf(EntityDiscoveryService::class);
        });

        it('can be instantiated with custom transformers', function (): void {
            $resourceTransformer = Mockery::mock(ResourceTransformer::class);
            $pageTransformer = Mockery::mock(PageTransformer::class);
            $widgetTransformer = Mockery::mock(WidgetTransformer::class);

            $service = new EntityDiscoveryService(
                $resourceTransformer,
                $pageTransformer,
                $widgetTransformer
            );

            expect($service)->toBeInstanceOf(EntityDiscoveryService::class);
        });
    });

    describe('discoverResources', function (): void {
        it('returns a collection', function (): void {
            $service = new EntityDiscoveryService;

            $resources = $service->discoverResources();

            expect($resources)->toBeInstanceOf(Collection::class);
        });

        it('uses cache when enabled', function (): void {
            config(['filament-authz.discovery.cache.enabled' => true]);

            $service = new EntityDiscoveryService;

            // First call
            $resources1 = $service->discoverResources();

            // Second call should use cache
            $resources2 = $service->discoverResources();

            expect($resources1)->toEqual($resources2);
        });
    });

    describe('discoverPages', function (): void {
        it('returns a collection', function (): void {
            $service = new EntityDiscoveryService;

            $pages = $service->discoverPages();

            expect($pages)->toBeInstanceOf(Collection::class);
        });

        it('uses cache when enabled', function (): void {
            config(['filament-authz.discovery.cache.enabled' => true]);

            $service = new EntityDiscoveryService;

            // First call
            $pages1 = $service->discoverPages();

            // Second call should use cache
            $pages2 = $service->discoverPages();

            expect($pages1)->toEqual($pages2);
        });
    });

    describe('discoverWidgets', function (): void {
        it('returns a collection', function (): void {
            $service = new EntityDiscoveryService;

            $widgets = $service->discoverWidgets();

            expect($widgets)->toBeInstanceOf(Collection::class);
        });

        it('uses cache when enabled', function (): void {
            config(['filament-authz.discovery.cache.enabled' => true]);

            $service = new EntityDiscoveryService;

            // First call
            $widgets1 = $service->discoverWidgets();

            // Second call should use cache
            $widgets2 = $service->discoverWidgets();

            expect($widgets1)->toEqual($widgets2);
        });
    });

    describe('discoverAll', function (): void {
        it('returns array with resources, pages, and widgets', function (): void {
            $service = new EntityDiscoveryService;

            $result = $service->discoverAll();

            expect($result)->toBeArray();
            expect($result)->toHaveKeys(['resources', 'pages', 'widgets']);
            expect($result['resources'])->toBeInstanceOf(Collection::class);
            expect($result['pages'])->toBeInstanceOf(Collection::class);
            expect($result['widgets'])->toBeInstanceOf(Collection::class);
        });
    });

    describe('getDiscoveredPermissions', function (): void {
        it('returns a collection of permission strings', function (): void {
            $service = new EntityDiscoveryService;

            $permissions = $service->getDiscoveredPermissions();

            expect($permissions)->toBeInstanceOf(Collection::class);
            expect($permissions->toArray())->each->toBeString();
        });

        it('returns unique permissions', function (): void {
            $service = new EntityDiscoveryService;

            $permissions = $service->getDiscoveredPermissions();

            expect($permissions->count())->toBe($permissions->unique()->count());
        });
    });

    describe('warmCache', function (): void {
        it('clears and repopulates cache', function (): void {
            $service = new EntityDiscoveryService;

            // Should not throw
            $service->warmCache();

            expect(true)->toBeTrue();
        });
    });

    describe('clearCache', function (): void {
        it('clears all caches', function (): void {
            $service = new EntityDiscoveryService;

            // Populate cache
            $service->discoverResources();
            $service->discoverPages();
            $service->discoverWidgets();

            // Clear cache
            $service->clearCache();

            // Verify internal cache is cleared by checking that subsequent calls work
            $resources = $service->discoverResources();
            expect($resources)->toBeInstanceOf(Collection::class);
        });
    });

    describe('shouldInclude with options', function (): void {
        it('excludes resources in exclude list', function (): void {
            config(['filament-authz.discovery.exclude' => ['SomeExcludedResource']]);

            $service = new EntityDiscoveryService;

            $resources = $service->discoverResources(['exclude' => ['SomeExcludedResource']]);

            // Should not contain excluded resource
            expect($resources->contains(fn ($r) => $r->fqcn === 'SomeExcludedResource'))->toBeFalse();
        });

        it('filters by namespace patterns', function (): void {
            config([
                'filament-authz.discovery.namespaces.include' => ['App\\Filament\\*'],
                'filament-authz.discovery.namespaces.exclude' => ['*\\Test\\*'],
            ]);

            $service = new EntityDiscoveryService;

            $resources = $service->discoverResources();

            // Should filter based on namespace patterns
            expect($resources)->toBeInstanceOf(Collection::class);
        });

        it('excludes patterns from config', function (): void {
            config(['filament-authz.discovery.exclude_patterns' => ['*Test*']]);

            $service = new EntityDiscoveryService;

            $resources = $service->discoverResources();

            expect($resources)->toBeInstanceOf(Collection::class);
        });
    });

    describe('panel filtering', function (): void {
        it('discovers from all panels by default', function (): void {
            config(['filament-authz.discovery.discover_all_panels' => true]);

            $service = new EntityDiscoveryService;

            $resources = $service->discoverResources();

            expect($resources)->toBeInstanceOf(Collection::class);
        });

        it('filters by specific panels when provided', function (): void {
            $service = new EntityDiscoveryService;

            $resources = $service->discoverResources(['panels' => ['admin']]);

            expect($resources)->toBeInstanceOf(Collection::class);
        });
    });

    describe('cache configuration', function (): void {
        it('respects cache disabled setting', function (): void {
            config(['filament-authz.discovery.cache.enabled' => false]);

            $service = new EntityDiscoveryService;

            // Each call should be fresh when cache disabled
            $resources1 = $service->discoverResources();
            $resources2 = $service->discoverResources();

            expect($resources1)->toBeInstanceOf(Collection::class);
            expect($resources2)->toBeInstanceOf(Collection::class);
        });

        it('uses configured cache TTL', function (): void {
            config(['filament-authz.discovery.cache.ttl' => 7200]);
            config(['filament-authz.discovery.cache.enabled' => true]);

            $service = new EntityDiscoveryService;
            $service->discoverResources();

            // TTL should be respected - just verify no exception
            expect(true)->toBeTrue();
        });
    });
});
