<?php

declare(strict_types=1);

use AIArmada\Events\Data\EventDetailData;
use AIArmada\Events\Data\EventReviewSchemaData;
use AIArmada\Events\Data\EventSearchResultData;
use AIArmada\Events\Data\OccurrenceDetailData;
use AIArmada\Events\Enums\EventModerationStatus;
use AIArmada\Events\Enums\EventStatus;
use AIArmada\Events\Enums\EventVisibility;
use AIArmada\Events\Enums\OccurrenceParticipationMode;
use AIArmada\Events\Enums\OccurrenceStatus;
use AIArmada\Events\Enums\RegistrationAttendanceSource;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventSubLocation;
use AIArmada\Events\Models\Occurrence;
use AIArmada\Events\Models\Registration;
use AIArmada\Events\Models\Venue;
use AIArmada\Events\Support\Policy\EventModerationPolicy;
use AIArmada\FilamentEvents\FilamentEventsPlugin;
use AIArmada\FilamentEvents\FilamentEventsServiceProvider;
use AIArmada\FilamentEvents\Resources\EventResource;
use AIArmada\FilamentEvents\Resources\EventResource\Pages\EditEvent;
use AIArmada\FilamentEvents\Resources\EventResource\Pages\ListEvents;
use AIArmada\FilamentEvents\Resources\EventResource\Pages\ViewEvent;
use AIArmada\FilamentEvents\Resources\EventResource\RelationManagers\EventAgendasRelationManager;
use AIArmada\FilamentEvents\Resources\EventResource\RelationManagers\EventAssetsRelationManager;
use AIArmada\FilamentEvents\Resources\EventResource\RelationManagers\EventAttendanceRelationManager;
use AIArmada\FilamentEvents\Resources\EventResource\RelationManagers\EventChangesRelationManager;
use AIArmada\FilamentEvents\Resources\EventResource\RelationManagers\EventClassificationsRelationManager;
use AIArmada\FilamentEvents\Resources\EventResource\RelationManagers\EventEngagementsRelationManager;
use AIArmada\FilamentEvents\Resources\EventResource\RelationManagers\EventPeopleRelationManager;
use AIArmada\FilamentEvents\Resources\EventResource\RelationManagers\EventReviewsRelationManager;
use AIArmada\FilamentEvents\Resources\EventResource\RelationManagers\EventSubmissionsRelationManager;
use AIArmada\FilamentEvents\Resources\EventResource\RelationManagers\OccurrencesRelationManager;
use AIArmada\FilamentEvents\Resources\EventSeriesResource;
use AIArmada\FilamentEvents\Resources\EventSeriesResource\Pages\EditEventSeries;
use AIArmada\FilamentEvents\Resources\EventSeriesResource\Pages\ListEventSeries;
use AIArmada\FilamentEvents\Resources\EventSeriesResource\Pages\ViewEventSeries;
use AIArmada\FilamentEvents\Resources\EventSubLocationResource;
use AIArmada\FilamentEvents\Resources\EventSubLocationResource\Pages\EditEventSubLocation;
use AIArmada\FilamentEvents\Resources\EventSubLocationResource\Pages\ListEventSubLocations;
use AIArmada\FilamentEvents\Resources\EventSubLocationResource\Pages\ViewEventSubLocation;
use AIArmada\FilamentEvents\Resources\OccurrenceResource;
use AIArmada\FilamentEvents\Resources\OccurrenceResource\Pages\CreateOccurrence;
use AIArmada\FilamentEvents\Resources\OccurrenceResource\Pages\EditOccurrence;
use AIArmada\FilamentEvents\Resources\OccurrenceResource\Pages\ListOccurrences;
use AIArmada\FilamentEvents\Resources\OccurrenceResource\Pages\ViewOccurrence;
use AIArmada\FilamentEvents\Resources\OccurrenceResource\RelationManagers\RegistrationsRelationManager;
use AIArmada\FilamentEvents\Resources\RegistrationResource;
use AIArmada\FilamentEvents\Resources\RegistrationResource\Pages\EditRegistration;
use AIArmada\FilamentEvents\Resources\RegistrationResource\Pages\ListRegistrations;
use AIArmada\FilamentEvents\Resources\RegistrationResource\Pages\ViewRegistration;
use AIArmada\FilamentEvents\Resources\VenueResource;
use AIArmada\FilamentEvents\Resources\VenueResource\Pages\EditVenue;
use AIArmada\FilamentEvents\Resources\VenueResource\Pages\ListVenues;
use AIArmada\FilamentEvents\Resources\VenueResource\Pages\ViewVenue;
use AIArmada\FilamentEvents\Support\FilamentEventQueryAdapter;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

