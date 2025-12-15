<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Widgets\ImpersonationBannerWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['filament-authz.user_model' => User::class]);
    config(['filament-authz.super_admin_role' => 'super_admin']);
});

describe('ImpersonationBannerWidget', function (): void {
    it('has full column span', function (): void {
        $widget = new ImpersonationBannerWidget;
        $reflection = new ReflectionClass($widget);
        $property = $reflection->getProperty('columnSpan');

        expect($property->getValue($widget))->toBe('full');
    });

    it('uses correct view', function (): void {
        $widget = new ImpersonationBannerWidget;
        $reflection = new ReflectionClass($widget);
        $property = $reflection->getProperty('view');

        expect($property->getValue($widget))->toBe('filament-authz::widgets.impersonation-banner');
    });

    it('denies view when user is not authenticated', function (): void {
        Auth::shouldReceive('user')->andReturn(null);

        expect(ImpersonationBannerWidget::canView())->toBeFalse();
    });

    it('denies view when user does not have super admin role', function (): void {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasRole')
            ->with('super_admin')
            ->andReturn(false);

        Auth::shouldReceive('user')->andReturn($user);

        expect(ImpersonationBannerWidget::canView())->toBeFalse();
    });

    it('allows view when user has super admin role', function (): void {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasRole')
            ->with('super_admin')
            ->andReturn(true);

        Auth::shouldReceive('user')->andReturn($user);

        expect(ImpersonationBannerWidget::canView())->toBeTrue();
    });

    it('returns None when no user authenticated for role context', function (): void {
        Auth::shouldReceive('user')->andReturn(null);

        $widget = new ImpersonationBannerWidget;
        $result = $widget->getCurrentRoleContext();

        expect($result)->toBe('None');
    });

    it('returns joined role names when user has roles', function (): void {
        $roles = collect([
            (object) ['name' => 'admin'],
            (object) ['name' => 'editor'],
        ]);

        $user = Mockery::mock(User::class)->makePartial();
        $user->roles = $roles;

        Auth::shouldReceive('user')->andReturn($user);

        $widget = new ImpersonationBannerWidget;
        $result = $widget->getCurrentRoleContext();

        expect($result)->toBe('admin, editor');
    });
});
