<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Enums\AuditEventType;
use AIArmada\FilamentAuthz\Enums\AuditSeverity;
use AIArmada\FilamentAuthz\Models\PermissionAuditLog;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::dropIfExists('authz_audit_logs');
    Schema::create('authz_audit_logs', function ($table): void {
        $table->uuid('id')->primary();
        $table->string('event_type');
        $table->string('severity')->default('info');
        $table->nullableMorphs('actor');
        $table->nullableMorphs('subject');
        $table->nullableMorphs('target');
        $table->string('target_name')->nullable();
        $table->nullableMorphs('owner');
        $table->json('old_value')->nullable();
        $table->json('new_value')->nullable();
        $table->json('context')->nullable();
        $table->string('ip_address')->nullable();
        $table->string('user_agent')->nullable();
        $table->string('session_id')->nullable();
        $table->timestamp('occurred_at');
        $table->timestamps();
    });
});

afterEach(function (): void {
    Schema::dropIfExists('authz_audit_logs');
});

test('can create permission audit log', function (): void {
    $log = PermissionAuditLog::create([
        'event_type' => AuditEventType::PermissionGranted->value,
        'severity' => AuditSeverity::Low->value,
        'actor_type' => User::class,
        'actor_id' => 'user-123',
        'occurred_at' => now(),
    ]);

    expect($log)->toBeInstanceOf(PermissionAuditLog::class)
        ->and($log->event_type)->toBe('permission.granted');
});

test('getEventTypeEnum returns correct enum', function (): void {
    $log = PermissionAuditLog::create([
        'event_type' => AuditEventType::PermissionGranted->value,
        'severity' => AuditSeverity::Low->value,
        'occurred_at' => now(),
    ]);

    expect($log->getEventTypeEnum())->toBe(AuditEventType::PermissionGranted);
});

test('getEventTypeEnum returns null for invalid type', function (): void {
    $log = PermissionAuditLog::create([
        'event_type' => 'invalid_type',
        'severity' => AuditSeverity::Low->value,
        'occurred_at' => now(),
    ]);

    expect($log->getEventTypeEnum())->toBeNull();
});

test('getSeverityEnum returns correct enum', function (): void {
    $log = PermissionAuditLog::create([
        'event_type' => AuditEventType::PermissionGranted->value,
        'severity' => AuditSeverity::High->value,
        'occurred_at' => now(),
    ]);

    expect($log->getSeverityEnum())->toBe(AuditSeverity::High);
});

test('getSeverityEnum defaults to Low for invalid severity', function (): void {
    $log = PermissionAuditLog::create([
        'event_type' => AuditEventType::PermissionGranted->value,
        'severity' => 'invalid',
        'occurred_at' => now(),
    ]);

    expect($log->getSeverityEnum())->toBe(AuditSeverity::Low);
});

test('getDescription returns event label', function (): void {
    $log = PermissionAuditLog::create([
        'event_type' => AuditEventType::PermissionGranted->value,
        'severity' => AuditSeverity::Low->value,
        'occurred_at' => now(),
    ]);

    expect($log->getDescription())->toBe('Permission Granted');
});

test('getDescription includes target name when present', function (): void {
    $log = PermissionAuditLog::create([
        'event_type' => AuditEventType::PermissionGranted->value,
        'severity' => AuditSeverity::Low->value,
        'target_name' => 'orders.view',
        'occurred_at' => now(),
    ]);

    expect($log->getDescription())->toBe('Permission Granted: orders.view');
});

test('getDescription returns raw event type for invalid enum', function (): void {
    $log = PermissionAuditLog::create([
        'event_type' => 'custom_event',
        'severity' => AuditSeverity::Low->value,
        'occurred_at' => now(),
    ]);

    expect($log->getDescription())->toBe('custom_event');
});

test('getChanges returns differences between old and new values', function (): void {
    $log = PermissionAuditLog::create([
        'event_type' => AuditEventType::PermissionUpdated->value,
        'severity' => AuditSeverity::Medium->value,
        'old_value' => ['name' => 'Old Name', 'active' => true],
        'new_value' => ['name' => 'New Name', 'active' => true],
        'occurred_at' => now(),
    ]);

    $changes = $log->getChanges();

    expect($changes)->toHaveKey('name')
        ->and($changes['name'])->toBe(['old' => 'Old Name', 'new' => 'New Name'])
        ->and($changes)->not->toHaveKey('active'); // No change
});

