<?php

declare(strict_types=1);

namespace AIArmada\Docs\Numbering\Contracts;

interface DocumentNumberStrategy
{
    /**
     * Generate a unique document number for the given document type.
     */
    public function generate(string $docType): string;
}
