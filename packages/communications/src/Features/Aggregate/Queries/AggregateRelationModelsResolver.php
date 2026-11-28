<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Aggregate\Queries;

use AIArmada\Communications\Features\Aggregate\Models\CommunicationAttachment;
use AIArmada\Communications\Features\Aggregate\Models\CommunicationBatch;
use AIArmada\Communications\Features\Aggregate\Models\CommunicationContent;
use AIArmada\Communications\Features\Aggregate\Models\CommunicationRecipient;
use AIArmada\Communications\Features\Aggregate\Models\CommunicationReference;
use AIArmada\Communications\Features\Aggregate\Models\CommunicationThread;
use AIArmada\Communications\Runtime\AggregateRelationModels;

final readonly class AggregateRelationModelsResolver implements AggregateRelationModels
{
    public function batch(): string
    {
        return CommunicationBatch::class;
    }

    public function thread(): string
    {
        return CommunicationThread::class;
    }

    public function recipient(): string
    {
        return CommunicationRecipient::class;
    }

    public function content(): string
    {
        return CommunicationContent::class;
    }

    public function reference(): string
    {
        return CommunicationReference::class;
    }

    public function attachment(): string
    {
        return CommunicationAttachment::class;
    }
}
