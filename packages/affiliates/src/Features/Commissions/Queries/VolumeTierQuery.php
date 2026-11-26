<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Commissions\Queries;

use AIArmada\Affiliates\Features\Commissions\Api\VolumeTierReader;
use AIArmada\Affiliates\Features\Commissions\Api\VolumeTierSnapshot;
use AIArmada\Affiliates\Features\Commissions\Models\AffiliateVolumeTier;
use Illuminate\Database\Eloquent\Builder;

final class VolumeTierQuery implements VolumeTierReader
{
    public function forProgram(string $programId): array
    {
        return AffiliateVolumeTier::query()
            ->where(static fn (Builder $query) => $query->whereNull('program_id')->orWhere('program_id', $programId))
            ->orderBy('min_volume_minor')
            ->orderBy('id')
            ->get()
            ->map(static fn (AffiliateVolumeTier $tier): VolumeTierSnapshot => new VolumeTierSnapshot(
                $tier->min_volume_minor,
                $tier->currencyCode(),
                $tier->commission_rate_basis_points,
            ))
            ->values()
            ->all();
    }
}
