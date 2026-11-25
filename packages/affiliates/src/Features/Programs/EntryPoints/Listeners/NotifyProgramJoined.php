<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\EntryPoints\Listeners;

use AIArmada\Affiliates\Enums\MembershipStatus;
use AIArmada\Affiliates\Events\AffiliateProgramJoined;
use AIArmada\Affiliates\Features\Programs\Notifications\ProgramJoinedNotification;
use AIArmada\Affiliates\Features\Programs\Queries\ProgramJoinQuery;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Communications\Actions\DispatchManagedNotificationAction;
use AIArmada\Communications\Data\CommunicationContextData;

/**
 * Notifies the affiliate on program approval.
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
            new ProgramJoinedNotification($program->name, $membership->status === MembershipStatus::Approved),
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
