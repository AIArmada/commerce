<?php

declare(strict_types=1);

namespace AIArmada\Events\Listeners;

use AIArmada\Events\Contracts\EventChangeNoticeNotificationDispatcher;
use AIArmada\Events\Events\EventChangeNoticePublished;

final class DispatchEventChangeNoticeNotifications
{
    public function __construct(
        private readonly EventChangeNoticeNotificationDispatcher $dispatcher,
    ) {}

    public function handle(EventChangeNoticePublished $event): void
    {
        $this->dispatcher->dispatch($event->notice, $event->notice->audiences());
    }
}
