<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

interface CanBeEventParticipant
{
    public function eventParticipantName(): string;

    public function eventParticipantEmail(): ?string;

    public function eventParticipantPhone(): ?string;
}
