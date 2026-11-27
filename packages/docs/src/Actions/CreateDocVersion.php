<?php

declare(strict_types=1);

namespace AIArmada\Docs\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocVersion;
use Carbon\CarbonImmutable;

final readonly class CreateDocVersion
{
    public function execute(Doc $doc, ?string $summary = null): DocVersion
    {
        $owner = OwnerContext::resolve();
        $includeGlobal = (bool) config('docs.owner.include_global', false);

        if (config('docs.owner.enabled', false)) {
            OwnerWriteGuard::findOrFailForOwner(
                Doc::class,
                (string) $doc->getKey(),
                owner: $owner,
                includeGlobal: $includeGlobal,
                message: 'Document is not available in the current owner scope.',
            );
        }

        return $doc->getConnection()->transaction(function () use ($doc, $summary, $owner, $includeGlobal): DocVersion {
            $lockedDoc = $doc->newQuery()
                ->forOwner($owner, $includeGlobal)
                ->lockForUpdate()
                ->findOrFail($doc->getKey());

            return OwnerContext::withOwner($lockedDoc->owner, function () use ($lockedDoc, $summary): DocVersion {
                $version = $lockedDoc->versions()->make([
                    'version_number' => (int) $lockedDoc->versions()->max('version_number') + 1,
                    'snapshot' => $lockedDoc->toArray(),
                    'change_summary' => $summary,
                    'changed_by' => auth()->id(),
                    'created_at' => CarbonImmutable::now(),
                ]);

                $version->save();

                return $version;
            });
        });
    }
}
