<?php

declare(strict_types=1);

namespace AIArmada\Communications\Runtime;

/**
 * Internal Eloquent relation and audit composition.
 *
 * @phpstan-type RelatedCommunicationTrackingToken \AIArmada\Communications\Features\Tracking\Models\CommunicationTrackingToken
 */
interface TrackingRelationModel
{
    /** @return class-string<RelatedCommunicationTrackingToken> */
    public function token(): string;
}
