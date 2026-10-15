<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Contracts\CountryAddressProfile;
use AIArmada\Addressing\Data\AddressHierarchyDefinition;
use AIArmada\Addressing\Data\AddressLevelDefinition;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaPostalCode;
use AIArmada\Addressing\Models\AddressAreaRelationship;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\PostalCode;
use AIArmada\Addressing\Models\State;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAddressing\RelationManagers\AreaAliasesRelationManager;
use AIArmada\FilamentAddressing\RelationManagers\AreaPostalCodesRelationManager;
use AIArmada\FilamentAddressing\RelationManagers\AreaRolesRelationManager;
use AIArmada\FilamentAddressing\RelationManagers\AreaStateLinksRelationManager;
use AIArmada\FilamentAddressing\RelationManagers\ChildAreasRelationManager;
use AIArmada\FilamentAddressing\RelationManagers\IncomingAreaRelationshipsRelationManager;
use AIArmada\FilamentAddressing\RelationManagers\OutgoingAreaRelationshipsRelationManager;
use AIArmada\FilamentAddressing\Resources\AddressAreaResource;
use AIArmada\FilamentAddressing\Resources\AddressAreaResource\Pages\EditAddressArea;
use AIArmada\FilamentAddressing\Resources\AddressAreaResource\Pages\ListAddressAreas;
use AIArmada\FilamentAddressing\Resources\AddressAreaResource\Pages\ViewAddressArea;
use AIArmada\FilamentAddressing\Schemas\AddressAreaFormSchema;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use Livewire\Component as LivewireComponent;
use Livewire\Livewire;

final class AreaAdminCapabilitiesTestPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, AddressArea $area): bool
    {
        return $area->name !== 'Private parent';
    }
}

final class AreaAdminCapabilitiesPanelUser extends User implements FilamentUser
{
    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}

final class AreaAdminSchemaHost extends LivewireComponent implements HasSchemas
{
    use InteractsWithSchemas;

    public ?array $data = [];

    public function render()
    {
        return view('livewire.placeholder');
    }
}

it('exposes area reference relationships on the detail page', function (): void {
    $page = app(ViewAddressArea::class);
    $page->record = AddressArea::factory()->create();
    $method = new ReflectionMethod(ViewAddressArea::class, 'getRelationManagers');
    $method->setAccessible(true);

    expect($method->invoke($page))->toEqualCanonicalizing([
        ChildAreasRelationManager::class,
        AreaAliasesRelationManager::class,
        AreaRolesRelationManager::class,
        OutgoingAreaRelationshipsRelationManager::class,
        IncomingAreaRelationshipsRelationManager::class,
        AreaStateLinksRelationManager::class,
        AreaPostalCodesRelationManager::class,
    ]);
});

it('configures manual alias add, edit, and delete actions on the area relation manager', function (): void {
    $manager = new AreaAliasesRelationManager;
    $manager->ownerRecord = AddressArea::factory()->create();
    $table = $manager->table(Table::make(Mockery::mock(HasTable::class)));

    expect(array_map(static fn ($action): string => $action->getName(), $table->getHeaderActions()))->toContain('addAlias')
        ->and(array_map(static fn ($action): string => $action->getName(), $table->getActions()))->toContain('editAlias', 'deleteAlias');
});

it('mounts alias actions and protects provider-owned aliases in the area relation manager', function (): void {
    $this->actingAs(User::factory()->create());
    Filament::getCurrentPanel()?->registerResources([AddressAreaResource::class]);
    Gate::policy(AddressArea::class, AreaAdminCapabilitiesTestPolicy::class);
    $originalReadOnly = config('filament-addressing.resources.areas.read_only');
    config()->set('filament-addressing.resources.areas.read_only', false);

    $area = AddressArea::factory()->create();
    expect(AddressAreaResource::isReadOnly())->toBeFalse()
        ->and(AddressAreaResource::canEdit($area))->toBeTrue();

    Livewire::test(AreaAliasesRelationManager::class, [
        'ownerRecord' => $area,
        'pageClass' => ViewAddressArea::class,
    ])->callTableAction('addAlias', data: [
        'name' => 'Admin alias',
        'name_type' => 'alternative',
        'is_preferred' => false,
    ])->assertHasNoActionErrors();

    $manualAlias = $area->names()->where('name', 'Admin alias')->firstOrFail();

    Livewire::test(AreaAliasesRelationManager::class, [
        'ownerRecord' => $area,
        'pageClass' => ViewAddressArea::class,
    ])->callTableAction('editAlias', $manualAlias, data: [
        'name' => 'Admin alias updated',
        'name_type' => 'short',
        'is_preferred' => true,
    ])->assertHasNoTableActionErrors();

    expect($manualAlias->fresh()->name)->toBe('Admin alias updated')
        ->and($manualAlias->fresh()->is_preferred)->toBeTrue();

    Livewire::test(AreaAliasesRelationManager::class, [
        'ownerRecord' => $area,
        'pageClass' => ViewAddressArea::class,
    ])->callTableAction('deleteAlias', $manualAlias)
        ->assertHasNoTableActionErrors();

    expect($manualAlias->fresh())->toBeNull();

    $providerAlias = $area->names()->create([
        'name' => 'Provider alias',
        'source' => 'provider',
    ]);

    Livewire::test(AreaAliasesRelationManager::class, [
        'ownerRecord' => $area,
        'pageClass' => ViewAddressArea::class,
    ])->assertTableActionHidden('editAlias', $providerAlias)
        ->assertTableActionHidden('deleteAlias', $providerAlias);

    config()->set('filament-addressing.resources.areas.read_only', $originalReadOnly);
});

