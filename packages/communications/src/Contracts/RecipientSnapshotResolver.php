<?php

declare(strict_types=1);

namespace AIArmada\Communications\Contracts;

use AIArmada\Communications\Data\RecipientSnapshotData;

interface RecipientSnapshotResolver
{
    public function resolve(mixed $notifiable): RecipientSnapshotData;
}
