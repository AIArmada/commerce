<?php

declare(strict_types=1);

namespace AIArmada\Docs\Features\Versioning\Actions;

use AIArmada\Docs\Contracts\DocumentVersions;

final readonly class DocumentVersionOperations implements DocumentVersions
{
    public function capture(string $docId, ?string $summary = null): string
    {
        return app(CreateDocVersion::class)->execute($docId, $summary);
    }

    public function restore(string $versionId, ?string $summary = null): void
    {
        app(RestoreDocVersion::class)->execute($versionId, $summary);
    }
}
