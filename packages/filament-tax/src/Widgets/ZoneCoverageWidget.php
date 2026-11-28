<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Widgets;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Tax\Models\TaxZone;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

final class ZoneCoverageWidget extends Widget
{
    /** @var view-string */
    protected string $view = 'filament-tax::widgets.zone-coverage';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 3;

    public static function canView(): bool
    {
        $actor = Filament::auth()->user();

        return $actor !== null
            && $actor->can('tax.zones.view')
            && $actor->can('tax.rates.view');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        /** @var Builder<TaxZone> $query */
        $query = OwnerUiScope::apply(TaxZone::query(), includeGlobal: false);

        $zones = $query
            ->with(['rates' => fn (HasMany $rates): Builder => OwnerUiScope::apply($rates->getQuery(), includeGlobal: false)])
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
     * @return Collection<int, covariant array<string, mixed>>
     */
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

        return $formattedZones;
    }
}
