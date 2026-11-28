<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Features\Passes\EntryPoints\Jobs;

use AIArmada\CommerceSupport\Contracts\OwnerScopedJob;
use AIArmada\CommerceSupport\Data\OwnerJobContext;
use AIArmada\CommerceSupport\Traits\OwnerContextJob;
use AIArmada\Ticketing\Events\PassesBulkTransferred;
use AIArmada\Ticketing\Models\PassHolder;
use AIArmada\Ticketing\Notifications\PassTransferredToNewHolderNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Notification;

final class BulkSendTransferNotificationsJob implements OwnerScopedJob, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use OwnerContextJob;
    use Queueable;

    public function __construct(
        public readonly PassesBulkTransferred $event,
        public readonly ?string $ownerType,
        public readonly string | int | null $ownerId,
        public readonly bool $ownerIsGlobal = false,
    ) {}

    public function ownerContext(): OwnerJobContext
    {
        return new OwnerJobContext(
            ownerType: $this->ownerType,
            ownerId: $this->ownerId,
            ownerIsGlobal: $this->ownerIsGlobal,
        );
    }

    protected function performJob(): void
    {
        $previousByPassId = $this->event->previousHolders->keyBy(
            fn ($holder): string => (string) $holder->pass_id
        );

        $currentHolders = PassHolder::query()
            ->whereIn('id', $this->event->newHolders->modelKeys())
            ->where('is_current', true)
            ->get()
            ->keyBy('pass_id');

        foreach ($this->event->passes as $pass) {
            $holder = $currentHolders->get((string) $pass->getKey());

            if ($holder === null || blank($holder->email)) {
                continue;
            }

            $previousHolder = $previousByPassId->get((string) $pass->getKey(), $holder);

            Notification::route('mail', $holder->email)
                ->notify(new PassTransferredToNewHolderNotification($pass, $previousHolder, $this->event->reason));
        }
    }
}
