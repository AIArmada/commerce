<?php

declare(strict_types=1);

namespace AIArmada\Chip\Webhooks\Handlers;

use AIArmada\Chip\Data\EnrichedWebhookPayload;
use AIArmada\Chip\Data\PayoutData;
use AIArmada\Chip\Data\WebhookResult;
use AIArmada\Chip\Enums\SendInstructionState;
use AIArmada\Chip\Events\PayoutFailed;
use AIArmada\Chip\Models\SendInstruction;

/**
 * Handles send_instruction.rejected and payout.failed webhook events.
 *
 * Note: Webhooks arrive without tenant context, so we use withoutOwnerScope()
 * to find the instruction by its unique ID. This is safe because webhook
 * payloads are cryptographically signed and the ID is globally unique.
 */
class SendRejectedHandler implements WebhookHandler
{
    public function handle(EnrichedWebhookPayload $payload): WebhookResult
    {
        $sendInstructionId = $payload->get('id') ?? $payload->get('data.id');

        if (empty($sendInstructionId)) {
            return WebhookResult::skipped('No send instruction ID in payload');
        }

        // Webhooks have no tenant context - lookup by unique ID is safe
        // because the payload signature verifies authenticity
        $instruction = SendInstruction::query()
            ->withoutOwnerScope()
            ->where('id', $sendInstructionId)
            ->first();

        if ($instruction === null) {
            return WebhookResult::skipped('Send instruction not found locally');
        }

        // Get failure reason
        $failureReason = $payload->get('error.message')
            ?? $payload->get('rejection_reason')
            ?? $payload->get('failure_reason')
            ?? 'Unknown rejection';

        // Update local status
        $instruction->update([
            'state' => SendInstructionState::REJECTED,
        ]);

        // Dispatch Laravel event using Dispatchable trait
        PayoutFailed::dispatch(
            PayoutData::from($payload->rawPayload),
            $payload->rawPayload,
        );

        return WebhookResult::handled("Send instruction {$instruction->id} marked as rejected");
    }
}
