<?php

declare(strict_types=1);

namespace AIArmada\Addressing\Geography\Estonia;

use AIArmada\Addressing\Contracts\AddressAreaSource;
use AIArmada\Addressing\Contracts\CountryAddressAreaMetadataProvider;
use AIArmada\Addressing\Contracts\CountryGeographyProvider;
use AIArmada\Addressing\Contracts\CountryHierarchyProvider;
use AIArmada\Addressing\Data\AddressHierarchyDefinition;
use AIArmada\Addressing\Data\AddressLevelDefinition;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Support\CsvAddressAreaSource;
use AIArmada\Addressing\Support\ModelResolver;

class EstoniaGeographyProvider implements CountryAddressAreaMetadataProvider, CountryGeographyProvider, CountryHierarchyProvider
{
    public const string AREA_SOURCE = 'aiarmada_addressing_estonia_v1';

    private const string PROVIDER_KEY = 'aiarmada.addressing.estonia';

    public function providerKey(): string
    {
        return self::PROVIDER_KEY;
    }

    public function countryCode(): string
    {
        return 'EE';
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
                        key: 'county',
                        label: 'County / Rural Municipality / Urban Municipality',
                        kind: 'state',
                        hierarchyType: 'administrative',
                        areaTypes: ['county', 'rural_municipality', 'urban_municipality'],
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
                'county' => ['county'],
                'rural_municipality' => ['rural_municipality'],
                'urban_municipality' => ['urban_municipality'],
                default => [],
            };

