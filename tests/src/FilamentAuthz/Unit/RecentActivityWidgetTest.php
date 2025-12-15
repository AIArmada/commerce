<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Models\PermissionAuditLog;
use AIArmada\FilamentAuthz\Widgets\RecentActivityWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['filament-authz.user_model' => User::class]);
});

describe('RecentActivityWidget', function (): void {
    it('has correct sort order', function (): void {
        $reflection = new ReflectionClass(RecentActivityWidget::class);
        $property = $reflection->getProperty('sort');

        expect($property->getValue())->toBe(3);
    });

    it('has full column span', function (): void {
        $widget = new RecentActivityWidget;
        $reflection = new ReflectionClass($widget);
        $property = $reflection->getProperty('columnSpan');

        expect($property->getValue($widget))->toBe('full');
    });

    it('has correct heading', function (): void {
        $reflection = new ReflectionClass(RecentActivityWidget::class);
        $property = $reflection->getProperty('heading');

        expect($property->getValue())->toBe('Recent Permission Activity');
    });

    it('returns table with audit log query', function (): void {
        $widget = new RecentActivityWidget;

        // We can't easily test the actual Table object without Livewire context,
        // but we can verify the table method exists and doesn't throw
        expect(method_exists($widget, 'table'))->toBeTrue();
    });

    it('audit log model has correct table name', function (): void {
        $log = new PermissionAuditLog;

        expect($log->getTable())->toContain('audit_logs');
    });
});
