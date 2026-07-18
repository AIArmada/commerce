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
use AIArmada\FilamentEvents\Resources\EventAttendanceResource;
use AIArmada\FilamentEvents\Resources\EventChangeLogResource;
use AIArmada\FilamentEvents\Resources\EventOccurrenceResource;
use AIArmada\FilamentEvents\Resources\EventOccurrenceResource\RelationManagers\OccurrenceAttendancesRelationManager;
use AIArmada\FilamentEvents\Resources\EventOccurrenceResource\RelationManagers\OccurrenceInvolvementsRelationManager;
use AIArmada\FilamentEvents\Resources\EventOccurrenceResource\RelationManagers\OccurrenceLocationsRelationManager;
use AIArmada\FilamentEvents\Resources\EventOccurrenceResource\RelationManagers\OccurrenceRegistrationsRelationManager;
use AIArmada\FilamentEvents\Resources\EventOccurrenceResource\RelationManagers\OccurrenceSessionsRelationManager;
use AIArmada\FilamentEvents\Resources\EventRegistrationParticipantResource;
use AIArmada\FilamentEvents\Resources\EventRegistrationResource;
use AIArmada\FilamentEvents\Resources\EventResource;
use AIArmada\FilamentEvents\Resources\EventResource\RelationManagers\AttendancesRelationManager;
use AIArmada\FilamentEvents\Resources\EventResource\RelationManagers\InvolvementsRelationManager;
use AIArmada\FilamentEvents\Resources\EventResource\RelationManagers\LocationsRelationManager;
use AIArmada\FilamentEvents\Resources\EventResource\RelationManagers\OccurrencesRelationManager;
use AIArmada\FilamentEvents\Resources\EventResource\RelationManagers\RegistrationsRelationManager;
use AIArmada\FilamentEvents\Resources\EventResource\RelationManagers\SessionsRelationManager;
use AIArmada\FilamentEvents\Resources\EventSessionResource;
use AIArmada\FilamentEvents\Resources\EventSessionResource\RelationManagers\SessionAttendancesRelationManager;
use AIArmada\FilamentEvents\Resources\EventSessionResource\RelationManagers\SessionInvolvementsRelationManager;
use AIArmada\FilamentEvents\Resources\EventSessionResource\RelationManagers\SessionLocationsRelationManager;
use AIArmada\FilamentEvents\Resources\EventSessionResource\RelationManagers\SessionMaterialsRelationManager;
use AIArmada\FilamentEvents\Resources\EventSessionResource\RelationManagers\SessionRegistrationsRelationManager;
use AIArmada\FilamentEvents\Resources\VenueResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Panel;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Component as LivewireComponent;

if (! function_exists('filamentEvents_makeSchemaLivewire')) {
    function filamentEvents_makeSchemaLivewire(): LivewireComponent & HasSchemas
    {
        return new class extends LivewireComponent implements HasSchemas
        {
            use InteractsWithSchemas;

            public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
            {
                return null;
            }

            public function getOldSchemaState(string $statePath): mixed
            {
                return null;
            }

            public function getSchemaComponent(
                string $key,
                bool $withHidden = false,
                array $skipComponentsChildContainersWhileSearching = [],
            ): Component | Action | ActionGroup | null {
                return null;
            }

            public function getSchema(string $name): ?Schema
            {
                return null;
            }

            public function currentlyValidatingSchema(?Schema $schema): void {}

            public function getDefaultTestingSchemaName(): ?string
            {
                return null;
            }
        };
    }
}

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

it('reads the configured navigation group from resources', function (): void {
    config()->set('filament-events.navigation.group', 'Event Operations');

    foreach ([
        EventResource::class,
        EventOccurrenceResource::class,
        EventSessionResource::class,
        EventRegistrationResource::class,
        EventRegistrationParticipantResource::class,
        EventAttendanceResource::class,
        EventChangeLogResource::class,
        VenueResource::class,
    ] as $resource) {
        expect($resource::getNavigationGroup())->toBe('Event Operations');
    }
});

it('uses native contact methods for registration participant contact data', function (): void {
    expect(EventRegistrationParticipantResource::getEloquentQuery()->getEagerLoads())
        ->toHaveKey('contactMethods')
        ->and(EventRegistrationResource::getEloquentQuery()->getEagerLoads())
        ->toHaveKey('participants.contactMethods');
});

