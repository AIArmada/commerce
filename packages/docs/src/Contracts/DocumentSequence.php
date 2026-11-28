<?php

declare(strict_types=1);

namespace AIArmada\Docs\Contracts;

interface DocumentSequence
{
    public function nextNumber(string $docType): string;
}
