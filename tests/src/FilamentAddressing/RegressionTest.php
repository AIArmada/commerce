<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Contracts\CountryAddressProfile;
use AIArmada\Addressing\Data\AddressHierarchyDefinition;
use AIArmada\Addressing\Data\AddressLevelDefinition;
use AIArmada\Addressing\Models\Address;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\City;
use AIArmada\Addressing\Models\PostalCode;
use AIArmada\Addressing\Models\State;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentAddressing\Exports\AddressExporter;
use AIArmada\FilamentAddressing\Exports\PostalCodeExporter;
use AIArmada\FilamentAddressing\Imports\PostalCodeImporter;
use AIArmada\FilamentAddressing\RelationManagers\AddressesRelationManager;
use AIArmada\FilamentAddressing\Resources\AddressCountryResource;
use AIArmada\FilamentAddressing\Resources\AddressResource\Pages\CreateAddress;
use AIArmada\FilamentAddressing\Resources\PostalCodeResource;
use AIArmada\FilamentAddressing\Resources\PostalCodeResource\Pages\CreatePostalCode;
use AIArmada\FilamentAddressing\Resources\PostalCodeResource\Pages\EditPostalCode;
use AIArmada\FilamentAddressing\Resources\PostalCodeResource\Pages\ListPostalCodes;
use AIArmada\FilamentAddressing\Schemas\AddressFormSchema;
use AIArmada\FilamentAddressing\Support\AddressingFilterOptions;
use AIArmada\FilamentAddressing\Tables\AddressAreaTable;
use AIArmada\FilamentAddressing\Tables\AddressCityTable;
use AIArmada\FilamentAddressing\Tables\AddressCountryTable;
use AIArmada\FilamentAddressing\Tables\AddressStateTable;
use AIArmada\FilamentAddressing\Tables\PostalCodeTable;
use Filament\Actions\Imports\Models\Import;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    app(SeedAddressCountriesAction::class)->execute();
});

it('creates addresses with the configured model override', function (): void {
    config()->set('filament-addressing.resources.addresses.model', RepairCustomAddress::class);

    $owner = User::factory()->create();
    $page = app(CreateAddress::class);

    $form = Mockery::mock(Schema::class);
    $form->shouldReceive('getRawState')->andReturn([]);

    $cachedSchemas = new ReflectionProperty($page, 'cachedSchemas');
    $cachedSchemas->setAccessible(true);
    $cachedSchemas->setValue($page, ['form' => $form]);

    $method = new ReflectionMethod(CreateAddress::class, 'handleRecordCreation');
    $method->setAccessible(true);

    try {
        $address = OwnerContext::withOwner(
            $owner,
            fn (): Address => $method->invoke($page, ['line1' => 'Override check', 'country_code' => 'MY']),
        );

        expect($address)->toBeInstanceOf(RepairCustomAddress::class)
            ->and($address->getAttribute('line1'))->toBe('Override check');
    } finally {
        config()->set('filament-addressing.resources.addresses.model', Address::class);
    }
});

it('resolves the address exporter model from config and scopes exports to the owner', function (): void {
    config()->set('filament-addressing.resources.addresses.model', RepairCustomAddress::class);

    try {
        expect(AddressExporter::getModel())->toBe(RepairCustomAddress::class);
    } finally {
        config()->set('filament-addressing.resources.addresses.model', Address::class);
    }

    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    $addressA = OwnerContext::withOwner($ownerA, fn (): Address => Address::query()->create([
        'line1' => 'Owner A export', 'country_code' => 'MY',
    ]));
    OwnerContext::withOwner($ownerB, fn (): Address => Address::query()->create([
        'line1' => 'Owner B export', 'country_code' => 'MY',
    ]));

    $ids = OwnerContext::withOwner(
        $ownerA,
        fn (): array => AddressExporter::modifyQuery(Address::query())->pluck('id')->all(),
    );

    expect($ids)->toEqual([$addressA->id]);
});

it('wires postcode import and export actions with working importer and exporter', function (): void {
    expect(config('filament-addressing.features.postal_code_import'))->toBeFalse();

    config()->set('filament-addressing.features.postal_code_import', true);
    config()->set('filament-addressing.features.postal_code_export', true);

    try {
        $page = app(ListPostalCodes::class);
        $method = new ReflectionMethod(ListPostalCodes::class, 'getHeaderActions');
        $method->setAccessible(true);
        $actions = collect($method->invoke($page))->keyBy(fn ($action): string => $action->getName());

        expect($actions->has('import'))->toBeTrue()
            ->and($actions->has('export'))->toBeTrue()
            ->and($actions->get('import')->getImporter())->toBe(PostalCodeImporter::class)
            ->and($actions->get('export')->getExporter())->toBe(PostalCodeExporter::class)
            ->and(PostalCodeExporter::getModel())->toBe(PostalCode::class);
    } finally {
        config()->set('filament-addressing.features.postal_code_import', false);
        config()->set('filament-addressing.features.postal_code_export', false);
    }
});

