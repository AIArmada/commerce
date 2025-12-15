<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\FilamentAuthz\Unit;

use AIArmada\FilamentAuthz\Support\DefaultAbilityToPermissionMapper;

describe('DefaultAbilityToPermissionMapper', function (): void {
    beforeEach(function (): void {
        $this->mapper = new DefaultAbilityToPermissionMapper;
    });

    it('maps model class and ability to permission string', function (): void {
        $result = ($this->mapper)('App\\Models\\User', 'viewAny');

        expect($result)->toBe('user.viewAny');
    });

    it('handles namespaced model classes', function (): void {
        $result = ($this->mapper)('App\\Domain\\Models\\BlogPost', 'create');

        expect($result)->toBe('blogpost.create');
    });

    it('converts class name to lowercase', function (): void {
        $result = ($this->mapper)('App\\Models\\UserProfile', 'view');

        expect($result)->toBe('userprofile.view');
    });

    it('preserves ability case', function (): void {
        $result = ($this->mapper)('App\\Models\\Post', 'viewAny');

        expect($result)->toBe('post.viewAny');
    });

    it('works with simple class names', function (): void {
        $result = ($this->mapper)('Order', 'delete');

        expect($result)->toBe('order.delete');
    });

    it('handles various abilities', function (string $modelClass, string $ability, string $expected): void {
        $result = ($this->mapper)($modelClass, $ability);

        expect($result)->toBe($expected);
    })->with([
        ['App\\Models\\User', 'viewAny', 'user.viewAny'],
        ['App\\Models\\User', 'view', 'user.view'],
        ['App\\Models\\User', 'create', 'user.create'],
        ['App\\Models\\User', 'update', 'user.update'],
        ['App\\Models\\User', 'delete', 'user.delete'],
        ['App\\Models\\Post', 'publish', 'post.publish'],
        ['App\\Models\\Order', 'process', 'order.process'],
    ]);
});
