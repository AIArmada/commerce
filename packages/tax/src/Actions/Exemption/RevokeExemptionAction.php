<?php

declare(strict_types=1);

namespace AIArmada\Tax\Actions\Exemption;

use AIArmada\Tax\Models\TaxExemption;
use AIArmada\Tax\States\TaxExemptionState\RevokedState;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class RevokeExemptionAction
{
    public function execute(TaxExemption $exemption): TaxExemption
    {
        return DB::transaction(function () use ($exemption): TaxExemption {
            $locked = $exemption->newQuery()->whereKey($exemption->getKey())->lockForUpdate()->firstOrFail();
            $exemption->setRawAttributes($locked->getAttributes(), true)->unsetRelations();
            $exemption->revoked_at ??= CarbonImmutable::now();
            $exemption->status->transitionTo(RevokedState::class);

            return $exemption;
        });
    }
}