afterEach(function (): void {
    if (class_exists(Mockery::class)) {
        Mockery::close();
    }
});

function makeFilamentEventsTable(): Table
{
    /** @var HasTable $livewire */
    $livewire = Mockery::mock(HasTable::class);

    return Table::make($livewire);
}

it('registers the filament events plugin resources', function (): void {
    $panel = Panel::make()->id('admin');
    $plugin = FilamentEventsPlugin::make();

    expect($plugin->getId())->toBe('filament-events');

    $plugin->register($panel);
    $plugin->boot($panel);

    expect($panel->getResources())
        ->toContain(
            EventSeriesResource::class,
            EventResource::class,
            OccurrenceResource::class,
            VenueResource::class,
            EventSubLocationResource::class,
            RegistrationResource::class,
        );
});

it('loads the filament events package config', function (): void {
    $this->app->register(FilamentEventsServiceProvider::class);

    expect(config('filament-events.navigation.group'))->toBe('Events');
});

it('builds event resource schemas and tables', function (): void {
    foreach ([EventSeriesResource::class, EventResource::class, VenueResource::class, EventSubLocationResource::class, OccurrenceResource::class, RegistrationResource::class] as $resource) {
        expect($resource::form(Schema::make()))->toBeInstanceOf(Schema::class);
        expect($resource::table(makeFilamentEventsTable()))->toBeInstanceOf(Table::class);
        expect($resource::infolist(Schema::make()))->toBeInstanceOf(Schema::class);
        expect($resource::getPages())->not->toBeEmpty();
    }

    expect(EventResource::getRelations())
        ->toContain(OccurrencesRelationManager::class)
        ->toContain(EventPeopleRelationManager::class)
        ->toContain(EventSubmissionsRelationManager::class)
        ->toContain(EventReviewsRelationManager::class)
        ->toContain(EventChangesRelationManager::class)
        ->toContain(EventAssetsRelationManager::class)
        ->toContain(EventClassificationsRelationManager::class)
        ->toContain(EventEngagementsRelationManager::class)
        ->toContain(EventAttendanceRelationManager::class)
        ->toContain(EventAgendasRelationManager::class);
    expect(OccurrenceResource::getRelations())->toContain(RegistrationsRelationManager::class);
});

it('builds event relation manager schemas and tables', function (): void {
    $occurrences = app(OccurrencesRelationManager::class);
    $eventPeople = app(EventPeopleRelationManager::class);
    $registrations = app(RegistrationsRelationManager::class);
    $submissions = app(EventSubmissionsRelationManager::class);
    $reviews = app(EventReviewsRelationManager::class);
    $changeNotices = app(EventChangesRelationManager::class);
    $assets = app(EventAssetsRelationManager::class);
    $classifications = app(EventClassificationsRelationManager::class);
    $engagements = app(EventEngagementsRelationManager::class);
    $attendance = app(EventAttendanceRelationManager::class);
    $agendaItems = app(EventAgendasRelationManager::class);

    expect($occurrences->form(Schema::make()))->toBeInstanceOf(Schema::class);
    expect($occurrences->table(makeFilamentEventsTable()))->toBeInstanceOf(Table::class);

    expect($eventPeople->form(Schema::make()))->toBeInstanceOf(Schema::class);
    expect($eventPeople->table(makeFilamentEventsTable()))->toBeInstanceOf(Table::class);

    expect($registrations->form(Schema::make()))->toBeInstanceOf(Schema::class);
    expect($registrations->table(makeFilamentEventsTable()))->toBeInstanceOf(Table::class);

    expect($submissions->form(Schema::make()))->toBeInstanceOf(Schema::class);
    expect($submissions->table(makeFilamentEventsTable()))->toBeInstanceOf(Table::class);

    expect($reviews->form(Schema::make()))->toBeInstanceOf(Schema::class);
    expect($reviews->table(makeFilamentEventsTable()))->toBeInstanceOf(Table::class);

    expect($changeNotices->form(Schema::make()))->toBeInstanceOf(Schema::class);
    expect($changeNotices->table(makeFilamentEventsTable()))->toBeInstanceOf(Table::class);

    expect($assets->form(Schema::make()))->toBeInstanceOf(Schema::class);
    expect($assets->table(makeFilamentEventsTable()))->toBeInstanceOf(Table::class);

    expect($classifications->form(Schema::make()))->toBeInstanceOf(Schema::class);
    expect($classifications->table(makeFilamentEventsTable()))->toBeInstanceOf(Table::class);

    expect($engagements->form(Schema::make()))->toBeInstanceOf(Schema::class);
    expect($engagements->table(makeFilamentEventsTable()))->toBeInstanceOf(Table::class);

    expect($attendance->form(Schema::make()))->toBeInstanceOf(Schema::class);
    expect($attendance->table(makeFilamentEventsTable()))->toBeInstanceOf(Table::class);

    expect($agendaItems->form(Schema::make()))->toBeInstanceOf(Schema::class);
    expect($agendaItems->table(makeFilamentEventsTable()))->toBeInstanceOf(Table::class);
});

