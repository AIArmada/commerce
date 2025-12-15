<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Enums\AuditEventType;
use AIArmada\FilamentAuthz\Enums\AuditSeverity;
use AIArmada\FilamentAuthz\Jobs\WriteAuditLogJob;
use AIArmada\FilamentAuthz\Models\PermissionAuditLog;
use AIArmada\FilamentAuthz\Services\AuditLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->auditLogger = app(AuditLogger::class);

    // Enable audit logging
    config(['filament-authz.audit.enabled' => true]);
    config(['filament-authz.audit.async' => false]);

    // Drop and recreate audit log table matching the actual migration schema
    Schema::dropIfExists('authz_audit_logs');
    Schema::create('authz_audit_logs', function ($table): void {
        $table->uuid('id')->primary();
        $table->string('event_type');
        $table->string('severity');
        $table->string('actor_type')->nullable();
        $table->uuid('actor_id')->nullable();
        $table->string('subject_type')->nullable();
        $table->uuid('subject_id')->nullable();
        $table->string('target_type')->nullable();
        $table->uuid('target_id')->nullable();
        $table->string('target_name')->nullable();
        $table->json('old_value')->nullable();
        $table->json('new_value')->nullable();
        $table->json('context')->nullable();
        $table->ipAddress('ip_address')->nullable();
        $table->text('user_agent')->nullable();
        $table->string('session_id')->nullable();
        $table->timestamp('occurred_at')->nullable();
        $table->timestamps();
    });

    $this->testUser = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);
});

afterEach(function (): void {
    Schema::dropIfExists('authz_audit_logs');
});

test('can be instantiated', function (): void {
    expect($this->auditLogger)->toBeInstanceOf(AuditLogger::class);
});

test('log creates audit log entry', function (): void {
    // Use setUser to avoid triggering login events from Cart package
    Auth::setUser($this->testUser);

    $this->auditLogger->log(
        eventType: AuditEventType::PermissionGranted,
        subject: $this->testUser,
        newValues: ['permission' => 'users.view']
    );

    $log = PermissionAuditLog::first();

    expect($log)->not->toBeNull()
        ->and($log->event_type)->toBe(AuditEventType::PermissionGranted->value)
        ->and($log->subject_type)->toBe($this->testUser->getMorphClass())
        ->and($log->subject_id)->toBe((string) $this->testUser->getKey())
        ->and($log->new_value)->toBe(['permission' => 'users.view']);
});

test('log does not create entry when audit disabled', function (): void {
    config(['filament-authz.audit.enabled' => false]);

    $this->auditLogger->log(
        eventType: AuditEventType::PermissionGranted,
        subject: $this->testUser
    );

    expect(PermissionAuditLog::count())->toBe(0);
});

test('log dispatches job when async enabled', function (): void {
    Queue::fake();
    config(['filament-authz.audit.async' => true]);

    $this->auditLogger->log(
        eventType: AuditEventType::PermissionGranted,
        subject: $this->testUser
    );

    Queue::assertPushed(WriteAuditLogJob::class);
    expect(PermissionAuditLog::count())->toBe(0);
});

test('log uses default severity from event type', function (): void {
    Auth::setUser($this->testUser);

    $this->auditLogger->log(
        eventType: AuditEventType::PermissionGranted,
        subject: $this->testUser
    );

    $log = PermissionAuditLog::first();

    expect($log->severity)->toBe(AuditEventType::PermissionGranted->defaultSeverity()->value);
});

test('log allows custom severity', function (): void {
    Auth::setUser($this->testUser);

    $this->auditLogger->log(
        eventType: AuditEventType::PermissionGranted,
        subject: $this->testUser,
        severity: AuditSeverity::Critical
    );

    $log = PermissionAuditLog::first();

    expect($log->severity)->toBe(AuditSeverity::Critical->value);
});

