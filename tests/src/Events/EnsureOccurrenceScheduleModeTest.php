<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Actions\EnsureOccurrenceAction;
use AIArmada\Events\Enums\OccurrenceStatus;

it('requires starts_at when schedule_mode is manual and no resolver is bound', function (): void {
    expect(fn () => OwnerContext::withOwner(null, static function (): void {
        app(EnsureOccurrenceAction::class)->handle(
            series: [
                'name' => 'Manual Series',
                'slug' => 'manual-series',
            ],
            event: [
                'name' => 'Manual Event',
                'slug' => 'manual-event',
            ],
            occurrence: [
                'schedule_mode' => 'manual',
                'timezone' => 'UTC',
                'status' => OccurrenceStatus::Scheduled,
            ],
        );
    }))->toThrow(InvalidArgumentException::class, '[starts_at]');
});

it('refuses a non-manual schedule mode with a clear message when no resolver is bound', function (): void {
    expect(fn () => OwnerContext::withOwner(null, static function (): void {
        app(EnsureOccurrenceAction::class)->handle(
            series: [
                'name' => 'Prayer Relative Series',
                'slug' => 'prayer-relative-series',
            ],
            event: [
                'name' => 'Prayer Relative Event',
                'slug' => 'prayer-relative-event',
            ],
            occurrence: [
                'schedule_mode' => 'prayer_relative',
                'schedule_reference_key' => 'fajr_after_15',
                'schedule_reference_payload' => [
                    'date' => '2026-08-21',
                    'coordinates' => ['lat' => 3.139, 'lng' => 101.6869],
                ],
                'timezone' => 'UTC',
                'status' => OccurrenceStatus::Scheduled,
            ],
        );
    }))->toThrow(InvalidArgumentException::class, 'schedule resolver');
});
