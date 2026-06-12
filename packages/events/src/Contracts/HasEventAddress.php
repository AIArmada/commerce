<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

interface HasEventAddress
{
    public function eventLocationName(): string;

    public function eventAddress(): ?array;

    public function eventCoordinates(): ?array;

    public function eventMapLinks(): ?array;

    public function eventDirections(): ?string;

    public function toEventLocationSnapshot(): array;
}