it('keeps the event people relation manager aligned with the current event person schema', function (): void {
    $relationship = new ReflectionProperty(EventPeopleRelationManager::class, 'relationship');
    $relationship->setAccessible(true);

    $title = new ReflectionProperty(EventPeopleRelationManager::class, 'title');
    $title->setAccessible(true);

    expect($relationship->getValue())->toBe('people')
        ->and($title->getValue())->toBe('People');
});

it('builds event resource page header actions', function (): void {
    $getActions = function (object $page): array {
        $method = new ReflectionMethod($page::class, 'getHeaderActions');

        return $method->invoke($page);
    };

    $pages = [
        new ListEventSeries,
        new EditEventSeries,
        new ViewEventSeries,
        new ListEvents,
        new EditEvent,
        new ViewEvent,
        new ListVenues,
        new EditVenue,
        new ViewVenue,
        new ListEventSubLocations,
        new EditEventSubLocation,
        new ViewEventSubLocation,
        new ListOccurrences,
        new EditOccurrence,
        new ViewOccurrence,
        new ListRegistrations,
        new EditRegistration,
        new ViewRegistration,
    ];

    foreach ($pages as $page) {
        expect($getActions($page))->toBeArray()->not->toBeEmpty();
    }
});

it('exposes event moderation and visibility options', function (): void {
    expect(EventResource::moderationStatusOptions())->toMatchArray([
        EventModerationStatus::Pending->value => EventModerationStatus::Pending->label(),
        EventModerationStatus::Approved->value => EventModerationStatus::Approved->label(),
        EventModerationStatus::Rejected->value => EventModerationStatus::Rejected->label(),
    ])
        ->and(EventResource::visibilityOptions())->toMatchArray([
            EventVisibility::Public->value => EventVisibility::Public->label(),
            EventVisibility::Unlisted->value => EventVisibility::Unlisted->label(),
            EventVisibility::Private->value => EventVisibility::Private->label(),
        ]);
});

it('exposes participation modes and labels walk-in registrations without email', function (): void {
    expect(OccurrenceResource::participationModeOptions())->toMatchArray([
        OccurrenceParticipationMode::None->value => OccurrenceParticipationMode::None->label(),
        OccurrenceParticipationMode::RegistrationRequired->value => OccurrenceParticipationMode::RegistrationRequired->label(),
        OccurrenceParticipationMode::WalkInOnly->value => OccurrenceParticipationMode::WalkInOnly->label(),
        OccurrenceParticipationMode::Hybrid->value => OccurrenceParticipationMode::Hybrid->label(),
    ]);

    $walkIn = new Registration([
        'attendance_source' => RegistrationAttendanceSource::WalkIn,
        'first_name' => 'Walk-in',
        'last_name' => 'Attendee',
        'email' => null,
    ]);

    expect(RegistrationResource::registrationContactLabel($walkIn))->toBe('Walk-in');
});

