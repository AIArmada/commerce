<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Contracts\EventRelationalContentSubject;
use AIArmada\Events\Services\EventContentSynchronizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

final class SynchronizeEventContent
{
    public function __construct(
        private readonly EventContentSynchronizer $synchronizer,
    ) {}

    public function handle(EventRelationalContentSubject & Model $subject, ?string $source = null): void
    {
        $owner = $subject->getAttribute('owner_type') !== null && $subject->getAttribute('owner_id') !== null
            ? OwnerContext::fromTypeAndId(
                (string) $subject->getAttribute('owner_type'),
                (string) $subject->getAttribute('owner_id'),
            )
            : null;

        OwnerContext::withOwner($owner, function () use ($subject): void {
            $this->synchronizer->sync($subject);
        });

        Log::info('Event content synchronized', [
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'source' => $source ?? 'action',
        ]);
    }
}
