<?php

declare(strict_types=1);

namespace AIArmada\Addressing\Geography\Ireland;

use AIArmada\Addressing\Contracts\AddressAreaSource;
use AIArmada\Addressing\Contracts\CountryAddressAreaMetadataProvider;
use AIArmada\Addressing\Contracts\CountryGeographyProvider;
use AIArmada\Addressing\Contracts\CountryHierarchyProvider;
use AIArmada\Addressing\Data\AddressHierarchyDefinition;
use AIArmada\Addressing\Data\AddressLevelDefinition;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Support\CsvAddressAreaSource;
use AIArmada\Addressing\Support\ModelResolver;

class IrelandGeographyProvider implements CountryAddressAreaMetadataProvider, CountryGeographyProvider, CountryHierarchyProvider
{
    public const string AREA_SOURCE = 'aiarmada_addressing_ireland_v1';

    private const string PROVIDER_KEY = 'aiarmada.addressing.ireland';

    public function providerKey(): string
    {
        return self::PROVIDER_KEY;
    }

    public function countryCode(): string
    {
        return 'IE';
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
                        key: 'province',
                        label: 'Province / County',
                        kind: 'state',
                        hierarchyType: 'administrative',
                        areaTypes: ['province', 'county'],
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
                'province' => ['province'],
                'county' => ['county'],
                default => [],
            };

            $roles[$area->sourceId] = array_map(
                static fn (string $role): array => ['role' => $role, 'country_code' => 'IE', 'is_primary' => true],
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
            __DIR__ . '/../../../resources/geography/ireland-address-areas.csv',
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
            'CW' => 'CW',
            'CN' => 'CN',
            'CE' => 'CE',
            'C' => 'C',
            'CO' => 'CO',
            'DL' => 'DL',
            'D' => 'D',
            'G' => 'G',
            'KY' => 'KY',
            'KE' => 'KE',
            'KK' => 'KK',
            'LS' => 'LS',
            'L' => 'L',
            'LM' => 'LM',
            'LK' => 'LK',
            'LD' => 'LD',
            'LH' => 'LH',
            'MO' => 'MO',
            'MH' => 'MH',
            'MN' => 'MN',
            'M' => 'M',
            'OY' => 'OY',
            'RN' => 'RN',
            'SO' => 'SO',
            'TA' => 'TA',
            'U' => 'U',
            'WD' => 'WD',
            'WH' => 'WH',
            'WX' => 'WX',
            'WW' => 'WW',
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
            ['name' => 'Carlow', 'code' => 'CW'],
            ['name' => 'Cavan', 'code' => 'CN'],
            ['name' => 'Clare', 'code' => 'CE'],
            ['name' => 'Connacht', 'code' => 'C'],
            ['name' => 'Cork', 'code' => 'CO'],
            ['name' => 'Donegal', 'code' => 'DL'],
            ['name' => 'Dublin', 'code' => 'D'],
            ['name' => 'Galway', 'code' => 'G'],
            ['name' => 'Kerry', 'code' => 'KY'],
            ['name' => 'Kildare', 'code' => 'KE'],
            ['name' => 'Kilkenny', 'code' => 'KK'],
            ['name' => 'Laois', 'code' => 'LS'],
            ['name' => 'Leinster', 'code' => 'L'],
            ['name' => 'Leitrim', 'code' => 'LM'],
            ['name' => 'Limerick', 'code' => 'LK'],
            ['name' => 'Longford', 'code' => 'LD'],
            ['name' => 'Louth', 'code' => 'LH'],
            ['name' => 'Mayo', 'code' => 'MO'],
            ['name' => 'Meath', 'code' => 'MH'],
            ['name' => 'Monaghan', 'code' => 'MN'],
            ['name' => 'Munster', 'code' => 'M'],
            ['name' => 'Offaly', 'code' => 'OY'],
            ['name' => 'Roscommon', 'code' => 'RN'],
            ['name' => 'Sligo', 'code' => 'SO'],
            ['name' => 'Tipperary', 'code' => 'TA'],
            ['name' => 'Ulster', 'code' => 'U'],
            ['name' => 'Waterford', 'code' => 'WD'],
            ['name' => 'Westmeath', 'code' => 'WH'],
            ['name' => 'Wexford', 'code' => 'WX'],
            ['name' => 'Wicklow', 'code' => 'WW'],
        ];
    }
}