it('shows active state and source identity in the area table', function (): void {
    $table = AddressAreaResource::table(Table::make(Mockery::mock(HasTable::class)));

    expect($table->getColumns())->toHaveKeys(['is_active', 'source_id'])
        ->and(array_keys($table->getFilters()))->toContain('is_active');
});

it('links writable area list and view pages to create and edit workflows', function (): void {
    $this->actingAs(User::factory()->create());
    Filament::getCurrentPanel()?->registerResources([AddressAreaResource::class]);
    $originalReadOnly = config('filament-addressing.resources.areas.read_only');
    config()->set('filament-addressing.resources.areas.read_only', false);

    try {
        $area = AddressArea::factory()->create();

        Livewire::test(ListAddressAreas::class)
            ->assertActionVisible('create');

        Livewire::test(ViewAddressArea::class, ['record' => $area->getRouteKey()])
            ->assertActionVisible('edit');
    } finally {
        config()->set('filament-addressing.resources.areas.read_only', $originalReadOnly);
    }
});

it('serves the area create URL instead of treating create as a record key', function (): void {
    $storedUser = User::factory()->create();
    $panelUser = new AreaAdminCapabilitiesPanelUser;
    $panelUser->setRawAttributes($storedUser->getAttributes(), true);
    $panelUser->exists = true;
    $this->actingAs($panelUser);
    Filament::getCurrentPanel()?->registerResources([AddressAreaResource::class]);
    Gate::policy(AddressArea::class, AreaAdminCapabilitiesTestPolicy::class);
    $originalReadOnly = config('filament-addressing.resources.areas.read_only');
    config()->set('filament-addressing.resources.areas.read_only', false);

    try {
        $response = $this->get(AddressAreaResource::getUrl('create'));
        $response->assertOk();
    } finally {
        config()->set('filament-addressing.resources.areas.read_only', $originalReadOnly);
    }
});

it('shows provider hierarchy choices after country selection without choosing among parallel hierarchies', function (): void {
    app(SeedAddressCountriesAction::class)->execute();
    $profile = new class implements CountryAddressProfile
    {
        public function countryCode(): string
        {
            return 'MY';
        }

        public function addressHierarchies(): array
        {
            return [
                new AddressHierarchyDefinition('postal', 'Postal hierarchy', [new AddressLevelDefinition('postcode-area', 'Postcode area', 'area')]),
                new AddressHierarchyDefinition('administrative', 'Administrative hierarchy', [new AddressLevelDefinition('district', 'District', 'area')]),
            ];
        }
    };
    config()->set('addressing.geography.providers', [get_class($profile)]);
    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();
    $components = AddressAreaFormSchema::form(Schema::make(new AreaAdminSchemaHost))->getComponents();
    $findComponents = function (array $components, string $name) use (&$findComponents): array {
        $matches = [];

        foreach ($components as $component) {
            if (method_exists($component, 'getName') && $component->getName() === $name) {
                $matches[] = $component;
            }

            if (method_exists($component, 'getChildComponents')) {
                $matches = [...$matches, ...$findComponents($component->getChildComponents(), $name)];
            }
        }

        return $matches;
    };
    $countryField = $findComponents($components, 'country_id')[0];
    $hierarchyOptions = (new ReflectionMethod(AddressAreaFormSchema::class, 'hierarchyOptions'));
    $hierarchyOptions->setAccessible(true);
    $updatedCallbacks = (new ReflectionProperty($countryField, 'afterStateUpdated'))->getValue($countryField);
    $state = ['parent_id' => 'old-parent', 'hierarchy_type' => 'postal'];
    $set = static function (string $path, mixed $value) use (&$state): void {
        $state[$path] = $value;
    };

    $updatedCallbacks[0]((string) $country->getKey(), $set);

    expect($hierarchyOptions->invoke(null, (string) $country->getKey()))
        ->toBe([
            'postal' => 'Postal hierarchy',
            'administrative' => 'Administrative hierarchy',
        ])
        ->and($state['parent_id'])->toBeNull()
        ->and($state['hierarchy_type'])->toBeNull();
});

