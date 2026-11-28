<?php

declare(strict_types=1);

namespace AIArmada\Docs\Features\Versioning\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Docs\Models\Doc;
use Carbon\CarbonImmutable;
use RuntimeException;

final readonly class CreateDocVersion
{
    public function execute(string $docId, ?string $summary = null): string
    {
        $owner = OwnerContext::resolve();
        $includeGlobal = (bool) config('docs.owner.include_global', false);

        if (config('docs.owner.enabled', false)) {
            OwnerWriteGuard::findOrFailForOwner(
                Doc::class,
                $docId,
                owner: $owner,
                includeGlobal: $includeGlobal,
                message: 'Document is not available in the current owner scope.',
            );
        }

        return (new Doc)->getConnection()->transaction(function () use ($docId, $summary, $owner, $includeGlobal): string {
            $lockedDoc = Doc::query()
                ->forOwner($owner, $includeGlobal)
                ->lockForUpdate()
                ->findOrFail($docId);

            return OwnerContext::withOwner($lockedDoc->owner, function () use ($lockedDoc, $summary): string {
                $latest = (int) $lockedDoc->versions()->max('version_number');

                if ($latest >= 2147483647) {
                    throw new RuntimeException('Document version number exhausted.');
                }

                $version = $lockedDoc->versions()->make([
                    'version_number' => $latest + 1,
                    'snapshot' => $lockedDoc->attributesToArray(),
                    'change_summary' => $summary,
                    'changed_by' => auth()->id(),
                    'created_at' => CarbonImmutable::now(),
                ]);

                $version->save();

                return (string) $version->getKey();
            });
        });
    }
}
