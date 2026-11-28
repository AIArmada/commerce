<?php

declare(strict_types=1);

namespace AIArmada\Communications\Runtime;

/**
 * Internal Eloquent relation and audit composition.
 *
 * @phpstan-type RelatedCommunicationSuppression \AIArmada\Communications\Features\Suppressions\Models\CommunicationSuppression
 */
interface SuppressionAuditModel
{
    /** @return class-string<RelatedCommunicationSuppression> */
    public function suppression(): string;
}
