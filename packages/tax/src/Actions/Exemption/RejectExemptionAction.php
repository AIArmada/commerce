<?php

declare(strict_types=1);

namespace AIArmada\Tax\Actions\Exemption;

use AIArmada\Tax\Models\TaxExemption;
use AIArmada\Tax\Queries\TaxOwnerScope;
use AIArmada\Tax\States\TaxExemptionState\RejectedState;
use Illuminate\Support\Facades\DB;

final readonly class RejectExemptionAction
{
    public function execute(TaxExemption $exemption, string $reason): TaxExemption
    {
        return DB::transaction(function () use ($exemption, $reason): TaxExemption {
            $locked = TaxOwnerScope::lockWritable(TaxExemption::query(), [$exemption->getKey()])->firstOrFail();
            $exemption->setRawAttributes($locked->getAttributes(), true)->unsetRelations();
            $exemption->rejection_reason = $reason;
            $exemption->status->transitionTo(RejectedState::class);

            return $exemption;
        });
    }
}