it('renders existing area state and postcode links when sibling resources are disabled', function (): void {
    app(SeedAddressCountriesAction::class)->execute();
    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();
    $area = AddressArea::factory()->create(['country_code' => 'MY']);
    $state = State::factory()->create([
        'country_id' => $country->getKey(),
        'country_code' => 'MY',
        'name' => 'Area link state',
    ]);
    $postcode = PostalCode::factory()->create(['country_code' => 'MY', 'code' => '88321']);
    $stateLink = AddressAreaStateLink::query()->create([
        'address_area_id' => $area->getKey(),
        'state_id' => $state->getKey(),
        'hierarchy_type' => 'administrative',
        'metadata' => ['source' => 'test'],
    ]);
    AddressAreaPostalCode::query()->create([
        'address_area_id' => $area->getKey(),
        'postal_code_id' => $postcode->getKey(),
        'source' => 'test',
        'relationship_type' => 'served_by',
        'is_primary' => true,
    ]);

    $stateEnabled = config('filament-addressing.resources.states.enabled');
    $postcodesEnabled = config('filament-addressing.resources.postal_codes.enabled');
    config()->set('filament-addressing.resources.states.enabled', false);
    config()->set('filament-addressing.resources.postal_codes.enabled', false);

    try {
        Livewire::test(AreaStateLinksRelationManager::class, [
            'ownerRecord' => $area,
            'pageClass' => ViewAddressArea::class,
        ])->assertCanSeeTableRecords([$stateLink]);

        Livewire::test(AreaPostalCodesRelationManager::class, [
            'ownerRecord' => $area,
            'pageClass' => ViewAddressArea::class,
        ])->assertCanSeeTableRecords([$area->postalCodeCoverage()->firstOrFail()]);
    } finally {
        config()->set('filament-addressing.resources.states.enabled', $stateEnabled);
        config()->set('filament-addressing.resources.postal_codes.enabled', $postcodesEnabled);
    }
});

it('keeps parallel area relationship and postcode coverage rows keyed by their pivots', function (): void {
    app(SeedAddressCountriesAction::class)->execute();
    $parent = AddressArea::factory()->create(['country_code' => 'MY', 'name' => 'Shared parent']);
    $child = AddressArea::factory()->create(['country_code' => 'MY', 'name' => 'Child']);

    $relationshipRows = collect([
        ['relationship_type' => 'contains', 'hierarchy_type' => 'administrative', 'source' => 'provider-a'],
        ['relationship_type' => 'contains', 'hierarchy_type' => 'postal', 'source' => 'provider-b'],
    ])->map(fn (array $attributes): AddressAreaRelationship => AddressAreaRelationship::query()->create([
        'parent_address_area_id' => $parent->getKey(),
        'child_address_area_id' => $child->getKey(),
        ...$attributes,
    ]));

    $incoming = Livewire::test(IncomingAreaRelationshipsRelationManager::class, [
        'ownerRecord' => $child,
        'pageClass' => ViewAddressArea::class,
    ]);
    $incomingRecords = $incoming->instance()->getTableRecords()->getCollection();
    expect($incomingRecords)->toHaveCount(2)
        ->and($incomingRecords->pluck('id')->all())->toEqualCanonicalizing($relationshipRows->pluck('id')->all())
        ->and($incomingRecords->map(fn (AddressAreaRelationship $record): string => $incoming->instance()->getTableRecordKey($record))->unique())->toHaveCount(2)
        ->and($incomingRecords->pluck('parent_address_area_id')->unique()->all())->toBe([$parent->getKey()]);
    $incoming->assertSee(AddressAreaResource::getUrl('view', ['record' => $parent]));

    $outgoing = Livewire::test(OutgoingAreaRelationshipsRelationManager::class, [
        'ownerRecord' => $parent,
        'pageClass' => ViewAddressArea::class,
    ]);
    $outgoingRecords = $outgoing->instance()->getTableRecords()->getCollection();

    expect($outgoingRecords)->toHaveCount(2)
        ->and($outgoingRecords->pluck('id')->all())->toEqualCanonicalizing($relationshipRows->pluck('id')->all())
        ->and($outgoingRecords->map(fn (AddressAreaRelationship $record): string => $outgoing->instance()->getTableRecordKey($record))->unique())->toHaveCount(2)
        ->and($outgoingRecords->pluck('child_address_area_id')->unique()->all())->toBe([$child->getKey()]);
    $outgoing->assertSee(AddressAreaResource::getUrl('view', ['record' => $child]));

    $postcode = PostalCode::factory()->create(['country_code' => 'MY', 'code' => '88321']);
    $coverageRows = collect([
        ['relationship_type' => 'served_by', 'source' => 'provider-a'],
        ['relationship_type' => 'preferred_for', 'source' => 'provider-b'],
    ])->map(fn (array $attributes): AddressAreaPostalCode => AddressAreaPostalCode::query()->create([
        'address_area_id' => $parent->getKey(),
        'postal_code_id' => $postcode->getKey(),
        ...$attributes,
    ]));

    $coverage = Livewire::test(AreaPostalCodesRelationManager::class, [
        'ownerRecord' => $parent,
        'pageClass' => ViewAddressArea::class,
    ]);
    $coverageRecords = $coverage->instance()->getTableRecords()->getCollection();

    expect($coverageRecords)->toHaveCount(2)
        ->and($coverageRecords->pluck('id')->all())->toEqualCanonicalizing($coverageRows->pluck('id')->all())
        ->and($coverageRecords->map(fn (AddressAreaPostalCode $record): string => $coverage->instance()->getTableRecordKey($record))->unique())->toHaveCount(2)
        ->and($coverageRecords->pluck('postal_code_id')->unique()->all())->toBe([$postcode->getKey()]);
});

