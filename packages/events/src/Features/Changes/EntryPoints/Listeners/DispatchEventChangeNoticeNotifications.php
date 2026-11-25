<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Changes\EntryPoints\Listeners;

use AIArmada\Events\Events\EventChangeNoticePublished;
use AIArmada\Events\Features\Changes\Actions\DispatchEventChangeNoticeNotificationsAction;

final class DispatchEventChangeNoticeNotifications
{
    public function __construct(
        private readonly DispatchEventChangeNoticeNotificationsAction $action,
    ) {}

    public function handle(EventChangeNoticePublished $event): void
    {
        $this->action->handle($event->changeLogId);
    }
}
