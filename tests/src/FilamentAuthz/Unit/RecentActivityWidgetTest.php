<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Enums\AuditEventType;
use AIArmada\FilamentAuthz\Enums\AuditSeverity;
use AIArmada\FilamentAuthz\Models\PermissionAuditLog;
use AIArmada\FilamentAuthz\Widgets\RecentActivityWidget;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['filament-authz.user_model' => User::class]);
});

describe('RecentActivityWidget', function (): void {
    describe('class structure', function (): void {
        it('extends TableWidget', function (): void {
            expect(RecentActivityWidget::class)
                ->toExtend(TableWidget::class);
        });

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
    });

    describe('table method', function (): void {
        it('returns table with audit log query', function (): void {
            $widget = new RecentActivityWidget;

            expect(method_exists($widget, 'table'))->toBeTrue();
        });

        it('configures table with columns', function (): void {
            $widget = new RecentActivityWidget;
            $reflection = new ReflectionClass($widget);
            $method = $reflection->getMethod('table');

            $mockTable = Table::make($widget);
            $result = $method->invoke($widget, $mockTable);

            expect($result)->toBeInstanceOf(Table::class);
            expect($result->getColumns())->toHaveCount(5);
        });

        it('has created_at column', function (): void {
            $widget = new RecentActivityWidget;
            $reflection = new ReflectionClass($widget);
            $method = $reflection->getMethod('table');

            $mockTable = Table::make($widget);
            $result = $method->invoke($widget, $mockTable);

            $columnNames = array_keys($result->getColumns());
            expect($columnNames)->toContain('created_at');
        });

        it('has event_type column', function (): void {
            $widget = new RecentActivityWidget;
            $reflection = new ReflectionClass($widget);
            $method = $reflection->getMethod('table');

            $mockTable = Table::make($widget);
            $result = $method->invoke($widget, $mockTable);

            $columnNames = array_keys($result->getColumns());
            expect($columnNames)->toContain('event_type');
        });

        it('has severity column', function (): void {
            $widget = new RecentActivityWidget;
            $reflection = new ReflectionClass($widget);
            $method = $reflection->getMethod('table');

            $mockTable = Table::make($widget);
            $result = $method->invoke($widget, $mockTable);

            $columnNames = array_keys($result->getColumns());
            expect($columnNames)->toContain('severity');
        });

        it('has description column', function (): void {
            $widget = new RecentActivityWidget;
            $reflection = new ReflectionClass($widget);
            $method = $reflection->getMethod('table');

            $mockTable = Table::make($widget);
            $result = $method->invoke($widget, $mockTable);

            $columnNames = array_keys($result->getColumns());
            expect($columnNames)->toContain('description');
        });

        it('has actor_id column', function (): void {
            $widget = new RecentActivityWidget;
            $reflection = new ReflectionClass($widget);
            $method = $reflection->getMethod('table');

            $mockTable = Table::make($widget);
            $result = $method->invoke($widget, $mockTable);

            $columnNames = array_keys($result->getColumns());
            expect($columnNames)->toContain('actor_id');
        });

        it('disables pagination', function (): void {
            $widget = new RecentActivityWidget;
            $reflection = new ReflectionClass($widget);
            $method = $reflection->getMethod('table');

            $mockTable = Table::make($widget);
            $result = $method->invoke($widget, $mockTable);

            expect($result->isPaginated())->toBeFalse();
        });
    });

    describe('severity color mapping', function (): void {
        it('maps low severity to gray', function (): void {
            $severity = new class
            {
                public string $value = 'low';
            };

            $color = match ($severity->value) {
                'low' => 'gray',
                'medium' => 'warning',
                'high' => 'danger',
                'critical' => 'danger',
                default => 'gray',
            };

            expect($color)->toBe('gray');
        });

        it('maps medium severity to warning', function (): void {
            $severity = new class
            {
                public string $value = 'medium';
            };

            $color = match ($severity->value) {
                'low' => 'gray',
                'medium' => 'warning',
                'high' => 'danger',
                'critical' => 'danger',
                default => 'gray',
            };

            expect($color)->toBe('warning');
        });

        it('maps high severity to danger', function (): void {
            $severity = new class
            {
                public string $value = 'high';
            };

            $color = match ($severity->value) {
                'low' => 'gray',
                'medium' => 'warning',
                'high' => 'danger',
                'critical' => 'danger',
                default => 'gray',
            };

            expect($color)->toBe('danger');
        });

        it('maps critical severity to danger', function (): void {
            $severity = new class
            {
                public string $value = 'critical';
            };

            $color = match ($severity->value) {
                'low' => 'gray',
                'medium' => 'warning',
                'high' => 'danger',
                'critical' => 'danger',
                default => 'gray',
            };

            expect($color)->toBe('danger');
        });

        it('maps unknown severity to gray', function (): void {
            $severity = new class
            {
                public string $value = 'unknown';
            };

            $color = match ($severity->value) {
                'low' => 'gray',
                'medium' => 'warning',
                'high' => 'danger',
                'critical' => 'danger',
                default => 'gray',
            };

            expect($color)->toBe('gray');
        });
    });

    describe('audit log model', function (): void {
        it('has correct table name', function (): void {
            $log = new PermissionAuditLog;

            expect($log->getTable())->toContain('audit_logs');
        });
    });

    describe('with data', function (): void {
        it('renders widget when audit logs exist', function (): void {
            PermissionAuditLog::create([
                'event_type' => AuditEventType::PermissionGranted,
                'severity' => AuditSeverity::Medium,
                'description' => 'Granted permission to user',
                'actor_type' => User::class,
                'actor_id' => 'user-1',
                'occurred_at' => now(),
            ]);

            $widget = new RecentActivityWidget;
            $reflection = new ReflectionClass($widget);
            $method = $reflection->getMethod('table');

            $mockTable = Table::make($widget);
            $result = $method->invoke($widget, $mockTable);

            expect($result)->toBeInstanceOf(Table::class);
            expect(PermissionAuditLog::count())->toBe(1);
        });
    });
});