test('getChanges handles null values', function (): void {
    $log = PermissionAuditLog::create([
        'event_type' => AuditEventType::PermissionGranted->value,
        'severity' => AuditSeverity::Low->value,
        'old_value' => null,
        'new_value' => null,
        'occurred_at' => now(),
    ]);

    expect($log->getChanges())->toBe([]);
});

test('getChanges includes added and removed keys', function (): void {
    $log = PermissionAuditLog::create([
        'event_type' => AuditEventType::PermissionUpdated->value,
        'severity' => AuditSeverity::Medium->value,
        'old_value' => ['removed_key' => 'value'],
        'new_value' => ['added_key' => 'value'],
        'occurred_at' => now(),
    ]);

    $changes = $log->getChanges();

    expect($changes)->toHaveKey('removed_key')
        ->and($changes['removed_key'])->toBe(['old' => 'value', 'new' => null])
        ->and($changes)->toHaveKey('added_key')
        ->and($changes['added_key'])->toBe(['old' => null, 'new' => 'value']);
});

test('isHighSeverity returns true for high severity', function (): void {
    $log = PermissionAuditLog::create([
        'event_type' => AuditEventType::PermissionGranted->value,
        'severity' => AuditSeverity::High->value,
        'occurred_at' => now(),
    ]);

    expect($log->isHighSeverity())->toBeTrue();
});

test('isHighSeverity returns true for critical severity', function (): void {
    $log = PermissionAuditLog::create([
        'event_type' => AuditEventType::PermissionGranted->value,
        'severity' => AuditSeverity::Critical->value,
        'occurred_at' => now(),
    ]);

    expect($log->isHighSeverity())->toBeTrue();
});

test('isHighSeverity returns false for medium severity', function (): void {
    $log = PermissionAuditLog::create([
        'event_type' => AuditEventType::PermissionGranted->value,
        'severity' => AuditSeverity::Medium->value,
        'occurred_at' => now(),
    ]);

    expect($log->isHighSeverity())->toBeFalse();
});

test('isHighSeverity returns false for low severity', function (): void {
    $log = PermissionAuditLog::create([
        'event_type' => AuditEventType::PermissionGranted->value,
        'severity' => AuditSeverity::Low->value,
        'occurred_at' => now(),
    ]);

    expect($log->isHighSeverity())->toBeFalse();
});

test('scopeOfEventType filters by event type enum', function (): void {
    PermissionAuditLog::create([
        'event_type' => AuditEventType::PermissionGranted->value,
        'severity' => AuditSeverity::Low->value,
        'occurred_at' => now(),
    ]);

    PermissionAuditLog::create([
        'event_type' => AuditEventType::PermissionRevoked->value,
        'severity' => AuditSeverity::Low->value,
        'occurred_at' => now(),
    ]);

    $logs = PermissionAuditLog::ofEventType(AuditEventType::PermissionGranted)->get();

    expect($logs)->toHaveCount(1)
        ->and($logs->first()->event_type)->toBe('permission.granted');
});

test('scopeOfEventType filters by event type string', function (): void {
    PermissionAuditLog::create([
        'event_type' => AuditEventType::PermissionGranted->value,
        'severity' => AuditSeverity::Low->value,
        'occurred_at' => now(),
    ]);

    $logs = PermissionAuditLog::ofEventType('permission.granted')->get();

    expect($logs)->toHaveCount(1);
});

test('scopeOfSeverity filters by severity enum', function (): void {
    PermissionAuditLog::create([
        'event_type' => AuditEventType::PermissionGranted->value,
        'severity' => AuditSeverity::High->value,
        'occurred_at' => now(),
    ]);

    PermissionAuditLog::create([
        'event_type' => AuditEventType::PermissionGranted->value,
        'severity' => AuditSeverity::Low->value,
        'occurred_at' => now(),
    ]);

    $logs = PermissionAuditLog::ofSeverity(AuditSeverity::High)->get();

    expect($logs)->toHaveCount(1)
        ->and($logs->first()->severity)->toBe('high');
});

