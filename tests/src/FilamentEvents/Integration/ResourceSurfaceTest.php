<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventAttendance;
use AIArmada\Events\Models\EventChangeLog;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Models\EventSession;
use AIArmada\Events\Models\EventTicketType;
use AIArmada\FilamentEvents\Resources\EventAttendanceResource;
use AIArmada\FilamentEvents\Resources\EventChangeLogResource;
use AIArmada\FilamentEvents\Resources\EventOccurrenceResource;
use AIArmada\FilamentEvents\Resources\EventOccurrenceResource\RelationManagers\OccurrenceAttendancesRelationManager;
use AIArmada\FilamentEvents\Resources\EventOccurrenceResource\RelationManagers\OccurrenceInvolvementsRelationManager;
use AIArmada\FilamentEvents\Resources\EventOccurrenceResource\RelationManagers\OccurrenceLocationsRelationManager;
use AIArmada\FilamentEvents\Resources\EventOccurrenceResource\RelationManagers\OccurrenceRegistrationsRelationManager;
use AIArmada\FilamentEvents\Resources\EventOccurrenceResource\RelationManagers\OccurrenceSessionsRelationManager;
use AIArmada\FilamentEvents\Resources\EventOccurrenceResource\RelationManagers\OccurrenceTicketTypesRelationManager;
use AIArmada\FilamentEvents\Resources\EventRegistrationResource;
use AIArmada\FilamentEvents\Resources\EventResource;
use AIArmada\FilamentEvents\Resources\EventResource\RelationManagers\AttendancesRelationManager;
use AIArmada\FilamentEvents\Resources\EventResource\RelationManagers\InvolvementsRelationManager;
use AIArmada\FilamentEvents\Resources\EventResource\RelationManagers\LocationsRelationManager;
use AIArmada\FilamentEvents\Resources\EventResource\RelationManagers\OccurrencesRelationManager;
use AIArmada\FilamentEvents\Resources\EventResource\RelationManagers\RegistrationsRelationManager;
use AIArmada\FilamentEvents\Resources\EventResource\RelationManagers\SessionsRelationManager;
use AIArmada\FilamentEvents\Resources\EventResource\RelationManagers\TicketTypesRelationManager;
use AIArmada\FilamentEvents\Resources\EventSessionResource;
use AIArmada\FilamentEvents\Resources\EventSessionResource\RelationManagers\SessionAttendancesRelationManager;
use AIArmada\FilamentEvents\Resources\EventSessionResource\RelationManagers\SessionInvolvementsRelationManager;
use AIArmada\FilamentEvents\Resources\EventSessionResource\RelationManagers\SessionLocationsRelationManager;
use AIArmada\FilamentEvents\Resources\EventSessionResource\RelationManagers\SessionMaterialsRelationManager;
use AIArmada\FilamentEvents\Resources\EventTicketTypeResource;
use AIArmada\FilamentEvents\Resources\VenueResource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

afterEach(function (): void {
    if (class_exists(Mockery::class)) {
        Mockery::close();
    }
});

it('registers the event resource on a panel', function (): void {
    $panel = Panel::make()->id('admin');

    $panel->resources([EventResource::class]);

    expect($panel->getResources())->toContain(EventResource::class);
});

it('builds the current event resources and relation managers', function (): void {
    $table = Table::make(Mockery::mock(HasTable::class));

    foreach ([EventResource::class, EventOccurrenceResource::class, EventSessionResource::class, EventRegistrationResource::class, EventTicketTypeResource::class, EventAttendanceResource::class, EventChangeLogResource::class, VenueResource::class] as $resource) {
        expect($resource::table($table))->toBeInstanceOf(Table::class);
        expect($resource::infolist(Schema::make()))->toBeInstanceOf(Schema::class);
        expect($resource::getPages())->not->toBeEmpty();
    }

    expect(EventResource::getRelations())
        ->toContain(OccurrencesRelationManager::class)
        ->toContain(SessionsRelationManager::class)
        ->toContain(LocationsRelationManager::class)
        ->toContain(InvolvementsRelationManager::class)
        ->toContain(RegistrationsRelationManager::class)
        ->toContain(TicketTypesRelationManager::class)
        ->toContain(AttendancesRelationManager::class);

    expect(EventOccurrenceResource::getRelations())
        ->toContain(OccurrenceSessionsRelationManager::class)
        ->toContain(OccurrenceLocationsRelationManager::class)
        ->toContain(OccurrenceInvolvementsRelationManager::class)
        ->toContain(OccurrenceRegistrationsRelationManager::class)
        ->toContain(OccurrenceTicketTypesRelationManager::class)
        ->toContain(OccurrenceAttendancesRelationManager::class);

    expect(EventSessionResource::getRelations())
        ->toContain(SessionInvolvementsRelationManager::class)
        ->toContain(SessionLocationsRelationManager::class)
        ->toContain(SessionAttendancesRelationManager::class)
        ->toContain(SessionMaterialsRelationManager::class);
});

