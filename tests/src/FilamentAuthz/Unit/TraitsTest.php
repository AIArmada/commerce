<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Concerns\HasPageAuthz;
use AIArmada\FilamentAuthz\Concerns\HasPanelAuthz;
use AIArmada\FilamentAuthz\Concerns\HasWidgetAuthz;
use AIArmada\FilamentAuthz\Concerns\SyncsRolePermissions;
use AIArmada\FilamentAuthz\Resources\RoleResource\Concerns\HasAuthzFormComponents;

describe('HasPageAuthz Trait', function () {
    it('exists', function () {
        expect(trait_exists(HasPageAuthz::class))->toBeTrue();
    });

    it('has canAccess method', function () {
        $reflection = new ReflectionClass(HasPageAuthz::class);

        expect($reflection->hasMethod('canAccess'))->toBeTrue();
    });

    it('has getAuthzPermission method', function () {
        $reflection = new ReflectionClass(HasPageAuthz::class);

        expect($reflection->hasMethod('getAuthzPermission'))->toBeTrue();
    });
});

describe('HasWidgetAuthz Trait', function () {
    it('exists', function () {
        expect(trait_exists(HasWidgetAuthz::class))->toBeTrue();
    });
});

describe('HasPanelAuthz Trait', function () {
    it('exists', function () {
        expect(trait_exists(HasPanelAuthz::class))->toBeTrue();
    });
});

describe('SyncsRolePermissions Trait', function () {
    it('exists', function () {
        expect(trait_exists(SyncsRolePermissions::class))->toBeTrue();
    });
});

describe('HasAuthzFormComponents Trait', function () {
    it('exists', function () {
        expect(trait_exists(HasAuthzFormComponents::class))->toBeTrue();
    });

    it('has getPermissionTabs method', function () {
        $reflection = new ReflectionClass(HasAuthzFormComponents::class);

        expect($reflection->hasMethod('getPermissionTabs'))->toBeTrue();
    });

    it('has getResourcesTab method', function () {
        $reflection = new ReflectionClass(HasAuthzFormComponents::class);

        expect($reflection->hasMethod('getResourcesTab'))->toBeTrue();
    });

    it('has getPagesTab method', function () {
        $reflection = new ReflectionClass(HasAuthzFormComponents::class);

        expect($reflection->hasMethod('getPagesTab'))->toBeTrue();
    });

    it('has getWidgetsTab method', function () {
        $reflection = new ReflectionClass(HasAuthzFormComponents::class);

        expect($reflection->hasMethod('getWidgetsTab'))->toBeTrue();
    });

    it('has getCustomPermissionsTab method', function () {
        $reflection = new ReflectionClass(HasAuthzFormComponents::class);

        expect($reflection->hasMethod('getCustomPermissionsTab'))->toBeTrue();
    });
});
