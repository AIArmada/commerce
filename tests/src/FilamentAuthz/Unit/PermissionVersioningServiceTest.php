<?php

declare(strict_types=1);

namespace Tests\FilamentAuthz\Unit;

use AIArmada\FilamentAuthz\Models\PermissionSnapshot;
use AIArmada\FilamentAuthz\Services\AuditLogger;
use AIArmada\FilamentAuthz\Services\PermissionVersioningService;
use Illuminate\Database\Eloquent\Collection;
use Mockery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

afterEach(function (): void {
    Mockery::close();
});

describe('PermissionVersioningService', function (): void {
    describe('createSnapshot', function (): void {
        it('creates a snapshot with name and description', function (): void {
            $auditLogger = Mockery::mock(AuditLogger::class);
            $auditLogger->shouldReceive('log')->once();

            $service = new PermissionVersioningService($auditLogger);

            $snapshot = $service->createSnapshot('Test Snapshot', 'A test description');

            expect($snapshot)->toBeInstanceOf(PermissionSnapshot::class);
            expect($snapshot->name)->toBe('Test Snapshot');
            expect($snapshot->description)->toBe('A test description');
            expect($snapshot->state)->toBeArray();
            expect($snapshot->hash)->not->toBeEmpty();
        });

        it('creates a snapshot without description', function (): void {
            $auditLogger = Mockery::mock(AuditLogger::class);
            $auditLogger->shouldReceive('log')->once();

            $service = new PermissionVersioningService($auditLogger);

            $snapshot = $service->createSnapshot('Minimal Snapshot');

            expect($snapshot->name)->toBe('Minimal Snapshot');
            expect($snapshot->description)->toBeNull();
        });

        it('includes roles in snapshot state', function (): void {
            Role::create(['name' => 'test-role', 'guard_name' => 'web']);

            $auditLogger = Mockery::mock(AuditLogger::class);
            $auditLogger->shouldReceive('log')->once();

            $service = new PermissionVersioningService($auditLogger);
            $snapshot = $service->createSnapshot('With Roles');

            expect($snapshot->state)->toHaveKey('roles');
            expect($snapshot->state['roles'])->toBeArray();
        });

        it('includes permissions in snapshot state', function (): void {
            Permission::create(['name' => 'test-permission', 'guard_name' => 'web']);

            $auditLogger = Mockery::mock(AuditLogger::class);
            $auditLogger->shouldReceive('log')->once();

            $service = new PermissionVersioningService($auditLogger);
            $snapshot = $service->createSnapshot('With Permissions');

            expect($snapshot->state)->toHaveKey('permissions');
            expect($snapshot->state['permissions'])->toBeArray();
        });

        it('includes assignments in snapshot state', function (): void {
            $auditLogger = Mockery::mock(AuditLogger::class);
            $auditLogger->shouldReceive('log')->once();

            $service = new PermissionVersioningService($auditLogger);
            $snapshot = $service->createSnapshot('With Assignments');

            expect($snapshot->state)->toHaveKey('assignments');
        });
    });

    describe('compare', function (): void {
        it('compares two snapshots and returns differences', function (): void {
            $auditLogger = Mockery::mock(AuditLogger::class);
            $auditLogger->shouldReceive('log')->once();

            $service = new PermissionVersioningService($auditLogger);

            // Create first snapshot
            $snapshot1 = $service->createSnapshot('Snapshot 1');

            // Add a role
            Role::create(['name' => 'new-role', 'guard_name' => 'web']);

            // Create second snapshot
            $auditLogger->shouldReceive('log')->once();
            $snapshot2 = $service->createSnapshot('Snapshot 2');

            // Compare
            $diff = $service->compare($snapshot1, $snapshot2);

            expect($diff)->toHaveKeys(['roles', 'permissions', 'assignments_changed']);
            expect($diff['roles'])->toHaveKeys(['added', 'removed']);
            expect($diff['permissions'])->toHaveKeys(['added', 'removed']);
        });

        it('detects added roles', function (): void {
            $auditLogger = Mockery::mock(AuditLogger::class);
            $auditLogger->shouldReceive('log')->twice();

            $service = new PermissionVersioningService($auditLogger);

            $snapshot1 = $service->createSnapshot('Before');
            Role::create(['name' => 'added-role', 'guard_name' => 'web']);
            $snapshot2 = $service->createSnapshot('After');

            $diff = $service->compare($snapshot1, $snapshot2);

            expect($diff['roles']['added'])->toContain('added-role');
        });

        it('detects removed roles', function (): void {
            Role::create(['name' => 'removed-role', 'guard_name' => 'web']);

            $auditLogger = Mockery::mock(AuditLogger::class);
            $auditLogger->shouldReceive('log')->twice();

            $service = new PermissionVersioningService($auditLogger);

            $snapshot1 = $service->createSnapshot('Before');
            Role::findByName('removed-role', 'web')->delete();
            $snapshot2 = $service->createSnapshot('After');

            $diff = $service->compare($snapshot1, $snapshot2);

            expect($diff['roles']['removed'])->toContain('removed-role');
        });
    });

    describe('listSnapshots', function (): void {
        it('returns collection of snapshots', function (): void {
            $auditLogger = Mockery::mock(AuditLogger::class);
            $auditLogger->shouldReceive('log')->twice();

            $service = new PermissionVersioningService($auditLogger);

            $service->createSnapshot('Snapshot 1');
            $service->createSnapshot('Snapshot 2');

            $snapshots = $service->listSnapshots();

            expect($snapshots)->toBeInstanceOf(Collection::class);
            expect($snapshots->count())->toBeGreaterThanOrEqual(2);
        });

        it('orders snapshots by created_at desc', function (): void {
            $auditLogger = Mockery::mock(AuditLogger::class);
            $auditLogger->shouldReceive('log')->twice();

            $service = new PermissionVersioningService($auditLogger);

            $first = $service->createSnapshot('First');
            $second = $service->createSnapshot('Second');

            $snapshots = $service->listSnapshots();

            // Most recent should be first
            expect($snapshots->first()->name)->toBe('Second');
        });
    });

    describe('previewRollback', function (): void {
        it('returns preview without making changes', function (): void {
            $auditLogger = Mockery::mock(AuditLogger::class);
            $auditLogger->shouldReceive('log')->once();

            $service = new PermissionVersioningService($auditLogger);

            $snapshot = $service->createSnapshot('Original');
            Role::create(['name' => 'after-snapshot-role', 'guard_name' => 'web']);

            $preview = $service->previewRollback($snapshot);

            expect($preview)->toBeArray();
            expect($preview)->toHaveKey('roles');

            // Verify the role still exists (no actual rollback)
            expect(Role::where('name', 'after-snapshot-role')->exists())->toBeTrue();
        });
    });

    describe('deleteSnapshot', function (): void {
        it('deletes a snapshot', function (): void {
            $auditLogger = Mockery::mock(AuditLogger::class);
            $auditLogger->shouldReceive('log')->once(); // Only for createSnapshot

            $service = new PermissionVersioningService($auditLogger);

            $snapshot = $service->createSnapshot('To Delete');
            $id = $snapshot->id;

            $result = $service->deleteSnapshot($snapshot);

            expect($result)->toBeTrue();
            expect(PermissionSnapshot::find($id))->toBeNull();
        });

        it('returns true on successful deletion', function (): void {
            $auditLogger = Mockery::mock(AuditLogger::class);
            $auditLogger->shouldReceive('log')->once();

            $service = new PermissionVersioningService($auditLogger);

            $snapshot = $service->createSnapshot('Delete Test');
            $result = $service->deleteSnapshot($snapshot);

            expect($result)->toBeTrue();
        });
    });
});
