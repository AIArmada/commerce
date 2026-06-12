<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

interface OwnsEvents
{
    /** @return \Illuminate\Database\Eloquent\Relations\MorphMany */
    public function ownedEvents();

    public function defaultEventVisibility(): string;

    public function defaultEventApprovalRequired(): bool;
}