it('imports postcodes through the core action', function (): void {
    $importer = new PostalCodeImporter(new Import, [
        'country_code' => 'country_code',
        'code' => 'code',
        'source' => 'source',
        'source_id' => 'source_id',
        'area_source' => 'area_source',
        'area_source_id' => 'area_source_id',
        'relationship_type' => 'relationship_type',
        'is_primary' => 'is_primary',
        'metadata' => 'metadata',
    ], []);

    $importer([
        'country_code' => 'MY',
        'code' => '47500',
        'source' => 'repair-test',
        'source_id' => 'MY-47500',
        'area_source' => null,
        'area_source_id' => null,
        'relationship_type' => null,
        'is_primary' => false,
        'metadata' => '{"source":"import"}',
    ]);

    $postcode = PostalCode::query()->where('country_code', 'MY')->where('code', '47500')->firstOrFail();

    expect($postcode->metadata)->toBe(['source' => 'repair-test', 'source_id' => 'MY-47500']);
});

it('caches area filter option scans', function (): void {
    AddressingFilterOptions::forgetAreaOptions();
    Cache::flush();

    AddressArea::query()->create([
        'country_code' => 'MY', 'type' => 'state', 'level' => 1, 'name' => 'Selangor',
        'slug' => 'selangor', 'source' => 'test', 'source_id' => 'MY-10',
    ]);

    DB::enableQueryLog();
    DB::flushQueryLog();
    AddressingFilterOptions::areaTypes();
    $firstCallQueries = count(DB::getQueryLog());

    DB::flushQueryLog();
    AddressingFilterOptions::areaTypes();
    $secondCallQueries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($firstCallQueries)->toBeGreaterThan(0)
        ->and($secondCallQueries)->toBe(0)
        ->and(AddressingFilterOptions::areaTypes())->toBe(['state' => 'state']);

    AddressingFilterOptions::forgetAreaOptions();
    Cache::flush();
});

it('eager loads relation columns on every addressing table', function (): void {
    $areaQuery = AddressAreaTable::make(Table::make(Mockery::mock(HasTable::class)))
        ->applyQueryScopes(AddressArea::query());

    expect(array_keys($areaQuery->getEagerLoads()))
        ->toContain('names', 'roles', 'parent');

    $postcodeQuery = PostalCodeTable::make(Table::make(Mockery::mock(HasTable::class)))
        ->applyQueryScopes(PostalCode::query());

    expect(array_keys($postcodeQuery->getEagerLoads()))->toContain('areas');

    $countryQuery = AddressCountryTable::make(Table::make(Mockery::mock(HasTable::class)))
        ->applyQueryScopes(AddressCountry::query());

    expect(array_keys($countryQuery->getEagerLoads()))->toContain('currencies');

    $stateQuery = AddressStateTable::make(Table::make(Mockery::mock(HasTable::class)))
        ->applyQueryScopes(State::query());

    expect(array_keys($stateQuery->getEagerLoads()))->toContain('country');

    $cityQuery = AddressCityTable::make(Table::make(Mockery::mock(HasTable::class)))
        ->applyQueryScopes(City::query());

    expect(array_keys($cityQuery->getEagerLoads()))
        ->toContain('country', 'state');
});

it('returns no state options without a country and scopes them by country', function (): void {
    $malaysia = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();
    $singapore = AddressCountry::query()->where('iso2', 'SG')->firstOrFail();

    $selangor = State::query()->create(['country_id' => $malaysia->getKey(), 'name' => 'Selangor']);
    State::query()->create(['country_id' => $singapore->getKey(), 'name' => 'Singapore']);

    $field = collect(AddressFormSchema::make())
        ->first(fn ($component): bool => $component->getName() === 'state_id');

    $options = (new ReflectionProperty(Select::class, 'options'))->getValue($field);

    expect($options)->toBeInstanceOf(Closure::class)
        ->and($options(static fn (string $path): ?string => null))->toBe([])
        ->and($options(static fn (string $path): ?string => ''))->toBe([])
        ->and($options(static fn (string $path): string => 'MY'))->toBe([$selangor->getKey() => 'Selangor']);
});

it('escapes LIKE wildcards in area search', function (): void {
    config()->set('addressing.geography.providers', [RepairWildcardProfile::class]);

    $area = AddressArea::query()->create([
        'country_code' => 'MY', 'type' => 'locality', 'level' => 1, 'name' => 'Alpha',
        'slug' => 'alpha', 'source' => 'test', 'source_id' => 'alpha',
    ]);

    $field = collect(AddressFormSchema::make())
        ->first(fn ($component): bool => $component->getName() === 'area_assignments.wildcard_locality');
    $callback = (new ReflectionProperty($field, 'getSearchResultsUsing'))->getValue($field);
    $get = static fn (string $path): ?string => $path === 'country_code' ? 'MY' : null;

    expect($callback('%%%', $get))->toBe([])
        ->and($callback('Alpha', $get))->toBe([$area->getKey() => 'Alpha']);
});

