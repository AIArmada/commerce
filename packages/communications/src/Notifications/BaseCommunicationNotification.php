<?php

declare(strict_types=1);

namespace AIArmada\Communications\Notifications;

use AIArmada\Communications\Contracts\CommunicationContextAware;
use AIArmada\Communications\Enums\NotificationFamily;
use AIArmada\Communications\Enums\NotificationTrigger;
use AIArmada\Communications\Traits\HasCommunicationContext;
use Illuminate\Notifications\Notification;
use Throwable;

abstract class BaseCommunicationNotification extends Notification implements CommunicationContextAware
{
    use HasCommunicationContext;

    public function notificationFamily(): ?NotificationFamily
    {
        return null;
    }

    public function notificationTrigger(): ?NotificationTrigger
    {
        return null;
    }

    public function failed(Throwable $exception): void
    {
        $this->recordCommunicationFailure($exception);
    }
}
