<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaPostalCode;
use AIArmada\Addressing\Models\PostalCode;
use AIArmada\Commerce\Tests\Fixtures\FilamentAddressingPanelUser;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAddressing\Exports\PostalCodeExporter;
use AIArmada\FilamentAddressing\Imports\PostalCodeImporter;
use AIArmada\FilamentAddressing\Resources\PostalCodeResource;
use AIArmada\FilamentAddressing\Resources\PostalCodeResource\Pages\CreatePostalCode;
use AIArmada\FilamentAddressing\Resources\PostalCodeResource\Pages\EditPostalCode;
use AIArmada\FilamentAddressing\Resources\PostalCodeResource\Pages\ListPostalCodes;
use AIArmada\FilamentAddressing\Resources\PostalCodeResource\Pages\ViewPostalCode;
use Filament\Actions\Exports\Models\Export;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Models\Import;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\Livewire;

beforeEach(function (): void {
    app(SeedAddressCountriesAction::class)->execute();
});

it('exports and imports a postcode with multiple independently sourced coverage links', function (): void {
    $areaOne = AddressArea::query()->create([
        'country_code' => 'MY', 'type' => 'locality', 'level' => 1, 'name' => 'Area One',
        'slug' => 'area-one', 'source' => 'areas-one', 'source_id' => 'one',
    ]);
    $areaTwo = AddressArea::query()->create([
        'country_code' => 'MY', 'type' => 'locality', 'level' => 1, 'name' => 'Area Two',
        'slug' => 'area-two', 'source' => 'areas-two', 'source_id' => 'two',
    ]);
    $postcode = PostalCode::query()->create([
        'country_code' => 'MY',
        'code' => '47500',
        'is_active' => false,
        'metadata' => ['provider' => 'root-provider', 'nested' => ['version' => 2]],
    ]);
    AddressAreaPostalCode::query()->create([
        'postal_code_id' => $postcode->getKey(), 'address_area_id' => $areaOne->getKey(),
        'source' => 'provider-one', 'source_id' => 'link-one', 'relationship_type' => 'served_by', 'is_primary' => true,
    ]);
    AddressAreaPostalCode::query()->create([
        'postal_code_id' => $postcode->getKey(), 'address_area_id' => $areaTwo->getKey(),
        'source' => 'provider-two', 'source_id' => 'link-two', 'relationship_type' => 'covers', 'is_primary' => false,
    ]);
    AddressAreaPostalCode::query()->create([
        'postal_code_id' => $postcode->getKey(), 'address_area_id' => $areaTwo->getKey(),
        'source' => 'provider-one', 'source_id' => 'link-one', 'relationship_type' => 'covers', 'is_primary' => false,
    ]);

    $exportColumns = PostalCodeExporter::getColumns();
    $columnNames = array_map(static fn ($column): string => $column->getName(), $exportColumns);
    $exporter = new PostalCodeExporter(new Export, array_combine($columnNames, $columnNames), []);
    $exportedValues = $exporter($postcode->fresh('areas'));
    $row = array_combine($columnNames, $exportedValues);

    expect($row)->toHaveKeys(['country_code', 'code', 'is_active', 'metadata', 'coverage'])
        ->and(json_decode((string) $row['coverage'], true, 512, JSON_THROW_ON_ERROR))->toBe([
            [
                'source' => 'provider-one',
                'source_id' => 'link-one',
                'area_source' => 'areas-one',
                'area_source_id' => 'one',
                'relationship_type' => 'served_by',
                'is_primary' => true,
            ],
            [
                'source' => 'provider-one',
                'source_id' => 'link-one',
                'area_source' => 'areas-two',
                'area_source_id' => 'two',
                'relationship_type' => 'covers',
                'is_primary' => false,
            ],
            [
                'source' => 'provider-two',
                'source_id' => 'link-two',
                'area_source' => 'areas-two',
                'area_source_id' => 'two',
                'relationship_type' => 'covers',
                'is_primary' => false,
            ],
        ]);

    AddressAreaPostalCode::query()->where('postal_code_id', $postcode->getKey())->where('source', 'provider-one')
        ->where('relationship_type', 'served_by')->update(['is_primary' => false]);

    $importer = new PostalCodeImporter(new Import, [
        'country_code' => 'country_code',
        'code' => 'code',
        'is_active' => 'is_active',
        'metadata' => 'metadata',
        'coverage' => 'coverage',
    ], []);
    $importer($row);

    $reloaded = $postcode->fresh();
    $links = AddressAreaPostalCode::query()->where('postal_code_id', $postcode->getKey())
        ->orderBy('source')->orderBy('relationship_type')->get(['source', 'source_id', 'relationship_type', 'is_primary']);

    expect($reloaded->is_active)->toBeFalse()
        ->and($reloaded->metadata)->toBe(['provider' => 'root-provider', 'nested' => ['version' => 2]])
        ->and($links->map(fn (AddressAreaPostalCode $link): array => [
            $link->source,
            $link->source_id,
            $link->relationship_type,
            $link->is_primary,
        ])->all())->toBe([
            ['provider-one', 'link-one', 'covers', false],
            ['provider-one', 'link-one', 'served_by', true],
            ['provider-two', 'link-two', 'covers', false],
        ]);
});

