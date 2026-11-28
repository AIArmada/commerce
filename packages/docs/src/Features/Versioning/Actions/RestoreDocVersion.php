<?php

declare(strict_types=1);

namespace AIArmada\Docs\Features\Versioning\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Docs\Contracts\DocServiceInterface;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Features\Versioning\Models\DocVersion;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final readonly class RestoreDocVersion
{
    public function __construct(private DocServiceInterface $documents) {}

    public function execute(string $versionId, ?string $summary = null): void
    {
        $candidate = DocVersion::query()->forOwner()->findOrFail($versionId);
        $docId = (string) $candidate->doc_id;

        if (config('docs.owner.enabled', false)) {
            OwnerWriteGuard::findOrFailForOwner(Doc::class, $docId);
        }

        (new Doc)->getConnection()->transaction(function () use ($versionId, $docId, $summary): void {
            $doc = Doc::query()->forOwner()->lockForUpdate()->findOrFail($docId);
            $version = DocVersion::query()->forOwner()->where('doc_id', $docId)->lockForUpdate()->findOrFail($versionId);

            if ($version->owner_type !== $doc->owner_type || (string) $version->owner_id !== (string) $doc->owner_id) {
                throw (new ModelNotFoundException)->setModel(DocVersion::class, [$versionId]);
            }

            OwnerContext::withOwner(
                OwnerContext::fromTypeAndIdOrFail($doc->owner_type, $doc->owner_id),
                fn () => $this->documents->update($docId, $version->snapshot, $summary ?? "Restored version {$version->version_number}"),
            );
        });
    }
}
