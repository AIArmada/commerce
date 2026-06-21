<?php

declare(strict_types=1);

namespace AIArmada\Communications\Contracts;

use AIArmada\Communications\Data\CommunicationContextData;
use AIArmada\Communications\Models\Communication;
use Illuminate\Notifications\Notification;

interface CommunicationManager
{
    public function notify(
        mixed $notifiable,
        Notification $notification,
        ?CommunicationContextData $context = null,
    ): Communication;

    public function recordNative(
        mixed $notifiable,
        Notification $notification,
        string $channel,
    ): ?Communication;
}