it('revalidates occurrence address and sub-location ids before create and save', function (): void {
    $venue = Venue::query()->create([
        'name' => 'Validated Address',
        'slug' => 'validated-address',
        'country' => 'MY',
    ]);

    $subLocation = EventSubLocation::query()->create([
        'name' => 'Validated Sub-location',
        'slug' => 'validated-sub-location',
    ]);

    $createPage = new CreateOccurrence;
    $createMethod = new ReflectionMethod($createPage::class, 'mutateFormDataBeforeCreate');
    $createMethod->setAccessible(true);

    $editPage = new EditOccurrence;
    $saveMethod = new ReflectionMethod($editPage::class, 'mutateFormDataBeforeSave');
    $saveMethod->setAccessible(true);

    $mutated = $createMethod->invoke($createPage, [
        'address_type' => Venue::class,
        'address_id' => $venue->id,
        'sub_location_id' => $subLocation->id,
    ]);

    $saved = $saveMethod->invoke($editPage, [
        'address_type' => Venue::class,
        'address_id' => $venue->id,
        'sub_location_id' => $subLocation->id,
    ]);

    expect($mutated['address_id'])->toBe($venue->id)
        ->and($mutated['sub_location_id'])->toBe($subLocation->id)
        ->and($saved['address_id'])->toBe($venue->id)
        ->and($saved['sub_location_id'])->toBe($subLocation->id);

    expect(fn () => $createMethod->invoke($createPage, [
        'address_type' => Venue::class,
        'address_id' => 'missing-address',
    ]))->toThrow(ValidationException::class);

    expect(fn () => $saveMethod->invoke($editPage, [
        'address_type' => 'Illuminate\\Database\\Eloquent\\Model',
        'address_id' => $venue->id,
    ]))->toThrow(ValidationException::class);

    expect(fn () => $createMethod->invoke($createPage, [
        'sub_location_id' => 'missing-sub-location',
    ]))->toThrow(ValidationException::class);
});

it('translates filament filter state into package-native search criteria', function (): void {
    $criteria = FilamentEventQueryAdapter::buildCriteria(
        filterState: [
            'status' => 'published',
            'moderation_status' => ['pending', 'approved'],
            'visibility' => 'public',
            'structure' => 'single',
            'classification_group' => 'topic',
            'asset_role' => 'cover',
            'reference_kind' => 'link',
            'term' => 'concert',
        ],
        context: [
            'page' => 2,
            'perPage' => 50,
            'sort' => 'name',
            'direction' => 'asc',
            'includeGlobal' => true,
        ],
    );

    expect($criteria->term)->toBe('concert')
        ->and($criteria->statuses)->toBe(['published'])
        ->and($criteria->moderationStatuses)->toBe(['pending', 'approved'])
        ->and($criteria->visibilities)->toBe(['public'])
        ->and($criteria->structures)->toBe(['single'])
        ->and($criteria->classificationGroups)->toBe(['topic'])
        ->and($criteria->assetRoles)->toBe(['cover'])
        ->and($criteria->referenceKinds)->toBe(['link'])
        ->and($criteria->page)->toBe(2)
        ->and($criteria->perPage)->toBe(50)
        ->and($criteria->sort)->toBe('name')
        ->and($criteria->direction)->toBe('asc')
        ->and($criteria->includeGlobal)->toBeTrue();
});

it('normalizes malformed filament filter state into safe search criteria', function (): void {
    $criteria = FilamentEventQueryAdapter::buildCriteria(
        filterState: [
            'status' => ['  ', 'published', 0, null, 'draft'],
            'term' => '   ',
            'moderation_status' => 'invalid-string-state',
        ],
        context: [
            'direction' => 'BOGUS',
        ],
    );

    expect($criteria->statuses)->toBe(['published', 'draft'])
        ->and($criteria->term)->toBeNull()
        ->and($criteria->moderationStatuses)->toBe(['invalid-string-state'])
        ->and($criteria->direction)->toBe('desc')
        ->and($criteria->includeGlobal)->toBeFalse();
});

