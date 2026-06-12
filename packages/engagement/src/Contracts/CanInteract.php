<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Contracts;

interface CanInteract
{
    public function interactionDisplayName(): string;

    public function interactionNotificationRoute(?string $channel = null): mixed;
}
