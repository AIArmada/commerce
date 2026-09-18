<?php

declare(strict_types=1);

namespace AIArmada\Addressing\Geography\Guinea;

use AIArmada\Addressing\Contracts\AddressAreaSource;
use AIArmada\Addressing\Contracts\CountryAddressAreaMetadataProvider;
use AIArmada\Addressing\Contracts\CountryGeographyProvider;
use AIArmada\Addressing\Contracts\CountryHierarchyProvider;
use AIArmada\Addressing\Data\AddressHierarchyDefinition;
use AIArmada\Addressing\Data\AddressLevelDefinition;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Support\CsvAddressAreaSource;
use AIArmada\Addressing\Support\ModelResolver;

class GuineaGeographyProvider implements CountryAddressAreaMetadataProvider, CountryGeographyProvider, CountryHierarchyProvider
{
    public const string AREA_SOURCE = 'aiarmada_addressing_guinea_v1';

    private const string PROVIDER_KEY = 'aiarmada.addressing.guinea';

    public function providerKey(): string
    {
        return self::PROVIDER_KEY;
    }

    public function countryCode(): string
    {
        return 'GN';
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
                        key: 'administrative_region',
                        label: 'Administrative Region / Governorate / Prefecture',
                        kind: 'state',
                        hierarchyType: 'administrative',
                        areaTypes: ['administrative_region', 'governorate', 'prefecture'],
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
                'administrative_region' => ['administrative_region'],
                'governorate' => ['governorate'],
                'prefecture' => ['prefecture'],
                default => [],
            };

            $roles[$area->sourceId] = array_map(
                static fn (string $role): array => ['role' => $role, 'country_code' => 'GN', 'is_primary' => true],
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
            __DIR__ . '/../../../resources/geography/guinea-address-areas.csv',
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
            'BE' => 'BE',
            'BF' => 'BF',
            'B' => 'B',
            'BK' => 'BK',
            'C' => 'C',
            'CO' => 'CO',
            'DB' => 'DB',
            'DL' => 'DL',
            'DI' => 'DI',
            'DU' => 'DU',
            'F' => 'F',
            'FA' => 'FA',
            'FO' => 'FO',
            'FR' => 'FR',
            'GA' => 'GA',
            'GU' => 'GU',
            'KA' => 'KA',
            'K' => 'K',
            'KE' => 'KE',
            'D' => 'D',
            'KD' => 'KD',
            'KS' => 'KS',
            'KB' => 'KB',
            'KN' => 'KN',
            'KO' => 'KO',
            'LA' => 'LA',
            'L' => 'L',
            'LE' => 'LE',
            'LO' => 'LO',
            'MC' => 'MC',
            'ML' => 'ML',
            'M' => 'M',
            'MM' => 'MM',
            'MD' => 'MD',
            'N' => 'N',
            'NZ' => 'NZ',
            'PI' => 'PI',
            'SI' => 'SI',
            'TE' => 'TE',
            'TO' => 'TO',
            'YO' => 'YO',
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
            ['name' => 'Beyla', 'code' => 'BE'],
            ['name' => 'Boffa', 'code' => 'BF'],
            ['name' => 'Boké', 'code' => 'B'],
            ['name' => 'Boké', 'code' => 'BK'],
            ['name' => 'Conakry', 'code' => 'C'],
            ['name' => 'Coyah', 'code' => 'CO'],
            ['name' => 'Dabola', 'code' => 'DB'],
            ['name' => 'Dalaba', 'code' => 'DL'],
            ['name' => 'Dinguiraye', 'code' => 'DI'],
            ['name' => 'Dubréka', 'code' => 'DU'],
            ['name' => 'Faranah', 'code' => 'F'],
            ['name' => 'Faranah', 'code' => 'FA'],
            ['name' => 'Forécariah', 'code' => 'FO'],
            ['name' => 'Fria', 'code' => 'FR'],
            ['name' => 'Gaoual', 'code' => 'GA'],
            ['name' => 'Guéckédou', 'code' => 'GU'],
            ['name' => 'Kankan', 'code' => 'KA'],
            ['name' => 'Kankan', 'code' => 'K'],
            ['name' => 'Kérouané', 'code' => 'KE'],
            ['name' => 'Kindia', 'code' => 'D'],
            ['name' => 'Kindia', 'code' => 'KD'],
            ['name' => 'Kissidougou', 'code' => 'KS'],
            ['name' => 'Koubia', 'code' => 'KB'],
            ['name' => 'Koundara', 'code' => 'KN'],
            ['name' => 'Kouroussa', 'code' => 'KO'],
            ['name' => 'Labé', 'code' => 'LA'],
            ['name' => 'Labé', 'code' => 'L'],
            ['name' => 'Lélouma', 'code' => 'LE'],
            ['name' => 'Lola', 'code' => 'LO'],
            ['name' => 'Macenta', 'code' => 'MC'],
            ['name' => 'Mali', 'code' => 'ML'],
            ['name' => 'Mamou', 'code' => 'M'],
            ['name' => 'Mamou', 'code' => 'MM'],
            ['name' => 'Mandiana', 'code' => 'MD'],
            ['name' => 'Nzérékoré', 'code' => 'N'],
            ['name' => 'Nzérékoré', 'code' => 'NZ'],
            ['name' => 'Pita', 'code' => 'PI'],
            ['name' => 'Siguiri', 'code' => 'SI'],
            ['name' => 'Télimélé', 'code' => 'TE'],
            ['name' => 'Tougué', 'code' => 'TO'],
            ['name' => 'Yomou', 'code' => 'YO'],
        ];
    }
}
