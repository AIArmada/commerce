<?php

declare(strict_types=1);

namespace AIArmada\Addressing\Geography\SriLanka;

use AIArmada\Addressing\Contracts\AddressAreaSource;
use AIArmada\Addressing\Contracts\CountryAddressAreaMetadataProvider;
use AIArmada\Addressing\Contracts\CountryGeographyProvider;
use AIArmada\Addressing\Contracts\CountryHierarchyProvider;
use AIArmada\Addressing\Data\AddressHierarchyDefinition;
use AIArmada\Addressing\Data\AddressLevelDefinition;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Support\CsvAddressAreaSource;
use AIArmada\Addressing\Support\ModelResolver;

class SriLankaGeographyProvider implements CountryAddressAreaMetadataProvider, CountryGeographyProvider, CountryHierarchyProvider
{
    public const string AREA_SOURCE = 'aiarmada_addressing_sri_lanka_v1';

    private const string PROVIDER_KEY = 'aiarmada.addressing.sri_lanka';

    public function providerKey(): string
    {
        return self::PROVIDER_KEY;
    }

    public function countryCode(): string
    {
        return 'LK';
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
                        label: 'Province / District',
                        kind: 'state',
                        hierarchyType: 'administrative',
                        areaTypes: ['province', 'district'],
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
                'district' => ['district'],
                default => [],
            };

            $roles[$area->sourceId] = array_map(
                static fn (string $role): array => ['role' => $role, 'country_code' => 'LK', 'is_primary' => true],
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
            __DIR__ . '/../../../resources/geography/sri-lanka-address-areas.csv',
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
            '52' => '52',
            '71' => '71',
            '81' => '81',
            '51' => '51',
            '2' => '2',
            '11' => '11',
            '5' => '5',
            '31' => '31',
            '12' => '12',
            '33' => '33',
            '41' => '41',
            '13' => '13',
            '21' => '21',
            '92' => '92',
            '42' => '42',
            '61' => '61',
            '43' => '43',
            '22' => '22',
            '32' => '32',
            '82' => '82',
            '45' => '45',
            '7' => '7',
            '6' => '6',
            '4' => '4',
            '23' => '23',
            '72' => '72',
            '62' => '62',
            '91' => '91',
            '9' => '9',
            '3' => '3',
            '53' => '53',
            '8' => '8',
            '44' => '44',
            '1' => '1',
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
            ['name' => 'Ampara', 'code' => '52'],
            ['name' => 'Anuradhapura', 'code' => '71'],
            ['name' => 'Badulla', 'code' => '81'],
            ['name' => 'Batticaloa', 'code' => '51'],
            ['name' => 'Central', 'code' => '2'],
            ['name' => 'Colombo', 'code' => '11'],
            ['name' => 'Eastern', 'code' => '5'],
            ['name' => 'Galle', 'code' => '31'],
            ['name' => 'Gampaha', 'code' => '12'],
            ['name' => 'Hambantota', 'code' => '33'],
            ['name' => 'Jaffna', 'code' => '41'],
            ['name' => 'Kalutara', 'code' => '13'],
            ['name' => 'Kandy', 'code' => '21'],
            ['name' => 'Kegalle', 'code' => '92'],
            ['name' => 'Kilinochchi', 'code' => '42'],
            ['name' => 'Kurunegala', 'code' => '61'],
            ['name' => 'Mannar', 'code' => '43'],
            ['name' => 'Matale', 'code' => '22'],
            ['name' => 'Matara', 'code' => '32'],
            ['name' => 'Monaragala', 'code' => '82'],
            ['name' => 'Mullaitivu', 'code' => '45'],
            ['name' => 'North Central', 'code' => '7'],
            ['name' => 'North Western', 'code' => '6'],
            ['name' => 'Northern', 'code' => '4'],
            ['name' => 'Nuwara Eliya', 'code' => '23'],
            ['name' => 'Polonnaruwa', 'code' => '72'],
            ['name' => 'Puttalam', 'code' => '62'],
            ['name' => 'Ratnapura', 'code' => '91'],
            ['name' => 'Sabaragamuwa', 'code' => '9'],
            ['name' => 'Southern', 'code' => '3'],
            ['name' => 'Trincomalee', 'code' => '53'],
            ['name' => 'Uva', 'code' => '8'],
            ['name' => 'Vavuniya', 'code' => '44'],
            ['name' => 'Western', 'code' => '1'],
        ];
    }
}
