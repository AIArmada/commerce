<?php

declare(strict_types=1);

namespace AIArmada\Communications\Runtime;

use AIArmada\Communications\Features\Tracking\Models\CommunicationTrackingToken;

/** Internal Eloquent relation and audit composition. */
interface TrackingRelationModel
{
    /** @return class-string<CommunicationTrackingToken> */
    public function token(): string;
}
