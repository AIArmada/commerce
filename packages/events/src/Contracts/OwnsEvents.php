<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;

interface OwnsEvents
{
    /** @return MorphMany */
    public function ownedEvents();

    public function defaultEventVisibility(): string;

    public function defaultEventApprovalRequired(): bool;
}