it('keeps the current event resources owner scoped', function (): void {
    $createGraph = function (User $owner, string $suffix): array {
        return OwnerContext::withOwner($owner, function () use ($suffix): array {
            $event = Event::factory()->create([
                'title' => 'Event ' . $suffix,
                'slug' => 'event-' . $suffix,
            ]);

            $occurrence = EventOccurrence::factory()->create([
                'event_id' => $event->id,
                'title' => 'Occurrence ' . $suffix,
                'slug' => 'occurrence-' . $suffix,
            ]);

            $session = EventSession::factory()->create([
                'event_id' => $event->id,
                'event_occurrence_id' => $occurrence->id,
                'title' => 'Session ' . $suffix,
                'slug' => 'session-' . $suffix,
            ]);

            $ticketType = EventTicketType::factory()->create([
                'event_id' => $event->id,
                'event_occurrence_id' => $occurrence->id,
                'event_session_id' => $session->id,
            ]);

            $registration = EventRegistration::factory()->create([
                'event_id' => $event->id,
                'event_occurrence_id' => $occurrence->id,
                'event_session_id' => $session->id,
            ]);

            $attendance = EventAttendance::factory()->create([
                'event_id' => $event->id,
                'event_occurrence_id' => $occurrence->id,
                'event_session_id' => $session->id,
                'event_registration_id' => $registration->id,
            ]);

            $changeLog = EventChangeLog::factory()->create([
                'event_id' => $event->id,
                'event_occurrence_id' => $occurrence->id,
                'event_session_id' => $session->id,
            ]);

            return compact(
                'event',
                'occurrence',
                'session',
                'ticketType',
                'registration',
                'attendance',
                'changeLog',
            );
        });
    };

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'filament-events-resource-owner-a-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'filament-events-resource-owner-b-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $ownerAGraph = $createGraph($ownerA, 'a');
    $ownerBGraph = $createGraph($ownerB, 'b');

    $ownerAIds = OwnerContext::withOwner($ownerA, function (): array {
        return [
            'events' => EventResource::getEloquentQuery()->pluck('id')->all(),
            'occurrences' => EventOccurrenceResource::getEloquentQuery()->pluck('id')->all(),
            'sessions' => EventSessionResource::getEloquentQuery()->pluck('id')->all(),
            'registrations' => EventRegistrationResource::getEloquentQuery()->pluck('id')->all(),
            'ticketTypes' => EventTicketTypeResource::getEloquentQuery()->pluck('id')->all(),
            'attendances' => EventAttendanceResource::getEloquentQuery()->pluck('id')->all(),
            'changeLogs' => EventChangeLogResource::getEloquentQuery()->pluck('id')->all(),
        ];
    });

    $ownerBIds = OwnerContext::withOwner($ownerB, function (): array {
        return [
            'events' => EventResource::getEloquentQuery()->pluck('id')->all(),
            'occurrences' => EventOccurrenceResource::getEloquentQuery()->pluck('id')->all(),
            'sessions' => EventSessionResource::getEloquentQuery()->pluck('id')->all(),
            'registrations' => EventRegistrationResource::getEloquentQuery()->pluck('id')->all(),
            'ticketTypes' => EventTicketTypeResource::getEloquentQuery()->pluck('id')->all(),
            'attendances' => EventAttendanceResource::getEloquentQuery()->pluck('id')->all(),
            'changeLogs' => EventChangeLogResource::getEloquentQuery()->pluck('id')->all(),
        ];
    });

    expect($ownerAIds['events'])->toBe([$ownerAGraph['event']->id])
        ->and($ownerAIds['occurrences'])->toBe([$ownerAGraph['occurrence']->id])
        ->and($ownerAIds['sessions'])->toBe([$ownerAGraph['session']->id])
        ->and($ownerAIds['registrations'])->toBe([$ownerAGraph['registration']->id])
        ->and($ownerAIds['ticketTypes'])->toBe([$ownerAGraph['ticketType']->id])
        ->and($ownerAIds['attendances'])->toBe([$ownerAGraph['attendance']->id])
        ->and($ownerAIds['changeLogs'])->toBe([$ownerAGraph['changeLog']->id]);

    expect($ownerBIds['events'])->toBe([$ownerBGraph['event']->id])
        ->and($ownerBIds['occurrences'])->toBe([$ownerBGraph['occurrence']->id])
        ->and($ownerBIds['sessions'])->toBe([$ownerBGraph['session']->id])
        ->and($ownerBIds['registrations'])->toBe([$ownerBGraph['registration']->id])
        ->and($ownerBIds['ticketTypes'])->toBe([$ownerBGraph['ticketType']->id])
        ->and($ownerBIds['attendances'])->toBe([$ownerBGraph['attendance']->id])
        ->and($ownerBIds['changeLogs'])->toBe([$ownerBGraph['changeLog']->id]);
});
