<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\ExportResolutionGapAliasesAction;
use AIArmada\Addressing\Actions\ImportAddressAreasAction;
use AIArmada\Addressing\Actions\LogAddressResolutionGapAction;
use AIArmada\Addressing\Actions\MatchGapToAreaAction;
use AIArmada\Addressing\Contracts\AddressAreaSource;
use AIArmada\Addressing\Contracts\CountryAddressAreaMetadataProvider;
use AIArmada\Addressing\Contracts\CountryGeographyProvider;
use AIArmada\Addressing\Contracts\CountryHierarchyProvider;
use AIArmada\Addressing\Data\AddressAreaData;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\ResolutionGap;
use AIArmada\Addressing\Support\ArrayAddressAreaSource;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Artisan;

final class GapScopingFakeGeographyProvider implements CountryAddressAreaMetadataProvider, CountryGeographyProvider, CountryHierarchyProvider
{
    /** @var array<int, AddressAreaData> */
    public static array $shippedAreas = [];

    public function providerKey(): string
    {
        return 'test.fake';
    }

    public function countryCode(): string
    {
        return 'MY';
    }

    public function seed(AddressCountry $country): void {}

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

    public function areaRoles(AddressCountry $country): array
    {
        return [];
    }

    public function areaNames(AddressCountry $country): array
    {
        return [];
    }

    public function areaRelationships(AddressCountry $country): array
    {
        return [];
    }
}

beforeEach(function (): void {
    $this->seedCountry('MY');

    app(ImportAddressAreasAction::class)->execute(new ArrayAddressAreaSource('areas', [
        new AddressAreaData(source: 'areas', sourceId: 'a-area', countryCode: 'MY', type: 'locality', level: 2, name: 'A Area Official'),
        new AddressAreaData(source: 'areas', sourceId: 'b-area', countryCode: 'MY', type: 'locality', level: 2, name: 'B Area Official'),
        new AddressAreaData(source: 'areas', sourceId: 'g-area', countryCode: 'MY', type: 'locality', level: 2, name: 'G Area Official'),
    ]));

    GapScopingFakeGeographyProvider::$shippedAreas = [
        new AddressAreaData(source: 'fake', sourceId: 'a-area', countryCode: 'MY', type: 'locality', level: 2, name: 'A Area Official'),
        new AddressAreaData(source: 'fake', sourceId: 'b-area', countryCode: 'MY', type: 'locality', level: 2, name: 'B Area Official'),
        new AddressAreaData(source: 'fake', sourceId: 'g-area', countryCode: 'MY', type: 'locality', level: 2, name: 'G Area Official'),
    ];

    $this->areas = AddressArea::query()->where('source', 'areas')->get()->keyBy('source_id');
});

function logGapForScoping(?User $owner, string $value): ResolutionGap
{
    $log = fn (): ResolutionGap => app(LogAddressResolutionGapAction::class)->execute('google-picker', 'MY', 'postal_locality', $value);

    if ($owner === null) {
        return OwnerContext::withOwner(null, $log);
    }

    return OwnerContext::withOwner($owner, $log);
}

function matchGapForScoping(?User $owner, ResolutionGap $gap, AddressArea $area): void
{
    $match = function () use ($gap, $area): void {
        app(MatchGapToAreaAction::class)->execute($gap, $area, 'scope-test');
    };

    if ($owner === null) {
        OwnerContext::withOwner(null, $match);

        return;
    }

    OwnerContext::withOwner($owner, $match);
}

it('logs gaps under the current owner and falls back to global rows', function (): void {
    $owner = User::factory()->create();

    $owned = OwnerContext::withOwner($owner, fn (): ResolutionGap => app(LogAddressResolutionGapAction::class)->execute('google-picker', 'MY', 'postal_locality', 'Owned Value'));
    $global = OwnerContext::withOwner(null, fn (): ResolutionGap => app(LogAddressResolutionGapAction::class)->execute('google-picker', 'MY', 'postal_locality', 'Global Value'));

    expect($owned->owner_type)->toBe($owner->getMorphClass())
        ->and($owned->owner_id)->toBe((string) $owner->getKey())
        ->and($global->owner_type)->toBeNull()
        ->and($global->owner_id)->toBeNull();
});

it('isolates identical gap tuples per owner with independent hit counters', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    $gapA = logGapForScoping($ownerA, 'Same Value');
    $gapB = logGapForScoping($ownerB, 'Same Value');
    logGapForScoping($ownerA, 'Same Value');

    expect($gapA->getKey())->not->toBe($gapB->getKey());

    $visibleToB = OwnerContext::withOwner($ownerB, fn () => ResolutionGap::query()->pluck('id')->all());
    $hitsA = OwnerContext::withOwner($ownerA, fn () => $gapA->fresh()->hits);
    $hitsB = OwnerContext::withOwner($ownerB, fn () => $gapB->fresh()->hits);

    expect($visibleToB)->toBe([$gapB->getKey()])
        ->and($hitsA)->toBe(2)
        ->and($hitsB)->toBe(1);
});

it('excludes global rows by default but includes them on request', function (): void {
    $owner = User::factory()->create();

    $global = logGapForScoping(null, 'Global Value');

    $default = OwnerContext::withOwner($owner, fn () => ResolutionGap::query()->pluck('id')->all());
    $withGlobal = OwnerContext::withOwner($owner, fn () => ResolutionGap::query()->forOwner($owner, true)->pluck('id')->all());

    expect($default)->toBe([])
        ->and($withGlobal)->toBe([$global->getKey()]);
});

it('blocks cross-owner gap writes', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    $gapA = logGapForScoping($ownerA, 'Owned Value');

    OwnerContext::withOwner($ownerB, function () use ($gapA): void {
        $gapA->status = 'ignored';
        $gapA->save();
    });
})->throws(AuthorizationException::class);

it('keeps export tenant-scoped unless consolidation is requested', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    matchGapForScoping($ownerA, logGapForScoping($ownerA, 'A Area Alpha'), $this->areas['a-area']);
    matchGapForScoping($ownerB, logGapForScoping($ownerB, 'B Area Beta'), $this->areas['b-area']);
    matchGapForScoping(null, logGapForScoping(null, 'G Area Gamma'), $this->areas['g-area']);

    config()->set('addressing.geography.providers', [GapScopingFakeGeographyProvider::class]);

    $inA = OwnerContext::withOwner($ownerA, fn () => app(ExportResolutionGapAliasesAction::class)->execute('MY')->candidateCount());
    $inB = OwnerContext::withOwner($ownerB, fn () => app(ExportResolutionGapAliasesAction::class)->execute('MY')->candidateCount());
    $all = OwnerContext::withOwner($ownerA, fn () => app(ExportResolutionGapAliasesAction::class)->execute('MY', allOwners: true)->candidateCount());

    expect($inA)->toBe(1)
        ->and($inB)->toBe(1)
        ->and($all)->toBe(3);
});

it('reports gaps consolidated across every owner', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    logGapForScoping($ownerA, 'Alpha Tenant Value');
    logGapForScoping($ownerB, 'Beta Tenant Value');
    logGapForScoping(null, 'Global Telemetry Value');

    config()->set('addressing.geography.providers', [GapScopingFakeGeographyProvider::class]);

    $exit = Artisan::call('address:resolution-gaps', ['--country' => 'MY', '--limit' => 10]);
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('Alpha Tenant Value')
        ->and($output)->toContain('Beta Tenant Value')
        ->and($output)->toContain('Global Telemetry Value');
});
