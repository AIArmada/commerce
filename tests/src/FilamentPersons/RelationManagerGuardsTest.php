<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Models\Language;
use AIArmada\FilamentPersons\Resources\CredentialDefinitionResource;
use AIArmada\FilamentPersons\Resources\PersonResource;
use AIArmada\FilamentPersons\Resources\PersonResource\RelationManagers\AffiliationsRelationManager;
use AIArmada\FilamentPersons\Resources\PersonResource\RelationManagers\CredentialAssignmentsRelationManager;
use AIArmada\FilamentPersons\Resources\PersonResource\RelationManagers\NamesRelationManager;
use AIArmada\FilamentPersons\Resources\PersonResource\RelationManagers\TitleAssignmentsRelationManager;
use AIArmada\FilamentPersons\Resources\TitleIssuerResource;
use AIArmada\FilamentPersons\Resources\TitleIssuerResource\Pages\CreateTitleIssuer;
use AIArmada\FilamentPersons\Resources\TitleIssuerResource\Pages\EditTitleIssuer;
use AIArmada\FilamentPersons\Resources\TitleResource;
use AIArmada\Persons\Enums\AssignmentStatus;
use AIArmada\Persons\Models\CredentialAssignment;
use AIArmada\Persons\Models\CredentialDefinition;
use AIArmada\Persons\Models\Person;
use AIArmada\Persons\Models\Title;
use AIArmada\Persons\Models\TitleAssignment;
use AIArmada\Persons\Models\TitleCategory;
use Filament\Actions\EditAction;
use Filament\Schemas\Schema;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $base = dirname(__DIR__, 3) . '/packages/persons/database/migrations/';
    $migrations = glob($base . '*.php') ?: [];

    if ($migrations === []) {
        throw new RuntimeException('Persons migrations not found for test setup.');
    }

    foreach ($migrations as $migration) {
        (require $migration)->up();
    }
});

afterEach(function (): void {
    Mockery::close();
});

function buildRelationManagerTable(object $manager): Table
{
    /** @var HasTable $livewire */
    $livewire = Mockery::mock(HasTable::class);

    return $manager->table(Table::make($livewire));
}

function relationManagerFormFields(object $manager): array
{
    $components = $manager->form(Schema::make())->getComponents();

    return array_map(static fn ($component): string => $component->getName(), $components);
}

function seedTitle(string $name = 'Doctor'): Title
{
    $category = TitleCategory::query()->firstOrCreate(
        ['code' => 'academic'],
        ['name' => 'Academic', 'sort_order' => 1]
    );

    return Title::query()->create([
        'category_id' => $category->id,
        'name' => $name,
        'usage_position' => 'before_name',
        'sort_order' => 1,
    ]);
}

it('shares assignment forms between create and edit actions', function (): void {
    $titleManager = new TitleAssignmentsRelationManager;
    $credentialManager = new CredentialAssignmentsRelationManager;
    $namesManager = new NamesRelationManager;
    $affiliationsManager = new AffiliationsRelationManager;

    expect(relationManagerFormFields($titleManager))
        ->toContain('title_id', 'date_awarded', 'date_expired', 'status')
        ->and(relationManagerFormFields($credentialManager))
        ->toContain('credential_id', 'registration_number', 'date_obtained', 'date_expired', 'status')
        ->and(relationManagerFormFields($namesManager))
        ->toContain('name_type', 'full_name', 'language_code', 'is_primary')
        ->and(relationManagerFormFields($affiliationsManager))
        ->toContain('affiliation_type', 'institution_id', 'joined_at', 'left_at', 'is_primary');

    foreach ([$titleManager, $credentialManager, $namesManager, $affiliationsManager] as $manager) {
        $actions = buildRelationManagerTable($manager)->getActions();

        $editActions = array_filter(
            $actions,
            static fn ($action): bool => $action instanceof EditAction
        );

        expect($editActions)->not->toBeEmpty();
    }
});

it('rejects inconsistent assignment dates and duplicate assignments', function (): void {
    $person = Person::query()->create(['name' => 'Jane Doe']);
    $title = seedTitle();

    $manager = new TitleAssignmentsRelationManager;
    $manager->ownerRecord = $person;

    $assert = new ReflectionMethod(TitleAssignmentsRelationManager::class, 'assertAssignmentData');

    expect(fn () => $assert->invoke($manager, [
        'title_id' => $title->id,
        'date_awarded' => '2024-01-10',
        'date_expired' => '2023-01-10',
    ]))->toThrow(ValidationException::class, 'on or after the awarded date');

    $person->titleAssignments()->create([
        'title_id' => $title->id,
        'status' => AssignmentStatus::Active,
    ]);

    expect(fn () => $assert->invoke($manager, ['title_id' => $title->id]))
        ->toThrow(ValidationException::class, 'already assigned');

    $credentialManager = new CredentialAssignmentsRelationManager;
    $credentialManager->ownerRecord = $person;

    $credentialAssert = new ReflectionMethod(CredentialAssignmentsRelationManager::class, 'assertAssignmentData');
    $definition = CredentialDefinition::query()->create([
        'name' => 'Medical License',
        'credential_type' => 'professional_license',
    ]);

    expect(fn () => $credentialAssert->invoke($credentialManager, [
        'credential_id' => $definition->id,
        'date_obtained' => '2024-01-10',
        'date_expired' => '2023-01-10',
    ]))->toThrow(ValidationException::class, 'on or after the obtained date');

    $person->credentialAssignments()->create([
        'credential_id' => $definition->id,
        'status' => AssignmentStatus::Active,
    ]);

    expect(fn () => $credentialAssert->invoke($credentialManager, ['credential_id' => $definition->id]))
        ->toThrow(ValidationException::class, 'already assigned');
});