it('gates postcode create and edit pages on read-only mode', function (): void {
    config()->set('filament-addressing.resources.postal_codes.read_only', true);

    try {
        expect(CreatePostalCode::canAccess())->toBeFalse()
            ->and(EditPostalCode::canAccess())->toBeFalse();

        config()->set('filament-addressing.resources.postal_codes.read_only', false);

        expect(CreatePostalCode::canAccess())->toBeTrue()
            ->and(EditPostalCode::canAccess())->toBeTrue();
    } finally {
        config()->set('filament-addressing.resources.postal_codes.read_only', false);
    }
});

it('scopes the addresses relation manager record select and row actions to the owner', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    $addressA = OwnerContext::withOwner($ownerA, fn (): Address => Address::query()->create([
        'line1' => 'Owner A attached', 'country_code' => 'MY',
    ]));
    $addressB = OwnerContext::withOwner($ownerB, fn (): Address => Address::query()->create([
        'line1' => 'Owner B attached', 'country_code' => 'MY',
    ]));

    $table = (new AddressesRelationManager)->table(Table::make(Mockery::mock(HasTable::class)));
    $headerActions = collect($table->getHeaderActions());
    $rowActions = collect($table->getActions());

    $attach = $headerActions->first(fn ($action): bool => $action->getName() === 'attach');
    $scopeCallback = (new ReflectionProperty($attach, 'modifyRecordSelectOptionsQueryUsing'))->getValue($attach);

    expect($scopeCallback)->toBeInstanceOf(Closure::class);

    $scopedIds = OwnerContext::withOwner(
        $ownerA,
        fn (): array => $scopeCallback(Address::query())->pluck('id')->all(),
    );

    expect($scopedIds)->toContain($addressA->id)->not->toContain($addressB->id);

    $edit = $rowActions->first(fn ($action): bool => $action->getName() === 'edit');
    $detach = $rowActions->first(fn ($action): bool => $action->getName() === 'detach');

    OwnerContext::withOwner($ownerA, function () use ($edit, $detach, $addressA, $addressB): void {
        expect($edit->record($addressA)->isVisible())->toBeTrue()
            ->and($edit->record($addressB)->isVisible())->toBeFalse()
            ->and($detach->record($addressA)->isVisible())->toBeTrue()
            ->and($detach->record($addressB)->isVisible())->toBeFalse()
            ->and(fn () => $edit->record($addressB)->callBefore())->toThrow(HttpException::class);
    });
});

it('requires two-letter alpha ISO2 codes', function (): void {
    $schema = AddressCountryResource::form(Schema::make(Mockery::mock(Component::class, HasSchemas::class)));
    $inputs = collect($schema->getComponents())
        ->flatMap(fn ($section): array => $section->getChildComponents())
        ->filter(fn ($component): bool => $component instanceof TextInput)
        ->keyBy(fn (TextInput $component): string => $component->getName());

    expect($inputs->get('iso2')->getMinLength())->toBe(2)
        ->and($inputs->get('iso2')->getMaxLength())->toBe(2)
        ->and($inputs->get('iso3')->getMinLength())->toBe(3)
        ->and($inputs->get('iso3')->getMaxLength())->toBe(3);
});

it('AUD#B1 offers only active areas on the postcode form', function (): void {
    AddressArea::query()->create([
        'country_code' => 'MY', 'type' => 'locality', 'level' => 1, 'name' => 'Active Area',
        'slug' => 'active-area', 'source' => 'test', 'source_id' => 'active-area', 'is_active' => true,
    ]);
    AddressArea::query()->create([
        'country_code' => 'MY', 'type' => 'locality', 'level' => 1, 'name' => 'Retired Area',
        'slug' => 'retired-area', 'source' => 'test', 'source_id' => 'retired-area', 'is_active' => false,
    ]);

    $schema = PostalCodeResource::form(Schema::make(Mockery::mock(Component::class, HasSchemas::class)));
    $areas = collect($schema->getComponents())
        ->flatMap(fn ($section): array => $section->getChildComponents())
        ->first(fn ($component): bool => $component instanceof Select && $component->getName() === 'areas');

    $modifyQuery = (new ReflectionProperty(Select::class, 'modifyRelationshipQueryUsing'))->getValue($areas);
    $query = $modifyQuery(AddressArea::query(), static fn (): string => 'MY');

    expect($query->pluck('name')->all())->toBe(['Active Area']);
});

class RepairCustomAddress extends Address {}

class RepairWildcardProfile implements CountryAddressProfile
{
    public function countryCode(): string
    {
        return 'MY';
    }

    public function addressHierarchies(): array
    {
        return [new AddressHierarchyDefinition('wildcard', 'Wildcard', [
            new AddressLevelDefinition(
                key: 'locality',
                label: 'Wildcard locality',
                kind: 'area',
                hierarchyType: 'wildcard',
                areaTypes: ['locality'],
                areaLevel: 1,
                assignmentRole: 'wildcard_locality',
            ),
        ])];
    }
}
