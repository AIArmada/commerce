<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Concerns\HasPageAuthz;
use AIArmada\FilamentAuthz\Concerns\HasPanelAuthz;
use AIArmada\FilamentAuthz\Concerns\HasWidgetAuthz;
use AIArmada\FilamentAuthz\Concerns\SyncsRolePermissions;
use AIArmada\FilamentAuthz\Resources\RoleResource\Concerns\HasAuthzFormComponents;
use Filament\Pages\Page;
use Filament\Widgets\Widget;

class CustomAuthzPage extends Page
{
    use HasPageAuthz;

    protected string $view = 'filament-authz::tests.page';

    public static function authzPermission(): ?string
    {
        return 'page.custom-access';
    }
}

class CustomAuthzWidget extends Widget
{
    use HasWidgetAuthz;

    protected string $view = 'filament-authz::tests.widget';

    public static function authzPermission(): ?string
    {
        return 'widget.custom-access';
    }
}

describe('HasPageAuthz Trait', function (): void {
    it('exists', function (): void {
        expect(trait_exists(HasPageAuthz::class))->toBeTrue();
    });

    it('has canAccess method', function (): void {
        $reflection = new ReflectionClass(HasPageAuthz::class);

        expect($reflection->hasMethod('canAccess'))->toBeTrue();
    });

    it('has getAuthzPermission method', function (): void {
        $reflection = new ReflectionClass(HasPageAuthz::class);

        expect($reflection->hasMethod('getAuthzPermission'))->toBeTrue();
    });

    it('uses custom permission override', function (): void {
        expect(CustomAuthzPage::getAuthzPermission())->toBe('page.custom-access');
    });
});

describe('HasWidgetAuthz Trait', function (): void {
    it('exists', function (): void {
        expect(trait_exists(HasWidgetAuthz::class))->toBeTrue();
    });

    it('uses custom permission override', function (): void {
        expect(CustomAuthzWidget::getAuthzPermission())->toBe('widget.custom-access');
    });
});

describe('HasPanelAuthz Trait', function (): void {
    it('exists', function (): void {
        expect(trait_exists(HasPanelAuthz::class))->toBeTrue();
    });
});

describe('SyncsRolePermissions Trait', function (): void {
    it('exists', function (): void {
        expect(trait_exists(SyncsRolePermissions::class))->toBeTrue();
    });
});

describe('HasAuthzFormComponents Trait', function (): void {
    it('exists', function (): void {
        expect(trait_exists(HasAuthzFormComponents::class))->toBeTrue();
    });

    it('has getPermissionTabs method', function (): void {
        $reflection = new ReflectionClass(HasAuthzFormComponents::class);

        expect($reflection->hasMethod('getPermissionTabs'))->toBeTrue();
    });

    it('has getResourcesTab method', function (): void {
        $reflection = new ReflectionClass(HasAuthzFormComponents::class);

        expect($reflection->hasMethod('getResourcesTab'))->toBeTrue();
    });

    it('has getPagesTab method', function (): void {
        $reflection = new ReflectionClass(HasAuthzFormComponents::class);

        expect($reflection->hasMethod('getPagesTab'))->toBeTrue();
    });

    it('has getWidgetsTab method', function (): void {
        $reflection = new ReflectionClass(HasAuthzFormComponents::class);

        expect($reflection->hasMethod('getWidgetsTab'))->toBeTrue();
    });

    it('has getCustomPermissionsTab method', function (): void {
        $reflection = new ReflectionClass(HasAuthzFormComponents::class);

        expect($reflection->hasMethod('getCustomPermissionsTab'))->toBeTrue();
    });
});
