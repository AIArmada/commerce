<?php

declare(strict_types=1);

namespace AIArmada\Communications\Contracts;

use AIArmada\Communications\Data\CapturedRecipientContentData;

interface NotificationContentCapture
{
    public function capture(string $communicationId, mixed $notifiable, object $notification, string $channel): CapturedRecipientContentData;
}