it('builds the current event resources and relation managers', function (): void {
    $table = Table::make(Mockery::mock(HasTable::class));
    $schemaLivewire = filamentEvents_makeSchemaLivewire();

    foreach ([EventResource::class, EventOccurrenceResource::class, EventSessionResource::class, EventRegistrationResource::class, EventRegistrationParticipantResource::class, EventAttendanceResource::class, EventChangeLogResource::class, VenueResource::class] as $resource) {
        expect($resource::table($table))->toBeInstanceOf(Table::class);
        expect($resource::infolist(Schema::make($schemaLivewire)))->toBeInstanceOf(Schema::class);
        expect($resource::getPages())->not->toBeEmpty();
    }

    expect(EventResource::getRelations())
        ->toContain(OccurrencesRelationManager::class)
        ->toContain(SessionsRelationManager::class)
        ->toContain(LocationsRelationManager::class)
        ->toContain(InvolvementsRelationManager::class)
        ->toContain(RegistrationsRelationManager::class)
        ->toContain(AttendancesRelationManager::class);

    expect(EventOccurrenceResource::getRelations())
        ->toContain(OccurrenceSessionsRelationManager::class)
        ->toContain(OccurrenceLocationsRelationManager::class)
        ->toContain(OccurrenceInvolvementsRelationManager::class)
        ->toContain(OccurrenceRegistrationsRelationManager::class)
        ->toContain(OccurrenceAttendancesRelationManager::class);

    expect(EventSessionResource::getRelations())
        ->toContain(SessionInvolvementsRelationManager::class)
        ->toContain(SessionLocationsRelationManager::class)
        ->toContain(SessionRegistrationsRelationManager::class)
        ->toContain(SessionAttendancesRelationManager::class)
        ->toContain(SessionMaterialsRelationManager::class);

    $flatten = function (array $components) use (&$flatten): array {
        $all = [];

        foreach ($components as $component) {
            if (! is_object($component)) {
                continue;
            }

            $all[] = $component;

            if (method_exists($component, 'getChildComponents')) {
                $all = [...$all, ...$flatten($component->getChildComponents())];
            }
        }

        return $all;
    };

    $schemaLivewire = filamentEvents_makeSchemaLivewire();
    $occurrenceFormComponents = collect($flatten(EventOccurrenceResource::form(Schema::make($schemaLivewire))->getComponents()));
    $sessionFormComponents = collect($flatten(EventSessionResource::form(Schema::make($schemaLivewire))->getComponents()));

    $occurrenceVisibility = $occurrenceFormComponents->first(function (object $component): bool {
        return $component instanceof Select && $component->getName() === 'visibility';
    });
    $sessionVisibility = $sessionFormComponents->first(function (object $component): bool {
        return $component instanceof Select && $component->getName() === 'visibility';
    });
    $sessionStartsAt = $sessionFormComponents->first(function (object $component): bool {
        return $component instanceof DateTimePicker && $component->getName() === 'starts_at';
    });
    $sessionEndsAt = $sessionFormComponents->first(function (object $component): bool {
        return $component instanceof DateTimePicker && $component->getName() === 'ends_at';
    });

    expect($occurrenceVisibility)
        ->toBeInstanceOf(Select::class)
        ->and($occurrenceVisibility?->getDefaultState())->toBeNull();

    expect($sessionVisibility)
        ->toBeInstanceOf(Select::class)
        ->and($sessionVisibility?->getDefaultState())->toBeNull();

    expect($sessionStartsAt)->toBeInstanceOf(DateTimePicker::class)
        ->and($sessionEndsAt)->toBeInstanceOf(DateTimePicker::class);

    $occurrenceTable = EventOccurrenceResource::table(Table::make(Mockery::mock(HasTable::class)));
    $occurrenceFilters = array_map(static fn ($filter): string => $filter->getName(), $occurrenceTable->getFilters());
    $occurrenceStatusFilter = collect($occurrenceTable->getFilters())->first(
        static fn ($filter): bool => $filter->getName() === 'status',
    );

    expect($occurrenceFilters)->toContain('status', 'visibility', 'event_id');
    expect($occurrenceStatusFilter?->getOptions())->toHaveKey('rescheduled');

    $sessionTable = EventSessionResource::table(Table::make(Mockery::mock(HasTable::class)));
    $sessionColumns = array_map(static fn ($column): string => $column->getName(), $sessionTable->getColumns());
    $sessionFilters = array_map(static fn ($filter): string => $filter->getName(), $sessionTable->getFilters());
    $sessionHeaderActions = array_map(static fn ($action): string => $action->getName(), $sessionTable->getHeaderActions());
    $sessionStatusFilter = collect($sessionTable->getFilters())->first(
        static fn ($filter): bool => $filter->getName() === 'status',
    );

    expect($sessionColumns)->toContain(
        'event.title',
        'occurrence.title',
        'title',
        'starts_at',
        'ends_at',
        'status',
        'visibility',
        'capacity',
        'published_at',
        'cancelled_at',
        'sort_order',
    );
    expect($sessionFilters)->toContain('status', 'visibility', 'event_id', 'event_occurrence_id');
    expect($sessionStatusFilter?->getOptions())->toHaveKey('rescheduled');
    expect($sessionHeaderActions)->toContain('import', 'export', 'delay', 'postpone', 'cancel', 'complete');
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
            'attendances' => EventAttendanceResource::getEloquentQuery()->pluck('id')->all(),
            'changeLogs' => EventChangeLogResource::getEloquentQuery()->pluck('id')->all(),
        ];
    });

    expect($ownerAIds['events'])->toBe([$ownerAGraph['event']->id])
        ->and($ownerAIds['occurrences'])->toBe([$ownerAGraph['occurrence']->id])
        ->and($ownerAIds['sessions'])->toBe([$ownerAGraph['session']->id])
        ->and($ownerAIds['registrations'])->toBe([$ownerAGraph['registration']->id])
        ->and($ownerAIds['attendances'])->toBe([$ownerAGraph['attendance']->id])
        ->and($ownerAIds['changeLogs'])->toBe([$ownerAGraph['changeLog']->id]);

    expect($ownerBIds['events'])->toBe([$ownerBGraph['event']->id])
        ->and($ownerBIds['occurrences'])->toBe([$ownerBGraph['occurrence']->id])
        ->and($ownerBIds['sessions'])->toBe([$ownerBGraph['session']->id])
        ->and($ownerBIds['registrations'])->toBe([$ownerBGraph['registration']->id])
        ->and($ownerBIds['attendances'])->toBe([$ownerBGraph['attendance']->id])
        ->and($ownerBIds['changeLogs'])->toBe([$ownerBGraph['changeLog']->id]);
});
