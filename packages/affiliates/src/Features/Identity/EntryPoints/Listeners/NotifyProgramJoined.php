<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\EntryPoints\Listeners;

use AIArmada\Affiliates\Events\AffiliateProgramJoined;
use AIArmada\Affiliates\Features\Identity\Actions\DispatchAffiliateNotification;
use AIArmada\Affiliates\Features\Identity\Notifications\ProgramJoinedNotification;
use AIArmada\Affiliates\Features\Programs\Api\ProgramJoinReader;
use AIArmada\Communications\Data\CommunicationContextData;

final readonly class NotifyProgramJoined
{
    public function __construct(
        private ProgramJoinReader $joins,
        private DispatchAffiliateNotification $notifications,
    ) {}

    public function handle(AffiliateProgramJoined $event): void
    {
        if (! $this->notifications->enabled()) {
            return;
        }

        $record = $this->joins->find($event->reference);

        if ($record === null) {
            return;
        }

        $this->notifications->send(
            $record->reference->affiliate,
            new ProgramJoinedNotification($record->programName, $record->approved),
            new CommunicationContextData(
                purpose: 'affiliates.program_joined',
                subjectType: $record->programMorphType,
                subjectId: $record->reference->programId,
                idempotencyKey: 'program-joined:' . $record->reference->membershipId,
            ),
        );
    }
}
