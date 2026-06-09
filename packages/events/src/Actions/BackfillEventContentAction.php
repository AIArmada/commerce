<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Models\Event;

final class BackfillEventContentAction
{
    public function __construct(
        private readonly SynchronizeEventContent $synchronizeEventContent,
    ) {}

    public function handle(): int
    {
        $synced = 0;

        Event::query()
            ->withoutOwnerScope()
            ->orderBy('id')
            ->get()
            ->each(function (Event $event) use (&$synced): void {
                $owner = OwnerContext::fromTypeAndId(
                    is_string($event->getAttribute('owner_type')) ? $event->getAttribute('owner_type') : null,
                    is_scalar($event->getAttribute('owner_id')) ? (string) $event->getAttribute('owner_id') : null,
                );

                OwnerContext::withOwner($owner, function () use ($event): void {
                    $this->synchronizeEventContent->handle($event, 'backfill');
                });

                $synced++;
            });

        return $synced;
    }
}
