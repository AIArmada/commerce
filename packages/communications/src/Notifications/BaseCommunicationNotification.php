<?php

declare(strict_types=1);

namespace AIArmada\Communications\Notifications;

use AIArmada\Communications\Traits\HasCommunicationContext;
use Illuminate\Notifications\Notification;
use Throwable;

abstract class BaseCommunicationNotification extends Notification
{
    use HasCommunicationContext;

    public function failed(Throwable $exception): void
    {
        $this->recordCommunicationFailure($exception);
    }
}
