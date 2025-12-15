<?php

declare(strict_types=1);

use AIArmada\Chip\Enums\ChargeStatus;
use AIArmada\Chip\Enums\RecurringInterval;
use AIArmada\Chip\Enums\RecurringStatus;
use AIArmada\Chip\Events\RecurringChargeRetryScheduled;
use AIArmada\Chip\Events\RecurringChargeSucceeded;
use AIArmada\Chip\Events\RecurringScheduleCancelled;
use AIArmada\Chip\Events\RecurringScheduleCreated;
use AIArmada\Chip\Events\RecurringScheduleFailed;
use AIArmada\Chip\Models\RecurringCharge;
use AIArmada\Chip\Models\RecurringSchedule;

describe('RecurringScheduleCreated event', function (): void {
    it('can be constructed with a schedule', function (): void {
        $schedule = new RecurringSchedule;
        $schedule->forceFill([
            'id' => 'sched_123',
            'chip_client_id' => 'client_abc',
            'recurring_token_id' => 'token_xyz',
            'status' => RecurringStatus::Active,
            'amount_minor' => 10000,
            'currency' => 'MYR',
            'interval' => RecurringInterval::Monthly,
            'interval_count' => 1,
        ]);

        $event = new RecurringScheduleCreated($schedule);

        expect($event->schedule)->toBe($schedule)
            ->and($event->schedule->id)->toBe('sched_123');
    });
});

describe('RecurringScheduleCancelled event', function (): void {
    it('can be constructed with a schedule', function (): void {
        $schedule = new RecurringSchedule;
        $schedule->forceFill([
            'id' => 'sched_456',
            'chip_client_id' => 'client_def',
            'recurring_token_id' => 'token_uvw',
            'status' => RecurringStatus::Cancelled,
            'amount_minor' => 5000,
            'currency' => 'MYR',
            'interval' => RecurringInterval::Weekly,
            'interval_count' => 2,
            'cancelled_at' => now(),
        ]);

        $event = new RecurringScheduleCancelled($schedule);

        expect($event->schedule)->toBe($schedule)
            ->and($event->schedule->status)->toBe(RecurringStatus::Cancelled);
    });
});

describe('RecurringScheduleFailed event', function (): void {
    it('can be constructed with a schedule', function (): void {
        $schedule = new RecurringSchedule;
        $schedule->forceFill([
            'id' => 'sched_789',
            'chip_client_id' => 'client_ghi',
            'recurring_token_id' => 'token_rst',
            'status' => RecurringStatus::Failed,
            'amount_minor' => 15000,
            'currency' => 'MYR',
            'interval' => RecurringInterval::Yearly,
            'interval_count' => 1,
            'failure_count' => 3,
            'max_failures' => 3,
        ]);

        $event = new RecurringScheduleFailed($schedule);

        expect($event->schedule)->toBe($schedule)
            ->and($event->schedule->status)->toBe(RecurringStatus::Failed);
    });
});

describe('RecurringChargeRetryScheduled event', function (): void {
    it('can be constructed with schedule and retry delay', function (): void {
        $schedule = new RecurringSchedule;
        $schedule->forceFill([
            'id' => 'sched_retry',
            'chip_client_id' => 'client_retry',
            'recurring_token_id' => 'token_retry',
            'status' => RecurringStatus::Active,
            'amount_minor' => 8000,
            'currency' => 'MYR',
            'interval' => RecurringInterval::Daily,
            'interval_count' => 1,
            'failure_count' => 1,
            'max_failures' => 3,
        ]);

        $event = new RecurringChargeRetryScheduled($schedule, 24);

        expect($event->schedule)->toBe($schedule)
            ->and($event->retryDelayHours)->toBe(24);
    });
});

describe('RecurringChargeSucceeded event', function (): void {
    it('can be constructed with schedule and charge', function (): void {
        $schedule = new RecurringSchedule;
        $schedule->forceFill([
            'id' => 'sched_success',
            'chip_client_id' => 'client_success',
            'recurring_token_id' => 'token_success',
            'status' => RecurringStatus::Active,
            'amount_minor' => 12000,
            'currency' => 'MYR',
            'interval' => RecurringInterval::Monthly,
            'interval_count' => 1,
        ]);

        $charge = new RecurringCharge;
        $charge->forceFill([
            'id' => 'charge_123',
            'schedule_id' => 'sched_success',
            'chip_purchase_id' => 'purch_abc',
            'amount_minor' => 12000,
            'currency' => 'MYR',
            'status' => ChargeStatus::Success,
            'attempted_at' => now(),
        ]);

        $event = new RecurringChargeSucceeded($schedule, $charge);

        expect($event->schedule)->toBe($schedule)
            ->and($event->charge)->toBe($charge)
            ->and($event->charge->chip_purchase_id)->toBe('purch_abc');
    });
});
