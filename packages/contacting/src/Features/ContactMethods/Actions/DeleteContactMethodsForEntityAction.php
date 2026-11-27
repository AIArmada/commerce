<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Features\ContactMethods\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleParser;
use AIArmada\Contacting\Features\ContactMethods\Models\ContactMethod;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

final readonly class DeleteContactMethodsForEntityAction
{
    public function execute(Model $entity): void
    {
        ContactMethod::withoutOwnerScope()
            ->where('contactable_type', $entity->getMorphClass())
            ->where('contactable_id', $entity->getKey())
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
}
