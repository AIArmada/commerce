<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Widgets;

use AIArmada\Tax\Models\TaxZone;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class ZoneCoverageWidget extends Widget
{
    /** @var view-string */
    protected string $view = 'filament-tax::widgets.zone-coverage';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 3;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        /** @var Builder<TaxZone> $query */
        $query = TaxZone::query();

        $zones = $query
            ->with('rates')
            ->active()
            ->orderBy('priority', 'desc')
            ->limit(50)
            ->get();

        return [
            'zones' => $this->formatZones($zones),
        ];
    }

    /**
     * @param  Collection<int, TaxZone>  $zones
     * @return Collection<int, array{
     *     id: string,
     *     name: string,
     *     code: string,
     *     type: string,
     *     countries: array,
     *     states: array,
     *     priority: int,
     *     is_default: bool,
     *     rates: array<int, array{name: string, class: string, rate: string, is_compound: bool}>,
     *     rate_count: int
     * }>
     */
    // @phpstan-ignore-next-line Collection covariance false positive with exact array shape.
    protected function formatZones(Collection $zones): Collection
    {
        $formattedZones = $zones->map(function (TaxZone $zone): array {
            $rates = $zone->rates
                ->map(fn ($rate): array => [
                    'name' => $rate->name,
                    'class' => ucfirst($rate->tax_class),
                    'rate' => number_format($rate->rate / 100, 2) . '%',
                    'is_compound' => (bool) $rate->is_compound,
                ])
                ->values()
                ->all();

            return [
                'id' => $zone->id,
                'name' => $zone->name,
                'code' => $zone->code,
                'type' => ucfirst($zone->type->value),
                'countries' => $zone->countries ?? [],
                'states' => $zone->states ?? [],
                'priority' => $zone->priority,
                'is_default' => $zone->is_default,
                'rates' => $rates,
                'rate_count' => $zone->rates->count(),
            ];
        })->values();

        // @phpstan-ignore-next-line Collection covariance false positive with exact array shape.
        return $formattedZones;
    }
}
