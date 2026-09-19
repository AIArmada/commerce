<?php

declare(strict_types=1);

namespace AIArmada\Addressing\Geography\DominicanRepublic;

use AIArmada\Addressing\Contracts\AddressAreaSource;
use AIArmada\Addressing\Contracts\CountryAddressAreaMetadataProvider;
use AIArmada\Addressing\Contracts\CountryGeographyProvider;
use AIArmada\Addressing\Contracts\CountryHierarchyProvider;
use AIArmada\Addressing\Data\AddressHierarchyDefinition;
use AIArmada\Addressing\Data\AddressLevelDefinition;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Support\CsvAddressAreaSource;
use AIArmada\Addressing\Support\ModelResolver;

class DominicanRepublicGeographyProvider implements CountryAddressAreaMetadataProvider, CountryGeographyProvider, CountryHierarchyProvider
{
    public const string AREA_SOURCE = 'aiarmada_addressing_dominican_republic_v1';

    private const string PROVIDER_KEY = 'aiarmada.addressing.dominican_republic';

    public function providerKey(): string
    {
        return self::PROVIDER_KEY;
    }

    public function countryCode(): string
    {
        return 'DO';
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
                        label: 'Region / Province / District',
                        kind: 'state',
                        hierarchyType: 'administrative',
                        areaTypes: ['region', 'province', 'district'],
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
                'province' => ['province'],
                'district' => ['district'],
                default => [],
            };

            $roles[$area->sourceId] = array_map(
                static fn (string $role): array => ['role' => $role, 'country_code' => 'DO', 'is_primary' => true],
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
            __DIR__ . '/../../../resources/geography/dominican-republic-address-areas.csv',
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
            '02' => '02',
            '03' => '03',
            '04' => '04',
            '33' => '33',
            '34' => '34',
            '35' => '35',
            '36' => '36',
            '05' => '05',
            '01' => '01',
            '06' => '06',
            '08' => '08',
            '37' => '37',
            '07' => '07',
            '38' => '38',
            '09' => '09',
            '30' => '30',
            '19' => '19',
            '39' => '39',
            '10' => '10',
            '11' => '11',
            '12' => '12',
            '13' => '13',
            '14' => '14',
            '28' => '28',
            '15' => '15',
            '29' => '29',
            '40' => '40',
            '16' => '16',
            '17' => '17',
            '18' => '18',
            '20' => '20',
            '21' => '21',
            '31' => '31',
            '22' => '22',
            '23' => '23',
            '24' => '24',
            '25' => '25',
            '26' => '26',
            '32' => '32',
            '41' => '41',
            '27' => '27',
            '42' => '42',
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
            ['name' => 'Azua', 'code' => '02'],
            ['name' => 'Baoruco', 'code' => '03'],
            ['name' => 'Barahona', 'code' => '04'],
            ['name' => 'Cibao Nordeste', 'code' => '33'],
            ['name' => 'Cibao Noroeste', 'code' => '34'],
            ['name' => 'Cibao Norte', 'code' => '35'],
            ['name' => 'Cibao Sur', 'code' => '36'],
            ['name' => 'Dajabón', 'code' => '05'],
            ['name' => 'Distrito Nacional', 'code' => '01'],
            ['name' => 'Duarte', 'code' => '06'],
            ['name' => 'El Seibo', 'code' => '08'],
            ['name' => 'El Valle', 'code' => '37'],
            ['name' => 'Elías Piña', 'code' => '07'],
            ['name' => 'Enriquillo', 'code' => '38'],
            ['name' => 'Espaillat', 'code' => '09'],
            ['name' => 'Hato Mayor', 'code' => '30'],
            ['name' => 'Hermanas Mirabal', 'code' => '19'],
            ['name' => 'Higuamo', 'code' => '39'],
            ['name' => 'Independencia', 'code' => '10'],
            ['name' => 'La Altagracia', 'code' => '11'],
            ['name' => 'La Romana', 'code' => '12'],
            ['name' => 'La Vega', 'code' => '13'],
            ['name' => 'María Trinidad Sánchez', 'code' => '14'],
            ['name' => 'Monseñor Nouel', 'code' => '28'],
            ['name' => 'Monte Cristi', 'code' => '15'],
            ['name' => 'Monte Plata', 'code' => '29'],
            ['name' => 'Ozama', 'code' => '40'],
            ['name' => 'Pedernales', 'code' => '16'],
            ['name' => 'Peravia', 'code' => '17'],
            ['name' => 'Puerto Plata', 'code' => '18'],
            ['name' => 'Samaná', 'code' => '20'],
            ['name' => 'San Cristóbal', 'code' => '21'],
            ['name' => 'San José de Ocoa', 'code' => '31'],
            ['name' => 'San Juan', 'code' => '22'],
            ['name' => 'San Pedro de Macorís', 'code' => '23'],
            ['name' => 'Sánchez Ramírez', 'code' => '24'],
            ['name' => 'Santiago', 'code' => '25'],
            ['name' => 'Santiago Rodríguez', 'code' => '26'],
            ['name' => 'Santo Domingo', 'code' => '32'],
            ['name' => 'Valdesia', 'code' => '41'],
            ['name' => 'Valverde', 'code' => '27'],
            ['name' => 'Yuma', 'code' => '42'],
        ];
    }
}
