<?php

declare(strict_types=1);

namespace AIArmada\Communications\Services;

use AIArmada\Communications\Contracts\RecipientSnapshotResolver;
use AIArmada\Communications\Data\RecipientSnapshotData;

class NullRecipientSnapshotResolver implements RecipientSnapshotResolver
{
    public function resolve(mixed $notifiable): RecipientSnapshotData
    {
        $id = method_exists($notifiable, 'getKey') ? (string) $notifiable->getKey() : spl_object_id($notifiable);

        return RecipientSnapshotData::from(['identifier' => $id]);
    }
}
