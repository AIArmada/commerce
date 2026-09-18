<?php

declare(strict_types=1);

namespace AIArmada\Addressing\Geography\Malawi;

use AIArmada\Addressing\Contracts\AddressAreaSource;
use AIArmada\Addressing\Contracts\CountryAddressAreaMetadataProvider;
use AIArmada\Addressing\Contracts\CountryGeographyProvider;
use AIArmada\Addressing\Contracts\CountryHierarchyProvider;
use AIArmada\Addressing\Data\AddressHierarchyDefinition;
use AIArmada\Addressing\Data\AddressLevelDefinition;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Support\CsvAddressAreaSource;
use AIArmada\Addressing\Support\ModelResolver;

class MalawiGeographyProvider implements CountryAddressAreaMetadataProvider, CountryGeographyProvider, CountryHierarchyProvider
{
    public const string AREA_SOURCE = 'aiarmada_addressing_malawi_v1';

    private const string PROVIDER_KEY = 'aiarmada.addressing.malawi';

    public function providerKey(): string
    {
        return self::PROVIDER_KEY;
    }

    public function countryCode(): string
    {
        return 'MW';
    }

    public function seed(AddressCountry $country): void
    {
        $stateClass = ModelResolver::stateClass();
        $statesData = $this->stateDefinitions();

        foreach ($statesData as $s) {
            $stateClass::updateOrCreate(
                ['country_id' => $country->id, 'code' => $s['code']],
                [
                    'name' => $s['name'],
                    'country_code' => $this->countryCode(),
                ],
            );
        }
    }

    /** @return list<AddressHierarchyDefinition> */
    public function addressHierarchies(): array
    {
        return [
            new AddressHierarchyDefinition(
                key: 'administrative',
                label: 'Administrative / Territorial Geography',
                levels: [
                    new AddressLevelDefinition(
                        key: 'region',
                        label: 'Region / District',
                        kind: 'state',
                        hierarchyType: 'administrative',
                        areaTypes: ['region', 'district'],
                        areaLevel: 1,
                    ),
                ],
            ),
        ];
    }

    /** @return array<string, list<array{role: string, country_code?: string, is_primary?: bool}>> */
    public function areaRoles(AddressCountry $country): array
    {
        $roles = [];

        foreach ($this->addressAreaSource()->areas() as $area) {
            $areaRoles = match ($area->type) {
                'region' => ['region'],
                'district' => ['district'],
                default => [],
            };

            $roles[$area->sourceId] = array_map(
                static fn (string $role): array => ['role' => $role, 'country_code' => 'MW', 'is_primary' => true],
                $areaRoles,
            );
        }

        return $roles;
    }

    /** @return array<string, list<array{name: string, name_type?: string, is_preferred?: bool}>> */
    public function areaNames(AddressCountry $country): array
    {
        return [];
    }

    /** @return array<string, list<array{parent_source_id: string, relationship_type: string, hierarchy_type: string}>> */
    public function areaRelationships(AddressCountry $country): array
    {
        $relationships = [];

        foreach ($this->addressAreaSource()->areas() as $area) {
            if ($area->parentSourceId === null) {
                continue;
            }

            $relationships[$area->sourceId][] = [
                'parent_source_id' => $area->parentSourceId,
                'relationship_type' => 'contains',
                'hierarchy_type' => 'administrative',
            ];
        }

        return $relationships;
    }

    public function addressAreaSource(): AddressAreaSource
    {
        return new CsvAddressAreaSource(
            __DIR__ . '/../../../resources/geography/malawi-address-areas.csv',
            self::AREA_SOURCE,
        );
    }

    /**
     * @return array<string, array{area_code: string, source: string, area_level: int, hierarchy_types?: list<string>}>
     */
    public function stateAreaMappings(): array
    {
        /** @var array<string, string> */
        $areaCodes = [
            'BA' => 'BA',
            'BL' => 'BL',
            'C' => 'C',
            'CK' => 'CK',
            'CR' => 'CR',
            'CT' => 'CT',
            'DE' => 'DE',
            'DO' => 'DO',
            'KR' => 'KR',
            'KS' => 'KS',
            'LK' => 'LK',
            'LI' => 'LI',
            'MH' => 'MH',
            'MG' => 'MG',
            'MC' => 'MC',
            'MU' => 'MU',
            'MW' => 'MW',
            'MZ' => 'MZ',
            'NE' => 'NE',
            'NB' => 'NB',
            'NK' => 'NK',
            'N' => 'N',
            'NS' => 'NS',
            'NU' => 'NU',
            'NI' => 'NI',
            'PH' => 'PH',
            'RU' => 'RU',
            'SA' => 'SA',
            'S' => 'S',
            'TH' => 'TH',
            'ZO' => 'ZO',
        ];

        return array_map(
            static fn (string $areaCode): array => [
                'area_code' => $areaCode,
                'source' => self::AREA_SOURCE,
                'area_level' => 1,
                'hierarchy_types' => ['administrative'],
            ],
            $areaCodes,
        );
    }

    /**
     * @return list<array{name: string, code: string}>
     */
    private function stateDefinitions(): array
    {
        return [
            ['name' => 'Balaka', 'code' => 'BA'],
            ['name' => 'Blantyre', 'code' => 'BL'],
            ['name' => 'Central', 'code' => 'C'],
            ['name' => 'Chikwawa', 'code' => 'CK'],
            ['name' => 'Chiradzulu', 'code' => 'CR'],
            ['name' => 'Chitipa', 'code' => 'CT'],
            ['name' => 'Dedza', 'code' => 'DE'],
            ['name' => 'Dowa', 'code' => 'DO'],
            ['name' => 'Karonga', 'code' => 'KR'],
            ['name' => 'Kasungu', 'code' => 'KS'],
            ['name' => 'Likoma', 'code' => 'LK'],
            ['name' => 'Lilongwe', 'code' => 'LI'],
            ['name' => 'Machinga', 'code' => 'MH'],
            ['name' => 'Mangochi', 'code' => 'MG'],
            ['name' => 'Mchinji', 'code' => 'MC'],
            ['name' => 'Mulanje', 'code' => 'MU'],
            ['name' => 'Mwanza', 'code' => 'MW'],
            ['name' => 'Mzimba', 'code' => 'MZ'],
            ['name' => 'Neno', 'code' => 'NE'],
            ['name' => 'Nkhata Bay', 'code' => 'NB'],
            ['name' => 'Nkhotakota', 'code' => 'NK'],
            ['name' => 'Northern', 'code' => 'N'],
            ['name' => 'Nsanje', 'code' => 'NS'],
            ['name' => 'Ntcheu', 'code' => 'NU'],
            ['name' => 'Ntchisi', 'code' => 'NI'],
            ['name' => 'Phalombe', 'code' => 'PH'],
            ['name' => 'Rumphi', 'code' => 'RU'],
            ['name' => 'Salima', 'code' => 'SA'],
            ['name' => 'Southern', 'code' => 'S'],
            ['name' => 'Thyolo', 'code' => 'TH'],
            ['name' => 'Zomba', 'code' => 'ZO'],
        ];
    }
}
