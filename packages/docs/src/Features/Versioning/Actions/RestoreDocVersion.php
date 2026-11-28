<?php

declare(strict_types=1);

namespace AIArmada\Docs\Features\Versioning\Actions;

use AIArmada\Docs\Contracts\DocServiceInterface;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocVersion;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final readonly class RestoreDocVersion
{
    public function __construct(private DocServiceInterface $documents) {}

    public function execute(string $versionId, ?string $summary = null): void
    {
        $version = DocVersion::query()->forOwner()->findOrFail($versionId);
        $doc = Doc::query()->forOwner()->findOrFail($version->doc_id);

        if ($version->owner_type !== $doc->owner_type || (string) $version->owner_id !== (string) $doc->owner_id) {
            throw (new ModelNotFoundException)->setModel(DocVersion::class, [$versionId]);
        }

        $this->documents->update((string) $doc->getKey(), $version->snapshot, $summary ?? "Restored version {$version->version_number}");
    }
}
