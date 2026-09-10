<?php

declare(strict_types=1);

namespace AIArmada\Communications\Actions;

use AIArmada\Communications\Contracts\PayloadRedactor;
use AIArmada\Communications\Enums\RecipientRole;
use AIArmada\Communications\Models\Communication;
use AIArmada\Communications\Models\CommunicationRecipient;

final class AddCommunicationRecipientAction
{
    public function __construct(
        private readonly PayloadRedactor $redactor,
    ) {}

    public function handle(
        string $communicationId,
        string $recipientType,
        string $recipientId,
        RecipientRole $role,
        ?array $snapshot = null,
    ): CommunicationRecipient {
        $communication = Communication::query()->findOrFail($communicationId);

        $recipient = new CommunicationRecipient;
        $recipient->communication_id = $communication->id;
        $recipient->recipient_type = $recipientType;
        $recipient->recipient_id = $recipientId;
        $recipient->role = $role;
        $recipient->snapshot = $snapshot !== null ? $this->redactor->redact($snapshot) : null;
        $recipient->save();

        return $recipient;
    }
}
