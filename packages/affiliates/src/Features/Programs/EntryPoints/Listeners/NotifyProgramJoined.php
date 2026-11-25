<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\EntryPoints\Listeners;

use AIArmada\Affiliates\Events\AffiliateProgramJoined;
use AIArmada\Affiliates\Features\Programs\Queries\ProgramJoinQuery;
use AIArmada\Affiliates\Notifications\ProgramJoinedNotification;
use AIArmada\Communications\Actions\DispatchManagedNotificationAction;
use AIArmada\Communications\Data\CommunicationContextData;
use AIArmada\CommerceSupport\Support\OwnerContext;

/**
 * Notifies the affiliate on program join (approved or pending).
 *
 * Managed notifications via the communications package when installed;
 * silent otherwise. On by default — silence must be an explicit
 * config choice, not an accident.
 */
final class NotifyProgramJoined
{
    public function __construct(private readonly ProgramJoinQuery $joins) {}

    public function handle(AffiliateProgramJoined $event): void
    {
        $dispatcher = self::dispatcher();

        if ($dispatcher === null) {
            return;
        }

        $resolved = $this->joins->resolve($event->reference);

        if ($resolved === null) {
            return;
        }

        [$affiliate, $program, $membership] = $resolved;
        $owner = OwnerContext::fromTypeAndIdOrFail($event->reference->affiliate->ownerType, $event->reference->affiliate->ownerId);

        OwnerContext::withOwner($owner, static fn () => $dispatcher->handle(
            $affiliate,
            new ProgramJoinedNotification($program, $membership),
            new CommunicationContextData(
                purpose: 'affiliates.program_joined',
                subjectType: $program->getMorphClass(),
                subjectId: (string) $program->getKey(),
                idempotencyKey: 'program-joined:' . $membership->getKey(),
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
