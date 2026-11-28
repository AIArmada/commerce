<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Tracking\Queries;

use AIArmada\Communications\Features\Tracking\Models\CommunicationTrackingToken;
use AIArmada\Communications\Runtime\TrackingRelationModel;

final readonly class TrackingRelationModelResolver implements TrackingRelationModel
{
    public function token(): string
    {
        return CommunicationTrackingToken::class;
    }
}
