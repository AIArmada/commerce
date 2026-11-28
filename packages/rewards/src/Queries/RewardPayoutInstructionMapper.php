<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Queries;

use AIArmada\Rewards\Data\RewardPayoutInstruction;
use AIArmada\Rewards\Features\Finance\Models\RewardPayoutRequest;

final readonly class RewardPayoutInstructionMapper
{
    public static function fromRequest(RewardPayoutRequest $request): RewardPayoutInstruction
    {
        return new RewardPayoutInstruction(
            id: (string) $request->getKey(),
            awardId: $request->award_id,
            programId: $request->program_id,
            payeeId: $request->payee_id,
            currency: $request->currency,
            amountMinor: $request->amount_minor,
            feeMinor: $request->fee_minor,
            feePayer: $request->fee_payer,
            processor: $request->processor,
            operationKey: $request->operation_key,
            firstReference: $request->first_reference,
            payeeSnapshot: $request->payee_snapshot,
            status: $request->status->value,
            externalPayoutId: $request->external_payout_id,
            externalReferenceId: $request->external_reference_id,
            externalProvider: $request->external_provider,
            providerReference: $request->provider_reference,
            failureCode: $request->failure_code,
            evidence: $request->evidence,
        );
    }
}