            $roles[$area->sourceId] = array_map(
                static fn (string $role): array => ['role' => $role, 'country_code' => 'EE', 'is_primary' => true],
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
            __DIR__ . '/../../../resources/geography/estonia-address-areas.csv',
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
            '130' => '130',
            '141' => '141',
            '142' => '142',
            '171' => '171',
            '214' => '214',
            '184' => '184',
            '191' => '191',
            '37' => '37',
            '198' => '198',
            '39' => '39',
            '205' => '205',
            '45' => '45',
            '52' => '52',
            '255' => '255',
            '245' => '245',
            '50' => '50',
            '247' => '247',
            '251' => '251',
            '272' => '272',
            '283' => '283',
            '284' => '284',
            '291' => '291',
            '293' => '293',
            '296' => '296',
            '303' => '303',
            '305' => '305',
            '317' => '317',
            '321' => '321',
            '338' => '338',
            '353' => '353',
            '56' => '56',
            '430' => '430',
            '441' => '441',
            '60' => '60',
            '431' => '431',
            '424' => '424',
            '442' => '442',
            '432' => '432',
            '446' => '446',
            '503' => '503',
            '478' => '478',
            '480' => '480',
            '486' => '486',
            '511' => '511',
            '514' => '514',
            '528' => '528',
            '557' => '557',
            '567' => '567',
            '68' => '68',
            '624' => '624',
            '586' => '586',
            '638' => '638',
            '615' => '615',
            '618' => '618',
            '622' => '622',
            '64' => '64',
            '651' => '651',
            '653' => '653',
            '663' => '663',
            '661' => '661',
            '708' => '708',
            '668' => '668',
            '71' => '71',
            '698' => '698',
            '689' => '689',
            '712' => '712',
            '74' => '74',
            '714' => '714',
            '719' => '719',
            '726' => '726',
            '732' => '732',
            '735' => '735',
            '784' => '784',
            '792' => '792',
            '793' => '793',
            '79' => '79',
            '796' => '796',
            '803' => '803',
            '809' => '809',
            '824' => '824',
            '834' => '834',
            '928' => '928',
            '855' => '855',
            '81' => '81',
            '890' => '890',
            '897' => '897',
            '84' => '84',
            '899' => '899',
            '901' => '901',
            '903' => '903',
            '907' => '907',
            '917' => '917',
            '87' => '87',
            '919' => '919',
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
            ['name' => 'Alutaguse', 'code' => '130'],
            ['name' => 'Anija', 'code' => '141'],
            ['name' => 'Antsla', 'code' => '142'],
            ['name' => 'Elva', 'code' => '171'],
            ['name' => 'Häädemeeste', 'code' => '214'],
            ['name' => 'Haapsalu', 'code' => '184'],
            ['name' => 'Haljala', 'code' => '191'],
            ['name' => 'Harju', 'code' => '37'],
            ['name' => 'Harku', 'code' => '198'],
            ['name' => 'Hiiu', 'code' => '39'],
            ['name' => 'Hiiumaa', 'code' => '205'],
            ['name' => 'Ida-Viru', 'code' => '45'],
            ['name' => 'Järva', 'code' => '52'],
            ['name' => 'Järva', 'code' => '255'],
            ['name' => 'Joelähtme', 'code' => '245'],
            ['name' => 'Jõgeva', 'code' => '50'],
            ['name' => 'Jõgeva', 'code' => '247'],
            ['name' => 'Jõhvi', 'code' => '251'],
            ['name' => 'Kadrina', 'code' => '272'],
            ['name' => 'Kambja', 'code' => '283'],
            ['name' => 'Kanepi', 'code' => '284'],
            ['name' => 'Kastre', 'code' => '291'],
            ['name' => 'Kehtna', 'code' => '293'],
            ['name' => 'Keila', 'code' => '296'],
            ['name' => 'Kihnu', 'code' => '303'],
            ['name' => 'Kiili', 'code' => '305'],
            ['name' => 'Kohila', 'code' => '317'],
            ['name' => 'Kohtla-Järve', 'code' => '321'],
            ['name' => 'Kose', 'code' => '338'],
            ['name' => 'Kuusalu', 'code' => '353'],
            ['name' => 'Lääne', 'code' => '56'],
            ['name' => 'Lääne-Harju', 'code' => '430'],
            ['name' => 'Lääne-Nigula', 'code' => '441'],
            ['name' => 'Lääne-Viru', 'code' => '60'],
            ['name' => 'Lääneranna', 'code' => '431'],
            ['name' => 'Loksa', 'code' => '424'],
            ['name' => 'Lüganuse', 'code' => '442'],
            ['name' => 'Luunja', 'code' => '432'],
            ['name' => 'Maardu', 'code' => '446'],
            ['name' => 'Märjamaa', 'code' => '503'],
            ['name' => 'Muhu', 'code' => '478'],
            ['name' => 'Mulgi', 'code' => '480'],
            ['name' => 'Mustvee', 'code' => '486'],
            ['name' => 'Narva', 'code' => '511'],
            ['name' => 'Narva-Jõesuu', 'code' => '514'],
            ['name' => 'Noo', 'code' => '528'],
            ['name' => 'Otepää', 'code' => '557'],
            ['name' => 'Paide', 'code' => '567'],
            ['name' => 'Pärnu', 'code' => '68'],
            ['name' => 'Pärnu', 'code' => '624'],
            ['name' => 'Peipsiääre', 'code' => '586'],
            ['name' => 'Põhja-Pärnu', 'code' => '638'],
            ['name' => 'Põhja-Sakala', 'code' => '615'],
            ['name' => 'Poltsamaa', 'code' => '618'],
            ['name' => 'Põlva', 'code' => '622'],
            ['name' => 'Põlva', 'code' => '64'],
            ['name' => 'Raasiku', 'code' => '651'],
            ['name' => 'Rae', 'code' => '653'],
            ['name' => 'Rakvere', 'code' => '663'],
            ['name' => 'Rakvere', 'code' => '661'],
            ['name' => 'Räpina', 'code' => '708'],
            ['name' => 'Rapla', 'code' => '668'],
            ['name' => 'Rapla', 'code' => '71'],
            ['name' => 'Rõuge', 'code' => '698'],
            ['name' => 'Ruhnu', 'code' => '689'],
            ['name' => 'Saarde', 'code' => '712'],
            ['name' => 'Saare', 'code' => '74'],
            ['name' => 'Saaremaa', 'code' => '714'],
            ['name' => 'Saku', 'code' => '719'],
            ['name' => 'Saue', 'code' => '726'],
            ['name' => 'Setomaa', 'code' => '732'],
            ['name' => 'Sillamäe', 'code' => '735'],
            ['name' => 'Tallinn', 'code' => '784'],
            ['name' => 'Tapa', 'code' => '792'],
            ['name' => 'Tartu', 'code' => '793'],
            ['name' => 'Tartu', 'code' => '79'],
            ['name' => 'Tartu', 'code' => '796'],
            ['name' => 'Toila', 'code' => '803'],
            ['name' => 'Tori', 'code' => '809'],
            ['name' => 'Tõrva', 'code' => '824'],
            ['name' => 'Türi', 'code' => '834'],
            ['name' => 'Väike-Maarja', 'code' => '928'],
            ['name' => 'Valga', 'code' => '855'],
            ['name' => 'Valga', 'code' => '81'],
            ['name' => 'Viimsi', 'code' => '890'],
            ['name' => 'Viljandi', 'code' => '897'],
            ['name' => 'Viljandi', 'code' => '84'],
            ['name' => 'Viljandi', 'code' => '899'],
            ['name' => 'Vinni', 'code' => '901'],
            ['name' => 'Viru-Nigula', 'code' => '903'],
            ['name' => 'Vormsi', 'code' => '907'],
            ['name' => 'Võru', 'code' => '917'],
            ['name' => 'Võru', 'code' => '87'],
            ['name' => 'Võru', 'code' => '919'],
        ];
    }
}
