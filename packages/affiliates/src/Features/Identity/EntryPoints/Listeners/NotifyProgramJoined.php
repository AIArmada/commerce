<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\EntryPoints\Listeners;

use AIArmada\Affiliates\Events\AffiliateProgramJoined;
use AIArmada\Affiliates\Features\Identity\Notifications\ProgramJoinedNotification;
use AIArmada\Affiliates\Features\Programs\Api\ProgramJoinReader;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Communications\Actions\DispatchManagedNotificationAction;
use AIArmada\Communications\Data\CommunicationContextData;
use InvalidArgumentException;

/**
 * Notifies the affiliate on program approval.
 *
 * Managed notifications via the communications package when installed;
 * silent otherwise. On by default — silence must be an explicit
 * config choice, not an accident.
 */
final class NotifyProgramJoined
{
    public function __construct(private readonly ProgramJoinReader $joins) {}

    public function handle(AffiliateProgramJoined $event): void
    {
        $dispatcher = self::dispatcher();

        if ($dispatcher === null) {
            return;
        }

        $record = $this->joins->find($event->reference);

        if ($record === null) {
            return;
        }

        $reference = $record->reference->affiliate;
        $affiliate = Affiliate::query()->withoutOwnerScope()->find($reference->id);

        if ($affiliate === null) {
            return;
        }

        if (! $reference->matches($affiliate->lifecycleReference())) {
            throw new InvalidArgumentException('Trusted affiliate owner or identity does not match the canonical source.');
        }

        $owner = OwnerContext::fromTypeAndIdOrFail($reference->ownerType, $reference->ownerId);

        OwnerContext::withOwner($owner, static fn () => $dispatcher->handle(
            $affiliate,
            new ProgramJoinedNotification($record->programName, $record->approved),
            new CommunicationContextData(
                purpose: 'affiliates.program_joined',
                subjectType: $record->programMorphType,
                subjectId: $record->reference->programId,
                idempotencyKey: 'program-joined:' . $record->reference->membershipId,
            ),
        ));
    }

    private static function dispatcher(): ?DispatchManagedNotificationAction
    {
        if (! config('affiliates.notifications.enabled', true)) {
            return null;
        }

        if (! class_exists(DispatchManagedNotificationAction::class)) {
            return null;
        }

        $bound = app()->bound(DispatchManagedNotificationAction::class);
        $loaded = isset(app()->getLoadedProviders()['AIArmada\Communications\CommunicationsServiceProvider']);

        if (! $bound && ! $loaded) {
            return null;
        }

        return app(DispatchManagedNotificationAction::class);
    }
}
