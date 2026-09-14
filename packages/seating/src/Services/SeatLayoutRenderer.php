<?php

declare(strict_types=1);

namespace AIArmada\Seating\Services;

use AIArmada\Seating\Contracts\SeatLayoutInterface;
use AIArmada\Seating\Models\SeatMap;

final class SeatLayoutRenderer implements SeatLayoutInterface
{
    public function describe(SeatMap $map, ?string $sectionId = null): array
    {
        $sections = [];
        $seats = [];
        $maxRows = 0;
        $maxCols = 0;

        $loadedSections = $map->sections()
            ->when($sectionId !== null, fn ($query) => $query->whereKey($sectionId))
            ->orderBy('sort_order')
            ->with(['seats' => fn ($query): mixed => $query->orderBy('row_number')->orderBy('column_number')])
            ->get();

        foreach ($loadedSections as $section) {
            $sectionSeats = [];

            foreach ($section->seats as $seat) {
                if ($seat->row_number > $maxRows) {
                    $maxRows = $seat->row_number;
                }

                if ($seat->column_number > $maxCols) {
                    $maxCols = $seat->column_number;
                }

                $sectionSeats[] = [
                    'id' => $seat->id,
                    'section' => $section->code,
                    'row' => $seat->row_label,
                    'label' => $seat->seat_label,
                    'column' => $seat->column_number,
                    'category' => $seat->category,
                ];
            }

            $seats = array_merge($seats, $sectionSeats);
            $sections[] = [
                'id' => $section->id,
                'code' => $section->code,
                'name' => $section->name,
                'sort_order' => $section->sort_order,
                'seats' => $sectionSeats,
            ];
        }

        return [
            'map' => [
                'id' => $map->id,
                'name' => $map->name,
                'slug' => $map->slug,
            ],
            'sections' => $sections,
            'seats' => $seats,
            'bounds' => [
                'rows' => $maxRows,
                'cols' => $maxCols,
            ],
        ];
    }
}