it('allows saving an assignment unchanged without tripping the duplicate guard', function (): void {
    $person = Person::query()->create(['name' => 'John Doe']);
    $title = seedTitle('Professor');

    /** @var TitleAssignment $assignment */
    $assignment = $person->titleAssignments()->create([
        'title_id' => $title->id,
        'status' => AssignmentStatus::Active,
    ]);

    $manager = new TitleAssignmentsRelationManager;
    $manager->ownerRecord = $person;

    $assert = new ReflectionMethod(TitleAssignmentsRelationManager::class, 'assertAssignmentData');
    $assert->invoke($manager, ['title_id' => $title->id], $assignment->getKey());

    $credential = CredentialDefinition::query()->create([
        'name' => 'Engineering License',
        'credential_type' => 'professional_license',
    ]);

    /** @var CredentialAssignment $credentialAssignment */
    $credentialAssignment = $person->credentialAssignments()->create([
        'credential_id' => $credential->id,
        'status' => AssignmentStatus::Active,
    ]);

    $credentialManager = new CredentialAssignmentsRelationManager;
    $credentialManager->ownerRecord = $person;

    $credentialAssert = new ReflectionMethod(CredentialAssignmentsRelationManager::class, 'assertAssignmentData');
    $credentialAssert->invoke($credentialManager, ['credential_id' => $credential->id], $credentialAssignment->getKey());

    expect(true)->toBeTrue();
});

it('resolves institution options from the configured institution model', function (): void {
    config()->set('persons.models.institution', User::class);

    $university = User::factory()->create(['name' => 'National University']);

    $options = AffiliationsRelationManager::getInstitutionOptions();

    expect($options)->not->toBe([])
        ->and($options[$university->getKey()] ?? null)->toBe('National University')
        ->and(AffiliationsRelationManager::getInstitutionLabel($university->getKey()))->toBe('National University');

    config()->set('persons.models.institution', null);

    expect(AffiliationsRelationManager::getInstitutionOptions())->toBe([]);
});

it('guards title issuer institution references with validation errors', function (): void {
    config()->set('persons.models.institution', User::class);

    $institution = User::factory()->create();

    $create = new ReflectionMethod(CreateTitleIssuer::class, 'mutateFormDataBeforeCreate');
    $edit = new ReflectionMethod(EditTitleIssuer::class, 'mutateFormDataBeforeSave');

    expect($create->invoke(new CreateTitleIssuer, ['institution_id' => $institution->getKey()]))
        ->toBe(['institution_id' => $institution->getKey()])
        ->and($edit->invoke(new EditTitleIssuer, ['institution_id' => $institution->getKey()]))
        ->toBe(['institution_id' => $institution->getKey()])
        ->and($create->invoke(new CreateTitleIssuer, ['issuer_name' => 'Board']))
        ->toBe(['issuer_name' => 'Board']);

    expect(fn () => $create->invoke(new CreateTitleIssuer, ['institution_id' => (string) Str::uuid()]))
        ->toThrow(ValidationException::class);
});

it('caches the language list instead of querying per form render', function (): void {
    Cache::flush();

    Language::query()->create(['code' => 'en', 'name' => 'English']);
    Language::query()->create(['code' => 'ms', 'name' => 'Malay']);

    DB::enableQueryLog();
    $first = NamesRelationManager::getLanguageOptions();
    $queriesAfterFirst = count(DB::getQueryLog());

    $second = NamesRelationManager::getLanguageOptions();
    $queriesAfterSecond = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($first)->toBe(['en' => 'English', 'ms' => 'Malay'])
        ->and($second)->toBe($first)
        ->and($queriesAfterFirst)->toBe(1)
        ->and($queriesAfterSecond)->toBe(1);
});

it('denies identity resource access without granted abilities', function (): void {
    $person = new Person;

    foreach ([
        PersonResource::class,
        TitleResource::class,
        TitleIssuerResource::class,
        CredentialDefinitionResource::class,
    ] as $resource) {
        expect($resource::canViewAny())->toBeFalse()
            ->and($resource::canView($person))->toBeFalse()
            ->and($resource::canCreate())->toBeFalse()
            ->and($resource::canEdit($person))->toBeFalse()
            ->and($resource::canDelete($person))->toBeFalse()
            ->and($resource::shouldRegisterNavigation())->toBeFalse();
    }

    Gate::define('person.viewAny', static fn (): bool => true);
    $this->be(User::factory()->create());

    expect(PersonResource::canViewAny())->toBeTrue()
        ->and(PersonResource::shouldRegisterNavigation())->toBeTrue()
        ->and(TitleResource::canViewAny())->toBeFalse();
});

it('skips the placement query until category and position are selected', function (): void {
    DB::enableQueryLog();
    $blankMessage = TitleResource::placementGuidance(null, null, 2);
    $blankQueries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($blankMessage)->toContain('Select a category')
        ->and($blankQueries)->toBe([]);

    $category = TitleCategory::query()->create(['code' => 'royal', 'name' => 'Royal']);
    $title = seedTitle('Duke');
    $title->category_id = $category->id;
    $title->usage_position = 'after_name';
    $title->sort_order = 2;
    $title->save();

    expect(TitleResource::placementGuidance($category->id, 'after_name', 2))->toContain('already occupied')
        ->and(TitleResource::placementGuidance($category->id, 'after_name', 3))->toContain('shift later titles')
        ->and(TitleResource::placementGuidance($category->id, 'after_name', 0))->toContain('1 or higher');
});
