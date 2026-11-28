<?php

declare(strict_types=1);

namespace AIArmada\Communications\Drivers\Notifications;

use AIArmada\Communications\Contracts\RecipientSnapshotResolver;
use AIArmada\Communications\Data\RecipientSnapshotData;

class NullRecipientSnapshotResolver implements RecipientSnapshotResolver
{
    public function resolve(mixed $notifiable): RecipientSnapshotData
    {
        $id = method_exists($notifiable, 'getKey') ? (string) $notifiable->getKey() : (string) spl_object_id($notifiable);

        return new RecipientSnapshotData(identifier: $id);
    }
}
