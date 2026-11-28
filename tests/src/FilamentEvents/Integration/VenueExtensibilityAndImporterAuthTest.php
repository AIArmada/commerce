<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Catalog\Models\EventOccurrence;
use AIArmada\Events\Features\Catalog\Models\EventSession;
use AIArmada\Events\Features\Registrations\Models\EventRegistration;
use AIArmada\Events\Features\Venues\Models\Venue;
use AIArmada\Events\Features\Venues\Models\VenueSpace;
use AIArmada\Events\Platform\ModelResolver;
use AIArmada\FilamentEvents\Actions\Exporter\VenueExporter;
use AIArmada\FilamentEvents\Actions\Importer\EventRegistrationImporter;
use AIArmada\FilamentEvents\Actions\Importer\EventSessionImporter;
use AIArmada\FilamentEvents\Actions\Importer\VenueImporter;
use AIArmada\FilamentEvents\Resources\EventRegistrationResource;
use AIArmada\FilamentEvents\Resources\EventSessionResource;
use AIArmada\FilamentEvents\Resources\VenueResource;
use AIArmada\FilamentEvents\Resources\VenueSpaceResource;
use Filament\Actions\ImportAction;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Models\Import;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;

final class ConfiguredCustomVenue extends Venue {}

final class ConfiguredCustomVenueSpace extends VenueSpace {}

final class TestVenueRelationManager extends RelationManager
{
    protected static string $relationship = 'spaces';
}

final class TestVenueSpaceRelationManager extends RelationManager
{
    protected static string $relationship = 'facilities';
}

final class DenyAllVenuePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Venue $venue): bool
    {
        return $venue->name === 'Editable Existing Venue';
    }
}

final class DenySessionImportPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, EventSession $session): bool
    {
        return false;
    }
}

final class DenyRegistrationImportPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, EventRegistration $registration): bool
    {
        return false;
    }
}

it('resolves configured venue models, navigation groups, and relation managers', function (): void {
    config()->set('events.models.venue', ConfiguredCustomVenue::class);
    config()->set('events.models.venue_space', ConfiguredCustomVenueSpace::class);
    config()->set('filament-events.navigation.group', 'Events');
    config()->set('filament-events.resources.navigation_group.venue', 'Places');
    config()->set('filament-events.resources.navigation_group.venue_space', 'Places');
    config()->set('filament-events.resources.venue_relation_managers', [
        TestVenueRelationManager::class,
        stdClass::class,
    ]);
    config()->set('filament-events.resources.venue_space_relation_managers', [
        TestVenueSpaceRelationManager::class,
        'MissingClass',
    ]);

    expect(ModelResolver::venueClass())->toBe(ConfiguredCustomVenue::class)
        ->and(ModelResolver::venueSpaceClass())->toBe(ConfiguredCustomVenueSpace::class)
        ->and(VenueResource::getModel())->toBe(ConfiguredCustomVenue::class)
        ->and(VenueSpaceResource::getModel())->toBe(ConfiguredCustomVenueSpace::class)
        ->and(VenueImporter::getModel())->toBe(ConfiguredCustomVenue::class)
        ->and(VenueExporter::getModel())->toBe(ConfiguredCustomVenue::class)
        ->and(VenueResource::getNavigationGroup())->toBe('Places')
        ->and(VenueSpaceResource::getNavigationGroup())->toBe('Places')
        ->and(VenueResource::getRelations())->toBe([TestVenueRelationManager::class])
        ->and(VenueSpaceResource::getRelations())->toBe([TestVenueSpaceRelationManager::class]);
});

it('enforces model policies on venue, session, and registration importers for create and update', function (): void {
    app()->bind(Authenticatable::class, User::class);

    $user = User::factory()->create();
    $this->actingAs($user);

    Gate::policy(Venue::class, DenyAllVenuePolicy::class);

    $venueImport = new Import;
    $venueImport->user()->associate($user);

    $venueImporter = new VenueImporter($venueImport, [
        'name' => 'name',
        'venue_type' => 'venue_type',
        'status' => 'status',
    ], []);

    expect(fn () => $venueImporter([
        'name' => 'Blocked Hall',
        'venue_type' => 'physical',
        'status' => 'active',
    ]))->toThrow(RowImportFailedException::class, 'not authorized');

    $existingVenue = Venue::query()->create([
        'name' => 'Immutable Existing Venue',
        'venue_type' => 'physical',
        'status' => 'active',
        'visibility' => 'public',
    ]);

    $updateMethod = new ReflectionMethod(VenueImporter::class, 'beforeUpdate');
    $recordProp = new ReflectionProperty(VenueImporter::class, 'record');
    $dataProp = new ReflectionProperty(VenueImporter::class, 'data');

    $existingVenue->name = 'Editable Existing Venue';
    $recordProp->setValue($venueImporter, $existingVenue);
    $dataProp->setValue($venueImporter, ['name' => 'Editable Existing Venue']);

    expect(fn () => $updateMethod->invoke($venueImporter))
        ->toThrow(RowImportFailedException::class, 'not authorized');

    Gate::policy(EventSession::class, DenySessionImportPolicy::class);

    $event = Event::factory()->create();
    $occurrence = EventOccurrence::factory()->create(['event_id' => $event->getKey()]);
    $sessionImporter = new EventSessionImporter($venueImport, [
        'event_id' => 'event_id',
        'event_occurrence_id' => 'event_occurrence_id',
        'title' => 'title',
        'starts_at' => 'starts_at',
        'ends_at' => 'ends_at',
        'timezone' => 'timezone',
        'status' => 'status',
    ], []);

    expect(fn () => $sessionImporter([
        'event_id' => $event->getKey(),
        'event_occurrence_id' => $occurrence->getKey(),
        'title' => 'Unauthorized Session',
        'starts_at' => now()->toIso8601String(),
        'ends_at' => now()->addHour()->toIso8601String(),
        'timezone' => 'UTC',
        'status' => 'scheduled',
    ]))->toThrow(RowImportFailedException::class, 'not authorized');

    Gate::policy(EventRegistration::class, DenyRegistrationImportPolicy::class);

    $registrationImporter = new EventRegistrationImporter($venueImport, [
        'event_id' => 'event_id',
        'registration_type' => 'registration_type',
        'status' => 'status',
        'source' => 'source',
    ], []);

    expect(fn () => $registrationImporter([
        'event_id' => $event->getKey(),
        'registration_type' => 'individual',
        'status' => 'confirmed',
        'source' => 'import',
    ]))->toThrow(RowImportFailedException::class, 'not authorized');
});

it('authorizes and hides import actions when resource create policy denies access', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    Gate::policy(Venue::class, DenyAllVenuePolicy::class);
    Gate::policy(EventSession::class, DenySessionImportPolicy::class);
    Gate::policy(EventRegistration::class, DenyRegistrationImportPolicy::class);

    /** @var HasTable $livewire */
    $livewire = Mockery::mock(HasTable::class);

    foreach ([VenueResource::class, EventSessionResource::class, EventRegistrationResource::class] as $resource) {
        $table = $resource::table(Table::make($livewire));
        $importAction = collect($table->getHeaderActions())
            ->first(static fn ($action): bool => $action instanceof ImportAction);

        expect($importAction)->toBeInstanceOf(ImportAction::class)
            ->and($importAction?->isVisible())->toBeFalse()
            ->and($importAction?->isAuthorized())->toBeFalse();
    }
});
