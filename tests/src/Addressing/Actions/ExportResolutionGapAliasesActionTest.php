<?php

declare(strict_types=1);

use AIArmada\Addressing\Contracts\AddressAreaImporter;
use AIArmada\Addressing\Contracts\AddressAreaSource;
use AIArmada\Addressing\Contracts\CountryAddressAreaMetadataProvider;
use AIArmada\Addressing\Contracts\CountryGeographyProvider;
use AIArmada\Addressing\Contracts\CountryHierarchyProvider;
use AIArmada\Addressing\Contracts\ResolutionGapCuration;
use AIArmada\Addressing\Contracts\ResolutionGapReporter;
use AIArmada\Addressing\Data\AddressAreaData;
use AIArmada\Addressing\Data\CountryStateSeedData;
use AIArmada\Addressing\Data\ExportResolutionGapAliasesResultData;
use AIArmada\Addressing\Features\ResolutionGaps\Actions\ExportResolutionGapAliasesAction;
use AIArmada\Addressing\Features\ResolutionGaps\Models\ResolutionGap;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaName;
use AIArmada\Addressing\Support\ArrayAddressAreaSource;
use Illuminate\Support\Facades\Artisan;

final class GapExportFakeGeographyProvider implements CountryAddressAreaMetadataProvider, CountryGeographyProvider, CountryHierarchyProvider
{
    /** @var array<int, AddressAreaData> */
    public static array $shippedAreas = [];

    /** @var array<string, list<array{name: string, name_type?: string, is_preferred?: bool}>> */
    public static array $declaredNames = [];

    public function providerKey(): string
    {
        return 'test.fake';
    }

    public function countryCode(): string
    {
        return 'MY';
    }

    public function stateSeedData(): CountryStateSeedData
    {
        return new CountryStateSeedData([]);
    }

    public function addressHierarchies(): array
    {
        return [];
    }

    public function addressAreaSource(): AddressAreaSource
    {
        return new ArrayAddressAreaSource('fake', self::$shippedAreas);
    }

    public function stateAreaMappings(): array
    {
        return [];
    }

    public function areaRoles(): array
    {
        return [];
    }

    public function areaNames(): array
    {
        return self::$declaredNames;
    }

    public function areaRelationships(): array
    {
        return [];
    }
}

beforeEach(function (): void {
    $this->seedCountry('MY');
    $this->seedCountry('ID');

    app(AddressAreaImporter::class)->execute(new ArrayAddressAreaSource('areas', [
        new AddressAreaData(source: 'areas', sourceId: 'b-area', countryCode: 'MY', type: 'locality', level: 2, name: 'B Area Official'),
        new AddressAreaData(source: 'areas', sourceId: 'a-area', countryCode: 'MY', type: 'locality', level: 2, name: 'A Area Official'),
    ]));

    GapExportFakeGeographyProvider::$shippedAreas = [
        new AddressAreaData(source: 'fake', sourceId: 'b-area', countryCode: 'MY', type: 'locality', level: 2, name: 'B Area Official'),
        new AddressAreaData(source: 'fake', sourceId: 'a-area', countryCode: 'MY', type: 'locality', level: 2, name: 'A Area Official'),
    ];
    GapExportFakeGeographyProvider::$declaredNames = [];

    $this->realProviders = config('addressing.geography.providers', []);
    config()->set('addressing.geography.providers', [GapExportFakeGeographyProvider::class]);

    $this->areas = AddressArea::query()->where('source', 'areas')->get()->keyBy('source_id');
});

function matchGapForExport(string $value, AddressArea $area): void
{
    $gap = ResolutionGap::query()->findOrFail(app(ResolutionGapReporter::class)->report('google-picker', 'MY', 'postal_locality', $value));
    app(ResolutionGapCuration::class)->match((string) $gap->getKey(), (string) $area->getKey());
}

