<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Models\FacilityType;

final class SeedFacilityTypesAction
{
    /**
     * Stable default place-facility catalog.
     *
     * @var list<array{code: string, name: string, category: string, sort_order: int}>
     */
    public const array DEFAULTS = [
        ['code' => 'parking', 'name' => 'Parking', 'category' => 'parking', 'sort_order' => 10],
        ['code' => 'parking_oku', 'name' => 'OKU Parking', 'category' => 'parking', 'sort_order' => 20],
        ['code' => 'aircond', 'name' => 'Air Conditioning', 'category' => 'comfort', 'sort_order' => 30],
        ['code' => 'wheelchair_access', 'name' => 'Wheelchair Access', 'category' => 'accessibility', 'sort_order' => 40],
        ['code' => 'wudu_area', 'name' => 'Wudu Area', 'category' => 'worship', 'sort_order' => 50],
    ];

    /**
     * Insert missing default catalog rows without touching existing rows.
     *
     * Existing codes keep their app-customized attributes, and unrelated
     * rows are never modified or removed. Applications seed extra types
     * directly on the FacilityType model.
     *
     * @return array{created: int, skipped: int}
     */
    public function execute(): array
    {
        return OwnerContext::withOwner(null, function (): array {
            $created = 0;
            $skipped = 0;

            foreach (self::DEFAULTS as $row) {
                $type = FacilityType::query()->firstOrCreate(['code' => $row['code']], $row);

                if ($type->wasRecentlyCreated) {
                    $created++;
                } else {
                    $skipped++;
                }
            }

            return ['created' => $created, 'skipped' => $skipped];
        });
    }
}
