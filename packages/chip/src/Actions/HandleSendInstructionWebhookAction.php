<?php

declare(strict_types=1);

namespace AIArmada\Chip\Actions;

use AIArmada\Chip\Data\EnrichedWebhookPayload;
use AIArmada\Chip\Data\PayoutData;
use AIArmada\Chip\Data\WebhookResult;
use AIArmada\Chip\Enums\SendInstructionState;
use AIArmada\Chip\Models\SendInstruction;

final class HandleSendInstructionWebhookAction
{
    /**
     * @param  array<int, mixed>  $eventArgs
     */
    public function execute(
        EnrichedWebhookPayload $payload,
        SendInstructionState $targetState,
        string $eventClass,
        array $eventArgs = [],
    ): WebhookResult {
        $sendInstructionId = $payload->get('id') ?? $payload->get('data.id');

        if (empty($sendInstructionId)) {
            return WebhookResult::skipped('No send instruction ID in payload');
        }

        $instruction = SendInstruction::query()
            ->withoutOwnerScope()
            ->where('id', $sendInstructionId)
            ->first();

        if ($instruction === null) {
            return WebhookResult::skipped('Send instruction not found locally');
        }

        $instruction->update([
            'state' => $targetState,
        ]);

        $eventArgs = $eventArgs !== [] ? $eventArgs : [
            PayoutData::from($payload->rawPayload),
            $payload->rawPayload,
        ];

        $eventClass::dispatch(...$eventArgs);

        return WebhookResult::handled("Send instruction {$instruction->id} marked as {$targetState->value}");
    }
}
