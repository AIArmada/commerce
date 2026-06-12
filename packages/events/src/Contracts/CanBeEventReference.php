<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

interface CanBeEventReference
{
    public function eventReferenceTitle(): string;

    public function eventReferenceCitation(): ?string;

    public function eventReferenceUrl(): ?string;
}
