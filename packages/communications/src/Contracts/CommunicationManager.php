<?php

declare(strict_types=1);

namespace AIArmada\Communications\Contracts;

use AIArmada\Communications\Data\CommunicationContextData;
use Illuminate\Notifications\Notification;

interface CommunicationManager
{
    public function notify(
        mixed $notifiable,
        Notification $notification,
        ?CommunicationContextData $context = null,
    ): string;
}
