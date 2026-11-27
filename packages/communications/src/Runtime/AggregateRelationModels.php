<?php

declare(strict_types=1);

namespace AIArmada\Communications\Runtime;

/**
 * Internal Eloquent relation and audit composition.
 *
 * @phpstan-type RelatedCommunicationAttachment \AIArmada\Communications\Features\Aggregate\Models\CommunicationAttachment
 * @phpstan-type RelatedCommunicationBatch \AIArmada\Communications\Features\Aggregate\Models\CommunicationBatch
 * @phpstan-type RelatedCommunicationContent \AIArmada\Communications\Features\Aggregate\Models\CommunicationContent
 * @phpstan-type RelatedCommunicationRecipient \AIArmada\Communications\Features\Aggregate\Models\CommunicationRecipient
 * @phpstan-type RelatedCommunicationReference \AIArmada\Communications\Features\Aggregate\Models\CommunicationReference
 * @phpstan-type RelatedCommunicationThread \AIArmada\Communications\Features\Aggregate\Models\CommunicationThread
 */
interface AggregateRelationModels
{
    /** @return class-string<RelatedCommunicationBatch> */
    public function batch(): string;

    /** @return class-string<RelatedCommunicationThread> */
    public function thread(): string;

    /** @return class-string<RelatedCommunicationRecipient> */
    public function recipient(): string;

    /** @return class-string<RelatedCommunicationContent> */
    public function content(): string;

    /** @return class-string<RelatedCommunicationReference> */
    public function reference(): string;

    /** @return class-string<RelatedCommunicationAttachment> */
    public function attachment(): string;
}
