<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentPersons\FilamentPersonsServiceProvider;
use AIArmada\FilamentPersons\Resources\PersonResource;
use AIArmada\FilamentPersons\Resources\PersonResource\RelationManagers\AffiliationsRelationManager;
use AIArmada\Persons\Enums\AffiliationType;
use AIArmada\Persons\Features\Affiliations\Actions\SaveAffiliation;
use AIArmada\Persons\Models\Person;
use AIArmada\Persons\PersonsServiceProvider;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

final class ConfiguredCustomPerson extends Person {}

final class TestPersonExtraRelationManager extends RelationManager
{
    protected static string $relationship = 'names';
}

beforeEach(function (): void {
    $this->app->register(PersonsServiceProvider::class);
    $this->app->register(FilamentPersonsServiceProvider::class);

    $base = dirname(__DIR__, 3) . '/packages/persons/database/migrations/';
    $migrations = glob($base . '*.php') ?: [];

    foreach ($migrations as $migration) {
        (require $migration)->up();
    }
});

it('filters institution search results and resolves labels with single-row queries', function (): void {
    config()->set('persons.models.institution', User::class);

    $national = User::factory()->create(['name' => 'National Academy']);
    User::factory()->create(['name' => 'Regional Institute']);

    $filtered = AffiliationsRelationManager::getInstitutionOptions('National', 25);

    expect($filtered)->toBe([
        $national->getKey() => 'National Academy',
    ]);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $label = AffiliationsRelationManager::getInstitutionLabel($national->getKey());
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($label)->toBe('National Academy')
        ->and($queries)->toHaveCount(1)
        ->and($queries[0]['query'])->not->toContain('limit 500');
});

it('uses searchable async institution select and eager-loads institutions in table with position field', function (): void {
    config()->set('persons.models.institution', User::class);

    $institution = User::factory()->create(['name' => 'Science Guild']);
    $person = Person::query()->create(['name' => 'Dr. Ahmad']);
    app(SaveAffiliation::class)->create($person, [
        'affiliation_type' => AffiliationType::Advisor,
        'position' => 'Senior Fellow',
        'institution_id' => $institution->getKey(),
        'is_primary' => true,
    ]);

    $manager = new AffiliationsRelationManager;
    $manager->ownerRecord = $person;

    $formComponents = collect($manager->form(Schema::make())->getComponents());
    /** @var Select|null $institutionField */
    $institutionField = $formComponents->first(static fn ($component): bool => $component->getName() === 'institution_id');

    expect($institutionField)->toBeInstanceOf(Select::class)
        ->and($institutionField?->isSearchable())->toBeTrue()
        ->and($institutionField?->getSearchResults('Science'))->toBe([$institution->getKey() => 'Science Guild'])
        ->and($formComponents->map(static fn ($component): string => $component->getName())->all())->toContain('position');

    /** @var HasTable $livewire */
    $livewire = Mockery::mock(HasTable::class);
    $table = $manager->table(Table::make($livewire));

    expect($table->getColumn('position'))->not->toBeNull();

    $affiliation = $person->affiliations()->with('institution')->firstOrFail();
    expect($affiliation->position)->toBe('Senior Fellow');
});

it('resolves configured person model and relation managers on PersonResource', function (): void {
    config()->set('persons.models.person', ConfiguredCustomPerson::class);
    config()->set('filament-persons.resources.person_relation_managers', [
        TestPersonExtraRelationManager::class,
        'NonExistentClass',
    ]);

    expect(PersonResource::getModel())->toBe(ConfiguredCustomPerson::class)
        ->and(PersonResource::getRelations())->toContain(TestPersonExtraRelationManager::class)
        ->and(PersonResource::getRelations())->not->toContain('NonExistentClass');
});
