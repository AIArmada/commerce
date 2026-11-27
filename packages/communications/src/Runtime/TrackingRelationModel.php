<?php

declare(strict_types=1);

namespace AIArmada\Communications\Runtime;

/** Internal Eloquent relation and audit composition. */
interface TrackingRelationModel
{
    /** @return class-string<\AIArmada\Communications\Features\Tracking\Models\CommunicationTrackingToken> */
    public function token(): string;
}
