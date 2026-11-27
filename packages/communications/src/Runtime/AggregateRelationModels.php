<?php

declare(strict_types=1);

namespace AIArmada\Communications\Runtime;

/** Internal Eloquent relation and audit composition. */
interface AggregateRelationModels
{
    /** @return class-string<\AIArmada\Communications\Features\Aggregate\Models\CommunicationBatch> */
    public function batch(): string;

    /** @return class-string<\AIArmada\Communications\Features\Aggregate\Models\CommunicationThread> */
    public function thread(): string;

    /** @return class-string<\AIArmada\Communications\Features\Aggregate\Models\CommunicationRecipient> */
    public function recipient(): string;

    /** @return class-string<\AIArmada\Communications\Features\Aggregate\Models\CommunicationContent> */
    public function content(): string;

    /** @return class-string<\AIArmada\Communications\Features\Aggregate\Models\CommunicationReference> */
    public function reference(): string;

    /** @return class-string<\AIArmada\Communications\Features\Aggregate\Models\CommunicationAttachment> */
    public function attachment(): string;
}
