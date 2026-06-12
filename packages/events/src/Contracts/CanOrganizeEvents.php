<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

interface CanOrganizeEvents
{
    public function eventOrganizerName(): string;

    public function eventOrganizerProfileUrl(): ?string;

    public function shouldBePublicOrganizerByDefault(): bool;
}
