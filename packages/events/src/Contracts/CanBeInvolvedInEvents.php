<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

interface CanBeInvolvedInEvents
{
    public function eventDisplayName(): string;

    public function eventDisplaySubtitle(): ?string;

    public function eventDisplayImage(): ?string;

    public function eventProfileUrl(): ?string;
}
