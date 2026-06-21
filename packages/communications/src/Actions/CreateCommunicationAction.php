<?php

declare(strict_types=1);

namespace AIArmada\Communications\Actions;

use AIArmada\Communications\Contracts\CommunicationRecorder;
use AIArmada\Communications\Data\CommunicationContextData;
use AIArmada\Communications\Events\CommunicationCreated;
use AIArmada\Communications\Events\CommunicationScheduled;
use AIArmada\Communications\Models\Communication;
use Illuminate\Support\Facades\Event;

final class CreateCommunicationAction
{
    public function __construct(
        private readonly CommunicationRecorder $recorder,
    ) {}

    public function handle(CommunicationContextData $context): Communication
    {
        $communication = $this->recorder->createCommunication($context);

        Event::dispatch(new CommunicationCreated(
            communicationId: $communication->id,
            category: $communication->category->value,
            direction: $communication->direction->value,
            purpose: $communication->purpose,
        ));

        if ($communication->scheduled_at !== null) {
            Event::dispatch(new CommunicationScheduled(
                communicationId: $communication->id,
                scheduledAt: $communication->scheduled_at->toIso8601String(),
            ));
        }

        return $communication;
    }
}