test('scopeMinimumSeverity filters by minimum severity', function (): void {
    PermissionAuditLog::create([
        'event_type' => AuditEventType::PermissionGranted->value,
        'severity' => AuditSeverity::Low->value,
        'occurred_at' => now(),
    ]);

    PermissionAuditLog::create([
        'event_type' => AuditEventType::PermissionGranted->value,
        'severity' => AuditSeverity::High->value,
        'occurred_at' => now(),
    ]);

    PermissionAuditLog::create([
        'event_type' => AuditEventType::PermissionGranted->value,
        'severity' => AuditSeverity::Critical->value,
        'occurred_at' => now(),
    ]);

    $logs = PermissionAuditLog::minimumSeverity(AuditSeverity::High)->get();

    expect($logs)->toHaveCount(2);
});

test('scopeByActor filters by actor', function (): void {
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    PermissionAuditLog::create([
        'event_type' => AuditEventType::PermissionGranted->value,
        'severity' => AuditSeverity::Low->value,
        'actor_type' => $user->getMorphClass(),
        'actor_id' => (string) $user->getKey(),
        'occurred_at' => now(),
    ]);

    PermissionAuditLog::create([
        'event_type' => AuditEventType::PermissionGranted->value,
        'severity' => AuditSeverity::Low->value,
        'actor_type' => User::class,
        'actor_id' => 'other-user',
        'occurred_at' => now(),
    ]);

    $logs = PermissionAuditLog::byActor($user)->get();

    expect($logs)->toHaveCount(1);
});

test('scopeBetweenDates filters by date range', function (): void {
    PermissionAuditLog::create([
        'event_type' => AuditEventType::PermissionGranted->value,
        'severity' => AuditSeverity::Low->value,
        'occurred_at' => now()->subDays(5),
    ]);

    PermissionAuditLog::create([
        'event_type' => AuditEventType::PermissionGranted->value,
        'severity' => AuditSeverity::Low->value,
        'occurred_at' => now()->subDay(),
    ]);

    PermissionAuditLog::create([
        'event_type' => AuditEventType::PermissionGranted->value,
        'severity' => AuditSeverity::Low->value,
        'occurred_at' => now()->addDays(5),
    ]);

    $logs = PermissionAuditLog::betweenDates(now()->subDays(3), now())->get();

    expect($logs)->toHaveCount(1);
});

test('scopeRecent filters by recent hours', function (): void {
    PermissionAuditLog::create([
        'event_type' => AuditEventType::PermissionGranted->value,
        'severity' => AuditSeverity::Low->value,
        'occurred_at' => now()->subHours(2),
    ]);

    PermissionAuditLog::create([
        'event_type' => AuditEventType::PermissionGranted->value,
        'severity' => AuditSeverity::Low->value,
        'occurred_at' => now()->subDays(2),
    ]);

    $logs = PermissionAuditLog::recent(24)->get();

    expect($logs)->toHaveCount(1);
});

test('scopeForSession filters by session id', function (): void {
    PermissionAuditLog::create([
        'event_type' => AuditEventType::PermissionGranted->value,
        'severity' => AuditSeverity::Low->value,
        'session_id' => 'session-123',
        'occurred_at' => now(),
    ]);

    PermissionAuditLog::create([
        'event_type' => AuditEventType::PermissionGranted->value,
        'severity' => AuditSeverity::Low->value,
        'session_id' => 'session-456',
        'occurred_at' => now(),
    ]);

    $logs = PermissionAuditLog::forSession('session-123')->get();

    expect($logs)->toHaveCount(1);
});

test('casts work correctly', function (): void {
    $log = PermissionAuditLog::create([
        'event_type' => AuditEventType::PermissionGranted->value,
        'severity' => AuditSeverity::Low->value,
        'old_value' => ['key' => 'value'],
        'new_value' => ['key' => 'new_value'],
        'context' => ['ip' => '127.0.0.1'],
        'occurred_at' => '2024-01-01 00:00:00',
    ]);

    expect($log->old_value)->toBeArray()
        ->and($log->new_value)->toBeArray()
        ->and($log->context)->toBeArray()
        ->and($log->occurred_at)->toBeInstanceOf(Illuminate\Support\Carbon::class);
});