it('delegates the filament event query adapter to the package search engine', function (): void {
    $result = FilamentEventQueryAdapter::search(
        filterState: ['status' => 'published'],
        context: ['perPage' => 5],
    );

    expect($result)->toBeInstanceOf(EventSearchResultData::class)
        ->and($result->perPage)->toBe(5)
        ->and($result->page)->toBe(1)
        ->and($result->items)->toBeArray();
});

it('resolves event and occurrence snapshots through the package query service', function (): void {
    $venue = Venue::query()->create([
        'name' => 'Snapshot Hall',
        'slug' => 'snapshot-hall',
        'country' => 'MY',
    ]);

    $event = Event::query()->create([
        'name' => 'Snapshot Showcase',
        'slug' => 'snapshot-showcase',
        'status' => EventStatus::Active,
        'visibility' => EventVisibility::Public,
        'moderation_status' => EventModerationStatus::Pending,
    ]);

    $occurrence = Occurrence::query()->create([
        'event_id' => $event->id,
        'name' => 'Main Show',
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHours(2),
        'status' => OccurrenceStatus::Scheduled,
        'timezone' => 'Asia/Kuala_Lumpur',
        'registration_mode' => 'free',
        'duplicate_strategy' => 'per_occurrence',
    ]);
    $occurrence->refresh();

    $eventSnapshot = EventResource::snapshot($event);
    $reviewSchema = EventResource::reviewSchema($event);
    $occurrenceSnapshot = OccurrenceResource::snapshot($occurrence);

    expect($eventSnapshot)->toBeInstanceOf(EventDetailData::class)
        ->and($eventSnapshot->name)->toBe('Snapshot Showcase')
        ->and($reviewSchema)->toBeInstanceOf(EventReviewSchemaData::class)
        ->and($reviewSchema->actions)->toEqual(EventModerationPolicy::allowedActionsFor($event->moderation_status))
        ->and($occurrenceSnapshot)->toBeInstanceOf(OccurrenceDetailData::class)
        ->and($occurrenceSnapshot->name)->toBe('Main Show');
});

it('exposes moderation actions keyed by package policy on the event view page', function (): void {
    $event = Event::query()->create([
        'name' => 'Reviewable Event',
        'slug' => 'reviewable-event',
        'status' => EventStatus::Draft,
        'visibility' => EventVisibility::Private,
        'moderation_status' => EventModerationStatus::Pending,
    ]);

    $viewEvent = new ViewEvent;
    $viewEvent->record = $event;

    $getActions = new ReflectionMethod($viewEvent, 'getHeaderActions');
    $getActions->setAccessible(true);
    $actions = $getActions->invoke($viewEvent);

    $names = array_map(
        static fn (mixed $action): string => method_exists($action, 'getName') ? (string) $action->getName() : '',
        $actions,
    );

    expect($names)->toContain('submitForReview', 'approveEvent', 'requestChanges', 'rejectEvent');

    $reviewSchema = EventResource::reviewSchema($event);
    expect($reviewSchema->actions)->toEqual(EventModerationPolicy::allowedActionsFor($event->moderation_status));

    $submitAction = EventResource::submitForReviewAction();
    $approveAction = EventResource::approveAction();
    $requestChangesAction = EventResource::requestChangesAction();
    $rejectAction = EventResource::rejectEventAction();

    expect($submitAction->getName())->toBe('submitForReview')
        ->and($approveAction->getName())->toBe('approveEvent')
        ->and($requestChangesAction->getName())->toBe('requestChanges')
        ->and($rejectAction->getName())->toBe('rejectEvent');

    $approvedEvent = Event::query()->create([
        'name' => 'Already Approved',
        'slug' => 'already-approved',
        'status' => EventStatus::Active,
        'visibility' => EventVisibility::Public,
        'moderation_status' => EventModerationStatus::Approved,
    ]);

    $approvedReviewSchema = EventResource::reviewSchema($approvedEvent);
    $approvedAllowedKeys = $approvedReviewSchema->actions;

    expect($approvedAllowedKeys)->toContain('request_changes')
        ->and($approvedAllowedKeys)->toContain('reject')
        ->and($approvedAllowedKeys)->not->toContain('approve');
});
