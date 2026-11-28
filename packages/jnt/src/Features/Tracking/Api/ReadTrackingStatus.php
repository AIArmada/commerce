<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Features\Tracking\Api;

use AIArmada\Jnt\Data\TrackingData;
use AIArmada\Jnt\Data\TrackingDetailData;
use AIArmada\Jnt\Data\TrackingStatus;
use Carbon\CarbonImmutable;

interface ReadTrackingStatus
{
    public function getCurrentStatus(TrackingData $trackingData): TrackingStatus;

    public function parseDetailScanTime(TrackingDetailData $detail): ?CarbonImmutable;
}
