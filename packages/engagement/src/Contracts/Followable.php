<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Contracts;

interface Followable
{
    public function followableName(): string;

    public function followableUrl(): ?string;

    public function followableImage(): ?string;

    public function defaultFollowNotificationLevel(): ?string;
}
