<?php

declare(strict_types=1);

namespace AIArmada\Addressing\Geography\Burundi;

use AIArmada\Addressing\Contracts\AddressAreaSource;
use AIArmada\Addressing\Contracts\CountryAddressAreaMetadataProvider;
use AIArmada\Addressing\Contracts\CountryGeographyProvider;
use AIArmada\Addressing\Contracts\CountryHierarchyProvider;
use AIArmada\Addressing\Data\AddressHierarchyDefinition;
use AIArmada\Addressing\Data\AddressLevelDefinition;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Support\CsvAddressAreaSource;
use AIArmada\Addressing\Support\ModelResolver;

class BurundiGeographyProvider implements CountryAddressAreaMetadataProvider, CountryGeographyProvider, CountryHierarchyProvider
{
    public const string AREA_SOURCE = 'aiarmada_addressing_burundi_v1';

    private const string PROVIDER_KEY = 'aiarmada.addressing.burundi';

    public function providerKey(): string
    {
        return self::PROVIDER_KEY;
    }

    public function countryCode(): string
    {
        return 'BI';
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
                        label: 'Province',
                        kind: 'state',
                        hierarchyType: 'administrative',
                        areaTypes: ['province'],
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
                default => [],
            };

            $roles[$area->sourceId] = array_map(
                static fn (string $role): array => ['role' => $role, 'country_code' => 'BI', 'is_primary' => true],
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
            __DIR__ . '/../../../resources/geography/burundi-address-areas.csv',
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
            'BB' => 'BB',
            'BM' => 'BM',
            'BL' => 'BL',
            'BR' => 'BR',
            'CA' => 'CA',
            'CI' => 'CI',
            'GI' => 'GI',
            'KR' => 'KR',
            'KY' => 'KY',
            'KI' => 'KI',
            'MA' => 'MA',
            'MU' => 'MU',
            'MY' => 'MY',
            'MW' => 'MW',
            'NG' => 'NG',
            'RM' => 'RM',
            'RT' => 'RT',
            'RY' => 'RY',
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
            ['name' => 'Bubanza', 'code' => 'BB'],
            ['name' => 'Bujumbura Mairie', 'code' => 'BM'],
            ['name' => 'Bujumbura Rural', 'code' => 'BL'],
            ['name' => 'Bururi', 'code' => 'BR'],
            ['name' => 'Cankuzo', 'code' => 'CA'],
            ['name' => 'Cibitoke', 'code' => 'CI'],
            ['name' => 'Gitega', 'code' => 'GI'],
            ['name' => 'Karuzi', 'code' => 'KR'],
            ['name' => 'Kayanza', 'code' => 'KY'],
            ['name' => 'Kirundo', 'code' => 'KI'],
            ['name' => 'Makamba', 'code' => 'MA'],
            ['name' => 'Muramvya', 'code' => 'MU'],
            ['name' => 'Muyinga', 'code' => 'MY'],
            ['name' => 'Mwaro', 'code' => 'MW'],
            ['name' => 'Ngozi', 'code' => 'NG'],
            ['name' => 'Rumonge', 'code' => 'RM'],
            ['name' => 'Rutana', 'code' => 'RT'],
            ['name' => 'Ruyigi', 'code' => 'RY'],
        ];
    }
}
