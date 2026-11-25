<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

interface OwnsEvents
{
    public function ownedEvents(): mixed;

    public function defaultEventVisibility(): string;

    public function defaultEventApprovalRequired(): bool;
}
