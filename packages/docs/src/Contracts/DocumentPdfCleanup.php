<?php

declare(strict_types=1);

namespace AIArmada\Docs\Contracts;

interface DocumentPdfCleanup
{
    public function execute(string $docId, string $docType, ?string $path): void;
}
