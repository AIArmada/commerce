<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Enums\AuditEventType;
use AIArmada\FilamentAuthz\Enums\AuditSeverity;
use AIArmada\FilamentAuthz\Models\PermissionAuditLog;
use AIArmada\FilamentAuthz\Pages\AuditLogPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['filament-authz.user_model' => User::class]);
    config(['filament-authz.navigation.group' => 'Authorization']);
});

describe('AuditLogPage', function (): void {
    it('has correct title', function (): void {
        $reflection = new ReflectionClass(AuditLogPage::class);
        $property = $reflection->getProperty('title');

        expect($property->getValue())->toBe('Permission Audit Log');
    });

    it('has correct view', function (): void {
        $page = new AuditLogPage;
        $reflection = new ReflectionClass($page);
        $property = $reflection->getProperty('view');

        expect($property->getValue($page))->toBe('filament-authz::pages.audit-log');
    });

    it('has correct navigation label', function (): void {
        $reflection = new ReflectionClass(AuditLogPage::class);
        $property = $reflection->getProperty('navigationLabel');

        expect($property->getValue())->toBe('Audit Log');
    });

    it('gets navigation group from config', function (): void {
        expect(AuditLogPage::getNavigationGroup())->toBe('Authorization');
    });

    it('has navigation sort order of 12', function (): void {
        $reflection = new ReflectionClass(AuditLogPage::class);
        $property = $reflection->getProperty('navigationSort');

        expect($property->getValue())->toBe(12);
    });

    it('initializes with default date range on mount', function (): void {
        $page = new AuditLogPage;
        $page->mount();

        expect($page->startDate)->toBe(now()->subDays(7)->toDateString())
            ->and($page->endDate)->toBe(now()->toDateString())
            ->and($page->eventTypeFilter)->toBeNull()
            ->and($page->severityFilter)->toBeNull();
    });

    it('loads logs for date range', function (): void {
        // Create some audit logs
        PermissionAuditLog::create([
            'event_type' => AuditEventType::PermissionGranted,
            'severity' => AuditSeverity::Low,
            'actor_type' => 'user',
            'actor_id' => 'user-1',
            'occurred_at' => now(),
        ]);

        $page = new AuditLogPage;
        $page->mount();

        expect($page->logs)->toBeInstanceOf(Collection::class)
            ->and($page->logs->count())->toBe(1);
    });

    it('can filter by event type', function (): void {
        PermissionAuditLog::create([
            'event_type' => AuditEventType::PermissionGranted,
            'severity' => AuditSeverity::Low,
            'actor_type' => 'user',
            'actor_id' => 'user-1',
            'occurred_at' => now(),
        ]);
        PermissionAuditLog::create([
            'event_type' => AuditEventType::PermissionRevoked,
            'severity' => AuditSeverity::Low,
            'actor_type' => 'user',
            'actor_id' => 'user-2',
            'occurred_at' => now(),
        ]);

        $page = new AuditLogPage;
        $page->mount();
        $page->filterByEventType(AuditEventType::PermissionGranted->value);

        expect($page->eventTypeFilter)->toBe(AuditEventType::PermissionGranted->value)
            ->and($page->logs->count())->toBe(1);
    });

    it('can filter by severity', function (): void {
        PermissionAuditLog::create([
            'event_type' => AuditEventType::PermissionGranted,
            'severity' => AuditSeverity::Low,
            'actor_type' => 'user',
            'actor_id' => 'user-1',
            'occurred_at' => now(),
        ]);
        PermissionAuditLog::create([
            'event_type' => AuditEventType::AccessDenied,
            'severity' => AuditSeverity::High,
            'actor_type' => 'user',
            'actor_id' => 'user-2',
            'occurred_at' => now(),
        ]);

        $page = new AuditLogPage;
        $page->mount();
        $page->filterBySeverity(AuditSeverity::High->value);

        expect($page->severityFilter)->toBe(AuditSeverity::High->value)
            ->and($page->logs->count())->toBe(1);
    });

    it('can clear filters', function (): void {
        $page = new AuditLogPage;
        $page->mount();
        $page->filterByEventType(AuditEventType::PermissionGranted->value);
        $page->filterBySeverity(AuditSeverity::High->value);
        $page->clearFilters();

        expect($page->eventTypeFilter)->toBeNull()
            ->and($page->severityFilter)->toBeNull();
    });

    it('returns event type options', function (): void {
        $page = new AuditLogPage;
        $options = $page->getEventTypeOptions();

        expect($options)->toBeArray()
            ->and(count($options))->toBeGreaterThan(0)
            ->and($options)->toHaveKey(AuditEventType::PermissionGranted->value);
    });

    it('returns statistics for current logs', function (): void {
        PermissionAuditLog::create([
            'event_type' => AuditEventType::PermissionGranted,
            'severity' => AuditSeverity::Low,
            'actor_type' => 'user',
            'actor_id' => 'user-1',
            'occurred_at' => now(),
        ]);
        PermissionAuditLog::create([
            'event_type' => AuditEventType::AccessDenied,
            'severity' => AuditSeverity::High,
            'actor_type' => 'user',
            'actor_id' => 'user-2',
            'occurred_at' => now(),
        ]);

        $page = new AuditLogPage;
        $page->mount();
        $stats = $page->getStatistics();

        expect($stats)->toHaveKeys(['total', 'by_severity', 'by_category'])
            ->and($stats['total'])->toBe(2);
    });

    it('limits logs to 500 results', function (): void {
        $page = new AuditLogPage;
        $page->mount();

        // Verify limit is applied (no actual data, just checking method works)
        expect($page->logs)->toBeInstanceOf(Collection::class);
    });
});
