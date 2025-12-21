<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\FilamentAuthz\Unit;

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Enums\AuditEventType;
use AIArmada\FilamentAuthz\Enums\AuditSeverity;
use AIArmada\FilamentAuthz\Listeners\PermissionEventSubscriber;
use AIArmada\FilamentAuthz\Services\AuditLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use AIArmada\FilamentAuthz\Models\Permission;
use AIArmada\FilamentAuthz\Models\Role;
use stdClass;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->auditLogger = Mockery::mock(AuditLogger::class);
    $this->subscriber = new PermissionEventSubscriber($this->auditLogger);
});

afterEach(function (): void {
    Mockery::close();
});

describe('PermissionEventSubscriber', function (): void {
    describe('subscribe', function (): void {
        it('registers event listeners', function (): void {
            $dispatcher = Mockery::mock(Dispatcher::class);

            $dispatcher->shouldReceive('listen')
                ->with(Login::class, Mockery::type('array'))
                ->once();

            $dispatcher->shouldReceive('listen')
                ->with(Logout::class, Mockery::type('array'))
                ->once();

            $dispatcher->shouldReceive('listen')
                ->with(Failed::class, Mockery::type('array'))
                ->once();

            $dispatcher->shouldReceive('listen')
                ->with(PasswordReset::class, Mockery::type('array'))
                ->once();

            $dispatcher->shouldReceive('listen')
                ->with('Spatie\\Permission\\Events\\RoleCreated', Mockery::type('array'))
                ->once();

            $dispatcher->shouldReceive('listen')
                ->with('Spatie\\Permission\\Events\\RoleDeleted', Mockery::type('array'))
                ->once();

            $dispatcher->shouldReceive('listen')
                ->with('Spatie\\Permission\\Events\\PermissionCreated', Mockery::type('array'))
                ->once();

            $dispatcher->shouldReceive('listen')
                ->with('Spatie\\Permission\\Events\\PermissionDeleted', Mockery::type('array'))
                ->once();

            $this->subscriber->subscribe($dispatcher);
        });
    });

    describe('handleLogin', function (): void {
        it('logs user login event', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password',
            ]);

            $this->auditLogger
                ->shouldReceive('log')
                ->once()
                ->with(
                    Mockery::on(fn ($eventType): bool => $eventType === AuditEventType::UserLogin),
                    Mockery::on(fn ($subject): bool => $subject->id === $user->id)
                );

            $event = new Login('web', $user, false);
            $this->subscriber->handleLogin($event);
        });
    });

    describe('handleLogout', function (): void {
        it('logs user logout event', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password',
            ]);

            $this->auditLogger
                ->shouldReceive('log')
                ->once()
                ->with(
                    Mockery::on(fn ($eventType): bool => $eventType === AuditEventType::UserLogout),
                    Mockery::on(fn ($subject): bool => $subject->id === $user->id)
                );

            $event = new Logout('web', $user);
            $this->subscriber->handleLogout($event);
        });
    });

    describe('handleFailedLogin', function (): void {
        it('logs failed login event', function (): void {
            $credentials = ['email' => 'test@example.com', 'password' => 'wrong'];

            $this->auditLogger
                ->shouldReceive('log')
                ->once()
                ->withArgs(function ($eventType, $subject, $target, $oldValues, $newValues, $metadata, $severity) use ($credentials): bool {
                    return $eventType === AuditEventType::LoginFailed
                        && $subject === null
                        && $metadata === ['credentials' => $credentials]
                        && $severity === AuditSeverity::Medium;
                });

            $event = new Failed('web', null, $credentials);
            $this->subscriber->handleFailedLogin($event);
        });
    });

    describe('handlePasswordReset', function (): void {
        it('logs password reset event', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password',
            ]);

            $this->auditLogger
                ->shouldReceive('log')
                ->once()
                ->with(
                    Mockery::on(fn ($eventType): bool => $eventType === AuditEventType::PasswordChanged),
                    Mockery::on(fn ($subject): bool => $subject->id === $user->id)
                );

            $event = new PasswordReset($user);
            $this->subscriber->handlePasswordReset($event);
        });
    });

    describe('handleRoleCreated', function (): void {
        it('logs role created event when role property exists', function (): void {
            $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);

            $event = new stdClass;
            $event->role = $role;

            $this->auditLogger
                ->shouldReceive('logRoleCreated')
                ->once()
                ->with($role);

            $this->subscriber->handleRoleCreated($event);
        });

        it('does nothing when role property missing', function (): void {
            $event = new stdClass;

            $this->auditLogger->shouldNotReceive('logRoleCreated');

            $this->subscriber->handleRoleCreated($event);
        });
    });

    describe('handleRoleDeleted', function (): void {
        it('logs role deleted event when role property exists', function (): void {
            $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);

            $event = new stdClass;
            $event->role = $role;

            $this->auditLogger
                ->shouldReceive('logRoleDeleted')
                ->once()
                ->with($role);

            $this->subscriber->handleRoleDeleted($event);
        });

        it('does nothing when role property missing', function (): void {
            $event = new stdClass;

            $this->auditLogger->shouldNotReceive('logRoleDeleted');

            $this->subscriber->handleRoleDeleted($event);
        });
    });

    describe('handlePermissionCreated', function (): void {
        it('logs permission created event when permission property exists', function (): void {
            $permission = Permission::create(['name' => 'posts.view', 'guard_name' => 'web']);

            $event = new stdClass;
            $event->permission = $permission;

            $this->auditLogger
                ->shouldReceive('log')
                ->once()
                ->with(
                    Mockery::on(fn ($eventType): bool => $eventType === AuditEventType::PermissionCreated),
                    Mockery::on(fn ($subject): bool => $subject->id === $permission->id)
                );

            $this->subscriber->handlePermissionCreated($event);
        });

        it('does nothing when permission property missing', function (): void {
            $event = new stdClass;

            $this->auditLogger->shouldNotReceive('log');

            $this->subscriber->handlePermissionCreated($event);
        });
    });

    describe('handlePermissionDeleted', function (): void {
        it('logs permission deleted event when permission property exists', function (): void {
            $permission = Permission::create(['name' => 'posts.delete', 'guard_name' => 'web']);

            $event = new stdClass;
            $event->permission = $permission;

            $this->auditLogger
                ->shouldReceive('log')
                ->once()
                ->with(
                    Mockery::on(fn ($eventType): bool => $eventType === AuditEventType::PermissionDeleted),
                    Mockery::on(fn ($subject): bool => $subject->id === $permission->id)
                );

            $this->subscriber->handlePermissionDeleted($event);
        });

        it('does nothing when permission property missing', function (): void {
            $event = new stdClass;

            $this->auditLogger->shouldNotReceive('log');

            $this->subscriber->handlePermissionDeleted($event);
        });
    });
});
