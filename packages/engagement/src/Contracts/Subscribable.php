<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Contracts;

interface Subscribable
{
    public function subscribableName(): string;

    /** @return array<string> */
    public function availableSubscriptionTypes(): array;

    public function defaultSubscriptionNotificationLevel(): ?string;
}
