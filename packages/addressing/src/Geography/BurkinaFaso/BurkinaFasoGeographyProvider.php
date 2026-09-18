<?php

declare(strict_types=1);

namespace AIArmada\Addressing\Geography\BurkinaFaso;

use AIArmada\Addressing\Contracts\AddressAreaSource;
use AIArmada\Addressing\Contracts\CountryAddressAreaMetadataProvider;
use AIArmada\Addressing\Contracts\CountryGeographyProvider;
use AIArmada\Addressing\Contracts\CountryHierarchyProvider;
use AIArmada\Addressing\Data\AddressHierarchyDefinition;
use AIArmada\Addressing\Data\AddressLevelDefinition;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Support\CsvAddressAreaSource;
use AIArmada\Addressing\Support\ModelResolver;

class BurkinaFasoGeographyProvider implements CountryAddressAreaMetadataProvider, CountryGeographyProvider, CountryHierarchyProvider
{
    public const string AREA_SOURCE = 'aiarmada_addressing_burkina_faso_v1';

    private const string PROVIDER_KEY = 'aiarmada.addressing.burkina_faso';

    public function providerKey(): string
    {
        return self::PROVIDER_KEY;
    }

    public function countryCode(): string
    {
        return 'BF';
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
                        label: 'Region / Province',
                        kind: 'state',
                        hierarchyType: 'administrative',
                        areaTypes: ['region', 'province'],
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
                default => [],
            };

            $roles[$area->sourceId] = array_map(
                static fn (string $role): array => ['role' => $role, 'country_code' => 'BF', 'is_primary' => true],
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
            __DIR__ . '/../../../resources/geography/burkina-faso-address-areas.csv',
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
            'BAL' => 'BAL',
            'BAM' => 'BAM',
            'BAN' => 'BAN',
            'BAZ' => 'BAZ',
            '01' => '01',
            'BGR' => 'BGR',
            'BLG' => 'BLG',
            'BLK' => 'BLK',
            '02' => '02',
            '03' => '03',
            '04' => '04',
            '05' => '05',
            '06' => '06',
            '07' => '07',
            'COM' => 'COM',
            '08' => '08',
            'GAN' => 'GAN',
            'GNA' => 'GNA',
            'GOU' => 'GOU',
            '09' => '09',
            'HOU' => 'HOU',
            'IOB' => 'IOB',
            'KAD' => 'KAD',
            'KEN' => 'KEN',
            'KMD' => 'KMD',
            'KMP' => 'KMP',
            'KOS' => 'KOS',
            'KOP' => 'KOP',
            'KOT' => 'KOT',
            'KOW' => 'KOW',
            'LER' => 'LER',
            'LOR' => 'LOR',
            'MOU' => 'MOU',
            'NAO' => 'NAO',
            'NAM' => 'NAM',
            'NAY' => 'NAY',
            '10' => '10',
            'NOU' => 'NOU',
            'OUB' => 'OUB',
            'OUD' => 'OUD',
            'PAS' => 'PAS',
            '11' => '11',
            'PON' => 'PON',
            '12' => '12',
            'SNG' => 'SNG',
            'SMT' => 'SMT',
            'SEN' => 'SEN',
            'SIS' => 'SIS',
            'SOM' => 'SOM',
            'SOR' => 'SOR',
            '13' => '13',
            'TAP' => 'TAP',
            'TUI' => 'TUI',
            'YAG' => 'YAG',
            'YAT' => 'YAT',
            'ZIR' => 'ZIR',
            'ZON' => 'ZON',
            'ZOU' => 'ZOU',
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
            ['name' => 'Balé', 'code' => 'BAL'],
            ['name' => 'Bam', 'code' => 'BAM'],
            ['name' => 'Banwa', 'code' => 'BAN'],
            ['name' => 'Bazèga', 'code' => 'BAZ'],
            ['name' => 'Boucle du Mouhoun', 'code' => '01'],
            ['name' => 'Bougouriba', 'code' => 'BGR'],
            ['name' => 'Boulgou', 'code' => 'BLG'],
            ['name' => 'Boulkiemde', 'code' => 'BLK'],
            ['name' => 'Cascades', 'code' => '02'],
            ['name' => 'Centre', 'code' => '03'],
            ['name' => 'Centre-Est', 'code' => '04'],
            ['name' => 'Centre-Nord', 'code' => '05'],
            ['name' => 'Centre-Ouest', 'code' => '06'],
            ['name' => 'Centre-Sud', 'code' => '07'],
            ['name' => 'Comoé', 'code' => 'COM'],
            ['name' => 'Est', 'code' => '08'],
            ['name' => 'Ganzourgou', 'code' => 'GAN'],
            ['name' => 'Gnagna', 'code' => 'GNA'],
            ['name' => 'Gourma', 'code' => 'GOU'],
            ['name' => 'Hauts-Bassins', 'code' => '09'],
            ['name' => 'Houet', 'code' => 'HOU'],
            ['name' => 'Ioba', 'code' => 'IOB'],
            ['name' => 'Kadiogo', 'code' => 'KAD'],
            ['name' => 'Kénédougou', 'code' => 'KEN'],
            ['name' => 'Komondjari', 'code' => 'KMD'],
            ['name' => 'Kompienga', 'code' => 'KMP'],
            ['name' => 'Kossi', 'code' => 'KOS'],
            ['name' => 'Koulpélogo', 'code' => 'KOP'],
            ['name' => 'Kouritenga', 'code' => 'KOT'],
            ['name' => 'Kourwéogo', 'code' => 'KOW'],
            ['name' => 'Léraba', 'code' => 'LER'],
            ['name' => 'Loroum', 'code' => 'LOR'],
            ['name' => 'Mouhoun', 'code' => 'MOU'],
            ['name' => 'Nahouri', 'code' => 'NAO'],
            ['name' => 'Namentenga', 'code' => 'NAM'],
            ['name' => 'Nayala', 'code' => 'NAY'],
            ['name' => 'Nord', 'code' => '10'],
            ['name' => 'Noumbiel', 'code' => 'NOU'],
            ['name' => 'Oubritenga', 'code' => 'OUB'],
            ['name' => 'Oudalan', 'code' => 'OUD'],
            ['name' => 'Passoré', 'code' => 'PAS'],
            ['name' => 'Plateau-Central', 'code' => '11'],
            ['name' => 'Poni', 'code' => 'PON'],
            ['name' => 'Sahel', 'code' => '12'],
            ['name' => 'Sanguié', 'code' => 'SNG'],
            ['name' => 'Sanmatenga', 'code' => 'SMT'],
            ['name' => 'Séno', 'code' => 'SEN'],
            ['name' => 'Sissili', 'code' => 'SIS'],
            ['name' => 'Soum', 'code' => 'SOM'],
            ['name' => 'Sourou', 'code' => 'SOR'],
            ['name' => 'Sud-Ouest', 'code' => '13'],
            ['name' => 'Tapoa', 'code' => 'TAP'],
            ['name' => 'Tuy', 'code' => 'TUI'],
            ['name' => 'Yagha', 'code' => 'YAG'],
            ['name' => 'Yatenga', 'code' => 'YAT'],
            ['name' => 'Ziro', 'code' => 'ZIR'],
            ['name' => 'Zondoma', 'code' => 'ZON'],
            ['name' => 'Zoundwéogo', 'code' => 'ZOU'],
        ];
    }
}
