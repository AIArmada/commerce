<?php

declare(strict_types=1);

namespace AIArmada\Links\Actions;

use AIArmada\Links\Models\Link;

final readonly class DeleteLinkForSubject
{
    public function handle(string $linkId, string $subjectType, string $subjectId): bool
    {
        $connection = (new Link)->getConnection();

        return $connection->transaction(static function () use ($linkId, $subjectType, $subjectId): bool {
            $link = Link::query()
                ->withoutOwnerScope()
                ->whereKey($linkId)
                ->where('subject_type', $subjectType)
                ->where('subject_id', $subjectId)
                ->lockForUpdate()
                ->first();

            return $link === null || (bool) $link->delete();
        });
    }
}
