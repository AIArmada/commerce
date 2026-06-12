<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

interface CanBeEventMaterial
{
    public function eventMaterialTitle(): string;

    public function eventMaterialType(): string;

    public function eventMaterialUrl(): ?string;
}
