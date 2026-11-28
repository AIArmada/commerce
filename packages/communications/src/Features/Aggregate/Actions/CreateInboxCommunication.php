<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Aggregate\Actions;

use AIArmada\Communications\Contracts\InboxCommunicationCreator;
use AIArmada\Communications\Enums\CommunicationCategory;
use AIArmada\Communications\Enums\CommunicationDirection;
use AIArmada\Communications\Enums\CommunicationPriority;
use AIArmada\Communications\Enums\CommunicationStatus;
use AIArmada\Communications\Models\Communication;
use Carbon\CarbonImmutable;

final readonly class CreateInboxCommunication implements InboxCommunicationCreator
{
    public function create(): string
    {
        $communication = new Communication;
        $communication->direction = CommunicationDirection::Internal;
        $communication->category = CommunicationCategory::Internal;
        $communication->priority = CommunicationPriority::Normal;
        $communication->purpose = 'inbox_notification';
        $communication->status = CommunicationStatus::Completed;
        $communication->completed_at = CarbonImmutable::now();
        $communication->save();

        return $communication->id;
    }
}
