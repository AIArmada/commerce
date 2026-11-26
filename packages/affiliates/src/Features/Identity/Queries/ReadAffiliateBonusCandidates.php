<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Queries;

use AIArmada\Affiliates\Features\Identity\Api\AffiliateBonusCandidate;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateBonusReader;
use AIArmada\Affiliates\Features\Identity\Models\Affiliate;
use AIArmada\Affiliates\States\Active;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final readonly class ReadAffiliateBonusCandidates implements AffiliateBonusReader
{
    public function candidates(bool $includeGlobal): iterable
    {
        foreach ($this->eligible($includeGlobal)->select(['id', 'name', 'currency'])->lazyById(100) as $affiliate) {
            yield new AffiliateBonusCandidate($affiliate->id, $affiliate->name, $affiliate->currency);
        }
    }

    public function currencies(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids, static fn (string $id): bool => Str::isUuid($id))));
        $currencies = [];

        foreach (array_chunk($ids, 500) as $chunk) {
            foreach ($this->eligible(false)->whereKey($chunk)->pluck('currency', 'id') as $id => $currency) {
                $currencies[(string) $id] = $currency === null ? null : (string) $currency;
            }
        }

        return $currencies;
    }

    public function recruiters(CarbonImmutable $from, CarbonImmutable $to, int $minimum, bool $includeGlobal): iterable
    {
        $parentTable = (new Affiliate)->getTable();
        $children = static function (Builder $query) use ($from, $to, $includeGlobal, $parentTable): void {
            /** @var Builder<Affiliate> $query */
            $query->forOwner(includeGlobal: $includeGlobal)
                ->whereBetween('created_at', [$from, $to])
                ->where('status', Active::$name)
                ->whereCommercialPurpose();

            if (config('affiliates.owner.enabled', false)) {
                foreach (['owner_type', 'owner_id'] as $column) {
                    $left = $query->getModel()->qualifyColumn($column);
                    $right = $parentTable . '.' . $column;
                    $query->where(static fn (Builder $nested) => $nested->whereColumn($left, $right)
                        ->orWhere(static fn (Builder $both) => $both->whereNull($left)->whereNull($right)));
                }
            }
        };

        foreach ($this->eligible($includeGlobal)
            ->select(['id', 'name', 'currency'])
            ->withCount(['children' => $children])
            ->whereHas('children', $children, '>=', $minimum)
            ->lazyById(100) as $affiliate) {
            yield new AffiliateBonusCandidate(
                $affiliate->id,
                $affiliate->name,
                $affiliate->currency,
                (int) $affiliate->getAttribute('children_count'),
            );
        }
    }

    /** @return Builder<Affiliate> */
    private function eligible(bool $includeGlobal): Builder
    {
        return Affiliate::query()->forOwner(includeGlobal: $includeGlobal)
            ->where('status', Active::$name)
            ->whereCommercialPurpose();
    }
}
