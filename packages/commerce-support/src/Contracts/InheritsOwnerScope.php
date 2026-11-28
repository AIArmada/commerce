<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Contracts;

interface InheritsOwnerScope
{
    public function ownerScopeSourceType(): ?string;

    public function ownerScopeSourceId(): string | int | null;
}
