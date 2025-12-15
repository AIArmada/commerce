<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Models\PermissionRequest;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['filament-authz.database.tables.permission_requests' => 'authz_permission_requests']);
    config(['filament-authz.user_model' => User::class]);
});

describe('PermissionRequest', function (): void {
    describe('constants', function (): void {
        it('has pending status constant', function (): void {
            expect(PermissionRequest::STATUS_PENDING)->toBe('pending');
        });

        it('has approved status constant', function (): void {
            expect(PermissionRequest::STATUS_APPROVED)->toBe('approved');
        });

        it('has denied status constant', function (): void {
            expect(PermissionRequest::STATUS_DENIED)->toBe('denied');
        });

        it('has expired status constant', function (): void {
            expect(PermissionRequest::STATUS_EXPIRED)->toBe('expired');
        });
    });

    describe('create', function (): void {
        it('creates a permission request with basic attributes', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password',
            ]);

            $request = PermissionRequest::create([
                'requester_id' => $user->id,
                'status' => PermissionRequest::STATUS_PENDING,
            ]);

            expect($request)
                ->requester_id->toBe($user->id)
                ->status->toBe('pending');
        });

        it('creates a permission request with requested permissions', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password',
            ]);

            $request = PermissionRequest::create([
                'requester_id' => $user->id,
                'requested_permissions' => ['posts.view', 'posts.edit'],
                'status' => PermissionRequest::STATUS_PENDING,
            ]);

            expect($request->requested_permissions)->toBe(['posts.view', 'posts.edit']);
        });

        it('creates a permission request with requested roles', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password',
            ]);

            $request = PermissionRequest::create([
                'requester_id' => $user->id,
                'requested_roles' => ['editor', 'moderator'],
                'status' => PermissionRequest::STATUS_PENDING,
            ]);

            expect($request->requested_roles)->toBe(['editor', 'moderator']);
        });

        it('creates a permission request with justification', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password',
            ]);

            $request = PermissionRequest::create([
                'requester_id' => $user->id,
                'justification' => 'I need access to edit posts for my new role',
                'status' => PermissionRequest::STATUS_PENDING,
            ]);

            expect($request->justification)->toBe('I need access to edit posts for my new role');
        });

        it('creates a permission request with expiration', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password',
            ]);

            $request = PermissionRequest::create([
                'requester_id' => $user->id,
                'status' => PermissionRequest::STATUS_PENDING,
                'expires_at' => now()->addDays(30),
            ]);

            expect($request->expires_at)->not->toBeNull();
        });
    });

    describe('getTable', function (): void {
        it('returns table name from config', function (): void {
            $request = new PermissionRequest;

            expect($request->getTable())->toBe('authz_permission_requests');
        });

        it('returns custom table name from config', function (): void {
            config(['filament-authz.database.tables.permission_requests' => 'custom_permission_requests']);
            $request = new PermissionRequest;

            expect($request->getTable())->toBe('custom_permission_requests');
        });
    });

    describe('requester relationship', function (): void {
        it('belongs to a requester user', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password',
            ]);

            $request = PermissionRequest::create([
                'requester_id' => $user->id,
                'status' => PermissionRequest::STATUS_PENDING,
            ]);

            expect($request->requester)->toBeInstanceOf(User::class)
                ->and($request->requester->id)->toBe($user->id);
        });
    });

    describe('approver relationship', function (): void {
        it('belongs to an approver user when approved', function (): void {
            $requester = User::create([
                'name' => 'Requester',
                'email' => 'requester@example.com',
                'password' => 'password',
            ]);

            $approver = User::create([
                'name' => 'Approver',
                'email' => 'approver@example.com',
                'password' => 'password',
            ]);

            $request = PermissionRequest::create([
                'requester_id' => $requester->id,
                'approver_id' => $approver->id,
                'status' => PermissionRequest::STATUS_APPROVED,
            ]);

            expect($request->approver)->toBeInstanceOf(User::class)
                ->and($request->approver->id)->toBe($approver->id);
        });

        it('returns null when no approver', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password',
            ]);

            $request = PermissionRequest::create([
                'requester_id' => $user->id,
                'status' => PermissionRequest::STATUS_PENDING,
            ]);

            expect($request->approver)->toBeNull();
        });
    });

    describe('isPending', function (): void {
        it('returns true when status is pending', function (): void {
            $request = new PermissionRequest(['status' => PermissionRequest::STATUS_PENDING]);

            expect($request->isPending())->toBeTrue();
        });

        it('returns false when status is not pending', function (): void {
            $request = new PermissionRequest(['status' => PermissionRequest::STATUS_APPROVED]);

            expect($request->isPending())->toBeFalse();
        });
    });

    describe('isApproved', function (): void {
        it('returns true when status is approved', function (): void {
            $request = new PermissionRequest(['status' => PermissionRequest::STATUS_APPROVED]);

            expect($request->isApproved())->toBeTrue();
        });

        it('returns false when status is not approved', function (): void {
            $request = new PermissionRequest(['status' => PermissionRequest::STATUS_PENDING]);

            expect($request->isApproved())->toBeFalse();
        });
    });

    describe('isDenied', function (): void {
        it('returns true when status is denied', function (): void {
            $request = new PermissionRequest(['status' => PermissionRequest::STATUS_DENIED]);

            expect($request->isDenied())->toBeTrue();
        });

        it('returns false when status is not denied', function (): void {
            $request = new PermissionRequest(['status' => PermissionRequest::STATUS_PENDING]);

            expect($request->isDenied())->toBeFalse();
        });
    });

    describe('isExpired', function (): void {
        it('returns true when status is expired', function (): void {
            $request = new PermissionRequest(['status' => PermissionRequest::STATUS_EXPIRED]);

            expect($request->isExpired())->toBeTrue();
        });

        it('returns true when expires_at is in the past', function (): void {
            $request = new PermissionRequest([
                'status' => PermissionRequest::STATUS_PENDING,
                'expires_at' => now()->subDay(),
            ]);

            expect($request->isExpired())->toBeTrue();
        });

        it('returns false when status is not expired and no expiration', function (): void {
            $request = new PermissionRequest(['status' => PermissionRequest::STATUS_PENDING]);

            expect($request->isExpired())->toBeFalse();
        });

        it('returns false when expires_at is in the future', function (): void {
            $request = new PermissionRequest([
                'status' => PermissionRequest::STATUS_PENDING,
                'expires_at' => now()->addDay(),
            ]);

            expect($request->isExpired())->toBeFalse();
        });
    });

    describe('approve', function (): void {
        it('updates status to approved', function (): void {
            $requester = User::create([
                'name' => 'Requester',
                'email' => 'requester@example.com',
                'password' => 'password',
            ]);

            $approver = User::create([
                'name' => 'Approver',
                'email' => 'approver@example.com',
                'password' => 'password',
            ]);

            $request = PermissionRequest::create([
                'requester_id' => $requester->id,
                'status' => PermissionRequest::STATUS_PENDING,
            ]);

            $request->approve($approver);

            expect($request->status)->toBe(PermissionRequest::STATUS_APPROVED)
                ->and($request->approver_id)->toBe($approver->id)
                ->and($request->approved_at)->not->toBeNull();
        });

        it('saves approver note', function (): void {
            $requester = User::create([
                'name' => 'Requester',
                'email' => 'requester@example.com',
                'password' => 'password',
            ]);

            $approver = User::create([
                'name' => 'Approver',
                'email' => 'approver@example.com',
                'password' => 'password',
            ]);

            $request = PermissionRequest::create([
                'requester_id' => $requester->id,
                'status' => PermissionRequest::STATUS_PENDING,
            ]);

            $request->approve($approver, 'Approved for 30 days trial');

            expect($request->approver_note)->toBe('Approved for 30 days trial');
        });

        it('grants requested permissions to requester', function (): void {
            Permission::create(['name' => 'posts.view', 'guard_name' => 'web']);
            Permission::create(['name' => 'posts.edit', 'guard_name' => 'web']);

            $requester = User::create([
                'name' => 'Requester',
                'email' => 'requester@example.com',
                'password' => 'password',
            ]);

            $approver = User::create([
                'name' => 'Approver',
                'email' => 'approver@example.com',
                'password' => 'password',
            ]);

            $request = PermissionRequest::create([
                'requester_id' => $requester->id,
                'requested_permissions' => ['posts.view', 'posts.edit'],
                'status' => PermissionRequest::STATUS_PENDING,
            ]);

            $request->approve($approver);

            $requester->refresh();

            expect($requester->hasPermissionTo('posts.view'))->toBeTrue()
                ->and($requester->hasPermissionTo('posts.edit'))->toBeTrue();
        });

        it('assigns requested roles to requester', function (): void {
            Role::create(['name' => 'editor', 'guard_name' => 'web']);

            $requester = User::create([
                'name' => 'Requester',
                'email' => 'requester@example.com',
                'password' => 'password',
            ]);

            $approver = User::create([
                'name' => 'Approver',
                'email' => 'approver@example.com',
                'password' => 'password',
            ]);

            $request = PermissionRequest::create([
                'requester_id' => $requester->id,
                'requested_roles' => ['editor'],
                'status' => PermissionRequest::STATUS_PENDING,
            ]);

            $request->approve($approver);

            $requester->refresh();

            expect($requester->hasRole('editor'))->toBeTrue();
        });
    });

    describe('deny', function (): void {
        it('updates status to denied', function (): void {
            $requester = User::create([
                'name' => 'Requester',
                'email' => 'requester@example.com',
                'password' => 'password',
            ]);

            $approver = User::create([
                'name' => 'Approver',
                'email' => 'approver@example.com',
                'password' => 'password',
            ]);

            $request = PermissionRequest::create([
                'requester_id' => $requester->id,
                'status' => PermissionRequest::STATUS_PENDING,
            ]);

            $request->deny($approver, 'Not authorized for this level of access');

            expect($request->status)->toBe(PermissionRequest::STATUS_DENIED)
                ->and($request->approver_id)->toBe($approver->id)
                ->and($request->denied_at)->not->toBeNull()
                ->and($request->denial_reason)->toBe('Not authorized for this level of access');
        });
    });

    describe('casts', function (): void {
        it('casts requested_permissions to array', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password',
            ]);

            $request = PermissionRequest::create([
                'requester_id' => $user->id,
                'requested_permissions' => ['perm1', 'perm2'],
                'status' => PermissionRequest::STATUS_PENDING,
            ]);

            $request->refresh();

            expect($request->requested_permissions)->toBeArray();
        });

        it('casts requested_roles to array', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password',
            ]);

            $request = PermissionRequest::create([
                'requester_id' => $user->id,
                'requested_roles' => ['role1', 'role2'],
                'status' => PermissionRequest::STATUS_PENDING,
            ]);

            $request->refresh();

            expect($request->requested_roles)->toBeArray();
        });

        it('casts approved_at to datetime', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password',
            ]);

            $request = PermissionRequest::create([
                'requester_id' => $user->id,
                'status' => PermissionRequest::STATUS_APPROVED,
                'approved_at' => '2024-01-15 10:30:00',
            ]);

            $request->refresh();

            expect($request->approved_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
        });

        it('casts denied_at to datetime', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password',
            ]);

            $request = PermissionRequest::create([
                'requester_id' => $user->id,
                'status' => PermissionRequest::STATUS_DENIED,
                'denied_at' => '2024-01-15 10:30:00',
            ]);

            $request->refresh();

            expect($request->denied_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
        });

        it('casts expires_at to datetime', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password',
            ]);

            $request = PermissionRequest::create([
                'requester_id' => $user->id,
                'status' => PermissionRequest::STATUS_PENDING,
                'expires_at' => '2024-12-31 23:59:59',
            ]);

            $request->refresh();

            expect($request->expires_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
        });
    });
});