test('log enriches metadata with request info', function (): void {
    Auth::setUser($this->testUser);

    $this->auditLogger->log(
        eventType: AuditEventType::PermissionGranted,
        subject: $this->testUser,
        metadata: ['custom' => 'data']
    );

    $log = PermissionAuditLog::first();

    expect($log->context)
        ->toHaveKey('custom')
        ->toHaveKey('ip_address')
        ->toHaveKey('user_agent')
        ->toHaveKey('url')
        ->toHaveKey('method');
});

test('logPermissionGranted creates correct log entry', function (): void {
    Auth::setUser($this->testUser);

    $this->auditLogger->logPermissionGranted($this->testUser, 'users.view');

    $log = PermissionAuditLog::first();

    expect($log->event_type)->toBe(AuditEventType::PermissionGranted->value)
        ->and($log->new_value)->toBe(['permission' => 'users.view']);
});

test('logPermissionRevoked creates correct log entry', function (): void {
    Auth::setUser($this->testUser);

    $this->auditLogger->logPermissionRevoked($this->testUser, 'users.view');

    $log = PermissionAuditLog::first();

    expect($log->event_type)->toBe(AuditEventType::PermissionRevoked->value)
        ->and($log->old_value)->toBe(['permission' => 'users.view']);
});

test('logRoleAssigned creates correct log entry', function (): void {
    Auth::setUser($this->testUser);

    $this->auditLogger->logRoleAssigned($this->testUser, 'Admin');

    $log = PermissionAuditLog::first();

    expect($log->event_type)->toBe(AuditEventType::RoleAssigned->value)
        ->and($log->new_value)->toBe(['role' => 'Admin']);
});

test('logRoleRemoved creates correct log entry', function (): void {
    Auth::setUser($this->testUser);

    $this->auditLogger->logRoleRemoved($this->testUser, 'Admin');

    $log = PermissionAuditLog::first();

    expect($log->event_type)->toBe(AuditEventType::RoleRemoved->value)
        ->and($log->old_value)->toBe(['role' => 'Admin']);
});

test('logRoleCreated creates correct log entry', function (): void {
    Auth::setUser($this->testUser);
    $role = Role::create(['name' => 'NewRole', 'guard_name' => 'web']);

    $this->auditLogger->logRoleCreated($role);

    $log = PermissionAuditLog::first();

    expect($log->event_type)->toBe(AuditEventType::RoleCreated->value)
        ->and($log->new_value)->toBe(['name' => 'NewRole']);
});

test('logRoleDeleted creates correct log entry with high severity', function (): void {
    Auth::setUser($this->testUser);
    $role = Role::create(['name' => 'ToDelete', 'guard_name' => 'web']);

    $this->auditLogger->logRoleDeleted($role);

    $log = PermissionAuditLog::first();

    expect($log->event_type)->toBe(AuditEventType::RoleDeleted->value)
        ->and($log->old_value)->toBe(['name' => 'ToDelete'])
        ->and($log->severity)->toBe(AuditSeverity::High->value);
});

test('logAccessDenied creates correct log entry', function (): void {
    Auth::setUser($this->testUser);

    $this->auditLogger->logAccessDenied(
        $this->testUser,
        'users.delete',
        null,
        ['attempted_action' => 'delete user']
    );

    $log = PermissionAuditLog::first();

    expect($log->event_type)->toBe(AuditEventType::AccessDenied->value)
        ->and($log->context)->toHaveKey('permission')
        ->and($log->context['permission'])->toBe('users.delete');
});

test('logPolicyEvaluated creates correct log entry', function (): void {
    Auth::setUser($this->testUser);

    $this->auditLogger->logPolicyEvaluated('view', 'User', [
        'result' => 'allow',
        'matched_rules' => 3,
    ]);

    $log = PermissionAuditLog::first();

    expect($log->event_type)->toBe(AuditEventType::PolicyEvaluated->value)
        ->and($log->context)->toHaveKey('action')
        ->and($log->context['action'])->toBe('view')
        ->and($log->context['resource'])->toBe('User');
});

