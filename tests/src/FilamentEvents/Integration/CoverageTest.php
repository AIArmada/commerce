<?php

declare(strict_types=1);

use AIArmada\FilamentEvents\FilamentEventsPlugin;
use AIArmada\FilamentEvents\FilamentEventsServiceProvider;
use AIArmada\FilamentEvents\Resources\EventResource;
use AIArmada\FilamentEvents\Resources\EventResource\Pages\EditEvent;
use AIArmada\FilamentEvents\Resources\EventResource\Pages\ListEvents;
use AIArmada\FilamentEvents\Resources\EventResource\Pages\ViewEvent;
use AIArmada\FilamentEvents\Resources\EventResource\RelationManagers\OccurrencesRelationManager;
use AIArmada\FilamentEvents\Resources\EventSeriesResource;
use AIArmada\FilamentEvents\Resources\EventSeriesResource\Pages\EditEventSeries;
use AIArmada\FilamentEvents\Resources\EventSeriesResource\Pages\ListEventSeries;
use AIArmada\FilamentEvents\Resources\EventSeriesResource\Pages\ViewEventSeries;
use AIArmada\FilamentEvents\Resources\OccurrenceResource;
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
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

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
            RegistrationResource::class,
        );
});

it('loads the filament events package config', function (): void {
    $this->app->register(FilamentEventsServiceProvider::class);

    expect(config('filament-events.navigation.group'))->toBe('Events');
});

it('builds event resource schemas and tables', function (): void {
    foreach ([EventSeriesResource::class, EventResource::class, VenueResource::class, OccurrenceResource::class, RegistrationResource::class] as $resource) {
        expect($resource::form(Schema::make()))->toBeInstanceOf(Schema::class);
        expect($resource::table(makeFilamentEventsTable()))->toBeInstanceOf(Table::class);
        expect($resource::infolist(Schema::make()))->toBeInstanceOf(Schema::class);
        expect($resource::getPages())->not->toBeEmpty();
    }

    expect(EventResource::getRelations())->toContain(OccurrencesRelationManager::class);
    expect(OccurrenceResource::getRelations())->toContain(RegistrationsRelationManager::class);
});

it('builds event relation manager schemas and tables', function (): void {
    $occurrences = app(OccurrencesRelationManager::class);
    $registrations = app(RegistrationsRelationManager::class);

    expect($occurrences->form(Schema::make()))->toBeInstanceOf(Schema::class);
    expect($occurrences->table(makeFilamentEventsTable()))->toBeInstanceOf(Table::class);

    expect($registrations->form(Schema::make()))->toBeInstanceOf(Schema::class);
    expect($registrations->table(makeFilamentEventsTable()))->toBeInstanceOf(Table::class);
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
