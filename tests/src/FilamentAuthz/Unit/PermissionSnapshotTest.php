<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\FilamentAuthz\Unit;

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Models\PermissionSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['filament-authz.user_model' => User::class]);
});

describe('PermissionSnapshot', function (): void {
    describe('getTable', function (): void {
        it('returns table name from config', function (): void {
            $model = new PermissionSnapshot;

            expect($model->getTable())->toBe('authz_permission_snapshots');
        });
    });

    describe('creator relationship', function (): void {
        it('belongs to a user', function (): void {
            $user = User::create([
                'name' => 'Admin',
                'email' => 'admin@example.com',
                'password' => 'password',
            ]);

            $snapshot = PermissionSnapshot::create([
                'name' => 'Initial Snapshot',
                'description' => 'Initial permissions state',
                'created_by' => $user->id,
                'state' => ['roles' => [], 'permissions' => []],
                'hash' => md5('test'),
            ]);

            expect($snapshot->creator)->toBeInstanceOf(User::class)
                ->and($snapshot->creator->id)->toBe($user->id);
        });

        it('returns null when no creator', function (): void {
            $snapshot = PermissionSnapshot::create([
                'name' => 'System Snapshot',
                'created_by' => null,
                'state' => [],
                'hash' => md5('test'),
            ]);

            expect($snapshot->creator)->toBeNull();
        });
    });

    describe('getRoles', function (): void {
        it('returns roles from state', function (): void {
            $roles = [
                ['name' => 'admin', 'permissions' => ['users.view', 'users.create']],
                ['name' => 'editor', 'permissions' => ['posts.view', 'posts.edit']],
            ];

            $snapshot = PermissionSnapshot::create([
                'name' => 'Test Snapshot',
                'state' => ['roles' => $roles, 'permissions' => []],
                'hash' => md5('test'),
            ]);

            expect($snapshot->getRoles())->toBe($roles);
        });

        it('returns empty array when no roles', function (): void {
            $snapshot = PermissionSnapshot::create([
                'name' => 'Empty Snapshot',
                'state' => ['permissions' => []],
                'hash' => md5('test'),
            ]);

            expect($snapshot->getRoles())->toBe([]);
        });
    });

    describe('getPermissions', function (): void {
        it('returns permissions from state', function (): void {
            $permissions = [
                ['name' => 'users.view', 'guard_name' => 'web'],
                ['name' => 'users.create', 'guard_name' => 'web'],
            ];

            $snapshot = PermissionSnapshot::create([
                'name' => 'Test Snapshot',
                'state' => ['roles' => [], 'permissions' => $permissions],
                'hash' => md5('test'),
            ]);

            expect($snapshot->getPermissions())->toBe($permissions);
        });

        it('returns empty array when no permissions', function (): void {
            $snapshot = PermissionSnapshot::create([
                'name' => 'Empty Snapshot',
                'state' => ['roles' => []],
                'hash' => md5('test'),
            ]);

            expect($snapshot->getPermissions())->toBe([]);
        });
    });

    describe('getAssignments', function (): void {
        it('returns assignments from state', function (): void {
            $assignments = [
                ['user_id' => 'user-1', 'role' => 'admin'],
                ['user_id' => 'user-2', 'role' => 'editor'],
            ];

            $snapshot = PermissionSnapshot::create([
                'name' => 'Test Snapshot',
                'state' => ['roles' => [], 'permissions' => [], 'assignments' => $assignments],
                'hash' => md5('test'),
            ]);

            expect($snapshot->getAssignments())->toBe($assignments);
        });

        it('returns empty array when no assignments', function (): void {
            $snapshot = PermissionSnapshot::create([
                'name' => 'Empty Snapshot',
                'state' => ['roles' => []],
                'hash' => md5('test'),
            ]);

            expect($snapshot->getAssignments())->toBe([]);
        });
    });

    describe('casts', function (): void {
        it('casts state to array', function (): void {
            $snapshot = PermissionSnapshot::create([
                'name' => 'Test Snapshot',
                'state' => ['key' => 'value'],
                'hash' => md5('test'),
            ]);

            $snapshot->refresh();

            expect($snapshot->state)->toBeArray()
                ->and($snapshot->state['key'])->toBe('value');
        });

        it('casts created_at to datetime', function (): void {
            $snapshot = PermissionSnapshot::create([
                'name' => 'Test Snapshot',
                'state' => [],
                'hash' => md5('test'),
            ]);

            expect($snapshot->created_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
        });
    });

    describe('fillable attributes', function (): void {
        it('can mass assign allowed attributes', function (): void {
            $snapshot = PermissionSnapshot::create([
                'name' => 'Full Snapshot',
                'description' => 'A complete snapshot',
                'state' => ['roles' => [['name' => 'admin']]],
                'hash' => 'abc123',
            ]);

            expect($snapshot->name)->toBe('Full Snapshot')
                ->and($snapshot->description)->toBe('A complete snapshot')
                ->and($snapshot->hash)->toBe('abc123');
        });
    });
});