it('exports matched aliases grouped and sorted by source id then name', function (): void {
    // Real MY provider defines the role, so match before swapping providers.
    config()->set('addressing.geography.providers', $this->realProviders);
    matchGapForExport('B Area Common', $this->areas['b-area']);
    matchGapForExport('A Area Zed', $this->areas['a-area']);
    matchGapForExport('A Area Alpha', $this->areas['a-area']);
    config()->set('addressing.geography.providers', [GapExportFakeGeographyProvider::class]);

    $result = app(ExportResolutionGapAliasesAction::class)->execute('MY');

    expect($result->countryCode)->toBe('MY')
        ->and($result->candidateCount())->toBe(3)
        ->and(array_keys($result->candidatesByProvider))->toBe([GapExportFakeGeographyProvider::class])
        ->and($result->candidatesByProvider[GapExportFakeGeographyProvider::class])->toBe([
            ['source_id' => 'a-area', 'name' => 'A Area Alpha', 'name_type' => 'common', 'is_preferred' => false],
            ['source_id' => 'a-area', 'name' => 'A Area Zed', 'name_type' => 'common', 'is_preferred' => false],
            ['source_id' => 'b-area', 'name' => 'B Area Common', 'name_type' => 'common', 'is_preferred' => false],
        ]);
});

it('skips aliases whose area is not shipped by the provider', function (): void {
    GapExportFakeGeographyProvider::$shippedAreas = [
        new AddressAreaData(source: 'fake', sourceId: 'a-area', countryCode: 'MY', type: 'locality', level: 2, name: 'A Area Official'),
    ];

    $gap = ResolutionGap::query()->findOrFail(app(ResolutionGapReporter::class)->report('google-picker', 'MY', 'postal_locality', 'B Area Common'));
    AddressAreaName::query()->create([
        'address_area_id' => $this->areas['b-area']->getKey(),
        'name' => 'B Area Common',
        'source' => 'manual',
        'name_type' => 'common',
        'is_preferred' => false,
    ]);
    $gap->status = 'matched';
    $gap->matched_area_id = $this->areas['b-area']->getKey();
    $gap->save();

    $result = app(ExportResolutionGapAliasesAction::class)->execute('MY');

    expect($result->candidateCount())->toBe(0)
        ->and($result->skippedUnshipped)->toBe(1)
        ->and(implode("\n", $result->warnings))->toContain('nowhere to land');
});

it('skips values already declared in the provider area names', function (): void {
    GapExportFakeGeographyProvider::$declaredNames = [
        'a-area' => [
            ['name' => 'a area alpha', 'name_type' => 'common'],
        ],
    ];

    $gap = ResolutionGap::query()->findOrFail(app(ResolutionGapReporter::class)->report('google-picker', 'MY', 'postal_locality', 'A Area Alpha'));
    AddressAreaName::query()->create([
        'address_area_id' => $this->areas['a-area']->getKey(),
        'name' => 'A Area Alpha',
        'source' => 'manual',
        'name_type' => 'common',
        'is_preferred' => false,
    ]);
    $gap->status = 'matched';
    $gap->matched_area_id = $this->areas['a-area']->getKey();
    $gap->save();

    $result = app(ExportResolutionGapAliasesAction::class)->execute('my');

    expect($result->candidateCount())->toBe(0)
        ->and($result->skippedDeclared)->toBe(1);
});

it('skips matched gaps whose manual alias went missing', function (): void {
    $gap = ResolutionGap::query()->findOrFail(app(ResolutionGapReporter::class)->report('google-picker', 'MY', 'postal_locality', 'A Area Alpha'));
    $gap->status = 'matched';
    $gap->matched_area_id = $this->areas['a-area']->getKey();
    $gap->save();

    $result = app(ExportResolutionGapAliasesAction::class)->execute('MY');

    expect($result->candidateCount())->toBe(0)
        ->and($result->skippedMissingAlias)->toBe(1);
});

it('dedupes the same value matched from several sources', function (): void {
    foreach (['google-picker', 'onemap'] as $source) {
        $gap = ResolutionGap::query()->findOrFail(app(ResolutionGapReporter::class)->report($source, 'MY', 'postal_locality', 'A Area Alpha'));
        $gap->status = 'matched';
        $gap->matched_area_id = $this->areas['a-area']->getKey();
        $gap->save();
    }

    AddressAreaName::query()->create([
        'address_area_id' => $this->areas['a-area']->getKey(),
        'name' => 'A Area Alpha',
        'source' => 'manual',
        'name_type' => 'common',
        'is_preferred' => false,
    ]);

    $result = app(ExportResolutionGapAliasesAction::class)->execute('MY');

    expect($result->candidateCount())->toBe(1)
        ->and($result->skippedDuplicates)->toBe(1);
});

it('refuses countries without a configured provider', function (): void {
    expect(fn (): ExportResolutionGapAliasesResultData => app(ExportResolutionGapAliasesAction::class)->execute('ID'))
        ->toThrow(InvalidArgumentException::class, 'No geography provider is configured for country [ID].');
});

