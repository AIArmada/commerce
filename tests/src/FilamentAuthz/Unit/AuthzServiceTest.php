<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Authz;
use AIArmada\FilamentAuthz\Services\EntityDiscoveryService;
use AIArmada\FilamentAuthz\Services\PermissionKeyBuilder;

beforeEach(function (): void {
    $this->keyBuilder = new PermissionKeyBuilder;
    $this->discovery = new EntityDiscoveryService($this->keyBuilder);
    $this->authz = new Authz($this->discovery, $this->keyBuilder);
});

describe('Authz Service', function (): void {
    it('can build permission keys', function (): void {
        $permission = $this->authz->buildPermissionKey('Order', 'view');

        expect($permission)->toBeString();
        expect($permission)->toContain('order');
        expect($permission)->toContain('view');
    });

    it('can use custom permission key builder', function (): void {
        $this->authz->buildPermissionKeyUsing(fn ($subject, $action) => mb_strtoupper("{$subject}_{$action}"));

        $permission = $this->authz->buildPermissionKey('Order', 'view');

        expect($permission)->toBe('ORDER_VIEW');
    });

    it('can get custom permissions from config', function (): void {
        config()->set('filament-authz.custom_permissions', [
            'export_data' => 'Export Data',
            'approve_posts',
        ]);

        $custom = $this->authz->getCustomPermissions();

        expect($custom)->toBeArray();
        expect($custom)->toHaveKey('export_data');
        expect($custom['export_data'])->toBe('Export Data');
        expect($custom)->toHaveKey('approve_posts');
    });

    it('can clear cache', function (): void {
        $this->authz->clearCache();

        expect(true)->toBeTrue();
    });

    it('returns empty collection when panel is null', function (): void {
        $resources = $this->authz->getResources(null);
        $pages = $this->authz->getPages(null);
        $widgets = $this->authz->getWidgets(null);

        expect($resources)->toBeEmpty();
        expect($pages)->toBeEmpty();
        expect($widgets)->toBeEmpty();
    });
});

describe('Permission Key Builder', function (): void {
    it('builds keys with default separator', function (): void {
        config()->set('filament-authz.permissions.separator', '.');
        config()->set('filament-authz.permissions.case', 'kebab');

        $builder = new PermissionKeyBuilder;
        $key = $builder->build('Order', 'viewAny');

        expect($key)->toBe('order.view-any');
    });

    it('supports snake case', function (): void {
        config()->set('filament-authz.permissions.case', 'snake');

        $builder = new PermissionKeyBuilder;
        $key = $builder->build('OrderItem', 'viewAny');

        expect($key)->toBe('order_item.view_any');
    });

    it('supports kebab case', function (): void {
        config()->set('filament-authz.permissions.case', 'kebab');

        $builder = new PermissionKeyBuilder;
        $key = $builder->build('OrderItem', 'viewAny');

        expect($key)->toBe('order-item.view-any');
    });

    it('supports camel case', function (): void {
        config()->set('filament-authz.permissions.case', 'camel');

        $builder = new PermissionKeyBuilder;
        $key = $builder->build('OrderItem', 'viewAny');

        expect($key)->toBe('orderItem.viewAny');
    });

    it('supports pascal case', function (): void {
        config()->set('filament-authz.permissions.case', 'pascal');

        $builder = new PermissionKeyBuilder;
        $key = $builder->build('order_item', 'view_any');

        expect($key)->toBe('OrderItem.ViewAny');
    });

    it('supports upper snake case', function (): void {
        config()->set('filament-authz.permissions.case', 'upper_snake');

        $builder = new PermissionKeyBuilder;
        $key = $builder->build('OrderItem', 'viewAny');

        expect($key)->toBe('ORDER_ITEM.VIEW_ANY');
    });

    it('supports lower case', function (): void {
        config()->set('filament-authz.permissions.case', 'lower');

        $builder = new PermissionKeyBuilder;
        $key = $builder->build('OrderItem', 'ViewAny');

        expect($key)->toBe('orderitem.viewany');
    });

    it('uses custom separator', function (): void {
        config()->set('filament-authz.permissions.separator', '_');
        config()->set('filament-authz.permissions.case', 'snake');

        $builder = new PermissionKeyBuilder;
        $key = $builder->build('Order', 'view');

        expect($key)->toBe('order_view');
    });
});
