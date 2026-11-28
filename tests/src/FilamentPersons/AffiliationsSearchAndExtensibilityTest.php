<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScopeOverride;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
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
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema as SchemaFacade;

final class ConfiguredCustomPerson extends Person {}

final class TestPersonExtraRelationManager extends RelationManager
{
    protected static string $relationship = 'names';
}

final class OwnerScopedInstitution extends Model
{
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected $table = 'owner_scoped_institutions';

    protected $fillable = ['name'];

    protected static string $ownerScopeConfigKey = 'testing.institution_owner';
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

it('uses configured institution label and search columns with one query', function (): void {
    config()->set('persons.models.institution', User::class);
    config()->set('filament-persons.resources.institution.label_column', 'email');
    config()->set('filament-persons.resources.institution.search_columns', ['email']);

    $matched = User::factory()->create([
        'name' => 'Unrelated Name',
        'email' => 'custom-label-' . uniqid() . '@example.com',
    ]);
    User::factory()->create([
        'name' => 'Custom Label Name',
        'email' => 'other-' . uniqid() . '@example.com',
    ]);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $options = AffiliationsRelationManager::getInstitutionOptions('custom-label');
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($options)->toBe([$matched->getKey() => $matched->email])
        ->and($queries)->toHaveCount(1);
});

it('treats like wildcards and the escape character literally', function (): void {
    config()->set('persons.models.institution', User::class);
    config()->set('filament-persons.resources.institution.label_column', 'name');
    config()->set('filament-persons.resources.institution.search_columns', null);

    $percent = User::factory()->create(['name' => 'Rate 100%']);
    $underscore = User::factory()->create(['name' => 'Code A_B']);
    $bang = User::factory()->create(['name' => 'Bang! Academy']);
    User::factory()->create(['name' => 'Plain 100X']);

    expect(AffiliationsRelationManager::getInstitutionOptions('%'))->toBe([$percent->getKey() => 'Rate 100%'])
        ->and(AffiliationsRelationManager::getInstitutionOptions('_'))->toBe([$underscore->getKey() => 'Code A_B'])
        ->and(AffiliationsRelationManager::getInstitutionOptions('!'))->toBe([$bang->getKey() => 'Bang! Academy']);
});

it('orders institution results by label and primary key before applying the cap', function (): void {
    config()->set('persons.models.institution', User::class);
    config()->set('filament-persons.resources.institution.label_column', 'name');
    config()->set('filament-persons.resources.institution.search_columns', null);

    $label = 'Duplicate Institute';
    User::factory()->count(5)->create(['name' => $label]);

    $expected = User::query()
        ->orderBy('name')
        ->orderBy('id')
        ->limit(3)
        ->get()
        ->mapWithKeys(static fn (User $user): array => [$user->getKey() => $user->name])
        ->all();

    expect(AffiliationsRelationManager::getInstitutionOptions(null, 3))->toBe($expected);
});

it('keeps institution options owner-scoped and honors include-global overrides', function (): void {
    SchemaFacade::create('owner_scoped_institutions', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->nullableMorphs('owner');
        $table->string('name');
        $table->timestamps();
    });

    config()->set('persons.models.institution', OwnerScopedInstitution::class);
    config()->set('testing.institution_owner.enabled', true);
    config()->set('testing.institution_owner.include_global', false);
    config()->set('testing.institution_owner.auto_assign_on_create', true);

    $firstOwner = User::create(['name' => 'Institution Owner A', 'email' => 'institution-a-' . uniqid() . '@example.com', 'password' => 'secret']);
    $secondOwner = User::create(['name' => 'Institution Owner B', 'email' => 'institution-b-' . uniqid() . '@example.com', 'password' => 'secret']);

    $global = OwnerContext::withOwner(null, fn (): OwnerScopedInstitution => OwnerScopedInstitution::create(['name' => 'Global Institute']));
    $first = OwnerContext::withOwner($firstOwner, fn (): OwnerScopedInstitution => OwnerScopedInstitution::create(['name' => 'Alpha Institute']));
    OwnerContext::withOwner($secondOwner, fn (): OwnerScopedInstitution => OwnerScopedInstitution::create(['name' => 'Beta Institute']));

    expect(OwnerContext::withOwner($firstOwner, fn (): array => AffiliationsRelationManager::getInstitutionOptions()))
        ->toBe([$first->getKey() => 'Alpha Institute']);

    config()->set('testing.institution_owner.include_global', true);

    $withGlobal = OwnerContext::withOwner($firstOwner, fn (): array => AffiliationsRelationManager::getInstitutionOptions());

    expect($withGlobal)->toBe([
        $first->getKey() => 'Alpha Institute',
        $global->getKey() => 'Global Institute',
    ]);

    $withoutGlobal = OwnerContext::withOwner(
        $firstOwner,
        fn (): array => OwnerScopeOverride::withoutIncludeGlobal(
            fn (): array => AffiliationsRelationManager::getInstitutionOptions(),
        ),
    );

    expect($withoutGlobal)->toBe([$first->getKey() => 'Alpha Institute']);
});

it('rejects non-column institution label and search configuration', function (): void {
    config()->set('persons.models.institution', User::class);

    config()->set('filament-persons.resources.institution.label_column', 'name; drop table users');
    expect(fn (): array => AffiliationsRelationManager::getInstitutionOptions())
        ->toThrow(InvalidArgumentException::class);

    config()->set('filament-persons.resources.institution.label_column', 'name');
    config()->set('filament-persons.resources.institution.search_columns', ['users.name']);
    expect(fn (): array => AffiliationsRelationManager::getInstitutionOptions())
        ->toThrow(InvalidArgumentException::class);
});
