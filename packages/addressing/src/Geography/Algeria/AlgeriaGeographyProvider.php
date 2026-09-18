<?php

declare(strict_types=1);

namespace AIArmada\Addressing\Geography\Algeria;

use AIArmada\Addressing\Contracts\AddressAreaSource;
use AIArmada\Addressing\Contracts\CountryAddressAreaMetadataProvider;
use AIArmada\Addressing\Contracts\CountryGeographyProvider;
use AIArmada\Addressing\Contracts\CountryHierarchyProvider;
use AIArmada\Addressing\Data\AddressHierarchyDefinition;
use AIArmada\Addressing\Data\AddressLevelDefinition;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Support\CsvAddressAreaSource;
use AIArmada\Addressing\Support\ModelResolver;

class AlgeriaGeographyProvider implements CountryAddressAreaMetadataProvider, CountryGeographyProvider, CountryHierarchyProvider
{
    public const string AREA_SOURCE = 'aiarmada_addressing_algeria_v1';

    private const string PROVIDER_KEY = 'aiarmada.addressing.algeria';

    public function providerKey(): string
    {
        return self::PROVIDER_KEY;
    }

    public function countryCode(): string
    {
        return 'DZ';
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
                        key: 'wilaya',
                        label: 'Wilaya',
                        kind: 'state',
                        hierarchyType: 'administrative',
                        areaTypes: ['wilaya'],
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
                'wilaya' => ['wilaya'],
                default => [],
            };

            $roles[$area->sourceId] = array_map(
                static fn (string $role): array => ['role' => $role, 'country_code' => 'DZ', 'is_primary' => true],
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
            __DIR__ . '/../../../resources/geography/algeria-address-areas.csv',
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
            '01' => '01',
            '02' => '02',
            '03' => '03',
            '04' => '04',
            '05' => '05',
            '06' => '06',
            '07' => '07',
            '08' => '08',
            '09' => '09',
            '10' => '10',
            '11' => '11',
            '12' => '12',
            '13' => '13',
            '14' => '14',
            '15' => '15',
            '16' => '16',
            '17' => '17',
            '18' => '18',
            '19' => '19',
            '20' => '20',
            '21' => '21',
            '22' => '22',
            '23' => '23',
            '24' => '24',
            '25' => '25',
            '26' => '26',
            '27' => '27',
            '28' => '28',
            '29' => '29',
            '30' => '30',
            '31' => '31',
            '32' => '32',
            '33' => '33',
            '34' => '34',
            '35' => '35',
            '36' => '36',
            '37' => '37',
            '38' => '38',
            '39' => '39',
            '40' => '40',
            '41' => '41',
            '42' => '42',
            '43' => '43',
            '44' => '44',
            '45' => '45',
            '46' => '46',
            '47' => '47',
            '48' => '48',
            '49' => '49',
            '50' => '50',
            '51' => '51',
            '52' => '52',
            '53' => '53',
            '54' => '54',
            '55' => '55',
            '56' => '56',
            '57' => '57',
            '58' => '58',
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
            ['name' => 'Adrar', 'code' => '01'],
            ['name' => 'Chlef', 'code' => '02'],
            ['name' => 'Laghouat', 'code' => '03'],
            ['name' => 'Oum El Bouaghi', 'code' => '04'],
            ['name' => 'Batna', 'code' => '05'],
            ['name' => 'Béjaïa', 'code' => '06'],
            ['name' => 'Biskra', 'code' => '07'],
            ['name' => 'Béchar', 'code' => '08'],
            ['name' => 'Blida', 'code' => '09'],
            ['name' => 'Bouïra', 'code' => '10'],
            ['name' => 'Tamanghasset', 'code' => '11'],
            ['name' => 'Tébessa', 'code' => '12'],
            ['name' => 'Tlemcen', 'code' => '13'],
            ['name' => 'Tiaret', 'code' => '14'],
            ['name' => 'Tizi Ouzou', 'code' => '15'],
            ['name' => 'Alger', 'code' => '16'],
            ['name' => 'Djelfa', 'code' => '17'],
            ['name' => 'Jijel', 'code' => '18'],
            ['name' => 'Sétif', 'code' => '19'],
            ['name' => 'Saïda', 'code' => '20'],
            ['name' => 'Skikda', 'code' => '21'],
            ['name' => 'Sidi Bel Abbès', 'code' => '22'],
            ['name' => 'Annaba', 'code' => '23'],
            ['name' => 'Guelma', 'code' => '24'],
            ['name' => 'Constantine', 'code' => '25'],
            ['name' => 'Médéa', 'code' => '26'],
            ['name' => 'Mostaganem', 'code' => '27'],
            ['name' => 'M\'Sila', 'code' => '28'],
            ['name' => 'Mascara', 'code' => '29'],
            ['name' => 'Ouargla', 'code' => '30'],
            ['name' => 'Oran', 'code' => '31'],
            ['name' => 'El Bayadh', 'code' => '32'],
            ['name' => 'Illizi', 'code' => '33'],
            ['name' => 'Bordj Bou Arréridj', 'code' => '34'],
            ['name' => 'Boumerdès', 'code' => '35'],
            ['name' => 'El Tarf', 'code' => '36'],
            ['name' => 'Tindouf', 'code' => '37'],
            ['name' => 'Tissemsilt', 'code' => '38'],
            ['name' => 'El Oued', 'code' => '39'],
            ['name' => 'Khenchela', 'code' => '40'],
            ['name' => 'Souk Ahras', 'code' => '41'],
            ['name' => 'Tipasa', 'code' => '42'],
            ['name' => 'Mila', 'code' => '43'],
            ['name' => 'Aïn Defla', 'code' => '44'],
            ['name' => 'Naama', 'code' => '45'],
            ['name' => 'Aïn Témouchent', 'code' => '46'],
            ['name' => 'Ghardaïa', 'code' => '47'],
            ['name' => 'Relizane', 'code' => '48'],
            ['name' => 'El M\'ghair', 'code' => '49'],
            ['name' => 'El Menia', 'code' => '50'],
            ['name' => 'Ouled Djellal', 'code' => '51'],
            ['name' => 'Bordj Baji Mokhtar', 'code' => '52'],
            ['name' => 'Béni Abbès', 'code' => '53'],
            ['name' => 'Timimoun', 'code' => '54'],
            ['name' => 'Touggourt', 'code' => '55'],
            ['name' => 'Djanet', 'code' => '56'],
            ['name' => 'In Salah', 'code' => '57'],
            ['name' => 'In Guezzam', 'code' => '58'],
        ];
    }
}
