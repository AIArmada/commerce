<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Concerns\HasPanelAuthz;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('filament-authz.super_admin_role', 'super-admin');
    config()->set('filament-authz.panel_user_role', 'panel-user');
    config()->set('filament-authz.panel_roles', []);
});

// Create a minimal test class using the trait
class TestUserWithPanelAuthz
{
    use HasPanelAuthz;

    public bool $hasRoleCalled = false;

    public array $rolesForCheck = [];

    public function hasRole(string $role): bool
    {
        $this->hasRoleCalled = true;

        return in_array($role, $this->rolesForCheck);
    }

    public function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if (in_array($role, $this->rolesForCheck)) {
                return true;
            }
        }

        return false;
    }
}

describe('HasPanelAuthz', function () {
    it('allows panel access for super admin', function () {
        $user = new TestUserWithPanelAuthz;
        $user->rolesForCheck = ['super-admin'];

        $panel = Mockery::mock(Panel::class);
        $panel->shouldReceive('getId')->andReturn('admin');

        expect($user->canAccessPanel($panel))->toBeTrue();
    });

    it('allows panel access when user has panel-specific role', function () {
        config()->set('filament-authz.panel_roles.admin', ['admin', 'editor']);

        $user = new TestUserWithPanelAuthz;
        $user->rolesForCheck = ['editor'];

        $panel = Mockery::mock(Panel::class);
        $panel->shouldReceive('getId')->andReturn('admin');

        expect($user->canAccessPanel($panel))->toBeTrue();
    });

    it('denies panel access when user lacks panel-specific role', function () {
        config()->set('filament-authz.panel_roles.admin', ['admin', 'editor']);

        $user = new TestUserWithPanelAuthz;
        $user->rolesForCheck = ['viewer'];

        $panel = Mockery::mock(Panel::class);
        $panel->shouldReceive('getId')->andReturn('admin');

        expect($user->canAccessPanel($panel))->toBeFalse();
    });

    it('falls back to panel user role when no panel-specific roles configured', function () {
        config()->set('filament-authz.panel_roles', []);

        $user = new TestUserWithPanelAuthz;
        $user->rolesForCheck = ['panel-user'];

        $panel = Mockery::mock(Panel::class);
        $panel->shouldReceive('getId')->andReturn('admin');

        expect($user->canAccessPanel($panel))->toBeTrue();
    });

    it('denies access when user lacks panel user role', function () {
        config()->set('filament-authz.panel_roles', []);

        $user = new TestUserWithPanelAuthz;
        $user->rolesForCheck = ['some-other-role'];

        $panel = Mockery::mock(Panel::class);
        $panel->shouldReceive('getId')->andReturn('admin');

        expect($user->canAccessPanel($panel))->toBeFalse();
    });

    it('returns accessible panels', function () {
        $panel1 = Mockery::mock(Panel::class);
        $panel1->shouldReceive('getId')->andReturn('admin');

        $panel2 = Mockery::mock(Panel::class);
        $panel2->shouldReceive('getId')->andReturn('public');

        config()->set('filament-authz.panel_roles.admin', ['admin']);
        config()->set('filament-authz.panel_roles.public', ['user']);

        Filament::shouldReceive('getPanels')->andReturn([
            'admin' => $panel1,
            'public' => $panel2,
        ]);

        $user = new TestUserWithPanelAuthz;
        $user->rolesForCheck = ['user'];

        $accessiblePanels = $user->getAccessiblePanels();

        expect($accessiblePanels)->toHaveCount(1);
        expect($accessiblePanels->first())->toBe($panel2);
    });

    it('returns true for hasAnyPanelAccess when user has panel access', function () {
        $panel = Mockery::mock(Panel::class);
        $panel->shouldReceive('getId')->andReturn('admin');

        Filament::shouldReceive('getPanels')->andReturn(['admin' => $panel]);

        $user = new TestUserWithPanelAuthz;
        $user->rolesForCheck = ['super-admin'];

        expect($user->hasAnyPanelAccess())->toBeTrue();
    });

    it('returns false for hasAnyPanelAccess when user has no panel access', function () {
        $panel = Mockery::mock(Panel::class);
        $panel->shouldReceive('getId')->andReturn('admin');

        config()->set('filament-authz.panel_roles.admin', ['admin']);

        Filament::shouldReceive('getPanels')->andReturn(['admin' => $panel]);

        $user = new TestUserWithPanelAuthz;
        $user->rolesForCheck = ['viewer'];

        expect($user->hasAnyPanelAccess())->toBeFalse();
    });

    it('returns default panel as first accessible panel', function () {
        $panel1 = Mockery::mock(Panel::class);
        $panel1->shouldReceive('getId')->andReturn('admin');

        $panel2 = Mockery::mock(Panel::class);
        $panel2->shouldReceive('getId')->andReturn('public');

        Filament::shouldReceive('getPanels')->andReturn([
            'admin' => $panel1,
            'public' => $panel2,
        ]);

        $user = new TestUserWithPanelAuthz;
        $user->rolesForCheck = ['super-admin'];

        $defaultPanel = $user->getDefaultPanel();

        expect($defaultPanel)->toBe($panel1);
    });

    it('returns null for default panel when no panels accessible', function () {
        $panel = Mockery::mock(Panel::class);
        $panel->shouldReceive('getId')->andReturn('admin');

        config()->set('filament-authz.panel_roles.admin', ['admin']);

        Filament::shouldReceive('getPanels')->andReturn(['admin' => $panel]);

        $user = new TestUserWithPanelAuthz;
        $user->rolesForCheck = [];

        expect($user->getDefaultPanel())->toBeNull();
    });
});
