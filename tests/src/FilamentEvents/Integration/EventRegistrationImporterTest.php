<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Registrations\Actions\ImportEventRegistrationAction;
use AIArmada\Events\Features\Registrations\Models\EventRegistration;
use AIArmada\FilamentEvents\Actions\Importer\EventRegistrationImporter;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;

final class ConfiguredEventRegistration extends EventRegistration {}

it('initializes registration lifecycle state during import', function (): void {
    $owner = OwnerContext::resolve();

    expect($owner)->toBeInstanceOf(Authenticatable::class);
    $this->actingAs($owner);

    $import = new Import;
    $import->user()->associate($owner);

    $event = Event::factory()->create();
    $importer = new EventRegistrationImporter($import, [
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
    $owner = OwnerContext::resolve();
    expect($owner)->toBeInstanceOf(Authenticatable::class);

    $import = new Import;
    $import->user()->associate($owner);

    $event = Event::factory()->create();
    $importer = new EventRegistrationImporter($import, [
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

    expect($owner)->toBeInstanceOf(Authenticatable::class);
    $this->actingAs($owner);

    $import = new Import;
    $import->user()->associate($owner);

    $event = Event::factory()->create();
    $importer = new EventRegistrationImporter($import, [
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

    expect($owner)->toBeInstanceOf(Authenticatable::class);
    $this->actingAs($owner);

    $import = new Import;
    $import->user()->associate($owner);

    $event = Event::factory()->create();
    $importer = new EventRegistrationImporter($import, [
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

it('validates and authorizes registration import writes at the domain boundary', function (): void {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();

    OwnerContext::withOwner($owner, function () use ($owner, $outsider): void {
        $event = Event::factory()->create();
        $action = app(ImportEventRegistrationAction::class);
        $data = [
            'event_id' => $event->id,
            'registration_type' => 'individual',
            'status' => 'confirmed',
            'source' => 'import',
        ];

        foreach ([
            ['total_participants' => '0'],
            ['total_participants' => '2147483648'],
            ['total_amount' => '-1'],
            ['total_amount' => '1.5'],
            ['status' => 'invalid'],
            ['registration_type' => str_repeat('x', 65)],
        ] as $invalid) {
            expect(fn () => $action->handle(array_replace($data, $invalid), $owner))
                ->toThrow(ValidationException::class);
        }

        expect(fn () => $action->handle($data, $outsider))->toThrow(ValidationException::class)
            ->and(EventRegistration::query()->where('event_id', $event->id)->exists())->toBeFalse();

        $record = $action->handle([...$data, 'total_participants' => null, 'notes' => 'Not importable'], $owner)->refresh();

        expect($record->total_participants)->toBe(1)
            ->and($record->notes)->toBeNull()
            ->and($record->status->getValue())->toBe('confirmed');
    });
});

it('never substitutes the ambient actor for an unattributed import', function (): void {
    $owner = OwnerContext::resolve();
    expect($owner)->toBeInstanceOf(Authenticatable::class);
    $this->actingAs($owner);

    $event = Event::factory()->create();
    $columns = [
        'event_id' => 'event_id',
        'registration_type' => 'registration_type',
        'status' => 'status',
        'source' => 'source',
    ];
    $data = [
        'event_id' => $event->getKey(),
        'registration_type' => 'individual',
        'status' => 'confirmed',
        'source' => 'import',
    ];

    $unattributed = new Import;
    $unresolved = new Import;
    $unresolved->user()->associate($owner);
    $unresolved->setRelation('user', null);

    foreach ([$unattributed, $unresolved] as $import) {
        $importer = new EventRegistrationImporter($import, $columns, []);

        expect(fn () => $importer($data))
            ->toThrow(RowImportFailedException::class, 'not authorized');
    }

    expect(EventRegistration::query()->where('event_id', $event->getKey())->exists())->toBeFalse();
});
