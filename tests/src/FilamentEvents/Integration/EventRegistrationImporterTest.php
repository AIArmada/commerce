<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Registrations\Models\EventRegistration;
use AIArmada\FilamentEvents\Actions\Importer\EventRegistrationImporter;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;

final class ConfiguredEventRegistration extends EventRegistration {}

it('initializes registration lifecycle state during import', function (): void {
    $owner = OwnerContext::resolve();

    if ($owner instanceof Authenticatable) {
        $this->actingAs($owner);
    }

    $event = Event::factory()->create();
    $importer = new EventRegistrationImporter(new Import, [
        'event_id' => 'event_id',
        'registration_type' => 'registration_type',
        'status' => 'status',
        'source' => 'source',
    ], []);

    $importer([
        'event_id' => $event->getKey(),
        'registration_type' => 'individual',
        'status' => 'confirmed',
        'source' => 'import',
    ]);

    $registration = EventRegistration::query()
        ->where('event_id', $event->getKey())
        ->firstOrFail();

    expect($registration->status->getValue())->toBe('confirmed')
        ->and($registration->approved_at)->not->toBeNull()
        ->and($registration->last_state_change_at)->not->toBeNull();
});

it('rejects an unknown registration status during import', function (): void {
    $event = Event::factory()->create();
    $importer = new EventRegistrationImporter(new Import, [
        'event_id' => 'event_id',
        'registration_type' => 'registration_type',
        'status' => 'status',
        'source' => 'source',
    ], []);

    expect(fn () => $importer([
        'event_id' => $event->getKey(),
        'registration_type' => 'individual',
        'status' => 'not-a-registration-status',
        'source' => 'import',
    ]))->toThrow(ValidationException::class);

    expect(EventRegistration::query()->where('event_id', $event->getKey())->exists())->toBeFalse();
});

it('imports through the configured registration model', function (): void {
    config(['events.models.registration' => ConfiguredEventRegistration::class]);

    $owner = OwnerContext::resolve();

    if ($owner instanceof Authenticatable) {
        $this->actingAs($owner);
    }

    $event = Event::factory()->create();
    $importer = new EventRegistrationImporter(new Import, [
        'event_id' => 'event_id',
        'registration_type' => 'registration_type',
        'status' => 'status',
        'source' => 'source',
    ], []);

    $importer([
        'event_id' => $event->getKey(),
        'registration_type' => 'individual',
        'status' => 'confirmed',
        'source' => 'import',
    ]);

    $registration = ConfiguredEventRegistration::query()
        ->where('event_id', $event->getKey())
        ->firstOrFail();

    expect($registration)->toBeInstanceOf(ConfiguredEventRegistration::class)
        ->and(EventRegistrationImporter::getModel())->toBe(ConfiguredEventRegistration::class);
});

it('does not persist unmapped registration columns', function (): void {
    $owner = OwnerContext::resolve();

    if ($owner instanceof Authenticatable) {
        $this->actingAs($owner);
    }

    $event = Event::factory()->create();
    $importer = new EventRegistrationImporter(new Import, [
        'event_id' => 'event_id',
        'registration_type' => 'registration_type',
        'status' => 'status',
        'source' => 'source',
    ], []);

    $importer([
        'event_id' => $event->getKey(),
        'registration_type' => 'individual',
        'status' => 'confirmed',
        'source' => 'import',
        'total_amount' => '999999',
    ]);

    $registration = EventRegistration::query()->where('event_id', $event->getKey())->firstOrFail();

    expect($registration->total_amount)->not->toBe(999999);
});