it('does not link incoming area relationships when the related parent is not viewable', function (): void {
    $this->actingAs(User::factory()->create());
    Gate::policy(AddressArea::class, AreaAdminCapabilitiesTestPolicy::class);
    $parent = AddressArea::factory()->create(['country_code' => 'MY', 'name' => 'Private parent']);
    $child = AddressArea::factory()->create(['country_code' => 'MY', 'name' => 'Child']);
    AddressAreaRelationship::query()->create([
        'parent_address_area_id' => $parent->getKey(),
        'child_address_area_id' => $child->getKey(),
        'relationship_type' => 'contains',
        'hierarchy_type' => 'administrative',
        'source' => 'test',
    ]);

    Livewire::test(IncomingAreaRelationshipsRelationManager::class, [
        'ownerRecord' => $child,
        'pageClass' => ViewAddressArea::class,
    ])
        ->assertSeeText('Private parent')
        ->assertDontSee(AddressAreaResource::getUrl('view', ['record' => $parent]));
});

it('keeps provider area source identity and data when handling a forged update payload', function (): void {
    app(SeedAddressCountriesAction::class)->execute();
    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();
    $record = AddressArea::factory()->create([
        'country_id' => $country->getKey(),
        'country_code' => 'MY',
        'source' => 'my.provider',
        'source_id' => 'MY-STATE-1',
        'name' => 'Selangor',
        'type' => 'state',
        'level' => 1,
    ]);
    $page = app(EditAddressArea::class);
    $page->record = $record;
    $method = new ReflectionMethod(EditAddressArea::class, 'handleRecordUpdate');
    $method->setAccessible(true);

    $updated = $method->invoke($page, $record, [
        'country_id' => 'invalid-country',
        'name' => 'Forged name',
        'type' => 'district',
        'source' => 'manual',
        'source_id' => 'forged-id',
        'parent_source_id' => 'forged-parent',
        'is_active' => false,
    ]);

    expect($updated->country_id)->toBe($country->getKey())
        ->and($updated->name)->toBe('Selangor')
        ->and($updated->type)->toBe('state')
        ->and($updated->source)->toBe('my.provider')
        ->and($updated->source_id)->toBe('MY-STATE-1')
        ->and($updated->parent_source_id)->toBeNull()
        ->and($updated->is_active)->toBeFalse();
});

it('does not silently resubmit the hierarchy type when editing unrelated fields', function (): void {
    $page = app(EditAddressArea::class);
    $page->record = AddressArea::factory()->create(['country_code' => 'MY']);
    $method = new ReflectionMethod(EditAddressArea::class, 'mutateFormDataBeforeSave');
    $method->setAccessible(true);

    $data = $method->invoke($page, ['name' => 'Renamed Area']);

    expect($data)->not->toHaveKey('hierarchy_type');
});
