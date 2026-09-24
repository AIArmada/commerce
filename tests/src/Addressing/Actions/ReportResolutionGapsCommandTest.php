<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\ImportAddressAreasAction;
use AIArmada\Addressing\Actions\LogAddressResolutionGapAction;
use AIArmada\Addressing\Contracts\AddressAreaSource;
use AIArmada\Addressing\Contracts\CountryAddressAreaMetadataProvider;
use AIArmada\Addressing\Contracts\CountryGeographyProvider;
use AIArmada\Addressing\Contracts\CountryHierarchyProvider;
use AIArmada\Addressing\Data\AddressAreaData;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaName;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\ResolutionGap;
use AIArmada\Addressing\Support\ArrayAddressAreaSource;
use Carbon\CarbonImmutable;

final class GapReportFakeGeographyProvider implements CountryAddressAreaMetadataProvider, CountryGeographyProvider, CountryHierarchyProvider
{
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
        return new ArrayAddressAreaSource('fake', [
            new AddressAreaData(source: 'fake', sourceId: 'kl', countryCode: 'MY', type: 'locality', level: 2, name: 'Kuala Lumpur'),
        ]);
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
    $this->seedCountry('ID');

    $log = app(LogAddressResolutionGapAction::class);
    $log->execute('google-picker', 'MY', 'postal_locality', 'Kuala Lumpur');
    $log->execute('google-picker', 'MY', 'postal_locality', 'Kuala Lumpur');
    $log->execute('google-picker', 'MY', 'postal_locality', 'Kuala Lumpur');
    $log->execute('onemap', 'MY', 'postal_locality', 'Bangsar');
    $log->execute('google-picker', 'ID', 'regency', 'Jakarta Pusat', 'ambiguous');

    ResolutionGap::query()->where('value', 'Bangsar')->update(['last_seen_at' => CarbonImmutable::now()->subDays(60)]);
});

it('lists top gaps by hits within the recency window', function (): void {
    $this->artisan('address:resolution-gaps')
        ->expectsOutputToContain('Kuala Lumpur')
        ->expectsOutputToContain('Matched but not yet in providers: 0')
        ->assertSuccessful();
});

it('honors country, reason, and status filters', function (): void {
    $this->artisan('address:resolution-gaps', ['--country' => 'ID'])
        ->expectsOutputToContain('Jakarta Pusat')
        ->assertSuccessful();

    $this->artisan('address:resolution-gaps', ['--reason' => 'ambiguous'])
        ->expectsOutputToContain('Jakarta Pusat')
        ->assertSuccessful();

    $this->artisan('address:resolution-gaps', ['--status' => 'matched'])
        ->expectsOutputToContain('No resolution gaps found.')
        ->assertSuccessful();
});

it('rejects invalid filter values', function (): void {
    $this->artisan('address:resolution-gaps', ['--reason' => 'nope'])->assertFailed();
    $this->artisan('address:resolution-gaps', ['--status' => 'nope'])->assertFailed();
    $this->artisan('address:resolution-gaps', ['--days' => '0'])->assertFailed();
});

it('counts the promotion backlog from evidence-backed candidates', function (): void {
    config()->set('addressing.geography.providers', [GapReportFakeGeographyProvider::class]);

    app(ImportAddressAreasAction::class)->execute(new ArrayAddressAreaSource('areas', [
        new AddressAreaData(source: 'areas', sourceId: 'kl', countryCode: 'MY', type: 'locality', level: 2, name: 'Wilayah Persekutuan Kuala Lumpur'),
    ]));
    $area = AddressArea::query()->where('source_id', 'kl')->firstOrFail();

    $gap = ResolutionGap::query()->where('value', 'Kuala Lumpur')->firstOrFail();
    AddressAreaName::query()->create([
        'address_area_id' => $area->getKey(),
        'name' => 'Kuala Lumpur',
        'source' => 'manual',
        'name_type' => 'common',
        'is_preferred' => false,
    ]);
    $gap->status = 'matched';
    $gap->matched_area_id = $area->getKey();
    $gap->save();

    $this->artisan('address:resolution-gaps', ['--country' => 'MY'])
        ->expectsOutputToContain('Matched but not yet in providers: 1')
        ->assertSuccessful();

    $this->artisan('address:resolution-gaps')
        ->expectsOutputToContain('Matched but not yet in providers: 1')
        ->assertSuccessful();
});
