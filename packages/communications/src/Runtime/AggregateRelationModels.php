<?php

declare(strict_types=1);

namespace AIArmada\Communications\Runtime;

use AIArmada\Communications\Features\Aggregate\Models\CommunicationAttachment;
use AIArmada\Communications\Features\Aggregate\Models\CommunicationBatch;
use AIArmada\Communications\Features\Aggregate\Models\CommunicationContent;
use AIArmada\Communications\Features\Aggregate\Models\CommunicationRecipient;
use AIArmada\Communications\Features\Aggregate\Models\CommunicationReference;
use AIArmada\Communications\Features\Aggregate\Models\CommunicationThread;

/** Internal Eloquent relation and audit composition. */
interface AggregateRelationModels
{
    /** @return class-string<CommunicationBatch> */
    public function batch(): string;

    /** @return class-string<CommunicationThread> */
    public function thread(): string;

    /** @return class-string<CommunicationRecipient> */
    public function recipient(): string;

    /** @return class-string<CommunicationContent> */
    public function content(): string;

    /** @return class-string<CommunicationReference> */
    public function reference(): string;

    /** @return class-string<CommunicationAttachment> */
    public function attachment(): string;
}
