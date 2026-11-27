<?php

declare(strict_types=1);

namespace AIArmada\Communications\Contracts;

use AIArmada\Communications\Data\CapturedNotificationData;

interface NativeNotificationCapture
{
    public function capture(
        mixed $notifiable,
        object $notification,
        string $channel,
        ?string $communicationId = null,
    ): CapturedNotificationData;
}
