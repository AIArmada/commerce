<?php

declare(strict_types=1);

use AIArmada\Events\Features\Attendance\Models\EventAttendance;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Catalog\Models\EventOccurrence;
use AIArmada\Events\Features\Catalog\Models\EventSession;
use AIArmada\Events\Features\Registrations\Models\EventRegistration;
use AIArmada\FilamentEvents\Actions\Exporter\EventAttendanceExporter;
use AIArmada\FilamentEvents\Actions\Exporter\EventExporter;
use AIArmada\FilamentEvents\Actions\Exporter\EventOccurrenceExporter;
use AIArmada\FilamentEvents\Actions\Exporter\EventRegistrationExporter;
use AIArmada\FilamentEvents\Actions\Exporter\EventSessionExporter;

final class ExportConfiguredEvent extends Event {}

final class ExportConfiguredRegistration extends EventRegistration {}

final class ExportConfiguredAttendance extends EventAttendance {}

it('resolves configured models for event exports without caching the binding', function (string $key, string $exporter, string $default, string $configured): void {
    config()->set('events.models.' . $key, $default);

    expect($exporter::getModel())->toBe($default);

    config()->set('events.models.' . $key, $configured);

    expect($exporter::getModel())->toBe($configured);

    config()->set('events.models.' . $key, $default);

    expect($exporter::getModel())->toBe($default);
})->with([
    'event' => ['event', EventExporter::class, Event::class, ExportConfiguredEvent::class],
    'registration' => ['registration', EventRegistrationExporter::class, EventRegistration::class, ExportConfiguredRegistration::class],
    'attendance' => ['attendance', EventAttendanceExporter::class, EventAttendance::class, ExportConfiguredAttendance::class],
]);

it('resolves the configured schedule models for exports', function (): void {
    config()->set('events.models.occurrence', EventOccurrence::class);
    config()->set('events.models.session', EventSession::class);

    expect(EventOccurrenceExporter::getModel())->toBe(EventOccurrence::class)
        ->and(EventSessionExporter::getModel())->toBe(EventSession::class);
});