test('logSuspiciousActivity creates critical severity log', function (): void {
    Auth::setUser($this->testUser);

    $this->auditLogger->logSuspiciousActivity(
        $this->testUser,
        'Multiple failed login attempts',
        ['attempts' => 10]
    );

    $log = PermissionAuditLog::first();

    expect($log->event_type)->toBe(AuditEventType::SuspiciousActivity->value)
        ->and($log->severity)->toBe(AuditSeverity::Critical->value)
        ->and($log->context['activity'])->toBe('Multiple failed login attempts');
});

test('logPrivilegeEscalation creates critical severity log', function (): void {
    Auth::setUser($this->testUser);

    $this->auditLogger->logPrivilegeEscalation($this->testUser, ['super_admin', 'delete_users']);

    $log = PermissionAuditLog::first();

    expect($log->event_type)->toBe(AuditEventType::PrivilegeEscalation->value)
        ->and($log->severity)->toBe(AuditSeverity::Critical->value)
        ->and($log->new_value['privileges'])->toBe(['super_admin', 'delete_users']);
});

test('logBulkOperation creates correct log entry', function (): void {
    Auth::setUser($this->testUser);

    $this->auditLogger->logBulkOperation('delete_users', 50, ['reason' => 'cleanup']);

    $log = PermissionAuditLog::first();

    expect($log->event_type)->toBe(AuditEventType::BulkOperation->value)
        ->and($log->context['operation'])->toBe('delete_users')
        ->and($log->context['affected_count'])->toBe(50);
});

test('logBulkOperation uses high severity for large operations', function (): void {
    Auth::setUser($this->testUser);

    $this->auditLogger->logBulkOperation('delete_users', 150);

    $log = PermissionAuditLog::first();

    expect($log->severity)->toBe(AuditSeverity::High->value);
});

test('logBulkOperation uses medium severity for small operations', function (): void {
    Auth::setUser($this->testUser);

    $this->auditLogger->logBulkOperation('delete_users', 50);

    $log = PermissionAuditLog::first();

    expect($log->severity)->toBe(AuditSeverity::Medium->value);
});

test('log records actor information from authenticated user', function (): void {
    Auth::setUser($this->testUser);

    $this->auditLogger->log(
        eventType: AuditEventType::PermissionGranted,
        subject: $this->testUser
    );

    $log = PermissionAuditLog::first();

    expect($log->actor_type)->toBe($this->testUser->getMorphClass())
        ->and($log->actor_id)->toBe((string) $this->testUser->getKey());
});

test('log handles null actor when not authenticated', function (): void {
    // Ensure no user is authenticated
    Auth::forgetUser();

    $this->auditLogger->log(
        eventType: AuditEventType::PermissionGranted,
        subject: $this->testUser
    );

    $log = PermissionAuditLog::first();

    expect($log->actor_type)->toBeNull()
        ->and($log->actor_id)->toBeNull();
});

test('log handles target model', function (): void {
    Auth::setUser($this->testUser);
    $targetUser = User::create([
        'name' => 'Target User',
        'email' => 'target@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->auditLogger->log(
        eventType: AuditEventType::PermissionGranted,
        subject: $this->testUser,
        target: $targetUser
    );

    $log = PermissionAuditLog::first();

    expect($log->target_type)->toBe($targetUser->getMorphClass())
        ->and($log->target_id)->toBe((string) $targetUser->getKey());
});

test('log stores old and new values', function (): void {
    Auth::setUser($this->testUser);

    $this->auditLogger->log(
        eventType: AuditEventType::RoleAssigned,
        subject: $this->testUser,
        oldValues: ['role' => 'User'],
        newValues: ['role' => 'Admin']
    );

    $log = PermissionAuditLog::first();

    expect($log->old_value)->toBe(['role' => 'User'])
        ->and($log->new_value)->toBe(['role' => 'Admin']);
});
