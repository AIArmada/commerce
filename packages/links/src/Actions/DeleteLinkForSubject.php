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
                ->lockForUpdate()
                ->first();

            if ($link === null) {
                return true;
            }

            if ($link->subject_type !== $subjectType || $link->subject_id !== $subjectId) {
                return false;
            }

            return (bool) $link->delete();
        });
    }
}
