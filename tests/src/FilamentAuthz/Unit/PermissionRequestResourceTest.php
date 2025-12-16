<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Models\PermissionRequest;
use AIArmada\FilamentAuthz\Resources\PermissionRequestResource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function (): void {
    \Mockery::close();
});

describe('PermissionRequestResource', function (): void {
    it('allows access when approvals are enabled', function (): void {
        config()->set('filament-authz.enterprise.approvals.enabled', true);

        expect(PermissionRequestResource::canAccess())->toBeTrue();
    });

    it('denies access when approvals are disabled', function (): void {
        config()->set('filament-authz.enterprise.approvals.enabled', false);

        expect(PermissionRequestResource::canAccess())->toBeFalse();
    });

    it('builds form schema', function (): void {
        $form = \Mockery::mock(Schema::class);
        $form->shouldReceive('schema')->once()->andReturnSelf();

        $result = PermissionRequestResource::form($form);

        expect($result)->toBe($form);
    });

    it('builds table with columns, filters, actions, bulk actions, and sort', function (): void {
        $table = \Mockery::mock(Table::class);
        $table->shouldReceive('columns')->once()->andReturnSelf();
        $table->shouldReceive('filters')->once()->andReturnSelf();
        $table->shouldReceive('actions')->once()->andReturnSelf();
        $table->shouldReceive('bulkActions')->once()->andReturnSelf();
        $table->shouldReceive('defaultSort')->once()->andReturnSelf();

        $result = PermissionRequestResource::table($table);

        expect($result)->toBe($table);
    });

    it('returns navigation badge color', function (): void {
        expect(PermissionRequestResource::getNavigationBadgeColor())->toBe('warning');
    });

    it('returns null navigation badge when no pending requests exist', function (): void {
        PermissionRequest::query()->delete();

        expect(PermissionRequestResource::getNavigationBadge())->toBeNull();
    });

    it('returns navigation badge count for pending requests', function (): void {
        $user = User::create([
            'name' => 'Requester',
            'email' => 'requester@example.com',
            'password' => bcrypt('password'),
        ]);

        PermissionRequest::create([
            'requester_id' => (string) $user->id,
            'requested_permissions' => ['orders.view'],
            'requested_roles' => ['Manager'],
            'justification' => 'Need access',
            'status' => PermissionRequest::STATUS_PENDING,
        ]);

        PermissionRequest::create([
            'requester_id' => (string) $user->id,
            'requested_permissions' => ['orders.update'],
            'requested_roles' => [],
            'justification' => 'Need access',
            'status' => PermissionRequest::STATUS_APPROVED,
        ]);

        expect(PermissionRequestResource::getNavigationBadge())->toBe('1');
    });
});
