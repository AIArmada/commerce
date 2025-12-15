<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\FilamentAuthz\Unit;

use AIArmada\FilamentAuthz\Enums\AuditEventType;
use AIArmada\FilamentAuthz\Enums\AuditSeverity;
use AIArmada\FilamentAuthz\Jobs\WriteAuditLogJob;
use AIArmada\FilamentAuthz\Models\PermissionAuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

describe('WriteAuditLogJob', function (): void {
    describe('constructor', function (): void {
        it('sets queue from config', function (): void {
            config(['filament-authz.audit.queue' => 'audit-logs']);

            $data = ['event_type' => 'permission_granted'];
            $job = new WriteAuditLogJob($data);

            expect($job->queue)->toBe('audit-logs');
        });

        it('uses default queue when not configured', function (): void {
            config(['filament-authz.audit.queue' => null]);

            $data = ['event_type' => 'permission_granted'];
            $job = new WriteAuditLogJob($data);

            expect($job->data)->toBe($data);
        });
    });

    describe('handle', function (): void {
        it('creates permission audit log entry', function (): void {
            $data = [
                'event_type' => AuditEventType::PermissionGranted->value,
                'severity' => AuditSeverity::Low->value,
                'actor_type' => 'System',
                'actor_id' => 'system',
                'subject_type' => 'User',
                'subject_id' => 'user-123',
                'occurred_at' => now(),
            ];

            $job = new WriteAuditLogJob($data);
            $job->handle();

            expect(PermissionAuditLog::count())->toBe(1);

            $log = PermissionAuditLog::first();
            expect($log->event_type)->toBe(AuditEventType::PermissionGranted->value)
                ->and($log->severity)->toBe(AuditSeverity::Low->value)
                ->and($log->actor_type)->toBe('System')
                ->and($log->subject_type)->toBe('User')
                ->and($log->subject_id)->toBe('user-123');
        });
    });

    describe('backoff', function (): void {
        it('returns backoff intervals', function (): void {
            $job = new WriteAuditLogJob([]);

            expect($job->backoff())->toBe([1, 5, 10]);
        });
    });

    describe('tries', function (): void {
        it('returns number of tries', function (): void {
            $job = new WriteAuditLogJob([]);

            expect($job->tries())->toBe(3);
        });
    });

    describe('dispatch', function (): void {
        it('can be dispatched to queue', function (): void {
            Queue::fake();

            $data = [
                'event_type' => AuditEventType::PermissionGranted->value,
                'severity' => AuditSeverity::Low->value,
                'message' => 'Test message',
                'occurred_at' => now(),
            ];

            WriteAuditLogJob::dispatch($data);

            Queue::assertPushed(WriteAuditLogJob::class, function ($job) use ($data): bool {
                return $job->data === $data;
            });
        });
    });
});
