<?php

declare(strict_types=1);

namespace AIArmada\Addressing\Geography\Fiji;

use AIArmada\Addressing\Contracts\AddressAreaSource;
use AIArmada\Addressing\Contracts\CountryAddressAreaMetadataProvider;
use AIArmada\Addressing\Contracts\CountryGeographyProvider;
use AIArmada\Addressing\Contracts\CountryHierarchyProvider;
use AIArmada\Addressing\Data\AddressHierarchyDefinition;
use AIArmada\Addressing\Data\AddressLevelDefinition;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Support\CsvAddressAreaSource;
use AIArmada\Addressing\Support\ModelResolver;

class FijiGeographyProvider implements CountryAddressAreaMetadataProvider, CountryGeographyProvider, CountryHierarchyProvider
{
    public const string AREA_SOURCE = 'aiarmada_addressing_fiji_v1';

    private const string PROVIDER_KEY = 'aiarmada.addressing.fiji';

    public function providerKey(): string
    {
        return self::PROVIDER_KEY;
    }

    public function countryCode(): string
    {
        return 'FJ';
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
                        key: 'division',
                        label: 'Division / Province / Dependency',
                        kind: 'state',
                        hierarchyType: 'administrative',
                        areaTypes: ['division', 'province', 'dependency'],
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
                'division' => ['division'],
                'province' => ['province'],
                'dependency' => ['dependency'],
                default => [],
            };

            $roles[$area->sourceId] = array_map(
                static fn (string $role): array => ['role' => $role, 'country_code' => 'FJ', 'is_primary' => true],
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
            __DIR__ . '/../../../resources/geography/fiji-address-areas.csv',
            self::AREA_SOURCE,
        );
    }

    /**
     * @return array<int|string, array{area_code: string, source: string, area_level: int, hierarchy_types?: list<string>}>
     */
    public function stateAreaMappings(): array
    {
        /** @var array<int|string, string> */
        $areaCodes = [
            '01' => '01',
            '02' => '02',
            '03' => '03',
            'C' => 'C',
            'E' => 'E',
            '04' => '04',
            '05' => '05',
            '06' => '06',
            '07' => '07',
            '08' => '08',
            '09' => '09',
            '10' => '10',
            'N' => 'N',
            '11' => '11',
            '12' => '12',
            'R' => 'R',
            '13' => '13',
            '14' => '14',
            'W' => 'W',
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
            ['name' => 'Ba', 'code' => '01'],
            ['name' => 'Bua', 'code' => '02'],
            ['name' => 'Cakaudrove', 'code' => '03'],
            ['name' => 'Central', 'code' => 'C'],
            ['name' => 'Eastern', 'code' => 'E'],
            ['name' => 'Kadavu', 'code' => '04'],
            ['name' => 'Lau', 'code' => '05'],
            ['name' => 'Lomaiviti', 'code' => '06'],
            ['name' => 'Macuata', 'code' => '07'],
            ['name' => 'Nadroga-Navosa', 'code' => '08'],
            ['name' => 'Naitasiri', 'code' => '09'],
            ['name' => 'Namosi', 'code' => '10'],
            ['name' => 'Northern', 'code' => 'N'],
            ['name' => 'Ra', 'code' => '11'],
            ['name' => 'Rewa', 'code' => '12'],
            ['name' => 'Rotuma', 'code' => 'R'],
            ['name' => 'Serua', 'code' => '13'],
            ['name' => 'Tailevu', 'code' => '14'],
            ['name' => 'Western', 'code' => 'W'],
        ];
    }
}
