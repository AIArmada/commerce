<?php

declare(strict_types=1);

namespace AIArmada\Chip\Webhooks\Handlers;

use AIArmada\Chip\Actions\HandleSendInstructionWebhookAction;
use AIArmada\Chip\Data\EnrichedWebhookPayload;
use AIArmada\Chip\Data\WebhookResult;
use AIArmada\Chip\Enums\SendInstructionState;
use AIArmada\Chip\Events\PayoutSuccess;

class SendCompletedHandler implements WebhookHandler
{
    public function __construct(
        private readonly HandleSendInstructionWebhookAction $action,
    ) {}

    public function handle(EnrichedWebhookPayload $payload): WebhookResult
    {
        return $this->action->execute(
            payload: $payload,
            targetState: SendInstructionState::COMPLETED,
            eventClass: PayoutSuccess::class,
        );
    }
}
