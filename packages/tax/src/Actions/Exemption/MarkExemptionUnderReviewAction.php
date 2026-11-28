<?php

declare(strict_types=1);

namespace AIArmada\Tax\Actions\Exemption;

use AIArmada\Tax\Models\TaxExemption;
use AIArmada\Tax\States\TaxExemptionState\UnderReviewState;
use Illuminate\Support\Facades\DB;

final readonly class MarkExemptionUnderReviewAction
{
    public function execute(TaxExemption $exemption): TaxExemption
    {
        return DB::transaction(function () use ($exemption): TaxExemption {
            $locked = $exemption->newQuery()->whereKey($exemption->getKey())->lockForUpdate()->firstOrFail();
            $exemption->setRawAttributes($locked->getAttributes(), true)->unsetRelations();
            $exemption->status->transitionTo(UnderReviewState::class);

            return $exemption;
        });
    }
}