it('round trips an unlinked postcode without requiring flat source columns', function (): void {
    $postcode = PostalCode::query()->create([
        'country_code' => 'MY',
        'code' => '00000',
        'is_active' => false,
        'metadata' => null,
    ]);
    $columnNames = array_map(static fn ($column): string => $column->getName(), PostalCodeExporter::getColumns());
    $exporter = new PostalCodeExporter(new Export, array_combine($columnNames, $columnNames), []);
    $row = array_combine($columnNames, $exporter($postcode->fresh('areas')));
    $importer = new PostalCodeImporter(new Import, [
        'country_code' => 'country_code',
        'code' => 'code',
        'metadata' => 'metadata',
        'coverage' => 'coverage',
    ], []);

    expect($row['coverage'])->toBe('[]')
        ->and($row['metadata'])->toBe('null');

    $importer($row);

    expect($postcode->fresh()->is_active)->toBeFalse()
        ->and($postcode->fresh()->metadata)->toBeNull()
        ->and(AddressAreaPostalCode::query()->where('postal_code_id', $postcode->getKey())->exists())->toBeFalse();
});

it('preserves postcode metadata when a grouped coverage import omits the metadata column', function (): void {
    $postcode = PostalCode::query()->create([
        'country_code' => 'MY',
        'code' => '50501',
        'is_active' => false,
        'metadata' => ['keep' => ['source' => 'existing']],
    ]);
    $importer = new PostalCodeImporter(new Import, [
        'country_code' => 'country_code',
        'code' => 'code',
        'coverage' => 'coverage',
    ], []);

    $importer([
        'country_code' => 'MY',
        'code' => '50501',
        'coverage' => '[]',
    ]);

    expect($postcode->fresh()->metadata)->toBe(['keep' => ['source' => 'existing']])
        ->and($postcode->fresh()->is_active)->toBeFalse();
});

it('offers explicit manual coverage fields instead of a destructive many-to-many relationship select', function (): void {
    $schema = PostalCodeResource::form(Schema::make(Mockery::mock(Component::class, HasSchemas::class)));
    $sections = collect($schema->getComponents());
    $manualCoverage = $sections
        ->flatMap(fn ($section): array => $section->getChildComponents())
        ->first(fn ($component): bool => $component instanceof Repeater && $component->getName() === 'manual_coverage');

    expect($manualCoverage)->toBeInstanceOf(Repeater::class)
        ->and(array_map(static fn ($component): string => $component->getName(), $manualCoverage->getChildComponents()))
        ->toContain('area_id', 'relationship_type', 'is_primary');
});

