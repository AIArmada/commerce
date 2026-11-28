<?php

declare(strict_types=1);

namespace AIArmada\Tax\Actions\Exemption;

use AIArmada\Tax\Models\TaxExemption;
use AIArmada\Tax\Queries\TaxOwnerScope;
use AIArmada\Tax\States\TaxExemptionState\ApprovedState;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class ApproveExemptionAction
{
    public function execute(TaxExemption $exemption): TaxExemption
    {
        return DB::transaction(function () use ($exemption): TaxExemption {
            $locked = TaxOwnerScope::lockWritable(TaxExemption::query(), [$exemption->getKey()])->firstOrFail();
            $exemption->setRawAttributes($locked->getAttributes(), true)->unsetRelations();
            $exemption->verified_at ??= CarbonImmutable::now();
            $exemption->status->transitionTo(ApprovedState::class);

            return $exemption;
        });
    }
}