it('prunes only manual aliases exactly duplicated by a provider pair', function (): void {
    app(AddressAreaImporter::class)->execute(new ArrayAddressAreaSource('areas', [
        new AddressAreaData(source: 'areas', sourceId: 'id-area', countryCode: 'ID', type: 'locality', level: 2, name: 'ID Area'),
    ]));
    $idArea = AddressArea::query()->where('source_id', 'id-area')->firstOrFail();

    $providerAlias = AddressAreaName::query()->create([
        'address_area_id' => $this->areas['a-area']->getKey(), 'name' => 'Promoted Name',
        'source' => 'test.fake', 'name_type' => 'common', 'is_preferred' => false,
    ]);
    $duplicated = AddressAreaName::query()->create([
        'address_area_id' => $this->areas['a-area']->getKey(), 'name' => 'Promoted Name',
        'source' => 'manual', 'name_type' => 'common', 'is_preferred' => false,
    ]);
    $caseDiffers = AddressAreaName::query()->create([
        'address_area_id' => $this->areas['a-area']->getKey(), 'name' => 'promoted name',
        'source' => 'manual', 'name_type' => 'common', 'is_preferred' => false,
    ]);
    $unpromoted = AddressAreaName::query()->create([
        'address_area_id' => $this->areas['a-area']->getKey(), 'name' => 'Still Manual',
        'source' => 'manual', 'name_type' => 'common', 'is_preferred' => false,
    ]);
    $otherCountry = AddressAreaName::query()->create([
        'address_area_id' => $idArea->getKey(), 'name' => 'Promoted Name',
        'source' => 'manual', 'name_type' => 'common', 'is_preferred' => false,
    ]);

    $result = app(ExportResolutionGapAliasesAction::class)->execute('MY', prune: true);

    expect($result->pruned)->toBe(1)
        ->and(AddressAreaName::query()->whereKey($duplicated->getKey())->exists())->toBeFalse()
        ->and(AddressAreaName::query()->whereKey($providerAlias->getKey())->exists())->toBeTrue()
        ->and(AddressAreaName::query()->whereKey($caseDiffers->getKey())->exists())->toBeTrue()
        ->and(AddressAreaName::query()->whereKey($unpromoted->getKey())->exists())->toBeTrue()
        ->and(AddressAreaName::query()->whereKey($otherCountry->getKey())->exists())->toBeTrue();
});

it('emits copy-paste-ready provider entries from the command', function (): void {
    $gap = ResolutionGap::query()->findOrFail(app(ResolutionGapReporter::class)->report('google-picker', 'MY', 'postal_locality', 'A Area Alpha'));
    AddressAreaName::query()->create([
        'address_area_id' => $this->areas['a-area']->getKey(),
        'name' => 'A Area Alpha',
        'source' => 'manual',
        'name_type' => 'common',
        'is_preferred' => false,
    ]);
    $gap->status = 'matched';
    $gap->matched_area_id = $this->areas['a-area']->getKey();
    $gap->save();

    $this->artisan('address:export-gap-aliases', ['--country' => 'MY'])
        ->expectsOutputToContain('GapExportFakeGeographyProvider::areaNames() additions')
        ->expectsOutputToContain("'a-area' => [")
        ->expectsOutputToContain("'name' => 'A Area Alpha', 'name_type' => 'common'")
        ->expectsOutputToContain('1 candidates')
        ->assertSuccessful();
});

it('requires a country for the export command', function (): void {
    $this->artisan('address:export-gap-aliases')->assertFailed();
});

it('groups several aliases per area into one provider block', function (): void {
    foreach (['A Area Alpha', 'A Area Zed'] as $value) {
        $gap = ResolutionGap::query()->findOrFail(app(ResolutionGapReporter::class)->report('google-picker', 'MY', 'postal_locality', $value));
        AddressAreaName::query()->create([
            'address_area_id' => $this->areas['a-area']->getKey(),
            'name' => $value,
            'source' => 'manual',
            'name_type' => 'common',
            'is_preferred' => false,
        ]);
        $gap->status = 'matched';
        $gap->matched_area_id = $this->areas['a-area']->getKey();
        $gap->save();
    }

    $exitCode = Artisan::call('address:export-gap-aliases', ['--country' => 'MY']);
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and(mb_substr_count($output, "'a-area' => ["))->toBe(1)
        ->and($output)->toContain("'name' => 'A Area Alpha'")
        ->and($output)->toContain("'name' => 'A Area Zed'");
});
