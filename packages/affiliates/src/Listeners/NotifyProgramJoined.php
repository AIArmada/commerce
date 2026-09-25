<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Listeners;

use AIArmada\Affiliates\Events\AffiliateProgramJoined;
use AIArmada\Affiliates\Notifications\ProgramJoinedNotification;
use AIArmada\Communications\Actions\DispatchManagedNotificationAction;
use AIArmada\Communications\Data\CommunicationContextData;

/**
 * Notifies the affiliate on program join (approved or pending).
 *
 * Managed notifications via the communications package when installed;
 * silent otherwise. On by default — silence must be an explicit
 * config choice, not an accident.
 */
final class NotifyProgramJoined
{
    public function handle(AffiliateProgramJoined $event): void
    {
        $dispatcher = self::dispatcher();

        if ($dispatcher === null) {
            return;
        }

        $dispatcher->handle(
            $event->affiliate,
            new ProgramJoinedNotification($event->program, $event->membership),
            new CommunicationContextData(
                purpose: 'affiliates.program_joined',
                subjectType: $event->program->getMorphClass(),
                subjectId: (string) $event->program->getKey(),
                idempotencyKey: 'program-joined:' . $event->membership->getKey(),
            ),
        );
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
