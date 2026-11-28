<?php

declare(strict_types=1);

namespace AIArmada\Docs\Contracts;

interface DocumentVersions
{
    public function capture(string $docId, ?string $summary = null): string;

    public function restore(string $versionId, ?string $summary = null): void;
}
