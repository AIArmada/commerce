<?php

declare(strict_types=1);

namespace AIArmada\Events\Traits;

trait CanOrganizeEvents
{
    public function eventOrganizerName(): string
    {
        return $this->name ?? $this->title ?? '';
    }

    public function eventOrganizerProfileUrl(): ?string
    {
        return $this->profile_url ?? null;
    }

    public function shouldBePublicOrganizerByDefault(): bool
    {
        return (bool) ($this->public_organizer ?? true);
    }
}
