<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Features\SocialProfiles\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleParser;
use AIArmada\Contacting\Features\SocialProfiles\Models\SocialProfile;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

final readonly class DeleteSocialProfilesForEntityAction
{
    public function execute(Model $entity): void
    {
        SocialProfile::withoutOwnerScope()
            ->where('socialable_type', $entity->getMorphClass())
            ->where('socialable_id', $entity->getKey())
            ->chunkById(100, function (Collection $records): void {
                foreach ($records as $record) {
                    $owner = OwnerTupleParser::fromTypeAndId(
                        $record->owner_type,
                        $record->owner_id,
                    )->toOwnerModelOrFail();

                    OwnerContext::withOwner($owner, function () use ($record): void {
                        $record->delete();
                    });
                }
            });
    }

    /**
     * @param  list<string>  $entityIds
     */
    public function executeForEntities(string $morphClass, array $entityIds): void
    {
        foreach (array_chunk($entityIds, 200) as $chunk) {
            SocialProfile::withoutOwnerScope()
                ->where('socialable_type', $morphClass)
                ->whereIn('socialable_id', $chunk)
                ->delete();
        }
    }
}
