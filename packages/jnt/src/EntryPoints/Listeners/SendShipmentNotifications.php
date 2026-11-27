<?php

declare(strict_types=1);

namespace AIArmada\Jnt\EntryPoints\Listeners;

use AIArmada\Jnt\Events\JntOrderStatusChanged;
use AIArmada\Jnt\Features\Notifications\Actions\NotifyShipmentStatusChanged;
use Illuminate\Contracts\Queue\ShouldQueue;

final class SendShipmentNotifications implements ShouldQueue
{
    public function __construct(private readonly NotifyShipmentStatusChanged $notify) {}

    public function handle(JntOrderStatusChanged $event): void
    {
        $this->notify->execute($event);
    }

    public function shouldQueue(JntOrderStatusChanged $event): bool
    {
        return (bool) config('jnt.notifications.queue', true);
    }
}
