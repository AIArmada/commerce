<?php

declare(strict_types=1);

namespace AIArmada\Links\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScopeOverride;
use AIArmada\Links\Models\Link;
use AIArmada\Links\Persistence\DeleteLockedLink;

final readonly class DeleteLinkForSubject
{
    public function handle(string $linkId, string $subjectType, string $subjectId): bool
    {
        $connection = (new Link)->getConnection();

        return OwnerScopeOverride::withoutIncludeGlobal(fn (): bool => $connection->transaction(function () use ($linkId, $subjectType, $subjectId): bool {
            $config = Link::ownerScopeConfig();

            if ($config->enabled) {
                $owner = OwnerContext::resolveConfigured($config);
                OwnerContext::assertResolvedOrExplicitGlobal(
                    $owner,
                    'Deleting a tracked link requires an owner context or explicit global context.',
                );
            }

            $link = Link::query()
                ->whereKey($linkId)
                ->lockForUpdate()
                ->first();

            if ($link === null) {
                return true;
            }

            if ($link->subject_type !== $subjectType || $link->subject_id !== $subjectId) {
                return false;
            }

            return app(DeleteLockedLink::class)->handle($link);
        }));
    }
}
