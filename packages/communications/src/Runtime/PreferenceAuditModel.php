<?php

declare(strict_types=1);

namespace AIArmada\Communications\Runtime;

/**
 * Internal Eloquent relation and audit composition.
 *
 * @phpstan-type RelatedCommunicationPreference \AIArmada\Communications\Features\RecipientRouting\Models\CommunicationPreference
 */
interface PreferenceAuditModel
{
    /** @return class-string<RelatedCommunicationPreference> */
    public function preference(): string;
}
