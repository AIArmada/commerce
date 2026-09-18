<?php

declare(strict_types=1);

namespace AIArmada\Addressing\Geography\CzechRepublic;

use AIArmada\Addressing\Contracts\AddressAreaSource;
use AIArmada\Addressing\Contracts\CountryAddressAreaMetadataProvider;
use AIArmada\Addressing\Contracts\CountryGeographyProvider;
use AIArmada\Addressing\Contracts\CountryHierarchyProvider;
use AIArmada\Addressing\Data\AddressHierarchyDefinition;
use AIArmada\Addressing\Data\AddressLevelDefinition;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Support\CsvAddressAreaSource;
use AIArmada\Addressing\Support\ModelResolver;

class CzechRepublicGeographyProvider implements CountryAddressAreaMetadataProvider, CountryGeographyProvider, CountryHierarchyProvider
{
    public const string AREA_SOURCE = 'aiarmada_addressing_czech_republic_v1';

    private const string PROVIDER_KEY = 'aiarmada.addressing.czech_republic';

    public function providerKey(): string
    {
        return self::PROVIDER_KEY;
    }

    public function countryCode(): string
    {
        return 'CZ';
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
                        label: 'Region / District / Capital City',
                        kind: 'state',
                        hierarchyType: 'administrative',
                        areaTypes: ['region', 'district', 'capital_city'],
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
                'capital_city' => ['capital_city'],
                default => [],
            };

