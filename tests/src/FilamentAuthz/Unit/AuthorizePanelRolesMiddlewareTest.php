<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Http\Middleware\AuthorizePanelRoles;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

beforeEach(function (): void {
    $this->middleware = new AuthorizePanelRoles;
    $this->request = Mockery::mock(Request::class);
});

afterEach(function (): void {
    Mockery::close();
});

describe('AuthorizePanelRoles', function (): void {
    it('passes through when no panel is set', function (): void {
        Filament::shouldReceive('getCurrentPanel')->once()->andReturn(null);

        $response = $this->middleware->handle($this->request, fn () => 'passed');

        expect($response)->toBe('passed');
    });

    it('passes through when feature is disabled', function (): void {
        $panel = Mockery::mock(Panel::class);
        $panel->shouldReceive('getId')->andReturn('admin');

        Filament::shouldReceive('getCurrentPanel')->once()->andReturn($panel);
        config(['filament-authz.features.panel_role_authorization' => false]);

        $response = $this->middleware->handle($this->request, fn () => 'passed');

        expect($response)->toBe('passed');
    });

    it('throws AccessDeniedException when no user is authenticated', function (): void {
        $panel = Mockery::mock(Panel::class);
        $panel->shouldReceive('getId')->andReturn('admin');

        Filament::shouldReceive('getCurrentPanel')->once()->andReturn($panel);
        config(['filament-authz.features.panel_role_authorization' => true]);

        $this->request->shouldReceive('user')->once()->andReturn(null);

        $this->middleware->handle($this->request, fn () => 'passed');
    })->throws(AccessDeniedHttpException::class);

    it('passes through when user has super admin role', function (): void {
        $panel = Mockery::mock(Panel::class);
        $panel->shouldReceive('getId')->andReturn('admin');

        Filament::shouldReceive('getCurrentPanel')->once()->andReturn($panel);
        config(['filament-authz.features.panel_role_authorization' => true]);
        config(['filament-authz.super_admin_role' => 'super_admin']);

        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasRole')
            ->with('super_admin')
            ->once()
            ->andReturn(true);

        $this->request->shouldReceive('user')->once()->andReturn($user);

        $response = $this->middleware->handle($this->request, fn () => 'passed');

        expect($response)->toBe('passed');
    });

    it('passes through when no roles are configured for panel', function (): void {
        $panel = Mockery::mock(Panel::class);
        $panel->shouldReceive('getId')->andReturn('admin');

        Filament::shouldReceive('getCurrentPanel')->once()->andReturn($panel);
        config(['filament-authz.features.panel_role_authorization' => true]);
        config(['filament-authz.super_admin_role' => 'super_admin']);
        config(['filament-authz.panel_roles' => []]);

        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasRole')
            ->with('super_admin')
            ->once()
            ->andReturn(false);

        $this->request->shouldReceive('user')->once()->andReturn($user);

        $response = $this->middleware->handle($this->request, fn () => 'passed');

        expect($response)->toBe('passed');
    });

    it('passes through when user has any required role without guard', function (): void {
        $panel = Mockery::mock(Panel::class);
        $panel->shouldReceive('getId')->andReturn('admin');

        Filament::shouldReceive('getCurrentPanel')->once()->andReturn($panel);
        config(['filament-authz.features.panel_role_authorization' => true]);
        config(['filament-authz.super_admin_role' => 'super_admin']);
        config(['filament-authz.panel_roles.admin' => ['admin', 'editor']]);
        config(['filament-authz.panel_guard_map' => []]);

        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasRole')
            ->with('super_admin')
            ->once()
            ->andReturn(false);
        $user->shouldReceive('hasAnyRole')
            ->with(['admin', 'editor'])
            ->once()
            ->andReturn(true);

        $this->request->shouldReceive('user')->once()->andReturn($user);

        $response = $this->middleware->handle($this->request, fn () => 'passed');

        expect($response)->toBe('passed');
    });

    it('passes through when user has any required role with guard', function (): void {
        $panel = Mockery::mock(Panel::class);
        $panel->shouldReceive('getId')->andReturn('admin');

        Filament::shouldReceive('getCurrentPanel')->once()->andReturn($panel);
        config(['filament-authz.features.panel_role_authorization' => true]);
        config(['filament-authz.super_admin_role' => 'super_admin']);
        config(['filament-authz.panel_roles.admin' => ['admin', 'editor']]);
        config(['filament-authz.panel_guard_map.admin' => 'web']);

        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasRole')
            ->with('super_admin')
            ->once()
            ->andReturn(false);
        $user->shouldReceive('hasAnyRole')
            ->with(['admin', 'editor'], 'web')
            ->once()
            ->andReturn(true);

        $this->request->shouldReceive('user')->once()->andReturn($user);

        $response = $this->middleware->handle($this->request, fn () => 'passed');

        expect($response)->toBe('passed');
    });

    it('throws AccessDeniedException when user does not have required roles', function (): void {
        $panel = Mockery::mock(Panel::class);
        $panel->shouldReceive('getId')->andReturn('admin');

        Filament::shouldReceive('getCurrentPanel')->once()->andReturn($panel);
        config(['filament-authz.features.panel_role_authorization' => true]);
        config(['filament-authz.super_admin_role' => 'super_admin']);
        config(['filament-authz.panel_roles.admin' => ['admin', 'editor']]);
        config(['filament-authz.panel_guard_map' => []]);

        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasRole')
            ->with('super_admin')
            ->once()
            ->andReturn(false);
        $user->shouldReceive('hasAnyRole')
            ->with(['admin', 'editor'])
            ->once()
            ->andReturn(false);

        $this->request->shouldReceive('user')->once()->andReturn($user);

        $this->middleware->handle($this->request, fn () => 'passed');
    })->throws(AccessDeniedHttpException::class);

    it('passes through when super admin role is empty string', function (): void {
        $panel = Mockery::mock(Panel::class);
        $panel->shouldReceive('getId')->andReturn('admin');

        Filament::shouldReceive('getCurrentPanel')->once()->andReturn($panel);
        config(['filament-authz.features.panel_role_authorization' => true]);
        config(['filament-authz.super_admin_role' => '']);
        config(['filament-authz.panel_roles' => []]);

        $user = Mockery::mock(User::class)->makePartial();

        $this->request->shouldReceive('user')->once()->andReturn($user);

        $response = $this->middleware->handle($this->request, fn () => 'passed');

        expect($response)->toBe('passed');
    });

    it('handles user without hasRole method for super admin check', function (): void {
        $panel = Mockery::mock(Panel::class);
        $panel->shouldReceive('getId')->andReturn('admin');

        Filament::shouldReceive('getCurrentPanel')->once()->andReturn($panel);
        config(['filament-authz.features.panel_role_authorization' => true]);
        config(['filament-authz.super_admin_role' => 'super_admin']);
        config(['filament-authz.panel_roles' => []]);

        // Use stdClass which doesn't have hasRole method
        $user = new stdClass;

        $this->request->shouldReceive('user')->once()->andReturn($user);

        $response = $this->middleware->handle($this->request, fn () => 'passed');

        expect($response)->toBe('passed');
    });

    it('throws exception when user without hasAnyRole method fails authorization', function (): void {
        $panel = Mockery::mock(Panel::class);
        $panel->shouldReceive('getId')->andReturn('admin');

        Filament::shouldReceive('getCurrentPanel')->once()->andReturn($panel);
        config(['filament-authz.features.panel_role_authorization' => true]);
        config(['filament-authz.super_admin_role' => '']);
        config(['filament-authz.panel_roles.admin' => ['admin']]);
        config(['filament-authz.panel_guard_map' => []]);

        // Use stdClass which doesn't have hasAnyRole method
        $user = new stdClass;

        $this->request->shouldReceive('user')->once()->andReturn($user);

        $this->middleware->handle($this->request, fn () => 'passed');
    })->throws(AccessDeniedHttpException::class);
});
