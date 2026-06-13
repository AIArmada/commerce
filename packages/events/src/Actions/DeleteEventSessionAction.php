<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Events\Events\EventSessionDeleted;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventSession;
use Carbon\CarbonImmutable;

final class DeleteEventSessionAction
{
    public function handle(EventSession $session): void
    {
        OwnerWriteGuard::findOrFailForOwner(Event::class, $session->event_id);

        $session->update([
            'status' => 'archived',
            'archived_at' => CarbonImmutable::now(),
        ]);

        event(new EventSessionDeleted($session));
    }
}