it('mounts postcode create and edit with manual coverage while retaining provider coverage and locking identity', function (): void {
    config()->set('auth.providers.users.model', FilamentAddressingPanelUser::class);
    Filament::auth()->login(FilamentAddressingPanelUser::query()->create(User::factory()->raw()));
    Gate::define('create', fn (): bool => true);
    Gate::define('update', fn (): bool => true);
    Gate::define('viewAny', fn (): bool => true);
    Gate::define('view', fn (): bool => true);
    Gate::policy(PostalCode::class, PostalCodeAdminCapabilitiesTestPolicy::class);
    Filament::getCurrentPanel()?->registerResources([PostalCodeResource::class]);
    $originalReadOnly = config('filament-addressing.resources.postal_codes.read_only');
    config()->set('filament-addressing.resources.postal_codes.read_only', false);

    try {
        expect(PostalCodeResource::isReadOnly())->toBeFalse()
            ->and(PostalCodeResource::canCreate())->toBeTrue();
        $response = $this->get(PostalCodeResource::getUrl('create'));
        expect($response->status())->toBe(200, $response->getContent());

        $area = AddressArea::query()->create([
            'country_code' => 'MY', 'type' => 'locality', 'level' => 1, 'name' => 'Bangsar',
            'slug' => 'bangsar', 'source' => 'areas', 'source_id' => 'bangsar',
        ]);

        Livewire::test(CreatePostalCode::class)
            ->fillForm([
                'country_code' => 'MY',
                'code' => '50450',
                'manual_coverage' => [[
                    'area_id' => $area->getKey(),
                    'relationship_type' => 'served_by',
                    'is_primary' => true,
                ]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $postcode = PostalCode::query()->where('code', '50450')->firstOrFail();
        $providerArea = AddressArea::query()->create([
            'country_code' => 'MY', 'type' => 'locality', 'level' => 1, 'name' => 'Provider Area',
            'slug' => 'provider-area', 'source' => 'areas', 'source_id' => 'provider',
        ]);
        AddressAreaPostalCode::query()->create([
            'postal_code_id' => $postcode->getKey(), 'address_area_id' => $providerArea->getKey(),
            'source' => 'provider', 'source_id' => 'feed-1', 'relationship_type' => 'covers', 'is_primary' => false,
        ]);

        Livewire::test(EditPostalCode::class, ['record' => $postcode->getRouteKey()])
            ->fillForm([
                'country_code' => 'SG',
                'code' => '99999',
                'manual_coverage' => [],
                'is_active' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($postcode->fresh()->country_code)->toBe('MY')
            ->and($postcode->fresh()->code)->toBe('50450')
            ->and($postcode->fresh()->is_active)->toBeFalse()
            ->and(AddressAreaPostalCode::query()->where('postal_code_id', $postcode->getKey())->count())->toBe(1)
            ->and(AddressAreaPostalCode::query()->where('postal_code_id', $postcode->getKey())->value('source'))->toBe('provider');
    } finally {
        config()->set('filament-addressing.resources.postal_codes.read_only', $originalReadOnly);
    }
});

it('rejects a postcode code that does not match its selected country', function (): void {
    $this->actingAs(User::factory()->create());
    Gate::define('create', fn (): bool => true);
    Gate::define('viewAny', fn (): bool => true);
    Filament::getCurrentPanel()?->registerResources([PostalCodeResource::class]);
    $originalReadOnly = config('filament-addressing.resources.postal_codes.read_only');
    config()->set('filament-addressing.resources.postal_codes.read_only', false);

    try {
        Livewire::test(CreatePostalCode::class)
            ->fillForm(['country_code' => 'MY', 'code' => 'ABCDE'])
            ->call('create')
            ->assertHasFormErrors(['code']);

        expect(PostalCode::query()->where('country_code', 'MY')->where('code', 'ABCDE')->exists())->toBeFalse();
    } finally {
        config()->set('filament-addressing.resources.postal_codes.read_only', $originalReadOnly);
    }
});

it('edits postcode metadata as typed JSON and preserves or clears it intentionally', function (): void {
    $this->actingAs(User::factory()->create());
    Gate::define('update', fn (): bool => true);
    Gate::policy(PostalCode::class, PostalCodeAdminCapabilitiesTestPolicy::class);
    Filament::getCurrentPanel()?->registerResources([PostalCodeResource::class]);

    $initialMetadata = ['provider' => 'postal', 'nested' => ['enabled' => true, 'count' => 2, 'items' => [1, false, 'x']]];
    $postcode = PostalCode::query()->create([
        'country_code' => 'MY',
        'code' => '50503',
        'metadata' => $initialMetadata,
    ]);

    Livewire::test(EditPostalCode::class, ['record' => $postcode->getRouteKey()])
        ->fillForm(['is_active' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($postcode->fresh()->metadata)->toBe($initialMetadata);

    Livewire::test(EditPostalCode::class, ['record' => $postcode->getRouteKey()])
        ->fillForm(['metadata' => '{"nested": }'])
        ->call('save')
        ->assertHasFormErrors(['metadata']);

    expect($postcode->fresh()->metadata)->toBe($initialMetadata);

    Livewire::test(EditPostalCode::class, ['record' => $postcode->getRouteKey()])
        ->fillForm(['metadata' => 'true'])
        ->call('save')
        ->assertHasFormErrors(['metadata' => 'Metadata must be a JSON object, array, or null.']);

    expect($postcode->fresh()->metadata)->toBe($initialMetadata);

    $updatedMetadata = ['enabled' => true, 'count' => 3, 'nested' => ['items' => [1, false, 'updated']]];
    Livewire::test(EditPostalCode::class, ['record' => $postcode->getRouteKey()])
        ->fillForm(['metadata' => json_encode($updatedMetadata, JSON_THROW_ON_ERROR)])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($postcode->fresh()->metadata)->toBe($updatedMetadata);

    Livewire::test(EditPostalCode::class, ['record' => $postcode->getRouteKey()])
        ->fillForm(['metadata' => 'null'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($postcode->fresh()->metadata)->toBeNull();
});

it('hides mutation actions and rejects direct imports when postcode management is read only', function (): void {
    $this->actingAs(User::factory()->create());
    Gate::define('create', fn (): bool => true);
    Gate::define('update', fn (): bool => true);
    Filament::getCurrentPanel()?->registerResources([PostalCodeResource::class]);
    $originalReadOnly = config('filament-addressing.resources.postal_codes.read_only');
    $originalImport = config('filament-addressing.features.postal_code_import');
    config()->set('filament-addressing.resources.postal_codes.read_only', true);
    config()->set('filament-addressing.features.postal_code_import', true);

    try {
        $postcode = PostalCode::query()->create(['country_code' => 'MY', 'code' => '50450']);

        Livewire::test(ListPostalCodes::class)->assertActionHidden('import');

        $listPage = app(ListPostalCodes::class);
        $headerActionsMethod = new ReflectionMethod(ListPostalCodes::class, 'getHeaderActions');
        $headerActionsMethod->setAccessible(true);

        expect(array_map(static fn ($action): string => $action->getName(), $headerActionsMethod->invoke($listPage)))
            ->not->toContain('create');
        Livewire::test(ViewPostalCode::class, ['record' => $postcode->getRouteKey()])
            ->assertActionHidden('edit');

        $importer = new PostalCodeImporter(new Import, [
            'country_code' => 'country_code',
            'code' => 'code',
            'source' => 'source',
            'source_id' => 'source_id',
            'coverage' => 'coverage',
        ], []);

        expect(fn () => $importer([
            'country_code' => 'MY',
            'code' => '50450',
            'source' => 'provider',
            'source_id' => 'legacy-record',
            'coverage' => '[]',
        ]))->toThrow(RowImportFailedException::class, 'not authorized');
    } finally {
        config()->set('filament-addressing.resources.postal_codes.read_only', $originalReadOnly);
        config()->set('filament-addressing.features.postal_code_import', $originalImport);
    }
});

it('authorizes an imported postcode against its persisted attributes before applying CSV changes', function (): void {
    $this->actingAs(User::factory()->create());
    Gate::define('update', fn (User $user, PostalCode $record): bool => ($record->metadata['allowed'] ?? false) === true);
    Filament::getCurrentPanel()?->registerResources([PostalCodeResource::class]);

    $postcode = PostalCode::query()->create([
        'country_code' => 'MY',
        'code' => '50502',
        'metadata' => ['allowed' => true],
    ]);
    $importer = new PostalCodeImporter(new Import, [
        'country_code' => 'country_code',
        'code' => 'code',
        'metadata' => 'metadata',
        'coverage' => 'coverage',
    ], []);

    $importer([
        'country_code' => 'MY',
        'code' => '50502',
        'metadata' => '{"allowed":false}',
        'coverage' => '[]',
    ]);

    expect($postcode->fresh()->metadata)->toBe(['allowed' => false]);
});

it('renders coverage without linking to a disabled area resource', function (): void {
    $this->actingAs(User::factory()->create());
    Filament::getCurrentPanel()?->registerResources([PostalCodeResource::class]);
    Gate::define('viewAny', fn (): bool => true);
    Gate::define('view', fn (): bool => true);
    $originalAreasEnabled = config('filament-addressing.resources.areas.enabled');

    try {
        config()->set('filament-addressing.resources.areas.enabled', false);
        $area = AddressArea::query()->create([
            'country_code' => 'MY', 'type' => 'locality', 'level' => 1, 'name' => 'Bangsar',
            'slug' => 'bangsar', 'source' => 'areas', 'source_id' => 'bangsar',
        ]);
        $postcode = PostalCode::query()->create(['country_code' => 'MY', 'code' => '50450']);
        AddressAreaPostalCode::query()->create([
            'postal_code_id' => $postcode->getKey(), 'address_area_id' => $area->getKey(),
            'source' => 'provider', 'source_id' => 'feed-1', 'relationship_type' => 'served_by', 'is_primary' => false,
        ]);

        Livewire::test(ViewPostalCode::class, ['record' => $postcode->getRouteKey()])
            ->assertSeeText('Bangsar')
            ->assertSeeText('provider')
            ->assertSeeText('feed-1');
    } finally {
        config()->set('filament-addressing.resources.areas.enabled', $originalAreasEnabled);
    }
});

it('uses the configured postcode model for mounted create and update writes', function (): void {
    $this->actingAs(User::factory()->create());
    Gate::define('create', fn (): bool => true);
    Gate::define('update', fn (): bool => true);
    Filament::getCurrentPanel()?->registerResources([PostalCodeResource::class]);
    $originalModel = config('filament-addressing.resources.postal_codes.model');
    $originalReadOnly = config('filament-addressing.resources.postal_codes.read_only');
    config()->set('filament-addressing.resources.postal_codes.model', AdminConfiguredPostalCode::class);
    config()->set('filament-addressing.resources.postal_codes.read_only', false);

    try {
        Livewire::test(CreatePostalCode::class)
            ->fillForm(['country_code' => 'MY', 'code' => '50500'])
            ->call('create')
            ->assertHasNoFormErrors();

        $postcode = AdminConfiguredPostalCode::query()->where('code', '50500')->firstOrFail();

        Livewire::test(EditPostalCode::class, ['record' => $postcode->getRouteKey()])
            ->fillForm(['is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($postcode->fresh())
            ->toBeInstanceOf(AdminConfiguredPostalCode::class)
            ->and($postcode->fresh()->is_active)->toBeFalse();
    } finally {
        config()->set('filament-addressing.resources.postal_codes.model', $originalModel);
        config()->set('filament-addressing.resources.postal_codes.read_only', $originalReadOnly);
    }
});

class AdminConfiguredPostalCode extends PostalCode {}

class PostalCodeAdminCapabilitiesTestPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, PostalCode $record): bool
    {
        return true;
    }

    public function update(User $user, PostalCode $record): bool
    {
        return true;
    }
}