            $roles[$area->sourceId] = array_map(
                static fn (string $role): array => ['role' => $role, 'country_code' => 'CZ', 'is_primary' => true],
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
            __DIR__ . '/../../../resources/geography/czech-republic-address-areas.csv',
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
            '201' => '201',
            '202' => '202',
            '641' => '641',
            '644' => '644',
            '642' => '642',
            '643' => '643',
            '801' => '801',
            '511' => '511',
            '311' => '311',
            '312' => '312',
            '411' => '411',
            '422' => '422',
            '531' => '531',
            '421' => '421',
            '321' => '321',
            '802' => '802',
            '631' => '631',
            '645' => '645',
            '521' => '521',
            '512' => '512',
            '711' => '711',
            '522' => '522',
            '632' => '632',
            '31' => '31',
            '64' => '64',
            '313' => '313',
            '41' => '41',
            '412' => '412',
            '803' => '803',
            '203' => '203',
            '322' => '322',
            '204' => '204',
            '63' => '63',
            '52' => '52',
            '721' => '721',
            '205' => '205',
            '513' => '513',
            '51' => '51',
            '423' => '423',
            '424' => '424',
            '206' => '206',
            '207' => '207',
            '80' => '80',
            '425' => '425',
            '523' => '523',
            '804' => '804',
            '208' => '208',
            '712' => '712',
            '71' => '71',
            '805' => '805',
            '806' => '806',
            '532' => '532',
            '53' => '53',
            '633' => '633',
            '314' => '314',
            '324' => '324',
            '323' => '323',
            '325' => '325',
            '32' => '32',
            '315' => '315',
            '209' => '209',
            '20A' => '20A',
            '10' => '10',
            '714' => '714',
            '20B' => '20B',
            '713' => '713',
            '20C' => '20C',
            '326' => '326',
            '524' => '524',
            '514' => '514',
            '413' => '413',
            '316' => '316',
            '20' => '20',
            '715' => '715',
            '533' => '533',
            '317' => '317',
            '327' => '327',
            '426' => '426',
            '634' => '634',
            '525' => '525',
            '722' => '722',
            '42' => '42',
            '427' => '427',
            '534' => '534',
            '723' => '723',
            '646' => '646',
            '635' => '635',
            '724' => '724',
            '72' => '72',
            '647' => '647',
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
            ['name' => 'Benešov', 'code' => '201'],
            ['name' => 'Beroun', 'code' => '202'],
            ['name' => 'Blansko', 'code' => '641'],
            ['name' => 'Břeclav', 'code' => '644'],
            ['name' => 'Brno-město', 'code' => '642'],
            ['name' => 'Brno-venkov', 'code' => '643'],
            ['name' => 'Bruntál', 'code' => '801'],
            ['name' => 'Česká Lípa', 'code' => '511'],
            ['name' => 'České Budějovice', 'code' => '311'],
            ['name' => 'Český Krumlov', 'code' => '312'],
            ['name' => 'Cheb', 'code' => '411'],
            ['name' => 'Chomutov', 'code' => '422'],
            ['name' => 'Chrudim', 'code' => '531'],
            ['name' => 'Děčín', 'code' => '421'],
            ['name' => 'Domažlice', 'code' => '321'],
            ['name' => 'Frýdek-Místek', 'code' => '802'],
            ['name' => 'Havlíčkův Brod', 'code' => '631'],
            ['name' => 'Hodonín', 'code' => '645'],
            ['name' => 'Hradec Králové', 'code' => '521'],
            ['name' => 'Jablonec nad Nisou', 'code' => '512'],
            ['name' => 'Jeseník', 'code' => '711'],
            ['name' => 'Jičín', 'code' => '522'],
            ['name' => 'Jihlava', 'code' => '632'],
            ['name' => 'Jihočeský kraj', 'code' => '31'],
            ['name' => 'Jihomoravský kraj', 'code' => '64'],
            ['name' => 'Jindřichův Hradec', 'code' => '313'],
            ['name' => 'Karlovarský kraj', 'code' => '41'],
            ['name' => 'Karlovy Vary', 'code' => '412'],
            ['name' => 'Karviná', 'code' => '803'],
            ['name' => 'Kladno', 'code' => '203'],
            ['name' => 'Klatovy', 'code' => '322'],
            ['name' => 'Kolín', 'code' => '204'],
            ['name' => 'Kraj Vysočina', 'code' => '63'],
            ['name' => 'Královéhradecký kraj', 'code' => '52'],
            ['name' => 'Kroměříž', 'code' => '721'],
            ['name' => 'Kutná Hora', 'code' => '205'],
            ['name' => 'Liberec', 'code' => '513'],
            ['name' => 'Liberecký kraj', 'code' => '51'],
            ['name' => 'Litoměřice', 'code' => '423'],
            ['name' => 'Louny', 'code' => '424'],
            ['name' => 'Mělník', 'code' => '206'],
            ['name' => 'Mladá Boleslav', 'code' => '207'],
            ['name' => 'Moravskoslezský kraj', 'code' => '80'],
            ['name' => 'Most', 'code' => '425'],
            ['name' => 'Náchod', 'code' => '523'],
            ['name' => 'Nový Jičín', 'code' => '804'],
            ['name' => 'Nymburk', 'code' => '208'],
            ['name' => 'Olomouc', 'code' => '712'],
            ['name' => 'Olomoucký kraj', 'code' => '71'],
            ['name' => 'Opava', 'code' => '805'],
            ['name' => 'Ostrava-město', 'code' => '806'],
            ['name' => 'Pardubice', 'code' => '532'],
            ['name' => 'Pardubický kraj', 'code' => '53'],
            ['name' => 'Pelhřimov', 'code' => '633'],
            ['name' => 'Písek', 'code' => '314'],
            ['name' => 'Plzeň-jih', 'code' => '324'],
            ['name' => 'Plzeň-město', 'code' => '323'],
            ['name' => 'Plzeň-sever', 'code' => '325'],
            ['name' => 'Plzeňský kraj', 'code' => '32'],
            ['name' => 'Prachatice', 'code' => '315'],
            ['name' => 'Praha-východ', 'code' => '209'],
            ['name' => 'Praha-západ', 'code' => '20A'],
            ['name' => 'Praha, Hlavní město', 'code' => '10'],
            ['name' => 'Přerov', 'code' => '714'],
            ['name' => 'Příbram', 'code' => '20B'],
            ['name' => 'Prostějov', 'code' => '713'],
            ['name' => 'Rakovník', 'code' => '20C'],
            ['name' => 'Rokycany', 'code' => '326'],
            ['name' => 'Rychnov nad Kněžnou', 'code' => '524'],
            ['name' => 'Semily', 'code' => '514'],
            ['name' => 'Sokolov', 'code' => '413'],
            ['name' => 'Strakonice', 'code' => '316'],
            ['name' => 'Středočeský kraj', 'code' => '20'],
            ['name' => 'Šumperk', 'code' => '715'],
            ['name' => 'Svitavy', 'code' => '533'],
            ['name' => 'Tábor', 'code' => '317'],
            ['name' => 'Tachov', 'code' => '327'],
            ['name' => 'Teplice', 'code' => '426'],
            ['name' => 'Třebíč', 'code' => '634'],
            ['name' => 'Trutnov', 'code' => '525'],
            ['name' => 'Uherské Hradiště', 'code' => '722'],
            ['name' => 'Ústecký kraj', 'code' => '42'],
            ['name' => 'Ústí nad Labem', 'code' => '427'],
            ['name' => 'Ústí nad Orlicí', 'code' => '534'],
            ['name' => 'Vsetín', 'code' => '723'],
            ['name' => 'Vyškov', 'code' => '646'],
            ['name' => 'Žďár nad Sázavou', 'code' => '635'],
            ['name' => 'Zlín', 'code' => '724'],
            ['name' => 'Zlínský kraj', 'code' => '72'],
            ['name' => 'Znojmo', 'code' => '647'],
        ];
    }
}
